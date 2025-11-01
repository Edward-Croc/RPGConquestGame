<?php
$pageName = 'accueil';

require_once '../base/basePHP.php';

if (
    !empty($_GET['controller_id'])
) {
    $debug = strtolower($_SESSION['DEBUG']) === 'true';
    // GET CONTROLLER_ID and controllers list so page does not fail.
    if ($debug) echo "_GET['controller_id']:". var_export($_GET['controller_id'], true).'<br/><br/>';
    $controllers = getControllers($gameReady, NULL, $_GET['controller_id']);
    if ($debug) echo "controllers:". var_export($controllers, true).'<br/><br/>';
    $_SESSION['controller'] =  $controllers[0];
    $controller_id = $controllers[0]['id'];
}

require_once '../base/baseHTML.php';

$intro = getConfig($gameReady, 'PRESENTATION');
echo sprintf("<div class='intro'> %s </div>", $intro);

require_once '../controllers/view.php';
?>
<div class="content flex">
    <div>
        <?php require_once '../zones/view.php'; ?>
    </div> <div>
        <?php require_once '../workers/viewAll.php'; ?>
    </div>
</div>

<script>
    // Function to show controller details when a controller is selected from the list
    document.getElementById('controllerSelect').addEventListener('change', function() {
        var controllerId = this.value;
        var controllers = <?php echo json_encode($controllers); ?>;

        // Find the selected controller in the array
        var selectedController = controllers.find(function(controllers) {
            return controllers.id == controllerId;
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
