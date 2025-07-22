<?php
$pageName = 'admin';

require_once '../base/basePHP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if ( isset($_POST['resetBDD']) ) {
        // empty controller SESSION
        $_SESSION['controller'] = NULL;
        destroyAllTables($gameReady);
        $gameReady = gameReady();
    }
}


require_once '../base/baseHTML.php';

?>

<div class="content">
    <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
        <div class="mechanics">
            <h1>Mechanics</h1>
            <?php echo getConfig($gameReady, 'IntrigueOrga'); ?>
            <table border="1">
                <tr>
                    <th> Key </th>
                    <th> Value </th>
                </tr>
                <?php
            // Display config values in a table
                foreach ($mechanics as $key => $value) {
                echo" <tr>
                    <td> $key </td>
                    <td> $value </td>
                </tr>";
                }
                ?>
            </table>
        </div>
        <div class="config">
            <h1>Config Management</h1>
            <?php echo sprintf('<form action="/%s/base/admin.php" method="post">',  $_SESSION['FOLDER']); ?>
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
        <div class="config">
                <h1>Management</h1>
                <?php echo sprintf( '
                <p> <a href="/%1$s/base/configuration.php">Configuration</a> </p>
                <p> <a href="/%1$s/artefacts/managment.php">Artefacts</a> </p>
                <p> <a href="/%1$s/controllers/managment.php">Player-Controllers</a> </p>',
                $_SESSION['FOLDER']
                ); ?>
        </div>
    </div>
    <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
        <div class="config">
                <h1>List</h1>
                <?php echo sprintf( '
                <p> <a href="/%1$s/zones/managment_zones.php">Zone control list</a> </p>
                <p> <a href="/%1$s/zones/managment_bases.php">Attack on player base list</a> </p>
                <p> <a href="/%1$s/zones/managment_locations.php">Discovered location list</a> </p>',
                $_SESSION['FOLDER']
                ); ?>
        </div>
    </div>
    <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
        <?php
            // Allow for admin creation of a perfect agent
            require_once '../workers/newPerfectWorker.php';
        ?>
    </div>
</div>
</body>
