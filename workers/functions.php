<?php

// Function to get Controlers and return as an array
function getWorkers($pdo, $worker_ids) {
    $workersArray = array();

    if ( empty($worker_ids) ) return NULL;
    $worker_id_str = implode(',', $worker_ids);

    /*
    
            (SELECT 
                FROM worker_powers AS 
                JOIN link_power_type AS lpt ON lpt.id = wp.link_power_type_id
                JOIN link_power_type AS lpt ON lpt.id = wp.link_power_type_id
                WHERE wp.worker_id = w.id
            )
    */
    $sql = "SELECT w.*,
            wo.name AS origin_name,
            z.name  AS zone_name
        FROM workers AS w
        JOIN worker_origins AS wo ON wo.id = w.origin_id
        JOIN zones AS z ON z.id = w.zone_id
        WHERE w.id IN (:worker_id_str)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':worker_id_str' => $worker_id_str]);
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store Controlers in the array
    foreach ($workers as $worker) {
        $workersArray[] = $worker;
    }
    return $workersArray;
}

// Function to get Controlers and return as an array
function getWorkersByControler($pdo, $controler_id) {
    $workersArray = array();

    $sql = " SELECT * FROM controler_worker AS cw
        WHERE cw.controler_id = :controler_id
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':controler_id' => $controler_id]);
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $controler_workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $worker_ids = NULL;
    // Store Controlers in the array
    foreach ($controler_workers as $controler_worker) {
        $worker_ids[] = $controler_worker['worker_id'];
    }

    return getWorkers($pdo, $worker_ids);
}

function randomWorkerOrigin($pdo, $limit = 1, $origin_list = '') {
    $originsArray = array();

    $sql_origin_id = '';
    if ( !empty($origin_list) ){
        $sql_origin_id .= " WHERE id in ($origin_list)";
    }

    try{
        // Get a random value from worker_origins
        $sql = "SELECT * FROM worker_origins $sql_origin_id ORDER BY RANDOM() LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }   

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
        try{
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } catch (PDOException $e) {
            echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
            return NULL;
        }

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

function createWorker($pdo, $array) {
    /* Array should contain :         
        "creation"="true"
       firstname
      lastname
        origin
        "power_hobby" value="%4$s">
        <input type="hidden" name="power_metier" value="%5$s">
        <input type="hidden" name="origin_id" value="%6$s">
        <input type="hidden" name="power_hobby_id" value="%7$s">
        <input type="hidden" name="power_metier_id" value="%8$s">
    */
     try{
        $sql = "INSERT INTO value 
            FROM config 
            WHERE name = :configName
        ";
        $stmt = $pdo->prepare();
        $stmt->execute([':configName' => $configName]);
        return $stmt->fetchColumn();  
    } catch (PDOException $e) {
        echo "getConfig $configName failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    return NULL;
}
