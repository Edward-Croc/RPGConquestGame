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
        echo sprintf("Get Config for %s : $config ", $elements[1]);
        if (!empty($config)){
            $sqlArray[] = sprintf('UPDATE worker_actions SET %1$s 
                WHERE turn_number = %2$s AND action IN (%3$s)', $valSQL, $turn_number, $config );
        }
    }
    
    foreach ($sqlArray as $sql) {
        echo "DO SQL : <br> $sql <br>";
        try {
            // Prepare and execute SQL query
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
            return FALSE;
        }
        echo "DONE <br><br>";
    }
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
        JOIN controler_worker AS cw ON cw.worker_id = w.id;
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
            wa.worker_id AS found_id,
            wa.enquete_val AS found_enquete_val,
            (s.searcher_enquete_val - wa.enquete_val) AS enquete_difference,
        FROM 
            searchers s
        JOIN 
            worker_actions wa ON 
                s.zone_id = wa.zone_id AND turn_number = :turn_number
        WHERE 
            s.searcher_id != wa.worker_id
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

function investigateMecanic($pdo ) {
    echo '<div> investigateMecanic :';
    $investigations = getSearcherComparisons($pdo);
    foreach ($investigations as $row) {
        echo  '<p>';
        echo "Searcher ID: {$row['searcher_id']}, Searcher Enquete Val: {$row['searcher_enquete_val']}, ";
        echo "Found ID: {$row['found_id']}, Found Enquete Val: {$row['found_enquete_val']}, ";
        echo "Difference: {$row['enquete_difference']}\n";
        echo  '</p>';
    }
    echo '<div>';
}