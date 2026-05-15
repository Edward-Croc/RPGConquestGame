-- Minimal data required for the app to function (gm login, core config keys,
-- starting mechanics row, fixed power type ids). Postgres variant.
--
-- Runs once after setupBDD.sql during schema init, AND is safe to re-run at
-- any time: all statements are idempotent via ON CONFLICT DO NOTHING;
-- mechanics uses WHERE NOT EXISTS since it has no natural unique key.

-- Core game state (single row)
INSERT INTO {prefix}mechanics (turncounter, gamestate)
SELECT 0, 0
WHERE NOT EXISTS (SELECT 1 FROM {prefix}mechanics);

-- Core config (game rules, worker creation, rolls, attack/claim, UI texts, base info)
INSERT INTO {prefix}config (name, value, description)
VALUES
    -- Debugs vals
    ('DEBUG', 'FALSE', 'Activates the Debugging texts'),
    ('DEBUG_REPORT', 'FALSE', 'Activates the Debugging texts for the investigation report'),
    ('DEBUG_ATTACK', 'FALSE', 'Activates the Debugging texts for the attack report mechanics'),
    ('DEBUG_IA', 'FALSE', 'Activates the Debugging texts for the aiMechanic state machine'),
    ('aiAggressionThreshold', '2', 'Min known-enemies count for searching → aggressive ai_type transition'),
    ('DEBUG_TRANSFORM', 'FALSE', 'Activates the Debugging texts for the attack report mechanics'),
    ('ACTIVATE_TESTS', 'FALSE', 'Activates the insertion of tests values'),
    ('TITLE', '', 'Name of game'),
    ('PRESENTATION', '', 'Presentation text'),
    ('IntrigueOrga', '', 'Organisation info'),
    ('basePowerNames', '''power1'',''power2''', 'List of Powers accessible to all workers'),
    ('turn_recrutable_workers', '1', 'Number of workers recrutable per turn'),
    ('turn_firstcome_workers', '1', 'Number of worker recrutable by firstcome pick per turn'),
    ('first_come_nb_choices', '1', 'Number of worker options presented for 1st come recrutment'),
    ('first_come_origin_list', 'rand', 'Origins used for worker generation'),
    ('recrutement_nb_choices', '3', 'Number of choices presented for recrutment'),
    ('recrutement_origin_list', '1,2,3,4,5', 'Origins used for worker generation'),
    ('local_origin_list', '1', 'Spécific list of local origins for investigations texts'),
    ('recrutement_disciplines', '1', 'Number of disciplines allowed on recrutment'),
    ('recrutement_transformation', '{"action": "check"}', 'Json string calibrating transformations allowed on recrutment'),
    ('age_discipline', '{"age": ["2"]}', 'If disciplines can be gained with AGE'),
    ('age_transformation', '{"action": "check"}', 'If transformation can be gained with AGE'),
    ('owner_knows_own_base_secret', 'TRUE', 'On base creation / scenario load, seed controller_known_locations with found_secret=TRUE so owners auto-know their own base secrets. Set FALSE for fog-of-war setups where the owner must learn their own base secret separately.'),
    ('MINROLL', '1', 'Minimum Roll for an active worker'),
    ('MAXROLL', '6', 'Maximum Roll for a an active worker'),
    ('PASSIVEVAL', '3', 'Value for passive actions'),
    ('ENQUETE_ZONE_BONUS', '0', 'Bonus à la valeur enquete si le worker est dans une zone contrôlée'),
    ('ATTACK_ZONE_BONUS', '0', 'Bonus à la valeur attaque si le worker est dans une zone contrôlée'),
    ('DEFENCE_ZONE_BONUS', '1', 'Bonus à la valeur défense si le worker est dans une zone contrôlée'),
    ('HIDE_ENQUETE_FLAT_BONUS', '4', 'Bonus to the investigate value if the worker is using hide'),
    ('HIDE_DEFENCE_FLAT_BONUS', '1', 'Bonus to the investigate value if the worker is using hide'),
    ('investigateActionsList', '''passive'',''investigate''', 'Action choices that actually run investigation against enemy workers (filter inside investigateMechanic). Distinct from passive/active*InvestigateActions which only pick D6-vs-PASSIVEVAL for enquete_val computation.'),
    ('passiveInvestigateActions', '''passive'',''attack'',''captured'',''hide''', 'Liste of passive investigation actions'),
    ('activeInvestigateActions', '''investigate'',''claim''', 'Liste of active investigation actions'),
    ('passiveAttackActions', '''passive'',''investigate'',''hide''', 'Liste of passive attack actions'),
    ('activeAttackActions', '''attack'',''claim''', 'Liste of active attack actions'),
    ('passiveDefenceActions', '''passive'',''investigate'',''attack'',''claim'',''captured'',''hide''', 'Liste of passive defence actions'),
    ('activeDefenceActions', '', 'Liste of active defense actions'),
    ('REPORTDIFF0', '-1', 'Value for Level 0 information'),
    ('REPORTDIFF1', '1', 'Value for Level 1 information'),
    ('REPORTDIFF2', '2', 'Value for Level 2 information'),
    ('REPORTDIFF3', '4', 'Value for Level 3 information'),
    ('LOCATIONNAMEDIFF', '0', 'Value for Location Name'),
    ('LOCATIONINFORMATIONDIFF', '1', 'Value for Location Information'),
    ('LOCATIONARTEFACTSDIFF', '2', 'Value for Location Artefact discovery'),
    ('attackTimeWindow', '1', 'Number of turns a discovered worker is attackable after being lost'),
    ('canAttackNetwork', '1', 'If 0 then only workers ar shown, > 0 then workers are sorted by networks when network is known = REPORTDIFF2 obtained '),
    ('LIMIT_ATTACK_BY_ZONE', '0', 'If 0 then attack happens if worker leave zone, > 0 then attack is limited to workers in zone'),
    ('ATTACKDIFF0', '1', 'Value for Attack Success'),
    ('ATTACKDIFF1', '3', 'Value for Capture'),
    ('RIPOSTACTIVE', '1', 'Activate Ripost when attacked'),
    ('RIPOSTDIFF', '2', 'Value for Successful Ripost'),
    ('DISCRETECLAIMDIFF', '2', 'Value for discrete claim'),
    ('VIOLENTCLAIMDIFF', '0', 'Value for violent claim'),
    ('claimMode', 'worker', 'Zone claim resolution mode. worker | worker_leader | controller (v1 ships worker + worker_leader only)'),
    ('baseClaim', '0', 'Base claim value floor (modes B+C)'),
    ('baseClaimAddWorkers', '1', 'Per-worker multiplier for claim value (modes B+C)'),
    ('baseClaimAddOwnedLocations', '1', 'Per-owned-location multiplier for claim value (modes B+C)'),
    ('maxBonusClaimWorkers', '0', 'Cap on worker bonus for claim (0 = no cap)'),
    ('maxBonusClaimOwnedLocations', '0', 'Cap on owned-location bonus for claim (0 = no cap)'),
    ('claimDiff', '1', 'Threshold. claim_val minus defence_val must clear this to succeed (modes B+C)'),
    ('claimVisibleToRealBonus', '1', 'claim_val bonus when attacker already holds claimer_controller_id but not holder_controller_id'),
    ('baseClaimAddSupporting', 1, 'Per-supporting-claimer bonus. max(0, COUNT(workers in zone with action=claim) - 1) times this multiplier (claimMode worker_leader)'),
    ('baseZoneDefenceAddSupporting', 1, 'Per-supporting-claimer bonus. max(0, COUNT(workers in zone with action=claim) - 1) times this multiplier (claimMode worker_leader)'),
    ('baseZoneDefence', '0', 'Base zone defence floor'),
    ('baseZoneDefenceAddWorkers', '1', 'Per-worker multiplier for zone defence (holder workers in zone)'),
    ('baseZoneDefenceAddOwnedLocations', '1', 'Per-owned-location multiplier for zone defence (holder locations in zone)'),
    ('maxBonusZoneDefenceWorkers', '0', 'Cap on worker bonus for zone defence (0 = no cap)'),
    ('maxBonusZoneDefenceOwnedLocations', '0', 'Cap on owned-location bonus for zone defence (0 = no cap)'),
    ('noControllerZoneDefenceBonus', '3', 'Fixed defence bonus when holder_controller_id IS NULL'),
    ('txt_ps_passive', 'surveille', 'Text for passive action'),
    ('txt_ps_investigate', 'enquête', 'Text for investigate action'),
    ('txt_ps_hide', 'se cache', 'Text for hide action'),
    ('txt_ps_attack', 'attaque', 'Text for attack action'),
    ('txt_ps_claim', 'revendique le quartier', 'Text for claim action'),
    ('txt_ps_captured', 'a disparu', 'Text for captured action'),
    ('txt_ps_dead', 'a disparu', 'Text for dead action'),
    ('txt_ps_prisoner', 'est un.e agent %s %s que nous avons fait.e prisonnier.e', 'Text for beeing prisoner'),
    ('txt_ps_double_agent', 'a infiltré le réseau %s %s ', 'Text for being infiltrator'),
    ('txt_inf_passive', 'surveiller', 'Text for passive action'),
    ('txt_inf_investigate', 'enquêter', 'Text for investigate action'),
    ('txt_inf_hide', 'se cacher', 'Text for hide action'),
    ('txt_inf_attack', 'attaquer', 'Text for attack action'),
    ('txt_inf_claim', 'revendiquer le quartier', 'Text for claim action'),
    ('txt_inf_captured', 'a été capturé', 'Text for captured action'),
    ('txt_inf_dead', 'est mort', 'Text for dead action'),
    ('continuing_investigate_action', '1', 'Does the investigate action stay active'),
    ('continuing_claimed_action', '1', 'Does the claim action stay active'),
    ('baseDiscoveryDiff', '3', 'Base discovery value for bases'),
    ('baseDiscoveryDiffAddPowers', '1', 'Base discovery value Power presence ponderation 0 for no'),
    ('baseDiscoveryDiffAddWorkers', '1', 'Base discovery value worker presence ponderation 0 for no'),
    ('baseDiscoveryDiffAddTurns', '0.5', 'Base discovery value base age presence ponderation 0 for no'),
    ('maxBonusDiscoveryDiffPowers', '5', 'Maximum bonus obtainable from power presence'),
    ('maxBonusDiscoveryDiffWorkers', '4', 'Maximum bonus obtainable from worker presence'),
    ('maxBonusDiscoveryDiffTurns', '3', 'Maximum bonus obtainable from age of base'),
    ('locationAttackMode', 'immediate', 'Where/when controller location attacks resolve: immediate | endTurn | worker'),
    ('attackLocationOutcomeBandwidth', '2', 'Half-width of the faible-chances band for the queued-attack prediction'),
    ('baseAttack', '0', 'Base attack value for bases'),
    ('baseAttackAddPowers', '1', 'Base attack value Power presence ponderation 0 for no'),
    ('baseAttackAddWorkers', '1', 'Base attack value worker presence ponderation 0 for no'),
    ('baseDefence', '0', 'Base defence value for bases'),
    ('baseDefenceAddPowers', '1', 'Base defence value Power presence ponderation 0 for no'),
    ('baseDefenceAddWorkers', '1', 'Base defence value worker presence ponderation 0 for no'),
    ('baseDefenceAddTurns', '0.5', 'Base defence value base age presence ponderation 0 for no'),
    ('noControllerDefenceBonus', '3', 'Base defence value for no controller'),
    ('maxBonusDefenceTurns', '3', 'Maximum bonus obtainable from age of base'),
    ('attackLocationDiff', '1', 'Difficulty to destroy a Location'),
    ('textLocationDestroyed', 'Le lieu %s a été détruit selon votre bon vouloir.', 'Text for location destroyed'),
    ('textLocationPillaged', 'Le lieu %s a été pillé, mais nous n''avons pas pu le détruire.', 'Text for location pillaged'),
    ('textLocationNotDestroyed', 'Le lieu %s n''a pas été détruit, nos excuses.', 'Text for location not destroyed'),
    ('textLocationAttackQueued', 'Attaque planifiée contre %s. Force d''attaque actuelle : %d. Prédiction : %s.', 'Live-rendered queued-attack message. Placeholders. location name, current attack force, predicted outcome'),
    ('textLocationAttackOutcomeFail', 'Échec probable.', 'Predicted-outcome band when current attack is well below snapshot defence'),
    ('textLocationAttackOutcomeWeak', 'Faibles chances.', 'Predicted-outcome band when current attack is within bandwidth of snapshot defence'),
    ('textLocationAttackOutcomeProbable', 'Réussite probable.', 'Predicted-outcome band when current attack is well above snapshot defence'),
    ('textLocationAttackResolved', 'Attaque sur %s en fin de tour %d : %s.', 'End-of-turn resolved-attack message. Placeholders. location name, resolved_turn, outcome text'),
    ('textLocationAttackDestroyed', 'Le lieu %1$s a été détruit avant notre arrivée.', 'Attacker-only log line when an end-turn queued attack arrives after a prior attack in the same turn destroyed the target. Placeholder: %1$s = location name.'),
    ('textLocationAttackMoved', 'Le lieu %1$s avait été déplacé avant notre arrivée.', 'Attacker-only log line when an end-turn queued attack is cancelled because the target base moved before resolution. Placeholder: %1$s = location name.'),
    ('textOwnedArtefacts', 'Vos artefacts :', 'Text for location owned artefacts'),
    ('ressource_management', 'TRUE', 'Ressource management configuration')
ON CONFLICT (name) DO NOTHING;

-- Map defaults
INSERT INTO {prefix}config (name, value, description)
VALUES
    ('map_file', 'shikoku.png', 'Map file to use'),
    ('map_alt', 'Carte', 'Map alt')
ON CONFLICT (name) DO NOTHING;

-- Controller / time denomimator texts
INSERT INTO {prefix}config (name, value, description)
VALUES
    ('controllerNameDenominatorThe', '', 'Denominator for the controler name'),
    ('controllerNameDenominatorOf', 'de', 'Denominator ’of’ for the controler full name'),
    ('controllerLastNameDenominatorOf', 'de', 'Denominator ’of’ for the controler last name'),
    ('textForZoneType', 'zone', 'Text for the type of zone'),
    ('timeValue', 'Tour', 'Text for time span'),
    ('timeDenominatorThis', 'ce', 'Denominator ’this’ for time text'),
    ('timeDenominatorThe', 'le', 'Denominator ’the’ for time text'),
    ('timeDenominatorOf', 'du', 'Denominator ’of’ for time text')
ON CONFLICT (name) DO NOTHING;

-- Core user (must exist for login)
INSERT INTO {prefix}players (username, passwd, is_privileged)
VALUES ('gm', 'orga', 1)
ON CONFLICT (username) DO NOTHING;

-- Fixed power types used by application code (hobbys/jobs linking logic).
-- Scenario-specific SQL files can supply their own extended set.
INSERT INTO {prefix}power_types (id, name, description) VALUES
    (1, 'Hobby', 'Objet fétiche'),
    (2, 'Metier', 'Rôle'),
    (3, 'Discipline', 'Maitrise des Arts'),
    (4, 'Transformation', 'Equipements Rares')
ON CONFLICT (id) DO NOTHING;
