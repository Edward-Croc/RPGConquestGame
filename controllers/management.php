<?php
$pageName = 'Controller Management';

require_once '../base/basePHP.php';

// Admin-only page: require privileged session
if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

require_once '../base/baseHTML.php';

$prefix = $_SESSION['GAME_PREFIX'];

// Handle form submissions
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $player_id = intval($_POST['player_id'] ?? 0);
    $controller_id = intval($_POST['controller_id'] ?? 0);

    if (isset($_POST['add'])) {
        // Add player to controller
        $sql = "INSERT INTO {$prefix}player_controller (player_id, controller_id) 
                SELECT :player_id, :controller_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$prefix}player_controller WHERE player_id = :player_id AND controller_id = :controller_id
                )";
        $stmt = $gameReady->prepare($sql);
        $stmt->execute(['player_id' => $player_id, 'controller_id' => $controller_id]);
        $message = "Player added to controller.";
    } elseif (isset($_POST['remove'])) {
        // Remove player from controller
        $sql = "DELETE FROM {$prefix}player_controller WHERE player_id = :player_id AND controller_id = :controller_id";
        $stmt = $gameReady->prepare($sql);
        $stmt->execute(['player_id' => $player_id, 'controller_id' => $controller_id]);
        $message = "Player removed from controller.";
    }

    // Toggle can_build_base
    if (isset($_POST['toggle_base_controller_id'])) {
        $cid = intval($_POST['toggle_base_controller_id']);
        $current = $gameReady->prepare("SELECT can_build_base FROM {$prefix}controllers WHERE id = :id");
        $current->execute(['id' => $cid]);
        $val = $current->fetchColumn();
        $newVal = ($val) ? 0 : 1;
        $upd = $gameReady->prepare("UPDATE {$prefix}controllers SET can_build_base = :newVal WHERE id = :id");
        $upd->execute(['newVal' => $newVal, 'id' => $cid]);
        $message = "Can Build Base status toggled.";
    }

    // Toggle secret_controller
    if (isset($_POST['toggle_secret_controller_id'])) {
        $cid = intval($_POST['toggle_secret_controller_id']);
        $current = $gameReady->prepare("SELECT secret_controller FROM {$prefix}controllers WHERE id = :id");
        $current->execute(['id' => $cid]);
        $val = $current->fetchColumn();
        $newVal = ($val) ? 0 : 1;
        $upd = $gameReady->prepare("UPDATE {$prefix}controllers SET secret_controller = :newVal WHERE id = :id");
        $upd->execute(['newVal' => $newVal, 'id' => $cid]);
        $message = "Secret Controller status toggled.";
    }

    // Reset turn_recruited_workers
    if (isset($_POST['reset_turn_recruited_workers_id'])) {
        $cid = intval($_POST['reset_turn_recruited_workers_id']);
        $upd = $gameReady->prepare("UPDATE {$prefix}controllers SET turn_recruited_workers = 0 WHERE id = :id");
        $upd->execute(['id' => $cid]);
        $message = "Turn recruited workers reset.";
    }
    // Reset turn_firstcome_workers
    if (isset($_POST['reset_turn_firstcome_workers_id'])) {
        $cid = intval($_POST['reset_turn_firstcome_workers_id']);
        $upd = $gameReady->prepare("UPDATE {$prefix}controllers SET turn_firstcome_workers = 0 WHERE id = :id");
        $upd->execute(['id' => $cid]);
        $message = "Turn recruited firstcome workers reset.";
    }

}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['giftInformationAgent'])){
        //  Get Turn Number
        $mechanics = getMechanics($gameReady);
        $zone_id = $_GET['zone_id'];
        $target_controller_id = $_GET['target_controller_id'];
        $enemy_worker_id = $_GET['enemy_worker_id'];

        addWorkerToCKE($gameReady, $target_controller_id, $enemy_worker_id, $mechanics['turncounter'], $zone_id);
    }
    if (isset($_GET['giftInformationLocation'])){
        //  Get Turn Number
        $mechanics = getMechanics($gameReady);
        $target_controller_id = $_GET['target_controller_id'];
        $location_id = $_GET['location_id'];
        addLocationToCKL($gameReady, $target_controller_id, $location_id, $mechanics['turncounter'], false);
    }
}

// Fetch all players and controllers
$players = $gameReady->query("SELECT id, username FROM {$prefix}players ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
$controllers = $gameReady->query("SELECT id, lastname FROM {$prefix}controllers ORDER BY lastname")->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="content">
    <h1>Controller Management</h1>
    <?php if ($message): ?>
        <p style="color:green;"><?php echo $message; ?></p>
    <?php endif; ?>
    <form method="post">
        <label for="player_id">Player:</label>
        <select name="player_id" id="player_id" required>
            <option value="">-- Select Player --</option>
            <?php foreach ($players as $player): ?>
                <option value="<?php echo $player['id']; ?>"><?php echo $player['username']; ?></option>
            <?php endforeach; ?>
        </select>
        <label for="controller_id">Controller:</label>
        <select name="controller_id" id="controller_id" required>
            <option value="">-- Select Controller --</option>
            <?php foreach ($controllers as $controller): ?>
                <option value="<?php echo $controller['id']; ?>"><?php echo $controller['lastname']; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="add">Add Player to Controller</button>
        <button type="submit" name="remove">Remove Player from Controller</button>
    </form>
    <hr>
    <h2>Controller Details</h2>
    <table border="1">
        <tr>
            <th>ID - Controller</th>
            <th>Is seceret</th>
            <th>Can Build Base</th>
            <th>Total Recruited Workers</th>
            <th>Turn Recruited Workers</th>
            <th>Turn Recruited Firstcome Workers</th>
            <th>Players</th>
            <th>Action</th>
        </tr>
        <?php
        // Fetch all controllers with their properties and player list
        $controllers = $gameReady->query("
            SELECT 
                c.id,
                c.lastname,
                c.can_build_base,
                c.secret_controller,
                c.recruited_workers,
                c.turn_recruited_workers,
                c.turn_firstcome_workers
            FROM {$prefix}controllers c
            ORDER BY c.lastname
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($controllers as $controller) {
            // Fetch players for this controller
            $players = $gameReady->prepare("
                SELECT p.username 
                FROM {$prefix}player_controller pc
                JOIN {$prefix}players p ON pc.player_id = p.id
                WHERE pc.controller_id = :controller_id
                ORDER BY p.username
            ");
            $players->execute(['controller_id' => $controller['id']]);
            $playerList = $players->fetchAll(PDO::FETCH_COLUMN);

            echo sprintf('<tr class="controller-row" data-controller-id="%1$s" data-controller-name="%2$s">
                <td data-field="lastname">%1$s - %2$s</td>
                <td data-field="secret_controller">%3$s</td>
                <td data-field="can_build_base">%4$s</td>
                <td data-field="recruited_workers">%5$s</td>
                <td data-field="turn_recruited_workers">%6$s</td>
                <td data-field="turn_firstcome_workers">%7$s</td>
                <td data-field="players">%8$s</td>
                <td data-field="actions">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="toggle_base_controller_id" value="%1$s"/>
                    <button type="submit" name="toggle_base">Change Can Build Base status</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="toggle_secret_controller_id" value="%1$s"/>
                    <button type="submit" name="toggle_secret_controller">Change Is Secret Controller</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="reset_turn_recruited_workers_id" value="%1$s"/>
                    <button type="submit" name="reset_turn_recruited_workers">Reset Turn Workers</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="reset_turn_firstcome_workers_id" value="%1$s"/>
                    <button type="submit" name="reset_turn_firstcome_workers">Reset Firstcome Workers</button>
                </form>
                </td>
                </tr>',
                intval($controller['id']),
                $controller['lastname'],
                (isset($controller['secret_controller']) && $controller['secret_controller'] ? '✔️ Yes' : '❌ No'),
                (isset($controller['can_build_base']) && $controller['can_build_base'] ? '✔️ Yes' : '❌ No'),
                $controller['recruited_workers'],
                $controller['turn_recruited_workers'],
                $controller['turn_firstcome_workers'],
                implode(', ', $playerList)
            );
        }
        ?>
    </table>
    <?php echo buildGiveKnowledgeHTML($gameReady, 'admin'); ?>
</div>
<?php
$infoTxs = $gameReady->query(
    "SELECT
        l.turn,
        l.target_type,
        l.target_id,
        l.created_at,
        CONCAT(g.firstname, ' ', g.lastname) AS giver_name,
        gf.name AS giver_faction,
        CONCAT(r.firstname, ' ', r.lastname) AS recipient_name,
        rf.name AS recipient_faction
     FROM {$prefix}information_gift_logs l
     JOIN {$prefix}controllers g ON l.giver_controller_id = g.id
     LEFT JOIN {$prefix}factions gf ON g.faction_id = gf.ID
     JOIN {$prefix}controllers r ON l.recipient_controller_id = r.id
     LEFT JOIN {$prefix}factions rf ON r.faction_id = rf.ID
     ORDER BY l.turn DESC, l.created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($infoTxs as &$tx) {
    if ($tx['target_type'] === 'agent') {
        $s = $gameReady->prepare("SELECT CONCAT(firstname, ' ', lastname) AS lbl FROM {$prefix}workers WHERE id = :id");
        $s->execute([':id' => (int)$tx['target_id']]);
        $tx['target_label'] = $s->fetchColumn() ?: '#'.(int)$tx['target_id'];
    } elseif ($tx['target_type'] === 'location') {
        $s = $gameReady->prepare("SELECT name AS lbl FROM {$prefix}locations WHERE id = :id");
        $s->execute([':id' => (int)$tx['target_id']]);
        $tx['target_label'] = $s->fetchColumn() ?: '#'.(int)$tx['target_id'];
    } else {
        $tx['target_label'] = '#'.(int)$tx['target_id'];
    }
}
unset($tx);
?>
<div class='management'>
    <h1>Information Transactions</h1>
<?php if (empty($infoTxs)): ?>
    <p>Aucune transaction enregistrée.</p>
<?php else: ?>
    <table border="1" cellpadding="5">
        <tr>
            <th>Turn</th>
            <th>Date</th>
            <th>Giver</th>
            <th>Recipient</th>
            <th>Type</th>
            <th>Target</th>
        </tr>
<?php foreach ($infoTxs as $tx): ?>
        <tr>
            <td><?= (int)$tx['turn'] ?></td>
            <td><?= htmlspecialchars($tx['created_at']) ?></td>
            <td><?= htmlspecialchars($tx['giver_name'].' ('.($tx['giver_faction'] ?? '—').')') ?></td>
            <td><?= htmlspecialchars($tx['recipient_name'].' ('.($tx['recipient_faction'] ?? '—').')') ?></td>
            <td><?= htmlspecialchars($tx['target_type']) ?></td>
            <td><?= htmlspecialchars($tx['target_label']) ?></td>
        </tr>
<?php endforeach; ?>
    </table>
<?php endif; ?>
</div>