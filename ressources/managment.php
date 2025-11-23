<?php 

require_once '../base/basePHP.php';

$pageName = 'ressources_admin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_ressource'])) {
        $stmt = $gameReady->prepare("INSERT INTO controller_ressources (controller_id, ressource_id, amount, amount_stored, end_turn_gain) VALUES (:controller_id, :ressource_id, :amount, :amount_stored, :end_turn_gain)");
        $stmt->execute([':controller_id' => $_POST['controller_id'], ':ressource_id' => $_POST['ressource_id'], ':amount' => $_POST['amount'], ':amount_stored' => $_POST['amount_stored'], ':end_turn_gain' => $_POST['end_turn_gain']]);
        echo "Ressource added to controller .";
    }

    if (isset($_POST['update_ressource'])) {
        $stmt = $gameReady->prepare("UPDATE controller_ressources SET amount = :amount, amount_stored = :amount_stored, end_turn_gain = :end_turn_gain WHERE id = :id");
        $stmt->execute([':amount' => $_POST['amount'], ':amount_stored' => $_POST['amount_stored'], ':end_turn_gain' => $_POST['end_turn_gain'], ':id' => $_POST['controller_ressource_id']]);
        echo "Ressource updated.";
    }

    if (isset($_POST['remove_ressource'])) {
        $stmt = $gameReady->prepare("DELETE FROM controller_ressources WHERE id = :id");
        $stmt->execute([':id' => $_POST['controller_ressource_id']]);
        echo "Ressource removed from controller.";
    }
}

// Fetch controllers and ressources
$controllerRessources = $gameReady->query(
        "SELECT rc.id as rc_id, r.id as ressource_id, rc.*, r.*, c.*
        FROM controller_ressources rc
        JOIN ressources_config r ON rc.ressource_id = r.id
        JOIN controllers c ON rc.controller_id = c.id
        ORDER BY rc.controller_id DESC")->fetchAll(PDO::FETCH_ASSOC);

require_once '../base/baseHTML.php';

/**    
  *  Show ressources_config table
  */
  $ressourcesConfig = $gameReady->query("SELECT * FROM ressources_config ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
  ?> 
  <div class="managment">
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
                <th>Actions</th>
    <?php foreach ($ressourcesConfig as $ressourceConfig) {
        echo sprintf('<tr>
            <td>%2$s</td>
            <td>%3$s</td>
            <td>%4$s</td>
            <td>%5$s</td>
            <td>%6$s</td>
            <td>%7$s</td>
            <td>%8$s</td>
            <td>%9$s</td>
            <td>%10$s</td>
            <td>%11$s</td>
            <td></td>',
            $ressourceConfig['rc_id'],
            $ressourceConfig['ressource_name'],
            $ressourceConfig['presentation'],
            $ressourceConfig['stored_text'],
            (isset($ressourceConfig['is_rollable']) && $ressourceConfig['is_rollable'] ? '✔️ Yes' : '❌ No'),
            (isset($ressourceConfig['is_stored']) && $ressourceConfig['is_stored'] ? '✔️ Yes' : '❌ No'),
            $ressourceConfig['base_building_cost'],
            $ressourceConfig['base_moving_cost'],
            $ressourceConfig['location_repaire_cost'],
            $ressourceConfig['servant_first_come_cost'],
            $ressourceConfig['servant_recruitment_cost']
        );
    }
    ?>
    </table>
</div>

<div class='managment'>
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

 $ressources = $gameReady->query("SELECT * FROM ressources_config ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
 $controllers = $gameReady->query("SELECT * FROM controllers ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
 ?>
 <div class='managment'>
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
