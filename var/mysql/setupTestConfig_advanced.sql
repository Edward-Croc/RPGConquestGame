-- TestConfig advanced: test workers for issue #2 game mechanics tests
-- Requires: factions, controllers, zones, powers (hobbys+jobs) loaded first

-- Deterministic dice: set MINROLL = MAXROLL = 3 (matches PASSIVEVAL)
UPDATE {prefix}config SET value = '3' WHERE name = 'MINROLL';
UPDATE {prefix}config SET value = '3' WHERE name = 'MAXROLL';

-- =====================================================================
-- Create 7 test workers in ZoneA
-- Agent1-6: detection/combat tests (issue #2)
-- Agent7: negative stat values test
-- =====================================================================
INSERT INTO {prefix}workers (firstname, lastname, origin_id, zone_id)
SELECT
    nd.firstname, nd.lastname,
    wo.id AS origin_id,
    z.id AS zone_id
FROM (
    SELECT 'Test' AS firstname, 'Agent1' AS lastname, 'origine Accessible' AS origin_name, 'ZoneA' AS zone_name
    UNION ALL SELECT 'Test', 'Agent2', 'origine Accessible', 'ZoneA'
    UNION ALL SELECT 'Test', 'Agent3', 'origine Limitée', 'ZoneA'
    UNION ALL SELECT 'Test', 'Agent4', 'origine Limitée', 'ZoneA'
    UNION ALL SELECT 'Test', 'Agent5', 'origine Commune', 'ZoneA'
    UNION ALL SELECT 'Test', 'Agent6', 'origine Commune', 'ZoneA'
    UNION ALL SELECT 'Test', 'Agent7', 'origine Commune', 'ZoneA'
) AS nd
JOIN {prefix}worker_origins wo ON wo.name = nd.origin_name
JOIN {prefix}zones z ON z.name = nd.zone_name;

-- =====================================================================
-- Assign each agent to its own controller
-- Agent1->Charlie, Agent2->Delta, Agent3->Echo, Agent4->Foxtrot, Agent5->Golf
-- Agent6->Alpha (reuse existing), Agent7->Beta (reuse existing)
-- =====================================================================
INSERT INTO {prefix}controller_worker (controller_id, worker_id)
SELECT c.id, w.id
FROM {prefix}controllers c, {prefix}workers w
WHERE (c.lastname = 'Charlie' AND w.lastname = 'Agent1')
   OR (c.lastname = 'Delta'   AND w.lastname = 'Agent2')
   OR (c.lastname = 'Echo'    AND w.lastname = 'Agent3')
   OR (c.lastname = 'Foxtrot' AND w.lastname = 'Agent4')
   OR (c.lastname = 'Golf'    AND w.lastname = 'Agent5')
   OR (c.lastname = 'Alpha'   AND w.lastname = 'Agent6')
   OR (c.lastname = 'Beta'    AND w.lastname = 'Agent7');

-- =====================================================================
-- Pre-set worker actions for turn 0
-- Agents 1-5: passive (investigation val = PASSIVEVAL + power bonus)
-- Agent 6: investigate (to test active dice roll, val = ROLL + 0 = 3)
-- Agent 7: passive (negative stat test)
-- =====================================================================
INSERT INTO {prefix}worker_actions (
    worker_id, controller_id, turn_number, zone_id, action_choice, action_params
)
SELECT
    w.id, cw.controller_id, 0, w.zone_id,
    entry.action_choice, entry.action_params
FROM (
    SELECT 'Agent1' AS lastname, 'passive' AS action_choice, '{}' AS action_params
    UNION ALL SELECT 'Agent2', 'passive', '{}'
    UNION ALL SELECT 'Agent3', 'passive', '{}'
    UNION ALL SELECT 'Agent4', 'passive', '{}'
    UNION ALL SELECT 'Agent5', 'passive', '{}'
    UNION ALL SELECT 'Agent6', 'investigate', '{}'
    UNION ALL SELECT 'Agent7', 'passive', '{}'
) AS entry
JOIN {prefix}workers w ON w.lastname = entry.lastname
JOIN {prefix}controller_worker cw ON cw.worker_id = w.id;

-- =====================================================================
-- Assign powers to workers (1 hobby + 1 metier each)
-- Stats are additive: total = hobby + metier
--
-- Agent1: Hobby A(2,1,1) + Metier A(2,2,2) = 4/3/3
-- Agent2: Hobby A(2,1,1) + Metier A(2,2,2) = 4/3/3
-- Agent3: Hobby B(1,0,1) + Metier B(2,0,1) = 3/0/2
-- Agent4: Hobby C(1,0,0) + Metier C(1,0,0) = 2/0/0
-- Agent5: Hobby D(0,3,2) + Metier D(1,2,1) = 1/5/3
-- Agent6: Hobby E(0,0,0) + Metier E(0,0,0) = 0/0/0
-- Agent7: Hobby Neg(-1,1,-1) + Metier C(1,0,0) = 0/1/-1 (negative defence)
-- =====================================================================
INSERT INTO {prefix}worker_powers (worker_id, link_power_type_id)
SELECT w.id, lpt.id
FROM {prefix}workers w
JOIN (
    SELECT 'Agent1' AS lastname, 'Test Hobby A' AS power_name
    UNION ALL SELECT 'Agent1', 'Test Metier A'
    UNION ALL SELECT 'Agent2', 'Test Hobby A'
    UNION ALL SELECT 'Agent2', 'Test Metier A'
    UNION ALL SELECT 'Agent3', 'Test Hobby B'
    UNION ALL SELECT 'Agent3', 'Test Metier B'
    UNION ALL SELECT 'Agent4', 'Test Hobby C'
    UNION ALL SELECT 'Agent4', 'Test Metier C'
    UNION ALL SELECT 'Agent5', 'Test Hobby D'
    UNION ALL SELECT 'Agent5', 'Test Metier D'
    UNION ALL SELECT 'Agent6', 'Test Hobby E'
    UNION ALL SELECT 'Agent6', 'Test Metier E'
    UNION ALL SELECT 'Agent7', 'Test Hobby Neg'
    UNION ALL SELECT 'Agent7', 'Test Metier C'
) AS wp ON wp.lastname = w.lastname
JOIN {prefix}powers p ON p.name = wp.power_name
JOIN {prefix}link_power_type lpt ON lpt.power_id = p.id;
