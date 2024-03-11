-- make sur a database RPGConquestGame exists and a user php_gamedev exists 

-- DROP DATABASE IF EXISTS RPGConquestGame;
-- CREATE DATABASE RPGConquestGame OWNER php_gamedev;
-- CREATE EXTENSION IF NOT EXISTS pgcrypto;

INSERT INTO players (username, passwd, is_privileged) 
VALUES 
    ('player1', 'one', false),
    ('player2', 'two', false)
;

INSERT INTO factions (name) 
VALUES
    ('Brujah'),
    ('Ventrue'),
    ('Toréador'),
    ('Tremere'),
    ('Malkavien'),
    ('Gangrel'),
    ('Nosfératu'),
    ('Giovanni'),
    ('Assamites'),
    ('Discple'),
    ('Tzimisce'),
    ('Lasombra'),
    ('Humain'),
    ('Eglise'),
    ('Démon');

INSERT INTO controlers (
    firstname, lastname, startworkers, is_AI, faction_id, fake_faction_id
) VALUES 
    ('Dame', 'Calabreze', 0, FALSE,
        (SELECT ID FROM factions WHERE name = 'Malkavien' ),
        (SELECT ID FROM factions WHERE name = 'Malkavien' )
    ),
    ('Angelo', 'Ricciotti', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Brujah' ),
        (SELECT ID FROM factions WHERE name = 'Brujah' )
    ),
    ('Elisa', 'Bonapart', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Toréador' ),
        (SELECT ID FROM factions WHERE name = 'Toréador' )
    ),
    ('Gaetano', 'Trentini', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Tremère' ),
        (SELECT ID FROM factions WHERE name = 'Tremère' )
    ),
    ('Duca Amandin', 'Franco', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Ventrue' ),
        (SELECT ID FROM factions WHERE name = 'Ventrue' )
    ),
    ('Duca Gaston', 'da Firenze', 10, FALSE,
        (SELECT ID FROM factions WHERE name = 'Giovanni' ),
        (SELECT ID FROM factions WHERE name = 'Giovanni' )
    ),
    ('Dame', 'Vizirof', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Assamites' ),
        (SELECT ID FROM factions WHERE name = 'Tremère' )
    ),
    ('Adamo', 'de Toscane', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Nosfératu' ),
        (SELECT ID FROM factions WHERE name = 'Nosfératu' )
    ),
    ('Der', 'Swartz', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Gangrel' ),
        (SELECT ID FROM factions WHERE name = 'Tzimisce' )
    ),
    ('Hassan', 'Ben Hassan', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Discple' ),
        (SELECT ID FROM factions WHERE name = 'Discple' )
    ),
    ('Frère Inquisiteur', 'Lorenzo', 1, TRUE,
        (SELECT ID FROM factions WHERE name = 'Humain' ),
        (SELECT ID FROM factions WHERE name = 'Eglise' )
    ),
    ('Dark', 'Dimonio', 1, TRUE,
        (SELECT ID FROM factions WHERE name = 'Humain' ),
        (SELECT ID FROM factions WHERE name = 'Démon' )
    );


INSERT INTO player_controler (controler_id, player_id)
    SELECT ID, (SELECT ID FROM players WHERE username = 'gm')
    FROM controlers;

INSERT INTO player_controler (player_id, controler_id) 
VALUES 
    (
        (SELECT ID FROM players WHERE username = 'player1'),
        (SELECT ID FROM controlers WHERE lastname = 'Ricciotti')
    ), -- player1 controls  Angelo Ricciotti
    (
        (SELECT ID FROM players WHERE username = 'player2'),
        (SELECT ID FROM controlers WHERE lastname = 'Calabreze')
    ); -- Gaetano Trentini controls player2