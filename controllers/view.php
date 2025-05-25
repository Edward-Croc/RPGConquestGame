<?php

    $zonesArray = getZonesArray($gameReady);
    $showZoneSelect = showZoneSelect($gameReady, $zonesArray, false, false);
    $mapFile = getConfig($gameReady, 'map_file');
    $mapAlt = getConfig($gameReady, 'map_alt');
    $imgString = '<img src="/RPGConquestGame/img/'.$mapFile.'" alt="'.$mapAlt.'" style="max-width:100%; height:auto;">';

    $controllers = getControllers($gameReady, $_SESSION['user_id']);
    $debug = false;
    if (strtolower(getConfig($gameReady, 'DEBUG')) == 'true') $debug = true;
    echo '<div class="factions">';
        // Show factions if Multiple controllers are available
        if (count($controllers) > 1) {
            echo '<h2>Factions</h2>';
            echo sprintf('
                <form action="/RPGConquestGame/base/accueil.php" method="GET">
                    %s
                <input type="submit" name="chosir" value="Choisir" />
                </form>
                <!-- Display controller details section (initially hidden) changed by the select action-->
                <div id="controllerDetails" style="display: none;"> </div>', 
                showControllerSelect($controllers)
            );
        }
        if ( isset($_SESSION['controller']) ) {
            $controllers = getControllers($gameReady, NULL, $_SESSION['controller']['id'])[0];
            echo sprintf ('<h2>Votre Faction </h2>
                Vous êtes %1$s %2$s (réseau %3$s) de la faction %4$s (%5$s)<br>
                %6$s %7$s
                <div ><form action="/RPGConquestGame/controllers/action.php" method="GET">
                <input type="hidden" name="controller_id" value=%3$s>
                <h3>Actions : </h3> <p>',
                $controllers['firstname'],
                $controllers['lastname'],
                $controllers['id'],
                $controllers['faction_name'],
                $controllers['fake_faction_name'],
                !empty($controllers['url']) ? '<button onclick="window.open(\''.$controllers['url'].'\', \'_blank\')"> This is your url </button><br>' : '',
                !empty($controllers['story']) ? $controllers['story'] : ''
            );
            $bases = hasBase($gameReady, $controllers['id']);
            if (empty($bases)) {
                echo sprintf(
                    '<input type="submit" name="createBase" value="%1$s" class="worker-action-btn"> %2$s <br />',
                    getConfig($gameReady, 'textcontrollerActionCreateBase'),
                    $showZoneSelect
                );
            } else {
                if ($debug) echo sprintf('<p> %s </p>', var_export($bases, true));
                $textcontrollerActionMoveBase = getConfig($gameReady, 'textcontrollerActionMoveBase');
                echo '<p>';
                foreach ($bases as $base ){
                    echo sprintf('
                        <input type="hidden" name="base_id" value=%3$s>
                        Votre %4$s à %5$s ne sera découvert que sur une valeur d’enquête de %6$s ou plus, si découvert, le texte suivant sera présenté à l’enquêteur : <br /> %7$s<br />
                        <input type="submit" name="moveBase" value="%1$s" class="controller-action-btn"> %2$s <br /><br />',
                        $textcontrollerActionMoveBase,
                        $showZoneSelect,
                        $base['id'],
                        $base['name'],
                        $base['zone_name'],
                        $base['discovery_diff'],
                        $base['description']
                    );
                }
            }
            echo '</p><p>';

            $showAttackablecontrollerKnownLocations = showAttackablecontrollerKnownLocations($gameReady, $controllers['id']);
            if($showAttackablecontrollerKnownLocations !== NULL)
                echo sprintf('<form action="/RPGConquestGame/controllers/action.php" method="GET">
                        <input type="hidden" name="controller_id" value=%1$s>
                        <input type="submit" name="attackLocation" value="Intéragir avec : " class="controller-action-btn"> %2$s',
                        $controllers['id'],
                        $showAttackablecontrollerKnownLocations
                ); 
            else echo 'Aucun lieu connu attaquable.';

        if (!empty($attackLocationResult['message']))
                echo sprintf('%s', $attackLocationResult['message']);
            echo '
            </p>
            </form>';
    }
    echo '</div>';

if (!empty($pageName) && $pageName == 'controllers_action')
    require_once '../workers/viewAll.php';
