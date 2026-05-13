<?php
require_once '../base/basePHP.php'; // Set up $pdo and session

// Admin-only page: require privileged session
if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

$pageName = 'admin_location_attacks';

$prefix = $_SESSION['GAME_PREFIX'];

// Fetch attack logs with JOINs to get names
$sql = "
    SELECT 
        bal.id,
        bal.location_name,
        bal.turn,
        bal.success,
        bal.attack_val,
        bal.defence_val,
        bal.target_result_text,
        bal.attacker_result_text,
        bal.created_at,
        CONCAT(c.firstname, ' ', c.lastname) AS attacker_name
    FROM {$prefix}location_attack_logs bal
    LEFT JOIN {$prefix}controllers c ON bal.attacker_id = c.id
    ORDER BY bal.created_at DESC
";
$stmt = $gameReady->query($sql);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$locationAttackMode = getConfig($gameReady, 'locationAttackMode');
$pending = [];
if ($locationAttackMode === 'endTurn') {
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
    <?php if ($locationAttackMode === 'endTurn'): ?>
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
            <td><?= $row['id'] ?></td>
            <td><?= $row['location_name'] . ' - ' . ($row['owner_lastname'] ?? '') ?></td>
            <td><?= $row['attacker_name'] ?? 'Inconnu' ?></td>
            <td><?= $row['queued_turn'] ?></td>
            <td><?= $row['defence_val_snapshot'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php endif; ?>

    <h1>Historique des attaques de bases</h1>

    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Base</th>
            <th>Attaquant</th>
            <th>Tour</th>
            <th>Succès</th>
            <th>Valeurs</th>
            <th>target_result_text</th>
            <th>attacker_result_text</th>
        </tr>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= $log['id'] ?></td>
            <td><?= $log['location_name'] ?></td>
            <td><?= $log['attacker_name'] ?? 'Inconnu' ?></td>
            <td><?= $log['turn'] ?></td>
            <td><?= $log['success'] ? '✔️ Réussie' : '❌ Échec' ?></td>
            <td><?= sprintf('%s / %s',  ($log['attack_val']), $log['defence_val'])?></td>
            <td><?= nl2br($log['target_result_text']) ?></td>
            <td><?= nl2br($log['attacker_result_text']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
