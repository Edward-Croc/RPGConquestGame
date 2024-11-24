<?php

// Function to get controllers and return as an array
function getControllersArray($pdo, $player_id = NULL) {
    $controlersArray = array();

    $sql = "SELECT c.*, f.name AS faction_name FROM controlers c LEFT JOIN factions f ON c.faction_id = f.ID";
    if ($player_id !== NULL){
        $sql .= "
            INNER JOIN player_controler pc ON pc.controler_id = c.id
            WHERE pc.player_id = '$player_id'";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // Fetch the results
    $controlers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store controllers in the array
    foreach ($controlers as $controler) {
        $controlersArray[] = $controler;
    }

    return $controlersArray;
}

function getControler($pdo, $controler_id) {
    try{
        $stmt = $pdo->prepare("SELECT * 
            FROM controlers 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $controler_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        echo "getConfig $configName failed: " . $e->getMessage()."<br />";
        return NULL;
    }
}
