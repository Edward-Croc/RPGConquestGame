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

function getControlers($pdo, $player_id = NULL){
    $sql = "SELECT c.*, f.name AS faction_name FROM controlers c LEFT JOIN factions f ON c.faction_id = f.ID";
    if ($player_id !== NULL){
        $sql .= "
            INNER JOIN player_controler pc ON pc.controler_id = c.id
            WHERE pc.player_id = '$player_id'";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // Fetch the results
    $controllers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output the results
    if ($controllers) {
        foreach ($controllers as $controller) {
            echo $controller['firstname'] . " " . $controller['lastname'] . "(".$controller['id'].") " . $controller['faction_name'] . "<br>";
        }
    } else {
        echo "No controllers found.";
    }
}

echo'
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>RPGConquestGame</title>
            <style>';
            include_once './style.css';
echo'
        </style>
    </head>
    <body>
        <div class="header">
            <h1>RPGConquestGame</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        <div class="content">
            <div class="zones">
                <h2>Zones</h2>
                <!-- Add content for zones here -->';
                echo'
            </div>
            <div class="factions">
                <h2>Factions</h2>
                <!-- Add content for factions here -->';
                    // Prepare and execute SQL query to select list of controllers with their faction names
                    getControlers($gameReady, $_SESSION['userid']);
                echo'
            </div>
        </div>
    </body>
</html>
    ';


?>
