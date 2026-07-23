<?php
if ( !isset($_SESSION['DEBUG']) ){
    session_start(); // Start the session
    $_SESSION['DEBUG'] = false;
    $_SESSION['DEBUG_REPORT'] = false;
}

$pageName = 'index';

// Set BEFORE errorLog.php require so its ini_set('error_log', ...) picks it up.
$GLOBALS['LOG_PATH'] = __DIR__ . '/var/logs/game_errors.log';

require_once './base/errorLog.php';
require_once './BDD/db_connector.php';

/**
 * get value from Config by name
 *
 * @param PDO $pdo : database connection
 * @param string $configName : config key to look up
 *
 * @return string|null : value, or NULL on missing key / PDO error
 */
function getConfig(PDO $pdo, string $configName): string|null {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with configName : ' . $configName, [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    try{
        $stmt = $pdo->prepare("SELECT value
            FROM {$prefix}config
            WHERE name = :configName
        ");
        $stmt->execute([':configName' => $configName]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : NULL;
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT value failed : ' . $e->getMessage(), ['configName' => $configName], 'error');
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
    if (strtolower(getConfig($gameReady, 'DEBUG')) == 'true') {
        $_SESSION['DEBUG'] = true;
    }
    if (strtolower(getConfig($gameReady, 'DEBUG_REPORT')) == 'true') {
        $_SESSION['DEBUG_REPORT'] = true;
    }
    $gameTitle = getConfig($gameReady, 'TITLE');
    if ($_SESSION['DEBUG'] == true){
        echo "The game is ready.<br />";
        echo "The gameTitle is : '$gameTitle'.<br />";
    }
}

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect the user to the login page if not logged in
    header('Location: connection/loginForm.php');
    exit();
}
    // Redirect the user to the login page if not logged in
    header('Location: base/accueil.php');
    exit();
