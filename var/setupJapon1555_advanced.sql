
-- Create the Kosagi Yōkai start worker

INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES
    ('Kosagi', 'Kotatsu', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shödoshima'), (SELECT ID FROM zones WHERE name = 'Ile de Shödoshima'));
-- Link Kotatsu to 'Yōkai'
INSERT INTO controler_worker (controler_id, worker_id) VALUES (
    (SELECT ID FROM controlers WHERE lastname in ('Shikoku')),
    (SELECT ID FROM workers WHERE lastname in ('Kotatsu'))
);
-- Add base actions
INSERT INTO worker_actions (worker_id, controler_id, turn_number, zone_id) VALUES (
    (SELECT ID FROM workers WHERE lastname = 'Kotatsu'),
    (SELECT ID FROM controlers WHERE lastname in ('Shikoku')),
    0,
    (SELECT ID FROM zones WHERE name = 'Ile de Shödoshima')
);
/*
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
*/
