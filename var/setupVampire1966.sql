
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
('Stazione ferroviaria norte', 'Au nord de Florence se situe Peretola, un village qui c’est fait absorbée pour devenir le quartier des habitations bas de gamme due à la présence du petit aéroport de Florence et des quelques avions qui passe au dessus des habitation.'),
('Le Cascine', 'Le quartier de Le Cascine tire son nom de son parc, le plus grand parc public de la ville de Florence qui tire son nom des anciennes fermes grand-ducales. Le quartier est principalement occupée par les halles du Mercatello delle Cascine plus vaste marché de la ville. '),
('Monticelli', 'Située de l’autre côté du Fleuve Arno face au parc Cascine c’est ici dans ce quartier excentrée de florence que l’on trouve les étudiants et ceux qui tiennent les boutiques du Mercatello delle Cascine.'),
('Fortezza Basso', 'La massive Forteresse de Basso du 16eme siècle qui est désormais devenu un palais des exposition donne son nom au quartier qui contient aussi le Centre hospitalier universitaire et la Scuola di ingegneria.'),
('Santa Maria Novella', 'Ce quartier qui est un centre-ville commerçant tire son nom de sa basilique Sainte Marie nouvelle. Ce quartier est traversée par la rue commerçante Sainte marie qui mène de la gare Santa maria de Firenze jusqu’au au Mercato di San Lorenzo.'),
('Indipendenza', ''),
('Duomo', 'Le Duomo est l’autre nom de la Cathédrale Santa Maria del Fiore, la 5eme église d’europe qui préside la Piazza del Duomo. Cette place du centre ville piétonnier est bordée du musée de l’opéra et de plusieurs Palazzos.'),
('Palazzo Pitti', 'Au centre du quartier domine le Palazzo Pitti, un sublime palais de la renaissance qui forme le coeur des affaire du sénéchal actuelle de la ville. Ce quartier est située de l’autre côté de l’arno et connectée au centre ville par le fameux Ponte Vecchio.'),
('Santa Croce', 'Entre la basilique Santa Croce et la place du buste de Guglielmo Oberdan ce trouve ce que l’on peut qualifier de quartier financier de Florence.  Et ses multiples ‘rue Giovanni’ (Angelico, Bovio, Ciambue et Lanza)'),
('Oberdan', 'Entre la basilique Santa Croce et la place du buste de Guglielmo Oberdan ce trouve ce que l’on peut qualifier de quartier financier de Florence.  Et ses multiples ‘rue Giovanni’ (Angelico, Bovio, Ciambue et Lanza)'),
('Piazza della Liberta & Savonarola', ''),
('Michelangelo-Gavinana', 'Ce quartier du sud de Florence est le plus étendu des quartiers composée de bâtiments qui longe le fleuve Arno. On y trouve un golfe, des hôtels de luxe, une parfumerie et la Viale Europa qui travers le quartier de part en part.'),
('Campo di Marte', 'L’ancien Champ de mars de Firenze est devenue un immense complexe sportif au milieu d’un quartier plus résidentiel. L’autre grand élément de ce quartier est sa gare fret du champ de mars.');


-- Insert the data
INSERT INTO locations (name, description, is_secret, zone_id) VALUES
('Gare', '', 0, (SELECT ID FROM zones WHERE name = 'Stazione ferroviaria norte')),
('Le barrage','', 1, (SELECT ID FROM zones WHERE name = 'Stazione ferroviaria norte')),
('Fortezza da Basso', '', 0, (SELECT ID FROM zones WHERE name = 'Fortezza Basso')),
('Facolta di Ingegneria', '', 0, (SELECT ID FROM zones WHERE name = 'Fortezza Basso')),
('Gare', '', 0, (SELECT ID FROM zones WHERE name = 'Santa Maria Novella')),
('Balistero','', 0, (SELECT ID FROM zones WHERE name = 'Santa Maria Novella')),
('L’hospitalidero','', 0, (SELECT ID FROM zones WHERE name = 'Indipendenza')),
('Palazzo Vecchio', '', 0, (SELECT ID FROM zones WHERE name = 'Duomo')),
('Duomo', '', 0, (SELECT ID FROM zones WHERE name = 'Duomo')),
('Cairn','', 1, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
('Palazzo Pitti','', 0, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
('Santa Croce', '', 0, (SELECT ID FROM zones WHERE name = 'Santa Croce')),
('Banca Di Firenze','', 0, (SELECT ID FROM zones WHERE name = 'Oberdan')),
('Piazza della Liberta', '', 0,(SELECT ID FROM zones WHERE name = 'Piazza della Liberta & Savonarola')),
('Pallazzo Medeci Ricardi', '', 1,(SELECT ID FROM zones WHERE name = 'Piazza della Liberta & Savonarola')),
('Musée Degli di Firenze','', 0, (SELECT ID FROM zones WHERE name = 'Michelangelo'));