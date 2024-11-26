
<div class="workers">
<?php

    echo var_export($_SESSION, true);

    $workersArray = [];
    if ( isset($_SESSION['controler']) ) {
        $controler_id = $_SESSION['controler']['id'];
        $workersArray = getWorkersByControler($gameReady, $controler_id);
        echo sprintf("
            <h2>Agents</h2>
            <form action='/RPGConquestGame/workers/new.php' method='GET'>
                <input type='hidden' name='controler_id'  value=%s>
                <input type='submit' name='first_come'  value='Prendre le premier venu'>
                <input type='submit' name='recrutement' value='Recruter un serviteur'>
            </form>",
            $controler_id
        );
?>
<?php
    }
    echo var_export($workersArray, true);
?>
</div>