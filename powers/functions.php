<?php
/**
 * Get the description for a power_type
 * 
 * @param PDO $pdo
 * @param string $name
 * 
 * @return string|NULL : $description 
 */
function getPowerTypesDescription($pdo, $name){
    try{
        $stmt = $pdo->prepare("SELECT description
            FROM power_types
            WHERE name = :name
        ");
        $stmt->execute([':name' => $name]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $name failed: " . $e->getMessage()."<br />";
        return NULL;
    }
}

/**
 * Get the format for a power text
 * 
 * @param bool $short
 * 
 * @return string
 */
function getSQLPowerText($short = TRUE) {
    if (!$short) return "CONCAT(p.name, p.description, ' (', p.enquete, ', ', p.attack, '/', p.defence, ')') AS power_text";
    return "CONCAT(p.name, ' (', p.enquete, ', ', p.attack, '/', p.defence, ')') AS power_text";
}

/**
 * Gets array of powers for a worker id
 * 
 * @param PDO $pdo
 * @param string $worker_id_str
 * 
 * @return array 
 */
function getPowersByWorkers($pdo, $worker_id_str) {
    $power_text = getSQLPowerText(FALSE);
    $sql = "SELECT
        w.id AS worker_id,
        p.*,
        $power_text,
        pt.name AS power_type_name
    FROM workers w
    JOIN worker_powers wp ON w.id = wp.worker_id
    JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
    JOIN powers p ON lpt.power_id = p.ID
    JOIN power_types pt ON lpt.power_type_id = pt.ID
    WHERE w.id IN ($worker_id_str)
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
    if ($_SESSION['DEBUG'] == true) echo sprintf("workers_powers %s <br /> <br />", var_export($workers_powers,true));

    return $workers_powers;
}

/**
 *  get a numnber of random elements from the type of power given
 * 
 * @param PDO $pdo
 * @param string $type_list
 * @param int $limit
 * 
 * @return array|null : $powerArray
 */
// TODO : Add a select limit by controller_id like in the getPowersByType function
function randomPowersByType($pdo, $type_list, $limit = 1) {
    $powerArray = array();
    $power_text = getSQLPowerText();
    try{
        // Get x random values from powers for a power_type
        $sql = "SELECT *, $power_text FROM powers AS p
        INNER JOIN link_power_type ON link_power_type.power_id = p.id
        WHERE link_power_type.power_type_id IN ($type_list) ORDER BY RANDOM() LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $powerArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $powerArray;
}

/**
 * get power from a type with options :
 *  - powers linked to a cotroler by faction
 *  - base power from config
 * 
 * @param PDO $pdo
 * @param int $type_list  // TODO change from ID of link_power_type to a type name ? 
 * @param int $controller_id
 * @param bool $add_base
 * 
 * @return array|null : $powerArray
 */
function getPowersByType($pdo, $type_list, $controller_id = NULL, $add_base = TRUE) {
    $powerArray = array();
    $power_text = getSQLPowerText();

    $basePowerNames = '';
    if ( $add_base ){
        $configBasePowerNames = getConfig($pdo, 'basePowerNames');
        if ( !empty($configBasePowerNames) ) {
            $basePowerNames = $configBasePowerNames;
        }
    }

    $conditions = '';
    if ($controller_id != "" || $basePowerNames != "") {
    $conditions = sprintf("AND ( %s %s %s )",
        $basePowerNames != "" ? "powers.name IN ($basePowerNames)" : '',
        ($controller_id != "" && $basePowerNames != "") ? "OR" : '',
        $controller_id != "" ? "controllers.id IN ($controller_id)" : '');
    }

    // Get all powers from a type_list
    $sql = sprintf('SELECT p.*, %3$s, link_power_type.id as link_power_type_id
        FROM powers AS p
        JOIN link_power_type ON link_power_type.power_id = p.id
        WHERE p.id IN (
            SELECT distinct(powers.id)
            FROM powers
            JOIN link_power_type ON link_power_type.power_id = powers.id
            LEFT JOIN faction_powers ON faction_powers.link_power_type_id = link_power_type.id
            LEFT JOIN factions ON factions.id = faction_powers.faction_id
            LEFT JOIN controllers ON controllers.faction_id = factions.id
            WHERE link_power_type.power_type_id IN ( %1$s )
            %2$s
        )',
        $type_list,
        $conditions,
        $power_text,
    );
    if ($_SESSION['DEBUG'] == true){
        echo __FUNCTION__."(): $sql <br />";
    }

    try{
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $powerArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $powerArray;
}

/**
 * builds the discipline select field from an array of Disciplines
 * 
 * @param PDO $pdo
 * @param array $powerDisciplineArray
 * @param bool $showText default: true
 * 
 * @return string: $showDisciplineSelect
 */
function showDisciplineSelect($pdo, $powerDisciplineArray, $showText = True){
    if (empty($powerDisciplineArray)) return '';

    $disciplinesOptions = '';

    // Display select list of controllers
    foreach ( $powerDisciplineArray as $powerDiscipline) {
        $disciplinesOptions .= "<option value='" . $powerDiscipline['link_power_type_id'] . "'>" . $powerDiscipline['power_text'] . " </option>";
    }
    $showDisciplineSelect = sprintf(" %s
        <select id='disciplineSelect' name='discipline'>
            <option value=\'\'>Select %s</option>
            %s
        </select>
        <br />
        ",
        $showText ? getPowerTypesDescription($pdo, 'Discipline').' :' : '',
        getPowerTypesDescription($pdo, 'Discipline'),
        $disciplinesOptions
    );

    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showDisciplineSelect: ".var_export($showDisciplineSelect, true)."<br /><br />";

    return $showDisciplineSelect;
}

/**
 * 
 * 
 * @param PDO $pdo : database connection
 * @param array $powerArray
 * @param int $controller_id
 * @param int $worker_id
 * @param int $turn_number
 * @param string $state_text
 * 
 * @return array|null : $powerArray
 *
 */
function cleanPowerListFromJsonConditions($pdo, $powerArray, $controller_id, $worker_id, $turn_number, $state_text ){
    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG_TRANSFORM')) == 'true') $debug = TRUE;

    $workersArray = array();
    $workersPowersList = array();
    if (!empty($worker_id)){
        $workersArray = getWorkers($pdo, [$worker_id]);
        $workersPowersArray = getPowersByWorkers($pdo, $worker_id);
        foreach ($workersPowersArray as $workerPower){
            $workersPowersList[] = $workerPower['id'];
        }
    }
    $controllersArray = array();
    if (!empty($controller_id))
        $controllersArray = getControllers($pdo, NULL, $controller_id,);

    if ($debug)
        echo sprintf("<p> powerArray : %s<br/>
            workersArray: %s <br/>
            controllersArray: %s<p/>
         ",var_export($powerArray,true),var_export($workersArray,true),var_export($controllersArray,true)
        );
    // TODO : Implement NOT Effect ?? NOT controller X
    // TODO : Implement a controller must have Zone check
    foreach ( $powerArray AS $key => $power ) {
        if (!empty($worker_id) && !empty($workersPowersArray) && in_array($power['id'],$workersPowersList,true) ){
            if ($debug) echo sprintf("kill power(%s) <br>", $key);
            unset($powerArray[$key]);
            continue;
        }
        $powerConditions = json_decode($power['other'], true);
        if ($debug) echo sprintf("power(%s) : %s ==> json powerConditions : %s <br>", $key, var_export($power, true), var_export($powerConditions,true));
        $keepElement = FALSE;
        if (!empty($powerConditions[$state_text]) ){
            if ($powerConditions[$state_text] == 'TRUE'){
                $keepElement = TRUE;
            }
            else if( is_array($powerConditions[$state_text]) ) {
                if ($debug) echo sprintf("powerConditions[%s] is array : %s  <br>", $state_text, var_export($powerConditions[$state_text], true));
                $keepElement = TRUE;
                if (!empty($powerConditions[$state_text]['OR']) ){
                    if ($debug) echo 'test the OR condition : <br/>' ;
                    $OR = FALSE;
                    foreach ($powerConditions[$state_text]['OR'] AS $element){
                        if (!empty($workersArray) ) {
                            if (!empty($element['age']) && ($element['age'] <= $workersArray[0]['age']) ) {
                                if ($debug) echo 'test PASSE the age condition : <br/>' ;
                                $OR = $OR || TRUE;
                            }
                            if (!empty($element['worker_is_alive']) && ((INT)$element['worker_is_alive'] == (INT)$workersArray[0]['is_alive'])) {
                                if ($debug) echo 'test PASSED the worker_is_alive condition : <br/>' ;
                                $OR = $OR || TRUE;
                            }
                        }
                    }
                    $keepElement = $OR;
                }
                if (!empty($workersArray) ) {
                    if ($debug) echo sprintf("!empty(workersArray) (%s): %s <br>", $workersArray, var_export($workersArray,true));
                    if (isset($powerConditions[$state_text]['age']) && ($powerConditions[$state_text]['age'] > $workersArray[0]['age']) ) {
                        if ($debug) echo 'test FAILED the age condition : <br/>' ;
                        $keepElement = FALSE;
                    }
                    if (isset($powerConditions[$state_text]['worker_is_alive'])){
                        if ($debug) echo 'test the worker_is_alive condition :';
                        if ($debug) echo sprintf(' $workersArray[0][is_alive] (%s): %s ',  gettype( $workersArray[0]['is_alive']),  $workersArray[0]['is_alive']);
                        if ($debug) echo sprintf(' $powerConditions[$state_text][worker_is_alive] (%s): %s ',  gettype( $powerConditions[$state_text]['worker_is_alive']),  $powerConditions[$state_text]['worker_is_alive']);
                        $should_be_alive = TRUE;
                        if ($powerConditions[$state_text]['worker_is_alive'] == "0" ) $should_be_alive = FALSE;
                        if ($debug) echo sprintf(' $should_be_alive (%s): %s ',  gettype($should_be_alive), $should_be_alive );
                        if ( $workersArray[0]['is_alive'] !== $should_be_alive ) {
                            if ($debug) echo ' FAILD' ;
                            $keepElement = FALSE;
                        }
                        if ($debug) echo ' <br/>' ;
                    }
                }
                if (isset($powerConditions[$state_text]['turn']) && $powerConditions[$state_text]['turn'] > $turn_number) {
                    if ($debug) echo 'test FAILED the turn condition : <br/>' ;
                    $keepElement = FALSE;
                }
                if (!empty($powerConditions[$state_text]['controller_faction']) && $powerConditions[$state_text]['controller_faction'] != $controllersArray[0]['faction_name']){
                    if ($debug) echo 'test FAILD the controller_faction condition : <br/>' ;
                    $keepElement = FALSE;
                }
            }
        }
        else{
            if ($debug) echo sprintf("powerConditions[%s] is empty : %s ", $state_text, $powerConditions[$state_text]);
        }
        if (!$keepElement){
            if ($debug) echo sprintf("kill power(%s) <br>", $key);
            unset($powerArray[$key]);
        }
    }
    if ($debug) echo sprintf("Whats left of powerArray : %s <br>", var_export($powerArray,true));
    return empty($powerArray) ? NULL : $powerArray ;
}

/**
 * Build select field for Transformations in array 
 * 
 * @param PDO $pdo : database connection
 * @param array $powerTransformationArray
 * @param bool $showText default true
 * 
 * @return string : $showTransformationSelect
 * 
 */
function showTransformationSelect($pdo, $powerTransformationArray, $showText = True){
    if (empty($powerTransformationArray)) return '';

    $transformationsOptions = '';

    // Display select list of controllers
    foreach ( $powerTransformationArray as $powerTransformation) {
        $transformationsOptions .= "<option value='" . $powerTransformation['link_power_type_id'] . "'>" . $powerTransformation['power_text'] . " </option>";
    }
    $showTransformationSelect = sprintf("%s
        <select id='transformationSelect' name='transformation'>
            <option value=\'\'>Select %s</option>
            %s
        </select>
        <br />
        ",
        $showText ? getPowerTypesDescription($pdo, 'Transformation').' :' : '',
        getPowerTypesDescription($pdo, 'Transformation'),
        $transformationsOptions
    );

    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showTransformationSelect: ".var_export($showTransformationSelect, true)."<br /><br />";

    return $showTransformationSelect;
}
