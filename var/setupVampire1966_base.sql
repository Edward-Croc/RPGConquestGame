
UPDATE config SET value = 'Firenze 1966' WHERE name = 'TITLE';
UPDATE config SET
    value = 'Le 6 novembre 1966, l''Arno inonde une grande partie du centre-ville, endommageant de nombreux chefs-d''œuvre. Un grand mouvement de solidarité internationale naît à la suite de cet évènement et mobilise des milliers de volontaires, surnommés Les anges de la boue.'
    WHERE name = 'PRESENTATION';

UPDATE config SET value = '''Célérité'', ''Endurance'', ''Puissance'''
WHERE name = 'basePowerNames';

INSERT INTO config (name, value) VALUES 
    ('first_come_nb_choices', '1'),
    ('recrutement_nb_choices', '2'),
    ('recrutement_origin_list', '1,2,3,4,5')
;

INSERT INTO players (username, passwd, is_privileged) VALUES
    ('player1', 'one', false),
    ('player2', 'two', false),
    ('player3', 'three', false),
    ('player4', 'four', false),
    ('player5', 'five', false)
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
--
INSERT INTO powers ( name, enquete, action, defence) VALUES 
    ('Vampire nouveau née', 1,1,1),
    ('Szlatcha', -1,2,2),
    ('Fantome', 2,-2,2)
    ;

INSERT INTO powers ( name, enquete, action, defence) VALUES
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
    ('Augure', 2,-1,1)
;

INSERT INTO  link_power_type ( power_type_id, power_id ) VALUES
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Vampire nouveau née')),
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Szlatcha')),
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Fantome'))
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
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Augure'))
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
    ((SELECT ID FROM factions WHERE name = 'Discple'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Serpentis'
    )),
    ((SELECT ID FROM factions WHERE name = 'Discple'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Endurance'
    )),
    ((SELECT ID FROM factions WHERE name = 'Discple'), (
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
    ))
;

