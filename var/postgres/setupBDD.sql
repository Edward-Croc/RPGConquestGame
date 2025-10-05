-- Necessary base SQL setup
-- DROP DATABASE IF EXISTS RPGConquestGame;
-- CREATE DATABASE RPGConquestGame OWNER php_gamedev;
-- CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE mechanics (
    id SERIAL PRIMARY KEY,
    turncounter INTEGER DEFAULT 0,
    gamestate INTEGER DEFAULT 0,
    end_step TEXT DEFAULT ''
);

INSERT INTO mechanics (turncounter, gamestate)
VALUES (0, 0);

-- create configuration table
CREATE TABLE config (
    id SERIAL PRIMARY KEY,
    name text UNIQUE NOT NULL, --name used key
    value text DEFAULT '', --value to be read
    description text -- explain configuration usage
);

INSERT INTO config (name, value, description)
VALUES
    -- Debugs vals
    ('DEBUG', 'FALSE', 'Activates the Debugging texts'),
    ('DEBUG_REPORT', 'FALSE', 'Activates the Debugging texts for the investigation report'),
    ('DEBUG_ATTACK', 'FALSE', 'Activates the Debugging texts for the attack report mechanics'),
    ('DEBUG_TRANSFORM', 'FALSE', 'Activates the Debugging texts for the attack report mechanics'),
    ('ACTIVATE_TESTS', 'TRUE', 'Activates the insertion of tests values'),
    ('TITLE', 'RPGConquest', 'Name of game'),
    ('PRESENTATION', 'RPGConquest', 'Name of game'),
    ('IntrigueOrga', 'IntrigueOrga', 'Organisation info'),
    ('basePowerNames', '''power1'',''power2''', 'List of Powers accessible to all workers'),
    -- worker creation
    ('turn_recrutable_workers', '1', 'Number of workers recrutable per turn'),
    ('turn_firstcome_workers', '1', 'Number of worker recrutable by firstcome pick per turn'),
    ('first_come_nb_choices', '1', 'Number of worker options presented for 1st come recrutment'),
    ('first_come_origin_list', 'rand', 'Origins used for worker generation'),
    ('recrutement_nb_choices', '3', 'Number of choices presented for recrutment'),
    ('recrutement_origin_list', '1,2,3,4,5', 'Origins used for worker generation'),
    ('local_origin_list', '1', 'Spécific list of local origins for investigations texts'),
    -- ('recrutement_hobby', '1', 'Number of hobbies added on generation'),
    -- ('recrutement_metier', '1', 'Number of jobs added on generation'),
    ('recrutement_disciplines', '1', 'Number of disciplines allowed on recrutment'),
    ('recrutement_transformation', '{"action": "check"}', 'Json string calibrating transformations allowed on recrutment'),
    -- Worker experience
    -- ('age_hobby', 'FALSE', ' If hobbys can be gained with AGE'),
    -- ('age_metier', 'FALSE', 'If jobs can be gained with AGE'),
    ('age_discipline', '{"age": ["2"]}', 'If disciplines can be gained with AGE'),
    ('age_transformation', '{"action": "check"}', 'If transformation can be gained with AGE'),
    -- worker rolls
    ('MINROLL', 1, 'Minimum Roll for an active worker'),
    ('MAXROLL', 6, 'Maximum Roll for a an active worker'),
    ('PASSIVEVAL', 3, 'Value for passive actions'),
    ('ENQUETE_ZONE_BONUS', 0, 'Bonus à la valeur enquete si le worker est dans une zone contrôlée'),
    ('ATTACK_ZONE_BONUS', 0, 'Bonus à la valeur attaque si le worker est dans une zone contrôlée'),
    ('DEFENCE_ZONE_BONUS', 1, 'Bonus à la valeur défense si le worker est dans une zone contrôlée'),
    ('HIDE_ENQUETE_FLAT_BONUS', 4, 'Bonus to the investigate value if the worker is using hide'),
    ('HIDE_DEFENCE_FLAT_BONUS', 1, 'Bonus to the investigate value if the worker is using hide'),
    -- passive, investigate, attack, claim, captured, dead
    ('passiveInvestigateActions', '''passive'',''attack'',''captured'',''hide''', 'Liste of passive investigation actions'),
    ('activeInvestigateActions', '''investigate'',''claim''', 'Liste of active investigation actions'),
    ('passiveAttackActions', '''passive'',''investigate'',''hide''', 'Liste of passive attack actions'),
    ('activeAttackActions', '''attack'',''claim''', 'Liste of active attack actions'),
    ('passiveDefenceActions', '''passive'',''investigate'',''attack'',''claim'',''captured'',''hide''', 'Liste of passive defence actions'),
    ('activeDefenceActions', '', 'Liste of active defense actions'),
    -- Diff vals for investigation results 
    ('REPORTDIFF0', -1, 'Value for Level 0 information'),
    ('REPORTDIFF1', 1, 'Value for Level 1 information'),
    ('REPORTDIFF2', 2, 'Value for Level 2 information'),
    ('REPORTDIFF3', 4, 'Value for Level 3 information'),
    ('LOCATIONNAMEDIFF', 0, 'Value for Location Name'),
    ('LOCATIONINFORMATIONDIFF', 1, 'Value for Location Information'),
    ('LOCATIONARTEFACTSDIFF', 2, 'Value for Location Artefact discovery'),
    -- Attack choices
    ('attackTimeWindow', 1, 'Number of turns a discovered worker is attackable after being lost'),
    ('canAttackNetwork', 0, 'If 0 then only workers ar shown, > 0 then workers are sorted by networks when network is known = REPORTDIFF2 obtained '),
    -- Diff vals for attack results
    ('LIMIT_ATTACK_BY_ZONE', 0, 'If 0 then attack happens if worker leave zone, > 0 then attack is limited to workers in zone'),
    ('ATTACKDIFF0', 1, 'Value for Attack Success'),
    ('ATTACKDIFF1', 3, 'Value for Capture'),
    ('RIPOSTACTIVE', '1', 'Activate Ripost when attacked'),
    ('RIPOSTDIFF', 2, 'Value for Successful Ripost'),
    -- Diff vals in claim results
    ('DISCRETECLAIMDIFF', 2, 'Value for discrete claim'),
    ('VIOLENTCLAIMDIFF', 0, 'Value for violent claim'),
    -- action text in report config
    ('txt_ps_passive', 'surveille', 'Text for passive action'),
    ('txt_ps_investigate', 'enquête', 'Text for investigate action'),
    ('txt_ps_hide', 'se cache', 'Text for hide action'),
    ('txt_ps_attack', 'attaque', 'Text for attack action'),
    ('txt_ps_claim', 'revendique le quartier', 'Text for claim action'),
    ('txt_ps_captured', 'a disparu', 'Text for captured action'),
    ('txt_ps_dead', 'a disparu', 'Text for dead action'),
    ('txt_ps_prisoner', 'est un.e agent de %s que nous avons fait.e prisonnier.e', 'Text for beeing prisoner'),
    ('txt_ps_double_agent', 'a infiltré le réseau de %s ', 'Text for being infiltrator'),
    ('txt_inf_passive', 'surveiller', 'Text for passive action'),
    ('txt_inf_investigate', 'enquêter', 'Text for investigate action'),
    ('txt_inf_hide', 'se cacher', 'Text for hide action'),
    ('txt_inf_attack', 'attaquer', 'Text for attack action'),
    ('txt_inf_claim', 'revendiquer le quartier', 'Text for claim action'),
    ('txt_inf_captured', 'as été capturer', 'Text for captured action'),
    ('txt_inf_dead', 'est mort', 'Text for dead action'),
    -- Action End turn effects
    ('continuing_investigate_action', FALSE, 'Does the investigate action stay active' ),
    ('continuing_claimed_action', FALSE, 'Does the claim action stay active' )
    -- Base information
    ,('baseDiscoveryDiff', 3, 'Base discovery value for bases' )
    ,('baseDiscoveryDiffAddPowers', 1, 'Base discovery value Power presence ponderation 0 for no' )
    ,('baseDiscoveryDiffAddWorkers', 1, 'Base discovery value worker presence ponderation 0 for no' )
    ,('baseDiscoveryDiffAddTurns', 1, 'Base discovery value base age presence ponderation 0 for no' )
    ,('maxBonusDiscoveryDiffPowers', 5, 'Maximum bonus obtainable from power presence' )
    ,('maxBonusDiscoveryDiffWorkers', 4, 'Maximum bonus obtainable from worker presence' )
    ,('maxBonusDiscoveryDiffTurns', 2, 'Maximum bonus obtainable from age of base' )
    ,('baseAttack', 0, 'Base defence value for bases' )
    ,('baseAttackAddPowers', 1, 'Base defence value Power presence ponderation 0 for no' )
    ,('baseAttackAddWorkers', 1, 'Base defence value worker presence ponderation 0 for no' )
    ,('baseDefence', 0, 'Base defence value for bases' )
    ,('baseDefenceAddPowers', 1, 'Base defence value Power presence ponderation 0 for no' )
    ,('baseDefenceAddWorkers', 1, 'Base defence value worker presence ponderation 0 for no' )
    ,('baseDefenceAddTurns', 1, 'Base defence value base age presence ponderation 0 for no' )
    ,('maxBonusDefenceTurns', 3, 'Maximum bonus obtainable from age of base' )
    ,('attackLocationDiff', 1, 'Difficulty to destroy a Location' )
    ,('textLocationDestroyed', 'Le lieu %s a été détruit selon votre bon vouloir.', 'Text for location destroyed')
    ,('textLocationPillaged', 'Le lieu %s a été pillée, mais nous n’avons pas pu le détruire.', 'Text for location pillaged')
    ,('textLocationNotDestroyed', 'Le lieu %s n’a pas été détruit, nos excuses.', 'Text for location not destroyed')
;

INSERT INTO config (name, value, description)
VALUES
    -- MAP INFO
    ('map_file', 'shikoku.png', 'Map file to use'),
    ('map_alt', 'Carte', 'Map alt')
;
--  Text info
INSERT INTO config (name, value, description)
VALUES
    ('controllerNameDenominatorThe', '', 'Denominator ’the’ for the controler name'),
    ('controllerNameDenominatorOf', 'de', 'Denominator ’of’ for the controler name'),
    ('controllerLastNameDenominatorOf', 'de', 'Denominator ’of’ for the controler last name'),
    ('textForZoneType', 'zone', 'Text for the type of zone'),
    ('timeValue', 'Tour', 'Text for time span'),
    ('timeDenominatorThis', 'ce', 'Denominator ’this’ for time text'),
    ('timeDenominatorThe', 'le', 'Denominator ’the’ for time text'),
    ('timeDenominatorOf', 'du', 'Denominator ’of’ for time text')
;

-- player tables
CREATE TABLE players (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    passwd VARCHAR(64) NOT NULL,
    url text,
    is_privileged BOOLEAN DEFAULT FALSE -- does player have god mode
);

INSERT INTO players (username, passwd, is_privileged)
VALUES
    ('gm', 'orga', True);

-- faction tables
CREATE TABLE factions (
    id SERIAL PRIMARY KEY,
    name text NOT NULL
);

-- controller / character tables
CREATE TABLE controllers (
    id SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    url text,
    story text,
    can_build_base BOOLEAN DEFAULT TRUE, -- can build a base
    start_workers INT DEFAULT 1,
    recruited_workers INT DEFAULT 0,
    turn_recruited_workers INT DEFAULT 0,
    turn_firstcome_workers INT DEFAULT 0,
    ia_type text DEFAULT '',
    faction_id INT NOT NULL,
    fake_faction_id INT NOT NULL,
    secret_controller BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (faction_id) REFERENCES factions (id),
    FOREIGN KEY (fake_faction_id) REFERENCES factions (id)
);

-- player to controller link
CREATE TABLE player_controller (
    controller_id INT NOT NULL,
    player_id INT NOT NULL,
    PRIMARY KEY (controller_id, player_id),
    FOREIGN KEY (controller_id) REFERENCES controllers (id),
    FOREIGN KEY (player_id) REFERENCES players (id)
);

-- Create the zones and locations
CREATE TABLE zones (
    id SERIAL PRIMARY KEY,
    name text NOT NULL,
    description text NOT NULL,
    defence_val INT DEFAULT 6, -- Base defence to claim the zone
    calculated_defence_val INT DEFAULT 6, -- Updated defence value when actively protected
    claimer_controller_id INT, -- id of controller officialy claiming the zone
    holder_controller_id INT,   -- id of controller defending the zone
    hide_turn_zero BOOLEAN DEFAULT FALSE, -- JSON storing the hide turns checks
    FOREIGN KEY (claimer_controller_id) REFERENCES controllers (id),
    FOREIGN KEY (holder_controller_id) REFERENCES controllers (id)
);

CREATE TABLE locations (
    id SERIAL PRIMARY KEY,
    name text NOT NULL,
    description text NOT NULL,
    zone_id INT,
    setup_turn INT DEFAULT 0, -- Turn in which the location was created
    discovery_diff INT DEFAULT 0,
    controller_id INT DEFAULT NULL, -- Owner of secret location
    can_be_destroyed BOOLEAN DEFAULT FALSE,
    can_be_repaired BOOLEAN DEFAULT FALSE,
    is_base BOOLEAN DEFAULT FALSE, -- Is a controllers Base
    activate_json JSON DEFAULT '{}'::json,
    FOREIGN KEY (zone_id) REFERENCES zones (id),
    FOREIGN KEY (controller_id) REFERENCES controllers (id)
);

CREATE TABLE artefacts (
    id SERIAL PRIMARY KEY,          -- Unique id of the artefact
    location_id INTEGER,            -- Foreign key referencing a location
    name TEXT NOT NULL,             -- Name of the artefact
    description TEXT NOT NULL,      -- Description of the artefact
    full_description TEXT NOT NULL,  -- Description of the artefact if the player controls the location
    FOREIGN KEY (location_id) REFERENCES locations (id) -- Link to locations table
);

CREATE TABLE controller_known_locations (
    id SERIAL PRIMARY KEY,
    controller_id INT NOT NULL,
    location_id INT NOT NULL,
    first_discovery_turn INT NOT NULL, -- Turn number when discovery happened
    last_discovery_turn INT NOT NULL, -- Turn number when discovery happened
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (controller_id, location_id), -- Unicity constraint on controller/worker combo
    FOREIGN KEY (controller_id) REFERENCES controllers (id), -- Link to controllers table
    FOREIGN KEY (location_id) REFERENCES locations (id) -- Link to locations table
);

CREATE TABLE location_attack_logs (
    id SERIAL PRIMARY KEY,
    location_name TEXT,
    target_controller_id INT REFERENCES controllers(id), 
    attacker_id INT REFERENCES controllers(id),
    attack_val INT DEFAULT 0,
    defence_val INT DEFAULT 0,
    turn INT NOT NULL,
    success BOOLEAN NOT NULL,
    target_result_text TEXT,
    attacker_result_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (target_controller_id) REFERENCES controllers (id), -- Link to controllers table
    FOREIGN KEY (attacker_id) REFERENCES controllers (id) -- Link to controllers table
);

-- Prepare the Worker Origins
CREATE TABLE worker_origins (
    id SERIAL PRIMARY KEY,
    name text NOT NULL
);

-- Table storing the worker random names by origin
CREATE TABLE worker_names (
    id SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    origin_id INT NOT NULL,
    FOREIGN KEY (origin_id) REFERENCES worker_origins (id)
);

CREATE TABLE workers (
    id SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    origin_id INT NOT NULL,
    zone_id INT NOT NULL,
    is_alive BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (origin_id) REFERENCES worker_origins (id),
    FOREIGN KEY (zone_id) REFERENCES zones (id)
);

CREATE TABLE controller_worker (
    id SERIAL PRIMARY KEY,
    controller_id INT,
    worker_id INT,
    is_primary_controller BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Adding unique constraint
    UNIQUE (controller_id, worker_id),
    UNIQUE (worker_id, is_primary_controller),
    -- Adding FOREIGN KEY
    FOREIGN KEY (controller_id) REFERENCES controllers (id),
    FOREIGN KEY (worker_id) REFERENCES workers (id)
);

CREATE TABLE power_types (
    id SERIAL PRIMARY KEY,
    name text NOT NULL,
    description text
);

CREATE TABLE powers (
    id SERIAL PRIMARY KEY,
    name text NOT NULL,
    description text,
    enquete INT DEFAULT 0,
    attack INT DEFAULT 0,
    defence INT DEFAULT 0,
    other JSON DEFAULT '{}'::json
);

CREATE TABLE link_power_type (
    id SERIAL PRIMARY KEY,
    power_type_id INT NOT NULL,
    power_id INT NOT NULL,
    UNIQUE (power_type_id, power_id),
    FOREIGN KEY (power_type_id) REFERENCES power_types (id),
    FOREIGN KEY (power_id) REFERENCES powers (id)
);

CREATE TABLE worker_powers (
    id SERIAL PRIMARY KEY,
    worker_id INT NOT NULL,
    link_power_type_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (worker_id, link_power_type_id), -- Adding unique constraint
    FOREIGN KEY (worker_id) REFERENCES workers (id),
    FOREIGN KEY (link_power_type_id) REFERENCES link_power_type (id)
);

CREATE TABLE faction_powers (
    id SERIAL PRIMARY KEY,
    faction_id INT NOT NULL,
    link_power_type_id INT NOT NULL,
    UNIQUE (faction_id, link_power_type_id), -- Adding unique constraint
    FOREIGN KEY (faction_id) REFERENCES factions (id),
    FOREIGN KEY (link_power_type_id) REFERENCES link_power_type (id)
);

CREATE TABLE worker_actions (
    id SERIAL PRIMARY KEY,
    worker_id INT NOT NULL,
    turn_number INT NOT NULL DEFAULT 0,
    zone_id INT NOT NULL,
    controller_id INT NOT NULL,
    enquete_val INT DEFAULT 0,
    attack_val INT DEFAULT 0,
    defence_val INT DEFAULT 0,
    action_choice TEXT DEFAULT 'passive',
    action_params JSON DEFAULT '{}'::json,
    report JSON DEFAULT '{}'::json, -- Expected keys 'life_report', 'attack_report', 'investigate_report', 'claim_report', 'secrets_report'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (worker_id, turn_number), -- Adding unique constraint
    FOREIGN KEY (worker_id) REFERENCES workers (id),
    FOREIGN KEY (zone_id) REFERENCES zones (id),
    FOREIGN KEY (controller_id) REFERENCES controllers (id)
);

CREATE TABLE controllers_known_enemies (
    id SERIAL PRIMARY KEY,
    controller_id INT NOT NULL, -- controller A
    discovered_worker_id INT NOT NULL, -- id of the discovered worker
    discovered_controller_id INT, -- Optional id of their controller
    discovered_controller_name TEXT, -- Optional name of their controller
    zone_id INT NOT NULL, -- Zone of discovery
    first_discovery_turn INT NOT NULL, -- Turn number when discovery happened
    last_discovery_turn INT NOT NULL, -- Turn number when discovery happened
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (controller_id, discovered_worker_id), -- Unicity constraint on controller/worker combo
    FOREIGN KEY (controller_id) REFERENCES controllers (id), -- Link to controllers table
    FOREIGN KEY (discovered_worker_id) REFERENCES workers (id), -- Link to workers table
    FOREIGN KEY (discovered_controller_id) REFERENCES controllers (id), -- Link to controllers table
    FOREIGN KEY (zone_id) REFERENCES zones (id) -- Link to zones table
);
