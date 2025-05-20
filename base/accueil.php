<?php
$pageName = 'accueil';

require_once '../base/base_php.php';

if (
    !empty($_GET['controller_id'])
) {
    // GET CONTROLLER_ID and controllers list so page does not fail.
    if ($_SESSION['DEBUG'] == true) echo "_GET['controller_id']:". var_export($_GET['controller_id'], true).'<br/><br/>';
    $controllers = getcontrollers($gameReady, NULL, $_GET['controller_id']);
    if ($_SESSION['DEBUG'] == true) echo "controllers:". var_export($controllers, true).'<br/><br/>';
    $_SESSION['controller'] =  $controllers[0];
    $controller_id = $controllers[0]['id'];
}

require_once '../base/base_html.php';

$intro = getConfig($gameReady, 'PRESENTATION');
echo sprintf("<div class='intro'> %s </div>", $intro);

require_once '../controllers/view.php';
?>
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
        var selectedcontroller = controllers.find(function(controllers) {
            return controllers.id == controllerId;
        });

        // Display controller details
        document.getElementById('controllerDetails').style.display = 'block';
        document.getElementById('controllerDetails').innerHTML = "<h3>controller Details</h3>" +
            "<p>Name: " + selectedcontroller.firstname + " " + selectedcontroller.lastname +
            ", ID: " + selectedcontroller.id +
            ", Faction Name: " + selectedcontroller.faction_name + "</p>";
    });
</script>

</body>
</html>
