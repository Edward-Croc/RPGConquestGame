
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
        (SELECT ID FROM factions WHERE name = 'Tremere' )
    ),
    ('Adamo', 'de Toscane', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Nosfératu' ),
        (SELECT ID FROM factions WHERE name = 'Nosfératu' )
    ),
    ('Der', 'Swartz', 1, FALSE,
        (SELECT ID FROM factions WHERE name = 'Tzimisce' ),
        (SELECT ID FROM factions WHERE name = 'Gangrel' )
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
('Indipendenza', 'Le quartier de l''indépendance et la place publique dont elle tire le nom sont situées au coeur du centre historique de Firenze et sont faciles à atteindre de la gare principale de Santa Maria Novella.'),
('Duomo', 'Le Duomo est l’autre nom de la Cathédrale Santa Maria del Fiore, la 5eme église d’europe qui préside la Piazza del Duomo. Cette place du centre ville piétonnier est bordée du musée de l’opéra et de plusieurs Palazzos.'),
('Palazzo Pitti', 'Au centre du quartier domine le Palazzo Pitti, un sublime palais de la renaissance qui forme le coeur des affaire du sénéchal actuelle de la ville. Ce quartier est située de l’autre côté de l’arno et connectée au centre ville par le fameux Ponte Vecchio.'),
('Santa Croce', 'Entre la basilique Santa Croce et la place du buste de Guglielmo Oberdan ce trouve ce que l’on peut qualifier de quartier financier de Florence.  Et ses multiples ‘rue Giovanni’ (Angelico, Bovio, Ciambue et Lanza)'),
('Oberdan', 'Entre la basilique Santa Croce et la place du buste de Guglielmo Oberdan ce trouve ce que l’on peut qualifier de quartier financier de Florence.  Et ses multiples ‘rue Giovanni’ (Angelico, Bovio, Ciambue et Lanza)'),
('Piazza della Liberta & Savonarola', 'La place de la liberté marque la fin du centre ville et le début du quartier des universités anciennes de la ville ou l’on peut trouver la Universite de Syracuse et la Instituto Leonardo Da Vinci.'),
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
('Instituto Leonardo Da Vinci','', 0, (SELECT ID FROM zones WHERE name = 'Indipendenza')),
('Piazza della Liberta','', 0, (SELECT ID FROM zones WHERE name = 'Indipendenza')),
('Palazzo Vecchio', '', 0, (SELECT ID FROM zones WHERE name = 'Duomo')),
('Duomo', '', 0, (SELECT ID FROM zones WHERE name = 'Duomo')),
('Cairn','', 1, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
('Palazzo Pitti','', 0, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
('Santa Croce', '', 0, (SELECT ID FROM zones WHERE name = 'Santa Croce')),
('Banca Di Firenze','', 0, (SELECT ID FROM zones WHERE name = 'Oberdan')),
('Piazza della Liberta', '', 0,(SELECT ID FROM zones WHERE name = 'Piazza della Liberta & Savonarola')),
('Pallazzo Medeci Ricardi', '', 1,(SELECT ID FROM zones WHERE name = 'Piazza della Liberta & Savonarola')),
('Musée Degli di Firenze','', 0, (SELECT ID FROM zones WHERE name = 'Michelangelo'));

-- Insert names into worker_origins
INSERT INTO worker_origins (name) VALUES 
    ('Italie'),
    ('Suede'),
    ('France'),
    ('Allemagne'),
    ('Angleterre'),
    ('Espagne'),
    ('Autriche');

-- Insert names into worker_names
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Amerigo', 'Attilio', 1),
    ('Marco', 'Martino', 1),
    ('Benvenuto', 'Braulio', 1),
    ('Cirrillo', 'Cajetan', 1),
    ('Donato', 'Demarco', 1),
    ('Eriberto', 'Ettore', 1),
    ('Flavio', 'Fortino', 1),
    ('Gino', 'Giancarlo', 1),
    ('Hortensio', 'Honorius', 1),
    ('Indro', 'Lombardi', 1),
    ('Massimo', 'Maury', 1),
    ('Bianca', 'Abriana', 1),
    ('Carlotta', 'Cara', 1),
    ('Donatella', 'Domani', 1),
    ('Fabiana', 'Fiorella', 1),
    ('Graziella', 'Giordana', 1),
    ('Ilaria', 'Itala', 1),
    ('Justina', 'Lanza', 1),
    ('Liona', 'Lave', 1),
    ('Luciana', 'Marsala', 1),
    ('Marietta', 'Mila', 1),
    ('Natalia', 'Neroli', 1),
    ('Ornella', 'Prima', 1),
    ('Quorra', 'Ricarda', 1),
    ('Rocio', 'Sidonia', 1),
    ('Teressa', 'Trilby', 1),
    ('Mercury', 'Messala', 1),
    ('Michelangelo', 'Belluchi', 1),
    ('Nino', 'Nek', 1),
    ('Othello', 'Pancrazio', 1),
    ('Paolo', 'Pisano', 1),
    ('Primo', 'Proculeius', 1),
    ('Romeo', 'Rocco', 1),
    ('Saverio', 'Santo', 1),
    ('Silvano', 'Solanio', 1),
    ('Taddeo', 'Ugo', 1),
    ('Umberto', 'Venezio', 1),
    ('Venturo', 'Vesuvio', 1),
    ('Vitalian', 'Vittorio', 1),
    ('Zanebono', 'Zanipolo', 1),
    ('Uberta', 'Vedette', 1),
    ('Venecia', 'Zola', 1);

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Lars', 'Johansson', 2),
    ('Anna', 'Andersson', 2),
    ('Johan', 'Karlsson', 2),
    ('Erik', 'Nilsson', 2),
    ('Anders', 'Eriksson', 2),
    ('Maria', 'Larsson', 2),
    ('Karin', 'Olsson', 2),
    ('Per', 'Persson', 2),
    ('Fredrik', 'Svensson', 2),
    ('Emma', 'Gustafsson', 2);

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Jean', 'Martin', 3),
    ('Marie', 'Bernard', 3),
    ('Pierre', 'Dubois', 3),
    ('Jacques', 'Thomas', 3),
    ('Michel', 'Robert', 3),
    ('Claude', 'Richard', 3),
    ('Nicolas', 'Petit', 3),
    ('Thomas', 'Durand', 3),
    ('Sophie', 'Leroy', 3),
    ('Claire', 'Moreau', 3);

    INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Hans', 'Müller', 4),
    ('Anna', 'Schmidt', 4),
    ('Klaus', 'Schneider', 4),
    ('Peter', 'Fischer', 4),
    ('Karl', 'Weber', 4),
    ('Maria', 'Meyer', 4),
    ('Heinrich', 'Wagner', 4),
    ('Helga', 'Becker', 4),
    ('Wolfgang', 'Schulz', 4),
    ('Erika', 'Hoffmann', 4);

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('James', 'Smith', 5),
    ('Mary', 'Johnson', 5),
    ('John', 'Williams', 5),
    ('Elizabeth', 'Brown', 5),
    ('William', 'Jones', 5),
    ('Sarah', 'Miller', 5),
    ('George', 'Davis', 5),
    ('Emma', 'Wilson', 5),
    ('Thomas', 'Moore', 5),
    ('Charlotte', 'Taylor', 5);

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES  
    ('Antonio', 'García', 6),  
    ('María', 'Fernández', 6),  
    ('Manuel', 'González', 6),  
    ('Carmen', 'Rodríguez', 6),  
    ('José', 'López', 6),  
    ('Ana', 'Martínez', 6),  
    ('Francisco', 'Sánchez', 6),  
    ('Laura', 'Pérez', 6),  
    ('Juan', 'Gómez', 6),  
    ('Isabel', 'Martín', 6);  

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES  
    ('Maximilian', 'Gruber', 7),  
    ('Anna', 'Huber', 7),  
    ('Lukas', 'Bauer', 7),  
    ('Sophia', 'Wagner', 7),  
    ('Elias', 'Müller', 7),  
    ('Emma', 'Steiner', 7),  
    ('Jakob', 'Mayer', 7),  
    ('Lena', 'Schmidt', 7),  
    ('Tobias', 'Hofer', 7),  
    ('Laura', 'Lehner', 7);  
