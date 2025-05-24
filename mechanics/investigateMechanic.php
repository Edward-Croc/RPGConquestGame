<?php

/**
 *  Remove curly braces and quotes and Split the string by commas into an array
 * 
 * @param string $input
 * 
 * @return array 
 * 
 */
function cleanAndSplitString($input) {
    // Remove curly braces and quotes
    $cleaned = str_replace(['{', '}', '"'], '', $input);
    // Split the string by commas into an array
    return array_map('trim', explode(',', $cleaned));
}

/**
 * gets the comparaison table between the workers on search/investigate and there possible targets
 * 
 * @param PDO $pdo : database connection
 * @param string|null $turn_number
 * @param string|null $searcher_id
 * 
 * @return array 
 * 
 */
function getSearcherComparisons($pdo, $turn_number = NULL, $searcher_id = NULL) {
    if (empty($turn_number)) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];
        echo "turn_number : $turn_number <br>";
    }

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
            wa.worker_id AS found_id,
            wa.enquete_val AS found_enquete_val,
            wa.action_choice AS found_action,
            wa.action_params AS found_action_params,
            CONCAT(w.firstname, ' ', w.lastname) AS found_name,
            wo.id AS found_worker_origin_id,
            wo.name AS found_worker_origin_name,
            cw.controller_id AS found_controller_id,
            CONCAT(c.firstname, ' ', c.lastname) AS found_controller_name,
            (
                SELECT ARRAY_AGG(p.name)
                FROM worker_powers wp
                JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                JOIN powers p ON lpt.power_id = p.ID
                JOIN power_types pt ON lpt.power_type_id = pt.ID
                WHERE wp.worker_id = wa.worker_id AND pt.name = 'Metier'
            ) AS found_metier,
            (
                SELECT ARRAY_AGG(p.name)
                FROM worker_powers wp
                JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                JOIN powers p ON lpt.power_id = p.ID
                JOIN power_types pt ON lpt.power_type_id = pt.ID
                WHERE wp.worker_id = wa.worker_id AND pt.name = 'Hobby'
            ) AS found_hobby,
            (
                SELECT ARRAY_AGG(p.name)
                FROM worker_powers wp
                JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                JOIN powers p ON lpt.power_id = p.ID
                JOIN power_types pt ON lpt.power_type_id = pt.ID
                WHERE wp.worker_id = wa.worker_id AND pt.name = 'Discipline'
            ) AS found_discipline,
            (
                SELECT ARRAY_AGG(p.name)
                FROM worker_powers wp
                JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                JOIN powers p ON lpt.power_id = p.ID
                JOIN power_types pt ON lpt.power_type_id = pt.ID
                WHERE wp.worker_id = wa.worker_id AND pt.name = 'Transformation'
            ) AS found_transformation,
            (
                SELECT ARRAY_AGG((p.other->>'hidden')::INT)
                FROM worker_powers wp
                JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                JOIN powers p ON lpt.power_id = p.ID
                JOIN power_types pt ON lpt.power_type_id = pt.ID
                WHERE wp.worker_id = wa.worker_id AND pt.name = 'Transformation'
            ) AS hidden_transformation,
            (s.searcher_enquete_val - wa.enquete_val) AS enquete_difference
        FROM searchers s
        JOIN zones z ON z.id = s.zone_id
        JOIN worker_actions wa ON
            s.zone_id = wa.zone_id AND turn_number = :turn_number AND action_choice NOT IN ('dead', 'captured')
        JOIN workers w ON wa.worker_id = w.ID
        JOIN worker_origins wo ON wo.id = w.origin_id
        JOIN controller_worker cw ON wa.worker_id = cw.worker_id AND is_primary_controller = true
        JOIN controllers c ON cw.controller_id = c.ID
        WHERE
            s.searcher_id != wa.worker_id
            AND s.searcher_controller_id != wa.controller_id
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

/**
 * do the necessary checks for the claim Mechanic
 * 
 * @param PDO $pdo : database connection
 * 
 * @return bool success
 */
function investigateMechanic($pdo) {
    echo '<div> <h3> investigateMechanic : </h3> ';

    if (empty($turn_number)) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];
    }
    echo "turn_number : $turn_number <br>";

    $debug = strtolower(getConfig($pdo, 'DEBUG_REPORT')) === 'true';

    $REPORTDIFF0 = getConfig($pdo, 'REPORTDIFF0');
    $REPORTDIFF1 = getConfig($pdo, 'REPORTDIFF1');
    $REPORTDIFF2 = getConfig($pdo, 'REPORTDIFF2');
    $REPORTDIFF3 = getConfig($pdo, 'REPORTDIFF3');
    if ($debug) {
        echo "REPORTDIFF0 : $REPORTDIFF0 <br/>";
        echo "REPORTDIFF1 : $REPORTDIFF1 <br/>";
        echo "REPORTDIFF1 : $REPORTDIFF1 <br/>";
        echo "REPORTDIFF1 : $REPORTDIFF1 <br/>";
    }

    $investigations = getSearcherComparisons($pdo, $turn_number, NULL);
    $reportArray = [];

    $txtArray = [];
    $txtArray['passive']['ps'] = getConfig($pdo, 'txt_ps_passive');
    $txtArray['investigate']['ps'] = getConfig($pdo, 'txt_ps_investigate');
    $txtArray['attack']['ps'] = getConfig($pdo, 'txt_ps_attack');
    $txtArray['claim']['ps'] = getConfig($pdo, 'txt_ps_claim');
    $txtArray['captured']['ps'] = getConfig($pdo, 'txt_ps_captured');
    $txtArray['dead']['ps'] = getConfig($pdo, 'txt_ps_dead');
    $txtArray['passive']['inf'] = getConfig($pdo, 'txt_inf_passive');
    $txtArray['investigate']['inf'] = getConfig($pdo, 'txt_inf_investigate');
    $txtArray['attack']['inf'] = getConfig($pdo, 'txt_inf_attack');
    $txtArray['claim']['inf'] = getConfig($pdo, 'txt_inf_claim');
    $txtArray['captured']['inf'] = getConfig($pdo, 'txt_inf_captured');
    $txtArray['dead']['inf'] = getConfig($pdo, 'txt_inf_dead');

    foreach ($investigations as $row) {

        // Build report :
        if ($debug) echo "<div>
            <p> row : ". var_export($row, true). "</p>";

        // If no report has been created yet for this worker
        if ( empty($reportArray[$row['searcher_id']]) )
            $reportArray[$row['searcher_id']] = sprintf( getConfig($pdo,'textesStartInvestigate'), $row['zone_name'] );
        if ($debug) echo "<p> START : reportArray[row['searcher_id']] : ". var_export($reportArray[$row['searcher_id']], true). "</p>";

        $disciplines = cleanAndSplitString($row['found_discipline']);
        $discipline_2 = '';
        $textesFoundDisciplines = json_decode(getConfig($pdo,'textesFoundDisciplines'), true);
        foreach ($disciplines AS $key => $discipline) {
            if ($key == 0) continue;
            $discipline_2 .= sprintf($textesFoundDisciplines[array_rand($textesFoundDisciplines)], $discipline);
        }
        if ($debug) echo "Prepare discipline_2 string : $discipline_2 <br>";

        // transformation
        $hidden_transformation = cleanAndSplitString($row['hidden_transformation']);
        $found_transformation = cleanAndSplitString($row['found_transformation']);
        $transformationTextDiff[0] = '';
        $transformationTextDiff[1] = '';
        $transformationTextDiff[2] = '';
        $transformationTextDiff[3] = '';
        $textesTransformationDiff1 = json_decode(getConfig($pdo,'textesTransformationDiff1'), true);
        $textesTransformationDiff2 = json_decode(getConfig($pdo,'textesTransformationDiff2'), true);

        // for ($iteration = 0; $iteration < count($hidden_transformation); $iteration++) {
        foreach ($hidden_transformation AS $iteration => $diffval ) {
            if ( $iteration > 0 && !empty($transformationTextDiff[$diffval]))
                $transformationTextDiff[$diffval] .= ' et ';
            if ( $diffval == 0 || $diffval == 3)
                $transformationTextDiff[$diffval] .= $found_transformation[$iteration];
            if ( $diffval == 1 )
                $transformationTextDiff[$diffval] .= sprintf($textesTransformationDiff1[array_rand($textesTransformationDiff1)], $found_transformation[$iteration]);
            if ( $diffval == 2 )
                $transformationTextDiff[$diffval] .= sprintf($textesTransformationDiff2[array_rand($textesTransformationDiff2)], $found_transformation[$iteration]);
        }
        if ($debug) echo "Build transformationTextDiff array string :".var_export($transformationTextDiff, true)." <br>";

        $text_action_ps = $txtArray[$row['found_action']]['ps'];
        $text_action_inf = $txtArray[$row['found_action']]['inf'];
        // get action info from params
        echo "row['found_action'] : ".$row['found_action']."; row['found_action_params']:".$row['found_action_params']."; <br>";
        if ( $row['found_action'] == 'claim' && $row['found_action_params'] != '{}' ) {
            $found_action_params = json_decode($row['found_action_params'],true);
            if (is_array($found_action_params)) {
                // USE $found_action_params['claim_controller_id']
                $controllers = getControllers($pdo, NULL, $found_action_params['claim_controller_id']);
                $text_action_ps .= ' au nom de '.$controllers[0]['firstname']. " ".$controllers[0]['lastname'];
                $text_action_inf .= ' au nom de '.$controllers[0]['firstname']. " ".$controllers[0]['lastname'];
            }
        }
        if ( $row['found_action'] == 'attack' && $row['found_action_params'] != '{}' ) {
            $found_action_params = json_decode($row['found_action_params'],true);
            if (is_array($found_action_params)) {
                $networkIDs = [];
                $workerIDs = [];

                // Iterate through the decoded array
                foreach ($found_action_params as $param) {
                    if (isset($param['attackScope']) && isset($param['attackID'])) {
                        if ($param['attackScope'] === 'network') {
                            $networkIDs[] = $param['attackID']; // Collect network IDs
                        } elseif ($param['attackScope'] === 'worker') {
                            $workerIDs[] = $param['attackID']; // Collect worker IDs
                        }
                    }
                }
            }
            if ( count($networkIDs) != 0 ){
                $text_action_ps .= '';
                $text_action_inf .= '';
                if ( count($networkIDs) ==1 ){
                    $text_action_ps .= ' le réseau '.$networkIDs[0];
                    $text_action_inf .= ' le réseau '.$networkIDs[0];
                }else{
                    $text_action_ps .= ' les réseaux '. implode(', ',$networkIDs);
                    $text_action_inf .= ' les réseaux '. implode(', ',$networkIDs);
                }
            }else if (count($workerIDs) != 0) {
                if (count($workerIDs) == 1){
                    $text_action_ps .= ' une personne ';
                    $text_action_inf .= ' une personne ';
                }else{
                    $text_action_ps .= ' plusieurs personnes ';
                    $text_action_inf .= ' plusieurs personnes ';
                }
            }
        }
        if ($debug) echo "Build text_action_ps et text_action_inf <br>";

       $originTexte = '';
        $local_origin_list = getConfig($pdo, 'local_origin_list');
        if (!in_array($row['found_worker_origin_id'], explode(',',$local_origin_list))) {
            $textesOrigine = json_decode(getConfig($pdo,'textesOrigine'), true);
            $originTexte = sprintf($textesOrigine[array_rand($textesOrigine)], $row['found_worker_origin_name']);
        }
        if ($debug) echo "Build originTexte : $originTexte <br>";

        // Extract hobby and metier
        $found_hobby = cleanAndSplitString($row['found_hobby']);
        if ($debug) echo "Build found_hobby array :".var_export($found_hobby, true)." <br>";

        $found_metier = cleanAndSplitString($row['found_metier']);
        if ($debug) echo "Build found_metier array string :".var_export($found_metier, true)." <br>";

        // Start compiling the report
        $report = "<p>";

        /*
        textesDiff01Array
        (nom(id)) - %1$s
        (metier/role) - %2$s
        (hobby/objet) - %3$s
        (action_ps) - %4$s
        (action_inf) - %5$s
        (discipline) - %6$s
        (transformation0) - %7$s
        (transformation1) - %8$s
        (origin_text) - %9$s
        */
        $textesDiff01Array = json_decode(getConfig($pdo,'textesDiff01Array'), true);
        // ! (transformation0)
        if (!empty( $transformationTextDiff[0]))
            $textesDiff01Array = json_decode(getConfig($pdo,'textesDiff01TransformationDiff0Array'), true);
        $texteDiff01 = $textesDiff01Array[array_rand($textesDiff01Array)];

        // Diff 0
        if ($debug) echo "With row['enquete_difference'] AS :".var_export($row['enquete_difference'] , true)." <br>";
        if ( (int)$row['enquete_difference'] >= (int)$REPORTDIFF0 ) {
            if ($debug) echo " REPORTDIFF 0 Start <br>";

            $report = sprintf($texteDiff01[0],
                sprintf('%s (%s)',$row['found_name'], $row['found_id']), // (nom(id)) - %1$s
                $found_metier[0], // (metier) - %2$s
                $found_hobby[0], // (hobby) - %3$s
                $text_action_ps, // (action_ps) - %4$s
                $text_action_inf, // (action_inf) - %5$s
                $disciplines[0], // (discipline) - %6$s
                $transformationTextDiff[0], // (transformation0) - %7$s
                $transformationTextDiff[1], //(transformation1) - %8$s
                $originTexte, // (origin_text) - %9$s
            );
        }

        // Diff 1
        if ( (int)$row['enquete_difference'] >= (int)$REPORTDIFF1 ) {
            if ($debug) echo " REPORTDIFF 1 Start <br>";
            $report .= sprintf($texteDiff01[1],
                sprintf('%s (%s)',$row['found_name'], $row['found_id']), // (nom(id)) - %1$s
                $found_metier[0], // (metier) - %2$s
                $found_hobby[0], // (hobby) - %3$s
                $text_action_ps, // (action_ps) - %4$s
                $text_action_inf, // (action_inf) - %5$s
                $disciplines[0], // (discipline) - %6$s
                $transformationTextDiff[0], // (transformation0) - %7$s
                $transformationTextDiff[1], //(transformation1) - %8$s
                $originTexte, // (origin_text) - %9$s
            );
        }

        // Diff 2
        // %1$s - réseau
        // %2$s - (transformation2)
        // %3$s - (discipline_2)
        if ( (int)$row['enquete_difference'] >= (int)$REPORTDIFF2 ) {
            if ($debug) echo " REPORTDIFF 3 Start <br>";
            $textesDiff2 = json_decode(getConfig($pdo,'textesDiff2'), true);
            $report .= sprintf($textesDiff2[array_rand($textesDiff2)], $row['found_controller_id'], $transformationTextDiff[2], $discipline_2 );
        }

        // Diff 3
        // %1$s - found_controller_name
        if ( (int)$row['enquete_difference'] >= (int)$REPORTDIFF3 ) {
            if ($debug) echo " REPORTDIFF 3 Start <br>";
            $textesDiff3 = json_decode(getConfig($pdo,'textesDiff3'), true);
            $report .= sprintf($textesDiff3[array_rand($textesDiff3)], $row['found_controller_name']);
        }

        // Debug report
        if ($debug) {
            $report .= "Searcher ID: {$row['searcher_id']}, Searcher Enquete Val: {$row['searcher_enquete_val']}, ";
            $report .= "Found ID: {$row['found_id']}, Found Enquete Val: {$row['found_enquete_val']}, ";
            $report .= "Difference: {$row['enquete_difference']}, ";
        }
        $report .= '</p>';
        echo sprintf("Rapport: %s<br />",var_export( $report, true));
        $reportArray[$row['searcher_id']] .= $report;

        if ( (int)$row['enquete_difference'] >= (int)$REPORTDIFF0 ) {
            if ($debug) echo "<p> Start controllers_known_enemies - <br /> ";
            // Add to controllers_known_enemies
            try {

                $cke_existing_record_id = addWorkerToCKE($pdo, $row['searcher_controller_id'], $row['found_id'], $turn_number, $row['zone_id'] ) ;

                if ( (int)$row['enquete_difference'] >= (int)$REPORTDIFF2 && $cke_existing_record_id !== NULL ) {
                    $discovered_controller_name_sql = '';
                    if ( (int)$row['enquete_difference'] >= (int)$REPORTDIFF3 ) {
                        $discovered_controller_name_sql = ', discovered_controller_name = :discovered_controller_name ';
                    }
                    // Update if record exists
                    $sqlUpdate = sprintf("UPDATE controllers_known_enemies
                            SET discovered_controller_id = :discovered_controller_id
                            %s
                            WHERE id = :id",
                            $discovered_controller_name_sql
                    );
                    if ($debug) echo "sql :".var_export($sqlUpdate, true)." <br>";
                    $stmtUpdate = $pdo->prepare($sqlUpdate);
                    $stmtUpdate->bindParam(':discovered_controller_id', $row['found_controller_id']);
                    $stmtUpdate->bindParam(':id',$cke_existing_record_id);
                    if ( (int)$row['enquete_difference'] >= (int)$REPORTDIFF3 ) {
                        $stmtUpdate->bindParam(':discovered_controller_name', $row['found_controller_name']);
                    }
                    $stmtUpdate->execute();
                }
            } catch (PDOException $e) {
                echo __FUNCTION__."(): Error: " . $e->getMessage();
            }
            if ($debug) echo " DONE </p>";
        }
    }

    if ($debug)
        echo "<div>".var_export( $reportArray, true)."</div>";

    foreach ($reportArray as $worker_id => $report){
        try{
            updateWorkerAction($pdo, $worker_id,  $turn_number, NULL, ['investigate_report' => $report]);
        } catch (Exception $e) {
            echo "updateWorkerAction() failed for worker_id $worker_id: " . $e->getMessage() . "<br />";
            break;
        }
    }

    echo '<p>investigateMechanic : DONE </p> </div>';

    return TRUE;
}