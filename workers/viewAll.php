<?php
require_once '../base/basePHP.php';
$pageName = 'view_workers';
require_once '../base/baseHTML.php';

if ($_SESSION['DEBUG'] == true) echo "_SESSION: ".var_export($_SESSION, true)."<br /><br />";

if ( !empty($_SESSION['controller']) ||  !empty($controller_id) ) {

    if ( $_SESSION['DEBUG'] == true ) echo "_SESSION['controller']['id']: ".var_export($_SESSION['controller']['id'], true)."<br /><br />";
    if ( empty($controller_id) ) $controller_id = $_SESSION['controller']['id'];
    if ( $_SESSION['DEBUG'] == true ) echo "controller_id: ".var_export($controller_id, true)."<br /><br />";

    $zonesArray = getZonesArray($gameReady);
    if ($_SESSION['DEBUG'] == true) echo "zonesArray: ".var_export($zonesArray, true)."<br /><br />";

    // TODO : Change view for DEAD, CAPTURED and Non Primary controller
    $workersArray = getWorkersBycontroller($gameReady, $controller_id);

    echo "<div class='workers'>";
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
            echo '</form> </div>';
        }
    }
}
?>
</div>