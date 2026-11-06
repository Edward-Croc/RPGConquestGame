<?php
require_once '../base/basePHP.php'; // Set up $pdo and session

// Admin-only page: require privileged session
if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

$pageName = 'admin_recruit_candidates';

$prefix = $_SESSION['GAME_PREFIX'];

$validSorts = ['controller', 'recruit_type'];
$sort = in_array($_GET['sort'] ?? '', $validSorts, true) ? $_GET['sort'] : 'controller';

// Every POST reloads the page by a GET, the redraw keeps the candidate's id and kept flag.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $turn_number = $mechanics['turncounter'] ?? null;
    if (is_string($_POST['candidate_id'] ?? null) && ctype_digit($_POST['candidate_id']) && $turn_number !== null) {
        try {
            $stmt = $gameReady->prepare("SELECT controller_id, recruit_type FROM {$prefix}recruit_candidates WHERE id = :id");
            $stmt->execute([':id' => (int) $_POST['candidate_id']]);
            $candidate_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($candidate_row)) {
                game_error_log('management_candidates_page', 'Candidate to redraw not found', ['candidate_id' => $_POST['candidate_id']], 'warning');
            } else {
                $newWorker = generateNewWorker($gameReady, (int) $candidate_row['controller_id'], $candidate_row['recruit_type']);
                if ($newWorker === null) {
                    game_error_log('management_candidates_page', 'Candidate redraw failed', ['candidate_id' => $_POST['candidate_id']], 'error');
                } else {
                    $stmt = $gameReady->prepare("UPDATE {$prefix}recruit_candidates
                        SET firstname = :firstname, lastname = :lastname, origin_id = :origin_id,
                            power_hobby_id = :power_hobby_id, power_metier_id = :power_metier_id, turn_generated = :turn_generated
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':firstname' => $newWorker['firstname'],
                        ':lastname' => $newWorker['lastname'],
                        ':origin_id' => $newWorker['origin_id'],
                        ':power_hobby_id' => $newWorker['power_1']['link_power_type_id'] ?? null,
                        ':power_metier_id' => $newWorker['power_2']['link_power_type_id'] ?? null,
                        ':turn_generated' => (int) $turn_number,
                        ':id' => (int) $_POST['candidate_id'],
                    ]);
                }
            }
        } catch (PDOException $e) {
            game_error_log('management_candidates_page', 'Redrawing the candidate failed : ' . $e->getMessage(), ['candidate_id' => $_POST['candidate_id']], 'error');
        }
    }
    header(sprintf('Location: /%s/workers/management_candidates.php?sort=%s', $_SESSION['FOLDER'], $sort));
    exit();
}

$candidates = getRecruitCandidates($gameReady, null, null, true) ?: array();

// Sorted in PHP : ORDER BY on a name follows the DB collation, which differs per backend.
usort($candidates, function ($a, $b) use ($sort) {
    $byController = strcasecmp($a['controller_lastname'], $b['controller_lastname']);
    $byType = strcmp($a['recruit_type'], $b['recruit_type']);
    $cmp = $sort === 'recruit_type' ? ($byType ?: $byController) : ($byController ?: $byType);
    return $cmp ?: ((int) $a['id'] <=> (int) $b['id']);
});

require_once '../base/baseHTML.php';
?>
<div class='management'>
    <h1>Candidats au recrutement</h1>
    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th><a href="?sort=controller">Faction</a></th>
            <th><a href="?sort=recruit_type">Type</a></th>
            <th>Tour</th>
            <th>Candidat</th>
            <th>Origine</th>
            <th>Métier / Loisir</th>
            <th>Gardé</th>
            <th>Action</th>
        </tr>
<?php foreach ($candidates as $candidate): ?>
        <tr>
            <td><?= (int) $candidate['id'] ?></td>
            <td><?= htmlspecialchars($candidate['controller_lastname'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($candidate['recruit_type'], ENT_QUOTES) ?></td>
            <td><?= (int) $candidate['turn_generated'] ?></td>
            <td><?= htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($candidate['origin'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($candidate['power_metier_text'] ?? '', ENT_QUOTES) ?> <br /> <?= htmlspecialchars($candidate['power_hobby_text'] ?? '', ENT_QUOTES) ?></td>
            <td><?= !empty($candidate['is_kept']) ? 'oui' : '' ?></td>
            <td>
                <form method="POST">
                    <input type="hidden" name="candidate_id" value="<?= (int) $candidate['id'] ?>">
                    <input type="submit" name="regenerate" value="Régénérer" class="button is-info">
                </form>
            </td>
        </tr>
<?php endforeach; ?>
    </table>
</div>
