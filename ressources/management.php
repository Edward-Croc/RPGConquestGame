<?php 

require_once '../base/basePHP.php';

// Admin-only page: require privileged session
if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

$pageName = 'ressources_admin';

$prefix = $_SESSION['GAME_PREFIX'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_ressource'])) {
        $stmt = $gameReady->prepare("INSERT INTO {$prefix}controller_ressources (controller_id, ressource_id, amount, amount_stored, end_turn_gain) VALUES (:controller_id, :ressource_id, :amount, :amount_stored, :end_turn_gain)");
        $stmt->execute([':controller_id' => $_POST['controller_id'], ':ressource_id' => $_POST['ressource_id'], ':amount' => $_POST['amount'], ':amount_stored' => $_POST['amount_stored'], ':end_turn_gain' => $_POST['end_turn_gain']]);
        echo "Ressource added to controller .";
    }

    if (isset($_POST['update_ressource'])) {
        $stmt = $gameReady->prepare("UPDATE {$prefix}controller_ressources SET amount = :amount, amount_stored = :amount_stored, end_turn_gain = :end_turn_gain WHERE id = :id");
        $stmt->execute([':amount' => $_POST['amount'], ':amount_stored' => $_POST['amount_stored'], ':end_turn_gain' => $_POST['end_turn_gain'], ':id' => $_POST['controller_ressource_id']]);
        echo "Ressource updated.";
    }

    if (isset($_POST['remove_ressource'])) {
        $stmt = $gameReady->prepare("DELETE FROM {$prefix}controller_ressources WHERE id = :id");
        $stmt->execute([':id' => $_POST['controller_ressource_id']]);
        echo "Ressource removed from controller.";
    }

    if (isset($_POST['update_gain_rules'])) {
        $rules = trim($_POST['gain_rules'] ?? '');
        $stmt = $gameReady->prepare("UPDATE {$prefix}ressources_config SET gain_rules = :rules WHERE id = :id");
        $stmt->execute([
            ':rules' => $rules !== '' ? $rules : null,
            ':id'    => $_POST['ressource_config_id'],
        ]);
        echo "Gain rules updated.";
    }
}

// Fetch controllers and ressources
$controllerRessources = $gameReady->query(
        "SELECT rc.id as rc_id, r.id as ressource_id, rc.*, r.*, c.*
        FROM {$prefix}controller_ressources rc
        JOIN {$prefix}ressources_config r ON rc.ressource_id = r.id
        JOIN {$prefix}controllers c ON rc.controller_id = c.id
        ORDER BY rc.controller_id DESC")->fetchAll(PDO::FETCH_ASSOC);

require_once '../base/baseHTML.php';

/**    
  *  Show ressources_config table
  */
  $ressourcesConfig = $gameReady->query("SELECT * FROM {$prefix}ressources_config ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
  ?> 
  <div class="management">
        <h1>Ressources Config</h1>
        <table border="1" cellpadding="5">
            <tr>
                <th>Ressource Name</th>
                <th>Presentation</th>
                <th>Stored Text</th>
                <th>Is Rollable</th>
                <th>Is Stored</th>
                <th>Base Building Cost</th>
                <th>Base Moving Cost</th>
                <th>Location Repaire Cost</th>
                <th>Servant First Come Cost</th>
                <th>Servant Recruitment Cost</th>
                <th>Gain Rules (JSON)</th>
                <th>Actions</th>
<?php foreach ($ressourcesConfig as $ressourceConfig): ?>
        <tr>
            <td><?= htmlspecialchars($ressourceConfig['ressource_name']) ?></td>
            <td><?= htmlspecialchars($ressourceConfig['presentation']) ?></td>
            <td><?= htmlspecialchars($ressourceConfig['stored_text']) ?></td>
            <td><?= !empty($ressourceConfig['is_rollable']) ? '✔️ Yes' : '❌ No' ?></td>
            <td><?= !empty($ressourceConfig['is_stored']) ? '✔️ Yes' : '❌ No' ?></td>
            <td><?= (int)$ressourceConfig['base_building_cost'] ?></td>
            <td><?= (int)$ressourceConfig['base_moving_cost'] ?></td>
            <td><?= (int)$ressourceConfig['location_repaire_cost'] ?></td>
            <td><?= (int)$ressourceConfig['servant_first_come_cost'] ?></td>
            <td><?= (int)$ressourceConfig['servant_recruitment_cost'] ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <textarea name="gain_rules" rows="3" cols="40"><?= htmlspecialchars($ressourceConfig['gain_rules'] ?? '') ?></textarea>
                    <input type="hidden" name="ressource_config_id" value="<?= (int)$ressourceConfig['id'] ?>">
                    <br><button type="submit" name="update_gain_rules">Update gain rules</button>
                </form>
            </td>
        </tr>
<?php endforeach; ?>
    </table>
</div>

<div class='management'>
    <h1>Ressources Management</h1>
    <table border="1" cellpadding="5">
        <tr>
            <th>Controller</th>
            <th>Ressource</th>
            <th>Amount</th>
            <th>Amount Stored</th>
            <th>End Turn Gain</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($controllerRessources as $controllerRessource) {
            echo sprintf('<tr> <form method="POST" style="display:inline;">
                    <td>%2$s</td>
                    <td>%3$s</td>
                    <td> <input type="number" name="amount" value="%4$s"> </td>
                    <td> <input type="number" name="amount_stored" value="%5$s"> </td>
                    <td> <input type="number" name="end_turn_gain" value="%6$s"> </td>
                    <td>
                            <input type="hidden" name="controller_ressource_id" value="%1$s">
                            <button type="submit" name="update_ressource">Update</button>
                            <button type="submit" name="remove_ressource">Remove</button>

                    </td>
                </form> </tr>',
                    $controllerRessource['rc_id'],
                    $controllerRessource['firstname'] . ' ' . $controllerRessource['lastname'],
                    $controllerRessource['ressource_name'],
                    $controllerRessource['amount'],
                    $controllerRessource['amount_stored'],
                    $controllerRessource['end_turn_gain']
                );
        }
        ?>
    </table>
</div>
<?php 

/**
 *  2nd Add an existing ressource to a controller that does not have it
 *  - Select the controller
 *  - Select the ressource
 *  - Add the ressource to the controller
 *  - Redirect to the 1st form
 */

 $ressources = $gameReady->query("SELECT * FROM {$prefix}ressources_config ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
 $controllers = $gameReady->query("SELECT * FROM {$prefix}controllers ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
 ?>
 <div class='management'>
    <h1>Add Ressource to Controller</h1>
    <form method="POST">
        <p>
            <label>Controller:</label>
            <select name="controller_id">
                <?php foreach ($controllers as $controller): ?>
                    <option value="<?php echo $controller['id']; ?>"><?php echo $controller['firstname'] . ' ' . $controller['lastname']; ?></option>
                <?php endforeach; ?>
            </select>
            <label>Ressource:</label>
            <select name="ressource_id">
                <?php foreach ($ressources as $ressource): ?>
                    <option value="<?php echo $ressource['id']; ?>"><?php echo $ressource['ressource_name']; ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label>Amount:</label>
            <input type="number" name="amount" value="0">
            <label>Amount Stored:</label>
            <input type="number" name="amount_stored" value="0">
            <label>End Turn Gain:</label>
            <input type="number" name="end_turn_gain" value="0">
        </p>
        <button type="submit" name="add_ressource">Add Ressource</button>
    </form>
 </div>
<?php
$transactions = $gameReady->query(
    "SELECT
        l.turn,
        l.amount,
        l.created_at,
        CONCAT(g.firstname, ' ', g.lastname) AS giver_name,
        gf.name AS giver_faction,
        CONCAT(r.firstname, ' ', r.lastname) AS recipient_name,
        rf.name AS recipient_faction,
        rc.ressource_name AS ressource
     FROM {$prefix}ressource_gift_logs l
     JOIN {$prefix}controllers g ON l.giver_controller_id = g.id
     LEFT JOIN {$prefix}factions gf ON g.faction_id = gf.ID
     JOIN {$prefix}controllers r ON l.recipient_controller_id = r.id
     LEFT JOIN {$prefix}factions rf ON r.faction_id = rf.ID
     JOIN {$prefix}ressources_config rc ON l.ressource_id = rc.id
     ORDER BY l.turn DESC, l.created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<div class='management'>
    <h1>Ressource Transactions</h1>
<?php if (empty($transactions)): ?>
    <p>Aucune transaction enregistrée.</p>
<?php else: ?>
    <table border="1" cellpadding="5">
        <tr>
            <th>Turn</th>
            <th>Date</th>
            <th>Giver</th>
            <th>Recipient</th>
            <th>Ressource</th>
            <th>Amount</th>
        </tr>
<?php foreach ($transactions as $tx): ?>
        <tr>
            <td><?= (int)$tx['turn'] ?></td>
            <td><?= htmlspecialchars($tx['created_at']) ?></td>
            <td><?= htmlspecialchars($tx['giver_name'].' ('.($tx['giver_faction'] ?? '—').')') ?></td>
            <td><?= htmlspecialchars($tx['recipient_name'].' ('.($tx['recipient_faction'] ?? '—').')') ?></td>
            <td><?= htmlspecialchars($tx['ressource']) ?></td>
            <td><?= (int)$tx['amount'] ?></td>
        </tr>
<?php endforeach; ?>
    </table>
<?php endif; ?>
</div>
