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
            lc.name
            (s.searcher_enquete_val - l.discovery_diff) AS enquete_difference
        FROM searchers s
        JOIN zones z ON z.id = s.zone_id
        JOIN locations l ON
            s.zone_id = l.zone_id
        LEFT JOIN controlers lc ON l.controler_id = lc.id
    ";
    if ( !EMPTY($searcher_id) ) $sql .= " AND s.searcher_id = :searcher_id";
    try{
        // Prepare and execute the statement
        $stmt = $pdo->prepare($sql);
        if ( !EMPTY($searcher_id) ) $stmt->bindParam(':searcher_id', $searcher_id);
        $stmt->bindParam(':turn_number', $turn_number);
        $stmt->execute();

    } catch (PDOException $e) {
        echo __FUNCTION__."(): Error: " . $e->getMessage();
    }
    // Fetch and return the results
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function locationSearchMecanic($pdo ) {
    echo '<div> <h3> locationSearchMecanic : </h3> ';

    if (empty($turn_number)) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
    }
    echo "turn_number : $turn_number <br>";

    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG_REPORT')) == 'true') $debug = TRUE;
    $debug = TRUE;

    $locationsInvestigation = getLocationSearcherComparisons($pdo, $turn_number);
    if ($debug) echo " <p> locationsInvestigation : ". var_export($locationsInvestigation, true). "</p>";
    $reportArray = [];

    $LOCATIONDISCOVERYDIFF = getConfig($pdo, 'LOCATIONNAMEDIFF');
    $LOCATIONDISCOVERYDIFF = getConfig($pdo, 'LOCATIONINFORMATIONDIFF');

    foreach ($locationsInvestigation as $row) {

        // Build report :
        if ($debug) echo "<div>
            <p> row : ". var_export($row, true). "</p>";

        // If no report has been created yet for this worker
        if ( empty($reportArray[$row['searcher_id']]) )
            $reportArray[$row['searcher_id']] = sprintf( "<p> Dans lea %s %s.</p>", getConfig($pdo, 'textForZoneType'), $row['zone_name'] );
        if ($debug) echo "<p> START : reportArray[row['searcher_id']] : ". var_export($reportArray[$row['searcher_id']], true). "</p>";

    }

    echo '</div>';

    return TRUE;
}