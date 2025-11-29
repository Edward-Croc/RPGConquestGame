<?php

if (empty($pageName)) {
    require_once '../base/basePHP.php';
    $pageName = 'view_workers';
    require_once '../base/baseHTML.php';
}

if ($_SESSION['DEBUG'] == true) echo "_SESSION: ".var_export($_SESSION, true)."<br /><br />";

if ( !empty($_SESSION['controller']) ||  !empty($controller_id) ) {

    if ( $_SESSION['DEBUG'] == true ) echo "_SESSION['controller']['id']: ".var_export($_SESSION['controller']['id'], true)."<br /><br />";
    if ( empty($controller_id) ) $controller_id = $_SESSION['controller']['id'];
    if ( $_SESSION['DEBUG'] == true ) echo "controller_id: ".var_export($controller_id, true)."<br /><br />";

    echo "<div class='section workers'>";
        $recruitButton = "";
        if (canStartRecrutement($gameReady, $controller_id, (INT)$mechanics['turncounter'])){
            $recruitButton = "<input type='submit' name='recrutement' value='Recruter un serviteur' class='button is-link'>";
        } elseif (empty(hasBase($gameReady, $controller_id))) {
            $recruitButton = "<span class='has-text-danger'>" . getConfig($gameReady, 'textcontrollerRecrutmentNeedsBase') . "</span>";
        }

        $firstComeButton = "";
        if (canStartFirstCome($gameReady, $controller_id))
            $firstComeButton = "<input type='submit' name='first_come' value='Prendre le premier venu' class='button is-info'>";

        echo sprintf("
            <h1 class='title is-2'>Agents</h1>
            <form action='/%s/workers/new.php' method='GET' class='box mb-5'>
                <h3 class='title is-4'>Recrutement :</h3>
                <input type='hidden' name='controller_id' value='%s'>
                <div class='field is-grouped is-grouped-multiline'>
                    <div class='control'>%s</div>
                    <div class='control'>%s</div>
                </div>
            </form>",
            $_SESSION['FOLDER'],
            $controller_id,
            $firstComeButton,
            $recruitButton
        );

        $workersArray = getWorkersBycontroller($gameReady, $controller_id);

        if ( $_SESSION['DEBUG'] == true )
            echo "workersArray: ".var_export($workersArray, true)."<br /><br />";
        if ( !empty($workersArray) ) {
            $liveWorkerArray = array();
            $doubleAgentWorkerArray = array();
            $prisonersWorkerArray = array();
            $deadWorkerArray = array();
            foreach ($workersArray as $worker){
                if ( $worker['controller_id'] != $controller_id) continue;
                if ( $_SESSION['DEBUG'] == true ) echo sprintf('mechanics[turncounter] : %s  <br>', var_export($mechanics['turncounter'],true));

                // liveWorkerArray : worker alive and active and that we control
                if ( $worker['is_alive'] && $worker['is_active'] && $worker['is_primary_controller'] ) {
                    $worker['view'] = showWorkerShort($gameReady, $worker, $mechanics, true);
                    $liveWorkerArray[] = $worker;
                } else {
                    $worker['view'] = showWorkerShort($gameReady, $worker, $mechanics);
                }

                //doubleAgentWorkerArray : worker alive and active that we don't control
                if ( $worker['is_alive'] && $worker['is_active'] && !$worker['is_primary_controller'] )
                    $doubleAgentWorkerArray[] = $worker;

                //prisonersWorkerArray : worker alive and not active that we do control are our prisonners
                if ( $worker['is_alive'] && !$worker['is_active'] && $worker['is_primary_controller'] )
                    $prisonersWorkerArray[] = $worker;

                // deadWorkerArray : our dead (worker not alive) or our workers prisonner of others (worker alive and not active that we do not control) 
                if ( !$worker['is_alive'] || ( $worker['is_alive'] && !$worker['is_active'] && !$worker['is_primary_controller']  ) )
                    $deadWorkerArray[] = $worker;
            }
            if (!empty($liveWorkerArray)) {
                echo "<div class='box mb-4'> <h3 class='title is-5'>Nos Agents :</h3>";
                // Mass worker action form
                echo sprintf("<form action='/%s/workers/massAction.php' method='GET' class='mb-4'>", $_SESSION['FOLDER']);
                foreach ($liveWorkerArray as $worker) {
                    echo $worker['view'];
                }
                // Mass worker action
                // Mass move to zone
                    // Zone select
                    echo '<div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">';
                    echo showZoneSelect($gameReady, getZonesArray($gameReady), null, false, false, true);
                    echo " <div class='control'> <input type='submit' name='mass_move' value='Déplacer les agents sélectionnés' class='button is-warning mb-2 ml-2'></div>";
                    echo "</div>";
                    // Submit button
                echo "</form>";
                echo "</div>";
            }

            if ( !empty($doubleAgentWorkerArray )) {
                echo "<div class='box mb-4'> <h3 class='title is-5'>Nos Agents doubles :</h3>";
                foreach ($doubleAgentWorkerArray as $worker) {
                    echo $worker['view'];
                }
                echo "</div>";
            }

            if ( !empty($prisonersWorkerArray )) {
                echo "<div class='box mb-4'> <h3 class='title is-5'>Nos Prisonniers :</h3>";
                foreach ($prisonersWorkerArray as $worker) {
                    echo $worker['view'];
                }
                echo "</div>";
            }

            if ( !empty($deadWorkerArray )) {
                echo "<div class='box mb-4'> <h3 class='title is-5'>Nos Anciens agents :</h3>";
                foreach ($deadWorkerArray as $worker) {
                    echo $worker['view'];
                }
                echo "</div>";
            }
        }
    echo "</div>"; // closing class='workers'
}