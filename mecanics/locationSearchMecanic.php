<?php

function getLocationSearcherComparisons($pdo, $turn_number = NULL, $searcher_id = NULL) {
    if (empty($turn_number)) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
        echo "turn_number : $turn_number <br>";
    }

    return array();
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

    $investigations = getLocationSearcherComparisons($pdo, $turn_number);
    $reportArray = [];

    foreach ($investigations as $row) {

        // Build report :
        if ($debug) echo "<div>
            <p> row : ". var_export($row, true). "</p>";

        // If no report has been created yet for this worker
        if ( empty($reportArray[$row['searcher_id']]) )
            $reportArray[$row['searcher_id']] = sprintf( "<p> Nous avons mener l'enquête dans le quartier %s.</p>", $row['zone_name'] );
        if ($debug) echo "<p> START : reportArray[row['searcher_id']] : ". var_export($reportArray[$row['searcher_id']], true). "</p>";

    }

    echo '</div>';

    return TRUE;
}