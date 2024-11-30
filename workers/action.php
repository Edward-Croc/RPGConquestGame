<?php

require_once '../base/base_php.php';
$pageName = 'action';

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_SESSION['DEBUG'] == true) echo "_GET:".var_export($_GET, true)." <br /> <br />";

    $worker_id = NULL;
    if ( !empty($_GET['worker_id']) ) $worker_id = $_GET['worker_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "worker_id: ".var_export($worker_id, true)."<br /><br />";
    $zone_id = NULL;
    if ( !empty($_GET['zone_id']) ) $zone_id = $_GET['zone_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "zone_id: ".var_export($zone_id, true)."<br /><br />";
    $enemy_worker_id = NULL;
    if ( !empty($_GET['enemy_worker_id']) ) $enemy_worker_id = $_GET['enemy_worker_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "enemy_worker_id: ".var_export($enemy_worker_id, true)."<br /><br />";
    $claim_controler_id = NULL;
    if ( !empty($_GET['claim_controler_id']) ) $claim_controler_id = $_GET['claim_controler_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "claim_controler_id: ".var_export($claim_controler_id, true)."<br /><br />";

    if (isset($_GET['creation'])){
        $worker_id = createWorker($gameReady, $_GET);
        if ($_SESSION['DEBUG'] == true) echo 'createWorker : DONE <br />';
    }
    if (isset($_GET['move'])){
        if (!empty($zone_id)) moveWorker($gameReady, $worker_id, $zone_id);
    }
    if (isset($_GET['attack'])){
        activateWorker($gameReady, $worker_id, 'attack', $enemy_worker_id);
    }
    if (isset($_GET['activate'])){
        activateWorker($gameReady, $worker_id, 'activate');
    }
    if (isset($_GET['claim'])){
        activateWorker($gameReady, $worker_id, 'claim', $claim_controler_id);
    }
    if (isset($_GET['gift'])){
        activateWorker($gameReady, $worker_id, 'gift', $claim_controler_id);
    }
}

require_once '../base/base_html.php';
require_once '../workers/view.php';


