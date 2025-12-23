<?php
if ( !isset($_SESSION['DEBUG']) ){
    session_start(); // Start the session
    $_SESSION['DEBUG'] = false;
    $_SESSION['DEBUG_REPORT'] = false;
}

require_once '../BDD/db_connector.php';
require_once '../controllers/functions.php';


/**
 *  Extract configuration value from the database by key
 * copy of getConfig function from basePHP.php because the function is unavailable here
 *
 * @param PDO $pdo : database connection
 * @param string $configName : configuration key
 * 
 * @return string|null value
 */
function getConfig($pdo, $configName) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try{
        $stmt = $pdo->prepare("SELECT value
            FROM {$prefix}config
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
    header(sprintf('Location: /%s/base/accueil.php', $_SESSION['FOLDER']));
    exit();
}

if (
    isset($_POST['username'])
    && isset($_POST['passwd'])
) {
    $_SESSION['username'] = strtolower(trim($_POST['username']));
    $passwd = strtolower(trim($_POST['passwd']));

    try {
        // SQL query to select username from the players table
        // Prepare and execute SQL query
        $prefix = $_SESSION['GAME_PREFIX'];
        $stmt = $gameReady->prepare("SELECT id, is_privileged FROM {$prefix}players WHERE username = :username AND passwd = :passwd");
        $stmt->bindParam(':username', $_SESSION['username'], PDO::PARAM_STR);
        $stmt->bindParam(':passwd', $passwd, PDO::PARAM_STR);
        $stmt->execute();
        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Output the result
        if ($result) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $result['id'];
            $_SESSION['is_privileged'] = $result['is_privileged'];
            $_SESSION['logged_in'] = true;
            if ($_SESSION['DEBUG'] == true){
                echo "ID: " . $_SESSION['user_id']. ", is_privileged: " . $_SESSION['is_privileged'];
            }

            // Get controllers array
            $controllers = getControllers($gameReady, $_SESSION['user_id'], null, false);
            if (count($controllers) == 1) {
                $_SESSION['controller'] = $controllers[0];
            }

            // Redirect the user to a logged-in page
            header(sprintf('Location: /%s/base/accueil.php', $_SESSION['FOLDER']));
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
    <form action="loginForm.php" method="post">
        <h3> Please log in : </H3>
    <p>Username: <input type="text" name="username" /></p>
    <p>Password: <input type="password" name="passwd" /></p>
    <input type="submit" name="submit" value="Submit" />
    </form>
</div>