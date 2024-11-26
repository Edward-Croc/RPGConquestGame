<?php

require_once '../base/base_php.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $buttonClicked = $_GET['button'] ?? null;
    echo "buttonClicked : $buttonClicked";
}

$originsArray = randomWorkerOrigin($gameReady);
echo var_export($originsArray, true);


$nameArray = randomWorkerName($gameReady, '1,2,3,4,5');
echo var_export($nameArray, true);

?>