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
    $sql = sprintf(
        "WITH searchers AS (
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
            CONCAT(zcc.firstname, ' ', zcc.lastname) AS zone_claimer_controller_name,
            zhc.id AS zone_holder_controller_id,
            CONCAT(zhc.firstname, ' ', zhc.lastname) AS zone_holder_controller_name,
            (s.searcher_enquete_val - z.calculated_defence_val) AS zone_discovery_diff,
            l.id AS found_id,
            l.discovery_diff AS found_discovery_diff,
            l.name AS found_name,
            l.description AS found_description,
            l.hidden_description AS found_hidden_description,
            l.can_be_destroyed AS found_can_be_destroyed,
            l.controller_id AS location_controller,
            CONCAT(lc.firstname, ' ', lc.lastname) AS location_controller_name,
            (s.searcher_enquete_val - l.discovery_diff) AS enquete_difference
        FROM searchers s
        JOIN zones z ON z.id = s.zone_id
        LEFT JOIN controllers zcc ON z.claimer_controller_id = zcc.id
        LEFT JOIN controllers zhc ON z.holder_controller_id = zhc.id
        JOIN locations l ON s.zone_id = l.zone_id
        LEFT JOIN controllers lc ON l.controller_id = lc.id
        %s
        ORDER BY l.discovery_diff DESC, l.id DESC;",
        (!empty($searcher_id)) ? " WHERE s.searcher_id = :searcher_id" : ''
    );
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        if (!empty($searcher_id)) $stmt->bindParam(':searcher_id', $searcher_id, PDO::PARAM_INT);
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
 * @param array $mechanics : mechanics array
 * 
 * @return bool success
 */
function locationSearchMechanic($pdo, $mechanics) {
    echo '<div><h3>locationSearchMechanic :</h3>';
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

    $textForZoneType = getConfig($pdo, 'textForZoneType');
    $controllerNameDenominatorOf = getConfig($pdo, 'controllerNameDenominatorOf');

    foreach ($locationsInvestigation as $row) {
        if ($debug) echo "<div><p>row: " . var_export($row, true) . "</p>";

        if (empty($reportArray[$row['searcher_id']])) {

            // Do the necessary checks for the zone Investigate Mechanic
            // In the reports it is necessary to investigate who is the controller that is the holder of a zone,
            // not just the banner under which it is !!
            $holderTexte = '';
            if (!empty($row['zone_holder_controller_id'])) {
                // 	- Basée sur la défense d'enquête de la zone VS l'enquête du serviteur
                // 		- Inférieur	ne sais pas
                // 		- 0-2		découvre le réseau
                // 		- 3+		découvre le réseau, le contrôleur 
                if ( (int)$row['zone_discovery_diff'] > 0 ) {
                    $holderTexte = sprintf(
                        " Ce %s est défendu par le réseau <strong> %s </strong>",
                        $textForZoneType,
                        $row['zone_holder_controller_id']
                    );
                    if ( (int)$row['zone_discovery_diff'] > 2) {
                        $holderTexte .= sprintf(
                            ", les hommes de <strong>%s</strong>",
                            $row['zone_holder_controller_name']
                        );
                    }
                    $holderTexte .= ".";
                }
            }

            // At begining of the report Show zone name and information
            $reportArray[$row['searcher_id']] = sprintf(
                "<p>Dans le %s <strong>%s</strong>. </br> %s %s </p>",
                $textForZoneType,
                $row['zone_name'],
                // If a claimer exists, use it,
                !empty($row['zone_claimer_controller_name']) ? sprintf("Ce %s est sous la bannière %s <strong> %s </strong>. ", $textForZoneType, $controllerNameDenominatorOf, $row['zone_claimer_controller_name']) : "",
                // If a holder exists, use it,
                $holderTexte
            );
        }

        if ($row['searcher_controller_id'] == $row['location_controller']) continue;

        if ((INT)$row['enquete_difference'] >= (INT)$LOCATIONNAMEDIFF) {
            $found_secret = false;
            $reportElement = "<p>".sprintf($locationNameText[array_rand($locationNameText)], $row['found_name']);

            if ((INT)$row['enquete_difference'] >= (INT)$LOCATIONINFORMATIONDIFF) {
                $reportElement = "<p>".sprintf($locationDescText[array_rand($locationDescText)], $row['found_name'], $row['found_description']);
                
                if ((INT)$row['enquete_difference'] >= (INT)$LOCATIONARTEFACTSDIFF) {
                    if (!empty($row['found_hidden_description'])) {
                        $reportElement .= "<br />" . $row['found_hidden_description'];
                        $found_secret = true;
                    }
                }
                addLocationToCKL($pdo, $row['searcher_controller_id'], $row['found_id'], $turn_number, $found_secret);

                if ((INT)$row['found_can_be_destroyed'] == 1) {
                    $reportElement .= $locationDestroyableText[array_rand($locationDestroyableText)];
                }

                if ((INT)$row['enquete_difference'] >= (INT)$LOCATIONARTEFACTSDIFF) {
                    // Fetch artefacts for this location
                    $stmtArt = $pdo->prepare("
                    SELECT name, description
                    FROM artefacts 
                    WHERE location_id = :location_id
                    ");
                    $stmtArt->execute([':location_id' => $row['found_id']]);
                    $artefacts = $stmtArt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($artefacts)) {
                        $reportElement .= "<br />Ce lieu contient : <ul>";
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
