<?php

// Function to get Controlers and return as an array
function getZonesArray($pdo) {
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

    // Store Controlers in the array
    foreach ($zones as $zone) {
        $zonesArray[] = $zone;
    }

    return $zonesArray;
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

function showZoneSelect($zonesArray){
    $zoneOptions = '';
    // Display select list of Controlers
    foreach ( $zonesArray as $zone) {
        $zoneOptions .= "<option value='" . $zone['id'] . "'>" . $zone['name'] . " </option>";
    }
    
    $showZoneSelect = sprintf("
        <select id='zoneSelect' name='zone'>
            <option value=\'\'>Select Zone</option>
            %s
        </select>
        ",
        $zoneOptions
    );
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";

    return $showZoneSelect;
}