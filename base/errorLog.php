<?php
// Route les erreurs vers var/logs/game_errors.log. Le dossier doit exister
// (cree par l'ops au deploiement). Acces public bloque par var/.htaccess.

$gameLogPath = __DIR__ . '/../var/logs/game_errors.log';
@ini_set('error_log', $gameLogPath);


/**
 * Retourne les N dernieres lignes du log, plus recentes en premier.
 * Filtres optionnels par prefix et par level (case-insensitive pour level).
 *
 * @param int         $lines
 * @param string|null $prefixFilter   ex. $_SESSION['GAME_PREFIX']
 * @param string|null $levelFilter    'error' | 'warning' | 'debug'
 * @return array
 */
function game_error_log_tail(int $lines = 2, ?string $prefixFilter = null, ?string $levelFilter = null): array {
    $logPath = __DIR__ . '/../var/logs/game_errors.log';
    if (!is_readable($logPath)) return [];
    $raw = @file($logPath, FILE_IGNORE_NEW_LINES);
    if ($raw === false) return [];

    if ($prefixFilter !== null && $prefixFilter !== '') {
        $needle = "[{$prefixFilter}]";
        $raw = array_filter($raw, fn($l) => str_contains($l, $needle));
    }
    if ($levelFilter !== null && $levelFilter !== '') {
        $needle = "[" . strtoupper($levelFilter) . "]";
        $raw = array_filter($raw, fn($l) => str_contains($l, $needle));
    }

    return array_reverse(array_slice(array_values($raw), -$lines));
}

/**
 * Ecrit une ligne d'erreur formatee dans le fichier de log.
 *
 * Format : [prefix] [LEVEL] function: message | ctx={json}
 *
 * ERROR + WARNING : toujours ecrits au fichier.
 * DEBUG : ecrit uniquement si $_SESSION['DEBUG']=true (unlock global admin)
 *         OU section listee dans $GLOBALS['DEBUG_LOG_SECTIONS'] (opt-in
 *         par fichier via une ligne commentee en tete).
 * Le passthrough echo sur la page reste conditionne a $_SESSION['DEBUG'].
 *
 * @param string $function  short id (typiquement __FUNCTION__)
 * @param string $message   free text
 * @param array  $context   optional key/value bag serialise en JSON
 * @param string $level     'error' (default) | 'warning' | 'debug'
 */
function game_error_log(string $function, string $message, array $context = [], string $level = 'error'): void {
    $prefix = $_SESSION['GAME_PREFIX'] ?? 'no-prefix';
    $lvl = strtoupper($level);
    if (!in_array($lvl, ['ERROR', 'WARNING', 'DEBUG'], true)) {
        $lvl = 'ERROR';
    }
    // DEBUG write gate : ecriture skipe si global DEBUG off ET section non opt-in
    // via $GLOBALS['DEBUG_LOG_SECTIONS']. ERROR + WARNING ecrivent toujours.
    if ($lvl === 'DEBUG'
        && empty($_SESSION['DEBUG'])
        && !in_array($function, $GLOBALS['DEBUG_LOG_SECTIONS'] ?? [], true)
    ) {
        return;
    }
    // Anti log-injection : neutralise les \r\n dans message + context strings
    $message = strtr($message, ["\r" => ' ', "\n" => ' ']);
    foreach ($context as $k => $v) {
        if (is_string($v)) $context[$k] = strtr($v, ["\r" => ' ', "\n" => ' ']);
    }
    $ctx = $context ? ' | ctx=' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    error_log("[{$prefix}] [{$lvl}] {$function}: {$message}{$ctx}");

    if (!empty($_SESSION['DEBUG'])) {
        echo htmlspecialchars("[{$lvl}] {$function}: {$message}{$ctx}", ENT_QUOTES) . "<br />";
    }
}
