<?php
$pageName = 'admin';

require_once '../base/base_php.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if action is to add a new config value
    if (isset($_POST['add_config'])) {
        $name = $_POST['name'];
        $value = $_POST['value'];

        try{
            // Insert new config value into the database
            $stmt = $gameReady->prepare("INSERT INTO config (name, value) VALUES (:name, :value)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__."(): INSERT config Failed: " . $e->getMessage()."<br />";
        }
    }

    // Check if action is to delete a config value
    if (isset($_POST['delete_config'])) {
        if ($_SESSION['DEBUG'] == true){
            echo "delete_config => id: ".$_POST['id'].".<br />";
        }
        try{
            $id = $_POST['id'];

            // Delete config value from the database
            $stmt = $gameReady->prepare("DELETE FROM config WHERE ID = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__."(): DELETE config Failed: " . $e->getMessage()."<br />";
        }
    }

    // Check if action is to update a config value
    if (isset($_POST['update_config'])) {
        if ($_SESSION['DEBUG'] == true){
            echo "update_config => id: ".$_POST['id']." and value ".$_POST['value'].".<br />";
        }
        try{
            $id = $_POST['id'];
            $value = $_POST['value'];

            // Update config value in the database
            $stmt = $gameReady->prepare("UPDATE config SET value = :value WHERE ID = :id");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__."(): UPDATE config Failed: " . $e->getMessage()."<br />";
        }
    }

    if ( isset($_POST['resetBDD']) ) {
        // empty controler SESSION
        $_SESSION['controler'] = NULL;
        destroyAllTables($gameReady);
        $gameReady = gameReady();
    }
}

try{
    // Retrieve config values from the database
    $stmt = $gameReady->query("SELECT * FROM config ORDER BY ID ASC");
    $config_values = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo __FUNCTION__."(): GET config Failed: " . $e->getMessage()."<br />";
}

require_once '../base/base_html.php';

?>

<div class="content">
    <div class="flex">
        <div  class="mecanics">
            <h1>Mecanics</h1>
        </div>
        <div class="mecanics">
        <table border="1">
            <tr>
                <th> Key </th>
                <th> Value </th>
            </tr>
            <?php
           // Display config values in a table
            foreach ($mecanics as $key => $value) {
            echo" <tr>
                <td> $key </td>
                <td> $value </td>
             </tr>";
            }
            ?>
        </table>
        </div>
    </div>
    <div class="flex">
        <div  class="config">
            <h1>Config Management</h1>
        </div>
        <div  class="config">
            <form action="/RPGConquestGame/connection/admin.php" method="post">
                <h2> FULL Reset :
                    <select id="configSelect" name="config_name">
                        <option  value='Vampire1966'> Firenze Vampire 1966 </option>
                        <option  value='Japon1555'> Shikoku (四国) Succession 1555 </option>
                    </select>
                    <input type="hidden" name="resetBDD" />
                    <input type="submit" name="submit" value="Submit" />
                </h2>
            </form>
        </div>
    </div>
    <div class="flex">
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
                    <td><input type="text" name="value" value="' . $config['value'] . '"></td>
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
    </div>
</div>
</body>
