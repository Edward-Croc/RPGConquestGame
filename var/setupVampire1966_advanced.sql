
-- Create the 'Mazzino/Ricciotti' start worker
INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES
    ('Harvey', 'Matthews', (SELECT ID FROM worker_origins WHERE name = 'Angleterre'), (SELECT ID FROM zones WHERE name = 'Palazzo Pitti'));
-- Link Matthews to 'Mazzino/Ricciotti'
INSERT INTO controller_worker (controller_id, worker_id) VALUES (
    (SELECT ID FROM controllers WHERE lastname in ('Mazzino', 'Ricciotti')),
    (SELECT ID FROM workers WHERE lastname in ('Matthews'))
);
-- Add base actions
INSERT INTO worker_actions (worker_id, controller_id, turn_number, zone_id) VALUES (
    (SELECT ID FROM workers WHERE lastname = 'Matthews'),
    (SELECT ID FROM controllers WHERE lastname in ('Mazzino', 'Ricciotti')),
    0,
    (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')
);
-- Add base powers to the worker
INSERT INTO worker_powers (worker_id, link_power_type_id) VALUES
    ((SELECT ID FROM workers WHERE lastname = 'Matthews'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Alcoolique'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Matthews'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Policier'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Matthews'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Célérité'
    ))
;

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
-- Add base powers to the workers
INSERT INTO worker_powers (worker_id, link_power_type_id) VALUES
    ((SELECT ID FROM workers WHERE lastname = 'Marsala'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Garou'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Marsala'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Retraité curieux'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Marsala'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Adepte de muscu'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Cacciatore'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Garou'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Cacciatore'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Propriétaire de Lévrier Italien'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Cacciatore'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Policier'
    ))
;