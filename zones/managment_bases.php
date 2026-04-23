<?php
require_once '../base/basePHP.php'; // Set up $pdo and session
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

require_once '../base/baseHTML.php';
?>
<div class='managment'>
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
