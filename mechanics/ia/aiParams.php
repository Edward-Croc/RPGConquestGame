<?php

/**
 * Per-controller AI invariants and overrides. NULL columns fall back
 * to the global config table. Memoized per request via $GLOBALS so
 * writers can invalidate.
 */
function getAiControllerParams(PDO $pdo, int $controller_id): array {
    if (!isset($GLOBALS['__aiParamsCache'])) $GLOBALS['__aiParamsCache'] = [];
    $cache =& $GLOBALS['__aiParamsCache'];
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

/**
 * Drop the cached row so the next read re-fetches from DB.
 */
function aiParamsInvalidate(int $controller_id): void {
    if (!isset($GLOBALS['__aiParamsCache'])) return;
    unset($GLOBALS['__aiParamsCache'][$controller_id]);
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

/**
 * Runtime AI state. Defaults to 'passive' when row missing or NULL.
 */
function aiCurrentState(PDO $pdo, int $controller_id): string {
    $p = getAiControllerParams($pdo, $controller_id);
    $v = $p['current_state'] ?? null;
    if ($v === null || $v === '') return 'passive';
    return (string) $v;
}

function aiExpansionism(PDO $pdo, int $controller_id): ?string {
    $p = getAiControllerParams($pdo, $controller_id);
    $v = $p['expansionism'] ?? null;
    return ($v === null || $v === '') ? null : (string) $v;
}

function aiWorkerViolence(PDO $pdo, int $controller_id): ?string {
    $p = getAiControllerParams($pdo, $controller_id);
    $v = $p['worker_violence'] ?? null;
    return ($v === null || $v === '') ? null : (string) $v;
}

/**
 * Atomic UPSERT of current_state. Cross-DB via DBTYPE branch. Refreshes the cache.
 */
function setAiCurrentState(PDO $pdo, int $controller_id, string $newState): void {
    $prefix = $_SESSION['GAME_PREFIX'];
    $dbtype = strtolower((string)($_SESSION['DBTYPE'] ?? 'mysql'));
    try {
        if ($dbtype === 'postgres' || $dbtype === 'postgresql' || $dbtype === 'pgsql') {
            $sql = "INSERT INTO {$prefix}ai_controller_params (controller_id, current_state)
                    VALUES (:cid, :s)
                    ON CONFLICT (controller_id) DO UPDATE SET current_state = EXCLUDED.current_state";
        } else {
            $sql = "INSERT INTO {$prefix}ai_controller_params (controller_id, current_state)
                    VALUES (:cid, :s)
                    ON DUPLICATE KEY UPDATE current_state = VALUES(current_state)";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cid' => $controller_id, ':s' => $newState]);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPSERT failed: ".$e->getMessage()."<br />";
        return;
    }
    aiParamsInvalidate($controller_id);
}

/**
 * Per-pair AI disposition. Returns null if no row (D23 — absence is meaningful).
 */
function aiRelationDisposition(PDO $pdo, int $controller_id, int $target_controller_id): ?string {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT disposition FROM {$prefix}ai_controller_relations
             WHERE controller_id = :cid AND target_controller_id = :tcid LIMIT 1"
        );
        $stmt->execute([':cid' => $controller_id, ':tcid' => $target_controller_id]);
        $val = $stmt->fetchColumn();
    } catch (PDOException $e) {
        return null;
    }
    if ($val === false || $val === null || $val === '') return null;
    return (string) $val;
}
