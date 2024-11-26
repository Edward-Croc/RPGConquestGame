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

function randomWorkerOrigin($pdo, $limit = 1, $origin_list = '') {
    $originsArray = array();

    $sql_origin_id = '';
    if ( !empty($origin_list) ){
        $sql_origin_id .= " WHERE id in ($origin_list)";
    }

    // Get a random value from worker_origins
    $sql = "SELECT * FROM worker_origins $sql_origin_id ORDER BY RANDOM() LIMIT $limit";
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

function randomWorkerName($pdo, $origin_list, $iterations = 1) {
    $nameArray = array();

    $originsArray = randomWorkerOrigin($pdo,  $iterations, $origin_list);

    for ($iteration = 0; $iteration < $iterations; $iteration++) {
        $origin_id = $originsArray[$iteration]['id'];
        // Get 2 random values from worker_names for and origin ID
        $sql = "SELECT * FROM worker_names
            JOIN worker_origins ON worker_origins.id = worker_names.origin_id
            WHERE origin_id = $origin_id
            ORDER BY RANDOM()
            LIMIT 2";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        // Fetch the results
        $worker_names = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Store worker_origins in the array
        $nameArray[$iteration]['firstname'] = $worker_names[0]['firstname'];
        $nameArray[$iteration]['lastname'] = $worker_names[1]['lastname'];
        $nameArray[$iteration]['origin_id'] = $origin_id;
        $nameArray[$iteration]['origin'] = $worker_names[1]['name'];
    }
    return $nameArray;
}
