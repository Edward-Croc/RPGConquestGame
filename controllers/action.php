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
        if ($debug) echo sprintf('end <br/>', $attackLocationResult['success'], $attackLocationResult['message']);
    }
    
}

require_once '../base/baseHTML.php';
require_once '../controllers/view.php';


