<?php

/**
 * get all information for Controllers
 *  - optional by player
 *  - optional for a specific controller
 *  - optional exclusion of one controller (e.g. session-active controller for "target" selects)
 *
 * @param PDO $pdo : database connection
 * @param int|null $player_id
 * @param int|null $controller_id
 * @param bool $hide_secret_controllers default: true
 * @param int|null $exclude_controller_id : drops one controller from the result (used to forbid self-target on gift selects)
 *
 * @return array|null
 *
 */
// Function to get controllers and return as an array
function getControllers($pdo, $player_id = NULL, $controller_id = NULL, $hide_secret_controllers = true, $exclude_controller_id = NULL) {
    $controllersArray = array();
    $prefix = $_SESSION['GAME_PREFIX'];

    try{
        $sql = "SELECT c.*,
            f.name AS faction_name,
            ff.name AS fake_faction_name
            FROM {$prefix}controllers c
            LEFT JOIN {$prefix}factions f ON c.faction_id = f.ID
            LEFT JOIN {$prefix}factions ff ON c.fake_faction_id = ff.ID";
        $hasWhere = false;
        if ($player_id !== NULL){
            $sql .= "
                INNER JOIN {$prefix}player_controller pc ON pc.controller_id = c.id
                WHERE pc.player_id = '$player_id'";
            $hasWhere = true;
        }
        if ($controller_id !== NULL){
            $sql .= sprintf(' %s c.id = \'%s\'', $hasWhere ? 'AND' : 'WHERE', $controller_id);
            $hasWhere = true;
        } else if ($hide_secret_controllers == true){
            $sql .= sprintf(' %s c.secret_controller IS NOT True', $hasWhere ? 'AND' : 'WHERE');
            $hasWhere = true;
        }
        if ($exclude_controller_id !== NULL){
            $sql .= sprintf(' %s c.id <> %d', $hasWhere ? 'AND' : 'WHERE', (int) $exclude_controller_id);
            $hasWhere = true;
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
    $prefix = $_SESSION['GAME_PREFIX'];
    try{ 
        $sql = "SELECT  CONCAT(c.firstname, ' ', c.lastname) AS controller_name FROM {$prefix}controllers c WHERE c.id = :controller_id";
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
    $prefix = $_SESSION['GAME_PREFIX'];
    $sql = "UPDATE {$prefix}controllers SET turn_firstcome_workers=0, turn_recruited_workers=0 WHERE True";
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
    $prefix = $_SESSION['GAME_PREFIX'];

    $sql = "SELECT l.*, z.id AS zone_id, z.name AS zone_name FROM {$prefix}locations l
        LEFT JOIN {$prefix}zones z ON l.zone_id = z.ID
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

    $prefix = $_SESSION['GAME_PREFIX'];

    // Reject hidden-zone base builds when the controller cannot see the zone.
    $zoneRows = getZonesArray($pdo, null, null, $zone_id);
    $zone = $zoneRows[0] ?? null;
    if ($zone && !empty($zone['is_hidden'])) {
        $isGm = !empty($_SESSION['is_privileged']);
        if (!canControllerSeeZone($pdo, $zone, (int)$controller_id, $isGm)) {
            echo "Zone non accessible.<br />";
            return false;
        }
    }

    if (!spendRessourcesToBuildBase($pdo, $controller_id)) {
        echo "Stock insuffisant ou modifié.<br />";
        return false;
    }

    $controllers = getControllers($pdo, NULL, $controller_id);
    $controller_name = $controllers[0]['firstname']. ' '. $controllers[0]['lastname'];
    if ($debug) echo sprintf("controller_name : %s </br>", $controller_name);

    $discovery_diff = calculateSecretLocationDiscoveryDiff($pdo, $zone_id, null, $controller_id );

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
        strtolower($timeValue),
        getConfig($pdo, 'controllerNameDenominatorOf')
    );
    $hidden_description = '';
    if ($controllers[0]['faction_id'] != $controllers[0]['fake_faction_id'])
        $hidden_description = sprintf(
            getConfig($pdo, 'texteHiddenFactionBase'),
            $controllers[0]['fake_faction_name'],
            $controllers[0]['faction_name']
        );

    try{
    // Check if base already exists for this controller in the zone
    $checkSql = "SELECT COUNT(*) FROM {$prefix}locations WHERE zone_id = :zone_id AND controller_id = :controller_id AND is_base = True";
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

    $sql = "INSERT INTO {$prefix}locations (zone_id, name, description, hidden_description, controller_id, discovery_diff, can_be_destroyed, is_base, location_types) VALUES
        (:zone_id, :baseName, :description, :hidden_description, :controller_id, :discovery_diff, True, True, '[\"fortress\"]')";
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
        $stmt->bindParam(':baseName', $baseName, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':hidden_description', $hidden_description, PDO::PARAM_STR);
        $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
        $stmt->bindParam(':discovery_diff', $discovery_diff, PDO::PARAM_INT);
        $stmt->execute();
        $base_id = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT locations Failed: " . $e->getMessage()."<br />";
        return false;
    }

    // Owner must know own base — CKL-joined panels otherwise hide it.
    $mechanics = getMechanics($pdo);
    $turn_number = isset($mechanics['turncounter']) ? (int)$mechanics['turncounter'] : 0;
    $ownerKnowsSecret = (strtoupper((string)getConfig($pdo, 'owner_knows_own_base_secret')) === 'TRUE');
    addLocationToCKL($pdo, $controller_id, $base_id, $turn_number, $ownerKnowsSecret);

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
function moveBase($pdo, $base_id, $zone_id, $controller_id) {

    if (!spendRessourcesToMoveBase($pdo, $controller_id)) {
        echo "Stock insuffisant ou modifié.<br />";
        return false;
    }

    $prefix = $_SESSION['GAME_PREFIX'];

    // Cancel any in-flight end-turn attacks targeting this base.
    $mechanics = getMechanics($pdo);
    $turn_number = isset($mechanics['turncounter']) ? (int)$mechanics['turncounter'] : 0;
    try {
        $sel = $pdo->prepare("SELECT id, location_id, location_name, attacker_controller_id
            FROM {$prefix}controller_location_attacks
            WHERE location_id = :base_id AND queued_turn = :turn AND success IS NULL");
        $sel->bindParam(':base_id', $base_id, PDO::PARAM_INT);
        $sel->bindParam(':turn', $turn_number, PDO::PARAM_INT);
        $sel->execute();
        $inFlight = $sel->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT in-flight attacks failed: " . $e->getMessage()."<br />";
        $inFlight = [];
    }
    foreach ($inFlight as $row) {
        failQueuedLocationAttack($pdo, $row, $turn_number, 'moved');
    }

    // Defensive: ensure any pre-existing base lacking the fortress tag gets it.              
    try {                                                                                     
        $stmt = $pdo->prepare("UPDATE {$prefix}locations SET location_types = '[\"fortress\"]'
            WHERE id = :base_id AND location_types IS NULL");                                            
        $stmt->bindParam(':base_id', $base_id, PDO::PARAM_INT);                               
            $stmt->execute();                                                                     
        } catch (PDOException $e) {                                                               
            echo __FUNCTION__."(): UPDATE location_types failed: " . $e->getMessage()."<br />";   
    }
  
    // update locations set zone_id where controller_id = "%s";
    $sql = "UPDATE {$prefix}locations SET zone_id = :zone_id, setup_turn = (SELECT turncounter FROM {$prefix}mechanics LIMIT 1) WHERE id = :base_id";
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
        $stmt->bindParam(':base_id', $base_id, PDO::PARAM_INT);
        $stmt->execute();

        // Delete controller knowledge of the location
        $deleteSQL = "DELETE FROM {$prefix}controller_known_locations WHERE location_id = :base_id";
        $deleteStmt = $pdo->prepare($deleteSQL);
        $deleteStmt->bindParam(':base_id', $base_id, PDO::PARAM_INT);
        $deleteStmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE locations SET zone_id: " . $e->getMessage()."<br />";
        return false;
    }

    // Re-seed the owner's CKL row at the new location.
    $mechanics = getMechanics($pdo);
    $turn_number = isset($mechanics['turncounter']) ? (int)$mechanics['turncounter'] : 0;
    $ownerKnowsSecret = (strtoupper((string)getConfig($pdo, 'owner_knows_own_base_secret')) === 'TRUE');
    addLocationToCKL($pdo, $controller_id, $base_id, $turn_number, $ownerKnowsSecret);

    return true;
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
    // Exclude own — controller cannot attack own base.
    $excludePending = in_array(getConfig($pdo, 'locationAttackMode'), ['endTurn'], true);
    $locations = listControllerKnownLocations($pdo, $controller_id, true, false, true, $excludePending);
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
 * Affiche les options de sélection pour les bases connues et réparables par un contrôleur
 * 
 * @param PDO $pdo
 * @param int $controller_id
 * 
 * @return string returnText
 * 
 */
function showRepairableControllerKnownLocations($pdo, $controller_id) {
    $locations = listControllerKnownLocations($pdo, $controller_id, false, true);
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
                    <select id="repairLocationSelect" name="target_location_id">
                        <option value="">Sélectionner un lieu à réparer</option>
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
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';
    $prefix = $_SESSION['GAME_PREFIX'];

    $mode = getConfig($pdo, 'locationAttackMode');
    if (!in_array($mode, ['immediate', 'endTurn'], true)) {
        return array('success' => false, 'message' => 'Action indisponible.');
    }

    $mechanics = getMechanics($pdo);
    $turn_number = $mechanics['turncounter'];

    try {
        $sql = "SELECT l.*, z.id AS zone_id, z.name AS zone_name FROM {$prefix}locations l
            JOIN {$prefix}zones z ON l.zone_id = z.id
            WHERE l.id = :id
            LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $target_location_id]);
        $location = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    if (empty($location)) {
        return array('success' => false, 'message' => 'Lieu introuvable.');
    }

    $zone_id = $location[0]['zone_id'];
    if ($debug) echo sprintf("%s() SELECT * FROM locations : %s <br>",__FUNCTION__, var_export($location, true));
    $controllerAttack = calculatecontrollerAttack($pdo, $zone_id, $controller_id);
    if ($debug) echo sprintf("%s() controllerAttack : %s <br>",__FUNCTION__, var_export($controllerAttack, true));
    $locationDefence = calculateSecretLocationDefence($pdo, $zone_id, $target_location_id, $location[0]['controller_id']);
    if ($debug) echo sprintf("%s() locationDefence : %s <br>",__FUNCTION__, var_export($locationDefence, true));

    if ($mode === 'endTurn') {
        try {
            // INSERT WHERE NOT EXISTS: dedupe (attacker, location, turn) at the backend.
            $insertSql = "INSERT INTO {$prefix}controller_location_attacks
                (location_id, location_name, attacker_controller_id, queued_turn, defence_val_snapshot)
                SELECT :location_id, :location_name, :attacker, :turn, :defence
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$prefix}controller_location_attacks
                    WHERE attacker_controller_id = :attacker_check
                      AND location_id = :location_check
                      AND queued_turn = :turn_check
                )";
            $stmt = $pdo->prepare($insertSql);
            $stmt->bindParam(':location_id', $target_location_id, PDO::PARAM_INT);
            $stmt->bindParam(':location_check', $target_location_id, PDO::PARAM_INT);
            $stmt->bindParam(':location_name', $location[0]['name'], PDO::PARAM_STR);
            $stmt->bindParam(':attacker', $controller_id, PDO::PARAM_INT);
            $stmt->bindParam(':attacker_check', $controller_id, PDO::PARAM_INT);
            $stmt->bindParam(':turn', $turn_number, PDO::PARAM_INT);
            $stmt->bindParam(':turn_check', $turn_number, PDO::PARAM_INT);
            $stmt->bindParam(':defence', $locationDefence, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                return array('success' => false, 'queued' => false, 'message' => 'Attaque déjà planifiée ce tour.');
            }
        } catch (PDOException $e) {
            echo __FUNCTION__."(): INSERT controller_location_attacks Failed: " . $e->getMessage()."<br />";
            return array('success' => false, 'message' => 'Error : Queue Failed');
        }
        return array(
            'success' => true,
            'queued'  => true,
            'message' => sprintf('Attaque planifiée contre %s. Résolution en fin de tour.', $location[0]['name'])
        );
    }

    return resolveLocationAttackEffects($pdo, $location[0], $controller_id, $turn_number, $controllerAttack, $locationDefence);
}

function resolveLocationAttackEffects($pdo, $location, $controller_id, $turn_number, $controllerAttack, $locationDefence) {
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';
    $prefix = $_SESSION['GAME_PREFIX'];
    $return = array('success' => false, 'message' => '');
    $targetResultText = '';

    $zone_id = $location['zone_id'];
    $target_location_id = $location['id'];
    $attackLocationDiff = getConfig($pdo, 'attackLocationDiff');
    if ($debug) echo sprintf("%s() attackLocationDiff : %s <br>",__FUNCTION__, var_export($attackLocationDiff, true));

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
            $location['name'], $controller_id
        );

        // Do actions depending on JSON for location
        if (!empty($location['activate_json'])) {
            $activate_json = json_decode($location['activate_json'], true);
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
                updateLocation($pdo, $location, $activate_json);
            }
            $return['message'] .= sprintf($textSuccess, $location['name']);
            // TODO on JSON key:
            // create_location => Create New location from name, description, discovery_diff, can_be_destroyed, controller_id, save_to_json
            // show_text => add text to the message
            // add_worker => add worker to controller
            // change_ia => change the functionning of an IA character
        } else {
            $return['message'] .= sprintf(
                getConfig($pdo, 'textLocationDestroyed'),
                $location['name']
            );
        }

        $captureResult = captureLocationsArtefacts($pdo, $target_location_id, $controller_id);
        $return['message'] .= $captureResult['message'];
        // IF location is destroyed and captureResult is success
        if ($destroy && $captureResult['success']) {
            // Delete elements from players and location tables. The
            // controller_location_attacks FK uses ON DELETE SET NULL so
            // resolved queue rows survive with their stored location_name.
            try {
                $sql = "DELETE FROM {$prefix}controller_known_locations WHERE location_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $target_location_id]);
                $sql = "DELETE FROM {$prefix}locations WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $target_location_id]);
            } catch (PDOException $e) {
                echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                return NULL;
            }
            $targetResultText .= ' Tout a été détruit.';
        }
    } else {
        $locationAttackFailTextsArray = json_decode(getConfig($pdo,'TEXT_LOCATION_ATTACK_FAIL'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
            $locationAttackFailTextsArray = array("Notre %s a été attaqué.e, par des agents du réseau %s.  Heureusement, ils ne semblent pas avoir atteint leur objectif.");
        }
        $targetResultText .= sprintf(
            $locationAttackFailTextsArray[array_rand($locationAttackFailTextsArray)],
            $location['name'], $controller_id
        );
        $return['message'] = sprintf(
            getConfig($pdo, 'textLocationNotDestroyed'),
            $location['name']
        );
    }

    try {
        $target_controller_id = (!empty($location['controller_id'])) ? $location['controller_id'] : null;
        $logSql = "
            INSERT INTO {$prefix}location_attack_logs (
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
        $logStmt->bindParam(':location_name', $location['name'], PDO::PARAM_STR);
        $logStmt->bindParam(':attack_val', $controllerAttack, PDO::PARAM_INT);
        $logStmt->bindParam(':defence_val', $locationDefence, PDO::PARAM_INT);
        $logStmt->bindParam(':success', $return['success'], PDO::PARAM_BOOL);
        $logStmt->bindParam(':target_result_text', $targetResultText, PDO::PARAM_STR);
        $logStmt->bindParam(':attacker_result_text', $return['message'], PDO::PARAM_STR );
        $logStmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $logStmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT location_attack_logs Failed: " . $e->getMessage()."<br />";
        return array('success' => false, 'message' => 'Error : Resultat Save Failed');
    }

    // Add attack/defence participation to life_report of workers of the controllers in the locations zone
    $defenseText = '';
    if ($location['controller_id']){
        $defenseText = sprintf(' défendu par le réseau %s', $location['controller_id']);
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

    $active_actions = "'".implode("','", ACTIVE_ACTIONS)."'";
    $sqlWorkerForZoneAndController = sprintf("SELECT w.id
        FROM {$prefix}workers w
        JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
        JOIN {$prefix}worker_actions wa ON wa.worker_id = w.id AND wa.turn_number = :turn_number
        WHERE cw.controller_id = :controller_id
            AND w.zone_id = :zone_id
            AND wa.action_choice IN (%s)
            AND cw.is_primary_controller = %s",
        $active_actions,
        ($_SESSION['DBTYPE'] == 'mysql') ? 1 : 'true'
    );
    $stmt = $pdo->prepare($sqlWorkerForZoneAndController);
    $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
    $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
    $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
    $stmt->execute();
    $workerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($workerIds as $workerId) {
        $report = sprintf(
            $locationAttackAgentReportArray[array_rand($locationAttackAgentReportArray)],
            $location['name'], $location['zone_name'], $defenseText
        );
        updateWorkerAction($pdo, $workerId, $turn_number, NULL, ['life_report' => $report]);
    }
    if ($location['controller_id']) {
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

        $stmt = $pdo->prepare($sqlWorkerForZoneAndController);
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmt->bindParam(':controller_id', $location['controller_id'], PDO::PARAM_INT);
        $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
        $stmt->execute();
        $workerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($workerIds as $workerId) {
            $report = sprintf(
                $locationDefenceAgentReportArray[array_rand($locationDefenceAgentReportArray)],
                $location['name'], $location['zone_name'], $controller_id
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
    $prefix = $_SESSION['GAME_PREFIX'];

    // Step 1: Get base location of the controller
    $stmt = $pdo->prepare("SELECT id FROM {$prefix}locations WHERE controller_id = ? AND is_base = TRUE LIMIT 1");
    $stmt->execute([$controller_id]);
    $baseLocation = $stmt->fetchColumn();

    if (!$baseLocation) {
        $return['message'] = " Nous n'avons pas de forteresse pour ramener des prisonniers.";
        $return['success'] = false;
        return $return;
    }

    // Step 2: Move artefacts from captured location to base
    $stmt = $pdo->prepare("UPDATE {$prefix}artefacts SET location_id = ? WHERE location_id = ?");
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
 * Read a single CKE row for (controller, worker). Caller compares prior state (zone_id + level flags) before calling addWorkerToCKE.
 *
 * @param PDO $pdo
 * @param int $controller_id
 * @param int $worker_id
 *
 * @return array|null  associative row, or NULL if no CKE entry exists
 */
function getCKEEntry($pdo, $controller_id, $worker_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $sql = "SELECT * FROM {$prefix}controllers_known_enemies
            WHERE controller_id = :controller_id
              AND discovered_worker_id = :worker_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':controller_id' => $controller_id,
            ':worker_id' => $worker_id
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Error SELECT FROM {$prefix}controllers_known_enemies Failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    return $row ?: NULL;
}

/**
 *
 * @param PDO $pdo
 * @param int $searcher_controller_id
 * @param int $found_id
 * @param int $turn_number
 * @param int $zone_id
 * @param int|null $discovered_controller_id   monotonic — only persisted if truthy
 * @param string|null $discovered_controller_name   monotonic — only persisted if truthy
 * @param bool $discovered_powers   monotonic — DIFF1 flag (disciplines/transformations/hobby revealed); only persisted if truthy
 *
 * @return int $cke_existing_record_id
 *
 */
function addWorkerToCKE(
        $pdo,
        $searcher_controller_id,
        $found_worker_id,
        $turn_number,
        $zone_id,
        $discovered_controller_id = NULL,
        $discovered_controller_name = NULL,
        $discovered_powers = false
    ) {
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    $cke_existing_record_id = NULL;

    $prefix = $_SESSION['GAME_PREFIX'];
    
    try{
        // Only add information to controllers_known_enemies if the worker is not controlled by the target controller
        $sql = "SELECT COUNT(*) AS count FROM {$prefix}controller_worker WHERE controller_id = :searcher_controller_id AND worker_id = :found_worker_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':searcher_controller_id' => $searcher_controller_id,
            ':found_worker_id' => $found_worker_id
        ]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Error COUNT(*) FROM {$prefix}controller_worker Failed: " . $e->getMessage()."<br />";
    }
    if ($count == 1) {
        if ($debug) echo __FUNCTION__."(): COUNT(*) FROM {$prefix}controller_worker; Worker is already controlled by the target controller<br />";
        return NULL;
    }

    try{
        // Search for the existing controller-Worker combo
        $sql = "SELECT id FROM {$prefix}controllers_known_enemies
            WHERE controller_id = :searcher_controller_id
                AND discovered_worker_id = :found_worker_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':searcher_controller_id' => $searcher_controller_id,
            ':found_worker_id' => $found_worker_id
        ]);
        $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($debug) echo sprintf(" existingRecord: %s<br/> ", var_export($existingRecord,true));
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Error SELECT id FROM {$prefix}controllers_known_enemies Failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    if (!empty($existingRecord)) {
        try{
            $cke_existing_record_id = $existingRecord['id'];
            // Update if record exists
            $sql = sprintf("UPDATE {$prefix}controllers_known_enemies
                SET last_discovery_turn = :turn_number, zone_id = :zone_id
                %s %s %s
                WHERE id = :id",
                $discovered_controller_id ? ", discovered_controller_id = :discovered_controller_id" : "",
                $discovered_controller_name ? ", discovered_controller_name = :discovered_controller_name" : "",
                $discovered_powers ? ", discovered_powers = :discovered_powers" : ""
            );
            if ($debug) echo sprintf(" existingRecord: %s<br/> ", var_export($existingRecord,true));
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
            $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
            $stmt->bindParam(':id', $existingRecord['id'], PDO::PARAM_INT);
            if ($discovered_controller_id) {
                $stmt->bindParam(':discovered_controller_id', $discovered_controller_id, PDO::PARAM_INT);
            }
            if ($discovered_controller_name) {
                $stmt->bindParam(':discovered_controller_name', $discovered_controller_name, PDO::PARAM_STR);
            }
            if ($discovered_powers) {
                $stmt->bindParam(':discovered_powers', $discovered_powers, PDO::PARAM_BOOL);
            }
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__."(): Error UPDATE {$prefix}controllers_known_enemies Failed: " . $e->getMessage()."<br />";
        }
    } else {
        try{
            // Insert if record doesn't exist
            $sql = "INSERT INTO {$prefix}controllers_known_enemies
                (controller_id, discovered_worker_id, first_discovery_turn, last_discovery_turn, zone_id, discovered_controller_id, discovered_controller_name, discovered_powers)
                VALUES (:searcher_controller_id, :found_worker_id, :turn_number, :turn_number, :zone_id, :discovered_controller_id, :discovered_controller_name, :discovered_powers)";
            if ($debug) echo "sql :".var_export($sql, true)." <br>";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':searcher_controller_id', $searcher_controller_id, PDO::PARAM_INT);
            $stmt->bindParam(':found_worker_id', $found_worker_id, PDO::PARAM_INT);
            $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
            $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
            $stmt->bindParam(':discovered_controller_id', $discovered_controller_id, PDO::PARAM_INT);
            $stmt->bindParam(':discovered_controller_name', $discovered_controller_name, PDO::PARAM_STR);
            $stmt->bindParam(':discovered_powers', $discovered_powers, PDO::PARAM_BOOL);
            $stmt->execute();
            $cke_existing_record_id = $pdo->lastInsertId();
        } catch (PDOException $e) {
            echo __FUNCTION__."(): Error INSERT INTO {$prefix}controllers_known_enemies Failed: " . $e->getMessage()."<br />";
        }
    }
    return $cke_existing_record_id;
}

/**
 * Read a single CKL row for (controller, location). Caller compares prior state (found_secret) before calling addLocationToCKL.
 *
 * @param PDO $pdo
 * @param int $controller_id
 * @param int $location_id
 *
 * @return array|null  associative row, or NULL if no CKL entry exists
 */
function getCKLEntry($pdo, $controller_id, $location_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $sql = "SELECT * FROM {$prefix}controller_known_locations
            WHERE controller_id = :controller_id
              AND location_id = :location_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':controller_id' => $controller_id,
            ':location_id' => $location_id
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Error SELECT FROM {$prefix}controller_known_locations Failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    return $row ?: NULL;
}

/**
 *
 * @param PDO $pdo
 * @param int $controller_id
 * @param int $location_id
 * @param int $turn_number
 *
 * @return int $ckl_existing_record_id
 *
 */
function addLocationToCKL($pdo, $controller_id, $location_id, $turn_number, $found_secret) {
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';
    $prefix = $_SESSION['GAME_PREFIX'];

    $ckl_existing_record_id = NULL;

    // Search for the existing controller-Worker combo
    $sql = "SELECT id FROM {$prefix}controller_known_locations
        WHERE controller_id = :controller_id
            AND location_id = :location_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':controller_id' => $controller_id,
        ':location_id' => $location_id
    ]);
    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($debug) echo sprintf(" existingRecord: %s<br/> ", var_export($existingRecord,true));

    if (!empty($existingRecord)) {
        try{
            $ckl_existing_record_id = $existingRecord['id'];
            // Update if record exists
            $sql = sprintf("UPDATE {$prefix}controller_known_locations
                SET last_discovery_turn = :turn_number
                %s
                WHERE id = :id",
                $found_secret ? ", found_secret = :found_secret" : ""
            );
            $stmt = $pdo->prepare($sql);
            if ($found_secret) {
                $stmt->bindParam(':found_secret', $found_secret, PDO::PARAM_BOOL);
            }
            $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
            $stmt->bindParam(':id', $existingRecord['id'], PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__."(): Error UPDATE {$prefix}controller_known_locations Failed: " . $e->getMessage()."<br />";
        }
    } else {
        try{
            // Insert if record doesn't exist
            $sqlInsert = "INSERT INTO {$prefix}controller_known_locations (
                controller_id, location_id, found_secret, first_discovery_turn, last_discovery_turn
            ) VALUES (
                :cid, :lid, :found_secret, :first_discovery_turn, :last_discovery_turn
            )";
            $insertStmt = $pdo->prepare($sqlInsert);
            $insertStmt->bindParam(':cid', $controller_id, PDO::PARAM_INT);
            $insertStmt->bindParam(':lid', $location_id, PDO::PARAM_INT);
            $insertStmt->bindParam(':found_secret', $found_secret, PDO::PARAM_BOOL);
            $insertStmt->bindParam(':first_discovery_turn', $turn_number, PDO::PARAM_INT);
            $insertStmt->bindParam(':last_discovery_turn', $turn_number, PDO::PARAM_INT);
            $insertStmt->execute();
            $ckl_existing_record_id = $pdo->lastInsertId();
        } catch (PDOException $e) {
            echo __FUNCTION__."(): Error INSERT INTO {$prefix}controller_known_locations Failed: " . $e->getMessage()."<br />";
        }
    }
    return $ckl_existing_record_id;
}

/**
 * Show the owned artefacts of a controller
 * 
 * @param PDO $pdo
 * @param int $controller_id
 * 
 * @return string
 */
function showOwnedArtefacts($pdo, $controller_id) {
    $html = '';
    $prefix = $_SESSION['GAME_PREFIX'];
    $sql ="SELECT artefacts.name, artefacts.description, artefacts.full_description
        FROM {$prefix}artefacts artefacts
        JOIN {$prefix}locations locations ON artefacts.location_id = locations.id
        WHERE locations.controller_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$controller_id]);
    $artefacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($artefacts)) {
        foreach ($artefacts as $artefact) {
            $html .= sprintf('<strong>%s</strong> : %s %s</li>', $artefact['name'], $artefact['description'], $artefact['full_description']);
        }
    }
    return $html;
}

/** Build HTML to give knowleadge of a worker to a controller
 * 
 * @param PDO $pdo : database connection
 * @param int $controller_id
 * @param int $turn_number
 * 
 * @return string
 */
function buildGiveKnowledgeHTML($pdo, $origin = 'controller', $controller_id = NULL) {
    $html = '';

    $returnLink = '/controllers/action.php';
    if ($origin == 'admin') 
        $returnLink = '/controllers/management.php';


    $zones = getZonesArray($pdo);

    $zoneSearchElement = '<input type="hidden" name="controller_id" value="'.$controller_id.'">';
    if ($origin == 'admin') { 
        $zoneSearchElement = showZoneSelect($pdo, $zones, null, false, false, true);
    }

    $enemyWorkerOptions = '';
    // For each zone
    if ($origin != 'admin') {
        foreach ($zones as $zone) {
            $zoneEnemyWorkers = getEnemyWorkers($pdo, $zone['id'], $controller_id);
            foreach ( $zoneEnemyWorkers['workers_without_controller'] as $enemyWorker) {
                $enemyWorkerOptions .= sprintf('<option value="%1$s"> %2$s (%3$s)</option>', $enemyWorker['discovered_worker_id'],  $enemyWorker['name'], $zone['name']);
            }
            foreach ( $zoneEnemyWorkers['workers_with_controller'] as $enemyWorker) {
                $enemyWorkerOptions .= sprintf('<option value="%1$s"> %2$s (%3$s)</option>', $enemyWorker['discovered_worker_id'],  $enemyWorker['name'], $zone['name']);
            }
        }
    } else {
        // select from all workers
        $prefix = $_SESSION['GAME_PREFIX'];
        $sql = "SELECT w.id AS worker_id, CONCAT (w.firstname, ' ', w.lastname) AS name, z.name AS zone_name FROM {$prefix}workers w JOIN {$prefix}zones z ON w.zone_id = z.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($workers as $worker) {
            $enemyWorkerOptions .= sprintf('<option value="%1$s"> %2$s (%3$s)</option>', $worker['worker_id'],  $worker['name'], $worker['zone_name']);
        }
    }
    $enemyWorkersSelect = sprintf("
        <div class='control for-select'>
            <div class='select is-fullwidth'>
                <select id='enemyWorkersSelect' name='enemy_worker_id' required>
                    <option value=\"\">Sélectionner un agent</option>
                    %s
                </select>
            </div>
        </div>
        ",
        $enemyWorkerOptions
    );

    $controllers = getControllers($pdo, null, null, ($origin != 'admin'), ($origin != 'admin') ? $controller_id : NULL);
    $htmlGiftInformationAgent = sprintf('
        <form action="/%s/%s" method="GET">
        <h4 class="title is-5 mt-5">Sur les agents connus:</h4>
            %s
            <div class="field">
                Sélectionner une faction à informer : %s
            </div>
            <div class="field">
                Sélectionner un agent connu: 
                %s
            </div>
            <div class="field">
                <div class="control">
                    <input type="submit" name="giftInformationAgent" value="Donner l\'information" class="button is-link">
                </div>
            </div>
        </form>', 
        $_SESSION['FOLDER'],
        $returnLink,
        $zoneSearchElement,
        showControllerSelect($controllers, $controller_id, 'target_controller_id' ),
        $enemyWorkersSelect
    );


    $knownLocationsOptions = '';
    if ($origin == 'admin') {
        $locations = getLocationsArray($pdo);
        foreach ($locations as $location) {
            $knownLocationsOptions .= sprintf('<option value="%1$s"> %2$s (%3$s)</option>', $location['id'],  $location['name'], $location['zone_name']);
        }
    } else {
        // Exclude own from CKL — listControllerLinkedLocations adds them once.
        $controllerKnownLocations = listControllerKnownLocations($pdo, $controller_id, false, false, true);
        $controllerLinkedLocations = listControllerLinkedLocations($pdo, $controller_id);
        foreach ($zones as $zone) {
            if (isset($controllerKnownLocations[$zone['id']])) {
                foreach ($controllerKnownLocations[$zone['id']]['locations'] as $location) {
                    $knownLocationsOptions .= sprintf('<option value="%1$s"> %2$s (%3$s)</option>', $location['id'],  $location['name'], $zone['name']);
                }
            }
            if (isset($controllerLinkedLocations[$zone['id']])) {
                foreach ($controllerLinkedLocations[$zone['id']]['locations'] as $location) {
                    $knownLocationsOptions .= sprintf('<option value="%1$s"> %2$s (%3$s)</option>', $location['id'],  $location['name'], $zone['name']);
                }
            }
        }
    }
    $knownLocationsSelect = sprintf("
        <div class='control for-select'>
            <div class='select is-fullwidth'>
                <select id='locationsSelect' name='location_id' required>
                    <option value=\"\">Sélectionner un lieu</option>
                    %s
                </select>
            </div>
        </div>
        ",
        $knownLocationsOptions
    );
        
    $htmlGiftInformationLocation = sprintf('
        <form action="/%s/%s" method="GET" >
        <h4 class="title is-5 mt-5">Sur les lieux connus:</h4>
            <div class="field">
                Sélectionner une faction à informer : %s
            </div>
            <div class="field">
                Sélectionner un lieu connu: 
                %s
            </div>
            <div class="field">
                <div class="control">
                    <input type="submit" name="giftInformationLocation" value="Donner l\'information" class="button is-link">
                </div>
            </div>
        </form>', 
        $_SESSION['FOLDER'],
        $returnLink,
        showControllerSelect($controllers, $controller_id, 'target_controller_id' ),
        $knownLocationsSelect
    );

    $html = '<div class="box mb-5"><details><summary class="title is-5">Donner des informations :</summary>';
    $html .= $htmlGiftInformationAgent;
    $html .= $htmlGiftInformationLocation;
    $html .= '</details></div>';
    return $html;
}

/**
 * Append a row to information_gift_logs. Silent on error; the caller's
 * actual gift-information action has already succeeded (CKE/CKL row
 * written) and we don't want the log failure to surface as a user-facing
 * error.
 *
 * @param PDO    $pdo
 * @param int    $giver_id
 * @param int    $recipient_id
 * @param string $target_type 'agent' | 'location'
 * @param int    $target_id   worker_id or location_id
 * @param int    $turn
 *
 * @return void
 */
function logInformationGift($pdo, $giver_id, $recipient_id, $target_type, $target_id, $turn) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare("INSERT INTO {$prefix}information_gift_logs
            (giver_controller_id, recipient_controller_id, target_type, target_id, turn)
            VALUES (:giver, :recipient, :type, :tid, :turn)");
        $stmt->execute([
            ':giver'     => (int)$giver_id,
            ':recipient' => (int)$recipient_id,
            ':type'      => $target_type,
            ':tid'       => (int)$target_id,
            ':turn'      => (int)$turn,
        ]);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT information_gift_logs failed: ".$e->getMessage()."<br />";
    }
}

/**
 * Fetch information gifts received by a controller, newest first.
 * Each row resolves `target_label` via the appropriate table:
 *   target_type='agent'    → worker firstname + lastname
 *   target_type='location' → location name
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return array each row: ['turn', 'giver', 'target_type', 'target_label']
 */
function getInformationGiftsReceived($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $sql = "SELECT
            l.turn,
            l.target_type,
            l.target_id,
            CONCAT(c.firstname, ' ', c.lastname) AS giver
        FROM {$prefix}information_gift_logs l
        JOIN {$prefix}controllers c ON l.giver_controller_id = c.id
        WHERE l.recipient_controller_id = :recipient
        ORDER BY l.turn DESC, l.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':recipient' => (int)$controller_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT information_gift_logs failed: ".$e->getMessage()."<br />";
        return [];
    }

    foreach ($rows as &$row) {
        if ($row['target_type'] === 'agent') {
            $w = $pdo->prepare("SELECT CONCAT(firstname, ' ', lastname) AS label FROM {$prefix}workers WHERE id = :id");
            $w->execute([':id' => (int)$row['target_id']]);
            $row['target_label'] = $w->fetchColumn() ?: '#'.(int)$row['target_id'];
        } elseif ($row['target_type'] === 'location') {
            $l = $pdo->prepare("SELECT name AS label FROM {$prefix}locations WHERE id = :id");
            $l->execute([':id' => (int)$row['target_id']]);
            $row['target_label'] = $l->fetchColumn() ?: '#'.(int)$row['target_id'];
        } else {
            $row['target_label'] = '#'.(int)$row['target_id'];
        }
    }
    return $rows;
}