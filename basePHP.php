<?php

require_once './dbConnector.php';

// Call the gameReady() function from dbConnector.php
$gameReady = gameReady();
// Use the return value
if (!$gameReady) {
    echo "The game is not ready. Please check DB Configuration and Setup. <br />";
    exit();
}else{
    $_SESSION['DEBUG'] = getConfig($gameReady, 'DEBUG');
    if ($_SESSION['DEBUG'] == true){
        echo "The game is ready.<br />";
    }
}

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect the user to the login page if not logged in
    header('Location: loginForm.php');
    exit();
}
if ($_SESSION['DEBUG'] == true){
    echo "Debug : ".$_SESSION['DEBUG'].";  ID: " . $_SESSION['userid']. ", is_privileged: '" . $_SESSION['is_privileged']. "' <br />";
}


?>
