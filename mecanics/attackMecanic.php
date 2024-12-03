<?php

function getAttackerComparisons($pdo, $turn_number = NULL, $attacker_id = NULL, $atk_threshold = 0, $rpst_threshold = 0 ) {
    $debug = FALSE;
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
                wa.zone_id,
                wa.turn_number
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
            wa.controler_id AS attacker_controler_id,
            wa.zone_id,
            CONCAT(c.firstname, ' ', c.lastname) AS attacker_controler_name
        FROM
            worker_actions wa
            JOIN controlers c ON wa.controler_id = c.ID
        WHERE
            wa.worker_id = :attacker_id
            AND turn_number = :turn_number
    )
    SELECT
        a.attacker_id,
        a.attacker_action_val,
        a.attacker_defence_val,
        a.attacker_controler_id,
        a.attacker_controler_name,
        z.id AS zone_id,
        z.name AS zone_name,
        wa.turn_number,
        wa.worker_id AS defender_id,
        wa.action_val AS defender_action_val,
        wa.defence_val AS defender_defence_val,
        CONCAT(w.firstname, ' ', w.lastname) AS defender_name,
        wo.id AS defender_origin_id,
        wo.name AS defender_origin_name,
        (a.attacker_action_val - wa.defence_val) AS attack_difference,
        (wa.action_val - a.attacker_defence_val) AS riposte_difference,
        cke.id AS defender_knows_enemy
    FROM attackers a
    JOIN zones z ON z.id = a.zone_id
    JOIN worker_actions wa ON
            a.zone_id = wa.zone_id AND wa.turn_number = :turn_number
    JOIN workers w ON wa.worker_id = w.ID
    JOIN worker_origins wo ON wo.id = w.origin_id
    JOIN controler_worker cw ON wa.worker_id = cw.worker_id AND is_primary_controler = true
    LEFT JOIN controlers_known_enemies cke ON cke.controler_id = cw.controler_id AND cke.discovered_worker_id = a.attacker_id
    WHERE w.id IN (%s) 
        AND (a.attacker_action_val - wa.defence_val) >= :atk_threshold
        AND (wa.action_val - a.attacker_defence_val) >= :rpst_threshold
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
            $stmtValCompare->bindParam(':atk_threshold', $atk_threshold, PDO::PARAM_INT);
            $stmtValCompare->bindParam(':rpst_threshold', $rpst_threshold, PDO::PARAM_INT);
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
    $RIPOSTDIFF = getConfig($pdo, 'RIPOSTDIFF');
    $RIPOSTONDEATH = getConfig($pdo, 'RIPOSTONDEATH');
    $RIPOSTACTIVE = getConfig($pdo, 'RIPOSTACTIVE');
    
    if ($debug) {
        echo "ATTACKDIFF0 : $ATTACKDIFF0 <br/>";
        echo "ATTACKDIFF1 : $ATTACKDIFF1 <br/>";
        echo "RIPOSTDIFF : $RIPOSTDIFF <br/>";
        echo "RIPOSTONDEATH : $RIPOSTONDEATH <br/>";
        echo "RIPOSTACTIVE : $RIPOSTACTIVE <br/>";
    }

    $attacksArray = getAttackerComparisons($pdo, NULL, NULL, (INT)$ATTACKDIFF0 , (INT)$RIPOSTDIFF);
    if ($debug)
        echo sprintf("attacksArray : %s <br/>", var_export($attacksArray, true));
    if (empty($attacksArray)) { echo 'All is calm </div>'; return TRUE;}
    foreach ($attacksArray as $attacker_id => $defenders) {
        // Build report :
        if ($debug)
            echo sprintf("attacker_id: %s =>row %s <br/>", $attacker_id, var_export($defenders, true));
        foreach ($defenders as $defender) {
            $survived = true;
            if ($defender['attack_difference'] >= (INT)$ATTACKDIFF0 ){
                $survived = false;
                echo $defender['defender_name']. ' HAS DIED !';
                // in  workers set  is_alive  FALSE,  is_active FASLE,
                updateWorkerStatus($pdo, $defender['defender_id'], FALSE, FALSE);
                // for id = defender_id in worker_actions  set action   'dead'
                // set report life_report append Tué 
                $life_report = "Tué";
                updateWorkerAction($pdo, $defender['defender_id'], $defender['turn_number'], 'dead', $life_report );
                // Start report attack_report append attaque OK
                $attack_report = $defender['defender_name'].' attaque OK';
                if ($defender['attack_difference'] >= (INT)$ATTACKDIFF1 ){
                    // in workers set  is_alive  TRUE, is_active FASLE, for  id = defender_id
                    updateWorkerStatus($pdo, $defender['defender_id'], TRUE, FALSE);
                    // in worker_actions set action   'captured' et report life_report append Disparu   for worker_id = defender_id AND turn_number = turn
                    $life_report = "Disparu";
                    updateWorkerAction($pdo, $defender['defender_id'], $defender['turn_number'], 'captured', $life_report);
                    // in controler_worker insert attacker_controler_id, defender_id, is_primary_controler = false 
                    $stmt = $pdo->prepare("INSERT INTO controler_worker (controler_id, worker_id, is_primary_controler) VALUES (:controler_id, :worker_id, :is_primary)");
                    $stmt->execute([
                        'controler_id' => $defender['attacker_controler_id'],
                        'worker_id' => $defender['defender_id'],
                        'is_primary' => TRUE
                    ]);
                    // in controler_worker insert attacker_controler_id, defender_id, is_primary_controler = false 
                    $stmt = $pdo->prepare("UPDATE controler_worker SET is_primary_controler = :is_primary WHERE controler_id = :controler_id AND worker_id = :worker_id");
                    $stmt->execute([
                        'controler_id' => $defender['attacker_controler_id'],
                        'worker_id' => $defender['defender_id'],
                        'is_primary' => FALSE
                    ]);
                    // in worker_actions set report attack_report append attaque OK
                    // for worker_id = attacker_id AND turn_number = turn
                    echo $defender['defender_name']. ' Was Captured !';
                    $attack_report .= 'Capturer';
                }
                updateWorkerAction($pdo, $defender['attacker_id'], $defender['turn_number'], NULL, NULL, $attack_report );
            }
            if ($defender['attack_difference'] < (INT)$ATTACKDIFF0 ){
                // $defender['defender_knows_enemy']
                // in worker_actions
                // set report attack_report append attaque FAIL
                // for worker_id = attacker_id AND turn_number = turn
                echo $defender['defender_name']. ' Escaped !';
                $life_report = 'J\ai été attaquer, mais je me suis échapper!';
                updateWorkerAction($pdo, $defender['defender_id'], $defender['turn_number'], NULL, $life_report);
                $attack_report =  sprintf('J\ai échouer dans l\attaque de %s !',  $defender['defender_name']);
                updateWorkerAction($pdo, $defender['attacker_id'], $defender['turn_number'], NULL, NULL, $attack_report );
            }
            if ((BOOL)$RIPOSTACTIVE && ($survived || (BOOL)$RIPOSTONDEATH) && $defender['riposte_difference'] >= (INT)$RIPOSTDIFF ){
                // in  workers set  is_alive  FALSE et  is_active FASLE for  id = attacker_id
                updateWorkerStatus($pdo, $defender['attacker_id'], FALSE, FALSE);
                // in worker_actions set action   'dead' for worker_id = attacker_id AND turn_number = turn 
                // set report life_report append Tué 
                updateWorkerAction($pdo, $defender['attacker_id'], $defender['turn_number'], 'dead', $life_report , $attack_report );
                // in worker_actions set report attack_report append riposte  for worker_id = defender_id AND turn_number = turn
                $life_report = $defender['defender_name']. ' Riposte !';
                updateWorkerAction($pdo, $defender['defender_id'], $defender['turn_number'], NULL, $life_report );
                echo $life_report ;
            }
        }
    }

    echo '</div>';
}