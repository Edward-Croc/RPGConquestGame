<?php

/**
 * 
 * @param PDO $pdo
 * @param int $target_location_id
 * @param int $controller_id
 * 
 * @return array $return
 */
function locationAttackMechanic($pdo, $turn_number) {
    $debug = strtolower($_SESSION['DEBUG']) === 'true';



    $targetResultText = '';
    try{
        // Get location informatipon from target_location_id
        $sql = "SELECT l.*, z.id AS zone_id, z.name AS zone_name FROM locations l
            JOIN zones z ON l.zone_id = z.id
            WHERE l.id = :id
            LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $target_location_id]);
        $location = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    $zone_id = $location[0]['zone_id'];
    if ($debug) echo sprintf("%s() SELECT * FROM locations : %s <br>",__FUNCTION__, var_export($location, true));
    $attackLocationDiff = getConfig($pdo, 'attackLocationDiff');
    if ($debug) echo sprintf("%s() attackLocationDiff : %s <br>",__FUNCTION__, var_export($attackLocationDiff, true));
    $controllerAttack = calculatecontrollerAttack($pdo, $zone_id, $controller_id);
    if ($debug) echo sprintf("%s() controllerAttack : %s <br>",__FUNCTION__, var_export($controllerAttack, true));
    $locationDefence = calculateSecretLocationDefence($pdo, $zone_id, $target_location_id, $location[0]['controller_id']);
    if ($debug) echo sprintf("%s() locationDefence : %s <br>",__FUNCTION__, var_export($locationDefence, true));

    // Check result
    if (($controllerAttack - $locationDefence) >= $attackLocationDiff){ 
        $return['success'] = true;
        $destroy = true;

        // Notre %s a été attaqué.e, par des agents du réseau %s. Ils ont franchi les portes avec succès.
        $locationAttackSuccessTextsArray = json_decode(getConfig($pdo,'TEXT_LOCATION_ATTACK_SUCCESS'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
            $locationAttackSuccessTextsArray = array("Notre %s a été attaqué.e, par des agents du réseau %s. Ils ont franchi les portes avec succès.");
        }
        $targetResultText .= sprintf(
            $locationAttackSuccessTextsArray[array_rand($locationAttackSuccessTextsArray)],
            $location[0]['name'], $controller_id
        );

        // Do actions depending on JSON for location
        if (!empty($location[0]['activate_json'])) {
            $activate_json = json_decode($location[0]['activate_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
                $activate_json = array();
            }
            $textSuccess = getConfig($pdo, 'textLocationDestroyed');
            if (!empty($activate_json['indestructible']) && $activate_json['indestructible'] == "TRUE") {
               $destroy = false;
               $textSuccess = getConfig($pdo, 'textLocationPillaged');
            }
            // update_location => Update existing location from name, description, discovery_diff, can_be_destroyed, can_be_repaired, controller_id, is_base,save_to_json
            if (!empty($activate_json['update_location'])) {
                $destroy = false;
                // Update the location
                updateLocation($pdo, $location[0], $activate_json);
            }
            $return['message'] .= sprintf($textSuccess, $location[0]['name']);
            // TODO on JSON key:
            // create_location => Create New location from name, description, discovery_diff, can_be_destroyed, controller_id, save_to_json
            // show_text => add text to the message
            // add_worker => add worker to controller
            // change_ia => change the functionning of an IA character
        } else {
            $return['message'] .= sprintf(
                getConfig($pdo, 'textLocationDestroyed'),
                $location[0]['name']
            );
        }

        $captureResult = captureLocationsArtefacts($pdo, $target_location_id, $controller_id);
        $return['message'] .= $captureResult['message'];
        // IF location is destroyed and captureResult is success
        if ($destroy && $captureResult['success']) {
            // Delete elements from players and location tables
            try{
                $sql = "DELETE FROM controller_known_locations WHERE location_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $target_location_id]);
                $sql = "DELETE FROM locations WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $target_location_id]);
            } catch (PDOException $e) {
                echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                return NULL;
            }
            $targetResultText .= ' Tout a été détruit.';
        }
    } else {
        // Notre %s a été attaqué.e, par des agents du réseau %s.  Heureusement, ils ne semblent pas avoir atteint leur objectif.
        $locationAttackFailTextsArray = json_decode(getConfig($pdo,'TEXT_LOCATION_ATTACK_FAIL'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
            $locationAttackFailTextsArray = array("Notre %s a été attaqué.e, par des agents du réseau %s.  Heureusement, ils ne semblent pas avoir atteint leur objectif.");
        }
        $targetResultText .= sprintf(
            $locationAttackFailTextsArray[array_rand($locationAttackFailTextsArray)],
            $location[0]['name'], $controller_id
        );
        $return['message'] = sprintf(
            getConfig($pdo, 'textLocationNotDestroyed'),
            $location[0]['name']
        );
    }

    

    // Add attack/defence participation to life_report of workers of the controllers in the locations zone
    // Get all workerIds for the controllers $controller_id and $location[0]['controller_id']

    // Prepare textes
    $defenseText = '';
    if ($location[0]['controller_id']){
        $defenseText = sprintf(' défendu par le réseau %s', $location[0]['controller_id']);
    }
    if ($return['success']) {
        $locationAttackAgentReportJson = getConfig($pdo,'TEXT_LOCATION_ATTACK_AGENT_REPORT_SUCCESS');
    } else {
        $locationAttackAgentReportJson = getConfig($pdo,'TEXT_LOCATION_ATTACK_AGENT_REPORT_FAIL');
    }
    $locationAttackAgentReportArray = json_decode($locationAttackAgentReportJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
        $locationAttackAgentReportArray = array("Attaque du lieu %s dans %s %s.<br/>");
    }

    // Get worker ids
    $active_actions = "'".implode("','", ACTIVE_ACTIONS)."'";
    $sqlWorkerForZoneAndController = sprintf("SELECT w.id
        FROM workers w
        JOIN controller_worker cw ON cw.worker_id = w.id
        JOIN worker_actions wa ON wa.worker_id = w.id AND wa.turn_number = :turn_number
        WHERE cw.controller_id = :controller_id
            AND w.zone_id = :zone_id
            AND wa.action_choice IN (%s)
            AND cw.is_primary_controller = :is_primary_controller",
        $active_actions
    );
    $stmt = $pdo->prepare($sqlWorkerForZoneAndController);
    $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
    $stmt->bindParam(':is_primary_controller', true, PDO::PARAM_BOOL);
    $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
    $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
    $stmt->execute();
    $workerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);  
    foreach ($workerIds as $workerId) {
        $report = sprintf(
            $locationAttackAgentReportArray[array_rand($locationAttackAgentReportArray)],
            $location[0]['name'], $location[0]['zone_name'], $defenseText
        );
        updateWorkerAction($pdo, $workerId, $turn_number, NULL, ['life_report' => $report]);
     }
     if ($location[0]['controller_id']) {
        // Prepare textes
        if ( !$return['success']) {
            $locationDefenceAgentReportJson = getConfig($pdo,'TEXT_LOCATION_DEFENCE_AGENT_REPORT_SUCCESS');
        } else {
            $locationDefenceAgentReportJson = getConfig($pdo,'TEXT_LOCATION_DEFENCE_AGENT_REPORT_FAIL');
        }
        $locationDefenceAgentReportArray = json_decode($locationDefenceAgentReportJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
            $locationDefenceAgentReportArray = array('Défense du lieu %s dans %s contre les agent du réseau %s.<br/>');
        }

        // Get worker ids
        $stmt = $pdo->prepare($sqlWorkerForZoneAndController);
        $stmt->execute([':controller_id' => $location[0]['controller_id'], ':zone_id' => $zone_id]);
        $workerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($workerIds as $workerId) {
            $report = sprintf(
            $locationDefenceAgentReportArray[array_rand($locationDefenceAgentReportArray)],
                $location[0]['name'], $location[0]['zone_name'], $controller_id
            );
            updateWorkerAction($pdo, $workerId, $turn_number, NULL, ['life_report' => $report]);
        }
     }

}