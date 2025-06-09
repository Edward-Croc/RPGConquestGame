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
    <div class="flex">
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
            <form action="/RPGConquestGame/base/admin.php" method="post">
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
                <h1>Links</h1>
                <p> <a href="/RPGConquestGame/base/configuration.php">Configuration Management</a> </p>
                <p> <a href="/RPGConquestGame/artefacts/managment.php">Artefacts Management</a> </p>
                <p> <a href="/RPGConquestGame/location/managment.php">Attack on player base Management</a> </p>
        </div>
    </div>
</div>
</body>
