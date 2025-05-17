<?php

function getLocationSearcherComparisons($pdo, $turn_number = NULL, $searcher_id = NULL) {
    // Define the SQL query
    $sql = "
                WITH searchers AS (
            SELECT
                wa.worker_id AS searcher_id,
                wa.controler_id AS searcher_controler_id,
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
            s.searcher_controler_id,
            z.id AS zone_id,
            z.name AS zone_name,
            l.id AS found_id,
            l.discovery_diff AS found_discovery_diff,
            l.name AS found_name,
            l.description AS found_description,
            l.can_be_destroyed AS found_can_be_destroyed,
            l.controler_id AS location_controler,
            CONCAT(lc.firstname, ' ', lc.lastname) AS location_controler_name,
            (s.searcher_enquete_val - l.discovery_diff) AS enquete_difference
        FROM searchers s
        JOIN zones z ON z.id = s.zone_id
        JOIN locations l ON s.zone_id = l.zone_id
        LEFT JOIN controlers lc ON l.controler_id = lc.id
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

function locationSearchMecanic($pdo) {
    echo '<div><h3>locationSearchMecanic :</h3>';

    $mecanics = getMecanics($pdo);
    $turn_number = $mecanics['turncounter'];
    echo "turn_number : $turn_number <br>";

    $debug = strtolower(getConfig($pdo, 'DEBUG_REPORT')) === 'true';
    $debug = TRUE;

    $locationsInvestigation = getLocationSearcherComparisons($pdo, $turn_number);
    if ($debug) echo "<p>locationsInvestigation : " . var_export($locationsInvestigation, true) . "</p>";

    $reportArray = [];
    $LOCATIONNAMEDIFF = getConfig($pdo, 'LOCATIONNAMEDIFF');
    $LOCATIONINFORMATIONDIFF = getConfig($pdo, 'LOCATIONINFORMATIONDIFF');

    // Fetch dynamic text templates
    $locationNameText =  json_decode(getConfig($pdo,'TEXT_LOCATION_DISCOVERED_NAME'), true);
    $locationDescText =  json_decode(getConfig($pdo,'TEXT_LOCATION_DISCOVERED_DESCRIPTION'), true);
    $locationDestroyableText =  json_decode(getConfig($pdo,'TEXT_LOCATION_CAN_BE_DESTROYED'), true);

    foreach ($locationsInvestigation as $row) {
        if ($debug) echo "<div><p>row: " . var_export($row, true) . "</p>";

        if (empty($reportArray[$row['searcher_id']])) {
            $reportArray[$row['searcher_id']] = sprintf(
                "<p>Dans lea %s %s.</p>",
                getConfig($pdo, 'textForZoneType'),
                $row['zone_name']
            );
        }

        if ($row['searcher_controler_id'] == $row['location_controler']) continue;

        if ($row['enquete_difference'] >= $LOCATIONNAMEDIFF) {
            $reportElement = sprintf($locationNameText[array_rand($locationNameText)], $row['found_name']);

            if ($row['enquete_difference'] >= $LOCATIONINFORMATIONDIFF) {
                $checkStmt = $pdo->prepare("SELECT id FROM controler_known_locations WHERE controler_id = :cid AND location_id = :lid");
                $checkStmt->execute([
                    ':cid' => $row['searcher_controler_id'],
                    ':lid' => $row['found_id']
                ]);

                if ($known = $checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $updateStmt = $pdo->prepare("UPDATE controler_known_locations SET last_discovery_turn = :turn WHERE id = :id");
                    $updateStmt->execute([
                        ':turn' => $turn_number,
                        ':id' => $known['id']
                    ]);
                } else {
                    $insertStmt = $pdo->prepare("INSERT INTO controler_known_locations (controler_id, location_id, first_discovery_turn, last_discovery_turn) VALUES (:cid, :lid, :turn, :turn)");
                    $insertStmt->execute([
                        ':cid' => $row['searcher_controler_id'],
                        ':lid' => $row['found_id'],
                        ':turn' => $turn_number
                    ]);
                }

                $reportElement .= sprintf($locationDescText[array_rand($locationDescText)], $row['found_description']);

                if ($row['found_can_be_destroyed']) {
                    $reportElement .= $locationDestroyableText[array_rand($locationDestroyableText)];
                }
            }

            $reportArray[$row['searcher_id']] .= $reportElement;
        }

        if ($debug) echo "<p>Updated reportArray: " . var_export($reportArray[$row['searcher_id']], true) . "</p></div>";
    }

    foreach ($reportArray AS $worker_id => $report) {
        updateWorkerAction($pdo, $worker_id, $turn_number, NULL, ['secrets_report' => $report]);
    }

    echo '</div>';
    return $report;
}
