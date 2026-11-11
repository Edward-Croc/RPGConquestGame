<?php

require_once '../base/basePHP.php';

$pageName = 'new';

if (isset($_SESSION['controller'])) {
    $controller_id = $_SESSION['controller']['id'];
}
if (isset($_GET['controller_id'])) {
    $controller_id = $_GET['controller_id'];
}

// Checked before any candidate is drawn : a stranger could otherwise draw a rival's candidates.
if (empty($_SESSION['logged_in']) || empty($controller_id)) {
    header(sprintf('Location: /%s/connection/loginForm.php', $_SESSION['FOLDER']));
    exit();
}
if (!empty($_SESSION['is_privileged']) && empty($_SESSION['controller']['id'])) {
    game_error_log('workers_new_page', 'Recruitment page refused to the organiser without a selected faction', ['controller_id' => $controller_id], 'warning');
    http_response_code(403);
    exit();
}
if (empty($_SESSION['is_privileged']) && (int) $controller_id !== (int) ($_SESSION['controller']['id'] ?? 0)) {
    game_error_log('workers_new_page', 'Recruitment page refused for another controller', ['controller_id' => $controller_id], 'warning');
    http_response_code(403);
    exit();
}

$controllerValues = getControllers($gameReady, null, $controller_id);

$buttonClicked = 'first_come';
$pageTitle = 'Recruter le premier venu';

if ($_SESSION['DEBUG'] == true) {
    echo '<p>turncounter: '. (int)$mechanics['turncounter']
        .'; turn_firstcome_workers: '. getConfig($gameReady, 'turn_firstcome_workers')
        .'; turn_recrutable_workers: '. getConfig($gameReady, 'turn_recrutable_workers')
        .'; start_workers :'. $controllerValues[0]['start_workers']
        .'; turn_recruited_workers :'. $controllerValues[0]['turn_recruited_workers']
        .'; turn_firstcome_workers :'. $controllerValues[0]['turn_firstcome_workers']
    .'</p>';
}

// The same checks as the buttons of the agents page : a recruitment needs a base, the first comer does not.
if (isset($_GET['recrutement'])) {
    $buttonClicked = 'recrutement';
    $pageTitle = "Recrutement d'un Agent";
    $recrutment_allowed = canStartRecrutement($gameReady, $controller_id, (int)$mechanics['turncounter']);
} else {
    $recrutment_allowed = canStartFirstCome($gameReady, $controller_id);
}
if (!$recrutment_allowed) {
    require_once '../base/baseHTML.php';
    echo " <div> <h2> $pageTitle </h2> </div>
        <div >
                Le recrutement n'est pas permis !
            </div>";
    return 0;
}

$prefix = $_SESSION['GAME_PREFIX'];
$markKeptCandidate = $buttonClicked == 'recrutement' && getConfig($gameReady, 'recruitCarryMode') === 'chosen';

// Marks the one candidate kept over the end of turn, then reloads the page by a GET.
if ($markKeptCandidate && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && is_string($_POST['keep_candidate_id'] ?? null) && ctype_digit($_POST['keep_candidate_id'])
) {
    try {
        $gameReady->beginTransaction();
        $stmt = $gameReady->prepare("UPDATE {$prefix}recruit_candidates SET is_kept = :is_kept WHERE controller_id = :controller_id AND recruit_type = 'recrutement'");
        $stmt->bindValue(':is_kept', false, PDO::PARAM_BOOL);
        $stmt->bindValue(':controller_id', (int) $controller_id, PDO::PARAM_INT);
        $stmt->execute();
        $stmt = $gameReady->prepare("UPDATE {$prefix}recruit_candidates SET is_kept = :is_kept WHERE id = :id AND controller_id = :controller_id AND recruit_type = 'recrutement'");
        $stmt->bindValue(':is_kept', true, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', (int) $_POST['keep_candidate_id'], PDO::PARAM_INT);
        $stmt->bindValue(':controller_id', (int) $controller_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 1) {
            $gameReady->commit();
        } else {
            $gameReady->rollBack();
            game_error_log('workers_new_page', 'Kept candidate refused, not a recruitment candidate of this controller', ['controller_id' => $controller_id, 'keep_candidate_id' => $_POST['keep_candidate_id']], 'warning');
        }
    } catch (PDOException $e) {
        if ($gameReady->inTransaction()) {
            $gameReady->rollBack();
        }
        game_error_log('workers_new_page', 'Marking the kept candidate failed : ' . $e->getMessage(), ['controller_id' => $controller_id], 'error');
    }
    header(sprintf('Location: /%s/workers/new.php?recrutement=true&controller_id=%d', $_SESSION['FOLDER'], (int) $controller_id));
    exit();
}

if ($_SESSION['DEBUG'] == true) {
    echo "buttonClicked : $buttonClicked";
    echo"<br />";
    echo"<br />";
}

$nbChoices = 1;
$tmpChoices = getConfig($gameReady, $buttonClicked.'_nb_choices');
if (!empty($tmpChoices)) {
    $nbChoices = intval($tmpChoices);
}

if ($_SESSION['DEBUG'] == true) {
    echo "nbChoices : $nbChoices";
    echo"<br />";
    echo"<br />";
}

// The controller row lock serialises two players of one faction drawing the same set.
try {
    $gameReady->beginTransaction();
    $stmt = $gameReady->prepare("SELECT turn_firstcome_workers, turn_recruited_workers FROM {$prefix}controllers WHERE id = :controller_id FOR UPDATE");
    $stmt->execute([':controller_id' => $controller_id]);
    $controller_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $gameReady->prepare("SELECT COUNT(*) FROM {$prefix}recruit_candidates WHERE controller_id = :controller_id AND recruit_type = :recruit_type");
    $stmt->execute([':controller_id' => $controller_id, ':recruit_type' => $buttonClicked]);
    $stored_count = (int) $stmt->fetchColumn();
    $drawCount = 0;
    if (is_array($controller_row)) {
        $recruited_this_turn = (int) $controller_row[$buttonClicked == 'first_come' ? 'turn_firstcome_workers' : 'turn_recruited_workers'];
        $missingCount = max(0, $nbChoices - $stored_count - $recruited_this_turn);
        if ($stored_count == 0 || getConfig($gameReady, 'recruitMissingCandidates') === 'generate') {
            $drawCount = $missingCount;
        }
    }
    if ($drawCount > 0 && !topUpRecruitCandidates($gameReady, (int) $controller_id, $buttonClicked, $drawCount)) {
        $gameReady->rollBack();
    } else {
        $gameReady->commit();
    }
} catch (PDOException $e) {
    if ($gameReady->inTransaction()) {
        $gameReady->rollBack();
    }
    game_error_log('workers_new_page', 'Drawing recruitment candidates failed : ' . $e->getMessage(), ['controller_id' => $controller_id, 'recruit_type' => $buttonClicked], 'error');
}

$candidates = getRecruitCandidates($gameReady, (int) $controller_id, $buttonClicked) ?: array();

$powerDisciplineArray = getPowersByType($gameReady, '3', $controller_id, true);
$zonesArray = getZonesArray($gameReady);
if ($_SESSION['DEBUG'] == true) {
    echo "powerDisciplineArray: ".var_export($powerDisciplineArray, true)."<br /><br />";
    echo "zonesArray: ".var_export($zonesArray, true). "<br /><br />";
}

require_once '../base/baseHTML.php';

echo "
    <div> <h2> $pageTitle </h2> </div>
    <div >
";

foreach ($candidates as $candidate) {
    if ($_SESSION['DEBUG'] == true) {
        echo "candidate: ".var_export($candidate, true)."<br /><br />";
    }
    $proposalValues = [
        $candidate['firstname'],
        $candidate['lastname'],
        $candidate['origin'],
        $candidate['power_hobby_text'] ?? '',
        $candidate['power_metier_text'] ?? '',
        $candidate['origin_id'],
        $candidate['power_hobby_id'],
        $candidate['power_metier_id'],
        $controller_id,
        $_SESSION['FOLDER'],
    ];
    // The job and hobby sentence numbers its values like the form around it.
    $jobHobbyText = formatConfigText('textRecrutementJobHobby', getConfig($gameReady, 'textRecrutementJobHobby'), ...$proposalValues);
    echo vsprintf(
        '
    <div class="workers">
    <form action="/%10$s/workers/action.php" method="GET">
        <p>
        <strong>%1$s %2$s</strong> de %3$s <br />
        %11$s <br />
        <!-- Hidden inputs -->
        <input type="hidden" name="creation" value="true">
        <input type="hidden" name="firstname" value="%13$s">
        <input type="hidden" name="lastname" value="%14$s">
        <input type="hidden" name="origin_id" value="%6$s">
        <input type="hidden" name="controller_id" value="%9$s">
        <input type="hidden" name="candidate_id" value="%12$s">
    ',
        [...$proposalValues, $jobHobbyText, $candidate['id'], htmlspecialchars($candidate['firstname']), htmlspecialchars($candidate['lastname'])]
    );

    // Check Transformation Conditions
    $powerTransformationArray = array();
    $recrutement_transformation_json = getConfig($gameReady, 'recrutement_transformation');
    $recrutement_transformation_array = array();
    if (!empty($recrutement_transformation_json)) {
        $recrutement_transformation_array = decodeJson($recrutement_transformation_json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo __FUNCTION__."():JSON decoding error: " . json_last_error_msg() . "<br />";
            $recrutement_transformation_array = array();
        }
    }
    if ($_SESSION['DEBUG_TRANSFORM']) {
        echo sprintf("recrutement_transformation_array :%s  <br>", var_export($recrutement_transformation_array, true));
    }
    if (!empty($recrutement_transformation_array['action']) && $recrutement_transformation_array['action'] == 'check') {
        // get transformations
        $powerTransformationArray = getPowersByType($gameReady, '4', null, false);
        if ($_SESSION['DEBUG_TRANSFORM']) {
            echo sprintf("powerTransformationArray: %s <br />", var_export($powerTransformationArray, true));
        }
        $powerTransformationArray = cleanPowerListFromJsonConditions($gameReady, $powerTransformationArray, $controller_id, null, $mechanics['turncounter'], 'on_recrutment');
        if ($_SESSION['DEBUG_TRANSFORM']) {
            echo sprintf("powerTransformationArray: %s <br/>", var_export($powerTransformationArray, true));
        }
    }

    $html = sprintf(
        '
        <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
            %s
        </div>
        %s
        <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
            %s 
            <div class="control">
                <input type="submit" name="chosir" value="Recruter et Affecter" class="button is-link" />
            </div>
        </div>
    </p></form>',
        showDisciplineSelect($gameReady, $powerDisciplineArray),
        (! empty($powerTransformationArray)) ? sprintf(
            '<div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">%s</div>',
            showTransformationSelect($gameReady, $powerTransformationArray, true)
        ) : '',
        showZoneSelect($gameReady, $zonesArray, null, false, false, true)
    );
    echo $html;

    if ($markKeptCandidate && !empty($candidate['is_kept'])) {
        echo '<p><strong>Gardé pour le prochain tour</strong></p>';
    } elseif ($markKeptCandidate) {
        echo sprintf(
            '
    <form method="POST">
        <input type="hidden" name="keep_candidate_id" value="%s">
        <input type="submit" name="keep_candidate" value="Garder ce candidat" class="button is-info">
    </form>',
            (int) $candidate['id']
        );
    }
    echo '</div>';
}
echo "</div>";
