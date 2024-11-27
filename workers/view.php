
<div class="workers">
<?php
if ($_SESSION['DEBUG'] == true) echo var_export($_SESSION, true);

if ( !empty($_SESSION['controler']) ) {

    $zonesArray = getZonesArray($gameReady);
    if ($_SESSION['DEBUG'] == true) var_export($zonesArray, true). "<br /><br />";

    $workersArray = [];
    $controler_id = $_SESSION['controler']['id'];
    $workersArray = getWorkersByControler($gameReady, $controler_id);

    echo sprintf("
        <h2>Agents</h2>
        Recrutement :
        <form action='/RPGConquestGame/workers/new.php' method='GET'>
            <input type='hidden' name='controler_id'  value=%s>
            <input type='submit' name='first_come'  value='Prendre le premier venu'>
            <input type='submit' name='recrutement' value='Recruter un serviteur'>
        </form>",
        $controler_id
    );

    if ($_SESSION['DEBUG'] == true ) echo var_export($workersArray, true)."<br><br>";
    foreach ($workersArray as $worker){
        echo sprintf('
            <b onclick="toggleInfo(%1$s)" style="cursor: pointer;"> %2$s %3$s </b> surveille %5$s
            <i id="info-%1$s" style="display: none;">
                Originaire de %4$s, c\'etait un %5$s et il est un %6$s <br>
                Ses disciplines dévéloppées : %7$s
            ',
            $worker['id'],
            $worker['firstname'],
            $worker['lastname'],
            $worker['origin_name'],
            $worker['powers']['Metier']['texte'],
            $worker['powers']['Hobby']['texte'],
            $worker['powers']['Discipline']['texte'] != "" ? $worker['powers']['Discipline']['texte'] : ''
        );
        echo sprintf('
            <form action="/RPGConquestGame/workers/action.php" method="GET">
                <input type="hidden" name="worker_id"  value=%2$s>
                %1$s
                <input type="submit" name="demenager"  value="Demenager">
            </form>  
            ',
            showZoneSelect($zonesArray),
            $worker['id'],
        );
        echo '</i>';
    }
}
?>
</div>