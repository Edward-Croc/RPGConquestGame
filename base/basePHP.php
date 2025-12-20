<?php
if ( !isset($_SESSION['DEBUG']) ){
    session_start(); // Start the session
    $_SESSION['DEBUG'] = false;
}

if ($_SESSION['DEBUG'] == true ){
    echo sprintf("_SESSION %s <br />", var_export($_SESSION, true));
}

require_once '../BDD/db_connector.php';
require_once '../controllers/functions.php';
require_once '../mechanics/functions.php';
require_once '../powers/functions.php';
require_once '../ressources/functions.php';
require_once '../workers/functions.php';
require_once '../zones/functions.php';

/**
 *  Extract configuration value from the database by key
 *
 * @param PDO $pdo : database connection
 * @param string $configName : configuration key
 * 
 * @return string|null $value 
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
        echo  __FUNCTION__."(): $configName failed: " . $e->getMessage()."<br />";
        return NULL;
    }
}

/**
 *  Extract elements of mechanics from database
 *
 * @param PDO $pdo : database connection
 *
 * @return array|null : $mechanics
 */
function getMechanics($pdo) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try{
        $stmt = $pdo->query("SELECT * FROM {$prefix}mechanics");
        $mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($_SESSION['DEBUG'] == true){
            echo "mechanics :  <br />";
            print_r ($mechanics);
            echo "<br />";
        }
        return $mechanics[0];
    } catch (PDOException $e) {
        echo __FUNCTION__."(): failed: " . $e->getMessage()."<br />";
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
    // Set the session debug status
    $_SESSION['DEBUG'] = false;
    if (strtolower(getConfig($gameReady, 'DEBUG')) == 'true') {
        $_SESSION['DEBUG'] = true;
    }
    $_SESSION['DEBUG_REPORT'] = false;
    if (strtolower(getConfig($gameReady, 'DEBUG_REPORT')) == 'true') {
        $_SESSION['DEBUG_REPORT'] = true;
    }
    $_SESSION['DEBUG_ATTACK'] = false;
    if (strtolower(getConfig($gameReady, 'DEBUG_ATTACK')) == 'true') {
        $_SESSION['DEBUG_ATTACK'] = true;
    }
    $_SESSION['DEBUG_TRANSFORM'] = false;
    if (strtolower(getConfig($gameReady, 'DEBUG_TRANSFORM')) == 'true') {
        $_SESSION['DEBUG_TRANSFORM'] = true;
    }

    // Set game title
    $gameTitle = getConfig($gameReady, 'TITLE');
    if ($_SESSION['DEBUG'] == true){
        echo "The game is ready.<br />";
        echo "The gameTitle is : '$gameTitle'.<br />";
    }

    // Get mechanics values
    $mechanics = getMechanics($gameReady);
}

if ($_SESSION['DEBUG'] == true){
    // print debug values
    echo "Debug : ".$_SESSION['DEBUG'].";  ID: " . $_SESSION['user_id']. ", is_privileged: '" . $_SESSION['is_privileged']. "' <br />";
    echo "Turn : ".$mechanics['turncounter']."; gamestate : '".$mechanics['gamestate']. "' <br />";
}


?>
