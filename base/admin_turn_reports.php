<?php

$pageName = 'admin_turn_reports';

require_once '../base/basePHP.php';

// Admin-only page — mirror the guard used by other admin pages
if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

// $GLOBALS['DEBUG_LOG_SECTIONS'][] = 'admin_turn_reports_page';  // uncomment to log DEBUG events from this page

$reportDir = $GLOBALS['TURN_REPORT_DIR'];
$action_msg = '';

/**
 * Validate that a requested filename resolves to a real .html file
 * inside $reportDir. Returns the absolute path on success, null on
 * rejection (path traversal, missing file, wrong extension).
 *
 * @param string $reportDir : directory holding the archives
 * @param string $requested : untrusted file name from the request
 *
 * @return string|null : absolute path, NULL when the request is rejected
 */
function _resolve_turn_report_path(string $reportDir, string $requested): string|null
{
    $safe = basename($requested);
    if ($safe === '' || !str_ends_with($safe, '.html')) {
        return null;
    }
    $full = $reportDir . '/' . $safe;
    if (!is_file($full)) {
        return null;
    }
    return $full;
}

// GET view branch — stream the archived narrative then exit before any HTML render
if (isset($_GET['view'])) {
    $full = _resolve_turn_report_path($reportDir, $_GET['view']);
    if ($full === null) {
        game_error_log('admin_turn_reports_page', 'Invalid view request', ['requested' => $_GET['view']], 'warning');
        http_response_code(404);
        exit('Turn report not found.');
    }
    header('Content-Type: text/html; charset=UTF-8');
    readfile($full);
    exit;
}

// POST delete_report — single file delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_report'])) {
    $full = _resolve_turn_report_path($reportDir, $_POST['delete_report']);
    if ($full !== null && unlink($full)) {
        $action_msg = "<p style='color: green;'>Deleted " . htmlspecialchars(basename($full)) . "</p>";
    } else {
        game_error_log('admin_turn_reports_page', 'Delete failed', ['requested' => $_POST['delete_report']], 'warning');
        $action_msg = "<p style='color: red;'>Delete failed for the requested file.</p>";
    }
}

// POST purge_all — remove every .html in the dir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_all'])) {
    $removed = 0;
    if (is_dir($reportDir)) {
        foreach (glob($reportDir . '/*.html') as $file) {
            if (unlink($file)) {
                $removed++;
            }
        }
    }
    $action_msg = "<p style='color: green;'>Purged $removed turn report(s).</p>";
}

// Collect the archives for listing (newest first)
$reports = [];
if (is_dir($reportDir)) {
    foreach (glob($reportDir . '/*.html') as $file) {
        $name = basename($file);
        // Names are written as {prefix}turn_{NNNN}_{Ymd-His}.html by saveTurnReport.
        $turn = preg_match('/turn_(\d+)_/', $name, $m) ? (int) $m[1] : null;
        $reports[] = [
            'name' => $name,
            'turn' => $turn,
            'size' => filesize($file),
            'mtime' => filemtime($file),
        ];
    }
    usort($reports, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
}

require_once '../base/baseHTML.php';
?>

<div class="content">
    <h1>Turn reports : </h1>
    <p>Le récit de chaque fin de tour, archivé tel qu'il a été affiché.</p>
    <p>Location : <code><?= htmlspecialchars(realpath($reportDir) ?: $reportDir) ?></code></p>
    <?= $action_msg ?>

    <?php if (empty($reports)): ?>
        <p><em>No turn report found.</em></p>
    <?php else: ?>
        <form action="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/base/admin_turn_reports.php" method="post" style="margin-bottom: 1em;">
            <input type="hidden" name="purge_all" value="1" />
            <button type="submit" class="button is-danger" onclick="return confirm('Purge ALL <?= count($reports) ?> turn reports ? This cannot be undone.');">Purge all reports</button>
        </form>

        <table class="table is-striped">
            <thead>
                <tr>
                    <th>Turn</th>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Written</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td><?= $r['turn'] === null ? '—' : (int) $r['turn'] ?></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= number_format($r['size'] / 1024, 1) ?> KB</td>
                        <td><?= date('Y-m-d H:i:s', $r['mtime']) ?></td>
                        <td>
                            <a href="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/base/admin_turn_reports.php?view=<?= urlencode($r['name']) ?>" class="button is-small" target="_blank">View</a>
                            <form action="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/base/admin_turn_reports.php" method="post" style="display:inline;">
                                <input type="hidden" name="delete_report" value="<?= htmlspecialchars($r['name']) ?>" />
                                <button type="submit" class="button is-small is-danger" onclick="return confirm('Delete <?= htmlspecialchars(addslashes($r['name'])) ?> ?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p style="margin-top: 1em;"><a href="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/base/admin.php">&larr; Back to admin</a></p>
</div>
</body>
</html>
