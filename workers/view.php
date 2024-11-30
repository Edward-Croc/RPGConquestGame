<?php 

if ($_SESSION['DEBUG'] == true) echo "_SESSION: ".var_export($_SESSION, true)."<br /><br />";

if ( !empty($_SESSION['controler']) ||  !empty($controler_id) ) {

    if ( $_SESSION['DEBUG'] == true ) echo "_SESSION['controler']['id']: ".var_export($_SESSION['controler']['id'], true)."<br /><br />";
    if ( empty($controler_id) ) $controler_id = $_SESSION['controler']['id'];
    if ( $_SESSION['DEBUG'] == true ) echo "controler_id: ".var_export($controler_id, true)."<br /><br />";

    $zonesArray = getZonesArray($gameReady);
    if ($_SESSION['DEBUG'] == true) echo "zonesArray: ".var_export($zonesArray, true)."<br /><br />";

    $workersArray = [];
    if ( !empty ($worker_id) ) {
        $workersArray = getWorkers($gameReady, [$worker_id]);
    } else {
        $workersArray = getWorkersByControler($gameReady, $controler_id);
    }
    echo "<div class='workers'>";
    if ( empty($worker_id) ) {
        echo sprintf("
            <h2>Agents</h2>
            <form action='/RPGConquestGame/workers/new.php' method='GET'>
                <b> Recrutement : </b>
                <input type='hidden' name='controler_id'  value=%s>
                <input type='submit' name='first_come'  value='Prendre le premier venu'>
                <input type='submit' name='recrutement' value='Recruter un serviteur'>
            </form>",
            strval($controler_id)
        );
    } else {
        echo sprintf("<h2>Agent</h2>");
    }

    if ( $_SESSION['DEBUG'] == true ) echo "workersArray: ".var_export($workersArray, true)."<br /><br />";
    if ( !empty($workersArray) ) {
        $showZoneSelect = showZoneSelect($zonesArray);
        $controlers = getControlers($gameReady);
        $showControlerSelect = showControlerSelect($controlers, 'claim_controler_id');
        foreach ($workersArray as $worker){
            $enemyWorkersSelect = enemyWorkersSelect($gameReady, $worker['zone_id'], $controler_id);
            if ($_SESSION['DEBUG'] == true) echo "showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";
            echo sprintf('<div ><form action="/RPGConquestGame/workers/action.php" method="GET">
                <input type="hidden" name="worker_id" value=%1$s>
                <b onclick="toggleInfo(%1$s)" style="cursor: pointer;" > %2$s %3$s </b> surveille le quartier %4$s.
                <div id="info-%1$s" style="%5$s">
                ',
                $worker['id'],
                $worker['firstname'],
                $worker['lastname'],
                $worker['zone_name'],
                empty($worker_id) ? 'display: none;' : 'display: block;',
            );
            echo sprintf('<i> Capacité d\'enquete : %1$s. Capacité d\'attaque / défense : %2$s / %3$s <br /> %4$s</i> </div>',
                $worker['total_enquete'],
                $worker['total_action'],
                $worker['total_defence'],
                empty($worker_id) ? '<input type="submit" name="voir" value="Voir" class="worker-action-btn">' : ''
            );

            if ( !empty($worker_id) ) {

                echo sprintf('<div>
                    <h4>Historique : </h4>
                    <p>
                        Originaire de %1$s, c\'etait un %2$s et il est un %3$s <br />
                        Ses disciplines dévéloppées sont: %4$s <br />
                        Il as été transformer en: %5$s <br />
                    </p></div>',
                    $worker['origin_name'],
                    empty($worker['powers']['Metier']['texte']) ? '' : $worker['powers']['Metier']['texte'],
                    empty($worker['powers']['Hobby']['texte']) ? '' : $worker['powers']['Hobby']['texte'],
                    empty($worker['powers']['Discipline']['texte']) ? '' : $worker['powers']['Discipline']['texte'],
                    empty($worker['powers']['Transformation']['texte']) ? '' : $worker['powers']['Transformation']['texte'],
                );

                echo sprintf('<div> <p>
                        <h4>Actions : </h4>
                        <input type="submit" name="move" value="Demenager vers :" class="worker-action-btn"> %2$s <br />
                        <input type="submit" name="activate" value="%4$s" class="worker-action-btn"> OU 
                        <input type="submit" name="attack" value="Attaquer" class="worker-action-btn"> %3$s OU
                        <input type="submit" name="claim" value="Revendiquer le quartier au nom de" class="worker-action-btn"> %5$s
                    </p> </div>
                    ',
                    $worker['id'],
                    $showZoneSelect,
                    $enemyWorkersSelect,
                    true ? "Enqueter" : "Se cacher",
                    $showControlerSelect,
                );
            }
            echo ' </form>';

            if ( !empty($worker_id) ) {
                echo sprintf('
                    <p>
                        <h4>Rapport : </h4>
                    </p>
                    '
                );
                foreach ( $worker['actions'] as $turn_number => $action ){
                    echo "<div> <h5> Semaine $turn_number </h5> ";
                    if ($_SESSION['DEBUG_REPORT'] == true) "";
                        echo "<p> action: ".var_export($action, true)."</p>";
                    if ($action['report'] != '{}') {
                        // Decode the existing JSON into an associative array
                        $currentReport = json_decode($action['report'], true);
                        echo '<h6> Mes investigations : </h6> '.$currentReport['investigate_report'];
                        
                    }
                    echo "</div>";
                }
            }
            echo ' </div>';
        }
    }
}
?>
</div>