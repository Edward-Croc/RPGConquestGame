<?php
    $zones = getZonesArray($gameReady);
    $imgString = sprintf('<img src="/%s/img/%s" alt="%s" style="max-width:%s; height:auto;">',  $_SESSION['FOLDER'], getConfig($gameReady, 'map_file'), getConfig($gameReady, 'map_alt'), '100%');
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
                    %6$s
                    <i>%3$s</i>
                    %5$s
                </div>
                ',
                $zone['name'], $zone['zone_id'],
                $descritpion,
                (!empty($zone['controller_id']))
                    ? sprintf('sous la banière de %s %s', $zone['firstname'], $zone['lastname'])
                    : '',
                !empty($_SESSION['controller']['id']) ? showcontrollerKnownSecrets($gameReady, $_SESSION['controller']['id'], $zone['zone_id']) : '',
                (!empty($_SESSION['controller']['id']) && $zone['holder_controller_id'] == $_SESSION['controller']['id'])? '<b>Sous notre controle <br></b>' : ''
            );
        }
    ?>
</div>