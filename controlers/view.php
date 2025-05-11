<?php

    $zones = getZonesArray($gameReady);
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

    if ( isset($_SESSION['controler']) )
    echo sprintf ("Vous êtes %s %s (%s)<br /> les %s ", $_SESSION['controler']['firstname'], $_SESSION['controler']['lastname'], $_SESSION['controler']['id'], $_SESSION['controler']['faction_name']);

    ?>
    </div>




