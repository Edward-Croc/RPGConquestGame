<?php
session_start();

$pageName = 'Controller Management';

require_once '../base/basePHP.php';
require_once '../base/baseHTML.php';

// Handle form submissions
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $player_id = intval($_POST['player_id'] ?? 0);
    $controller_id = intval($_POST['controller_id'] ?? 0);

    if (isset($_POST['add'])) {
        // Add player to controller
        $sql = "INSERT INTO player_controller (player_id, controller_id) 
                SELECT :player_id, :controller_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM player_controller WHERE player_id = :player_id AND controller_id = :controller_id
                )";
        $stmt = $gameReady->prepare($sql);
        $stmt->execute(['player_id' => $player_id, 'controller_id' => $controller_id]);
        $message = "Player added to controller.";
    } elseif (isset($_POST['remove'])) {
        // Remove player from controller
        $sql = "DELETE FROM player_controller WHERE player_id = :player_id AND controller_id = :controller_id";
        $stmt = $gameReady->prepare($sql);
        $stmt->execute(['player_id' => $player_id, 'controller_id' => $controller_id]);
        $message = "Player removed from controller.";
    }

    // Toggle can_build_base
    if (isset($_POST['toggle_base_controller_id'])) {
        $cid = intval($_POST['toggle_base_controller_id']);
        $current = $gameReady->prepare("SELECT can_build_base FROM controllers WHERE id = :id");
        $current->execute(['id' => $cid]);
        $val = $current->fetchColumn();
        $newVal = ($val) ? 0 : 1;
        $upd = $gameReady->prepare("UPDATE controllers SET can_build_base = :newVal WHERE id = :id");
        $upd->execute(['newVal' => $newVal, 'id' => $cid]);
        $message = "Can Build Base status toggled.";
    }

    // Toggle secret_controller
    if (isset($_POST['toggle_secret_controller_id'])) {
        $cid = intval($_POST['toggle_secret_controller_id']);
        $current = $gameReady->prepare("SELECT secret_controller FROM controllers WHERE id = :id");
        $current->execute(['id' => $cid]);
        $val = $current->fetchColumn();
        $newVal = ($val) ? 0 : 1;
        $upd = $gameReady->prepare("UPDATE controllers SET secret_controller = :newVal WHERE id = :id");
        $upd->execute(['newVal' => $newVal, 'id' => $cid]);
        $message = "Secret Controller status toggled.";
    }

    // Reset turn_recruited_workers
    if (isset($_POST['reset_turn_recruited_workers_id'])) {
        $cid = intval($_POST['reset_turn_recruited_workers_id']);
        $upd = $gameReady->prepare("UPDATE controllers SET turn_recruited_workers = 0 WHERE id = :id");
        $upd->execute(['id' => $cid]);
        $message = "Turn recruited workers reset.";
    }
    // Reset turn_firstcome_workers
    if (isset($_POST['reset_turn_firstcome_workers_id'])) {
        $cid = intval($_POST['reset_turn_firstcome_workers_id']);
        $upd = $gameReady->prepare("UPDATE controllers SET turn_firstcome_workers = 0 WHERE id = :id");
        $upd->execute(['id' => $cid]);
        $message = "Turn recruited firstcome workers reset.";
    }
}

// Fetch all players and controllers
$players = $gameReady->query("SELECT id, username FROM players ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
$controllers = $gameReady->query("SELECT id, lastname FROM controllers ORDER BY lastname")->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="content">
    <h1>Controller Management</h1>
    <?php if ($message): ?>
        <p style="color:green;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form method="post">
        <label for="player_id">Player:</label>
        <select name="player_id" id="player_id" required>
            <option value="">-- Select Player --</option>
            <?php foreach ($players as $player): ?>
                <option value="<?php echo $player['id']; ?>"><?php echo htmlspecialchars($player['username']); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="controller_id">Controller:</label>
        <select name="controller_id" id="controller_id" required>
            <option value="">-- Select Controller --</option>
            <?php foreach ($controllers as $controller): ?>
                <option value="<?php echo $controller['id']; ?>"><?php echo htmlspecialchars($controller['lastname']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="add">Add Player to Controller</button>
        <button type="submit" name="remove">Remove Player from Controller</button>
    </form>
    <hr>
    <h2>Controller Details</h2>
    <table border="1">
        <tr>
            <th>Controller</th>
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
            FROM controllers c
            ORDER BY c.lastname
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($controllers as $controller) {
            // Fetch players for this controller
            $players = $gameReady->prepare("
                SELECT p.username 
                FROM player_controller pc
                JOIN players p ON pc.player_id = p.id
                WHERE pc.controller_id = :controller_id
                ORDER BY p.username
            ");
            $players->execute(['controller_id' => $controller['id']]);
            $playerList = $players->fetchAll(PDO::FETCH_COLUMN);

            echo "<tr>";
            echo "<td>" . htmlspecialchars($controller['lastname']) . "</td>";
            echo "<td>" . (isset($controller['secret_controller']) && $controller['secret_controller'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . (isset($controller['can_build_base']) && $controller['can_build_base'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . htmlspecialchars($controller['recruited_workers']) . "</td>";
            echo "<td>" . htmlspecialchars($controller['turn_recruited_workers']) . "</td>";
            echo "<td>" . htmlspecialchars($controller['turn_firstcome_workers']) . "</td>";
            echo "<td>" . htmlspecialchars(implode(', ', $playerList)) . "</td>";
            // Add forms for actions
            echo '<td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="toggle_base_controller_id" value="' . intval($controller['id']) . '"/>
                    <button type="submit" name="toggle_base">Change Can Build Base status</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="toggle_secret_controller_id" value="' . intval($controller['id']) . '"/>
                    <button type="submit" name="toggle_secret_controller">Change Is Secret Controller</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="reset_turn_recruited_workers_id" value="' . intval($controller['id']) . '"/>
                    <button type="submit" name="reset_turn_recruited_workers">Reset Turn Workers</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="reset_turn_firstcome_workers_id" value="' . intval($controller['id']) . '"/>
                    <button type="submit" name="reset_turn_firstcome_workers">Reset Firstcome Workers</button>
                </form>
            </td>';
            echo "</tr>";
        }
        ?>
    </table>
</div>