<?php
require_once '../base/basePHP.php'; // Set up $pdo and session
$pageName = 'admin_workers';

$workerIds = [];
$workerIds = $gameReady->query("SELECT id FROM workers ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

$workers = getWorkers($gameReady, $workerIds);

$powerDisciplineArray = getPowersByType($gameReady,'3', NULL, false);
# echo "powerDisciplineArray : ". var_export($powerDisciplineArray, true)."<br/>";
$powerTransformationArray = getPowersByType($gameReady,'4', null, false);
# echo "powerTransformationArray : ". var_export($powerTransformationArray, true)."<br/>";

require_once '../base/baseHTML.php';
?>
<div class='managment'>
    <h1>Liste des Agents</h1>
    <table border="1" cellpadding="5">
        <tr>
            <th>ID / CID</th>
            <th>Nom Prénom (Stat.)</th>
            <th>Zone</th>
            <th>Alive/Active/Trace</th>
            <th>Action</th>
        </tr>
    <?php 
    # echo var_export($workers, true);
    foreach ($workers as $worker){
        $workerHtml = sprintf('<tr>
                <td>%1$s / %11$s </td>
                <td>%2$s (%4$s)</td>
                <td>%3$s</td>
                <td>%12$s</td>
                <td>
                    <span style="cursor: pointer; color: #4936f4; text-decoration: underline;" onclick="toggleDisciplines(%1$s)">Disciplines</span>
                    <div id="disciplines-%1$s" style="display: none; margin-top: 10px;">
                        %5$s <br />
                        %6$s <br />
                        %7$s
                    </div>
                </td>
                <td>
                    <span style="cursor: pointer; color: #4936f4; text-decoration: underline;" onclick="toggleActions(%1$s)">Actions</span>
                    <div id="actions-%1$s" style="display: none; margin-top: 10px;">
                        <form action="/%10$s/workers/action.php" method="GET">
                            <input type="hidden" name="worker_id" value="%1$s">
                            %8$s <input type="submit" name="teach_discipline" value="Enseigner" class="button is-info"> <br />
                            %9$s <input type="submit" name="transform" value="Equiper" class="button is-info">
                        </form>
                    </div>
                </td>
            </tr>',
            $worker['id'],
            $worker['firstname']." ".$worker['lastname'],
            $worker['zone_name'],
            sprintf('<strong>%s, %s/%s</strong>', $worker['total_enquete'], $worker['total_attack'], $worker['total_defence']),
            $worker['powers']['Metier']['texte'] . ' ' . $worker['powers']['Hobby']['texte'],
            $worker['powers']['Discipline']['texte'] ?? '',
            $worker['powers']['Transformation']['texte'] ?? '',
            showDisciplineSelect($gameReady, $powerDisciplineArray, true),
            showTransformationSelect($gameReady, $powerTransformationArray, true),
            $_SESSION['FOLDER'],
            $worker['controller_id'],
            $worker['is_alive']."/".$worker['is_active']."/".$worker['is_trace']
        );
        echo $workerHtml;
    }  ?>
</table>
</div>