<?php

require_once __DIR__ . '/aiZones.php';

function aiRankTargets($pdo, $c) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $cid = (int) ($c['id'] ?? 0);
    $origin_zone_id = isset($c['origin_zone_id']) ? (int) $c['origin_zone_id'] : 0;
    if ($cid <= 0) return [];

    $own_zone_ids = array_map('intval', aiOwnZoneIds($pdo, $cid));
    $own_and_adj = array_map('intval', aiOwnedAndAdjacentZoneIds($pdo, $cid));
    $adj_only = array_values(array_diff($own_and_adj, $own_zone_ids));

    $targets = [];

    try {
        $stmt = $pdo->prepare(
            "SELECT zone_id, discovered_worker_id FROM {$prefix}controllers_known_enemies
             WHERE controller_id = :cid AND discovered_worker_id IS NOT NULL"
        );
        $stmt->execute([':cid' => $cid]);
        $cke_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cke_rows = [];
    }

    foreach ($cke_rows as $row) {
        $zid = (int) $row['zone_id'];
        $wid = (int) $row['discovered_worker_id'];
        if ($wid <= 0) continue;
        $estimated_strength = aiWorkerPowerCount($pdo, $wid);
        $distance_score = aiTargetDistanceScore($pdo, $zid, $origin_zone_id, $own_zone_ids, $adj_only);
        $yield_score = function_exists('aiZoneYieldScore') ? (int) aiZoneYieldScore($pdo, $zid) : 0;
        $priority = (10 - $estimated_strength) + $distance_score + $yield_score;
        $targets[] = [
            'kind' => 'worker',
            'zone_id' => $zid,
            'target_id' => $wid,
            'estimated_strength' => $estimated_strength,
            'distance_score' => $distance_score,
            'yield_score' => $yield_score,
            'priority' => $priority,
        ];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT ckl.location_id, l.zone_id FROM {$prefix}controller_known_locations ckl
             JOIN {$prefix}locations l ON l.id = ckl.location_id
             WHERE ckl.controller_id = :cid
               AND (l.controller_id IS NULL OR l.controller_id != :cid)
               AND l.can_be_destroyed = True"
        );
        $stmt->execute([':cid' => $cid]);
        $ckl_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ckl_rows = [];
    }

    foreach ($ckl_rows as $row) {
        $zid = (int) $row['zone_id'];
        $lid = (int) $row['location_id'];
        $distance_score = aiTargetDistanceScore($pdo, $zid, $origin_zone_id, $own_zone_ids, $adj_only);
        $yield_score = function_exists('aiZoneYieldScore') ? (int) aiZoneYieldScore($pdo, $zid) : 0;
        $priority = 5 + $distance_score + $yield_score;
        $targets[] = [
            'kind' => 'location',
            'zone_id' => $zid,
            'target_id' => $lid,
            'estimated_strength' => 0,
            'distance_score' => $distance_score,
            'yield_score' => $yield_score,
            'priority' => $priority,
        ];
    }

    usort($targets, function ($a, $b) {
        return $b['priority'] <=> $a['priority'];
    });

    return $targets;
}

function aiTargetDistanceScore($pdo, $zone_id, $origin_zone_id, $own_zone_ids, $adj_only_zone_ids) {
    $zid = (int) $zone_id;
    if ($origin_zone_id > 0 && $zid === (int) $origin_zone_id) return 3;
    if (in_array($zid, $own_zone_ids, true)) return 2;
    if (in_array($zid, $adj_only_zone_ids, true)) return 1;
    return 0;
}
