# Architecture — note de reprise pour développeur

Ce document s'adresse à **quelqu'un qui reprend le code**. Il décrit comment le
système est agencé et pourquoi, avec les pièges qui coûtent une demi-journée
quand on les découvre en production.

Son pendant, [`configuration.md`](configuration.md), s'adresse à un **orga ou un
créateur de scénario** : il décrit les clés de configuration et leurs effets de
jeu, sans supposer la lecture du code. Quand les deux se recoupent, celui-ci
explique le mécanisme, l'autre le réglage.

---

## 1. Forme générale

PHP 8 procédural, sans framework. Une page = un fichier ; chaque page commence
par `require_once '../base/basePHP.php'` qui ouvre la session et fournit `$pdo`,
puis rend son HTML via `base/baseHTML.php`.

| Dossier | Rôle |
|---|---|
| `base/` | amorçage, session, page d'accueil, administration, configuration |
| `BDD/` | `db_connector.php` : connexion, création du schéma, importeur de scénario |
| `mechanics/` | le moteur de fin de tour et ses mécaniques |
| `workers/`, `controllers/`, `zones/`, `ressources/`, `powers/`, `artefacts/` | domaines métier : pages, vues, fonctions |
| `var/` | schémas SQL, données de scénario, journaux, sauvegardes |
| `tests/` | suite Playwright/pytest |

### Le trajet d'une requête

1. L'URL touche un point d'entrée : `*/action.php`, `*/management_*.php`, `base/admin.php`…
2. **`base/basePHP.php`** est requis en premier. Il ouvre un tampon de sortie
   (`ob_start()`, pour que `header()` fonctionne même si un avertissement a déjà
   été émis), démarre la session, charge les neuf bibliothèques de fonctions
   (`version`, `errorLog`, `db_connector`, puis `controllers`, `mechanics`,
   `powers`, `ressources`, `workers`, `zones`), définit `getConfig()` et
   `getMechanics()`, puis appelle `gameReady()` — qui établit le PDO, garantit le
   schéma et recharge un scénario si `$_POST['config_name']` est posté.
3. Une page d'administration place sa **garde `is_privileged`** juste après
   `basePHP.php`, avant tout handler : la garde de `baseHTML.php` (étape 5)
   n'exige que `logged_in` et n'est évaluée qu'après l'exécution des POST.
4. Le point d'entrée lit ses paramètres, applique sa **garde de propriété** — un
   non-privilégié ne peut pas agir pour un autre contrôleur — puis aiguille sur
   l'action demandée.
5. **`base/baseHTML.php`** rend l'en-tête et la barre latérale. Il refuse d'être
   appelé directement (comparaison `realpath`) et redirige vers la connexion si la
   session n'est pas authentifiée, sauf si `$noConnection` est posé — le drapeau
   des pages de login et de logout.
6. Un `register_shutdown_function` émet le pied de page, ce qui ferme le HTML même
   si la page se termine tôt.

Deux bases sont supportées, **MySQL et PostgreSQL**, choisies par
`$_SESSION['DBTYPE']`. Tout ce qui touche au SQL doit fonctionner dans les deux.

---

## 2. Base de données et scénarios

### Création du schéma

`gameReady()` (`BDD/db_connector.php`) teste l'existence d'une table témoin. Si
elle manque, il rejoue `var/{dialecte}/setupBDD.sql`, puis
`var/{dialecte}/minimalData.sql`, puis charge le scénario demandé.

**Conséquence à retenir** : `setupBDD.sql` n'est rejoué que si les tables sont
absentes. Ajouter une colonne ne suffit donc pas — il faut que les tables soient
détruites pour que le nouveau schéma s'applique.

### La destruction, et le piège qui va avec

`destroyAllTables()` procède différemment selon le dialecte :

- **PostgreSQL** interroge `information_schema` et supprime tout ce qui porte le
  préfixe. Robuste.
- **MySQL** parcourt une **liste de tables en dur**, dans un ordre choisi pour
  respecter les clés étrangères, le tout dans un seul `try/catch`.

Une table présente en base mais **absente de cette liste** — typiquement laissée
par une autre branche — garde ses clés étrangères vivantes, le `DROP` lève, et la
boucle s'interrompt **en silence au milieu**. Tout ce qui suit garde son ancien
schéma, `setupBDD.sql` n'est pas rejoué puisque les tables existent, et l'on
croit à un bug de code.

> Toute branche qui ajoute une table doit l'ajouter aux **deux** `setupBDD.sql`
> **et** à la liste de `destroyAllTables`. Diagnostic : compter les lignes
> « dropped successfully » de la réponse du reset contre la longueur de la liste.

### Où vivent les données

| Fichier | Contenu |
|---|---|
| `var/{dialecte}/minimalData.sql` | le **socle** : compte orga, ligne `mechanics`, clés de configuration de base |
| `var/csv/setup{Scenario}_*.csv` | scénarios modernes, importés par en-tête de colonne |
| `var/{dialecte}/setup{Scenario}SQL_*.sql` | anciens scénarios, SQL écrit à la main |

`minimalData.sql` **n'est pas exhaustif** : environ 39 clés lues par le code n'y
figurent pas et viennent des scénarios. Cette répartition n'est pas une règle
écrite — voir l'issue #120.

Deux contraintes qui font échouer un chargement entier :

- `config.name` est `VARCHAR(255) UNIQUE NOT NULL`. Une clé seedée deux fois
  interrompt tout le scénario. Quand un scénario doit surcharger une clé du
  socle, il faut la clause d'upsert maison (`ON DUPLICATE KEY UPDATE` /
  `ON CONFLICT (name) DO UPDATE`).
- L'importeur CSV fait `array_combine($header, $row)` avec un contrôle strict du
  nombre de champs. Une seule ligne trop courte avorte le chargement.

Certains fichiers de scénario SQL sont en **CRLF**. Les éditer avec un outil qui
normalise les fins de ligne produit un diff fantôme de plusieurs centaines de
lignes.

---

## 3. Le moteur de fin de tour

`mechanics/endTurn.php` exécute une suite d'étapes, dans cet ordre :

```
updateRessources → calculateValsReport → attackMechanic
→ recalculateBaseZoneDefence → locationAttackMechanic → claimMechanic
→ ressourceGainAfterClaim → investigateMechanic → locationSearchMechanic
→ createNewTurnLines → restartTurnRecrutementCount
```

Le compteur de tour n'est incrémenté qu'**à la toute fin**. Une exception au
milieu laisse donc la partie à moitié résolue, au tour précédent.

### Le modèle de reprise — un choix, pas un accident

Chaque étape réussie écrit son nom dans `mechanics.end_step`, et le moteur saute
au démarrage tout ce qui est déjà fait. Une sauvegarde automatique est prise en
tête de fin de tour dans `var/backups/`.

Quand une étape échoue, la fin de tour reste dans son état intermédiaire et
**l'orga choisit** : relancer, ce qui reprend à la dernière étape réussie, ou
restaurer la sauvegarde et recommencer.

Ce que relancer implique réellement, pour une mécanique interrompue en son
milieu :

- **Les valeurs des agents ne bougent pas.** Le dé est jeté une seule fois par
  tour, dans le SQL de `calculateVals`, et stocké dans
  `worker_actions.attack_val` / `defence_val` / `enquete_val`. Cette étape étant
  antérieure, elle est sautée. Les mêmes duels se rejouent avec les mêmes
  nombres.
- **Les morts ne se rejouent pas.** Un combattant tué ou capturé a vu son
  `action_choice` réécrit en `dead` / `captured`, et les requêtes de
  regroupement filtrent sur les actions vivantes. Il sort donc du calcul.
- **Les échecs, si.** Un duel perdu sans mort se rejoue à l'identique et
  produira une seconde ligne dans `worker_combat_logs`.

Autrement dit, une reprise duplique des lignes de journal, pas des morts.

---

## 4. Valeurs, combat, rapports

### Les valeurs

`calculateVals` écrit `attack_val`, `defence_val` et `enquete_val` dans
`worker_actions` pour le tour courant. Le tirage vit dans le SQL lui-même, borné
par `MINROLL` et `MAXROLL`. Toute mécanique ultérieure **relit** ces colonnes ;
aucune ne re-tire.

En test, `MINROLL = MAXROLL`, ce qui rend chaque valeur déterministe.

### Le combat entre agents

`resolveWorkerCombat()` (`mechanics/attackMechanic.php`) résout **un duel** et
retourne `{kill, capture, riposte_kill}`. Il écrit les rapports, crée les agents
leurres et réécrit `action_choice` des morts. C'est la brique partagée : le
combat entre agents et l'attaque de lieu passent tous deux par elle.

Sa signature porte deux paramètres de destination :

```php
resolveWorkerCombat($pdo, $defender, $mechanics,
    string $attackerReportKey = 'attack_report',
    string $defenderReportKey = 'life_report')
```

Les défauts reproduisent le combat entre agents. L'attaque de lieu passe
`'location_attack_report'` des deux côtés, pour que ses duels soient classés
dans leur propre section.

### Les rapports

`updateWorkerAction()` (`workers/functions.php`) **concatène** dans un JSON par
clé, et la liste des clés acceptées est une **liste blanche**. Une clé absente de
cette liste est silencieusement ignorée — pas d'erreur, pas de texte.

`workers/view.php` rend chaque clé sous son propre titre. Les sections décident
donc de ce que le joueur croit lire : un duel d'assaut classé en `attack_report`
raconte une attaque d'agent.

---

## 5. L'attaque de lieu

Trois modes, réglés par `locationAttackMode` :

| Mode | Résolution |
|---|---|
| `immediate` | au clic, valeurs agrégées du contrôleur |
| `endTurn` | mise en file au clic, résolue en fin de tour |
| `agent_attack_defence` | par **combat d'agents** en fin de tour |

Le troisième est le plus récent et vit dans `mechanics/locationAttackMechanic.php`.

### L'échelle de duels

Les agents dont l'`action_choice` vaut `attack_location` ou `defend_location`
sont groupés par lieu visé. Les agents doubles dont le maître secret possède la
cible sont écartés avant le combat. Puis attaquants et défenseurs sont appariés
séquentiellement : une mort fait avancer le défenseur, tout le reste dépense
l'attaquant. La boucle s'arrête quand un camp est épuisé, donc au plus
`|A| + |D| - 1` duels.

### Gagner n'est pas prendre

Deux notions distinctes, et c'est la source d'erreur la plus fréquente :

- **`falls`** — le verdict du combat, par comparaison des survivants selon
  `locationOverwhelmMode`.
- **`taken`** — l'assaut a produit son effet : destruction, échange **ou**
  simple pillage.

L'écart entre les deux vient du butin. Les artefacts pris doivent aller quelque
part : le premier lieu **destructible** du réseau vainqueur, trié par
`discovery_diff` décroissant. Un réseau qui n'en possède aucun ne peut rien
emporter, et le lieu reste alors debout **malgré un combat gagné**.

Un lieu pillé garde son propriétaire : `taken` n'est donc pas un changement de
propriétaire.

---

## 6. Points d'entrée d'action

| Fichier | Forme | Actions |
|---|---|---|
| `controllers/action.php` | GET | `createBase`, `moveBase`, `attackLocation`, `cancelLocationAttack`, `repairLocation`, `giftInformationAgent`, `giftInformationLocation` |
| `workers/action.php` | GET | `creation`, `move`, `attack`, `attackLocation`, `defendLocation`, `hide`, `passive`, `investigate`, `claim`, `gift`, `recallDoubleAgent`, `returnPrisoner`, `teach_discipline`, `transform` |
| `workers/massAction.php` | GET | `mass_move`, `mass_investigate`, `mass_passive`, `mass_hide` |
| `ressources/action.php` | POST | don de ressource, en *post-redirect-get* pour qu'un rafraîchissement ne rejoue pas l'envoi |
| `zones/action.php` | — | ne fait qu'inclure la vue |

`controllers/action.php` déclare explicitement sa liste `$MUTATING_ACTIONS` et
n'applique la garde de propriété qu'à celles-ci : une lecture reste possible sur
un contrôleur tiers, ce qui fait vivre les pages de renseignement.

`workers/action.php` ajoute un verrou d'écriture sur les agents morts ou en
trace, avec une exception pour `transform` — la résurrection vampire.

Les actions de masse pré-vérifient **chaque** identifiant de la liste contre le
contrôleur de session avant d'agir sur le premier.

---

## 7. Conventions qui mordent

**PDO.** Les booléens passent par `PDO::PARAM_BOOL` — la forme tableau de
`execute([...])` les lie en chaîne, et PostgreSQL refuse `''` pour un `BOOLEAN`.
`LIMIT` ne se lie jamais. Un placeholder nommé ne se répète jamais.

**Dialectes.** `UPDATE … JOIN … SET` est spécifique à MySQL ; PostgreSQL veut
`UPDATE … SET … FROM …`. Une sous-requête qui lit la table en cours de mise à
jour déclenche l'erreur MySQL 1093 et demande une table dérivée.

**Booléens.** Seize colonnes, identiques dans les deux dialectes :

`can_be_destroyed`, `can_be_repaired`, `can_build_base`, `discovered_powers`,
`found_secret`, `hide_turn_zero`, `hide_when_zero`, `is_base`, `is_hidden`,
`is_primary_controller`, `is_privileged`, `is_rollable`, `is_stored`,
`is_updated_location`, `secret_controller`, `success`.

Le fichier mysql les déclare tantôt `TINYINT(1)`, tantôt `BOOLEAN` — c'est le
même type de son point de vue, mais chercher `TINYINT(1)` seul en manque trois.

En SQL brut, `= True` fonctionne partout ; un littéral `0` ou `1` dans un
`SELECT` sur une colonne `BOOLEAN` échoue sur PostgreSQL. Côté PHP, lier un
booléen autrement qu'en `PDO::PARAM_BOOL` le transmet en chaîne, et PostgreSQL
refuse `''`.

**Textes.** Les pools sont des listes JSON tirées au sort, éditables par un orga.
En PHP 8, `sprintf` **lève** sur un gabarit mal formé et `array_rand` **lève** sur
un tableau vide — dans la fin de tour, cela coupe la résolution. Voir l'issue
#117 ; `pickLocationAgeText()` et `pickLocationAgentText()` sont les deux
prototypes de garde.

**Types PHP.** Unions écrites `int|null`, jamais `?int`.

**Nommage.** `snake_case` pour ce qui vient de la base, `camelCase` pour ce qui
est calculé.

**Commentaires.** Ils décrivent le **comportement**, en une ligne. Le pourquoi
d'une décision va dans le message de commit ou dans un document, pas dans le
code.

---

## 8. Les tests

Playwright piloté par pytest. Les tests observent l'**interface rendue** plutôt
que la base, ce qui leur permet de tourner sous `UI_ONLY=1` contre un
déploiement distant ; ceux qui interrogent la base directement portent
`@pytest.mark.db`.

Les données de test viennent des CSV de scénario, pas de fixtures SQL.

Trois habitudes qui évitent des tests trompeurs :

- **Prouver qu'un test échoue sans le correctif.** L'annuler, le voir rougir,
  restaurer. Un test qui ne fait que décrire le comportement observé ne protège
  rien.
- **Adosser toute négation à une positive** dans le même test. Une page d'agent
  **accumule** ses rapports tour après tour, donc une assertion négative doit
  porter sur quelque chose qui ne pouvait pas exister avant.
- **Une seule suite à la fois.** Le bootstrap MySQL est partagé ; deux exécutions
  concurrentes se corrompent.

La CI découpe la suite en deux shards par `pytest-split`. Sans fichier de durées,
la répartition se fait par comptage, donc **ajouter un test déplace la frontière**
et change ce qui s'exécute avec quoi.

---

## 9. Ce que ce document ne couvre pas

Écrit à partir du moteur de fin de tour et du sous-système d'attaque de lieu, les
parties vérifiées ligne à ligne. Restent à documenter par qui les explorera : le
moteur d'IA (`mechanics/ia/`), les pouvoirs et disciplines, la revendication de
zone, l'économie de ressources, le système de dons, et une carte complète du
schéma table par table.

### Sur `tests/CODE_KNOWLEDGE.md`

Ce document reprend une partie de `tests/CODE_KNOWLEDGE.md`, une note de travail
non suivie par git. Ce qui en a été repris a été **revérifié dans le code**, et
deux écarts ont été corrigés au passage : `workers/action.php` traite quatorze
actions et non douze — `attackLocation` et `defendLocation` sont arrivées avec
l'issue #73 — et les mentions de la branche `88-zone-rules`, fusionnée depuis,
ont été retirées.

Sa liste d'issues n'a **délibérément pas** été reprise : elle était datée du
2026-06-07 et citait comme ouverts des tickets fermés depuis. `gh issue list`
fait autorité, une liste figée dans un document ne le peut pas.

Mieux vaut étendre ce fichier que le dupliquer ailleurs.
