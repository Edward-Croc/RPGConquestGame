<?php
if ($_SESSION['DEBUG'] == true) echo "_SESSION: ".var_export($_SESSION, true)."<br /><br />";

if ( !empty($_SESSION['controller']) ||  !empty($controller_id) ) {

    if ( $_SESSION['DEBUG'] == true ) echo "_SESSION['controller']['id']: ".var_export($_SESSION['controller']['id'], true)."<br /><br />";
    if ( empty($controller_id) ) $controller_id = $_SESSION['controller']['id'];
    if ( $_SESSION['DEBUG'] == true ) echo "controller_id: ".var_export($controller_id, true)."<br /><br />";

    $zonesArray = getZonesArray($gameReady);
    if ($_SESSION['DEBUG'] == true) echo "zonesArray: ".var_export($zonesArray, true)."<br /><br />";

    $workersArray = [];
    // TODO : Change view for DEAD, CAPTURED and Non Primary controller
    if ( !empty ($worker_id) ) {
        $workersArray = getWorkers($gameReady, [$worker_id]);
    } else {
        $workersArray = getWorkersBycontroller($gameReady, $controller_id);
    }

    echo "<div class='workers'>";
    if ( empty($worker_id) ) {
        $recruitButton = "";
        if (canStartRecrutement($gameReady, $controller_id, (INT)$mechanics['turncounter'])){
            $recruitButton = "<input type='submit' name='recrutement' value='Recruter un serviteur'>";
        } elseif (empty(hasBase($gameReady, $controller_id))) {
            $recruitButton = getConfig($gameReady, 'textcontrollerRecrutmentNeedsBase');
        }

        $firstComeButton = "";
        if (canStartFirstCome($gameReady, $controller_id))
            $firstComeButton = "<input type='submit' name='first_come' value='Prendre le premier venu'>";

            echo sprintf("
            <h1>Agents</h1>
            <form action='/RPGConquestGame/workers/new.php' method='GET'>
                <b> Recrutement : </b>
                <input type='hidden' name='controller_id' value='%s'>
                %s
                %s
            </form>",
            htmlspecialchars($controller_id),
            $firstComeButton,
            $recruitButton
        );
    } else {
        echo sprintf("<h1>Agent</h1>");
    }

    if ( $_SESSION['DEBUG'] == true ) echo "workersArray: ".var_export($workersArray, true)."<br /><br />";
    if ( !empty($workersArray) ) {
        $showZoneSelect = showZoneSelect($gameReady, $zonesArray, FALSE, FALSE);
        if ($_SESSION['DEBUG'] == true) echo "showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";

        if ( !empty($worker_id) ) {
            $controllers = getControllers($gameReady);
            $showcontrollersSelect = showControllerSelect($controllers, 'gift_controller_id');
            $showListClaimTargetsSelect = showControllerSelect($controllers, 'claim_controller_id', TRUE);
        }

        if ( $_SESSION['DEBUG'] == true ) echo sprintf('workersArray : %s <br>', var_export($workersArray,true));
        $currentAction = array();
        foreach ($workersArray as $worker){
            if ( $_SESSION['DEBUG'] == true ) echo sprintf('mechanics[turncounter] : %s  <br>', var_export($mechanics['turncounter'],true));
            foreach($worker['actions'] as $action) {
                if ( $_SESSION['DEBUG'] == true ) echo sprintf('workersArray as worker => worker[actions] as action : %s  <br>', var_export($action,true));
                if ( $_SESSION['DEBUG'] == true ) echo sprintf('action[turn_number] : %s  <br>', var_export($action['turn_number'],true));
                if ( (INT)$action['turn_number'] == (INT)$mechanics['turncounter'] ) {
                    if ( $_SESSION['DEBUG'] == true ) echo "Set current action <br>";
                    $currentAction = $action;
                }
            }
            if ( $_SESSION['DEBUG'] == true ) echo sprintf('currentAction : %s  <br>', var_export($currentAction,true));

            echo sprintf('<div ><form action="/RPGConquestGame/workers/action.php" method="GET">
                <input type="hidden" name="worker_id" value=%1$s>
                <b onclick="toggleInfo(%1$s)" style="cursor: pointer;" > %2$s %3$s (%1$s) </b> %6$s au %4$s.
                <div id="info-%1$s" style="%5$s">
                ',
                $worker['id'],
                $worker['firstname'],
                $worker['lastname'],
                $worker['zone_name'],
                empty($worker_id) ? 'display: none;' : 'display: block;',
                getConfig($gameReady,'txt_ps_'.$currentAction['action_choice'])
            );
            echo sprintf('<i> Capacité d’enquete : %1$s. Capacité d’attaque / défense : %2$s / %3$s <br /> %4$s</i> </div>',
                $worker['total_enquete'],
                $worker['total_attack'],
                $worker['total_defence'],
                empty($worker_id) ? '<input type="submit" name="voir" value="Voir" class="worker-action-btn">' : ''
            );

            if ( !empty($worker_id) ) {
                $enemyWorkersSelect = showEnemyWorkersSelect($gameReady, $worker['zone_id'], $controller_id);

                echo sprintf('<div class="history">
                    <h3>Historique : </h3>
                    <p>
                        Originaire de %1$s, '.getConfig($gameReady, 'textViewWorkerJobHobby').' <br />
                        %4$s %5$s
                    </p></div>',
                    $worker['origin_name'],
                    empty($worker['powers']['Metier']['texte']) ? '' : $worker['powers']['Metier']['texte'],
                    empty($worker['powers']['Hobby']['texte']) ? '' : $worker['powers']['Hobby']['texte'],
                    empty($worker['powers']['Discipline']['texte']) ? '' :
                        sprintf(getConfig($gameReady, 'textViewWorkerDisciplines'),$worker['powers']['Discipline']['texte']),
                    empty($worker['powers']['Transformation']['texte']) ? '' :
                        sprintf(getConfig($gameReady, 'textViewWorkerTransformations'), $worker['powers']['Transformation']['texte']),
                );
                if ($worker['is_active'])
                    echo sprintf('<div class="actions">
                        <h3>Actions : </h3> <p>
                        <input type="submit" name="activate" value="%4$s" class="worker-action-btn"> %3$s <br />
                        <input type="submit" name="move" value="Déménager vers :" class="worker-action-btn"> %2$s <br />
                        <input type="submit" name="claim" value="Revendiquer le '.getConfig($gameReady, 'textForZoneType').' au nom de " class="worker-action-btn"> %5$s <br />
                        <input type="submit" name="gift" value="Donner mon serviteur a " class="worker-action-btn"> %6$s <br />
                        </p></div>
                        ',
                        $worker['id'],
                        $showZoneSelect,
                        (empty($enemyWorkersSelect)) ? '' : sprintf(' OU <input type="submit" name="attack" value="Attaquer" class="worker-action-btn"> %s ', $enemyWorkersSelect),
                        ($currentAction['action_choice'] == 'passive') ? "Enquêter" : "Surveiller",
                        $showListClaimTargetsSelect,
                        $showcontrollersSelect
                    );
            }
            echo '</form>';

            if ( !empty($worker_id) ) {
                $upgrade_HTML = sprintf('<div class="upgrade">
                    <h3> Evolutions : </h3>
                    <form action="/RPGConquestGame/workers/action.php" method="GET">
                    <input type="hidden" name="worker_id" value=%1$s>
                ',
                $worker['id']
                );

                // TODO : UPDATE powers on age code ?
                /* ('age_hobby', 'FALSE', ''),
                ('age_metier', 'FALSE', ''), */

                // Allow Discipline teaching via age_discipline param
                if ($worker['is_active']) {
                    $debug_discipline_age = $_SESSION['DEBUG_TRANSFORM'];
                    $age_discipline_json = getConfig($gameReady, 'age_discipline');
                    if ( $debug_discipline_age ) echo sprintf("age_discipline_json :%s  <br>", $age_discipline_json);
                    $age_discipline_array = json_decode($age_discipline_json, True);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo "JSON decoding error: " . json_last_error_msg() . "<br />";
                    }
                    if ( $debug_discipline_age ) echo sprintf("age_discipline_array :%s  <br>", var_export($age_discipline_array, True));
                    $powerDisciplineArray = getPowersByType($gameReady,'3', $controller_id, True);
                    if ( $debug_discipline_age ) echo sprintf("powerDisciplineArray : %s <br/>", var_export($powerDisciplineArray, True));
                    $powerDisciplineArray = cleanPowerListFromJsonConditions($gameReady, $powerDisciplineArray, $controller_id, $worker['id'], $mechanics['turncounter'], 'on_age' );
                    if ( $debug_discipline_age ) echo sprintf("powerDisciplineArray : %s <br/>", var_export($powerDisciplineArray, True));
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
                        $upgrade_HTML .= sprintf('<input type="submit" name="teach_discipline" value="Enseigner une %2$s " class="worker-upgrade-btn"> %1$s ',
                            showDisciplineSelect($gameReady, $powerDisciplineArray, False),
                            strtolower(getPowerTypesDescription($gameReady, 'Discipline'))
                        );
                    }
                }
                // Check Transformation Conditions
                $debug_transformation_age = $_SESSION['DEBUG_TRANSFORM'];
                $age_transformation_json = getConfig($gameReady, 'age_transformation');
                $age_transformation_array = json_decode($age_transformation_json, True);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo "JSON decoding error: " . json_last_error_msg() . "<br />";
                }
                if ( $debug_transformation_age ) echo sprintf("age_transformation_array :%s  <br>", var_export($age_transformation_array, True));
                if (!empty($age_transformation_array['action']) && $age_transformation_array['action'] == 'check' ) {
                    // get transformations
                    $powerTransformationArray = getPowersByType($gameReady,'4', Null, False);
                    if ( $debug_transformation_age ) echo sprintf("powerTransformationArray: %s <br />",var_export($powerTransformationArray, True));
                    $powerTransformationArray = cleanPowerListFromJsonConditions($gameReady, $powerTransformationArray, $controller_id, $worker['id'], $mechanics['turncounter'], 'on_transformation' );
                    if ( $debug_transformation_age ) echo sprintf("powerTransformationArray: %s <br/>", var_export($powerTransformationArray, True));
                    if (! empty($powerTransformationArray) )
                        $upgrade_HTML .= sprintf('<input type="submit" name="transform" value="Ajouter %2$s " class="worker-upgrade-btn"> %1$s ',
                            showTransformationSelect($gameReady, $powerTransformationArray, False),
                            strtolower(getPowerTypesDescription($gameReady, 'Transformation'))
                        );
                }

                $upgrade_HTML .= sprintf('</form> </div >');
                echo $upgrade_HTML;

                echo sprintf('<div class="report"> <h3> Rapport : </h3>');

                $timeText = getConfig($gameReady, 'timeValue');
                $timeTextThis = getConfig($gameReady, 'timeDenominatorThis');
                foreach ( $worker['actions'] as $turn_number => $action ){
                    echo sprintf(
                        '<div class="report week"> <h4> %s </h4>',
                        ( (INT)$turn_number == (INT)$mechanics['turncounter'] ) ? ucfirst(sprintf("%s %s", $timeTextThis, $timeText )) : ucfirst(sprintf("%s %s", $timeText, $turn_number ))
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
            echo ' </div>';
        }
    }
}
?>
</div>