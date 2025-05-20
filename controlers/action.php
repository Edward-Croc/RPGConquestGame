<?php

require_once '../base/base_php.php';
$pageName = 'action';
$_SESSION['DEBUG'] = true;

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_SESSION['DEBUG'] == true) echo "_GET:".var_export($_GET, true)." <br /> <br />";

    // ID Getters
    $controler_id = NULL;
    if ( !empty($_GET['controler_id']) ) $controler_id = $_GET['controler_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "controler_id: ".var_export($controler_id, true)."<br /><br />";

    $zone_id = NULL;
    if ( !empty($_GET['zone_id']) ) $zone_id = $_GET['zone_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "zone_id: ".var_export($zone_id, true)."<br /><br />";
    
    $base_id = NULL;
    if ( !empty($_GET['base_id']) ) $base_id = $_GET['base_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "base_id: ".var_export($base_id, true)."<br /><br />";

    $target_location_id = NULL;
    if ( !empty($_GET['target_location_id']) ) $target_location_id = $_GET['target_location_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "target_location_id: ".var_export($target_location_id, true)."<br /><br />";

    // Actions
    if (isset($_GET['createBase'])){
        createBase($gameReady, $controler_id, $zone_id);
    }
    if (isset($_GET['moveBase'])){
        moveBase($gameReady, $base_id, $zone_id);
    }
    if (isset($_GET['attackLocation'])){
        $attackLocationResult = attackLocation($gameReady, $controler_id, $target_location_id);
    }
    
}

require_once '../base/base_html.php';
require_once '../controlers/view.php';


