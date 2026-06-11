<?php

require_once __DIR__ . '/aiZones.php';
require_once __DIR__ . '/aiWorkers.php';

/**
 * Assign claim actions to uncommitted workers. Claims unclaimed-by-self
 * zones first, then contests zones where a non-self controller holds the
 * claim. Worker stats are own-side only (fairness rule).
 */
function aiAllocateClaimAndContest($pdo, $c, $turn_number, $availableWorkerIds = []) {
    $result = ['claimed_zones' => [], 'contest_actions' => []];
    if (empty($availableWorkerIds)) return $result;

    $available_set = array_flip(array_map('intval', $availableWorkerIds));
    $alive = aiAliveWorkers($pdo, $c['id'], $turn_number);
    if (empty($alive)) return $result;

    $workersByZone = [];
    foreach ($alive as $row) {
        $wid = (int)$row['id'];
        if (!isset($available_set[$wid])) continue;
        $zid = (int)$row['zone_id'];
        if (!isset($workersByZone[$zid])) $workersByZone[$zid] = [];
        $workersByZone[$zid][] = $wid;
    }
    if (empty($workersByZone)) return $result;

    $yieldSort = strtoupper((string)getConfig($pdo, 'aiClaimPriorityByYield')) === 'TRUE';
    $self_cid = (int)$c['id'];
    $prefix = $_SESSION['GAME_PREFIX'];

    $zone_info = [];
    foreach (array_keys($workersByZone) as $zid) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, claimer_controller_id, holder_controller_id
                   FROM {$prefix}zones WHERE id = :zid"
            );
            $stmt->bindValue(':zid', (int)$zid, PDO::PARAM_INT);
            $stmt->execute();
            $z = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $z = null;
        }
        if (!$z) continue;
        $zone_info[(int)$zid] = $z;
    }

    $claim_zones = [];
    $contest_zones = [];
    foreach ($zone_info as $zid => $z) {
        $claimer = $z['claimer_controller_id'];
        if ($claimer === null || $claimer === '' || (int)$claimer === 0) {
            $claim_zones[] = $zid;
        } elseif ((int)$claimer !== $self_cid) {
            $contest_zones[] = $zid;
        }
    }

    $sorter = function($a, $b) use ($pdo, $yieldSort) {
        if ($yieldSort) {
            $sa = aiZoneYieldScore($pdo, (int)$a);
            $sb = aiZoneYieldScore($pdo, (int)$b);
            if ($sa !== $sb) return $sb - $sa;
        }
        return (int)$a - (int)$b;
    };
    usort($claim_zones, $sorter);
    usort($contest_zones, $sorter);

    $claim_committed = [];

    foreach ($claim_zones as $zid) {
        if (empty($workersByZone[$zid])) continue;
        $wid = (int)$workersByZone[$zid][0];
        if (isset($claim_committed[$wid])) continue;
        activateWorker($pdo, $wid, 'claim', $self_cid);
        $claim_committed[$wid] = true;
        $result['claimed_zones'][] = (int)$zid;
    }

    // Conservative contest: queue claim on any contested zone where AI has a free worker; mechanism resolves at EOT.
    foreach ($contest_zones as $zid) {
        if (empty($workersByZone[$zid])) continue;
        $picked = null;
        foreach ($workersByZone[$zid] as $wid) {
            $wid = (int)$wid;
            if (isset($claim_committed[$wid])) continue;
            $picked = $wid;
            break;
        }
        if ($picked === null) continue;
        activateWorker($pdo, $picked, 'claim', $self_cid);
        $claim_committed[$picked] = true;
        $result['contest_actions'][] = (int)$picked;
    }

    return $result;
}
