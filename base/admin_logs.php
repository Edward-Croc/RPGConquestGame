<?php
require_once '../base/basePHP.php';

// Admin-only page: require privileged session
if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

$pageName = 'admin_logs';

// Boutons pour écrire des lignes test aux 3 niveaux
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_error'])) {
        game_error_log('test', 'clicked at ' . date('c'), ['user_id' => $_SESSION['user_id'] ?? null], 'error');
    }
    if (isset($_POST['test_warning'])) {
        game_error_log('test', 'clicked at ' . date('c'), ['user_id' => $_SESSION['user_id'] ?? null], 'warning');
    }
    if (isset($_POST['test_debug'])) {
        game_error_log('test', 'clicked at ' . date('c'), ['user_id' => $_SESSION['user_id'] ?? null], 'debug');
    }
    header('Location: admin_logs.php' . (empty($_GET) ? '' : '?' . http_build_query($_GET)));
    exit();
}

$logPath = __DIR__ . '/../var/logs/game_errors.log';

$lines = [];
if (is_readable($logPath)) {
    $raw = @file($logPath, FILE_IGNORE_NEW_LINES);
    if ($raw !== false) {
        $lines = array_slice($raw, -500);
        $lines = array_reverse($lines);
    }
}

$filterPrefix = trim($_GET['prefix'] ?? '');
if ($filterPrefix !== '') {
    $needle = "[{$filterPrefix}]";
    $lines = array_values(array_filter($lines, fn($l) => str_contains($l, $needle)));
}

$filterLevel = strtoupper(trim($_GET['level'] ?? ''));
if (in_array($filterLevel, ['ERROR', 'WARNING', 'DEBUG'], true)) {
    $needle = "[{$filterLevel}]";
    $lines = array_values(array_filter($lines, fn($l) => str_contains($l, $needle)));
} else {
    $filterLevel = '';
}

$LEVEL_COLORS = [
    'ERROR'   => '#c0392b',
    'WARNING' => '#e67e22',
    'DEBUG'   => '#7f8c8d',
];

function classify_log_line(string $line, array $colors): string {
    foreach ($colors as $lvl => $color) {
        if (str_contains($line, "[{$lvl}]")) {
            return $color;
        }
    }
    return '#333';
}

$currentPrefix = $_SESSION['GAME_PREFIX'] ?? '';
$fileExists = file_exists($logPath);
$fileReadable = is_readable($logPath);
$fileSize = $fileExists ? filesize($logPath) : 0;
$iniErrorLog = ini_get('error_log');

require_once '../base/baseHTML.php';
?>
<div class="content">
    <h1>Game Errors Log </h1>

    <div class="box">
        <p><strong>Log path :</strong> <code><?= htmlspecialchars($logPath) ?></code></p>
        <p><strong>ini_get('error_log') :</strong> <code><?= htmlspecialchars($iniErrorLog ?: '(empty)') ?></code>
            <?php if ($iniErrorLog !== $logPath): ?>
                <span style="color:orange">&nbsp;— &ne; path attendu, <code>ini_set</code> peut avoir echoue</span>
            <?php else: ?>
                <span style="color:green">&nbsp;— OK</span>
            <?php endif; ?>
        </p>
        <p><strong>Status :</strong>
            <?php if (!$fileExists): ?>
                <span style="color:orange">Fichier inexistant. Cliquer le bouton ci-dessous pour ecrire la premiere ligne.</span>
            <?php elseif (!$fileReadable): ?>
                <span style="color:red">Non lisible (permissions).</span>
            <?php else: ?>
                <span style="color:green"><?= number_format($fileSize) ?> octets</span>
            <?php endif; ?>
        </p>
    </div>

    <form method="get" style="margin-bottom:1em;">
        <label>Filter prefix :
            <input type="text" name="prefix" value="<?= htmlspecialchars($filterPrefix) ?>"
                placeholder="ex. <?= htmlspecialchars($currentPrefix) ?>" size="30">
        </label>
        &nbsp;&nbsp;
        <label>Filter level :
            <select name="level">
                <option value="">All</option>
                <?php foreach (array_keys($LEVEL_COLORS) as $lvl): ?>
                    <option value="<?= $lvl ?>" <?= $filterLevel === $lvl ? 'selected' : '' ?> style="color:<?= $LEVEL_COLORS[$lvl] ?>">
                        <?= $lvl ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Filtrer</button>
        <a href="admin_logs.php">Reset</a>
    </form>

    <form method="post" style="margin-bottom:1em;">
        <button type="submit" name="test_error" class="button" style="background:<?= $LEVEL_COLORS['ERROR'] ?>;color:white;">
            Ecrire ERROR test
        </button>
        <button type="submit" name="test_warning" class="button" style="background:<?= $LEVEL_COLORS['WARNING'] ?>;color:white;">
            Ecrire WARNING test
        </button>
        <button type="submit" name="test_debug" class="button" style="background:<?= $LEVEL_COLORS['DEBUG'] ?>;color:white;">
            Ecrire DEBUG test
        </button>
    </form>

    <h2><?= count($lines) ?> derniere(s) ligne(s) <small>(plus recente d'abord)</small></h2>
    <pre style="background:#f5f5f5;padding:1em;overflow:auto;max-height:600px;font-size:12px;line-height:1.4;">
<?php foreach ($lines as $line): ?>
<span style="color:<?= classify_log_line($line, $LEVEL_COLORS) ?>;"><?= htmlspecialchars($line) ?></span>

<?php endforeach; ?>
<?php if (empty($lines)): ?>
<em>(rien a afficher)</em>
<?php endif; ?>
    </pre>
</div>
