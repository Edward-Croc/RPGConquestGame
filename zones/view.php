<?php
    $zones = getZonesArray($gameReady);
?>

<div class="zones">
    <h2>Zones</h2>
    <!-- Add content for zones here -->
    <?php
        // Display select list of Controlers
        foreach ($zones as $zone) {
            echo sprintf('
                <h4 onclick="toggleDescription(%2$s)" style="cursor: pointer;"> %1$s (%2$s)</h4>
                <i id="description-%2$s" style="display: none;">%3$s</i>
            ',
            $zone['name'], $zone['id'], $zone['description']);
        }
    ?>
</div>