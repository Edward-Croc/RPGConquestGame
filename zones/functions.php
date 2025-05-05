<?php

function getZoneName($pdo, $zone_id){
    try{
        $sql = "SELECT name FROM zones AS z WHERE id=$zone_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    // Fetch the results
    $zone = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $zone[0]['name'];
}

// Function to get Controlers and return as an array
function getZonesArray($pdo, $zone_id = NULL) {
    $zonesArray = array();

    try{
        $sql = "SELECT * FROM zones AS z";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $zones;
}

function showZoneSelect($zonesArray, $show_text = false, $place_holder = true){

    if (empty($zonesArray)) return '';

    $zoneOptions = '';
    // Display select list of Controlers
    foreach ( $zonesArray as $zone) {
        $zoneOptions .= sprintf(
            '<option value=\'%1$s\'> %2$s (%1$s) </option>',
            $zone['id'], $zone['name']
        );
    }

    $showZoneSelect = sprintf(" %s
        <select id='zoneSelect' name='zone_id'>
            %s
            %s
        </select>
        ",
        $show_text ? 'Quartier :' : '',
        $place_holder ? "<option value=''>Select Zone</option>": '',
        $zoneOptions
    );
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";

    return $showZoneSelect;
}

// Function to get Controlers and return as an array
function getLocationsArray($pdo) {
    $locationsArray = array();

    try{
        $sql = "SELECT * FROM locations AS z";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $location = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store Controlers in the array
    foreach ($locations as $location) {
        $locationsArray[] = $location;
    }

    return $locationsArray;
}

// calculateSecretLocationDefence
// get BaseLaireDef from config 
// add current turn + setup_turn
// OR
// get BaseSecretDef from config

// add servants in zone if controler_id is set


// showControlerKnownSecrets
// Get elements from controler_known_locations by controler ID for Zone_id
// show name and text for location 
// Destroy location 
// if can_be_destroyed that add button for servants attack on location
// and button for vampire attack on location


