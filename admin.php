<?php
session_start(); // Start the session

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
    echo "Debug : ".$_SESSION['DEBUG'].";  ID: " . $_SESSION['userid']. ", is_privileged: " . $_SESSION['is_privileged']. "<br />";
}

if (
    isset($_POST['resetBDD'])
) {
    destroyAllTables($gameReady);
    $gameReady = gameReady();
}

?>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPGConquestGame</title>
    <style>
        <?php include_once './style.css'; ?>
    </style>
</head>
<body>
<div class="header">
    <h1>RPGConquestGame</h1>
    <div class="menu_top_left">
        <a href="index.php" class="admin-btn">Retour</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>
<div class="content">
    <form action="admin.php" method="post">
        <h3> Reset Configuration :
            <input type="hidden" name="resetBDD" />
            <input type="submit" name="submit" value="Submit" />
        </H3> 
    </form>
</div>
