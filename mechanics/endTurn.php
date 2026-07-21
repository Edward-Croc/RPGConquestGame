<?php
$pageName = 'End Turn';

require_once '../base/basePHP.php';

require_once '../base/baseHTML.php';

// $GLOBALS['DEBUG_LOG_SECTIONS'][] = 'endTurn_page';  // uncomment to log DEBUG events from this page

$started = toggleMechanicsGamestate($gameReady, $mechanics, true);
if (!$started) {
    game_error_log('endTurn_page', 'toggleMechanicsGamestate failed to start the game', [], 'warning');
    exit();
}

echo sprintf(" <h2>  Starting END of Turn %s with end_step : %s</h2>",
    $mechanics['turncounter'],
    $mechanics['end_step']
);

if (getConfig($gameReady, 'ressource_management') == 'TRUE') {
    if (in_array($mechanics['end_step'], [null, ''])) {
        $ressourcesResult = updateRessources($gameReady, $mechanics);
        if ( !$ressourcesResult){
            game_error_log('endTurn_page', 'updateRessources failed', [], 'warning');
            return false;
        }
        $beforeClaimGainResult = ressourceGainMechanic($gameReady, 'before_claim');
        if ( !$beforeClaimGainResult){
            game_error_log('endTurn_page', 'ressourceGainMechanic before_claim failed', [], 'warning');
            return false;
        }
        changeEndTurnState($gameReady, 'updateRessources', $mechanics);
        $mechanics['end_step'] = 'updateRessources';
    }
}

if (in_array($mechanics['end_step'], [null, '', 'calculateVals', 'updateRessources'])) {
    $valsResult = true;
    $valsResult = calculateVals($gameReady, $mechanics);
    if ($valsResult) {
        $prefix = $_SESSION['GAME_PREFIX'];
        $inactive_actions = "'".implode("','", INACTIVE_ACTIONS)."'";
        // Add calculated values to worker report
        $sql = sprintf("SELECT w.id AS worker_id, wa.enquete_val AS enquete_val, wa.attack_val AS attack_val, wa.defence_val AS defence_val
            FROM {$prefix}workers w
            JOIN {$prefix}worker_actions wa ON wa.worker_id = w.id AND turn_number = :turn_number
            WHERE wa.action_choice NOT IN (%s)
            ", $inactive_actions
        );
        $stmt = $gameReady->prepare($sql);
        $stmt->bindParam(':turn_number', $mechanics['turncounter'], PDO::PARAM_INT);
        $stmt->execute();
        $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $timeValue = strtolower(getConfig($gameReady, 'timeValue'));
        $timeDenominatorThis = strtolower(getConfig($gameReady, 'timeDenominatorThis'));

        foreach ($workers as $worker) {
            $investigation = $worker['enquete_val'];
            $attack = $worker['attack_val'];
            $defense = $worker['defence_val'];

            // Format the life report string
            $reportAppendArray = [
                'life_report' => sprintf(
                    "<strong>%s %s</strong> j'ai <strong>%s</strong> en investigation et <strong>%s/%s</strong> en attaque/défense.",
                ucfirst($timeDenominatorThis), $timeValue, $investigation, $attack, $defense
                )
            ];

            // Update the report using your existing game context
            if ( !updateWorkerAction($gameReady, $worker['worker_id'], $mechanics['turncounter'], null, $reportAppendArray)){
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

// set Controlled by IA actions
// $IAResult = aiMechanic($gameReady);

if ($mechanics['end_step'] == 'calculateValsReport') {
    // check attacks
    $attackResult = attackMechanic($gameReady, $mechanics);
    if ( !$attackResult){
        game_error_log('endTurn_page', 'attackMechanic failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'attackMechanic', $mechanics);
    $mechanics['end_step'] = 'attackMechanic';
}

if ($mechanics['end_step'] == 'attackMechanic') {
    // recalculate base defence
    $bdrResult = recalculateBaseDefence($gameReady);
    if ( !$bdrResult){
        game_error_log('endTurn_page', 'recalculateBaseDefence failed', [], 'warning');
        return false;
    }
    // recalculate zone defence (claimMode-aware single source of truth)
    $zdrResult = recalculateZoneDefence($gameReady, $mechanics);
    if ( !$zdrResult){
        game_error_log('endTurn_page', 'recalculateZoneDefence failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'recalculateBaseZoneDefence', $mechanics);
    $mechanics['end_step'] = 'recalculateBaseZoneDefence';
}

if ($mechanics['end_step'] == 'recalculateBaseZoneDefence') {
    require_once 'locationAttackMechanic.php';
    $locationAttackResult = locationAttackMechanic($gameReady, $mechanics['turncounter']);
    if ( !$locationAttackResult){
        game_error_log('endTurn_page', 'locationAttackMechanic failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'locationAttackMechanic', $mechanics);
    $mechanics['end_step'] = 'locationAttackMechanic';
}

if ($mechanics['end_step'] == 'locationAttackMechanic') {
    // check claiming territory
    $claimResult = claimMechanic($gameReady, $mechanics);
    if ( !$claimResult){
        game_error_log('endTurn_page', 'claimMechanic failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'claimMechanic', $mechanics);
    $mechanics['end_step'] = 'claimMechanic';
}

if ($mechanics['end_step'] == 'claimMechanic') {
    if (getConfig($gameReady, 'ressource_management') == 'TRUE') {
        $afterClaimGainResult = ressourceGainMechanic($gameReady, 'after_claim');
        if ( !$afterClaimGainResult){
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
    if ( !$investigateResult){
        game_error_log('endTurn_page', 'investigateMechanic failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'investigateMechanic', $mechanics);
    $mechanics['end_step'] = 'investigateMechanic';
}

if ($mechanics['end_step'] == 'investigateMechanic') {
    // check locations seach
    $locationsearchResult = locationSearchMechanic($gameReady, $mechanics);
    if ( !$locationsearchResult){
        game_error_log('endTurn_page', 'locationSearchMechanic failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'locationSearchMechanic', $mechanics);
    $mechanics['end_step'] = 'locationSearchMechanic';
}

// update turn counter
$turn = (INT)$mechanics['turncounter'] + 1;
if ($mechanics['end_step'] == 'locationSearchMechanic') {
    // create new turn lines
    $turnLinesResult = createNewTurnLines($gameReady, $turn);
    if ( !$turnLinesResult){
        game_error_log('endTurn_page', 'createNewTurnLines failed', ['turn' => $turn], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'createNewTurnLines', $mechanics);
    $mechanics['end_step'] = 'createNewTurnLines';
}

if ($mechanics['end_step'] == 'createNewTurnLines') {
    $restartRecrutementCount = restartTurnRecrutementCount($gameReady);
    if ( !$restartRecrutementCount){
        game_error_log('endTurn_page', 'restartTurnRecrutementCount failed', [], 'warning');
        return false;
    }
    changeEndTurnState($gameReady, 'restartTurnRecrutementCount', $mechanics);
    $mechanics['end_step'] = 'restartTurnRecrutementCount';
}

if ($mechanics['end_step'] == 'restartTurnRecrutementCount') {
    $prefix = $_SESSION['GAME_PREFIX'];
    try{
        // SQL query to select username from the players table
        $sql = "UPDATE {$prefix}mechanics set turncounter = :turncounter, end_step = '' WHERE id = :id";
        // Prepare and execute SQL query
        $stmt = $gameReady->prepare($sql);
        $stmt->execute([':turncounter' => $turn, ':id' => $mechanics['id'] ]);
    } catch (PDOException $e) {
        game_error_log('endTurn_page', 'UPDATE mechanics failed', ['error' => $e->getMessage()]);
    }
}

echo sprintf("<h2> %s: %s </h2>", ucfirst(getConfig($gameReady, 'timeValue')), $turn);