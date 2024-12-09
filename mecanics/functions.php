<?php

require_once '../mecanics/attackMecanic.php';
require_once '../mecanics/investigateMecanic.php';

function diceSQL() {
    return "FLOOR(
        RANDOM() * (
            CAST((SELECT value FROM config WHERE name = 'MAXROLL') AS INT)
            - CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT)
            +  1
        ) + CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT)
    )";
}

function diceRoll() {
    $diceSQL = diceSQL();
    $sql = "SELECT $diceSQL as roll";

    try{
        // Prepare and execute SQL query
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE config Failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    $roll = $stmt->fetchALL(PDO::FETCH_ASSOC);
    return $roll[0]['roll'];
}

function calculateVals($pdo, $turn_number){

    $sqlArray = [];
    $array = [];
    $array[] = ['enquete', 'passiveInvestigateActions',false];
    $array[] = ['enquete', 'activeInvestigateActions',true];
    $array[] = ['attack', 'passiveAttackActions',false];
    $array[] = ['attack', 'activeAttackActions',true];
    $array[] = ['defence', 'passiveDefenceActions',false];
    $array[] = ['defence', 'activeDefenceActions',true];
    echo '<div> calculateVals : <p>';
    foreach ($array as $elements) {

        $valBaseSQL = "(SELECT CAST(value AS INT) FROM config WHERE name = 'PASSIVEVAL')";
        if ($elements[2]) {
            $valBaseSQL = diceSQL();
        }
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

        //
        $config = getConfig($pdo, $elements[1]);
        echo sprintf("Get Config for %s : $config <br /> ", $elements[1]);
        if (!empty($config)){
            $sqlArray[] = sprintf('UPDATE worker_actions SET %1$s
                WHERE turn_number = %2$s AND action_choice IN (%3$s)', $valSQL, $turn_number, $config );
        }
    }
    echo '</p>';
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
    try {
        $sql = "UPDATE zones
            SET calculated_defence_val = defence_val + subquery.worker_count
            FROM (
                SELECT
                    z.id AS zone_id,
                    COALESCE(COUNT(w.id), 0) AS worker_count
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

function createNewTurnLines($pdo, $turn_number){
    echo '<div> <h3>  createNewTurnLines : </h3> ';
    $sqlInsert = "
        INSERT INTO worker_actions (worker_id, turn_number, zone_id, controler_id)
        SELECT
            w.id AS worker_id,
            :turn_number AS turn_number,
            w.zone_id AS zone_id,
            cw.controler_id AS controler_id
        FROM workers w
        JOIN controler_worker AS cw ON cw.worker_id = w.id AND is_primary_controler = true
    ";
    try {
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([':turn_number' => $turn_number]);
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql INSERT FAILED : ".$e->getMessage()."<br/> sql: $sql<br/>";
        return FALSE;
    }

    $config_continuing_investigate_action = getConfig($pdo, 'continuing_investigate_action');
        if ($config_continuing_investigate_action){
        $sqlSetInvestigate = "
            UPDATE worker_actions SET action_choice = 'investigate' WHERE turn_number = :turn_number AND worker_id IN (
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
        if ($config_continuing_claimed_action){
        $sqlSetClaim = "
            UPDATE worker_actions SET action_choice = 'claim' WHERE turn_number = :turn_number AND worker_id IN (
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

    echo '<p>DONE</p> </div>';

    return TRUE;
}


function claimMecanic($pdo, $turn_number = NULL, $claimer_id = NULL){
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
        (c.claimer_enquete_val - z.calculated_defence_val) AS discrete_claim,
        (c.claimer_attack_val - z.calculated_defence_val) AS violent_claim
    FROM
        claimers c
    JOIN zones z ON z.id = c.claimer_zone
--    WHERE c.claimer_controler_id != z.holder_controler_id
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
    echo sprintf("%s(): claimerArray %s </br>", __FUNCTION__, var_export($claimerArray,true));

    $DISCRETECLAIMDIFF = getConfig($pdo, 'DISCRETECLAIMDIFF');
    $VIOLENTCLAIMDIFF = getConfig($pdo, 'VIOLENTCLAIMDIFF');
    foreach( $claimerArray AS $claimer ) {
        if ( (INT)$claimer['discrete_claim'] >= (INT)$DISCRETECLAIMDIFF ) {
            // compare discrete_claim
            $sql = sprintf("UPDATE zone SET claimer_controler_id = %s , holder_controler_id = %s WHERE zone_id = %s", $claimer['claimer_controler_id'], $claimer['claimer_params'], $claimer['zone_id'] );
            echo $sql. "</br>";
            
        } elseif ((INT) $claimer['violent_claim'] >= (INT)$VIOLENTCLAIMDIFF ) {
            // compare violent_claim
            $sql = sprintf("UPDATE zone SET claimer_controler_id = %s, holder_controler_id = %s WHERE zone_id = %s", $claimer['claimer_controler_id'], $claimer['claimer_params'], $claimer['zone_id'] );
            // Warn controlers of worker
            // get workers of zone
            // update controler_known_enemies for controlers of workers in zone
            // with network and name of targer controler
        }
    }
    echo '<p>DONE</p> </div>';

    return TRUE;
}

