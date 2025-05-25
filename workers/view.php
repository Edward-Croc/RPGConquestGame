<?php
if ($_SESSION['DEBUG'] == true) echo "_SESSION: ".var_export($_SESSION, true)."<br /><br />";

if ( !empty($_SESSION['controller']) ||  !empty($controller_id) ) {
    if ( $_SESSION['DEBUG'] == true ) echo "_SESSION['controller']['id']: ".var_export($_SESSION['controller']['id'], true)."<br /><br />";
    if ( empty($controller_id) ) $controller_id = $_SESSION['controller']['id'];
    if ( $_SESSION['DEBUG'] == true ) echo "controller_id: ".var_export($controller_id, true)."<br /><br />";

    $workersArray = getWorkers($gameReady, [$worker_id]);

    echo "<div class='workers'>";

    if ( $_SESSION['DEBUG'] == true )
        echo "workersArray: ".var_export($workersArray, true)."<br /><br />";
    if ( !empty($workersArray) ) {

        foreach ($workersArray as $worker){
            if ( $worker['controller_id'] != $controller_id) continue;
            $workerStatus = 'unfound';
            // liveWorkerArray : worker alive and active and that we control
            if ( $worker['is_alive'] && $worker['is_active'] && $worker['is_primary_controller'] ) {
                $workerStatus = 'alive';
            //doubleAgentWorkerArray : worker alive and active that we don't control
            } else if ( $worker['is_alive'] && $worker['is_active'] && !$worker['is_primary_controller'] ) {
                $workerStatus = 'doubleAgent';
            //prisonersWorkerArray : worker alive and not active that we do control are our prisonners
            } else if ( $worker['is_alive'] && !$worker['is_active'] && $worker['is_primary_controller'] ) {
                $workerStatus = 'prisoners';
            // deadWorkerArray : our dead (worker not alive) or our workers prisonner of others (worker alive and not active that we do not control) 
            } else if ( !$worker['is_alive'] || ( $worker['is_alive'] && !$worker['is_active'] && !$worker['is_primary_controller'] ) ) {
                $workerStatus = 'dead';
            }
            //if ( $_SESSION['DEBUG'] == true )
                echo $workerStatus;

            $currentAction = setWorkerCurrentAction($worker['actions'], $mechanics['turncounter']);

            // TODO : change action text if prisonner or double agent 
            // TODO : show original controller of prisonner or infiltrated controller of double agent 
            $viewHTML = sprintf(
                '<div><h1>Agent %2$s %3$s (%1$s) </h1> 
                    %5$s au %4$s.<br />
                    <i>
                        Capacité d’enquete : %6$s. Capacité d’attaque / défense : %7$s / %8$s
                    </i>
                </form> </div>
                ',
                $worker['id'],
                $worker['firstname'],
                $worker['lastname'],
                $worker['zone_name'],
                ucfirst(getConfig($gameReady,'txt_ps_'.$currentAction['action_choice'])),
                $worker['total_enquete'],
                $worker['total_attack'],
                $worker['total_defence']
            );
        
            $viewHTML .= sprintf(
                '<div class="history"> <h3>Historique : </h3>
                    <p>
                        Originaire de %1$s, '.getConfig($gameReady, 'textViewWorkerJobHobby').' <br />
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
            echo $viewHTML;

            // worker must be active to be allowed actions
            if ($worker['is_active']) {
                $zonesArray = getZonesArray($gameReady);
                if ($_SESSION['DEBUG'] == true) echo "zonesArray: ".var_export($zonesArray, true)."<br /><br />";
                $showZoneSelect = showZoneSelect($gameReady, $zonesArray, false, false);
                if ($_SESSION['DEBUG'] == true) echo "showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";
        
                $controllers = getControllers($gameReady);
                $showcontrollersSelect = showControllerSelect($controllers, 'gift_controller_id');
                $showListClaimTargetsSelect = showControllerSelect($controllers, 'claim_controller_id', TRUE);
        
                $enemyWorkersSelect = showEnemyWorkersSelect($gameReady, $worker['zone_id'], $controller_id);
        
                // TODO on $workerStatus = 'doubleAgent' Warn that worker is controlled by other controller
                $actionHTML .= sprintf('<div class="actions">
                    <form action="/RPGConquestGame/workers/action.php" method="GET">
                    <h3>Actions : </h3> <p>
                    <input type="submit" name="activate" value="%4$s" class="worker-action-btn"> %3$s <br />
                    <input type="submit" name="move" value="Déménager vers :" class="worker-action-btn"> %2$s <br />
                    <input type="submit" name="claim" value="Revendiquer le '.getConfig($gameReady, 'textForZoneType').' au nom de " class="worker-action-btn"> %5$s <br />
                    <input type="submit" name="gift" value="Donner mon serviteur a " class="worker-action-btn"> %6$s <br />
                    </p></div>
                    </form>
                    ',
                    $worker['id'],
                    $showZoneSelect,
                    (empty($enemyWorkersSelect)) ? '' : sprintf(' OU <input type="submit" name="attack" value="Attaquer" class="worker-action-btn"> %s ', $enemyWorkersSelect),
                    ($currentAction['action_choice'] == 'passive') ? "Enquêter" : "Surveiller",
                    $showListClaimTargetsSelect,
                    $showcontrollersSelect
                );
                echo $actionHTML;
            }
            // TODO : on $workerStatus = 'prisonner' show return to owner button


            // worker must be active to get upgrades
            if ($worker['is_active']) {
                $upgradeHTML = sprintf('<div class="upgrade">
                    <h3> Evolutions : </h3>
                    <form action="/RPGConquestGame/workers/action.php" method="GET">
                    <input type="hidden" name="worker_id" value=%1$s>
                ',
                $worker['id']
                );

                // TODO : UPDATE powers on age code ?
                /* ('age_hobby', 'false', ''),
                ('age_metier', 'false', ''), */

                // Allow Discipline teaching via age_discipline param
                $debug_discipline_age = $_SESSION['DEBUG_TRANSFORM'];
                $age_discipline_json = getConfig($gameReady, 'age_discipline');
                if ( $debug_discipline_age ) echo sprintf("age_discipline_json :%s  <br>", $age_discipline_json);
                $age_discipline_array = json_decode($age_discipline_json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo "JSON decoding error: " . json_last_error_msg() . "<br />";
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
                if ( $debug_discipline_age )
                    echo sprintf(
                        "nb_current_disciplines :count(%s) => %s, nb_disciplines: %s <br>",
                        $nb_current_disciplines[0]['discipline_count'], var_export($nb_current_disciplines, true), $nb_disciplines
                    );
                if ( (INT)$nb_current_disciplines[0]['discipline_count'] < (INT)$nb_disciplines) {
                    $upgradeHTML .= sprintf('<input type="submit" name="teach_discipline" value="Enseigner une %2$s " class="worker-upgrade-btn"> %1$s ',
                        showDisciplineSelect($gameReady, $powerDisciplineArray, false),
                        strtolower(getPowerTypesDescription($gameReady, 'Discipline'))
                    );
                }
                // Check Transformation Conditions
                $debug_transformation_age = $_SESSION['DEBUG_TRANSFORM'];
                $age_transformation_json = getConfig($gameReady, 'age_transformation');
                $age_transformation_array = json_decode($age_transformation_json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo "JSON decoding error: " . json_last_error_msg() . "<br />";
                }
                if ( $debug_transformation_age ) echo sprintf("age_transformation_array :%s  <br>", var_export($age_transformation_array, true));
                if (!empty($age_transformation_array['action']) && $age_transformation_array['action'] == 'check' ) {
                    // get transformations
                    $powerTransformationArray = getPowersByType($gameReady,'4', null, false);
                    if ( $debug_transformation_age ) echo sprintf("powerTransformationArray: %s <br />",var_export($powerTransformationArray, true));
                    $powerTransformationArray = cleanPowerListFromJsonConditions($gameReady, $powerTransformationArray, $controller_id, $worker['id'], $mechanics['turncounter'], 'on_transformation' );
                    if ( $debug_transformation_age ) echo sprintf("powerTransformationArray: %s <br/>", var_export($powerTransformationArray, true));
                    if (! empty($powerTransformationArray) )
                        $upgradeHTML .= sprintf('<input type="submit" name="transform" value="Ajouter %2$s " class="worker-upgrade-btn"> %1$s ',
                            showTransformationSelect($gameReady, $powerTransformationArray, false),
                            strtolower(getPowerTypesDescription($gameReady, 'Transformation'))
                        );
                }
    
                $upgradeHTML .= sprintf('</form> </div >');
                echo $upgradeHTML;
            }

            echo sprintf('<div class="report"> <h3> Rapport : </h3>');

            $timeText = getConfig($gameReady, 'timeValue');
            $timeTextThis = getConfig($gameReady, 'timeDenominatorThis');
            foreach ( $worker['actions'] as $turn_number => $action ){
                echo sprintf(
                    '<div class="report week"> <h4> %s </h4>',
                    (isset($action['turn_number']) && (INT)$turn_number == (INT)$mechanics['turncounter'] ) ? ucfirst(sprintf("%s %s", $timeTextThis, $timeText )) : ucfirst(sprintf("%s %s", $timeText, $turn_number ))
                );
                if ($_SESSION['DEBUG_REPORT'])
                    echo "<p> action: ".var_export($action, true)."</p>";
                if ($action['report'] != '{}') {
                    // Decode the existing JSON into an associative array
                    $currentReport = json_decode($action['report'], true);
                    if (!empty($currentReport['life_report']))
                        echo '<h4> Changements : </h4> '.$currentReport['life_report'];
                    if (!empty($currentReport['attack_report']))
                        echo '<h4> Attaques : </h4> '.$currentReport['attack_report'];
                    if (!empty($currentReport['investigate_report']))
                        echo '<h4> Mes investigations : </h4> '.$currentReport['investigate_report'];
                    if (!empty($currentReport['secrets_report']))
                        echo '<h4> Mes recherches : </h4> '.$currentReport['secrets_report'];
                    if (!empty($currentReport['claim_report']))
                        echo '<h4> Controle: </h4> '.$currentReport['claim_report'];
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