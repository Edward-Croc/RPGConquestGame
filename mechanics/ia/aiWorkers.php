<?php

/**
 * Count powers attached to a worker. Used as the fair strength proxy
 * for known-enemy workers per the AI fairness rule (+1 per power).
 */
function aiWorkerPowerCount($pdo, $worker_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM {$prefix}worker_powers WHERE worker_id = :wid"
        );
        $stmt->execute([':wid' => $worker_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Set every alive worker's action_choice to $action, skipping any worker
 * id in $skipWorkerIds (typically workers just moved this turn).
 */
function aiSetWorkerActionsForState($pdo, $c, $action, $turn_number, $skipWorkerIds = []) {
    $skip = array_flip(array_map('intval', $skipWorkerIds));
    $workers = aiAliveWorkers($pdo, $c['id'], $turn_number);
    foreach ($workers as $w) {
        if (isset($skip[(int)$w['id']])) continue;
        activateWorker($pdo, $w['id'], $action, null);
    }
}

/**
 * Aggressive/violent worker action assignment.
 * $globalAttack=true attacks any known enemy worker anywhere.
 * $globalAttack=false attacks enemies in the worker's current zone.
 * Workers without an attack target fall back to investigate.
 * Workers in $skipWorkerIds are left untouched.
 */
function aiSetAggressiveWorkerActions($pdo, $c, $turn_number, $globalAttack, $skipWorkerIds = []) {
    $skip = array_flip(array_map('intval', $skipWorkerIds));
    $workers = aiAliveWorkers($pdo, $c['id'], $turn_number);
    if (empty($workers)) return;

    $prefix = $_SESSION['GAME_PREFIX'];

    foreach ($workers as $w) {
        if (isset($skip[(int)$w['id']])) continue;
        try {
            if ($globalAttack) {
                $eStmt = $pdo->prepare(
                    "SELECT discovered_worker_id FROM {$prefix}controllers_known_enemies
                     WHERE controller_id = :cid
                     ORDER BY discovered_worker_id ASC LIMIT 1"
                );
                $eStmt->execute([':cid' => $c['id']]);
            } else {
                $eStmt = $pdo->prepare(
                    "SELECT discovered_worker_id FROM {$prefix}controllers_known_enemies
                     WHERE controller_id = :cid AND zone_id = :zid
                     ORDER BY discovered_worker_id ASC LIMIT 1"
                );
                $eStmt->execute([':cid' => $c['id'], ':zid' => $w['zone_id']]);
            }
            $enemy = $eStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $enemy = null;
        }
        if ($enemy) {
            activateWorker($pdo, $w['id'], 'attack', ['worker_'.$enemy['discovered_worker_id']]);
        } else {
            activateWorker($pdo, $w['id'], 'investigate', null);
        }
    }
}

/**
 * Distribute alive workers across $allowedZones. Workers already in an
 * allowed zone stay; workers outside go to the least-occupied allowed
 * zone. Returns the int[] of worker ids that were actually moved
 * (these workers stay action_choice='passive' for the turn).
 */
function aiDistributeWorkers($pdo, $c, $turn_number, $allowedZones) {
    if (empty($allowedZones)) return [];
    $workers = aiAliveWorkers($pdo, $c['id'], $turn_number);
    if (empty($workers)) return [];

    $count = array_fill_keys(array_map('intval', $allowedZones), 0);
    $needsMove = [];
    foreach ($workers as $w) {
        $z = (int)$w['zone_id'];
        if (isset($count[$z])) {
            $count[$z]++;
        } else {
            $needsMove[] = $w;
        }
    }

    $moved = [];
    foreach ($needsMove as $w) {
        $minZone = null; $minCount = PHP_INT_MAX;
        foreach ($count as $z => $n) {
            if ($n < $minCount) { $minCount = $n; $minZone = $z; }
        }
        moveWorker($pdo, $w['id'], $minZone);
        $count[$minZone]++;
        $moved[] = (int)$w['id'];
    }
    return $moved;
}

/**
 * Split alive primary-controller workers into investigators and fighters
 * by comparing total_enquete vs total_attack/total_defence. Ties go to
 * fighter (strict >).
 */
function aiClassifyWorkers($pdo, $controller_id) {
    $mechanics = getMechanics($pdo);
    $turn_number = (int)($mechanics['turncounter'] ?? 0);
    $alive = aiAliveWorkers($pdo, $controller_id, $turn_number);
    $result = ['investigators' => [], 'fighters' => []];
    if (empty($alive)) return $result;

    foreach ($alive as $row) {
        $workerId = (int)$row['id'];
        $workers = getWorkers($pdo, [$workerId]);
        if (empty($workers)) { $result['fighters'][] = $workerId; continue; }
        $w = $workers[0];
        $enq = (int)($w['total_enquete'] ?? 0);
        $atk = (int)($w['total_attack'] ?? 0);
        $def = (int)($w['total_defence'] ?? 0);
        if ($enq > $atk && $enq > $def) {
            $result['investigators'][] = $workerId;
        } else {
            $result['fighters'][] = $workerId;
        }
    }
    return $result;
}

/**
 * For each target zone the AI must own, if no alive worker of $c is
 * already there, move one adjacent alive worker (lowest id) into it.
 * Each target zone receives at most one mover per turn; workers in
 * $skipWorkerIds are not eligible. Returns int[] of moved worker ids.
 */
function aiMoveTowardTargetZones($pdo, $c, $turn_number, $skipWorkerIds = []): array {
    $targets = aiTargetZoneIds($pdo, (int)$c['id']);
    if (empty($targets)) return [];

    $alive = aiAliveWorkers($pdo, $c['id'], $turn_number);
    if (empty($alive)) return [];

    $skip = array_flip(array_map('intval', $skipWorkerIds));
    $prefix = $_SESSION['GAME_PREFIX'];
    $primaryTrue = ($_SESSION['DBTYPE'] == 'mysql') ? '1' : 'true';

    $moved = [];
    foreach ($targets as $z) {
        $z = (int)$z;
        if ($z <= 0) continue;

        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM {$prefix}workers w
                 JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
                 WHERE cw.controller_id = :cid AND cw.is_primary_controller = {$primaryTrue}
                   AND w.zone_id = :zid"
            );
            $stmt->execute([':cid' => $c['id'], ':zid' => $z]);
            $already = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            continue;
        }
        if ($already > 0) continue;

        $adjacent = array_flip(array_map('intval', getAdjacentZoneIds($pdo, $z)));
        if (empty($adjacent)) continue;

        foreach ($alive as $w) {
            $wid = (int)$w['id'];
            if (isset($skip[$wid])) continue;
            if (in_array($wid, $moved, true)) continue;
            if (!isset($adjacent[(int)$w['zone_id']])) continue;
            moveWorker($pdo, $wid, $z);
            $moved[] = $wid;
            break;
        }
    }
    return $moved;
}

function aiAliveWorkers($pdo, $controller_id, $turn_number) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $inactive = "'".implode("','", INACTIVE_ACTIONS)."'";
    try {
        $sql = sprintf(
            "SELECT w.id, w.zone_id FROM {$prefix}workers w
             JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
             LEFT JOIN {$prefix}worker_actions wa
                 ON wa.worker_id = w.id AND wa.turn_number = :turn
             WHERE cw.controller_id = :cid AND cw.is_primary_controller = %s
               AND (wa.action_choice IS NULL OR wa.action_choice NOT IN (%s))
             ORDER BY w.id ASC",
            ($_SESSION['DBTYPE'] == 'mysql') ? '1' : 'true',
            $inactive
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cid' => $controller_id, ':turn' => $turn_number]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
