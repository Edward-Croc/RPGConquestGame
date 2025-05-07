<?php
$pageName = 'accueil';

require_once '../base/base_php.php';

if (
    !empty($_GET['controler_id'])
) {
    // GET CONTROLER_ID and controlers list so page does not fail.
    if ($_SESSION['DEBUG'] == true) echo "_GET['controler_id']:". var_export($_GET['controler_id'], true).'<br/><br/>';
    $controlers = getControlers($gameReady, NULL, $_GET['controler_id']);
    if ($_SESSION['DEBUG'] == true) echo "controlers:". var_export($controlers, true).'<br/><br/>';
    $_SESSION['controler'] =  $controlers[0];
    $controler_id = $controlers[0]['id'];
}

require_once '../base/base_html.php';

$intro = getConfig($gameReady, 'PRESENTATION');
echo sprintf("<div class='intro'> %s </div>", $intro);

$controlers = getControlers($gameReady, $_SESSION['user_id']);
// Show factions if Multiple controlers are available
if (count($controlers) > 1) {
?>
    <div class="factions">
        <h2>Factions</h2>
        <form action="/RPGConquestGame/base/accueil.php" method="GET">
            <?php
            echo showControlerSelect($controlers);
            ?>
        <input type="submit" name="chosir" value="Choisir" />
        </form>
    <!-- Display Controler details section (initially hidden) changed by the select action-->
    <div id='ControlerDetails' style='display: none;'>";
    </div>
</div>
<?php
}
?>
<div class="content flex">
    <?php require_once '../workers/view.php'; ?>
    <?php require_once '../zones/view.php'; ?>
</div>

<script>
    // Function to show Controler details when a Controler is selected from the list
    document.getElementById('controlerSelect').addEventListener('change', function() {
        var controlerId = this.value;
        var controlers = <?php echo json_encode($controlers); ?>;

        // Find the selected Controler in the array
        var selectedControler = controlers.find(function(controlers) {
            return controlers.id == controlerId;
        });

        // Display Controler details
        document.getElementById('ControlerDetails').style.display = 'block';
        document.getElementById('ControlerDetails').innerHTML = "<h3>Controler Details</h3>" +
            "<p>Name: " + selectedControler.firstname + " " + selectedControler.lastname +
            ", ID: " + selectedControler.id +
            ", Faction Name: " + selectedControler.faction_name + "</p>";
    });
</script>

</body>
</html>
