<?php

/**
 * Mark a queued end-turn location attack as failed and write an
 * attacker-only log entry. Used by both the cascade-destroyed branch
 * in locationAttackMechanic() and the cancel-on-move path in moveBase().
 *
 * @param PDO $pdo : database connection
 * @param array $queue_row : controller_location_attacks row (needs id, location_name, attacker_controller_id)
 * @param int $turn_number : current turn number
 * @param string $reason : 'destroyed' | 'moved'
 * @return bool : true on success, false on DB failure
 */
function failQueuedLocationAttack(PDO $pdo, array $queue_row, int $turn_number, string $reason): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with queue_row_id : ' . $queue_row['id'], ['queue_row' => $queue_row, 'turn_number' => $turn_number, 'reason' => $reason], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    $textKey = $reason === 'moved' ? 'textLocationAttackMoved' : 'textLocationAttackDestroyed';
    $attackerText = sprintf((string)getConfig($pdo, $textKey), $queue_row['location_name']);

    try {
        $u = $pdo->prepare("UPDATE {$prefix}controller_location_attacks
            SET success = :success, resolved_turn = :turn
            WHERE id = :id");
        $false = false;
        $u->bindParam(':success', $false, PDO::PARAM_BOOL);
        $u->bindParam(':turn', $turn_number, PDO::PARAM_INT);
        $u->bindParam(':id', $queue_row['id'], PDO::PARAM_INT);
        $u->execute();
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'UPDATE controller_location_attacks Failed: ' . $e->getMessage(), ['queue_row' => $queue_row, 'turn_number' => $turn_number, 'reason' => $reason], 'error');
        return false;
    }

    if (!logLocationAttack(
        $pdo,
        (string) $queue_row['location_name'],
        $turn_number,
        false,
        (int) $queue_row['attacker_controller_id'],
        null,
        $attackerText
    )) {
        game_error_log(__FUNCTION__, 'logLocationAttack failed', ['queue_row' => $queue_row, 'turn_number' => $turn_number, 'reason' => $reason, 'attackerText' => $attackerText], 'error');
        return false;
    }

    return true;
}

/**
 * Resolve every queued location attack for the given turn: fetch the
 * queue, compute attacker/defender values, apply effects, and update
 * the queue row with the outcome. Skips work when locationAttackMode
 * config is not 'endTurn'.
 *
 * @param PDO $pdo : database connection
 * @param int $turn_number : current turn number
 * @return bool : true when the loop completed (or mode was skipped), false on DB failure
 */
function locationAttackMechanic(PDO $pdo, int $turn_number): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with turn_number : ' . $turn_number, [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    $mode = getConfig($pdo, 'locationAttackMode');

    echo "<div><h3>locationAttackMechanic : mode '".htmlspecialchars((string)$mode)."'</h3>" ;
    if ($mode === 'agent_attack_defence') {
        $agentResult = resolveAgentLocationCombat($pdo, $turn_number);
        echo '<p> locationAttackMechanic : DONE </p> </div>';
        return $agentResult;
    }
    if (!in_array($mode, ['endTurn'], true)) {
        echo " not supported, skipped</div>";
        return true;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, location_id, location_name, attacker_controller_id
            FROM {$prefix}controller_location_attacks
            WHERE queued_turn = :turn AND success IS NULL
            ORDER BY id ASC");
        $stmt->bindParam(':turn', $turn_number, PDO::PARAM_INT);
        $stmt->execute();
        $queued = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT queued attacks failed: ' . $e->getMessage(), ['turn_number' => $turn_number], 'error');
        return false;
    }

    foreach ($queued as $row) {
        $locStmt = $pdo->prepare("SELECT l.*, z.id AS zone_id, z.name AS zone_name
            FROM {$prefix}locations l
            JOIN {$prefix}zones z ON l.zone_id = z.id
            WHERE l.id = :id LIMIT 1");
        $locStmt->execute([':id' => $row['location_id']]);
        $location = $locStmt->fetch(PDO::FETCH_ASSOC);
        if (!$location) {
            failQueuedLocationAttack($pdo, $row, $turn_number, 'destroyed');
            continue;
        }

        $zone_id = $location['zone_id'];
        $resolvedAttack = calculatecontrollerAttack($pdo, $zone_id, $row['attacker_controller_id']);
        $resolvedDefence = calculateSecretLocationDefence($pdo, $zone_id, $row['location_id'], $location['controller_id']);

        $result = resolveControllerLocationAttackEffects(
            $pdo,
            $location,
            $row['attacker_controller_id'],
            $turn_number,
            $resolvedAttack,
            $resolvedDefence
        );

        try {
            $success = !empty($result['success']);
            $u = $pdo->prepare("UPDATE {$prefix}controller_location_attacks
                SET attack_val_resolved = :att, defence_val_resolved = :def,
                    success = :success, resolved_turn = :turn
                WHERE id = :id");
            $u->bindParam(':att', $resolvedAttack, PDO::PARAM_INT);
            $u->bindParam(':def', $resolvedDefence, PDO::PARAM_INT);
            $u->bindParam(':success', $success, PDO::PARAM_BOOL);
            $u->bindParam(':turn', $turn_number, PDO::PARAM_INT);
            $u->bindParam(':id', $row['id'], PDO::PARAM_INT);
            $u->execute();
        } catch (PDOException $e) {
            game_error_log(__FUNCTION__, 'UPDATE queue row failed: ' . $e->getMessage(), ['row' => $row, 'turn_number' => $turn_number, 'resolvedAttack' => $resolvedAttack, 'resolvedDefence' => $resolvedDefence, 'result' => $result], 'error');
            return false;
        }
    }

    game_error_log(__FUNCTION__, 'DONE with turn_number : ' . $turn_number, ['queued_count' => count($queued)], 'debug');

    echo '<p> locationAttackMechanic : DONE </p> </div>';
    return true;
}

/**
 * Return the current-turn location actions grouped by targeted location.
 *
 * Rows are ordered enquete_val DESC then worker_id ASC, the same initiative order
 * attackMechanic uses, so each group's lists come out already sorted. Workers killed
 * earlier in the turn are absent by construction: their action_choice no longer
 * matches the filter.
 *
 * @param PDO $pdo : database connection
 * @param int $turn_number : current turn number
 *
 * @return array|null : [location_id => ['attackers' => [...], 'defenders' => [...]]], NULL on DB failure
 */
function getAgentLocationActionGroups(PDO $pdo, int $turn_number): array|null
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with turn_number : ' . $turn_number, [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];

    try {
        $stmt = $pdo->prepare("SELECT
                wa.worker_id,
                wa.action_choice,
                wa.action_params,
                wa.zone_id,
                wa.controller_id,
                wa.enquete_val,
                wa.attack_val,
                wa.defence_val,
                CONCAT(w.firstname, ' ', w.lastname) AS worker_name
            FROM {$prefix}worker_actions wa
            JOIN {$prefix}workers w ON w.id = wa.worker_id
            WHERE wa.turn_number = :turn_number
              AND wa.action_choice IN ('attack_location', 'defend_location')
            ORDER BY wa.enquete_val DESC, wa.worker_id ASC");
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT location actions failed: ' . $e->getMessage(), ['turn_number' => $turn_number], 'error');
        return null;
    }

    $groups = [];
    foreach ($rows as $row) {
        $params = json_decode((string) $row['action_params'], true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($params['location_id'])) {
            game_error_log(__FUNCTION__, 'skipped a location action without a usable location_id', ['worker_id' => $row['worker_id'], 'action_params' => $row['action_params']], 'warning');
            continue;
        }
        $locationId = (int) $params['location_id'];
        if (!isset($groups[$locationId])) {
            $groups[$locationId] = ['attackers' => [], 'defenders' => []];
        }
        $side = $row['action_choice'] === 'attack_location' ? 'attackers' : 'defenders';
        $groups[$locationId][$side][] = $row;
    }

    game_error_log(__FUNCTION__, 'DONE with turn_number : ' . $turn_number, ['location_count' => count($groups)], 'debug');

    return $groups;
}

/**
 * Remove attackers whose secret master owns the targeted location.
 *
 * A double agent takes orders from the infiltrated faction but answers to the
 * secondary controller_worker link. Ordered against their real master's location,
 * they never arrive. Must run before the ladder: the capture path of
 * resolveWorkerCombat rewrites controller_worker and would erase the link.
 *
 * @param PDO $pdo : database connection
 * @param array $attackers : attacker rows for one location
 * @param array $location : hydrated location row (needs id, controller_id)
 * @param int $turn_number : current turn number
 *
 * @return array : the attackers who actually reach the location
 */
function excludeLocationCombatSaboteurs(PDO $pdo, array $attackers, array $location, int $turn_number): array
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with location_id : ' . $location['id'], ['attacker_count' => count($attackers)], 'debug');

    if (empty($location['controller_id'])) {
        return $attackers;
    }

    $prefix = $_SESSION['GAME_PREFIX'];
    $secondaryLiteral = ($_SESSION['DBTYPE'] == 'postgres') ? 'false' : '0';
    $unreachableArray = json_decode(getConfig($pdo, 'textLocationUnreachable'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        game_error_log(__FUNCTION__, 'JSON decoding error : ' . json_last_error_msg(), ['config_key' => 'textLocationUnreachable'], 'warning');
        $unreachableArray = array("Je n'ai jamais pu atteindre le lieu.");
    }
    $unreachableText = $unreachableArray[array_rand($unreachableArray)];

    $kept = [];

    foreach ($attackers as $attacker) {
        try {
            $stmt = $pdo->prepare("SELECT cw.controller_id
                FROM {$prefix}controller_worker cw
                WHERE cw.worker_id = :worker_id
                  AND cw.is_primary_controller = {$secondaryLiteral}");
            $stmt->bindParam(':worker_id', $attacker['worker_id'], PDO::PARAM_INT);
            $stmt->execute();
            $secondary = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            game_error_log(__FUNCTION__, 'SELECT secondary controller failed: ' . $e->getMessage(), ['worker_id' => $attacker['worker_id']], 'warning');
            $kept[] = $attacker;
            continue;
        }

        if (!empty($secondary) && (int) $secondary['controller_id'] === (int) $location['controller_id']) {
            echo sprintf('%s never reached %s <br />', htmlspecialchars((string) $attacker['worker_name']), htmlspecialchars((string) $location['name']));
            updateWorkerAction($pdo, (int) $attacker['worker_id'], $turn_number, null, ['life_report' => $unreachableText]);
            continue;
        }

        $kept[] = $attacker;
    }

    game_error_log(__FUNCTION__, 'DONE with location_id : ' . $location['id'], ['kept' => count($kept), 'excluded' => count($attackers) - count($kept)], 'debug');

    return $kept;
}

/**
 * Build the row resolveWorkerCombat() and logWorkerCombat() expect for one pair.
 *
 * Carries the ten keys resolveWorkerCombat reads, the five extra ones the logger
 * reads off the same array, plus the location so the combat log can be filtered by
 * place. Difference arithmetic mirrors getAttackerComparisons (attackMechanic.php:169).
 *
 * @param array $attacker : attacker row from getAgentLocationActionGroups()
 * @param array $defender : defender row from getAgentLocationActionGroups()
 * @param array $location : hydrated location row (needs id, name, zone_id, zone_name)
 * @param int $turn_number : current turn number
 *
 * @return array : the pair row
 */
function buildLocationCombatPair(array $attacker, array $defender, array $location, int $turn_number): array
{
    return [
        'attacker_id'             => (int) $attacker['worker_id'],
        'attacker_name'           => $attacker['worker_name'],
        'attacker_controller_id'  => (int) $attacker['controller_id'],
        'defender_id'             => (int) $defender['worker_id'],
        'defender_name'           => $defender['worker_name'],
        'defender_controller_id'  => (int) $defender['controller_id'],
        'zone_id'                 => (int) $location['zone_id'],
        'zone_name'               => $location['zone_name'],
        'turn_number'             => $turn_number,
        'attacker_attack_val'     => (int) $attacker['attack_val'],
        'attacker_defence_val'    => (int) $attacker['defence_val'],
        'defender_attack_val'     => (int) $defender['attack_val'],
        'defender_defence_val'    => (int) $defender['defence_val'],
        'attack_difference'       => (int) $attacker['attack_val'] - (int) $defender['defence_val'],
        'riposte_difference'      => (int) $defender['attack_val'] - (int) $attacker['defence_val'],
        'location_id'             => (int) $location['id'],
        'location_name'           => $location['name'],
    ];
}

/**
 * Return the combatants still active this turn, as a subset of the input rows.
 *
 * Rows are returned rather than a count so the caller keeps their controller_id
 * and enquete_val, which the spoils ranking needs without a second query.
 *
 * @param PDO $pdo : database connection
 * @param array $combatants : combatant rows from getAgentLocationActionGroups()
 * @param int $turn_number : current turn number
 *
 * @return array : the rows whose action_choice is not in INACTIVE_ACTIONS
 */
function getActiveLocationCombatants(PDO $pdo, array $combatants, int $turn_number): array
{
    if (empty($combatants)) {
        return [];
    }

    $prefix = $_SESSION['GAME_PREFIX'];
    // Cast every id so the interpolated list can only ever be integers.
    $idList = implode(',', array_map('intval', array_column($combatants, 'worker_id')));

    try {
        $stmt = $pdo->prepare("SELECT worker_id, action_choice
            FROM {$prefix}worker_actions
            WHERE turn_number = :turn_number AND worker_id IN ({$idList})");
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT survivor action_choice failed: ' . $e->getMessage(), ['turn_number' => $turn_number], 'warning');
        return [];
    }

    $activeIds = [];
    foreach ($rows as $row) {
        if (!in_array($row['action_choice'], INACTIVE_ACTIONS, true)) {
            $activeIds[(int) $row['worker_id']] = true;
        }
    }

    $active = [];
    foreach ($combatants as $combatant) {
        if (isset($activeIds[(int) $combatant['worker_id']])) {
            $active[] = $combatant;
        }
    }

    return $active;
}

/**
 * Resolve agent-versus-agent combat over every targeted location for the turn.
 *
 * One sequential ladder per location rather than a cartesian product: an attacker
 * keeps going while it kills, a defender holds while it survives, and an attacker
 * that fails without dying is spent. Each pair is settled by resolveWorkerCombat(),
 * whose return value drives the two cursors. Produces the capture verdict; applying
 * it to the location is step 5.D.
 *
 * @param PDO $pdo : database connection
 * @param int $turn_number : current turn number
 *
 * @return bool : true when every location was walked, false on DB failure
 */
function resolveAgentLocationCombat(PDO $pdo, int $turn_number): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with turn_number : ' . $turn_number, [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];

    $groups = getAgentLocationActionGroups($pdo, $turn_number);
    if ($groups === null) {
        return false;
    }

    foreach ($groups as $locationId => $group) {
        try {
            $locStmt = $pdo->prepare("SELECT l.*, z.id AS zone_id, z.name AS zone_name
                FROM {$prefix}locations l
                JOIN {$prefix}zones z ON l.zone_id = z.id
                WHERE l.id = :id LIMIT 1");
            $locStmt->execute([':id' => $locationId]);
            $location = $locStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            game_error_log(__FUNCTION__, 'SELECT location failed: ' . $e->getMessage(), ['location_id' => $locationId], 'error');
            return false;
        }
        if (!$location) {
            game_error_log(__FUNCTION__, 'targeted location no longer exists, skipped', ['location_id' => $locationId], 'warning');
            continue;
        }

        // Logged, never excluded : a combatant away from the place still fights.
        foreach (array_merge($group['attackers'], $group['defenders']) as $participant) {
            if ((int) $participant['zone_id'] !== (int) $location['zone_id']) {
                game_error_log(__FUNCTION__, 'combatant is not in the location zone', ['worker_id' => $participant['worker_id'], 'worker_zone_id' => $participant['zone_id'], 'location_id' => $locationId, 'location_zone_id' => $location['zone_id']], 'warning');
            }
        }

        $attackers = excludeLocationCombatSaboteurs($pdo, $group['attackers'], $location, $turn_number);
        $defenders = $group['defenders'];

        echo sprintf(
            '<p>%s : %d attaquant(s) contre %d défenseur(s)</p>',
            htmlspecialchars((string) $location['name']),
            count($attackers),
            count($defenders)
        );

        $attackerCount = count($attackers);
        $defenderCount = count($defenders);
        $a = 0;
        $d = 0;
        while ($a < $attackerCount && $d < $defenderCount) {
            $pair = buildLocationCombatPair($attackers[$a], $defenders[$d], $location, $turn_number);
            $outcome = resolveWorkerCombat($pdo, $pair, ['turncounter' => $turn_number]);

            if (!empty($outcome['kill'])) {
                $d++;
                continue;
            }
            // Riposte death and plain failure both spend the attacker.
            $a++;
        }

        $aliveAttackerRows = getActiveLocationCombatants($pdo, $attackers, $turn_number);
        $aliveAttackers = count($aliveAttackerRows);
        $aliveDefenders = count(getActiveLocationCombatants($pdo, $defenders, $turn_number));
        // A missing key must not collapse the threshold to zero and hand over the location.
        $configured = getConfig($pdo, 'locationOverwhelmValue');
        $value = is_numeric($configured) ? (int) $configured : 2;
        $threshold = getLocationOverwhelmMode($pdo) === 'morethan'
            ? $aliveDefenders + $value
            : $aliveDefenders * $value;

        // Strict comparison also settles nobody-versus-nobody : the location holds.
        $falls = $aliveAttackers > $threshold;

        game_error_log(__FUNCTION__, 'verdict for location_id ' . $locationId . ' : ' . ($falls ? 'falls' : 'holds'), ['alive_attackers' => $aliveAttackers, 'alive_defenders' => $aliveDefenders, 'mode' => getLocationOverwhelmMode($pdo), 'value' => getConfig($pdo, 'locationOverwhelmValue')], 'debug');

        echo sprintf(
            '<p data-location-verdict="%s" data-location-id="%d" data-alive-attackers="%d" data-alive-defenders="%d">%s : %d attaquant(s) vivant(s) contre %d défenseur(s) — %s</p>',
            $falls ? 'falls' : 'holds',
            $locationId,
            $aliveAttackers,
            $aliveDefenders,
            htmlspecialchars((string) $location['name']),
            $aliveAttackers,
            $aliveDefenders,
            $falls ? 'le lieu tombe' : 'le lieu tient'
        );
    }

    game_error_log(__FUNCTION__, 'DONE with turn_number : ' . $turn_number, ['location_count' => count($groups)], 'debug');

    return true;
}
