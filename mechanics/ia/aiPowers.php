<?php

/**
 * Parse the comma-separated aiPowerPriorityList config into a clean
 * array of type names, with the canonical fallback when unset.
 */
function aiPowerPriorityList($pdo) {
    $raw = getConfig($pdo, 'aiPowerPriorityList');
    $list = [];
    if (is_string($raw) && trim($raw) !== '') {
        foreach (explode(',', $raw) as $part) {
            $name = trim($part);
            if ($name !== '') $list[] = $name;
        }
    }
    if (empty($list)) $list = ['Discipline', 'Transformation'];
    return $list;
}

/**
 * Return [typeName => link_power_type_id] picking the first available
 * link per priority-listed type from this faction's pool. Types absent
 * from the faction's powers are simply omitted from the result.
 */
function aiFactionPowerLinksByType($pdo, $faction_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $priorityList = aiPowerPriorityList($pdo);

    $placeholders = [];
    $params = [':fid' => $faction_id];
    foreach ($priorityList as $i => $name) {
        $key = ':pt'.$i;
        $placeholders[] = $key;
        $params[$key] = $name;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT lpt.id AS link_id, pt.name AS type_name
             FROM {$prefix}link_power_type lpt
             JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.id
             JOIN {$prefix}faction_powers fp ON fp.link_power_type_id = lpt.id
             WHERE fp.faction_id = :fid
               AND pt.name IN (".implode(',', $placeholders).")
             ORDER BY pt.name, lpt.id ASC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
    $byType = [];
    foreach ($rows as $r) {
        if (!isset($byType[$r['type_name']])) $byType[$r['type_name']] = $r['link_id'];
    }
    return $byType;
}

/**
 * Resolve a power_type row id from its name. Returns null if absent.
 */
function aiPowerTypeIdByName($pdo, $name) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare("SELECT id FROM {$prefix}power_types WHERE name = :n LIMIT 1");
        $stmt->execute([':n' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Return the first transformation link_power_type_id this worker is
 * eligible for, using the same conditional-JSON filter as the human
 * recruit form (on_transformation state). null if none.
 */
function aiAvailableTransformationLinkId($pdo, $c, $worker_id, $turn_number) {
    $typeId = aiPowerTypeIdByName($pdo, 'Transformation');
    if ($typeId === null) return null;
    $all = getPowersByType($pdo, (string)$typeId, null, false);
    if (empty($all)) return null;
    $available = cleanPowerListFromJsonConditions($pdo, $all, $c['id'], $worker_id, $turn_number, 'on_transformation');
    if (empty($available)) return null;
    $first = reset($available);
    return !empty($first['link_power_type_id']) ? (int) $first['link_power_type_id'] : null;
}

/**
 * Teach the first available faction power of each type in
 * aiPowerPriorityList to every worker missing one. Disciplines and
 * other faction-tied types come from faction_powers; Transformations
 * are filtered per-worker via on_transformation JSON conditions.
 * Idempotent.
 */
function aiEquipPowers($pdo, $c) {
    $prefix = $_SESSION['GAME_PREFIX'];

    $priorityList = aiPowerPriorityList($pdo);
    $linkByType = aiFactionPowerLinksByType($pdo, $c['faction_id']);

    try {
        $stmt = $pdo->prepare(
            "SELECT w.id FROM {$prefix}workers w
             JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
             WHERE cw.controller_id = :cid"
        );
        $stmt->execute([':cid' => $c['id']]);
        $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }
    if (empty($workers)) return;

    $mechanics = getMechanics($pdo);
    $turn_number = (int) ($mechanics['turncounter'] ?? 0);

    foreach ($workers as $w) {
        foreach ($priorityList as $typeName) {
            if (aiWorkerHasPowerType($pdo, $w['id'], $typeName)) continue;
            $linkId = null;
            if ($typeName === 'Transformation') {
                $linkId = aiAvailableTransformationLinkId($pdo, $c, $w['id'], $turn_number);
            } elseif (isset($linkByType[$typeName])) {
                $linkId = $linkByType[$typeName];
            }
            if ($linkId !== null) {
                upgradeWorker($pdo, $w['id'], $linkId);
            }
        }
    }
}

function aiWorkerHasPowerType($pdo, $worker_id, $type_name) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM {$prefix}worker_powers wp
             JOIN {$prefix}link_power_type lpt ON lpt.id = wp.link_power_type_id
             JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.id
             WHERE wp.worker_id = :wid AND pt.name = :tn LIMIT 1"
        );
        $stmt->execute([':wid' => $worker_id, ':tn' => $type_name]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return true;
    }
}
