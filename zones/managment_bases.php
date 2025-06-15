<?php
require_once '../base/basePHP.php'; // Set up $pdo and session
$pageName = 'admin_location_attacks';

// Fetch attack logs with JOINs to get names
$sql = "
    SELECT 
        bal.id,
        bal.turn,
        bal.success,
        bal.target_result_text,
        bal.attacker_result_text,
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
<div class='managment'>
    <h1>ZONES — Contrôle & Revendication</h1>

    <?php
    $zoneSql = "
        SELECT 
            z.id,
            z.name,
            z.description,
            claimer.id AS claimer_id,
            CONCAT(claimer.firstname, ' ', claimer.lastname) AS claimer_name,
            holder.id AS holder_id,
            CONCAT(holder.firstname, ' ', holder.lastname) AS holder_name
        FROM zones z
        LEFT JOIN controllers claimer ON z.claimer_controller_id = claimer.id
        LEFT JOIN controllers holder ON z.holder_controller_id = holder.id
        ORDER BY z.id ASC
    ";
    $zoneStmt = $gameReady->query($zoneSql);
    $zones = $zoneStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Nom de la Zone</th>
            <!-- <th>Description</th>-->
            <th>Sous la banère de</th>
            <th>Défendue par</th>
        </tr>
        <?php foreach ($zones as $zone): ?>
            <tr>
                <td><?= htmlspecialchars($zone['id']) ?></td>
                <td><?= htmlspecialchars($zone['name']) ?></td>
                <!--<td><?= htmlspecialchars($zone['description']) ?></td>-->
                <td><?= $zone['claimer_name'] ?? '<em>Aucun</em>' ?></td>
                <td><?= $zone['holder_name'] ?? '<em>Aucun</em>' ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h1>Historique des attaques de bases</h1>

    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Base</th>
            <th>Attaquant</th>
            <th>Tour</th>
            <th>Succès</th>
            <th>target_result_text</th>
            <th>attacker_result_text</th>
        </tr>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= htmlspecialchars($log['id']) ?></td>
            <td><?= htmlspecialchars($log['base_name']) ?></td>
            <td><?= htmlspecialchars($log['attacker_name'] ?? 'Inconnu') ?></td>
            <td><?= htmlspecialchars($log['turn']) ?></td>
            <td><?= $log['success'] ? '✔️ Réussie' : '❌ Échec' ?></td>
            <td><?= nl2br(htmlspecialchars($log['target_result_text'])) ?></td>
            <td><?= nl2br(htmlspecialchars($log['attacker_result_text'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
