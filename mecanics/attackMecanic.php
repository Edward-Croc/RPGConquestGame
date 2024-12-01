<?php

function getAttackerComparisons($pdo, $turn_number = NULL, $attacker_id = NULL, $threshold = 0 ) {
    if (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true') $debug = TRUE;

    if (empty($turn_number)) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
    }
    echo "turn_number : $turn_number <br>";
    try{
        // Define the SQL query
        $sql = "SELECT
                wa.worker_id AS attacker_id,
                wa.action_params AS params,
                wa.controler_id,
                wa.zone_id
            FROM
                worker_actions wa
            WHERE
                wa.action IN ('attack')
                AND turn_number = :turn_number";

        // Add Limit to only 1 caracter
        if ( !EMPTY($attacker_id) ) $sql .= " AND s.attacker_id = :attacker_id";

        // Prepare and execute the statement
        $stmt = $pdo->prepare($sql);
        if ( !EMPTY($attacker_id) ) $stmt->bindParam(':attacker_id', $attacker_id);
        $stmt->bindParam(':turn_number', $turn_number);
        $stmt->execute();
    } catch (PDOException $e) {
        echo "Failed to SELECT list of attackers: " . $e->getMessage() . "<br />";
    }
    $attackersActionArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($debug)
        echo sprintf("attackersActionArray : %s <br/>", var_export($attackersActionArray, true));

    $attackArray = array();
    foreach ($attackersActionArray AS $attackAction){
        if (!empty($attackAction['params'])) {
            $attackArray[$attackAction['attacker_id']]=array();
            $attackParams = json_decode($attackAction['params'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "JSON decoding error: " . json_last_error_msg() . "<br />";
            }
            foreach($attackParams AS $param){
                if ($param['attackScope'] == 'network'){
                    try{
                        $sqlNetworkSearch ="SELECT discovered_worker_id FROM controlers_known_enemies
                            WHERE zone_id = :zone_id AND discovered_controler_id = :network_id AND controler_id = :controler_id";
                        $stmtNetworkSearch = $pdo->prepare($sqlNetworkSearch);
                        $stmtNetworkSearch->bindParam(':network_id', $param['attackID']);
                        $stmtNetworkSearch->bindParam(':zone_id', $attackAction['zone_id']);
                        $stmtNetworkSearch->bindParam(':controler_id', $attackAction['controler_id']);
                        $stmtNetworkSearch->execute();
                    } catch (PDOException $e) {
                        echo "Failed to SELECT list of attackers for network : " . $e->getMessage() . "<br />";
                    }
                    $networkWorkersList = $stmtNetworkSearch->fetchAll(PDO::FETCH_COLUMN);
                    if ($debug)
                        echo sprintf("networkWorkersList : %s <br/>", var_export($networkWorkersList, true));
                    foreach($networkWorkersList AS $woker_id){
                        $attackArray[$attackAction['attacker_id']][] = $woker_id;
                    }
                } elseif ($param['attackScope'] == 'worker') {
                    if (in_array($param['attackID'], $attackArray) ) {
                        $attackArray[$attackAction['attacker_id']][] = $param['attackID'];
                    }
                }
            }
        }
    }

    if ($debug)
        echo sprintf("attackArray : %s <br/>", var_export($attackArray, true));
    // Fetch and return the

    $sqlValCompare = "
    WITH attackers AS (
        SELECT
            wa.worker_id AS attacker_id,
            wa.action_val AS attacker_action_val,
            wa.defence_val AS attacker_defence_val,
            wa.zone_id
        FROM
            worker_actions wa
        WHERE
            wa.worker_id = :attacker_id
            AND turn_number = :turn_number
    )
    SELECT
        a.attacker_id,
        a.attacker_action_val,
        a.attacker_defence_val,
        z.id AS zone_id,
        z.name AS zone_name,
        wa.worker_id AS attacked_id,
        wa.action_val AS attacked_action_val,
        wa.defence_val AS attacked_defence_val,
        CONCAT(w.firstname, ' ', w.lastname) AS attacked_name,
        wo.id AS attacked_origin_id,
        wo.name AS attacked_origin_name,
        (a.attacker_action_val - wa.defence_val) AS attack_difference,
        (wa.action_val - a.attacker_defence_val) AS riposte_difference
    FROM attackers a
    JOIN zones z ON z.id = a.zone_id
    JOIN worker_actions wa ON
            a.zone_id = wa.zone_id AND turn_number = :turn_number
    JOIN workers w ON wa.worker_id = w.ID
    JOIN worker_origins wo ON wo.id = w.origin_id
    WHERE w.id IN (%s)
    ";
    $final_attacks_aggregate = array();
    foreach ($attackArray AS $compared_attacker_id => $defender_ids ) {
        try {
            if ($debug)
                echo sprintf("compared_attacker_id : %s => defender_ids : %s <br/>", $compared_attacker_id, var_export($defender_ids, true));
            
            $stmtValCompare = $pdo->prepare(
                sprintf($sqlValCompare, implode(',', $defender_ids))
            );
            $stmtValCompare->bindParam(':turn_number', $turn_number);
            $stmtValCompare->bindParam(':attacker_id', $compared_attacker_id);
            $stmtValCompare->execute();
        } catch (PDOException $e) {
            echo "Failed to SELECT compare attackers to defenders : " . $e->getMessage() . "<br />";
        }
        $final_attacks_aggregate[$compared_attacker_id] = $stmtValCompare->fetchAll(PDO::FETCH_ASSOC);
        if ($debug)
            echo sprintf("final_attacks_aggregate : %s <br/>", var_export($final_attacks_aggregate[$compared_attacker_id], true));
    }
    return $final_attacks_aggregate;

}


function attackMecanic($pdo){
    echo '<div> <h3>  attackMecanic : </h3> ';

    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true') $debug = TRUE;
    $ATTACKDIFF0 = getConfig($pdo, 'ATTACKDIFF0');
    $ATTACKDIFF1 = getConfig($pdo, 'ATTACKDIFF1');
    $RIPOSTDIFF3 = getConfig($pdo, 'RIPOSTDIFF3');
    $RIPOSTONDEATH = getConfig($pdo, 'RIPOSTONDEATH');
    if ($debug) {
        echo "ATTACKDIFF0 : $ATTACKDIFF0 <br/>";
        echo "ATTACKDIFF1 : $ATTACKDIFF1 <br/>";
        echo "RIPOSTDIFF3 : $RIPOSTDIFF3 <br/>";
        echo "REPORTFDIFF1 : $REPORTFDIFF1 <br/>";
    }

    $attacksArray = getAttackerComparisons($pdo);
    if ($debug)
        echo sprintf("attacksArray : %s <br/>", var_export($attacksArray, true));
    if (empty($attacksArray)) { echo 'All is calm </div>'; return TRUE;}
    foreach ($attacksArray as $attacker_id => $defenders) {
        // Build report :
        if ($debug)
            echo sprintf("attacker_id: %s =>row %s <br/>", $attacker_id, var_export($defenders, true));
        foreach ($defenders as $defender) {
            if ($defender['attack_difference'] < 1 ){
                echo $defender['attacked_name']. ' Escaped !';
            }
            if ($defender['attack_difference'] >= 1 ){
                echo $defender['attacked_name']. ' HAS DIED !';
            }
            if ($defender['riposte_difference'] >= 1 ){
                echo $defender['attacked_name']. ' Riposte !';
            }
        }
    }

    echo '</div>';
}