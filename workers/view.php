<?php
    $workersArray = getWorkersByControler($gameReady ,$_SESSION['controler']['id'])
?>
<div class="agents">
        <h2>Agents</h2>
        <?php
            echo var_export($_SESSION, true);
            echo var_export($workersArray, true);
        ?>
    </div>