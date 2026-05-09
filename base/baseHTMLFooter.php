<?php
// Include-only page — block direct HTTP access.
// Closes the document opened by base/baseHTML.php and renders the
// version footer.
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit();
}

$appVersion = defined('APP_VERSION') ? APP_VERSION : '?';
?>
    <footer class="app-footer">
        <span class="app-version">v<?php echo htmlspecialchars($appVersion); ?></span>
    </footer>
</body>
</html>
