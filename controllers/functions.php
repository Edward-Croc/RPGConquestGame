<?php

/**
 * get all information for Controllers 
 *  - optional by player
 *  - optional for a specific controller
 * 
 * @param PDO $pdo : database connection
 * @param int|null $player_id
 * @param int|null $controller_id
 * 
 * @return array|null 
 * 
 */
// Function to get controllers and return as an array
function getControllers($pdo, $player_id = NULL, $controller_id = NULL, $hide_secret_controllers = true) {
    $controllersArray = array();

    try{
        $sql = "SELECT c.*,
            f.name AS faction_name, 
            ff.name AS fake_faction_name
            FROM controllers c
            LEFT JOIN factions f ON c.faction_id = f.ID
            LEFT JOIN factions ff ON c.fake_faction_id = ff.ID";
        if ($player_id !== NULL){
            $sql .= "
                INNER JOIN player_controller pc ON pc.controller_id = c.id
                WHERE pc.player_id = '$player_id'";
        }
        if ($controller_id !== NULL){
            $sql .= sprintf (
                " %s c.id = '%s'",
                $player_id !== NULL ? 'AND' : 'WHERE',
                $controller_id
            );
        } else if ($hide_secret_controllers == true){
            $sql .=  ($player_id !== NULL) ? ' AND' : ' WHERE';
            $sql .= " c.secret_controller IS NOT True";
        }
        $sql .= ' ORDER BY c.id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $player_id failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $controllers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store controllers in the array
    foreach ($controllers as $controller) {
        $controllersArray[] = $controller;
    }

    return $controllersArray;
}

/**
 * Get the name of a controller
 * 
 * @param PDO $pdo
 * @param int $controller_id
 * 
 * @return string
 */
function getControllerName($pdo, $controller_id) {
    try{ 
        $sql = "SELECT  CONCAT(c.firstname, ' ', c.lastname) AS controller_name FROM controllers c WHERE c.id = :controller_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':controller_id' => $controller_id]);
        $controller = $stmt->fetch(PDO::FETCH_ASSOC);
        return $controller['controller_name'];
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT controllers Failed: " . $e->getMessage()."<br />";
        return NULL;
    }
}

/**
 * Show list of controller options for à controller select field.
 * 
 * @param array $controllers : array of controllers ('id', 'firstname', 'lastname')
 * @param int|null $selectedID : ID of the selected controller (optional)
 * @param string $field_name : name of the select field (default: 'controller_id')
 * @param bool $addEmptySpace : add an empty option at the top of the list (default: false)
 * 
 * @return string : HTML select field
 */
function showControllerSelect($controllers, $selectedID = null ,$field_name = 'controller_id', $addEmptySpace = false ) {

    if (empty($controllers)) return '';
    $controllerOptions = '';
    if ($addEmptySpace) $controllerOptions .= "<option value='null'> Personne (Sans bannière) </option>";
    foreach ($controllers as $controller) {
        $controllerOptions .= sprintf (
            "<option value='%s' %s >%s %s</option>",
            $controller['id'],
            ($selectedID !== null && $controller['id'] == $selectedID) ? 'selected' : '',
            $controller['firstname'],
            $controller['lastname']
        );
    }

    // Bulma form field
    $showControllerSelect = sprintf('
        <div class="control for-select">
            <div class="select is-fullwidth">
                <select id="controllerSelect" name="%1$s">
                    %2$s
                </select>
            </div>
        </div>',
        $field_name,
        $controllerOptions
    );
    if (!empty($_SESSION['DEBUG']) && $_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showControllerSelect: ".var_export($showControllerSelect, true)."<br /><br />";

    return $showControllerSelect;
}

/** This function resets the turn_recruited_workers and turn_firstcome_workers to 0 for every controller */
function  restartTurnRecrutementCount($pdo){
    $sql = 'UPDATE controllers SET turn_firstcome_workers=0, turn_recruited_workers=0 WHERE True';
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT locations Failed: " . $e->getMessage()."<br />";
        return NULL;
    }
}

/**
 *
 */
function canStartFirstCome($pdo, $controller_id) {

    $controllerValues = getControllers($pdo, NULL, $controller_id);
    if ( $_SESSION['DEBUG'] == true )
    echo '<p>'
        .'; turn_firstcome_workers: '. getConfig($pdo, 'turn_firstcome_workers')
        .'; turn_firstcome_workers :'. $controllerValues[0]['turn_firstcome_workers']
    .'</p>';
    if (
        (INT)$controllerValues[0]['turn_firstcome_workers'] < (INT)getConfig($pdo, 'turn_firstcome_workers')
    )  return true;
    return false;
}

/**
 *
 */
function canStartRecrutement($pdo, $controller_id, $turnNumber){

    $controllerValues = getControllers($pdo, NULL, $controller_id);
    if ( $_SESSION['DEBUG'] == true )
    echo '<p>hasBase: '. var_export(hasBase($pdo, $controller_id), true)
        .';turncounter: '. $turnNumber
        .'; turn_recrutable_workers: '. getConfig($pdo, 'turn_recrutable_workers')
        .'; start_workers :'. $controllerValues[0]['start_workers']
        .'; turn_recruited_workers :'. $controllerValues[0]['turn_recruited_workers']
    .'</p>';

    if (    
        count(hasBase($pdo, $controller_id)) > 0
        &&
        ((
            ( $turnNumber == 0 )
            && ( (INT)$controllerValues[0]['turn_recruited_workers'] < (INT)$controllerValues[0]['start_workers'] )
        ) || (
            ( (INT)$turnNumber > 0 )
            && ( $controllerValues[0]['turn_recruited_workers'] < (INT)getConfig($pdo, 'turn_recrutable_workers') )
        ))
    ) return true;
    return false;
}

/**
 * This function returns an array of all bases a controller has or a NULL
 *
 * @param PDO $pdo : database connection
 * @param string : $controller_id 
 *
 * @return array|null : $bases
 */
function hasBase($pdo, $controller_id) {

    $sql = "SELECT l.*, z.id AS zone_id, z.name AS zone_name FROM locations l
        LEFT JOIN zones z ON l.zone_id = z.ID
        WHERE controller_id = :controller_id and is_base = True
    ";
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':controller_id' => $controller_id]);
        $bases = $stmt->fetchALL(PDO::FETCH_ASSOC);
        return $bases;
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT locations Failed: " . $e->getMessage()."<br />";
    }
    return NULL;
}

/**
 * Create the base for the controler in zone and return if success
 *
 * @param PDO $pdo : database connection
 * @param int : $controller_id 
 * @param int : $zone_id 
 * 
 * @return bool
 * 
*/
function createBase($pdo, $controller_id, $zone_id) {
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    $controllers = getControllers($pdo, NULL, $controller_id);
    $controller_name = $controllers[0]['firstname']. ' '. $controllers[0]['lastname'];
    if ($debug) echo sprintf("controller_name : %s </br>", $controller_name);

    $discovery_diff = calculateSecretLocationDiscoveryDiff($pdo, $controller_id, $zone_id);

    $timeValue = getConfig($pdo, 'timeValue');
    if ($debug) echo sprintf("timeValue : %s </br>", var_export($timeValue, true));

    $baseName = sprintf(
        getConfig($pdo, 'texteNameBase'),
        $controllers[0]['fake_faction_name']
    );

    $description = sprintf(
        getConfig($pdo, 'texteDescriptionBase'),
        $controller_name,
        $controllers[0]['fake_faction_name'],
        strtolower($timeValue)
    );
    if ($controllers[0]['faction_id'] != $controllers[0]['fake_faction_id'])
        $description .= sprintf(
            getConfig($pdo, 'texteHiddenFactionBase'),
            $controllers[0]['fake_faction_name'],
            $controllers[0]['faction_name']
        );

    try{
    // Check if base already exists for this controller in the zone
    $checkSql = "SELECT COUNT(*) FROM locations WHERE zone_id = :zone_id AND controller_id = :controller_id AND is_base = True";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':zone_id' => $zone_id,
        ':controller_id' => $controller_id
    ]);

        if ($checkStmt->fetchColumn() > 0) {
            if ($debug) echo "Base already exists for this controller in this zone.<br />";
            return false;
        }
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT locations Failed: " . $e->getMessage()."<br />";
    }

    $sql = "INSERT INTO locations (zone_id, name, description, controller_id, discovery_diff, can_be_destroyed, is_base) VALUES
        (:zone_id, :baseName, :description, :controller_id, :discovery_diff, True, True)";
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
        $stmt->bindParam(':baseName', $baseName);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
        $stmt->bindParam(':discovery_diff', $discovery_diff, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT locations Failed: " . $e->getMessage()."<br />";
        return false;
    }
    return true;
}

/**
 * Changes the base to the new zone
 * 
 * @param PDO $pdo : database connection
 * @param int : $base_id 
 * @param int : $zone_id 
 * 
 * @return bool
 * 
 */
function moveBase($pdo, $base_id, $zone_id) {
    // update locations set zone_id where controller_id = "%s";
    $sql = "UPDATE locations SET zone_id = :zone_id, setup_turn = (SELECT turncounter FROM mechanics LIMIT 1) WHERE id = :base_id";
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
        $stmt->bindParam(':base_id', $base_id, PDO::PARAM_INT);
        $stmt->execute();

        // Delete controller knowledge of the location
        $deleteSQL = "DELETE FROM controller_known_locations WHERE location_id = :base_id";
        $deleteStmt = $pdo->prepare($deleteSQL);
        $deleteStmt->bindParam(':base_id', $base_id, PDO::PARAM_INT);
        $deleteStmt->execute();

        return true;
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE locations SET zone_id: " . $e->getMessage()."<br />";
        return false;
    }
}

/**
 * Affiche les options de sélection pour les bases connues et attaquables par un contrôleur
 * 
 * @param PDO $pdo
 * @param int $controller_id
 * 
 * @return string returnText
 * 
 */
function showAttackableControllerKnownLocations($pdo, $controller_id) {
    $locations = listControllerKnownLocations($pdo, $controller_id, true);
    if (empty($locations)) return NULL;

    $options = '';
    foreach ($locations as $zone) {
        foreach ($zone['locations'] as $loc) {
            $options .= sprintf(
                '<option value="%d">%s (%s)</option>',
                (int)$loc['id'],
                $loc['name'],
                $zone['name']
            );
        }
    }

    return sprintf('
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="attackLocationSelect" name="target_location_id">
                        <option value="">Sélectionner un lieu</option>
                        %s
                    </select>
                </div>
            </div>
    ', $options);
}

/**
 * 
 * 
 * @param PDO $pdo
 * @param int $controller_id
 * @param int $target_location_id
 * 
 * @return array $return
 * 
 */
function attackLocation($pdo, $controller_id, $target_location_id) {
    $debug = $_SESSION['DEBUG'];
    $return = array('success' => false, 'message' => '');

    //  Get Turn Number
    $mechanics = getMechanics($pdo);
    $turn_number = $mechanics['turncounter'];

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
    $controllerAttack = calculatecontrollerAttack($pdo, $controller_id, $zone_id);
    if ($debug) echo sprintf("%s() controllerAttack : %s <br>",__FUNCTION__, var_export($controllerAttack, true));
    $locationDefence = calculateSecretLocationDefence($pdo, $location[0]['controller_id'], $zone_id, $target_location_id);
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
            $targetResultText .= ' Tout as été détruit.';
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

    try {
        $target_controller_id = (!empty($location[0]['controller_id'])) ? $location[0]['controller_id'] : null;
        // ADD Base was attacked succesfuly/unsuccesfuly to show on Admin Page
        $logSql = "
            INSERT INTO location_attack_logs (
                target_controller_id,
                location_name,
                attacker_id,
                attack_val,
                defence_val,
                turn,
                success,
                target_result_text,
                attacker_result_text
            )
            VALUES (
                :target_controller_id,
                :location_name,
                :attacker_id,
                :attack_val,
                :defence_val,
                :turn_number,
                :success,
                :target_result_text,
                :attacker_result_text
            )
        ";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->bindParam(':target_controller_id', $target_controller_id, $target_controller_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $logStmt->bindParam(':attacker_id', $controller_id, PDO::PARAM_INT);
        $logStmt->bindParam(':location_name', $location[0]['name']);
        $logStmt->bindParam(':attack_val', $controllerAttack, PDO::PARAM_INT);
        $logStmt->bindParam(':defence_val', $locationDefence, PDO::PARAM_INT);
        $logStmt->bindParam(':success', $return['success'], PDO::PARAM_BOOL);
        $logStmt->bindParam(':target_result_text', $targetResultText);
        $logStmt->bindParam(':attacker_result_text', $return['message']);
        $logStmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $logStmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT location_attack_logs Failed: " . $e->getMessage()."<br />";
        return array('success' => false, 'message' => 'Error : Resultat Save Failed');
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
    $sqlWorkerForZoneAndController = "SELECT w.id
        FROM workers w
        JOIN controller_worker cw ON cw.worker_id = w.id
        WHERE cw.controller_id = :controller_id
            AND w.zone_id = :zone_id
            AND w.is_active = True
            AND cw.is_primary_controller = True";
    $stmt = $pdo->prepare($sqlWorkerForZoneAndController);
    $stmt->execute([':controller_id' => $controller_id, ':zone_id' => $zone_id]);
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


    return $return;
}

/**
 * Changes all Artefacts in the location to the new controllers base.
 * 
 * @param PDO $pdo
 * @param int $controller_id
 * @param int $target_location_id
 * 
 * @return string message
 */
function captureLocationsArtefacts($pdo, $location_id, $controller_id) {
    $return = array('success' => true, 'message' => '');

    // Step 1: Get base location of the controller
    $stmt = $pdo->prepare("SELECT id FROM locations WHERE controller_id = ? AND is_base = TRUE LIMIT 1");
    $stmt->execute([$controller_id]);
    $baseLocation = $stmt->fetchColumn();

    if (!$baseLocation) {
        $return['message'] = " Nous n'avons pas de forteresse pour ramener des prisonniers.";
        $return['success'] = false;
        return $return;
    }

    // Step 2: Move artefacts from captured location to base
    $stmt = $pdo->prepare("UPDATE artefacts SET location_id = ? WHERE location_id = ?");
    $stmt->execute([$baseLocation, $location_id]);

    // Step 3: Optional — count how many artefacts moved
    $count = $stmt->rowCount();

    if ($count > 0) {
        $return['message'] = " Nous avons ramené des prisonniers du raid.";
        return $return;
    }
    return $return;
}

/**
 *
 * @param PDO $pdo
 * @param int $searcher_controller_id
 * @param int $found_id
 * @param int $turn_number
 * @param int $zone_id
 *
 * @return int $cke_existing_record_id
 * 
 */
function addWorkerToCKE($pdo, $searcher_controller_id, $found_id, $turn_number, $zone_id) {
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    $cke_existing_record_id = NULL;

    // Search for the existing controller-Worker combo
    $sql = "SELECT id FROM controllers_known_enemies
        WHERE controller_id = :searcher_controller_id
            AND discovered_worker_id = :found_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':searcher_controller_id' => $searcher_controller_id,
        ':found_id' => $found_id
    ]);
    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($debug) echo sprintf(" existingRecord: %s<br/> ", var_export($existingRecord,true));

    if (!empty($existingRecord)) {
        $cke_existing_record_id = $existingRecord['id'];
        // Update if record exists
        $sql = "UPDATE controllers_known_enemies
            SET last_discovery_turn = :turn_number, zone_id = :zone_id
            WHERE id = :id";
        if ($debug) echo sprintf(" existingRecord: %s<br/> ", var_export($existingRecord,true));
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':turn_number' => $turn_number,
            ':zone_id' => $zone_id,
            ':id' => $existingRecord['id']
        ]);
    } else {
        // Insert if record doesn't exist
        $sql = "INSERT INTO controllers_known_enemies
            (controller_id, discovered_worker_id, first_discovery_turn, last_discovery_turn, zone_id)
            VALUES (:searcher_controller_id, :found_id, :turn_number, :turn_number, :zone_id)";
        if ($debug) echo "sql :".var_export($sql, true)." <br>";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
        ':searcher_controller_id' => $searcher_controller_id,
        ':found_id' => $found_id,
        ':turn_number' => $turn_number,
        ':zone_id' => $zone_id,
        ]);
        $cke_existing_record_id = $pdo->lastInsertId();
    }
    return $cke_existing_record_id;
}