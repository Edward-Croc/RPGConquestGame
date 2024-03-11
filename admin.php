<?php
session_start(); // Start the session

require_once './dbConnector.php';

// Call the gameReady() function from dbConnector.php
$gameReady = gameReady();
// Use the return value
if (!$gameReady) {
    echo "The game is not ready. Please check DB Configuration and Setup. <br />";
    exit();
}else{
    $_SESSION['DEBUG'] = getConfig($gameReady, 'DEBUG');
    if ($_SESSION['DEBUG'] == true){
        echo "The game is ready.<br />";
    }
}

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect the user to the login page if not logged in
    header('Location: loginForm.php');
    exit();
}
if ($_SESSION['DEBUG'] == true){
    echo "Debug : ".$_SESSION['DEBUG'].";  ID: " . $_SESSION['userid']. ", is_privileged: " . $_SESSION['is_privileged']. "<br />";
}

if (
    isset($_POST['resetBDD'])
) {
    destroyAllTables($gameReady);
    $gameReady = gameReady();
}

?>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPGConquestGame</title>
    <style>
        <?php include_once './style.css'; ?>
    </style>
</head>
<body>
<div class="header">
    <h1>RPGConquestGame</h1>
    <div class="menu_top_left">
        <a href="index.php" class="admin-btn">Retour</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>
<div class="content">
    <div class="flex">
        <div  class="config">
            <h1>Config Management</h1>
        </div>
        <div  class="config">
            <form action="admin.php" method="post">
                <h2> FULL Reset :
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
                <label>Name:</label> <input type="text" name="name" required><br>
                <label>Value:</label> <input type="text" name="value" required><br>
                <input type="submit" name="add_config" value="Add">
            </form>
        </div>
        <div class="config">
        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Check if action is to add a new config value
            if (isset($_POST['add_config'])) {
                $name = $_POST['name'];
                $value = $_POST['value'];

                // Insert new config value into the database
                $stmt = $gameReady->prepare("INSERT INTO config (name, value) VALUES (:name, :value)");
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':value', $value);
                $stmt->execute();
            }

            // Check if action is to delete a config value
            if (isset($_POST['delete_config'])) {
                $id = $_POST['id'];

                // Delete config value from the database
                $stmt = $gameReady->prepare("DELETE FROM config WHERE ID = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
            }

            // Check if action is to update a config value
            if (isset($_POST['update_config'])) {
                $id = $_POST['id'];
                $value = $_POST['value'];

                // Update config value in the database
                $stmt = $gameReady->prepare("UPDATE config SET value = :value WHERE ID = :id");
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':value', $value);
                $stmt->execute();
            }
        }

        // Retrieve config values from the database
        $stmt = $gameReady->query("SELECT * FROM config");
        $config_values = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Display config values in a table
        echo '<table border="1">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Value</th>
                    <th>Actions</th>
                </tr>';
        foreach ($config_values as $config) {
            echo '<tr>
                    <td>' . $config['id'] . '</td>
                    <td>' . $config['name'] . '</td>
                    <td><form method="post"><input type="text" name="value" value="' . $config['value'] . '"></td>
                    <td>
                        <input type="hidden" name="id" value="' . $config['id'] . '">
                        <input type="submit" name="update_config" value="Update">
                        <input type="submit" name="delete_config" value="Delete">
                    </td>
                  </tr>';
        }
        echo '</table>';
        ?>
        </div>
    </div>
</div>
</body>
