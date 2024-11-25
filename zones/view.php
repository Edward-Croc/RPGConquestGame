<?php
    $zones = getZonesArray($gameReady);
?>

<div class="zones">
    <h2>Zones</h2>
    <!-- Add content for zones here -->
    <?php
        // Display select list of controllers
        foreach ($zones as $zone) {
            echo "<h4>".$zone['name']."</h4>";
            echo var_export($zone, true);
        }
        ?>
</div>