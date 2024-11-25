
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
    ('MAXTURNS', 6, 'Sets number of turns for game');

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

CREATE TABLE workers (
    ID SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    origin_id INT NOT NULL,
    zone_id INT NOT NULL,
    FOREIGN KEY (origin_id) REFERENCES worker_origins (ID),
    FOREIGN KEY (zone_id) REFERENCES zones (ID)
);

-- Table pour stocker the worker random names
CREATE TABLE worker_names (
    ID SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    origin_id INT NOT NULL,
    FOREIGN KEY (origin_id) REFERENCES worker_origins (ID)
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

