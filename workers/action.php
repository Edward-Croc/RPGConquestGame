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
        $claim_mode = getConfig($gameReady, 'claimMode');
        if (!in_array($claim_mode, ['worker', 'worker_leader'], true)) {
            http_response_code(403);
            exit();
        }
        activateWorker($gameReady, $worker_id, 'claim', $claim_controller_id);
    }
    if (isset($_GET['gift'])){
        $session_controller_id = $_SESSION['controller']['id'] ?? null;
        if (empty($_SESSION['is_privileged']) && $session_controller_id !== null && (int)$gift_controller_id === (int)$session_controller_id) {
            http_response_code(403);
            exit();
        }
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
        if (!empty($_SESSION['is_privileged']) && empty($_SESSION['controller']['id'])) {
            upgradeWorker($gameReady, $worker_id, $_GET['discipline']);
        } else {
            $session_controller_id = $_SESSION['controller']['id'] ?? null;
            $turn = $mechanics['turncounter'] ?? 0;
            $linkPowerTypeId = $_GET['discipline'] ?? null;
            if (empty($linkPowerTypeId) || empty($session_controller_id)) {
                http_response_code(403);
                exit();
            }
            $candidates = getPowersByType($gameReady, '3', $session_controller_id, true);
            $candidates = cleanPowerListFromJsonConditions($gameReady, $candidates, $session_controller_id, $worker_id, $turn, 'on_age');
            $matchedPower = null;
            if (is_array($candidates)) {
                foreach ($candidates as $p) {
                    if ((string)$p['link_power_type_id'] === (string)$linkPowerTypeId) { $matchedPower = $p; break; }
                }
            }
            if ($matchedPower === null) {
                echo "Cette discipline n'est plus disponible.<br />";
            } else {
                upgradeWorker($gameReady, $worker_id, $linkPowerTypeId);
            }
        }
    }
    if (isset($_GET['transform'])){
        if (!empty($_SESSION['is_privileged']) && empty($_SESSION['controller']['id'])) {
            upgradeWorker($gameReady, $worker_id, $_GET['transformation']);
        } else {
            $session_controller_id = $_SESSION['controller']['id'] ?? null;
            $turn = $mechanics['turncounter'] ?? 0;
            $linkPowerTypeId = $_GET['transformation'] ?? null;
            if (empty($linkPowerTypeId) || empty($session_controller_id)) {
                http_response_code(403);
                exit();
            }
            $candidates = getPowersByType($gameReady, '4', NULL, false);
            $candidates = cleanPowerListFromJsonConditions($gameReady, $candidates, $session_controller_id, $worker_id, $turn, 'on_transformation');
            $matchedPower = null;
            if (is_array($candidates)) {
                foreach ($candidates as $p) {
                    if ((string)$p['link_power_type_id'] === (string)$linkPowerTypeId) { $matchedPower = $p; break; }
                }
            }
            if ($matchedPower === null) {
                echo "Cette transformation n'est plus disponible.<br />";
            } else {
                $cost = getRuleCostForPower($gameReady, $matchedPower, $session_controller_id, $worker_id, $turn, 'on_transformation');
                if ($cost === null) {
                    upgradeWorker($gameReady, $worker_id, $linkPowerTypeId);
                } else {
                    $gameReady->beginTransaction();
                    if (consumeRessource($gameReady, (int)$session_controller_id, $cost['ressource_id'], $cost['amount'])) {
                        if (upgradeWorker($gameReady, $worker_id, $linkPowerTypeId)) {
                            $gameReady->commit();
                        } else {
                            $gameReady->rollBack();
                        }
                    } else {
                        $gameReady->rollBack();
                        echo "Stock insuffisant ou modifié.<br />";
                    }
                }
            }
        }
    }

}

// Resolve effective sort for prev/next nav buttons — URL > session > 'age', both whitelist-validated
$navValidSorts = ['age', 'zone', 'investigate', 'attack'];
$sort = 'age';
if (in_array($_GET['sort'] ?? '', $navValidSorts, true)) {
    $sort = $_GET['sort'];
} elseif (in_array($_SESSION['workers_view_sort'] ?? '', $navValidSorts, true)) {
    $sort = $_SESSION['workers_view_sort'];
}

// Resolve prev / next worker ids + bucket class for the card background
$navControllerId = (int)($_SESSION['controller']['id'] ?? 0);
$navIds = ['prev' => null, 'next' => null];
$navBucketClass = '';
if ($navControllerId > 0 && !empty($worker_id)) {
    $navIds = getPrevNextWorkerIds(
        $gameReady,
        $navControllerId,
        (int)$worker_id,
        $sort,
        (int)($mechanics['turncounter'] ?? 0)
    );
    $navWorkerArray = getWorkers($gameReady, [$worker_id]);
    $navWorkerRow = null;
    foreach ($navWorkerArray ?? [] as $row) {
        if ((int)($row['controller_id'] ?? 0) === $navControllerId) {
            $navWorkerRow = $row;
            break;
        }
    }
    if ($navWorkerRow !== null) {
        $navWorkerStatus = getWorkerStatus($navWorkerRow, $mechanics);
        if ($navWorkerStatus && $navWorkerStatus !== 'unfound') {
            $navBucketClass = 'is-bucket-' . str_replace('_', '-', $navWorkerStatus);
        }
    }
}

// Back URL — Referer-aware with strict-equality whitelist + host equality
$navFolder = $_SESSION['FOLDER'] ?? '';
$navBackUrl = "/{$navFolder}/workers/viewAll.php";
$navReferer = $_SERVER['HTTP_REFERER'] ?? '';
if ($navReferer !== '') {
    $navRefererParts = parse_url($navReferer);
    $navRefererHost = $navRefererParts['host'] ?? '';
    if (isset($navRefererParts['port'])) {
        $navRefererHost .= ':' . $navRefererParts['port'];
    }
    $navRefererPath = rtrim($navRefererParts['path'] ?? '', '/');
    if ($navRefererHost === ($_SERVER['HTTP_HOST'] ?? '')) {
        // Whitelist the canonical entry points (action.php / accueil.php).
        // *view.php files are include-only (403 direct) so they shouldn't
        // appear as a real Referer in practice — kept defensively.
        $navAllowedPaths = [
            "/{$navFolder}/workers/viewAll.php",
            "/{$navFolder}/zones/action.php",
            "/{$navFolder}/zones/view.php",
            "/{$navFolder}/controllers/action.php",
            "/{$navFolder}/controllers/view.php",
            "/{$navFolder}/base/accueil.php",
        ];
        foreach ($navAllowedPaths as $allowed) {
            if ($navRefererPath === rtrim($allowed, '/')) {
                $navBackUrl = $navReferer;
                break;
            }
        }
    }
}

require_once '../base/baseHTML.php';
require_once '../workers/view.php';


