<?php
require_once '../base/basePHP.php'; // Set up $pdo and session

// Admin-only page: require privileged session
if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

$pageName = 'admin_zones';

$prefix = $_SESSION['GAME_PREFIX'];

$update_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zone_id'])) {
    $zoneId = (int) $_POST['zone_id'];
    $newClaimer = !empty($_POST['claimer_id']) ? $_POST['claimer_id'] : null;
    $newHolder = !empty($_POST['holder_id']) ? $_POST['holder_id'] : null;
    $adjacentZones = isset($_POST['adjacent_zones']) ? trim($_POST['adjacent_zones']) : '';

    $zoneRulesRaw = isset($_POST['zone_rules']) ? $_POST['zone_rules'] : '';
    $zoneRulesToStore = null;
    $jsonError = false;
    if (trim($zoneRulesRaw) !== '') {
        $decoded = json_decode($zoneRulesRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $jsonError = true;
        } else {
            $zoneRulesToStore = json_encode($decoded);
        }
    }

    if ($jsonError) {
        $update_msg = "<p style='color: red;'>Zone #$zoneId : JSON invalide, mise à jour annulée.</p>";
    } else {
        $stmt = $gameReady->prepare("UPDATE {$prefix}zones SET claimer_controller_id = ?, holder_controller_id = ?, adjacent_zones = ?, zone_rules = ? WHERE id = ?");
        $stmt->execute([$newClaimer, $newHolder, $adjacentZones, $zoneRulesToStore, $zoneId]);
        $update_msg = "<p style='color: green;'>Zone #$zoneId mise à jour avec succès.</p>";
    }
}

$controllerStmt = $gameReady->query("SELECT id, CONCAT(firstname, ' ', lastname) AS name FROM {$prefix}controllers ORDER BY id ASC");
$allControllers = $controllerStmt->fetchAll(PDO::FETCH_ASSOC);

$zoneSql = "
SELECT
    z.id,
    z.name,
    z.description,
    z.adjacent_zones,
    z.zone_rules,
    claimer.id AS claimer_id,
    CONCAT(claimer.firstname, ' ', claimer.lastname) AS claimer_name,
    holder.id AS holder_id,
    CONCAT(holder.firstname, ' ', holder.lastname) AS holder_name
FROM {$prefix}zones z
LEFT JOIN {$prefix}controllers claimer ON z.claimer_controller_id = claimer.id
LEFT JOIN {$prefix}controllers holder ON z.holder_controller_id = holder.id
ORDER BY z.id ASC
";
$zoneStmt = $gameReady->query($zoneSql);
$zones = $zoneStmt->fetchAll(PDO::FETCH_ASSOC);

$zoneNameById = [];
foreach ($zones as $z) { $zoneNameById[(int)$z['id']] = $z['name']; }

require_once '../base/baseHTML.php';
?>
<div class='management'>
    <h1>ZONES — Contrôle & Revendication</h1>
    <?php echo $update_msg; ?>
    <div style="overflow-x: auto;">
    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Nom de la Zone</th>
            <!-- <th>Description</th>-->
            <th>Sous la banière de</th>
            <th>Défendue par</th>
            <th>Zones adjacentes</th>
            <th>Adjacent zones (raw)</th>
            <th>Zone rules (JSON)</th>
            <th></th>
        </tr>
        <?php foreach ($zones as $zone):
            $adjacentNames = '—';
            if (!empty($zone['adjacent_zones'])) {
                $ids = array_filter(array_map('trim', explode(',', $zone['adjacent_zones'])));
                $names = [];
                foreach ($ids as $id) {
                    $names[] = $zoneNameById[(int)$id] ?? "#$id";
                }
                if ($names) $adjacentNames = implode(', ', $names);
            }
        ?>
        <tr> <form method="post" style="display:inline;">
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
            <td data-field="adjacent_zones"><?= htmlspecialchars($adjacentNames) ?></td>
            <td>
                <input type="text" name="adjacent_zones" size="20" value="<?= htmlspecialchars($zone['adjacent_zones'] ?? '') ?>">
            </td>
            <td>
                <textarea name="zone_rules" rows="4" cols="60"><?= htmlspecialchars($zone['zone_rules'] ?? '') ?></textarea>
            </td>
            <td>
                <input type="hidden" name="zone_id" value="<?= $zone['id'] ?>">
                <button type="submit">Update</button>
            </td>
        </form> </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
