<?php

$pageName = 'admin_backups';

require_once '../base/basePHP.php';

// Admin-only page — mirror the guard used by other admin pages
if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

// $GLOBALS['DEBUG_LOG_SECTIONS'][] = 'admin_backups_page';  // uncomment to log DEBUG events from this page

$backupDir = __DIR__ . '/../var/backups';
$action_msg = '';

/**
 * Validate that a requested filename resolves to a real .sql file
 * inside $backupDir. Returns the absolute path on success, null on
 * rejection (path traversal, missing file, wrong extension).
 */
function _resolve_backup_path(string $backupDir, string $requested): string|null
{
    $safe = basename($requested);
    if ($safe === '' || !str_ends_with($safe, '.sql')) {
        return null;
    }
    $full = $backupDir . '/' . $safe;
    if (!is_file($full)) {
        return null;
    }
    return $full;
}

// GET download branch — stream file then exit before any HTML render
if (isset($_GET['download'])) {
    $full = _resolve_backup_path($backupDir, $_GET['download']);
    if ($full === null) {
        game_error_log('admin_backups_page', 'Invalid download request', ['requested' => $_GET['download']], 'warning');
        http_response_code(404);
        exit('Backup file not found.');
    }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($full) . '"');
    header('Content-Length: ' . filesize($full));
    readfile($full);
    exit;
}

// POST delete_backup — single file delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    $full = _resolve_backup_path($backupDir, $_POST['delete_backup']);
    if ($full !== null && unlink($full)) {
        $action_msg = "<p style='color: green;'>Deleted " . htmlspecialchars(basename($full)) . "</p>";
    } else {
        game_error_log('admin_backups_page', 'Delete failed', ['requested' => $_POST['delete_backup']], 'warning');
        $action_msg = "<p style='color: red;'>Delete failed for the requested file.</p>";
    }
}

// POST purge_all — remove every .sql in the dir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_all'])) {
    $removed = 0;
    if (is_dir($backupDir)) {
        foreach (glob($backupDir . '/*.sql') as $file) {
            if (unlink($file)) {
                $removed++;
            }
        }
    }
    $action_msg = "<p style='color: green;'>Purged $removed backup file(s).</p>";
}

// Collect backup files for listing (newest first)
$backups = [];
if (is_dir($backupDir)) {
    foreach (glob($backupDir . '/*.sql') as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'mtime' => filemtime($file),
        ];
    }
    usort($backups, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
}

require_once '../base/baseHTML.php';
?>

<div class="content">
    <h1>DB backups : </h1>
    <p>Location : <code><?= htmlspecialchars(realpath($backupDir) ?: $backupDir) ?></code></p>
    <?= $action_msg ?>

    <?php if (empty($backups)): ?>
        <p><em>No backup files found.</em></p>
    <?php else: ?>
        <table class="table is-striped">
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Modified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= number_format($b['size'] / 1024, 1) ?> KB</td>
                        <td><?= date('Y-m-d H:i:s', $b['mtime']) ?></td>
                        <td>
                            <a href="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/base/admin_backups.php?download=<?= urlencode($b['name']) ?>" class="button is-small">Download</a>
                            <form action="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/base/admin_backups.php" method="post" style="display:inline;">
                                <input type="hidden" name="delete_backup" value="<?= htmlspecialchars($b['name']) ?>" />
                                <button type="submit" class="button is-small is-danger" onclick="return confirm('Delete <?= htmlspecialchars(addslashes($b['name'])) ?> ?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form action="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/base/admin_backups.php" method="post" style="margin-top: 1em;">
            <input type="hidden" name="purge_all" value="1" />
            <button type="submit" class="button is-danger" onclick="return confirm('Purge ALL <?= count($backups) ?> backup files ? This cannot be undone.');">Purge all backups</button>
        </form>
    <?php endif; ?>

    <p style="margin-top: 1em;"><a href="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/base/admin.php">&larr; Back to admin</a></p>
</div>
</body>
</html>
