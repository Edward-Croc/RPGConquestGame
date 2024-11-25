<?php

// Function to get controllers and return as an array
function getZonesArray($pdo) {
    $zonesArray = array();

    $sql = "SELECT * FROM zones AS z";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // Fetch the results
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store controllers in the array
    foreach ($zones as $zone) {
        $zonesArray[] = $zone;
    }

    return $zonesArray;
}

// Function to get controllers and return as an array
function getLocationsArray($pdo) {
    $locationsArray = array();

    $sql = "SELECT * FROM locations AS z";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // Fetch the results
    $location = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store controllers in the array
    foreach ($locations as $location) {
        $locationsArray[] = $location;
    }

    return $locationsArray;
}