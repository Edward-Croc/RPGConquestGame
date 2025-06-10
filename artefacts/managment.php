<?php

require_once '../base/basePHP.php';

$pageName = 'artefacts_admin';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $gameReady->prepare("DELETE FROM artefacts WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
    }

    if (isset($_POST['update_location'])) {
        $stmt = $gameReady->prepare("UPDATE artefacts SET location_id = ? WHERE id = ?");
        $stmt->execute([$_POST['new_location_id'], $_POST['artefact_id']]);
    }

    if (isset($_POST['add_artefact'])) {
        $stmt = $gameReady->prepare("INSERT INTO artefacts (name, description, full_description, location_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['artefact_name'], $_POST['artefact_description'], $_POST['artefact_full_description'], $_POST['location_id']]);
    }

    if (isset($_POST['update_description'])) {
        $stmt = $gameReady->prepare("UPDATE artefacts SET description = ?, full_description = ? WHERE id = ?");
        $stmt->execute([
            $_POST['description'],
            $_POST['full_description'],
            $_POST['artefact_id']
        ]);
    }
    
}

// Fetch artefacts and locations
$artefacts = $gameReady->query("
    SELECT a.id, a.name, a.location_id, a.description, a.full_description,
           CONCAT(l.name, ' - ', z.name) AS location_name
    FROM artefacts a
    LEFT JOIN locations l ON a.location_id = l.id
    LEFT JOIN zones z ON z.id = l.zone_id
    ORDER BY a.id
")->fetchAll(PDO::FETCH_ASSOC);

$locations = $gameReady->query("SELECT id, name FROM locations ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

require_once '../base/baseHTML.php';

?>

    <h1>Artefacts List</h1>
    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Location</th>
            <th>Change Location</th>
            <th>Delete</th>
        </tr>
        <?php foreach ($artefacts as $art): ?>
            <tr>
                <td><?= htmlspecialchars($art['id']) ?></td>
                <td><?= htmlspecialchars($art['name']) ?></td>
                <td><?= htmlspecialchars($art['location_name']) ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="artefact_id" value="<?= $art['id'] ?>">
                        <select name="new_location_id">
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['id'] ?>" <?= $loc['id'] == $art['location_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_location">Update</button>
                    </form>
                </td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?= $art['id'] ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
            <tr>
                <td colspan="5">
                    <form method="POST" style="margin-top:5px;">
                        <input type="hidden" name="artefact_id" value="<?= $art['id'] ?>">
                        <label>Description:
                            <input type="text" name="description" value="<?= htmlspecialchars($art['description'] ?? '') ?>" size="40">
                        </label>
                        <label>Full Description:
                            <input type="text" name="full_description" value="<?= htmlspecialchars($art['full_description'] ?? '') ?>" size="60">
                        </label>
                        <button type="submit" name="update_description">Update Descriptions</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Add New Artefact</h2>
    <form method="POST">
        <label>Artefact Name:
            <input type="text" name="artefact_name" required>
        <label>Found description:
            <input type="text" name="artefact_description" required>
        <label>Full description:
            <input type="text" name="artefact_full_description" required>
        </label>
        <label>Location:
            <select name="location_id" required>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" name="add_artefact">Add Artefact</button>
    </form>
</body>
</html>
