# Architecture — note de reprise pour développeur

Les conventions de code, de test, de branche et de commit vivent dans
[`coding_rules.md`](coding_rules.md).

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
| `base/` | amorçage, session (`session.php`), page d'accueil, pages de configuration du scénario |
| `admin/` | les pages d'administration : tableau de bord, sauvegardes, CSV, journaux, récits de fin de tour |
| `connection/` | connexion, déconnexion et compte du joueur (`account.php` : factions rattachées, changement de mot de passe) — **hors du trajet décrit plus bas** : `loginForm.php` porte son propre amorçage dupliqué, `logout.php` ne rend aucun HTML |
| `BDD/` | `db_connector.php` : connexion, création du schéma, importeur de scénario |
| `mechanics/` | le moteur de fin de tour et ses mécaniques |
| `workers/`, `controllers/`, `zones/`, `ressources/`, `powers/`, `artefacts/` | domaines métier : pages, vues, fonctions |
| `var/` | schémas SQL, données de scénario, journaux, sauvegardes |
| `tests/` | suite Playwright/pytest |

### Le trajet d'une requête

1. L'URL touche un point d'entrée : `*/action.php`, `*/management_*.php`, `admin/admin.php`…
2. **`base/basePHP.php`** est requis en premier. Il ouvre un tampon de sortie
   (`ob_start()`, pour que `header()` fonctionne même si un avertissement a déjà
   été émis), démarre la session par `startGameSession()` (voir plus bas), charge
   les neuf bibliothèques de fonctions
   (`version`, `errorLog`, `db_connector`, puis `controllers`, `mechanics`,
   `powers`, `ressources`, `workers`, `zones`), définit `getConfig()` et
   `getMechanics()`, puis appelle `gameReady()` — qui établit le PDO, garantit le
   schéma et, sur une base neuve, charge un scénario si `$_POST['config_name']` est
   posté. Il lit enfin les mécaniques dans `$mechanics`, **sans garantir qu'elles
   existent** :
   `getMechanics()` rend `null` aussi bien sur une `PDOException` que sur une table
   `mechanics` vide, celle-ci étant créée par `setupBDD.sql` et peuplée par
   `minimalData.sql`. Or `gameReady()` ne joue ces deux fichiers **que si la table
   `players` est absente** (`gameReady`, `BDD/db_connector.php`), et le rechargement de
   scénario est imbriqué dans la même condition : une base dont `players`
   existe mais dont `mechanics` est vide — réinitialisation de test, destruction à
   moitié avortée — n'est donc réparée par aucune page. Arrêter l'amorçage la
   rendrait inatteignable au lieu de la protéger. Les gardes n'ont pourtant pas à le
   vérifier une à une : il leur suffit d'être écrites de sorte que **l'absence de
   donnée refuse**, et un tour illisible se ferme alors de lui-même.
3. Une page d'administration place sa **garde `is_privileged`** juste après
   `basePHP.php`, avant tout handler : la garde de `baseHTML.php` (étape 5)
   n'exige que `logged_in` et n'est évaluée qu'après l'exécution des POST.
4. Le point d'entrée lit ses paramètres, applique sa **garde de propriété** — un
   non-privilégié ne peut pas agir pour un autre contrôleur — puis aiguille sur
   l'action demandée.
5. **`base/baseHTML.php`** rend l'en-tête et la barre latérale. Il refuse d'être
   appelé directement (comparaison `realpath`) et redirige vers la connexion si la
   session n'est pas authentifiée, sauf si `$noConnection` est posé. Un seul
   fichier le pose : `base/systemPresentation.php`, la page de présentation
   publique. Les pages de connexion, elles, n'ont pas besoin du drapeau puisque
   **elles n'utilisent pas `baseHTML.php`**.
6. Un `register_shutdown_function` émet le pied de page, ce qui ferme le HTML même
   si la page se termine tôt.

### La session est cloisonnée par installation

Un cookie de session est lié au **domaine**, pas au chemin. Plusieurs jeux servis sous le
même domaine partageraient donc leur session sous le nom de cookie par défaut, et
`logged_in`, `user_id` et `is_privileged` traverseraient d'une installation à l'autre. Le
point aggravant est que `DBNAME`, `FOLDER` et `GAME_PREFIX` sont **réécrits à chaque
requête** par `getDBConnection` : le `user_id` survivant serait relu contre une **autre
base**, donc réécrit en quelqu'un d'autre.

`base/session.php` pose donc deux gardes, et les quatre points d'entrée qui ouvrent une
session passent tous par lui — `base/basePHP.php`, `index.php`,
`connection/loginForm.php`, `connection/logout.php` :

- le cookie est **nommé d'après le répertoire de l'installation**, donc deux jeux ne
  partagent plus d'identifiant ;
- la session **retient l'installation qui l'a ouverte**, et une session venue d'ailleurs
  est vidée au lieu d'être crue.

La seconde garde tient même si la première est mal déployée — **mesuré** sous
`session.auto_start = 1`, où la session existe avant notre code et le cookie ne peut plus
être renommé : le passage d'un jeu à l'autre reste refusé. Le prix est qu'alors les deux
installations deviennent mutuellement exclusives, chaque visite vidant la session de
l'autre. C'est un arbitrage assumé : `auto_start` est une mauvaise configuration, et une
brèche est pire qu'une déconnexion.

Conséquence pratique du déploiement : **changer ce nom de cookie déconnecte tout le
monde**, une fois.

### Le mode d'environnement

La clé `env` du fichier de contexte vaut `test` ou `production`, et `isTestEnvironment()`
(`base/basePHP.php`) répond à la question. **Tout ce qui n'est pas littéralement `test`
vaut production** : un fichier de contexte écrit avant l'existence de cette clé, ou une
valeur mal orthographiée, doit masquer la donnée plutôt que l'exposer.

Ce que ça change aujourd'hui : `buildWorkerStateAttributes()` (`workers/functions.php`),
appelée par les deux sites de rendu d'une fiche d'agent, **vide** `data-action-choice`,
`data-action-params` et `data-worker-status` hors d'une installation de test. Elle ne
choisit pas quelles valeurs seraient sûres — **rien ne les lit** : le dépôt n'expédie aucun
code client qui y touche, ils n'existent que pour la suite UI-only, qui observe l'état d'un
agent à travers eux faute de base de données.

Ce choix est délibérément **indépendant des données de scénario**. Une règle qui déciderait
du masquage d'après les textes — « une action sans entrée `txt_ps_` est un leurre » —
tomberait le jour où un auteur remplit, ou oublie, une clé de texte. Vider ne peut pas
casser ainsi.

`data-worker-id` et `data-worker-lastname` restent tels quels : tous deux répètent ce que
le lien et la phrase voisins montrent déjà.

**La règle générale** : un attribut `data-*` qui n'existe que pour la suite de tests, sur
une page qu'un joueur peut voir, ne dit rien en production. Les autres restent — les
`data-tab-*` pilotent les onglets (`selectTab`, `base/baseScript.php`), et les pages
d'administration sont hors de portée d'un joueur. `TestNoUnexpectedDataAttributes`
(`tests/test_agent_combat_e2e.py`) tient la liste blanche et fait rougir la suite dès qu'un
attribut non prévu atteint une page joueur.

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

La carte des tables, table par table, est en **§9**.

### Où vivent les données

| Fichier | Contenu |
|---|---|
| `var/{dialecte}/minimalData.sql` | le **socle** : compte orga, ligne `mechanics`, clés de configuration de base |
| `var/csv/setup{Scenario}_*.csv` | scénarios modernes, importés par en-tête de colonne |
| `var/{dialecte}/setup{Scenario}SQL_*.sql` | anciens scénarios, SQL écrit à la main |

`minimalData.sql` **n'est pas exhaustif**. Il sème 166 clés ; le code en lit 119
sous forme littérale, dont **39 n'y figurent pas** et viennent des scénarios.
Cette répartition n'est pas une règle écrite — voir l'issue #120.

Deux de ces clés ne sont semées par **aucun** scénario non plus, dans aucun
dialecte : `DEBUG_IA` et `generation_order`. Elles sont lues et jamais définies,
donc `getConfig` renvoie `null` — ce qui est silencieux, un `(int)null` valant
zéro. Le décompte ignore par ailleurs les clés construites par concaténation de
préfixe (`txt_ps_`, `txt_inf_`), qu'aucune recherche littérale ne peut retrouver.

Deux contraintes qui font échouer un chargement entier :

- `config.name` est `UNIQUE NOT NULL` — `VARCHAR(255)` côté MySQL, `text` côté
  PostgreSQL. Une clé seedée deux fois interrompt tout le scénario. Quand un
  scénario doit surcharger une clé du socle, il faut la clause d'upsert maison
  (`ON DUPLICATE KEY UPDATE` / `ON CONFLICT (name) DO UPDATE`).
- L'importeur CSV compare le nombre de champs à celui de l'en-tête avant
  `array_combine`. Une ligne au mauvais compte **n'avorte pas** le chargement :
  elle est **sautée** (`loadCSVFile`, `BDD/db_connector.php`), avec un avertissement
  affiché à l'écran mais **non journalisé**. C'est plus sournois qu'un arrêt net
  — le scénario se charge, incomplet, et rien n'en garde trace.

Certains fichiers de scénario SQL sont en **CRLF**. Les éditer avec un outil qui
normalise les fins de ligne produit un diff fantôme de plusieurs centaines de
lignes.

---

## 3. Le moteur de fin de tour

`mechanics/endTurn.php` exécute une suite d'étapes, dans cet ordre :

Chaque étape rend un booléen. **Un `false` fait sortir `endTurn` avant
`changeEndTurnState`**, donc `end_step` reste sur l'étape précédente et la fin de tour
**reprend à l'endroit du ratage** quand on la relance — le jeton à usage unique est
régénéré au rendu suivant (`base/baseHTML.php`). C'est pourquoi une requête qui
échoue doit faire échouer son étape plutôt que sauter le travail : les étapes suivantes
lisent le monde que la précédente était censée modifier, et `createNewTurnLines` rend
l'écart définitif.

La règle qui en découle, dans les mécaniques : une **situation de jeu** (une attaque qui
ne nomme personne) vaut un `warning` et un `continue` ; une **`PDOException` sur une
requête entièrement liée** n'est jamais une situation de jeu, et doit remonter.

| État écrit dans `end_step` | Ce que l'étape exécute réellement |
|---|---|
| `updateRessources` | `updateRessources` **puis** `ressourceGainMechanic('before_claim')` |
| `calculateValsReport` | `calculateVals` puis la rédaction des rapports de valeurs |
| `attackMechanic` | `attackMechanic` |
| `recalculateBaseZoneDefence` | `recalculateBaseDefence` **puis** `recalculateZoneDefence` |
| `locationAttackMechanic` | `locationAttackMechanic` |
| `claimMechanic` | `claimMechanic` |
| `ressourceGainAfterClaim` | `ressourceGainMechanic('after_claim')` |
| puis | `investigateMechanic`, `locationSearchMechanic`, `createNewTurnLines`, `restartTurnRecrutementCount` |

**La granularité de reprise est l'état, pas la fonction.** Deux étapes portent
deux appels sous un seul nom : le gain de ressources `before_claim` partage l'état
de `updateRessources`, et les deux recalculs de défense partagent
`recalculateBaseZoneDefence`. Une panne entre les deux appels d'un même état fait
donc **rejouer les deux** à la reprise.

### Comment une fin de tour se déclenche

**Une fin de tour ne se déclenche jamais depuis la page de fin de tour.**

`endTurn.php` n'accepte qu'une requête `POST` portant `end_turn_token`, un jeton
à usage unique comparé par `hash_equals`. Tout le reste — un GET, un POST sans
jeton, un jeton mort ou forgé — reçoit « Fin de tour non déclenchée » et ne mute
rien.

Le jeton est frappé dans `$_SESSION` par `base/baseHTML.php`, au moment où il
rend le bouton de la barre latérale, et seulement s'il n'en existe pas déjà :
toutes les pages ouvertes portent donc le même. `endTurn.php` le brûle, mais
uniquement quand il l'accepte — un GET parasite n'invalide pas le bouton
légitime.

La page de fin de tour ne rend aucun déclencheur : `$pageName === 'End Turn'`
supprime le bloc, qui ne frappe donc aucun jeton. Le meneur de jeu repasse par
une page de jeu pour en obtenir un neuf. Le bouton y annonce « Reprendre la fin
de tour » tant que `end_step` est non vide.

`toggleMechanicsGamestate` n'est pas une garde et ne peut pas en tenir lieu :
quand `gamestate` vaut déjà 1 elle laisse son `UPDATE` vide et renvoie `true`
quand même.

Côté tests, `helpers.end_turn` rejoint une page portant la barre latérale puis
soumet le formulaire ; une navigation directe vers `endTurn.php` est refusée
comme n'importe quel autre GET.

### Le récit d'un tour est archivé

La page de fin de tour **est** le récit du tour : elle énonce chaque étape et ce
qu'elle a produit, dont tout ce que le moteur affiche sans le stocker ailleurs —
gains de ressources, recalculs de défense, verdicts d'assaut, revendications.

`endTurn.php` enregistre donc la page telle qu'elle a été rendue, sous
`var/turn_reports/`, nommée `{préfixe}turn_{tour}_{horodatage}.html`. Le tour
retenu est le compteur **d'avant** l'incrément, c'est-à-dire celui dont la page
raconte la résolution.

L'écriture passe par `register_shutdown_function`, déclarée après l'inclusion de
`baseHTML` : elle s'exécute donc après le pied de page, et l'archive contient la
page entière. C'est aussi ce qui fait qu'une résolution interrompue en cours de
route laisse quand même son récit partiel — le cas où il est le plus utile.

`admin/admin_turn_reports.php` liste ces archives, les ouvre et les supprime, une
par une ou toutes. Le répertoire est créé au premier usage, et `var/.htaccess`
interdit d'y accéder par le web.

Un rechargement de configuration les efface, comme il vide déjà le journal
d'erreurs : elles racontent la partie que la remise à zéro détruit.

`aiMechanic` ne figure pas dans la chaîne : le moteur d'IA n'est pas branché sur
la fin de tour.

Le compteur de tour n'est incrémenté qu'**à la toute fin**, et écrit en base dans la
foulée. Une exception au milieu laisse donc la partie à moitié résolue, au tour
précédent.

### Ce que l'incrément tardif implique pour les dates

Toute mécanique écrit ses dates avec le compteur **d'avant** l'incrément. Une
découverte faite pendant la fin du tour N porte donc `last_discovery_turn = N`,
et personne ne la lit avant le tour N+1 : **le timbre le plus frais qu'un joueur
puisse voir vaut toujours `tour − 1`**, jamais le tour courant.

C'est ce qui rend correcte la règle de fenêtre de `buildEnemyWorkerListing`
(`buildEnemyWorkerListing`, `workers/functions.php`) :

```php
$bucket = $w['last_discovery_turn'] >= ($turn_number - $window) ? 'recent' : 'older';
```

Avec `attackTimeWindow = 1`, une entrée estampillée N est `recent` au tour N+1
et `older` au tour N+2 — **exactement un tour de visibilité**. Le `>=` n'est pas
un off-by-one ; ne pas le « corriger » en `>`.

**Ce qui est exploitable se limite à `recent`.** Une observation périmée ne prouve
plus rien : l'agent a pu déménager, mourir ou être échangé depuis, et tout cela se
résout en fin de tour, hors de la vue du joueur. Les trois consommateurs du listing se
répartissent donc ainsi :

| Consommateur | Groupes utilisés |
|---|---|
| Sélecteur d'attaque (`showEnemyWorkersSelect`) | `recent` seul — et le bloc entier disparaît quand il est vide |
| Liste de don d'information (`controllers/functions.php`) | `recent` seul, « *an agent leaves the gift list when it leaves the attack list* » |
| Boîte de zone (`zones/functions.php`) | **les deux**, sous des titres séparés |

La boîte de zone est l'exception assumée : elle **informe** sans rien offrir à faire,
donc une observation ancienne y garde sa place. Partout où l'on peut **agir**, `older`
n'apparaît pas — sans quoi l'interface proposerait une action que la règle refuse, ou
pire, un bouton surmontant une liste vide.

**Corollaire pour toute action de joueur qui date une ligne en milieu de tour.**
Elle est en avance d'un cran sur les données de fin de tour et doit être reculée
de la fenêtre pour rester comparable. C'est ce que fait le don d'agent, dans
`controllers/action.php` et `controllers/management.php` :

```php
$giftDiscoveryTurn = max(0, (int)$mechanics['turncounter'] - (int)$attackTimeWindow);
```

Normalisation, pas malus : sans elle un don vaudrait deux tours de visibilité,
soit plus qu'une découverte de première main n'en accorde jamais. Le plancher
`max(0, …)` neutralise le recul au tour 0, où il n'aurait aucun sens.

L'effet de recrutement `add_opposition` suit la même règle, dans
`createOppositionWorker` (`workers/functions.php`) : le contrôleur qui dépêche un
agent apprend l'existence du recruté, et le recrutement est lui aussi une action de
milieu de tour. Toute nouvelle écriture dans `controllers_known_enemies` hors de la fin
de tour doit reculer de la même fenêtre.

`addWorkerToCKE` (`controllers/functions.php`) complète par un
`GREATEST(last_discovery_turn, :turn_number)`,
si bien qu'une source périmée ne peut jamais vieillir un acquis plus frais,
tandis que l'enquête, qui passe le tour courant, continue de rafraîchir.

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

### Actions actives et inactives

Deux constantes, en tête de `workers/functions.php`, décident de
tout ce qui suit :

```php
ACTIVE_ACTIONS   = ['passive','investigate','attack','claim','hide','attack_location','defend_location']
INACTIVE_ACTIONS = ['dead','captured','trace']
```

Une mécanique de fin de tour ne ramasse que des agents dont l'`action_choice`
est active, et c'est **par cette liste** qu'un mort sort du jeu : `dead`,
`captured` et `trace` ne sont pas des drapeaux séparés mais des valeurs
d'`action_choice`. Ajouter une action au jeu suppose donc de l'ajouter à
`ACTIVE_ACTIONS`, sans quoi elle est posée en base et ignorée partout.

Le statut affiché s'en déduit, croisé avec `is_primary_controller`
(`getWorkerStatus`, `workers/functions.php`) : actif et à nous vaut `alive`, actif et pas à
nous vaut `double_agent`, inactif vaut `dead` — sauf `captured`, traité à part.

### Ce qu'un geôlier peut faire d'un prisonnier

Un prisonnier est un agent dont l'`action_choice` vaut `captured` et dont
l'unique ligne `controller_worker` — primaire — appartient à son geôlier. La
capture enregistre la faction qu'il servait dans
`worker_actions.action_params.original_controller_id`, et le lien d'agent double
éventuel dans `double_agent_controller_id`. Ce JSON survit à la bascule de tour :
la remise à zéro de `createNewTurnLines` ne frappe que les six actions
continuables, dont `captured` ne fait pas partie.

Trois gestes, dont deux libèrent et un seul ne libère pas :

| Geste | Destination | Résultat |
|---|---|---|
| `returnPrisoner` vers l'origine | `original_controller_id` | libéré, `passive` |
| `returnPrisoner` vers le maître double | `double_agent_controller_id` | libéré, `passive` |
| `transferPrisoner` | toute autre faction | **reste `captured`** chez son nouveau geôlier |

Le transfert n'est pas une variante de `returnPrisoner` : celui-ci se termine
sur `$new_action = 'passive'`, donc il affranchit. `transferPrisoner` conserve
`captured` et **recopie** `original_controller_id`, de sorte que le nouveau
geôlier puisse à son tour relâcher vers l'origine. C'est nécessaire parce
qu'`activateWorker` réécrit `action_params` après le `switch` : un `case` qui ne
renseigne pas `$jsonOutput` efface l'origine de la capture.

Les deux gestes se répartissent les destinations par des règles inverses, l'une
et l'autre gardées en 403 au point d'entrée.

**Une libération ne va que vers une faction que la capture a enregistrée** :
`original_controller_id` ou `double_agent_controller_id`, et rien d'autre. La
liste blanche vaut même quand cette faction est le geôlier du moment — une
faction qui reprend son propre agent le libère légitimement. Le paramètre
`double_controller_id` est vérifié contre celui de la capture, faute de quoi un
geôlier s'installerait maître secret de l'agent qu'il relâche.

La faction qui libère est contrôlée elle aussi : `recall_controller_id` doit être
le contrôleur primaire réel de l'agent, et la session doit agir pour lui. Sans
cela, en nommer une autre laissait l'`UPDATE` de garde sans effet pendant que
l'action se terminait quand même sur `passive` — le geôlier gardait le prisonnier
en agent **actif**, l'origine ne récupérait rien, et `createTraceWorker` déposait
un dossier complet de l'agent chez la faction nommée dans l'URL.

**Un transfert ne va que vers une faction que la capture n'a pas enregistrée** :
l'origine et le maître double appellent une libération. Il exige en outre que
l'agent soit réellement `captured`, que `recall_controller_id` soit son
contrôleur primaire réel, et que la session agisse pour ce contrôleur. Ses
destinations sortent de `getControllers`, la liste que rend déjà le menu : une
faction secrète ou inexistante n'y figure pas.

Un rejeu est refusé en amont par la garde, le geôlier n'étant plus le contrôleur
primaire. En seconde barrière, quand l'`UPDATE` de garde ne touche aucune ligne,
ni trace ni texte ne sont écrits : rien ne peut laisser un agent-leurre en trop.

Les trois gestes exigent que la session agisse pour le geôlier, donc le panneau
d'actions d'un prisonnier ne s'affiche pas dans la vue d'une autre faction.

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
`'location_attack_report'` des deux côtés (`resolveAgentLocationCombat`, `mechanics/locationAttackMechanic.php`),
pour que ses duels soient classés dans leur propre section.

Deux précisions que la signature ne dit pas :

- **La riposte peut tuer l'attaquant.** Si le défenseur survit et que
  `riposte_difference` atteint `RIPOSTDIFF`, l'attaquant meurt et le duel
  retourne `riposte_kill` (`resolveWorkerCombat`, `mechanics/attackMechanic.php`). Attaquer
  n'est donc jamais sans risque, et `RIPOSTACTIVE = 0` désactive le mécanisme
  entièrement. On ne peut ni fuir ni riposter dans le même duel.
- **Les agents leurres ne naissent que d'une capture**, pas d'une simple mort
  (`resolveWorkerCombat`, selon qu'il est agent double ou non).

### L'attaque qui n'atteint personne

Une attaque peut se résoudre sans le moindre duel, et c'est une **situation de jeu
normale**, pas une donnée corrompue. Deux chemins y mènent.

**L'attaquant a pressé « Attaquer » sans cocher aucune cible.** Un `<select multiple>`
sans sélection n'envoie aucune clé : l'action était alors enregistrée avec une liste de
cibles vide, et la comparaison construisait `WHERE w.id IN ()`, que MySQL refuse.
`workers/action.php` refuse cette soumission et laisse l'agent en `passive`, de sorte
que l'état incohérent n'est pas écrit.
`getAttackerComparisons` porte en plus sa propre garde sur la liste vide
(`getAttackerComparisons`, `mechanics/attackMechanic.php`) : la fonction qui construit le `IN (...)` est aussi
celle qui doit refuser de le construire vide.

Une soumission vide n'est pas une soumission forgée, et les deux ne reçoivent pas la
même réponse. Le formulaire ne produit qu'un tableau de `worker_N` / `network_N` : un
scalaire ou un élément qui ne suit pas cette forme ne peut venir que d'une requête
fabriquée. **C'est le point d'entrée qui juge la requête** : `workers/action.php` vérifie
la forme de la charge avant d'appeler quoi que ce soit et répond **400**, laissant
intacte l'action déjà en file. `activateWorker` garde la même vérification en garde
défensive — elle journalise et sort sans écrire — mais elle n'arbitre plus : elle rend
`void`, parce qu'une valeur de retour que personne ne lit finit par mentir.

Le déplacement se garde de même, et plus strictement : son sélecteur ne soumet qu'un
entier positif, donc tout le reste est forgé et vaut 400. La vérification porte sur la
**forme** de la valeur reçue, et non sur un `(int)`, parce qu'un transtypage rend `1`
sans un mot sur un tableau non vide — `zone_id[]=2` déplacerait l'agent vers la zone 1
au lieu d'être refusé.

**L'attaquant a visé un agent nommé déjà `dead`, `captured` ou réduit à une `trace`.**
Le bloc `defenders` filtre sur `ACTIVE_ACTIONS`, y compris sous
`LIMIT_ATTACK_BY_ZONE`, la paire n'atteint donc jamais la boucle de résolution — et
**rien n'est écrit dans le rapport de l'attaquant**. La liste des cibles, elle, ne filtre
pas sur le statut (`getEnemyWorkers`, `workers/functions.php`) : un cadavre, un agent
capturé ou un **agent leurre** restent proposés à l'attaque.

À ne pas confondre avec la cible qui meurt **pendant** la résolution, tuée par un duel
antérieur de la même boucle : celle-là vaut à l'attaquant un texte tiré
d'`unfoundAttackTextes` (`attackMechanic`, `mechanics/attackMechanic.php`).

**Une attaque de réseau ne voit que les découvertes affiliées.** La requête filtre sur
`discovered_controller_id`, donc un agent connu comme présent mais **pas encore rattaché
à une faction** est invisible pour une attaque de réseau et n'est atteignable que
nommément. Rien dans l'interface ne le dit au joueur, qui voit pourtant l'agent dans sa
liste.

**Un don ne soustrait pas l'agent à une attaque de réseau déjà ordonnée.** Le don réécrit
`controller_worker` et `worker_actions`, jamais `controllers_known_enemies` : l'agent
reste classé sous son ancien réseau et encaisse le coup, que l'attaquant porte donc à une
faction qu'il n'a pas visée. Comportement figé par
`tests/test_attack_target_gifted_e2e.py` ; l'arbitrage est ouvert dans l'issue #168.

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

Les deux camps sont triés par `enquete_val` **décroissant**. Le sens du tri est
la convention du moteur, pas une règle propre à cette mécanique :
`getAttackerComparisons` trie déjà les attaquants et les défenseurs par
`enquete_val DESC`. Le départage des égalités par `worker_id` croissant, lui, est
**propre à l'attaque de lieu** (`getLocationActionsByLocation`, `locationAttackMechanic.php`) ; le combat
entre agents ne le porte pas.

L'énoncé de l'issue #73 annonce l'ordre inverse : c'est lui qui diverge du
code, et le code qui fait foi.

Aucun code de vivacité n'est nécessaire dans l'échelle : le filtre sur
`action_choice` exclut déjà les morts à l'entrée, puisque `resolveWorkerCombat`
remplace l'`action_choice` par `dead` ou `captured`, et pendant l'échelle la
valeur de retour du duel fait autorité.

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
| `workers/action.php` | GET | `creation`, `move`, `attack`, `attackLocation`, `defendLocation`, `hide`, `passive`, `investigate`, `claim`, `gift`, `recallDoubleAgent`, `returnPrisoner`, `transferPrisoner`, `teach_discipline`, `transform` |
| `workers/massAction.php` | GET | `mass_move`, `mass_investigate`, `mass_passive`, `mass_hide`, `mass_claim` |
| `ressources/action.php` | POST | don de ressource, en *post-redirect-get* pour qu'un rafraîchissement ne rejoue pas l'envoi |
| `zones/action.php` | — | ne fait qu'inclure la vue |

`controllers/action.php` déclare explicitement sa liste `$MUTATING_ACTIONS` et
n'applique la garde de propriété qu'à celles-ci : une lecture reste possible sur
un contrôleur tiers, ce qui fait vivre les pages de renseignement.

`workers/action.php` ajoute un verrou d'écriture sur les agents dont l'action du tour
est dans `INACTIVE_ACTIONS`, avec une exception pour `transform` sur un mort — la
résurrection vampire.

Le verrou n'autorise que sur une **lecture positive** : il faut que la ligne du tour
existe et porte autre chose qu'une action inactive. Écrit à l'envers — refuser sur
`trace` ou `dead` — il laissait passer dès qu'aucune ligne n'était trouvée, ce que
produit un tour illisible ; et `activateWorker`, qui relit les mécaniques pour son
propre compte, aurait alors écrit sur le bon tour. Le `catch` autour de la lecture
refuse de même, là où une `PDOException` rendait une page blanche.

Les actions de masse pré-vérifient **chaque** identifiant de la liste contre le
contrôleur de session avant d'agir sur le premier, et portent le **même** verrou
d'inactivité que `workers/action.php`, écrit dans la même forme positive : la requête
compte les agents dont l'action du tour n'est **pas** dans `INACTIVE_ACTIONS`, et il en
faut autant que d'identifiants soumis. Un prisonnier manque à l'appel, donc le compte
ne tombe pas juste et un geôlier ne remet pas son prisonnier au travail — tout comme un
tour illisible, qui ne compte rien. Elles traitent en revanche différemment le mode de
revendication non autorisé : là où `workers/action.php` répond 403, l'action de masse
l'**ignore en silence**, puisque le bouton n'est alors pas rendu et qu'un formulaire
périmé ne mérite pas une page d'erreur.

Les gardes se replient **fermées** : une panne SQL vaut un 403, car refuser coûte moins
cher que faire agir un prisonnier.

Le repli sur tour illisible n'est **pas observable depuis l'interface** : il faudrait
faire échouer `getMechanics()` sans faire échouer la requête suivante. Aucun test ne le
couvre, et aucun ne prétend le faire.

Deux actions de masse portent un paramètre : `mass_move` la zone de destination, et
`mass_claim` la bannière au nom de laquelle on revendique. Chaque agent sélectionné
revendique **la zone où il se trouve**, donc une sélection répartie sur plusieurs zones
en revendique plusieurs.

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

Seize **noms** pour dix-sept occurrences : `success` existe sur deux tables
(`controller_location_attacks` et `location_attack_logs`).

Le fichier mysql les déclare tantôt `TINYINT(1)`, tantôt `BOOLEAN` — c'est le
même type de son point de vue, mais chercher `TINYINT(1)` seul en manque trois
(`is_rollable`, `is_stored`, `hide_when_zero`).

En SQL brut, `= True` fonctionne partout ; un littéral `0` ou `1` dans un
`SELECT` sur une colonne `BOOLEAN` échoue sur PostgreSQL. Le dépôt en porte
encore un cas non branché par dialecte : `artefacts/management.php`. Côté PHP, lier un
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

### Pièges récurrents

1. **La mise à jour de `addWorkerToCKE` est monotone** — la clause `UPDATE` ne touche
   `discovered_controller_id`, `discovered_controller_name` et `discovered_powers` que si
   la valeur passée est non nulle (`addWorkerToCKE`, `controllers/functions.php`, `SET` conditionnel
   construit par `sprintf`). Un appel à 5 arguments (valeurs par défaut `false`/`null`),
   comme le font `attackMechanic` ou `claimMechanic`, ne rétrograde donc jamais un drapeau
   posé par une investigation antérieure. Toute nouvelle colonne CKE doit reprendre ce même
   motif de `SET` conditionnel, sous peine de régression silencieuse.

2. **Lire l'entrée CKE/CKL avant d'appeler l'upsert, jamais après** — `addWorkerToCKE`
   et `addLocationToCKL` (`controllers/functions.php`) écrasent `zone_id` (ou l'état courant) à chaque
   appel. Détecter un déplacement suppose donc de lire la ligne existante via
   `getCKEEntry` ou `getCKLEntry` (`controllers/functions.php`) avant l'upsert — inverser l'ordre efface
   silencieusement l'information « précédemment ici ».

3. **`ORDER BY` ne peut pas être bindé par PDO** — la direction doit être interpolée
   littéralement dans le SQL. Le seul point d'entrée sûr est un getter à whitelist stricte,
   comme `getInvestigateOrder` (`mechanics/functions.php`, restreint à
   `'asc'|'desc'`) ou `validateActionChoiceListForSql`
   (`validateActionChoiceListForSql`, `mechanics/functions.php`, restreint à une liste d'actions autorisées). Accepter
   une valeur de config brute à cet endroit ouvre une injection SQL.

4. **`toggleDescription()` peut fermer une boîte déjà ouverte** — l'ouverture forcée
   depuis JS doit positionner `style.display = 'block'` directement plutôt que d'appeler
   `toggleDescription(id)`, qui bascule l'état et peut donc refermer un panneau déjà
   visible (`toggleDescription`, `base/baseScript.php`).

5. **Le seed CKL d'une base n'est centralisé nulle part** — `createBase()`
   (`createBase`, `controllers/functions.php`, appel à `addLocationToCKL`) et la resynthèse
   post-chargement de `gameReady()` (`BDD/db_connector.php:~1100-1133`, `INSERT ... SELECT`
   direct sur `controller_known_locations`) portent chacun leur propre logique pour
   garantir qu'un propriétaire connaît sa propre base, toutes deux conditionnées par
   `owner_knows_own_base_secret`. Un nouveau chemin de création de base (import, éditeur
   admin, etc.) qui n'appelle ni l'un ni l'autre laisse la base invisible pour son
   propriétaire dans tous les panneaux filtrés par CKL.

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

### Attendre une page

`wait_for_load_state("networkidle")` est **proscrit** : il attend 500 ms de
silence réseau, état qu'une page chargée n'atteint pas toujours dans le délai par
défaut, et son coût croît avec la charge de la machine. Un fichier de test en
comptait trente-trois ; leur suppression a fait passer la suite complète de
40 min 47 s à 26 min 28 s et le premier shard de CI de 14 min 47 s à 8 min 23 s.

Trois formes correctes, par ordre de préférence : `safe_goto`, qui attend déjà
`load` ; `_wait_loaded(page, sélecteur)`, qui attend l'élément dont le test a
réellement besoin ; ou une attente explicite sur ce même élément.

Le rechargement de scénario ne se mesure pas au chronomètre. `gameReady` émet
`END <br />` en fin de chargement (`gameReady`, `BDD/db_connector.php`), et ce
marqueur est absent d'une page ordinaire : c'est lui qu'il faut attendre. Un
reset réel prend une douzaine de secondes côté serveur, en une seule réponse
synchrone, et le clic de confirmation attend déjà cette navigation.

### Le garde-fou d'erreurs

`conftest.py` compte les lignes `[ERROR]` de `admin/admin_logs.php` avant et après
chaque test et fait échouer celui qui en ajoute. Deux propriétés à préserver :
le verdict d'indisponibilité **ne doit jamais être mémorisé** pour la session —
un hoquet au démarrage désactiverait la détection pour tous les tests suivants —
et son indisponibilité doit **se signaler** en fin de session, sans quoi la suite
verdit sans plus rien surveiller.

Un test qui provoque volontairement une erreur porte
`@pytest.mark.expects_errors`.

### Le découpage CI

La CI découpe la suite en deux shards par `pytest-split`. Sans fichier de durées,
la répartition se fait par comptage, donc **ajouter un test déplace la frontière**
et change ce qui s'exécute avec quoi.

Le dépôt ne versionne **volontairement pas** `.test_durations`. Deux raisons :
`--store-durations` **fusionne** avec le fichier existant au lieu de le
remplacer, si bien qu'il conserve indéfiniment les entrées de fichiers supprimés
— une régénération honnête exige de le supprimer d'abord ; et le déséquilibre
qu'il devait corriger était lui-même un effet de `networkidle`. Une fois celui-ci
éliminé, les deux shards sont tombés à sept secondes d'écart sans aucun fichier
de durées.

---

## 9. Carte du schéma

`var/mysql/setupBDD.sql` compte **29** `CREATE TABLE`. `var/postgres/setupBDD.sql`
en compte également 29, avec les mêmes 29 noms de table — aucun des deux
dialectes n'a de table que l'autre ignore. Les cinq groupes ci-dessous totalisent
4 + 5 + 7 + 8 + 5 = **29** : chaque table du schéma y apparaît une fois et une
seule.

### Identité

| Table | Contenu | FK clés |
|---|---|---|
| `players` | Comptes de connexion (username, passwd, is_privileged) | — |
| `factions` | Factions, y compris les fausses factions d'apparence | — |
| `controllers` | Meneurs de faction (firstname/lastname, story, ia_type, origin_zone_id) | `faction_id` → factions, `fake_faction_id` → factions |
| `player_controller` | Table de liaison joueurs ↔ meneurs (M:N) | `controller_id` → controllers, `player_id` → players |

`controllers.ia_type` est une colonne texte libre (ex. `passive`, `searching`,
`aggressive`, `violent`) ; il n'existe **pas** de colonne `is_ia` sur cette
table — aucun fichier `.sql` ni `.php` du dépôt n'en définit ou n'en lit une.

### Pouvoirs

| Table | Contenu | FK clés |
|---|---|---|
| `power_types` | Catégories fixes : `Hobby`, `Metier`, `Discipline`, `Transformation` (seedées par `minimalData.sql`, ids 1-4) | — |
| `powers` | Pouvoirs individuels (enquete/attack/defence, `other` JSON) | — |
| `link_power_type` | Jonction pouvoir × catégorie | `power_type_id` → power_types, `power_id` → powers |
| `faction_powers` | Pouvoirs accessibles à une faction | `faction_id` → factions, `link_power_type_id` → link_power_type |
| `worker_powers` | Pouvoirs qu'un agent a appris | `worker_id` → workers, `link_power_type_id` → link_power_type |

### Agents

| Table | Contenu | FK clés |
|---|---|---|
| `worker_origins` | Types d'origine des agents | — |
| `worker_names` | Réservoir de prénoms/noms par origine | `origin_id` → worker_origins |
| `workers` | Identité d'un agent (firstname/lastname, origin, zone) ; **aucune colonne de valeur de jeu** | `origin_id` → worker_origins, `zone_id` → zones |
| `controller_worker` | Jonction meneur ↔ agent (M:N) | `controller_id` → controllers, `worker_id` → workers |
| `worker_actions` | État par tour d'un agent (action_choice, action_params, report, enquete_val/attack_val/defence_val) | `worker_id` → workers, `zone_id` → zones, `controller_id` → controllers |
| `workers_trace_links` | Agent-leurre → agent primaire | `primary_worker_id`, `trace_worker_id` → workers, `controller_id` → controllers |
| `worker_combat_logs` | Journal d'un duel agent-agent (une ligne par paire attaquant/défenseur) | `attacker_id`, `defender_id` → workers, `attacker_controller_id`, `defender_controller_id` → controllers, `zone_id` → zones |

### Zones / Lieux / Renseignement

| Table | Contenu | FK clés |
|---|---|---|
| `zones` | Zones de la carte (claimer/holder, defence_val, adjacent_zones, zone_rules) | `claimer_controller_id`, `holder_controller_id` → controllers |
| `locations` | Lieux dans une zone (is_base, activate_json, location_types) | `zone_id` → zones, `controller_id` → controllers |
| `artefacts` | Objets rattachés à un lieu | `location_id` → locations |
| `controller_known_locations` | Découverte lieu par meneur (found_secret, first/last_discovery_turn) | `controller_id` → controllers, `location_id` → locations |
| `controllers_known_enemies` | Agents ennemis détectés par zone (connaissance progressive) | `controller_id`, `discovered_controller_id` → controllers, `discovered_worker_id` → workers, `zone_id` → zones |
| `controller_location_attacks` | File d'attente + résolution des attaques de lieu (mode `endTurn`) | `location_id` → locations, `attacker_controller_id` → controllers |
| `location_attack_logs` | Journal d'audit des attaques de lieu | `target_controller_id`, `attacker_id` → controllers |
| `information_gift_logs` | Journal des dons d'information (agent ou lieu) | `giver_controller_id`, `recipient_controller_id` → controllers |

### Runtime / Configuration / Ressources

| Table | Contenu | FK clés |
|---|---|---|
| `mechanics` | Ligne singleton : turncounter, gamestate, end_step | — |
| `config` | Clé/valeur de configuration (`name` UNIQUE) | — |
| `ressources_config` | Définition des ressources (coûts, gain_rules JSON) | — |
| `controller_ressources` | Inventaire par meneur | `controller_id` → controllers, `ressource_id` → ressources_config |
| `ressource_gift_logs` | Journal des dons de ressources | `giver_controller_id`, `recipient_controller_id` → controllers, `ressource_id` → ressources_config |

---

### Tables de jonction : les colonnes réelles

Plusieurs de ces tables ne portent pas les noms de colonne qu'on devine.

| Jonction | Relie | Colonnes réelles |
|---|---|---|
| `link_power_type` | powers × power_types | `power_type_id`, `power_id` |
| `worker_powers` | workers × pouvoirs | `worker_id`, **`link_power_type_id`** — pas `power_id` |
| `faction_powers` | factions × pouvoirs | `faction_id`, **`link_power_type_id`** — pas `power_id` |
| `controller_worker` | controllers × workers | `controller_id`, `worker_id`, `is_primary_controller` |
| `player_controller` | players × controllers | `controller_id`, `player_id`, **clé primaire composite, aucune colonne `id`** |
| `controller_ressources` | controllers × ressources_config | `controller_id`, `ressource_id` |
| `workers_trace_links` | agent-leurre → agent primaire | `primary_worker_id`, `trace_worker_id`, `controller_id` |

**`faction_powers` confirmé** : ses colonnes sont bien `faction_id` et
`link_power_type_id` ; il n'existe pas de colonne `power_id` sur cette table
(ni sur `worker_powers`, structurée à l'identique). Pour remonter d'une ligne
`faction_powers` jusqu'à un `powers.id`, il faut passer par
`link_power_type.power_id` — un `JOIN` de plus qu'une lecture rapide du nom de
la table ne le suggère.

**`powers.id` et `link_power_type.id` ne sont jamais interchangeables.** Ils
coïncident souvent, quand chaque pouvoir reçoit un seul lien inséré juste après
lui, ce qui masque l'erreur. Une requête qui alimente un formulaire d'agent
expose donc l'identifiant du lien sous l'alias `link_power_type_id`, comme
`getPowersByType` et `randomPowersByType`. Le scénario de test les décale exprès :
`setupTestConfig_jobs.csv` ouvre sur un pouvoir sans type.

### Colonnes à sens caché

**`workers` ne porte aucune colonne `*_val`.** Les valeurs de jeu
(`enquete_val`, `attack_val`, `defence_val`) vivent uniquement sur
`worker_actions`, une ligne par `(worker_id, turn_number)`, recalculée à chaque
tour par `calculateVals()` (`mechanics/functions.php`) à partir d'une somme en
direct sur `worker_powers` → `link_power_type` → `powers`, plus le tirage de dé
et les bonus de zone/config (détail du calcul en §4 du document principal).
Chercher une valeur de combat sur `workers` ne renvoie donc rien à corriger :
la colonne n'y a jamais existé.

`worker_actions.action_choice` est le verbe choisi pour ce tour (`passive`,
`attack`, `attack_location`, `defend_location`, `claim`, `investigate`, `dead`,
`captured`…) — une whitelist appliquée côté PHP, pas une contrainte SQL.
`action_params` (JSON) porte la charge utile propre à ce verbe (ex.
`claim_controller_id`, `location_id`), relue par les mécaniques de fin de tour
et par `investigateMechanic` pour formuler ses rapports.

`worker_actions` porte `UNIQUE (worker_id, turn_number)` : une seule ligne par
agent et par tour. C'est cette contrainte qui permet à `updateWorkerAction()`
de concaténer sans ambiguïté les rapports du tour courant, et qui impose aux
mécaniques de fin de tour de filtrer explicitement sur `turn_number`, jamais de
lire « la dernière ligne » de l'agent.

`locations.setup_turn` est le tour de création **ou du dernier changement
d'état** du lieu (pas seulement la création) ; `is_updated_location` bascule à
vrai une fois que le lieu a changé d'état au moins une fois. Le couple sert à
`locationSearchMechanic` à calculer un âge (`turnDiff`) et à choisir entre un
texte « jamais changé », « en ruines » ou « restauré ».

`locations.activate_json` (JSON) porte la clé `update_location` consommée par
`updateLocation()` (`zones/functions.php`) et par `locationAttackMechanic` :
elle décrit la transition à appliquer quand un lieu « s'active » (destruction,
réparation), et peut inclure l'ancien contenu du lieu replié dedans pour
historique.

`zones.adjacent_zones` et `zones.zone_rules` portent deux formats différents
pour deux notions voisines : `adjacent_zones` est un TEXT en liste d'ids
séparés par des virgules (topologie de la carte), `zone_rules` est un JSON de
règles `value_delta` par type, consommé par `applyZoneRules` en fin de
`calculateControllerValue`. Les deux se ressemblent par le nom mais n'ont ni le
même type SQL ni le même rôle.

`controllers_known_enemies.last_discovery_turn` est **monotone** :
`addWorkerToCKE()` ne l'avance que vers l'avant — un appelant qui passerait un
tour plus ancien que celui déjà stocké laisse la colonne inchangée, si bien
qu'une source obsolète ne peut pas rajeunir un renseignement déjà connu.

`controller_known_locations.found_secret` conditionne si la description cachée
d'un lieu (`locations.hidden_description`) est montrée à ce meneur précis ;
c'est la colonne au cœur de la logique « le propriétaire connaît le secret de
sa propre base », que la clé `owner_knows_own_base_secret` gouverne.

`mechanics.turncounter` et `mechanics.end_step` : ligne singleton, déjà
détaillée au §3 du document principal — `end_step` est le marqueur textuel qui
permet à `endTurn.php` de reprendre sa machine à états après une panne en cours
de résolution. Ce n'est **pas** un rechargement de page : celui-ci est refusé,
voir §3.

### Le piège booléen inter-dialecte

17 colonnes portent une valeur booléenne dans le schéma. MySQL en déclare 14
en `TINYINT(1)` et 3 avec le mot-clé littéral `BOOLEAN` (`is_rollable`,
`is_stored`, `hide_when_zero` — un simple alias de `TINYINT(1)` côté MySQL,
sans effet sur le stockage) ; PostgreSQL déclare les 17 mêmes colonnes en
`BOOLEAN`. Les deux fichiers **s'accordent exactement** sur la liste : aucune
colonne booléenne d'un côté n'est un autre type de l'autre côté.

Colonnes concernées : `players.is_privileged`, `controllers.can_build_base`,
`controllers.secret_controller`, `zones.hide_turn_zero`, `zones.is_hidden`,
`locations.is_updated_location`, `locations.can_be_destroyed`,
`locations.can_be_repaired`, `locations.is_base`,
`controller_known_locations.found_secret`,
`controller_location_attacks.success`, `location_attack_logs.success`,
`controller_worker.is_primary_controller`,
`controllers_known_enemies.discovered_powers`, `ressources_config.is_rollable`,
`ressources_config.is_stored`, `ressources_config.hide_when_zero`.

Les règles de liaison PDO (pourquoi lire/écrire ces colonnes demande
`PDO::PARAM_BOOL` plutôt qu'un littéral `0`/`1`) sont déjà couvertes au §7 du
document principal ; ce qui précède ne porte que sur le schéma lui-même.

### `destroyAllTables` : la liste MySQL face au schéma réel

Le §2 du document principal décrit le risque : une table absente de la liste
en dur de `destroyAllTables()` (branche MySQL) interrompt le `DROP` en
silence. Vérification faite contre l'état actuel du schéma :

- `var/mysql/setupBDD.sql` définit **29** tables.
- La liste en dur dans `BDD/db_connector.php` (branche `mysql` de
  `destroyAllTables()`) contient **29** entrées.
- Comparaison ensembliste des deux listes : **aucune** table du schéma
  n'est absente de la liste, et **aucune** entrée de la liste ne correspond à
  une table qui n'existe plus dans le schéma. Concordance exacte, 29/29.

Le risque documenté au §2 reste donc structurel — la liste doit être tenue à
jour manuellement à chaque table ajoutée ou renommée — mais n'est **pas**
matérialisé aujourd'hui : les deux fichiers sont synchronisés au moment de la
rédaction de cette carte.

---

## 10. Les autres mécaniques

### La revendication de zone

`mechanics/claimMechanic.php` lit `claimMode` (`claimMechanic`) et ne reconnaît que deux
valeurs, vérifiées par un `in_array(..., true)` strict : `worker` et
`worker_leader`. Toute autre valeur — y compris un hypothétique `controller` —
**n'est pas refusée par erreur mais absente de la liste** : la fonction affiche
un message et sort sans toucher aux zones (`claimMechanic`). Il n'existe nulle part
dans le code un mode « par contrôleur » : si le besoin apparaît un jour, c'est
une troisième branche à écrire, pas un bug à corriger.

Les deux modes appellent chacun leur propre moteur de résolution, dispatché en
`claimMechanic` :

| Mode | Fonction | Granularité |
|---|---|---|
| `worker` | `claimByWorkerMath` | une résolution par **agent** ayant choisi `claim` |
| `worker_leader` | `claimByWorkerLeaderMath` | une résolution par **groupe** (contrôleur × zone), portée par un agent meneur |

**Mode `worker`.** La requête ordonne les revendicateurs par
`z.id, wa.attack_val DESC` (`claimByWorkerMath`) — **sans clé tertiaire** : à `attack_val`
égal entre deux agents de la même zone, l'ordre de retour dépend du moteur SQL
et n'est pas garanti stable. Pour chaque zone, le premier de cet ordre dont
`discrete_claim` (`enquete_val - defence`) ou `violent_claim`
(`attack_val - defence`) franchit respectivement `DISCRETECLAIMDIFF` ou
`VIOLENTCLAIMDIFF` gagne la zone ; les revendicateurs suivants sur la même zone
produisent quand même une résolution (rapport d'échec, fuite CKE) mais avec
`success = false` (`claimByWorkerMath`).

Un cas particulier, silencieux : si un agent est **seul** à revendiquer une
zone encore non tenue ce tour (`$isFirstAndOnlyForUnclaimedZone`, `mechanics/claimMechanic.php`) et
que seul `discrete_claim` franchit son seuil, la réussite est discrète
(`isViolent = false`) et `fire_observer_reports` passe à `false` : aucun témoin
actif dans la zone ne reçoit de rapport ni de fuite `addWorkerToCKE`
(`claimMechanic`). Toute autre réussite (seuil violent franchi, ou plusieurs
revendicateurs simultanés même si l'un d'eux passait le seuil discret) est
bruyante.

**Mode `worker_leader`.** Les agents en `claim` sont groupés par
`(controller_id, zone_id)` (`claimByWorkerLeaderMath`) ; le meneur du groupe est celui dont le
vecteur `[attack_val, defence_val, enquete_val, -worker_id]` est le plus grand
— en cas d'égalité stricte sur les trois stats, c'est le plus petit
`worker_id` qui l'emporte. Les groupes sont ensuite triés par
`[attack_val, defence_val, enquete_val, controller_id ASC]` (`claimByWorkerLeaderMath`) : ce
tri, contrairement à celui du mode `worker`, a une clé terminale déterministe
(`controller_id`). **Un groupe dont le contrôleur tient déjà la zone est
purement et simplement ignoré** (`claimByWorkerLeaderMath`) — le commentaire renvoie au terme
de soutien déjà appliqué par `recalculateZoneDefence` : un tenant ne
« revendique » pas sa propre zone par ce chemin. Le seuil est
`calculateControllerValue('Claim', ...) - calculated_defence_val >= claimDiff`
(`claimByWorkerLeaderMath`) ; le premier groupe de la liste triée à le franchir gagne la zone
pour ce tour, les groupes suivants qui passeraient aussi le seuil produisent
quand même leurs rapports d'échec.

**`claimer_controller_id` vs `holder_controller_id`.** Sur un succès, l'écriture
(`claimMechanic`) fixe toujours `holder_controller_id` au contrôleur qui a
effectivement gagné la zone (`$r['cid']`) — c'est la donnée de contrôle réel.
`claimer_controller_id`, en revanche, est résolu par
`_claimResolveClaimerControllerIdForWrite` : par défaut le même
contrôleur, mais l'agent meneur peut le déporter via
`action_params.claim_controller_id` — c'est la donnée d'**affichage**, celle
que montrent les rapports et les vues « qui tient quoi visiblement ».

Le sentinel `'null'` (chaîne, pas booléen PHP) sur
`action_params.claim_controller_id` signifie « revendication invisible » :
`_claimResolveClaimerControllerIdForWrite` écrit alors `NULL` SQL dans
`claimer_controller_id` (le vrai tenant reste inchangé dans
`holder_controller_id`), et `_claimResolveOnBehalfName` rend ce cas
sous la forme « Personne (Sans bannière) » partout où `%4$s` apparaît dans les
gabarits de rapport. Ne rien mettre dans `claim_controller_id` revendique pour
soi ; y mettre `'null'` revendique sous bannière anonyme ; y mettre un id
revendique au nom d'un tiers.

**L'agent double revendique pour son primaire, mais compte pour les deux.**
Un agent double porte une ligne `controller_worker` par contrôleur : primaire
chez sa faction d'origine, secondaire chez celle qui l'a recruté. Deux
requêtes exploitent cette table différemment, et **l'écart est voulu** :

| Requête | Filtre | Effet |
|---|---|---|
| groupement des revendicateurs (`claimByWorkerLeaderMath`, `claimMechanic.php`) | `is_primary_controller = 1` | la revendication est attribuée au **primaire** |
| comptage des soutiens (`calculateControllerValue`, `zones/functions.php`) | aucun | l'agent est compté **aussi** chez le recruteur |

Un agent double qui revendique le fait donc pour sa faction d'origine. Chez
son recruteur il ne crée aucun groupe, mais il grossit le compte des soutiens
— lequel vaut `max(0, count - 1)`. Le recruteur n'en tire donc rien tant qu'il
n'a pas **un agent à lui** qui revendique la même zone ; dès lors, l'agent
double lui apporte un soutien de plus.

Ajouter `is_primary_controller` au comptage des soutiens « par cohérence »
supprimerait cette règle : ce n'est pas une omission.

Les listes de témoins écartent de leur côté les revendicateurs eux-mêmes
(`getLocationsArray` et `calculateControllerValue`), et le rapport d'observateur n'est écrit qu'une fois
par agent (`getZonesArray`) — sans quoi un agent double, présent deux fois dans la
liste, lirait deux fois la même phrase sur sa fiche unique.

**`applyZoneRules`** (`zones/functions.php`) s'exécute sans condition à la
toute fin de `calculateControllerValue`, pour les cinq types qui existent
(`Claim`, `ZoneDefence`, `Attack`, `Defence`, `DiscoveryDiff` —
`calculateControllerValue`, `zones/functions.php`) : ce qui décide si une règle s'applique n'est pas le
type en lui-même mais la présence d'une clé du même nom dans
`zones.zone_rules`. Deux formes de règle, mutuellement exclusives
(`applyZoneRules`, une règle qui porte les deux clés ou aucune est journalisée et
ignorée) :

- **spécifique** (`zone_name` + `condition` + `value_delta`) — regarde le
  tenant de la zone nommée n'importe où dans la partie, sans exigence
  d'adjacence (`applyZoneRuleSpecific`) ;
- **`adjacent_zones: true`** — itère la colonne CSV `adjacent_zones` **de la
  zone évaluée elle-même** (`applyZoneRuleAdjacent`), pas de la
  zone référencée : chaque zone listée y est comparée au tenant, et les
  deltas s'accumulent (plusieurs correspondances peuvent s'ajouter).

**Invariant à un seul saut.** `applyZoneRuleAdjacent` ne lit jamais la colonne
`adjacent_zones` d'une zone adjacente — il n'y a pas de récursion. « Adjacent »
signifie donc toujours *une* zone de distance depuis la zone qui déclenche le
calcul ; il n'existe aucune adjacence transitive (deux sauts) dans ce mécanisme.

---

### L'économie de ressources

Les montants vivent dans `controller_ressources` (`amount`, `amount_stored`,
`end_turn_gain`, une ligne par `(controller_id, ressource_id)`,
`var/mysql/setupBDD.sql`) ; la configuration par ressource — coûts,
`hide_when_zero`, `gain_rules` — vit dans `ressources_config`
(`var/mysql/setupBDD.sql`). `getRessources()` (`getRessources`, `ressources/functions.php`)
fait toujours la jointure des deux.

**`updateRessources`** (), appelé à l'étape `before_claim` de la fin de
tour : pour chaque ressource d'un contrôleur, si `is_stored` elle bascule
`amount` dans `amount_stored` ; si `is_rollable` est faux, `amount` est remis à
zéro *avant* d'ajouter `end_turn_gain` ; puis `end_turn_gain` s'ajoute dans
tous les cas.

**Dépense atomique.** Les trois opérations `spendRessourcesTo{BuildBase,
MoveBase,RepairLocation}` ne sont que des façades vers
`spendRessourcesByCostField($pdo, $controller_id, $costField)`
(`spendRessourcesByCostField`) — le nom de colonne de coût est vérifié contre une liste blanche
figée (`base_building_cost`, `base_moving_cost`, `location_repaire_cost`) ;
toute autre valeur est refusée. La fonction ne retient que les ressources dont
ce champ est `> 0`, ouvre **une** transaction, et appelle `consumeRessource`
(`consumeRessource`, un `UPDATE ... WHERE amount >= :amt` — garde TOCTOU en une seule
requête) pour chacune ; le premier échec fait tout annuler
(`rollBack`) : un contrôleur paie la totalité du coût multi-ressource, ou rien.

**Trap : trois champs de coût morts.** `ressources_config` déclare aussi
`servant_first_come_cost`, `servant_recruitment_cost` et
`extra_first_come_cost` — affichés dans `ressources/management.php` —
mais **aucun n'apparaît dans la liste blanche de `spendRessourcesByCostField`,
ni ailleurs dans le code** : ce sont des colonnes de configuration sans chemin
de dépense. Les renseigner n'a aujourd'hui aucun effet de jeu.

**`hide_when_zero`.** `filterVisibleRessources()` est un filtre
d'affichage pur : il ne masque une ligne que si le flag est posé *et* que
`amount`, `amount_stored` et `end_turn_gain` sont tous les trois nuls (et,
quand un `gainEstimate` est fourni, que le gain prévu au tour suivant est aussi
`<= 0`). Les vérifications internes de coût et de blocage appellent
`getRessources()` directement, sans ce filtre : une ressource masquée à zéro
reste pleinement dépensable et gate le jeu normalement.

**`gain_rules`** (JSON par ligne `ressources_config`) est lu par deux
fonctions séparées : `ressourceGainMechanic($pdo, $timing)`
(`ressourceGainMechanic`, `mechanics/ressourceGainMechanic.php`), qui **écrit** — appelée deux fois
en fin de tour, une fois par `timing` — et
`ressourceGainEstimateForController()` (`ressourceGainEstimateForController`, `ressources/functions.php`), qui
**ne fait qu'estimer** pour la page Ressources (aperçu du prochain tour), en
ré-implémentant la même lecture de règles sans jamais écrire. Une règle mal
formée (montant non numérique, `timing` absent, type de condition hors
liste) est ignorée silencieusement et journalisée
(`ressourceGainRuleIsValid`).

Types de condition acceptés (`RESSOURCE_GAIN_CONDITION_TYPES`, :

| Type | Résolu contre | Filtres SQL | Filtre post-fetch |
|---|---|---|---|
| `holds_zone` | `zones.holder_controller_id` | `zone_id`, `zone_name` | — |
| `claims_zone` | `zones.claimer_controller_id` | `zone_id`, `zone_name` | — |
| `owns_location_type` | `locations.controller_id` | `is_base`, `can_be_destroyed`, `zone_id`, `location_id` | `location_type` (comparé en PHP au tableau JSON `locations.location_types`) |

Chaque règle qui matche produit `amount × COUNT(correspondances)` pour le
contrôleur concerné — pas un montant forfaitaire.

`unlock_turn` : la règle est ignorée tant que `unlock_turn > tour_courant`
(`updateRessources` / `ressourceGainEstimateForController`) — **la borne est inclusive** : une règle `unlock_turn = 5`
produit son premier gain au tour 5, pas au tour 6.

**Montant négatif.** Rien ne distingue un `amount` négatif d'un positif, hormis
le court-circuit `amount === 0` : le `UPDATE ... SET amount = amount + :gain`
(`getRessources`) applique un gain négatif tel quel, sans plancher. Or
`controller_ressources.amount` est un `INT` signé ordinaire, sans contrainte
`CHECK` (`var/mysql/setupBDD.sql`) : une règle négative mal calibrée peut
faire passer une ressource sous zéro sans qu'aucune couche ne s'y oppose.
`rowCount() === 0` sur cet `UPDATE` (le contrôleur n'a pas de ligne pour cette
ressource — scénarios creux type Japon1555) est traité comme normal, journalisé
en avertissement, et **ne fait pas échouer** la mécanique.

---

### Le système de dons

Quatre canaux de don coexistent, avec des garde-fous et des chemins d'écriture
différents.

**Ressource** — `ressources/action.php`, POST uniquement, appelle
`giftRessource($pdo, $giver_id, $_POST)` (`giftRessource`, `ressources/functions.php`).
Garde-fous : montant `> 0`, cible différente du donneur, cible existante et non
`secret_controller`, ressource existante, stock du donneur suffisant — vérifié
une première fois en lecture puis une seconde fois dans la clause
`WHERE amount >= :amt2` du `UPDATE` réel (`giftRessource`), donc une course sur le
stock échoue proprement plutôt que de passer en négatif. Décrément donneur,
incrément (ou upsert) destinataire et `INSERT ressource_gift_logs` sont dans
une seule transaction. Le point d'entrée est un *post-redirect-get* strict :
succès ou échec, il se termine toujours par un `header('Location: ...
?feedback=...&msg=...')` puis `exit()` (`ressources/action.php`) — un
rafraîchissement de page ne peut donc pas rejouer l'envoi.

**Renseignement sur un lieu** — le bloc `giftInformationLocation` de
`controllers/action.php`, un bloc GET conditionné sur
`isset($_GET[...])`, pas une fonction dédiée) appelle
`addLocationToCKL($pdo, $target, $location_id, $mechanics['turncounter'], false)`
puis `logInformationGift($pdo, $giver_id, $target, 'location', ...)`. Auto-don
refusé (`giver_id === target_controller_id`) sans redirection : la page rend
la vue normalement en dessous du bandeau d'avertissement, dans la même
réponse.

**Renseignement sur un agent** — même fichier, bloc `giftInformationAgent`
, appelle `addWorkerToCKE($pdo, $target, $enemy_worker_id,
$giftDiscoveryTurn, $zone_id)` puis `logInformationGift(..., 'agent', ...)`.
Même garde d'auto-don. **Le trap** : `logInformationGift` est appelé
inconditionnellement juste après `addWorkerToCKE`, y compris quand celui-ci a
silencieusement refusé d'écrire (cas où la cible contrôle déjà cet agent —
`addWorkerToCKE` retourne alors `null`, `controllers/functions.php`) :
le journal peut donc affirmer qu'un don a eu lieu alors que la ligne CKE n'a
pas bougé.

Datation : les découvertes sont estampillées avec le compteur de tour
**d'avant** l'incrément (voir plus haut, « Ce que l'incrément tardif implique
pour les dates ») ; le don d'agent recule donc son estampille de
`attackTimeWindow` pour rester comparable
(`$giftDiscoveryTurn = max(0, $mechanics['turncounter'] - $attackTimeWindow)`,
dans `controllers/action.php`). Le don de lieu, lui, écrit le tour courant tel quel, sans ce
recul. Ce n'est pas une incohérence visible aujourd'hui : rien dans le code ne
relit `controller_known_locations.last_discovery_turn` à travers une fenêtre
« récent / ancien » comparable à celle qu'utilise `buildEnemyWorkerListing`
pour les agents (`buildEnemyWorkerListing`, `workers/functions.php`) — mais si une telle
fenêtre était un jour ajoutée côté lieux, l'asymétrie deviendrait un bug de
datation à corriger en miroir de celle déjà appliquée côté agents.

**Version admin.** `controllers/management.php` reproduit les deux
blocs `giftInformationAgent` / `giftInformationLocation` sans aucune des trois
choses que porte la version joueur : pas de garde d'auto-don, pas de garde de
propriété (accès privilégié), et surtout **pas d'appel à
`logInformationGift`** — un don du meneur de jeu n'entre jamais dans
`information_gift_logs` : ce n'est pas un échange entre factions, donc pas un
événement à journaliser comme tel.

> **Cette duplication est délibérée, et il ne faut pas la « corriger ».**
>
> L'orga agit en étant connecté sur une faction. Journaliser son don le ferait
> apparaître dans les transactions comme un échange **de cette faction vers une
> autre** — un geste d'arbitrage deviendrait une manœuvre attribuée à un joueur,
> visible de tous. L'absence de journal n'est donc pas un oubli : c'est ce qui
> garde le geste du meneur de jeu invisible, comme il doit l'être.
>
> Mutualiser les deux blocs supposerait un drapeau « ne pas journaliser »
> traversant le chemin commun, pour un gain de quelques lignes et un risque
> réel de brancher un jour le journal du mauvais côté. Le doublon est le moindre
> mal, tant que ce paragraphe explique pourquoi.

**L'agent lui-même.** Un quatrième canal, distinct des trois précédents,
transfère l'agent en personne : `workers/action.php`, action `gift`
, protégée par la garde de propriété générale de la page (le
`worker_id` doit appartenir au contrôleur de session, sauf privilège,
 et par une garde d'auto-don spécifique
(`(int)$gift_controller_id === (int)$session_controller_id` → 403,.
L'effet passe par `activateWorker($pdo, $workerId, 'gift', $extraVal)`
(`activateWorker`, `workers/functions.php`) et s'applique **immédiatement**, pas en
fin de tour comme `attack`/`claim`/`investigate` : le contrôleur primaire de
`controller_worker` bascule vers le nouveau maître, `worker_actions` du tour
courant est réécrit à `passive`, un agent-trace est créé pour l'ancien
contrôleur (`createTraceWorker`) et une éventuelle trace déjà posée sur le
nouveau contrôleur est détruite (`destroyTraceWorker`). **Ce canal n'écrit
dans aucune table de journal de don** — ni `ressource_gift_logs`, ni
`information_gift_logs`, ni équivalent : contrairement aux trois autres, un
don d'agent ne laisse pas de trace consultable après coup.

---

### Le moteur d'IA

**`mechanics/ia/` n'existe pas sur cette branche.** Le seul fichier existant est
`mechanics/aiMechanic.php`, une coquille vide :

- la fonction ouvre un bloc HTML de debug, lit le flag `DEBUG_IA` (`aiMechanic`,
  une clé qui n'est seedée dans aucun des CSV de config du dépôt —
  `setupJapon1555CSV_config.csv`, `setupTestConfig_config.csv`,
  `setupVampire1966CSV_config.csv`), puis termine sans avoir exécuté la
  moindre requête SQL ni la moindre logique ;
- aucun comportement n'y est écrit : ni machine à états, ni création d'agents,
  ni déplacement. La conception et le moteur déjà écrit sur une autre branche
  sont décrits dans l'issue #182 ;
- **rien ne l'appelle** : `mechanics/endTurn.php` ne la nomme pas, ce que le
  document note déjà en §3. `aiMechanic()` n'est donc jamais invoquée par la fin
  de tour, ni gatée par le mécanisme de reprise par état.

Le modèle de données pour *déclarer* un contrôleur IA existe, lui, réellement :
`controllers.ia_type` (`TEXT`) et `controllers.origin_zone_id` (`INT`, « AI
anchor zone » selon son commentaire de schéma) sont deux colonnes réelles
(`var/mysql/setupBDD.sql`), et `origin_zone_id` est même résolu depuis un
nom de zone au chargement CSV (`gameReady`, `BDD/db_connector.php`,
`'zones__name->origin_zone_id'`). Mais aucune requête, nulle part dans le
code, ne lit `ia_type` ou `origin_zone_id` pour en tirer un comportement : ce
sont des colonnes de scénario sans consommateur. De même, ni `is_ia`, ni
`ai_controller_params`, ni les fonctions `aiCheckStateTransition` /
`aiPassiveBehaviour` / `aiSearchingBehaviour` / `aiAggressiveBehaviour` /
`aiViolentBehaviour` / `aiEnsureBase` / `aiRecruitOneInZone` n'existent
n'importe où dans le dépôt sur `main` — la recherche est exhaustive et vide.

**Ce que cela signifie pour la lecture d'autres documents du dépôt** : la
branche non fusionnée `feat-dumb-ai-engine` (dernier commit `bb7f5a4`) porte,
elle, un véritable découpage `mechanics/ia/*.php` en 14 fichiers
(`aiBudget`, `aiDefence`, `aiIntel`, `aiKnowledge`, `aiMechanic`, `aiParams`,
`aiPowers`, `aiRecruit`, `aiState`, `aiStrike`, `aiTargets`, `aiTerritory`,
`aiWorkers`, `aiZones`) — c'est manifestement l'origine des mentions
`mechanics/ia/` ailleurs dans la documentation et des descriptions détaillées
de machine à états dans les notes de travail. Cette branche n'a jamais été
fusionnée dans `main` ; rien de son contenu ne doit être présenté comme
l'état actuel du dépôt tant qu'elle ne l'a pas été.

**La règle d'équité que le projet vise à respecter** — une IA ne doit
connaître que ce qu'un joueur humain pourrait savoir au même moment, donc
**compter les `worker_powers` effectivement révélés plutôt que sommer les
statistiques réelles des ennemis** — est un principe de conception documenté
dans les notes du projet, pas une contrainte qu'on puisse aujourd'hui vérifier
dans `mechanics/aiMechanic.php` : il n'y a ni requête ni calcul à auditer, le
fichier ne fait rien. Le respecter restera à démontrer le jour où une
implémentation — sur `main` ou lors d'une fusion depuis la branche citée
ci-dessus — remplacera ce stub.

---

## 11. Ce que ce document ne couvre pas

Les pouvoirs et disciplines — leur modèle de règles `findMatchingBranch`, les
transformations, l'enseignement — ne sont décrits qu'en surface, par leurs points
d'entrée d'action (§6) et leurs colonnes (§9). C'est la dernière zone d'ombre
notable.

La liste des sujets ouverts n'est pas reprise ici : `gh issue list` fait
autorité, une liste figée dans un document ne le peut pas.
