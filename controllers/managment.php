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
    <h2>Current Player-Controller Assignments</h2>
    <table border="1">
        <tr>
            <th>Player</th>
            <th>Controller</th>
        </tr>
        <?php
        $assignments = $gameReady->query(
            "SELECT p.username, c.lastname
             FROM player_controller pc
             JOIN players p ON pc.player_id = p.id
             JOIN controllers c ON pc.controller_id = c.id
             ORDER BY c.lastname, p.username"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($assignments as $row):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['username']); ?></td>
            <td><?php echo htmlspecialchars($row['lastname']); ?></td>
        </tr>
        <?php endforeach; ?>