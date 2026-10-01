<?php

require_once '../base/basePHP.php';

// $GLOBALS['DEBUG_LOG_SECTIONS'][] = 'workers_mass_action_page';  // uncomment to log DEBUG events from this page

// Check if the user is logged in
if (
    (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)
) {
    header(sprintf('Location: /%s/connection/loginForm.php', $_SESSION['FOLDER']));
    exit();
}

$MASS_ACTIONS = ['mass_move', 'mass_investigate', 'mass_passive', 'mass_hide', 'mass_claim'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_SESSION['DEBUG'] == true) {
        echo "_GET:".var_export($_GET, true)." <br /> <br />";
    }

    $worker_ids = null;
    if (!empty($_GET['worker_ids'])) {
        $worker_ids = $_GET['worker_ids'];
    }
    if ($_SESSION['DEBUG'] == true) {
        echo "worker_ids: ".var_export($worker_ids, true)."<br /><br />";
    }
    $zone_id = null;
    if (!empty($_GET['zone_id'])) {
        $zone_id = $_GET['zone_id'];
    }
    $claim_controller_id = null;
    if (!empty($_GET['claim_controller_id'])) {
        $claim_controller_id = $_GET['claim_controller_id'];
    }
    if ($_SESSION['DEBUG'] == true) {
        echo "zone_id: ".var_export($zone_id, true)."<br /><br />";
    }

    $mass_action_requested = false;
    foreach ($MASS_ACTIONS as $k) {
        if (isset($_GET[$k])) {
            $mass_action_requested = true;
            break;
        }
    }

    if ($mass_action_requested && !empty($worker_ids)) {
        if (!is_array($worker_ids)) {
            game_error_log('workers_mass_action_page', 'worker_ids not an array', ['worker_ids' => $worker_ids], 'warning');
            http_response_code(403);
            exit();
        }
        $worker_ids = array_map('intval', $worker_ids);

        if (empty($_SESSION['is_privileged'])) {
            $session_controller_id = $_SESSION['controller']['id'] ?? null;
            if (empty($session_controller_id)) {
                game_error_log('workers_mass_action_page', 'missing session controller_id', [], 'warning');
                http_response_code(403);
                exit();
            }

            try {
                $prefix = $_SESSION['GAME_PREFIX'];
                $placeholders = implode(',', array_fill(0, count($worker_ids), '?'));
                $stmt = $gameReady->prepare(
                    "SELECT COUNT(*) FROM {$prefix}controller_worker
                     WHERE controller_id = ? AND worker_id IN ($placeholders)"
                );
                $stmt->execute(array_merge([$session_controller_id], $worker_ids));
                if ((int)$stmt->fetchColumn() !== count($worker_ids)) {
                    game_error_log('workers_mass_action_page', 'controller_worker ownership mismatch', ['session_controller_id' => $session_controller_id, 'worker_ids' => $worker_ids], 'warning');
                    http_response_code(403);
                    exit();
                }
            } catch (PDOException $e) {
                game_error_log('workers_mass_action_page', 'SELECT controller_worker failed : ' . $e->getMessage(), ['session_controller_id' => $session_controller_id, 'worker_ids' => $worker_ids], 'error');
                http_response_code(403);
                exit();
            }
        }

        // An inactive worker keeps its action : a captor must not put a prisoner back to work.
        if (empty($_SESSION['is_privileged'])) {
            try {
                $prefix = $_SESSION['GAME_PREFIX'];
                $placeholders = implode(',', array_fill(0, count($worker_ids), '?'));
                $inactive = implode(',', array_fill(0, count(INACTIVE_ACTIONS), '?'));
                // Counting what may act, not what may not : a row we cannot read then refuses.
                $stmt = $gameReady->prepare(
                    "SELECT COUNT(*) FROM {$prefix}worker_actions
                     WHERE turn_number = ? AND worker_id IN ($placeholders)
                       AND action_choice NOT IN ($inactive)"
                );
                $stmt->execute(array_merge([$mechanics['turncounter']], $worker_ids, INACTIVE_ACTIONS));
                if ((int)$stmt->fetchColumn() !== count($worker_ids)) {
                    game_error_log('workers_mass_action_page', 'inactive or unreadable worker in mass action', ['worker_ids' => $worker_ids], 'warning');
                    http_response_code(403);
                    exit();
                }
            } catch (PDOException $e) {
                game_error_log('workers_mass_action_page', 'SELECT worker_actions failed : ' . $e->getMessage(), ['worker_ids' => $worker_ids], 'error');
                http_response_code(403);
                exit();
            }
        }

        if (isset($_GET['mass_move'])) {
            // The zone select never renders an empty option, so a move without one is forged.
            if (empty($zone_id)) {
                game_error_log('workers_mass_action_page', 'mass move submitted without a zone', ['worker_ids' => $worker_ids], 'warning');
                http_response_code(400);
                exit();
            }
            foreach ($worker_ids as $worker_id) {
                moveWorker($gameReady, $worker_id, $zone_id);
            }
        } elseif (isset($_GET['mass_investigate'])) {
            foreach ($worker_ids as $worker_id) {
                activateWorker($gameReady, $worker_id, 'investigate');
            }
        } elseif (isset($_GET['mass_passive'])) {
            foreach ($worker_ids as $worker_id) {
                activateWorker($gameReady, $worker_id, 'passive');
            }
        } elseif (isset($_GET['mass_hide'])) {
            foreach ($worker_ids as $worker_id) {
                activateWorker($gameReady, $worker_id, 'hide');
            }
        } elseif (isset($_GET['mass_claim']) && !empty($claim_controller_id)) {
            // Ignored in silence when the scenario runs another claim mode
            if (in_array(getConfig($gameReady, 'claimMode'), ['worker', 'worker_leader'], true)) {
                foreach ($worker_ids as $worker_id) {
                    activateWorker($gameReady, $worker_id, 'claim', $claim_controller_id);
                }
            }
        }
    }
}
$_SESSION['DEBUG'] = false;
require_once '../workers/viewAll.php';
