
-- UPDATE config SET value = 0 WHERE name in ( 'REPORTDIFF0', 'REPORTDIFF1', 'REPORTDIFF2', 'REPORTDIFF3');

INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES
    -- Base test claim, passive, investigate
    ('Andrei', 'Popescu', (SELECT ID FROM worker_origins WHERE name = 'Roumanie'), (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
    ('Indro', 'Lombardi', (SELECT ID FROM worker_origins WHERE name = 'Firenze'), (SELECT ID FROM zones WHERE name = 'Palazzo Pitti')),
    -- Multi claim, should be violent
    ('Amerigo', 'Martino', (SELECT ID FROM worker_origins WHERE name = 'Firenze'), (SELECT ID FROM zones WHERE name = 'Railway Station')),
    ('Roman', 'Aliev', (SELECT ID FROM worker_origins WHERE name = 'Roumanie'), (SELECT ID FROM zones WHERE name = 'Railway Station')),
    ('Hortensio', 'Honorius', (SELECT ID FROM worker_origins WHERE name = 'Venezia'), (SELECT ID FROM zones WHERE name = 'Railway Station')),
    -- Attacking ?
    ('Maria', 'Ionescu', (SELECT ID FROM worker_origins WHERE name = 'Roumanie'), (SELECT ID FROM zones WHERE name = 'Fortezza Basso')),
    ('Mercury', 'Messala', (SELECT ID FROM worker_origins WHERE name = 'Firenze'), (SELECT ID FROM zones WHERE name = 'Fortezza Basso')),
    ('Maria', 'Marotta', (SELECT ID FROM worker_origins WHERE name = 'Venezia'), (SELECT ID FROM zones WHERE name = 'Fortezza Basso'));

INSERT INTO controller_worker (controller_id, worker_id) VALUES
    -- Base test claim, passive, investigate
    (
        (SELECT ID FROM controllers WHERE lastname in ('Calabreze')),
        (SELECT ID FROM workers WHERE lastname in ('Lombardi'))
    ), (
        (SELECT ID FROM controllers WHERE lastname in ('Walkil', 'Vizirof')),
        (SELECT ID FROM workers WHERE lastname in ('Popescu'))
    )
    -- Multi claim, should be violent
    ,(
        (SELECT ID FROM controllers WHERE lastname in ('da Firenze')),
        (SELECT ID FROM workers WHERE lastname in ('Honorius'))
    ), (
        (SELECT ID FROM controllers WHERE lastname in ('Walkil', 'Vizirof')),
        (SELECT ID FROM workers WHERE lastname in ('Aliev'))
    ), (
        (SELECT ID FROM controllers WHERE lastname in ('Mazzino', 'Ricciotti')),
        (SELECT ID FROM workers WHERE lastname in ('Martino'))
    )
    -- Attacking ?
    ,(
        (SELECT ID FROM controllers WHERE lastname in ('da Firenze')),
        (SELECT ID FROM workers WHERE lastname in ('Messala'))
    ), (
        (SELECT ID FROM controllers WHERE lastname in ('Walkil', 'Vizirof')),
        (SELECT ID FROM workers WHERE lastname in ('Ionescu'))
    ), (
        (SELECT ID FROM controllers WHERE lastname in ('Mazzino', 'Ricciotti')),
        (SELECT ID FROM workers WHERE lastname in ('Marotta'))
    )
;

UPDATE worker_actions
SET action_choice = 'claim', action_params = '{"claim_controller_id":"2"}'
WHERE worker_id = (SELECT ID FROM workers WHERE lastname in ('Matthews'));

-- Add actions to the workers :
INSERT INTO worker_actions (
    worker_id, controller_id, turn_number, zone_id, action_choice, action_params
)
SELECT
    w.id,
    cw.controller_id,
    0,
    w.zone_id,
    entry.action_choice,
    entry.action_params::json
FROM (
    -- Base test claim, passive, investigate
    SELECT 'Popescu' AS lastname, 'passive' AS action_choice, '{}' AS action_params  -- empty JSON object
    UNION ALL
    SELECT 'Lombardi', 'investigate', '{}'
    -- Multi claim, should be violent
    UNION ALL
    SELECT 'Honorius', 'claim', '{"claim_controller_id":"2"}'
    UNION ALL
    SELECT 'Aliev', 'claim', '{"claim_controller_id":"2"}'
    UNION ALL
    SELECT 'Martino', 'claim', '{"claim_controller_id":"2"}'
    -- Attacking ?
    UNION ALL
    SELECT 'Messala', 'investigate', '{}'
    UNION ALL
    SELECT 'Ionescu', 'investigate', '{}'
    UNION ALL
    SELECT 'Marotta', 'investigate', '{}'
) AS entry
JOIN workers w ON w.lastname = entry.lastname
JOIN controller_worker cw ON cw.worker_id = w.id;

-- Add powers to the workers :
INSERT INTO worker_powers (worker_id, link_power_type_id)
SELECT 
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM 
    workers w
JOIN (
    SELECT 'Matthews' AS lastname, 'Vampire nouveau né' AS power_name
    UNION ALL SELECT 'Popescu', 'Punk à chien'
    UNION ALL SELECT 'Popescu', 'Militaire'
    UNION ALL SELECT 'Popescu', 'Quiétus'
    UNION ALL SELECT 'Popescu', 'Célérité'
    UNION ALL SELECT 'Popescu', 'Vampire nouveau né'
    UNION ALL SELECT 'Popescu', 'Goule'

    UNION ALL SELECT 'Lombardi', 'Astronome amateur.rice'
    UNION ALL SELECT 'Lombardi', 'Étudiant.e en arts et lettres'
    UNION ALL SELECT 'Lombardi', 'Aliénation'
    UNION ALL SELECT 'Lombardi', 'Augure'
    UNION ALL SELECT 'Lombardi', 'Goule'
    
    UNION ALL SELECT 'Aliev', 'Détective privé.e'
    UNION ALL SELECT 'Aliev', 'Militaire réserviste'
    UNION ALL SELECT 'Aliev', 'Quiétus'
    UNION ALL SELECT 'Aliev', 'Célérité'
    UNION ALL SELECT 'Aliev', 'Goule'
    UNION ALL SELECT 'Aliev', 'Vampire nouveau né'
    
    UNION ALL SELECT 'Honorius', 'SDF'
    UNION ALL SELECT 'Honorius', 'Hooligan'
    UNION ALL SELECT 'Honorius', 'Endurance'
    UNION ALL SELECT 'Honorius', 'Puissance'
    UNION ALL SELECT 'Honorius', 'Vampire nouveau né'
    
    UNION ALL SELECT 'Martino', 'Ouvrier.ère du bâtiment'
    UNION ALL SELECT 'Martino', 'Alcoolique'
    UNION ALL SELECT 'Martino', 'Nécromancie'
    UNION ALL SELECT 'Martino', 'Goule'
    UNION ALL SELECT 'Martino', 'Vampire nouveau né'

    UNION ALL SELECT 'Messala', 'Militaire réserviste'
    UNION ALL SELECT 'Messala', 'Pompier'
    UNION ALL SELECT 'Messala', 'Célérité'
    UNION ALL SELECT 'Messala', 'Augure'
    UNION ALL SELECT 'Messala', 'Goule'
    
    UNION ALL SELECT 'Ionescu', 'Ouvrier.ère du bâtiment'
    UNION ALL SELECT 'Ionescu', 'Alcoolique'
    UNION ALL SELECT 'Ionescu', 'Nécromancie'
    UNION ALL SELECT 'Ionescu', 'Goule'
    UNION ALL SELECT 'Ionescu', 'Vampire nouveau né'

    UNION ALL SELECT 'Marotta', 'Militaire réserviste'
    UNION ALL SELECT 'Marotta', 'Pompier'
    UNION ALL SELECT 'Marotta', 'Célérité'
    UNION ALL SELECT 'Marotta', 'Augure'
    UNION ALL SELECT 'Marotta', 'Goule'
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;

