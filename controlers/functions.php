<?php

// Function to get Controlers and return as an array
function getControlers($pdo, $player_id = NULL, $controler_id = NULL) {
    $controlersArray = array();

    try{
        $sql = "SELECT c.*, f.name AS faction_name FROM controlers c LEFT JOIN factions f ON c.faction_id = f.ID";
        if ($player_id !== NULL){
            $sql .= "
                INNER JOIN player_controler pc ON pc.controler_id = c.id
                WHERE pc.player_id = '$player_id'";
        }
        if ($controler_id !== NULL){
            $sql .= sprintf (
                " %s c.id = '%s'",
                $player_id !== NULL ? 'AND' : 'WHERE',
                $controler_id
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
    $controlers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store Controlers in the array
    foreach ($controlers as $controler) {
        $controlersArray[] = $controler;
    }

    return $controlersArray;
}

/**
 * Show list of controler options for à controler select field.
 */
function showControlerSelect($controlers, $field_name = 'controler_id' ) {

    if (empty($controlers)) return '';
    $controlerOptions = '';
    // Display select list of Controlers
    foreach ( $controlers as $controler) {
        $controlerOptions .= sprintf (
            "<option value='%s'> %s %s </option>",
            $controler['id'],
            $controler['firstname'],
            $controler['lastname']
        );
    }

    $showControlerSelect = sprintf('
        <select id=\'controlerSelect\' name=\'%1$s\'>
            %2$s
        </select>',
        $field_name,
        $controlerOptions
    );
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showControlerSelect: ".var_export($showControlerSelect, true)."<br /><br />";

    return $showControlerSelect;
}

/** This function resets the turn_recruted_workers and turn_firstcome_workers to 0 for every controler */
function  restartTurnRecrutementCount($pdo){
    $sql = 'UPDATE controlers SET turn_firstcome_workers=0, turn_recruted_workers=0 WHERE TRUE';
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
function canStartFirstCome($pdo, $controler_id) {

    $controlerValues = getControlers($pdo, NULL, $controler_id);
    if ( $_SESSION['DEBUG'] == true )
    echo '<p>'
        .'; turn_firstcome_workers: '. getConfig($pdo, 'turn_firstcome_workers')
        .'; turn_firstcome_workers :'. $controlerValues[0]['turn_firstcome_workers']
    .'</p>';
    if (
        (INT)$controlerValues[0]['turn_firstcome_workers'] < (INT)getConfig($pdo, 'turn_firstcome_workers')
    )  return true;
    return false;
}

/**
 *
 */
function canStartRecrutement($pdo, $controler_id, $turnNumber){

    $controlerValues = getControlers($pdo, NULL, $controler_id);
    if ( $_SESSION['DEBUG'] == true )
    echo '<p>turncounter: '. $turnNumber
        .'; turn_recrutable_workers: '. getConfig($pdo, 'turn_recrutable_workers')
        .'; start_workers :'. $controlerValues[0]['start_workers']
        .'; turn_recruted_workers :'. $controlerValues[0]['turn_recruted_workers']
    .'</p>';

    if (
        hasBase($pdo, $controler_id)
        &&
        (
            ( $turnNumber == 0 )
            && ( (INT)$controlerValues[0]['turn_recruted_workers'] < (INT)$controlerValues[0]['start_workers'] )
        ) || (
            ( (INT)$turnNumber > 0 )
            && ( $controlerValues[0]['turn_recruted_workers'] < (INT)getConfig($pdo, 'turn_recrutable_workers') )
        )
    ) return true;
    return false;
}

/**
 * This function returns an array of all bases a controler has or a NULL
 *
 * params
 *  $controler_id string
 *
 * returns
 * array() | NULL
 */
function hasBase($pdo, $controler_id) {

    $sql = "SELECT * FROM locations WHERE controler_id = :controler_id and is_base = TRUE";
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':controler_id' => $controler_id]);
        $bases = $stmt->fetchALL(PDO::FETCH_ASSOC);
        return $bases;
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT locations Failed: " . $e->getMessage()."<br />";
    }
    return NULL;
}

/**
 *
 */
function createBase($pdo, $controler_id, $zone_id) {
    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true') $debug = TRUE;

    $controlers = getControlers($pdo, NULL, $controler_id);
    $controler_name = $controlers[0]['firstname']. ' '. $controlers[0]['lastname'];
    echo sprintf("controler_name : %s </br>", $controler_name);

    $discovery_diff = 6;
    $power_list = getPowersByType($pdo,'3', $controler_id, FALSE);
    echo sprintf("power_list : %s </br>", $power_list);
    foreach ($power_list as $power ) {
        $discovery_diff += $power['enquete'];
    }
    echo sprintf("discovery_diff : %s </br>", $discovery_diff);

    $timeValue = getConfig($pdo, 'timeValue');
    echo sprintf("timeValue : %s </br>", $timeValue);

    $description = sprintf(
        getConfig($pdo, 'texteBaseRepaire'),
        $controler_name,
        $timeValue
    );

    // Check if base already exists for this controler in the zone
    $checkSql = "SELECT COUNT(*) FROM locations WHERE zone_id = :zone_id AND controler_id = :controler_id AND is_base = TRUE";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':zone_id' => $zone_id,
        ':controler_id' => $controler_id
    ]);

    if ($checkStmt->fetchColumn() > 0) {
        if ($debug) echo "Base already exists for this controler in this zone.<br />";
        return false;
    }

    $sql = "INSERT INTO locations (zone_id, name, description, controler_id, discovery_diff, can_be_destroyed, is_base) VALUES
        (:zone_id, 'Repaire', :description, :controler_id, :discovery_diff, TRUE, TRUE)";
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':controler_id', $controler_id, PDO::PARAM_INT);
        $stmt->bindParam(':discovery_diff', $discovery_diff, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT locations Failed: " . $e->getMessage()."<br />";
        return false;
    }
    return true;
}

// TODO: move base
    // update locations set zone_id where controler_id = "%s";


// TODO: attack ennemy base


/**
 *
 *
 */


function addWorkerToCKE($pdo, $searcher_controler_id, $found_id, $turn_number, $zone_id) {
    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true') $debug = TRUE;

    $cke_existing_record_id = NULL;

    // Search for the existing Controler-Worker combo
    $sql = "SELECT id FROM controlers_known_enemies
        WHERE controler_id = :searcher_controler_id
            AND discovered_worker_id = :found_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':searcher_controler_id' => $searcher_controler_id,
        ':found_id' => $found_id
    ]);
    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    echo sprintf(" existingRecord: %s<br/> ", var_export($existingRecord,true));

    if (!empty($existingRecord)) {
        $cke_existing_record_id = $existingRecord['id'];
        // Update if record exists
        $sql = "UPDATE controlers_known_enemies
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
        $sql = "INSERT INTO controlers_known_enemies
            (controler_id, discovered_worker_id, first_discovery_turn, last_discovery_turn, zone_id)
            VALUES (:searcher_controler_id, :found_id, :turn_number, :turn_number, :zone_id)";
        if ($debug) echo "sql :".var_export($sql, true)." <br>";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
        ':searcher_controler_id' => $searcher_controler_id,
        ':found_id' => $found_id,
        ':turn_number' => $turn_number,
        ':zone_id' => $zone_id,
        ]);
        $cke_existing_record_id = $pdo->lastInsertId();
    }
    return $cke_existing_record_id;
}