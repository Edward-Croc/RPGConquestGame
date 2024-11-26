<?php

require_once '../base/base_php.php';
$pageName = 'action';

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['creation'])){
        createWorker($gameReady, $_GET);
        if ($_SESSION['DEBUG'] == true) {
            echo 'DONE';
        }
    }
}

require_once '../base/base_html.php';
