<?php
session_start(); // Start the session

require_once '../BDD/db_connector.php';
require_once '../controlers/functions.php';

function getConfig($pdo, $configName) {
    try{
        $stmt = $pdo->prepare("SELECT value 
            FROM config 
            WHERE name = :configName
        ");
        $stmt->execute([':configName' => $configName]);
        return $stmt->fetchColumn();  
    } catch (PDOException $e) {
        echo __FUNCTION__."(): $configName failed: " . $e->getMessage()."<br />";
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
    $_SESSION['DEBUG'] = getConfig($gameReady, 'DEBUG');
    if ($_SESSION['DEBUG'] == true){
        echo "The game is ready.<br />";
    }
}

if (
    isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
    && isset($_SESSION['username'])
) {    
    if ($_SESSION['DEBUG'] == true){
        echo "Redirect the user to a logged-in page.<br />";
    }

    // Redirect the user to a logged-in page
    header('Location: '.'/RPGConquestGame/base/accueil.php');
    exit();
}

if (
    isset($_POST['username'])
    && isset($_POST['passwd'])
) {
    $_SESSION['username'] = $_POST['username'];
    $passwd = $_POST['passwd'];
    if ($_SESSION['DEBUG'] == true){
        echo "Test  for : <br />";
        echo(" _POST[username] : ". $_POST['username']."; username: " . $_SESSION['username'] . "<br />");
        echo("passwd: " . $passwd . "<br />");
    }
    try {
        // SQL query to select username from the players table
        $sql = "SELECT id, is_privileged FROM players WHERE username = '".$_SESSION['username']."' AND passwd ='$passwd'";
        if ($_SESSION['DEBUG'] == true){
            echo "search SQL: $sql <br\>";
        }
        
        // Prepare and execute SQL query
        $stmt = $gameReady->prepare($sql);
        $stmt->execute();
        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Output the result
        if ($result) {
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['is_privileged'] = $result['is_privileged'];
            $_SESSION['logged_in'] = true;
            if ($_SESSION['DEBUG'] == true){
                echo "ID: " . $_SESSION['user_id']. ", is_privileged: " . $_SESSION['is_privileged'];
            }
        
            // Get Controlers array
            $controlers = getControlers($gameReady, $_SESSION['user_id']);
            if (count($controlers) == 1) {
                $_SESSION['controler'] = $controlers[0]; 
            }

            // Redirect the user to a logged-in page
            header('Location: '.'/RPGConquestGame/base/accueil.php');
            exit();
        } else {
            $_SESSION['logged_in'] = false;
            echo "Login impossible for the player. <br/>";
            echo "No matching record found.";
        }
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Get player failed: " . $e->getMessage()."<br/>";
        exit();
    }
}
?>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPGConquestGame</title>
    <style>
        <?php include_once  '../base/style.css'; ?>
    </style>
</head>
<body>
<div class="header">
    <h1>RPGConquestGame</h1>
</div>
<div class="content flex">
    <form action="login_form.php" method="post">
        <h3> Please log in : </H3> 
    <p>Username: <input type="text" name="username" /></p>
    <p>Password: <input type="password" name="passwd" /></p>
    <input type="submit" name="submit" value="Submit" />
    </form>
</div>