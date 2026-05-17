<?php

require_once '../mechanics/attackMechanic.php';
require_once '../mechanics/investigateMechanic.php';
require_once '../mechanics/aiMechanic.php';
require_once '../mechanics/locationSearchMechanic.php';

/**
 * Start or Pause the game state
 * 
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics array
 * @param bool $start : true to start, false to pause
 *
 * @return bool : success
 */
function toggleMechanicsGamestate($pdo, $mechanics, $start = true) {
    $prefix = $_SESSION['GAME_PREFIX'];

    // SQL query to update gamestate
    $sql = '';
    if ($start && $mechanics['gamestate'] == 0) {
        $sql = "UPDATE {$prefix}mechanics SET gamestate = 1 WHERE id = :id ";
    }
    if (!$start && $mechanics['gamestate'] == 1) {
        $sql = "UPDATE {$prefix}mechanics SET gamestate = 0 WHERE id = :id ";
    }
    if (!empty($sql)) {
        try{
            // Prepare and execute SQL query
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $mechanics['id']]);
        } catch (PDOException $e) {
            echo __FUNCTION__."():UPDATE mechanics Failed: " . $e->getMessage()."<br />";
            return false;
        }
    }
    return true;
}

/**
 * Change the end turn state
 *
 * @param PDO $pdo : database connection
 * @param string $state : state to change to
 * @param array $mechanics : mechanics array
 *
 * @return bool : success
 */
function changeEndTurnState($pdo, $state, $mechanics) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $sql = "UPDATE {$prefix}mechanics set end_step = :end_step WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':end_step' => $state, ':id' => $mechanics['id']]);
}

/**
 * Build base randomization SQL
 *
 * @return string : SQL string
 *
 */
function diceSQL() {
    $prefix = $_SESSION['GAME_PREFIX'];
    return sprintf(
        "FLOOR(
            %1\$s * (
                CAST((SELECT value FROM {$prefix}config WHERE name = 'MAXROLL') AS %2\$s)
                - CAST((SELECT value FROM {$prefix}config WHERE name = 'MINROLL') AS %2\$s)
                +  1
            ) + CAST((SELECT value FROM {$prefix}config WHERE name = 'MINROLL') AS %2\$s)
        )",
        ($_SESSION['DBTYPE'] == 'postgres') ? 'RANDOM()' : 'RAND()',
        ($_SESSION['DBTYPE'] == 'postgres') ? 'INT' : 'SIGNED'
    );
}

/**
 * Return value of a dice roll
 *
 * @param PDO $pdo : database connection
 *
 * @return int : rollvalue
 */
function diceRoll($pdo) {
    $diceSQL = diceSQL();
    $sql = "SELECT $diceSQL as roll";

    try{
        // Prepare and execute SQL query
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT diceSQL Failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    $roll = $stmt->fetchALL(PDO::FETCH_ASSOC);
    return $roll[0]['roll'];
}

/**
 * Calculates the final values for each worker depending on their chosen action.
 *
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics array
 *
 * @return bool : success
 *
 */
function calculateVals($pdo, $mechanics){
    $turn_number =  $mechanics['turncounter'];
    $prefix = $_SESSION['GAME_PREFIX'];

    $sqlArray = [];
    $array = [];
    $array[] = ['enquete', 'passiveInvestigateActions', false];
    $array[] = ['enquete', 'activeInvestigateActions', true];
    $array[] = ['attack', 'passiveAttackActions', false];
    $array[] = ['attack', 'activeAttackActions', true];
    $array[] = ['defence', 'passiveDefenceActions', false];
    $array[] = ['defence', 'activeDefenceActions', true];
    echo '<div> <h3> calculateVals : </h3> <p>';

    // foreach type of action
    foreach ($array as $elements) {
        $config = getConfig($pdo, $elements[1]);
        echo sprintf("Get Config for %s : $config <br /> ", $elements[1]);

        // chose SQL for value generation
        $valBaseSQL = sprintf(
            "(SELECT CAST(value AS %s) FROM {$prefix}config WHERE name = 'PASSIVEVAL')",
            ($_SESSION['DBTYPE'] == 'postgres') ? 'INT' : 'SIGNED'
        );
        if ($elements[2]) {
            $valBaseSQL = diceSQL();
        }

        // Name of configured bonus (ex : ENQUETE_ZONE_BONUS)
        $zoneBonusColumn = strtoupper("{$elements[0]}_zone_bonus");
        $zoneBonusSQL = sprintf(
            "(SELECT CAST(value AS %s) FROM {$prefix}config WHERE name = '%s')",
            ($_SESSION['DBTYPE'] == 'postgres') ? 'INT' : 'SIGNED',
            $zoneBonusColumn
        );

        // Flat bonus to a specific action from config
        $flatBonusSQL = "";
        // Remove single quotes
        $strActions = str_replace("'", "", $config);
        // Now split
        $actions = explode(",", $strActions);
        foreach ( $actions AS $action ) {
            // get flat bonus from config
            $bonusColumn = strtoupper(sprintf("%s_%s_flat_bonus", $action, $elements[0]));
            $flatBonusConfig = getConfig($pdo, $bonusColumn);
            echo sprintf("Get Config for %s : %s <br /> ", $bonusColumn, $flatBonusConfig);
            if (!empty($flatBonusConfig)) {
                $flatBonusSQL .= sprintf("
                    + CASE
                        WHEN wa1.action_choice = '%s'
                        THEN %s
                        ELSE 0
                    END",
                    $action,
                    $flatBonusConfig
                );
            }
        }
        // Build of base SQL request with the conditionnal bonus
        $valSQL = sprintf("%s_val = (
            COALESCE((
                SELECT SUM(p.%s)
                FROM {$prefix}workers AS w
                LEFT JOIN {$prefix}worker_powers wp ON w.id = wp.worker_id
                LEFT JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                LEFT JOIN {$prefix}powers p ON lpt.power_id = p.ID
                WHERE wa1.worker_id = w.id
            ), 0)
            + %s
            + CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM {$prefix}workers w2
                    JOIN {$prefix}zones z2 ON w2.zone_id = z2.id
                    JOIN {$prefix}controller_worker cw2 ON cw2.worker_id = w2.id AND is_primary_controller = True
                    WHERE w2.id = wa1.worker_id
                      AND z2.holder_controller_id = cw2.controller_id
                )
                THEN %s
                ELSE 0
            END
            %s

        )",
            $elements[0],
            $elements[0],
            $valBaseSQL,
            $zoneBonusSQL,
            $flatBonusSQL
        );

        // get list of actions to calibrate
        if (!empty($config)){
            // add to list of updates
            $sqlArray[] = array(
                'sql'=> sprintf(
                    "UPDATE {$prefix}worker_actions wa1 SET %1\$s WHERE turn_number = %2\$s AND action_choice IN (%3\$s)",
                    $valSQL, $turn_number, $config
                ),
                'config' => $config
            );
        }
    }
    echo '</p>';

    // Execute SQLs
    foreach ($sqlArray as $sql) {
        echo sprintf("<p>DO SQL for %s : <br /> %s <br>",  $sql['config'],  $sql['sql'] );
        try {
            // Prepare and execute SQL query
            $stmt = $pdo->prepare($sql['sql']);
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />";
            return false;
        }
        echo "DONE <br /></p>";
    }

    echo '</div>';

    changeEndTurnState($pdo, 'calculateVals', $mechanics);
    return true;
}

/**
 * Creation of new worker action lines for each worker for the new turn
 * maintaining certain action continuation (investigate and claim)
 * setting dead and captured status
 *
 * @param PDO $pdo : database connection
 * @param string $turn_number
 *
 * @return bool : success
 *
 */
function createNewTurnLines($pdo, $turn_number){
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';
    $prefix = $_SESSION['GAME_PREFIX'];
    echo '<div> <h3>  createNewTurnLines : </h3> ';
    $sqlInsert = "
        INSERT INTO {$prefix}worker_actions (worker_id, turn_number, zone_id, controller_id, action_choice, action_params)
        SELECT
            w.id AS worker_id,
            :turn_number AS turn_number,
            w.zone_id AS zone_id,
            cw.controller_id AS controller_id,
            wa.action_choice,
            wa.action_params
        FROM {$prefix}workers w
        JOIN {$prefix}controller_worker AS cw ON cw.worker_id = w.id AND is_primary_controller = " . ($_SESSION['DBTYPE'] == 'postgres' ? 'true' : '1') . "
        JOIN {$prefix}worker_actions AS wa ON wa.worker_id = w.id AND turn_number = :turn_number_n_1
        WHERE wa.worker_id NOT IN ( SELECT worker_id FROM {$prefix}worker_actions wa2 WHERE wa2.turn_number = :turn_number )
    ";
    try {
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmtInsert->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
        $stmtInsert->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql INSERT FAILED : ".$e->getMessage()."<br/> sql: $sqlInsert<br/>";
        return false;
    }

    $config_continuing_investigate_action = getConfig($pdo, 'continuing_investigate_action');
    if (!$config_continuing_investigate_action){
        $sqlSetInvestigate = "
            UPDATE {$prefix}worker_actions wa1
            JOIN {$prefix}worker_actions wa2 ON wa1.worker_id = wa2.worker_id
            SET wa1.action_choice = 'passive'
            WHERE wa1.turn_number = :turn_number
            AND wa2.action_choice = 'investigate'
            AND wa2.turn_number = :turn_number_n_1
        ";
        try {
            $stmtSetInvestigate = $pdo->prepare($sqlSetInvestigate);
            $stmtSetInvestigate->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
            $stmtSetInvestigate->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
            $stmtSetInvestigate->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql UPDATE investigate FAILED : ".$e->getMessage()."<br />$sqlSetInvestigate<br/>";
            return false;
        }
    }
    $config_continuing_claimed_action = getConfig($pdo, 'continuing_claimed_action');
        if (!$config_continuing_claimed_action){
        $sqlSetClaim = "
            UPDATE {$prefix}worker_actions wa1
            JOIN {$prefix}worker_actions wa2 ON wa1.worker_id = wa2.worker_id
            SET wa1.action_choice = 'passive'
            WHERE wa1.turn_number = :turn_number
            AND wa2.action_choice = 'claim'
            AND wa2.turn_number = :turn_number_n_1
        ";
        try {
            $stmtSetClaim = $pdo->prepare($sqlSetClaim);
            $stmtSetClaim->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
            $stmtSetClaim->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
            $stmtSetClaim->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql UPDATE claim FAILED : ".$e->getMessage()."<br />$sqlSetClaim<br/>";
            return false;
        }
    }

    echo '<p>createNewTurnLines : DONE</p> </div>';

    return true;
}

/**
 *
 * 
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics array
 *
 * @return bool : success
 *
 */
function claimMechanic($pdo, $mechanics) {
    $mode = getConfig($pdo, 'claimMode');
    if (!in_array($mode, ['worker', 'worker_leader'], true)) {
        echo "<div><h3>claimMechanic : mode '".htmlspecialchars((string)$mode)."' not supported, skipped</h3></div>";
        return true;
    }

    if ($mode === 'worker_leader') return claimByWorkerLeaderMechanic($pdo, $mechanics);

    // mode 'worker' function 
    $turn_number = $mechanics['turncounter'];
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    echo '<div> <h3>  claimMechanic : </h3> ';
    echo "turn_number : $turn_number <br>";

    $prefix = $_SESSION['GAME_PREFIX'];
    
    // Define the SQL query
    $sql = "SELECT
            wa.worker_id AS claimer_id,
            CONCAT(w.firstname, ' ', w.lastname) AS claimer_name,
            wa.enquete_val AS claimer_enquete_val,
            wa.attack_val AS claimer_attack_val,
            wa.action_params AS claimer_params,
            wa.controller_id AS claimer_controller_id,
            z.id AS zone_id,
            z.name AS zone_name,
            z.holder_controller_id AS zone_holder_controller_id,
            (wa.enquete_val - z.calculated_defence_val) AS discrete_claim,
            (wa.attack_val - z.calculated_defence_val) AS violent_claim
        FROM {$prefix}worker_actions wa
        JOIN {$prefix}zones z ON z.id = wa.zone_id
        JOIN {$prefix}workers w ON w.id = wa.worker_id
        -- JOIN controller c ON c.id = wa.controller_id
        WHERE
            wa.action_choice = 'claim'
            AND wa.turn_number = :turn_number
        ORDER BY
            z.id, wa.attack_val DESC
    ";
    try{
        // Prepare and execute the statement
        $stmt = $pdo->prepare($sql);
        if ( !EMPTY($searcher_id) ) $stmt->bindParam(':searcher_id', $searcher_id);
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
    }
    // Fetch and return the results
    $claimerArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($debug)
    echo sprintf("%s(): claimerArray %s </br>", __FUNCTION__, var_export($claimerArray,true));

    $arrayZoneInfo = array();
    $DISCRETECLAIMDIFF = (INT)getConfig($pdo, 'DISCRETECLAIMDIFF');
    $VIOLENTCLAIMDIFF = (INT)getConfig($pdo, 'VIOLENTCLAIMDIFF');

    foreach( $claimerArray AS $key => $claimer ) {
        $worker_id = $claimer['claimer_id'];

        if ($debug)
            echo sprintf("<p> %s(): checking key %s => claimer: %s </br>", __FUNCTION__, $key, var_export($claimer,true));

        // claims are generally violent
        $arrayZoneInfo[$claimer['zone_id']]['is_violent_claim'] = true;
        // claim are not generally successful
        $success = false;

        if ($debug) {
            echo sprintf(
                "%s(): zone_holder_controller_id : '%s', zone has not been claimed this turn: '%s', next key exists : '%s'",
                __FUNCTION__,
                var_export($claimer['zone_holder_controller_id'],true),
                var_export(empty($arrayZoneInfo[$claimer['zone_id']]['claimer']),true),
                var_export(!empty($claimerArray[$key+1]),true)
            );
            echo sprintf("%s(): It is this 1st iteration and only for active zone %s: ", __FUNCTION__, $claimer['zone_id']);
        }
        // if its the 1st and only claim for a previously unclaimed zone then
        if (
            $claimer['zone_holder_controller_id'] == NULL
            && empty($arrayZoneInfo[$claimer['zone_id']]['claimer'])
            && (
                $key-1 < 0
                || $claimerArray[$key-1]['zone_id'] != $claimer['zone_id']
            )
            && (
                empty($claimerArray[$key+1])
                || $claimerArray[$key+1]['zone_id'] != $claimer['zone_id']
            )
        ){
            if ($debug) echo " -> yes ";
            // if discrete_claim or violent_claim is sufficient to claim
            if ( (INT)$claimer['discrete_claim'] >= $DISCRETECLAIMDIFF || (INT)$claimer['violent_claim'] >= $VIOLENTCLAIMDIFF ) {
                // mark as success
                $success = true;
                // Save claimer info
                $arrayZoneInfo[$claimer['zone_id']]['claimer'] = $claimer;
                // Check if it is exceptionnaly a discret claim
                if ( (INT)$claimer['discrete_claim'] >= $DISCRETECLAIMDIFF )
                    $arrayZoneInfo[$claimer['zone_id']]['is_violent_claim'] = false;
            }

        // if its not the 1st claim found for the zone or the zone was already claimed then
        } else {
            if ($debug) echo " -> no  ";
            // if no successful claimer has been found yet
            if ( empty($arrayZoneInfo[$claimer['zone_id']]['claimer']) ) {
                if ($debug) echo " -> is Allowed to claim ";
                // if discrete_claim or violent_claim is sufficient to claim
                if ( (INT)$claimer['discrete_claim'] >= $DISCRETECLAIMDIFF || (INT)$claimer['violent_claim'] >= $VIOLENTCLAIMDIFF ) {
                    if ($debug) echo " -> Success ";
                    // mark as success
                    $success = true;
                    // Save claimer info
                    $arrayZoneInfo[$claimer['zone_id']]['claimer'] = $claimer;
                }
            }
        }
        //if ($debug)
        if ($debug) echo " </br>";

        //if ($debug)
        if ($debug) echo sprintf(
                "Warn controllers of workers that violence happened : %s and if it was successful or not : %s",
                var_export( $arrayZoneInfo[$claimer['zone_id']]['is_violent_claim'], true),
                var_export( $success, true)
            );
        if ($arrayZoneInfo[$claimer['zone_id']]['is_violent_claim']) {
            // get all workers of zone
            $sql_workers_by_zone = "SELECT *
                FROM {$prefix}workers w
                JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
                JOIN {$prefix}worker_actions wa ON wa.worker_id = w.id AND wa.turn_number = :turn_number
                WHERE
                    w.zone_id = :zone_id
                    AND cw.controller_id != :controller_id
                    AND wa.action_choice IN (%s)";
            try {
                $active_actions = "'".implode("','", ACTIVE_ACTIONS)."'";
                $sql_workers_by_zone = sprintf($sql_workers_by_zone, $active_actions);

                $stmt = $pdo->prepare($sql_workers_by_zone);
                $stmt->bindParam(':zone_id', $claimer['zone_id'], PDO::PARAM_INT);
                $stmt->bindParam(':controller_id', $claimer['claimer_controller_id'], PDO::PARAM_INT);
                $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
                $stmt->execute();
                $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                echo "getWorkersByZone(): Failed to fetch workers: " . $e->getMessage();
                continue;
            }
            if ($debug)
                echo sprintf ("sql_workers_by_zone => workers : %s <br>", var_export($workers, true) );
            foreach ( $workers AS $worker) {
                if ($debug)  echo sprintf ("for worker : %s <br>", var_export($worker, true) );
                //textesClaimFailViewArray / textesClaimSuccessViewArray
                // (nom) - %1$s
                // (zone) - %2$s
                $textesClaimViewArray = json_decode(getConfig($pdo,'textesClaimFailViewArray'), true);
                if ($success)
                    $textesClaimViewArray = json_decode(getConfig($pdo,'textesClaimSuccessViewArray'), true);
                $textesClaimView = $textesClaimViewArray[array_rand($textesClaimViewArray)];

                $report = sprintf($textesClaimView, $claimer['claimer_name'], $claimer['zone_name']).'<br/>';
                // add description of violent claim to report and if $success
                updateWorkerAction($pdo, $worker['worker_id'],  $turn_number, NULL, ['claim_report' => $report]);
                // update controller_known_enemies for controllers of workers in zone
                if ($debug)
                    echo sprintf("addWorkerToCKE (%s, %s, %s, %s) <br>", $worker['controller_id'], $worker_id, $turn_number, $claimer['zone_id']);
                addWorkerToCKE($pdo, $worker['controller_id'], $worker_id, $turn_number, $claimer['zone_id']);
            }
        }
        // For Worker add message if claim is successful or failed ($success) an if it was violent or not $arrayZoneInfo[$claimer['zone_id']]['is_violent_claim']
        // (nom) - %1$s
        // (zone) - %2$s
        $textesClaimViewArray = json_decode(getConfig($pdo,'textesClaimFailArray'), true);
        if ($success)
            $textesClaimViewArray = json_decode(getConfig($pdo,'textesClaimSuccessArray'), true);
        $textesClaimView = $textesClaimViewArray[array_rand($textesClaimViewArray)];
        $report = sprintf($textesClaimView, $claimer['claimer_name'], $claimer['zone_name']);
        try{
            updateWorkerAction($pdo, $worker_id,  $turn_number, NULL, ['claim_report' => $report]);
        } catch (Exception $e) {
            echo "updateWorkerAction() failed for worker_id $worker_id: " . $e->getMessage() . "<br />";
            break;
        }

        if ($debug) echo '</p>';
    }

    foreach ( $arrayZoneInfo as $key => $zoneInfo ) {
        if ( ! empty($zoneInfo['claimer']) )  {
            if ($debug) echo "zoneInfo['claimer']['claimer_params'] :". var_export($zoneInfo['claimer']['claimer_params'], true). "<br />";

            $claimer_params = json_decode($zoneInfo['claimer']['claimer_params'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
                $claimer_params = array();
            }
            if ($debug) echo "claimer_params :". var_export($claimer_params, true);

            $claimer_controller_id = $zoneInfo['claimer']['claimer_controller_id'];
            if ( !empty($claimer_params['claim_controller_id'])) {
                $claimer_controller_id = $claimer_params['claim_controller_id'];
                if ($claimer_params['claim_controller_id'] == 'null') $claimer_controller_id = null;
            }

            $sql = "UPDATE {$prefix}zones SET claimer_controller_id = :claimer_controller_id , holder_controller_id = :holder_controller_id WHERE id = :id";
            try{
                // Update config value in the database
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $zoneInfo['claimer']['zone_id'], PDO::PARAM_INT);
                $stmt->bindParam(':holder_controller_id', $zoneInfo['claimer']['claimer_controller_id'], PDO::PARAM_INT);
                $stmt->bindParam(':claimer_controller_id', $claimer_controller_id, PDO::PARAM_INT);
                $stmt->execute();
            } catch (PDOException $e) {
                echo __FUNCTION__."(): UPDATE zones Failed: " . $e->getMessage()."<br />";
            }
            echo $sql. "</br>";
        }
        else { echo "Zone $key Unclaimed. <br />";}
    }

    echo '<p>claimMechanic : DONE</p> </div>';

    return true;
}

/**
 * Deterministic claim resolver for the claim by a leader.
 * 
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics array
 * 
 *  Groups all worker by action_choice='claim' for this turn by (controller_id, zone_id),
 *  Picks the lowest worker_id as the leader of each group and resolves the claim
 *  via calculateControllerValue('Claim') vs calculateControllerValue('ZoneDefence').
 *  Adds claimVisibleToRealBonus to the claim_val when the atacking controller already holds zones.claimer_controller_id
 */
function claimByWorkerLeaderMechanic($pdo, $mechanics) {

    $turn_number = $mechanics['turncounter'];
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';
    $prefix = $_SESSION['GAME_PREFIX'];

    echo '<div> <h3>  claimMechanic_workerLeader : </h3> ';
    echo "turn_number : $turn_number <br>";

    // Get configs
    $claimDiff = (int) getConfig($pdo, 'claimDiff');

    // Groups all worker by action_choice='claim' for this turn by (controller_id, zone_id),
    // Picks the lowest worker_id as the leader of each group and resolves the claim
    try {
        $sql = "SELECT cw.controller_id, w.zone_id, MIN(w.id) AS leader_worker_id, z.name AS zone_name
                FROM {$prefix}worker_actions wa
                JOIN {$prefix}workers w ON w.id = wa.worker_id
                JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
                JOIN {$prefix}zones z ON z.id = w.zone_id
                WHERE wa.turn_number = :turn_number
                  AND wa.action_choice = 'claim'
                  AND cw.is_primary_controller = " . (($_SESSION['DBTYPE'] == 'mysql') ? '1' : 'true') . "
                GROUP BY cw.controller_id, w.zone_id, z.name";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmt->execute();
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT groups failed: ".$e->getMessage()."<br />";
        return false;
    }

    foreach ($groups as $group) {
        $zone_id = (int) $group['zone_id'];
        $cid = (int) $group['controller_id'];
        $leader_id = (int) $group['leader_worker_id'];

        // Fetch each leader's action_params so we can honour claim_controller_id
        $leaderParams = array();
        try {
            $pStmt = $pdo->prepare(
                "SELECT action_params FROM {$prefix}worker_actions
                 WHERE worker_id = :wid AND turn_number = :turn LIMIT 1"
            );
            $pStmt->bindParam(':wid', $leader_id, PDO::PARAM_INT);
            $pStmt->bindParam(':turn', $turn_number, PDO::PARAM_INT);
            $pStmt->execute();
            $row = $pStmt->fetch(PDO::FETCH_ASSOC);
            $leaderParams = !empty($row['action_params']) ? json_decode($row['action_params'], true) : array();
            if (json_last_error() !== JSON_ERROR_NONE) $params = array();
        } catch (PDOException $e) {
            echo __FUNCTION__."(): SELECT action_params failed: ".$e->getMessage()."<br />";
            continue;
        }

        // Get zones informations for the calculated_defence_val
        try {
            $zStmt = $pdo->prepare("SELECT * FROM {$prefix}zones WHERE id = :zid");
            $zStmt->bindParam(':zid', $zone_id, PDO::PARAM_INT);
            $zStmt->execute();
            $zone = $zStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo __FUNCTION__."(): SELECT zones failed: ".$e->getMessage()."<br />";
            continue;
        }
        $calculated_defence_val = (int) $zone['calculated_defence_val'];

        // Get the claime val
        $claimVal = calculateControllerValue($pdo, 'Claim', $zone_id, $cid);

        $success = ($claimVal - $calculated_defence_val) >= $claimDiff;
        if ($debug) echo sprintf("zone %d c %d : claim_val=%d calculated_defence_val=%d diff=%d => %s<br>",
            $zone_id, $cid, $claimVal, $calculated_defence_val, $claimDiff, $success ? 'WIN' : 'lose');

        $leaderName = '';
        try {
            $nStmt = $pdo->prepare("SELECT CONCAT(firstname, ' ', lastname) AS n FROM {$prefix}workers WHERE id = :wid");
            $nStmt->bindParam(':wid', $leader_id, PDO::PARAM_INT);
            $nStmt->execute();
            $leaderName = (string)($nStmt->fetch(PDO::FETCH_ASSOC)['n'] ?? '');
        } catch (PDOException $e) {
            echo __FUNCTION__."(): SELECT name FROM workers failed: ".$e->getMessage()."<br />";
        }

        // Select other worker of the zone to add the claim to their report.
        try {
            $oStmt = $pdo->prepare("SELECT cw.worker_id, cw.controller_id
                FROM {$prefix}workers w
                JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
                JOIN {$prefix}worker_actions wa ON wa.worker_id = w.id AND wa.turn_number = :turn_number
                WHERE w.zone_id = :zone_id
                  AND cw.controller_id != :controller_id
                  AND wa.action_choice IN (" . "'".implode("','", ACTIVE_ACTIONS)."'" . ")");
            $oStmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
            $oStmt->bindParam(':controller_id', $cid, PDO::PARAM_INT);
            $oStmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
            $oStmt->execute();
            $others = $oStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo __FUNCTION__."(): SELECT enemy workers failed: ".$e->getMessage()."<br />";
            $others = [];
        }

        // All claimer's workers with claim action in this zone get exposed
        // to enemies, not just the leader.
        try {
            $cStmt = $pdo->prepare("SELECT w.id FROM {$prefix}workers w
                JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
                JOIN {$prefix}worker_actions wa ON wa.worker_id = w.id AND wa.turn_number = :turn_number
                WHERE cw.controller_id = :controller_id
                  AND w.zone_id = :zone_id
                  AND wa.action_choice = 'claim'");
            $cStmt->bindParam(':controller_id', $cid, PDO::PARAM_INT);
            $cStmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
            $cStmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
            $cStmt->execute();
            $claimerWorkerIds = array_column($cStmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        } catch (PDOException $e) {
            $claimerWorkerIds = [$leader_id];
        }

        // Get the correct text config textesClaimFailViewArray / textesClaimSuccessViewArray
        // (nom) - %1$s
        // (zone) - %2$s
        $textesView = json_decode(getConfig($pdo, $success ? 'textesClaimSuccessViewArray' : 'textesClaimFailViewArray'), true) ?: [];
        // Per-worker claim_report write (one report per observing enemy worker).
        foreach ($others as $otherWorker) {
            if (!empty($textesView)) {
                $tpl = $textesView[array_rand($textesView)];
                $report = sprintf($tpl, $leaderName, $group['zone_name']).'<br/>';
                updateWorkerAction($pdo, $otherWorker['worker_id'], $turn_number, NULL, ['claim_report' => $report]);
            }
        }
        // CKE entries — one per (distinct observing controller × claimer worker) pair.
        $observerControllerIds = array_unique(array_column($others, 'controller_id'));
        foreach ($observerControllerIds as $observerCid) {
            foreach ($claimerWorkerIds as $cwid) {
                addWorkerToCKE($pdo, (int)$observerCid, (int)$cwid, $turn_number, $zone_id);
            }
        }

        // Get the text for the report
        // (nom) - %1$s
        // (zone) - %2$s
        $textesSelf = json_decode(getConfig($pdo, $success ? 'textesClaimSuccessArray' : 'textesClaimFailArray'), true) ?: [];
        if (!empty($textesSelf)) {
            $tpl = $textesSelf[array_rand($textesSelf)];
            $report = sprintf($tpl, $leaderName, $group['zone_name']);
            updateWorkerAction($pdo, $leader_id, $turn_number, NULL, ['claim_report' => $report]);
        }

        // On success update the zone
        if ($success) {
            // Holder = leader's controller. Claimer defaults to the same,
            // but the leader's action_params.claim_controller_id can override
            // (e.g. claim on behalf of another controller, or claim as
            // unowned via the 'null' sentinel string). Parity with mode A.
            $claimer_controller_id = $cid;
            if (!empty($leaderParams['claim_controller_id'])) {
                $claimer_controller_id = $leaderParams['claim_controller_id'];
                if ($leaderParams['claim_controller_id'] === 'null') $claimer_controller_id = null;
            }
            try {
                $uStmt = $pdo->prepare("UPDATE {$prefix}zones
                    SET claimer_controller_id = :claimer, holder_controller_id = :holder
                    WHERE id = :zid");
                $uStmt->bindParam(':claimer', $claimer_controller_id, PDO::PARAM_INT);
                $uStmt->bindParam(':holder', $cid, PDO::PARAM_INT);
                $uStmt->bindParam(':zid', $zone_id, PDO::PARAM_INT);
                $uStmt->execute();
            } catch (PDOException $e) {
                echo __FUNCTION__."(): UPDATE zones failed: ".$e->getMessage()."<br />";
            }
        }
    }

    echo '<p>claimMechanic_workerLeader : DONE</p> </div>';
    return true;
}

