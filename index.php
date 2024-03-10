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

// Function to get controllers and return as an array
function getControllersArray($pdo, $player_id = NULL) {
    $controllersArray = array();

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

    // Store controllers in the array
    foreach ($controllers as $controller) {
        $controllersArray[] = $controller;
    }

    return $controllersArray;
}

// Function to display controller details
/* function displayControllerDetails($controller) {
    echo "<h3>Controller Details</h3>";
    echo "<p>Name: " . $controller['firstname'] . " " . $controller['lastname'] . "</p>";
    echo "<p>ID: " . $controller['ID'] . "</p>";
    echo "<p>Faction Name: " . $controller['faction_name'] . "</p>";
} */

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
        <?php
            if ($_SESSION['is_privileged'] == true){
                echo '<a href="admin.php" class="admin-btn">Configuration</a>';
            }
        ?>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>
<div class="content">
    <div class="zones">
        <h2>Zones</h2>
        <!-- Add content for zones here -->
    </div>
    <div class="factions">
        <h2>Factions</h2>
        <!-- Add content for factions here -->
        <?php
        // Get controllers array
        $controllers = getControllersArray($gameReady, $_SESSION['userid']);

        // Display select list of controllers
        echo "<select id='controllerSelect'>";
        echo "<option value=''>Select Controller</option>";
        foreach ($controllers as $controller) {
            echo "<option value='" . $controller['id'] . "'>" . $controller['firstname'] . " " . $controller['lastname'] . "</option>";
        }
        echo "</select>";

        // Display controller details section (initially hidden)
        echo "<div id='controllerDetails' style='display: none;'>";
        echo "</div>";
        ?>
    </div>
</div>

<script>
    // Function to show controller details when a controller is selected from the list
    document.getElementById('controllerSelect').addEventListener('change', function() {
        var controllerId = this.value;
        var controllers = <?php echo json_encode($controllers); ?>;

        // Find the selected controller in the array
        var selectedController = controllers.find(function(controller) {
            return controller.id == controllerId;
        });

        // Display controller details
        document.getElementById('controllerDetails').style.display = 'block';
        document.getElementById('controllerDetails').innerHTML = "<h3>Controller Details</h3>" +
            "<p>Name: " + selectedController.firstname + " " + selectedController.lastname + "</p>" +
            "<p>ID: " + selectedController.id + "</p>" +
            "<p>Faction Name: " + selectedController.faction_name + "</p>";
    });
</script>

</body>
</html>
