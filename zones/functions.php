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
        $sql = "SELECT z.id AS zone_id, c.id AS controler_id, * FROM zones AS z
            LEFT JOIN controlers AS c ON c.id = z.claimer_controler_id
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
    // Display select list of Controlers
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
    $location = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store Controlers in the array
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
    // Get all bases with their controler and zone
    $sql = "SELECT id, controler_id, zone_id FROM locations WHERE is_base = TRUE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $bases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bases as $base) {
        $controler_id = $base['controler_id'];
        $zone_id = $base['zone_id'];

        $new_diff = calculateSecretLocationDiscoveryDiff($pdo, $controler_id, $zone_id);

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

        echo sprintf("Updated base (C: %s, Z: %s) to difficulty: %s<br/>", $controler_id, $zone_id, $new_diff);
    }
    return TRUE;
}

/**
 * calculateSecretLocationDiscoveryDiff
 * 
 * @param PDO $pdo
 * @param int $controler_id
 * @param int $zone_id
 */
function calculateSecretLocationDiscoveryDiff($pdo, $controler_id, $zone_id) {
    // Get baseDiscoveryDiff from config
    $baseDiscoveryDiff = (int)getConfig($pdo, 'baseDiscoveryDiff');
    $discoveryDiff = (!empty($baseDiscoveryDiff)) ? $baseDiscoveryDiff : 0;
    echo sprintf("discoveryDiff (base) : %s </br>", $discoveryDiff);

    // Add bonus from powers
    $baseDiscoveryDiffAddPowers = (int)getConfig($pdo, 'baseDiscoveryDiffAddPowers'); // bonus value from each power
    $maxBonusPowers = (int)getConfig($pdo, 'maxBonusDiscoveryDiffPowers'); // max bonus from powers
    if ($baseDiscoveryDiffAddPowers !== 0) {
        $power_list = getPowersByType($pdo, '3', $controler_id, false);
        echo sprintf("power_list : %s </br>", var_export($power_list, true));
        $powerBonus = 0;
        foreach ($power_list as $power) {
            $powerBonus += $power['enquete'] * $baseDiscoveryDiffAddPowers;
        }
        if ($maxBonusPowers > 0) $powerBonus = min($powerBonus, $maxBonusPowers);
        $discoveryDiff += $powerBonus;
        echo sprintf("discoveryDiff (+powers) : %s </br>", $discoveryDiff);
    }

    // Add bonus from workers
    $baseDiscoveryDiffAddWorkers = (int)getConfig($pdo, 'baseDiscoveryDiffAddWorkers');
    $maxBonusWorkers = (int)getConfig($pdo, 'maxBonusDiscoveryDiffWorkers'); // max bonus from workers
    if ($baseDiscoveryDiffAddWorkers !== 0) {
        $sql = "
            SELECT COUNT(*) AS worker_count 
            FROM workers w
            JOIN controler_worker cw ON cw.worker_id = w.id
            WHERE cw.controler_id = :controler_id 
              AND w.zone_id = :zone_id 
              AND w.is_active = TRUE
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':controler_id' => $controler_id,
            ':zone_id' => $zone_id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $worker_count = $result ? (int)$result['worker_count'] : 0;
        echo sprintf("valid worker count : %s </br>", $worker_count);
        $workerBonus = $worker_count * $baseDiscoveryDiffAddWorkers;
        if ($maxBonusWorkers > 0) $workerBonus = min($workerBonus, $maxBonusWorkers);
        $discoveryDiff += $workerBonus;
        echo sprintf("discoveryDiff (+workers) : %s </br>", $discoveryDiff);
    }

    // Add bonus from base age
    $baseDiscoveryDiffAddTurns = (int)getConfig($pdo, 'baseDiscoveryDiffAddTurns');
    $maxBonusTurns = (int)getConfig($pdo, 'maxBonusDiscoveryDiffTurns'); // max bonus from turns
    if ($baseDiscoveryDiffAddTurns !== 0) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
        echo "turn_number : $turn_number <br>";
        $sql = "
            SELECT setup_turn 
            FROM locations 
            WHERE controler_id = :controler_id 
              AND zone_id = :zone_id 
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':controler_id' => $controler_id,
            ':zone_id' => $zone_id
        ]);
        $base = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($base) {
            $setup_turn = (int)$base['setup_turn'];
            $turn_diff = max(0, $turn_number - $setup_turn);
            echo sprintf("Base age in turns: %s (current_turn %s - setup_turn %s)</br>", $turn_diff, $turn_number, $setup_turn);
            $turnBonus = $turn_diff * $baseDiscoveryDiffAddTurns;
            if ($maxBonusTurns > 0) $turnBonus = min($turnBonus, $maxBonusTurns);
            $discoveryDiff += $turnBonus;
        }
        echo sprintf("discoveryDiff (+turns) : %s </br>", $discoveryDiff);
    }

    return $discoveryDiff;
}


/**
 * calculateSecretLocationDefence
*/
function calculateSecretLocationDefence($pdo){

    // Get BasesDef from config
    $baseDefenceDiff = (INT)getConfig($pdo, 'baseDefenceDiff');
    $defenceDiff = (!empty($baseDefenceDiff)) ? $baseDefenceDiff : 0;
    echo sprintf("defenceDiff : %s </br>", $defenceDiff);

    // Add powers from Controler
    $baseDefenceDiffAddPowers = (INT)getConfig($pdo, 'baseDefenceDiffAddPowers');
    if ($baseDefenceDiffAddPowers != 0) {
        $power_list = getPowersByType($pdo,'3', $controler_id, FALSE);
        echo sprintf("power_list : %s </br>", var_export($power_list, true));
        foreach ($power_list as $power ) {
            $defenceDiff += $power['defence'] * $baseDefenceDiffAddPowers;
        }
        echo sprintf("defenceDiff : %s </br>", $defenceDiff);
    }

    // Add worker from Controler in the Zone
    $baseDefenceDiffAddWorkers = (INT)getConfig($pdo, 'baseDefenceDiffAddWorkers');
    if ($baseDefenceDiffAddWorkers != 0) {
        $sql = "
            SELECT COUNT(*) AS worker_count 
            FROM workers w
            JOIN controler_worker cw on cw.worker_id = w.id
            WHERE cw.controler_id = :controler_id 
                AND w.zone_id = :zone_id 
                AND w.is_active = TRUE
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':controler_id' => $controler_id,
            ':zone_id' => $zone_id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $worker_count = $result ? (INT)$result['worker_count'] : 0;
        echo sprintf("valid worker count : %s </br>", $worker_count);
        $defenceDiff += $worker_count * $baseDefenceDiffAddWorkers;

        echo sprintf(" defenceDiff : %s </br>", $defenceDiff);
    }

    // Add age of base as difficulty (turns since setup)
    $baseDefenceDiffAddTurns = (int)getConfig($pdo, 'baseDefenceDiffAddTurns');
    if ( $baseDefenceDiffAddTurns != 0 ) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
        echo "turn_number : $turn_number <br>";

        $sql = "
            SELECT setup_turn 
            FROM locations 
            WHERE controler_id = :controler_id 
                AND zone_id = :zone_id 
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':controler_id' => $controler_id,
            ':zone_id' => $zone_id
        ]);
        $base = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($base) {
            $setup_turn = (int)$base['setup_turn'];
            $turn_diff = max(0, $current_turn - $setup_turn);
            echo sprintf("Base age in turns: %s (current_turn %s - setup_turn %s)</br>", $turn_diff, $current_turn, $setup_turn);
            $defenceDiff += $turn_diff * $baseDefenceDiffAddTurns;
        }

        echo sprintf("defenceDiff : %s </br>", $defenceDiff);
    }

    return $defenceDiff;
}

/**
 * Affiche les bases connues ou possédées dans une zone par un contrôleur
 * Permet d'attaquer les bases destructibles
 * 
 * @param PDO $pdo
 * @param int $controler_id
 * @param int $zone_id
 */
function showControlerKnownSecrets($pdo, $controler_id, $zone_id) {
    $returnText = '';
    // Bases possédées par ce contrôleur dans la zone
    $sql = "
        SELECT l.id, l.name, l.can_be_destroyed, l.description
        FROM locations l
        WHERE l.controler_id = :controler_id
        AND l.zone_id = :zone_id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':controler_id' => $controler_id,
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
        FROM controler_known_locations ckl
        JOIN locations l ON ckl.location_id = l.id
        WHERE ckl.controler_id = :controler_id
        AND l.zone_id = :zone_id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':controler_id' => $controler_id,
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
                    <form action="/RPGConquestGame/controlers/action.php" method="GET">
                        <input type="hidden" name="controler_id" value="%d">
                        <input type="hidden" name="location_id" value="%d">
                        <input type="submit" name="attack" value="Attaquer personnellement cette base" class="controler-action-btn">
                    </form>',
                    $controler_id,
                    $base['id']
                );
            }
        }
        $returnText .=  '</p>';
    }
    return $returnText;
}
