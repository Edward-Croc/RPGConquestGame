<?php


// Function to get controllers and return as an array
function getWorkersByControler($pdo, $controler_id) {
    $workersArray = array();

    $sql = "SELECT * FROM controler_worker AS cw 
            WHERE cw.controler_id = '$controler_id'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // Fetch the results
    $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store controllers in the array
    foreach ($workers as $worker) {
        $workersArray[] = $worker;
    }
    return $workersArray;
}
