<?php 

function diceSQL() {
    return "FLOOR(
        RANDOM() * (
            CAST((SELECT value FROM config WHERE name = 'MAXROLL') AS INT)
            - CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT)
            +  1
        ) + CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT)
    )";
}

function diceRoll() {
    $diceSQL = diceSQL();
    $sql = "SELECT $diceSQL as roll";

    try{
        // Prepare and execute SQL query
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo "UPDATE config Failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    $roll = $stmt->fetchALL(PDO::FETCH_ASSOC);
    return $roll[0]['roll'];
}

function calculateVals($pdo, $turn_number){

    $sqlArray = [];
    $array = [];
    $array[] = ['enquete', 'passiveInvestigateActions',false];
    $array[] = ['enquete', 'activeInvestigateActions',true];
    $array[] = ['action', 'passiveActionActions',false];
    $array[] = ['action', 'activeActionActions',true];
    $array[] = ['defence', 'passiveDefenceActions',false];
    $array[] = ['defence', 'activeDefenceActions',true];
    echo '<div> calculateVals : <p>';
    foreach ($array as $elements) {
        if ($elements[2]) {
            $valSQL = sprintf("%s_val = (
                COALESCE((
                    SELECT SUM(p.%s)
                    FROM workers AS w
                    LEFT JOIN worker_powers wp ON w.id = wp.worker_id
                    LEFT JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    LEFT JOIN powers p ON lpt.power_id = p.ID
                    WHERE worker_actions.worker_id = w.id
                ), 0)
                + FLOOR(
                    RANDOM() * 
                    ((SELECT CAST(value AS INT) FROM config WHERE name = 'MAXROLL') - 
                    (SELECT CAST(value AS INT) FROM config WHERE name = 'MINROLL') + 1)
                    + (SELECT CAST(value AS INT) FROM config WHERE name = 'MINROLL')
                )
            )",
            $elements[0], $elements[0]);
        } else {
            $valSQL = sprintf("%s_val = (
                COALESCE((
                    SELECT SUM(p.%s)
                    FROM workers AS w
                    LEFT JOIN worker_powers wp ON w.id = wp.worker_id
                    LEFT JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    LEFT JOIN powers p ON lpt.power_id = p.ID
                    WHERE worker_actions.worker_id = w.id
                ), 0)
                + (SELECT CAST(value AS INT) FROM config WHERE name = 'PASSIVEVAL') 
            )",
            $elements[0], $elements[0]);
        }

        // 
        $config = getConfig($pdo, $elements[1]);
        echo sprintf("Get Config for %s : $config <br /> ", $elements[1]);
        if (!empty($config)){
            $sqlArray[] = sprintf('UPDATE worker_actions SET %1$s 
                WHERE turn_number = %2$s AND action IN (%3$s)', $valSQL, $turn_number, $config );
        }
    }
    echo '</p>';
    foreach ($sqlArray as $sql) {
        echo "<p>DO SQL : <br> $sql <br>";
        try {
            // Prepare and execute SQL query
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
            return FALSE;
        }
        echo "DONE <br></p>";
    }
    echo '</p></div>';
    return TRUE;
}

function createNewTurnLines($pdo, $turn_number){
    $turn_number = 2; // Example turn number
    $sql = "
        INSERT INTO worker_actions (worker_id, turn_number, zone_id, controler_id)
        SELECT 
            w.id AS worker_id,
            :turn_number AS turn_number,
            w.zone_id AS zone_id,
            cw.controler_id AS controler_id
        FROM workers w
        JOIN controler_worker AS cw ON cw.worker_id = w.id AND is_primary_controler = true;
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':turn_number' => $turn_number]);
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
        return FALSE;
    }
}

function getSearcherComparisons($pdo, $threshold = 0, $searcher_id = NULL, $turn_number = NULL ) {
    if (empty($turn_number)) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
    }
    echo "turn_number : $turn_number <br>";
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
                wa.action IN ('passive', 'investigate')
                AND turn_number = :turn_number
        )
        SELECT 
            s.searcher_id,
            s.searcher_enquete_val,
            z.name AS zone_name,
            wa.worker_id AS found_id,
            wa.enquete_val AS found_enquete_val,
            wa.action AS found_action,
            wa.action_params AS found_action_params,
            CONCAT(w.firstname, ' ', w.lastname) AS found_name,
            cw.controler_id AS found_controler_id,
            CONCAT(c.firstname, ' ', c.lastname) AS found_controler_name,
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
            (s.searcher_enquete_val - wa.enquete_val) AS enquete_difference
        FROM searchers s
        JOIN zones z ON z.id = s.zone_id
        JOIN worker_actions wa ON 
                s.zone_id = wa.zone_id AND turn_number = :turn_number
        JOIN workers w ON wa.worker_id = w.ID
        JOIN controler_worker cw ON wa.worker_id = cw.worker_id AND is_primary_controler = true
        JOIN controlers c ON cw.controler_id = c.ID
        WHERE
            s.searcher_id != wa.worker_id
            AND s.searcher_controler_id != wa.controler_id
            AND (s.searcher_enquete_val - wa.enquete_val) >= :threshold
    ";
    if ( !EMPTY($searcher_id) ) $sql .= " AND s.searcher_id = :searcher_id";

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    if ( !EMPTY($searcher_id) ) $stmt->bindParam(':searcher_id', $searcher_id);
    $stmt->bindParam(':turn_number', $turn_number);
    $stmt->bindParam(':threshold', $threshold);
    $stmt->execute();

    // Fetch and return the results
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cleanAndSplitString($input) {
    // Remove curly braces and quotes
    $cleaned = str_replace(['{', '}', '"'], '', $input);
    // Split the string by commas into an array
    return array_map('trim', explode(',', $cleaned));
}

function investigateMecanic($pdo ) {
    echo '<div> investigateMecanic :';
    $investigations = getSearcherComparisons($pdo);
    $rapportArray = [];

    $txtArray = [];
    $txtArray['passive'] = getConfig($pdo, 'txt_passive');
    $txtArray['investigate'] = getConfig($pdo, 'txt_investigate');
    $txtArray['attack'] = getConfig($pdo, 'txt_attack');
    $txtArray['claim'] = getConfig($pdo, 'txt_claim');

    foreach ($investigations as $row) {
        // Build Rapport : 
        if ( empty($rapportArray[$row['searcher_id']]) )
            $rapportArray[$row['searcher_id']] = sprintf( "<p> Dans le quartier %s.</p>", $row['zone_name'] );
        // Diff 0
        $rapport = sprintf(
            "<p> J'ai trouver %s (%s) %s qui n'est pas un agent à nous c'est un %s et un %s. ", 
            $row['found_name'], $row['found_id'],
            empty($row['found_transformation']) ? '' : (cleanAndSplitString($row['found_transformation'])[0]),
            cleanAndSplitString($row['found_metier'])[0], cleanAndSplitString($row['found_hobby'])[0]
        );
        // Diff 1
        $text_action_params = '';
        /*if ( !empty($row['found_action_params']) ) {
            $text_action_params = 'tada'; 
        }*/
        $discipline = cleanAndSplitString($row['found_discipline']);
        $rapport .= sprintf(
            "Il démontre une légere maitrise de la dicipline %s alors qu'il %s%s",
            $discipline[0],
            $txtArray[$row['found_action']],
            empty($text_action_params) ? '. ' : ' '
        );
        // Diff 2
        $discipline_2 = '';
        if (! empty($discipline[1]) ) 
            $discipline_2 = sprintf("Et une légere maitrise de la dicipline %s.", $discipline[1])   ;
        // $rapport .= "Et une légere maitrise de la dicipline {$row['found_discipline_2']}.";
        $rapport .= sprintf("%s Il fait parti du réseau %s. ", $discipline_2 , $row['found_controler_id'] );
        // Diff 3
        $rapport .= sprintf("Ce réseau répond à %s. ", $row['found_controler_name']);
        $rapport .= "Searcher ID: {$row['searcher_id']}, Searcher Enquete Val: {$row['searcher_enquete_val']}, ";
        $rapport .= "Found ID: {$row['found_id']}, Found Enquete Val: {$row['found_enquete_val']}, ";
        $rapport .= "Difference: {$row['enquete_difference']}\n";
        $rapport .= '</p>';
        $rapportArray[$row['searcher_id']] .= $rapport;
        echo $rapport;
    }
    echo '<div>';
}