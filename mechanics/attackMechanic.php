<?php
// Include-only page — block direct HTTP access.
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit();
}

/**
 * Search database for all workers in attack mode and return their targets and combat power differences.
 *
 * @param PDO $pdo : database connection
 * @param int|null $turn_number : turn number (falls back to current turn from mechanics when empty)
 * @param int|null $attacker_id : optional attacker id filter
 *
 * @return array : final_attacks_aggregate — map attacker_id => list of defender comparison rows
 */
function getAttackerComparisons(PDO $pdo, int|null $turn_number = NULL, int|null $attacker_id = NULL): array {
    if (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true')
        $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;

    game_error_log(__FUNCTION__, 'START with turn_number : ' . ($turn_number ?? 'NULL'), ['attacker_id' => $attacker_id], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];

    // Check turn number is selected
    if (empty($turn_number)) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];
    }
    game_error_log(__FUNCTION__, 'turn_number : ' . $turn_number, [], 'debug');

    try{
        // Define the SQL query to get all attackers for the turn
        $sql = "SELECT
                wa.worker_id AS attacker_id,
                wa.action_params AS params,
                wa.controller_id,
                wa.zone_id,
                wa.turn_number
            FROM
                {$prefix}worker_actions wa
            WHERE
                wa.action_choice IN ('attack')
                AND turn_number = :turn_number
        ORDER BY wa.enquete_val DESC";

        // Add Limit to only 1 caracter
        if ( !EMPTY($attacker_id) ) $sql .= " AND s.attacker_id = :attacker_id";

        // Prepare and execute the statement
        $stmt = $pdo->prepare($sql);
        if ( !EMPTY($attacker_id) ) $stmt->bindParam(':attacker_id', $attacker_id);
        $stmt->bindParam(':turn_number', $turn_number);
        $stmt->execute();
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT list of attackers failed', ['error' => $e->getMessage()]);
    }

    $attackersActionArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    game_error_log(__FUNCTION__, 'attackersActionArray fetched', ['attackersActionArray' => $attackersActionArray], 'debug');

    $attackArray = array();
    // For each attacker we get the targets of the action
    foreach ($attackersActionArray AS $attackAction){
        if (!empty($attackAction['params'])) {
            $attackArray[$attackAction['attacker_id']]=array();
            $attackParams = json_decode($attackAction['params'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                game_error_log(__FUNCTION__, 'JSON decode error', ['error' => json_last_error_msg(), 'attacker_id' => $attackAction['attacker_id']]);
                $attackParams = array();
            }
            foreach($attackParams AS $param){
                // If the attacker targets the network
                if ($param['attackScope'] == 'network'){
                    try{
                        $sqlNetworkSearch ="SELECT discovered_worker_id FROM {$prefix}controllers_known_enemies
                            WHERE zone_id = :zone_id AND discovered_controller_id = :network_id AND controller_id = :controller_id";
                        $stmtNetworkSearch = $pdo->prepare($sqlNetworkSearch);
                        $stmtNetworkSearch->bindParam(':network_id', $param['attackID'], PDO::PARAM_INT);
                        $stmtNetworkSearch->bindParam(':zone_id', $attackAction['zone_id'], PDO::PARAM_INT);
                        $stmtNetworkSearch->bindParam(':controller_id', $attackAction['controller_id'], PDO::PARAM_INT);
                        $stmtNetworkSearch->execute();
                    } catch (PDOException $e) {
                        game_error_log(__FUNCTION__, 'SELECT list of attackers for network failed', ['error' => $e->getMessage(), 'network_id' => $param['attackID']]);
                    }
                    $networkWorkersList = $stmtNetworkSearch->fetchAll(PDO::FETCH_COLUMN);
                    game_error_log(__FUNCTION__, 'networkWorkersList fetched', ['networkWorkersList' => $networkWorkersList], 'debug');
                    foreach($networkWorkersList AS $worker_id){
                        if (!in_array($worker_id, $attackArray[$attackAction['attacker_id']]) ) {
                            $attackArray[$attackAction['attacker_id']][] = $worker_id;
                        }
                    }
                // If the attacker choses a specific target
                } elseif ($param['attackScope'] == 'worker') {
                    if (!in_array($param['attackID'], $attackArray[$attackAction['attacker_id']]) ) {
                        $attackArray[$attackAction['attacker_id']][] = $param['attackID'];
                    }
                }
            }
        }
    }

    game_error_log(__FUNCTION__, 'attackArray built', ['attackArray' => $attackArray], 'debug');

    // Build SQL to compare attacker value to target value
    $sqlValCompare = "
    WITH attacker AS (
        SELECT
            wa.worker_id AS attacker_id,
            CONCAT(w.firstname, ' ', w.lastname) AS attacker_name,
            wa.attack_val AS attacker_attack_val,
            wa.defence_val AS attacker_defence_val,
            wa.controller_id AS attacker_controller_id,
            CONCAT(c.firstname, ' ', c.lastname) AS attacker_controller_name,
            wa.turn_number
        FROM
            {$prefix}worker_actions wa
            JOIN {$prefix}workers w ON w.id = wa.worker_id
            JOIN {$prefix}controllers c ON wa.controller_id = c.ID
        WHERE
            wa.worker_id = :attacker_id
            AND wa.turn_number = :turn_number
    ),
     defenders AS (
        SELECT
        wa.worker_id AS defender_id,
        wa.attack_val AS defender_attack_val,
        wa.defence_val AS defender_defence_val,
        wa.enquete_val AS defender_enquete_val,
        CONCAT(w.firstname, ' ', w.lastname) AS defender_name,
        wo.id AS defender_origin_id,
        wo.name AS defender_origin_name,
        cw.controller_id as defender_controller_id,
        z.id AS zone_id,
        z.name AS zone_name
        FROM {$prefix}workers w
        JOIN {$prefix}zones z ON z.id = w.zone_id
        JOIN {$prefix}worker_actions wa ON
            w.id = wa.worker_id AND wa.turn_number = :turn_number AND wa.action_choice IN (%s)
        JOIN {$prefix}worker_origins wo ON wo.id = w.origin_id
        JOIN {$prefix}controller_worker cw ON w.id = cw.worker_id AND is_primary_controller = %s
            WHERE w.id IN (%s)
    )
    SELECT
        a.attacker_id,
        a.attacker_name,
        a.attacker_attack_val,
        a.attacker_defence_val,
        a.attacker_controller_id,
        a.attacker_controller_name,
        a.turn_number,
        d.defender_id,
        d.defender_attack_val,
        d.defender_defence_val,
        d.defender_name,
        d.defender_origin_id,
        d.defender_origin_name,
        d.defender_controller_id,
        d.zone_id,
        d.zone_name,
        cke.id AS defender_knows_enemy,
        (a.attacker_attack_val - d.defender_defence_val) AS attack_difference,
        (d.defender_attack_val - a.attacker_defence_val) AS riposte_difference
        FROM attacker a
        CROSS JOIN defenders d
        LEFT JOIN {$prefix}controllers_known_enemies cke ON 
            cke.controller_id = d.defender_controller_id AND cke.discovered_worker_id = a.attacker_id
        ORDER BY d.defender_enquete_val DESC
    ";

    if ( (bool)getConfig($pdo, 'LIMIT_ATTACK_BY_ZONE') )
        $sqlValCompare = "
        WITH attacker AS (
            SELECT
                wa.worker_id AS attacker_id,
                CONCAT(w.firstname, ' ', w.lastname) AS attacker_name,
                wa.attack_val AS attacker_attack_val,
                wa.defence_val AS attacker_defence_val,
                wa.controller_id AS attacker_controller_id,
                wa.zone_id,
                CONCAT(c.firstname, ' ', c.lastname) AS attacker_controller_name
            FROM
                {$prefix}worker_actions wa
                JOIN {$prefix}workers w ON w.id = wa.worker_id
                JOIN {$prefix}controllers c ON wa.controller_id = c.ID
            WHERE
                wa.worker_id = :attacker_id
                AND turn_number = :turn_number
        )
        SELECT
            a.attacker_id,
            a.attacker_name,
            a.attacker_attack_val,
            a.attacker_defence_val,
            a.attacker_controller_id,
            a.attacker_controller_name,
            z.id AS zone_id,
            z.name AS zone_name,
            wa.turn_number,
            wa.worker_id AS defender_id,
            wa.attack_val AS defender_attack_val,
            wa.defence_val AS defender_defence_val,
            CONCAT(w.firstname, ' ', w.lastname) AS defender_name,
            wo.id AS defender_origin_id,
            wo.name AS defender_origin_name,
            cw.controller_id as defender_controller_id,
            (a.attacker_attack_val - wa.defence_val) AS attack_difference,
            (wa.attack_val - a.attacker_defence_val) AS riposte_difference,
            cke.id AS defender_knows_enemy
        FROM attacker a
        JOIN {$prefix}zones z ON z.id = a.zone_id
        JOIN {$prefix}worker_actions wa ON
                a.zone_id = wa.zone_id AND wa.turn_number = :turn_number AND wa.action_choice IN (%s)
        JOIN {$prefix}workers w ON wa.worker_id = w.ID
        JOIN {$prefix}worker_origins wo ON wo.id = w.origin_id
        JOIN {$prefix}controller_worker cw ON wa.worker_id = cw.worker_id AND is_primary_controller = %s
        LEFT JOIN {$prefix}controllers_known_enemies cke ON cke.controller_id = cw.controller_id AND cke.discovered_worker_id = a.attacker_id
        WHERE w.id IN (%s)
        ";

    $final_attacks_aggregate = array();
    $active_actions = "'".implode("','", ACTIVE_ACTIONS)."'";

    foreach ($attackArray AS $compared_attacker_id => $defender_ids ) {
        try {
            game_error_log(__FUNCTION__, 'compared_attacker_id : ' . $compared_attacker_id, ['defender_ids' => $defender_ids], 'debug');

            $stmtValCompare = $pdo->prepare(
                sprintf(
                    $sqlValCompare,
                    $active_actions,
                    ($_SESSION['DBTYPE'] == 'mysql') ? 1 : 'true',
                    implode(',', $defender_ids)
                )
            );
            $stmtValCompare->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
            $stmtValCompare->bindParam(':attacker_id', $compared_attacker_id, PDO::PARAM_INT);
            $stmtValCompare->execute();
        } catch (PDOException $e) {
            game_error_log(__FUNCTION__, 'SELECT compare attackers to defenders failed', ['error' => $e->getMessage(), 'attacker_id' => $compared_attacker_id]);
        }
        if ($stmtValCompare->rowCount() == 0) continue;
        $final_attacks_aggregate[$compared_attacker_id] = $stmtValCompare->fetchAll(PDO::FETCH_ASSOC);
        game_error_log(__FUNCTION__, 'final_attacks_aggregate row for attacker : ' . $compared_attacker_id, ['row' => $final_attacks_aggregate[$compared_attacker_id]], 'debug');
    }
    return $final_attacks_aggregate;

}

/**
 * Main function to calculate attack results.
 *
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics array (uses turncounter)
 *
 * @return bool : success
 */
function attackMechanic(PDO $pdo, array $mechanics): bool {
    if (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true')
        $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;

    game_error_log(__FUNCTION__, 'START with turncounter : ' . ($mechanics['turncounter'] ?? 'NULL'), [], 'debug');

    echo '<div> <h3>  attackMechanic : </h3> ';
    $prefix = $_SESSION['GAME_PREFIX'];

    $ATTACKDIFF0 = getConfig($pdo, 'ATTACKDIFF0');
    $ATTACKDIFF1 = getConfig($pdo, 'ATTACKDIFF1');
    $RIPOSTDIFF = getConfig($pdo, 'RIPOSTDIFF');
    $RIPOSTACTIVE = getConfig($pdo, 'RIPOSTACTIVE');

    game_error_log(__FUNCTION__, 'config thresholds', [
        'ATTACKDIFF0' => $ATTACKDIFF0,
        'ATTACKDIFF1' => $ATTACKDIFF1,
        'RIPOSTDIFF' => $RIPOSTDIFF,
        'RIPOSTACTIVE' => $RIPOSTACTIVE,
    ], 'debug');

    $attacksArray = getAttackerComparisons($pdo, $mechanics['turncounter'], NULL);
    game_error_log(__FUNCTION__, 'attacksArray fetched', ['attacksArray' => $attacksArray], 'debug');
    if (empty($attacksArray)) {
        echo 'All is calm </div>';
        game_error_log(__FUNCTION__, 'DONE with return : true (all is calm)', [], 'debug');
        return true;
    }

    $workerDisappearanceTexts = json_decode(getConfig($pdo,'workerDisappearanceTexts'), true);
    $workerCapturedTexts = json_decode(getConfig($pdo,'workerCapturedTexts'), true);
    $attackSuccessTexts = json_decode(getConfig($pdo,'attackSuccessTexts'), true);
    $captureSuccessTexts = json_decode(getConfig($pdo,'captureSuccessTexts'), true);
    $failedAttackTextes = json_decode(getConfig($pdo,'failedAttackTextes'), true);
    $unfoundAttackTextes = json_decode(getConfig($pdo,'unfoundAttackTextes'), true);
    $escapeTextes = json_decode(getConfig($pdo,'escapeTextes'), true);
    $textesAttackFailedAndCountered = json_decode(getConfig($pdo,'textesAttackFailedAndCountered'), true);
    $counterAttackTexts = json_decode(getConfig($pdo,'counterAttackTexts'), true);

    foreach ($attacksArray as $attacker_id => $defenders) {
        // Build report :
        game_error_log(__FUNCTION__, 'attacker_id : ' . $attacker_id, ['defenders' => $defenders], 'debug');
        if (!is_array($defenders[0])) continue;

        echo sprintf("attacker_name: %s <br/>", $defenders[0]['attacker_name']);

        // get updated attacker action choice from worker_actions for turn_number
        $attackerInfo = getWorkers($pdo, [$attacker_id]);
        if (in_array($attackerInfo[0]['actions'][$mechanics['turncounter']]['action_choice'], INACTIVE_ACTIONS)) {
            continue;
        }
        // For each defender, check if attack is successful and update defender status
        foreach ($defenders as $defender) {
            $attackerReport= array();
            $defenderReport= array();
            $defender_status = NULL;
            $defender_json = null;
            $attacker_status = NULL;
            $survived = true;

            // get updated defender status from worker_actions for turn_number
            $defenderArray = getWorkers($pdo, [$defender['defender_id']]);
            // if defender already is dead or prisoner, skip attack and add to report
            if (in_array($defenderArray[0]['actions'][$mechanics['turncounter']]['action_choice'], INACTIVE_ACTIONS)) {
                $attackerReport['attack_report'] = sprintf($unfoundAttackTextes[array_rand($unfoundAttackTextes)], $defender['defender_name']);
                updateWorkerAction($pdo, $defender['attacker_id'], $mechanics['turncounter'], $attacker_status, $attackerReport );
            } else {
                // if defender is alive, check if attack is successful and update defender status
                if ($defender['attack_difference'] >= (INT)$ATTACKDIFF0 ){
                    echo $defender['defender_name']. ' HAS DIED ! <br />';
                    $survived = false;
                    $defender_status = 'dead';

                    $attackerReport['attack_report'] = sprintf($attackSuccessTexts[array_rand($attackSuccessTexts)], $defender['defender_name']);
                    // %1$s - timeDenominatorThe lowercase, %2$s - timeDenominatorOf lowercase %3$s - timeValue %4$s - week number
                    $defenderReport['life_report'] = sprintf(
                        $workerDisappearanceTexts[array_rand($workerDisappearanceTexts)],
                        getConfig($pdo,'timeDenominatorThe'),
                        getConfig($pdo,'timeDenominatorOf'),
                        getConfig($pdo,'timeValue'),
                        $mechanics['turncounter']
                    );
                    if ($defender['attack_difference'] >= (INT)$ATTACKDIFF1 ){
                        echo $defender['defender_name']. ' Was Captured ! <br />';
                        $defender_status = 'captured';
                        $attackerReport['attack_report'] = sprintf($captureSuccessTexts[array_rand($captureSuccessTexts)], $defender['defender_name']);
                        $defender_json = array('original_controller_id' => $defender['defender_controller_id']);

                        $tmpLifeReport = $defenderReport['life_report'];

                        $tmpDoubleAgentReport = "";
                        // Si l'agent était agent double, on crée une trace et on enregistre son maitre dans le action_params et on l'ajoute à son rapport
                        try{
                            $sqlDoubleAgent = sprintf("SELECT cw.controller_id, CONCAT(c.firstname, ' ', c.lastname) AS double_agent_contoller_name
                                    FROM {$prefix}controller_worker AS cw
                                    JOIN {$prefix}controllers AS c ON c.id = cw.controller_id
                                    WHERE cw.is_primary_controller = %s AND cw.worker_id = :worker_id
                                ",
                                ($_SESSION['DBTYPE'] == 'mysql') ? 0 : 'false'
                            );

                            $stmt = $pdo->prepare($sqlDoubleAgent);
                            $stmt->bindParam(':worker_id', $defender['defender_id'], PDO::PARAM_INT);
                            $stmt->execute();
                            $doubleAgentControllerResult = $stmt->fetch(PDO::FETCH_ASSOC);
                            if (!empty($doubleAgentControllerResult)) {
                                $defender_json['double_agent_controller_id'] = $doubleAgentControllerResult['controller_id'];
                                // Create Trace
                                $traceWrokerID = createTraceWorker($pdo, $defender['defender_id'], $defender_json['double_agent_controller_id']);
                                updateWorkerAction($pdo, $traceWrokerID, $defender['turn_number'], null, ['life_report' => $tmpLifeReport] );
                                // 
                                $tmpDoubleAgentReport = sprintf("<br/> J'était un <strong>agent double %s %s.</strong>", getConfig($pdo,'controllerNameDenominatorOf'), $doubleAgentControllerResult['double_agent_contoller_name']);
                            }
                        } catch (PDOException $e) {
                            game_error_log(__FUNCTION__, 'SELECT double agent controller failed', ['error' => $e->getMessage()]);
                        }

                        $defenderReport['life_report'] = sprintf(
                            $workerCapturedTexts[array_rand($workerCapturedTexts)],
                            $defender['attacker_controller_id'],
                            $tmpDoubleAgentReport
                        );

                        // Create Trace
                        $traceWrokerID = createTraceWorker($pdo, $defender['defender_id'], $defender['defender_controller_id']);
                        updateWorkerAction($pdo, $traceWrokerID, $defender['turn_number'], null, ['life_report' => $tmpLifeReport]);

                        try{
                            // in controller_worker delete defender_id
                            $stmt = $pdo->prepare("DELETE FROM {$prefix}controller_worker WHERE worker_id = :worker_id");
                            $stmt->bindParam(':worker_id', $defender['defender_id'], PDO::PARAM_INT);
                            $stmt->execute();
                            // in controller_worker insert attacker_controller_id, defender_id, is_primary_controller = true
                            $sql = sprintf( 
                                "INSERT INTO {$prefix}controller_worker (controller_id, worker_id, is_primary_controller) VALUES (:controller_id, :worker_id, %s)",
                                ($_SESSION['DBTYPE'] == 'mysql') ? 1 : 'true'
                            );
                            $stmt = $pdo->prepare($sql);
                            $stmt->bindParam(':controller_id', $defender['attacker_controller_id'], PDO::PARAM_INT);
                            $stmt->bindParam(':worker_id', $defender['defender_id'], PDO::PARAM_INT);
                            $stmt->execute();
                        } catch (PDOException $e) {
                            game_error_log(__FUNCTION__, 'UPDATE controller_worker on capture failed', ['error' => $e->getMessage(), 'defender_id' => $defender['defender_id']]);
                        }
                        // Check for existing trace
                        if (destroyTraceWorker($pdo, $defender['defender_id'], $defender['attacker_controller_id']) === false)
                            game_error_log(__FUNCTION__, 'destroyTraceWorker failed', ['defender_id' => $defender['defender_id']], 'warning');
                    }
                } else {
                    echo $defender['defender_name']. ' Escaped !<br />';
                    $attackerReport['attack_report'] = sprintf($failedAttackTextes[array_rand($failedAttackTextes)], $defender['defender_name']);
                    // Check if attaker is in know ennemies
                    try{
                        $knownEnemycontroller = '';
                        $sql_known_ennemies = "
                            SELECT * FROM {$prefix}controllers_known_enemies
                                WHERE controller_id = :controller_id
                                AND discovered_worker_id = :discovered_worker_id
                                AND zone_id = :zone_id";
                        $stmtA = $pdo->prepare($sql_known_ennemies);
                        $stmtA->bindParam(':controller_id', $defender['defender_controller_id'], PDO::PARAM_INT);
                        $stmtA->bindParam(':discovered_worker_id', $defender['attacker_id'], PDO::PARAM_INT);
                        $stmtA->bindParam(':zone_id', $defender['zone_id'], PDO::PARAM_INT);
                        $stmtA->execute();
                        $knownEnemy = $stmtA->fetchAll(PDO::FETCH_ASSOC); // Only return worker IDs
                        if (!empty($knownEnemy)) {
                            // Yes and is controller known ? Add to report
                            if (!empty($knownEnemy[0]['discovered_controller_id'])) {
                                $knownEnemycontroller .= sprintf(' du résseau %s', $knownEnemy[0]['discovered_controller_id']);
                            }
                            if (!empty($knownEnemy[0]['discovered_controller_name'])) {
                                $knownEnemycontroller .= sprintf(' des agents de %s', $knownEnemy[0]['discovered_controller_name']);
                            }
                        } else {
                            // No Add to know ennemies
                            addWorkerToCKE($pdo, $defender['defender_controller_id'], $defender['attacker_id'], $defender['turn_number'], $defender['zone_id']);
                        }
                    } catch (PDOException $e) {
                        game_error_log(__FUNCTION__, 'SELECT/INSERT controllers_known_enemies failed', ['error' => $e->getMessage()]);
                    }
                    $defenderReport['life_report'] = sprintf($escapeTextes[array_rand($escapeTextes)], sprintf("%s(%s)%s",$defender['attacker_name'], $defender['attacker_id'], $knownEnemycontroller));
                }
                game_error_log(__FUNCTION__, 'survived : ' . ($survived ? 'true' : 'false'), [], 'debug');
                if ( $RIPOSTACTIVE!= '0' && $survived  && $defender['riposte_difference'] >= (INT)$RIPOSTDIFF ){
                    $attacker_status = 'dead';
                    echo $defender['defender_name']. ' RIPOSTE ! <br />';
                    $attackerReport['attack_report'] = sprintf($textesAttackFailedAndCountered[array_rand($textesAttackFailedAndCountered)], $defender['defender_name']);
                    // %1$s - timeDenominatorThe lowercase, %2$s - timeDenominatorOf lowercase %3$s - timeValue %4$s - week number
                    $defenderReport['life_report'] = sprintf(
                        $workerDisappearanceTexts[array_rand($workerDisappearanceTexts)],
                        getConfig($pdo,' timeDenominatorThe'),
                        getConfig($pdo,' timeDenominatorOf'),
                        getConfig($pdo,'timeValue'),
                        $mechanics['turncounter']
                    );
                    $defenderReport['life_report'] .= sprintf($counterAttackTexts[array_rand($counterAttackTexts)], sprintf("%s(%s)%s",$defender['attacker_name'], $defender['attacker_id'], $knownEnemycontroller));
                }
                updateWorkerAction($pdo, $defender['attacker_id'], $defender['turn_number'], $attacker_status, $attackerReport );
                updateWorkerAction($pdo, $defender['defender_id'], $defender['turn_number'], $defender_status , $defenderReport, $defender_json );
            }
        }
    }

    echo '<p> attackMechanic: DONE ! </p> </div>';
    game_error_log(__FUNCTION__, 'DONE with return : true', [], 'debug');
    return true;
}