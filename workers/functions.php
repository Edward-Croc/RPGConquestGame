<?php

function updateWorkerActiveStatus($pdo, $workerId, $isActive = false) {
    if (is_null($isActive)) {
        echo  __FUNCTION__."(): isActive: NULL<br />";
        return FALSE;
    }

    $query = sprintf("UPDATE workers SET is_active = FALSE WHERE id = %s",$workerId);
    if ($isActive) $query = sprintf("UPDATE workers SET is_active = TRUE WHERE id = %s", $workerId);
    echo sprintf(" query : %s, ", $query);

    try{
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return FALSE;
    }
    return TRUE;
}

function updateWorkerAliveStatus($pdo, $workerId, $isAlive = false) {
    if (is_null($isAlive)) {
        echo  __FUNCTION__."(): isAlive: NULL<br />";
        return FALSE;
    }

    $query = sprintf("UPDATE workers SET is_alive = FALSE WHERE id = %s", $workerId );
    if ($isAlive) $query = sprintf("UPDATE workers SET is_alive = TRUE WHERE id = %s", $workerId);
    echo sprintf(" query : %s, ", $query);
    try{
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return FALSE;
    }
    return TRUE;
}

function updateWorkerAction($pdo, $workerId, $turnNumber, $action_choice = null, $reportAppendArray = null) {
    $query = "UPDATE worker_actions SET ";
    $updates = [];
    $params = ['worker_id' => $workerId, 'turn_number' => $turnNumber];

    if (!empty($action_choice)) {
        $updates[] = "action_choice = '$action_choice'";
    }
    if (!empty($reportAppendArray)) {
        // Step 1: Fetch the existing report
        $stmt = $pdo->prepare("SELECT report FROM worker_actions WHERE worker_id = :worker_id AND turn_number = :turn_number");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception("No record found for worker_id $workerId and turn_number $turnNumber.");
        }
        // Step 2: Decode the JSON report
        $report = json_decode($row['report'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(__FUNCTION__."():Failed to decode JSON: " . json_last_error_msg());
        }
        // Step 3: Append the new element to the specified key
        if (!empty($reportAppendArray['life_report'])){
            if (empty($report['life_report'])) $report['life_report']=''; 
            $report['life_report'] .= $reportAppendArray['life_report'];
        }
        if (!empty($reportAppendArray['attack_report'])){
            if (empty($report['attack_report'])) $report['attack_report']=''; 
            $report['attack_report'] .= $reportAppendArray['attack_report'];
        }
        if (!empty($reportAppendArray['investigate_report'])){
            if (empty($report['investigate_report'])) $report['investigate_report'] = ''; 
            $report['investigate_report'] .= $reportAppendArray['investigate_report'];
        }
        $updates[] = "report = :report";
        $params['report'] = json_encode($report);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to encode JSON: " . json_last_error_msg());
        }
    }

    if (count($updates)>0) {
        $query .= implode(", ", $updates) . " WHERE worker_id = :worker_id AND turn_number = :turn_number";
        echo sprintf(" query : %s, Params : %s ", $query, var_export($params, TRUE));

        try{
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        } catch (PDOException $e) {
            echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
            return FALSE;
        }
        return TRUE;
    }
    return FALSE;
}

// Function to get Controlers and return as an array
function getWorkers($pdo, $worker_ids) {

    if ( empty($worker_ids) ) return NULL;
    $worker_id_str = implode(',', $worker_ids);

    $sql = "SELECT
            w.*,
            wo.name AS origin_name,
            z.name AS zone_name,
            cw.is_primary_controler,
            (SELECT MAX(wa.turn_number) - MIN(wa.turn_number)
                FROM worker_actions wa
                WHERE wa.worker_id = w.id
                GROUP BY wa.worker_id
            ) AS age,
            COALESCE(SUM(p.enquete), 0) AS total_enquete,
            COALESCE(SUM(p.attack), 0) AS total_attack,
            COALESCE(SUM(p.defence), 0) AS total_defence
        FROM
            workers AS w
        JOIN controler_worker AS cw ON cw.worker_id = w.id
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
        GROUP BY w.id, wo.name, z.name, cw.is_primary_controler
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
        transformation
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
    if (!empty($array['discipline']) && $array['discipline'] != "\'\'" ) $link_power_type_id_array[] = $array['discipline'];
    if (!empty($array['transformation']) && $array['transformation'] != "\'\'" ) $link_power_type_id_array[] = $array['transformation'];
    foreach($link_power_type_id_array as $link_power_type_id ) {
        upgradeWorker($pdo, $worker_id, $link_power_type_id);
    }
    return $worker_id;
}

function upgradeWorker($pdo, $worker_id, $link_power_type_id){
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

function countWorkerDisciplines($pdo, $worker_id = NULL) {
    try {
        $sql = sprintf("SELECT 
                wp.worker_id, 
                COUNT(*) AS discipline_count
            FROM 
                worker_powers wp
            INNER JOIN 
                link_power_type lpt ON wp.link_power_type_id = lpt.ID
            INNER JOIN 
                power_types pt ON lpt.power_type_id = pt.ID
            WHERE 
                pt.name = 'Discipline'
                %s
            GROUP BY 
                wp.worker_id",
            empty($worker_id) ? "" : sprintf(" AND wp.worker_id IN (%s) ", implode(',',$worker_id))
        );
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__." (): Error counting worker disciplines: " . $e->getMessage();
        return [];
    }
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
    $actions = getWorkerActions($pdo, $worker_id);
    $action = $actions[0];
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): action: ".var_export($action, true)."<br/><br/>";

    // Decode the existing JSON into an associative array
    $currentReport = json_decode($action['report'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo __FUNCTION__."(): JSON decoding error: " . json_last_error_msg() . "<br />";
        $currentReport = array();
    }
    $zone_name = getZoneName($pdo, $zone_id);
    if (empty($currentReport['life_report'])) $currentReport['life_report'] ='';
    $currentReport['life_report'] .= "J'ai déménagé vers $zone_name. ";
    // Encode the updated array back into JSON
    $updatedReportJson = json_encode($currentReport);
    if (json_last_error() !== JSON_ERROR_NONE)
        echo "JSON decoding error: " . json_last_error_msg() . "<br />";
    try{
        // UPDATE worker_actions values
        $stmt = $pdo->prepare("UPDATE worker_actions SET zone_id = :zone_id, report = :report WHERE id = :id ");
        $stmt->bindParam(':zone_id', $zone_id);
        $stmt->bindParam(':id', $action['id']);
        $stmt->bindParam(':report', $updatedReportJson );
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
    $currentAction = $worker_actions[0];

    // Decode the existing JSON into an associative array
    $currentReport = json_decode($currentAction['report'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo __FUNCTION__."():JSON decoding error: " . json_last_error_msg() . "<br />";
        $currentReport = array();
    }
    $sql_worker_actions = "UPDATE worker_actions SET ";
    $new_action = $action;
    $jsonOutput = '{}';
    switch($action) {
        case 'attack' :
            if ($_SESSION['DEBUG_ATTACK'] == true){
                echo sprintf("%s(): attack %s <br/><br/>",__FUNCTION__, var_export($extraVal,true)) ;
            }
            // Build attack JSON
            $chosenAttackOptions = array();
            foreach ($extraVal as $val){
                $attackScope = '';
                $attackID = null;
                // Determine scope and ID
                if (preg_match('/^(network|worker)_(\d+)$/', $val, $matches)) {
                    $attackScope = $matches[1]; // Extract scope (e.g., 'network' or 'worker')
                    $attackID = intval($matches[2]); // Extract ID as integer
                } else {
                    throw new Exception('Invalid extraVal format');
                }
                $chosenAttackOptions[] =
                [
                    'attackScope' => $attackScope,
                    'attackID' => $attackID
                ];
            }
            // Create JSON table
            $jsonOutput = json_encode($chosenAttackOptions);
            break;
        case 'activate' :
            if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): activate <br/><br/>";
            // if worker is other than passive set passive
            $new_action = 'passive';
            if ( $worker_actions[0]['action_choice'] == 'passive' ) { // if worker is passive set investigating
                $new_action = 'investigate';
            }
            break;
        case 'claim' :
            if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): claim <br/><br/>";
            // Create JSON table
            $jsonOutput = json_encode([
                'claim_controler_id' => $extraVal
            ]);
            break;
        case 'gift' :
            if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): gift <br/><br/>";
            $new_action = 'passive';
            if (empty($currentReport['life_report'])) $currentReport['life_report'] ='';
            $currentReport['life_report'] .= "J'ai rejoint un nouveau maitre. ";
            // Set controler_worker controler_id and set worker_actions controler_id where turn_numer = current_turn to $extraVal
            try {
                // Update the controler_worker table
                $sqlControlerWorker = "UPDATE controler_worker SET controler_id = :extraVal WHERE worker_id = :worker_id";
                $stmtControlerWorker = $pdo->prepare($sqlControlerWorker);
                $stmtControlerWorker->execute([
                    ':extraVal' => $extraVal,
                    ':worker_id' => $worker_id
                ]);
                // Update the worker_actions table
                $sqlWorkerActions = "UPDATE worker_actions SET controler_id = :extraVal
                    WHERE worker_id = :worker_id AND turn_number = :turn_number";
                $stmtWorkerActions = $pdo->prepare($sqlWorkerActions);
                $stmtWorkerActions->execute([
                    ':extraVal' => $extraVal,
                    ':worker_id' => $worker_id,
                    ':turn_number' => $turn_number
                ]);
            } catch (PDOException $e) {
                echo __FUNCTION__." (): Failed to update tables: " . $e->getMessage() . "<br />";
            }
            break;
    }
    $sql_worker_actions .= " action_choice = '$new_action' ";
    $sql_worker_actions .= ", action_params = '$jsonOutput'";
    // Encode the updated array back into JSON
    $updatedReportJson = json_encode($currentReport);
    if (json_last_error() === JSON_ERROR_NONE) {
        $sql_worker_actions .= ", report = :report ";
    } else { echo "JSON decoding error: " . json_last_error_msg() . "<br />"; }
    try{
        $sql_worker_actions .= " WHERE id = :id AND turn_number = :turn_number ";
        if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): sql_worker_actions : ".var_export($sql_worker_actions, true)." <br/><br/>";
        // Insert new workers value into the database
        $stmt = $pdo->prepare($sql_worker_actions);
        $stmt->bindParam(':id', $worker_actions[0]['id']);
        $stmt->bindParam(':turn_number', $turn_number );
        $stmt->bindParam(':report', $updatedReportJson );
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE workers Failed: " . $e->getMessage()."<br />";
    }
    return $worker_id;
}

function getEnemyWorkers($pdo, $zone_id, $controler_id) {
    // Select from controlers_known_enemies by $zone_id, $controler_id
        // return table of :
        // A worker discovered_worker_id with no discovered_controler_id
        // B workers discovered_worker_id with identical discovered_controler_id
            // Optional discovered_controler_name if is associated to a
    if ($_SESSION['DEBUG_ATTACK'] == true) {
        echo sprintf("zone_id: %s <br/> " , var_export($zone_id, true));
        echo sprintf("controler_id: %s <br/> " , var_export($controler_id, true));
    }
    try {
        // Query for workers with no discovered_controler_id (A)
        $sqlA = "
            SELECT
                CONCAT(w.firstname, ' ', w.lastname) AS name,
                cke.id,
                last_discovery_turn,
                (last_discovery_turn - first_discovery_turn) AS discovery_age
            FROM
                controlers_known_enemies cke
            JOIN
                workers AS w ON cke.discovered_worker_id = w.id
            WHERE
                cke.zone_id = :zone_id
                AND cke.controler_id = :controler_id
                AND cke.discovered_controler_id IS NULL
            ORDER BY last_discovery_turn DESC
        ";
        $stmtA = $pdo->prepare($sqlA);
        $stmtA->execute([
            ':zone_id' => $zone_id,
            ':controler_id' => $controler_id
        ]);
        $workersWithoutControler = $stmtA->fetchAll(PDO::FETCH_ASSOC); // Only return worker IDs

        // Query for workers with identical discovered_controler_id (B)
        $sqlB = "
            SELECT
                CONCAT(w.firstname, ' ', w.lastname) AS name,
                cke.id,
                cke.discovered_controler_id,
                COALESCE(cke.discovered_controler_name, 'Unknown') AS discovered_controler_name,
                last_discovery_turn,
                (last_discovery_turn - first_discovery_turn) AS discovery_age
            FROM
                controlers_known_enemies cke
            JOIN
                workers AS w ON cke.discovered_worker_id = w.id
            WHERE
                cke.zone_id = :zone_id
                AND cke.controler_id = :controler_id
                AND cke.discovered_controler_id IS NOT NULL
            ORDER BY discovered_controler_name ASC, discovered_controler_id ASC, last_discovery_turn DESC
        ";
        $stmtB = $pdo->prepare($sqlB);
        $stmtB->execute([
            ':zone_id' => $zone_id,
            ':controler_id' => $controler_id
        ]);
        $workersWithControler = $stmtB->fetchAll(PDO::FETCH_ASSOC);

        // Return the combined result
        return [
            'workers_without_controler' => $workersWithoutControler, // A
            'workers_with_controler' => $workersWithControler       // B
        ];

    } catch (PDOException $e) {
        echo __FUNCTION__." (): Error fetching enemy workers: " . $e->getMessage();
        return [];
    }
}

function showEnemyWorkersSelect($pdo, $zone_id, $controler_id) {
    $enemyWorkerOptions = '';

    $enemyWorkersArray = getEnemyWorkers($pdo, $zone_id, $controler_id);

    if ($_SESSION['DEBUG_ATTACK']) {
        echo sprintf("enemyWorkersArray: %s <br/> " , var_export($enemyWorkersArray, true));
    }

    if (empty($enemyWorkersArray['workers_without_controler']) && empty($enemyWorkersArray['workers_with_controler'])) return '';

    if (!empty($enemyWorkersArray['workers_without_controler'])){
        // Display select list of Controlers
        foreach ( $enemyWorkersArray['workers_without_controler'] as $enemyWorker) {
            $enemyWorkerOptions .= "<option value='worker_" . $enemyWorker['id'] . "'>" . $enemyWorker['name'] . " </option>";
        }
    }
    if (!empty($enemyWorkersArray['workers_with_controler'])) {
        $discovered_controler_id = 0;
        // Display select list of Controlers
        foreach ( $enemyWorkersArray['workers_with_controler'] as $enemyWorker) {
            if ( $discovered_controler_id != $enemyWorker['discovered_controler_id']){
                $discovered_controler_id = $enemyWorker['discovered_controler_id'];
                $enemyWorkerOptions .= sprintf(
                    '<option value=\'network_%1$s\'>Réseau %1$s - %2$s</option>',
                    $enemyWorker['discovered_controler_id'],
                    $enemyWorker['discovered_controler_name'],
                );
            }
            $enemyWorkerOptions .= "<option value='worker_".$enemyWorker['id']."'> - ".$enemyWorker['name']." </option>";
        }
    }
    $enemyWorkersSelect = sprintf("
        <select id='enemyWorkersSelect' name='enemy_worker_id[]' multiple>
            %s
        </select>
        ",
        $enemyWorkerOptions
    );
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): enemyWorkersSelect: ".var_export($enemyWorkersSelect, true)."<br /><br />";

    return $enemyWorkersSelect;
}