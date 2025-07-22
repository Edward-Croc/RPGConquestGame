<?php
require_once '../base/basePHP.php'; // Set up $pdo and session
$pageName = 'admin_zones';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zone_id'])) {
    $zoneId = $_POST['zone_id'];
    $newClaimer = !empty($_POST['claimer_id']) ? $_POST['claimer_id'] : null;
    $newHolder = !empty($_POST['holder_id']) ? $_POST['holder_id'] : null;

    $stmt = $gameReady->prepare("UPDATE zones SET claimer_controller_id = ?, holder_controller_id = ? WHERE id = ?");
    $stmt->execute([$newClaimer, $newHolder, $zoneId]);

    $update_msg = "<p style='color: green;'>Zone #$zoneId mise à jour avec succès.</p>";
}

$controllerStmt = $gameReady->query("SELECT id, CONCAT(firstname, ' ', lastname) AS name FROM controllers ORDER BY id ASC");
$allControllers = $controllerStmt->fetchAll(PDO::FETCH_ASSOC);

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

require_once '../base/baseHTML.php';
?>
<div class='managment'>
    <h1>ZONES — Contrôle & Revendication</h1>
    <?php echo $update_msg; ?>
    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Nom de la Zone</th>
            <!-- <th>Description</th>-->
            <th>Sous la banière de</th>
            <th>Défendue par</th>
        </tr>
        <?php foreach ($zones as $zone): ?>
        <tr>
            <form method="post">
                <td><?= htmlspecialchars($zone['id']) ?></td>
                <td><?= htmlspecialchars($zone['name']) ?></td>
                <td>
                    <select name="claimer_id">
                        <option value="">-- Aucun --</option>
                        <?php foreach ($allControllers as $ctrl): ?>
                            <option value="<?= $ctrl['id'] ?>" <?= ($zone['claimer_id'] == $ctrl['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ctrl['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="holder_id">
                        <option value="">-- Aucun --</option>
                        <?php foreach ($allControllers as $ctrl): ?>
                            <option value="<?= $ctrl['id'] ?>" <?= ($zone['holder_id'] == $ctrl['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ctrl['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="hidden" name="zone_id" value="<?= $zone['id'] ?>">
                    <button type="submit">Update</button>
                </td>
            </form>
        </tr>
    <?php endforeach; ?>
    </table>
</div>
