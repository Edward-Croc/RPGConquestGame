<?php
    $workersArray = getWorkersByControler($gameReady ,$_SESSION['controler']['id'])
?>
<div class="agents">
        <h2>Agents</h2>
        <form action="/RPGConquestGame/workers/new_worker.php" method="GET">
        <button type="submit" name="button" value="first_come">Prendre le premier venu</button>
        <button type="submit" name="button" value="recrutement">Recruter un serviteur</button>
        </form>
        <?php
            echo var_export($_SESSION, true);
            echo var_export($workersArray, true);
        ?>
    </div>