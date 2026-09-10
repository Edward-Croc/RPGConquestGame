# Règles de code et de contribution

Ce document est la référence partagée du dépôt. Il est versionné : toute règle
qui compte pour plus d'une personne doit y figurer, et non dans les notes
privées d'un contributeur.

Pour comprendre le fonctionnement du jeu avant de coder, lire
[`architecture.md`](architecture.md). Pour les clés de configuration, lire
[`configuration.md`](configuration.md).

---

## 1. Avant d'écrire une ligne

**Lire `docs/architecture.md` d'abord.** C'est le document d'orientation. S'il
est faux ou incomplet sur ce que tu touches, corrige-le dans la même branche —
un document périmé coûte plus cher qu'un document absent.

**Chercher avant d'écrire.** Un `grep` sur le nom probable de la fonction avant
d'en créer une nouvelle. Le dépôt contient déjà beaucoup d'aides : `getZoneName`,
`getLocationName`, `getMechanics`, `getConfig`, `spendRessourcesByCostField`,
`createTraceWorker`, `updateWorkerAction`. Étendre ou appeler l'existant plutôt
que dupliquer.

**S'harmoniser avec le code qui entoure.** Style, motifs HTML, idiomes PDO,
langue des chaînes. Une divergence non justifiée est une remarque de revue.

---

## 2. Nommage

| Origine de la valeur | Convention | Exemple |
|---|---|---|
| Lue en base de données | `snake_case` | `$turn_number`, `$controller_id`, `$action_choice` |
| Calculée en PHP | `camelCase` | `$controllerAttack`, `$locationDefence`, `$ownerKnowsSecret` |

Les clés de configuration ont **trois** familles, toutes vivantes : `camelCase`
pour les récentes (`locationAttackMode`, `attackTimeWindow`,
`baseDefenceAddTurns`), capitales pour les anciennes (`MINROLL`,
`LOCATIONNAMEDIFF`), et `snake_case` pour les interrupteurs de fonctionnalité
(`ressource_management`, `age_discipline`, `recrutement_disciplines`). Aucune
n'est renommée : une clé est une donnée de scénario.

---

## 3. La documentation dit ce qui **est**

`architecture.md` et `configuration.md` décrivent l'état du code, pas son
histoire. Un lecteur y cherche comment le jeu fonctionne aujourd'hui, et chaque
phrase au passé le force à deviner ce qui est encore vrai.

Ne s'écrivent donc pas dans ces documents : « X mutait sur un GET », « désormais
refusé », « corrigé en PR #139 », « arbitrage du 2026-09-09 », un tableau des
écarts trouvés lors d'une relecture, un horodatage de session. Le récit d'un
changement vit dans le message de commit et le corps de la PR, que `git log` et
GitHub conservent bien mieux qu'un paragraphe qui pourrit.

```markdown
Mal : `endTurn.php` mutait sur un simple GET ; trois pièces ferment désormais
      le rejeu.
Bien : `endTurn.php` n'accepte qu'un POST portant un jeton à usage unique.
```

Une **raison** se documente, elle, au présent : « cette duplication est
délibérée, parce que… » explique une contrainte qui tient encore. Ce qui est
proscrit, c'est la date, le numéro de PR et le verbe au passé qui la
transforment en anecdote.

Une exception assumée : un renvoi vers une question **ouverte** (`voir l'issue
#120`) décrit bien l'état présent — celui d'un point non tranché.

---

## 4. Commentaires

**Un commentaire dit ce que le code *fait* ou pourquoi il le fait ainsi — jamais
comment on en est arrivé là.** Pas de numéro d'issue, pas de « suite à l'audit
X », pas de récit de décision. Ces éléments appartiennent au message de commit
et à la description de PR, qui sont consultables par `git log` et ne pourrissent
pas dans la source.

**Un commentaire de code tient sur une ligne, et une seule.** Pas deux, pas
trois. Si une ligne ne suffit pas, c'est que tu écris une justification : elle
va dans le message de commit. Et une ligne veut dire une ligne *courte*, pas
une phrase de 160 caractères qu'on a refusé de couper.

```php
// Mal : trois lignes, dont deux de raisonnement.
// A turn is a one-shot mutation. It must arrive as a POST carrying the token
// minted beside the sidebar button, and that token is burned on use, so an F5
// replays the POST with a dead token and is refused.

// Bien : ce que le code fait, en une ligne.
// EndTurn is a one shot mutation and must have a token, we burn the token on use.
```

**Un bloc de documentation — PHPDoc, docstring — peut être plus long**, puisque
c'est son rôle de décrire une signature et un contrat. Il doit rester au sujet :
ce que la fonction fait, ce qu'elle attend, ce qu'elle rend. Pas d'historique,
pas de justification de conception.

**Les fichiers de test `.py` sont exemptés** de la limite d'une ligne. Un test
qui explique ce qu'il verrouille et pourquoi il rougirait vaut mieux qu'un test
muet.

```php
// Bien : dit ce que la garde protège.
// Released only once the move is committed, so the queue survives a failed UPDATE.

// Mal : raconte l'historique.
// Suite au point 3 de l'audit #74, on a décidé après discussion que...
```

Vérifier après toute insertion qu'aucun bloc de documentation orphelin ne
subsiste au-dessus d'une fonction déplacée.

---

## 5. Prélude des fonctions PHP

Forme canonique, dans cet ordre :

```php
/**
 * Une ligne qui dit ce que la fonction fait.
 *
 * @param PDO $pdo : database connection
 * @param int $base_id : identifier of the base
 *
 * @return int : number of freed workers
 */
function releaseAgentsTargetingMovedBase(PDO $pdo, int $base_id, int $turn_number): int
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with base_id : ' . $base_id, ['turn_number' => $turn_number], 'debug');
    ...
```

- signature **typée**, y compris le type de retour ;
- ligne DEBUG **commentée**, activable à la main ;
- un `game_error_log(… 'START …', 'debug')` en entrée ;
- un `'DONE'` en sortie quand la fonction produit un résultat qu'on voudra
  relire.

Niveaux : `debug` pour la trace, `warning` pour un refus attendu et récupérable,
`error` pour une panne. La suite de tests **échoue** sur toute nouvelle entrée
`error` non annotée `@pytest.mark.expects_errors` ; les `warning` sont ignorés.

---

## 6. Base de données

**Toujours lier les paramètres.** Jamais d'interpolation d'une valeur dans le
SQL. Rester cohérent à l'intérieur d'une même fonction : `bindParam` partout, ou
tableau passé à `execute` partout, pas les deux.

**Les deux dialectes évoluent ensemble.** Toute modification de schéma se
répercute dans `var/mysql/setupBDD.sql` **et** `var/postgres/setupBDD.sql`.

**Les booléens se lient en `PDO::PARAM_BOOL`**, jamais via le tableau
d'`execute` : `false` y devient `''`, que PostgreSQL refuse sur une colonne
`BOOLEAN`. Côté MySQL le littéral s'écrit `0`/`1`, côté PostgreSQL
`false`/`true` — le dépôt choisit avec `$_SESSION['DBTYPE'] == 'postgres'`.

**Le piège de vérité PHP.** `(bool)"FALSE" === true`. Seuls `"0"` et `""` sont
faux. Une valeur de configuration se compare donc explicitement :
`strtoupper((string)getConfig($pdo, 'clé')) === 'TRUE'`.

---

## 7. Gardes et actions rejouables

Une action déclenchée par une URL peut être rejouée : F5, retour arrière,
double-clic, ou URL forgée à la main. Deux conséquences :

1. **La garde passe avant la dépense.** Vérifier l'état *puis* débiter, jamais
   l'inverse.
2. **Un repli permissif ne déréférence rien.** Si le `SELECT` de la garde échoue
   et que la ligne manque, il n'y a rien à appliquer : refuser vaut mieux que
   dépenser puis travailler sur `null`.

Le motif sûr à recopier est celui de `ressources/action.php` : mutation en POST,
puis `header('Location: …')` et `exit()`, de sorte que la réponse à la mutation
n'est jamais la page mutée.

**L'exception est `mechanics/endTurn.php`**, dont la réponse *est* la page mutée
parce qu'elle diffuse le récit du tour au fil de la résolution. Là où la
redirection est impossible, il faut un jeton à usage unique : voir
[`architecture.md`](architecture.md) §3.

---

## 8. Tests

La suite est Playwright pilotée par pytest, dans `tests/`.

**Observer l'interface rendue, pas la base.** Un test qui interroge MySQL
directement porte `@pytest.mark.db` et sera **sauté** en `UI_ONLY=1`, donc absent
de la campagne Demo. Ne pose ce marqueur que si le test fait réellement du SQL :
posé à tort, il rend un test de non-régression silencieusement inopérant.

**Les données de test viennent des CSV** de `var/csv/`, via `setupTestConfig*`,
et non de jeux SQL écrits pour l'occasion. On peut ajouter des lignes à un CSV
pour enchaîner davantage de contrôles avant un rechargement.

**Prouver qu'un test échoue sans le correctif.** Retirer le correctif, voir le
test rougir, le remettre. Et accompagner toute assertion négative d'une
assertion positive prouvant que le chemin nominal marche encore — sans quoi un
test peut passer parce qu'il n'observe rien.

**Économiser les rechargements de scénario et les fins de tour.** Ce sont les
deux coûts dominants. Grouper les contrôles d'un même périmètre (Agent, Faction,
Lieu, Zone) autour d'un seul chargement, et placer une série avant la fin de
tour puis une série après.

**Ne jamais utiliser `networkidle`.** Il attend 500 ms de silence réseau et se
dégrade sous charge. Attendre un sélecteur ou une condition explicite.

**Un seul `pytest` à la fois.** Les campagnes locale et Demo partagent
l'amorçage MySQL.

---

## 9. Branches, commits et revue

**Jamais de commit direct sur `main`.** Une branche par issue, fusionnée par
pull request. La CI (`.github/workflows/test.yml`) ne se déclenche que sur les PR
visant `main`.

**Jamais de commit non demandé.** L'auteur du dépôt décide quand on commite.

**Chaque commit a sa propre revue explicite.** Une revue accordée sur un fichier
ne vaut pas pour le suivant.

**Suite complète avant commit.** Et prévenir bruyamment quand elle tourne : elle
occupe la même base de données qu'un test manuel.

**Format du message.** Conventional Commits, avec une espace avant les deux
points (`feat : `, `fix : `, `test : `, `docs : `, `ci : `, `config : `), sujet
en français, puis trois à cinq lignes qui disent *pourquoi*. C'est là que va la
justification qu'on a retirée des commentaires.

```
fix : refuser un rejeu de réparation de lieu

repairLocation dépensait la ressource à chaque rechargement de l'URL,
puis réappliquait update_location — un lieu déjà relevé repartait en
ruines. Le SELECT qui alimentait updateLocation sert désormais aussi de
garde, en amont de la dépense.
```

**Aucune co-signature d'outil.** Pas de `Co-Authored-By` d'assistant, pas de
mention d'aide automatisée.

---

## 10. Ce qui ne se versionne pas

- `CLAUDE.md` — instructions personnelles, propres à chaque contributeur ;
- `tests/AUDIT_*.md` — documents de travail temporaires ;
- `.test_durations` — les durées de `pytest-split` s'accumulent au lieu de se
  remplacer et gardent des entrées de fichiers supprimés.

---

## 11. Éviter le code inutile

Une nouvelle colonne, une nouvelle clé de configuration ou une nouvelle fonction
d'aide se justifie par un comportement **visible par un joueur ou par le meneur
de jeu**. Si personne ne peut observer la différence, la pièce n'a pas sa place.

Les décisions de conception — nom d'une clé de configuration, découpage d'une
fonction, choix d'un repli — se posent en question avant d'être codées.
