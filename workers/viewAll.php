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
                <h3> Recrutement : </h3>
                <input type='hidden' name='controller_id' value='%s'>
                %s
                %s
            </form>",
            $controller_id,
            $firstComeButton,
            $recruitButton
        );

        // TODO : Change view for DEAD, CAPTURED and Non Primary controller
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

                $worker['view'] = showWorkerShort($gameReady, $worker, $mechanics);

                // liveWorkerArray : worker alive and active and that we control
                if ( $worker['is_alive'] && $worker['is_active'] && $worker['is_primary_controller'] )
                    $liveWorkerArray[] = $worker;

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
            if ( !empty($liveWorkerArray )) {
                echo "<div > <h3>Nos Agents :</h3>";
                foreach ($liveWorkerArray as $worker) {
                    echo $worker['view'];
                }
                echo "</div>";
            }

            if ( !empty($doubleAgentWorkerArray )) {
                echo "<div > <h3>Nos Agents doubles :</h3>";
                foreach ($doubleAgentWorkerArray as $worker) {
                    echo $worker['view'];
                }
                echo "</div>";
            }

            if ( !empty($prisonersWorkerArray )) {
                echo "<div > <h3>Nos Prisonniers :</h3>";
                foreach ($prisonersWorkerArray as $worker) {
                    echo $worker['view'];
                }
                echo "</div>";
            }

            if ( !empty($deadWorkerArray )) {
                echo "<div > <h3>Nos morts :</h3>";
                foreach ($deadWorkerArray as $worker) {
                    echo $worker['view'];
                }
                echo "</div>";
            }
        }
    echo "</div>"; // closing class='workers'
}