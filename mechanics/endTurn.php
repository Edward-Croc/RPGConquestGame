<?php

$pageName = 'End Turn';

require_once '../base/basePHP.php';

// Privileged-only: EOT triggers state mutation (mysqldump, mechanic steps) so
// non-gm sessions must not reach it.
if (empty($_SESSION['is_privileged'])) {
    game_error_log(
        'endTurn_page',
        'Non-privileged EOT bypass attempt',
        ['user_id' => $_SESSION['user_id'] ?? null],
        'warning'
    );
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

// EndTurn is a one shot mutation and must have a token, we burn the token on use.
$submittedEndTurnToken = is_string($_POST['end_turn_token'] ?? null) ? $_POST['end_turn_token'] : '';
$expectedEndTurnToken = $_SESSION['end_turn_token'] ?? '';
$endTurnAllowed = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && $submittedEndTurnToken !== ''
    && $expectedEndTurnToken !== ''
    && hash_equals($expectedEndTurnToken, $submittedEndTurnToken);
if ($endTurnAllowed) {
    unset($_SESSION['end_turn_token']);
}

require_once '../base/baseHTML.php';

if (!$endTurnAllowed) {
    game_error_log(
        'endTurn_page',
        'End of turn refused, missing or stale one-shot token',
        ['method' => $_SERVER['REQUEST_METHOD'] ?? ''],
        'warning'
    );
    echo '<h2>Fin de tour non déclenchée</h2>';
    echo '<p>Cette page ne déclenche jamais de fin de tour. Utilisez le bouton de la barre latérale depuis une autre page.</p>';
    exit();
}

// A fresh end of turn is claimed atomically on the turn the form was rendered at: of two simultaneous triggers only one wins.
$seenTurn = is_string($_POST['turn_seen'] ?? null) && ctype_digit($_POST['turn_seen']) ? (int) $_POST['turn_seen'] : null;
$claimRefused = $seenTurn === null || $seenTurn !== (int) $mechanics['turncounter'];
// The form carries the step it was rendered at: a step moved by another session since the render is a late second trigger.
$seenStep = is_string($_POST['end_step_seen'] ?? null) ? $_POST['end_step_seen'] : null;
if ($seenStep === null || $seenStep !== (string) ($mechanics['end_step'] ?? '')) {
    $claimRefused = true;
}
// An auto-fire only proceeds while the timer still holds the deadline its tab rendered with.
if (!$claimRefused && isset($_POST['auto_deadline'])) {
    $autoDeadline = is_string($_POST['auto_deadline']) && ctype_digit($_POST['auto_deadline']) ? (int) $_POST['auto_deadline'] : null;
    $currentTimer = getTurnTimerState($mechanics);
    if ($autoDeadline === null || $currentTimer === null || $currentTimer['deadline'] === null || $currentTimer['deadline'] !== $autoDeadline) {
        $claimRefused = true;
    }
}
if (!$claimRefused && ($mechanics['end_step'] ?? '') === '') {
    try {
        $claimStmt = $gameReady->prepare(
            "UPDATE {$_SESSION['GAME_PREFIX']}mechanics SET end_step = 'claimed' WHERE id = :id AND turncounter = :seen AND (end_step IS NULL OR end_step = '')"
        );
        $claimStmt->bindValue(':id', (int) $mechanics['id'], PDO::PARAM_INT);
        $claimStmt->bindValue(':seen', $seenTurn, PDO::PARAM_INT);
        $claimStmt->execute();
        $claimRefused = $claimStmt->rowCount() !== 1;
        if (!$claimRefused) {
            $mechanics['end_step'] = 'claimed';
        }
    } catch (PDOException $e) {
        game_error_log('endTurn_page', 'End of turn claim failed', ['error' => $e->getMessage()], 'error');
        $claimRefused = true;
    }
}
if ($claimRefused) {
    game_error_log(
        'endTurn_page',
        'End of turn refused, stale turn or already claimed',
        ['turn_seen' => $seenTurn, 'turncounter' => $mechanics['turncounter']],
        'warning'
    );
    echo '<h2>Fin de tour non déclenchée</h2>';
    echo '<p>Fin de tour déjà en cours ou déjà effectuée, rechargez la page.</p>';
    exit();
}

// $GLOBALS['DEBUG_LOG_SECTIONS'][] = 'endTurn_page';  // uncomment to log DEBUG events from this page

// A resume after the turn increment still archives under the turn being ended.
$archivedTurnNumber = (int) $mechanics['turncounter'] - (($mechanics['end_step'] ?? '') === 'updateTurnCounter' ? 1 : 0);
// Registered after baseHTML so it runs after the footer : the archive holds the page as sent.
register_shutdown_function(function () use ($archivedTurnNumber) {
    saveTurnReport((string) ob_get_contents(), $archivedTurnNumber);
});

$backupDir = __DIR__ . '/../var/backups';
if (is_dir($backupDir) && is_writable($backupDir)) {
    exportBDD(true);
} else {
    game_error_log('endTurn_page', 'Skipping auto-backup — var/backups not writable', ['backupDir' => $backupDir], 'warning');
}
$started = toggleMechanicsGamestate($gameReady, $mechanics, true);
if (!$started) {
    game_error_log('endTurn_page', 'toggleMechanicsGamestate failed to start the game', [], 'warning');
    exit();
}

echo sprintf(
    " <h2>  Starting END of Turn %s with end_step : %s</h2>",
    $archivedTurnNumber,
    $mechanics['end_step']
);

if (getConfig($gameReady, 'ressource_management') == 'TRUE') {
    if (in_array($mechanics['end_step'], [null, '', 'claimed'])) {
        $ressourcesResult = updateRessources($gameReady, $mechanics);
        if (!$ressourcesResult) {
            game_error_log('endTurn_page', 'updateRessources failed', [], 'warning');
            return false;
        }
        $beforeClaimGainResult = ressourceGainMechanic($gameReady, 'before_claim');
        if (!$beforeClaimGainResult) {
            game_error_log('endTurn_page', 'ressourceGainMechanic before_claim failed', [], 'warning');
            return false;
        }
        changeEndTurnState($gameReady, 'updateRessources', $mechanics);
        $mechanics['end_step'] = 'updateRessources';
    }
}

if (in_array($mechanics['end_step'], [null, '', 'claimed', 'calculateVals', 'updateRessources'])) {
    $valsResult = true;
    $valsResult = calculateVals($gameReady, $mechanics);
    if ($valsResult) {
        $prefix = $_SESSION['GAME_PREFIX'];
        $inactive_actions = "'".implode("','", INACTIVE_ACTIONS)."'";
        // Add calculated values to worker report
        $sql = sprintf(
            "SELECT w.id AS worker_id, wa.enquete_val AS enquete_val, wa.attack_val AS attack_val, wa.defence_val AS defence_val,
                    wa.action_choice, wa.action_params,
                    w.zone_id, z.name AS zone_name, z.claimer_controller_id,
                    c.lastname AS claimer_lastname,
                    cw.controller_id AS owner_controller_id
            FROM {$prefix}workers w
            JOIN {$prefix}worker_actions wa ON wa.worker_id = w.id AND turn_number = :turn_number
            JOIN {$prefix}zones z ON z.id = w.zone_id
            LEFT JOIN {$prefix}controllers c ON c.id = z.claimer_controller_id
            LEFT JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id AND cw.is_primary_controller = %s
            WHERE wa.action_choice NOT IN (%s)
            ",
            ($_SESSION['DBTYPE'] == 'postgres') ? 'true' : '1',
            $inactive_actions
        );
        $stmt = $gameReady->prepare($sql);
        $stmt->bindParam(':turn_number', $mechanics['turncounter'], PDO::PARAM_INT);
        $stmt->execute();
        $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $timeValue = strtolower((string) getConfig($gameReady, 'timeValue'));
        $timeDenominatorThis = strtolower((string) getConfig($gameReady, 'timeDenominatorThis'));

        foreach ($workers as $worker) {
            $investigation = $worker['enquete_val'];
            $attack = $worker['attack_val'];
            $defense = $worker['defence_val'];

            $workerExtraInfo = buildWorkerActionInfo(
                $gameReady,
                (string) $worker['action_choice'],
                $worker['action_params'] ?? null
            );
            $actionPhrase = buildWorkerZoneActionPhrase(
                $gameReady,
                (int) $worker['worker_id'],
                (string) $worker['action_choice'],
                $worker['action_params'] ?? null,
                'alive',
                $workerExtraInfo,
                (string) $worker['zone_name'],
                (int) $worker['zone_id'],
                (int) ($worker['owner_controller_id'] ?? 0),
                empty($worker['claimer_controller_id']) ? null : (int) $worker['claimer_controller_id'],
                $worker['claimer_lastname'] ?? null,
                true
            );

            // Format the life report string
            $reportAppendArray = [
                'life_report' => sprintf(
                    " <strong>%s %s</strong> j'ai <strong>%s</strong> en investigation et <strong>%s/%s</strong> en attaque/défense. <br/>%s",
                    ucfirst($timeDenominatorThis),
                    $timeValue,
                    $investigation,
                    $attack,
                    $defense,
                    $actionPhrase
                )
            ];

            // Update the report using your existing game context
            if (!updateWorkerAction($gameReady, $worker['worker_id'], $mechanics['turncounter'], null, $reportAppendArray)) {
                game_error_log('endTurn_page', 'updateWorkerAction failed for calculateValsReport', ['worker_id' => $worker['worker_id']], 'warning');
                return false;
            }
        }
        changeEndTurnState($gameReady, 'calculateValsReport', $mechanics);
        $mechanics['end_step'] = 'calculateValsReport';
    } else {
        game_error_log('endTurn_page', 'calculateVals failed', [], 'warning');
        return false;
    }
}

if ($mechanics['end_step'] == 'calculateValsReport') {
    // check attacks
    $attackResult = attackMechanic($gameReady, $mechanics);
    if (!$attackResult) {
        game_error_log('endTurn_page', 'attackMechanic failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'attackMechanic', $mechanics);
    $mechanics['end_step'] = 'attackMechanic';
}

if ($mechanics['end_step'] == 'attackMechanic') {
    // recalculate base defence
    $bdrResult = recalculateBaseDefence($gameReady);
    if (!$bdrResult) {
        game_error_log('endTurn_page', 'recalculateBaseDefence failed', [], 'warning');
        return false;
    }
    // recalculate zone defence (claimMode-aware single source of truth)
    $zdrResult = recalculateZoneDefence($gameReady, $mechanics);
    if (!$zdrResult) {
        game_error_log('endTurn_page', 'recalculateZoneDefence failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'recalculateBaseZoneDefence', $mechanics);
    $mechanics['end_step'] = 'recalculateBaseZoneDefence';
}

if ($mechanics['end_step'] == 'recalculateBaseZoneDefence') {
    require_once 'locationAttackMechanic.php';
    $locationAttackResult = locationAttackMechanic($gameReady, $mechanics['turncounter']);
    if (!$locationAttackResult) {
        game_error_log('endTurn_page', 'locationAttackMechanic failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'locationAttackMechanic', $mechanics);
    $mechanics['end_step'] = 'locationAttackMechanic';
}

if ($mechanics['end_step'] == 'locationAttackMechanic') {
    // check claiming territory
    $claimResult = claimMechanic($gameReady, $mechanics);
    if (!$claimResult) {
        game_error_log('endTurn_page', 'claimMechanic failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'claimMechanic', $mechanics);
    $mechanics['end_step'] = 'claimMechanic';
}

if ($mechanics['end_step'] == 'claimMechanic') {
    if (getConfig($gameReady, 'ressource_management') == 'TRUE') {
        $afterClaimGainResult = ressourceGainMechanic($gameReady, 'after_claim');
        if (!$afterClaimGainResult) {
            game_error_log('endTurn_page', 'ressourceGainMechanic after_claim failed', [], 'warning');
            return false;
        }
    }
    changeEndTurnState($gameReady, 'ressourceGainAfterClaim', $mechanics);
    $mechanics['end_step'] = 'ressourceGainAfterClaim';
}

if ($mechanics['end_step'] == 'ressourceGainAfterClaim') {
    // check investigations
    $investigateResult = investigateMechanic($gameReady, $mechanics);
    if (!$investigateResult) {
        game_error_log('endTurn_page', 'investigateMechanic failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'investigateMechanic', $mechanics);
    $mechanics['end_step'] = 'investigateMechanic';
}

if ($mechanics['end_step'] == 'investigateMechanic') {
    // check locations seach
    $locationsearchResult = locationSearchMechanic($gameReady, $mechanics);
    if (!$locationsearchResult) {
        game_error_log('endTurn_page', 'locationSearchMechanic failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'locationSearchMechanic', $mechanics);
    $mechanics['end_step'] = 'locationSearchMechanic';
}

// update turn counter
$turn = (int)$mechanics['turncounter'] + 1;
if ($mechanics['end_step'] == 'locationSearchMechanic') {
    // create new turn lines
    $turnLinesResult = createNewTurnLines($gameReady, $turn);
    if (!$turnLinesResult) {
        game_error_log('endTurn_page', 'createNewTurnLines failed', ['turn' => $turn], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'createNewTurnLines', $mechanics);
    $mechanics['end_step'] = 'createNewTurnLines';
}

if ($mechanics['end_step'] == 'createNewTurnLines') {
    $restartRecrutementCount = restartTurnRecrutementCount($gameReady);
    if (!$restartRecrutementCount) {
        game_error_log('endTurn_page', 'restartTurnRecrutementCount failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'restartTurnRecrutementCount', $mechanics);
    $mechanics['end_step'] = 'restartTurnRecrutementCount';
}

if ($mechanics['end_step'] == 'restartTurnRecrutementCount') {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        // The turn and its step move together, so a resume never increments twice.
        // Re-read so a Pause, Arrêter or Reprendre made during the resolution is kept.
        $freshMechanics = getMechanics($gameReady);
        $timerState = $freshMechanics === null ? null : getTurnTimerState($freshMechanics);
        $timerSet = '';
        $timerParams = [];
        if ($timerState !== null && ($timerState['deadline'] !== null || $timerState['remaining'] !== null)) {
            // The deadline of the new turn moves with the turn, a paused timer restarts paused with the full duration.
            $now = time();
            $nextDeadline = computeNextTurnDeadline($gameReady, $turn, $now);
            $timerParams = [
                ':deadline' => ($nextDeadline === null || $timerState['deadline'] === null) ? null : gmdate('Y-m-d H:i:s', $nextDeadline),
                ':remaining' => ($nextDeadline === null || $timerState['remaining'] === null) ? null : max(0, $nextDeadline - $now),
            ];
            $timerSet = ', turn_deadline = :deadline, turn_timer_remaining = :remaining';
        }
        $sql = "UPDATE {$prefix}mechanics set turncounter = :turncounter, end_step = 'updateTurnCounter'{$timerSet} WHERE id = :id";
        $stmt = $gameReady->prepare($sql);
        $stmt->execute([':turncounter' => $turn, ':id' => $mechanics['id']] + $timerParams);
    } catch (PDOException $e) {
        game_error_log('endTurn_page', 'UPDATE mechanics failed : ' . $e->getMessage(), ['turn' => $turn], 'error');
        return false;
    }
    $mechanics['turncounter'] = $turn;
    $mechanics['end_step'] = 'updateTurnCounter';
}

if ($mechanics['end_step'] == 'updateTurnCounter') {
    $prefix = $_SESSION['GAME_PREFIX'];
    $recruitCarryMode = (string) getConfig($gameReady, 'recruitCarryMode');
    $nbChoicesByType = [];
    foreach (['first_come', 'recrutement'] as $recruitType) {
        $tmpChoices = getConfig($gameReady, $recruitType . '_nb_choices');
        $nbChoicesByType[$recruitType] = empty($tmpChoices) ? 1 : intval($tmpChoices);
    }
    // Every controller in one transaction, so a resumed step redraws from a clean state.
    $candidatesResult = false;
    try {
        $gameReady->beginTransaction();
        // Locks the controller rows as workers/new.php does, so no page draws during this step.
        $controller_ids = $gameReady->query("SELECT id FROM {$prefix}controllers ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        $stmtDeleteType = $gameReady->prepare("DELETE FROM {$prefix}recruit_candidates WHERE controller_id = :controller_id AND recruit_type = :recruit_type");
        $stmtCandidates = $gameReady->prepare("SELECT id, is_kept FROM {$prefix}recruit_candidates WHERE controller_id = :controller_id AND recruit_type = 'recrutement' ORDER BY id");
        $stmtDeleteOthers = $gameReady->prepare("DELETE FROM {$prefix}recruit_candidates WHERE controller_id = :controller_id AND recruit_type = 'recrutement' AND id <> :kept_id");
        $stmtUnmark = $gameReady->prepare("UPDATE {$prefix}recruit_candidates SET is_kept = :is_kept WHERE id = :kept_id");
        $candidatesResult = true;
        foreach ($controller_ids as $controller_id) {
            // A controller whose draw fails keeps its previous candidates and the turn still closes, the admin redraws.
            $gameReady->exec('SAVEPOINT recruit_candidates_controller');
            $stmtDeleteType->bindValue(':controller_id', (int) $controller_id, PDO::PARAM_INT);
            $stmtDeleteType->bindValue(':recruit_type', 'first_come', PDO::PARAM_STR);
            $stmtDeleteType->execute();
            if (!topUpRecruitCandidates($gameReady, (int) $controller_id, 'first_come', $nbChoicesByType['first_come'])) {
                $gameReady->exec('ROLLBACK TO SAVEPOINT recruit_candidates_controller');
                game_error_log('endTurn_page', 'Candidate draw failed, previous candidates kept', ['controller_id' => $controller_id, 'recruit_type' => 'first_come'], 'error');
                continue;
            }

            $stmtCandidates->bindValue(':controller_id', (int) $controller_id, PDO::PARAM_INT);
            $stmtCandidates->execute();
            $stored_candidates = $stmtCandidates->fetchAll(PDO::FETCH_ASSOC);
            $storedIds = array_map('intval', array_column($stored_candidates, 'id'));
            $keptId = null;
            if (!empty($storedIds) && $recruitCarryMode === 'newest_agent') {
                $keptId = max($storedIds);
            } elseif (!empty($storedIds) && $recruitCarryMode === 'random') {
                $keptId = $storedIds[random_int(0, count($storedIds) - 1)];
            } elseif ($recruitCarryMode === 'chosen') {
                foreach ($stored_candidates as $stored_candidate) {
                    if (!empty($stored_candidate['is_kept'])) {
                        $keptId = (int) $stored_candidate['id'];
                    }
                }
            }

            if ($keptId === null) {
                $stmtDeleteType->bindValue(':controller_id', (int) $controller_id, PDO::PARAM_INT);
                $stmtDeleteType->bindValue(':recruit_type', 'recrutement', PDO::PARAM_STR);
                $stmtDeleteType->execute();
            } else {
                $stmtDeleteOthers->bindValue(':controller_id', (int) $controller_id, PDO::PARAM_INT);
                $stmtDeleteOthers->bindValue(':kept_id', $keptId, PDO::PARAM_INT);
                $stmtDeleteOthers->execute();
                $stmtUnmark->bindValue(':is_kept', false, PDO::PARAM_BOOL);
                $stmtUnmark->bindValue(':kept_id', $keptId, PDO::PARAM_INT);
                $stmtUnmark->execute();
            }
            $drawCount = max(0, $nbChoicesByType['recrutement'] - ($keptId === null ? 0 : 1));
            if ($drawCount > 0 && !topUpRecruitCandidates($gameReady, (int) $controller_id, 'recrutement', $drawCount)) {
                $gameReady->exec('ROLLBACK TO SAVEPOINT recruit_candidates_controller');
                game_error_log('endTurn_page', 'Candidate draw failed, previous candidates kept', ['controller_id' => $controller_id, 'recruit_type' => 'recrutement'], 'error');
            }
        }
        if ($candidatesResult) {
            changeEndTurnState($gameReady, '', $mechanics);
            $gameReady->commit();
        } else {
            $gameReady->rollBack();
        }
    } catch (PDOException $e) {
        if ($gameReady->inTransaction()) {
            $gameReady->rollBack();
        }
        game_error_log('endTurn_page', 'Generating recruitment candidates failed : ' . $e->getMessage(), ['recruitCarryMode' => $recruitCarryMode], 'error');
        return false;
    }
    if (!$candidatesResult) {
        game_error_log('endTurn_page', 'Recruitment candidates generation failed', [], 'warning');
        return false;
    }
    $mechanics['end_step'] = '';
}

echo sprintf("<h2> %s: %s </h2>", ucfirst(getConfig($gameReady, 'timeValue')), (int) $mechanics['turncounter']);
