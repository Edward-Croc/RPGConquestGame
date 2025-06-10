-- DROP DATABASE IF EXISTS RPGConquestGame;
-- CREATE DATABASE RPGConquestGame;
-- USE RPGConquestGame;

CREATE TABLE mechanics (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    turncounter INT DEFAULT 0,
    gamestate INT DEFAULT 0
);

INSERT INTO mechanics (turncounter, gamestate)
VALUES (0, 0);

CREATE TABLE config (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    name TEXT UNIQUE NOT NULL,
    value TEXT DEFAULT '',
    description TEXT
);

   
-- (Les `INSERT INTO config` sont inchangés, sauf TRUE/FALSE)
INSERT INTO config (name, value, description) VALUES
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
    -- passive, investigate, attack, claim, captured, dead
    ('passiveInvestigateActions', '''passive'',''attack'',''captured''', 'Liste of passive investigation actions'),
    ('activeInvestigateActions', '''investigate'',''claim''', 'Liste of active investigation actions'),
    ('passiveAttackActions', '''passive'',''investigate''', 'Liste of passive attack actions'),
    ('activeAttackActions', '''attack'',''claim''', 'Liste of active attack actions'),
    ('passiveDefenceActions', '''passive'',''investigate'',''attack'',''claim'',''captured''', 'Liste of passive defence actions'),
    ('activeDefenceActions', '', 'Liste of active defense actions'),
    -- Diff vals for investigation results 
    ('REPORTDIFF0', 0, 'Value for Level 0 information'),
    ('REPORTDIFF1', 1, 'Value for Level 1 information'),
    ('REPORTDIFF2', 2, 'Value for Level 2 information'),
    ('REPORTDIFF3', 3, 'Value for Level 3 information'),
    ('LOCATIONNAMEDIFF', 0, 'Value for Location Name'),
    ('LOCATIONINFORMATIONDIFF', 1, 'Value for Location Information'),
    -- Attack choices
    ('attackTimeWindow', 1, 'Number of turns a discovered worker is attackable after being lost'),
    ('canAttackNetwork', 0, 'If 0 then only workers ar shown, > 0 then workers are sorted by networks when network is known = REPORTDIFF2 obtained '),
    -- Diff vals for attack results
    ('LIMIT_ATTACK_BY_ZONE', 0, 'If 0 then attack happens if worker leave zone, > 0 then attack is limited to workers in zone'),
    ('ATTACKDIFF0', 1, 'Value for Attack Success'),
    ('ATTACKDIFF1', 4, 'Value for Capture'),
    ('RIPOSTACTIVE', '1', 'Activate Ripost when attacked'),
    ('RIPOSTDIFF', 2, 'Value for Successful Ripost'),
    -- Diff vals in claim results
    ('DISCRETECLAIMDIFF', 2, 'Value for discrete claim'),
    ('VIOLENTCLAIMDIFF', 0, 'Value for violent claim'),
    -- action text in report config
    ('txt_ps_passive', 'surveille', 'Text for passive action'),
    ('txt_ps_investigate', 'enquete', 'Text for investigate action'),
    ('txt_ps_attack', 'attaque', 'Text for attack action'),
    ('txt_ps_claim', 'revendique le quartier', 'Text for claim action'),
    ('txt_ps_captured', 'a disparu', 'Text for captured action'),
    ('txt_ps_dead', 'a disparu', 'Text for dead action'),
    ('txt_ps_prisoner', 'est un.e agent de %s que nous avons fait.e prisonnier.e', 'Text for beeing prisoner'),
    ('txt_ps_double_agent', 'a infiltré le réseau de %s ', 'Text for being infiltrator'),
    ('txt_inf_passive', 'surveiller', 'Text for passive action'),
    ('txt_inf_investigate', 'enqueter', 'Text for investigate action'),
    ('txt_inf_attack', 'attaquer', 'Text for attack action'),
    ('txt_inf_claim', 'revendiquer le quartier', 'Text for claim action'),
    ('txt_inf_captured', 'as été capturer', 'Text for captured action'),
    ('txt_inf_dead', 'est mort', 'Text for dead action'),
    -- Action End turn effects
    ('continuing_investigate_action', FALSE, 'Does the investigate action stay active' ),
    ('continuing_claimed_action', FALSE, 'Does the claim action stay active' )
    -- Base information
    ,('baseDiscoveryDiff', 4, 'Base discovery value for bases' )
    ,('baseDiscoveryDiffAddPowers', 1, 'Base discovery value Power presence ponderation 0 for no' )
    ,('baseDiscoveryDiffAddWorkers', 1, 'Base discovery value worker presence ponderation 0 for no' )
    ,('baseDiscoveryDiffAddTurns', 1, 'Base discovery value base age presence ponderation 0 for no' )
    ,('maxBonusDiscoveryDiffPowers', 5, 'Maximum bonus obtainable from power presence' )
    ,('maxBonusDiscoveryDiffWorkers', 3, 'Maximum bonus obtainable from worker presence' )
    ,('maxBonusDiscoveryDiffTurns', 3, 'Maximum bonus obtainable from age of base' )
    ,('baseDefenceDiff', 2, 'Base defence value for bases' )
    ,('baseDefenceDiffAddPowers', 1, 'Base defence value Power presence ponderation 0 for no' )
    ,('baseDefenceDiffAddWorkers', 1, 'Base defence value worker presence ponderation 0 for no' )
    ,('baseDefenceDiffAddTurns', 1, 'Base defence value base age presence ponderation 0 for no' )
    ,('attackLocationDiff', 1, 'Difficulty to destroy a Location' )
    ,('textLocationDestroyed', 'Le lieu %s a été détruit selon votre bon vouloir', 'Text for location destroyed')
    ,('textLocationDestroyed', 'Le lieu %s a été pillée', 'Text for location destroyed')
    ,('textLocationNotDestroyed', 'Le lieu %s n’a pas été détruit, nos excuses', 'Text for location not destroyed')
;

CREATE TABLE players (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    passwd VARCHAR(64) NOT NULL,
    is_privileged TINYINT(1) DEFAULT 0
);

INSERT INTO players (username, passwd, is_privileged)
VALUES ('gm', 'orga', 1);

CREATE TABLE factions (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    name TEXT NOT NULL
);

CREATE TABLE controllers (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    firstname TEXT NOT NULL,
    lastname TEXT NOT NULL,
    url TEXT,
    story TEXT,
    start_workers INT DEFAULT 1,
    recruited_workers INT DEFAULT 0,
    turn_recruited_workers INT DEFAULT 0,
    turn_firstcome_workers INT DEFAULT 0,
    ia_type TEXT DEFAULT '',
    faction_id INT NOT NULL,
    fake_faction_id INT NOT NULL,
    FOREIGN KEY (faction_id) REFERENCES factions(ID),
    FOREIGN KEY (fake_faction_id) REFERENCES factions(ID)
);

CREATE TABLE player_controller (
    controller_id INT NOT NULL,
    player_id INT NOT NULL,
    PRIMARY KEY (controller_id, player_id),
    FOREIGN KEY (controller_id) REFERENCES controllers(ID),
    FOREIGN KEY (player_id) REFERENCES players(ID)
);

CREATE TABLE zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    defence_val INT DEFAULT 6,
    calculated_defence_val INT DEFAULT 6,
    claimer_controller_id INT,
    holder_controller_id INT,
    FOREIGN KEY (claimer_controller_id) REFERENCES controllers(ID),
    FOREIGN KEY (holder_controller_id) REFERENCES controllers(ID)
);

CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    zone_id INT,
    setup_turn INT DEFAULT 0,
    discovery_diff INT DEFAULT 0,
    controller_id INT DEFAULT NULL,
    can_be_destroyed TINYINT(1) DEFAULT 0,
    is_base TINYINT(1) DEFAULT 0,
    activate_json JSON DEFAULT '{}',
    FOREIGN KEY (zone_id) REFERENCES zones(ID),
    FOREIGN KEY (controller_id) REFERENCES controllers(ID)
);

CREATE TABLE controller_known_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    controller_id INT NOT NULL,
    location_id INT NOT NULL,
    first_discovery_turn INT NOT NULL,
    last_discovery_turn INT NOT NULL,
    UNIQUE (controller_id, location_id),
    FOREIGN KEY (controller_id) REFERENCES controllers(ID),
    FOREIGN KEY (location_id) REFERENCES locations(ID)
);

CREATE TABLE artefacts (
    id INT AUTO_INCREMENT PRIMARY KEY,          -- Unique ID of the artefact
    location_id INT NOT NULL,                   -- Foreign key referencing a location
    name TEXT NOT NULL,                         -- Name of the artefact
    description TEXT NOT NULL,                  -- Description of the artefact
    full_description TEXT NOT NULL,             -- Full description if the player controls the location
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

CREATE TABLE worker_origins (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    name TEXT NOT NULL
);

CREATE TABLE worker_names (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    firstname TEXT NOT NULL,
    lastname TEXT NOT NULL,
    origin_id INT NOT NULL,
    FOREIGN KEY (origin_id) REFERENCES worker_origins(ID)
);

CREATE TABLE workers (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    firstname TEXT NOT NULL,
    lastname TEXT NOT NULL,
    origin_id INT NOT NULL,
    zone_id INT NOT NULL,
    is_alive TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (origin_id) REFERENCES worker_origins(ID),
    FOREIGN KEY (zone_id) REFERENCES zones(ID)
);

CREATE TABLE controller_worker (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    controller_id INT,
    worker_id INT,
    is_primary_controller TINYINT(1) DEFAULT 1,
    UNIQUE (controller_id, worker_id),
    UNIQUE (worker_id, is_primary_controller),
    FOREIGN KEY (controller_id) REFERENCES controllers(ID),
    FOREIGN KEY (worker_id) REFERENCES workers(ID)
);

CREATE TABLE power_types (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT
);

CREATE TABLE powers (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    enquete INT DEFAULT 0,
    attack INT DEFAULT 0,
    defence INT DEFAULT 0,
    other JSON DEFAULT '{}'
);

CREATE TABLE link_power_type (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    power_type_id INT NOT NULL,
    power_id INT NOT NULL,
    UNIQUE (power_type_id, power_id),
    FOREIGN KEY (power_type_id) REFERENCES power_types(ID),
    FOREIGN KEY (power_id) REFERENCES powers(ID)
);

CREATE TABLE worker_powers (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    worker_id INT NOT NULL,
    link_power_type_id INT NOT NULL,
    UNIQUE (worker_id, link_power_type_id),
    FOREIGN KEY (worker_id) REFERENCES workers(ID),
    FOREIGN KEY (link_power_type_id) REFERENCES link_power_type(ID)
);

CREATE TABLE faction_powers (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    faction_id INT NOT NULL,
    link_power_type_id INT NOT NULL,
    UNIQUE (faction_id, link_power_type_id),
    FOREIGN KEY (faction_id) REFERENCES factions(ID),
    FOREIGN KEY (link_power_type_id) REFERENCES link_power_type(ID)
);

CREATE TABLE worker_actions (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    worker_id INT NOT NULL,
    turn_number INT NOT NULL DEFAULT 0,
    zone_id INT NOT NULL,
    controller_id INT NOT NULL,
    enquete_val INT DEFAULT 0,
    attack_val INT DEFAULT 0,
    defence_val INT DEFAULT 0,
    action_choice TEXT DEFAULT 'passive',
    action_params JSON DEFAULT '{}',
    report JSON DEFAULT '{}',
    UNIQUE (worker_id, turn_number),
    FOREIGN KEY (worker_id) REFERENCES workers(ID),
    FOREIGN KEY (zone_id) REFERENCES zones(ID),
    FOREIGN KEY (controller_id) REFERENCES controllers(ID)
);

CREATE TABLE controllers_known_enemies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    controller_id INT NOT NULL,
    discovered_worker_id INT NOT NULL,
    discovered_controller_id INT,
    discovered_controller_name TEXT,
    zone_id INT NOT NULL,
    first_discovery_turn INT NOT NULL,
    last_discovery_turn INT NOT NULL,
    UNIQUE (controller_id, discovered_worker_id),
    FOREIGN KEY (controller_id) REFERENCES controllers(ID),
    FOREIGN KEY (discovered_worker_id) REFERENCES workers(ID),
    FOREIGN KEY (discovered_controller_id) REFERENCES controllers(ID),
    FOREIGN KEY (zone_id) REFERENCES zones(ID)
);
