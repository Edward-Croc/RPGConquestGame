<?php
$pageName = 'Configuration';

require_once '../base/basePHP.php';

// $GLOBALS['DEBUG_LOG_SECTIONS'][] = 'configuration_page';  // uncomment to log DEBUG events from this page

$prefix = $_SESSION['GAME_PREFIX'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if action is to add a new config value
    if (isset($_POST['add_config'])) {
        $name = $_POST['name'];
        $value = $_POST['value'];

        try{
            // Insert new config value into the database
            $stmt = $gameReady->prepare("INSERT INTO {$prefix}config (name, value) VALUES (:name, :value)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
        } catch (PDOException $e) {
            game_error_log('configuration_page', 'INSERT config failed', ['error' => $e->getMessage()]);
        }
    }

    // Check if action is to delete a config value
    if (isset($_POST['delete_config'])) {
        game_error_log('configuration_page', 'delete_config request', ['id' => $_POST['id']], 'debug');
        try{
            $id = $_POST['id'];

            // Delete config value from the database
            $stmt = $gameReady->prepare("DELETE FROM {$prefix}config WHERE ID = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
        } catch (PDOException $e) {
            game_error_log('configuration_page', 'DELETE config failed', ['error' => $e->getMessage()]);
        }
    }

    // Check if action is to update a config value
    if (isset($_POST['update_config'])) {
        game_error_log('configuration_page', 'update_config request', ['id' => $_POST['id'], 'value' => $_POST['value']], 'debug');
        try{
            $id = $_POST['id'];
            $value = $_POST['value'];

            // Update config value in the database
            $stmt = $gameReady->prepare("UPDATE {$prefix}config SET value = :value WHERE ID = :id");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
        } catch (PDOException $e) {
            game_error_log('configuration_page', 'UPDATE config failed', ['error' => $e->getMessage()]);
        }
    }
}

try{
    // Retrieve config values from the database
    $stmt = $gameReady->query("SELECT * FROM {$prefix}config ORDER BY ID ASC");
    $config_values = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    game_error_log('configuration_page', 'SELECT config failed', ['error' => $e->getMessage()]);
}

require_once '../base/baseHTML.php';

?>
        <div class="config">
            <p>Documentation : <a href="/<?php echo htmlspecialchars($_SESSION['FOLDER']); ?>/base/docConfig.php">Guide de configuration</a> — référence des clés de configuration et des modes du système.</p>
        </div>
        <div  class="config">
            <form method="post">
                <h2>Add New Config Value</h2>
                <label>Name:</label> <input type="text" name="name" required><br />
                <label>Value:</label> <input type="text" name="value" required><br />
                <input type="submit" name="add_config" value="Add">
            </form>
        </div>
        <div class="config">
        <table border="1">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Value</th>
                    <th>Actions</th>
                </tr>
        <?php
       // Display config values in a table
        foreach ($config_values as $config) {
            echo '<tr>
                <form method="post">
                    <td>' . $config['id'] . '</td>
                    <td>' . $config['name'] . '</td>
                    <td><input type="textarea" name="value" value="' . htmlspecialchars($config['value']) . '"></td>
                    <td>
                        <input type="hidden" name="id" value="' . $config['id'] . '">
                        <input type="submit" name="update_config" value="Update">
                        <input type="submit" name="delete_config" value="Delete">
                    </td>
                </form>
            </tr>';
        }
        ?>
        </table>
        </div>