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
    $prefix = $_SESSION['GAME_PREFIX'];
    try{
        $stmt = $pdo->prepare("SELECT description
            FROM {$prefix}power_types
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
function getSQLPowerText($short = true) {
    $sql = "CONCAT(p.name, ' (', p.enquete, ', ', p.attack, '/', p.defence, ')') AS power_text";
    if (!$short) {
        $sql = "CONCAT('<strong>', p.name, ' (', p.enquete, ', ', p.attack, '/', p.defence, ')</strong> ', p.description) AS power_text";
        if ($_SESSION['DBTYPE'] == 'mysql')
            $sql = "CONCAT('<strong>', p.name, ' (', p.enquete, ', ', p.attack, '/', p.defence, ')</strong> ', IFNULL(p.description,'')) AS power_text";
    }
    
    return $sql;
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
    $power_text = getSQLPowerText(false);
    $prefix = $_SESSION['GAME_PREFIX'];
    $sql = "SELECT
        w.id AS worker_id,
        p.*,
        $power_text,
        pt.name AS power_type_name
    FROM {$prefix}workers w
    JOIN {$prefix}worker_powers wp ON w.id = wp.worker_id
    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
    JOIN {$prefix}powers p ON lpt.power_id = p.ID
    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
    WHERE w.id IN ($worker_id_str)
    ORDER BY w.id ASC
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return array();
    }
    // Fetch the results
    $workers_powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($_SESSION['DEBUG'] == true)
        echo sprintf("workers_powers %s <br /> <br />", var_export($workers_powers,true));

    return $workers_powers;
}

/**
 *  get a number of random elements from the type of power given
 *
 * @param PDO $pdo
 * @param string $type
 * @param array $newWorker
 *
 * @return array : $newWorker
 */
function randomPowersByType($pdo, $type, $newWorker) {

    // TODO : Add a select limit by controller_id like in the getPowersByType function
    // TODO : Allow locking certain Hobbys/Metiers by origin or controler !

    $power_text = getSQLPowerText(false);
    $randCommand = 'RANDOM()';
    if ($_SESSION['DBTYPE'] == 'mysql')
        $randCommand = 'RAND()';
    $prefix = $_SESSION['GAME_PREFIX'];
    try{
        // Get x random values from powers for a power_type
        $sql = sprintf("SELECT p.*, %s FROM {$prefix}powers AS p
            INNER JOIN {$prefix}link_power_type ON link_power_type.power_id = p.id
            WHERE link_power_type.power_type_id = %s ORDER BY %s LIMIT 1",
            $power_text,
            $type,
            $randCommand
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }

    // Fetch the results
    $powerArray = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $newWorker['power_'.$type] = $powerArray[0];
    
    return $newWorker;
}

/**
 * get power from a type with options :
 *  - powers linked to a controller by faction
 *  - base power from config
 *
 * @param PDO $pdo
 * @param int $type_list  // TODO change from ID of link_power_type to a type name ?
 * @param int $controller_id
 * @param bool $add_base
 *
 * @return array|null : $powerArray
 */
function getPowersByType($pdo, $type_list, $controller_id = NULL, $add_base = true) {
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
        $conditions = sprintf("AND ( %s %s %s)",
        $basePowerNames != "" ? "powers.name IN ($basePowerNames)" : '',
        ($controller_id != "" && $basePowerNames != "") ? "OR" : '',
        $controller_id != "" ? "controllers.id IN ($controller_id)" : '');
    }

    $prefix = $_SESSION['GAME_PREFIX'];
    // Get all powers from a type_list
    $sql = sprintf("SELECT p.*, %3\$s, link_power_type.id as link_power_type_id
        FROM {$prefix}powers AS p
        JOIN {$prefix}link_power_type ON link_power_type.power_id = p.id
        WHERE p.id IN (
            SELECT distinct(powers.id)
            FROM {$prefix}powers
            JOIN {$prefix}link_power_type ON link_power_type.power_id = powers.id
            LEFT JOIN {$prefix}faction_powers ON faction_powers.link_power_type_id = link_power_type.id
            LEFT JOIN {$prefix}factions ON factions.id = faction_powers.faction_id
            LEFT JOIN {$prefix}controllers ON controllers.faction_id = factions.id
            WHERE link_power_type.power_type_id IN ( %1\$s )
            %2\$s
        )",
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
function showDisciplineSelect($pdo, $powerDisciplineArray, $showText = true){
    if (empty($powerDisciplineArray)) return '';

    $disciplinesOptions = '';
    foreach ($powerDisciplineArray as $powerDiscipline) {
        $disciplinesOptions .= "<option value='" . htmlspecialchars($powerDiscipline['link_power_type_id']) . "'>" . htmlspecialchars($powerDiscipline['power_text']) . "</option>";
    }

    $label = $showText ? getPowerTypesDescription($pdo, 'Discipline').' :' : '';

    $showDisciplineSelect = sprintf('
            %s
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="disciplineSelect" name="discipline">
                        <option value="">Sélectionner %s</option>
                        %s
                    </select>
                </div>
            </div>
        ',
        $label ? 'Enseigner un.e '.$label : '',
        htmlspecialchars(getPowerTypesDescription($pdo, 'Discipline')),
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
    $debug = (strtolower(getConfig($pdo, 'DEBUG_TRANSFORM')) == 'true');

    $worker = null;
    $workersPowersList = array();

    if (!empty($worker_id)){
        $workersArray = getWorkers($pdo, [$worker_id]);
        $worker = $workersArray[0];
        $workersPowersArray = getPowersByWorkers($pdo, $worker_id);
        foreach ($workersPowersArray as $workerPower){
            $workersPowersList[] = $workerPower['id'];
        }
    }
    $controllersArray = array();
    $zonesArray = array();
    if (!empty($controller_id)){
        $controllersArray = getControllers($pdo, NULL, $controller_id);
        $zonesArray = getZonesArray($pdo, $controller_id, null, null);
        $zonesArrayHolder = getZonesArray($pdo, null, $controller_id, null);
    }

    if ($debug)
        echo sprintf(
            "<p> powerArray : %s<br/> worker: %s <br/> controllersArray: %s<p/> ",
            var_export($powerArray,true),
            var_export($worker,true),
            var_export($controllersArray,true)
        );

    // TODO : Implement NOT Effect ?? NOT controller X
    // Loop through powers and validate against JSON conditions
    foreach ( $powerArray AS $key => $power ) {

        // Skip powers the worker already possesses
        if (!empty($worker_id) && !empty($powerArray) && in_array($power['id'],$workersPowersList,true) ){
            if ($debug) echo sprintf("kill power(%s) <br>", $key);
            unset($powerArray[$key]);
            continue;
        }

        $powerConditions = json_decode($power['other'], true);
        if ($debug) 
            echo sprintf("power(%s) : %s ==> json powerConditions : %s <br>", $key, var_export($power, true), var_export($powerConditions,true));

        // Base state is always keep
        $keepElement = true;
        if (!empty($powerConditions[$state_text]) ){

            // Raw false as string = to drop
            if ( (gettype($powerConditions[$state_text]) == "string") && (strtolower($powerConditions[$state_text]) == strtolower('false')) ){
                $keepElement = false;
            }
            // If condition is an array, check details
            else if( is_array($powerConditions[$state_text]) ) {
                if ($debug)
                     echo sprintf("powerConditions[%s] is array : %s  <br>", $state_text, var_export($powerConditions[$state_text], true));

                // OR condition block
                if (!empty($powerConditions[$state_text]['OR']) ){
                    if ($debug) echo 'test the OR condition : <br/>' ;
                    $OR = false;
                    foreach ($powerConditions[$state_text]['OR'] AS $element){
                        if (!empty($worker) ) {
                            if (!empty($element['age']) && !empty($worker['age']) && ($element['age'] <= $worker['age']) ) {
                                if ($debug) echo 'test PASSE the age condition : <br/>' ;
                                $OR = true;
                            }
                            if (!empty($element['worker_is_alive']) && isset($worker['actions'][$turn_number]['action_choice'])) {
                                $should_be_alive = true;
                                if ($element['worker_is_alive'] == "0" ) $should_be_alive = false;
                                if ( in_array($worker['actions'][$turn_number]['action_choice'], ACTIVE_ACTIONS) === $should_be_alive) {
                                    if ($debug) echo 'test PASSED the worker_is_alive condition : <br/>' ;
                                    $OR = true;
                                }
                            }
                        }
                    }
                    $keepElement = $OR;
                }

                // Direct checks
                if (!empty($worker) ) {
                    if ($debug) echo sprintf("!empty(worker) (%s): %s <br>", gettype($worker), var_export($worker,true));
                    if (isset($powerConditions[$state_text]['age']) && !empty($worker['age']) && ($powerConditions[$state_text]['age'] > $worker['age']) ) {
                        if ($debug) echo 'test FAILED the age condition : <br/>' ;
                        $keepElement = false;
                    }

                    if (isset($powerConditions[$state_text]['worker_is_alive']) && isset($worker['actions'][$turn_number]['action_choice'])){
                        if ($debug) echo sprintf("test the worker_is_alive condition : %s <br>", var_export($powerConditions[$state_text]['worker_is_alive'], true));
                        $should_be_alive = true;
                        if ($powerConditions[$state_text]['worker_is_alive'] == "0" ) $should_be_alive = false;
                        if ( in_array($worker['actions'][$turn_number]['action_choice'], ACTIVE_ACTIONS) !== $should_be_alive) {
                            if ($debug) echo sprintf("test FAILED the worker_is_alive condition : %s <br>", var_export($worker['actions'][$turn_number]['action_choice'], true));
                            $keepElement = false;
                        }
                        if ($debug) echo ' <br/>' ;
                    }
                }

                if (isset($powerConditions[$state_text]['turn']) && $powerConditions[$state_text]['turn'] > $turn_number) {
                    if ($debug) echo 'test FAILED the turn condition : <br/>' ;
                    $keepElement = false;
                }

                if (!empty($powerConditions[$state_text]['controller_faction']) && $powerConditions[$state_text]['controller_faction'] != $controllersArray[0]['faction_name']){
                    if ($debug) echo 'test FAILED the controller_faction condition : <br/>' ;
                    $keepElement = false;
                }

                // controller_has_zone
                if (!empty($powerConditions[$state_text]['controller_has_zone']) ) {
                    if (empty($zonesArray) && empty($zonesArrayHolder)) {
                        $keepElement = false;
                        if ($debug)
                            echo "FAILED controller_has_zone check<br/>";
                    } else{
                        $foundZone = false;
                        foreach ( $zonesArray as $zone ){
                           if ( $zone['name'] == $powerConditions[$state_text]['controller_has_zone'])
                            $foundZone = true;
                        }
                        foreach ( $zonesArrayHolder as $zone ){
                            if ( $zone['name'] == $powerConditions[$state_text]['controller_has_zone'])
                            $foundZone = true;
                        }
                        if ( !$foundZone ) $keepElement = false;
                    }
                }

                // worker_in_zone
                if (
                    !empty($powerConditions[$state_text]['worker_in_zone'])
                    && !empty($worker)
                    && ( ! ($worker['zone_name'] == $powerConditions[$state_text]['worker_in_zone']) )
                ) {
                    $keepElement = false;
                    if ($debug)
                        echo "FAILED controller_has_zone check<br/>";
                }
            }
        }
        else{
            if ($debug) echo sprintf("powerConditions[%s] is empty : %s ", $state_text, $powerConditions[$state_text]);
        }

        // Remove if not valid
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
function showTransformationSelect($pdo, $powerTransformationArray, $showText = true){
    if (empty($powerTransformationArray)) return '';

    $transformationsOptions = '';
    foreach ($powerTransformationArray as $powerTransformation) {
        $transformationsOptions .= "<option value='" . $powerTransformation['link_power_type_id'] . "'>" . $powerTransformation['power_text'] . "</option>";
    }

    $label = $showText ? getPowerTypesDescription($pdo, 'Transformation').' :' : '';

    $showTransformationSelect = sprintf('
            %s
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="transformationSelect" name="transformation">
                        <option value="">Sélectionner %s</option>
                        %s
                    </select>
                </div>
            </div>
        ',
        $label ? 'Ajouter un.e '.$label.'' : '',
        getPowerTypesDescription($pdo, 'Transformation'),
        $transformationsOptions
    );

    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showTransformationSelect: ".var_export($showTransformationSelect, true)."<br /><br />";

    return $showTransformationSelect;
}
