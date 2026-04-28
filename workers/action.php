<?php

require_once '../base/basePHP.php';
$pageName = 'workers_action';

// Check if the user is logged in
if (
    (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)
) {
    // Redirect the user to the login page if not logged in
    header(sprintf('Location: /%s/connection/loginForm.php', $_SESSION['FOLDER']));
    exit();
}

// A worker_id is necessary
$worker_id = NULL;
if ( !empty($_GET['worker_id']) ) $worker_id = $_GET['worker_id'];
if ( $_SESSION['DEBUG'] == true ) echo "worker_id: ".var_export($worker_id, true)."<br /><br />";
// it can be determined by worker creation 
if (isset($_GET['creation'])){
    $worker_id = createWorker($gameReady, $_GET);
    if ($_SESSION['DEBUG'] == true) echo 'createWorker : DONE <br />';
}
// if the is not worker ID this is an error
if (empty($worker_id)) {
    http_response_code(403);
    exit();
}

// If the user is not privileged and not the owner of the worker, he should not have access
if (empty($_SESSION['is_privileged'])) {
    $session_controller_id = $_SESSION['controller']['id'] ?? null;
    if (empty($session_controller_id)) {
        http_response_code(403);
        exit();
    }

    try {
        $prefix = $_SESSION['GAME_PREFIX'];
        $stmt = $gameReady->prepare(
            "SELECT 1 FROM {$prefix}controller_worker
                WHERE worker_id = :wid AND controller_id = :cid LIMIT 1"
        );
        $stmt->execute([':wid' => $worker_id, ':cid' => $session_controller_id]);
        if ($stmt->fetchColumn() === false) {
            http_response_code(403);
            exit();
        }
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT controller_worker Failed: " . $e->getMessage()."<br />";
        http_response_code(403);
        exit();
    }
}

// Blocking trace and dead workers from changing action illogicaly
$MUTATING_ACTIONS = [ 'move', 'attack', 'hide', 'passive', 'investigate',
    'claim', 'gift', 'recallDoubleAgent', 'returnPrisoner',
    'teach_discipline', 'transform'
];
$is_mutating = false;
foreach ($MUTATING_ACTIONS as $k) {
    if (isset($_GET[$k])) { $is_mutating = true; break; }
}
if ($is_mutating && empty($_SESSION['is_privileged']) && $worker_id) {
        $prefix = $_SESSION['GAME_PREFIX'];
        $stmt = $gameReady->prepare(
            "SELECT action_choice FROM {$prefix}worker_actions
             WHERE worker_id = :wid AND turn_number = :turn LIMIT 1"
        );
        $stmt->execute([
            ':wid' => $worker_id,
            ':turn' => $mechanics['turncounter'],
        ]);
        $current_choice = $stmt->fetchColumn();

        if (
            // trace worker should never change action
            $current_choice === 'trace'
            // dead worker sould only be able to transform
            || ($current_choice === 'dead' && !isset($_GET['transform']))
        ) {
            http_response_code(403);
            exit();
        }
}

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_SESSION['DEBUG'] == true) echo "_GET:".var_export($_GET, true)." <br /> <br />";

    $zone_id = NULL;
    if ( !empty($_GET['zone_id']) ) $zone_id = $_GET['zone_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "zone_id: ".var_export($zone_id, true)."<br /><br />";

    $enemy_worker_id = NULL;
    if ( !empty($_GET['enemy_worker_id']) ) $enemy_worker_id = $_GET['enemy_worker_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "enemy_worker_id: ".var_export($enemy_worker_id, true)."<br /><br />";

    $controller_id = NULL;
    if ( !empty($_GET['controller_id']) ) $controller_id = $_GET['controller_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "controller_id: ".var_export($controller_id, true)."<br /><br />";

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


