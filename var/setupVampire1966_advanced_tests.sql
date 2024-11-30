
UPDATE config SET value = 0 WHERE name in ( 'DIFF0', 'DIFF1', 'DIFF2', 'DIFF3');

INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES
    ('Harvey', 'Matthews', (SELECT ID FROM worker_origins WHERE name = 'Angleterre'), (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
    ('Andrei', 'Popescu', (SELECT ID FROM worker_origins WHERE name = 'Roumanie'), (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
    ('Indro', 'Lombardi', (SELECT ID FROM worker_origins WHERE name = 'Firenze'), (SELECT ID FROM zones WHERE name = 'Palazzo Pitti'));

INSERT INTO controler_worker (controler_id, worker_id) VALUES
    (
        (SELECT ID FROM controlers WHERE lastname in ('Mazzino', 'Ricciotti')),
        (SELECT ID FROM workers WHERE lastname in ('Matthews'))
    ), (
        (SELECT ID FROM controlers WHERE lastname in ('Walkil', 'Vizirof')),
        (SELECT ID FROM workers WHERE lastname in ('Popescu'))
    ), (
        (SELECT ID FROM controlers WHERE lastname in ('Calabreze')),
        (SELECT ID FROM workers WHERE lastname in ('Lombardi'))
    );

INSERT INTO worker_actions (worker_id, controler_id, turn_number, zone_id, action) VALUES 
    (
        (SELECT ID FROM workers WHERE lastname = 'Matthews'),
        (SELECT controler_id FROM controler_worker JOIN workers ON workers.ID = controler_worker.worker_id WHERE workers.lastname = 'Matthews'),
        0, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti'),
        'claim'
    ),
    (
        (SELECT ID FROM workers WHERE lastname = 'Popescu'),
        (SELECT controler_id FROM controler_worker JOIN workers ON workers.ID = controler_worker.worker_id WHERE workers.lastname = 'Popescu'),
        0, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti'),
        'passive'
    ),
    (
        (SELECT ID FROM workers WHERE lastname = 'Lombardi'),
        (SELECT controler_id FROM controler_worker JOIN workers ON workers.ID = controler_worker.worker_id WHERE workers.lastname = 'Lombardi'),
         0, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti'),
        'investigate'
    )
;

-- Add base powers to the workers :
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
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Matthews'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Puissance'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Matthews'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Vampire nouveau né'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Popescu'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Punk a chien'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Popescu'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Militaire'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Popescu'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Quiétus'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Popescu'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Puissance'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Popescu'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Vampire nouveau né'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Lombardi'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Astrologue Amateur'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Lombardi'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Étudiant (Arts, Lettres)'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Lombardi'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Aliénation'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Lombardi'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Augure'
    )),
    ((SELECT ID FROM workers WHERE lastname = 'Lombardi'), (
        SELECT link_power_type.ID FROM link_power_type
        JOIN powers on  powers.ID = link_power_type.power_id
        WHERE powers.name = 'Vampire nouveau né'
    ))
;
