<?php

// Function to get Controlers and return as an array
function getWorkers($pdo, $worker_ids) {

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
    if ($_SESSION['DEBUG'] == true) echo sprintf("workersArray %s <br /> <br />", var_export($workersArray,true));

    $workers_powers = getPowersByWorkers($pdo, $worker_id_str);

    // Index $workers_powers by worker_id for faster lookup
    $workerPowersById = [];
    foreach ($workers_powers as $power) {
        $worker_id = $power['worker_id'];
        $power_type = $power['power_type_name'];
        if ( empty($workerPowersById[$worker_id][$power_type]) )
             $workerPowersById[$worker_id][$power_type]['texte'] = '';
        if ( !empty($workerPowersById[$worker_id][$power_type]['texte']) ) {
            $workerPowersById[$worker_id][$power_type]['texte'] .= ', ';
        }
        $workerPowersById[$worker_id][$power_type]['texte'] .=  $power['power_text'];
        $workerPowersById[$worker_id][$power_type][] = $power['name'];
    }

    // Select  all entries to worker_actions for a worker_id
    $worker_actions = getActionsByWorkers($pdo, $worker_id_str);
    // Index $worker_actions by worker_id for faster lookup
    $workerActionsById = [];
    foreach ($worker_actions as $action) {
        if (empty($workerActionsById[$worker_id][$action['turn_number']])) $workerActionsById[$worker_id][$action['turn_number']] = [];
        $worker_id = $action['worker_id'];
        $workerActionsById[$worker_id][$action['turn_number']] =  $action;
    }

    foreach ($workersArray as $key => $worker) {
        $workersArray[$key]['powers'] = $workerPowersById[$worker['id']] ?? []; // Add powers or empty array if none
        $workersArray[$key]['actions'] = $workerActionsById[$worker['id']] ?? []; // Add actons or empty array if none
    }
    if ($_SESSION['DEBUG'] == true) echo sprintf("workersArray %s <br /> <br />", var_export($workersArray,true));

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

function getActionsByWorkers($pdo, $worker_id_str){
    $sql = "SELECT * FROM worker_actions w
        WHERE worker_id IN ($worker_id_str)
        ORDER BY worker_id ASC, turn_number ASC
    ";
    try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    } catch (PDOException $e) {
    echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
    return NULL;
    }
    // Fetch the results
    $worker_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($_SESSION['DEBUG'] == true) echo sprintf("worker_actions: %s <br /> <br />", var_export($workers_powers,true));

    return $worker_actions;
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
    // Check if worker already exists : 
    try{
        // Insert new workers value into the database
        $stmt = $pdo->prepare("SELECT w.id AS id FROM workers AS w
        INNER JOIN controler_worker AS cw ON cw.worker_id = w.id
        WHERE w.firstname = :firstname AND w.lastname = :lastname AND w.origin_id = :origin_id AND cw.controler_id = :controler_id");
        $stmt->bindParam(':firstname', $array['firstname']);
        $stmt->bindParam(':lastname', $array['lastname']);
        $stmt->bindParam(':origin_id', $array['origin_id']);
        $stmt->bindParam(':controler_id', $array['controler_id']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT workers Failed: " . $e->getMessage()."<br />";
    }
    $worker = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($worker)) return $worker[0]['id'];
    try{
        // Insert new workers value into the database
        $stmt = $pdo->prepare("INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES (:firstname, :lastname, :origin_id, :zone_id)");
        $stmt->bindParam(':firstname', $array['firstname']);
        $stmt->bindParam(':lastname', $array['lastname']);
        $stmt->bindParam(':origin_id', $array['origin_id']);
        $stmt->bindParam(':zone_id', $array['zone_id']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT workers Failed: " . $e->getMessage()."<br />";
    }
    // Get the last inserted ID
    $worker_id = $pdo->lastInsertId();

    addWorkerAction($pdo, $worker_id, $array['controler_id'], $array['zone_id']);

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
    return $worker_id;
}

function addWorkerAction($pdo, $worker_id, $controler_id, $zone_id){
    $mecanics = getMecanics($pdo);
    try{
        // Insert new controler_worker value into the database
        $stmt = $pdo->prepare("INSERT
            INTO worker_actions (worker_id, turn_number, zone_id, controler_id) 
             VALUES (:worker_id, :turn_number, :zone_id, :controler_id)");
        $stmt->bindParam(':controler_id', $controler_id,);
        $stmt->bindParam(':worker_id', $worker_id );
        $stmt->bindParam(':zone_id', $zone_id );
        $stmt->bindParam(':turn_number', $mecanics['turncounter']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT controler_worker Failed: " . $e->getMessage()."<br />";
    }
    // Get the last inserted ID
    return $pdo->lastInsertId();
}

function moveWorker($pdo, $worker_id, $zone_id) {
    try{
        // UPDATE workers value
        $stmt = $pdo->prepare("UPDATE workers SET zone_id = :zone_id WHERE id = :id ");
        $stmt->bindParam(':id', $worker_id);
        $stmt->bindParam(':zone_id', $zone_id);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE workers Failed: " . $e->getMessage()."<br />";
    }
    // get worker action status for turn 
    $action = getWorkerActions($pdo, $worker_id);
    try{
        // UPDATE worker_actions values
        $stmt = $pdo->prepare("UPDATE worker_actions SET zone_id = :zone_id WHERE id = :id ");
        $stmt->bindParam(':zone_id', $zone_id);
        $stmt->bindParam(':id', $action['action']['id']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE workers Failed: " . $e->getMessage()."<br />";
    }

    return $worker_id;
}

// get worker action status for turn 
function getWorkerActions($pdo, $worker_id, $turn_number = NULL ){

    if (empty($turn_number)) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
    }

    $sql = "SELECT * FROM worker_actions
        WHERE worker_id = $worker_id
        AND turn_number = $turn_number 
        ORDER BY id DESC
    ";
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): sql: ".var_export($sql, true)."<br/><br/>";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    // Fetch the results
    $worker_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): worker_actions: ".var_export($worker_actions, true)."<br/><br/>";
    return $worker_actions;
}

function activateWorker($pdo, $worker_id, $action, $extraVal = NULL) {

    $mecanics = getMecanics($pdo);
    $turn_number = $mecanics['turncounter'];

    // get worker action status for turn 
    $worker_actions = getWorkerActions($pdo, $worker_id);
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): worker_action: ".var_export($worker_actions, true)."<br/><br/>";
    $sql_worker_actions = "UPDATE worker_actions SET ";
    switch($action) {
        case 'attack' :
            if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): attack <br/><br/>";
            $sql_worker_actions .= " action = '$action' ";
            // Build attack JSON
            $attackScope = '';
            $attackID = null;
            // Determine scope and ID
            if ($extraVal === 'carnage' || $extraVal === 'allknown') {
                $attackScope = $extraVal;
            } elseif (preg_match('/^(network|worker)_(\d+)$/', $extraVal, $matches)) {
                $attackScope = $matches[1]; // Extract scope (e.g., 'network' or 'worker')
                $attackID = intval($matches[2]); // Extract ID as integer
            } else {
                throw new Exception('Invalid extraVal format');
            }
            // Create JSON table
            $jsonOutput = json_encode([
                'attackScope' => $attackScope,
                'attackID' => $attackID
            ]);
            $sql_worker_actions .= ", action_params = '$jsonOutput'";
            break;
        case 'activate' :
            if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): activate <br/><br/>";
            // if worker is other than passive set passive
            $new_action = 'passive';
            if ( $worker_actions[0]['action'] == 'passive' ) { // if worker is passive set investigating
                $new_action = 'investigate';
            }
            $sql_worker_actions .= " action = '$new_action' ";
            break;
        case 'claim' :
            if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): claim <br/><br/>";
            $sql_worker_actions .= " action = '$action' ";
            // Create JSON table
            $jsonOutput = json_encode([
                'claim_controler_id' => $extraVal
            ]);
            $sql_worker_actions .= ", action_params = '$jsonOutput'";
            
            break;
    }
    try{
        $sql_worker_actions .= " WHERE id = :id AND turn_number = :turn_number ";
        if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): sql_worker_actions : ".var_export($sql_worker_actions, true)." <br/><br/>";
        // Insert new workers value into the database
        $stmt = $pdo->prepare($sql_worker_actions);
        $stmt->bindParam(':id', $worker_actions[0]['id']);
        $stmt->bindParam(':turn_number', $turn_number );
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE workers Failed: " . $e->getMessage()."<br />";
    }
    return $worker_id;
}

function enemyWorkersFind($pdo, $zone_id, $controler_id) {

}

function enemyWorkersSelect($pdo, $zone_id, $controler_id) {
    $enemyWorkerOptions = '';
    $enemyWorkerArray = [];
    // Display select list of Controlers
    foreach ( $enemyWorkerArray as $enemyWorker) {
        $enemyWorkerOptions .= "<option value='" . $enemyWorker['id'] . "'>" . $enemyWorker['name'] . " </option>";
    }

    $enemyWorkersSelect = sprintf("
        <select id='enemyWorkersSelect' name='enemy_worker_id'>
            <option value='carnage'>Carnage!</option>
            <option value='allknown'>Tous les connus!</option>
            %s
        </select>
        ",
        $enemyWorkerOptions
    );
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): enemyWorkersSelect: ".var_export($enemyWorkersSelect, true)."<br /><br />";

    return $enemyWorkersSelect;
}