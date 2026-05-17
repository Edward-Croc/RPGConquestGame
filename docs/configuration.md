# Documentation de configuration

**Public visé :** organisateurs/admins de soirées enquête utilisant le système RPG Game Conquest, et auteurs de scénarios qui éditent la table `{prefix}config`.

**Note de lecture :** les **clés de configuration** (`claimMode`, `claimDiff`, etc.) sont stockées dans la table `{prefix}config` et modifiables via l'admin ; les valeurs par défaut sont notées entre parenthèses. Les **variables calculées** (`claim_val`, `calculated_defence_val`, etc.) sont recomputées à chaque tour à partir des configs et de l'état du jeu — elles ne sont pas modifiables directement, on les mentionne uniquement pour expliquer comment les configs s'y combinent. Pour les clés énumérées (modes), toute valeur non implémentée désactive le mécanisme correspondant.


## 1. Textes affichés

*Section à compléter dans un commit suivant.* Couvrira : `TITLE`, `PRESENTATION`, `IntrigueOrga`, `basePowerNames`, les familles `txt_ps_*` et `txt_inf_*`, les dénominateurs (`controllerNameDenominator*`, `timeDenominator*`), `textForZoneType`, `timeValue`, `map_file`, `map_alt`.

## 2. Moteur de jeu (autres calculs)

*Section à compléter dans un commit suivant.* Couvrira : `MINROLL`, `MAXROLL`, `PASSIVEVAL`, bonus de zone (`ENQUETE_ZONE_BONUS`, etc.), bonus plats de "hide", seuils de découverte de rapports (`REPORTDIFF*`, `LOCATION*DIFF`), listes d'actions actives/passives, formules de découverte de bases (`baseDiscoveryDiff*`), seuils d'attaque entre agents (`ATTACKDIFF*`, `RIPOST*`, `attackTimeWindow`, `canAttackNetwork`, `LIMIT_ATTACK_BY_ZONE`).


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
