<?php

    $zonesArray = getZonesArray($gameReady);
    $showZoneSelect = showZoneSelect($gameReady, $zonesArray, false, false);

    $controllersArray = array();
    $playerURL = null;

    if (isset( $_SESSION['user_id'])) {
        try{
            $sqlPlayers = sprintf("SELECT p.*
                FROM players p
                WHERE p.id = %s
            ", $_SESSION['user_id']);
            $stmtPlayers = $gameReady->prepare($sqlPlayers);
            $stmtPlayers->execute();
        } catch (PDOException $e) {
            echo sprintf("%s(): %s failed:  %s <br />",  __FUNCTION__, $e->getMessage(), $_SESSION['user_id']);
        }
        // Fetch the results
        $players = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);
        $playerURL = $players[0]['url'];
    }

    $controllers = getControllers($gameReady, $_SESSION['user_id'], null, false);
    $debug = false;
    if (strtolower(getConfig($gameReady, 'DEBUG')) == 'true') $debug = true;
    echo '<div class="section factions">';
        // Show factions if Multiple controllers are available
        if (count($controllers) > 1) {
            echo '<h2 class="title is-4">Factions</h2>';
            echo sprintf('
                <form action="/%s/base/accueil.php" method="GET" class="box mb-5">
                    <div class="field">
                        %s
                    </div>
                    <div class="field">
                        <div class="control">
                            <input type="submit" name="chosir" value="Choisir" class="button is-link">
                        </div>
                    </div>
                </form>
                <!-- Display controller details section (initially hidden) changed by the select action-->
                <div id="controllerDetails" style="display: none;"> </div>', 
                $_SESSION['FOLDER'],
                showControllerSelect($controllers)
            );
        }
        if ( isset($_SESSION['controller']) ) {
            $controllers = getControllers($gameReady, NULL, $_SESSION['controller']['id'])[0];
            echo sprintf ('<h2 class="title is-4">Votre Faction</h2>
                <div class="box mb-4">
                <p>
                Vous êtes <strong>%1$s %2$s</strong> (réseau <strong>%3$s</strong>) de la faction : <strong>%4$s</strong> (<span class="has-text-grey">%5$s</span>)
                </p>
                %8$s
                %6$s %7$s
                <form action="/%9$s/controllers/action.php" method="GET" class="mt-4">
                <input type="hidden" name="controller_id" value="%3$s">
                <h3 class="title is-5 mt-4">Votre Base :</h3>
                <p>',
                htmlspecialchars($controllers['firstname']),
                htmlspecialchars($controllers['lastname']),
                htmlspecialchars($controllers['id']),
                htmlspecialchars($controllers['faction_name']),
                htmlspecialchars($controllers['fake_faction_name']),
                !empty($controllers['url']) ? '<button type="button" class="button is-small is-info mb-2" onclick="window.open(\''.$controllers['url'].'\', \'_blank\')">Document de faction</button>' : '',
                !empty($playerURL) ? '<button type="button" class="button is-small is-info mb-2" onclick="window.open(\''.$playerURL.'\', \'_blank\')">Document du joueur</button>' : '',
                !empty($controllers['story']) ? '<div class="notification is-light mb-2"><span class="is-size-7">'.nl2br(htmlspecialchars($controllers['story'])).'</span></div>' : '',
                $_SESSION['FOLDER']
            );
            $bases = hasBase($gameReady, $controllers['id']);
            if (empty($bases)) {
                echo sprintf(
                    '<div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
                        <div class="control">
                            <input type="submit" name="createBase" value="%1$s" class="button is-link worker-action-btn">
                        </div>
                        %2$s
                    </div><br />',
                    htmlspecialchars(getConfig($gameReady, 'textControllerActionCreateBase')),
                    $showZoneSelect
                );
            } else {
                if ($debug) echo sprintf('<p> %s </p>', var_export($bases, true));
                $textControllerActionMoveBase = getConfig($gameReady, 'textControllerActionMoveBase');
                echo '<p>';
                foreach ($bases as $base ){
                    echo sprintf('
                        <input type="hidden" name="base_id" value="%3$s">
                        <div class="notification is-light mb-2">
                            <strong>Votre %4$s à %5$s</strong><br>
                            <details>
                                <summary>
                                    Ne sera découvert que sur une valeur d’enquête de <strong>%6$s</strong> ou plus. Si découvert, le texte suivant sera présenté à l’enquêteur :
                                </summary>
                                <blockquote>%7$s</blockquote>
                           </details>
                        </div>
                        <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
                            <div class="control">
                                <input type="submit" name="moveBase" value="%1$s" class="button is-warning controller-action-btn">
                            </div>
                            %2$s
                        </div>
                        <br>',
                        htmlspecialchars($textControllerActionMoveBase),
                        $showZoneSelect,
                        htmlspecialchars($base['id']),
                        htmlspecialchars($base['name']),
                        htmlspecialchars($base['zone_name']),
                        htmlspecialchars($base['discovery_diff']),
                        nl2br(htmlspecialchars($base['description']))
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
                echo "<div class='notification is-danger'>";
                echo "<strong>Alerte !</strong> Votre base a été attaquée ce tour !<ul>";
                foreach ($incomingAttacks as $attack) {
                    echo sprintf( "<li> %s</li>", htmlspecialchars($attack['target_result_text']));
                }
                echo "</ul></div>";
            }
            echo '</p>
            <h3 class="title is-5 mt-5">Les lieux découverts:</h3>
            <p>';
            $showAttackableControllerKnownLocations = showAttackableControllerKnownLocations($gameReady, $controllers['id']);
            if( hasBase($gameReady, $controllers['id'])) {
                if($showAttackableControllerKnownLocations !== NULL) {
                    echo sprintf('<form action="/%3$s/controllers/action.php" method="GET" class="mb-4">
                            <input type="hidden" name="controller_id" value="%1$s">
                            <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
                                <div class="control">
                                    <input type="submit" name="attackLocation" value="Mener une équipe d\'attaque vers :" class="button is-danger controller-action-btn">
                                </div>
                                %2$s
                            </div>
                        </form>',
                        htmlspecialchars($controllers['id']),
                        $showAttackableControllerKnownLocations,
                        $_SESSION['FOLDER']
                    ); 
                } else echo '<span class="has-text-grey">Aucun lieu connu attaquable.</span>';
            } else echo '<span class="has-text-grey">Les attaques de lieux sont impossible sans une base d\'opération.</span>';
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
                echo "<div class='notification is-warning'>";
                echo "<strong>Vos attaques ce tour :</strong><ul>";
                foreach ($outgoingAttacks as $attack) {
                    echo "<li>". htmlspecialchars($attack['attacker_result_text']) . "</li>";
                }
                echo "</ul></div>";
            }

            $controllerKnownLocations = listControllerKnownLocations($gameReady, $controllers['id']);

            if (!$controllerKnownLocations) {
                echo '<p class="notification is-warning">Aucun emplacement connu.</p>';
            } else {
                $htmlKnownLocations = "";
                // Build Bulma HTML
                foreach ($controllerKnownLocations as $zone) {
                    $zoneId = htmlspecialchars($zone['name']);
                    $htmlKnownLocations .= sprintf(
                        '<div class="box">
                            <details>
                                <summary class="has-text-weight-semibold">Lieux connus de %s</summary>
                            <ul>',
                        htmlspecialchars($zone['name'])
                    );
                    foreach ($zone['locations'] as $loc) {
                        $htmlKnownLocations .= sprintf(
                            '<li> <details>
                                <summary class="has-text-weight-semibold">%s</summary>
                             %s </details></li>',
                            htmlspecialchars($loc['name']),
                            htmlspecialchars($loc['description'])
                        );
                    }
                    $htmlKnownLocations .= '</ul></details></div>';
                }
                echo $htmlKnownLocations ;
            }

            echo '<h3 class="title is-5 mt-5">Vos lieux secrets:</h3>';

            $controllerLinkedLocations = listControllerLinkedLocations($gameReady, $controllers['id']);

            if (!$controllerLinkedLocations) {
                echo '<p class="notification is-warning">Aucun lieux.</p>';
            } else {
                $htmlLinkedLocations = "";
                // Build Bulma HTML
                foreach ($controllerLinkedLocations as $zone) {
                    $zoneId = htmlspecialchars($zone['name']);
                    $htmlLinkedLocations .= sprintf(
                        '<div class="box">
                            <details>
                                <summary class="has-text-weight-semibold">Lieux de %s</summary>
                            <ul>',
                        htmlspecialchars($zone['name'])
                    );
                    foreach ($zone['locations'] as $loc) {
                        $htmlLinkedLocations .= sprintf(
                            '<li> <details>
                                <summary class="has-text-weight-semibold">%s</summary>
                             %s </details></li>',
                            htmlspecialchars($loc['name']),
                            htmlspecialchars($loc['description'])
                        );
                    }
                    $htmlLinkedLocations .= '</ul></details></div>';
                }
                echo $htmlLinkedLocations ;
            }

            echo '
            </form>
            </div>';
    }
    echo '</div>';

if (!empty($pageName) && $pageName == 'controllers_action')
    require_once '../workers/viewAll.php';
