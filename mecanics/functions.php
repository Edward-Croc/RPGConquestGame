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