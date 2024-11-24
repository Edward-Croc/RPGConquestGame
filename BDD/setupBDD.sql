-- make sur a database RPGConquestGame exists and a user php_gamedev exists 

-- DROP DATABASE IF EXISTS RPGConquestGame;
-- CREATE DATABASE RPGConquestGame OWNER php_gamedev;
-- CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE mecanics (
    ID SERIAL PRIMARY KEY,
    turncounter INTEGER DEFAULT 0,
    gamestat BOOLEAN DEFAULT false
);

INSERT INTO mecanics (turncounter, gamestat) 
VALUES (0, FALSE);

CREATE TABLE config (
    ID SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    value text DEFAULT '',
    description VARCHAR(50)
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
    is_privileged BOOLEAN
);

INSERT INTO players (username, passwd, is_privileged) 
VALUES 
    ('gm', 'orga', true);

CREATE TABLE factions (
    ID SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

CREATE TABLE controlers (
    ID SERIAL PRIMARY KEY,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    startworkers INT DEFAULT 1,
    is_AI BOOLEAN DEFAULT FALSE,
    faction_id INT,
    fake_faction_id INT,
    FOREIGN KEY (faction_id) REFERENCES factions (ID),
    FOREIGN KEY (fake_faction_id) REFERENCES factions (ID)
);

CREATE TABLE player_controler (
    controler_id INT,
    player_id INT,
    PRIMARY KEY (controler_id, player_id),
    FOREIGN KEY (controler_id) REFERENCES controlers (ID),
    FOREIGN KEY (player_id) REFERENCES players (ID)
);

CREATE TABLE workers (
    ID SERIAL PRIMARY KEY,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL
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
