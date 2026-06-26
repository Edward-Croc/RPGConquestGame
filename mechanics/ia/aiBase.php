<?php

/**
 * Ensure the controller has a base. Picks origin_zone_id if set,
 * else a claimed/held zone, else zone id 1.
 */
function aiEnsureBase($pdo, $c) {
    if (empty($c['can_build_base'])) return;

    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM {$prefix}locations
             WHERE controller_id = :cid AND is_base = True LIMIT 1"
        );
        $stmt->execute([':cid' => $c['id']]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) return;

        $zoneId = !empty($c['origin_zone_id']) ? (int)$c['origin_zone_id'] : null;
        if ($zoneId === null) {
            $stmt = $pdo->prepare(
                "SELECT id FROM {$prefix}zones
                 WHERE claimer_controller_id = :cid OR holder_controller_id = :cid
                 ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute([':cid' => $c['id']]);
            $zone = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($zone) $zoneId = (int)$zone['id'];
        }
        if ($zoneId === null) {
            $stmt = $pdo->prepare("SELECT id FROM {$prefix}zones ORDER BY id ASC LIMIT 1");
            $stmt->execute();
            $zone = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($zone) $zoneId = (int)$zone['id'];
        }
        if ($zoneId !== null) {
            if (!hasEnoughRessourcesToBuildBase($pdo, $c['id'])) return;
            $ok = createBase($pdo, $c['id'], $zoneId);
            if (!$ok) return;
        }
    } catch (PDOException $e) {
        echo __FUNCTION__."(): failed: ".$e->getMessage()."<br />";
    }
}

function aiBaseZone($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT zone_id FROM {$prefix}locations
             WHERE controller_id = :cid AND is_base = True LIMIT 1"
        );
        $stmt->execute([':cid' => $controller_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['zone_id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

function aiBaseOrLocationAttackedThisTurn($pdo, $controller_id, $turn_number) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM {$prefix}location_attack_logs
             WHERE target_controller_id = :cid AND turn = :turn
             LIMIT 1"
        );
        $stmt->execute([':cid' => $controller_id, ':turn' => $turn_number]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Relocate the controller's base to the first owned safe zone (no known
 * enemy worker present). Falls back to origin_zone_id, then zone id 1.
 * No-op when the controller still has a base, lacks build permission, or
 * cannot afford to rebuild. Returns the chosen zone id, or null.
 */
function aiRelocateBase($pdo, $c) {
    if (aiBaseZone($pdo, $c['id']) !== null) return null;
    if (empty($c['can_build_base'])) return null;

    $prefix = $_SESSION['GAME_PREFIX'];
    $zoneId = null;

    $owned = aiOwnZoneIds($pdo, $c['id']);
    sort($owned, SORT_NUMERIC);
    foreach ($owned as $z) {
        $zid = (int)$z;
        if (aiKnownEnemiesInZones($pdo, $c['id'], $zid) === 0) { $zoneId = $zid; break; }
    }

    if ($zoneId === null && !empty($c['origin_zone_id'])) {
        $zoneId = (int)$c['origin_zone_id'];
    }
    if ($zoneId === null) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM {$prefix}zones ORDER BY id ASC LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $zoneId = (int)$row['id'];
        } catch (PDOException $e) {
            return null;
        }
    }
    if ($zoneId === null) return null;
    if (!hasEnoughRessourcesToBuildBase($pdo, $c['id'])) return null;

    $ok = createBase($pdo, $c['id'], $zoneId);
    return $ok ? $zoneId : null;
}
