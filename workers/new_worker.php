<?php
require_once '../base/base_php.php';

if (isset($_SESSION['user_id'])){
    $controler_id = $_SESSION['user_id'];
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['controler_id'])){
        $controler_id = $_GET['controler_id'];
    }

    $buttonClicked = 'first_come';
    if (isset($_GET['recrutement'])){
        $buttonClicked = 'recrutement';
    }
    echo "buttonClicked : $buttonClicked";
    echo"<br />";
    echo"<br />";
}

$originsArray = randomWorkerOrigin($gameReady);
echo var_export($originsArray, true);
echo"<br />";
echo"<br />";

$nameArray = randomWorkerName($gameReady, $originsArray[0]['id']);
echo var_export($nameArray, true);
echo"<br />";
echo"<br />";

$nameArray = randomWorkerName($gameReady, '1,2,3,4,5');
echo var_export($nameArray, true);
echo"<br />";
echo"<br />";

$powerHobbyArray = randomPowersByType($gameReady,'1',2);
echo var_export($powerHobbyArray, true);
echo"<br />";
echo"<br />";

$powerMetierArray = randomPowersByType($gameReady,'2',2);
echo var_export($powerMetierArray, true);
echo"<br />";
echo"<br />";

$powerMetierArray = getBasePowers($gameReady,'3', $controler_id);
echo var_export($powerMetierArray, true);
echo"<br />";
echo"<br />";
?>