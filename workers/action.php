<?php

require_once '../base/basePHP.php';
$pageName = 'workers_action';

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_SESSION['DEBUG'] == true) echo "_GET:".var_export($_GET, true)." <br /> <br />";

    $worker_id = NULL;
    if ( !empty($_GET['worker_id']) ) $worker_id = $_GET['worker_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "worker_id: ".var_export($worker_id, true)."<br /><br />";
    $zone_id = NULL;
    if ( !empty($_GET['zone_id']) ) $zone_id = $_GET['zone_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "zone_id: ".var_export($zone_id, true)."<br /><br />";
    $enemy_worker_id = NULL;
    if ( !empty($_GET['enemy_worker_id']) ) $enemy_worker_id = $_GET['enemy_worker_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "enemy_worker_id: ".var_export($enemy_worker_id, true)."<br /><br />";

    $claim_controller_id = NULL;
    if ( !empty($_GET['claim_controller_id']) ) $claim_controller_id = $_GET['claim_controller_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "claim_controller_id: ".var_export($claim_controller_id, true)."<br /><br />";

    $gift_controller_id = NULL;
    if ( !empty($_GET['gift_controller_id']) ) $gift_controller_id = $_GET['gift_controller_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "gift_controller_id: ".var_export($gift_controller_id, true)."<br /><br />";

    $recall_controller_id = NULL;
    if ( !empty($_GET['recall_controller_id']) ) $recall_controller_id = $_GET['recall_controller_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "recall_controller_id: ".var_export($recall_controller_id, true)."<br /><br />";

    $return_controller_id = NULL;
    if ( !empty($_GET['return_controller_id']) ) $return_controller_id = $_GET['return_controller_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "return_controller_id: ".var_export($return_controller_id, true)."<br /><br />";

    $double_controller_id = NULL;
    if ( !empty($_GET['double_controller_id']) ) $double_controller_id = $_GET['double_controller_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "double_controller_id: ".var_export($double_controller_id, true)."<br /><br />";

    if (isset($_GET['creation'])){
        $worker_id = createWorker($gameReady, $_GET);
        if ($_SESSION['DEBUG'] == true) echo 'createWorker : DONE <br />';
    }

    if (isset($_GET['move'])){
        if (!empty($zone_id)) moveWorker($gameReady, $worker_id, $zone_id);
    }

    if (isset($_GET['attack'])){
        activateWorker($gameReady, $worker_id, 'attack', $enemy_worker_id);
    }
    if (isset($_GET['hide'])){
        activateWorker($gameReady, $worker_id, 'hide');
    }
    if (isset($_GET['passive'])){
        activateWorker($gameReady, $worker_id, 'passive');
    }
    if (isset($_GET['investigate'])){
        activateWorker($gameReady, $worker_id, 'investigate');
    }
    if (isset($_GET['claim'])){
        activateWorker($gameReady, $worker_id, 'claim', $claim_controller_id);
    }
    if (isset($_GET['gift'])){
        activateWorker($gameReady, $worker_id, 'gift', $gift_controller_id);
        header(sprintf('Location: /%s/workers/viewAll.php', $_SESSION['FOLDER']));
    }
    if (isset($_GET['recallDoubleAgent'])){
        activateWorker($gameReady, $worker_id, 'recallDoubleAgent', $recall_controller_id);
    }
    if (isset($_GET['returnPrisoner'])){
        activateWorker(
            $gameReady,
            $worker_id,
            'returnPrisoner',
            array('recall_controller_id' => $recall_controller_id, 'return_controller_id' => $return_controller_id, 'double_controller_id' => $double_controller_id)
        );
        header(sprintf('Location: /%s/workers/viewAll.php', $_SESSION['FOLDER'], $worker_id));
    }

    if (isset($_GET['teach_discipline']) ){
        upgradeWorker($gameReady, $worker_id, $_GET['discipline']);
    }
    if (isset($_GET['transform'])){
        upgradeWorker($gameReady, $worker_id, $_GET['transformation']);
    }

}

require_once '../base/baseHTML.php';
require_once '../workers/view.php';


