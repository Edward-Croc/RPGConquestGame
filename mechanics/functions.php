<?php

require_once '../mechanics/attackMechanic.php';
require_once '../mechanics/investigateMechanic.php';
require_once '../mechanics/aiMechanic.php';
require_once '../mechanics/locationSearchMechanic.php';

/**
 * Build base randomization SQL
 * 
 * @return string
 * 
 */
function diceSQL() {
    return "FLOOR(
        RANDOM() * (
            CAST((SELECT value FROM config WHERE name = 'MAXROLL') AS INT)
            - CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT)
            +  1
        ) + CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT)
    )";
}

/**
 * Return value of a dice roll
 * 
 * @param PDO $pdo : database connection
 * 
 * @return int : rollval
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
 * @param string $turn_number
 * 
 * @return bool : success
 * 
 */
function calculateVals($pdo, $turn_number){

    $sqlArray = [];
    $array = [];
    $array[] = ['enquete', 'passiveInvestigateActions', false];
    $array[] = ['enquete', 'activeInvestigateActions', true];
    $array[] = ['attack', 'passiveAttackActions', false];
    $array[] = ['attack', 'activeAttackActions', true];
    $array[] = ['defence', 'passiveDefenceActions', false];
    $array[] = ['defence', 'activeDefenceActions', true];
    echo '<div> calculateVals : <p>';

    // foreach type of action
    foreach ($array as $elements) {

        // chose SQL for value generation
        $valBaseSQL = "(SELECT CAST(value AS INT) FROM config WHERE name = 'PASSIVEVAL')";
        if ($elements[2]) {
            $valBaseSQL = diceSQL();
        }

        // Nom du bonus zone configuré (ex : ENQUETE_ZONE_BONUS)
        $bonusColumn = strtoupper("{$elements[0]}_zone_bonus");
        $bonusSQL = sprintf("(SELECT CAST(value AS INT) FROM config WHERE name = '%s')", $bonusColumn);

        // Construction de la requête SQL principale avec bonus conditionnel
        $valSQL = sprintf("%s_val = (
            COALESCE((
                SELECT SUM(p.%s)
                FROM workers AS w
                LEFT JOIN worker_powers wp ON w.id = wp.worker_id
                LEFT JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                LEFT JOIN powers p ON lpt.power_id = p.ID
                WHERE worker_actions.worker_id = w.id
            ), 0)
            + %s
            + CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM workers w2
                    JOIN zones z2 ON w2.zone_id = z2.id
                    JOIN controller_worker cw2 ON cw2.worker_id = w2.id AND is_primary_controller = TRUE
                    WHERE w2.id = worker_actions.worker_id
                      AND z2.holder_controller_id = cw2.controller_id
                )
                THEN %s
                ELSE 0
            END
        )",
        $elements[0], $elements[0], $valBaseSQL, $bonusSQL );

        // get list of actions to calibrate
        $config = getConfig($pdo, $elements[1]);
        echo sprintf("Get Config for %s : $config <br /> ", $elements[1]);
        if (!empty($config)){
            // add to list of updates
            $sqlArray[] = sprintf('UPDATE worker_actions SET %1$s
                WHERE turn_number = %2$s AND action_choice IN (%3$s)', $valSQL, $turn_number, $config );
        }
    }
    echo '</p>';

    // Execute SQLs
    foreach ($sqlArray as $sql) {
        echo "<p>DO SQL : <br> $sql <br>";
        try {
            // Prepare and execute SQL query
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
            return FALSE;
        }
        echo "DONE <br></p>";
    }

    echo '<p>';
    // Calculate zone defense values
    try {
        $sql = "UPDATE zones
            SET calculated_defence_val = defence_val + subquery.worker_count
            FROM (
                SELECT
                    z.id AS zone_id,
                    COALESCE(COUNT(w.id)+1, 0) AS worker_count
                FROM
                    zones z
                LEFT JOIN
                    workers w ON w.zone_id = z.id
                LEFT JOIN
                    controller_worker cw ON cw.worker_id = w.id AND cw.is_primary_controller = TRUE
                WHERE
                    z.holder_controller_id = cw.controller_id
                GROUP BY
                    z.id
            ) AS subquery
            WHERE zones.id = subquery.zone_id
        ";
        // Prepare and execute SQL query
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
        return FALSE;
    }

    echo '</p></div>';

    return TRUE;
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
    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true') $debug = TRUE;
    echo '<div> <h3>  createNewTurnLines : </h3> ';
    $sqlInsert = "
        INSERT INTO worker_actions (worker_id, turn_number, zone_id, controller_id, action_choice, action_params)
        SELECT
            w.id AS worker_id,
            :turn_number AS turn_number,
            w.zone_id AS zone_id,
            cw.controller_id AS controller_id,
            wa.action_choice,
            wa.action_params
        FROM workers w
        JOIN controller_worker AS cw ON cw.worker_id = w.id AND is_primary_controller = true
        JOIN worker_actions AS wa ON wa.worker_id = w.id AND turn_number = :turn_number_n_1
    ";
    try {
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmtInsert->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
        $stmtInsert->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql INSERT FAILED : ".$e->getMessage()."<br/> sql: $sqlInsert<br/>";
        return FALSE;
    }

    $config_continuing_investigate_action = getConfig($pdo, 'continuing_investigate_action');
    if (!$config_continuing_investigate_action){
        $sqlSetInvestigate = "
            UPDATE worker_actions SET action_choice = 'passive' WHERE turn_number = :turn_number AND worker_id IN (
                SELECT worker_id FROM worker_actions wa WHERE action_choice = 'investigate' AND turn_number = :turn_number_n_1
            )
        ";
        try {
            $stmtSetInvestigate = $pdo->prepare($sqlSetInvestigate);
            $stmtSetInvestigate->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
            $stmtSetInvestigate->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
            $stmtSetInvestigate->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql UPDATE investigate FAILED : ".$e->getMessage()."<br />$sqlSetInvestigate<br/>";
            return FALSE;
        }
    }
    $config_continuing_claimed_action = getConfig($pdo, 'continuing_claimed_action');
        if (!$config_continuing_claimed_action){
        $sqlSetClaim = "
            UPDATE worker_actions SET action_choice = 'passive' WHERE turn_number = :turn_number AND worker_id IN (
                SELECT worker_id FROM worker_actions wa WHERE action_choice = 'claim' AND turn_number = :turn_number_n_1
            )
        ";
        try {
            $stmtSetClaim = $pdo->prepare($sqlSetClaim);
            $stmtSetClaim->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
            $stmtSetClaim->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
            $stmtSetClaim->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql UPDATE claim FAILED : ".$e->getMessage()."<br />$sqlSetClaim<br/>";
            return FALSE;
        }
    }

    echo '<p>DONE</p> </div>';

    return TRUE;
}

/**
 * 
 * @param PDO $pdo : database connection
 * @param string $turn_number
 * 
 * @return bool : success
 * 
 */
function claimMechanic($pdo, $turn_number = NULL) {
    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true') $debug = TRUE;

    echo '<div> <h3>  claimMechanic : </h3> ';

    if (empty($turn_number)) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];
        echo "turn_number : $turn_number <br>";
    }

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
        FROM worker_actions wa
        JOIN zones z ON z.id = wa.zone_id
        JOIN workers w ON w.id = wa.worker_id
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
        $stmt->bindParam(':turn_number', $turn_number);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
    }
    // Fetch and return the results
    $claimerArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //if ($debug)
    echo sprintf("%s(): claimerArray %s </br>", __FUNCTION__, var_export($claimerArray,true));

    $arrayZoneInfo = array();
    $DISCRETECLAIMDIFF = (INT)getConfig($pdo, 'DISCRETECLAIMDIFF');
    $VIOLENTCLAIMDIFF = (INT)getConfig($pdo, 'VIOLENTCLAIMDIFF');
    $CLAIMTYPEDIFF = $DISCRETECLAIMDIFF - $VIOLENTCLAIMDIFF;

    foreach( $claimerArray AS $key => $claimer ) {
        $worker_id = $claimer['claimer_id'];

        if ($debug)
            echo sprintf("<p> %s(): checking key %s => claimer: %s </br>", __FUNCTION__, $key, var_export($claimer,true));

        // claims are generally violent
        $arrayZoneInfo[$claimer['zone_id']]['is_violent_claim'] = TRUE;
        // claim are not generally successful
        $success = FALSE;

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
                $success = TRUE;
                // Save claimer info
                $arrayZoneInfo[$claimer['zone_id']]['claimer'] = $claimer;
                // Check if it is exceptionnaly a discret claim
                if ( (INT)$claimer['discrete_claim'] >= $DISCRETECLAIMDIFF )
                    $arrayZoneInfo[$claimer['zone_id']]['is_violent_claim'] = FALSE;
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
                    $success = TRUE;
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
                FROM workers w
                JOIN controller_worker cw ON cw.worker_id = w.id
                WHERE
                    w.zone_id = :zone_id
                    AND cw.controller_id != :controller_id
                    AND w.is_active = True";
            try {
                $stmt = $pdo->prepare($sql_workers_by_zone);
                $stmt->bindParam(':zone_id', $claimer['zone_id'], PDO::PARAM_INT);
                $stmt->bindParam(':controller_id', $claimer['claimer_controller_id'], PDO::PARAM_INT);
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
                if ($claimer_params['claim_controller_id'] == 'null') $claimer_controller_id = Null;
            }

            $sql = "UPDATE zones SET claimer_controller_id = :claimer_controller_id , holder_controller_id = :holder_controller_id WHERE id = :id";
            try{
                // Update config value in the database
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $zoneInfo['claimer']['zone_id'], PDO::PARAM_INT);
                $stmt->bindParam(':holder_controller_id', $zoneInfo['claimer']['claimer_controller_id'], PDO::PARAM_INT);
                $stmt->bindParam(':claimer_controller_id', $claimer_controller_id);
                $stmt->execute();
            } catch (PDOException $e) {
                echo __FUNCTION__."(): UPDATE zones Failed: " . $e->getMessage()."<br />";
            }
            echo $sql. "</br>";
        }
        else { echo "Zone $key Unclaimed. <br />";}
    }

    echo '<p>DONE</p> </div>';

    return TRUE;
}

