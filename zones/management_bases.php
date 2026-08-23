<?php
require_once '../base/basePHP.php'; // Set up $pdo and session

// Admin-only page: require privileged session
if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

$pageName = 'admin_location_attacks';

$prefix = $_SESSION['GAME_PREFIX'];

$validSorts = ['date', 'location', 'attacker'];
$sort = in_array($_GET['sort'] ?? '', $validSorts, true) ? $_GET['sort'] : 'date';

$logs = getLocationAttackLogs($gameReady, [], 'created_at', 'desc');

// Sorted in PHP : ORDER BY on a name follows the DB collation, which differs per backend.
if ($sort !== 'date') {
    usort($logs, function ($a, $b) use ($sort) {
        if ($sort === 'location') {
            $cmp = strcasecmp($a['location_name'] ?? '', $b['location_name'] ?? '');
        } else {
            // Compared on the displayed fallback so NULL names sort where they read
            $cmp = strcasecmp($a['attacker_name'] ?? 'Inconnu', $b['attacker_name'] ?? 'Inconnu');
        }
        return $cmp ?: ((int) $b['id'] <=> (int) $a['id']);
    });
}

$locationAttackMode = getConfig($gameReady, 'locationAttackMode');
$pending = [];
if (in_array($locationAttackMode, ['endTurn'], true)) {
    $pendingSql = "
        SELECT cla.id, cla.location_name, cla.queued_turn, cla.defence_val_snapshot,
               CONCAT(c.firstname, ' ', c.lastname) AS attacker_name,
               owner.lastname AS owner_lastname
        FROM {$prefix}controller_location_attacks cla
        LEFT JOIN {$prefix}controllers c ON cla.attacker_controller_id = c.id
        LEFT JOIN {$prefix}locations l ON cla.location_id = l.id
        LEFT JOIN {$prefix}controllers owner ON l.controller_id = owner.id
        WHERE cla.success IS NULL
        ORDER BY cla.queued_turn DESC, cla.id DESC
    ";
    $pending = $gameReady->query($pendingSql)->fetchAll(PDO::FETCH_ASSOC);
}

require_once '../base/baseHTML.php';
?>
<div class='management'>
    <?php if (in_array($locationAttackMode, ['endTurn'], true)): ?>
    <h1>Attaques de bases planifiées</h1>
    <?php if (empty($pending)): ?>
    <p>Aucune attaque planifiée.</p>
    <?php else: ?>
    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Base</th>
            <th>Attaquant</th>
            <th>Tour planifié</th>
            <th>Défense estimée</th>
        </tr>
        <?php foreach ($pending as $row): ?>
        <tr>
            <td><?= (int) $row['id'] ?></td>
            <td><?= htmlspecialchars($row['location_name'] . ' - ' . ($row['owner_lastname'] ?? ''), ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($row['attacker_name'] ?? 'Inconnu', ENT_QUOTES) ?></td>
            <td><?= (int) $row['queued_turn'] ?></td>
            <td><?= (int) $row['defence_val_snapshot'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php endif; ?>

    <h1>Historique des attaques de bases</h1>

    <?php
    $sortLabels = [
        'date'     => 'Date (plus récent d’abord)',
        'location' => 'Base',
        'attacker' => 'Contrôleur attaquant',
    ];
$sortOptionsHtml = '';
foreach ($sortLabels as $value => $label) {
    $sortOptionsHtml .= sprintf(
        '<option value="%s"%s>%s</option>',
        htmlspecialchars($value, ENT_QUOTES),
        ($sort === $value) ? ' selected' : '',
        htmlspecialchars($label, ENT_QUOTES)
    );
}
?>
    <form method="GET" class="box mb-5" data-bases-sort="1">
        <h3 class="title is-4">Tri :</h3>
        <div class="field is-grouped is-grouped-multiline">
            <div class="control">
                <div class="select"><select name="sort"><?= $sortOptionsHtml ?></select></div>
            </div>
            <div class="control">
                <input type="submit" value="Trier" class="button is-link">
            </div>
            <div class="control">
                <a href="management_bases.php" class="button" data-bases-sort-reset="1">Reset</a>
            </div>
        </div>
    </form>

    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th data-sort-key="location">Base</th>
            <th data-sort-key="attacker">Attaquant</th>
            <th>Tour</th>
            <th>Succès</th>
            <th>Valeurs</th>
            <th>target_result_text</th>
            <th>attacker_result_text</th>
        </tr>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= (int) $log['id'] ?></td>
            <td><?= htmlspecialchars($log['location_name'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($log['attacker_name'] ?? 'Inconnu', ENT_QUOTES) ?></td>
            <td><?= (int) $log['turn'] ?></td>
            <td><?= $log['success'] ? '✔️ Réussie' : '❌ Échec' ?></td>
            <td><?= sprintf('%d / %d', (int) $log['attack_val'], (int) $log['defence_val']) ?></td>
            <!-- Raw by design : GM-editable config templates may carry markup -->
            <td><?= nl2br($log['target_result_text'] ?? '') ?></td>
            <td><?= nl2br($log['attacker_result_text'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
