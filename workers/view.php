<div class="workers">

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
        foreach ($workersArray as $worker){
            $enemyWorkersSelect = enemyWorkersSelect($gameReady, $worker['zone_id'], $controler_id);
            if ($_SESSION['DEBUG'] == true) echo "showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";
            echo sprintf('<form action="/RPGConquestGame/workers/action.php" method="GET">
                <b onclick="toggleInfo(%1$s)" style="cursor: pointer;" > %2$s %3$s </b> surveille le quartier %4$s.
                <div id="info-%1$s" style="%5$s">
                ',
                $worker['id'],
                $worker['firstname'],
                $worker['lastname'],
                $worker['zone_name'],
                empty($worker_id) ? 'display: none;' : 'display: block;',
            );
            echo sprintf('
                    <i>
                        Originaire de %1$s, c\'etait un %2$s et il est un %3$s <br />
                        Ses disciplines dévéloppées sont: %4$s <br />
                        Capacité d\'enquete : %5$s. Capacité d\'attaque / défense : %6$s / %7$s <br />
                    </i>',
                $worker['origin_name'],
                empty($worker['powers']['Metier']['texte']) ? '' : $worker['powers']['Metier']['texte'],
                empty($worker['powers']['Hobby']['texte']) ? '' : $worker['powers']['Hobby']['texte'],
                empty($worker['powers']['Discipline']['texte']) ? '' : $worker['powers']['Discipline']['texte'],
                $worker['total_enquete'],
                $worker['total_action'],
                $worker['total_defence'],
            );

            echo sprintf('<input type="hidden" name="worker_id" value=%1$s>
                    <input type="submit" name="move"  value="Demenager vers :">
                    %2$s<br />
                    <input type="submit" name="activate" value="%4$s"><br />
                    %3$s<input type="submit" name="attack" value="Attaquer"><br />
                    <input type="submit" name="claim" value="Revendiquer le quartier"><br />
                ',
                $worker['id'],
                $showZoneSelect,
                $enemyWorkersSelect,
                true ? "Enqueter" : "Se cacher"
            );
            echo '</div> </form>';
        }
    }
}
?>
</div>