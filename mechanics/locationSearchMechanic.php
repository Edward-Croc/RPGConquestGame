<?php


/**
 * gets the comparaison table between the workers on search/investigate and there possible targets locations
 * 
 * @param PDO $pdo : database connection
 * @param string|null $turn_number
 * @param string|null $searcher_id
 * 
 * @return array 
 * 
 */
function getLocationSearcherComparisons($pdo, $turn_number = NULL, $searcher_id = NULL) {
    // Define the SQL query
    $sql = "
        WITH searchers AS (
            SELECT
                wa.worker_id AS searcher_id,
                wa.controller_id AS searcher_controller_id,
                wa.enquete_val AS searcher_enquete_val,
                wa.zone_id
            FROM
                worker_actions wa
            WHERE
                wa.action_choice IN ('passive', 'investigate')
                AND turn_number = :turn_number
        )
        SELECT
            s.searcher_id,
            s.searcher_enquete_val,
            s.searcher_controller_id,
            z.id AS zone_id,
            z.name AS zone_name,
            l.id AS found_id,
            l.discovery_diff AS found_discovery_diff,
            l.name AS found_name,
            l.description AS found_description,
            l.can_be_destroyed AS found_can_be_destroyed,
            l.controller_id AS location_controller,
            CONCAT(lc.firstname, ' ', lc.lastname) AS location_controller_name,
            (s.searcher_enquete_val - l.discovery_diff) AS enquete_difference
        FROM searchers s
        JOIN zones z ON z.id = s.zone_id
        JOIN locations l ON s.zone_id = l.zone_id
        LEFT JOIN controllers lc ON l.controller_id = lc.id
    ";

    if (!empty($searcher_id)) $sql .= " WHERE s.searcher_id = :searcher_id";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':turn_number', $turn_number);
        if (!empty($searcher_id)) $stmt->bindParam(':searcher_id', $searcher_id);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__ . "(): Error: " . $e->getMessage();
        return [];
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * do the necessary checks for the location Search Mechanic
 * 
 * @param PDO $pdo : database connection
 * 
 * @return bool success
 */
function locationSearchMechanic($pdo) {
    echo '<div><h3>locationSearchMechanic :</h3>';

    $mechanics = getMechanics($pdo);
    $turn_number = $mechanics['turncounter'];
    echo "turn_number : $turn_number <br>";

    $debug = strtolower(getConfig($pdo, 'DEBUG_REPORT')) === 'true';

    $locationsInvestigation = getLocationSearcherComparisons($pdo, $turn_number);
    if ($debug) echo "<p>locationsInvestigation : " . var_export($locationsInvestigation, true) . "</p>";

    $reportArray = [];
    $LOCATIONNAMEDIFF = getConfig($pdo, 'LOCATIONNAMEDIFF');
    $LOCATIONINFORMATIONDIFF = getConfig($pdo, 'LOCATIONINFORMATIONDIFF');
    $LOCATIONARTEFACTSDIFF = getConfig($pdo, 'LOCATIONARTEFACTSDIFF');

    // Fetch dynamic text templates
    $locationNameText =  json_decode(getConfig($pdo,'TEXT_LOCATION_DISCOVERED_NAME'), true);
    $locationDescText =  json_decode(getConfig($pdo,'TEXT_LOCATION_DISCOVERED_DESCRIPTION'), true);
    $locationDestroyableText =  json_decode(getConfig($pdo,'TEXT_LOCATION_CAN_BE_DESTROYED'), true);

    foreach ($locationsInvestigation as $row) {
        if ($debug) echo "<div><p>row: " . var_export($row, true) . "</p>";

        if (empty($reportArray[$row['searcher_id']])) {
            $reportArray[$row['searcher_id']] = sprintf(
                "<p>Dans le %s %s :</p>",
                getConfig($pdo, 'textForZoneType'),
                $row['zone_name']
            );
        }

        if ($row['searcher_controller_id'] == $row['location_controller']) continue;

        if ($row['enquete_difference'] >= $LOCATIONNAMEDIFF) {
            $reportElement = "<p>".sprintf($locationNameText[array_rand($locationNameText)], $row['found_name']);

            if ($row['enquete_difference'] >= $LOCATIONINFORMATIONDIFF) {
                $checkStmt = $pdo->prepare("SELECT id FROM controller_known_locations WHERE controller_id = :cid AND location_id = :lid");
                $checkStmt->execute([
                    ':cid' => $row['searcher_controller_id'],
                    ':lid' => $row['found_id']
                ]);

                if ($known = $checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $updateStmt = $pdo->prepare("UPDATE controller_known_locations SET last_discovery_turn = :turn WHERE id = :id");
                    $updateStmt->execute([
                        ':turn' => $turn_number,
                        ':id' => $known['id']
                    ]);
                } else {
                    $insertStmt = $pdo->prepare("INSERT INTO controller_known_locations (controller_id, location_id, first_discovery_turn, last_discovery_turn) VALUES (:cid, :lid, :turn, :turn)");
                    $insertStmt->execute([
                        ':cid' => $row['searcher_controller_id'],
                        ':lid' => $row['found_id'],
                        ':turn' => $turn_number
                    ]);
                }

                $reportElement = "<p>".sprintf($locationDescText[array_rand($locationDescText)], $row['found_name'], $row['found_description']);
                if ($row['found_can_be_destroyed']) {
                    $reportElement .= $locationDestroyableText[array_rand($locationDestroyableText)];
                }

                if ($row['enquete_difference'] >= $LOCATIONARTEFACTSDIFF) {
                    // Fetch artefacts for this location
                    $stmtArt = $pdo->prepare("
                    SELECT name, description
                    FROM artefacts 
                    WHERE location_id = :location_id
                    ");
                    $stmtArt->execute([':location_id' => $row['found_id']]);
                    $artefacts = $stmtArt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($artefacts)) {
                        $reportElement .= "Ce lieu contient : <ul>";
                        foreach ($artefacts as $art) {
                            $reportElement .= sprintf(
                                "<li><strong>%s</strong>: %s</li>",
                                htmlspecialchars($art['name']),
                                htmlspecialchars($art['description'])
                            );
                        }
                        $reportElement .= "</ul>";
                    }
                }
            }
            $reportElement .= '</p>';
            $reportArray[$row['searcher_id']] .= $reportElement;
        }

        if ($debug) echo "<p>Updated reportArray: " . var_export($reportArray[$row['searcher_id']], true) . "</p></div>";
    }

    foreach ($reportArray AS $worker_id => $report) {
        updateWorkerAction($pdo, $worker_id, $turn_number, NULL, ['secrets_report' => $report]);
    }

    echo '<p> locationSearchMechanic : DONE </p> </div>';
    return $report;
}
