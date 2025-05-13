<?php

require_once '../mecanics/attackMecanic.php';
require_once '../mecanics/investigateMecanic.php';
require_once '../mecanics/aiMecanic.php';
require_once '../mecanics/locationSearchMecanic.php';

/** 
 * Build base randomization SQL
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
 */
function diceRoll() {
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

        // TODO if worker is in zone held by controler give bonus from config ??

        // build SQL for value math adding bonuses from powers, and previous value
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
        )",
        $elements[0], $elements[0], $valBaseSQL );

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
                    controler_worker cw ON cw.worker_id = w.id AND cw.is_primary_controler = TRUE
                WHERE
                    z.holder_controler_id = cw.controler_id
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
 */
function createNewTurnLines($pdo, $turn_number){
    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true') $debug = TRUE;
    echo '<div> <h3>  createNewTurnLines : </h3> ';
    $sqlInsert = "
        INSERT INTO worker_actions (worker_id, turn_number, zone_id, controler_id, action_choice, action_params)
        SELECT
            w.id AS worker_id,
            :turn_number AS turn_number,
            w.zone_id AS zone_id,
            cw.controler_id AS controler_id,
            wa.action_choice,
            wa.action_params
        FROM workers w
        JOIN controler_worker AS cw ON cw.worker_id = w.id AND is_primary_controler = true
        JOIN worker_actions AS wa ON wa.worker_id = w.id AND turn_number = :turn_number_n_1
    ";
    try {
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmtInsert->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
        $stmtInsert->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql INSERT FAILED : ".$e->getMessage()."<br/> sql: $sql<br/>";
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
            echo __FUNCTION__." (): sql UPDATE investigate FAILED : ".$e->getMessage()."<br />$sql<br/>";
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
            echo __FUNCTION__." (): sql UPDATE claim FAILED : ".$e->getMessage()."<br />$sql<br/>";
            return FALSE;
        }
    }
/*
    $sqlSetDead = "
        UPDATE worker_actions SET action_choice = 'dead' WHERE turn_number = :turn_number AND worker_id IN (
            SELECT w.id FROM workers w WHERE is_alive = false AND is_active = false
        )
    ";
    try {
        $stmtSetDead = $pdo->prepare($sqlSetDead);
        $stmtSetDead->execute([':turn_number' => $turn_number]);
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql UPDATE dead FAILED : ".$e->getMessage()."<br />$sql<br/>";
        return FALSE;
    }
    $sqlSetCaptured = "
        UPDATE worker_actions SET action_choice = 'captured' WHERE turn_number = :turn_number AND worker_id IN (
            SELECT worker_id FROM worker_actions WHERE action_choice = 'captured' AND turn_number = :turn_number_n_1
        )
    ";
    try {
        $stmtSetCaptured = $pdo->prepare($sqlSetCaptured);
        $stmtSetCaptured->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmtSetCaptured->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
        $stmtSetCaptured->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql UPDATE captured FAILED : ".$e->getMessage()."<br />$sql<br/>";
        return FALSE;
    }
    */

    echo '<p>DONE</p> </div>';

    return TRUE;
}


function claimMecanic($pdo, $turn_number = NULL, $claimer_id = NULL) {
    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true') $debug = TRUE;

    echo '<div> <h3>  claimMecanic : </h3> ';

    if (empty($turn_number)) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
        echo "turn_number : $turn_number <br>";
    }

    // Define the SQL query
    $sql = "
    WITH claimers AS (
        SELECT
            wa.worker_id AS claimer_id,
            wa.controler_id AS claimer_controler_id,
            wa.enquete_val AS claimer_enquete_val,
            wa.attack_val AS claimer_attack_val,
            wa.action_params AS claimer_params,
            wa.zone_id AS claimer_zone
        FROM
            worker_actions wa
        WHERE
            wa.action_choice = 'claim'
            AND wa.turn_number = :turn_number
    )
    SELECT
        c.claimer_id,
        c.claimer_enquete_val,
        c.claimer_attack_val,
        c.claimer_params,
        c.claimer_controler_id,
        z.id AS zone_id,
        z.name AS zone_name,
        z.holder_controler_id AS holder_controler_id,
        (c.claimer_enquete_val - z.calculated_defence_val) AS discrete_claim,
        (c.claimer_attack_val - z.calculated_defence_val) AS violent_claim
    FROM
        claimers c
    JOIN zones z ON z.id = c.claimer_zone
    ORDER BY z.id, c.claimer_attack_val DESC
    ";
    if ( !EMPTY($searcher_id) ) $sql .= " AND w.worker_id = :worker_id";
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
        // claims are generally violent
        $arrayZoneInfo[$claimer['zone_id']]['is_violent_claim'] = TRUE;
        // claim are not generally successful
        $success = FALSE;

        // if its the 1st and only claim for a previously unclaimed zone then
        if ($claimer['holder_controler_id'] == NULL 
            && empty($zoneInfo[$claimer['zone_id']]) 
            && !empty($claimerArray[$key+1])
            && $claimerArray[$key+1]['zone_id'] != $claimer['zone_id']
        ){
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
            // if no successful claimer has been found yet
            if ( empty($zoneInfo[$claimer['zone_id']]) ) {
                // if discrete_claim or violent_claim is sufficient to claim
                if ( (INT)$claimer['discrete_claim'] >= $DISCRETECLAIMDIFF || (INT)$claimer['violent_claim'] >= $VIOLENTCLAIMDIFF ) {
                    // mark as success
                    $success = TRUE;
                    // Save claimer info
                    $arrayZoneInfo[$claimer['zone_id']]['claimer'] = $claimer;
                }
            }
        }

        // TODO: Warn controlers of workers that violence happened and if it was successful or not
        if ($arrayZoneInfo[$claimer['zone_id']]['is_violent_claim']) {
            // get workers of zone
            // add description of violent claim to report  $success
            // update controler_known_enemies for controlers of workers in zone
            // with network
        }
    }

    foreach ( $arrayZoneInfo as $key => $zoneInfo ) {
        if ( ! empty($zoneInfo['claimer']) )  {
            if ($debug) echo "zoneInfo['claimer']['claimer_params'] :". var_export($zoneInfo['claimer']['claimer_params'], true);

            $claimer_params = json_decode($zoneInfo['claimer']['claimer_params'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
                $claimer_params = array();
            }
            if ($debug) echo "claimer_params :". var_export($claimer_params, true);

            $holder_controler_id = $zoneInfo['claimer']['claimer_controler_id'];
            if ( !empty($claimer_params['claim_controler_id'])) $holder_controler_id = $claimer_params['claim_controler_id'];
            
            $sql = sprintf(
                "UPDATE zones SET claimer_controler_id = %s , holder_controler_id = %s WHERE id = %s",
                    $zoneInfo['claimer']['claimer_controler_id'], $holder_controler_id , $zoneInfo['claimer']['zone_id']
            );
            try{
                // Update config value in the database
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
            } catch (PDOException $e) {
                echo __FUNCTION__."(): UPDATE zones Failed: " . $e->getMessage()."<br />";
            }
            echo $sql. "</br>";
        }
        else { echo "Zone $key Unclaimed";}
    }

    echo '<p>DONE</p> </div>';

    return TRUE;
}

