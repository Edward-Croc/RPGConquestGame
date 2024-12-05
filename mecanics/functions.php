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
            /*$valBaseSQL = "FLOOR(
                RANDOM() *
                ((SELECT CAST(value AS INT) FROM config WHERE name = 'MAXROLL') -
                (SELECT CAST(value AS INT) FROM config WHERE name = 'MINROLL') + 1)
                + (SELECT CAST(value AS INT) FROM config WHERE name = 'MINROLL')
                )";*/
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
    echo '</p></div>';
    return TRUE;
}

function createNewTurnLines($pdo, $turn_number){
    echo '<div> <h3>  createNewTurnLines : </h3> ';
    $turn_number = 2; // Example turn number
    $sql = "
        INSERT INTO worker_actions (worker_id, turn_number, zone_id, controler_id)
        SELECT
            w.id AS worker_id,
            :turn_number AS turn_number,
            w.zone_id AS zone_id,
            cw.controler_id AS controler_id
        FROM workers w
        JOIN controler_worker AS cw ON cw.worker_id = w.id AND is_primary_controler = true;
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':turn_number' => $turn_number]);
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
        return FALSE;
    }
    $sqlSetClaim = "
        UPDATE worker_actions SET action_choice = 'claim' WHERE turn_number = :turn_number AND worker_id IN (
            SELECT worker_id FROM worker_actions wa WHERE action_choice = 'claim' AND turn_number = :turn_number_n_1
        )
    ";
    try {
        $stmtSetClaim = $pdo->prepare($sqlSetClaim);
        $stmtSetClaim->bindParam(':turn_number_n_1', $turn_number-1, PDO::PARAM_INT);
        $stmtSetClaim->execute([':turn_number' => $turn_number]);
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
        return FALSE;
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
        echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
        return FALSE;
    }
    $sqlSetCaptured = "
        UPDATE worker_actions SET action_choice = 'captured' WHERE turn_number = :turn_number AND worker_id IN (
            SELECT worker_id FROM worker_actions WHERE action_choice = 'captured' AND turn_number = :turn_number_n_1
    ";
    try {
        $stmtSetCaptured = $pdo->prepare($sqlSetCaptured);
        $stmtSetCaptured->bindParam(':turn_number', $turn_number);
        $stmtSetCaptured->bindParam(':turn_number_n_1', $turn_number-1, PDO::PARAM_INT);
        $stmtSetCaptured->execute([':turn_number' => $turn_number]);
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
        return FALSE;
    }

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
    WITH (
        SELECT
            wa.worker_id AS claimer_id,
            wa.controler_id AS claimer_controler_id,
            wa.enquete_val AS claimer_enquete_val,
            wa.attack_val AS claimer_attack_val,
            wa.action_params AS claimer_params
            wa.zone_id AS claimer_zone
        FROM
            worker_actions wa
        WHERE
            wa.action_choice IN ('claim')
            AND turn_number = :turn_number
    )
    SELECT
        c.claimer_id,
        c.claimer_enquete_val,
        c.claimer_attack_val,
        c.claimer_params,
        c.claimer_controler_id,
        z.id AS zone_id,
        z.name AS zone_name,
        (c.claimer_enquete_val - z.defence_val) AS discrete_claim,
        (c.claimer_attack_val -z.defence_val) AS violent_claim,
        FROM claimer c
        JOIN zones z ON z.id = s.zone_id
        JOIN worker_actions wa ON
                c.zone_id = wa.zone_id AND turn_number = :turn_number AND wa.action_choice IN ('claim')
        WHERE
            c.claimer_id != a.id
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
    echo sprintf("%s(): claimerArray %s", __FUNCTION__, var_export($claimerArray,true));

    return TRUE;
}

