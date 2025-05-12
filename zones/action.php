<?php

require_once '../base/base_php.php';
$pageName = 'action';

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_SESSION['DEBUG_ZONE'] == true) echo "_GET:".var_export($_GET, true)." <br /> <br />";

    $zone_id = NULL;
    if ( !empty($_GET['zone_id']) ) $zone_id = $_GET['zone_id'];
    if ( $_SESSION['DEBUG_ZONE'] == true ) echo "zone_id: ".var_export($zone_id, true)."<br /><br />";
    
    if (isset($_GET['attack'])){
        activateWorker($gameReady, $worker_id, 'attack', $enemy_worker_id);
    }
    if (isset($_GET['activate'])){
        activateWorker($gameReady, $worker_id, 'activate');
    }
    if (isset($_GET['gift'])){
        activateWorker($gameReady, $worker_id, 'gift', $claim_controler_id);
    }
    
}

require_once '../base/base_html.php';
require_once '../zones/view.php';


