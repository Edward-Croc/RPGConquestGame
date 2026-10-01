<?php

ob_start(); // Buffer output so header() redirects work even when warnings are emitted
require_once __DIR__ . '/session.php';
startGameSession();
if (!isset($_SESSION['DEBUG'])) {
    $_SESSION['DEBUG'] = false;
}

if ($_SESSION['DEBUG'] == true) {
    echo sprintf("_SESSION %s <br />", var_export($_SESSION, true));
}

// Set BEFORE errorLog.php require so its ini_set('error_log', ...) picks it up.
$GLOBALS['LOG_PATH'] = __DIR__ . '/../var/logs/game_errors.log';
// Where endTurn.php archives each turn narrative, listed by admin/admin_turn_reports.php.
$GLOBALS['TURN_REPORT_DIR'] = __DIR__ . '/../var/turn_reports';

require_once '../base/version.php';
require_once '../base/errorLog.php';
require_once '../BDD/db_connector.php';
require_once '../controllers/functions.php';
require_once '../mechanics/functions.php';
require_once '../powers/functions.php';
require_once '../ressources/functions.php';
require_once '../workers/functions.php';
require_once '../zones/functions.php';

/**
 * True when this deployment is declared as a test environment.
 *
 * @return bool : true when the markup may carry an agent's raw state
 */
function isTestEnvironment(): bool
{
    return ($_SESSION['ENV'] ?? 'production') === 'test';
}

/**
 * Extract configuration value from the database by key.
 *
 * @param PDO $pdo : database connection
 * @param string $configName : configuration key
 *
 * @return string|null : stored value or NULL on error / missing key
 */
function getConfig(PDO $pdo, string $configName): string|null
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with configName : ' . $configName, [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare("SELECT value
            FROM {$prefix}config
            WHERE name = :configName
        ");
        $stmt->execute([':configName' => $configName]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : null;
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'PDO error : ' . $e->getMessage(), ['configName' => $configName], 'error');
        return null;
    }
}

/**
 * Extract elements of mechanics from database.
 *
 * @param PDO $pdo : database connection
 *
 * @return array|null : first mechanics row (assoc) or NULL on error / empty
 */
function getMechanics(PDO $pdo): array|null
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START', [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->query("SELECT * FROM {$prefix}mechanics");
        $mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        game_error_log(__FUNCTION__, 'DONE', ['mechanics' => $mechanics], 'debug');
        return $mechanics[0] ?? null;
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'PDO error : ' . $e->getMessage(), [], 'error');
        return null;
    }
}

// Call the gameReady() function from dbConnector.php
$gameReady = gameReady();

// Use the return value
if (!$gameReady) {
    echo "The game is not ready. Please check DB Configuration and Setup. <br />";
    http_response_code(503);
    exit();
} else {
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
    game_error_log('basePHP', 'The game is ready.', [], 'debug');
    game_error_log('basePHP', 'The gameTitle is : '.$gameTitle, [], 'debug');

    // Get mechanics values
    $mechanics = getMechanics($gameReady);
    // getMechanics returns null on a PDO error
    if (!isset($mechanics['turncounter'])) {
        game_error_log('basePHP', 'unreadable turncounter', ['mechanics' => $mechanics], 'error');
        echo "The game is not ready. Please check DB Configuration and Setup. <br />";
        http_response_code(503);
        exit();
    }
}

// print debug values
game_error_log(
    'basePHP',
    'Debug ',
    [
        'debug' => $_SESSION['DEBUG'],
        'ID' => $_SESSION['user_id'] ?? 'null',
        'is_privileged' => $_SESSION['is_privileged'] ?? 'null',
    ],
    'debug'
);
game_error_log('basePHP', 'Turn : '.$mechanics['turncounter'].'; gamestate : '.$mechanics['gamestate'], ['mechanics' => $mechanics], 'debug');

game_error_log('basePHP', 'END', [], 'debug');
