<?php
// Include-only page — block direct HTTP access.
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit();
}

    // Get zones information
    $zonesArray = getZonesArray($gameReady);

    $controllersArray = array();
    $playerURL = null;
    $prefix = $_SESSION['GAME_PREFIX'];

    // get player information
    if (isset( $_SESSION['user_id'])) {
        try{
            $sqlPlayers = sprintf("SELECT p.*
                FROM {$prefix}players p
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

    // Get Controllers information
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
                        <!-- Display controller details section (initially hidden) changed by the select action-->
                        <div id="controllerDetails" style="display: none;"> </div>
                    </div>
                </form>
                ', 
                $_SESSION['FOLDER'],
                showControllerSelect(
                    $controllers, 
                    (!empty ($_SESSION['controller']['id'])) ? $_SESSION['controller']['id'] : null
                )
            );
        }
        if ( isset($_SESSION['controller']) ) {
            $controllers = getControllers($gameReady, NULL, $_SESSION['controller']['id'])[0];
            $htmlFaction = sprintf ('<h2 class="title is-4">Votre Faction</h2>
                <div class="box mb-4">
                <p>
                Vous êtes <strong>%1$s %2$s</strong> (réseau <strong>%3$s</strong>) de la faction : <strong>%4$s</strong> (<span class="has-text-grey">%5$s</span>)
                </p>
                %8$s
                %6$s %7$s
                <form action="/%9$s/controllers/action.php" method="GET" class="mt-4">
                <input type="hidden" name="controller_id" value="%3$s">
                <p>',
                $controllers['firstname'],
                $controllers['lastname'],
                $controllers['id'],
                $controllers['faction_name'],
                $controllers['fake_faction_name'],
                !empty($controllers['url']) ? '<button type="button" class="button is-small is-info mb-2" onclick="window.open(\''.$controllers['url'].'\', \'_blank\')">Document de faction</button>' : '',
                !empty($playerURL) ? '<button type="button" class="button is-small is-info mb-2" onclick="window.open(\''.$playerURL.'\', \'_blank\')">Document du joueur</button>' : '',
                !empty($controllers['story']) ? '<div class="notification is-light mb-2"><span class="is-size-7">'.nl2br($controllers['story']).'</span></div>' : '',
                $_SESSION['FOLDER']
            );
            echo $htmlFaction;

            if (getConfig($gameReady, 'ressource_management') == 'TRUE') {
                $ressources = getRessources($gameReady, $controllers['id']);
                if ($debug) 
                    echo sprintf('<p> ressources: %s </p>', var_export($ressources, true));
                if (!empty($ressources)) {
                    $htmlRessources = '<div class="box mb-5"><h3 class="title is-5 mt-4">Vos Ressources :</h3>';
                    foreach ($ressources as $ressource) {
                        $htmlRessources .= '<p>';
                        $htmlRessources .= sprintf($ressource['presentation'], $ressource['amount'], $ressource['ressource_name'], $ressource['end_turn_gain']);
                        if ($ressource['amount_stored'] > 0) {
                            $htmlRessources .= "</br>".sprintf($ressource['stored_text'], $ressource['amount_stored'], $ressource['ressource_name']);
                        }
                        $htmlRessources .= '</p>';
                        $htmlRessources .= '<br>';
                    }
                    $htmlRessources .= '</div>';
                    echo $htmlRessources;
                }
            }

            echo '<div class="box mb-5"> <h3 class="title is-5 mt-4">Les lieux :</h3>';
            $bases = hasBase($gameReady, $controllers['id']);
            $htmlBase = '<h4 class="title is-5 mt-4">Votre Base :</h4>';
            if (empty($bases) && $controllers['can_build_base']) {
                if (!hasEnoughRessourcesToBuildBase($gameReady, $controllers['id'])) {
                    $htmlBase .= sprintf(
                        '<div class="notification is-danger">Vous n\'avez pas les ressources nécessaires pour créer une base %s.</div>',
                        buildBaseCostHTML($gameReady, $controllers['id'])
                    );
                } else {
                    $htmlBase .= sprintf(
                        '<div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
                            <div class="control">
                                %1$s %3$s
                            </div>
                            %2$s
                            <div class="control">
                                <input type="submit" name="createBase" value="Créer" class="button is-link worker-action-btn">
                            </div>
                        </div><br />',
                        getConfig($gameReady, 'textControllerActionCreateBase'),
                        showZoneSelect($gameReady, $zonesArray, null, false, false, true),
                        buildBaseCostHTML($gameReady, $controllers['id'])
                    );
                }
            } else {
                if ($debug) echo sprintf('<p> %s </p>', var_export($bases, true));
                $textControllerActionMoveBase = getConfig($gameReady, 'textControllerActionMoveBase');
                $baseMoveHTML = '
                    <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
                    <div class="control">
                        %1$s %8$s
                    </div>
                    %2$s
                    <div class="control">
                        <input type="submit" name="moveBase" value="Déménager" class="button is-warning controller-action-btn">
                    </div>
                </div>';

                if (!hasEnoughRessourcesToMoveBase($gameReady, $controllers['id']))
                    $baseMoveHTML = sprintf(
                        '<div class="notification is-danger">Vous n\'avez pas les ressources nécessaires pour déménager une base %s.</div>', 
                        moveBaseCostHTML($gameReady, $controllers['id'])
                    );

                $htmlBase .= '<p>';
                foreach ($bases as $base ){
                    $htmlBase .= sprintf('
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
                        '.$baseMoveHTML.'
                        ',
                        $textControllerActionMoveBase,
                        showZoneSelect($gameReady, $zonesArray, $base['zone_id'], false, false, true),
                        $base['id'],
                        $base['name'],
                        $base['zone_name'],
                        $base['discovery_diff'],
                        nl2br($base['description'].$base['hidden_description']),
                        moveBaseCostHTML($gameReady, $controllers['id'])
                    );
                }
                $htmlBase .= '</p>';
            }
            // Incoming attacks across all turns — tabbed (latest first), empty turns skipped.
            $incomingStmt = $gameReady->prepare("
                SELECT * FROM {$prefix}location_attack_logs
                WHERE target_controller_id = :controller_id
                ORDER BY turn DESC, id DESC
            ");
            $incomingStmt->execute(['controller_id' => $_SESSION['controller']['id']]);
            $incomingAttacks = $incomingStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($incomingAttacks)) {
                $timeWord = (string)getConfig($gameReady, 'timeValue');
                $byTurn = [];
                foreach ($incomingAttacks as $atk) { $byTurn[(int)$atk['turn']][] = $atk; }
                krsort($byTurn);
                $tabs = '<div class="tabs title"><ul>';
                $panels = '';
                $first = true;
                $idx = 0;
                foreach ($byTurn as $turn => $atks) {
                    $tabs .= sprintf(
                        '<li%s data-tab-group="incoming-attacks" data-tab-index="%d"><a onclick="selectTab(\'incoming-attacks\', %d)">%s %d</a></li>',
                        $first ? ' class="is-active"' : '', $idx, $idx, ucfirst($timeWord), $turn
                    );
                    $items = '';
                    foreach ($atks as $atk) { $items .= sprintf('<li>%s</li>', $atk['target_result_text']); }
                    $panels .= sprintf(
                        '<div class="tab-content"%s data-tab-group="incoming-attacks" data-tab-index="%d"><ul>%s</ul></div>',
                        $first ? '' : ' style="display:none"', $idx, $items
                    );
                    $first = false;
                    $idx++;
                }
                $tabs .= '</ul></div>';
                $htmlBase .= sprintf(
                    "<div class='notification is-danger'><strong>Alerte !</strong> Votre base a été attaquée ce %s !%s%s</div>",
                    strtolower($timeWord), $tabs, $panels
                );
            }
            echo $htmlBase;

            $showAttackableControllerKnownLocations = showAttackableControllerKnownLocations($gameReady, $controllers['id']);
            $locationAttackMode = getConfig($gameReady, 'locationAttackMode');
            if (in_array($locationAttackMode, ['immediate', 'endTurn'], true) && hasBase($gameReady, $controllers['id'])) {
                if($showAttackableControllerKnownLocations !== NULL) {
                    echo sprintf('<form action="/%3$s/controllers/action.php" method="GET" class="mb-4">
                            <input type="hidden" name="controller_id" value="%1$s">
                            <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
                                <div class="control">
                                    Mener une équipe d\'attaque vers :
                                </div>
                                %2$s
                                <div class="control">
                                    <input type="submit" name="attackLocation" value="Attaquer" class="button is-danger controller-action-btn">
                                </div>
                            </div>
                        </form>',
                        $controllers['id'],
                        $showAttackableControllerKnownLocations,
                        $_SESSION['FOLDER']
                    );
                } else echo '<span class="has-text-grey">Aucun lieu connu attaquable.</span>';
            } else echo '<span class="has-text-grey">Les attaques de lieux sont impossibles sans une base d\'opération.</span>';

            $ownedArtefacts = showOwnedArtefacts($gameReady, $controllers['id']);
            if (!empty($ownedArtefacts)) 
                echo sprintf('<h4 class="title is-5 mt-5">%s</h4><p>%s</p>', getConfig($gameReady, 'textOwnedArtefacts'), $ownedArtefacts);

        echo '<h4 class="title is-5 mt-5">Vos lieux découverts:</h4>';
            // Outgoing attacks across all turns — tabbed (latest first), empty turns skipped.
            $outgoingStmt = $gameReady->prepare("
                SELECT * FROM {$prefix}location_attack_logs
                WHERE attacker_id = :controller_id
                ORDER BY turn DESC, id DESC
            ");
            $outgoingStmt->execute(['controller_id' => $_SESSION['controller']['id']]);
            $outgoingAttacks = $outgoingStmt->fetchAll(PDO::FETCH_ASSOC);

            $queuedStmt = $gameReady->prepare("
                SELECT cla.id AS queue_row_id, cla.queued_turn, cla.defence_val_snapshot,
                       l.id AS location_id, l.name AS location_name, l.zone_id
                FROM {$prefix}controller_location_attacks cla
                JOIN {$prefix}locations l ON cla.location_id = l.id
                WHERE cla.attacker_controller_id = :controller_id AND cla.success IS NULL
                ORDER BY cla.queued_turn DESC, cla.id DESC
            ");
            $queuedStmt->execute(['controller_id' => $_SESSION['controller']['id']]);
            $queuedAttacks = $queuedStmt->fetchAll(PDO::FETCH_ASSOC);

            $byTurn = [];
            foreach ($outgoingAttacks as $atk) { $byTurn[(int)$atk['turn']][] = $atk['attacker_result_text']; }
            $bandwidth = (int) getConfig($gameReady, 'attackLocationOutcomeBandwidth');
            $queuedTpl = getConfig($gameReady, 'textLocationAttackQueued');
            foreach ($queuedAttacks as $q) {
                $liveAttack = calculatecontrollerAttack($gameReady, $q['zone_id'], $_SESSION['controller']['id']);
                $diff = $liveAttack - (int)$q['defence_val_snapshot'];
                if ($diff > $bandwidth) {
                    $bandKey = 'textLocationAttackOutcomeProbable';
                } elseif ($diff < -$bandwidth) {
                    $bandKey = 'textLocationAttackOutcomeFail';
                } else {
                    $bandKey = 'textLocationAttackOutcomeWeak';
                }
                $cancelLink = '';
                if (in_array($locationAttackMode, ['endTurn'], true)) {
                    $cancelLink = sprintf(
                        '<a href="/%s/controllers/action.php?cancelLocationAttack=%d&controller_id=%d" class="button is-small is-warning ml-2 cancel-location-attack-btn">Annuler</a>',
                        $_SESSION['FOLDER'], $q['queue_row_id'], $_SESSION['controller']['id']
                    );
                }
                $byTurn[(int)$q['queued_turn']][] =
                    '<em>'.sprintf($queuedTpl, $q['location_name'], $liveAttack, getConfig($gameReady, $bandKey)).'</em>'
                    . $cancelLink;
            }

            if (!empty($byTurn)) {
                $timeWord = (string)getConfig($gameReady, 'timeValue');
                krsort($byTurn);
                $tabs = '<div class="tabs title"><ul>';
                $panels = '';
                $first = true;
                $idx = 0;
                foreach ($byTurn as $turn => $atks) {
                    $tabs .= sprintf(
                        '<li%s data-tab-group="outgoing-attacks" data-tab-index="%d"><a onclick="selectTab(\'outgoing-attacks\', %d)">%s %d</a></li>',
                        $first ? ' class="is-active"' : '', $idx, $idx, ucfirst($timeWord), $turn
                    );
                    $items = '';
                    foreach ($atks as $text) { $items .= sprintf('<li>%s</li>', $text); }
                    $panels .= sprintf(
                        '<div class="tab-content"%s data-tab-group="outgoing-attacks" data-tab-index="%d"><ul>%s</ul></div>',
                        $first ? '' : ' style="display:none"', $idx, $items
                    );
                    $first = false;
                    $idx++;
                }
                $tabs .= '</ul></div>';
                echo sprintf(
                    "<div class='notification is-warning'><strong>Vos attaques ce %s :</strong>%s%s</div>",
                    strtolower($timeWord), $tabs, $panels
                );
            }
            $showRepairableControllerKnownLocations = showRepairableControllerKnownLocations($gameReady, $controllers['id']);
            if($showRepairableControllerKnownLocations !== NULL) {
                if(hasEnoughRessourcesToRepairLocation($gameReady, $controllers['id'])) {
                    echo sprintf('<form action="/%3$s/controllers/action.php" method="GET" class="mb-4">
                        <input type="hidden" name="controller_id" value="%1$s">
                        <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
                            <div class="control">
                                Réparer un lieu : %4$s
                            </div>
                            %2$s
                            <div class="control">
                                <input type="submit" name="repairLocation" value="Réparer" class="button is-success controller-action-btn">
                            </div>
                        </div>
                        </form>',
                        $controllers['id'],
                        $showRepairableControllerKnownLocations,
                        $_SESSION['FOLDER'],
                        repairLocationCostHTML($gameReady, $controllers['id'])
                    );
                } else {
                    echo '<div class="notification is-danger">Vous n\'avez pas les ressources nécessaires pour réparer un lieu.</div>';
                }
            }

            // Exclude own — surfaced via listControllerLinkedLocations below.
            $controllerKnownLocations = listControllerKnownLocations($gameReady, $controllers['id'], false, false, true);

            if (!$controllerKnownLocations) {
                echo '<p class="notification is-warning">Aucun emplacement connu.</p>';
            } else {
                $htmlKnownLocations = "";
                // Build Bulma HTML
                foreach ($controllerKnownLocations as $zone) {
                    $htmlKnownLocations .= sprintf(
                        '<div class="box">
                            <details>
                                <summary class="has-text-weight-semibold">Lieux connus de %s</summary>
                            <ul>',
                        $zone['name']
                    );
                    foreach ($zone['locations'] as $loc) {
                        $htmlKnownLocations .= sprintf(
                            '<li> <details>
                                <summary class="has-text-weight-semibold">%s</summary>
                             %s%s </details></li>',
                            $loc['name'],
                            $loc['description'],
                            $loc['hidden_description']
                        );
                    }
                    $htmlKnownLocations .= '</ul></details></div>';
                }
                echo $htmlKnownLocations ;
            }

            echo '<h4 class="title is-5 mt-5">Vos lieux secrets:</h4>';
            $controllerLinkedLocations = listControllerLinkedLocations($gameReady, $controllers['id']);

            if (!$controllerLinkedLocations) {
                echo '<p class="notification is-warning">Aucun lieu.</p>';
            } else {
                $htmlLinkedLocations = "";
                // Build Bulma HTML
                foreach ($controllerLinkedLocations as $zone) {
                    $htmlLinkedLocations .= sprintf(
                        '<div class="box">
                            <details>
                                <summary class="has-text-weight-semibold">Lieux de %s</summary>
                            <ul>',
                        $zone['name']
                    );
                    foreach ($zone['locations'] as $loc) {
                        $artefactsHTML = '';
                        foreach ($loc['artefacts'] as $artefact) {
                            $artefactsHTML .= sprintf(
                                '<details>
                                        <summary class="">%s</summary>
                                        %s %s
                                    </details>
                                ',
                                $artefact['name'],
                                $artefact['description'],
                                $artefact['full_description']
                            );
                        }

                        $htmlLinkedLocations .= sprintf(
                            '<li> <details>
                                <summary class="has-text-weight-semibold">%s</summary>
                                %s
                                %s 
                            </details></li>',
                            $loc['name'],
                            $loc['description'],
                            $artefactsHTML
                        );
                    }
                    $htmlLinkedLocations .= '</ul></details></div>';
                }
                echo $htmlLinkedLocations ;
            }

            echo '</div></form>';
            echo buildGiveKnowledgeHTML($gameReady, 'controller', $controllers['id']);

            $receivedInfoGifts = getInformationGiftsReceived($gameReady, $controllers['id']);
            $htmlReceived = '<div class="box mb-5"><h3 class="title is-5 mt-5">Informations reçues :</h3>';
            if (empty($receivedInfoGifts)) {
                $htmlReceived .= '<p class="has-text-grey">Aucune information reçue.</p>';
            } else {
                $htmlReceived .= '<ul>';
                foreach ($receivedInfoGifts as $gift) {
                    $label = $gift['target_type'] === 'agent' ? "l'agent" : 'le lieu';
                    $htmlReceived .= sprintf(
                        '<li>T%d &mdash; %s vous a transmis %s <strong>%s</strong></li>',
                        (int)$gift['turn'],
                        htmlspecialchars($gift['giver']),
                        $label,
                        htmlspecialchars($gift['target_label'])
                    );
                }
                $htmlReceived .= '</ul>';
            }
            $htmlReceived .= '</div>';
            echo $htmlReceived;
            echo '</div>';
    }
    echo '</div>';

if (!empty($pageName) && $pageName == 'controllers_action')
    require_once '../workers/viewAll.php';
