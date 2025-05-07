<?php
    $zones = getZonesArray($gameReady);
    $mapFile = getConfig($gameReady, 'map_file');
    $mapAlt = getConfig($gameReady, 'map_alt');
    $imgString = '<img src="/RPGConquestGame/img/'.$mapFile.'" alt="'.$mapAlt.'" style="max-width:100%; height:auto;">';
?>

<div class="zones">
    <h2>Zones</h2>
    <h4 onclick="toggleDescription('carte')" style="cursor: pointer;"> Carte </h4>
                <i id="description-carte" style="display: none;"><?php echo $imgString; ?></i>

    <!-- Add content for zones here -->
    <?php
        // Display list of Zones
        foreach ($zones as $zone) {
            echo sprintf('
                <h4 onclick="toggleDescription(%2$s)" style="cursor: pointer;"> %1$s (%2$s)</h4>
                <i id="description-%2$s" style="display: none;">%3$s</i>
            ',
            $zone['name'], $zone['id'], $zone['description']);
            // TODO: show claimed zone
            // TODO: show found locations
            // TODO: show location actions
        }
    ?>
</div>