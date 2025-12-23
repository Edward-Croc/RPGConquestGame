-- Warning: If you read this file, you will no longer be eligible to participate as a player.

-- Create the 'Mazzino/Ricciotti' start worker
INSERT INTO {prefix}workers (firstname, lastname, origin_id, zone_id) VALUES
    ('Harvey', 'Matthews', (SELECT id FROM {prefix}worker_origins WHERE name = 'Angleterre'), (SELECT id FROM {prefix}zones WHERE name = 'Palazzo Pitti'));
-- Link Matthews to 'Mazzino/Ricciotti'
INSERT INTO {prefix}controller_worker (controller_id, worker_id) VALUES (
    (SELECT id FROM {prefix}controllers WHERE lastname in ('Mazzino', 'Ricciotti')),
    (SELECT id FROM {prefix}workers WHERE lastname in ('Matthews'))
);
-- Add base actions
INSERT INTO {prefix}worker_actions (worker_id, controller_id, zone_id) VALUES (
    (SELECT id FROM {prefix}workers WHERE lastname = 'Matthews'),
    (SELECT id FROM {prefix}controllers WHERE lastname in ('Mazzino', 'Ricciotti')),
    (SELECT id FROM {prefix}zones WHERE name = 'Palazzo Pitti')
);

-- Add powers to the workers :
INSERT INTO {prefix}worker_powers (worker_id, link_power_type_id)
SELECT 
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM {prefix}workers w
JOIN (
    SELECT 'Matthews' AS lastname, 'Goule' AS power_name
    UNION ALL SELECT 'Matthews', 'Alcoolique'
    UNION ALL SELECT 'Matthews', 'Policier.ère'
    UNION ALL SELECT 'Matthews', 'Augure'
) AS wp ON wp.lastname = w.lastname
JOIN {prefix}powers p ON p.name = wp.power_name
JOIN {prefix}link_power_type lpt ON lpt.power_id = p.id;


-- Create the 'Cacciatore' start workers
-- Michelangelo Marsala, Roma,  Adepte de muscu (-1, 2/1), Retraité curieux (1, 0/0) Célérité , Bosco Bello
-- Natalia Cacciatore, Firenze, Propriétaire de Lévrier Italien (1, 0/0), Policier (1, 1/-1), Monticelli
INSERT INTO {prefix}workers (firstname, lastname, origin_id, zone_id) VALUES 
    ('Michelangelo', 'Marsala', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Roma'), (SELECT ID FROM {prefix}zones WHERE name = 'Bosco Bello')),
    ('Natalia', 'Cacciatore', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Firenze'), (SELECT ID FROM {prefix}zones WHERE name = 'Monticelli'))
;
-- Link Matthews to 'Mazzino/Ricciotti'
INSERT INTO {prefix}controller_worker (controller_id, worker_id) VALUES 
    ((SELECT ID FROM {prefix}controllers WHERE lastname in ('Cacciatore')),(SELECT ID FROM {prefix}workers WHERE lastname in ('Marsala'))),
    ((SELECT ID FROM {prefix}controllers WHERE lastname in ('Cacciatore')),(SELECT ID FROM {prefix}workers WHERE lastname in ('Cacciatore')))
;
-- Add base actions
INSERT INTO {prefix}worker_actions (worker_id, controller_id, zone_id) VALUES 
    (
        (SELECT ID FROM {prefix}workers WHERE lastname = 'Marsala'),
        (SELECT ID FROM {prefix}controllers WHERE lastname in ('Cacciatore')),
        (SELECT ID FROM {prefix}zones WHERE name = 'Bosco Bello')
    ),
    (
        (SELECT ID FROM {prefix}workers WHERE lastname = 'Cacciatore'),
        (SELECT ID FROM {prefix}controllers WHERE lastname in ('Cacciatore')),
        (SELECT ID FROM {prefix}zones WHERE name = 'Monticelli')
    )
;

-- Add powers to the workers :
INSERT INTO {prefix}worker_powers (worker_id, link_power_type_id)
SELECT 
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM {prefix}workers w
JOIN (
    SELECT 'Marsala' AS lastname, 'Garou' AS power_name
    UNION ALL SELECT 'Marsala', 'Retraité.e curieux.se'
    UNION ALL SELECT 'Marsala', 'Adepte de muscu'
    UNION ALL SELECT 'Marsala', 'Célérité'
    UNION ALL SELECT 'Cacciatore', 'Garou'
    UNION ALL SELECT 'Cacciatore', 'Propriétaire de lévrier italien'
    UNION ALL SELECT 'Cacciatore', 'Policier.ère'
    UNION ALL SELECT 'Cacciatore', 'Célérité'
) AS wp ON wp.lastname = w.lastname
JOIN {prefix}powers p ON p.name = wp.power_name
JOIN {prefix}link_power_type lpt ON lpt.power_id = p.id;