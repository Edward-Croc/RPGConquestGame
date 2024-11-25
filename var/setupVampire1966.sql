
UPDATE config SET value = 'Firenze 1966' WHERE name = 'TITLE';
UPDATE config SET 
    value = 'Le 6 novembre 1966, l''Arno inonde une grande partie du centre-ville, endommageant de nombreux chefs-d''œuvre. Un grand mouvement de solidarité internationale naît à la suite de cet évènement et mobilise des milliers de volontaires, surnommés Les anges de la boue.'
    WHERE name = 'PRESENTATION';

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
    (
        --'Angelo', 'Ricciotti',
        'Antonio', 'Mazzino',
        1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Brujah' ),
        (SELECT ID FROM factions WHERE name = 'Brujah' )
    ),
    ('Elisa', 'Bonapart', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Toréador' ),
        (SELECT ID FROM factions WHERE name = 'Toréador' )
    ),
    ('Gaetano', 'Trentini', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Tremere' ),
        (SELECT ID FROM factions WHERE name = 'Tremere' )
    ),
    ('Duca Amandin', 'Franco', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Ventrue' ),
        (SELECT ID FROM factions WHERE name = 'Ventrue' )
    ),
    ('Duca Gaston', 'da Firenze', 10, FALSE,
        (SELECT ID FROM factions WHERE name = 'Giovanni' ),
        (SELECT ID FROM factions WHERE name = 'Giovanni' )
    ),
    (
        'Ana', 'Walkil',
        -- 'Dame', 'Vizirof',
        1, FALSE,
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
    ('Frère', 'Lorenzo', 1, TRUE,
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
        (SELECT ID FROM controlers WHERE lastname in ('Mazzino', 'Ricciotti'))
    ), -- player1 controls  Angelo Ricciotti/Antonio Mazzino,
    (
        (SELECT ID FROM players WHERE username = 'player2'),
        (SELECT ID FROM controlers WHERE lastname = 'Calabreze')
    );

INSERT INTO zones (name, description) VALUES
('Railway Station', ''),
('Le Cascine', ''),
('Monticelli', ''),
('Fortezza Basso', ''),
('Santa Maria Novella (Railway Station)', ''),
('Santa Maria Novella (Square)', ''),
('Indipendenza', ''),
('Duomo', ''),
('Palazzo Pitti', ''),
('Santa Croce', ''),
('Piazza della Liberta & Savonarola', ''),
('Oberdan', ''),
('Michelangelo', ''),
('Campo di Marte', ''),
('Gavinana', '');


-- Insert the data
INSERT INTO locations (name, description, is_secret, zone_id) VALUES
('Gare', '', 0, (SELECT ID FROM zones WHERE name = 'Railway Station')),
('Le barrage','', 1, (SELECT ID FROM zones WHERE name = 'Railway Station')),
('Fortezza da Basso', '', 0, (SELECT ID FROM zones WHERE name = 'Fortezza Basso')),
('Gare', '', 0, (SELECT ID FROM zones WHERE name = 'Santa Maria Novella (Railway Station)')),
('Facolta di Ingegneria/Balistero','', 0, (SELECT ID FROM zones WHERE name = 'Santa Maria Novella (Square)')),
('L’hospitalidero','', 0, (SELECT ID FROM zones WHERE name = 'Indipendenza')),
('Palazzo Vecchio', '', 0, (SELECT ID FROM zones WHERE name = 'Duomo')),
('Duomo', '', 0, (SELECT ID FROM zones WHERE name = 'Duomo')),
('Cairn','', 1, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
('Palazzo Pitti','', 0, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
('Santa Croce', '', 0, (SELECT ID FROM zones WHERE name = 'Santa Croce')),
('Piazza della Liberta', '', 0,(SELECT ID FROM zones WHERE name = 'Piazza della Liberta & Savonarola')),
('Pallazzo Medeci Ricardi', '', 1,(SELECT ID FROM zones WHERE name = 'Piazza della Liberta & Savonarola')),
('Banca Di Firenze','', 0, (SELECT ID FROM zones WHERE name = 'Oberdan')),
('Musée Degli di Firenze','', 0, (SELECT ID FROM zones WHERE name = 'Michelangelo'));