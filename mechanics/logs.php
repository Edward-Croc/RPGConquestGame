<?php

// Include-only page — block direct HTTP access.
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit();
}

// Booleans bind PARAM_BOOL, LIMIT is concatenated, and no placeholder repeats.

if (!defined('WORKER_COMBAT_OUTCOMES_ALLOWED')) {
    define('WORKER_COMBAT_OUTCOMES_ALLOWED', ['miss', 'kill', 'capture', 'riposte_kill', 'mutual_kill']);
}

/**
 * Map resolveWorkerCombat()'s three result flags to an outcome string.
 *
 * Reachable tuples : (F,F,F) miss, (T,F,F) kill, (T,T,F) capture,
 * (F,F,T) riposte_kill. capture always implies kill.
 *
 * mutual_kill cannot occur : mechanics/attackMechanic.php gates the riposte on
 * $survived, which the kill branch clears beforehand, so kill and riposte_kill
 * are mutually exclusive. The tuple is mapped anyway so this stays correct if
 * that gate is ever lifted.
 *
 * @param bool $kill : defender died this round (dead or captured)
 * @param bool $capture : defender captured (subset of kill)
 * @param bool $riposte_kill : attacker died via riposte
 *
 * @return string : a member of WORKER_COMBAT_OUTCOMES_ALLOWED
 */
function resolveWorkerCombatOutcome(bool $kill, bool $capture, bool $riposte_kill): string
{
    if ($kill && $riposte_kill) {
        return 'mutual_kill';
    }
    // capture is tested before kill because it implies it
    if ($capture) {
        return 'capture';
    }
    if ($kill) {
        return 'kill';
    }
    if ($riposte_kill) {
        return 'riposte_kill';
    }
    return 'miss';
}

/**
 * Open a worker_combat_logs row for one attacker-defender pair, outcome left
 * NULL. Call logWorkerCombatUpdate() with the returned id once the round
 * resolves; a row left at NULL marks a combat that started and never finished.
 *
 * Reuses an existing unresolved row for the same (turn, attacker, defender) and
 * bumps its attempt counter, so an end-of-turn replay does not duplicate it. A
 * pair that already resolved gets a fresh row, because a second resolution is a
 * real event; attempt keeps counting across those rows rather than restarting.
 *
 * Silent on error : a logging failure must never stop a combat from resolving.
 *
 * @param PDO $pdo : database connection
 * @param array $defender : row from getAttackerComparisons() — needs attacker_id,
 *   attacker_name, attacker_controller_id, defender_id, defender_name,
 *   defender_controller_id, zone_id, turn_number, attack_difference,
 *   riposte_difference. Optional location_id / location_name are set by the
 *   agent_attack_defence location combats and stay NULL for ordinary duels.
 *
 * @return int|null : the row id, or NULL when the row could not be written
 */
function logWorkerCombat(PDO $pdo, array $defender): int|null
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with attacker_id : ' . ($defender['attacker_id'] ?? 'null'), ['defender_id' => $defender['defender_id'] ?? null, 'turn_number' => $defender['turn_number'] ?? null], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];

    // An ambient rollback would erase the row before phase two can close it.
    if ($pdo->inTransaction()) {
        game_error_log(__FUNCTION__, 'called inside an open transaction — the log row is not crash-durable', ['attacker_id' => $defender['attacker_id'] ?? null], 'warning');
    }

    $turn = (int) ($defender['turn_number'] ?? 0);
    $attackerId = (int) ($defender['attacker_id'] ?? 0);
    $defenderId = (int) ($defender['defender_id'] ?? 0);
    if ($attackerId <= 0 || $defenderId <= 0) {
        game_error_log(__FUNCTION__, 'refused a pair without both worker ids', ['attacker_id' => $attackerId, 'defender_id' => $defenderId, 'turn' => $turn], 'warning');
        return null;
    }

    $attempt = 1;
    try {
        $lookup = $pdo->prepare("SELECT id, outcome, attempt
            FROM {$prefix}worker_combat_logs
            WHERE turn = :turn AND attacker_id = :attacker_id AND defender_id = :defender_id
            ORDER BY id DESC");
        $lookup->execute([
            ':turn'        => $turn,
            ':attacker_id' => $attackerId,
            ':defender_id' => $defenderId,
        ]);
        $previous = $lookup->fetch(PDO::FETCH_ASSOC);

        if (!empty($previous)) {
            $attempt = (int) $previous['attempt'] + 1;
            // An unresolved row means a previous attempt died mid-combat : reuse it.
            if ($previous['outcome'] === null) {
                $bump = $pdo->prepare("UPDATE {$prefix}worker_combat_logs SET attempt = :attempt WHERE id = :id");
                $bump->execute([':attempt' => $attempt, ':id' => (int) $previous['id']]);
                return (int) $previous['id'];
            }
        }
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'replay lookup on worker_combat_logs failed : ' . $e->getMessage(), ['attacker_id' => $attackerId, 'defender_id' => $defenderId, 'turn' => $turn], 'warning');
        return null;
    }

    // A NULL foreign key cast to 0 would violate the constraint and lose the row.
    $zoneId = !empty($defender['zone_id']) ? (int) $defender['zone_id'] : null;
    $attackerControllerId = !empty($defender['attacker_controller_id']) ? (int) $defender['attacker_controller_id'] : null;
    $defenderControllerId = !empty($defender['defender_controller_id']) ? (int) $defender['defender_controller_id'] : null;
    // Only location combats carry these; an ordinary duel leaves both NULL.
    $locationId = !empty($defender['location_id']) ? (int) $defender['location_id'] : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO {$prefix}worker_combat_logs
            (turn, zone_id, zone_name,
             attacker_id, attacker_name, attacker_controller_id,
             defender_id, defender_name, defender_controller_id,
             attacker_attack_val, attacker_defence_val,
             defender_attack_val, defender_defence_val,
             attack_difference, riposte_difference,
             location_id, location_name, attempt)
            VALUES (:turn, :zone_id, :zone_name,
             :attacker_id, :attacker_name, :attacker_controller_id,
             :defender_id, :defender_name, :defender_controller_id,
             :attacker_attack_val, :attacker_defence_val,
             :defender_attack_val, :defender_defence_val,
             :attack_difference, :riposte_difference,
             :location_id, :location_name, :attempt)");
        $stmt->bindValue(':turn', $turn, PDO::PARAM_INT);
        $stmt->bindValue(':zone_id', $zoneId, $zoneId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':zone_name', $defender['zone_name'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':attacker_id', $attackerId, PDO::PARAM_INT);
        $stmt->bindValue(':attacker_name', $defender['attacker_name'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':attacker_controller_id', $attackerControllerId, $attackerControllerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':defender_id', $defenderId, PDO::PARAM_INT);
        $stmt->bindValue(':defender_name', $defender['defender_name'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':defender_controller_id', $defenderControllerId, $defenderControllerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':attacker_attack_val', (int) ($defender['attacker_attack_val'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':attacker_defence_val', (int) ($defender['attacker_defence_val'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':defender_attack_val', (int) ($defender['defender_attack_val'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':defender_defence_val', (int) ($defender['defender_defence_val'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':attack_difference', (int) ($defender['attack_difference'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':riposte_difference', (int) ($defender['riposte_difference'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':location_id', $locationId, $locationId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':location_name', $defender['location_name'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':attempt', $attempt, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'INSERT worker_combat_logs failed : ' . $e->getMessage(), ['attacker_id' => $attackerId, 'defender_id' => $defenderId, 'turn' => $turn], 'warning');
        return null;
    }

    $combatLogId = (int) $pdo->lastInsertId();
    if ($combatLogId <= 0) {
        game_error_log(__FUNCTION__, 'INSERT worker_combat_logs returned no id', ['attacker_id' => $attackerId, 'defender_id' => $defenderId, 'turn' => $turn], 'warning');
        return null;
    }

    return $combatLogId;
}

/**
 * Close a worker_combat_logs row opened by logWorkerCombat().
 *
 * No-ops on a NULL id so the caller needs no guard. The statement only touches a
 * row whose outcome is still NULL, so a replay cannot overwrite a resolved one.
 *
 * Silent on error : by this point the combat has already mutated game state, so
 * a throw would leave the turn wedged with its side-effects applied.
 *
 * @param PDO $pdo : database connection
 * @param int|null $combatLogId : id returned by logWorkerCombat()
 * @param string $outcome : a member of WORKER_COMBAT_OUTCOMES_ALLOWED
 *
 * @return bool : true when a row was updated
 */
function logWorkerCombatUpdate(PDO $pdo, int|null $combatLogId, string $outcome): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with combatLogId : ' . var_export($combatLogId, true), ['outcome' => $outcome], 'debug');

    if ($combatLogId === null || $combatLogId <= 0) {
        game_error_log(__FUNCTION__, 'no combat log row to close', ['outcome' => $outcome], 'warning');
        return false;
    }
    if (!in_array($outcome, WORKER_COMBAT_OUTCOMES_ALLOWED, true)) {
        game_error_log(__FUNCTION__, 'refused an outcome outside the whitelist : ' . $outcome, ['combatLogId' => $combatLogId], 'error');
        return false;
    }

    $prefix = $_SESSION['GAME_PREFIX'];

    if ($pdo->inTransaction()) {
        game_error_log(__FUNCTION__, 'called inside an open transaction — the outcome is not crash-durable', ['combatLogId' => $combatLogId], 'warning');
    }

    try {
        $stmt = $pdo->prepare("UPDATE {$prefix}worker_combat_logs
            SET outcome = :outcome
            WHERE id = :id AND outcome IS NULL");
        $stmt->execute([':outcome' => $outcome, ':id' => $combatLogId]);
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'UPDATE worker_combat_logs failed : ' . $e->getMessage(), ['combatLogId' => $combatLogId, 'outcome' => $outcome], 'warning');
        return false;
    }

    if ($stmt->rowCount() === 0) {
        game_error_log(__FUNCTION__, 'row was already resolved, outcome left untouched', ['combatLogId' => $combatLogId, 'outcome' => $outcome], 'debug');
        return false;
    }

    return true;
}

/**
 * Read worker_combat_logs rows with both controller names resolved.
 *
 * @param PDO $pdo : database connection
 * @param array $filters : any of turn, attacker_id, defender_id, worker_id
 *   (either side), controller_id (either side), zone_id, location_id, outcome,
 *   unresolved (bool), limit. A NULL value means no filter on that key; turn 0
 *   is a real turn, so only NULL disables the turn filter.
 * @param string $orderBy : turn | created_at | id — anything else falls back to created_at
 * @param string $direction : asc | desc, anything else falls back to desc
 *
 * @return array : rows, empty on failure
 */
function getWorkerCombatLogs(PDO $pdo, array $filters = [], string $orderBy = 'created_at', string $direction = 'desc'): array
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with orderBy : ' . $orderBy, ['filters' => $filters, 'direction' => $direction], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];

    $where = [];
    $params = [];
    if (isset($filters['turn']) && $filters['turn'] !== null) {
        $where[] = 'wcl.turn = :turn';
        $params[':turn'] = (int) $filters['turn'];
    }
    if (!empty($filters['attacker_id'])) {
        $where[] = 'wcl.attacker_id = :attacker_id';
        $params[':attacker_id'] = (int) $filters['attacker_id'];
    }
    if (!empty($filters['defender_id'])) {
        $where[] = 'wcl.defender_id = :defender_id';
        $params[':defender_id'] = (int) $filters['defender_id'];
    }
    if (!empty($filters['worker_id'])) {
        $where[] = '(wcl.attacker_id = :worker_id_a OR wcl.defender_id = :worker_id_b)';
        $params[':worker_id_a'] = (int) $filters['worker_id'];
        $params[':worker_id_b'] = (int) $filters['worker_id'];
    }
    if (!empty($filters['controller_id'])) {
        $where[] = '(wcl.attacker_controller_id = :controller_id_a OR wcl.defender_controller_id = :controller_id_b)';
        $params[':controller_id_a'] = (int) $filters['controller_id'];
        $params[':controller_id_b'] = (int) $filters['controller_id'];
    }
    if (!empty($filters['zone_id'])) {
        $where[] = 'wcl.zone_id = :zone_id';
        $params[':zone_id'] = (int) $filters['zone_id'];
    }
    if (!empty($filters['location_id'])) {
        $where[] = 'wcl.location_id = :location_id';
        $params[':location_id'] = (int) $filters['location_id'];
    }
    if (!empty($filters['outcome'])) {
        $where[] = 'wcl.outcome = :outcome';
        $params[':outcome'] = (string) $filters['outcome'];
    }
    if (!empty($filters['unresolved'])) {
        $where[] = 'wcl.outcome IS NULL';
    }

    $orderColumn = match ($orderBy) {
        'turn' => 'wcl.turn',
        'id' => 'wcl.id',
        default => 'wcl.created_at',
    };
    $orderDirection = (strtolower(trim($direction)) === 'asc') ? 'ASC' : 'DESC';

    $sql = "SELECT wcl.*,
            CONCAT(ac.firstname, ' ', ac.lastname) AS attacker_controller_name,
            CONCAT(dc.firstname, ' ', dc.lastname) AS defender_controller_name
        FROM {$prefix}worker_combat_logs wcl
        LEFT JOIN {$prefix}controllers ac ON ac.id = wcl.attacker_controller_id
        LEFT JOIN {$prefix}controllers dc ON dc.id = wcl.defender_controller_id";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= sprintf(' ORDER BY %s %s, wcl.id DESC', $orderColumn, $orderDirection);
    if (!empty($filters['limit'])) {
        $sql .= ' LIMIT ' . (int) $filters['limit'];
    }

    try {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT worker_combat_logs failed : ' . $e->getMessage(), ['sql' => $sql, 'filters' => $filters], 'error');
        return [];
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Distinct filter option sets for the combat log admin page.
 *
 * Options come from worker_combat_logs itself, not from workers / locations :
 * those rows get hard-deleted, and an option list built on the live tables would
 * drop exactly the historical rows an admin wants to inspect.
 *
 * @param PDO $pdo : database connection
 *
 * @return array : keys 'workers' (worker_id + label), 'turns' (int list, desc),
 *   'locations' (location_id + label), each empty on failure
 */
function getWorkerCombatLogFilterOptions(PDO $pdo): array
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START', [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    $options = ['workers' => [], 'turns' => [], 'locations' => []];

    try {
        $turnStmt = $pdo->query("SELECT DISTINCT turn FROM {$prefix}worker_combat_logs ORDER BY turn DESC");
        $options['turns'] = array_map('intval', $turnStmt->fetchAll(PDO::FETCH_COLUMN));

        $workerStmt = $pdo->query("SELECT attacker_id AS worker_id, attacker_name AS worker_name
                FROM {$prefix}worker_combat_logs
            UNION
            SELECT defender_id AS worker_id, defender_name AS worker_name
                FROM {$prefix}worker_combat_logs");
        foreach ($workerStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $workerId = (int) $row['worker_id'];
            $options['workers'][$workerId] = [
                'worker_id' => $workerId,
                'label'     => empty($row['worker_name'])
                    ? sprintf('Agent supprimé (#%d)', $workerId)
                    : sprintf('%s (#%d)', $row['worker_name'], $workerId),
            ];
        }

        $locationStmt = $pdo->query("SELECT DISTINCT location_id, location_name
            FROM {$prefix}worker_combat_logs
            WHERE location_id IS NOT NULL");
        foreach ($locationStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $locationId = (int) $row['location_id'];
            $options['locations'][$locationId] = [
                'location_id' => $locationId,
                'label'       => empty($row['location_name'])
                    ? sprintf('Lieu supprimé (#%d)', $locationId)
                    : sprintf('%s (#%d)', $row['location_name'], $locationId),
            ];
        }
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT worker_combat_logs filter options failed : ' . $e->getMessage(), [], 'error');
        return ['workers' => [], 'turns' => [], 'locations' => []];
    }

    usort($options['workers'], fn ($a, $b) => strcasecmp($a['label'], $b['label']));
    usort($options['locations'], fn ($a, $b) => strcasecmp($a['label'], $b['label']));

    return $options;
}

/**
 * Append a row to location_attack_logs.
 *
 * Returns a bool rather than deciding for the caller, because the two call sites
 * react to a failed log differently.
 *
 * @param PDO $pdo : database connection
 * @param array $data : location_name, turn and success are required;
 *   attacker_id and target_controller_id (both default NULL — an empty attacker_id
 *   means nobody is credited), attack_val / defence_val (default 0),
 *   target_result_text / attacker_result_text (default '') are optional
 *
 * @return bool : true when the row was written
 */
function logLocationAttack(PDO $pdo, array $data): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with location_name : ' . ($data['location_name'] ?? 'null'), ['attacker_id' => $data['attacker_id'] ?? null, 'turn' => $data['turn'] ?? null, 'success' => $data['success'] ?? null], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];

    $targetControllerId = !empty($data['target_controller_id']) ? (int) $data['target_controller_id'] : null;
    // NULL means nobody is credited; a 0 would violate the foreign key instead.
    $attackerId = !empty($data['attacker_id']) ? (int) $data['attacker_id'] : null;
    $success = !empty($data['success']);

    try {
        $stmt = $pdo->prepare("INSERT INTO {$prefix}location_attack_logs
            (target_controller_id, location_name, attacker_id, attack_val, defence_val,
             turn, success, target_result_text, attacker_result_text)
            VALUES (:target_controller_id, :location_name, :attacker_id, :attack_val, :defence_val,
             :turn, :success, :target_result_text, :attacker_result_text)");
        $stmt->bindValue(':target_controller_id', $targetControllerId, $targetControllerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':location_name', (string) ($data['location_name'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':attacker_id', $attackerId, $attackerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':attack_val', (int) ($data['attack_val'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':defence_val', (int) ($data['defence_val'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':turn', (int) ($data['turn'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':success', $success, PDO::PARAM_BOOL);
        $stmt->bindValue(':target_result_text', (string) ($data['target_result_text'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':attacker_result_text', (string) ($data['attacker_result_text'] ?? ''), PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'INSERT location_attack_logs failed : ' . $e->getMessage(), ['data' => $data], 'error');
        return false;
    }

    return true;
}

/**
 * Read location_attack_logs rows with the attacking controller name resolved.
 *
 * @param PDO $pdo : database connection
 * @param array $filters : any of target_controller_id, attacker_id,
 *   controller_id (either side), turn, success (bool), location_name, limit
 * @param string $orderBy : turn | created_at | id — anything else falls back to created_at
 * @param string $direction : asc | desc, anything else falls back to desc
 *
 * @return array : rows, empty on failure
 */
function getLocationAttackLogs(PDO $pdo, array $filters = [], string $orderBy = 'created_at', string $direction = 'desc'): array
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with orderBy : ' . $orderBy, ['filters' => $filters, 'direction' => $direction], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];

    $where = [];
    $params = [];
    if (!empty($filters['target_controller_id'])) {
        $where[] = 'lal.target_controller_id = :target_controller_id';
        $params[':target_controller_id'] = (int) $filters['target_controller_id'];
    }
    if (!empty($filters['attacker_id'])) {
        $where[] = 'lal.attacker_id = :attacker_id';
        $params[':attacker_id'] = (int) $filters['attacker_id'];
    }
    if (!empty($filters['controller_id'])) {
        $where[] = '(lal.attacker_id = :controller_id_a OR lal.target_controller_id = :controller_id_b)';
        $params[':controller_id_a'] = (int) $filters['controller_id'];
        $params[':controller_id_b'] = (int) $filters['controller_id'];
    }
    if (isset($filters['turn']) && $filters['turn'] !== null) {
        $where[] = 'lal.turn = :turn';
        $params[':turn'] = (int) $filters['turn'];
    }
    if (!empty($filters['location_name'])) {
        $where[] = 'lal.location_name = :location_name';
        $params[':location_name'] = (string) $filters['location_name'];
    }
    if (isset($filters['success'])) {
        $where[] = 'lal.success = :success';
    }

    $orderColumn = match ($orderBy) {
        'turn' => 'lal.turn',
        'id' => 'lal.id',
        default => 'lal.created_at',
    };
    $orderDirection = (strtolower(trim($direction)) === 'asc') ? 'ASC' : 'DESC';

    $sql = "SELECT lal.*,
            CONCAT(c.firstname, ' ', c.lastname) AS attacker_name
        FROM {$prefix}location_attack_logs lal
        LEFT JOIN {$prefix}controllers c ON c.id = lal.attacker_id";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= sprintf(' ORDER BY %s %s, lal.id DESC', $orderColumn, $orderDirection);
    if (!empty($filters['limit'])) {
        $sql .= ' LIMIT ' . (int) $filters['limit'];
    }

    try {
        $stmt = $pdo->prepare($sql);
        // success is bound apart so a PHP bool never reaches the array form.
        if (isset($filters['success'])) {
            $stmt->bindValue(':success', !empty($filters['success']), PDO::PARAM_BOOL);
        }
        foreach ($params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT location_attack_logs failed : ' . $e->getMessage(), ['sql' => $sql, 'filters' => $filters], 'error');
        return [];
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
