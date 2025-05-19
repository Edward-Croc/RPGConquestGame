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
    <?php 
        // Display list of Zones
        foreach ($zones as $zone) {
            $descritpion = $zone['description'];
            echo sprintf('
                <h3 onclick="toggleDescription(%2$s)" style="cursor: pointer;"> %1$s (%2$s) %4$s </h3>
                <div id="description-%2$s" style="display: none;"> 
                    <i>%3$s</i>
                    %5$s
                </div>
                ',
                $zone['name'], $zone['zone_id'], 
                $descritpion,
                (!empty($zone['controler_id'])) 
                    ? sprintf('sous la banière de %s %s', $zone['firstname'], $zone['lastname'])
                    : '',
                showControlerKnownSecrets($gameReady, $_SESSION['controler']['id'], $zone['zone_id'])
            );
        }
    ?>
</div>