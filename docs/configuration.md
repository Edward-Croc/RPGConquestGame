# Documentation de configuration

Guide pratique pour préparer et ajuster un scénario : CSV de setup, clés `{prefix}config`, et exemples existants.

## Démarrage rapide — construire un CSV de config

**Public :** organisateurs / admins de soirées enquête, et auteurs de scénarios qui éditent `{prefix}config` (admin live ou CSV de setup).

### Format du fichier

1. Créez `var/csv/setup{NomScenario}_config.csv`.
2. Première ligne exactement :

```csv
name,value,description
```

3. Une ligne par clé à **surcharger**. Tout le reste vient de `minimalData.sql`.
4. Guillemets CSV standards : champs avec virgules ou retours ligne entre guillemets ; guillemets internes doublés (`""`).
5. Les listes d’actions SQL gardent leurs apostrophes : `"'investigate','claim'"`.
6. Les pools de texte sont souvent du JSON dans une cellule : `"[""Phrase %1$s.""]"`.

### Recette minimale

| Besoin | Clés à poser en premier |
|---|---|
| Identité du jeu | `TITLE`, `PRESENTATION`, `IntrigueOrga`, `map_file`, `map_alt` |
| Vocabulaire | `textForZoneType`, `timeValue`, dénominateurs `timeDenominator*` / `controllerNameDenominator*` |
| Mode conquête | `claimMode` (`worker` ou `worker_leader`) |
| Mode attaque de lieu | `locationAttackMode` (`immediate`, `endTurn`, ou `agent_attack_defence`) |
| Économie | `ressource_management` + CSV `*_ressources_config.csv` / `*_controller_ressources.csv` |
| Recrutement | `turn_recrutable_workers`, listes d’origines, `age_discipline` |

### Bonnes pratiques

- Partez d’un CSV existant (TestConfig pour un socle court, Japon1555 pour un scénario riche).
- Ne recopiez pas toutes les clés : surchargez seulement ce qui change le ressenti du scénario.
- Testez un reset admin avec votre `config_name` avant la soirée.
- Pour les règles de zone / tags de lieux / gains, ce sont **d’autres CSV** (`*_zones.csv`, `*_locations.csv`, `*_ressources_config.csv`), pas la table `config`.

**Note de lecture :** les **clés** (`claimMode`, `MINROLL`…) sont dans `{prefix}config`. Les **variables calculées** (`claim_val`, `calculated_defence_val`…) sont recalculées chaque tour — on les cite seulement pour expliquer les formules. Pour les modes énumérés, une valeur inconnue désactive le mécanisme.

## Exemples CSV à télécharger / comparer

Les fichiers vivent sous `var/csv/`. Pour les télécharger ou les vérifier dans l’UI, utilisez uniquement le panneau admin **CSV scénarios** (`base/admin_csv.php`, compte privilégié).

| Scénario | Fichier config | Ressources | Autres tables utiles |
|---|---|---|---|
| **TestConfig** (tests) | `setupTestConfig_config.csv` | `setupTestConfig_ressources_config.csv` | zones, locations, controllers, advanced… |
| **Japon1555CSV** (Shikoku 1555) | `setupJapon1555CSV_config.csv` | `setupJapon1555CSV_ressources_config.csv` | zones (`zone_rules`, `is_hidden`), locations, powers… |
| **Vampire1966CSV** (Firenze 1966) | `setupVampire1966CSV_config.csv` | *(pas de CSV ressources — `ressource_management=FALSE`)* | zones, locations, controllers… |

**En-tête config obligatoire :** `name,value,description`

Les valeurs absentes d’un CSV scénario restent celles de `var/{mysql|postgres}/minimalData.sql` (chargées avant le CSV, puis upsert).

### Comment vérifier une section

1. Ouvrir **Admin → CSV scénarios (download / check)** (`base/admin_csv.php`).
2. Choisir un fichier `*_config.csv` et un `section_key` (ex. `location_attack`).
3. Lire les clés **trouvées** vs **absentes** (souvent OK si le défaut `minimalData` suffit).
4. Télécharger le CSV pour le comparer à votre brouillon local.

## Index des clés par `section_key`

Le champ `section_key` est **uniquement documentaire** : il n’apparaît pas dans les CSV ni dans la table `{prefix}config`.
La carte complète vit dans [`docs/config_section_map.json`](config_section_map.json) ; le panneau admin peut vérifier un CSV config contre une section.

### `texts` — Textes affichés

Titre, présentation, dénominateurs, libellés d'actions, pools de rapports.

Clés : `TITLE`, `PRESENTATION`, `IntrigueOrga`, `basePowerNames`, `map_file`, `map_alt`, `textForZoneType`, `timeValue`, `timeDenominatorThis`, `timeDenominatorThe`, `timeDenominatorOf`, `controllerNameDenominatorThe`, `controllerNameDenominatorOf`, `controllerLastNameDenominatorOf`, `txt_ps_passive`, `txt_ps_investigate`, `txt_ps_hide`, `txt_ps_attack`, `txt_ps_claim`, `txt_ps_attack_location`, `txt_ps_defend_location`, `txt_ps_captured`, `txt_ps_dead`, `txt_ps_prisoner`, `txt_ps_double_agent`, `txt_ps_1p_passive`, `txt_ps_1p_investigate`, `txt_ps_1p_hide`, `txt_ps_1p_attack`, `txt_ps_1p_claim`, `txt_ps_1p_captured`, `txt_ps_1p_dead`, `txt_ps_1p_prisoner`, `txt_ps_1p_double_agent`, `txt_ps_1p_attack_location`, `txt_ps_1p_defend_location`, `txt_inf_passive`, `txt_inf_investigate`, `txt_inf_hide`, `txt_inf_attack`, `txt_inf_claim`, `txt_inf_attack_location`, `txt_inf_defend_location`, `txt_inf_captured`, `txt_inf_dead`, `textesStartInvestigate`, `textesFoundDisciplines`, `textesTransformationDiff1`, `textesTransformationDiff2`, `textesOrigine`, `textesDiff01Array`, `textesDiff01TransformationDiff0Array`, `textesDiff2`, `textesDiff3`, `textesAgentStillHere`, `textesAgentMoved`, `textesAgentUpgradeInfo`, `textesAgentReminderLabel`, `textesLocationStillHere`, `textLocationAgeOriginal`, `textLocationAgeRuined`, `textLocationAgeRestored`, `textLocationAgeLongAgo`, `textLocationAgeThisTurn`, `textLocationAgeTurnsAgo`, `textLocationDiscoveredName`, `textLocationDiscoveredDescription`, `textLocationDiscoveredDestroyable`, `textRecrutementJobHobby`, `textViewWorkerJobHobby`, `textViewWorkerDisciplines`, `textViewWorkerTransformations`, `textControllerActionCreateBase`, `textControllerActionMoveBase`, `textcontrollerRecrutmentNeedsBase`, `texteDescriptionBase`, `texteHiddenFactionBase`, `texteNameBase`, `attackSuccessTexts`, `captureSuccessTexts`, `counterAttackTexts`, `escapeTextes`, `failedAttackTextes`, `unfoundAttackTextes`, `workerCapturedTexts`, `workerDisappearanceTexts`, `textesAttackFailedAndCountered`, `textesClaimFailArray`, `textesClaimFailViewArray`, `textesClaimSuccessArray`, `textesClaimSuccessViewArray`

### `engine` — Moteur de jeu (calculs partagés)

Dés, bonus, listes active/passive, seuils d'enquête et de combat entre agents, difficulté de découverte.

Clés : `MINROLL`, `MAXROLL`, `PASSIVEVAL`, `ENQUETE_ZONE_BONUS`, `ATTACK_ZONE_BONUS`, `DEFENCE_ZONE_BONUS`, `HIDE_ENQUETE_FLAT_BONUS`, `HIDE_DEFENCE_FLAT_BONUS`, `DEFEND_LOCATION_DEFENCE_FLAT_BONUS`, `passiveInvestigateActions`, `activeInvestigateActions`, `passiveAttackActions`, `activeAttackActions`, `passiveDefenceActions`, `activeDefenceActions`, `investigateActionsList`, `investigateOrder`, `REPORTDIFF0`, `REPORTDIFF1`, `REPORTDIFF2`, `REPORTDIFF3`, `LOCATIONNAMEDIFF`, `LOCATIONINFORMATIONDIFF`, `LOCATIONARTEFACTSDIFF`, `ATTACKDIFF0`, `ATTACKDIFF1`, `attackTimeWindow`, `canAttackNetwork`, `RIPOSTACTIVE`, `RIPOSTDIFF`, `LIMIT_ATTACK_BY_ZONE`, `baseDiscoveryDiff`, `baseDiscoveryDiffAddPowers`, `baseDiscoveryDiffAddWorkers`, `baseDiscoveryDiffAddTurns`, `maxBonusDiscoveryDiffPowers`, `maxBonusDiscoveryDiffWorkers`, `maxBonusDiscoveryDiffTurns`, `continuing_investigate_action`, `continuing_attack_action`, `continuing_hide_action`, `continuing_claim_action`, `continuing_attack_location_action`, `continuing_defend_location_action`

### `claim` — Revendications de zone (claimMode)

Mode de résolution des revendications et formules associées.

Clés : `claimMode`, `DISCRETECLAIMDIFF`, `VIOLENTCLAIMDIFF`, `baseClaim`, `baseClaimAddWorkers`, `baseClaimAddOwnedLocations`, `baseClaimAddSupporting`, `maxBonusClaimWorkers`, `maxBonusClaimOwnedLocations`, `claimDiff`, `claimVisibleToRealBonus`, `baseZoneDefence`, `baseZoneDefenceAddWorkers`, `baseZoneDefenceAddOwnedLocations`, `baseZoneDefenceAddSupporting`, `maxBonusZoneDefenceWorkers`, `maxBonusZoneDefenceOwnedLocations`, `noControllerZoneDefenceBonus`

### `location_attack` — Attaque de lieu (locationAttackMode)

Modes immediate / endTurn / agent_attack_defence, formules d'attaque/défense de lieu, textes d'assaut.

Clés : `locationAttackMode`, `attackLocationDiff`, `attackLocationOutcomeBandwidth`, `baseAttack`, `baseAttackAddPowers`, `baseAttackAddWorkers`, `baseDefence`, `baseDefenceAddPowers`, `baseDefenceAddWorkers`, `baseDefenceAddTurns`, `maxBonusDefenceTurns`, `noControllerDefenceBonus`, `locationOverwhelmMode`, `locationOverwhelmValue`, `locationAttackCreditMode`, `textLocationDestroyed`, `textLocationPillaged`, `textLocationNotDestroyed`, `textOwnedArtefacts`, `textLocationAttackQueued`, `textLocationAttackOutcomeFail`, `textLocationAttackOutcomeWeak`, `textLocationAttackOutcomeProbable`, `textLocationAttackResolved`, `textLocationAttackDestroyed`, `textLocationAttackMoved`, `textLocationUnreachable`, `textLocationAssaultOwnerSuccess`, `textLocationAssaultOwnerFail`, `textLocationAssaultAgentSuccess`, `textLocationAssaultAgentFail`, `textLocationDefenceAgentSuccess`, `textLocationDefenceAgentFail`, `textLocationAssaultAgentNoHolder`, `textLocationDefenceAgentNoHolder`, `textLocationAssaultAgentUnengaged`, `textLocationDefenceAgentUnengaged`, `textLocationAgentSpoilsSelf`, `textLocationAgentSpoilsOther`

### `workers` — Recrutement et progression des agents

Slots de recrutement, origines, disciplines, transformations, secrets de base.

Clés : `turn_recrutable_workers`, `turn_firstcome_workers`, `first_come_nb_choices`, `first_come_origin_list`, `recrutement_nb_choices`, `recrutement_origin_list`, `local_origin_list`, `recrutement_disciplines`, `recrutement_transformation`, `age_discipline`, `age_transformation`, `owner_knows_own_base_secret`

### `resources` — Ressources

Activation du module économique. Les types et gains vivent aussi dans ressources_config / controller_ressources (autres CSV).

Clés : `ressource_management`

### `debug` — Débogage

Flags de debug et insertion de valeurs de test.

Clés : `DEBUG`, `DEBUG_REPORT`, `DEBUG_ATTACK`, `DEBUG_TRANSFORM`, `ACTIVATE_TESTS`

## Référence détaillée

Les sections suivantes détaillent le comportement. Chaque bloc porte un `section_key` pour croiser l’index et le panneau CSV.

### `texts` — Textes affichés

*Section à compléter dans un commit suivant.* Couvrira : `TITLE`, `PRESENTATION`, `IntrigueOrga`, `basePowerNames`, les familles `txt_ps_*` et `txt_inf_*`, les dénominateurs (`controllerNameDenominator*`, `timeDenominator*`), `textForZoneType`, `timeValue`, `map_file`, `map_alt`.

### `engine` — Moteur de jeu (calculs partagés)

Cette section couvre les clés qui pilotent les calculs partagés entre tous les modes : valeurs de base des actions d'agent, bonus contextuels, listes d'actions, seuils de découverte, combat entre agents et difficulté des places fortes. Ces clés s'appliquent quelles que soient les valeurs de `claimMode` et `locationAttackMode`.

### Dés et valeurs d'action

**`MINROLL`** (= 1) et **`MAXROLL`** (= 6) — Bornes inclusives du jet aléatoire utilisé pour calculer `enquete_val`, `attack_val` et `defence_val` des agents en action active. Le tirage est uniforme sur `[MINROLL, MAXROLL]`. Un intervalle plus large produit plus d'imprévisibilité ; plus étroit rend pouvoirs et bonus dominants.

**`PASSIVEVAL`** (= 3) — Valeur fixe utilisée à la place du jet pour les actions passives. Un agent qui surveille (`passive`) ou se cache (`hide`) ne tire pas de dé ; il reçoit cette valeur sur les axes où son action est considérée comme passive. Régler `PASSIVEVAL` près de la moyenne des dés (`(MINROLL + MAXROLL) / 2`) garde les actions passives compétitives.

### Bonus de contrôle de zone et bonus d'action

**`ENQUETE_ZONE_BONUS`** (= 0), **`ATTACK_ZONE_BONUS`** (= 0), **`DEFENCE_ZONE_BONUS`** (= 1) — Bonus ajoutés à `enquete_val`, `attack_val` et `defence_val` d'un agent dont le contrôleur détient (holder) la zone où l'agent se trouve. Par défaut, seule la défense profite du contrôle de zone. Augmenter `ATTACK_ZONE_BONUS` rend la conquête plus stratégique.

**`HIDE_ENQUETE_FLAT_BONUS`** (= 4), **`HIDE_DEFENCE_FLAT_BONUS`** (= 1) — Bonus plats ajoutés à `enquete_val` et `defence_val` quand l'agent choisit l'action `hide`. L'action « se cacher » renforce la défense de l'agent et complique sa détection par les enquêtes ennemies (la valeur d'enquête sert alors de résistance, pas d'investigation), au prix de ne pas attaquer ce tour.

**`DEFEND_LOCATION_DEFENCE_FLAT_BONUS`** (= 1) — Bonus plat ajouté à `defence_val` quand l'agent choisit `defend_location`. Défendre un lieu est une action de défense **passive** : l'agent ne tire pas de dé, il reçoit `PASSIVEVAL` plus ce bonus, ce qui rend la défense d'une place forte fiable plutôt qu'aléatoire. Le nom de la clé suit le gabarit `{ACTION}_{AXE}_FLAT_BONUS` que `calculateVals` construit ; une clé absente vaut zéro, ce qui est le cas de toutes les autres combinaisons action/axe.

#### Listes d'actions actives et passives

Les six clés suivantes ne pilotent **que le calcul des valeurs** `enquete_val`, `attack_val` et `defence_val` de chaque agent en début de tour. Pour chaque axe (enquête, attaque, défense), l'`action_choice` choisi par l'agent détermine si la valeur correspondante est obtenue par un **jet de dé aléatoire** (action listée comme `active`) ou par la **valeur fixe `PASSIVEVAL`** (action listée comme `passive`). Une action absente des deux listes d'un axe donne `0` sur cet axe.

> **Important :** ces listes ne déterminent **pas** quels agents effectuent réellement une enquête, une attaque ou une défense — ces comportements sont pilotés par d'autres clés. Par exemple, la recherche d'agents ennemis n'est exécutée que pour les `action_choice` listés dans **`investigateActionsList`** (= `'passive','investigate','defend_location'`). Cette clé est indépendante des six listes ci-dessous.

- **`passiveInvestigateActions`** (= `'passive','attack','captured','hide','attack_location','defend_location'`) — Actions dont la valeur d'enquête est `PASSIVEVAL`. Les deux actions de lieu y figurent, ce qui donne à leurs agents un `enquete_val` réel — c'est lui qui sert d'initiative au combat de lieu.
- **`activeInvestigateActions`** (= `'investigate','claim'`) — Actions dont la valeur d'enquête est tirée aléatoirement entre `MINROLL` et `MAXROLL` inclus.
- **`passiveAttackActions`** (= `'passive','investigate','hide','defend_location'`) — Actions dont la valeur d'attaque est `PASSIVEVAL` (utilisée pour les ripostes).
- **`activeAttackActions`** (= `'attack','claim','attack_location'`) — Actions dont la valeur d'attaque est tirée aléatoirement entre `MINROLL` et `MAXROLL` inclus.
- **`passiveDefenceActions`** (= `'passive','investigate','attack','claim','captured','hide','attack_location','defend_location'`) — Actions dont la valeur de défense est `PASSIVEVAL`. `defend_location` y figure et reçoit en plus `DEFEND_LOCATION_DEFENCE_FLAT_BONUS`.
- **`activeDefenceActions`** (= `''`, vide par défaut) — Actions dont la valeur de défense est tirée aléatoirement entre `MINROLL` et `MAXROLL` inclus. Vide signifie qu'aucune action ne fait tirer un dé de défense : toutes les valeurs de défense sont fixes.

Format attendu : chaîne SQL `'action1','action2',...` avec apostrophes incluses. L'action `claim` apparaît dans les deux axes actifs (`enquete` et `attack`) — c'est volontaire : revendiquer génère un jet pour les deux valeurs, ce qui rend l'agent compétitif quand le `claimMode='worker'` les compare à la défense de la zone.

### Seuils de découverte d'information

**`REPORTDIFF0`** (= -1), **`REPORTDIFF1`** (= 1), **`REPORTDIFF2`** (= 2), **`REPORTDIFF3`** (= 4) — Seuils progressifs de différence `enquete_val − target_defence_val` pour révéler les niveaux d'information dans un rapport d'enquête sur un agent : nom et action (niveau 0, accessible dès `≥ REPORTDIFF0`), capacités aléatoires (niveau 1), capacités du contrôleur et numéro de réseau (niveau 2), nom du contrôleur dominant (niveau 3). Le niveau 0 négatif (-1) signifie que l'information passe même avec un léger déficit ; mettre `REPORTDIFF0 = 1` conditionnerait toute découverte à un avantage net.

**`LOCATIONNAMEDIFF`** (= 0), **`LOCATIONINFORMATIONDIFF`** (= 1), **`LOCATIONARTEFACTSDIFF`** (= 2) — Seuils de différence `enquete_val − discovery_diff` pour les niveaux de découverte d'un lieu secret : nom du lieu, description / informations secrètes, présence d'artefacts récupérables. Augmenter ces seuils rend les enquêtes de zone moins rentables.

> **Important :** la recherche d'information (rapports d'enquête sur agents ennemis et découverte de lieux secrets) n'est effectuée que pour les actions listées dans **`investigateActionsList`** (= `'passive','investigate','defend_location'`). Le filtre est appliqué dans `mechanics/investigateMechanic.php` (agents) et `mechanics/locationSearchMechanic.php` (lieux).

### Réduction de la redondance des rapports d'enquête

Quand un enquêteur redécouvre un agent ou un lieu déjà connu de son contrôleur (via `controllers_known_enemies` / `controller_known_locations`), le rapport bascule sur une variante condensée — un résumé visible et le détail complet replié dans un widget `<details>` — au lieu de répéter les mêmes slabs. Les artefacts trouvés restent toujours affichés en dehors du repli.

**`investigateOrder`** (= `'asc'`) — Ordre de traitement des enquêteurs au sein d'un même contrôleur. `'asc'` (défaut) : les enquêteurs à faible `enquete_val` traitent leur cible en premier, ce qui laisse une chance à chaque enquêteur de découvrir une information avant qu'un collègue mieux équipé ne sature les `controllers_known_enemies`. `'desc'` : ordre inverse (les forts révèlent tout, les faibles voient principalement des « déjà connus »). Le tri est appliqué directement dans le SQL de `getSearcherComparisons` / `getLocationSearcherComparisons`. Toute valeur hors liste blanche retombe sur `'asc'`.

**Templates de variantes** (tableaux JSON ou chaînes simples — un élément suffit, plusieurs entrées sont tirées au hasard) :

- **`textesAgentStillHere`** (= `["L'agent %1$s est toujours présent dans ce %2$s."]`) — résumé `<summary>` quand un agent connu est revu dans la même zone sans nouvelle information. `%1$s` = nom de l'agent, `%2$s` = valeur du config `textForZoneType` (par exemple « territoire », « quartier »).
- **`textesAgentMoved`** (= `["L'agent %1$s, repéré précédemment dans %2$s, s'est déplacé ici."]`) — résumé quand `controllers_known_enemies.zone_id` diffère de la zone d'observation. `%1$s` = nom, `%2$s` = zone précédente.
- **`textesAgentUpgradeInfo`** (= `["Nous avons obtenu de nouvelles informations concernant %1$s :"]`) — en-tête visible quand l'enquête courante atteint un niveau `DIFF` supérieur à ce qui était déjà connu ; les slabs nouveaux apparaissent ensuite en clair, les anciens sont repliés.
- **`textesAgentReminderLabel`** (= `Rappel des informations connues`) — étiquette du `<summary>` qui replie les slabs déjà connus dans la variante « upgrade ».
- **`textesLocationStillHere`** (= `["Le lieu %1$s est toujours là."]`) — résumé pour un lieu déjà répertorié dans `controller_known_locations` sans nouvelle découverte. `%1$s` = nom du lieu.

#### Ancienneté d'un lieu

Chaque rapport de découverte de lieu se termine par une phrase d'ancienneté, construite en deux morceaux par `buildLocationAgeSentence` (`mechanics/locationSearchMechanic.php`) : un **verbe d'état**, puis une **locution d'âge**. Parce qu'elle divulgue l'**état** du lieu, elle est apposée au palier description (`LOCATIONINFORMATIONDIFF`), au même rang que `textLocationDiscoveredDestroyable` : un enquêteur qui n'atteint que `LOCATIONNAMEDIFF` obtient le nom seul, et en dessous il ne voit rien du tout.

L'état est lu sur `{prefix}locations.is_updated_location` combiné à `can_be_repaired` :

| `is_updated_location` | `can_be_repaired` | Clé utilisée |
|---|---|---|
| `0` | — | `textLocationAgeOriginal` — le lieu n'a jamais changé d'état |
| `1` | `1` | `textLocationAgeRuined` — en ruine, en attente de réparation |
| `1` | `0` | `textLocationAgeRestored` — ruiné puis relevé |

- **`textLocationAgeOriginal`** (= `["Ce.tte %1$s a été construit.e %2$s."]`)
- **`textLocationAgeRuined`** (= `["Ce.tte %1$s a été détruit.e par une attaque %2$s."]`)
- **`textLocationAgeRestored`** (= `["Ce.tte %1$s a été relevé.e de ses ruines %2$s."]`)

Pour ces trois clés, `%1$s` = nom du lieu et `%2$s` = la locution d'âge ci-dessous.

L'âge est lu sur `setup_turn`, avec une sentinelle : le tour `0` se lit « depuis toujours » **sauf** si le lieu a réellement changé d'état.

| `setup_turn` | `is_updated_location` | Lecture |
|---|---|---|
| `0` | `0` | `textLocationAgeLongAgo` |
| `0` | `1` | datée — `ThisTurn` au tour du changement, puis `TurnsAgo` |
| `> 0` | — | datée, par l'écart au tour courant |

- **`textLocationAgeLongAgo`** (= `["il y a des années"]`) — aucun placeholder.
- **`textLocationAgeThisTurn`** (= `["ce %1$s"]`) — `%1$s` = `timeValue`.
- **`textLocationAgeTurnsAgo`** (= `["il y a %1$d %2$s"]`) — `%1$d` = nombre de tours écoulés, `%2$s` = `timeValue`.

Un pool absent ou illisible produit une phrase vide et une entrée `warning` au journal, sans casser le rapport.

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

**`{prefix}locations.setup_turn` et `{prefix}locations.is_updated_location`** — `setup_turn` porte le tour de la dernière mise en place du lieu. Il est estampillé par `createBase` (construction), `updateLocation` (tout changement d'état) et `moveBase` (déménagement) ; les lieux de décor seedés restent à `0`, ce qui leur conserve l'écart maximal. `is_updated_location` vaut `false` à la construction et passe à `true` au premier changement d'état : seul `updateLocation` le lève, un déménagement ne le touche pas.

Conséquence d'équilibrage : le terme d'ancienneté alimente **deux** calculs, `Defence` et `DiscoveryDiff`. Un changement d'état remet donc l'ancienneté à zéro **sur les deux axes** — un lieu fraîchement construit, ruiné ou déplacé est à la fois plus fragile et plus facile à découvrir, jusqu'à ce qu'il ait de nouveau vieilli.

### Connaissance de ses propres lieux (seed CKL au chargement)

À la fin de chaque chargement de scénario, `gameReady` (`BDD/db_connector.php`) complète `{prefix}controller_known_locations` pour **tout lieu portant un `controller_id`**, base ou non. Un propriétaire connaît donc la description de l'intégralité de ce qu'il possède, et ses ruines possédées apparaissent dans la liste déroulante « Réparer un lieu » — alimentée par `listControllerKnownLocations`, donc par les lieux *connus*.

Le `found_secret` inséré est **conditionnel** :

| Lieu possédé | `found_secret` |
|---|---|
| `is_base = 1` | selon **`owner_knows_own_base_secret`** |
| `is_base = 0` | `false`, toujours |

La clé garde ainsi exactement la portée que son nom annonce : elle ne parle que des bases. Le secret (`hidden_description`) d'un lieu possédé non-base reste à découvrir par l'enquête, comme pour n'importe quel autre lieu.

Le seed est idempotent (`NOT EXISTS`) : il n'écrase jamais une ligne existante, et ne rétrograde donc pas un `found_secret` déjà acquis. En cours de partie, `createBase` et `moveBase` couvrent la création et le déménagement via `addLocationToCKL`.

#### Quand le secret d'un lieu est-il montré ?

Le `found_secret` ci-dessus décide ce que la base **stocke** ; six chemins décident ce qu'un joueur **lit**. Tous appliquent désormais la même règle : un secret ne se montre que si le lecteur l'a acquis.

| Chemin | Condition | Où |
|---|---|---|
| Ses propres lieux, groupés par zone | `is_base` **et** `owner_knows_own_base_secret`, **ou** `found_secret` en CKL | `listControllerLinkedLocations` (`zones/functions.php`) |
| « Vos lieux secrets » d'une zone | délègue au précédent | `showcontrollerKnownSecrets` |
| Aperçu de sa propre base | `owner_knows_own_base_secret` | `controllers/view.php` |
| Lieux connus non possédés | `found_secret` en CKL | `listControllerKnownLocations` |
| Bases ennemies connues d'une zone | `found_secret` en CKL | `showcontrollerKnownSecrets` |
| Rapport d'enquête | palier artefacts (`LOCATIONARTEFACTSDIFF`) | `locationSearchMechanic` |

Deux conséquences valent d'être connues.

**Posséder un lieu ne révèle pas son secret.** Seule une base le fait, et seulement si `owner_knows_own_base_secret` est à `TRUE`. Pour tout autre lieu possédé, le propriétaire doit le découvrir par l'enquête comme n'importe qui — c'est ce que `found_secret = false` du seed exprime.

**Le filtrage se fait en SQL là où c'est possible.** `listControllerLinkedLocations` renvoie une chaîne vide plutôt que le secret, qui ne quitte donc pas la base quand il n'est pas autorisé. Les autres chemins filtrent en PHP après lecture.

La page d'administration `zones/management_locations.php` affiche tous les secrets sans condition : elle est réservée aux sessions `is_privileged` (voir #121).

### Règles de modification contextuelles (`zones.zone_rules`)

**`zones.zone_rules`** — colonne JSON nullable de la table `{prefix}zones` portant des règles qui ajustent les valeurs de calcul d'un contrôleur sur cette zone. Deux **formes** de règles cohabitent :

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
    "Attack":        [ /* mêmes formes */ ],
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

**Forme spécifique — `zone_name` :**

- **`zone_name`** (string, requis) — nom exact de la zone à évaluer. Résolu par lookup SQL sur `zones.name`. **Pas de contrainte d'adjacence** : la zone peut se trouver n'importe où sur la carte.
- Se déclenche **au plus une fois** (une seule zone évaluée).

**Forme itérateur — `adjacent_zones: true` :**

- **`adjacent_zones`** (bool, requis, doit valoir `true`) — bascule la règle en mode itérateur sur la liste `adjacent_zones` de la zone porteuse.
- Se déclenche **une fois par voisine satisfaisant la condition** : les `value_delta` s'accumulent (un bonus `+1` avec 3 voisines détenues donne `+3`).

**Comment choisir le type de règle :** la présence de `zone_name` ou de `adjacent_zones: true` détermine la forme. Une règle qui a **les deux** ou **aucun des deux** est ignorée avec un `error_log`.

**Combinaison additive :** toutes les règles satisfaites (des deux formes) contribuent au résultat final : `base_value + Σ(value_delta pour chaque règle satisfaite)`. L'ordre des règles dans le tableau n'est pas significatif.

**Si une règle est cassée (comportement tolérant) :**

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

**Où c'est appliqué dans le code :** l'ajustement est appliqué à la fin de `calculateControllerValue` (`zones/functions.php`), **après** tous les autres termes du calcul (base, zone_control, powers, workers, owned_locations, supporting, turns). La fonction `applyZoneRules` dispatche chaque règle vers `applyZoneRuleSpecific` (pour `zone_name`) ou `applyZoneRuleAdjacent` (pour `adjacent_zones: true`), puis cumule les `value_delta` pertinents.

> **Exemple concret :** dans le scénario Japon1555, `Cité impériale de Kyōto` porte deux règles `Claim` avec `zone_name: "Plaines du Kansai"` (`-4` si l'acteur ne détient pas les plaines, `+2` s'il les détient). Un prétendant doit donc établir sa présence dans les plaines avant d'espérer conquérir la capitale.

**Édition CSV / admin :** la colonne est chargée depuis les CSV de scénario (`setup{ScenarioName}_zones.csv`) via `db_connector.php`. Le JSON doit être valide et échappé selon les règles CSV (guillemets internes doublés). Une interface d'édition admin est également disponible sur `zones/management_zones.php` : chaque ligne de zone expose une `<textarea>` pour `zone_rules` (JSON, textarea vide → `NULL`, JSON invalide → mise à jour refusée avec message rouge) et une `<textarea>` pour `adjacent_zones` (liste brute d'IDs séparés par des virgules, trim automatique, textarea vide → chaîne vide). La mise à jour est atomique avec les colonnes `claimer_controller_id` / `holder_controller_id` existantes.

### Zones cachées persistantes (`zones.is_hidden`)

**`zones.is_hidden`** — booléen sur `{prefix}zones` (défaut `0` / `FALSE`). Quand la valeur vaut `1`, la zone est **cachée à travers tous les tours** aux joueurs non-privilégiés qui n'ont ni la bannière (`holder_controller_id`) ni la revendication (`claimer_controller_id`) sur la zone. Complète — sans les remplacer — les colonnes existantes :

- **`hide_turn_zero`** — cache la zone uniquement au tour 0 (comportement legacy, indépendant).
- **`is_hidden`** — cache la zone en permanence, seulement révélée aux acteurs légitimes.

**Règles de visibilité (`canControllerSeeZone`, `zones/functions.php`) :**

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

*Section à compléter dans un commit suivant.* Couvrira : `continuing_investigate_action` et les comportements de continuité d'actions d'un tour au suivant (l'entrée `continuing_claim_action` est déjà documentée dans la section Modes de résolution).

### `claim` — Famille `claimMode` — résolution des revendications de zone

**`claimMode`** — Détermine comment le système résout les revendications de zone à la fin du tour. Valeurs implémentées :

- **`worker`** *(par défaut, mode A)* — Chaque agent qui revendique tire son propre jet et le compare à la défense de la zone. La zone bascule si un agent dépasse `calculated_defence_val` d'au moins **`DISCRETECLAIMDIFF`** (= 2) points avec son `enquete_val`, ou de **`VIOLENTCLAIMDIFF`** (= 0) points avec son `attack_val`. `calculated_defence_val` suit ici la formule SQL d'origine : `z.defence_val + COUNT(agents du holder dans la zone)` — les agents-doubles comptent pour les deux contrôleurs (primaire et secret), donc contribuent à la défense de leurs deux holders.
- **`worker_leader`** *(mode B)* — Les agents qui revendiquent dans une zone forment un groupe ; le leader (le plus ancien) porte la valeur agrégée du contrôleur. `claim_val` combine plancher **`baseClaim`** (= 0), agents présents (multiplicateur **`baseClaimAddWorkers`** = 1), lieux possédés (**`baseClaimAddOwnedLocations`** = 1), co-revendicateurs (**`baseClaimAddSupporting`** = 1, formule `max(0, COUNT − 1)`, exclut le leader) et un bonus **`claimVisibleToRealBonus`** (= 1) pour la prise de contrôle réel. La défense `calculated_defence_val` suit la formule symétrique **`baseZoneDefence`** + agents + lieux du holder, avec un bonus **`baseZoneDefenceAddSupporting`** (= 1) par agent en action `claim` dans la zone ; ou **`noControllerZoneDefenceBonus`** (= 3) si la zone est libre. La revendication réussit si `claim_val − calculated_defence_val ≥ claimDiff` (= 1). Pas de D6 ; résolution déterministe. Plafonds optionnels : `maxBonusClaim*`, `maxBonusZoneDefence*` (0 = sans plafond).
- **`controller`** *(v2, non implémenté)* — Mode réservé pour une future itération.

Toute autre valeur (faute de frappe, mode futur non développé) désactive le mécanisme de revendication.

**Clés communes à tous les modes :** `continuing_claim_action` (= 1, l'action reste active au tour suivant), `txt_ps_claim` et `txt_inf_claim` (textes affichés), et les listes d'actions `passiveInvestigateActions` / `activeAttackActions` / `passiveDefenceActions` qui contiennent toutes la valeur `'claim'`.

### `location_attack` — Famille `locationAttackMode` — attaque de lieu (locations)

**`locationAttackMode`** — Détermine où et quand les attaques de lieu (place forte, etc.) sont résolues. Valeurs implémentées :

- **`immediate`** *(par défaut)* — L'attaque est résolue dès le clic du contrôleur, avant la fin du tour. Comparaison : `attack_val − defence_val ≥ attackLocationDiff` (= 1). `attack_val` et `defence_val` sont les valeurs agrégées du contrôleur attaquant et du lieu, calculées via la famille `baseAttack*` (plancher + pouvoirs + agents) et `baseDefence*` (plancher + pouvoirs + agents + âge du lieu via **`baseDefenceAddTurns`** = 0.5, plafonné à **`maxBonusDefenceTurns`** = 3). Lieu sans contrôleur : bonus défensif **`noControllerDefenceBonus`** (= 3).
- **`endTurn`** — L'attaque est mise en file d'attente au clic, avec une prédiction d'issue affichée immédiatement (`attack_val_snapshot` et `defence_val_snapshot`). La résolution effective recalcule `attack_val_resolved` et `defence_val_resolved` en fin de tour, après les attaques entre agents. Les attaques sont résolues dans l'ordre chronologique de mise en file (`ORDER BY id ASC`) : la première attaque réussie détruit la cible et les attaques suivantes contre la même cible échouent avec le texte **`textLocationAttackDestroyed`**. Si la cible est déplacée (`moveBase`) entre la mise en file et la résolution, les attaques en cours sont annulées avec **`textLocationAttackMoved`** (visible uniquement par l'attaquant). Une seule entrée par (attaquant, cible, tour) : toute tentative de double mise en file est rejetée avec « Attaque déjà planifiée ce tour ». La prédiction utilise une bande "Faibles chances" de demi-largeur **`attackLocationOutcomeBandwidth`** (= 2) autour de l'égalité ; en-dehors, on affiche "Échec probable" ou "Réussite probable" via les clés `textLocationAttackOutcomeFail/Weak/Probable`.
- **`agent_attack_defence`** *(v2, cf. issue #73)* — Les contrôleurs ne peuvent plus attaquer directement les lieux (les boutons `Attaquer` et le bouton "Mener une équipe d'attaque sur place" côté zone sont cachés). Les agents choisissent `Attaquer le lieu` ou `Défendre le lieu` pour un lieu de leur zone courante ; un déplacement remet l'action à passif et efface la cible. En fin de tour, pour chaque lieu ciblé :
  - **Initiative.** Attaquants et défenseurs sont triés par `enquete_val` décroissant, avec `worker_id` croissant pour départager les égalités — le même ordre que `attackMechanic` applique déjà à ses propres listes. Les agents les plus perspicaces engagent les premiers.
  - **Résolution en échelle séquentielle**, et non en produit cartésien : un attaquant enchaîne les défenseurs tant qu'il tue, un défenseur encaisse tant qu'il survit, et un attaquant qui échoue sans mourir est consommé. La séquence s'arrête dès qu'un camp est épuisé. Chaque duel passe par `resolveWorkerCombat` — élimination via `ATTACKDIFF0`, capture via `ATTACKDIFF1`, riposte via `RIPOSTACTIVE` / `RIPOSTDIFF` — donc un défenseur capturé change de camp exactement comme dans un duel d'agents. Un agent tué plus tôt dans le tour par une attaque ordinaire ne rejoint pas l'échelle.
  - **Allégeance.** Un agent double envoyé contre un lieu que possède son maître secret (son lien `controller_worker` secondaire) n'arrive jamais : il est retiré des attaquants, ne compte dans aucun camp, et reçoit **`textLocationUnreachable`** à son rapport.
  - **Verdict.** Le lieu tombe quand les attaquants survivants dépassent **strictement** un seuil calculé sur les défenseurs survivants : **`locationOverwhelmMode`** (= `multipliby`) choisit la forme — `multipliby` → `défenseurs × locationOverwhelmValue`, `morethan` → `défenseurs + locationOverwhelmValue` — et **`locationOverwhelmValue`** (= 2) en donne l'opérande. La comparaison stricte règle aussi le cas dégénéré : personne contre personne, le lieu tient. Avec les valeurs par défaut, il faut donc trois attaquants survivants contre un défenseur survivant.
  - Chaque duel est tracé dans `worker_combat_logs` avec son lieu, consultable et filtrable sur la page d'administration *Agent combat log*.


Toute autre valeur désactive le mécanisme d'attaque de lieu.

**Où atterrit le butin — vaut pour les trois modes.** Quand une attaque de lieu réussit, `captureLocationsArtefacts()` (`controllers/functions.php`) déplace les artefacts du lieu pris vers un lieu du vainqueur. La destination n'est **pas** sa base : c'est le premier de ses lieux **destructibles**, trié par `discovery_diff` décroissant puis par `id` croissant — donc le plus difficile à découvrir d'abord.

Deux conséquences à connaître :

- un contrôleur qui ne possède **aucun** lieu destructible ne peut rien emporter. En mode `agent_attack_defence` le lieu reste alors debout et l'attaque est journalisée en échec, même si le combat a été gagné — c'est l'écart entre `falls` et `taken` décrit plus bas.
- le critère a changé : il était `is_base = TRUE`. Les scénarios où un contrôleur possède plusieurs lieux destructibles voient donc les prisonniers arriver ailleurs que dans sa base, y compris dans les modes `immediate` et `endTurn`.

**Clés communes aux trois modes implémentés :** les familles de formules `baseAttack*`, `baseDefence*`, `baseDiscoveryDiff*` (utilisée aussi pour la découverte de lieux), ainsi que les textes `textLocationDestroyed`, `textLocationPillaged`, `textLocationNotDestroyed`, `textOwnedArtefacts`.

**Spécifique `endTurn` :** textes d'échec d'arrivée `textLocationAttackDestroyed` (cible détruite par une attaque antérieure) et `textLocationAttackMoved` (cible déplacée avant résolution) — visibles uniquement par l'attaquant.

**Spécifique `agent_attack_defence` :** **`locationOverwhelmMode`** (= `multipliby`, valeurs `morethan` | `multipliby` ; toute autre valeur retombe sur `multipliby`) et **`locationOverwhelmValue`** (= 2) pour le verdict de prise, plus **`textLocationUnreachable`** pour l'agent double dont le maître secret possède la cible — liste JSON de formulations, tirée au sort comme les autres pools de texte, avec repli sur une phrase en dur si le JSON est invalide. Les seuils de duel sont ceux du combat entre agents : `ATTACKDIFF0`, `ATTACKDIFF1`, `RIPOSTACTIVE`, `RIPOSTDIFF`.

**Le nom du lieu ciblé est révélé à l'enquêteur — voulu.** Quand un enquêteur repère un agent dont l'action est `attack_location` ou `defend_location`, le rapport nomme le lieu visé (`mechanics/investigateMechanic.php`), **sans vérifier** que le contrôleur de l'enquêteur ait jamais découvert ce lieu. Voir un agent donner l'assaut renseigne sur ce qu'il assaille.

Deux limites qui bornent la fuite :

- le nom seul est divulgué, jamais la description, le secret ni les artefacts ;
- **aucune entrée n'est écrite dans `controller_known_locations`** : c'est une mention ponctuelle dans un rapport, pas une découverte. Les paliers `LOCATIONNAMEDIFF` / `LOCATIONINFORMATIONDIFF` / `LOCATIONARTEFACTSDIFF` restent entièrement à franchir par l'enquête de lieu.

Le nom passe par `htmlspecialchars` avant affichage.

**Rapports d'attaque de lieu.** Deux familles de pools, distinguées par leur **destinataire** — c'est ce que porte le segment `Owner` / `Agent` de leur nom. Ne pas les confondre avec `textLocationAttackOutcome*`, qui sont les **prédictions** affichées à l'attaquant avant résolution.

- **`textLocationAssaultOwnerSuccess`** / **`textLocationAssaultOwnerFail`** — vus par le **propriétaire** du lieu attaqué. `%1$s` = nom du lieu, `%2$s` = les assaillants, formulés selon `locationAttackCreditMode`. Seedés dans les deux `minimalData.sql`.
- **`textLocationAssaultAgentSuccess`** / **`textLocationAssaultAgentFail`** et **`textLocationDefenceAgentSuccess`** / **`textLocationDefenceAgentFail`** — vus par les **agents** ayant participé à l'assaut ou à la défense. `%1$s` = nom du lieu, `%2$s` = nom de la zone, `%3$s` = un troisième argument **qui diffère selon la famille** (voir ci-dessous). Seedés uniquement par les fichiers de scénario, pas par `minimalData.sql` : un scénario qui les omet obtient un pool nul, journalisé en `warning`, et le rapport correspondant est vide ou retombe sur un gabarit en dur selon le mode.

**`locationAttackCreditMode`** (= `networks`) — Détermine comment les assaillants sont nommés dans le texte du propriétaire. `networks` : liste les numéros de réseau attaquants. `agents` : nomme chaque agent, en n'attribuant un réseau que pour ceux que le propriétaire a déjà identifiés. Toute autre valeur retombe sur `networks`.

##### Rapports d'agent en mode `agent_attack_defence`

En mode `agent_attack_defence`, l'assaut est mené par des agents et non par un contrôleur : chaque participant reçoit donc son propre compte rendu de l'issue de l'assaut, écrit par `writeLocationAgentReports()` (`mechanics/locationAttackMechanic.php`). Ces lignes sont rangées dans la clé de rapport **`location_attack_report`**, affichée sur la fiche d'agent sous le titre « Attaque de lieu : » — séparément des duels eux-mêmes, qui restent dans `attack_report`. Avant cette séparation, les duels de lieu se mélangeaient aux attaques ordinaires et aucun participant n'apprenait comment l'assaut s'était terminé.

**Qui écrit quoi.** Deux verdicts distincts pilotent le choix du pool, et c'est leur écart qui justifie l'existence des pools `NoHolder` :

- `falls` — le **verdict de l'échelle de duels** : les attaquants survivants dépassent le seuil `locationOverwhelm*`. C'est la victoire au combat.
- `taken` — l'assaut a **réellement produit son effet** sur la place : `falls` est vrai **et** `rankLocationSpoilsControllers` a trouvé un réseau assaillant capable d'héberger le butin, c'est-à-dire possédant au moins un lieu `can_be_destroyed` où mener les prisonniers. Sans cette destination, le butin ne bouge pas, aucun effet n'est appliqué au lieu, et la place reste debout malgré la défaite de ses défenseurs (une entrée `warning` est journalisée).

À noter : `taken` ne veut pas dire « détruite ». Un lieu indestructible (`can_be_destroyed` à faux, ou `indestructible` dans son `activate_json`) est **pillé** et reste à son propriétaire, mais il compte pour `taken` — ses défenseurs lisent donc bien `textLocationDefenceAgentFail`. Formuler ces pools en termes de défaite et de butin perdu plutôt que de place rasée.

| Situation | Attaquant | Défenseur |
|---|---|---|
| Agent mort pendant l'échelle | *aucune ligne* | *aucune ligne* |
| `falls` sans `taken` (personne où mettre le butin) | `textLocationAssaultAgentNoHolder` | `textLocationDefenceAgentNoHolder` |
| `taken` — l'assaut aboutit | `textLocationAssaultAgentSuccess` | **`textLocationDefenceAgentFail`** |
| Ni `falls` ni `taken` — la place tient | `textLocationAssaultAgentFail` | `textLocationDefenceAgentSuccess` |
| Agent que l'échelle n'a jamais apparié | `textLocationAssaultAgentUnengaged` **en préfixe** | `textLocationDefenceAgentUnengaged` **en préfixe** |
| Des artefacts ont réellement changé de lieu | `textLocationAgentSpoils*` **en suffixe** | `textLocationAgentSpoils*` **en suffixe** |

**Attention — sens de Success / Fail :** Le succès est celui de *l'assaut*, jamais celui du lecteur. Un assaut qui produit son effet — destruction, échange ou simple pillage — est donc le succès de l'attaquant **et l'échec du défenseur** : c'est bien **`textLocationDefenceAgentFail`** qui part quand l'assaut a réussi, et `textLocationDefenceAgentSuccess` quand il a échoué. La même convention règne déjà sur le chemin contrôleur (`resolveControllerLocationAttackEffects`).

**Ordre de composition.** Le rapport d'un agent est assemblé dans cet ordre, et un agent mort n'en reçoit aucun morceau :

1. la ligne « jamais apparié » (`*Unengaged`), **seulement** si l'échelle ne l'a opposé à personne ;
2. la ligne d'issue — `*NoHolder` ou l'un des quatre `*Success` / `*Fail` ; ce sont des branches exclusives, jamais deux à la fois ;
3. la ligne de butin (`textLocationAgentSpoils*`), **seulement** si des artefacts ont réellement été déplacés.

Un agent que personne n'a affronté lit donc deux phrases : d'abord qu'il n'a pas combattu, ensuite comment l'assaut s'est terminé. Aucune écriture n'a lieu si l'ensemble reste vide.

**Placeholders.** Les six nouvelles clés ne reçoivent que les arguments listés ci-dessous — jamais le troisième argument des quatre pools d'issue. Y écrire un `%3$s` (ou un `%2$s` dans un pool `Spoils*`) fait lever `sprintf` en PHP 8 et casse la fin de tour : s'en tenir strictement au contrat de chaque clé.

| Clé | Placeholders |
|---|---|
| **`textLocationAssaultAgentNoHolder`** (= `["J'ai participé à la prise de %1$s dans %2$s, mais nous n'avions nulle part où mener les prisonniers : la place nous a filé entre les doigts.<br/>"]`) | `%1$s` = nom du lieu, `%2$s` = nom de la zone |
| **`textLocationDefenceAgentNoHolder`** (= `["Notre %1$s dans %2$s a été attaqué.e et nous avons cédé, mais les assaillants n'ont rien pu emporter.<br/>"]`) | idem |
| **`textLocationAssaultAgentUnengaged`** (= `["J'étais du raid sur %1$s dans %2$s, mais tout s'est joué sans moi.<br/>"]`) | idem |
| **`textLocationDefenceAgentUnengaged`** (= `["Notre %1$s dans %2$s a été attaqué.e ; je tenais mon poste, personne n'est venu jusqu'à moi.<br/>"]`) | idem |
| **`textLocationAgentSpoilsSelf`** (= `["Les prisonniers sont repartis avec nous.<br/>"]`) | **aucun** — apposé quand le lecteur appartient au réseau vainqueur |
| **`textLocationAgentSpoilsOther`** (= `["Les prisonniers sont repartis avec le réseau %1$s.<br/>"]`) | `%1$s` = numéro du réseau vainqueur |

**Le troisième argument des quatre pools d'issue.** Les deux familles préexistantes ne reçoivent pas la même chose en `%3$s`, et c'est une source d'erreur classique quand on rédige un scénario :

- **`textLocationAssaultAgent*`** — une **clause déjà rédigée** « ` défendu par le réseau N` », espace initiale comprise, vide quand le lieu n'appartient à personne. À coller directement dans la phrase, sans article ni préposition.
- **`textLocationDefenceAgent*`** — le ou les **numéros de réseau attaquants**, bruts. En mode `agent_attack_defence` c'est une **liste jointe par des virgules** : un assaut d'agents peut réunir plusieurs réseaux devant la même place, alors que le chemin contrôleur n'en passait jamais qu'un seul. Une formulation du type « du réseau %3$s » doit donc rester lisible au pluriel.

**Valeurs fournies par le scénario uniquement.** Comme les quatre pools d'agent qui les précèdent, ces six clés ne sont **pas** dans `minimalData.sql`. Elles sont seedées par `var/csv/setupTestConfig_config.csv` (anglais), `var/csv/setupJapon1555CSV_config.csv`, `var/mysql/setupJapon1555SQL_textes.sql` et `var/postgres/setupJapon1555SQL_textes.sql` (français). Une clé manquante ou dont le JSON est illisible ne casse rien : `pickLocationAgentText()` journalise un `warning` « missing or unusable text pool » et retourne un gabarit vide, donc la ligne correspondante est simplement absente du rapport — contrairement au chemin contrôleur, qui lui retombe sur une phrase en dur. Le partage de responsabilité entre socle et scénario fait l'objet de la question ouverte **#120** « `minimalData.sql` doit-il seeder toutes les clés lues, ou chaque site d'appel porter un repli ? » ; si elle se tranche en faveur du socle, ces six clés y descendront.

**Ancienneté du lieu :** le terme `baseDefenceAddTurns` se calcule sur `{prefix}locations.setup_turn`, désormais estampillé à chaque construction, changement d'état et déménagement (voir « Difficulté de découverte des places fortes »). Un lieu neuf ou fraîchement ruiné n'a donc plus le bonus d'ancienneté maximal qu'il recevait quand la colonne restait à `0`.

### `workers` — Recrutement et progression des agents

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

### `resources` — Ressources

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
- `{type: "owns_location_type", location_type: "temple", can_be_destroyed: 1}` → l'usage de Japon1555 pour `Koku`, sur `temple` comme sur `fortress`.

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

**Comportement automatique :** `createBase` ajoute le tag `"fortress"` aux nouvelles bases. **Tout autre lieu possédé doit être taggé à la main dans le CSV du scénario** — bases comprises quand elles sont seedées et non bâties en jeu. Un lieu possédé sans tag, ou portant un tag hors vocabulaire, est silencieusement ignoré par `owns_location_type` : rien ne valide le vocabulaire, ni à l'import CSV ni à la saisie admin.

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

### Dons d’informations entre factions

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

`controllers/view.php` rend un panneau qui liste les dons reçus par le contrôleur actif via `getInformationGiftsReceived()`. Le helper résout `target_label` via un JOIN sur `workers` (`firstname + lastname`) ou `locations` (`name`), et rapporte la colonne `zone_name` du journal.

**`textInformationGiftReceived`** (= `%1$s vous a informé sur %2$s <strong>%3$s</strong> à <strong>%4$s</strong>.`) — la ligne affichée pour un don reçu. `%1$s` = nom du donateur, `%2$s` = « l'agent » ou « le lieu », `%3$s` = nom de la cible, `%4$s` = zone. Une zone absente s'affiche « — ».

Le préfixe de l'onglet est la valeur configurée par `timeValue`, par exemple « Tour 12 » ou « Trimestre 12 ».

**`{prefix}information_gift_logs.zone_name`** porte la zone **telle qu'elle était au moment du don**, dénormalisée comme `worker_combat_logs.zone_name`. Pour un agent elle vient de la ligne `controllers_known_enemies` du donateur, donc de la zone qu'il connaissait ; pour un lieu, de la zone où il se trouvait alors. Un agent ou un lieu qui bouge ensuite ne réécrit pas le journal — c'est un registre historique, pas un état courant.

### Section admin « Information Transactions »

`controllers/management.php` reçoit une section listant tous les transferts (giver, recipient, type, target, zone) triés du plus récent au plus ancien — équivalent au panneau « Ressource Transactions » de la page admin des ressources.

### `debug` — Débogage

*Section à compléter dans un commit suivant.* Couvrira : `DEBUG`, `DEBUG_REPORT`, `DEBUG_ATTACK`, `DEBUG_TRANSFORM`, `ACTIVATE_TESTS`.

## Notes pour mainteneurs

Cette annexe regroupe les détails d’implémentation utiles au code, pas à la rédaction d’un scénario.

- **Import CSV** : `BDD/db_connector.php` charge `setup{config_name}_{table}.csv` avec upsert pour `config` et `power_types`.
- **Carte documentaire** : `docs/config_section_map.json` (pas importée).
- **Panneau admin** : `base/admin_csv.php` — download + check d’en-tête / `section_key`.
- **Guide rendu HTML** : `base/docConfig.php` (Parsedown sur ce fichier).
- **Logs / fail-open** : règles `zone_rules` invalides, pools texte illisibles, `gain_rules` mal formées → log + valeur de base intacte ou phrase vide selon le site d’appel.
- **Question ouverte #120** : `minimalData.sql` doit-il seeder toutes les clés lues, ou chaque site d’appel porter un repli ?
- Les noms internes de fichiers / fonctions (`calculateVals`, `resolveWorkerCombat`, `writeLocationAgentReports`, etc.) sont cités dans la référence détaillée quand ils aident à tracer un bug ; un auteur de CSV peut les ignorer.
