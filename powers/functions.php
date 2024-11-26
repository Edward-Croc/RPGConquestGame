<?php

function randomPowersByType($pdo, $type_list, $limit = 1) {
    $powerArray = array();

    try{
        // Get x random values from powers for a power_type 
        $sql = "SELECT * FROM powers
        INNER JOIN link_power_type ON link_power_type.power_id = powers.id
        WHERE link_power_type.power_type_id IN ($type_list) ORDER BY RANDOM() LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Store worker_origins in the array
    foreach ($powers as $power) {
        $powerArray[] = $power;
    }
    return $powerArray;
}

function getPowersByType($pdo, $type_list) {
    $powerArray = array();

    // Get all powers from a type_list
    try{
        $sql = "SELECT * FROM powers
        INNER JOIN link_power_type ON link_power_type.power_id = powers.id
        WHERE link_power_type.power_type_id IN ($type_list)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }   
    // Fetch the results
    $powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Store worker_origins in the array
    foreach ($powers as $power) {
        $powerArray[] = $power;
    }
    return $powerArray;
}

function getBasePowers($pdo, $type_list, $controler_id = null) {
    $powerArray = array();

    $basePowerNames = '';
    $configBasePowerNames = getConfig($pdo, 'basePowerNames');
    if ( !empty($configBasePowerNames) ) {
        $basePowerNames = $configBasePowerNames;
    }

    // Get all powers from a type_list
    $sql = sprintf("SELECT powers.* FROM powers WHERE powers.id IN (
            SELECT distinct(powers.id)
            FROM powers
            JOIN link_power_type ON link_power_type.power_id = powers.id 
            LEFT JOIN faction_powers ON faction_powers.link_power_type_id = link_power_type.id 
            LEFT JOIN factions ON factions.id = faction_powers.faction_id 
            LEFT JOIN controlers ON controlers.faction_id = factions.id 
            WHERE link_power_type.power_type_id IN (%s)
            AND (%s %s %s )
        )",
        $type_list,
        $basePowerNames != "" ? "powers.name IN ($basePowerNames)" : '',
        ($controler_id != "" && $basePowerNames != "") ? "OR" : '',
        $controler_id != "" ? "controlers.id IN ($controler_id)" : ''
    );
    if ($_SESSION['DEBUG'] == true){
        echo "getBasePowers(): sql  $sql <br />";
    }
    try{

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }   

    // Fetch the results
    $powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Store worker_origins in the array
    foreach ($powers as $power) {
        $powerArray[] = $power;
    }
    return $powerArray;
}