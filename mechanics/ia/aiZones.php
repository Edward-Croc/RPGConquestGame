<?php

require_once __DIR__ . '/aiBase.php';

/**
 * Resolve the zone set each ai_type considers reachable for worker
 * placement. Always includes the base zone if one exists.
 */
function aiAllowedZonesForState($pdo, $c, $state) {
    $base_zone = aiBaseZone($pdo, $c['id']);
    if ($state === 'passive') {
        $zones = aiOwnZoneIds($pdo, $c['id']);
    } else {
        $zones = aiOwnedAndAdjacentZoneIds($pdo, $c['id']);
    }
    if ($base_zone !== null && !in_array((int)$base_zone, array_map('intval', $zones), true)) {
        $zones[] = (int)$base_zone;
    }
    return array_values(array_map('intval', $zones));
}

function aiOwnZoneIds($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM {$prefix}zones
             WHERE claimer_controller_id = :cid OR holder_controller_id = :cid"
        );
        $stmt->execute([':cid' => $controller_id]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Sum a yield-priority score across all locations in a zone. Tags drive
 * the score (temple/fortress +2, base +3, otherwise +1). location_types
 * JSON parsed in PHP for cross-DB safety. Empty zone returns 0.
 */
function aiZoneYieldScore($pdo, $zone_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare("SELECT id, is_base, location_types
            FROM {$prefix}locations WHERE zone_id = :zid");
        $stmt->bindValue(':zid', (int)$zone_id, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return 0;
    }
    if (empty($rows)) return 0;

    $score = 0;
    foreach ($rows as $row) {
        $tags = json_decode($row['location_types'] ?? '[]', true);
        if (!is_array($tags)) $tags = [];
        $hasTemple = in_array('temple', $tags, true);
        $hasFortress = in_array('fortress', $tags, true);
        $isBase = !empty($row['is_base']);
        if ($hasTemple || $hasFortress) {
            $score += 2;
        } elseif ($isBase) {
            $score += 3;
        } else {
            $score += 1;
        }
    }
    return $score;
}

/**
 * Return the deduped int[] of zone ids the controller owns plus one
 * hop of adjacency from each.
 */
function aiOwnedAndAdjacentZoneIds($pdo, $controller_id) {
    $owned = aiOwnZoneIds($pdo, $controller_id);
    $set = [];
    foreach ($owned as $z) {
        $set[(int)$z] = true;
        foreach (getAdjacentZoneIds($pdo, (int)$z) as $adj) {
            $set[(int)$adj] = true;
        }
    }
    return array_keys($set);
}
