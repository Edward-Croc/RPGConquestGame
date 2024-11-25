<?php

require_once '../BDD/db_connector.php';
require_once '../controlers/functions.php';
require_once '../workers/functions.php';
require_once '../zones/functions.php';


function getConfig($pdo, $configName) {
    try{
        $stmt = $pdo->prepare("SELECT value 
            FROM config 
            WHERE name = :configName
        ");
        $stmt->execute([':configName' => $configName]);
        return $stmt->fetchColumn();  
    } catch (PDOException $e) {
        echo "getConfig $configName failed: " . $e->getMessage()."<br />";
        return NULL;
    }
}

function getMecanics($pdo) {
    try{
        $stmt = $pdo->query("SELECT * FROM mecanics");
        $mecanics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($_SESSION['DEBUG'] == true){
            echo "mecanics :  <br />";
            print_r ($mecanics);
            echo "<br />";
        }
        return $mecanics[0];
    } catch (PDOException $e) {
        echo "getMecanics failed: " . $e->getMessage()."<br />";
        return NULL;
    }
}

// Call the gameReady() function from dbConnector.php
$gameReady = gameReady();
// Use the return value
if (!$gameReady) {
    echo "The game is not ready. Please check DB Configuration and Setup. <br />";
    exit();
}else{
    $_SESSION['DEBUG'] = false;
    if (strtolower(getConfig($gameReady, 'DEBUG')) == 'true') {
        $_SESSION['DEBUG'] = true;
    }
    $pageTitle = getConfig($gameReady, 'TITLE');
    if ($_SESSION['DEBUG'] == true){
        echo "The game is ready.<br />";
        echo "The pageTitle is : '$pageTitle'.<br />";
    }

    $mecanics = getMecanics($gameReady);
}

if ($_SESSION['DEBUG'] == true){
    echo "Debug : ".$_SESSION['DEBUG'].";  ID: " . $_SESSION['userid']. ", is_privileged: '" . $_SESSION['is_privileged']. "' <br />";
    echo "Turn : ".$mecanics['turncounter']."; gamestat : '".$mecanics['gamestat']. "' <br />";
}


?>
