<?php
$pageName = 'accueil';

require_once '../base/base_php.php';

if (
    !empty($_GET['controler'])
) {
    
    if ($_SESSION['DEBUG'] == true) echo "_GET['controler']:". var_export($_GET['controler'], true).'<br/><br/>';
    $controler = getControlers($gameReady, NULL, $_GET['controler']);
    if ($_SESSION['DEBUG'] == true) echo "controler:". var_export($controler, true).'<br/><br/>';
    $_SESSION['controler'] =  $controler[0];
}

require_once '../base/base_html.php';

$Controlers = getControlers($gameReady, $_SESSION['user_id']);
if (count($Controlers) > 1) {
?>
    <div class="factions">
        <h2>Factions</h2>
        <!-- Add content for factions here -->
        <form action="/RPGConquestGame/base/accueil.php" method="GET">
        <select id='controlerSelect' name='controler'>
            <option value=''>Select Controler</option>
            <?php
            // Display select list of Controlers
            foreach ($Controlers as $Controler) {
                echo "<option value='" . $Controler['id'] . "'>" . $Controler['firstname'] . " " . $Controler['lastname'] . "</option>";
            }
            ?>
        </select>
        <input type="submit" name="chosir" value="Choisir" />
        </form>
<?php 
    }
?>
        <!-- Display Controler details section (initially hidden) -->
        <div id='ControlerDetails' style='display: none;'>";
        </div>
</div>
<div class="content flex">
    <?php require_once '../workers/view.php'; ?>
    <?php require_once '../zones/view.php'; ?>
</div>

<script>
    // Function to show Controler details when a Controler is selected from the list
    document.getElementById('ControlerSelect').addEventListener('change', function() {
        var ControlerId = this.value;
        var Controlers = <?php echo json_encode($Controlers); ?>;

        // Find the selected Controler in the array
        var selectedControler = Controlers.find(function(Controler) {
            return Controler.id == ControlerId;
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
