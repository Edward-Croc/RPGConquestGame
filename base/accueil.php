<?php
session_start(); // Start the session
$pageName = 'accueil';

require_once '../base/base_php.php';

if (
    isset($_GET['controlerSelect'])
) {
    echo 'controlerSelect';
    echo var_export($_POST['controlerSelect'], true);
    $controler = getControler($gameReady, $_POST['controlerSelect']);
    echo var_export($controler, true);
   // $_SESSION['controler']
}

require_once '../base/base_html.php';

$controllers = getControllersArray($gameReady, $_SESSION['user_id']);
if (count($controllers) > 1) {
?>
    <div class="factions">
        <h2>Factions</h2>
        <!-- Add content for factions here -->
        <form action="/RPGConquestGame/base/accueil.php" method="GET" name="selectfaction">
        <select id='controlerSelect' form="selectfaction">
            <option value=''>Select Controller</option>
            <?php
            // Display select list of controllers
            foreach ($controllers as $controller) {
                echo "<option value='" . $controller['id'] . "'>" . $controller['firstname'] . " " . $controller['lastname'] . "</option>";
            }
            ?>
        </select>
        <input type="submit" name="chosir" value="Choisir" />
        </form>
<?php 
    }
?>
        <!-- Display controller details section (initially hidden) -->
        <div id='controllerDetails' style='display: none;'>";
        </div>
</div>
<div class="content flex">
    <?php require_once '../workers/view.php'; ?>
    <?php require_once '../zones/view.php'; ?>
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
            "<p>Name: " + selectedController.firstname + " " + selectedController.lastname +
            ", ID: " + selectedController.id +
            ", Faction Name: " + selectedController.faction_name + "</p>";
    });
</script>

</body>
</html>
