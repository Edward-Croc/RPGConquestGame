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
 * @param int|null $controller_id
 *
 * @return array $zonesArray
 */
function getZonesArray($pdo, $controller_id = null) {
    $zonesArray = array();

    try{
        $sql = sprintf(
            "SELECT
                z.id AS zone_id,
                c.id AS controller_id,
                h.id AS holder_controller_id,
                z.*,
                c.*,
                h.*
            FROM zones AS z
            LEFT JOIN controllers AS c ON c.id = z.claimer_controller_id
            LEFT JOIN controllers AS h ON h.id = z.holder_controller_id
            %s
            ORDER BY z.id ASC",
            (!empty($controller_id))? "WHERE c.id = :controler_id" : ""
        );
        $stmt = $pdo->prepare($sql);
        if (!empty($controller_id)) $stmt->bindParam(':controler_id', $controller_id);
        $stmt->execute();
        // Fetch the results
        $zonesArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    return $zonesArray;
}

/**
 * Function to prepare the zone selector from à list of zones
 *
 * @param PDO $pdo
 * @param array $zonesArray
 * @param bool $showText default: false ->
 * @param bool $place_holder default: true -> Do we start with and empty spot
 *
 * @return string $showZoneSelect
 */
function showZoneSelect($pdo, $zonesArray, $showText = false, $place_holder = true){

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
        $showText ? ucfirst(getConfig($pdo, 'textForZoneType')) : '',
        $place_holder ? "<option value=''>Select Zone</option>": '',
        $zoneOptions
    );
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";

    return $showZoneSelect;
}

/** Function to get Locations and return as an array
 * @param PDO $pdo
 *
 * @return array|null $locationsArray
 *
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
 *
 * @return bool
 *
 */
function recalculateBaseDefence($pdo) {
    // Get all bases with their controller and zone
    $sql = "SELECT id, controller_id, zone_id FROM locations WHERE is_base = True";
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
            return false;
        }

        echo sprintf("Updated base (C: %s, Z: %s) to difficulty: %s<br/>", $controller_id, $zone_id, $new_diff);
    }
    return true;
}

/**
 * Calculate the value 'DEF, ATK, SEARCH' for the controller, and optionnal zone or location
 *
 * @param PDO $pdo
 * @param int $controller_id
 * @param string $type
 * @param int $zone_id
 * @param int|null $location_id
 *
 * @return int $value
 */
function calculateControllerValue($pdo, $controller_id, $type, $zone_id, $location_id = null) {
    $debug = $_SESSION['DEBUG'];
    $value = 0;

    // Base value
    $base = (int)getConfig($pdo, "base{$type}");
    $value = $base;
    if ($debug) echo sprintf("%s (base) : %d<br>", $type, $value);

    // Add +1 if controller owns or claims the zone
    $zoneStmt = $pdo->prepare("
        SELECT holder_controller_id, claimer_controller_id 
        FROM zones 
        WHERE id = :zone_id
    ");
    $zoneStmt->execute([':zone_id' => $zone_id]);
    $zone = $zoneStmt->fetch(PDO::FETCH_ASSOC);

    if ($zone) {
        if ($zone['holder_controller_id'] == $controller_id || $zone['claimer_controller_id'] == $controller_id) {
            $value += 1;
            if ($debug) echo sprintf("%s (+zone control) : %d<br>", $type, $value);
        }
    }

    // Powers
    $powerMultiplier = (int)getConfig($pdo, "base{$type}AddPowers");
    if ($debug) echo sprintf("powerMultiplier : %d<br>", $powerMultiplier);
    $maxPowerBonus = (int)getConfig($pdo, "maxBonus{$type}Powers");
    if ($debug) echo sprintf("maxPowerBonus : %d<br>", $maxPowerBonus);
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
                $attribute =  NULL;
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
    if ($debug) echo sprintf("workerMultiplier : %d<br>", $workerMultiplier);
    $maxWorkerBonus = (int)getConfig($pdo, "maxBonus{$type}Workers");
    if ($debug) echo sprintf("maxWorkerBonus : %d<br>", $maxWorkerBonus);
    if ($workerMultiplier !== 0) {
        $sql = "
            SELECT COUNT(*) AS worker_count
            FROM workers w
            JOIN controller_worker cw ON cw.worker_id = w.id
            WHERE cw.controller_id = :controller_id
              AND w.zone_id = :zone_id
              AND w.is_active = True
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
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];
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

/**
 * Calls the calculateControllerValue function with the 'DiscoveryDiff' type
 *
 * @param PDO $pdo
 * @param int $controller_id
 * @param int $zone_id
 * @param int|null $location_id
 *
 * @return int $value
 */
function calculateSecretLocationDiscoveryDiff($pdo, $controller_id, $zone_id, $location_id = null) {
    return calculateControllerValue($pdo, $controller_id, 'DiscoveryDiff', $zone_id, $location_id);
}

/**
 * Calls the calculateControllerValue function with the 'Defence' type
 *
 * @param PDO $pdo
 * @param int $controller_id
 * @param int $zone_id
 * @param int $location_id
 *
 * @return int $value
 */
function calculateSecretLocationDefence($pdo, $controller_id, $zone_id, $location_id) {
    return calculateControllerValue($pdo, $controller_id, 'Defence', $zone_id, $location_id);
}

/**
 * Calls the calculateControllerValue function with the 'Attack' type
 *
 * @param PDO $pdo
 * @param int $controller_id
 * @param int $zone_id
 *
 * @return int $value
 */
function calculatecontrollerAttack($pdo, $controller_id, $zone_id) {
    return calculateControllerValue($pdo, $controller_id, 'Attack', $zone_id, null);
}


/**
* Displays the known or owned bases in a zone by a controller
* Allows attacking destructible bases
 *
 * @param PDO $pdo
 * @param int $controller_id
 * @param int $zone_id
 *
 * @return string $text
 */
function showcontrollerKnownSecrets($pdo, $controller_id, $zone_id) {
    $returnText = '';
    // Bases owned by this controller in the zone
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

            // Fetch artefacts for this location
            $stmtArt = $pdo->prepare("
            SELECT name, description, full_description 
            FROM artefacts 
            WHERE location_id = :location_id
            ");
            $stmtArt->execute([':location_id' => $base['id']]);
            $artefacts = $stmtArt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($artefacts)) {
                $returnText .= "<ul>";
                foreach ($artefacts as $art) {
                    $returnText .= sprintf(
                        "<li><strong>%s</strong>: %s %s</li>",
                        htmlspecialchars($art['name']),
                        htmlspecialchars($art['description']),
                        htmlspecialchars($art['full_description'])
                    );
                }
                $returnText .= "</ul>";
            }
        }
        $returnText .=  "</p>";
    }

    // Known enemy bases in the zone
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
                    <form action="/%s/controllers/action.php" method="GET">
                        <input type="hidden" name="controller_id" value="%d">
                        <input type="hidden" name="target_location_id" value="%d">
                        <input type="submit" name="attack" value="Mener une équipe d\'attaque sur place" class="controller-action-btn">
                    </form>',
                    $_SESSION['FOLDER'],
                    $controller_id,
                    $base['id']
                );
            }
        }
        $returnText .=  '</p>';
    }
    return $returnText;
}
