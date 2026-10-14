<?php

/**
 * CSS of the visual theme a scenario names through its uiTheme configuration key.
 *
 * Only a file of base/themes/ can be named : the value is matched against the
 * files on disk, never put into a path.
 *
 * @param string|null $themeName : value of the uiTheme configuration key
 *
 * @return string : the theme's CSS, '' when no theme or an unknown one is named
 */
function themeStyleSheet(string|null $themeName): string
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with themeName : ' . (string) $themeName, [], 'debug');

    if (empty($themeName)) {
        return '';
    }
    foreach (glob(__DIR__ . '/themes/*.css') ?: [] as $themeFile) {
        if (basename($themeFile, '.css') === $themeName) {
            return (string) file_get_contents($themeFile);
        }
    }
    game_error_log(__FUNCTION__, 'uiTheme names no file of base/themes/', ['themeName' => $themeName], 'warning');
    return '';
}
