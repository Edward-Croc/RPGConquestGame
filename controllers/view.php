<?php

    $zonesArray = getZonesArray($gameReady);
    $showZoneSelect = showZoneSelect($gameReady, $zonesArray, false, false);

    $controllers = getControllers($gameReady, $_SESSION['user_id'], null, false);
    $debug = false;
    if (strtolower(getConfig($gameReady, 'DEBUG')) == 'true') $debug = true;
    echo '<div class="factions">';
        // Show factions if Multiple controllers are available
        if (count($controllers) > 1) {
            echo '<h2>Factions</h2>';
            echo sprintf('
                <form action="/%s/base/accueil.php" method="GET">
                    %s
                <input type="submit" name="chosir" value="Choisir" />
                </form>
                <!-- Display controller details section (initially hidden) changed by the select action-->
                <div id="controllerDetails" style="display: none;"> </div>', 
                $_SESSION['FOLDER'],
                showControllerSelect($controllers)
            );
        }
        if ( isset($_SESSION['controller']) ) {
            $controllers = getControllers($gameReady, NULL, $_SESSION['controller']['id'])[0];
            echo sprintf ('<h2>Votre Faction </h2>
                Vous êtes %1$s %2$s (réseau %3$s) de la faction %4$s (%5$s)<br>
                %6$s %7$s
                <div ><form action="/%8$s/controllers/action.php" method="GET">
                <input type="hidden" name="controller_id" value=%3$s>
                <h3>Votre Base : </h3> <p>',
                $controllers['firstname'],
                $controllers['lastname'],
                $controllers['id'],
                $controllers['faction_name'],
                $controllers['fake_faction_name'],
                !empty($controllers['url']) ? '<button onclick="window.open(\''.$controllers['url'].'\', \'_blank\')"> This is your url </button><br>' : '',
                !empty($controllers['story']) ? $controllers['story'] : '',
                $_SESSION['FOLDER']
            );
            $bases = hasBase($gameReady, $controllers['id']);
            if (empty($bases)) {
                echo sprintf(
                    '<input type="submit" name="createBase" value="%1$s" class="worker-action-btn"> %2$s <br />',
                    getConfig($gameReady, 'textControllerActionCreateBase'),
                    $showZoneSelect
                );
            } else {
                if ($debug) echo sprintf('<p> %s </p>', var_export($bases, true));
                $textControllerActionMoveBase = getConfig($gameReady, 'textControllerActionMoveBase');
                echo '<p>';
                foreach ($bases as $base ){
                    echo sprintf('
                        <input type="hidden" name="base_id" value=%3$s>
                        Votre %4$s à %5$s ne sera découvert que sur une valeur d’enquête de %6$s ou plus, si découvert, le texte suivant sera présenté à l’enquêteur : <br /> %7$s<br />
                        <input type="submit" name="moveBase" value="%1$s" class="controller-action-btn"> %2$s <br /><br />',
                        $textControllerActionMoveBase,
                        $showZoneSelect,
                        $base['id'],
                        $base['name'],
                        $base['zone_name'],
                        $base['discovery_diff'],
                        $base['description']
                    );
                }
            }
            // Incoming attacks this turn
            $incomingStmt = $gameReady->prepare("
            SELECT * FROM location_attack_logs 
            WHERE target_controller_id = :controller_id 
            AND turn = :turn
            ORDER BY id DESC
            ");
            $incomingStmt->execute([
            'controller_id' => $_SESSION['controller']['id'],
            'turn' => $mechanics['turncounter']
            ]);
            $incomingAttacks = $incomingStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($incomingAttacks)) {
                echo "<div class='alert alert-danger'>";
                echo "<strong>Alerte !</strong> Votre base a été attaquée ce tour !<ul>";
                foreach ($incomingAttacks as $attack) {
                    echo sprintf( "<li> %s</li>", htmlspecialchars($attack['target_result_text']));
                }
                echo "</ul></div>";
            }
            echo '</p>
            <h3>Les lieux : </h3>
            <p>';
            $showAttackableControllerKnownLocations = showAttackableControllerKnownLocations($gameReady, $controllers['id']);
            if($showAttackableControllerKnownLocations !== NULL && hasBase($gameReady, $controllers['id'])) {
                echo sprintf('<form action="/%3$s/controllers/action.php" method="GET">
                        <input type="hidden" name="controller_id" value=%1$s>
                        <input type="submit" name="attackLocation" value="Mener une équipe d\'attaque vers : " class="controller-action-btn"> %2$s',
                        $controllers['id'],
                        $showAttackableControllerKnownLocations,
                        $_SESSION['FOLDER']
                ); 
            } else echo 'Aucun lieu connu attaquable.';
            echo '</p>';

            // Outgoing attacks this turn
            $outgoingStmt = $gameReady->prepare("
            SELECT * FROM location_attack_logs 
            WHERE attacker_id = :controller_id 
            AND turn = :turn
            ORDER BY id DESC
            ");
            $outgoingStmt->execute([
            'controller_id' =>  $_SESSION['controller']['id'],
            'turn' => $mechanics['turncounter']
            ]);
            $outgoingAttacks = $outgoingStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($outgoingAttacks)) {
                echo "<div class='alert alert-info'>";
                echo "<strong>Vos attaques ce tour :</strong><ul>";
                foreach ($outgoingAttacks as $attack) {
                    echo "<li>". htmlspecialchars($attack['attacker_result_text']) . "</li>";
                }
                echo "</ul></div>";
            }

            echo '
            </p>
            </form>';
    }
    echo '</div>';

if (!empty($pageName) && $pageName == 'controllers_action')
    require_once '../workers/viewAll.php';
