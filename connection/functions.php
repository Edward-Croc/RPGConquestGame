<?php

/**
 * Hash a password for storage.
 *
 * @param string $plain : the password as typed or as seeded
 *
 * @return string : the hash to store in players.passwd
 */
function hashPlayerPassword(string $plain): string
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START', [], 'debug');

    return password_hash($plain, PASSWORD_DEFAULT);
}

/**
 * Check a typed password against a stored hash.
 *
 * @param string $plain : the password as typed
 * @param string|null $stored : the players.passwd value
 *
 * @return bool : true when they match
 */
function verifyPlayerPassword(string $plain, string|null $stored): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START', [], 'debug');

    if ($stored === null || $stored === '') {
        return false;
    }

    return password_verify($plain, $stored);
}
