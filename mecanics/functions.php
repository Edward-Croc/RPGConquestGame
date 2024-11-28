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
    $sql = "SELECT $diceSQL";

    return 0;
}

function calculateVals($pdo, $turn_number){
 
    $sql = "UPDATE worker_actions SET 
    enquete_val = (
        COALESCE((
            SELECT SUM(p.enquete)
            FROM workers AS w
            LEFT JOIN worker_powers wp ON w.id = wp.worker_id
            LEFT JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
            LEFT JOIN powers p ON lpt.power_id = p.ID
            WHERE worker_actions.worker_id = w.id
        ), 0)
        + FLOOR(
            RANDOM() * 
            (CAST((SELECT value FROM config WHERE name = 'MAXROLL') AS INT) - 
             CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT) + 1) + 
            CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT)
        )
    ),
    action_val = (
        COALESCE((
            SELECT SUM(p.action)
            FROM workers AS w
            LEFT JOIN worker_powers wp ON w.id = wp.worker_id
            LEFT JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
            LEFT JOIN powers p ON lpt.power_id = p.ID
            WHERE worker_actions.worker_id = w.id
        ), 0)
        + FLOOR(
            RANDOM() * 
            (CAST((SELECT value FROM config WHERE name = 'MAXROLL') AS INT) - 
             CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT) + 1) + 
            CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT)
        )
    )
    WHERE turn_number = $turn_number";

    try{
        // Prepare and execute SQL query
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo "UPDATE config Failed: " . $e->getMessage()."<br />";
        return FALSE;
    }
    return TRUE;
}
