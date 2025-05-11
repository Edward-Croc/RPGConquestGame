
UPDATE config SET value = 'Firenze 1966' WHERE name = 'TITLE';
UPDATE config SET
    value = '<p>Le 6 novembre 1966, l’Arno inonde une grande partie du centre-ville, endommageant de nombreux chefs-d’œuvre et deplacent la population du centre ville.
        Un grand mouvement de solidarité internationale naît à la suite de cet évènement et mobilise des milliers de volontaires, surnommés Les anges de la boue.
        Dans les jours suivant la catastrophe les forces surnaturelles reprennent doucement pied dans le la ville. Retrouverez vous votre pouvoir d’antant ?
        </p>'
    WHERE name = 'PRESENTATION';

INSERT INTO config (name, value, description)
VALUES
    -- MAP INFO
    ('map_file', 'carte_quartiers_florence.jpg', 'Map file to use'),
    ('map_alt', 'Carte des Quartiers de Florence', 'Map alt'),
    ('time_value', 'semaine', 'Text for time span'),
    ('time_denominator_the', 'la', 'Denominator ’the’ for time text'),
    ('time_denominator_ofthe', 'de la', 'Denominator ’of the’ for time text'),
    ('time_denominator_this', 'cette', 'Denominator ’this’ for time text')
    ;

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
('Railway Station', '(Stazione ferroviaria norte) Au nord de Florence se situe Peretola, un village qui s’est fait absorber pour devenir un quartier d’habitations bas de gamme à cause de la présence du petit aéroport de Florence et des quelques avions qui passent au-dessus des habitations.'),
('Le Cascine', 'Le quartier du Cascine tire son nom de son parc, le plus grand parc public de la ville de Florence, issu des anciennes fermes grand-ducales. Le quartier est principalement occupé par les halles du Mercatello delle Cascine, le plus vaste marché de la ville.'),
('Monticelli', 'Situé de l’autre côté du Fleuve Arno face au parc Cascine, c’est dans ce quartier excentré de Florence que l’on trouve les étudiants et les maisons de ceux qui tiennent les boutiques du Mercatello delle Cascine.'),
('Fortezza Basso', 'La Forteresse de Basso est une construction massive datant du 16ème siècle, qui est désormais devenue un palais des expositions. Elle donne son nom au quartier qui contient aussi le Centre hospitalier universitaire et la Scuola di ingegneria.'),
('Santa Maria Novella', 'Ce quartier est un centre-ville commerçant qui tire son nom de sa basilique Sainte Marie Nouvelle. Ce quartier est traversé par la rue commerçante Sainte Marie qui mène de la gare Santa Maria de Firenze jusqu’au au Mercato di San Lorenzo.'),
('Indipendenza', 'Le quartier de l’indépendance et la place publique dont elle tire son nom sont situés au coeur du centre historique de Firenze et sont faciles à atteindre depuis la gare principale de Santa Maria Novella.'),
('Duomo', 'Le Duomo est l’autre nom de la Cathédrale Santa Maria del Fiore, la 5ème église d’Europe qui domine la Piazza del Duomo. Cette place du centre ville piétonnier est bordée du musée de l’opéra et de plusieurs Palazzos.'),
('Palazzo Pitti', 'Ce quartier est dominé par le Palazzo Pitti, un sublime palais de la Renaissance qui forme le centre des affaires du dernier Sénéchal de la ville. Ce quartier est situé de l’autre côté de l’Arno et connecté au centre ville par le fameux Ponte Vecchio.'),
('Santa Croce - Oberdan', 'Entre la basilique Santa Croce et la place du buste de Guglielmo, Oberdan est le quartier financier de Florence. Ses multiples ‘rue Giovanni’ (Angelico, Bovio, Ciambue et Lanza) sont le fruit d’une influence notable.'),
('Piazza della Liberta & Savonarola', 'La place de la Liberté marque la fin du centre ville et le début du quartier des universités anciennes de la ville. L’on peut y trouver l’Université de Syracuse et l’Instituto Leonardo Da Vinci.'),
('Michelangelo-Gavinana', 'Ce quartier du sud de Florence est le plus étendu des quartiers, il est composé de bâtiments qui longent le fleuve Arno. On y trouve un golf, des hôtels de luxe, une parfumerie et la Viale Europa qui traverse le quartier de part en part.'),
('Campo di Marte', 'L’ancien Champ de mars de Firenze est devenue un immense complexe sportif au milieu d’un quartier plus résidentiel. L’autre grand élément de ce quartier est la gare de fret du Champ de Mars.'),
('Bosco Bello', 'Niché sur les hauteurs boisées à l’est de Florence, les villas de la Renaissance aux murs couverts de lierre côtoient de petites chapelles oubliées, au milieu des vignes. Considéré comme un lieu de retraite pour les nobles et les artistes, le Bosco Bello est un quartier discret, empreint de mystère et de sérénité.')
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

-- Table of Fixed Power Types used by code
INSERT INTO power_types (id, name, description) VALUES
    (1, 'Hobby', ''),
    (2, 'Metier', ''),
    (3, 'Discipline', ''),
    (4, 'Transformation', '');

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

