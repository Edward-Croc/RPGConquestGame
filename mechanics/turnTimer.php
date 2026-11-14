<?php

require_once '../base/basePHP.php';

// Privileged-only: the timer commands are an MJ prerogative.
if (empty($_SESSION['is_privileged'])) {
    game_error_log('turnTimer_page', 'Non-privileged timer command attempt', ['user_id' => $_SESSION['user_id'] ?? null], 'warning');
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

// The session token is checked but not burned, the commands are repeatable.
$submittedToken = is_string($_POST['end_turn_token'] ?? null) ? $_POST['end_turn_token'] : '';
$expectedToken = $_SESSION['end_turn_token'] ?? '';
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || $submittedToken === ''
    || $expectedToken === ''
    || !hash_equals($expectedToken, $submittedToken)
) {
    game_error_log('turnTimer_page', 'Timer command refused, missing or invalid token', ['method' => $_SERVER['REQUEST_METHOD'] ?? ''], 'warning');
    http_response_code(403);
    echo 'Commande refusée, rechargez la page.';
    exit();
}

$action = is_string($_POST['turn_timer_action'] ?? null) ? $_POST['turn_timer_action'] : '';
$timerState = getTurnTimerState($mechanics ?? []);
if ($timerState !== null && in_array($action, ['start', 'pause', 'resume', 'stop'], true)) {
    $now = time();
    $deadline = $timerState['deadline'];
    $remaining = $timerState['remaining'];
    $newDeadline = null;
    $newRemaining = null;
    $write = true;

    if ($action === 'start') {
        // Only a stopped timer starts, and nothing is written without a deadline.
        $newDeadline = ($deadline === null && $remaining === null) ? computeNextTurnDeadline($gameReady, (int) $mechanics['turncounter'], $now) : null;
        $write = $newDeadline !== null;
        if ($deadline === null && $remaining === null && $newDeadline === null) {
            game_error_log('turnTimer_page', 'No deadline to start the timer with', ['turncounter' => $mechanics['turncounter']], 'warning');
        }
    } elseif ($action === 'pause') {
        $write = $deadline !== null;
        $newRemaining = $deadline === null ? null : max(0, $deadline - $now);
    } elseif ($action === 'resume') {
        $write = $remaining !== null;
        if ($write && getConfig($gameReady, 'turnTimerMode') === 'dates') {
            $newDeadline = computeNextTurnDeadline($gameReady, (int) $mechanics['turncounter'], $now);
        } elseif ($write) {
            $newDeadline = $now + $remaining;
        }
    }

    if ($write) {
        $prefix = $_SESSION['GAME_PREFIX'];
        try {
            $stmt = $gameReady->prepare("UPDATE {$prefix}mechanics SET turn_deadline = :deadline, turn_timer_remaining = :remaining WHERE id = :id");
            $stmt->bindValue(':deadline', $newDeadline === null ? null : gmdate('Y-m-d H:i:s', $newDeadline), $newDeadline === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':remaining', $newRemaining, $newRemaining === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id', (int) $mechanics['id'], PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            game_error_log('turnTimer_page', 'UPDATE mechanics failed : ' . $e->getMessage(), ['action' => $action], 'error');
        }
    }
}

$folder = $_SESSION['FOLDER'];
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$sameHost = $referer !== '' && parse_url($referer, PHP_URL_HOST) === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
header('Location: ' . ($sameHost ? $referer : "/{$folder}/base/accueil.php"));
exit();
