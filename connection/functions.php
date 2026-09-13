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

/**
 * List the scenarios that seed player accounts, as named on disk.
 *
 * @return array : scenario names usable in a setup file name
 */
function listScenariosWithPlayers(): array
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START', [], 'debug');

    $scenarios = array();
    foreach (glob(__DIR__ . '/../var/csv/setup*_players.csv') ?: array() as $csvFile) {
        if (preg_match('/^setup(.+)_players\.csv$/', basename($csvFile), $matches)) {
            $scenarios[] = $matches[1];
        }
    }
    sort($scenarios);

    return $scenarios;
}

/**
 * Read the password a scenario seeds for one account.
 *
 * The scenario CSV is the only place the seeded password still exists in
 * clear : the import hashes it on the way in. The scenario name is matched
 * against the files on disk, so no caller can reach outside var/csv.
 *
 * @param string $scenario : scenario name, as listed by listScenariosWithPlayers
 * @param string $username : the account to look up
 *
 * @return string|null : the seeded password, NULL when the scenario or the account is unknown
 */
function scenarioSeededPassword(string $scenario, string $username): string|null
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with scenario : ' . $scenario, ['username' => $username], 'debug');

    if (!in_array($scenario, listScenariosWithPlayers(), true)) {
        game_error_log(__FUNCTION__, 'Unknown scenario : ' . $scenario, ['username' => $username], 'warning');
        return null;
    }

    $csvFile = sprintf('%s/../var/csv/setup%s_players.csv', __DIR__, $scenario);
    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        game_error_log(__FUNCTION__, 'fopen failed on CSV : ' . $csvFile, ['username' => $username], 'error');
        return null;
    }

    $header = fgetcsv($handle);
    $usernameIndex = is_array($header) ? array_search('username', $header, true) : false;
    $passwdIndex = is_array($header) ? array_search('passwd', $header, true) : false;
    $seeded = null;
    if ($usernameIndex !== false && $passwdIndex !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            if (($row[$usernameIndex] ?? null) === $username) {
                $seeded = (string) ($row[$passwdIndex] ?? '');
                break;
            }
        }
    }
    fclose($handle);

    return ($seeded === null || $seeded === '') ? null : $seeded;
}
