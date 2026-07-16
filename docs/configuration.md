# Documentation de configuration

**Public visé :** organisateurs/admins de soirées enquête utilisant le système RPG Game Conquest, et auteurs de scénarios qui éditent la table `{prefix}config`.

**Note de lecture :** les **clés de configuration** (`claimMode`, `claimDiff`, etc.) sont stockées dans la table `{prefix}config` et modifiables via l'admin ; les valeurs par défaut sont notées entre parenthèses. Les **variables calculées** (`claim_val`, `calculated_defence_val`, etc.) sont recalculées à chaque tour à partir des configs et de l'état du jeu — elles ne sont pas modifiables directement, on les mentionne uniquement pour expliquer comment les configs s'y combinent. Pour les clés énumérées (modes), toute valeur non implémentée désactive le mécanisme correspondant.


## 1. Textes affichés

*Section à compléter dans un commit suivant.* Couvrira : `TITLE`, `PRESENTATION`, `IntrigueOrga`, `basePowerNames`, les familles `txt_ps_*` et `txt_inf_*`, les dénominateurs (`controllerNameDenominator*`, `timeDenominator*`), `textForZoneType`, `timeValue`, `map_file`, `map_alt`.

## 2. Moteur de jeu (autres calculs)

Cette section couvre les clés qui pilotent les calculs partagés entre tous les modes : valeurs de base des actions d'agent, bonus contextuels, listes d'actions, seuils de découverte, combat entre agents et difficulté des places fortes. Ces clés s'appliquent quelles que soient les valeurs de `claimMode` et `locationAttackMode`.

### Dés et valeurs d'action

**`MINROLL`** (= 1) et **`MAXROLL`** (= 6) — Bornes inclusives du jet aléatoire utilisé pour calculer `enquete_val`, `attack_val` et `defence_val` des agents en action active. Le tirage est uniforme sur `[MINROLL, MAXROLL]`. Un intervalle plus large produit plus d'imprévisibilité ; plus étroit rend pouvoirs et bonus dominants.

**`PASSIVEVAL`** (= 3) — Valeur fixe utilisée à la place du jet pour les actions passives. Un agent qui surveille (`passive`) ou se cache (`hide`) ne tire pas de dé ; il reçoit cette valeur sur les axes où son action est considérée comme passive. Régler `PASSIVEVAL` près de la moyenne des dés (`(MINROLL + MAXROLL) / 2`) garde les actions passives compétitives.

### Bonus de contrôle de zone et bonus d'action

**`ENQUETE_ZONE_BONUS`** (= 0), **`ATTACK_ZONE_BONUS`** (= 0), **`DEFENCE_ZONE_BONUS`** (= 1) — Bonus ajoutés à `enquete_val`, `attack_val` et `defence_val` d'un agent dont le contrôleur détient (holder) la zone où l'agent se trouve. Par défaut, seule la défense profite du contrôle de zone. Augmenter `ATTACK_ZONE_BONUS` rend la conquête plus stratégique.

**`HIDE_ENQUETE_FLAT_BONUS`** (= 4), **`HIDE_DEFENCE_FLAT_BONUS`** (= 1) — Bonus plats ajoutés à `enquete_val` et `defence_val` quand l'agent choisit l'action `hide`. L'action « se cacher » renforce la défense de l'agent et complique sa détection par les enquêtes ennemies (la valeur d'enquête sert alors de résistance, pas d'investigation), au prix de ne pas attaquer ce tour.

#### Listes d'actions actives et passives

Les six clés suivantes ne pilotent **que le calcul des valeurs** `enquete_val`, `attack_val` et `defence_val` de chaque agent en début de tour. Pour chaque axe (enquête, attaque, défense), l'`action_choice` choisi par l'agent détermine si la valeur correspondante est obtenue par un **jet de dé aléatoire** (action listée comme `active`) ou par la **valeur fixe `PASSIVEVAL`** (action listée comme `passive`). Une action absente des deux listes d'un axe donne `0` sur cet axe.

> **Important :** ces listes ne déterminent **pas** quels agents effectuent réellement une enquête, une attaque ou une défense — ces comportements sont pilotés par d'autres clés. Par exemple, la recherche d'agents ennemis n'est exécutée que pour les `action_choice` listés dans **`investigateActionsList`** (= `'passive','investigate'`). Cette clé est indépendante des six listes ci-dessous.

- **`passiveInvestigateActions`** (= `'passive','attack','captured','hide'`) — Actions dont la valeur d'enquête est `PASSIVEVAL`.
- **`activeInvestigateActions`** (= `'investigate','claim'`) — Actions dont la valeur d'enquête est tirée aléatoirement entre `MINROLL` et `MAXROLL` inclus.
- **`passiveAttackActions`** (= `'passive','investigate','hide'`) — Actions dont la valeur d'attaque est `PASSIVEVAL` (utilisée pour les ripostes).
- **`activeAttackActions`** (= `'attack','claim'`) — Actions dont la valeur d'attaque est tirée aléatoirement entre `MINROLL` et `MAXROLL` inclus.
- **`passiveDefenceActions`** (= `'passive','investigate','attack','claim','captured','hide'`) — Actions dont la valeur de défense est `PASSIVEVAL`.
- **`activeDefenceActions`** (= `''`, vide par défaut) — Actions dont la valeur de défense est tirée aléatoirement entre `MINROLL` et `MAXROLL` inclus. Vide signifie que toutes les valeurs de défense sont fixes.

Format attendu : chaîne SQL `'action1','action2',...` avec apostrophes incluses. L'action `claim` apparaît dans les deux axes actifs (`enquete` et `attack`) — c'est volontaire : revendiquer génère un jet pour les deux valeurs, ce qui rend l'agent compétitif quand le `claimMode='worker'` les compare à la défense de la zone.

### Seuils de découverte d'information

**`REPORTDIFF0`** (= -1), **`REPORTDIFF1`** (= 1), **`REPORTDIFF2`** (= 2), **`REPORTDIFF3`** (= 4) — Seuils progressifs de différence `enquete_val − target_defence_val` pour révéler les niveaux d'information dans un rapport d'enquête sur un agent : nom et action (niveau 0, accessible dès `≥ REPORTDIFF0`), capacités aléatoires (niveau 1), capacités du contrôleur et numéro de réseau (niveau 2), nom du contrôleur dominant (niveau 3). Le niveau 0 négatif (-1) signifie que l'information passe même avec un léger déficit ; mettre `REPORTDIFF0 = 1` conditionnerait toute découverte à un avantage net.

**`LOCATIONNAMEDIFF`** (= 0), **`LOCATIONINFORMATIONDIFF`** (= 1), **`LOCATIONARTEFACTSDIFF`** (= 2) — Seuils de différence `enquete_val − discovery_diff` pour les niveaux de découverte d'un lieu secret : nom du lieu, description / informations secrètes, présence d'artefacts récupérables. Augmenter ces seuils rend les enquêtes de zone moins rentables.

> **Important :** la recherche d'information (rapports d'enquête sur agents ennemis et découverte de lieux secrets) n'est effectuée que pour les actions listées dans **`investigateActionsList`** (= `'passive','investigate'`). Le filtre est appliqué dans `mechanics/investigateMechanic.php` (agents) et `mechanics/locationSearchMechanic.php` (lieux).

### Réduction de la redondance des rapports d'enquête

Quand un enquêteur redécouvre un agent ou un lieu déjà connu de son contrôleur (via `controllers_known_enemies` / `controller_known_locations`), le rapport bascule sur une variante condensée — un résumé visible et le détail complet replié dans un widget `<details>` — au lieu de répéter les mêmes slabs. Les artefacts trouvés restent toujours affichés en dehors du repli.

**`investigateOrder`** (= `'asc'`) — Ordre de traitement des enquêteurs au sein d'un même contrôleur. `'asc'` (défaut) : les enquêteurs à faible `enquete_val` traitent leur cible en premier, ce qui laisse une chance à chaque enquêteur de découvrir une information avant qu'un collègue mieux équipé ne sature les `controllers_known_enemies`. `'desc'` : ordre inverse (les forts révèlent tout, les faibles voient principalement des « déjà connus »). Le tri est appliqué directement dans le SQL de `getSearcherComparisons` / `getLocationSearcherComparisons`. Toute valeur hors liste blanche retombe sur `'asc'`.

**Templates de variantes** (tableaux JSON ou chaînes simples — un élément suffit, plusieurs entrées sont tirées au hasard) :

- **`textesAgentStillHere`** (= `["L'agent %1$s est toujours présent dans ce %2$s."]`) — résumé `<summary>` quand un agent connu est revu dans la même zone sans nouvelle information. `%1$s` = nom de l'agent, `%2$s` = valeur du config `textForZoneType` (par exemple « territoire », « quartier »).
- **`textesAgentMoved`** (= `["L'agent %1$s, repéré précédemment dans %2$s, s'est déplacé ici."]`) — résumé quand `controllers_known_enemies.zone_id` diffère de la zone d'observation. `%1$s` = nom, `%2$s` = zone précédente.
- **`textesAgentUpgradeInfo`** (= `["Nous avons obtenu de nouvelles informations concernant %1$s :"]`) — en-tête visible quand l'enquête courante atteint un niveau `DIFF` supérieur à ce qui était déjà connu ; les slabs nouveaux apparaissent ensuite en clair, les anciens sont repliés.
- **`textesAgentReminderLabel`** (= `Rappel des informations connues`) — étiquette du `<summary>` qui replie les slabs déjà connus dans la variante « upgrade ».
- **`textesLocationStillHere`** (= `["Le lieu %1$s est toujours là."]`) — résumé pour un lieu déjà répertorié dans `controller_known_locations` sans nouvelle découverte. `%1$s` = nom du lieu.

### Combat entre agents

**`ATTACKDIFF0`** (= 1), **`ATTACKDIFF1`** (= 3) — Seuils de différence `attack_val − defence_val` pour les résultats d'attaque. En-dessous de `ATTACKDIFF0` : échec (la cible apprend le nom de l'attaquant). À partir de `ATTACKDIFF0` : élimination de la cible. À partir de `ATTACKDIFF1` : capture vivante (le contrôleur obtient l'accès aux rapports). Augmenter `ATTACKDIFF1` rend les captures plus rares.

**`attackTimeWindow`** (= 1) — Nombre de tours pendant lesquels un agent découvert reste attaquable après avoir perdu son couvert. Mettre à `0` désactive la fenêtre (aucune limite : tous les agents jamais découverts restent attaquables). Avec `1` (défaut), un agent reste attaquable au tour de sa découverte.

**`canAttackNetwork`** (= 1) — Si `0`, seuls les agents individuels apparaissent dans la liste des cibles ; si `> 0`, les agents sont regroupés par réseau dès que `REPORTDIFF2` est atteint, et le contrôleur peut attaquer un réseau entier.

#### Ripostes

**`RIPOSTACTIVE`** (= 1) — Active la mécanique de riposte. Si `1`, une cible qui résiste peut éliminer l'attaquant ; si `0`, la riposte est désactivée.

**`RIPOSTDIFF`** (= 2) — Seuil de différence `defence_val − attack_val` pour qu'une riposte réussisse. Plus élevé : ripostes rares ; plus bas : le défenseur dominant gagne souvent.

#### Options obsolètes

**`LIMIT_ATTACK_BY_ZONE`** (= 0) — Si `0`, une attaque enregistrée persiste même si la cible quitte la zone ; si `> 0`, l'attaque est annulée dès que la cible déménage. On déconseille cette option, car le déménagement étant immédiat les agents sont intouchables. Un développement futur ajoutera peut-être le déménagement en fin de tour, avec malus aux stats auquel cas cette option deviendra intéressante.

### Difficulté de découverte des places fortes

**`baseDiscoveryDiff`** (= 3) — Plancher de la difficulté de découverte (`discovery_diff`) d'une place forte. Plus la valeur est haute, plus il faut une `enquete_val` élevée pour découvrir le lieu.

**`baseDiscoveryDiffAddPowers`** (= 1), **`baseDiscoveryDiffAddWorkers`** (= 1), **`baseDiscoveryDiffAddTurns`** (= 0.5) — Multiplicateurs des composantes pondérées : pouvoirs du contrôleur défenseur, nombre de ses agents dans la zone, ancienneté de la base (en tours). Mettre un multiplicateur à `0` désactive complètement la composante.

**`maxBonusDiscoveryDiffPowers`** (= 5), **`maxBonusDiscoveryDiffWorkers`** (= 4), **`maxBonusDiscoveryDiffTurns`** (= 3) — Plafonds par composante. Au-delà du plafond, la composante est tronquée. Mettre un plafond à `0` retire la limite (attention : peut produire des bases impossibles à découvrir).

La `discovery_diff` finale d'un lieu est recalculée à chaque tour par `recalculateBaseDefence` (`zones/functions.php`). La formule complète vit dans `calculateSecretLocationDiscoveryDiff`.

### Règles de modification contextuelles (`zones.zone_rules`)

**`zones.zone_rules`** — colonne JSON nullable de la table `{prefix}zones` portant des règles qui ajustent les valeurs de calcul d'un contrôleur sur cette zone. Deux **shapes** de règles cohabitent :

- **`zone_name`** — cible une zone spécifique par nom (n'importe où sur la carte, sans contrainte d'adjacence). Utile pour imposer un prérequis territorial précis (gate distant), ou lier des zones stratégiques nommées.
- **`adjacent_zones: true`** — itère sur toutes les zones voisines (single-hop, via `adjacent_zones`) et applique le `value_delta` pour chaque voisine dont la condition est satisfaite. Utile pour récompenser la cohésion territoriale ou pénaliser un prétendant isolé.

**Schéma JSON :** un objet dont chaque clé est un **type d'application** et dont la valeur est un tableau de règles.

```json
{
    "Claim": [
        {"zone_name": "Plaines du Kansai", "condition": "not_held_by_actor", "value_delta": -4},
        {"zone_name": "Plaines du Kansai", "condition": "held_by_actor", "value_delta": 2},
        {"adjacent_zones": true, "condition": "held_by_actor", "value_delta": 1}
    ],
    "Attack":        [ /* mêmes shapes */ ],
    "Defence":       [ /* ... */ ],
    "ZoneDefence":   [ /* ... */ ],
    "DiscoveryDiff": [ /* ... */ ]
}
```

**Types supportés :**

- **`Claim`** — modifie la valeur retournée par `calculateControllerValue('Claim', ...)`, consommée par `claimMechanic` pour comparer prétendant et défense de la zone.
- **`Attack`** — modifie la valeur d'attaque agrégée d'un contrôleur dans la zone (place forte, agents en action `attack`).
- **`Defence`** — modifie la valeur de défense agrégée d'un contrôleur dans la zone.
- **`ZoneDefence`** — modifie la valeur de défense de zone recalculée en fin de tour (`recalculateBaseZoneDefence`).
- **`DiscoveryDiff`** — modifie la difficulté de découverte (`discovery_diff`) des lieux secrets présents dans la zone.

**Champs communs :**

- **`condition`** (enum, requis) — deux valeurs implémentées :
  - **`held_by_actor`** — la règle s'applique si l'acteur (le contrôleur pour qui on calcule) **détient** la zone évaluée (`holder_controller_id == actor_id`).
  - **`not_held_by_actor`** — la règle s'applique si l'acteur **ne détient pas** la zone évaluée.
- **`value_delta`** (int, requis) — entier signé ajouté à la valeur retournée quand la condition est satisfaite. Peut être négatif (pénalité) ou positif (bonus).

**Shape spécifique — `zone_name` :**

- **`zone_name`** (string, requis) — nom exact de la zone à évaluer. Résolu par lookup SQL sur `zones.name`. **Pas de contrainte d'adjacence** : la zone peut se trouver n'importe où sur la carte.
- Se déclenche **au plus une fois** (une seule zone évaluée).

**Shape itérateur — `adjacent_zones: true` :**

- **`adjacent_zones`** (bool, requis, doit valoir `true`) — bascule la règle en mode itérateur sur la liste `adjacent_zones` de la zone porteuse.
- Se déclenche **une fois par voisine satisfaisant la condition** : les `value_delta` s'accumulent (un bonus `+1` avec 3 voisines détenues donne `+3`).

**Discrimination :** la présence de `zone_name` ou de `adjacent_zones: true` détermine le shape. Une règle qui a **les deux** ou **aucun des deux** est ignorée avec un `error_log`.

**Combinaison additive :** toutes les règles satisfaites (des deux shapes) contribuent au résultat final : `base_value + Σ(value_delta pour chaque règle satisfaite)`. L'ordre des règles dans le tableau n'est pas significatif.

**Fail-open — comportements de robustesse :**

- `zone_rules IS NULL` → la valeur passe inchangée (aucun log).
- JSON invalide (parse fail) → `error_log` + la valeur passe inchangée.
- `controller_id NULL` (pas d'acteur, calcul générique) → la valeur passe inchangée.
- Règle avec `zone_name` référençant un nom introuvable dans `zones` → `error_log` + règle ignorée.
- Règle avec `adjacent_zones: true` mais la zone porteuse n'a aucune voisine listée → règle ignorée (aucun match possible, pas de log).
- Règle avec `zone_name` **et** `adjacent_zones: true` → `error_log` (conflit) + règle ignorée.
- Règle sans `zone_name` ni `adjacent_zones: true` → `error_log` (indéfinie) + règle ignorée.
- `condition` inconnue (hors `held_by_actor` / `not_held_by_actor`) → `error_log` + règle ignorée.
- Règle mal formée (champs `condition` ou `value_delta` manquants) → `error_log` + règle ignorée.

Le principe est simple : une configuration cassée dégrade la règle concernée mais laisse la valeur de base intacte.

**Point d'intégration :** l'ajustement est appliqué à la fin de `calculateControllerValue` (`zones/functions.php`), **après** tous les autres termes du calcul (base, zone_control, powers, workers, owned_locations, supporting, turns). La fonction `applyZoneRules` dispatche chaque règle vers `applyZoneRuleSpecific` (pour `zone_name`) ou `applyZoneRuleAdjacent` (pour `adjacent_zones: true`), puis cumule les `value_delta` pertinents.

> **Exemple concret :** dans le scénario Japon1555, `Cité impériale de Kyōto` porte deux règles `Claim` avec `zone_name: "Plaines du Kansai"` (`-4` si l'acteur ne détient pas les plaines, `+2` s'il les détient). Un prétendant doit donc établir sa présence dans les plaines avant d'espérer conquérir la capitale.

**Édition CSV / admin :** la colonne est chargée depuis les CSV de scénario (`setup{ScenarioName}_zones.csv`) via `db_connector.php`. Le JSON doit être valide et échappé selon les règles CSV (guillemets internes doublés). Une interface d'édition admin est également disponible sur `zones/management_zones.php` : chaque ligne de zone expose une `<textarea>` pour `zone_rules` (JSON, textarea vide → `NULL`, JSON invalide → mise à jour refusée avec message rouge) et une `<textarea>` pour `adjacent_zones` (liste brute d'IDs séparés par des virgules, trim automatique, textarea vide → chaîne vide). La mise à jour est atomique avec les colonnes `claimer_controller_id` / `holder_controller_id` existantes.

### Zones cachées persistantes (`zones.is_hidden`)

**`zones.is_hidden`** — booléen sur `{prefix}zones` (défaut `0` / `FALSE`). Quand la valeur vaut `1`, la zone est **cachée à travers tous les tours** aux joueurs non-privilégiés qui n'ont ni la bannière (`holder_controller_id`) ni la revendication (`claimer_controller_id`) sur la zone. Complète — sans les remplacer — les colonnes existantes :

- **`hide_turn_zero`** — cache la zone uniquement au tour 0 (comportement legacy, indépendant).
- **`is_hidden`** — cache la zone en permanence, seulement révélée aux acteurs légitimes.

**Contrat de visibilité (`canControllerSeeZone`, `zones/functions.php`) :**

- GM (`$_SESSION['is_privileged']`) → voit toutes les zones, y compris les cachées.
- Zone non cachée (`is_hidden = 0`) → tout le monde la voit (le filtre `hide_turn_zero` reste actif au tour 0).
- Zone cachée + contrôleur session est **holder** OU **claimer** de la zone → il la voit.
- Sinon → invisible côté display.

**Points d'application :** le filtre est appliqué **au moment du rendu**, jamais dans `getZonesArray`. Deux sites de filtrage seulement :

- `showZoneSelect` (`zones/functions.php`) — dropdown de zones dans `workers/new.php`, `workers/view.php`, `workers/viewAll.php`, `controllers/view.php`, etc.
- `zones/view.php` — page publique des zones (`div.box` par zone).

Les moteurs de fin de tour (`claimMechanic`, `attackMechanic`, `investigateMechanic`, `locationSearchMechanic`, `ressourceGainMechanic`) traitent toutes les zones **sans filtre** : les règles `zone_name`-based et les gains conditionnels calculent silencieusement pour les zones cachées. Les rapports (`workers/view.php`, `controllers/view.php`) exposent les informations que le joueur possède déjà par une voie légitime (agent présent, CKL/CKE, gift-info reçue) — pas de filtre supplémentaire.

**Garde-fou complémentaire :** `createBase` (`controllers/functions.php`) refuse la création d'une base dans une zone cachée non visible par le contrôleur (protection contre les URL forgées), avant même de dépenser les ressources. Le message affiché est `Zone non accessible.`.

**Édition CSV / admin :** la colonne `is_hidden` figure dans l'en-tête des CSV de scénario (`setup{ScenarioName}_zones.csv`, valeur `0` ou `1`) et dans `$fileNames['zones']` (`BDD/db_connector.php`). L'admin `zones/management_zones.php` expose une `<input type="checkbox" name="is_hidden">` par ligne de zone, atomique avec les colonnes existantes.

> **Exemple concret :** dans le scénario Japon1555, la zone `Kai (甲斐)` (fief ancestral des Takedas) porte `is_hidden = 1` avec `Takeda (武田)` comme claimer et holder. Aucun autre joueur ne voit ce territoire ; le clan Takeda et le GM le voient normalement.

### Modes de résolution

#### Famille Interaction entre Agents(workers)

*Section à compléter dans un commit suivant.*  Couvrira : `attack`, `hide`, `pasive`, `investigate`, `gift`, 


##### Actions et flux

*Section à compléter dans un commit suivant.* Couvrira : `continuing_investigate_action` et les comportements de continuité d'actions d'un tour au suivant (l'entrée `continuing_claimed_action` est déjà documentée dans la section Modes de résolution).

#### Famille `claimMode` — résolution des revendications de zone

**`claimMode`** — Détermine comment le système résout les revendications de zone à la fin du tour. Valeurs implémentées :

- **`worker`** *(par défaut, mode A)* — Chaque agent qui revendique tire son propre jet et le compare à la défense de la zone. La zone bascule si un agent dépasse `calculated_defence_val` d'au moins **`DISCRETECLAIMDIFF`** (= 2) points avec son `enquete_val`, ou de **`VIOLENTCLAIMDIFF`** (= 0) points avec son `attack_val`. `calculated_defence_val` suit ici la formule SQL d'origine : `z.defence_val + COUNT(agents du holder dans la zone)` — les agents-doubles comptent pour les deux contrôleurs (primaire et secret), donc contribuent à la défense de leurs deux holders.
- **`worker_leader`** *(mode B)* — Les agents qui revendiquent dans une zone forment un groupe ; le leader (le plus ancien) porte la valeur agrégée du contrôleur. `claim_val` combine plancher **`baseClaim`** (= 0), agents présents (multiplicateur **`baseClaimAddWorkers`** = 1), lieux possédés (**`baseClaimAddOwnedLocations`** = 1), co-revendicateurs (**`baseClaimAddSupporting`** = 1, formule `max(0, COUNT − 1)`, exclut le leader) et un bonus **`claimVisibleToRealBonus`** (= 1) pour la prise de contrôle réel. La défense `calculated_defence_val` suit la formule symétrique **`baseZoneDefence`** + agents + lieux du holder, avec un bonus **`baseZoneDefenceAddSupporting`** (= 1) par agent en action `claim` dans la zone ; ou **`noControllerZoneDefenceBonus`** (= 3) si la zone est libre. La revendication réussit si `claim_val − calculated_defence_val ≥ claimDiff` (= 1). Pas de D6 ; résolution déterministe. Plafonds optionnels : `maxBonusClaim*`, `maxBonusZoneDefence*` (0 = sans plafond).
- **`controller`** *(v2, non implémenté)* — Mode réservé pour une future itération.

Toute autre valeur (faute de frappe, mode futur non développé) désactive le mécanisme de revendication.

**Clés communes à tous les modes :** `continuing_claimed_action` (= 1, l'action reste active au tour suivant), `txt_ps_claim` et `txt_inf_claim` (textes affichés), et les listes d'actions `passiveInvestigateActions` / `activeAttackActions` / `passiveDefenceActions` qui contiennent toutes la valeur `'claim'`.

#### Famille `locationAttackMode` — attaque de lieu (locations)

**`locationAttackMode`** — Détermine où et quand les attaques de lieu (place forte, etc.) sont résolues. Valeurs implémentées :

- **`immediate`** *(par défaut)* — L'attaque est résolue dès le clic du contrôleur, avant la fin du tour. Comparaison : `attack_val − defence_val ≥ attackLocationDiff` (= 1). `attack_val` et `defence_val` sont les valeurs agrégées du contrôleur attaquant et du lieu, calculées via la famille `baseAttack*` (plancher + pouvoirs + agents) et `baseDefence*` (plancher + pouvoirs + agents + âge du lieu via **`baseDefenceAddTurns`** = 0.5, plafonné à **`maxBonusDefenceTurns`** = 3). Lieu sans contrôleur : bonus défensif **`noControllerDefenceBonus`** (= 3).
- **`endTurn`** — L'attaque est mise en file d'attente au clic, avec une prédiction d'issue affichée immédiatement (`attack_val_snapshot` et `defence_val_snapshot`). La résolution effective recalcule `attack_val_resolved` et `defence_val_resolved` en fin de tour, après les attaques entre agents. Les attaques sont résolues dans l'ordre chronologique de mise en file (`ORDER BY id ASC`) : la première attaque réussie détruit la cible et les attaques suivantes contre la même cible échouent avec le texte **`textLocationAttackDestroyed`**. Si la cible est déplacée (`moveBase`) entre la mise en file et la résolution, les attaques en cours sont annulées avec **`textLocationAttackMoved`** (visible uniquement par l'attaquant). Une seule entrée par (attaquant, cible, tour) : toute tentative de double mise en file est rejetée avec « Attaque déjà planifiée ce tour ». La prédiction utilise une bande "Faibles chances" de demi-largeur **`attackLocationOutcomeBandwidth`** (= 2) autour de l'égalité ; en-dehors, on affiche "Échec probable" ou "Réussite probable" via les clés `textLocationAttackOutcomeFail/Weak/Probable`.
- **`worker`** *(v2, non implémenté)* — Mode réservé pour une future itération.

Toute autre valeur désactive le mécanisme d'attaque de lieu.

**Clés communes aux deux modes implémentés :** les familles de formules `baseAttack*`, `baseDefence*`, `baseDiscoveryDiff*` (utilisée aussi pour la découverte de lieux), ainsi que les textes `textLocationDestroyed`, `textLocationPillaged`, `textLocationNotDestroyed`, `textOwnedArtefacts`.

**Spécifique `endTurn` :** textes d'échec d'arrivée `textLocationAttackDestroyed` (cible détruite par une attaque antérieure) et `textLocationAttackMoved` (cible déplacée avant résolution) — visibles uniquement par l'attaquant.

## 3. Recrutement et progression des Agents (workers)

*Section à compléter dans un commit suivant.* Couvrira : `turn_recrutable_workers`, `turn_firstcome_workers`, `first_come_*`, `recrutement_*`, `age_discipline`, `age_transformation`, `owner_knows_own_base_secret`.

### Règles JSON de déverrouillage des disciplines et transformations

La colonne `powers.other` peut contenir un objet JSON dont les clés `on_age` (disciplines), `on_transformation` (transformations) et `on_recrutment` (au moment du recrutement) décrivent les conditions à remplir pour rendre le power éligible. La même grammaire est appliquée par `cleanPowerListFromJsonConditions` au moment de l'affichage du sélecteur pour les trois états. **Re-validation côté commit** : pour `on_age` (`teach_discipline`) et `on_transformation` (`transform`), `workers/action.php` rejoue la vérification au moment de la validation, fermant l'écart entre affichage et commit — un GET forgé qui contournerait la liste filtrée est refusé côté serveur, et le coût ressource éventuel n'est débité que sur ce chemin (transformations seulement). `on_recrutment` reste filtré uniquement à l'affichage : aucun débit de ressource ni revalidation commit ne s'applique au recrutement dans cette version.

**Clés inconnues : fail-closed**. Si une règle référence une clé non listée ci-dessous (typo `controller_has_resource` au lieu de `controller_has_ressource`, ou nouvelle clé non encore implémentée), `evaluateRuleKeysAllMatch` retourne `false` et le power est masqué, plus un log d'erreur. Ajouter une nouvelle clé à la grammaire suppose donc de l'ajouter aussi au whitelist de la fonction.

**Forme d'une règle :**

```json
"on_transformation": {
    "worker_is_alive": "1",
    "controller_has_zone": "Province de Sanuki",
    "OR": [
        {"controller_has_zone": "Cap sud de Tosa"},
        {"controller_has_ressource": {"ressource_name": "Cheval Sanuki", "amount": 1, "consume": true}}
    ]
}
```

- Les **clés directes** (`worker_is_alive`, `controller_has_zone`, etc.) sont combinées en **AND** : toutes doivent être satisfaites.
- Le **bloc `OR`** est un **tableau d'objets** (jamais un objet simple). Chaque sous-objet est une **branche** ; à l'intérieur d'une branche, les clés sont aussi combinées en **AND**. Le tableau est évalué dans l'ordre, en premier-match-gagne : dès qu'une branche est satisfaite, la suivante n'est plus testée.
- Convention d'écriture : pour une règle « zone A OR zone B OR avoir la ressource », utiliser trois branches à une clé `[{A}, {B}, {C}]` plutôt qu'une branche à trois clés.

**Clés disponibles (toutes optionnelles) :**

- **`age`** (int) — l'agent doit avoir au moins cet âge.
- **`worker_is_alive`** (`"0"` ou `"1"`) — `1` exige une action active (move, attack, claim, gift, …), `0` exige une action inactive (passive, hide, dead, …).
- **`unlock_turn`** (int) — disponible **à partir** de ce tour inclus (ex. `5` masque le power aux tours 0 à 4, puis l'affiche dès le tour 5).
- **`controller_faction`** (string) — nom exact de la faction du contrôleur.
- **`controller_has_zone`** (string) — nom de zone que le contrôleur réclame ou détient (claim OR holder).
- **`worker_in_zone`** (string) — nom de zone où l'agent se trouve actuellement.
- **`controller_has_ressource`** (objet) — voir ci-dessous. Honorée en clé directe **et** à l'intérieur d'une branche OR.

**`controller_has_ressource` — porte ressource :**

```json
{"ressource_name": "Koku", "amount": 3, "consume": true}
```

- **`ressource_name`** (string, requis) — nom exact tel qu'apparaissant dans `ressources_config.ressource_name`.
- **`amount`** (int positif, requis) — seuil minimal. Une valeur absente, nulle, négative ou non-entière fait tomber le power au chargement avec un avertissement dans le log d'erreur.
- **`consume`** (bool, optionnel, **défaut : `true`**) — si absent ou `true`, le `amount` est décrémenté atomiquement au commit (porte ET coût). Mettre **explicitement `consume: false`** pour une porte seule (vérification sans coût). Toute autre valeur (chaîne, nombre, etc.) est rejetée comme mal formée.

**Composition direct + OR :**

Une règle peut porter `controller_has_ressource` au niveau direct **et** à l'intérieur d'une branche OR satisfaite. Si les deux décrivent un coût, **le niveau direct prime** et un avertissement « cross-resource cost not supported » est loggé : empiler deux ressources différentes n'est pas supporté en v1. Pour empiler le « coût toujours » (direct) avec un coût optionnel selon le contexte, mettre la branche OR coûtante en dernier et soit assurer une autre branche moins chère en première position, soit accepter le passage forcé par le coût direct.

**Convention OR pour le « gratuit si possédé, payant sinon » :**

L'ordre des branches OR détermine laquelle paye (premier-match-gagne). Pour obtenir « gratuit si je tiens la zone, payant si je dois échanger », placer la branche zone **avant** la branche ressource. L'inverse ferait payer un joueur qui tient la zone ET possède aussi la ressource.

**Chemin admin / gm :**

La validation au commit (re-vérification de la règle + débit ressource) est gardée par `$_SESSION['is_privileged']`. Le compte admin (`gm`) court-circuite tout : il peut accorder n'importe quelle discipline ou transformation à un agent **sans** vérification et **sans** consommer la moindre ressource. C'est une issue de secours volontaire, dans la même lignée que la création directe d'agents ou la modification d'action via la page d'administration.

### Verrou de tour sur les pouvoirs aléatoires (`on_random_pick.unlock_turn`)

Pour empêcher un Métier ou un Hobby d'apparaître trop tôt dans le tirage aléatoire à la création d'un agent, ajoutez `on_random_pick.unlock_turn` dans le JSON du power :

```json
{ "on_random_pick": { "unlock_turn": 2 } }
```

Ici, le power est verrouillé aux tours 0 et 1, puis devient tirable à partir du tour 2. Sans `on_random_pick.unlock_turn`, le power reste tirable dès le début.

Ce verrou concerne seulement le tirage aléatoire des Métiers et Hobbies dans `workers/new.php`. La page admin « Créer agent parfait » garde accès à tous les powers.

La même clé `unlock_turn` peut aussi être utilisée dans les règles `on_age`, `on_transformation` ou `on_recrutment` si un choix manuel doit rester caché avant un certain tour :

```json
{ "on_age": { "unlock_turn": 5 } }
```

Vérifiez simplement qu'il reste toujours au moins un Métier et un Hobby tirables à chaque tour atteignable. Si tous les powers d'un type sont verrouillés, le recrutement ne pourra pas proposer de tirage valide.

## 4. Ressources

Cette section décrit le système économique : coûts d'action, gain forfaitaire de fin de tour, puis gains conditionnels selon l'état du jeu.

**`ressource_management`** (= `TRUE`) — Active tout le module. Si `FALSE`, aucun coût n'est prélevé et aucun gain n'est distribué en fin de tour. Pratique pour les scénarios sans économie.

### Famille `ressources_config` — définition des ressources du scénario

La table `{prefix}ressources_config` définit les types de ressources disponibles. Dans la plupart des scénarios, on utilise une seule ressource (ex. Koku pour Japon1555, Gold pour TestConfig).

- **`ressource_name`** — nom affiché (« Koku », « Gold »).
- **`presentation`** + **`stored_text`** — textes UI avec placeholders `%s %s` (montant + nom de la ressource).
- **`is_rollable`** (= `0` ou `1`) — si `1`, `amount` est conservé d'un tour à l'autre ; si `0`, `amount` est remis à `0` en fin de tour avant `end_turn_gain` et `gain_rules`.
- **`is_stored`** (= `0` ou `1`) — si `1`, le `amount` du tour précédent est ajouté à `amount_stored` (réserve), ce qui sépare budget courant et stock accumulé.
- **`*_cost`** (`base_building_cost`, `base_moving_cost`, `location_repaire_cost`, `servant_first_come_cost`, `servant_recruitment_cost`) — coût soustrait à `amount` quand l'action correspondante est lancée.
- **`gain_rules`** — colonne JSON contenant les règles de gain conditionnel (détaillées ci-dessous).
- **`hide_when_zero`** (= `0` ou `1`, défaut `0`) — si `1`, la ressource est filtrée des pages d'affichage quand le contrôleur n'en a jamais possédé (seuil strict : `amount = 0` ET `amount_stored = 0` ET `end_turn_gain = 0`). Dès qu'une de ces trois colonnes devient non-nulle, la ressource réapparaît normalement. **Échappatoire sur la page « Ressources de la faction » uniquement** : si l'estimation issue de `gain_rules` pour le prochain tour est strictement positive (i.e. le contrôleur tient une zone qui va produire de la ressource), la ligne réapparaît même au seuil 0/0/0 — le joueur peut ainsi anticiper son acquisition. Le bloc « Vos Ressources » du tableau de bord faction (`controllers/view.php`) reste sur le filtre strict pour rester sobre. Cas d'usage : ressources rares et scénario-spécifiques (équipement par zone, devise de niche) qui encombreraient la page pour les contrôleurs qui ne les produisent pas. Le filtre est purement d'affichage : `ressourceGainMechanic`, `giftRessource` et les autres mécaniques mutent toujours `controller_ressources` directement, donc une ressource cachée continue à être réceptionnée silencieusement.

### Famille `controller_ressources` — état par contrôleur

Une ligne par couple `(controller_id, ressource_id)` (combinaison unique). Chaque ligne contient :
- **`amount`** — solde courant.
- **`amount_stored`** — réserve cumulative (utilisée surtout si `is_stored=1`).
- **`end_turn_gain`** — gain forfaitaire ajouté à `amount` à chaque fin de tour (avant les `gain_rules`).

### Famille `gain_rules` — gains conditionnels de fin de tour

Stockées en JSON dans `ressources_config.gain_rules`, ces règles sont évaluées pour chaque contrôleur. Chaque règle ajoute `amount × COUNT(matches)`.

**Exemple minimal :**

```json
{"amount": 100, "timing": "after_claim", "condition": {"type": "holds_zone"}}
```

- **`amount`** — multiplicateur entier. Les règles avec `amount = 0` sont ignorées (no-op). Les valeurs négatives sont autorisées et soustraient au lieu d'ajouter — utile pour configurer des pénalités conditionnelles.
- **`timing`** (`"before_claim"` ou `"after_claim"`) — moment d'application dans la séquence de fin de tour.
- **`unlock_turn`** (int, optionnel) — la règle ne produit rien avant ce tour inclus. Exemple : `1` masque le gain au tour 0, puis l'active dès le tour 1. Sans cette clé, la règle est active dès le début.
- **`condition`** — critère évalué pour le contrôleur. Une règle = un type de condition ; on cumule les effets en ajoutant plusieurs règles.

**Exemple avec verrou de tour :**

```json
{
    "amount": 2,
    "timing": "before_claim",
    "unlock_turn": 1,
    "condition": {"type": "holds_zone", "zone_name": "Côte Est d’Awa"}
}
```

**Types de condition implémentés :**

- **`holds_zone`** — match quand le contrôleur est `zones.holder_controller_id` ; filtres optionnels : `zone_id` (int) **ou** `zone_name` (string).
- **`claims_zone`** — match quand le contrôleur est `zones.claimer_controller_id` ; filtres optionnels : `zone_id` (int) **ou** `zone_name` (string).
- **`owns_location_type`** — match quand le contrôleur est `locations.controller_id`, puis filtrage optionnel AND-combiné via `is_base`, `can_be_destroyed`, `zone_id`, `location_id`, `location_type`.

**`holds_zone` vs `claims_zone` :** avec contrôleurs secrets / doubles agents, le propriétaire réel (`holder`) peut différer de la bannière visible (`claimer`). Choisir la condition selon ce que la récompense doit refléter.

**Agrégation binaire vs comptée :**

- `{type: "holds_zone", zone_id: 5}` → binaire : gain 1 fois si la zone 5 est tenue.
- `{type: "holds_zone", zone_name: "Province de Sanuki"}` → binaire stable au tri du CSV : gain 1 fois si la zone nommée est tenue (préférer `zone_name` à `zone_id` dans les CSV pour éviter le couplage à l'ordre des lignes).
- `{type: "holds_zone"}` → compté : gain multiplié par le nombre de zones tenues.
- `{type: "owns_location_type", is_base: true}` → compté filtré : gain par base possédée.
- `{type: "owns_location_type", location_type: "temple"}` → compté par tag : gain par lieu taggé `temple`.

**Filtres whitelistés pour `owns_location_type`** (tous optionnels, AND-combinés) :

- **`is_base`** (`bool`) — `locations.is_base = 1`.
- **`can_be_destroyed`** (`bool`) — `locations.can_be_destroyed = 1`.
- **`zone_id`** (`int`) — lieu dans une zone spécifique.
- **`location_id`** (`int`) — lieu précis par ID.
- **`location_type`** (`string`) — lieu contenant ce tag dans `locations.location_types` (JSON array).

Les clés hors whitelist sont ignorées silencieusement. Les règles mal formées (JSON invalide, `condition.type` inconnu, `amount` absent) sont ignorées et loggées à l'exécution.

### `locations.location_types` — tags multi-valués

Colonne JSON dans `{prefix}locations` contenant un tableau de tags textuels :

```json
["temple"]
["fortress"]
["temple", "fortress"]
```

Ces tags sont exploités par `owns_location_type` via le filtre `location_type`. Un même lieu peut cumuler plusieurs tags (ex. monastère fortifié = `["temple", "fortress"]`).

**Comportement automatique :** `createBase` ajoute le tag `"fortress"` aux nouvelles bases. Les bases historiques sans tag doivent être complétées dans le CSV du scénario.

### Séquence de fin de tour

Ordre des étapes liées aux ressources :

1. **`updateRessources`** (début de fin de tour, si `ressource_management=TRUE`) :
   1. Si `is_stored=1` : `amount_stored += amount`.
   2. Si `is_rollable=0` : `amount = 0`.
   3. `amount += end_turn_gain`.
   4. Application des règles `gain_rules` avec `timing="before_claim"`.
2. Étapes intermédiaires : `calculateVals` → `attackMechanic` → `recalculateBaseZoneDefence` → `locationAttackMechanic` → `claimMechanic`.
3. **`ressourceGainAfterClaim`** : application des règles `gain_rules` avec `timing="after_claim"`.
4. Étapes restantes : `investigateMechanic`, `locationSearchMechanic`, etc.

### Page « Ressources » et don entre factions

Quand `ressource_management=TRUE`, une entrée **Ressources** s'affiche dans la barre latérale (entre *Agents* et *Les Zones*). Elle pointe vers `ressources/view.php` et propose au contrôleur actif :

- un **résumé** de ses ressources (montant, montant stocké, estimation du gain au prochain tour) ;
- la **liste des règles `gain_rules`** rendues en français avec un compte courant (« +200 par zone tenue × 3 = +600 ») ;
- un **formulaire de don** pour transférer une quantité d'une ressource à une autre faction visible ;
- un panneau **Donations reçues** rappelant les transferts entrants.

Le don passe par `ressources/action.php` qui appelle l'helper `giftRessource()` (dans `ressources/functions.php`). La fonction :

1. valide l'entrée (montant > 0, cible ≠ soi-même, cible non secrète, ressource existante, stock suffisant) ;
2. ouvre une **transaction PDO** ;
3. décrémente le donneur (avec un garde-fou `WHERE amount >= :amt` pour annuler en cas de course) ;
4. incrémente le destinataire (insertion si la ligne `controller_ressources` n'existe pas encore) ;
5. inscrit le transfert dans **`ressource_gift_logs`** (`giver_controller_id`, `recipient_controller_id`, `ressource_id`, `amount`, `turn`, `created_at`) ;
6. commit / rollback complet sur exception.

La page admin `ressources/management.php` reçoit en bas une section **Ressource Transactions** qui liste tous les enregistrements de `ressource_gift_logs` triés du plus récent au plus ancien (utile pour suivre ou enquêter sur les échanges).

## 5. Journal des dons d'informations entre factions

Le système permet à un contrôleur de transmettre à une autre faction visible la connaissance d'un agent (`giftInformationAgent`) ou d'un lieu (`giftInformationLocation`) découvert. Ces actions écrivent directement dans `controllers_known_enemies` / `controller_known_locations` pour donner au destinataire la même connaissance que le donneur.

Pour permettre le suivi et l'enquête sur ces échanges, chaque don exécuté par le chemin joueur (`controllers/action.php`) écrit aussi une ligne dans `information_gift_logs` via l'helper `logInformationGift()` :

- `giver_controller_id` — contrôleur qui a fait le don ;
- `recipient_controller_id` — contrôleur destinataire ;
- `target_type` — `'agent'` ou `'location'` ;
- `target_id` — identifiant dans la table `workers` ou `locations` ;
- `turn` — tour courant ;
- `created_at` — horodatage.

**Note :** le chemin admin (`controllers/management.php`) qui permet au game master de pré-attribuer des connaissances n'écrit *pas* de log — il ne représente pas un échange entre joueurs.

### Panneau « Informations reçues » sur Ma Faction

`controllers/view.php` rend un panneau qui liste les dons reçus par le contrôleur actif via `getInformationGiftsReceived()`. Le helper résout `target_label` via un JOIN sur `workers` (`firstname + lastname`) ou `locations` (`name`). Format affiché :

> <timeValue> <turn> — <giver_name> (<faction>) vous a transmis l'agent / le lieu <target_label>

(le préfixe est la valeur configurée par `timeValue`, par exemple « Tour 12 » ou « Trimestre 12 »)

### Section admin « Information Transactions »

`controllers/management.php` reçoit une section listant tous les transferts (giver, recipient, type, target) triés du plus récent au plus ancien — équivalent au panneau « Ressource Transactions » de la page admin des ressources.

## 6. Débogage

*Section à compléter dans un commit suivant.* Couvrira : `DEBUG`, `DEBUG_REPORT`, `DEBUG_ATTACK`, `DEBUG_TRANSFORM`, `ACTIVATE_TESTS`.
