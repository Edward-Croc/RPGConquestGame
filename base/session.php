<?php

// Include-only page — block direct HTTP access.
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit();
}

/**
 * Start the session, isolated to this installation.
 * Several games served from the same domain, session cookies are bound to the domain, not to the path.
 *
 * @return void
 */
function startGameSession(): void
{
    $installation = hash('sha256', (string) realpath(__DIR__ . '/..'));

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('RPGSESSID' . substr($installation, 0, 12));
        session_start();
    }

    // Checked on every call, not only when we opened the session ourselves : with
    // session.auto_start the session exists before any of this code runs, and the
    // cookie name can no longer be set — this second guard is all that is left.
    if (($_SESSION['INSTALLATION'] ?? '') !== $installation) {
        $_SESSION = array();
        session_regenerate_id(true);
        $_SESSION['INSTALLATION'] = $installation;
    }
}
