<?php

// Include-only page — block direct HTTP access.
// Requires a run of base/basePHP.php and set $gameTitle / $mechanics / $_SESSION.
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit();
}

// Check if the user is logged in
if (
    (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)
    && (empty($noConnection) || $noConnection == false)
) {
    // Redirect the user to the login page if not logged in
    header(sprintf('Location: /%s/connection/loginForm.php', $_SESSION['FOLDER']));
    exit();
}
?>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPGConquestGame</title>
    <style>
        <?php include_once '../base/style.css'; ?>
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <style>
        <?php echo themeStyleSheet(getConfig($gameReady, 'uiTheme')); ?>
    </style>
</head>
<body class='content'>
    <div class="header"><?php
        echo sprintf(
            '%s : %s %s <br>',
            $gameTitle,
            ucfirst(getConfig($gameReady, 'timeValue')),
            $mechanics['turncounter']
        );
if (isset($_SESSION['controller'])) {
    echo sprintf("  %s %s (%s) des %s ", $_SESSION['controller']['firstname'], $_SESSION['controller']['lastname'], $_SESSION['controller']['id'], $_SESSION['controller']['faction_name']);
}
?></div>
    <!-- Sidebar MENU -->
    <div id="sidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="toggleSidebar()">&times;</a>
        <?php
    $folder = $_SESSION['FOLDER'];
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$isPrivileged = $_SESSION['is_privileged'] ?? false;

if (!$isLoggedIn && !empty($noConnection)) {
    $sysClass = ($pageName === 'systemPresentation') ? 'sidebar-btn select' : 'sidebar-btn';
    echo "<a href='/$folder/base/systemPresentation.php' class='$sysClass'>Le Système</a>";
    echo "<a href='/$folder/connection/loginForm.php' class='sidebar-btn'>Login</a>";
} else {
    // Define main links
    $links = [
        'accueil' => ['label' => 'Accueil', 'path' => 'base/accueil.php'],
        'controllers_action' => ['label' => 'Ma Faction', 'path' => 'controllers/action.php'],
        'view_workers' => ['label' => 'Agents de la faction', 'path' => 'workers/viewAll.php'],
    ];
    if (getConfig($gameReady, 'ressource_management') == 'TRUE') {
        $links['ressources_view'] = ['label' => 'Ressources', 'path' => 'ressources/view.php'];
    }
    $links['zones_action'] = ['label' => 'Les Zones', 'path' => 'zones/action.php'];
    $links['systemPresentation'] = ['label' => 'Le Système', 'path' => 'base/systemPresentation.php', 'class' => 'sidebar-btn'];

    foreach ($links as $key => $info) {
        $linkClass = trim(($info['class'] ?? '') . ($pageName === $key ? ' select' : ''));
        $classAttr = $linkClass === '' ? '' : " class='$linkClass'";
        echo "<a href='/$folder/{$info['path']}'$classAttr>{$info['label']}</a>";
    }

    // One token per session, shared by the timer commands and the end-of-turn form, burned by endTurn.php.
    if ($isPrivileged && $pageName !== 'End Turn' && empty($_SESSION['end_turn_token'])) {
        $_SESSION['end_turn_token'] = bin2hex(random_bytes(16));
    }

    // Turn timer counter, only when a mode is set and the mechanics columns exist.
    $timerMode = (string) getConfig($gameReady, 'turnTimerMode');
    $timerStateRead = in_array($timerMode, ['durations', 'dates'], true) ? getTurnTimerState($mechanics ?? []) : null;
    if ($timerStateRead !== null) {
        $timerNow = time();
        $timerDeadline = $timerStateRead['deadline'];
        $timerRemaining = $timerStateRead['remaining'];
        $timerImminentText = formatConfigText('turnTimerTextImminent', getConfig($gameReady, 'turnTimerTextImminent') ?? 'Fin de tour imminente.');
        // The page of the MJ fires the end of turn itself at expiry, only for a fresh end of turn and never from the end-of-turn page.
        $timerAutoFire = $isPrivileged && $pageName !== 'End Turn'
            && getConfig($gameReady, 'turnTimerOnExpiry') === 'endTurn'
            && empty($mechanics['end_step']);
        $timerPhase = 'stopped';
        if ($timerRemaining !== null) {
            $timerPhase = 'paused';
        } elseif ($timerDeadline !== null) {
            $timerPhase = 'running';
        }
        if ($timerPhase === 'paused') {
            $timerText = formatConfigText('turnTimerTextPaused', getConfig($gameReady, 'turnTimerTextPaused') ?? 'Jeu en pause, il reste %s.', formatTurnTimerDelay((int) $timerRemaining));
            echo "<div id='turnTimer' class='sidebar-timer'>" . htmlspecialchars($timerText, ENT_QUOTES) . '</div>';
        } elseif ($timerPhase === 'running' && $timerDeadline > $timerNow) {
            $timerUnit = strtolower((string) getConfig($gameReady, 'timeValue'));
            $timerTemplate = getConfig($gameReady, 'turnTimerTextRunning') ?? 'Fin de %s dans %s.';
            $timerText = formatConfigText('turnTimerTextRunning', $timerTemplate, $timerUnit, formatTurnTimerDelay($timerDeadline - $timerNow));
            $timerPattern = formatConfigText('turnTimerTextRunning', $timerTemplate, $timerUnit, '{delay}');
            echo "<div id='turnTimer' class='sidebar-timer' data-deadline='" . (int) $timerDeadline . "' data-now='" . (int) $timerNow . "'>" . htmlspecialchars($timerText, ENT_QUOTES) . '</div>';
            // Texts the countdown script swaps in, kept out of data-* attributes.
            echo '<script>var turnTimerTexts = ' . json_encode(['running' => $timerPattern, 'imminent' => $timerImminentText, 'autoFire' => $timerAutoFire], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . ';</script>';
        } elseif ($timerPhase === 'running') {
            $timerText = $timerImminentText;
            if ($isPrivileged) {
                $timerText .= ' Retard : ' . formatTurnTimerDelay($timerNow - $timerDeadline);
            }
            echo "<div id='turnTimer' class='sidebar-timer'>" . htmlspecialchars($timerText, ENT_QUOTES) . '</div>';
            if ($timerAutoFire) {
                echo '<script>var turnTimerExpiredAutoFire = true; var turnTimerExpiredDeadline = ' . (int) $timerDeadline . ';</script>';
            }
        }

        // Commands for the MJ, the end-of-turn page has no token minted.
        if ($isPrivileged && $pageName !== 'End Turn') {
            $timerCommands = [
                'stopped' => ['start' => 'Lancer'],
                'running' => ['pause' => 'Pause', 'stop' => 'Arrêter'],
                'paused' => ['resume' => 'Reprendre', 'stop' => 'Arrêter'],
            ][$timerPhase];
            foreach ($timerCommands as $timerAction => $timerLabel) {
                echo "<form class='sidebar-form' method='post' action='/$folder/mechanics/turnTimer.php'>";
                echo "<input type='hidden' name='turn_timer_action' value='$timerAction'>";
                echo "<input type='hidden' name='end_turn_token' value='" . htmlspecialchars($_SESSION['end_turn_token'], ENT_QUOTES) . "'>";
                echo "<button type='submit' class='sidebar-btn'>$timerLabel</button>";
                echo '</form>';
            }
        }
    }

    // Privileged user section
    if ($isPrivileged) {
        // The end-of-turn page carries no trigger of its own.
        if ($pageName !== 'End Turn') {
            if (!empty($mechanics['end_step'])) {
                $btnText = 'Resume End Turn';
            } else {
                $btnText = ($mechanics['gamestate'] ?? 0) == 0 ? 'Start Game' : 'End Turn';
            }
            echo "<form id='endTurnForm' class='sidebar-form' method='post' action='/$folder/mechanics/endTurn.php'>";
            echo "<input type='hidden' name='end_turn_token' value='" . htmlspecialchars($_SESSION['end_turn_token'], ENT_QUOTES) . "'>";
            echo "<input type='hidden' name='turn_seen' value='" . (int) $mechanics['turncounter'] . "'>";
            echo "<input type='hidden' name='end_step_seen' value='" . htmlspecialchars((string) ($mechanics['end_step'] ?? ''), ENT_QUOTES) . "'>";
            echo "<button type='submit' id='endTurnBtn' class='sidebar-btn'>$btnText</button>";
            echo '</form>';
        }

        $adminClass = ($pageName === 'admin') ? 'sidebar-btn select' : 'sidebar-btn';
        echo "<a href='/$folder/admin/admin.php' class='$adminClass'>Configuration</a>";
    }

    // Account and logout buttons
    $accountClass = ($pageName === 'account') ? 'sidebar-btn select' : 'sidebar-btn';
    echo "<a href='/$folder/connection/account.php' class='$accountClass'>Mon compte</a>";
    echo "<a href='/$folder/connection/logout.php' class='logout-btn'>Logout</a>";
}
?>
    </div>
    <!-- Sidebar Toggle Button -->
    <?php
echo '<span class="openbtn" onclick="toggleSidebar()"> ☰ </span>';

require_once '../base/baseScript.php';

// Register the footer emission for end-of-script — content rendered
// after this include lands inside <body>; the shutdown function then
// closes the document with the version footer. Skipped on redirect
// responses (where headers were already sent and we don't want HTML).
register_shutdown_function(function () {
    if (!headers_sent()) {
        $contentType = '';
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') === 0) {
                $contentType = $h;
                break;
            }
        }
        // Default Content-Type is text/html when nothing was set; if
        // anything non-HTML was set explicitly, skip the footer.
        if ($contentType && stripos($contentType, 'text/html') === false) {
            return;
        }
    }
    require_once __DIR__ . '/baseHTMLFooter.php';
});
?>
    <div id="endTurnModal" class="modal">
        <div class="modal-background"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">Confirm</p>
            </header>
            <section class="modal-card-body" id="endTurnModalBody">
                <p>Are you sure ?</p>
            </section>
            <footer class="modal-card-foot">
                <button id="endTurnModalYes" class="button is-danger">Yes</button>
                <button id="endTurnModalNo" class="button">Cancel</button>
            </footer>
        </div>
    </div>

    <script>
        function openEndTurnModal(event) {
            event.preventDefault();
            document.getElementById('endTurnModal').classList.add('is-active');
        }

        function closeEndTurnModal() {
            document.getElementById('endTurnModal').classList.remove('is-active');
        }

        function confirmEndTurn() {
            // The turn resolves for tens of seconds while the page streams : leaving
            // the buttons live let a second click fire a second end of turn.
            const yes = document.getElementById('endTurnModalYes');
            const no = document.getElementById('endTurnModalNo');
            if (yes.disabled) {
                return;
            }
            yes.disabled = true;
            no.disabled = true;
            yes.classList.add('is-loading');
            document.getElementById('endTurnModalBody').innerHTML =
                '<progress class="progress is-small is-primary" max="100"></progress>'
                + '<p>Résolution du tour en cours, merci de patienter…</p>';
            document.getElementById('endTurnForm').submit();
        }

        var endTurnAutoFired = false;
        var autoFireDeadline = '';

        function autoFireEndTurn() {
            if (endTurnAutoFired || !document.getElementById('endTurnForm')) return;
            endTurnAutoFired = true;
            // The deadline this tab rendered with, checked by endTurn.php against the live timer.
            if (autoFireDeadline) {
                const field = document.createElement('input');
                field.type = 'hidden';
                field.name = 'auto_deadline';
                field.value = autoFireDeadline;
                document.getElementById('endTurnForm').appendChild(field);
            }
            document.getElementById('endTurnModal').classList.add('is-active');
            confirmEndTurn();
        }

        function initEndTurnConfirmation() {
            const endTurnBtn = document.getElementById('endTurnBtn');
            if (!endTurnBtn) return; // Absent for non-privileged users and on the end-of-turn page

            endTurnBtn.addEventListener('click', openEndTurnModal);
            document.getElementById('endTurnModalNo').addEventListener('click', closeEndTurnModal);
            document.getElementById('endTurnModalYes').addEventListener('click', confirmEndTurn);
            document.querySelector('#endTurnModal .modal-background').addEventListener('click', function () {
                // Dismissing mid-resolution would expose the button again.
                if (!document.getElementById('endTurnModalYes').disabled) {
                    closeEndTurnModal();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', initEndTurnConfirmation);

        function initTurnTimerCountdown() {
            const timer = document.getElementById('turnTimer');
            if (!timer || !timer.dataset.deadline || typeof turnTimerTexts === 'undefined') return;

            const deadline = parseInt(timer.dataset.deadline, 10);
            autoFireDeadline = String(deadline);
            const serverNow = parseInt(timer.dataset.now, 10);
            const loadedAt = Date.now() / 1000;
            const pad = function (n) { return n < 10 ? '0' + n : '' + n; };
            const format = function (s) {
                return s >= 3600
                    ? Math.floor(s / 3600) + 'h' + pad(Math.floor((s % 3600) / 60))
                    : Math.floor(s / 60) + 'min ' + (s % 60) + 's';
            };
            const tick = function () {
                // Elapsed client time since load, so the client clock offset never matters.
                const left = Math.round(deadline - serverNow - (Date.now() / 1000 - loadedAt));
                if (left <= 0) {
                    timer.textContent = turnTimerTexts.imminent;
                    clearInterval(handle);
                    if (turnTimerTexts.autoFire) autoFireEndTurn();
                    return;
                }
                timer.textContent = turnTimerTexts.running.replace('{delay}', format(left));
            };
            const handle = setInterval(tick, 1000);
            tick();
        }

        document.addEventListener('DOMContentLoaded', initTurnTimerCountdown);

        // The deadline is already past at load: the countdown has nothing to count.
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof turnTimerExpiredAutoFire !== 'undefined' && turnTimerExpiredAutoFire) {
                autoFireDeadline = String(turnTimerExpiredDeadline);
                autoFireEndTurn();
            }
        });
    </script>