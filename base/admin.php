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

    if ( isset($_POST['exportBDD']) ) {
        // Export BDD to file.sql
        exportBDD($gameReady);
    }

    if ( isset($_POST['importBDD']) ) {
        echo "======= ".var_export($_FILES["bddFile"]["name"], true)." =======<br>";
        // Import BDD from file.sql
        importBDD($gameReady, $_FILES["bddFile"]);
    }
}

require_once '../base/baseHTML.php';

?>

<div class="content">
    <?php
    $adminRecentErrors = game_error_log_tail(2, $_SESSION['GAME_PREFIX'] ?? null, 'error');
    $adminBorderColor = empty($adminRecentErrors) ? '#27ae60' : '#c0392b';
    ?>
    <!-- Ligne 1 : Mechanics | Recent errors | BDD management -->
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
        <div class="config" style="border-left: 5px solid <?= $adminBorderColor ?>; padding-left: 0.75em;">
            <h1>Recent errors</h1>
            <p>prefix <code>[<?= htmlspecialchars($_SESSION['GAME_PREFIX'] ?? '') ?>]</code></p>
            <?php if (empty($adminRecentErrors)): ?>
                <p style="color: #27ae60;">Aucune erreur recente</p>
            <?php else: ?>
                <ul style="font-family: monospace; font-size: 12px; margin: 0.5em 0 0 0;">
                    <?php foreach ($adminRecentErrors as $line): ?>
                        <li style="color: #c0392b;"><?= htmlspecialchars($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p style="margin-top: 0.5em;"><a href="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/base/admin_logs.php">&rarr; Game errors log</a></p>
        </div>
        <div class="config">
                <h1>BDD management : </h1>
                <?php
                    // Add button to extract BDD to file.sql or .sql
                    echo sprintf('<p> <form action="/%s/base/admin.php" method="post">
                        <input type="hidden" name="exportBDD" />
                        <input type="submit" name="submitButton" value="Export BDD to file.sql" />
                    </form> </p>',
                    $_SESSION['FOLDER']
                    );
                    // Import BDD from file.sql
                    echo sprintf('<p> <form action="/%s/base/admin.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="importBDD" />
                        <input type="file" name="bddFile" id="bddFile" />
                        <input type="submit" name="submitButton" value="Import BDD from file.sql" />
                    </form> </p>',
                    $_SESSION['FOLDER']
                    );
                ?>
        </div>
    </div>
    <!-- Ligne 2 : Config Management | Management | List (avec Worker list) -->
    <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
        <div class="config">
            <h1>Config Management</h1>
            <?php echo sprintf( '<p> <a href="/%1$s/base/configuration.php">Configuration</a> </p>',
                $_SESSION['FOLDER']
            );
            echo sprintf('<form id="resetForm" action="/%s/base/admin.php" method="post">',  $_SESSION['FOLDER']); ?>
                <h2> FULL Reset : <br />
                    <select id="configSelect" name="config_name">
                        <optgroup label="Config via CSV">
                            <option  value='Japon1555CSV'> Shikoku (四国) 1555 </option>
                            <option  value='Vampire1966CSV'> Firenze Vampire 1966 </option>
                            <option  value='TestConfig'> TestConfig </option>
                        </optgroup>
                        <optgroup label="Anciennes config SQL">
                            <option  value='Vampire1966SQL'> Firenze Vampire 1966 </option>
                            <option  value='Japon1555SQL'> Shikoku (四国) 1555 </option>
                        </optgroup>
                    </select>
                    <input type="hidden" name="resetBDD" />
                    <input type="submit" name="submitButton" value="Submit" />
                </h2>
            </form>
        </div>
        <div class="config">
                <h1>Management</h1>
                <?php echo sprintf( '
                <p> <a href="/%1$s/controllers/management.php">Player-Controllers</a> </p>
                <p> <a href="/%1$s/artefacts/management.php">Artefacts</a> </p>
                <p> <a href="/%1$s/ressources/management.php">Ressources</a> </p>',
                $_SESSION['FOLDER']
                ); ?>
        </div>
        <div class="config">
                <h1>List</h1>
                <?php echo sprintf( '
                <p> <a href="/%1$s/zones/management_zones.php">Zone control list</a> </p>
                <p> <a href="/%1$s/zones/management_locations.php">Discovered location list</a> </p>
                <p> <a href="/%1$s/zones/management_bases.php">Attack on player base list</a> </p>
                <p> <a href="/%1$s/workers/management_workers.php">Worker list</a> </p>',
                $_SESSION['FOLDER']
                ); ?>
        </div>
    </div>
    <!-- Ligne 3 : Admin - Create Perfect Agent (pleine largeur) -->
    <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
        <?php
            // Allow for admin creation of a perfect agent
            require_once '../workers/newPerfectWorker.php';
        ?>
    </div>
</div>

<div id="confirmModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">Confirm Full Reset</p>
        </header>
        <section class="modal-card-body">
            <p>Are you sure you want to perform a <strong>full reset</strong> with the selected config?</p>
            <p>This is going to cause Mt.Fuji to explode and kill everyone.</p>
        </section>
        <footer class="modal-card-foot">
            <button id="confirmModalYes" class="button is-danger">Yes, reset</button>
            <button id="confirmModalNo" class="button">Cancel</button>
        </footer>
    </div>
</div>

<div id="waitModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">Creating the world...</p>
        </header>
        <section class="modal-card-body">
            <p id="waitModalMessage"></p>
            <progress class="progress is-primary" max="100">Loading</progress>
        </section>
    </div>
</div>

<script>

    const worldCreationMessages = [
        "Birthing Gaia out of Chaos...",
        "Hollowing Ymir's bones...",
        "Stirring the ocean with Izanagi (伊邪那岐神) and Izanami (伊邪那美神)'s spear...",
        "Some bearded dude is saying 'let there be light'...",
        "Spitting (let's keep it child-friendly) into Nu...",
        "Sorting through four suns...",
    ];

    function openConfirmModal() {
        document.getElementById('confirmModal').classList.add('is-active');
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('is-active');
    }

    function pickRandomWaitMessage() {
        const index = Math.floor(Math.random() * worldCreationMessages.length);
        document.getElementById('waitModalMessage').textContent = worldCreationMessages[index];
    }

    function openWaitModal() {
        pickRandomWaitMessage();
        document.getElementById('waitModal').classList.add('is-active');
    }

    function submitResetForm() {
        closeConfirmModal();
        openWaitModal();
        document.getElementById('resetForm').submit();
    }

    function initResetConfirmation() {

        document.getElementById('resetForm').addEventListener('submit', function (event) {
            event.preventDefault();
            openConfirmModal();
        });

        document.getElementById('confirmModalNo').addEventListener('click', closeConfirmModal);
        document.getElementById('confirmModalYes').addEventListener('click', submitResetForm);

        // Also close if clicking the dark background
        document.querySelector('#confirmModal .modal-background').addEventListener('click', closeConfirmModal);
    }

    document.addEventListener('DOMContentLoaded', initResetConfirmation);
</script>