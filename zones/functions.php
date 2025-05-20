<?php

/**
 * Recupere le nom de la zone
 *
 * @param PDO $pdo
 * @param int $zone_id
 *
 */
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

/**
 * Function to get ZONEs and return as an array
 *
 * @param PDO $pdo
 * @param int $zone_id | NULL
 *
 */
function getZonesArray($pdo, $zone_id = NULL) {
    $zonesArray = array();

    try{
        $sql = "SELECT z.id AS zone_id, c.id AS controller_id, * FROM zones AS z
            LEFT JOIN controllers AS c ON c.id = z.claimer_controller_id
            ORDER BY z.id ASC";
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

/**
 * Function to prepare the zone selector from à list of zones
 *
 * @param PDO $pdo
 * @param array $zonesArray
 * @param bool $show_text default: false ->
 * @param bool $place_holder default: true -> Do we start with and empty spot
 *
 */
function showZoneSelect($pdo, $zonesArray, $show_text = false, $place_holder = true){

    if (empty($zonesArray)) return '';

    $zoneOptions = '';
    // Display select list of controllers
    foreach ( $zonesArray as $zone) {
        $zoneOptions .= sprintf(
            '<option value=\'%1$s\'> %2$s (%1$s) </option>',
            $zone['zone_id'], $zone['name']
        );
    }

    $showZoneSelect = sprintf(" %s
        <select id='zoneSelect' name='zone_id'>
            %s
            %s
        </select>
        ",
        $show_text ? ucfirst(getConfig($pdo, 'textForZoneType')) : '',
        $place_holder ? "<option value=''>Select Zone</option>": '',
        $zoneOptions
    );
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";

    return $showZoneSelect;
}

/** Function to get Locations and return as an array
 * @param PDO $pdo
*/
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
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store locations in the array
    foreach ($locations as $location) {
        $locationsArray[] = $location;
    }

    return $locationsArray;
}

/**
 * Function to recalculateBaseDefence
 * @param PDO $pdo
 */
function recalculateBaseDefence($pdo) {
    // Get all bases with their controller and zone
    $sql = "SELECT id, controller_id, zone_id FROM locations WHERE is_base = TRUE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $bases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bases as $base) {
        $controller_id = $base['controller_id'];
        $zone_id = $base['zone_id'];
        $id = $base['id'];

        $new_diff = calculateSecretLocationDiscoveryDiff($pdo, $controller_id, $zone_id, $id);

        try {
            // Update base with new difficulty
            $update_sql = "
                UPDATE locations
                SET discovery_diff = :new_diff
                WHERE id = :id
            ";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                ':new_diff' => $new_diff,
                ':id' => $base['id']
            ]);
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
            return FALSE;
        }

        echo sprintf("Updated base (C: %s, Z: %s) to difficulty: %s<br/>", $controller_id, $zone_id, $new_diff);
    }
    return TRUE;
}

function calculatecontrollerValue($pdo, $controller_id, $zone_id = null, $location_id = null, $type) {
    $debug = $_SESSION['DEBUG'];
    $value = 0;

    // Base value
    $base = (int)getConfig($pdo, "base{$type}");
    $value = $base;
    if ($debug) echo sprintf("%s (base) : %d<br>", $type, $value);

    // Powers
    $powerMultiplier = (int)getConfig($pdo, "base{$type}AddPowers");
    $maxPowerBonus = (int)getConfig($pdo, "maxBonus{$type}Powers");
    if ($powerMultiplier !== 0) {
        switch ($type){ // 'defence', 'attack' or 'enquete'
            case 'DiscoveryDiff' :
                $attribute = 'enquete';
                break;
            case 'Defence' :
                $attribute = 'defence';
                break;
            case 'Attack' :
                $attribute = 'attack';
                break;
            default : 
                $attribute =  Null;
                break;
        }
        if (!empty($attribute) ){
            $power_list = getPowersByType($pdo, '3', $controller_id, false);
            $bonus = 0;
            foreach ($power_list as $power) {
                $bonus += isset($power[$attribute]) ? $power[$attribute] * $powerMultiplier : 0;
            }
            if ($maxPowerBonus > 0) $bonus = min($bonus, $maxPowerBonus);
            $value += $bonus;
            if ($debug) echo sprintf("%s (+powers) : %d<br>", $type, $value);
        } else echo sprintf("%s : attribute is NULL <br>", $type);
    }

    // Workers
    $workerMultiplier = (int)getConfig($pdo, "base{$type}AddWorkers");
    $maxWorkerBonus = (int)getConfig($pdo, "maxBonus{$type}Workers");
    if ($workerMultiplier !== 0) {
        $sql = "
            SELECT COUNT(*) AS worker_count
            FROM workers w
            JOIN controller_worker cw ON cw.worker_id = w.id
            WHERE cw.controller_id = :controller_id
              AND w.zone_id = :zone_id
              AND w.is_active = TRUE
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':controller_id' => $controller_id,
            ':zone_id' => $zone_id
        ]);
        $worker_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['worker_count'] ?? 0);
        $bonus = $worker_count * $workerMultiplier;
        if ($maxWorkerBonus > 0) $bonus = min($bonus, $maxWorkerBonus);
        $value += $bonus;
        if ($debug) echo sprintf("%s (+workers) : %d<br>", $type, $value);
    }

    // Turns / Age
    $turnMultiplier = (int)getConfig($pdo, "base{$type}AddTurns");
    $maxTurnBonus = (int)getConfig($pdo, "maxBonus{$type}Turns");
    if ($turnMultiplier !== 0 && $location_id !== NUll) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
        echo "turn_number : $turn_number <br>";

        $sql = "
            SELECT setup_turn
            FROM locations
            WHERE id = :location_id
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':location_id' => $location_id
        ]);
        $base = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($base) {
            $setup_turn = (int)$base['setup_turn'];
            $turn_diff = max(0, $turn_number - $setup_turn);
            $bonus = $turn_diff * $turnMultiplier;
            if ($maxTurnBonus > 0) $bonus = min($bonus, $maxTurnBonus);
            $value += $bonus;
            if ($debug) echo sprintf("%s (+turns) : %d<br>", $type, $value);
        }
    }

    if ($debug) echo sprintf("%s final value : %d<br>", $type, $value);
    return $value;
}

function calculateSecretLocationDiscoveryDiff($pdo, $controller_id, $zone_id, $location_id = null) {
    return calculatecontrollerValue($pdo, $controller_id, $zone_id, $location_id, 'DiscoveryDiff');
}

function calculateSecretLocationDefence($pdo, $controller_id, $zone_id, $location_id) {
    return calculatecontrollerValue($pdo, $controller_id, $zone_id, $location_id, 'Defence');
}

function calculatecontrollerAttack($pdo, $controller_id, $zone_id) {
    return calculatecontrollerValue($pdo, $controller_id, $zone_id, null, 'Attack');
}


/**
 * Affiche les bases connues ou possédées dans une zone par un contrôleur
 * Permet d'attaquer les bases destructibles
 *
 * @param PDO $pdo
 * @param int $controller_id
 * @param int $zone_id
 */
function showcontrollerKnownSecrets($pdo, $controller_id, $zone_id) {
    $returnText = '';
    // Bases possédées par ce contrôleur dans la zone
    $sql = "
        SELECT l.id, l.name, l.can_be_destroyed, l.description
        FROM locations l
        WHERE l.controller_id = :controller_id
        AND l.zone_id = :zone_id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':controller_id' => $controller_id,
        ':zone_id' => $zone_id
    ]);
    $owned_bases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($owned_bases)) {
        $returnText .= "<p><strong>Vos lieux secrets:</strong><br />";
        foreach ($owned_bases as $base) {
            $returnText .=  sprintf(
                "<b>%s</b><br><em>%s</em><br />",
                htmlspecialchars($base['name']),
                htmlspecialchars($base['description'])
            );
        }
        $returnText .=  "</p>";
    }

    // Bases ennemies connues dans la zone
    $sql = "
        SELECT l.id, l.name, l.can_be_destroyed, l.description
        FROM controller_known_locations ckl
        JOIN locations l ON ckl.location_id = l.id
        WHERE ckl.controller_id = :controller_id
        AND l.zone_id = :zone_id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':controller_id' => $controller_id,
        ':zone_id' => $zone_id
    ]);
    $known_bases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($known_bases)) {
        $returnText .=  "<p><strong>Lieux découverts :</strong><br />";
        foreach ($known_bases as $base) {
            $returnText .=  sprintf(
                "<b>%s</b><br/><em>%s</em><br/>",
                htmlspecialchars($base['name']),
                htmlspecialchars($base['description'])
            );

            if ($base['can_be_destroyed']) {
                $returnText .=  sprintf('
                    <form action="/RPGConquestGame/controllers/action.php" method="GET">
                        <input type="hidden" name="controller_id" value="%d">
                        <input type="hidden" name="location_id" value="%d">
                        <input type="submit" name="attack" value="Attaquer personnellement cette base" class="controller-action-btn">
                    </form>',
                    $controller_id,
                    $base['id']
                );
            }
        }
        $returnText .=  '</p>';
    }
    return $returnText;
}
