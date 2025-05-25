<?php

/**
 * Search database for all workers in attack mode and return their targets and combat power differences.
 * 
 * @param PDO $pdo : database connection
 * @param string|null $turn_number
 * @param int|null $attacker_id
 * 
 * @return array $final_attacks_aggregate
 * 
 */
function getAttackerComparisons($pdo, $turn_number = NULL, $attacker_id = NULL) {
    $debug = false;
    if (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true') $debug = true;

    // Check turn number is selected
    if (empty($turn_number)) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];
    }
    if ($debug) echo "turn_number : $turn_number <br>";

    try{
        // Define the SQL query to get all attackers for the turn
        $sql = "SELECT
                wa.worker_id AS attacker_id,
                wa.action_params AS params,
                wa.controller_id,
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
                        $sqlNetworkSearch ="SELECT discovered_worker_id FROM controllers_known_enemies
                            WHERE zone_id = :zone_id AND discovered_controller_id = :network_id AND controller_id = :controller_id";
                        $stmtNetworkSearch = $pdo->prepare($sqlNetworkSearch);
                        $stmtNetworkSearch->bindParam(':network_id', $param['attackID']);
                        $stmtNetworkSearch->bindParam(':zone_id', $attackAction['zone_id']);
                        $stmtNetworkSearch->bindParam(':controller_id', $attackAction['controller_id']);
                        $stmtNetworkSearch->execute();
                    } catch (PDOException $e) {
                        echo __FUNCTION__."(): Failed to SELECT list of attackers for network : " . $e->getMessage() . "<br />";
                    }
                    $networkWorkersList = $stmtNetworkSearch->fetchAll(PDO::FETCH_COLUMN);
                    if ($debug)
                        echo sprintf("networkWorkersList : %s <br/>", var_export($networkWorkersList, true));
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
            wa.controller_id AS attacker_controller_id,
            CONCAT(c.firstname, ' ', c.lastname) AS attacker_controller_name,
            wa.turn_number
        FROM
            worker_actions wa
            JOIN workers w ON w.id = wa.worker_id
            JOIN controllers c ON wa.controller_id = c.ID
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
        cw.controller_id as defender_controller_id,
        z.id AS zone_id,
        z.name AS zone_name
        FROM workers w
        JOIN zones z ON z.id = w.zone_id
        JOIN worker_actions wa ON
            w.id = wa.worker_id AND wa.turn_number = :turn_number AND wa.action_choice not in ('captured', 'dead')
        JOIN worker_origins wo ON wo.id = w.origin_id
        JOIN controller_worker cw ON w.id = cw.worker_id AND is_primary_controller = true
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
        LEFT JOIN controllers_known_enemies cke ON cke.controller_id = d.defender_controller_id AND cke.discovered_worker_id = a.attacker_id
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
                worker_actions wa
                JOIN workers w ON w.id = wa.worker_id
                JOIN controllers c ON wa.controller_id = c.ID
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
        JOIN zones z ON z.id = a.zone_id
        JOIN worker_actions wa ON
                a.zone_id = wa.zone_id AND wa.turn_number = :turn_number
        JOIN workers w ON wa.worker_id = w.ID
        JOIN worker_origins wo ON wo.id = w.origin_id
        JOIN controller_worker cw ON wa.worker_id = cw.worker_id AND is_primary_controller = true
        LEFT JOIN controllers_known_enemies cke ON cke.controller_id = cw.controller_id AND cke.discovered_worker_id = a.attacker_id
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
 * 
 * @param PDO $pdo : database connection
 * 
 * @return bool : success
*/
function attackMechanic($pdo){
    echo '<div> <h3>  attackMechanic : </h3> ';

    $debug = strtolower(getConfig($pdo, 'DEBUG_ATTACK')) === 'true';
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
    if (empty($attacksArray)) { echo 'All is calm </div>'; return true;}

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
                echo $defender['defender_name']. ' HAS DIED ! <br />';
                $survived = false;
                $defender_status = 'dead';
                $is_alive = false;
                $attackerReport['attack_report'] = sprintf($attackSuccessTexts[array_rand($attackSuccessTexts)], $defender['defender_name']);
                $defenderReport['life_report'] = sprintf($workerDisappearanceTexts[array_rand($workerDisappearanceTexts)], $defender['turn_number'] );
                if ($defender['attack_difference'] >= (INT)$ATTACKDIFF1 ){
                    $is_alive = NULL;
                    echo $defender['defender_name']. ' Was Captured ! <br />';
                    $defender_status = 'captured';
                    $attackerReport['attack_report'] = sprintf($captureSuccessTexts[array_rand($captureSuccessTexts)], $defender['defender_name']);
                    // in controller_worker update defender_controller_id, defender_id, is_primary_controller = false
                    $stmt = $pdo->prepare("UPDATE controller_worker SET is_primary_controller = :is_primary WHERE controller_id = :controller_id AND worker_id = :worker_id");
                    $stmt->execute([
                        'controller_id' => $defender['defender_controller_id'],
                        'worker_id' => $defender['defender_id'],
                        'is_primary' => 0
                    ]);
                    // in controller_worker insert attacker_controller_id, defender_id, is_primary_controller = true
                    $stmt = $pdo->prepare("INSERT INTO controller_worker (controller_id, worker_id, is_primary_controller) VALUES (:controller_id, :worker_id, :is_primary)");
                    $stmt->execute([
                        'controller_id' => $defender['attacker_controller_id'],
                        'worker_id' => $defender['defender_id'],
                        'is_primary' => 1
                    ]);
                }
                updateWorkerActiveStatus($pdo, $defender['defender_id']);
                updateWorkerAliveStatus($pdo, $defender['defender_id'], $is_alive);
            } else {
                echo $defender['defender_name']. ' Escaped !<br />';
                $attackerReport['attack_report'] = sprintf($failedAttackTextes[array_rand($failedAttackTextes)], $defender['defender_name']);
                // Check if attaker is in know ennemies
                try{
                    $knownEnemycontroller = '';
                    $sql_known_ennemies = sprintf(
                        "SELECT * FROM controllers_known_enemies WHERE controller_id = %s AND discovered_worker_id = %s AND zone_id = %s",
                        $defender['defender_controller_id'], $defender['attacker_id'],  $defender['zone_id'] );
                    $stmtA = $pdo->prepare($sql_known_ennemies);
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
                        $sqlInsert = sprintf(
                            "INSERT INTO controllers_known_enemies (controller_id, discovered_worker_id, first_discovery_turn, last_discovery_turn, zone_id) VALUES (%s, %s, %s, %s, %s)",
                            $defender['defender_controller_id'], $defender['attacker_id'], $defender['turn_number'], $defender['turn_number'], $defender['zone_id'] );
                        $stmtInsert = $pdo->prepare($sqlInsert);
                        $stmtInsert->execute();
                    }
                } catch (PDOException $e) {
                    echo __FUNCTION__." (): Error fetching/inserting enemy workers: " . $e->getMessage();
                    return [];
                }
                $defenderReport['life_report'] = sprintf($escapeTextes[array_rand($escapeTextes)], sprintf("%s(%s)%s",$defender['attacker_name'], $defender['attacker_id'], $knownEnemycontroller));
            }
            if ($debug)
                echo sprintf("(survived  : %s <br/>", ($survived ));
            if ( $RIPOSTACTIVE!= '0' && $survived  && $defender['riposte_difference'] >= (INT)$RIPOSTDIFF ){
                $attacker_status = 'dead';
                echo $defender['defender_name']. ' RIPOSTE ! <br />';
                $attackerReport['attack_report'] = sprintf($textesAttackFailedAndCountered[array_rand($textesAttackFailedAndCountered)], $defender['defender_name']);
                $attackerReport['life_report'] = sprintf($workerDisappearanceTexts[array_rand($workerDisappearanceTexts)], $defender['turn_number'] );
                $defenderReport['life_report'] = sprintf($counterAttackTexts[array_rand($counterAttackTexts)], sprintf("%s(%s)%s",$defender['attacker_name'], $defender['attacker_id'], $knownEnemycontroller));
                updateWorkerActiveStatus($pdo, $defender['attacker_id']);
                updateWorkerAliveStatus($pdo, $defender['attacker_id']);
            }
            updateWorkerAction($pdo, $defender['attacker_id'], $defender['turn_number'], $attacker_status, $attackerReport );
            updateWorkerAction($pdo, $defender['defender_id'], $defender['turn_number'], $defender_status , $defenderReport );
        }
    }

    echo '<p> attackMechanic: DONE ! </p> </div>';
    return true;
}