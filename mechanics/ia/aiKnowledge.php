<?php

function aiKnownEnemiesCount($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS n FROM {$prefix}controllers_known_enemies
             WHERE controller_id = :cid"
        );
        $stmt->execute([':cid' => $controller_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['n'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Count known enemy workers for $controller_id across one or more
 * zones. Accepts int $zone_id or int[] $zone_ids. Empty list → 0.
 */
function aiKnownEnemiesInZones($pdo, $controller_id, $zones) {
    if (is_int($zones)) $zones = [$zones];
    if (empty($zones)) return 0;
    $prefix = $_SESSION['GAME_PREFIX'];
    $placeholders = implode(',', array_fill(0, count($zones), '?'));
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM {$prefix}controllers_known_enemies
             WHERE controller_id = ? AND zone_id IN ($placeholders)"
        );
        $params = array_merge([$controller_id], array_map('intval', $zones));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function aiKnownEnemyLocationsCount($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS n FROM {$prefix}controller_known_locations ckl
             JOIN {$prefix}locations l ON l.id = ckl.location_id
             WHERE ckl.controller_id = :cid
               AND (l.controller_id IS NULL OR l.controller_id != :cid)
               AND l.can_be_destroyed = True"
        );
        $stmt->execute([':cid' => $controller_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['n'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}
