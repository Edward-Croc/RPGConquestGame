<?php

require_once '../base/basePHP.php';

// Check if the user is logged in
if (
    (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)
) {
    header(sprintf('Location: /%s/connection/loginForm.php', $_SESSION['FOLDER']));
    exit();
}

$MASS_ACTIONS = ['mass_move', 'mass_investigate', 'mass_passive', 'mass_hide'];

if ( $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_SESSION['DEBUG'] == true) echo "_GET:".var_export($_GET, true)." <br /> <br />";

    $worker_ids = NULL;
    if ( !empty($_GET['worker_ids']) ) $worker_ids = $_GET['worker_ids'];
    if ( $_SESSION['DEBUG'] == true ) echo "worker_ids: ".var_export($worker_ids, true)."<br /><br />";
    $zone_id = NULL;
    if ( !empty($_GET['zone_id']) ) $zone_id = $_GET['zone_id'];
    if ( $_SESSION['DEBUG'] == true ) echo "zone_id: ".var_export($zone_id, true)."<br /><br />";

    $mass_action_requested = false;
    foreach ($MASS_ACTIONS as $k) {
        if (isset($_GET[$k])) { $mass_action_requested = true; break; }
    }

    if ($mass_action_requested && !empty($worker_ids)) {
        if (!is_array($worker_ids)) { http_response_code(403); exit(); }
        $worker_ids = array_map('intval', $worker_ids);

        if (empty($_SESSION['is_privileged'])) {
            $session_controller_id = $_SESSION['controller']['id'] ?? null;
            if (empty($session_controller_id)) { http_response_code(403); exit(); }

            try {
                $prefix = $_SESSION['GAME_PREFIX'];
                $placeholders = implode(',', array_fill(0, count($worker_ids), '?'));
                $stmt = $gameReady->prepare(
                    "SELECT COUNT(*) FROM {$prefix}controller_worker
                     WHERE controller_id = ? AND worker_id IN ($placeholders)"
                );
                $stmt->execute(array_merge([$session_controller_id], $worker_ids));
                if ((int)$stmt->fetchColumn() !== count($worker_ids)) {
                    http_response_code(403); exit();
                }
            } catch (PDOException $e) {
                echo __FUNCTION__."(): SELECT controller_worker Failed: " . $e->getMessage()."<br />";
                http_response_code(403); exit();
            }
        }

        if (isset($_GET['mass_move']) && !empty($zone_id)) {
            foreach ($worker_ids as $worker_id) {
                moveWorker($gameReady, $worker_id, $zone_id);
            }
        } else if (isset($_GET['mass_investigate'])) {
            foreach ($worker_ids as $worker_id) {
                activateWorker($gameReady, $worker_id, 'investigate');
            }
        } else if (isset($_GET['mass_passive'])) {
            foreach ($worker_ids as $worker_id) {
                activateWorker($gameReady, $worker_id, 'passive');
            }
        } else if (isset($_GET['mass_hide'])) {
            foreach ($worker_ids as $worker_id) {
                activateWorker($gameReady, $worker_id, 'hide');
            }
        }
    }
}
$_SESSION['DEBUG'] = false;
require_once '../workers/viewAll.php';


