<?php
    $zones = getZonesArray($gameReady);
?>

<div class="zones">
    <h2>Zones</h2>
    <!-- Add content for zones here -->
    <?php
        // Display select list of Controlers
        foreach ($zones as $zone) {
            echo sprintf('<h4> %s (%s)</h4><i>%s</i>', $zone['name'], $zone['id'], $zone['description']);
        }
    ?>
</div>