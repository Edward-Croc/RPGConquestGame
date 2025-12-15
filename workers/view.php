<?php
if ($_SESSION['DEBUG'] == true) echo "_SESSION: ".var_export($_SESSION, true)."<br /><br />";

if ( !empty($_SESSION['controller']) ||  !empty($controller_id) ) {
    if ( $_SESSION['DEBUG'] == true ) echo "_SESSION['controller']['id']: ".var_export($_SESSION['controller']['id'], true)."<br /><br />";
    if ( empty($controller_id) ) $controller_id = $_SESSION['controller']['id'];
    if ( $_SESSION['DEBUG'] == true ) echo "controller_id: ".var_export($controller_id, true)."<br /><br />";

    $workersArray = getWorkers($gameReady, [$worker_id]);

    echo "<div class='workers section'>";

    if ( $_SESSION['DEBUG'] == true )
        echo "workersArray: ".var_export($workersArray, true)."<br /><br />";
    if ( !empty($workersArray) ) {

        foreach ($workersArray as $worker){
            if ( $worker['controller_id'] != $controller_id) continue;

            $workerStatus = getWorkerStatus($worker, $mechanics);

            $currentAction = setWorkerCurrentAction($worker['actions'], $mechanics['turncounter']);

            $textActionUpdated = getConfig($gameReady,'txt_ps_'.$currentAction['action_choice']);
            // change action text if prisoner or double agent
            if ($workerStatus == 'double_agent' || $workerStatus == 'prisoner') {

                // for double agent get name of infiltrated network
                if ($workerStatus == 'double_agent') {
                    $sql = "SELECT cw.controller_id
                    FROM controller_worker AS cw
                    WHERE cw.worker_id = :worker_id
                    AND cw.is_primary_controller = :is_primary_controller
                    LIMIT 1";
                    //  ORDER BY controller_worker.id
                    $stmt = $gameReady->prepare($sql);
                    $stmt->execute([
                        ':worker_id' => $worker['id'],
                        ':is_primary_controller' => 1
                    ]);
                    $infiltrated_controller_id = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $infiltrated_controller_name = getControllerName($gameReady, $infiltrated_controller_id[0]);
    
                    $textActionUpdated .= sprintf(
                        ' et ' . getConfig($gameReady,'txt_ps_'.$workerStatus),
                        getConfig($gameReady,'controllerNameDenominatorOf'),
                        $infiltrated_controller_name
                    );
                }
                // for prisonner get name of original controller
                if ($workerStatus == 'prisoner') {
                    $params = json_decode($currentAction['action_params'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo "JSON decoding error: " . json_last_error_msg() . "<br />";
                    }
                    $controller_name = getControllerName($gameReady, $params['original_controller_id']);

                    $textActionUpdated = sprintf(
                        getConfig($gameReady,'txt_ps_'.$workerStatus),
                        getConfig($gameReady,'controllerNameDenominatorOf'),
                        $controller_name
                    );
                }
            }

            $workerActionInfo = '';
            if ( in_array($currentAction['action_choice'], array('attack', 'claim')) ) {
                if ( $_SESSION['DEBUG'] == true )
                    $workerActionInfo .= ' Action spéciale en cours : <strong>'.$currentAction['action_choice'].'</strong> '. $currentAction['action_params'];

                $params = array();
                if (!empty($currentAction['action_params'])) {
                    $params = json_decode($currentAction['action_params'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo "JSON decoding error: " . json_last_error_msg() . "<br />";
                    }
                }
                if ($currentAction['action_choice'] == 'claim') {
                    if (!empty($params['claim_controller_id']) && ($params['claim_controller_id'] != "null") ) {
                        $controllers = getControllers($gameReady, null, $params['claim_controller_id']);
                        $workerActionInfo .= sprintf(
                            ' au nom de <strong>%1$s</strong>',
                            $controllers[0]['lastname'],
                        );
                    }
                }
                if ($currentAction['action_choice'] == 'attack') {
                    $attackedWorkerIds = array();
                    foreach ($params as $key => $value) {
                        if ( $_SESSION['DEBUG'] == true )
                            echo sprintf(" Paramètre %s => %s ; ", $key, var_export($value, true) );
                        if (!empty($value['attackScope']) && ($value['attackScope'] == "worker") ) {
                            $attackedWorkerIds[] = $value['attackID'];
                        }
                        if (!empty($value['attackScope']) && ($value['attackScope'] == "network") ) {
                            $attackedNetworkIds[] = $value['attackID'];
                        }
                    }
                    $workerActionInfo .= ' contre ';
                    if (!empty($attackedNetworkIds)) {
                        $workerActionInfo .= "les réseaux ".implode(', ', $attackedNetworkIds);
                    }
                    // $attackedWorkerIds has elements
                    if (!empty($attackedWorkerIds)) {
                        if (!empty($attackedNetworkIds)) $workerActionInfo .= ' et contre ';
                        $workersArray = getWorkers($gameReady, $attackedWorkerIds);
                        foreach( $workersArray AS $k => $w ) {
                            $workerActionInfo .= sprintf(
                                '%1$s %2$s %3$s',
                                $w['firstname'],
                                $w['lastname'],
                                ($k < (count($workersArray)-1)) ? ', ' : ''
                            );
                        }
                    }
                }
    
            }
            if ( $_SESSION['DEBUG'] == true )
                echo "workerActionText: ".var_export($workerActionText, true)."<br /><br />";

            // build worker action presentation texte :
            $workerActionText = sprintf( ' <strong> %1$s </strong> %4$s dans le %3$s <strong>%2$s</strong>',
                ucfirst($textActionUpdated), // %1$s
                $worker['zone_name'], // %2$s
                getConfig($gameReady, 'textForZoneType'), // %3$s
                $workerActionInfo // %4$s
            );

            // get zone value by  $worker['zone_id'] and use to get controller name
            $zonesArray = getZonesArray($gameReady, null, null, $worker['zone_id']);
            if ( !empty($zonesArray[0]['claimer_controller_id']) ) {
                if ($zonesArray[0]['claimer_controller_id'] == $controller_id) {
                    $workerActionText .= ' qui est sous notre bannière';
                } else {
                    $workerActionText .= sprintf(' qui est sous la bannière %s %s', getConfig($gameReady, "controllerLastNameDenominatorOf"), $zonesArray[0]['claimer_lastname'] );
                }
            }
            $workerActionText .= '.';
            $zoneOwner = false;
            if (!empty($zonesArray[0]['holder_controller_id']) && $zonesArray[0]['holder_controller_id'] == $controller_id){
                $zoneOwner = true;
                $workerActionText .= sprintf(
                    ' %s %s est déjà sous notre contrôle !',
                    ucfirst(getConfig($gameReady, 'timeDenominatorThis')),
                    getConfig($gameReady, 'textForZoneType')
                );     
            } 

            // build view history HTML
            $viewHistoryHTML = sprintf(
                '<div class="box history">
                    <h3 class="title is-5">Historique :</h3>
                    <p>
                        Originaire de <strong>%1$s</strong>, '.getConfig($gameReady, 'textViewWorkerJobHobby').' <br />
                        %4$s %5$s
                    </p>
                </div>',
                $worker['origin_name'],
                empty($worker['powers']['Metier']['texte']) ? '' : $worker['powers']['Metier']['texte'],
                empty($worker['powers']['Hobby']['texte']) ? '' : $worker['powers']['Hobby']['texte'],
                empty($worker['powers']['Discipline']['texte']) ? '' :
                    sprintf(getConfig($gameReady, 'textViewWorkerDisciplines'),$worker['powers']['Discipline']['texte']),
                empty($worker['powers']['Transformation']['texte']) ? '' :
                    sprintf(getConfig($gameReady, 'textViewWorkerTransformations'), $worker['powers']['Transformation']['texte']),
            );

            // build view actions HTML
            $actionHTML = '';
            // worker must be active to have actions available
            if (in_array($worker['actions'][$mechanics['turncounter']]['action_choice'], ACTIVE_ACTIONS)) {
                // on $workerStatus = 'double_agent' show recall button
                $recallWorkerButton = '';
                if (!empty($workerStatus) && $workerStatus == 'double_agent'){
                    $recallWorkerButton .= sprintf(
                        '<div class="control">
                            <i>%1$s</i><br \>
                            <input type="hidden" name="recall_controller_id" value="%3$s">
                            <input type="submit" name="recallDoubleAgent" value="%2$s" class="button is-info">
                        </div>',
                        'Cet agent étant infiltré.e, lui donner des ordres pourrait révéler son défaut d’allégeance !',
                        'Le rappeler a notre service !',
                        $controller_id
                    );
                }

                $zonesArray = getZonesArray($gameReady);
                if ($_SESSION['DEBUG'] == true) echo "zonesArray: ".var_export($zonesArray, true)."<br /><br />";
                $showZoneSelect = showZoneSelect($gameReady, $zonesArray, $worker['zone_id'], false, false, true);
                if ($_SESSION['DEBUG'] == true) echo "showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";

                $controllers = getControllers($gameReady);
                $showcontrollersSelect = showControllerSelect($controllers, $controller_id, 'gift_controller_id');
                $showListClaimTargetsSelect = showControllerSelect($controllers, $controller_id, 'claim_controller_id', TRUE);

                // build attack select HTML
                $enemyWorkersSelect = showEnemyWorkersSelect($gameReady, $worker['zone_id'], $controller_id);
                $attackActionHTML = '';
                if (!empty($enemyWorkersSelect)) {
                    $attackActionHTML = sprintf('
                        <div class="field is-grouped is-grouped-multiline">
                            <div class="control">
                                Attaquer :
                            </div>
                            %s
                            <div class="control">
                                <input type="submit" name="attack" value="Attaquer" class="button is-danger"><br />
                            </div>
                        </div>',
                        $enemyWorkersSelect
                    );
                }

                $actionHTML .= sprintf('<div class="box actions">
                    <form action="/%9$s/workers/action.php" method="GET">
                        <input type="hidden" name="worker_id" value=%1$s>
                        <h3 class="title is-5">Actions :</h3> 
                        <div class="field">
                            <label> <strong> Action de mise en place pour la fin de %13$s :</strong> %12$s</label>
                            <div class="control">
                                <input type="submit" name="investigate" value="%10$s" class="button is-info">
                                <input type="submit" name="passive" value="%4$s" class="button is-warning"> 
                                <input type="submit" name="hide" value="%11$s" class="button is-danger"><br />
                            </div>
                        </div>
                        <div class="field is-grouped is-grouped-multiline">
                            <div class="control">
                                %14$s le %8$s au nom de
                            </div>
                            %5$s
                            <div class="control">
                                <input type="submit" name="claim" value="%14$s" class="button is-success">
                            </div>
                        </div>
                        %3$s
                        <label class="label"><strong>Actions immédiates :</strong></label>
                        %7$s
                        <div class="field is-grouped is-grouped-multiline">
                            <div class="control">
                                Déménager vers :
                            </div>
                            %2$s
                            <div class="control">
                                <input type="submit" name="move" value="Déménager" class="button is-warning">
                            </div>
                        </div>
                        <div class="field is-grouped is-grouped-multiline">
                            <div class="control">
                                Donner mon serviteur à 
                            </div>
                            %6$s
                            <div class="control">
                                <input type="submit" name="gift" value="Donner" class="button is-danger">
                            </div>
                        </div>
                    </form>
                </div>',
                $worker['id'], // %1$s
                $showZoneSelect, // %2$s
                $attackActionHTML, // %3$s
                ucfirst(getConfig($gameReady, 'txt_inf_passive')), // %4$s
                $showListClaimTargetsSelect, // %5$s
                $showcontrollersSelect, // %6$s
                $recallWorkerButton, // %7$s 
                getConfig($gameReady, 'textForZoneType'), // %8$s
                $_SESSION['FOLDER'], // %9$s
                ucfirst(getConfig($gameReady, 'txt_inf_investigate')), // %10$s
                ucfirst(getConfig($gameReady, 'txt_inf_hide')), // %11$s
                $workerActionText, // %12$s
                ucfirst(getConfig($gameReady, 'timeValue')), // %13$s
                ($zoneOwner) ? 'Protéger/Revendiquer' : 'Revendiquer'
                );
            }

            // on $workerStatus = 'prisoner' show return to owner button
            if (!empty($workerStatus) && $workerStatus == 'prisoner'){

                $params = json_decode($worker['actions'][$mechanics['turncounter']]['action_params'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo "JSON decoding error: " . json_last_error_msg() . "<br />";
                }
                $return_controller_id = $params['original_controller_id'];
                $double_agent_controller_id = (!empty($params['double_agent_controller_id'])) ? $params['double_agent_controller_id'] : null;

                $actionHTML .= '<div class="actions"><h3>Actions : </h3> <p>';
                $controllerName = getControllerName($gameReady, $return_controller_id);
                $actionHTML .= sprintf('
                    <form action="/%5$s/workers/action.php" method="GET">
                    <input type="hidden" name="worker_id" value=%1$s>
                        <input type="hidden" name="recall_controller_id" value="%2$s">
                        <input type="hidden" name="return_controller_id" value="%3$s">
                        %6$s
                        <input type="submit" name="returnPrisoner" value="%4$s" class="button is-info"><br />
                    </form>
                ',
                    $worker['id'],
                    $controller_id, // %2$s
                    $return_controller_id, // %3$s
                    'Relâcher le prisonnier vers ' . $controllerName . ' !', // %4$s
                    $_SESSION['FOLDER'], // %5$s
                    (!empty($double_agent_controller_id)) ? sprintf('<input type="hidden" name="double_controller_id" value="%s">', $double_agent_controller_id) : '' // %6$s
                );
                if (!empty($double_agent_controller_id)) {
                    $controllerName = getControllerName($gameReady, $double_agent_controller_id);
                    $actionHTML .= sprintf('
                        <form action="/%5$s/workers/action.php" method="GET">
                        <input type="hidden" name="worker_id" value=%1$s>
                            <input type="hidden" name="recall_controller_id" value="%2$s">
                            <input type="hidden" name="return_controller_id" value="%3$s">
                            <input type="submit" name="returnPrisoner" value="%4$s" class="button is-warning"><br />
                       
                        </form>
                    ',
                        $worker['id'],
                        $controller_id,
                        $double_agent_controller_id,
                        'Relâcher le prisonnier vers ' . $controllerName . ' !',
                        $_SESSION['FOLDER']
                    );
                }
                $actionHTML .= '</p></div>';
            }

            // build upgrade HTML
            $upgradeHTML = '';
            // worker must be active to have upgrades available
            if (in_array($worker['actions'][$mechanics['turncounter']]['action_choice'], ACTIVE_ACTIONS)) {
                // TODO : UPDATE powers on age code ?
                /* ('age_hobby', 'false', ''),
                ('age_metier', 'false', ''), */

                // Allow Discipline teaching via age_discipline param
                $upgradeDisciplineHTML = "";
                $debug_discipline_age = $_SESSION['DEBUG_TRANSFORM'];
                $age_discipline_json = getConfig($gameReady, 'age_discipline');
                if ( $debug_discipline_age ) echo sprintf("age_discipline_json :%s  <br>", $age_discipline_json);

                $age_discipline_array = array();
                if (!empty($age_discipline_json)) {
                    $age_discipline_array = json_decode($age_discipline_json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo "JSON decoding error: " . json_last_error_msg() . "<br />";
                    }
                }
                if ( $debug_discipline_age ) echo sprintf("age_discipline_array :%s  <br>", var_export($age_discipline_array, true));
                $powerDisciplineArray = getPowersByType($gameReady,'3', $controller_id, true);
                if ( $debug_discipline_age ) echo sprintf("powerDisciplineArray : %s <br/>", var_export($powerDisciplineArray, true));
                $powerDisciplineArray = cleanPowerListFromJsonConditions($gameReady, $powerDisciplineArray, $controller_id, $worker['id'], $mechanics['turncounter'], 'on_age' );
                if ( $debug_discipline_age ) echo sprintf("powerDisciplineArray : %s <br/>", var_export($powerDisciplineArray, true));
                $nb_disciplines = (INT)getConfig($gameReady, 'recrutement_disciplines');
                if ( $debug_discipline_age ) echo sprintf("nb_disciplines :%s  <br>", $nb_disciplines);
                foreach ($age_discipline_array['age'] as $age) {
                    if ($age <= $worker['age']) {
                        $nb_disciplines += 1;
                    }
                }
                $nb_current_disciplines = countWorkerDisciplines($gameReady, array($worker['id']));
                if (!isset($nb_current_disciplines[0]['discipline_count']))
                    $nb_current_disciplines[0]['discipline_count'] = 0;
                if ( $debug_discipline_age )
                    echo sprintf(
                        "nb_current_disciplines :count(%s) => %s, nb_disciplines: %s <br>",
                        $nb_current_disciplines[0]['discipline_count'], var_export($nb_current_disciplines, true), $nb_disciplines
                    );
                if ( (INT)$nb_current_disciplines[0]['discipline_count'] < (INT)$nb_disciplines) {
                    $upgradeDisciplineHTML .= sprintf('
                        <div class="field is-grouped is-grouped-multiline">
                            %1$s
                            <div class="control">
                                <input type="submit" name="teach_discipline" value="Enseigner" class="button is-link worker-upgrade-btn">
                            </div>
                        </div>
                        ',
                        showDisciplineSelect($gameReady, $powerDisciplineArray, true)
                    );
                }
                // Check Transformation Conditions
                $upgradeTransformationHTML = "";
                $debug_transformation_age = $_SESSION['DEBUG_TRANSFORM'];
                $age_transformation_json = getConfig($gameReady, 'age_transformation');
                $age_transformation_array = array();
                if (!empty($age_transformation_json)) {
                    $age_transformation_array = json_decode($age_transformation_json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo "JSON decoding error: " . json_last_error_msg() . "<br />";
                    }
                }
                if ( $debug_transformation_age ) echo sprintf("age_transformation_array :%s  <br>", var_export($age_transformation_array, true));
                if (!empty($age_transformation_array['action']) && $age_transformation_array['action'] == 'check' ) {
                    // get transformations
                    $powerTransformationArray = getPowersByType($gameReady,'4', null, false);
                    if ( $debug_transformation_age ) echo sprintf("powerTransformationArray: %s <br />",var_export($powerTransformationArray, true));
                    $powerTransformationArray = cleanPowerListFromJsonConditions($gameReady, $powerTransformationArray, $controller_id, $worker['id'], $mechanics['turncounter'], 'on_transformation' );
                    if ( $debug_transformation_age ) echo sprintf("powerTransformationArray Json parsed: %s <br/>", var_export($powerTransformationArray, true));
                    if (! empty($powerTransformationArray) )
                        $upgradeTransformationHTML .= sprintf('
                            <div class="field is-grouped is-grouped-multiline">
                                %1$s
                                <div class="control">
                                    <input type="submit" name="transform" value="Ajouter" class="button is-link worker-upgrade-btn">
                                </div>
                            </div>
                            ',
                            showTransformationSelect($gameReady, $powerTransformationArray),
                            strtolower(getPowerTypesDescription($gameReady, 'Transformation'))
                        );
                }
                $upgradeHTML = sprintf('<div class="box upgrade">
                    <h3 class="title is-5">Evolutions :</h3>
                    <form action="/%2$s/workers/action.php" method="GET">
                        <input type="hidden" name="worker_id" value=%1$s>
                        %3$s
                        %4$s
                        %5$s
                    </form></div>
                ',
                $worker['id'],
                $_SESSION['FOLDER'],
                (empty($upgradeDisciplineHTML) && empty($upgradeTransformationHTML)) ? "Aucune évolution disponible actuellement." : "",
                $upgradeDisciplineHTML,
                $upgradeTransformationHTML
                );
            }

            $viewHTML = sprintf(
                '<div class="card">
                    <header class="card-header">
                        <p class="card-header-title">
                            Agent %2$s %3$s
                        </p>
                    </header>
                    <div class="card-content">
                        <div class="box info">
                            %4$s <br />
                            <i>
                                Capacité d’enquête : <strong>%6$s</strong>. 
                                Capacité d’attaque / défense : <strong>%7$s</strong> / <strong>%8$s</strong>
                            </i>
                        </div>
                        %9$s
                        %10$s
                        %11$s
                    </div>
                </div>',
                $worker['id'], // %1$s
                $worker['firstname'], // %2$s
                $worker['lastname'], // %3$s
                $workerActionText, // %4$s
                ucfirst($textActionUpdated), // %5$s --- IGNORE ---
                $worker['total_enquete'], // %6$s
                $worker['total_attack'], // %7$s
                $worker['total_defence'], // %8$s
                $viewHistoryHTML, // %9$s
                $actionHTML, // %10$s
                $upgradeHTML // %11$s
            );
            echo $viewHTML;

            echo sprintf('<div class="box report"> <h3 class="title is-5">Rapport :</h3>');

            $timeText = getConfig($gameReady, 'timeValue');
            $timeTextThis = getConfig($gameReady, 'timeDenominatorThis');
            foreach ( $worker['actions'] as $turn_number => $action ){
                echo sprintf(
                    '<div class="box report week"> <h4 class="subtitle is-5">%s : %s</h4>',
                    ucfirst(sprintf("%s %s", $timeText, $turn_number )),
                    (isset($action['turn_number']) && (INT)$turn_number == (INT)$mechanics['turncounter'] ) ? 
                        'en cours' : ''
                );
                if ($_SESSION['DEBUG_REPORT'])
                    echo "<p> action: ".var_export($action, true)."</p>";
                if ($action['report'] != '{}') {
                    // Decode the existing JSON into an associative array
                    $currentReport = json_decode($action['report'], true);
                    if (!empty($currentReport['life_report']))
                        echo '<p><h4 class="subtitle is-6"> Changements : </h4> '.$currentReport['life_report'].'</p>';
                    if (!empty($currentReport['attack_report']))
                        echo '<p><h4 class="subtitle is-6"> Attaques : </h4> '.$currentReport['attack_report'].'</p>';
                    if (!empty($currentReport['investigate_report']))
                        echo '<p><h4 class="subtitle is-6"> Mes investigations : </h4> '.$currentReport['investigate_report'].'</p>';
                    if (!empty($currentReport['secrets_report']))
                        echo '<p><h4 class="subtitle is-6"> Mes recherches : </h4> '.$currentReport['secrets_report'].'</p>';
                    if (!empty($currentReport['claim_report']))
                        echo '<p><h4 class="subtitle is-6"> Controle: </h4> '.$currentReport['claim_report'].'</p>';
                } else{
                    echo "Rien à signaler pour l'instant !";
                }
                echo "</div>";
            }
            echo ' </div>';
        }
    }
} else {
    echo " Le choix d'un controller est nécéssaire pour acceder à cette page.";
}
?>
</div>