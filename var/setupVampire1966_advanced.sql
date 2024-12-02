
INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES
    ('Harvey', 'Matthews', (SELECT ID FROM worker_origins WHERE name = 'Angleterre'), (SELECT ID FROM zones WHERE name = 'Palazzo Pitti'));

INSERT INTO controler_worker (controler_id, worker_id) VALUES
    (
        (SELECT ID FROM controlers WHERE lastname in ('Mazzino', 'Ricciotti')),
        (SELECT ID FROM workers WHERE lastname in ('Matthews'))

    )
;

INSERT INTO worker_actions (worker_id, controler_id, turn_number, zone_id) VALUES 
    (
        (SELECT ID FROM workers WHERE lastname = 'Matthews'),
        (SELECT ID FROM controler_worker JOIN workers ON workers.ID = controler_worker.worker_id WHERE workers.lastname = 'Matthews'),
          0, (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')
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
    ))
;
