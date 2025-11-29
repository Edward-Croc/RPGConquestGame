<?php

require_once '../base/basePHP.php';
$_SESSION['DEBUG'] = true;

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_SESSION['DEBUG'] == true) echo "_GET:".var_export($_GET, true)." <br /> <br />";

    $worker_ids = NULL;
    if ( !empty($_GET['worker_ids']) ) $worker_ids = $_GET['worker_ids'];
    if ( $_SESSION['DEBUG'] == true ) echo "worker_ids: ".var_export($worker_ids, true)."<br /><br />";
    $zone_id = NULL;
    if ( !empty($_GET['zone_id']) ) $zone_id = $_GET['zone_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "zone_id: ".var_export($zone_id, true)."<br /><br />";

    // Mass move workers to zone
    if (isset($_GET['mass_move']) && !empty($zone_id)){
        foreach ($worker_ids as $worker_id) {
            moveWorker($gameReady, $worker_id, $zone_id);
        }
    }
}
$_SESSION['DEBUG'] = false;
require_once '../workers/viewAll.php';


