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
 * @param int|null $zone_id
 *
 * @return array $zonesArray
 */
function getZonesArray($pdo, $controller_id = null, $zone_id = null) {
    $zonesArray = array();

    try{
        $sql = sprintf(
            "SELECT
                z.id AS zone_id,
                c.id AS controller_id,
                h.id AS holder_controller_id,
                z.*,
                c.lastname as claimer_lastname,
                c.firstname as claimer_firstname,
                h.lastname as holder_lastname,
                h.firstname as holder_firstname
            FROM zones AS z
            LEFT JOIN controllers AS c ON c.id = z.claimer_controller_id
            LEFT JOIN controllers AS h ON h.id = z.holder_controller_id
            %s %s %s %s
            ORDER BY z.id ASC",
            (!empty($controller_id) || !empty($zone_id))? "WHERE" : "",
            (!empty($controller_id))? "c.id = :controler_id" : "",
            (!empty($controller_id) && !empty($zone_id))? " AND " : "",
            (!empty($zone_id))? " z.id = :zone_id" : ""
        );
        $stmt = $pdo->prepare($sql);
        if (!empty($controller_id)) $stmt->bindParam(':controler_id', $controller_id);
        if (!empty($zone_id)) $stmt->bindParam(':zone_id', $zone_id);
        // Execute the statement
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
 * @param int|null $selectedID : ID of the selected zone (optional)
 * @param bool $showText default: false ->
 * @param bool $place_holder default: false -> Do we start with and empty spot
 * @param bool $hideZones default: false -> Do we hide the zones in the list
 *
 * @return string $showZoneSelect
 */
function showZoneSelect($pdo, $zonesArray, $selectedID = null, $showText = false, $place_holder = false, $hideZones = false) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];

    if (empty($zonesArray)) return '';

    $zoneOptions = '';
    foreach ($zonesArray as $zone) {
        if ($hideZones 
            && ($zone['hide_turn_zero'] && $turn_number == 0)
        ) continue;
        $zoneOptions .= sprintf(
            '<option value="%1$s" %3$s >%2$s (%1$s)</option>',
            htmlspecialchars($zone['zone_id']),
            htmlspecialchars($zone['name']),
            ($selectedID !== null && $zone['zone_id'] == $selectedID) ? 'selected' : '',
        );
    }

    $label = $showText ? ucfirst(htmlspecialchars(getConfig($pdo, 'textForZoneType'))) : '';

    $showZoneSelect = sprintf('
            %s
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="zoneSelect" name="zone_id">
                        %s
                        %s
                    </select>
                </div>
            </div>
        ',
        $label ? '<label class="label" for="zoneSelect">'.$label.'</label>' : '',
        $place_holder ? '<option value="">Sélectionner une zone</option>' : '',
        $zoneOptions
    );

    if (!empty($_SESSION['DEBUG']) && $_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";

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
        $sql = "SELECT l.id AS id, z.id AS zone_id, z.name AS zone_name, l.*
            FROM locations AS l
            JOIN zones AS z ON l.zone_id = z.id
            ORDER BY z.id, l.id";
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
    echo "Recalculating base defence<br />";

    // Get all bases with their controller and zone
    $sql = "SELECT id, controller_id, zone_id FROM locations WHERE is_base = True";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $bases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bases as $base) {
        $controller_id = $base['controller_id'];
        $zone_id = $base['zone_id'];
        $id = $base['id'];

        $new_diff = calculateSecretLocationDiscoveryDiff($pdo, $zone_id, $id, $controller_id);

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
function calculateControllerValue($pdo, $type, $zone_id, $controller_id = null, $location_id = null) {
    # $debug = $_SESSION['DEBUG'];
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';
   ## $debug = true;
    $value = 0;

    if ($debug) echo sprintf("calculateControllerValue : %s, %s, %s, %s<br>", $type, $zone_id, $controller_id, $location_id);

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

    if ($controller_id !== null) {
        // Powers
        $powerMultiplier = floatval(getConfig($pdo, "base{$type}AddPowers"));
        if ($debug) echo sprintf("powerMultiplier : %f<br>", $powerMultiplier);
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
                    $bonus += isset($power[$attribute]) ? ceil($power[$attribute] * $powerMultiplier) : 0;
                }
                if ($maxPowerBonus > 0) $bonus = min($bonus, $maxPowerBonus);
                $value += $bonus;
                if ($debug) echo sprintf("%s (+powers) : %d<br>", $type, $value);
            } else echo sprintf("%s : attribute is NULL <br>", $type);
        }

        // Workers
        $workerMultiplier = floatval(getConfig($pdo, "base{$type}AddWorkers"));
        if ($debug) echo sprintf("workerMultiplier : %f<br>", $workerMultiplier);
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
            $bonus = ceil($worker_count * $workerMultiplier);
            if ($maxWorkerBonus > 0) $bonus = min($bonus, $maxWorkerBonus);
            $value += $bonus;
            if ($debug) echo sprintf("%s (+workers) : %d<br>", $type, $value);
        }
    } else {
        if ($debug) echo sprintf("%s : controller_id is NULL <br>", $type);
        $value += getConfig($pdo, "noController{$type}Bonus");
    }

    // Turns / Age
    $turnMultiplier = floatval(getConfig($pdo, "base{$type}AddTurns"));
    if ($debug) echo sprintf("turnMultiplier : %f<br>", $turnMultiplier);
    $maxTurnBonus = (int)getConfig($pdo, "maxBonus{$type}Turns");
    if ($debug) echo sprintf("maxTurnBonus : %d<br>", $maxTurnBonus);
    if ($turnMultiplier !== 0 && $location_id !== NUll) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];

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
            // round up to integer
            $bonus = ceil($turn_diff * $turnMultiplier);
            if ($maxTurnBonus > 0) $bonus = min($bonus, $maxTurnBonus);
            $value += $bonus;
            if ($debug) echo sprintf("%s (+turns) : %d<br>", $type, $value);
        }
    }

    if ($debug) echo sprintf("%s final value : %d<br>", $type, $value);
    return $value;
}

/**
 * Calls the calculateSecretLocationDiscoveryDiff function with the 'DiscoveryDiff' type
 *
 * @param PDO $pdo
 * @param int $zone_id
 * @param int|null $location_id
 * @param int|null $controller_id
 *
 * @return int $value
 */
function calculateSecretLocationDiscoveryDiff($pdo, $zone_id, $location_id = null, $controller_id = null ) {
    return calculateControllerValue($pdo, 'DiscoveryDiff', $zone_id, $controller_id, $location_id);
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
function calculateSecretLocationDefence($pdo, $zone_id, $location_id, $controller_id = null) {
    return calculateControllerValue($pdo, 'Defence', $zone_id, $controller_id, $location_id);
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
function calculatecontrollerAttack($pdo, $zone_id, $controller_id = null) {
    return calculateControllerValue($pdo, 'Attack', $zone_id, $controller_id);
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
function showcontrollerKnownSecrets(PDO $pdo, int $controller_id, int $zone_id): string {
    $returnText = '';
    // Bases owned by this controller in the zone
    $sql = "
        SELECT l.id, l.name, l.can_be_destroyed, l.description, l.hidden_description
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
                "<b>%s</b><br><em>%s%s</em><br />",
                $base['name'],
                $base['description'],
                $base['hidden_description']
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
                        $art['name'],
                        $art['description'],
                        $art['full_description']
                    );
                }
                $returnText .= "</ul>";
            }
        }
        $returnText .=  "</p>";
    }

    // Known enemy bases in the zone
    $sql = "
        SELECT l.id, l.name, l.can_be_destroyed, l.description, l.hidden_description, ckl.found_secret
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
                "<b>%s</b><br/><em>%s%s</em><br/>",
                $base['name'],
                $base['description'],
                ((INT)$base['found_secret'] == 1) ? $base['hidden_description'] : ''
            );

            if ($base['can_be_destroyed'] && hasBase($pdo, $controller_id)) {
                $returnText .=  sprintf('
                    <form action="/%s/controllers/action.php" method="GET">
                        <input type="hidden" name="controller_id" value="%d">
                        <input type="hidden" name="target_location_id" value="%d">
                        <input
                            type="submit" name="attackLocation" 
                            value="Mener une équipe d\'attaque sur place"
                            class="button is-danger controller-action-btn"
                        >
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

/**
 * Get all known locations for a controller, grouped by zone.
 *
 * @param PDO $gameReady
 * @param int $controllerId
 * @param bool $limitByDestroyed
 * 
 * If $limitByDestroyed is true, it will only return locations that can be destroyed.
 * If false, it will return all locations.
 * @return array|null array of controllers know locations
 *  array of [zone_id]=> locations[]=> [
 *      'id' => int,
 *      'name' => string,
 *      'description' => string,
 *      'hidden_description' => string,
 *      'can_be_destroyed' => bool
 *  ]
 */
function listControllerKnownLocations(PDO $gameReady, int $controllerId, bool $limitByDestroyed = false, bool $limitByReparable = false): array|null {
    $sql = sprintf("
        SELECT
            z.id AS zone_id,
            z.name AS zone_name,
            z.description AS zone_description,
            l.id AS location_id,
            l.name AS location_name,
            l.description AS location_description,
            l.hidden_description AS location_hidden_description,
            ckl.found_secret AS location_found_secret,
            l.can_be_destroyed AS location_can_be_destroyed 
        FROM controller_known_locations ckl
        JOIN locations l ON ckl.location_id = l.id
        JOIN zones z ON l.zone_id = z.id
        WHERE ckl.controller_id = :controller_id
        %s%s
        ORDER BY z.id, l.id;
    ",
    $limitByDestroyed ? "AND l.can_be_destroyed = True" : "",
    $limitByReparable ? "AND l.can_be_repaired = True" : ""
    );
    $stmt = $gameReady->prepare($sql);
    $stmt->execute(['controller_id' => $controllerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return null;
    }

    // Group by zones
    $grouped = [];
    foreach ($rows as $row) {
        $zoneId = $row['zone_id'];
        if (!isset($grouped[$zoneId])) {
            $grouped[$zoneId] = [
                'name' => $row['zone_name'],
                'description' => $row['zone_description'],
                'locations' => []
            ];
        }
        $grouped[$zoneId]['locations'][] = [
            'id' => $row['location_id'],
            'name' => $row['location_name'],
            'description' => $row['location_description'],
            'hidden_description' => ((INT)$row['location_found_secret'] == 1) ? $row['location_hidden_description'] : '',
            'can_be_destroyed' => $row['location_can_be_destroyed']
        ];
    }
    return $grouped;
}


/**
 * Get all known locations for a controller, grouped by zone.
 *
 * @param PDO $gameReady
 * @param int $controllerId
 * @return array|null array of controllers know locations
 */
function listControllerLinkedLocations(PDO $gameReady, int $controllerId): array|null {
    $sql = "
        SELECT
            z.id AS zone_id,
            z.name AS zone_name,
            z.description AS zone_description,
            l.id AS location_id,
            l.name AS location_name,
            l.description AS location_description,
            l.hidden_description AS location_hidden_description,
            l.can_be_destroyed AS location_can_be_destroyed 
        FROM locations l
        JOIN zones z ON l.zone_id = z.id
        WHERE l.controller_id = :controller_id
        ORDER BY z.id, l.id;
    ";
    $stmt = $gameReady->prepare($sql);
    $stmt->execute(['controller_id' => $controllerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return null;
    }

    // Group by zones
    $grouped = [];
    foreach ($rows as $row) {
        $zoneId = $row['zone_id'];
        if (!isset($grouped[$zoneId])) {
            $grouped[$zoneId] = [
                'name' => $row['zone_name'],
                'description' => $row['zone_description'],
                'locations' => []
            ];
        }

        // Fetch artefacts for this location
        $artefactStmt = $gameReady->prepare("
            SELECT name, description, full_description 
            FROM artefacts 
            WHERE location_id = :location_id
        ");
        $artefactStmt->execute(['location_id' => $row['location_id']]);
        $artefacts = $artefactStmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped[$zoneId]['locations'][] = [
            'id' => $row['location_id'],
            'name' => $row['location_name'],
            'description' => $row['location_description'] . $row['location_hidden_description'],
            'can_be_destroyed' => $row['location_can_be_destroyed'],
            'artefacts' => $artefacts // array of ['name' => ..., 'full_description' => ...]
        ];
    }
    return $grouped;
}

/**
 * Update the location
 * 
 * @param PDO $pdo : database connection
 * @param array $location : location data
 * @param array $activate_json : activate json data
 */
function updateLocation($pdo, $location, $activate_json) {
    // Extract the update_location data
    $update_location_data = $activate_json['update_location'];

    // Start the new activate_json
    $new_activate_location = $activate_json;
    unset($new_activate_location['update_location']);

    // If the old data must be saved to json, add it to the new activate_json
    if ($update_location_data['save_to_json'] == "TRUE") {
        // Prepare the old location data
        $update_location = array(
            'name' => $location['name'],
            'description' => $location['description'],
            'discovery_diff' => $location['discovery_diff'],
            'can_be_destroyed' => $location['can_be_destroyed'],
            'can_be_repaired' => $location['can_be_repaired'],
            'controller_id' => $location['controller_id'],
            'is_base' => $location['is_base'],
            'save_to_json' => $update_location_data['save_to_json']
        );
        // Add the old location data to the new activate_json
        $new_activate_location['update_location'] = $update_location;   
    }
    // If a activate_json is present, add it to the new activate_json
    elseif (!empty($update_location_data['future_location'])) {
        $new_activate_location['update_location'] = $update_location_data['future_location'];
    }
    // Encode the new activate_json
    $encoded_activate_json = json_encode($new_activate_location);
    
    // Update the location
    // Build a single UPDATE query for all relevant fields present in update_location_data
    $fields_to_update = [
        'name' => PDO::PARAM_STR,
        'description' => PDO::PARAM_STR,
        'discovery_diff' => PDO::PARAM_INT,
        'can_be_destroyed' => PDO::PARAM_INT,
        'can_be_repaired' => PDO::PARAM_INT,
        'controller_id' => PDO::PARAM_INT,
        'is_base' => PDO::PARAM_INT
    ];
    // Build the set clauses
    $set_clauses = [];
    $params = [':id' => $location['id']];
    // Add the fields to the set clauses
    foreach ($fields_to_update as $field => $param_type) {
        if (isset($update_location_data[$field]) && $update_location_data[$field] !== '') {
            $set_clauses[] = "$field = :$field";
            $params[":$field"] = $update_location_data[$field];
        }
    }
    // Add the activate_json to the set clauses
    $set_clauses[] = "activate_json = :activate_json";
    // Update the location
    try{
        if (!empty($set_clauses)) {
            $sql = "UPDATE locations SET " . implode(', ', $set_clauses) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            // Bind parameters with correct types
            foreach ($fields_to_update as $field => $param_type) {
                if (array_key_exists(":$field", $params)) {
                    $stmt->bindValue(":$field", $params[":$field"], $param_type);
                }
            }
            $stmt->bindValue(':activate_json', $encoded_activate_json, PDO::PARAM_STR);
            $stmt->bindParam(':id', $params[':id'], PDO::PARAM_INT);
            $stmt->execute();
            return true;
        }
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE locations Failed: " . $e->getMessage()."<br />";
    }
    return false;
}