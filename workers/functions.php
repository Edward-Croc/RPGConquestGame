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

function randomWorkerOrigin($pdo, $limit = 1) {
    // Get a random value from worker_origins
    $sql = "SELECT * FROM worker_origins ORDER BY RANDOM() LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // Fetch the results
    $worker_origins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store worker_origins in the array
    foreach ($worker_origins as $worker_origin) {
        $originsArray[] = $worker_origin;
    }
    return $originsArray;
}

function randomWorkerName($pdo, $originList, $limit = 2) {
    // Get 2 random values from worker_names for and origin ID 
    $sql = "SELECT * FROM worker_names WHERE origin_id IN ($originList) ORDER BY RANDOM() LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    // Fetch the results
    $worker_names = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Store worker_origins in the array
    foreach ($worker_names as $worker_name) {
        $nameArray[] = $worker_name;
    }
    return $nameArray;
}
