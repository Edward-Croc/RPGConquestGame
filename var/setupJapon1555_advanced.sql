-- Create the Yōkai start workers

INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES
    ('Kosagi', 'Kotatsu', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shödoshima'), (SELECT ID FROM zones WHERE name = 'Ile de Shödoshima'));

-- Link Kotatsu to 'Yōkai'
INSERT INTO controler_worker (controler_id, worker_id) VALUES (
    (SELECT ID FROM controlers WHERE lastname in ('Shikoku (四国)')),
    (SELECT ID FROM workers WHERE lastname in ('Kotatsu'))
);

-- Add actions to the workers :
INSERT INTO worker_actions (
    worker_id, controler_id, turn_number, zone_id, action_choice, action_params
)
SELECT
    w.id,
    cw.controler_id,
    0,
    w.zone_id,
    entry.action_choice,
    entry.action_params::json
FROM (
    -- Base test claim, passive, investigate
    SELECT 'Kotatsu' AS lastname, 'passive' AS action_choice, '{}' AS action_params
) AS entry
JOIN workers w ON w.lastname = entry.lastname
JOIN controler_worker cw ON cw.worker_id = w.id;
;


-- Add powers to the workers :
INSERT INTO worker_powers (worker_id, link_power_type_id)
SELECT 
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM 
    workers w
JOIN (
    SELECT 'Kotatsu' AS lastname, 'Kōdō (香道) – Voie de l’encens' AS power_name
    UNION ALL SELECT 'Kotatsu', 'Biwa Hōshi (琵琶法師) – Conteur aveugle itinérant'
    UNION ALL SELECT 'Kotatsu', 'Tokkuri (徳利) – Bouteille à saké'
    UNION ALL SELECT 'Kotatsu', 'Kagenkō (影言講) – L’art de la parole de l’ombre'
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;