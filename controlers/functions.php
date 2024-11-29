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
