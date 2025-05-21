<?php

// Function to get controllers and return as an array
function getControllers($pdo, $player_id = NULL, $controller_id = NULL) {
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
 * Show list of controller options for à controller select field.
 */
function showcontrollerSelect($controllers, $field_name = 'controller_id', $addEmptySpace = FALSE ) {

    if (empty($controllers)) return '';
    $controllerOptions = '';
    if ($addEmptySpace) $controllerOptions .= "<option value='null'> Personne </option>";
    // Display select list of controllers
    foreach ( $controllers as $controller) {
        $controllerOptions .= sprintf (
            "<option value='%s'> %s %s </option>",
            $controller['id'],
            $controller['firstname'],
            $controller['lastname']
        );
    }

    $showcontrollerSelect = sprintf('
        <select id=\'controllerSelect\' name=\'%1$s\'>
            %2$s
        </select>',
        $field_name,
        $controllerOptions
    );
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showcontrollerSelect: ".var_export($showcontrollerSelect, true)."<br /><br />";

    return $showcontrollerSelect;
}

/** This function resets the turn_recruited_workers and turn_firstcome_workers to 0 for every controller */
function  restartTurnRecrutementCount($pdo){
    $sql = 'UPDATE controllers SET turn_firstcome_workers=0, turn_recruited_workers=0 WHERE TRUE';
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return TRUE;
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
    echo '<p>turncounter: '. $turnNumber
        .'; turn_recrutable_workers: '. getConfig($pdo, 'turn_recrutable_workers')
        .'; start_workers :'. $controllerValues[0]['start_workers']
        .'; turn_recruited_workers :'. $controllerValues[0]['turn_recruited_workers']
    .'</p>';

    if (
        hasBase($pdo, $controller_id)
        &&
        (
            ( $turnNumber == 0 )
            && ( (INT)$controllerValues[0]['turn_recruited_workers'] < (INT)$controllerValues[0]['start_workers'] )
        ) || (
            ( (INT)$turnNumber > 0 )
            && ( $controllerValues[0]['turn_recruited_workers'] < (INT)getConfig($pdo, 'turn_recrutable_workers') )
        )
    ) return true;
    return false;
}

/**
 * This function returns an array of all bases a controller has or a NULL
 *
 * @param PDO $pdo : database connection
 * @param string : $controller_id 
 *
 * @return array|NULL : $bases
 */
function hasBase($pdo, $controller_id) {

    $sql = "SELECT l.*, z.name AS zone_name FROM locations l
        LEFT JOIN zones z ON l.zone_id = z.ID
        WHERE controller_id = :controller_id and is_base = TRUE
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
    $debug = $_SESSION['DEBUG'];
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true') $debug = TRUE;

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
    $checkSql = "SELECT COUNT(*) FROM locations WHERE zone_id = :zone_id AND controller_id = :controller_id AND is_base = TRUE";
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
        (:zone_id, :baseName, :description, :controller_id, :discovery_diff, TRUE, TRUE)";
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
        return False;
    }
    return True;
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
    $sql = "UPDATE locations SET zone_id = :zone_id,setup_turn = (SELECT turncounter FROM mechanics LIMIT 1) WHERE id = :base_id";
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
        $stmt->bindParam(':base_id', $base_id, PDO::PARAM_INT);
        $stmt->execute();
        return True;
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE locations SET zone_id: " . $e->getMessage()."<br />";
        return False;
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
function showAttackablecontrollerKnownLocations($pdo, $controller_id) {
    $returnText = NULL;
    // Requête SQL pour récupérer les localisations connues et destructibles
    $sql = "
        SELECT 
            l.id AS location_id,
            l.name AS location_name,
            z.name AS zone_name
        FROM locations l
        JOIN controller_known_locations ckl ON ckl.location_id = l.id
        JOIN zones z ON z.id = l.zone_id
        WHERE 
            l.can_be_destroyed = TRUE
            AND ckl.controller_id = :controller_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':controller_id' => $controller_id]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Affichage des options HTML
    if (!empty($locations)) {
        $returnText .= '<select name="target_location_id">';
        foreach ($locations as $loc) {
            $label = sprintf("%s (%s) - %s", $loc['location_name'], $loc['location_id'],  $loc['zone_name']);
            $returnText .= sprintf('<option value="%d">%s</option>', $loc['location_id'], htmlspecialchars($label));
        }
        $returnText .= '</select>';
    }
    return $returnText;
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
    $return = array('success'=> false, 'message' => '');
    try{
        // Get ZONE ID from target_location_id
        $sql = "SELECT * FROM locations WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $target_location_id]);
        $location = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    $zone_id = $location[0]['zone_id'];
    $attackLocationDiff = getConfig($pdo, 'attackLocationDiff');
    $locationDefence = calculateSecretLocationDefence($pdo, $controller_id, $zone_id, $target_location_id);
    $controllerAttack = calculatecontrollerAttack($pdo, $controller_id, $zone_id);

    // Check result
    if (($controllerAttack - $locationDefence) > $attackLocationDiff){
        // Do actions depending on JSON for location
            $activate_json = json_decode($location[0]['activate_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
                $activate_json = array();
            }
            // Print Call GM Txt
            // Create New location
            //
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
    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true') $debug = TRUE;

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
    echo sprintf(" existingRecord: %s<br/> ", var_export($existingRecord,true));

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