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

/**
 * Web path of a player guide capture, taken from the set the guideCaptures key names.
 *
 * Each capture is looked up in that set first, then in the testconfig set, so a
 * scenario only provides the captures that differ. The names are matched against
 * the files on disk, never put into a path.
 *
 * @param string|null $setName : value of the guideCaptures configuration key
 * @param string $captureName : capture name, without its .jpg extension
 *
 * @return string|null : web path of the capture, null when no set holds it
 */
function guideCapturePath(string|null $setName, string $captureName): string|null
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with captureName : ' . $captureName, ['setName' => $setName], 'debug');

    $captureFiles = glob(__DIR__ . '/guide/*/*.jpg') ?: [];
    foreach (array_unique(array_filter([(string) $setName, 'testconfig'])) as $set) {
        foreach ($captureFiles as $captureFile) {
            if (basename(dirname($captureFile)) === $set && basename($captureFile, '.jpg') === $captureName) {
                return sprintf('/%s/base/guide/%s/%s.jpg', $_SESSION['FOLDER'], $set, $captureName);
            }
        }
    }
    game_error_log(__FUNCTION__, 'no guide capture found', ['setName' => $setName, 'captureName' => $captureName], 'warning');
    return null;
}

/**
 * HTML figure of a player guide capture, with its caption.
 *
 * @param string|null $setName : value of the guideCaptures configuration key
 * @param string $captureName : capture name, without its .jpg extension
 * @param string $caption : what the capture shows
 *
 * @return string : the figure, '' when no set holds the capture
 */
function guideCaptureFigure(string|null $setName, string $captureName, string $caption): string
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with captureName : ' . $captureName, [], 'debug');

    $capturePath = guideCapturePath($setName, $captureName);
    if ($capturePath === null) {
        return '';
    }
    return sprintf(
        '<figure class="guide-capture"><img src="%1$s" alt="%2$s" loading="lazy"><figcaption>%2$s</figcaption></figure>',
        htmlspecialchars($capturePath),
        htmlspecialchars($caption)
    );
}
