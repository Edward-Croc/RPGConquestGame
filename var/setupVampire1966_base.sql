
UPDATE config SET value = 'Firenze 1966' WHERE name = 'TITLE';
UPDATE config SET
    value = 'Le 6 novembre 1966, l''Arno inonde une grande partie du centre-ville, endommageant de nombreux chefs-d''œuvre. Un grand mouvement de solidarité internationale naît à la suite de cet évènement et mobilise des milliers de volontaires, surnommés Les anges de la boue.'
    WHERE name = 'PRESENTATION';

UPDATE config SET value = '''Célérité'', ''Endurance'', ''Puissance'''
WHERE name = 'basePowerNames';

INSERT INTO players (username, passwd, is_privileged) VALUES
    ('player1', 'one', false),
    ('player2', 'two', false),
    ('player3', 'three', false),
    ('player4', 'four', false),
    ('player5', 'five', false),
    ('player6', 'six', false),
    ('player7', 'seven', false),
    ('player8', 'eight', false),
    ('player9', 'nine', false),
    ('player10', 'ten', false),
    ('player11', 'eleven', false),
    ('player12', 'twelve', false),
    ('player13', '13', false)
;

INSERT INTO factions (name) VALUES
    ('Brujah'),
    ('Ventrue'),
    ('Toréador'),
    ('Tremere'),
    ('Malkavien'),
    ('Gangrel'),
    ('Nosfératu'),
    ('Giovanni'),
    ('Assamites'),
    ('Disciple'),
    ('Tzimisce'),
    ('Lasombra'),
    ('Humain'),
    ('Eglise'),
    ('Démon'),
    ('Garou');

-- players with start worker limits
INSERT INTO controlers (
    firstname, lastname,
    start_workers, recruted_workers, turn_recruted_workers,
    faction_id, fake_faction_id
) VALUES
    (
        'Dame', 'Calabreze',
        0, 0, 0,
        (SELECT ID FROM factions WHERE name = 'Malkavien' ),
        (SELECT ID FROM factions WHERE name = 'Malkavien' )
    ),
    (
        --'Sir Angelo', 'Ricciotti',
        'Sir Antonio', 'Mazzino',
        1,1,1,
        (SELECT ID FROM factions WHERE name = 'Brujah' ),
        (SELECT ID FROM factions WHERE name = 'Brujah' )
    ),
    (
        'Duca Gaston', 'da Firenze',
        10, 0, 0,
        (SELECT ID FROM factions WHERE name = 'Giovanni' ),
        (SELECT ID FROM factions WHERE name = 'Giovanni' )
    )
;

-- IA with no start workers
INSERT INTO controlers (
    firstname, lastname, ia_type,
    faction_id, fake_faction_id
) VALUES
    (
        'Sir Hassan', 'Ben Hasan', 'passive',
        (SELECT ID FROM factions WHERE name = 'Disciple' ),
        (SELECT ID FROM factions WHERE name = 'Disciple' )
    ),
    (
        'Frère', 'Lorenzo', 'passive',
        (SELECT ID FROM factions WHERE name = 'Eglise'),
        (SELECT ID FROM factions WHERE name = 'Humain')
    ),
    ('Sir Dimonio', 'Ricci', 'serching',
        (SELECT ID FROM factions WHERE name = 'Démon'),
        (SELECT ID FROM factions WHERE name = 'Lasombra')
    )
;

-- IA with start workers
INSERT INTO controlers (
    firstname, lastname, ia_type,
    start_workers, recruted_workers, turn_recruted_workers, turn_firstcome_workers,
    faction_id, fake_faction_id
) VALUES
    ('Signore Arno', 'Cacciatore', 'violent',
        1, 2, 1, 1,
        (SELECT ID FROM factions WHERE name = 'Garou'),
        (SELECT ID FROM factions WHERE name = 'Humain')
    )
;

-- players with no start worker limits
INSERT INTO controlers (
    firstname, lastname,
    faction_id, fake_faction_id
) VALUES
    ('Dame Elisa', 'Bonapart',
        (SELECT ID FROM factions WHERE name = 'Toréador' ),
        (SELECT ID FROM factions WHERE name = 'Toréador' )
    ),
    ('Sir Gaetano', 'Trentini',
        (SELECT ID FROM factions WHERE name = 'Tremere' ),
        (SELECT ID FROM factions WHERE name = 'Tremere' )
    ),
    ('Duca Amandin', 'Franco',
        (SELECT ID FROM factions WHERE name = 'Ventrue' ),
        (SELECT ID FROM factions WHERE name = 'Ventrue' )
    ),
    (
        -- 'Dame Ana', 'Walkil',
        'Dame Albane', 'Vizirof',
        (SELECT ID FROM factions WHERE name = 'Assamites' ),
        (SELECT ID FROM factions WHERE name = 'Tremere' )
    ),
    ('Sir Adamo', 'de Toscane',
        (SELECT ID FROM factions WHERE name = 'Nosfératu' ),
        (SELECT ID FROM factions WHERE name = 'Nosfératu' )
    ),
    ('Sir Wilhem', 'Der Swartz', -- 'Dame Ana', 'Sgorina',
        /*Wilhem est né dans une famille allemnde en 1923 sa famille était modeste
        il a vecu une vie normale jusqu'en 1934. Date ou ses parents quittèrent l'allemagne pour le nord de l'italie
        afin de vivre dans un petit village, appele SONDRIO où le jeune Wilhem apprit l'italien
        a l'age de 18 ans wilhem s'engagea comme garde forestier sa famille vivant a l'écart de la guerre
        il rejoint contre l'accord de ses parents l'armee allemande ou il mena quelque "batailles" ou son aspect solitaire
        ne lui fit que tres peu d'amis.
        il se fit etreindre en1953 par un Gangrel de gene 7 qui voulait en faire un leader gangrel..
        A l'heure actuelle son pere ne le suit plus mais un mentor l'a pris en charge et le "guide"
        que de maniere sporadiqeu. Wilhem ne connait pas son Sire .
        Il a recupere par la force un reseau de boites de nuits appartenant a des brujah antitribus.
        Ce qui lui a valu du prestige en france
        Il a recemment commis l'amaranthe en hongrie sur un regent tremere d'une fondation dirigée par un baali */
        (SELECT ID FROM factions WHERE name = 'Tzimisce' ),
        (SELECT ID FROM factions WHERE name = 'Gangrel' )
    )
;

INSERT INTO player_controler (controler_id, player_id)
    SELECT ID, (SELECT ID FROM players WHERE username = 'gm')
    FROM controlers;

INSERT INTO player_controler (player_id, controler_id) VALUES
    (
        (SELECT ID FROM players WHERE username = 'player1'),
        (SELECT ID FROM controlers WHERE lastname in ('Mazzino', 'Ricciotti'))
    ), -- player1 controls  Angelo Ricciotti/Antonio Mazzino,
    (
        (SELECT ID FROM players WHERE username = 'player2'),
        (SELECT ID FROM controlers WHERE lastname = 'Calabreze')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player3'),
        (SELECT ID FROM controlers WHERE lastname in ('Walkil', 'Vizirof'))
    ),
    (
        (SELECT ID FROM players WHERE username = 'player4'),
        (SELECT ID FROM controlers WHERE lastname = 'Bonapart')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player5'),
        (SELECT ID FROM controlers WHERE lastname = 'Trentini')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player6'),
        (SELECT ID FROM controlers WHERE lastname = 'Franco')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player7'),
        (SELECT ID FROM controlers WHERE lastname = 'da Firenze')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player8'),
        (SELECT ID FROM controlers WHERE lastname = 'de Toscane')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player9'),
        (SELECT ID FROM controlers WHERE lastname in ('Sgorina', 'Der Swartz'))
    ),
    (
        (SELECT ID FROM players WHERE username = 'player10'),
        (SELECT ID FROM controlers WHERE lastname = 'Ricci')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player11'),
        (SELECT ID FROM controlers WHERE lastname = 'Lorenzo')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player12'),
        (SELECT ID FROM controlers WHERE lastname = 'Ben Hasan')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player13'),
        (SELECT ID FROM controlers WHERE lastname = 'Cacciatore')
    )
;

INSERT INTO zones (name, description) VALUES
('Railway Station', '(Stazione ferroviaria norte) Au nord de Florence se situe Peretola, un village qui s’est fait absorber pour devenir un quartier d''habitations bas de gamme à cause de la présence du petit aéroport de Florence et des quelques avions qui passent au-dessus des habitations.'),
('Le Cascine', 'Le quartier du Cascine tire son nom de son parc, le plus grand parc public de la ville de Florence, issu des anciennes fermes grand-ducales. Le quartier est principalement occupé par les halles du Mercatello delle Cascine, le plus vaste marché de la ville.'),
('Monticelli', 'Situé de l’autre côté du Fleuve Arno face au parc Cascine, c’est dans ce quartier excentré de Florence que l’on trouve les étudiants et les maisons de ceux qui tiennent les boutiques du Mercatello delle Cascine.'),
('Fortezza Basso', 'La Forteresse de Basso est une construction massive datant du 16ème siècle, qui est désormais devenue un palais des expositions. Elle donne son nom au quartier qui contient aussi le Centre hospitalier universitaire et la Scuola di ingegneria.'),
('Santa Maria Novella', 'Ce quartier est un centre-ville commerçant qui tire son nom de sa basilique Sainte Marie Nouvelle. Ce quartier est traversé par la rue commerçante Sainte Marie qui mène de la gare Santa Maria de Firenze jusqu’au au Mercato di San Lorenzo.'),
('Indipendenza', 'Le quartier de l''indépendance et la place publique dont elle tire son nom sont situés au coeur du centre historique de Firenze et sont faciles à atteindre depuis la gare principale de Santa Maria Novella.'),
('Duomo', 'Le Duomo est l’autre nom de la Cathédrale Santa Maria del Fiore, la 5ème église d’Europe qui domine la Piazza del Duomo. Cette place du centre ville piétonnier est bordée du musée de l’opéra et de plusieurs Palazzos.'),
('Palazzo Pitti', 'Ce quartier est dominé par le Palazzo Pitti, un sublime palais de la Renaissance qui forme le centre des affaires du dernier Sénéchal de la ville. Ce quartier est situé de l’autre côté de l’Arno et connecté au centre ville par le fameux Ponte Vecchio.'),
('Santa Croce - Oberdan', 'Entre la basilique Santa Croce et la place du buste de Guglielmo, Oberdan est le quartier financier de Florence. Ses multiples ‘rue Giovanni’ (Angelico, Bovio, Ciambue et Lanza) sont le fruit d''une influence notable.'),
('Piazza della Liberta & Savonarola', 'La place de la Liberté marque la fin du centre ville et le début du quartier des universités anciennes de la ville. L’on peut y trouver l''Université de Syracuse et l''Instituto Leonardo Da Vinci.'),
('Michelangelo-Gavinana', 'Ce quartier du sud de Florence est le plus étendu des quartiers, il est composé de bâtiments qui longent le fleuve Arno. On y trouve un golf, des hôtels de luxe, une parfumerie et la Viale Europa qui traverse le quartier de part en part.'),
('Campo di Marte', 'L’ancien Champ de mars de Firenze est devenue un immense complexe sportif au milieu d’un quartier plus résidentiel. L’autre grand élément de ce quartier est la gare de fret du Champ de Mars.'),
('Bosco Bello', 'Niché sur les hauteurs boisées à l''est de Florence, les villas de la Renaissance aux murs couverts de lierre côtoient de petites chapelles oubliées, au milieu des vignes. Considéré comme un lieu de retraite pour les nobles et les artistes, le Bosco Bello est un quartier discret, empreint de mystère et de sérénité.')
;


-- Insert the data
INSERT INTO locations (name, description, discovery_diff, zone_id) VALUES
('Stazione ferroviaria', '', 0, (SELECT ID FROM zones WHERE name = 'Railway Station')),
('Les anges de la boue', '', 6, (SELECT ID FROM zones WHERE name = 'Railway Station')),
('Le barrage','', 6, (SELECT ID FROM zones WHERE name = 'Railway Station')),
('Les anges de la boue', '', 6, (SELECT ID FROM zones WHERE name = 'Le Cascine')),
('Linfant', '', 6, (SELECT ID FROM zones WHERE name = 'Le Cascine')),
('Cairn','', 6, (SELECT ID FROM zones WHERE name = 'Monticelli')),
('Fortezza da Basso', '', 0, (SELECT ID FROM zones WHERE name = 'Fortezza Basso')),
('Facolta di Ingegneria', '', 6, (SELECT ID FROM zones WHERE name = 'Fortezza Basso')),
('Gare/Les anges de la boue', '', 6, (SELECT ID FROM zones WHERE name = 'Santa Maria Novella')),
('Balistero','', 0, (SELECT ID FROM zones WHERE name = 'Santa Maria Novella')),
('Instituto Leonardo Da Vinci','', 0, (SELECT ID FROM zones WHERE name = 'Indipendenza')),
('Piazza della Liberta','', 0, (SELECT ID FROM zones WHERE name = 'Indipendenza')),
('Palazzo Vecchio', '', 0, (SELECT ID FROM zones WHERE name = 'Duomo')),
('Duomo', '', 0, (SELECT ID FROM zones WHERE name = 'Duomo')),
('Palazzo Pitti','', 0, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
('Ponte Vecchio','', 6, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
('Santa Croce', '', 6, (SELECT ID FROM zones WHERE name = 'Santa Croce - Oberdan')),
('Banca Di Firenze','', 0, (SELECT ID FROM zones WHERE name = 'Santa Croce - Oberdan')),
('Piazza della Liberta', '', 0,(SELECT ID FROM zones WHERE name = 'Piazza della Liberta & Savonarola')),
('Pallazzo Medeci Ricardi', '', 6,(SELECT ID FROM zones WHERE name = 'Piazza della Liberta & Savonarola')),
('Musée Degli di Firenze','', 0, (SELECT ID FROM zones WHERE name = 'Michelangelo-Gavinana')),
('Stazione ferroviaria fret', '', 0, (SELECT ID FROM zones WHERE name = 'Campo di Marte')),
('Le prince', '', 8, (SELECT ID FROM zones WHERE name = 'Campo di Marte')),
('Cairn', '', 8, (SELECT ID FROM zones WHERE name = 'Bosco Bello')),
('Le rituel','', 8, (SELECT ID FROM zones WHERE name = 'Bosco Bello'));

-- Insert names into worker_origins
INSERT INTO worker_origins (name) VALUES
    ('Firenze'),
    ('Roma'),
    ('Venezia'),
    ('Napoli'),
    ('Milano'),
    ('Suede'),
    ('France'),
    ('Allemagne'),
    ('Angleterre'),
    ('Espagne'),
    ('Autriche'),
    ('Roumanie');

-- Insert names into worker_names
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Amerigo', 'Attilio', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Marco', 'Martino', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Benvenuto', 'Braulio', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Cirrillo', 'Cajetan', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Donato', 'Demarco', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Eriberto', 'Ettore', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Flavio', 'Fortino', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Indro', 'Lombardi', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Massimo', 'Maury', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Carlotta', 'Cara', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Donatella', 'Domani', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Fabiana', 'Fiorella', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Graziella', 'Giordana', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Ilaria', 'Itala', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Justina', 'Lanza', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Liona', 'Lave', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Marietta', 'Mila', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Natalia', 'Neroli', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Ornella', 'Prima', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Quorra', 'Ricarda', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Rocio', 'Sidonia', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Teressa', 'Trilby', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Mercury', 'Messala', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Nino', 'Nek', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Othello', 'Pancrazio', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Primo', 'Proculeius', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Romeo', 'Rocco', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Saverio', 'Santo', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Silvano', 'Solanio', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Taddeo', 'Ugo', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Vitalian', 'Vittorio', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Zanebono', 'Zanipolo', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Uberta', 'Vedette', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Venecia', 'Zola', (SELECT ID FROM worker_origins WHERE name = 'Firenze'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Luciana', 'Marsala', (SELECT ID FROM worker_origins WHERE name = 'Roma')),
    ('Michelangelo', 'Belluchi', (SELECT ID FROM worker_origins WHERE name = 'Roma')),
    ('Umberto', 'Venezio', (SELECT ID FROM worker_origins WHERE name = 'Roma')),
    ('Venturo', 'Vesuvio', (SELECT ID FROM worker_origins WHERE name = 'Roma')),
    ('Gino', 'Giancarlo', (SELECT ID FROM worker_origins WHERE name = 'Venezia')),
    ('Hortensio', 'Honorius', (SELECT ID FROM worker_origins WHERE name = 'Venezia')),
    ('Bianca', 'Abriana', (SELECT ID FROM worker_origins WHERE name = 'Venezia')),
    ('Paolo', 'Pisano', (SELECT ID FROM worker_origins WHERE name = 'Venezia'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Antonio', 'Esposito', (SELECT ID FROM worker_origins WHERE name = 'Napoli')),
    ('Giuseppe', 'Russo', (SELECT ID FROM worker_origins WHERE name = 'Napoli')),
    ('Maria', 'Marotta', (SELECT ID FROM worker_origins WHERE name = 'Napoli')),
    ('Vincenzo', 'Romano', (SELECT ID FROM worker_origins WHERE name = 'Napoli')),
    ('Luigi', 'Coppola', (SELECT ID FROM worker_origins WHERE name = 'Napoli'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Marco', 'Rossi',  (SELECT ID FROM worker_origins WHERE name = 'Milano')),
    ('Matteo', 'Brambilla',  (SELECT ID FROM worker_origins WHERE name = 'Milano')),
    ('Alessandro', 'Ferrari',  (SELECT ID FROM worker_origins WHERE name = 'Milano')),
    ('Francesca', 'Colombo',  (SELECT ID FROM worker_origins WHERE name = 'Milano')),
    ('Luca', 'Bianchi',  (SELECT ID FROM worker_origins WHERE name = 'Milano'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Lars', 'Johansson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Anna', 'Andersson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Johan', 'Karlsson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Erik', 'Nilsson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Anders', 'Eriksson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Maria', 'Larsson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Karin', 'Olsson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Per', 'Persson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Fredrik', 'Svensson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Emma', 'Gustafsson', (SELECT ID FROM worker_origins WHERE name = 'Suede'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Jean', 'Martin', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Marie', 'Bernard', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Pierre', 'Dubois', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Jacques', 'Thomas', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Michel', 'Robert', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Claude', 'Richard', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Nicolas', 'Petit', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Thomas', 'Durand', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Sophie', 'Leroy', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Claire', 'Moreau', (SELECT ID FROM worker_origins WHERE name = 'France'));

    INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Hans', 'Müller', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Anna', 'Schmidt', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Klaus', 'Schneider', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Peter', 'Fischer', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Karl', 'Weber', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Maria', 'Meyer', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Heinrich', 'Wagner', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Helga', 'Becker', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Wolfgang', 'Schulz', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Erika', 'Hoffmann', (SELECT ID FROM worker_origins WHERE name = 'Allemagne'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('James', 'Smith', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Mary', 'Johnson', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('John', 'Williams', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Elizabeth', 'Brown', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('William', 'Jones', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Sarah', 'Miller', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('George', 'Davis', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Emma', 'Wilson', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Thomas', 'Moore', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Charlotte', 'Taylor', (SELECT ID FROM worker_origins WHERE name = 'Angleterre'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Antonio', 'García', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('María', 'Fernández', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Manuel', 'González', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Carmen', 'Rodríguez', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('José', 'López', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Ana', 'Martínez', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Francisco', 'Sánchez', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Laura', 'Pérez', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Juan', 'Gómez', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Isabel', 'Martín', (SELECT ID FROM worker_origins WHERE name = 'Espagne'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Maximilian', 'Gruber', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Anna', 'Huber', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Lukas', 'Bauer', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Sophia', 'Wagner', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Elias', 'Müller', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Emma', 'Steiner', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Jakob', 'Mayer', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Lena', 'Schmidt', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Tobias', 'Hofer', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Laura', 'Lehner', (SELECT ID FROM worker_origins WHERE name = 'Autriche'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Maria', 'Ionescu', (SELECT ID FROM worker_origins WHERE name = 'Roumanie')),
    ('Ioan', 'Stan', (SELECT ID FROM worker_origins WHERE name = 'Roumanie')),
    ('Elena', 'Dumitru', (SELECT ID FROM worker_origins WHERE name = 'Roumanie')),
    ('Alexandru', 'Gheorghe', (SELECT ID FROM worker_origins WHERE name = 'Roumanie'));


-- Table of Power Types
INSERT INTO power_types (name) VALUES
    ('Hobby'),
    ('Metier'),
    ('Discipline'),
    ('Transformation');

-- Table of powers
-- other possible keys hidden, on_recrutment, on_transformation
INSERT INTO powers ( name, enquete, attack, defence, other) VALUES
    ('Goule', 0,0,1, '{"hidden" : "2", "on_recrutment": "TRUE", "on_transformation": {"worker_is_alive": "1", "age": "0", "turn": "0"} }'),
    ('Vampire nouveau né', 1,1,2, '{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "age": "2", "turn": "2"} }'),
    ('Szlatcha', -1,2,3, '{"hidden" : "0", "on_recrutment": {"controler_faction": "Tzimisce"}, "on_transformation": {"worker_is_alive": "1", "controler_faction": "Tzimisce"}}'),
    ('Gargouille', 0,1,3, '{"hidden" : "0", "on_recrutment": {"controler_faction": "Tremere"}, "on_transformation": {"worker_is_alive": "1", "controler_faction": "Tremere"}}'),
    ('Fantome',3,-2,3, '{"hidden" : "0", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "0", "controler_faction": "Giovanni"}}'),
    ('Possession', 2,-1,2, '{"hidden" : "2", "on_recrutment": "FALSE", "on_transformation": {"OR": {"age": "2", "worker_is_alive": "0"}, "controler_faction": "Démon, Eglise"}}'),
    ('Garou', 1,2,2, '{"hidden" : "2", "on_recrutment": {"controler_faction": "Garou"}, "on_transformation": "FALSE"}')
;

INSERT INTO  link_power_type ( power_type_id, power_id ) VALUES
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Goule')),
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Vampire nouveau né')),
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Szlatcha')),
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Fantome')),
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Gargouille')),
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Possession')),
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Garou'))
;

INSERT INTO powers ( name, enquete, attack, defence) VALUES
    -- Suggested Disciplines
    -- Possible Values Based on +2 :
    -- ('', 1,1,0), ('', 0,1,1), ('', 1,0,1),
    -- ('', 2,0,0), ('', 0,2,0), ('', 0,0,2),
    -- ('', -1,2,1), ('', -1,1,2), ('', 2,-1,1), ('', 1,-1,2), ('', 1,2,-1),('', 2,1,-1),
    -- Possible Values Based on +2 : with imbalance on strong defence
    -- ('', 1,1,1),
    -- ('', 2,0,0), ('', 0,2,1), ('', 0,1,2),
    -- ('', -1,2,2), ('', 2,-1,1), ('', 2,2,-1),
    -- ('', 1,1,1),
    ('Célérité', 1,1,1),
    ('Domination', 1,1,1),
    ('Aliénation', 1,1,1),
    ('Obténébration', 1,1,1),
    ('Protéisme', 1,1,1),
    ('Thaumaturgie', 1,1,1),
    -- ('', 0,1,2),
    ('Endurance', 0,1,2),
    -- ('', 0,2,1),
    ('Puissance', 0,2,1),
    -- ('', 2,0,0),
    ('Animalisme', 2,0,0),
    ('Présence', 2,0,0),
    ('Chimérie', 2,0,0),
    ('Nécromancie', 2,0,0),
    ('Serpentis', 2,0,0),
    ('Vicissitude', 2,0,0),
    -- ('', 2,2,-1),
    ('Quiétus', 2,1,-1),
    ('Occultation', 2,1,-1),
    -- ('', 2,-1,1),
    ('Augure', 2,-1,1),
    -- Pouvoirs mortels et démons
    ('Vraie Foi', -1, 2,3),
    ('Sentir le mal', 3, 1,1)
;


INSERT INTO  link_power_type ( power_type_id, power_id ) VALUES
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Aliénation')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Célérité')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Chimérie')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Domination')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Obténébration')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Vicissitude')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Protéisme')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Endurance')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Puissance')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Serpentis')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Animalisme')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Occultation')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Présence')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Thaumaturgie')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Quiétus')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Nécromancie')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Augure')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Vraie Foi')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Sentir le mal'))
;

-- Add base powers to the factions :
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Brujah'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Célérité'
    )),
    ((SELECT ID FROM factions WHERE name = 'Brujah'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Puissance'
    )),
    ((SELECT ID FROM factions WHERE name = 'Brujah'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Présence'
    )),
    ((SELECT ID FROM factions WHERE name = 'Ventrue'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Présence'
    )),
    ((SELECT ID FROM factions WHERE name = 'Ventrue'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Domination'
    )),
    ((SELECT ID FROM factions WHERE name = 'Ventrue'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Endurance'
    )),
    ((SELECT ID FROM factions WHERE name = 'Toréador'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Célérité'
    )),
    ((SELECT ID FROM factions WHERE name = 'Toréador'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Présence'
    )),
    ((SELECT ID FROM factions WHERE name = 'Toréador'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Augure'
    )),
    ((SELECT ID FROM factions WHERE name = 'Tremere'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Augure'
    )),
    ((SELECT ID FROM factions WHERE name = 'Tremere'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Thaumaturgie'
    )),
    ((SELECT ID FROM factions WHERE name = 'Tremere'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Domination'
    )),
    ((SELECT ID FROM factions WHERE name = 'Malkavien'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Augure'
    )),
    ((SELECT ID FROM factions WHERE name = 'Malkavien'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Occultation'
    )),
    ((SELECT ID FROM factions WHERE name = 'Malkavien'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Aliénation'
    )),
    ((SELECT ID FROM factions WHERE name = 'Gangrel'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Endurance'
    )),
    ((SELECT ID FROM factions WHERE name = 'Gangrel'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Protéisme'
    )),
    ((SELECT ID FROM factions WHERE name = 'Gangrel'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Animalisme'
    )),
    ((SELECT ID FROM factions WHERE name = 'Nosfératu'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Animalisme'
    )),
    ((SELECT ID FROM factions WHERE name = 'Nosfératu'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Puissance'
    )),
    ((SELECT ID FROM factions WHERE name = 'Nosfératu'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Occultation'
    )),
    ((SELECT ID FROM factions WHERE name = 'Giovanni'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Puissance'
    )),
    ((SELECT ID FROM factions WHERE name = 'Giovanni'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Domination'
    )),
    ((SELECT ID FROM factions WHERE name = 'Giovanni'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Nécromancie'
    )),
    ((SELECT ID FROM factions WHERE name = 'Assamites'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Célérité'
    )),
    ((SELECT ID FROM factions WHERE name = 'Assamites'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Occultation'
    )),
    ((SELECT ID FROM factions WHERE name = 'Assamites'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Quiétus'
    )),
    ((SELECT ID FROM factions WHERE name = 'Disciple'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Serpentis'
    )),
    ((SELECT ID FROM factions WHERE name = 'Disciple'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Endurance'
    )),
    ((SELECT ID FROM factions WHERE name = 'Disciple'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Présence'
    )),
    ((SELECT ID FROM factions WHERE name = 'Tzimisce'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Vicissitude'
    )),
    ((SELECT ID FROM factions WHERE name = 'Tzimisce'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Animalisme'
    )),
    ((SELECT ID FROM factions WHERE name = 'Tzimisce'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Augure'
    )),
    ((SELECT ID FROM factions WHERE name = 'Lasombra'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Obténébration'
    )),
    ((SELECT ID FROM factions WHERE name = 'Lasombra'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Puissance'
    )),
    ((SELECT ID FROM factions WHERE name = 'Lasombra'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Domination'
    )),
    ((SELECT ID FROM factions WHERE name = 'Démon'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Obténébration'
    )),
    ((SELECT ID FROM factions WHERE name = 'Démon'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Puissance'
    )),
    ((SELECT ID FROM factions WHERE name = 'Démon'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Domination'
    )),
    ((SELECT ID FROM factions WHERE name = 'Eglise'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Vraie Foi'
    )),
    ((SELECT ID FROM factions WHERE name = 'Eglise'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Sentir le mal'
    ))
;

