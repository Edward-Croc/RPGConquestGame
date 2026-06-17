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
- **`condition`** — critère évalué pour le contrôleur. Une règle = un type de condition ; on cumule les effets en ajoutant plusieurs règles.

**Types de condition implémentés :**

- **`holds_zone`** — match quand le contrôleur est `zones.holder_controller_id` ; filtre optionnel : `zone_id`.
- **`claims_zone`** — match quand le contrôleur est `zones.claimer_controller_id` ; filtre optionnel : `zone_id`.
- **`owns_location_type`** — match quand le contrôleur est `locations.controller_id`, puis filtrage optionnel AND-combiné via `is_base`, `can_be_destroyed`, `zone_id`, `location_id`, `location_type`.

**`holds_zone` vs `claims_zone` :** avec contrôleurs secrets / doubles agents, le propriétaire réel (`holder`) peut différer de la bannière visible (`claimer`). Choisir la condition selon ce que la récompense doit refléter.

**Agrégation binaire vs comptée :**

- `{type: "holds_zone", zone_id: 5}` → binaire : gain 1 fois si la zone 5 est tenue.
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
