<?php

// Function to get controllers and return as an array
function getZonesArray($pdo) {
    $zonesArray = array();

    $sql = "SELECT z.*, l.* FROM zones AS z LEFT JOIN locations l ON z.ID = l.zone_id";
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