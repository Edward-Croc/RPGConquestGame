<?php

/**
 * Search database for all workers in attack mode and return their targets and combat power differences.
 */
function getAttackerComparisons($pdo, $turn_number = NULL, $attacker_id = NULL) {
    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true') $debug = TRUE;

    // Check turn number is selected
    if (empty($turn_number)) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
    }
    if ($debug) echo "turn_number : $turn_number <br>";

    try{
        // Define the SQL query to get all attackers for the turn
        $sql = "SELECT
                wa.worker_id AS attacker_id,
                wa.action_params AS params,
                wa.controler_id,
                wa.zone_id,
                wa.turn_number
            FROM
                worker_actions wa
            WHERE
                wa.action_choice IN ('attack')
                AND turn_number = :turn_number";

        // Add Limit to only 1 caracter
        if ( !EMPTY($attacker_id) ) $sql .= " AND s.attacker_id = :attacker_id";

        // Prepare and execute the statement
        $stmt = $pdo->prepare($sql);
        if ( !EMPTY($attacker_id) ) $stmt->bindParam(':attacker_id', $attacker_id);
        $stmt->bindParam(':turn_number', $turn_number);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Failed to SELECT list of attackers: " . $e->getMessage() . "<br />";
    }

    $attackersActionArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($debug)
        echo sprintf("attackersActionArray : %s <br/>", var_export($attackersActionArray, true));

    $attackArray = array();
    // For each attacker we get the targets of the action
    foreach ($attackersActionArray AS $attackAction){
        if (!empty($attackAction['params'])) {
            $attackArray[$attackAction['attacker_id']]=array();
            $attackParams = json_decode($attackAction['params'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
                $attackParams = array();
            }
            foreach($attackParams AS $param){
                // If the attacker targets the network
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
                        echo __FUNCTION__."(): Failed to SELECT list of attackers for network : " . $e->getMessage() . "<br />";
                    }
                    $networkWorkersList = $stmtNetworkSearch->fetchAll(PDO::FETCH_COLUMN);
                    if ($debug)
                        echo sprintf("networkWorkersList : %s <br/>", var_export($networkWorkersList, true));
                    foreach($networkWorkersList AS $woker_id){
                        if (!in_array($woker_id, $attackArray[$attackAction['attacker_id']]) ) {
                            $attackArray[$attackAction['attacker_id']][] = $woker_id;
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

    if ($debug)
        echo sprintf("attackArray : %s <br/>", var_export($attackArray, true));

    // Build SQL to compare attacker value to target value
    $sqlValCompare = "
    WITH attacker AS (
        SELECT
            wa.worker_id AS attacker_id,
            CONCAT(w.firstname, ' ', w.lastname) AS attacker_name,
            wa.attack_val AS attacker_attack_val,
            wa.defence_val AS attacker_defence_val,
            wa.controler_id AS attacker_controler_id,
            CONCAT(c.firstname, ' ', c.lastname) AS attacker_controler_name,
            wa.turn_number
        FROM
            worker_actions wa
            JOIN workers w ON w.id = wa.worker_id
            JOIN controlers c ON wa.controler_id = c.ID
        WHERE
            wa.worker_id = :attacker_id
            AND wa.turn_number = :turn_number
    ),
     defenders AS (
        SELECT
        wa.worker_id AS defender_id,
        wa.attack_val AS defender_attack_val,
        wa.defence_val AS defender_defence_val,
        CONCAT(w.firstname, ' ', w.lastname) AS defender_name,
        wo.id AS defender_origin_id,
        wo.name AS defender_origin_name,
        cw.controler_id as defender_controler_id,
        z.id AS zone_id,
        z.name AS zone_name
        FROM workers w
        JOIN zones z ON z.id = w.zone_id
        JOIN worker_actions wa ON
            w.id = wa.worker_id AND wa.turn_number = :turn_number AND wa.action_choice not in ('captured', 'dead')
        JOIN worker_origins wo ON wo.id = w.origin_id
        JOIN controler_worker cw ON w.id = cw.worker_id AND is_primary_controler = true
            WHERE w.id IN (%s)
    )
    SELECT
        a.attacker_id,
        a.attacker_name,
        a.attacker_attack_val,
        a.attacker_defence_val,
        a.attacker_controler_id,
        a.attacker_controler_name,
        a.turn_number,
        d.defender_id,
        d.defender_attack_val,
        d.defender_defence_val,
        d.defender_name,
        d.defender_origin_id,
        d.defender_origin_name,
        d.defender_controler_id,
        d.zone_id,
        d.zone_name,
        cke.id AS defender_knows_enemy,
        (a.attacker_attack_val - d.defender_defence_val) AS attack_difference,
        (d.defender_attack_val - a.attacker_defence_val) AS riposte_difference
        FROM attacker a
        CROSS JOIN defenders d
        LEFT JOIN controlers_known_enemies cke ON cke.controler_id = d.defender_controler_id AND cke.discovered_worker_id = a.attacker_id
    ";

    if ( (bool)getConfig($pdo, 'LIMIT_ATTACK_BY_ZONE') )
        $sqlValCompare = "
        WITH attacker AS (
            SELECT
                wa.worker_id AS attacker_id,
                CONCAT(w.firstname, ' ', w.lastname) AS attacker_name,
                wa.attack_val AS attacker_attack_val,
                wa.defence_val AS attacker_defence_val,
                wa.controler_id AS attacker_controler_id,
                wa.zone_id,
                CONCAT(c.firstname, ' ', c.lastname) AS attacker_controler_name
            FROM
                worker_actions wa
                JOIN workers w ON w.id = wa.worker_id
                JOIN controlers c ON wa.controler_id = c.ID
            WHERE
                wa.worker_id = :attacker_id
                AND turn_number = :turn_number
        )
        SELECT
            a.attacker_id,
            a.attacker_name,
            a.attacker_attack_val,
            a.attacker_defence_val,
            a.attacker_controler_id,
            a.attacker_controler_name,
            z.id AS zone_id,
            z.name AS zone_name,
            wa.turn_number,
            wa.worker_id AS defender_id,
            wa.attack_val AS defender_attack_val,
            wa.defence_val AS defender_defence_val,
            CONCAT(w.firstname, ' ', w.lastname) AS defender_name,
            wo.id AS defender_origin_id,
            wo.name AS defender_origin_name,
            cw.controler_id as defender_controler_id,
            (a.attacker_attack_val - wa.defence_val) AS attack_difference,
            (wa.attack_val - a.attacker_defence_val) AS riposte_difference,
            cke.id AS defender_knows_enemy
        FROM attacker a
        JOIN zones z ON z.id = a.zone_id
        JOIN worker_actions wa ON
                a.zone_id = wa.zone_id AND wa.turn_number = :turn_number
        JOIN workers w ON wa.worker_id = w.ID
        JOIN worker_origins wo ON wo.id = w.origin_id
        JOIN controler_worker cw ON wa.worker_id = cw.worker_id AND is_primary_controler = true
        LEFT JOIN controlers_known_enemies cke ON cke.controler_id = cw.controler_id AND cke.discovered_worker_id = a.attacker_id
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
            echo __FUNCTION__."():Failed to SELECT compare attackers to defenders : " . $e->getMessage() . "<br />";
        }
        $final_attacks_aggregate[$compared_attacker_id] = $stmtValCompare->fetchAll(PDO::FETCH_ASSOC);
        if ($debug)
            echo sprintf("final_attacks_aggregate : %s <br/>", var_export($final_attacks_aggregate[$compared_attacker_id], true));
    }
    return $final_attacks_aggregate;

}

/**
* Main function to calculate attack results.
*/
function attackMecanic($pdo){
    echo '<div> <h3>  attackMecanic : </h3> ';

    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true') $debug = TRUE;
    $ATTACKDIFF0 = getConfig($pdo, 'ATTACKDIFF0');
    $ATTACKDIFF1 = getConfig($pdo, 'ATTACKDIFF1');
    $RIPOSTDIFF = getConfig($pdo, 'RIPOSTDIFF');
    $RIPOSTACTIVE = getConfig($pdo, 'RIPOSTACTIVE');

    if ($debug) {
        echo "ATTACKDIFF0 : $ATTACKDIFF0 <br/>";
        echo "ATTACKDIFF1 : $ATTACKDIFF1 <br/>";
        echo "RIPOSTDIFF : $RIPOSTDIFF <br/>";
        echo "RIPOSTACTIVE : $RIPOSTACTIVE <br/>";
    }

    $attacksArray = getAttackerComparisons($pdo, NULL, NULL);
    if ($debug)
        echo sprintf("attacksArray : %s <br/>", var_export($attacksArray, true));
    if (empty($attacksArray)) { echo 'All is calm </div>'; return TRUE;}

    $workerDisappearanceTexts = json_decode(getConfig($pdo,'workerDisappearanceTexts'), true);
    $attackSuccessTexts = json_decode(getConfig($pdo,'attackSuccessTexts'), true);
    $captureSuccessTexts = json_decode(getConfig($pdo,'captureSuccessTexts'), true);
    $failedAttackTextes = json_decode(getConfig($pdo,'failedAttackTextes'), true);
    $escapeTextes = json_decode(getConfig($pdo,'escapeTextes'), true);
    $textesAttackFailedAndCountered = json_decode(getConfig($pdo,'textesAttackFailedAndCountered'), true);
    $counterAttackTexts = json_decode(getConfig($pdo,'counterAttackTexts'), true);

    foreach ($attacksArray as $attacker_id => $defenders) {
        // Build report :
        if ($debug)
            echo sprintf("attacker_id: %s =>row %s <br/>", $attacker_id, var_export($defenders, true));
        foreach ($defenders as $defender) {
            $attackerReport= array();
            $defenderReport= array();
            $defender_status = NULL;
            $attacker_status = NULL;
            $survived = true;
            $is_alive = NULL;
            if ($defender['attack_difference'] >= (INT)$ATTACKDIFF0 ){
                echo $defender['defender_name']. ' HAS DIED !';
                $survived = false;
                $defender_status = 'dead';
                $is_alive = FALSE;
                $attackerReport['attack_report'] = sprintf($attackSuccessTexts[array_rand($attackSuccessTexts)], $defender['defender_name']);
                $defenderReport['life_report'] = sprintf($workerDisappearanceTexts[array_rand($workerDisappearanceTexts)], $defender['turn_number'] );
                if ($defender['attack_difference'] >= (INT)$ATTACKDIFF1 ){
                    $is_alive = NULL;
                    echo $defender['defender_name']. ' Was Captured !';
                    $defender_status = 'captured';
                    $attackerReport['attack_report'] = sprintf($captureSuccessTextes[array_rand($captureSuccessTextes)], $defender['defender_name']);
                    // in controler_worker update defender_controler_id, defender_id, is_primary_controler = false
                    $stmt = $pdo->prepare("UPDATE controler_worker SET is_primary_controler = :is_primary WHERE controler_id = :controler_id AND worker_id = :worker_id");
                    $stmt->execute([
                        'controler_id' => $defender['defender_controler_id'],
                        'worker_id' => $defender['defender_id'],
                        'is_primary' => 0
                    ]);
                    // in controler_worker insert attacker_controler_id, defender_id, is_primary_controler = true
                    $stmt = $pdo->prepare("INSERT INTO controler_worker (controler_id, worker_id, is_primary_controler) VALUES (:controler_id, :worker_id, :is_primary)");
                    $stmt->execute([
                        'controler_id' => $defender['attacker_controler_id'],
                        'worker_id' => $defender['defender_id'],
                        'is_primary' => 1
                    ]);
                }
                updateWorkerActiveStatus($pdo, $defender['defender_id']);
                updateWorkerAliveStatus($pdo, $defender['defender_id'], $is_alive);
            } else {
                echo $defender['defender_name']. ' Escaped !';
                $attackerReport['attack_report'] = sprintf($failedAttackTextes[array_rand($failedAttackTextes)], $defender['defender_name']);
                // Check if attaker is in know ennemies
                try{
                    $knownEnemyControler = '';
                    $sql_known_ennemies = sprintf(
                        "SELECT * FROM controlers_known_enemies WHERE controler_id = %s AND discovered_worker_id = %s AND zone_id = %s",
                        $defender['defender_controler_id'], $defender['attacker_id'],  $defender['zone_id'] );
                    $stmtA = $pdo->prepare($sql_known_ennemies);
                    $stmtA->execute();
                    $knownEnemy = $stmtA->fetchAll(PDO::FETCH_ASSOC); // Only return worker IDs
                    if (!empty($knownEnemy)) {
                        // Yes and is controler known ? Add to report
                        if (!empty($knownEnemy[0]['discovered_controler_id'])) {
                            $knownEnemyControler .= sprintf(' du résseau %s', $knownEnemy[0]['discovered_controler_id']);
                        }
                        if (!empty($knownEnemy[0]['discovered_controler_name'])) {
                            $knownEnemyControler .= sprintf(' des agents de %s', $knownEnemy[0]['discovered_controler_name']);
                        }
                    } else {
                        // No Add to know ennemies
                        $sqlInsert = sprintf(
                            "INSERT INTO controlers_known_enemies (controler_id, discovered_worker_id, first_discovery_turn, last_discovery_turn, zone_id) VALUES (%s, %s, %s, %s, %s)",
                            $defender['defender_controler_id'], $defender['attacker_id'], $defender['turn_number'], $defender['turn_number'], $defender['zone_id'] );
                        $stmtInsert = $pdo->prepare($sqlInsert);
                        $stmtInsert->execute();
                    }
                } catch (PDOException $e) {
                    echo __FUNCTION__." (): Error fetching/inserting enemy workers: " . $e->getMessage();
                    return [];
                }
                $defenderReport['life_report'] = sprintf($escapeTextes[array_rand($escapeTextes)], sprintf("%s(%s)%s",$defender['attacker_name'], $defender['attacker_id'], $knownEnemyControler));
            }
            if ($debug)
                echo sprintf("(survived  : %s <br/>", ($survived ));
            if ( $RIPOSTACTIVE!= '0' && $survived  && $defender['riposte_difference'] >= (INT)$RIPOSTDIFF ){
                $attacker_status = 'dead';
                echo $defender['defender_name']. ' RIPOSTE !';
                $attackerReport['attack_report'] = sprintf($textesAttackFailedAndCountered[array_rand($textesAttackFailedAndCountered)], $defender['defender_name']);
                $attackerReport['life_report'] = sprintf($workerDisappearanceTexts[array_rand($workerDisappearanceTexts)], $defender['turn_number'] );
                updateWorkerActiveStatus($pdo, $defender['attacker_id']);
                updateWorkerAliveStatus($pdo, $defender['attacker_id']);
            }
            updateWorkerAction($pdo, $defender['attacker_id'], $defender['turn_number'], $attacker_status, $attackerReport );
            updateWorkerAction($pdo, $defender['defender_id'], $defender['turn_number'], $defender_status , $defenderReport );
        }
    }

    echo '</div>';
    return TRUE;
}