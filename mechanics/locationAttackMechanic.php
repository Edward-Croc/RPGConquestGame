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

        // Defenders alone are not an assault : no verdict, no log, no owner report.
        if (empty($attackers)) {
            game_error_log(__FUNCTION__, 'no attacker reached location_id ' . $locationId, ['defenders' => count($defenders)], 'debug');
            continue;
        }

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

        $participants = array_merge($group['attackers'], $group['defenders']);
        if (!resolveAgentLocationOutcome($pdo, $location, $attackers, $participants, $aliveAttackerRows, $aliveDefenders, $falls, $turn_number)) {
            return false;
        }
    }

    game_error_log(__FUNCTION__, 'DONE with turn_number : ' . $turn_number, ['location_count' => count($groups)], 'debug');

    return true;
}

/**
 * Join French list items as "a, b et c".
 *
 * @param array $items : already-rendered strings
 *
 * @return string : the joined list, empty when there is nothing to join
 */
function joinFrenchList(array $items): string
{
    $items = array_values(array_filter($items, 'strlen'));
    if (empty($items)) {
        return '';
    }
    if (count($items) === 1) {
        return $items[0];
    }
    $last = array_pop($items);
    return implode(', ', $items) . ' et ' . $last;
}

/**
 * Return where a controller would stash plundered artefacts, or NULL when it has
 * nowhere to put them.
 *
 * Runs the same query captureLocationsArtefacts() uses to pick its destination, so
 * eligibility and destination can never disagree. A controller whose only stronghold
 * has been ruined (can_be_destroyed swapped to 0) owns no valid destination.
 *
 * @param PDO $pdo : database connection
 * @param int $controller_id : candidate controller
 *
 * @return int|null : destination location id, NULL when the controller is not eligible
 */
function findLocationSpoilsDestination(PDO $pdo, int $controller_id): int|null
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];

    try {
        $stmt = $pdo->prepare("SELECT id FROM {$prefix}locations
            WHERE controller_id = :controller_id AND can_be_destroyed = TRUE
            ORDER BY discovery_diff DESC, id ASC LIMIT 1");
        $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
        $stmt->execute();
        $destination = $stmt->fetchColumn();
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT spoils destination failed: ' . $e->getMessage(), ['controller_id' => $controller_id], 'warning');
        return null;
    }

    return ($destination === false) ? null : (int) $destination;
}

/**
 * Pick which attacking controller carries off the spoils.
 *
 * Ranked by surviving agents, then by the best enquete_val among them, then by
 * controller id so the order never depends on the engine. The ranking is walked
 * downwards until a controller with somewhere to stash the loot is found.
 *
 * @param PDO $pdo : database connection
 * @param array $aliveAttackers : surviving attacker rows
 *
 * @return int|null : the winning controller id, NULL when none is eligible
 */
function rankLocationSpoilsControllers(PDO $pdo, array $aliveAttackers): int|null
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with alive_attackers : ' . count($aliveAttackers), [], 'debug');

    $byController = [];
    foreach ($aliveAttackers as $attacker) {
        $controllerId = (int) $attacker['controller_id'];
        $enquete = (int) $attacker['enquete_val'];
        if (!isset($byController[$controllerId])) {
            $byController[$controllerId] = ['controller_id' => $controllerId, 'survivors' => 0, 'best_enquete' => $enquete];
        }
        $byController[$controllerId]['survivors']++;
        $byController[$controllerId]['best_enquete'] = max($byController[$controllerId]['best_enquete'], $enquete);
    }

    $ranked = array_values($byController);
    usort($ranked, function ($a, $b) {
        return [$b['survivors'], $b['best_enquete'], $a['controller_id']]
           <=> [$a['survivors'], $a['best_enquete'], $b['controller_id']];
    });

    foreach ($ranked as $candidate) {
        if (findLocationSpoilsDestination($pdo, $candidate['controller_id']) !== null) {
            return $candidate['controller_id'];
        }
    }

    return null;
}

/**
 * Build the clause naming the assailants in the owner's report.
 *
 * Under 'networks' the attacking network ids are listed. Under 'agents' each agent is
 * named, and a network is attributed only for the agents the owner has already
 * identified in controllers_known_enemies — the investigation system stays in charge
 * of what the defender learns. An unowned location has nobody to identify anyone, so
 * it falls back to 'networks'.
 *
 * @param PDO $pdo : database connection
 * @param array $allAttackers : every attacker of this location, dead ones included
 * @param array $location : hydrated location row (needs controller_id)
 *
 * @return string : the clause, ready for the %2$s of the report template
 */
function buildLocationAttackerClause(PDO $pdo, array $allAttackers, array $location): string
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with location_id : ' . ($location['id'] ?? 'null'), ['attacker_count' => count($allAttackers)], 'debug');

    if (empty($allAttackers)) {
        return 'des assaillants inconnus';
    }

    $ownerId = !empty($location['controller_id']) ? (int) $location['controller_id'] : null;
    $mode = getLocationAttackCreditMode($pdo);

    if ($mode === 'networks' || $ownerId === null) {
        $networks = array_values(array_unique(array_map('intval', array_column($allAttackers, 'controller_id'))));
        sort($networks);
        $label = (count($networks) === 1) ? 'les agents du réseau ' : 'les agents des réseaux ';
        return $label . joinFrenchList(array_map('strval', $networks));
    }

    $prefix = $_SESSION['GAME_PREFIX'];
    $idList = implode(',', array_map('intval', array_column($allAttackers, 'worker_id')));
    $known = [];
    try {
        $stmt = $pdo->prepare("SELECT discovered_worker_id, discovered_controller_id, discovered_controller_name
            FROM {$prefix}controllers_known_enemies
            WHERE controller_id = :owner_id AND discovered_worker_id IN ({$idList})");
        $stmt->bindParam(':owner_id', $ownerId, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $known[(int) $row['discovered_worker_id']] = $row;
        }
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT controllers_known_enemies failed: ' . $e->getMessage(), ['owner_id' => $ownerId], 'warning');
    }

    $unidentified = [];
    $identified = [];
    foreach ($allAttackers as $attacker) {
        $name = (string) $attacker['worker_name'];
        $entry = $known[(int) $attacker['worker_id']] ?? null;
        if (empty($entry)) {
            $unidentified[] = $name;
            continue;
        }
        if (!empty($entry['discovered_controller_id'])) {
            $name .= sprintf(' du réseau %s', $entry['discovered_controller_id']);
        }
        if (!empty($entry['discovered_controller_name'])) {
            $name .= sprintf(' de %s', $entry['discovered_controller_name']);
        }
        $identified[] = $name;
    }

    $clause = 'les agents ' . joinFrenchList($unidentified);
    if (!empty($identified)) {
        $clause = empty($unidentified)
            ? 'les agents ' . joinFrenchList($identified)
            : $clause . ', accompagnés de ' . joinFrenchList($identified);
    }

    return $clause;
}

/**
 * Free every agent that was still targeting a location it just lost.
 *
 * Their action_choice goes back to passive and their action_params is emptied, so no
 * stale location_id survives into the next turn. Agents already dead or captured are
 * left alone.
 *
 * @param PDO $pdo : database connection
 * @param array $participants : every combatant of the lost location, saboteurs included
 * @param int $turn_number : current turn number
 *
 * @return int : how many agents were freed
 */
function resetWorkersTargetingLocation(PDO $pdo, array $participants, int $turn_number): int
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with turn_number : ' . $turn_number, ['participant_count' => count($participants)], 'debug');

    if (empty($participants)) {
        return 0;
    }

    $stillActive = getActiveLocationCombatants($pdo, $participants, $turn_number);
    foreach ($stillActive as $participant) {
        updateWorkerAction($pdo, (int) $participant['worker_id'], $turn_number, 'passive', null, array());
    }

    game_error_log(__FUNCTION__, 'freed ' . count($stillActive) . ' agent(s) from a lost location', ['turn_number' => $turn_number], 'debug');

    return count($stillActive);
}

/**
 * Apply the location combat verdict : plunder, outcome, log, and freeing of orphans.
 *
 * The spoils go to the attacking network with the most survivors, the place is razed,
 * swapped or merely pillaged, one location_attack_logs row is written, and agents whose
 * target was lost go back to passive. No player-facing report is written here.
 *
 * @param PDO $pdo : database connection
 * @param array $location : hydrated location row (needs id, name, controller_id, activate_json)
 * @param array $engagedAttackers : attackers that reached the place, dead ones included
 * @param array $allParticipants : every combatant of the group, saboteurs included
 * @param array $aliveAttackers : attacker rows still active after the ladder
 * @param int $aliveDefenderCount : defenders still active after the ladder
 * @param bool $falls : the verdict computed by resolveAgentLocationCombat()
 * @param int $turn_number : current turn number
 *
 * @return bool : false only on a DB failure that must abort the end of turn
 */
function resolveAgentLocationOutcome(PDO $pdo, array $location, array $engagedAttackers, array $allParticipants, array $aliveAttackers, int $aliveDefenderCount, bool $falls, int $turn_number): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with location_id : ' . $location['id'], ['falls' => $falls, 'turn_number' => $turn_number], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    $locationName = (string) $location['name'];
    $targetControllerId = !empty($location['controller_id']) ? (int) $location['controller_id'] : null;
    $attackerClause = buildLocationAttackerClause($pdo, $engagedAttackers, $location);
    $aliveCount = count($aliveAttackers);

    $winnerId = $falls ? rankLocationSpoilsControllers($pdo, $aliveAttackers) : null;
    if ($winnerId === null) {
        if ($falls) {
            game_error_log(__FUNCTION__, 'no attacking controller can stash the spoils, the location holds', ['location_id' => $location['id']], 'warning');
        }
        return logLocationAttack(
            $pdo,
            $locationName,
            $turn_number,
            false,
            null,
            $targetControllerId,
            sprintf((string) getConfig($pdo, 'textLocationNotDestroyed'), $locationName),
            sprintf(locationAttackText($pdo, false), $locationName, $attackerClause),
            $aliveCount,
            $aliveDefenderCount
        );
    }

    $activateJson = json_decode((string) ($location['activate_json'] ?? ''), true);
    if (!is_array($activateJson)) {
        $activateJson = [];
    }

    // A place flagged as not destroyable is looted, never razed.
    $pillaged = empty($location['can_be_destroyed'])
        || (!empty($activateJson['indestructible']) && $activateJson['indestructible'] == 'TRUE');
    $swapped = !$pillaged && !empty($activateJson['update_location']);

    $targetText = sprintf(locationAttackText($pdo, true), $locationName, $attackerClause);
    $attackerText = sprintf((string) getConfig($pdo, $pillaged ? 'textLocationPillaged' : 'textLocationDestroyed'), $locationName);

    if ($swapped) {
        updateLocation($pdo, $location, $activateJson);
    }

    $captureResult = captureLocationsArtefacts($pdo, (int) $location['id'], $winnerId);
    $attackerText .= $captureResult['message'];

    $destroyed = false;
    if (!$pillaged && !$swapped && !empty($captureResult['success'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM {$prefix}controller_known_locations WHERE location_id = :id");
            $stmt->execute([':id' => (int) $location['id']]);
            $stmt = $pdo->prepare("DELETE FROM {$prefix}locations WHERE id = :id");
            $stmt->execute([':id' => (int) $location['id']]);
        } catch (PDOException $e) {
            game_error_log(__FUNCTION__, 'DELETE location failed: ' . $e->getMessage(), ['location_id' => $location['id']], 'error');
            return false;
        }
        $destroyed = true;
        $targetText .= ' Tout a été détruit.';
    }

    // Freed on both forms of loss, never on a simple pillage.
    if ($destroyed || $swapped) {
        resetWorkersTargetingLocation($pdo, $allParticipants, $turn_number);
    }

    game_error_log(__FUNCTION__, 'DONE with location_id : ' . $location['id'], ['winner_id' => $winnerId, 'destroyed' => $destroyed, 'swapped' => $swapped, 'pillaged' => $pillaged, 'artefacts' => $captureResult['count']], 'debug');

    // attacker_id stays NULL unless artefacts actually moved.
    return logLocationAttack(
        $pdo,
        $locationName,
        $turn_number,
        true,
        empty($captureResult['count']) ? null : $winnerId,
        $targetControllerId,
        $attackerText,
        $targetText,
        $aliveCount,
        $aliveDefenderCount
    );
}

/**
 * Pick one line of the owner-facing report pool for a location attack.
 *
 * @param PDO $pdo : database connection
 * @param bool $success : true when the place was taken
 *
 * @return string : a sprintf template expecting %1$s location name, %2$s assailants
 */
function locationAttackText(PDO $pdo, bool $success): string
{
    $key = $success ? 'textLocationAssaultOwnerSuccess' : 'textLocationAssaultOwnerFail';
    $pool = json_decode((string) getConfig($pdo, $key), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($pool)) {
        game_error_log(__FUNCTION__, 'JSON decoding error, falling back', ['config_key' => $key], 'warning');
        $pool = $success
            ? array('Notre %1$s a été attaqué.e, par %2$s. Ils ont franchi les portes avec succès.')
            : array('Notre %1$s a été attaqué.e, par %2$s. Heureusement, ils ne semblent pas avoir atteint leur objectif.');
    }

    return $pool[array_rand($pool)];
}
