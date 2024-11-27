<?php

require_once '../base/base_php.php';
$pageName = 'action';

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['creation'])){
        $created_worker = createWorker($gameReady, $_GET);
        if ($_SESSION['DEBUG'] == true) echo 'createWorker : DONE <br>';
    }
}

require_once '../base/base_html.php';


