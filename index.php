<?php
session_start(); // Start the session

require_once './basePHP.php';

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

require_once './baseHTML.php';

?>

<div class="content flex">
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
