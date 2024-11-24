<?php
session_start(); // Start the 

$pageName = 'index';

require_once './BDD/db_connector.php';

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
}

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect the user to the login page if not logged in
    header('Location: connection/login_form.php');
    exit();
}
    // Redirect the user to the login page if not logged in
    header('Location: base/accueil.php');
    exit();