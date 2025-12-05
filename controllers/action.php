<?php

require_once '../base/basePHP.php';
$pageName = 'controllers_action';
$debug = strtolower($_SESSION['DEBUG']) === 'true';

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($debug) echo "_GET:".var_export($_GET, true)." <br /> <br />";

    // ID Getters
    $controller_id = NULL;
    if ( !empty($_GET['controller_id']) ) $controller_id = $_GET['controller_id'];
    if ($debug) echo "controller_id: ".var_export($controller_id, true)."<br /><br />";

    $zone_id = NULL;
    if ( !empty($_GET['zone_id']) ) $zone_id = $_GET['zone_id'];
    if ($debug) echo "zone_id: ".var_export($zone_id, true)."<br /><br />";
    
    $base_id = NULL;
    if ( !empty($_GET['base_id']) ) $base_id = $_GET['base_id'];
    if ($debug) echo "base_id: ".var_export($base_id, true)."<br /><br />";

    $target_location_id = NULL;
    if ( !empty($_GET['target_location_id']) ) $target_location_id = $_GET['target_location_id'];
    if ($debug) echo "target_location_id: ".var_export($target_location_id, true)."<br /><br />";

    // Actions
    if (isset($_GET['createBase'])){
        createBase($gameReady, $controller_id, $zone_id);
    }
    if (isset($_GET['moveBase'])){
        moveBase($gameReady, $base_id, $zone_id, $controller_id);
    }
    if (isset($_GET['attackLocation'])){
        if ($debug) echo sprintf('start <br> controller_id: %s, <br />target_location_id: %s<br /><br />', var_export($controller_id, true), var_export($target_location_id, true));
        $attackLocationResult = attackLocation($gameReady, $controller_id, $target_location_id);
        if ($debug) echo sprintf('end %s %s<br/>', $attackLocationResult['success'], $attackLocationResult['message']);
    }
    if (isset($_GET['repairLocation'])){
        if ($debug) echo sprintf('start <br> controller_id: %s, <br />target_location_id: %s<br /><br />', var_export($controller_id, true), var_export($target_location_id, true));
        spendRessourcesToRepairLocation($gameReady, $controller_id);
        $stmt = $gameReady->prepare("SELECT * FROM locations WHERE id = ?");
        $stmt->execute([$target_location_id]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
        $activate_json = json_decode($location['activate_json'], true);
        updateLocation($gameReady, $location, $activate_json);
        if ($debug) echo sprintf('end <br/>');
    }
    if (isset($_GET['giftInformationAgent'])){
        //  Get Turn Number
        $mechanics = getMechanics($gameReady);
        $controller_id = $_GET['controller_id'];
        $target_controller_id = $_GET['target_controller_id'];
        $enemy_worker_id = $_GET['enemy_worker_id'];

        // Get zone from controllers_known_enemies where controller_id = $controller_id and discovered_worker_id = $enemyWorkersSelect
        $sql = "SELECT zone_id FROM controllers_known_enemies WHERE controller_id = ? AND discovered_worker_id = ?";
        $stmt = $gameReady->prepare($sql);
        $stmt->execute([$controller_id, $enemy_worker_id]);
        $zone_id = $stmt->fetch(PDO::FETCH_ASSOC)['zone_id'];

        // Gift information
        addWorkerToCKE($gameReady, $target_controller_id, $enemy_worker_id, $mechanics['turncounter'], $zone_id);
    }
    if (isset($_GET['giftInformationLocation'])){
        // Get Turn Number
        $mechanics = getMechanics($gameReady);
        $target_controller_id = $_GET['target_controller_id'];
        $location_id = $_GET['location_id'];
        addLocationToCKL($gameReady, $target_controller_id, $location_id, $mechanics['turncounter'], false);
    }
}

require_once '../base/baseHTML.php';
require_once '../controllers/view.php';


