<?php

require_once '../base/base_php.php';
$pageName = 'action';

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_SESSION['DEBUG'] == true) echo "_GET:".var_export($_GET, true)." <br /> <br />";
    if (isset($_GET['creation'])){
        $worker_id  = createWorker($gameReady, $_GET);
        if ($_SESSION['DEBUG'] == true) echo 'createWorker : DONE <br />';
    }
    if (isset($_GET['demenager'])){
        $worker_id  = $_GET['worker_id'];
        $zone_id  = $_GET['zone'];
        moveWorker($gameReady, $worker_id, $zone_id);
    }
}

require_once '../base/base_html.php';

require_once '../workers/view.php';


