-- Warning: If you read this file, you will no longer be eligible to participate as a player.

-- UPDATE config SET value = 0 WHERE name in ( 'REPORTDIFF0', 'REPORTDIFF1', 'REPORTDIFF2', 'REPORTDIFF3');
/*
    Test Claiming Zone
    -- Base test claim, passive, via investigate value sucess and fail
    -- Multi claim, should be violent, 
*/

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
    UNION ALL SELECT 'Honorius', 'Hooligan "tifoso.a"'
    UNION ALL SELECT 'Honorius', 'Endurance'
    UNION ALL SELECT 'Honorius', 'Puissance'
    UNION ALL SELECT 'Honorius', 'Vampire nouveau né'
    
    UNION ALL SELECT 'Martino', 'Ouvrier.ère du bâtiment'
    UNION ALL SELECT 'Martino', 'Alcoolique'
    UNION ALL SELECT 'Martino', 'Nécromancie'
    UNION ALL SELECT 'Martino', 'Goule'
    UNION ALL SELECT 'Martino', 'Vampire nouveau né'

    UNION ALL SELECT 'Messala', 'Militaire réserviste'
    UNION ALL SELECT 'Messala', 'Pompier.ère'
    UNION ALL SELECT 'Messala', 'Célérité'
    UNION ALL SELECT 'Messala', 'Augure'
    UNION ALL SELECT 'Messala', 'Goule'
    
    UNION ALL SELECT 'Ionescu', 'Ouvrier.ère du bâtiment'
    UNION ALL SELECT 'Ionescu', 'Alcoolique'
    UNION ALL SELECT 'Ionescu', 'Nécromancie'
    UNION ALL SELECT 'Ionescu', 'Goule'
    UNION ALL SELECT 'Ionescu', 'Vampire nouveau né'

    UNION ALL SELECT 'Marotta', 'Militaire réserviste'
    UNION ALL SELECT 'Marotta', 'Pompier.ère'
    UNION ALL SELECT 'Marotta', 'Célérité'
    UNION ALL SELECT 'Marotta', 'Augure'
    UNION ALL SELECT 'Marotta', 'Goule'
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;

/*
Tests should be :

For agents :
    Setup for tests 
        - Create 6 agents int the Zone A 
            - Agent 1 and 2 should have a investigation lvl of 4 and a combat lvl of 3/3
            - Agent 3 shoudl have a investigation level of 3 and a atk/defense LVL of 0/2
            - Agent 4 should have a investigation level of 2 and a atk/defense lvl of 0/0
            - Agent 5 should have a investigation level of 1 and a atk/defense lvl of 5/3
            - Agent 6 should have a investigation level of 0 and be on investigate to check the random function

        - In the zone A we need to create 1 location with description and artefact
            - Location A have discovery diff of 4, a name a description and a linked artefact

        - Click End turn button

    Agent Detection of Agent Test (for the 4 level of information obtainable) )
        - Agents 1-5 agents should have there LVL +3 as value and the correct information
        - Agent 6 should have a random value between 1 and 6
        - Check that each agent has the correct information
            - 1 Can see 2, 3, 4 and 5
            - 3 Can't see 1 and 2, but can see 4 and 5
            - 4 Can se only 5
            - 5 Sees no one

    Agent detection of Location for the 2 level of details and the presence of and artifact
        - Agent 1 should see the location name details and artefact
        - Agent 3 should see the location name details and not artefact
        - Agent 4 should see the location name and no details or artefact
        - Agent 5 should not see the location

    Agent attack of Agent Test ( for the 4 possible results)
        - Use agents of privious setup
        - Drop random to 1
        - Agent 1 should attack agents 2, 3, 4
        - Agent 2 should attack agents 5
        - Click End turn button
        - Agent 1 attack on agent 2 should have no effect 
        - Agent 1 attack on agent 3 should be a victory ATTACKDIFF0 = 1
        - Agent 1 attack on agent 4 should be a capture ATTACKDIFF1 = 4
        - Agent 2 attack on agent 5 should be defeat RIPOSTDIFF = 2

    Agent changing controller correctly acts for the new controller
        - Agent 6 should change to Zone B


    Agent attack of another agent that has move or changed controller
        - Agent 1 should attack agent 6,
        - Click End turn button
        - 6 Should be dead

    Captured agent is correctly represented in both controllers
        - 4 Should be capture

    Return of captured agent, reverts to normal agent

    Double agent are correctly identified
    Dead agents stay dead and do not interact anymore

    Claiming agent can be silent 

    Claiming should be violent


For Locations :
    Detected location should appear in zone list and report
    Owned locations should appear in zone list
    Attack of location can fail or succeed, and an artifact can be captured

UPDATE config SET value = 3 WHERE name in ('MINROLL', 'MAXROLL');

*/

/*
- Agent 1 and 2 should have a investigation lvl of 4 and a combat lvl of 3/3
- Agent 3 shoudl have a investigation level of 3 and a atk/defense LVL of 0/2
- Agent 4 should have a investigation level of 2 and a atk/defense lvl of 0/0
- Agent 5 should have a investigation level of 1 and a atk/defense lvl of 5/3
- Agent 6 should have a investigation level of 0 and be on investigate to check the random function
*/
WITH names_data(firstname, lastname, origin_name, zone_name) AS (
    VALUES
        ('1', '1', 'Firenze', 'Piazza della Liberta & Savonarola'),
        ('2', '2', 'Firenze', 'Piazza della Liberta & Savonarola'),
        ('3', '3', 'Firenze', 'Piazza della Liberta & Savonarola'),
        ('4', '4', 'Firenze', 'Piazza della Liberta & Savonarola'),
        ('5', '5', 'Firenze', 'Piazza della Liberta & Savonarola'),
        ('6', '6', 'Firenze', 'Piazza della Liberta & Savonarola')
)
INSERT INTO workers (firstname, lastname, origin_id, zone_id)
SELECT
    nd.firstname,
    nd.lastname,
    wo.id AS origin_id,
    z.id AS zone_id
FROM names_data nd
JOIN worker_origins wo ON wo.name = nd.origin_name
JOIN zones z ON z.name = nd.zone_name;


INSERT INTO controller_worker (controller_id, worker_id) VALUES
    -- Base test claim, passive, investigate
    ( 1, (SELECT ID FROM workers WHERE lastname in ('1')))
    , ( 2, (SELECT ID FROM workers WHERE lastname in ('2')))
    , ( 3, (SELECT ID FROM workers WHERE lastname in ('3')))
    , ( 4, (SELECT ID FROM workers WHERE lastname in ('4')))
    , ( 5, (SELECT ID FROM workers WHERE lastname in ('5')))
    , ( 6, (SELECT ID FROM workers WHERE lastname in ('6')))
;

-- Add actions to the workers :
INSERT INTO worker_actions (
    worker_id, controller_id, turn_number, zone_id, action_choice, action_params
)
SELECT
    w.id,
    cw.controller_id,
    0,
    w.zone_id,
    'passive',
    '{}'::json
FROM (
    SELECT '1' AS lastname
    UNION ALL SELECT '2'
    UNION ALL SELECT  '3'
    UNION ALL SELECT  '4'
    UNION ALL SELECT  '5'
    UNION ALL SELECT  '6'
) AS entry
JOIN workers w ON w.lastname = entry.lastname
JOIN controller_worker cw ON cw.worker_id = w.id;
;

UPDATE worker_actions
SET action_choice = 'investigate'
WHERE worker_id = (SELECT ID FROM workers WHERE lastname in ('6'));

-- Add powers to the workers :
INSERT INTO worker_powers (worker_id, link_power_type_id)
SELECT 
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM 
    workers w
JOIN (
    -- Agent 1 and 2 should have a investigation lvl of 4 and a combat lvl of 4/4
    SELECT '1' AS lastname, 'Vampire nouveau né' AS power_name --1,1,2
    UNION ALL SELECT '1', 'Punk à chien' -- 1,1,-1
    UNION ALL SELECT '1', 'Domination' --1,1,1
    UNION ALL SELECT '1', 'Célérité' -- 1,1,1
    UNION ALL SELECT '1', 'Goule' -- 0,0,1
    UNION ALL SELECT '2', 'Vampire nouveau né' --1,1,2
    UNION ALL SELECT '2', 'Punk à chien' -- 1,1,-1
    UNION ALL SELECT '2', 'Domination' --1,1,1
    UNION ALL SELECT '2', 'Célérité' -- 1,1,1
    UNION ALL SELECT '2', 'Goule' -- 0,0,1

-- Agent 3 shoudl have a investigation level of 3 and a atk/defense LVL of 2/2
    UNION ALL SELECT '3', 'Vampire nouveau né' --1,1,2
    UNION ALL SELECT '3', 'Punk à chien' -- 1,1,-1
    UNION ALL SELECT '3', 'Radio-amateur.rice' --1,0,0
    UNION ALL SELECT '3', 'Goule' --0,0,1
    
-- Agent 4 should have a investigation level of 2 and a atk/defense lvl of 0/0
    UNION ALL SELECT '4', 'Animalisme' --2,0,0
    
-- Agent 5 should have a investigation level of 1 and a atk/defense lvl of 5/3
    UNION ALL SELECT '5', 'Agent.e de sécurité' --0,1,1
    UNION ALL SELECT '5', 'Collectionneur.se de couteaux' -- 0,1,1
    UNION ALL SELECT '5', 'Endurance' -- 0,0,2
    UNION ALL SELECT '5', 'Puissance' -- 0,2,0
    UNION ALL SELECT '5', 'Célérité' -- 1,1,1

-- Agent 6 should have a investigation level of 0 and be on investigate to check the random function
    UNION ALL SELECT '6', 'Agent.e de sécurité' --0,1,1
    UNION ALL SELECT '6', 'Collectionneur.se de couteaux' -- 0,1,1
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;

/*
        - In the zone A we need to create 1 location with description and artefact
            - Location A have discovery diff of 4, a name a description and a linked artefact
*/
INSERT INTO locations (name, description, discovery_diff, zone_id) VALUES
    ('Pallazzo Medeci Ricardi', 'TESTDecription', 4,(SELECT ID FROM zones WHERE name = 'Piazza della Liberta & Savonarola'))
;
INSERT INTO locations (name, description, discovery_diff, can_be_destroyed, zone_id, controller_id) VALUES
   
