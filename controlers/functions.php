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

/**
 * This function returns an array of all bases a controler has or a NULL
 * 
 * params
 *  $controler_id string
 * 
 * returns
 * array() | NULL
 */
function has_base($pdo, $controler_id) {

    $sql = "SELECT zone_id FROM locations WHERE controler_id = :controler_id and is_base = TRUE";
    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':controler_id' => $controler_id]);
        $bases = $stmt->fetchALL(PDO::FETCH_ASSOC);
        return $bases;
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT locations Failed: " . $e->getMessage()."<br />";
        return NULL;
    }
}

/**
 * 
 */
function create_base($pdo, $controler_id, $zone_id) {
    
    $controlers = getControlers($pdo, NULL, $controler_id);
    $controler_name = $controlers[0]['firstname']. ' '. $controlers[0]['lastname'];

    $discovery_diff = 6;
    $power_list = getPowersByType($pdo,'3', $controler_id, FALSE);
    foreach ($power_list as $power ) {
        $discovery_diff += $power['enquete'];
    }

    $description = sprintf (
        "Nous avons trouver le repaire de %$1s. Ses serviteurs ne semblent pas avoir fini de re-mettre en place les défences qui existaient avant la crue.
        En attaquant ce lieu nous pourrions lui porte un coup fatal.
        Sa disiparition causerait certainement quelques questions à l'Elyséum, mais un joueur en moins sur l'échéquier politique est toujours bénéfique.
        Nous ne devons pas tarder a prendre notre décision, ses defenses se refenforcent de %$2s en %$2s.",
        $controler_name,
        getConfig($pdo, 'time_value')
    );
    $sql = "INSERT INTO locations (zone_id, name, description, controler_id, discovery_diff, can_be_destroyed, is_base) VALUES
        ($zone_id, 'Repaire', $description, $controler_id, $discovery_diff, TRUE, TRUE)";

    try{
        // Update config value in the database
        $stmt = $pdo->prepare($sql);
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
