<?php

// Function to get Controlers and return as an array
function getWorkers($pdo, $worker_ids) {
    $workersArray = array();

    if ( empty($worker_ids) ) return NULL;
    $worker_id_str = implode(',', $worker_ids);

    $sql = "SELECT
            w.*,
            wo.name AS origin_name,
            z.name AS zone_name,
            COALESCE(SUM(p.enquete), 0) AS total_enquete,
            COALESCE(SUM(p.action), 0) AS total_action,
            COALESCE(SUM(p.defence), 0) AS total_defence
        FROM
            workers AS w
        JOIN
            worker_origins AS wo ON wo.id = w.origin_id
        JOIN
            zones AS z ON z.id = w.zone_id
        LEFT JOIN
            worker_powers wp ON w.id = wp.worker_id
        LEFT JOIN
            link_power_type lpt ON wp.link_power_type_id = lpt.ID
        LEFT JOIN
            powers p ON lpt.power_id = p.ID
        WHERE
            w.id IN ($worker_id_str)
        GROUP BY w.id, wo.name, z.name
        ORDER BY w.id ASC
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    // Fetch the results
    $workersArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($_SESSION['DEBUG'] == true) echo sprintf("workersArray %s <br> <br>", var_export($workersArray,true));

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
        w.id IN ($worker_id_str)
    ORDER BY w.id ASC
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    // Fetch the results
    $workers_powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($_SESSION['DEBUG'] == true) echo sprintf("workers_powers %s <br> <br>", var_export($workers_powers,true));

    // Index $workers_powers by worker_id for faster lookup
    $workerPowersById = [];
    foreach ($workers_powers as $power) {
        if ( !empty($workerPowersById[$power['worker_id']][$power['power_type_name']]['texte']) ) {
            $workerPowersById[$power['worker_id']][$power['power_type_name']]['texte'] .= ', ';
        }
        $workerPowersById[$power['worker_id']][$power['power_type_name']]['texte'] .= $power['power_name'];
        $workerPowersById[$power['worker_id']][$power['power_type_name']][] = $power['power_name'];
    }

    foreach ($workersArray as $key => $worker) {
        $workersArray[$key]['powers'] = $workerPowersById[$worker['id']] ?? []; // Add powers or empty array if none
    }
    if ($_SESSION['DEBUG'] == true) echo sprintf("workersArray %s <br> <br>", var_export($workersArray,true));

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
        power_hobby
        power_metier
        origin_id
        power_hobby_id
        power_metier_id
        zone
        discipline
        controler_id
    */
    try{
        // Insert new workers value into the database
        $stmt = $pdo->prepare("INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES (:firstname, :lastname, :origin_id, :zone_id)");
        $stmt->bindParam(':firstname', $array['firstname']);
        $stmt->bindParam(':lastname', $array['lastname']);
        $stmt->bindParam(':origin_id', $array['origin_id']);
        $stmt->bindParam(':zone_id', $array['zone']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT workers Failed: " . $e->getMessage()."<br />";
    }
    // Get the last inserted ID
    $worker_id = $pdo->lastInsertId();

    try{
        // Insert new controler_worker value into the database
        $stmt = $pdo->prepare("INSERT INTO controler_worker (controler_id, worker_id) VALUES (:controler_id, :worker_id)");
        $stmt->bindParam(':controler_id', $array['controler_id']);
        $stmt->bindParam(':worker_id', $worker_id );
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT controler_worker Failed: " . $e->getMessage()."<br />";
    }

    $link_power_type_id_array = [];
    if (!empty($array['power_hobby_id'])) $link_power_type_id_array[] = $array['power_hobby_id'];
    if (!empty($array['power_metier_id'])) $link_power_type_id_array[] = $array['power_metier_id'];
    if (!empty($array['discipline'])) $link_power_type_id_array[] = $array['discipline'];
    foreach($link_power_type_id_array as $link_power_type_id ) {
        try{
            // Insert new worker_powers value into the database
            $stmt = $pdo->prepare("INSERT INTO worker_powers (worker_id, link_power_type_id) VALUES (:worker_id, :link_power_type_id)");
            $stmt->bindParam(':link_power_type_id', $link_power_type_id);
            $stmt->bindParam(':worker_id', $worker_id );
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__."(): INSERT worker_powers Failed: " . $e->getMessage()."<br />";
        }
    }
    return NULL;
}
