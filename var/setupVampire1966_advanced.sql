
-- Create the 'Mazzino/Ricciotti' start worker
INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES
    ('Harvey', 'Matthews', (SELECT ID FROM worker_origins WHERE name = 'Angleterre'), (SELECT ID FROM zones WHERE name = 'Palazzo Pitti'));
-- Link Matthews to 'Mazzino/Ricciotti'
INSERT INTO controller_worker (controller_id, worker_id) VALUES (
    (SELECT ID FROM controllers WHERE lastname in ('Mazzino', 'Ricciotti')),
    (SELECT ID FROM workers WHERE lastname in ('Matthews'))
);
-- Add base actions
INSERT INTO worker_actions (worker_id, controller_id, zone_id) VALUES (
    (SELECT ID FROM workers WHERE lastname = 'Matthews'),
    (SELECT ID FROM controllers WHERE lastname in ('Mazzino', 'Ricciotti')),
    (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')
);

-- Add powers to the workers :
INSERT INTO worker_powers (worker_id, link_power_type_id)
SELECT 
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM 
    workers w
JOIN (
    SELECT 'Matthews' AS lastname, 'Goule' AS power_name
    UNION ALL SELECT 'Matthews', 'Alcoolique'
    UNION ALL SELECT 'Matthews', 'Policier.ère'
    UNION ALL SELECT 'Matthews', 'Augure'
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;


-- Create the 'Cacciatore' start workers
-- Michelangelo Marsala, Roma,  Adepte de muscu (-1, 2/1), Retraité curieux (1, 0/0) Célérité , Bosco Bello
-- Natalia Cacciatore, Firenze, Propriétaire de Lévrier Italien (1, 0/0), Policier (1, 1/-1), Monticelli
INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES 
    ('Michelangelo', 'Marsala', (SELECT ID FROM worker_origins WHERE name = 'Roma'), (SELECT ID FROM zones WHERE name = 'Bosco Bello')),
    ('Natalia', 'Cacciatore', (SELECT ID FROM worker_origins WHERE name = 'Firenze'), (SELECT ID FROM zones WHERE name = 'Monticelli'))
;
-- Link Matthews to 'Mazzino/Ricciotti'
INSERT INTO controller_worker (controller_id, worker_id) VALUES 
    ((SELECT ID FROM controllers WHERE lastname in ('Cacciatore')),(SELECT ID FROM workers WHERE lastname in ('Marsala'))),
    ((SELECT ID FROM controllers WHERE lastname in ('Cacciatore')),(SELECT ID FROM workers WHERE lastname in ('Cacciatore')))
;
-- Add base actions
INSERT INTO worker_actions (worker_id, controller_id, zone_id) VALUES 
    (
        (SELECT ID FROM workers WHERE lastname = 'Marsala'),
        (SELECT ID FROM controllers WHERE lastname in ('Cacciatore')),
        (SELECT ID FROM zones WHERE name = 'Bosco Bello')
    ),
    (
        (SELECT ID FROM workers WHERE lastname = 'Cacciatore'),
        (SELECT ID FROM controllers WHERE lastname in ('Cacciatore')),
        (SELECT ID FROM zones WHERE name = 'Monticelli')
    )
;

-- Add powers to the workers :
INSERT INTO worker_powers (worker_id, link_power_type_id)
SELECT 
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM 
    workers w
JOIN (
    SELECT 'Marsala' AS lastname, 'Garou' AS power_name
    UNION ALL SELECT 'Marsala', 'Retraité.e curieux.se'
    UNION ALL SELECT 'Marsala', 'Adepte de muscu'
    UNION ALL SELECT 'Cacciatore', 'Garou'
    UNION ALL SELECT 'Cacciatore', 'Propriétaire de lévrier italien'
    UNION ALL SELECT 'Cacciatore', 'Policier.ère'
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;