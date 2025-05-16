<?php

    $zonesArray = getZonesArray($gameReady);
    $showZoneSelect = showZoneSelect($zonesArray, FALSE, FALSE);
    $mapFile = getConfig($gameReady, 'map_file');
    $mapAlt = getConfig($gameReady, 'map_alt');
    $imgString = '<img src="/RPGConquestGame/img/'.$mapFile.'" alt="'.$mapAlt.'" style="max-width:100%; height:auto;">';

    $controlers = getControlers($gameReady, $_SESSION['user_id']);
    ?>

    <div class="factions">
        <h2>Factions</h2>
        <?php
        // Show factions if Multiple controlers are available
        if (count($controlers) > 1) {
        ?>
            <form action="/RPGConquestGame/base/accueil.php" method="GET">
                <?php
                echo showControlerSelect($controlers);
                ?>
            <input type="submit" name="chosir" value="Choisir" />
            </form>
        <!-- Display Controler details section (initially hidden) changed by the select action-->
        <div id='ControlerDetails' style='display: none;'>";
        </div>
    <?php
    }

    if ( isset($_SESSION['controler']) ) {
        $controlers = getControlers($gameReady, NULL, $_GET['controler_id'])[0];
        echo sprintf (
            "Vous êtes %s %s (%s) de lea faction %s (%s)",
            $controlers['firstname'],
            $controlers['lastname'],
            $controlers['id'],
            $controlers['faction_name'],
            $controlers['fake_faction_name']
        );

        echo sprintf('<div ><form action="/RPGConquestGame/controlers/action.php" method="GET">
            <input type="hidden" name="controler_id" value=%1$s>
            <h3>Actions : </h3> <p>',
            $controlers['id']
        );

        $base = hasBase($gameReady, $controlers['id']);
        if (empty($base)) {
            echo sprintf(
                '<input type="submit" name="createBase" value="%1$s" class="worker-action-btn"> %2$s <br />',
                getConfig($gameReady, 'textControlerActionCreateBase'),
                $showZoneSelect
            );
        } else {
            echo sprintf('%s', var_export($base, true));
        }
        echo '
        </p>
        </form>';
  } ?>
    </div>




