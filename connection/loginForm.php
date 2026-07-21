<?php
ob_start(); // Buffer output so header() redirects work even when DEBUG echoes are emitted
if ( !isset($_SESSION['DEBUG']) ){
    session_start(); // Start the session
    $_SESSION['DEBUG'] = false;
    $_SESSION['DEBUG_REPORT'] = false;
}

require_once '../base/version.php';
require_once '../base/errorLog.php';
require_once '../BDD/db_connector.php';
require_once '../controllers/functions.php';

// $GLOBALS['DEBUG_LOG_SECTIONS'][] = 'login_form_page';  // uncomment to log DEBUG events from this page

/**
 * Extract configuration value from the database by key.
 * Copy of getConfig from basePHP.php because that file is not loaded here
 * (login page bootstraps before the full session is wired).
 *
 * @param PDO $pdo : database connection
 * @param string $configName : configuration key
 *
 * @return string|null : stored value, or NULL on missing key / PDO error
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
    game_error_log('login_form_page', 'gameReady() returned falsy — DB configuration missing or unreachable', [], 'warning');
    echo "The game is not ready. Please check DB Configuration and Setup. <br />";
    exit();
}else{
    if (strtolower(getConfig($gameReady, 'DEBUG')) == 'true') {
        $_SESSION['DEBUG'] = true;
    }
    $gameTitle = getConfig($gameReady, 'TITLE');
    if ($_SESSION['DEBUG'] == true){
        echo "The game is ready.<br />";
        echo "The gameTitle is : '$gameTitle'.<br />";
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
            game_error_log('login_form_page', 'login rejected : no matching player row for submitted credentials', ['username' => $_SESSION['username']], 'warning');
            echo "Login impossible for the player. <br/>";
            echo "No matching record found.";
        }
    } catch (PDOException $e) {
        game_error_log('login_form_page', 'SELECT player failed : ' . $e->getMessage(), ['username' => $_SESSION['username']], 'error');
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
    <h1>RPGConquestGame : <?php echo $gameTitle ?> </h1>
</div>
<!-- Sidebar MENU -->
<div id="sidebar" class="sidebar">
    <a href="javascript:void(0)" class="closebtn" onclick="toggleSidebar()">&times;</a>
    <?php $folder = $_SESSION['FOLDER'] ?? ''; ?>
    <a href="/<?php echo htmlspecialchars($folder); ?>/base/systemPresentation.php">Le Système</a>
    <a href="/<?php echo htmlspecialchars($folder); ?>/connection/loginForm.php" class="select">Login</a>
</div>
<span class="openbtn" onclick="toggleSidebar()"> ☰ </span>
<?php require_once '../base/baseScript.php'; ?>
<div class="content flex">
    <form action="loginForm.php" method="post">
        <h3> Please log in : </H3>
    <p>Username: <input type="text" name="username" /></p>
    <p>Password: <input type="password" name="passwd" /></p>
    <input type="submit" name="submit" value="Submit" />
    </form>
</div>
<footer class="app-footer">
    <span class="app-version">v<?php echo htmlspecialchars(defined('APP_VERSION') ? APP_VERSION : '?'); ?></span>
</footer>
</body>
</html>