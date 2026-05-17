# Documentation de configuration

**Public visé :** organisateurs/admins de soirées enquête utilisant le système RPG Game Conquest, et auteurs de scénarios qui éditent la table `{prefix}config`.

**Note de lecture :** les **clés de configuration** (`claimMode`, `claimDiff`, etc.) sont stockées dans la table `{prefix}config` et modifiables via l'admin ; les valeurs par défaut sont notées entre parenthèses. Les **variables calculées** (`claim_val`, `calculated_defence_val`, etc.) sont recomputées à chaque tour à partir des configs et de l'état du jeu — elles ne sont pas modifiables directement, on les mentionne uniquement pour expliquer comment les configs s'y combinent. Pour les clés énumérées (modes), toute valeur non implémentée désactive le mécanisme correspondant.


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
- **`activeInvestigateActions`** (= `'investigate','claim'`) — Actions dont la valeur d'enquête est tirée au D6.
- **`passiveAttackActions`** (= `'passive','investigate','hide'`) — Actions dont la valeur d'attaque est `PASSIVEVAL` (utilisée pour les ripostes).
- **`activeAttackActions`** (= `'attack','claim'`) — Actions dont la valeur d'attaque est tirée au D6.
- **`passiveDefenceActions`** (= `'passive','investigate','attack','claim','captured','hide'`) — Actions dont la valeur de défense est `PASSIVEVAL`.
- **`activeDefenceActions`** (= `''`, vide par défaut) — Actions dont la valeur de défense est tirée au D6. Vide signifie que toutes les valeurs de défense sont fixes.

Format attendu : chaîne SQL `'action1','action2',...` avec apostrophes incluses. L'action `claim` apparaît dans les deux axes actifs (`enquete` et `attack`) — c'est volontaire : revendiquer génère un jet pour les deux valeurs, ce qui rend l'agent compétitif quand le `claimMode='worker'` les compare à la défense de la zone.

### Seuils de découverte d'information

**`REPORTDIFF0`** (= -1), **`REPORTDIFF1`** (= 1), **`REPORTDIFF2`** (= 2), **`REPORTDIFF3`** (= 4) — Seuils progressifs de différence `enquete_val − target_defence_val` pour révéler les niveaux d'information dans un rapport d'enquête sur un agent : nom et action (niveau 0, accessible dès `≥ REPORTDIFF0`), capacités aléatoires (niveau 1), capacités du contrôleur et numéro de réseau (niveau 2), nom du contrôleur dominant (niveau 3). Le niveau 0 négatif (-1) signifie que l'information passe même avec un léger déficit ; mettre `REPORTDIFF0 = 1` conditionnerait toute découverte à un avantage net.

**`LOCATIONNAMEDIFF`** (= 0), **`LOCATIONINFORMATIONDIFF`** (= 1), **`LOCATIONARTEFACTSDIFF`** (= 2) — Seuils de différence `enquete_val − discovery_diff` pour les niveaux de découverte d'un lieu secret : nom du lieu, description / informations secrètes, présence d'artefacts récupérables. Augmenter ces seuils rend les enquêtes de zone moins rentables.

> **Important :** la recherche d'information (rapports d'enquête sur agents ennemis et découverte de lieux secrets) n'est effectuée que pour les actions listées dans **`investigateActionsList`** (= `'passive','investigate'`). Le filtre est appliqué dans `mechanics/investigateMechanic.php` (agents) et `mechanics/locationSearchMechanic.php` (lieux).

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

### Modes de résolution

#### Famille Interaction entre Agents(workers)

*Section à compléter dans un commit suivant.*  Couvrira : `attack`, `hide`, `pasive`, `investigate`, `gift`, 


##### Actions et flux

*Section à compléter dans un commit suivant.* Couvrira : `continuing_investigate_action` et les comportements de continuité d'actions d'un tour au suivant (l'entrée `continuing_claimed_action` est déjà documentée dans la section Modes de résolution).

#### Famille `claimMode` — résolution des revendications de zone

**`claimMode`** — Détermine comment le système résout les revendications de zone à la fin du tour. Valeurs implémentées :

- **`worker`** *(par défaut, mode A)* — Chaque agent qui revendique tire son propre jet et le compare à la défense de la zone. La zone bascule si un agent dépasse `calculated_defence_val` d'au moins **`DISCRETECLAIMDIFF`** (= 2) points avec son `enquete_val`, ou de **`VIOLENTCLAIMDIFF`** (= 0) points avec son `attack_val`. `calculated_defence_val` suit ici la formule SQL d'origine : `z.defence_val + COUNT(agents du holder dans la zone)`.
- **`worker_leader`** *(mode B)* — Les agents qui revendiquent dans une zone forment un groupe ; le leader (le plus ancien) porte la valeur agrégée du contrôleur. `claim_val` combine plancher **`baseClaim`** (= 0), agents présents (multiplicateur **`baseClaimAddWorkers`** = 1), lieux possédés (**`baseClaimAddOwnedLocations`** = 1), co-revendicateurs (**`baseClaimAddSupportingClaimers`** = 1, formule `max(0, COUNT − 1)`) et un bonus **`claimVisibleToRealBonus`** (= 1) pour la prise de contrôle réel. La défense `calculated_defence_val` suit la formule symétrique **`baseZoneDefence`** + agents + lieux du holder, ou **`noControllerZoneDefenceBonus`** (= 3) si la zone est libre. La revendication réussit si `claim_val − calculated_defence_val ≥ claimDiff` (= 1). Pas de D6 ; résolution déterministe. Plafonds optionnels : `maxBonusClaim*`, `maxBonusZoneDefence*` (0 = sans plafond).
- **`controller`** *(v2, non implémenté)* — Mode réservé pour une future itération.

Toute autre valeur (faute de frappe, mode futur non développé) désactive le mécanisme de revendication.

**Clés communes à tous les modes :** `continuing_claimed_action` (= 1, l'action reste active au tour suivant), `txt_ps_claim` et `txt_inf_claim` (textes affichés), et les listes d'actions `passiveInvestigateActions` / `activeAttackActions` / `passiveDefenceActions` qui contiennent toutes la valeur `'claim'`.

#### Famille `locationAttackMode` — attaque de lieu (locations)

**`locationAttackMode`** — Détermine où et quand les attaques de lieu (place forte, etc.) sont résolues. Valeurs implémentées :

- **`immediate`** *(par défaut)* — L'attaque est résolue dès le clic du contrôleur, avant la fin du tour. Comparaison : `attack_val − defence_val ≥ attackLocationDiff` (= 1). `attack_val` et `defence_val` sont les valeurs agrégées du contrôleur attaquant et du lieu, calculées via la famille `baseAttack*` (plancher + pouvoirs + agents) et `baseDefence*` (plancher + pouvoirs + agents + âge du lieu via **`baseDefenceAddTurns`** = 0.5, plafonné à **`maxBonusDefenceTurns`** = 3). Lieu sans contrôleur : bonus défensif **`noControllerDefenceBonus`** (= 3).
- **`endTurn`** — L'attaque est mise en file d'attente au clic, avec une prédiction d'issue affichée immédiatement (`attack_val_snapshot` et `defence_val_snapshot`). La résolution effective recalcule `attack_val_resolved` et `defence_val_resolved` en fin de tour, après les attaques entre agents. La prédiction utilise une bande "Faibles chances" de demi-largeur **`attackLocationOutcomeBandwidth`** (= 2) autour de l'égalité ; en-dehors, on affiche "Échec probable" ou "Réussite probable" via les clés `textLocationAttackOutcomeFail/Weak/Probable`.
- **`worker`** *(v2, non implémenté)* — Mode réservé pour une future itération.

Toute autre valeur désactive le mécanisme d'attaque de lieu.

**Clés communes aux deux modes implémentés :** les familles de formules `baseAttack*`, `baseDefence*`, `baseDiscoveryDiff*` (utilisée aussi pour la découverte de lieux), ainsi que les textes `textLocationDestroyed`, `textLocationPillaged`, `textLocationNotDestroyed`, `textOwnedArtefacts`.

## 3. Recrutement et progression des Agents (workers)

*Section à compléter dans un commit suivant.* Couvrira : `turn_recrutable_workers`, `turn_firstcome_workers`, `first_come_*`, `recrutement_*`, `age_discipline`, `age_transformation`, `owner_knows_own_base_secret`.

## 4. Ressources

*Section à compléter dans un commit suivant.* Couvrira : `ressource_management` et le format de la table `ressources_config`.

## 5. Débogage

*Section à compléter dans un commit suivant.* Couvrira : `DEBUG`, `DEBUG_REPORT`, `DEBUG_ATTACK`, `DEBUG_TRANSFORM`, `ACTIVATE_TESTS`.
