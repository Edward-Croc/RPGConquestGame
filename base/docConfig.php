<?php
$noConnection = true;
require_once '../base/basePHP.php';
$pageName = 'docConfig';

require_once '../base/baseHTML.php';
require_once '../lib/parsedown.php';

$prefix_display = !empty($_SESSION['GAME_PREFIX']) ? $_SESSION['GAME_PREFIX'] : '{prefix}';
$md = file_get_contents(__DIR__ . '/../docs/configuration.md');
$md = str_replace('{prefix}', $prefix_display, $md);

$parsedown = new Parsedown();
$html = $parsedown->text($md);

$html = preg_replace_callback(
    '/<(h[1-6])>(.*?)<\/\1>/u',
    function ($m) {
        $slug = trim(preg_replace('/[^a-z0-9]+/u', '-', strtolower(strip_tags($m[2]))), '-');
        return $slug ? "<{$m[1]} id=\"{$slug}\">{$m[2]}</{$m[1]}>" : "<{$m[1]}>{$m[2]}</{$m[1]}>";
    },
    $html
);

echo '<div class="docConfig section">' . $html . '</div>';
