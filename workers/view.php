<div class="workers">

<?php
if ($_SESSION['DEBUG'] == true) echo "_SESSION: ".var_export($_SESSION, true)."<br /><br />";

if ( !empty($_SESSION['controler']) ) {

    $zonesArray = getZonesArray($gameReady);
    if ($_SESSION['DEBUG'] == true) echo "zonesArray: ".var_export($zonesArray, true)."<br /><br />";

    $workersArray = [];
    $controler_id = $_SESSION['controler']['id'];
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
                <input type='hidden' name='controler_id'  value=%$1s>
                <input type='submit' name='first_come'  value='Prendre le premier venu'>
                <input type='submit' name='recrutement' value='Recruter un serviteur'>
            </form>",
            $controler_id
        );
    }else{
        echo sprintf("<h2>Agent</h2>");
    }

    if ($_SESSION['DEBUG'] == true ) echo var_export($workersArray, true)."<br><br>";
    if ( !empty($workersArray) ) {
        foreach ($workersArray as $worker){
            $showZoneSelect = showZoneSelect($zonesArray);
            if ($_SESSION['DEBUG'] == true) echo "showZoneSelect: ".var_export($showZoneSelect, true)."<br /><br />";
            echo sprintf('<form action="/RPGConquestGame/workers/action.php" method="GET">
                <b onclick="toggleInfo(%1$s)" style="cursor: pointer;" > %2$s %3$s </b> surveille le %4$s.
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
                        Originaire de %1$s, c\'etait un %2$s et il est un %3$s <br>
                        Ses disciplines dévéloppées sont: %4$s <br>
                        Capacité d\'enquete : %5$s. Capacité d\'attaque / défense : %6$s / %7$s <br>
                    </i>',
                $worker['origin_name'],
                $worker['powers']['Metier']['texte'],
                $worker['powers']['Hobby']['texte'],
                $worker['powers']['Discipline']['texte'] != "" ? $worker['powers']['Discipline']['texte'] : '',
                $worker['total_enquete'],
                $worker['total_action'],
                $worker['total_defence'],
            );
            echo sprintf('
                    <input type="submit" name="demenager"  value="Demenager vers :"><input type="hidden" name="worker_id" value=%1$s>
                    %2$s
                ',
                $worker['id'],
                $showZoneSelect,
            );
            echo '</div> </form>';
        }
    }
}
?>
</div>