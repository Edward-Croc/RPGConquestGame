
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
    ('DEBUG', 'true', 'Activates the Debugging texts'),
    ('TITLE', 'RPGConquest', 'Name of game'),
    ('PRESENTATION', 'RPGConquest', 'Name of game'),
    ('basePowerNames', '''power2'',''power2''', 'List of Powers accessible to all workers'),
    ('MINROLL', 1, 'Minimum Roll for an active worker'),
    ('MAXROLL', 6, 'Maximum Roll for a an active worker'),
    ('PASSIVEVAL', 3, 'Value for passive actions'),
    -- passive, investigate, attack, claim
    ('passiveInvestigateActions', '''passive'',''attack''', 'Liste of passive investigation actions'),
    ('activeInvestigateActions', '''investigate'',''claim''', 'Liste of passive investigation actions'),
    ('passiveActionActions', '''passive'',''investigate''', 'Liste of passive investigation actions'),
    ('activeActionActions', '''attack'',''claim''', 'Liste of passive investigation actions'),
    ('passiveDefenceActions', '''passive'',''investigate'',''attack'',''claim''', 'Liste of passive defence actions'),
    ('activeDefenceActions', '', 'Liste of active investigation actions');

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
    description text NOT NULL
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
    action INT DEFAULT 0,
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
    action_val INT DEFAULT 0,
    defence_val INT DEFAULT 0,
    action TEXT DEFAULT 'passive',
    action_params JSON DEFAULT '{}'::json,
    report JSON DEFAULT '{}'::json,
    FOREIGN KEY (worker_id) REFERENCES workers (ID),
    FOREIGN KEY (zone_id) REFERENCES zones (ID),
    FOREIGN KEY (controler_id) REFERENCES controlers (ID)
);