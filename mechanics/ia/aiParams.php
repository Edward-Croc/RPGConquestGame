<?php

/**
 * Per-controller AI invariants and overrides. NULL columns fall back
 * to the global config table. Memoized per request.
 */
function getAiControllerParams(PDO $pdo, int $controller_id): array {
    static $cache = [];
    if (array_key_exists($controller_id, $cache)) return $cache[$controller_id];

    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM {$prefix}ai_controller_params WHERE controller_id = :cid LIMIT 1"
        );
        $stmt->execute([':cid' => $controller_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return $cache[$controller_id] = [];
    }
    if (!$row) return $cache[$controller_id] = [];

    foreach (['target_zone_ids', 'destroy_location_ids', 'repair_location_ids', 'ai_budget_by_state', 'objectives_json'] as $jsonCol) {
        if (!empty($row[$jsonCol]) && is_string($row[$jsonCol])) {
            $decoded = json_decode($row[$jsonCol], true);
            $row[$jsonCol] = is_array($decoded) ? $decoded : [];
        } else {
            $row[$jsonCol] = [];
        }
    }
    return $cache[$controller_id] = $row;
}

function aiTargetZoneIds(PDO $pdo, int $controller_id): array {
    $p = getAiControllerParams($pdo, $controller_id);
    return $p['target_zone_ids'] ?? [];
}

function aiDestroyLocationIds(PDO $pdo, int $controller_id): array {
    $p = getAiControllerParams($pdo, $controller_id);
    return $p['destroy_location_ids'] ?? [];
}

function aiRepairLocationIds(PDO $pdo, int $controller_id): array {
    $p = getAiControllerParams($pdo, $controller_id);
    return $p['repair_location_ids'] ?? [];
}

/**
 * Per-controller override of aiBudgetByState. Returns the raw JSON
 * string so callers can slot it into existing aiBudget consumers,
 * or null when the controller has no override (use global config).
 */
function aiBudgetByStateOverride(PDO $pdo, int $controller_id): ?string {
    $p = getAiControllerParams($pdo, $controller_id);
    if (empty($p['ai_budget_by_state'])) return null;
    return json_encode($p['ai_budget_by_state']);
}
