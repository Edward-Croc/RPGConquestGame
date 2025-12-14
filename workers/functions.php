<?php

define('ACTIVE_ACTIONS', [ 'passive', 'investigate', 'attack', 'claim', 'hide' ]);
define('INACTIVE_ACTIONS', [ 'dead', 'captured', 'trace' ]);

/**
 * Generate a new worker
 * 
 * @param PDO $pdo : database connection
 * @param int $controller_id
 * @param string $buttonClicked
 * 
 * @return array $newWorker
 */
function generateNewWorker($pdo, $controller_id, $buttonClicked) {
    $newWorker = array('controller_id' => $controller_id);

    $generationOrder = getConfig($pdo, 'generation_order');
    if (empty($generationOrder)){
        $generationOrder = 'metier,hobby,origin';
    }
    $generationOrderArray = explode(',', $generationOrder);

    foreach ($generationOrderArray as $generationOrder) {
        if ($generationOrder == 'origin') {
            $newWorker = randomWorkerOrigin($pdo, $newWorker, $buttonClicked);
            $newWorker = randomWorkerName($pdo, $newWorker);
        }
        if ($generationOrder == 'metier') {
            $newWorker = randomPowersByType($pdo, '2', $newWorker);
        }
        if ($generationOrder == 'hobby') {
            $newWorker = randomPowersByType($pdo, '1', $newWorker);
        }
    }

    return $newWorker;
}

/**
 * Update worker action table for a turn and change the action and/or add to the report
 *
 * @param PDO $pdo : database connection
 * @param int $workerId
 * @param int $turnNumber
 * @param string|null $actionChoice
 * @param string|null $reportAppendArray
 * @param array|null $jsonArray
 *
 * @return bool success
 */
function updateWorkerAction($pdo, $workerId, $turnNumber, $actionChoice = null, $reportAppendArray = null, $jsonArray = null) {
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    $query = "UPDATE worker_actions SET ";
    $updates = [];
    $params = ['worker_id' => $workerId, 'turn_number' => $turnNumber];

    if (!empty($actionChoice)) {
        $updates[] = "action_choice = '$actionChoice'";
    }
    if (!empty($jsonArray)) {
        $updates[] = "action_params = :json";
        $params['json'] = json_encode($jsonArray);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo  __FUNCTION__."(): Failed to encode JSON: " . json_last_error_msg()."<br />";
            return false;
        }
    }
    if (!empty($reportAppendArray)) {
        // Step 1: Fetch the existing report
        $stmt = $pdo->prepare("SELECT report FROM worker_actions WHERE worker_id = :worker_id AND turn_number = :turn_number");
        $stmt->bindParam(':worker_id', $workerId, PDO::PARAM_INT);
        $stmt->bindParam(':turn_number', $turnNumber, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo  __FUNCTION__."(): No record found for worker_id $workerId and turn_number $turnNumber.<br />";
            return false;
        }
        // Step 2: Decode the JSON report
        $report = array();
        if (!empty($row['report'])) {
            $report = json_decode($row['report'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(__FUNCTION__."():Failed to decode JSON: " . json_last_error_msg());
            }
        }
        // Step 3: Append the new element to the specified key
        $reportTypes = ['life_report', 'attack_report', 'investigate_report', 'claim_report', 'secrets_report'];
        foreach ($reportTypes as $reportType) {
            if (!empty($reportAppendArray[$reportType])){
                if (empty($report[$reportType])) $report[$reportType]='';
                $report[$reportType] .= $reportAppendArray[$reportType];
            }
        }

        $updates[] = "report = :report";
        $params['report'] = json_encode($report);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo  __FUNCTION__."(): Failed to encode JSON: " . json_last_error_msg()."<br />";
            return false;
        }
    }

    if (count($updates)>0) {
        $query .= implode(", ", $updates) . " WHERE worker_id = :worker_id AND turn_number = :turn_number";
        if ($debug) echo sprintf(" query : %s, Params : %s ", $query, var_export($params, true));

        try{
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        } catch (PDOException $e) {
            echo  __FUNCTION__."(): $query failed: " . $e->getMessage()."<br />";
            return false;
        }
        return true;
    }
    return false;
}

/**
 * Function to get worker and return as an array
 *
 * @param PDO $pdo : database connection
 * @param array $workerIds
 *
 * @return array|null workersArray
 */
function getWorkers($pdo, $workerIds) {

    if ( empty($workerIds) ) return NULL;
    $worker_id_str = implode(',', $workerIds);

    $sql = "SELECT
            w.*,
            wo.name AS origin_name,
            z.name AS zone_name,
            cw.controller_id AS controller_id,
            cw.is_primary_controller,
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
        JOIN controller_worker AS cw ON cw.worker_id = w.id
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
        GROUP BY w.id, wo.name, z.name, cw.is_primary_controller, cw.controller_id
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
        $action_worker_id = $action['worker_id'];
        if (empty($workerActionsById[$action_worker_id][$action['turn_number']])) $workerActionsById[$worker_id][$action['turn_number']] = [];
        $workerActionsById[$action_worker_id][$action['turn_number']] =  $action;
    }

    foreach ($workersArray as $key => $worker) {
        $workersArray[$key]['powers'] = $workerPowersById[$worker['id']] ?? []; // Add powers or empty array if none
        $workersArray[$key]['actions'] = $workerActionsById[$worker['id']] ?? []; // Add actions or empty array if none
    }
    if ($_SESSION['DEBUG'] == true) echo sprintf("workersArray %s <br /> <br />", var_export($workersArray,true));

    return $workersArray;
}

/**
 * Function to get controllers and return as an array
 *
 * @param PDO $pdo : database connection
 * @param int $controller_id
 *
 * @return array|null workersArray
 *
 */
function getWorkersByController($pdo, $controller_id) {

    $sql = " SELECT * FROM controller_worker AS cw
        WHERE cw.controller_id = :controller_id
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':controller_id' => $controller_id]);
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $controller_workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $worker_ids = NULL;
    // Store controllers in the array
    foreach ($controller_workers as $controller_worker) {
        $worker_ids[] = $controller_worker['worker_id'];
    }

    return getWorkers($pdo, $worker_ids);
}

/**
 * Determin current action for worker
 *
 * 
 * @param PDO $pdo : database connection
 * @param int $controller_id
 *
 * @return array|null workersArray
 *
 */
function setWorkerCurrentAction($workerActions, $turncounter) {
    foreach($workerActions as $action) {
        if ( $_SESSION['DEBUG'] == true )
            echo sprintf('workersArray as worker => worker[actions] as action : %s  <br>', var_export($action,true));

        if ( $_SESSION['DEBUG'] == true )
            echo sprintf('action[turn_number] : %s  <br>', var_export($action['turn_number'],true));

        if (isset($action['turn_number']) && (INT)$action['turn_number'] == (INT)$turncounter  ) {
            if ( $_SESSION['DEBUG'] == true ) echo sprintf('Set currentAction : %s  <br>', var_export($action,true));
            return $action;
        }
    }
    if ( $_SESSION['DEBUG'] == true ) echo 'No currentAction found ! <br>';
    return array();
}

/**
 * get Worker satus
 * 
 * @param array $worker : must contain following keys
 * - ['actions'][current_turn_number]['action_choice']
 * - 'is_primary_controller'
 * 
 * @return string 
 */
function getWorkerStatus($worker, $mechanics) {
    $workerStatus = 'unfound';
    // alive: worker alive and active and that we control
    if (
        in_array($worker['actions'][$mechanics['turncounter']]['action_choice'], ACTIVE_ACTIONS)
        && $worker['is_primary_controller']
    ) {
        $workerStatus = 'alive';
    //double_agent : worker alive and active that we don't control
    } else if (
        in_array($worker['actions'][$mechanics['turncounter']]['action_choice'], ACTIVE_ACTIONS)
        && !$worker['is_primary_controller']
    ) {
        $workerStatus = 'double_agent';
    //prisoner : worker alive and not active that we do control are our prisonners
    } else if (
        $worker['actions'][$mechanics['turncounter']]['action_choice'] == 'captured'
        && $worker['is_primary_controller']
    ) {
        $workerStatus = 'prisoner';
    // dead : our dead (worker not alive) or our workers prisonner of others (worker alive and not active that we do not control)
    } else if (
        in_array($worker['actions'][$mechanics['turncounter']]['action_choice'], INACTIVE_ACTIONS)
        && !($worker['actions'][$mechanics['turncounter']]['action_choice'] == 'captured')
    ) {
        $workerStatus = 'dead';
    }
    if ( $_SESSION['DEBUG'] == true )
        echo $workerStatus;
    return $workerStatus;
}

/**
 * show Worker view Short version
 *
 * @param PDO $pdo : database connection
 * @param array $worker : must contain following keys
 *   - 'id'
 *   - 'firstname'
 *   - 'lastname'
 *   - 'zone_name'
 *   - 'total_enquete'
 *   - 'total_attack'
 *   - 'total_defence'
 *   - 'actions'
 * @param array $mechanics : must contain key 'turncounter'
 *
 * @return string
 */
function showWorkerShort($pdo, $worker, $mechanics, $showCheckBox = false) {

    $currentAction = setWorkerCurrentAction($worker['actions'], $mechanics['turncounter']);

    $workerStatus = getWorkerStatus($worker, $mechanics);

    $textActionUpdated = getConfig($pdo,'txt_ps_'.$currentAction['action_choice']);
    // change action text if prisonner or double agent
    if ($workerStatus == 'double_agent' || $workerStatus == 'prisoner') {

        // for double agent get name of infiltrated network
        if ($workerStatus == 'double_agent') {
            $sql = "SELECT cw.controller_id
            FROM controller_worker AS cw
            WHERE cw.worker_id = :worker_id
            AND cw.is_primary_controller = :is_primary_controller
            LIMIT 1";
            //  ORDER BY controller_worker.id
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':worker_id' => $worker['id'],
                ':is_primary_controller' => 1
            ]);
            $controller_id = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $controller_name = getControllerName($pdo, $controller_id[0]);
        }
        // for prisonner get name of original controller
        if ($workerStatus == 'prisoner') {
            $params = json_decode($currentAction['action_params'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "JSON decoding error: " . json_last_error_msg() . "<br />";
            }
            $controller_name = getControllerName($pdo, $params['original_controller_id']);
        }

        $textActionUpdated = sprintf(
            getConfig($pdo,'txt_ps_'.$workerStatus),
            getConfig($pdo,'controllerNameDenominatorOf'),
            $controller_name
        );
    }

    $return = sprintf(
        '<div class="worker-short">
            %8$s
            <a href="/%7$s/workers/action.php?worker_id=%1$s" class="has-text-weight-semibold is-size-5" role="button" style="text-decoration:none;">
                %2$s %3$s
            </a>
            <span>%6$s %5$s %4$s.</span>
        </div>
        ',
        $worker['id'] // %1$s
        , $worker['firstname'] // %2$s
        , $worker['lastname'] // %3$s
        , $worker['zone_name'] // %4$s
        , $textActionUpdated // %5$s
        , sprintf(
            '<i>(<strong>%1$s</strong>, <strong>%2$s</strong>/<strong>%3$s</strong>)</i>',
            $worker['total_enquete'], // %1$s
            $worker['total_attack'], // %2$s
            $worker['total_defence'] // %3$s
        ) // %6$s
        , $_SESSION['FOLDER'] // %7$s
        , ($showCheckBox ? sprintf('<input type="checkbox" name="worker_ids[]" value="%s" class="mr-2">', $worker['id']) : '') // %8$s
    );

    return $return;
}

/**
 * getActionsByWorkers
 *
 * @param PDO $pdo : database connection
 * @param string $worker_id_str
 *
 * @return array|null workerActions
 */
function getActionsByWorkers($pdo, $worker_id_str){
    $sql = "SELECT * FROM worker_actions w
        WHERE worker_id IN ($worker_id_str)
        ORDER BY worker_id ASC, turn_number DESC
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    // Fetch the results
    $workerActions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($_SESSION['DEBUG'] == true) echo sprintf("workerActions: %s <br /> <br />", var_export($workerActions,true));

    return $workerActions;
}

/**
 * Function get a random Origin for Worker
 *
 * @param PDO $pdo : database connection
 * @param array $newWorker
 * @param string|null $originList
 *
 * @return array|null originsArray
 */
function randomWorkerOrigin($pdo, $newWorker, $buttonClicked) {

    $tmpOrigine = getConfig($pdo, $buttonClicked.'_origin_list');
    $originList = null;
    if ( !empty($tmpOrigine) && $tmpOrigine != 'rand' ){
        $originList = $tmpOrigine;
    }
    // TODO : Add locking of origins by controller_id

    // Locking of origins by config on hobbies
    if ( !empty($newWorker['power_1']['other']) ){
        $otherJson = json_decode($newWorker['power_1']['other'], true);
        if ( !empty($otherJson['on_recrutment']['origin_list']) ){
            $originList = $otherJson['on_recrutment']['origin_list'];
        }
    }
    // Locking of origins by config on metiers
    if ( !empty($newWorker['power_2']['other']) ){
        $otherJson = json_decode($newWorker['power_2']['other'], true);
        if ( !empty($otherJson['on_recrutment']['origin_list']) ){
            $originList = $otherJson['on_recrutment']['origin_list'];
        }
    }

    $sqlOriginId = '';
    if ( !empty($originList) ){
        $sqlOriginId .= " WHERE id in ($originList)";
    }

    $randCommand = 'RANDOM()';
    if ($_SESSION['DBTYPE'] == 'mysql')
        $randCommand = 'RAND()';

    try{
        // Get a random value from worker_origins
        $sql = sprintf("SELECT id, name FROM worker_origins %s ORDER BY %s LIMIT 1",
            $sqlOriginId,
            $randCommand
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $workerOrigins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store worker_origins in the array
    $newWorker['origin_id'] = $workerOrigins[0]['id'];
    $newWorker['origin'] = $workerOrigins[0]['name'];
    if ($_SESSION['DEBUG'] == true) 
        echo sprintf("newWorker: %s <br /> <br />", var_export($newWorker,true));

    return $newWorker;
}

/**
 * Function get random Name for Worker for originList
 *
 * @param PDO $pdo : database connection
 * @param array $newWorker
 *
 * @return array|null nameArray
 *
 */
function randomWorkerName($pdo, $newWorker) {

    // Get 2 random values from worker_names for and origin ID
    $randCommand = 'RANDOM()';
    if ($_SESSION['DBTYPE'] == 'mysql')
        $randCommand = 'RAND()';

    $sql = sprintf("SELECT * FROM worker_names
        JOIN worker_origins ON worker_origins.id = worker_names.origin_id
        WHERE origin_id = %d
        ORDER BY %s
        LIMIT 2",
        $newWorker['origin_id'],
        $randCommand
    );
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
    $newWorker['firstname'] = $worker_names[0]['firstname'];
    $newWorker['lastname'] = $worker_names[1]['lastname'];
    return $newWorker;
}

/**
 * Function to create worker and assing the controler
 *
 * @param PDO $pdo : database connection
 * @param array $array : $GET should contain :
 *     "creation"="true"
 *     firstname
 *     lastname
 *     origin
 *     power_hobby
 *     power_metier
 *     origin_id
 *     power_hobby_id
 *     power_metier_id
 *     zone
 *     discipline
 *     transformation
 *     controller_id
 *
 * @return string workerId
 */
function createWorker($pdo, $array) {
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    // If a necessary element of data is missing
    if (
        empty($array['firstname'])
        || empty($array['lastname'])
        || empty($array['origin_id'])
        || empty($array['controller_id'])
        || empty($array['zone_id'])
    ) {
        if ($debug) echo sprintf( "%s() => Unfound necessary element <br>",  __FUNCTION__ );
        return false;
    }

    // Check if worker already exists :
    try{
        // Select worker value from the database
        $stmt = $pdo->prepare("SELECT w.id AS id FROM workers AS w
        INNER JOIN controller_worker AS cw ON cw.worker_id = w.id
        WHERE w.firstname = :firstname AND w.lastname = :lastname AND w.origin_id = :origin_id AND cw.controller_id = :controller_id");
        $stmt->bindParam(':firstname', $array['firstname'], PDO::PARAM_STR);
        $stmt->bindParam(':lastname', $array['lastname'], PDO::PARAM_STR);
        $stmt->bindParam(':origin_id', $array['origin_id'], PDO::PARAM_INT);
        $stmt->bindParam(':controller_id', $array['controller_id'], PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT workers Failed: " . $e->getMessage()."<br />";
        return false;
    }
    $worker = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // If worker exist return worker ID
    if (!empty($worker)) return $worker[0]['id'];

    try{
        // Insert new workers value into the database
        $stmt = $pdo->prepare("INSERT INTO workers (firstname, lastname, origin_id, zone_id) VALUES (:firstname, :lastname, :origin_id, :zone_id)");
        $stmt->bindParam(':firstname', $array['firstname'], PDO::PARAM_STR);
        $stmt->bindParam(':lastname', $array['lastname'], PDO::PARAM_STR);
        $stmt->bindParam(':origin_id', $array['origin_id'], PDO::PARAM_INT);
        $stmt->bindParam(':zone_id', $array['zone_id'], PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT workers Failed: " . $e->getMessage()."<br />";
        return false;
    }
    // Get the last inserted ID
    $workerId = $pdo->lastInsertId();

    // Add the basic worker action line
    $newControllerName = getControllerName($pdo, $array['controller_id']);
    $zone_name = getZoneName($pdo, $array['zone_id']);
    $life_report = sprintf("J'ai été recruté par %s<strong>%s</strong> et envoyé en mission dans le %s <strong>%s</strong>.<br/>", getConfig($pdo, 'controllerNameDenominatorThe'), $newControllerName, getConfig($pdo, 'textForZoneType'), $zone_name );
    $reportArray = array('life_report' => $life_report);
    addWorkerAction($pdo, $workerId, $array['controller_id'], $array['zone_id'], $reportArray);

    try{
        // Insert new controller_worker value into the database
        $stmt = $pdo->prepare("INSERT INTO controller_worker (controller_id, worker_id) VALUES (:controller_id, :worker_id)");
        $stmt->bindParam(':controller_id', $array['controller_id'], PDO::PARAM_INT);
        $stmt->bindParam(':worker_id', $workerId, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT controller_worker Failed: " . $e->getMessage()."<br />";
    }

    // Add powers to worker
    $link_power_type_id_array = [];
    if (!empty($array['power_hobby_id'])) $link_power_type_id_array[] = $array['power_hobby_id'];
    if (!empty($array['power_metier_id'])) $link_power_type_id_array[] = $array['power_metier_id'];
    if (!empty($array['discipline']) && $array['discipline'] != "\'\'" ) $link_power_type_id_array[] = $array['discipline'];
    if (!empty($array['transformation']) && $array['transformation'] != "\'\'" ) $link_power_type_id_array[] = $array['transformation'];
    foreach($link_power_type_id_array as $link_power_type_id ) {
        if ($debug) echo sprintf("%s() => add to worker : %s, link_power_type_id: %s <br>",  __FUNCTION__, $workerId, var_export($link_power_type_id, true));
        upgradeWorker($pdo, $workerId, $link_power_type_id, true);
    }

    try{
        // increment recrutment values
        $sqlUpdateRecrutementCounter = 'UPDATE controllers SET recruited_workers = recruited_workers +1 WHERE id = :controller_id';
        $stmtUpdateRecrutementCounter = $pdo->prepare($sqlUpdateRecrutementCounter);
        $stmtUpdateRecrutementCounter->execute([
            ':controller_id' => $array['controller_id']
        ]);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE controllers SET recruited_workers  Failed: " . $e->getMessage()."<br />";
    }

    return $workerId;
}

/**
 * Function to update Worker with a power
 *
 * @param PDO $pdo : database connection
 * @param int $workerId
 * @param int $link_power_type_id
 * @param bool $isRecrutment
 *
 * @return bool success
 */
function upgradeWorker($pdo, $workerId, $link_power_type_id, $isRecrutment = false){
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    try{
        // Insert new worker_powers value into the database
        $stmt = $pdo->prepare("INSERT INTO worker_powers (worker_id, link_power_type_id) VALUES (:worker_id, :link_power_type_id)");
        $stmt->bindParam(':link_power_type_id', $link_power_type_id, PDO::PARAM_INT);
        $stmt->bindParam(':worker_id', $workerId, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT worker_powers Failed: " . $e->getMessage()."<br />";
        return false;
    }

    // Check if the power has an effect on obtention
    try {
        $sql = "
            SELECT p.other
            FROM powers p
            JOIN link_power_type lpt ON lpt.power_id = p.id
            WHERE lpt.id = :link_power_type_id
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':link_power_type_id' => $link_power_type_id]);
        $power = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($debug) echo sprintf("%s() => power other ? %s ",  __FUNCTION__, var_export($power, true));

        if (!empty($power['other'])) {
            $otherJson = json_decode($power['other'], true);
            if ($debug) echo __FUNCTION__ . "(): otherJson: " . var_export($otherJson, true) . "<br />";

            if (json_last_error() === JSON_ERROR_NONE && is_array($otherJson)) {
                // Apply effect if found
                applyPowerObtentionEffect($pdo, $workerId, $otherJson, $isRecrutment);
            }
        }
    } catch (PDOException $e) {
        echo __FUNCTION__ . "(): Failed to fetch power effect: " . $e->getMessage() . "<br />";
        return false;
    }

    return true;
}

/**
 *
 * @param PDO $pdo : database connection
 * @param int $workerId
 * @param array $otherJson
 * @param bool $isRecrutment
 *
 * @return bool success
 */
function applyPowerObtentionEffect($pdo, $workerId, $otherJson, $isRecrutment = false) {
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    // If it is a recrutment effect
    if ($isRecrutment && !empty($otherJson['on_recrutment']) && is_array($otherJson['on_recrutment']) ){
        foreach ($otherJson['on_recrutment'] AS $key => $element ){
            if ($debug) echo sprintf( "%s():key : %s, element: %s<br />",__FUNCTION__, $key, var_export($element, true));
            // If it is an action and we have a type
            if (
                $key == 'action'
                && !empty($element)
                && !empty($element['type'])
            ) {
                // go_traitor add the listed controler as a non primary controler
                if ( $element['type'] == 'go_traitor' && !empty($element['controller_lastname']) ){
                    try {
                        // Add non primary controller for the worker
                        $sql = "INSERT INTO controller_worker (controller_id, worker_id, is_primary_controller)
                                VALUES ( (SELECT id FROM controllers WHERE lastname = :lastname), :worker_id, False)";
                        if ($debug) echo __FUNCTION__ . "(): sql: " . var_export($sql, true) . "<br />";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':lastname' => $element['controller_lastname'], ':worker_id' => $workerId]);
                    } catch (PDOException $e) {
                        echo __FUNCTION__."(): go_traitor => INSERT controller_worker Failed: " . $e->getMessage()."<br />";
                    }
                }
                if ( $element['type'] == 'add_opposition' ){
                    // $element['controller_lastname']
                    // TODO
                    // Create worker with hobby and job in a random zone
                    // Add $workerId to CKE
                }
            }
        }
    }
    // TODO : If the effect can be obtained out of recrutment

 return true;
}

/**
 * Function add Action to Worker_action table for worker
 *
 * @param PDO $pdo : database connection
 * @param int $workerId
 * @param int $controllerId
 * @param int $zoneId
 *
 * @return int|null lastInsertId
 */
function addWorkerAction($pdo, $workerId, $controllerId, $zoneId, $reportArray = null){
    // Get turn nubmer
    $mechanics = getMechanics($pdo);

    $hasReport = false;

    $sql = "INSERT
            INTO worker_actions (worker_id, turn_number, zone_id, controller_id)
            VALUES (:worker_id, :turn_number, :zone_id, :controller_id)";
    if (!empty($reportArray)) {
        // Encode the report
        $reportJson = json_encode($reportArray);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "JSON encoding error: " . json_last_error_msg() . "<br />";
        }else{
            $sql = "INSERT
                INTO worker_actions (worker_id, turn_number, zone_id, controller_id, report)
                VALUES (:worker_id, :turn_number, :zone_id, :controller_id, :report)";
            $hasReport = true;
        }
    }

    if (!empty($_SESSION['debug'])) echo __METHOD__."() : insertsqL $sql <br/>";

    try{
        // Insert new controller_worker value into the database
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':controller_id', $controllerId, PDO::PARAM_INT);
        $stmt->bindParam(':worker_id', $workerId, PDO::PARAM_INT);
        $stmt->bindParam(':zone_id', $zoneId, PDO::PARAM_INT);
        $stmt->bindParam(':turn_number', $mechanics['turncounter'], PDO::PARAM_INT);
        if (!empty($hasReport)) $stmt->bindParam(':report', $reportJson);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT controller_worker Failed: " . $e->getMessage()."<br />";
        return null;
    }
    // Get the last inserted ID
    return $pdo->lastInsertId();
}

/**
 * Function to count disciplines of worker
 *
 * @param PDO $pdo : database connection
 * @param array $workerIds
 *
 * @return array {int worker_id, int discipline_count}
 */
function countWorkerDisciplines($pdo, $workerIds = NULL) {
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
            empty($workerIds) ? "" : sprintf(" AND wp.worker_id IN (%s) ", implode(',', $workerIds))
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__." (): Error counting worker disciplines: " . $e->getMessage();
        return [];
    }
}

/**
 * Function to assing worker to new zone
 *
 * @param PDO $pdo : database connection
 * @param int $workerId
 * @param int $zoneId
 *
 * @return int|null :
 */
function moveWorker($pdo, $workerId, $zoneId) {
    $debug = $_SESSION['DEBUG'];
    if ($debug) echo __FUNCTION__."(): Step 1 UPDATE workers <br/>";
    try{
        // UPDATE workers value
        $stmt = $pdo->prepare("UPDATE workers SET zone_id = :zone_id WHERE id = :id ");
        $stmt->bindParam(':id', $workerId, PDO::PARAM_INT);
        $stmt->bindParam(':zone_id', $zoneId, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE workers Failed: " . $e->getMessage()."<br />";
        return null;
    }

    if ($debug) echo __FUNCTION__."(): Step 2 UPDATE worker_actions <br/>";
    $actions = getWorkerActions($pdo, $workerId);

    $mechanics = getMechanics($pdo);
    $zone_name = getZoneName($pdo, $zoneId);
    updateWorkerAction($pdo, $workerId, $mechanics['turncounter'], 'passive', ['life_report' => "J'ai déménagé vers <strong>$zone_name</strong>. <br/>"], json_encode([]));

    try{
        // UPDATE worker_actions values
        $stmt = $pdo->prepare("UPDATE worker_actions SET zone_id = :zone_id WHERE id = :id ");
        $stmt->bindParam(':zone_id', $zoneId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $actions[0]['id'], PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE workers Failed: " . $e->getMessage()."<br />";
    }
    if ($debug) echo __FUNCTION__."(): DONE <br/>"; 

    return $workerId;
}

/**
 * get worker action status for turn
 * 
 * @param PDO $pdo : database connection
 * @param int $workerId
 * @param int|null $turn_number
 * 
 */
function getWorkerActions($pdo, $workerId, $turn_number = null ){

    if (empty($turn_number)) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];
    }

    $sql = "SELECT * FROM worker_actions
        WHERE worker_id = $workerId
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
    $workerActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): workerActions: ".var_export($workerActions, true)."<br/><br/>";
    return $workerActions;
}

/**
 * Activates workers depending opn give action
 * 
 * @param PDO $pdo : database connection
 * @param int $workerId
 * @param string $action
 * @param int|array|null $extraVal
 * 
 * @return int $workerId
 */
function activateWorker($pdo, $workerId, $action, $extraVal = NULL) {

    $mechanics = getMechanics($pdo);
    $turn_number = $mechanics['turncounter'];

    // get worker action status for turn
    $worker_actions = getWorkerActions($pdo, $workerId);
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): worker_action: ".var_export($worker_actions, true)."<br/><br/>";
    $currentAction = $worker_actions[0];

    // Decode the existing JSON into an associative array
    $currentReport = array();
    if (!empty($currentAction['report'])) {
        $currentReport = json_decode($currentAction['report'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo __FUNCTION__."():JSON decoding error: " . json_last_error_msg() . "<br />";
        }
    }
    $sql_worker_actions = "UPDATE worker_actions SET ";
    if ($_SESSION['DEBUG'] == true) echo sprintf("%s(): activate : %s <br/>", __FUNCTION__, $action);
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
        case 'claim' :
            if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): claim <br/><br/>";
            // Create JSON table
            $jsonOutput = json_encode([
                'claim_controller_id' => $extraVal
            ]);
            break;
        case 'gift' :
            if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): gift <br/><br/>";

            // get new controller name
            $newControllerName = getControllerName($pdo, $extraVal);
            // Build report
            $report_element = sprintf("J'ai <strong>rejoint %s%s</strong> comme nouveau maitre.<br />", getConfig($pdo, 'controllerNameDenominatorThe'), $newControllerName);
            // update action and report
            updateWorkerAction($pdo, $workerId,  $turn_number, 'passive', ['life_report' => $report_element]);

            // Create Trace
            if (createTraceWorker($pdo, $workerId, $currentAction['controller_id']) === false) {
                echo __FUNCTION__." (): Failed to create trace worker ! <br />";
            }
            // Check for existing trace
            if (destroyTraceWorker($pdo, $workerId, $extraVal) === false) {
                echo __FUNCTION__." (): Failed to destroy trace worker ! <br />";
            }

            // Check that the entry controller_id:extraVal and worker_id:workerId is not already in the controller_worker table and delete it if it is
            try {
                $sqlcontrollerWorker = "DELETE FROM controller_worker WHERE worker_id = :worker_id AND controller_id = :extraVal";
                $stmtcontrollerWorker = $pdo->prepare($sqlcontrollerWorker);
                $stmtcontrollerWorker->execute([
                    ':extraVal' => $extraVal,
                    ':worker_id' => $workerId
                ]);
            } catch (PDOException $e) {
               // echo __FUNCTION__." (): Failed to delete entry from controller_worker table: " . $e->getMessage() . "<br />";
            }
            // Set controller_worker controller_id and set worker_actions controller_id where turn_numer = current_turn to $extraVal
            try {
                // Update the controller_worker table
                $sqlcontrollerWorker = "UPDATE controller_worker SET controller_id = :extraVal WHERE worker_id = :worker_id AND is_primary_controller = " . ($_SESSION['DBTYPE'] == 'postgres' ? 'true' : '1') . "";
                $stmtcontrollerWorker = $pdo->prepare($sqlcontrollerWorker);
                $stmtcontrollerWorker->execute([
                    ':extraVal' => $extraVal,
                    ':worker_id' => $workerId
                ]);
                // Update the worker_actions table
                $sqlWorkerActions = "UPDATE worker_actions SET controller_id = :extraVal , action_params = '$jsonOutput'
                    WHERE worker_id = :worker_id AND turn_number = :turn_number";
                $stmtWorkerActions = $pdo->prepare($sqlWorkerActions);
                $stmtWorkerActions->execute([
                    ':extraVal' => $extraVal,
                    ':worker_id' => $workerId,
                    ':turn_number' => $turn_number
                ]);
            } catch (PDOException $e) {
                echo __FUNCTION__." (): Failed to update tables: " . $e->getMessage() . "<br />";
            }
            return $workerId;
            break;
        case 'recallDoubleAgent' :
            if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): recallDoubleAgent <br/><br/>";

            if (!createTraceWorker($pdo, $workerId, $currentAction['controller_id'])) {
                echo __FUNCTION__." (): Failed to create trace worker ! <br />";
            }

            try {
                $new_action = 'passive';
                if (empty($currentReport['life_report'])) $currentReport['life_report'] ='';
                $currentReport['life_report'] .= sprintf(
                        "J'ai été <strong>rappelé</strong> auprès de mon véritable maitre <strong>%s%s</strong>.<br />",
                        getConfig($pdo, 'controllerNameDenominatorThe'),
                        getControllerName($pdo, $extraVal)
                );

                // Update the controller_worker table
                $sqlDropControllerWorker = sprintf(
                    "DELETE FROM controller_worker WHERE worker_id = :worker_id AND is_primary_controller = %s AND NOT controller_id = :extraVal",
                    $_SESSION['DBTYPE'] == 'postgres' ? 'true' : '1'
                );
                $stmtDropControllerWorker = $pdo->prepare($sqlDropControllerWorker);
                $stmtDropControllerWorker->execute([
                    ':worker_id' => $workerId,
                    ':extraVal' => $extraVal
                ]);
                $sqlUpdatecontrollerWorker = sprintf("UPDATE controller_worker SET is_primary_controller = %s WHERE worker_id = :worker_id AND controller_id = :extraVal",
                    ($_SESSION['DBTYPE'] == 'postgres') ? 'true' : '1'
                );
                $stmtUpdateControllerWorker = $pdo->prepare($sqlUpdatecontrollerWorker);
                $stmtUpdateControllerWorker->execute([
                    ':extraVal' => $extraVal,
                    ':worker_id' => $workerId
                ]);

                // Update the worker_actions table
                $sqlWorkerActions = "UPDATE worker_actions SET controller_id = :extraVal
                    WHERE worker_id = :worker_id AND turn_number = :turn_number";
                $stmtWorkerActions = $pdo->prepare($sqlWorkerActions);
                $stmtWorkerActions->execute([
                    ':extraVal' => $extraVal,
                    ':worker_id' => $workerId,
                    ':turn_number' => $turn_number
                ]);
            } catch (PDOException $e) {
                echo __FUNCTION__." (): Failed to update tables: " . $e->getMessage() . "<br />";
            }

            break;
        case 'returnPrisoner' :
            if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): returnPrisoner <br/><br/>";
            if (empty($currentReport['life_report'])) $currentReport['life_report'] ='';
            $currentReport['life_report'] .= sprintf(
                "J'ai été <strong>relacher</strong> par le <strong>réseau %s</strong> vers %s.<br />",
                $extraVal['recall_controller_id'],
                getControllerName($pdo, $extraVal['return_controller_id'])                
            );

            if (createTraceWorker($pdo, $workerId, $extraVal['recall_controller_id']) === false) {
                echo __FUNCTION__." (): Failed to create trace worker ! <br />";
            }
            if (destroyTraceWorker($pdo, $workerId, $extraVal['return_controller_id']) === false) {
                echo __FUNCTION__." (): Failed to destroy trace worker ! <br />";
            }
            try {
                // Update the controller_worker table
                $sqlcontrollerWorker = sprintf(
                    "UPDATE controller_worker SET controller_id = :return_controller_id WHERE worker_id = :worker_id AND controller_id = :extraVal AND is_primary_controller = %s",
                    ($_SESSION['DBTYPE'] == 'postgres') ? 'true' : '1'
                );
                $stmtcontrollerWorker = $pdo->prepare($sqlcontrollerWorker);
                $stmtcontrollerWorker->execute([
                    ':return_controller_id' => $extraVal['return_controller_id'],
                    ':extraVal' => $extraVal['recall_controller_id'],
                    ':worker_id' => $workerId
                ]);

                // Update the worker_actions table
                $sqlWorkerActions = "UPDATE worker_actions SET controller_id = :extraVal
                    WHERE worker_id = :worker_id AND turn_number = :turn_number";
                $stmtWorkerActions = $pdo->prepare($sqlWorkerActions);
                $stmtWorkerActions->execute([
                    ':extraVal' => $extraVal['return_controller_id'],
                    ':worker_id' => $workerId,
                    ':turn_number' => $turn_number
                ]);
            } catch (PDOException $e) {
                echo __FUNCTION__." (): Failed to update tables: " . $e->getMessage() . "<br />";
            }

            $new_action = 'passive';
            break;
    }

    $sql_worker_actions .= " action_choice = '$new_action' ";
    $sql_worker_actions .= ", action_params = '$jsonOutput'";
    // Encode the updated array back into JSON
    $updatedReportJson = json_encode($currentReport);
    if (json_last_error() === JSON_ERROR_NONE) {
        $sql_worker_actions .= ", report = :report ";
    } else { echo "JSON encoding error: " . json_last_error_msg() . "<br />"; }
    try{
        $sql_worker_actions .= " WHERE id = :id AND turn_number = :turn_number ";
        if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): sql_worker_actions : ".var_export($sql_worker_actions, true)." <br/><br/>";
        // Insert new workers value into the database
        $stmt = $pdo->prepare($sql_worker_actions);
        $stmt->bindParam(':id', $worker_actions[0]['id'], PDO::PARAM_INT);
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmt->bindParam(':report', $updatedReportJson, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE workers Failed: " . $e->getMessage()."<br />";
    }
    return $workerId;
}

/**
 * Get the know enemy workers for the controller and the zone
 * 
 * @param PDO $pdo : database connection
 * @param int $zone_id
 * @param int|null $controller_id
 * 
 * @return array (
 *   -'workers_without_controller'
 *   -'workers_with_controller'
 * 
 */
function getEnemyWorkers($pdo, $zone_id, $controller_id = NULL) {
    // Select from controllers_known_enemies by $zone_id, $controller_id
        // return table of :
        // A worker discovered_worker_id with no discovered_controller_id
        // B workers discovered_worker_id with identical discovered_controller_id
            // Optional discovered_controller_name if is associated to a
    if ($_SESSION['DEBUG_ATTACK'] == true) {
        echo sprintf("zone_id: %s <br/> " , var_export($zone_id, true));
        echo sprintf("controller_id: %s <br/> " , var_export($controller_id, true));
    }
    try {
        // Query for workers with no discovered_controller_id (A)
        $sqlA = sprintf("
            SELECT
                cke.id,
                cke.discovered_worker_id,
                CONCAT(w.firstname, ' ', w.lastname) AS name,
                last_discovery_turn,
                (last_discovery_turn - first_discovery_turn) AS discovery_age
            FROM
                controllers_known_enemies cke
            JOIN
                workers AS w ON cke.discovered_worker_id = w.id
            WHERE
                cke.zone_id = :zone_id
                %s
                AND cke.discovered_controller_id IS NULL
            ORDER BY last_discovery_turn DESC
        ", (!empty($controller_id)) ? "AND cke.controller_id = :controller_id" : ""
        );
        $stmtA = $pdo->prepare($sqlA);
        if (!empty($controller_id)) $stmtA->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
        $stmtA->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
        $stmtA->execute();
        $workersWithoutController = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        // Query for workers with identical discovered_controller_id (B)
        $sqlB = sprintf("
            SELECT
                cke.id,
                cke.discovered_worker_id,
                CONCAT(w.firstname, ' ', w.lastname) AS name,
                cke.discovered_controller_id,
                COALESCE(cke.discovered_controller_name, 'Unknown') AS discovered_controller_name,
                last_discovery_turn,
                (last_discovery_turn - first_discovery_turn) AS discovery_age
            FROM
                controllers_known_enemies cke
            JOIN
                workers AS w ON cke.discovered_worker_id = w.id
            WHERE
                cke.zone_id = :zone_id
                %s
                AND cke.discovered_controller_id IS NOT NULL
            ORDER BY discovered_controller_name ASC, discovered_controller_id ASC, last_discovery_turn DESC
        ", (!empty($controller_id)) ? "AND cke.controller_id = :controller_id" : ""
        );
        $stmtB = $pdo->prepare($sqlB);
        if (!empty($controller_id)) $stmtB->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
        $stmtB->bindParam(':zone_id', $zone_id, PDO::PARAM_INT);
        $stmtB->execute();
        $workersWithController = $stmtB->fetchAll(PDO::FETCH_ASSOC);

        // Return the combined result
        return [
            'workers_without_controller' => $workersWithoutController, // A
            'workers_with_controller' => $workersWithController       // B
        ];

    } catch (PDOException $e) {
        echo __FUNCTION__." (): Error fetching enemy workers: " . $e->getMessage();
        return [];
    }
}

/**
 * Build the HTML select for the known enemy workers for the controller in the zone
 * 
 * @param PDO $pdo : database connection
 * @param int $zone_id
 * @param int $controller_id
 * @param int|null $turn_number
 * 
 * @return string 
 */
function showEnemyWorkersSelect($pdo, $zone_id, $controller_id, $turn_number = NULL) {
    $enemyWorkerOptions = '';
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    if (empty($turn_number)) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];
    }
    if ($debug) echo "turn_number : $turn_number <br>";

    $enemyWorkersArray = getEnemyWorkers($pdo, $zone_id, $controller_id);

    if ($_SESSION['DEBUG_ATTACK']) {
        echo sprintf("enemyWorkersArray: %s <br/> " , var_export($enemyWorkersArray, true));
    }

    if (empty($enemyWorkersArray['workers_without_controller']) && empty($enemyWorkersArray['workers_with_controller'])) return '';

    // Prepare config val
    $attackTimeWindow = getConfig($pdo, 'attackTimeWindow');
    if (empty($attackTimeWindow)) $attackTimeWindow = $turn_number;
    $canAttackNetwork = getConfig($pdo, 'canAttackNetwork');
    if (empty($attackTimeWindow)) $attackTimeWindow = 0;

    if (!empty($enemyWorkersArray['workers_without_controller'])){
        // Display select list of workers
        foreach ( $enemyWorkersArray['workers_without_controller'] as $enemyWorker) {
            if (!isset($enemyWorker['last_discovery_turn'])) continue;
            if ($enemyWorker['last_discovery_turn'] >= ($turn_number - $attackTimeWindow))
                $enemyWorkerOptions .= sprintf('<option value="worker_%1$s"> %2$s </option>', $enemyWorker['discovered_worker_id'],  $enemyWorker['name']);
        }
    }
    if (!empty($enemyWorkersArray['workers_with_controller'])) {
        $discovered_controller_id = 0;
        // Display select list of controllers
        foreach ( $enemyWorkersArray['workers_with_controller'] as $enemyWorker) {
            if (!isset($enemyWorker['last_discovery_turn'])) continue;
            if ($enemyWorker['last_discovery_turn'] >= ($turn_number - $attackTimeWindow)) {

                if ( $discovered_controller_id != $enemyWorker['discovered_controller_id'] && $canAttackNetwork > 0){
                    $discovered_controller_id = $enemyWorker['discovered_controller_id'];
                    $enemyWorkerOptions .= sprintf(
                        '<option value=\'network_%1$s\'>Réseau %1$s - %2$s :</option>',
                        $enemyWorker['discovered_controller_id'],
                        $enemyWorker['discovered_controller_name'],
                    );
                }
                $enemyWorkerOptions .= sprintf('<option value="worker_%1$s">- %2$s </option>', $enemyWorker['discovered_worker_id'],  $enemyWorker['name']);
            }
        }
    }
    $enemyWorkersSelect = sprintf("
        <div class='control for-select'>
            <div class='select is-multiple'>
                <select id='enemyWorkersSelect' name='enemy_worker_id[]' multiple>
                    %s
                </select>
            </div>
        </div>
        ",
        $enemyWorkerOptions
    );
    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): enemyWorkersSelect: ".var_export($enemyWorkersSelect, true)."<br /><br />";

    return $enemyWorkersSelect;
}

/**
 * Create a copy of the worker and worker_actions tables to the active controller as primary controller
 *  the new worker should be alive and active and the new worker_actions should be empty
 *  if the active controller has a previous copy of the woker it must be destroyed first
 * 
 * @param PDO $pdo : database connection
 * @param int $worker_id
 * @param int $controller_id
 * 
 * @return $new_worker_id
 *
 */ 
function createTraceWorker($pdo, $worker_id, $controller_id) {

    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    if ($debug) echo __FUNCTION__."(): createTraceWorker <br/><br/>";
    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Step 1 : Copy the worker table except id
        $query = "
            INSERT INTO workers (firstname, lastname, origin_id, zone_id) 
            SELECT firstname, lastname, origin_id, zone_id
            FROM workers WHERE id = :worker_id
        ";
        if ($debug) echo sprintf("%s(): Step 1 : %s <br/><br/>",  __FUNCTION__, $query);
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':worker_id', $worker_id, PDO::PARAM_INT);
        $stmt->execute();
        $new_worker_id = $pdo->lastInsertId();
        if ($debug) echo sprintf("%s(): Step 1 : new_worker_id = %s <br/><br/>",  __FUNCTION__, $new_worker_id);

        // Step 2 : Copy the worker_actions table except id, worker_id = new worker_id and action_choice = 'trace'
        $query = "
            INSERT INTO worker_actions (worker_id, turn_number, zone_id, controller_id, enquete_val, attack_val, defence_val, action_choice, action_params, report)
            SELECT :new_worker_id, turn_number, zone_id, controller_id, enquete_val, attack_val, defence_val, 'trace', action_params, report 
            FROM worker_actions WHERE worker_id = :worker_id
        ";
        if ($debug) echo sprintf("%s(): Step 2 : %s <br/><br/>",  __FUNCTION__, $query);
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':new_worker_id', $new_worker_id, PDO::PARAM_INT);
        $stmt->bindParam(':worker_id', $worker_id, PDO::PARAM_INT);
        $stmt->execute();

        // Step 3 : Copy the worker_powers
        $query = "
            INSERT INTO worker_powers (worker_id, link_power_type_id)
            SELECT :new_worker_id, link_power_type_id
            FROM worker_powers WHERE worker_id = :worker_id
        ";
        if ($debug) echo sprintf("%s(): Step 3 : %s <br/><br/>",  __FUNCTION__, $query);
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':new_worker_id', $new_worker_id, PDO::PARAM_INT);
        $stmt->bindParam(':worker_id', $worker_id, PDO::PARAM_INT);
        $stmt->execute();

        // Step 4 : Copy the add new worker to the controller_worker table as primary controller
        $query = "
            INSERT INTO controller_worker (controller_id, worker_id, is_primary_controller)
            SELECT :controller_id, :new_worker_id, true
        ";
        if ($debug) echo sprintf("%s(): Step 4 : %s <br/><br/>",  __FUNCTION__, $query);
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
        $stmt->bindParam(':new_worker_id', $new_worker_id, PDO::PARAM_INT);
        $stmt->execute();

        // Step 5: Add a new entry to the workers_trace_links table with the old worker_id as primary_worker_id and the new worker_id as trace_worker_id and the controller_id
        $query = "
            INSERT INTO workers_trace_links (primary_worker_id, trace_worker_id, controller_id)
            SELECT  :worker_id, :new_worker_id, :controller_id
        ";
        if ($debug) echo sprintf("%s(): Step 5 : %s <br/><br/>",  __FUNCTION__, $query);
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':worker_id', $worker_id, PDO::PARAM_INT);
        $stmt->bindParam(':new_worker_id', $new_worker_id, PDO::PARAM_INT);
        $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
        $stmt->execute();

        // Step 6 : Commit the transaction and return the new worker_id
        $pdo->commit();
    } catch (Exception $e) {
        // Rollback transaction on error/exception
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo __FUNCTION__." (): Error creating trace worker: " . $e->getMessage();
        echo  __FUNCTION__."(): Transaction failed: " . $e->getMessage()."<br />";
        return false;
    }
    return $new_worker_id;
}


/**
 * Destroy the trace of the worker, the worker_actions tables, the links to the controller as primary controller
 *  the worker links to his powers
 * 
 * @param PDO $pdo : database connection
 * @param int $worker_id
 * @param int $controller_id
 * 
 * @return $success
 *
 */ 
function destroyTraceWorker($pdo, $worker_id, $controller_id) {

    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';

    if ($debug) echo __FUNCTION__."(): destroyTraceWorker <br/><br/>";

    // Step 1 : find the trace_worker_id in the workers_trace_links table
    $query = "
        SELECT trace_worker_id FROM workers_trace_links WHERE primary_worker_id = :worker_id AND controller_id = :controller_id
    ";
    if ($debug) echo sprintf("%s(): Step 1 : %s <br/><br/>",  __FUNCTION__, $query);
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':worker_id', $worker_id, PDO::PARAM_INT);
    $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
    $stmt->execute();
    $traceWorkers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($debug) echo sprintf("%s(): Step 1 : traceWorkers: %s <br/><br/>",  __FUNCTION__, var_export($traceWorkers, true));
    if (empty($traceWorkers)) {
        echo __FUNCTION__." (): No trace worker found for the primary worker_id: " . $worker_id . " and controller_id: " . $controller_id;
    }
    if  ($traceWorkers !== false && !empty($traceWorkers)) {
        foreach ($traceWorkers as $traceWorker) {
            try {
                $trace_worker_id = $traceWorker['trace_worker_id'];
                if ($debug) echo sprintf("%s(): Step 2 : trace_worker_id: %s <br/><br/>",  __FUNCTION__, $trace_worker_id);
                // Begin transaction
                $pdo->beginTransaction();

                // Step 2 : Delete the trace worker from the controller_worker table as primary controller
                $query = "
                    DELETE FROM controller_worker WHERE worker_id = :trace_worker_id AND controller_id = :controller_id AND is_primary_controller = true
                ";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':trace_worker_id', $trace_worker_id, PDO::PARAM_INT);
                $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
                $stmt->execute();

                // Step 3 : Delete the worker_powers for the trace worker
                $query = "
                    DELETE FROM worker_powers WHERE worker_id = :trace_worker_id
                ";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':trace_worker_id', $trace_worker_id, PDO::PARAM_INT);
                $stmt->execute();

                // Step 4 : Delete the worker_actions for the trace worker where action_choice = 'trace'
                $query = "
                    DELETE FROM worker_actions WHERE worker_id = :trace_worker_id AND action_choice = 'trace'
                ";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':trace_worker_id', $trace_worker_id, PDO::PARAM_INT);
                $stmt->execute();

                // Step 5 : Delete the controllers_known_enemies for the trace worker (this should not exist
                // done by caution
                $query = "
                    DELETE FROM controllers_known_enemies WHERE discovered_worker_id = :trace_worker_id
                ";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':trace_worker_id', $trace_worker_id, PDO::PARAM_INT);
                $stmt->execute();

                // Step 6 : Delete the entry from the workers_trace_links table
                $query = "
                    DELETE FROM workers_trace_links
                        WHERE primary_worker_id = :worker_id
                        AND trace_worker_id = :trace_worker_id
                        AND controller_id = :controller_id
                ";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':worker_id', $worker_id, PDO::PARAM_INT);
                $stmt->bindParam(':trace_worker_id', $trace_worker_id, PDO::PARAM_INT);
                $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
                $stmt->execute();

                // Step 5 : Delete the trace worker from the workers
                $query = "
                    DELETE FROM workers WHERE id = :trace_worker_id
                ";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':trace_worker_id', $trace_worker_id, PDO::PARAM_INT);
                $stmt->execute();

                // Step 7 : Commit the transaction and return the success
                $pdo->commit();
            } catch (Exception $e) {
                // Rollback transaction on error/exception
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo __FUNCTION__." (): Error destroying trace worker: " . $e->getMessage();
                echo  __FUNCTION__."(): Transaction failed: " . $e->getMessage()."<br />";
                return false;
            }
        }
    }
    return true;
}


// TODO : Add Conversion to the captured agent possible actions list,
    // lock behind config JSON for certain factions, conversion probablility values 
    // This function should take an worker_id and a controller_id:
        // check the configuration for the JSON
        // decompresse the JSON
        // check if the worker_id is in the list of captured agents for the controller_id
        // roll the random conversion probability :

        // agent dies : ?
            // set 

        // if become double agent :
            // set the worker to active
            // controller_worker to primary controller

        // if converted : 
            // set original workers table to inactive dead and worker_actions to dead
            // Copies workers and worker_actions tables to the active controller
            // Adds a Tranformation with the info and a négativ effect ?
            // set original workers table to inactive dead and worker_actions to dead

// TODO : Add conversion of the captured agent faction power to the pirates
