<?php

/**
 * Rebuild any owned, repairable locations while resources allow.
 */
function aiRebuildOwnedLocations($pdo, $c) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM {$prefix}locations
             WHERE controller_id = :cid AND can_be_repaired = True
             ORDER BY id ASC"
        );
        $stmt->execute([':cid' => $c['id']]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT failed: ".$e->getMessage()."<br />";
        return;
    }
    foreach ($locations as $location) {
        if (!hasEnoughRessourcesToRepairLocation($pdo, $c['id'])) {
            continue;
        }
        $activate_json = json_decode($location['activate_json'], true);
        if (!spendRessourcesToRepairLocation($pdo, $c['id'])) continue;
        updateLocation($pdo, $location, $activate_json);
    }
}

/**
 * Concentrate top-defence fighters in the base zone when the AI is under
 * threat or its base/locations were attacked last turn. Surplus fighters
 * outside the base zone are set to 'hide'. Investigators are untouched.
 * Returns ['moved' => int[], 'hidden' => int[]].
 */
function aiDefensiveConsolidate($pdo, $c, $turn_number, $budget) {
    $result = ['moved' => [], 'hidden' => []];

    $attacked = aiBaseOrLocationAttackedThisTurn($pdo, $c['id'], $turn_number);

    $threatsKnown = 0;
    if (!$attacked) {
        $threatsKnown = aiKnownEnemiesInZones($pdo, $c['id'], aiOwnedAndAdjacentZoneIds($pdo, $c['id']));
    }
    if (!$attacked && $threatsKnown === 0) return $result;

    $baseZone = aiBaseZone($pdo, $c['id']);
    if ($baseZone === null) return $result;

    $classified = aiClassifyWorkers($pdo, $c['id']);
    $fighterIds = $classified['fighters'];
    if (empty($fighterIds)) return $result;

    $fighters = getWorkers($pdo, $fighterIds);
    if (empty($fighters)) return $result;

    $ranked = [];
    foreach ($fighters as $w) {
        $ranked[] = [
            'id' => (int)$w['id'],
            'zone_id' => (int)$w['zone_id'],
            'power_count' => aiWorkerPowerCount($pdo, (int)$w['id']),
        ];
    }
    usort($ranked, function($a, $b) {
        if ($b['power_count'] === $a['power_count']) return $a['id'] - $b['id'];
        return $b['power_count'] - $a['power_count'];
    });

    $defenceCount = max(0, (int)($budget['defence'] ?? 0));
    $topN = array_slice($ranked, 0, $defenceCount);
    $topIds = array_flip(array_column($topN, 'id'));

    foreach ($topN as $w) {
        if ($w['zone_id'] !== $baseZone) {
            moveWorker($pdo, $w['id'], $baseZone);
        }
        $result['moved'][] = $w['id'];
    }

    foreach ($ranked as $w) {
        if (isset($topIds[$w['id']])) continue;
        if ($w['zone_id'] === $baseZone) continue;
        activateWorker($pdo, $w['id'], 'hide', null);
        $result['hidden'][] = $w['id'];
    }

    return $result;
}
