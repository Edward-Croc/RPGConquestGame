<?php
require_once '../base/basePHP.php'; // Set up $pdo and session
$pageName = 'admin_location_attacks';

// Fetch attack logs with JOINs to get names
$sql = "
    SELECT 
        bal.id,
        bal.turn,
        bal.success,
        bal.notes,
        bal.created_at,
        CONCAT(c.firstname, ' ', c.lastname) AS attacker_name
    FROM location_attack_logs bal
    LEFT JOIN controllers c ON bal.attacker_id = c.id
    ORDER BY bal.created_at DESC
";
$stmt = $gameReady->query($sql);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../base/baseHTML.php';
?>

<h1>Historique des attaques de bases</h1>

<table border="1" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>Base</th>
        <th>Attaquant</th>
        <th>Tour</th>
        <th>Succès</th>
        <th>Notes</th>
    </tr>
    <?php foreach ($logs as $log): ?>
    <tr>
        <td><?= htmlspecialchars($log['id']) ?></td>
        <td><?= htmlspecialchars($log['base_name']) ?></td>
        <td><?= htmlspecialchars($log['attacker_name'] ?? 'Inconnu') ?></td>
        <td><?= htmlspecialchars($log['turn']) ?></td>
        <td><?= $log['success'] ? '✔️ Réussie' : '❌ Échec' ?></td>
        <td><?= nl2br(htmlspecialchars($log['notes'])) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
