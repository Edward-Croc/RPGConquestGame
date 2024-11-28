<?php

function getSQLPowerText() {
    return "CONCAT(p.name, ' (', p.enquete, ', ', p.action, '/', p.defence, ')') AS power_text";
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

function getPowersByType($pdo, $type_list) {
    $powerArray = array();
    $power_text = getSQLPowerText();

    // Get all powers from a type_list
    try{
        $sql = "SELECT p.*, $power_text FROM powers
        INNER JOIN link_power_type ON link_power_type.power_id = p.id
        WHERE link_power_type.power_type_id IN ($type_list)";
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

function getBasePowers($pdo, $type_list, $controler_id = null) {
    $powerArray = array();
    $power_text = getSQLPowerText();

    $basePowerNames = '';
    $configBasePowerNames = getConfig($pdo, 'basePowerNames');
    if ( !empty($configBasePowerNames) ) {
        $basePowerNames = $configBasePowerNames;
    }

    // Get all powers from a type_list
    $sql = sprintf('SELECT p.*, %5$s, link_power_type.id as link_power_type_id
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
            AND ( %2$s %3$s %4$s )
        )',
        $type_list,
        $basePowerNames != "" ? "powers.name IN ($basePowerNames)" : '',
        ($controler_id != "" && $basePowerNames != "") ? "OR" : '',
        $controler_id != "" ? "controlers.id IN ($controler_id)" : '',
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

function showDisciplineSelect($powerDisciplineArray){
    if (empty($powerDisciplineArray)) return '';

    $disciplinesOptions = '';

    // Display select list of Controlers
    foreach ( $powerDisciplineArray as $powerDiscipline) {
        $disciplinesOptions .= "<option value='" . $powerDiscipline['link_power_type_id'] . "'>" . $powerDiscipline['power_text'] . " </option>";
    }
    $showDisciplineSelect = sprintf(" Discipline:
        <select id='disciplineSelect' name='discipline'>
            <option value=\'\'>Select Discipline</option>
            %s
        </select>
        <br />
        ",
        $disciplinesOptions
    );

    if ($_SESSION['DEBUG'] == true) echo __FUNCTION__."(): showDisciplineSelect: ".var_export($showDisciplineSelect, true)."<br /><br />";

    return $showDisciplineSelect;
}
