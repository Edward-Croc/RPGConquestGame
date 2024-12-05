
-- DROP DATABASE IF EXISTS RPGConquestGame;
-- CREATE DATABASE RPGConquestGame OWNER php_gamedev;
-- CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE mecanics (
    ID SERIAL PRIMARY KEY,
    turncounter INTEGER DEFAULT 0,
    gamestat INTEGER DEFAULT 0
);

INSERT INTO mecanics (turncounter, gamestat)
VALUES (0, 0);

CREATE TABLE config (
    ID SERIAL PRIMARY KEY,
    name text UNIQUE NOT NULL,
    value text DEFAULT '',
    description text
);

INSERT INTO config (name, value, description)
VALUES
    ('DEBUG', 'false', 'Activates the Debugging texts'),
    ('DEBUG_REPORT', 'false', 'Activates the Debugging texts for the investigation report'),
    ('DEBUG_ATTACK', 'true', 'Activates the Debugging texts for the attack report mecanics'),
    ('DEBUG_TRANSFORM', 'false', 'Activates the Debugging texts for the attack report mecanics'),
    ('TITLE', 'RPGConquest', 'Name of game'),
    ('PRESENTATION', 'RPGConquest', 'Name of game'),
    ('basePowerNames', '''power2'',''power2''', 'List of Powers accessible to all workers'),
    -- worker creation
    ('first_come_nb_choices', '1', ''),
    ('first_come_origin_list', 'rand', ''),
    ('recrutement_nb_choices', '3', ''),
    ('recrutement_origin_list', '1,2,3,4,5', ''),
    ('local_origin_list', '1', ''),
    -- ('recrutement_hobby', '1', ''),
    -- ('recrutement_metier', '1', ''),
    ('recrutement_disciplines', '1', ''),
    ('recrutement_transformation', '{"action": "check"}', ''),
    -- Worker experience
    ('age_hobby', 'FALSE', ''),
    ('age_metier', 'FALSE', ''),
    ('age_discipline', '{"age": ["2"]}', ''),
    ('age_transformation', '{"action": "check"}', ''),
    -- worker rolls
    ('MINROLL', 1, 'Minimum Roll for an active worker'),
    ('MAXROLL', 6, 'Maximum Roll for a an active worker'),
    ('PASSIVEVAL', 3, 'Value for passive actions'),
    -- Diff vals in report 
    ('REPORTDIFF0', 0, 'Value for Level 0 information'),
    ('REPORTDIFF1', 1, 'Value for Level 1 information'),
    ('REPORTDIFF2', 2, 'Value for Level 2 information'),
    ('REPORTDIFF3', 3, 'Value for Level 3 information'),
    -- Diff vals in report
    ('ATTACKDIFF0', 1, 'Value for Attack Success'),
    ('ATTACKDIFF1', 4, 'Value for Capture'),
    ('RIPOSTACTIVE', TRUE, 'Value for Succesful Ripost'),
    ('RIPOSTONDEATH', FALSE, 'When Killed or Captured still riposts'),
    ('RIPOSTDIFF', 2, 'Value for Succesful Ripost'),
    -- passive, investigate, attack, claim, captured, dead
    ('passiveInvestigateActions', '''passive'',''attack'',''captured''', 'Liste of passive investigation actions'),
    ('activeInvestigateActions', '''investigate'',''claim''', 'Liste of active investigation actions'),
    ('passiveAttackActions', '''passive'',''investigate''', 'Liste of passive attack actions'),
    ('activeAttackActions', '''attack'',''claim''', 'Liste of active attack actions'),
    ('passiveDefenceActions', '''passive'',''investigate'',''attack'',''claim'',''captured''', 'Liste of passive defence actions'),
    ('activeDefenceActions', '', 'Liste of active defense actions'),
    -- action text in report config
    ('txt_ps_passive', 'surveille', 'Texte for passive action'),
    ('txt_ps_investigate', 'enquete', 'Texte for investigate action'),
    ('txt_ps_attack', 'attaque', 'Texte for attack action'),
    ('txt_ps_claim', 'revendique le quartier', 'Texte for claim action'),
    ('txt_inf_passive', 'surveiller', 'Texte for passive action'),
    ('txt_inf_investigate', 'enqueter', 'Texte for investigate action'),
    ('txt_inf_attack', 'attaquer', 'Texte for attack action'),
    ('txt_inf_claim', 'revendiquer le quartier', 'Texte for claim action'),
    -- 
    ('continuing_investigate_action', FALSE, 'Does the investigate action stay active' ),
    ('continuing_claimed_action', FALSE, 'Does the claim action stay active' )
;

CREATE TABLE players (
    ID SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    passwd VARCHAR(64) NOT NULL,
    is_privileged BOOLEAN DEFAULT FALSE
);

INSERT INTO players (username, passwd, is_privileged)
VALUES
    ('gm', 'orga', true);

CREATE TABLE factions (
    ID SERIAL PRIMARY KEY,
    name text NOT NULL
);

CREATE TABLE controlers (
    ID SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    startworkers INT DEFAULT 1,
    is_AI BOOLEAN DEFAULT FALSE,
    faction_id INT NOT NULL,
    fake_faction_id INT NOT NULL,
    FOREIGN KEY (faction_id) REFERENCES factions (ID),
    FOREIGN KEY (fake_faction_id) REFERENCES factions (ID)
);

CREATE TABLE player_controler (
    controler_id INT NOT NULL,
    player_id INT NOT NULL,
    PRIMARY KEY (controler_id, player_id),
    FOREIGN KEY (controler_id) REFERENCES controlers (ID),
    FOREIGN KEY (player_id) REFERENCES players (ID)
);


-- Create the zones and locations
CREATE TABLE zones (
    id SERIAL PRIMARY KEY,
    name text NOT NULL,
    description text NOT NULL,
    defence_val INT DEFAULT 6,
    calculated_defence_val INT DEFAULT 6,
    claimer_controler_id INT, 
    holder_controler_id INT
);

CREATE TABLE locations (
    id SERIAL PRIMARY KEY,
    name text NOT NULL,
    description text NOT NULL,
    is_secret INT DEFAULT 0,
    zone_id INT,
    FOREIGN KEY (zone_id) REFERENCES zones (ID)
);

-- Prepare the Worker Origins
CREATE TABLE worker_origins (
    ID SERIAL PRIMARY KEY,
    name text NOT NULL
);

-- Table storing the worker random names by origin
CREATE TABLE worker_names (
    ID SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    origin_id INT NOT NULL,
    FOREIGN KEY (origin_id) REFERENCES worker_origins (ID)
);

CREATE TABLE workers (
    ID SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    origin_id INT NOT NULL,
    zone_id INT NOT NULL,
    is_alive BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (origin_id) REFERENCES worker_origins (ID),
    FOREIGN KEY (zone_id) REFERENCES zones (ID)
);

CREATE TABLE controler_worker (
    controler_id INT,
    worker_id INT,
    is_primary_controler BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (controler_id, worker_id),
    UNIQUE (worker_id, is_primary_controler), -- Adding unique constraint
    FOREIGN KEY (controler_id) REFERENCES controlers (ID),
    FOREIGN KEY (worker_id) REFERENCES workers (ID)
);

CREATE TABLE power_types (
    ID SERIAL PRIMARY KEY,
    name text NOT NULL,
    activation JSON
);

CREATE TABLE powers (
    ID SERIAL PRIMARY KEY,
    name text NOT NULL,
    enquete INT DEFAULT 0,
    attack INT DEFAULT 0,
    defence INT DEFAULT 0,
    other JSON
);

CREATE TABLE link_power_type (
    ID SERIAL PRIMARY KEY,
    power_type_id INT NOT NULL,
    power_id INT NOT NULL,
    UNIQUE (power_type_id, power_id),
    FOREIGN KEY (power_type_id) REFERENCES power_types (ID),
    FOREIGN KEY (power_id) REFERENCES powers (ID)
);

CREATE TABLE worker_powers (
    ID SERIAL PRIMARY KEY,
    worker_id INT NOT NULL,
    link_power_type_id INT NOT NULL,
    UNIQUE (worker_id, link_power_type_id), -- Adding unique constraint
    FOREIGN KEY (worker_id) REFERENCES workers (ID),
    FOREIGN KEY (link_power_type_id) REFERENCES link_power_type (ID)
);

CREATE TABLE faction_powers (
    ID SERIAL PRIMARY KEY,
    faction_id INT NOT NULL,
    link_power_type_id INT NOT NULL,
    UNIQUE (faction_id, link_power_type_id), -- Adding unique constraint
    FOREIGN KEY (faction_id) REFERENCES factions (ID),
    FOREIGN KEY (link_power_type_id) REFERENCES link_power_type (ID)
);

CREATE TABLE worker_actions (
    ID SERIAL PRIMARY KEY,
    worker_id INT NOT NULL,
    turn_number INT NOT NULL,
    zone_id INT NOT NULL,
    controler_id INT NOT NULL,
    enquete_val INT DEFAULT 0,
    attack_val INT DEFAULT 0,
    defence_val INT DEFAULT 0,
    action_choice TEXT DEFAULT 'passive',
    action_params JSON DEFAULT '{}'::json,
    report JSON DEFAULT '{}'::json, -- Expected keys investigate_report, attack_report, life_report
    UNIQUE (worker_id, turn_number), -- Adding unique constraint
    FOREIGN KEY (worker_id) REFERENCES workers (ID),
    FOREIGN KEY (zone_id) REFERENCES zones (ID),
    FOREIGN KEY (controler_id) REFERENCES controlers (ID)
);

CREATE TABLE controlers_known_enemies (
    id SERIAL PRIMARY KEY,
    controler_id INT NOT NULL, -- Controler A
    discovered_worker_id INT NOT NULL, -- ID of the discovered worker
    discovered_controler_id INT, -- Optional ID of their controler
    discovered_controler_name TEXT, -- Optional name of their controler
    zone_id INT NOT NULL, -- Zone of discovery
    first_discovery_turn INT NOT NULL, -- Turn number when discovery happened
    last_discovery_turn INT NOT NULL, -- Turn number when discovery happened
    UNIQUE (controler_id, discovered_worker_id), -- Unicity constraint on controler/worker combo
    FOREIGN KEY (controler_id) REFERENCES controlers (ID), -- Link to controlers table
    FOREIGN KEY (discovered_worker_id) REFERENCES workers (ID), -- Link to workers table
    FOREIGN KEY (discovered_controler_id) REFERENCES controlers (ID), -- Link to controlers table
    FOREIGN KEY (zone_id) REFERENCES zones (ID) -- Link to zones table
);
