<?php

require_once '../base/base_php.php';
$pageName = 'action';
$_SESSION['DEBUG'] = true;

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_SESSION['DEBUG'] == true) echo "_GET:".var_export($_GET, true)." <br /> <br />";

    $controler_id = NULL;
    if ( !empty($_GET['controler_id']) ) $controler_id = $_GET['controler_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "controler_id: ".var_export($controler_id, true)."<br /><br />";

    $zone_id = NULL;
    if ( !empty($_GET['zone_id']) ) $zone_id = $_GET['zone_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "zone_id: ".var_export($zone_id, true)."<br /><br />";
    
    if (isset($_GET['attack'])){
        activateWorker($gameReady, $worker_id, 'attack', $enemy_worker_id);
    }

    if (isset($_GET['createBase'])){
        createBase($gameReady, $controler_id, $zone_id);
    }
    
}

require_once '../base/base_html.php';
require_once '../controlers/view.php';


