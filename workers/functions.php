<?php

// Function to get Controlers and return as an array
function getWorkers($pdo, $worker_ids) {
    $workersArray = array();

    if ( empty($worker_ids) ) return NULL;
    $worker_id_str = implode(',', $worker_ids);

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

    $sql = "SELECT 
        w1.id AS worker_id,
        COALESCE(SUM(p1.enquete), 0) AS total_enquete,
        COALESCE(SUM(p1.action), 0) AS total_action,
        COALESCE(SUM(p1.defence), 0) AS total_defence
    FROM 
        workers w1
    LEFT JOIN 
        worker_powers wp1 ON w1.id = wp1.worker_id
    LEFT JOIN 
        link_power_type lpt1 ON wp1.link_power_type_id = lpt1.ID
    LEFT JOIN 
        powers p1 ON lpt1.power_id = p1.ID
    WHERE 
        w1.id IN (:worker_id_str)
    GROUP BY 
        w1.id
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':worker_id_str' => $worker_id_str]);
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    // Fetch the results
    $workers_values = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($_SESSION['DEBUG'] == true) echo sprintf("workers_values %s <br> <br>", var_export($workers_values,true));

    $sql = "SELECT 
        w.id AS worker_id,
        p.name AS power_name,
        pt.name AS power_type_name
    FROM 
        workers w
    JOIN 
        worker_powers wp ON w.id = wp.worker_id
    JOIN 
        link_power_type lpt ON wp.link_power_type_id = lpt.ID
    JOIN 
        powers p ON lpt.power_id = p.ID
    JOIN 
        power_types pt ON lpt.power_type_id = pt.ID
    WHERE 
        w.id IN (:worker_id_str)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':worker_id_str' => $worker_id_str]);
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    // Fetch the results
    $workers_powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($_SESSION['DEBUG'] == true) echo sprintf("workers_powers %s <br> <br>", var_export($workers_powers,true));

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
