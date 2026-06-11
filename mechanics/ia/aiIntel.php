<?php

require_once __DIR__ . '/aiZones.php';
require_once __DIR__ . '/aiWorkers.php';

/**
 * Assign 'investigate' to a subset of uncommitted workers, prioritizing
 * zones planned for next-turn strike, then zones with stale CKE, then
 * other zones in own+adjacent recon scope. Returns the int[] of worker
 * ids committed this call.
 */
function aiPreStrikeIntel($pdo, $c, $turn_number, $availableWorkerIds = [], $plannedStrikeZones = []) {
    if (empty($availableWorkerIds)) return [];

    $availableSet = array_flip(array_map('intval', $availableWorkerIds));

    $classes = aiClassifyWorkers($pdo, $c['id']);
    $investigators = array_values(array_filter(
        array_map('intval', $classes['investigators'] ?? []),
        function ($wid) use ($availableSet) { return isset($availableSet[$wid]); }
    ));
    $fighters = array_values(array_filter(
        array_map('intval', $classes['fighters'] ?? []),
        function ($wid) use ($availableSet) { return isset($availableSet[$wid]); }
    ));
    $pool = !empty($investigators) ? $investigators : $fighters;
    if (empty($pool)) return [];

    $reconZones = array_map('intval', aiOwnedAndAdjacentZoneIds($pdo, $c['id']));
    if (empty($reconZones)) return [];
    $reconSet = array_flip($reconZones);

    $strikeSet = array_flip(array_map('intval', $plannedStrikeZones));

    $alive = aiAliveWorkers($pdo, $c['id'], $turn_number);
    $workerZoneById = [];
    $zoneOwnWorkerCount = [];
    foreach ($alive as $w) {
        $workerZoneById[(int)$w['id']] = (int)$w['zone_id'];
        $z = (int)$w['zone_id'];
        if (!isset($zoneOwnWorkerCount[$z])) $zoneOwnWorkerCount[$z] = 0;
        $zoneOwnWorkerCount[$z]++;
    }

    $staleSet = aiStaleCkeZones($pdo, $c['id'], $turn_number, $reconZones);

    $zonePriority = [];
    foreach ($reconZones as $z) {
        if (isset($strikeSet[$z])) {
            $zonePriority[$z] = 10;
        } elseif (isset($staleSet[$z])) {
            $zonePriority[$z] = 5;
        } else {
            $zonePriority[$z] = 1;
        }
    }

    arsort($zonePriority);
    $rankedZones = array_keys($zonePriority);

    $committed = [];
    foreach ($pool as $wid) {
        if (!isset($workerZoneById[$wid])) continue;
        $currentZone = $workerZoneById[$wid];

        if (isset($zonePriority[$currentZone]) && $zonePriority[$currentZone] > 0) {
            activateWorker($pdo, $wid, 'investigate', null);
            $committed[] = $wid;
            continue;
        }

        $targetZone = null;
        foreach ($rankedZones as $z) {
            if (($zoneOwnWorkerCount[$z] ?? 0) === 0) { $targetZone = $z; break; }
        }
        if ($targetZone === null) continue;
        moveWorker($pdo, $wid, $targetZone);
        $zoneOwnWorkerCount[$targetZone] = ($zoneOwnWorkerCount[$targetZone] ?? 0) + 1;
        $zoneOwnWorkerCount[$currentZone] = max(0, ($zoneOwnWorkerCount[$currentZone] ?? 1) - 1);
        $committed[] = $wid;
    }

    return $committed;
}

/**
 * Return zone_id => true map for recon zones whose CKE is stale
 * (no row with last_discovery_turn >= turn_number - 1).
 */
function aiStaleCkeZones($pdo, $controller_id, $turn_number, $reconZones) {
    $stale = [];
    if (empty($reconZones)) return $stale;
    $prefix = $_SESSION['GAME_PREFIX'];
    $threshold = (int)$turn_number - 1;
    foreach ($reconZones as $z) {
        try {
            $stmt = $pdo->prepare(
                "SELECT 1 FROM {$prefix}controllers_known_enemies
                 WHERE controller_id = :cid AND zone_id = :zid
                   AND last_discovery_turn >= :thr LIMIT 1"
            );
            $stmt->execute([':cid' => $controller_id, ':zid' => (int)$z, ':thr' => $threshold]);
            $fresh = (bool) $stmt->fetch();
        } catch (PDOException $e) {
            $fresh = true;
        }
        if (!$fresh) $stale[(int)$z] = true;
    }
    return $stale;
}
