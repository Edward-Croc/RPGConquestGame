<?php

function getSQLPowerText() {
    return "CONCAT(p.name, ' (', p.enquete, ', ', p.attack, '/', p.defence, ')') AS power_text";
}

function getPowersByWorkers($pdo, $worker_id_str) {
    $power_text = getSQLPowerText();
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

function getPowersByType($pdo, $type_list, $controler_id = NULL, $add_base = TRUE) {
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
    if ($controler_id != "" || $basePowerNames != "") {
    $conditions = sprintf("AND ( %s %s %s )",
        $basePowerNames != "" ? "powers.name IN ($basePowerNames)" : '',
        ($controler_id != "" && $basePowerNames != "") ? "OR" : '',
        $controler_id != "" ? "controlers.id IN ($controler_id)" : '');
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
            LEFT JOIN controlers ON controlers.faction_id = factions.id
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

function showDisciplineSelect($powerDisciplineArray, $show_text = true){
    if (empty($powerDisciplineArray)) return '';

    $disciplinesOptions = '';

    // Display select list of Controlers
    foreach ( $powerDisciplineArray as $powerDiscipline) {
        $disciplinesOptions .= "<option value='" . $powerDiscipline['link_power_type_id'] . "'>" . $powerDiscipline['power_text'] . " </option>";
    }
    $showDisciplineSelect = sprintf(" %s
        <select id='disciplineSelect' name='discipline'>
            <option value=\'\'>Select Discipline</option>
            %s
        </select>
        <br />
        ",
        $show_text ? ' Discipline:' : '',
        $disciplinesOptions
    );

    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showDisciplineSelect: ".var_export($showDisciplineSelect, true)."<br /><br />";

    return $showDisciplineSelect;
}

function cleanPowerListFromJsonConditions($pdo, $powerArray, $controler_id, $worker_id, $turn_number, $state_text ){
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
    $controlersArray = array();
    if (!empty($controler_id))
        $controlersArray = getControlers($pdo, NULL, $controler_id,);


    $debug = TRUE;
    if ($debug) 
        echo sprintf("<p> powerArray : %s<br/>
            workersArray: %s <br/>
            controlersArray: %s<p/>
         ",var_export($powerArray,true),var_export($workersArray,true),var_export($controlersArray,true)
        );
        $debug = TRUE;
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
                    if (!empty($powerConditions[$state_text]['age']) && ($powerConditions[$state_text]['age'] > $workersArray[0]['age']) ) {
                        if ($debug) echo 'test FAILED the age condition : <br/>' ;
                        $keepElement = FALSE;
                    }
                    if (!empty($powerConditions[$state_text]['worker_is_alive']) && ((INT)$powerConditions[$state_text]['worker_is_alive'] != (INT)$workersArray[0]['is_alive'])) {
                        if ($debug) echo 'test FAILD the worker_is_alive condition : <br/>' ;
                        $keepElement = FALSE;
                    }
                } else 
                    $keepElement = FALSE;
                if (!empty($powerConditions[$state_text]['turn']) && $powerConditions[$state_text]['turn'] > $turn_number) {
                    if ($debug) echo 'test FAILED the turn condition : <br/>' ;
                    $keepElement = FALSE;
                }
                if (!empty($powerConditions[$state_text]['controler_faction']) && $powerConditions[$state_text]['controler_faction'] != $controlersArray[0]['faction_name']){
                    if ($debug) echo 'test FAILD the worker_is_alive condition : <br/>' ;
                    $keepElement = FALSE;
                }
            }
        }
        if (!$keepElement){
            if ($debug) echo sprintf("kill power(%s) <br>", $key);
            unset($powerArray[$key]);
        }
    }
    if ($debug) echo sprintf("Whats left of powerArray : %s <br>", var_export($powerArray,true));
    return empty($powerArray) ? NULL : $powerArray ;
}

function showTransformationSelect($powerTransformationArray, $show_text = true){
    if (empty($powerTransformationArray)) return '';

    $transformationsOptions = '';

    // Display select list of Controlers
    foreach ( $powerTransformationArray as $powerTransformation) {
        $transformationsOptions .= "<option value='" . $powerTransformation['link_power_type_id'] . "'>" . $powerTransformation['power_text'] . " </option>";
    }
    $showTransformationSelect = sprintf("%s
        <select id='transformationSelect' name='transformation'>
            <option value=\'\'>Select Transformation</option>
            %s
        </select>
        <br />
        ",
        $show_text ? 'Transformation :' : '',
        $transformationsOptions
    );

    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showTransformationSelect: ".var_export($showTransformationSelect, true)."<br /><br />";

    return $showTransformationSelect;
}
