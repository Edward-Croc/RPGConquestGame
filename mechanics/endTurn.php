<?php
$pageName = 'End Turn';

require_once '../base/basePHP.php';

require_once '../base/baseHTML.php';

$started = toggleMechanicsGamestate($gameReady, $mechanics, true);
if (!$started) {
    echo __FUNCTION__."(): Failed to start the game.";
    exit();
}

echo sprintf(" Starting END of Turn %s with end_step : %s<br />",
    $mechanics['turncounter'],
    $mechanics['end_step']
);

if (getConfig($gameReady, 'ressource_management') == 'TRUE') {
    if (in_array($mechanics['end_step'], [null, ''])) {
        $ressourcesResult = updateRessources($gameReady, $mechanics);
        if ( !$ressourcesResult){
            echo __FUNCTION__."(): Failed to update ressources: updateRessources <br />";
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
        // Add calculated values to worker report
        $sql = "SELECT w.id AS worker_id, wa.enquete_val AS enquete_val, wa.attack_val AS attack_val, wa.defence_val AS defence_val
            FROM workers w
            JOIN worker_actions wa ON wa.worker_id = w.id AND turn_number = :turn_number
            WHERE is_alive = True AND is_active = True";
        $stmt = $gameReady->prepare($sql);
        $stmt->execute([':turn_number' => $mechanics['turncounter']]);
        $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $timeValue = strtolower(getConfig($gameReady, 'timeValue'));
        $timeDenominatorThis = strtolower(getConfig($gameReady, 'timeDenominatorThis'));

        foreach ($workers as $worker) {
            // Example: retrieve stats from a helper function or inline logic
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
                echo __FUNCTION__."(): Failed to update worker action: calculateValsReport <br />";
                return false;
            }
        }
        changeEndTurnState($gameReady, 'calculateValsReport', $mechanics);
        $mechanics['end_step'] = 'calculateValsReport';
    } else {
        echo __FUNCTION__."(): endTurn actions Failed: calculateVals <br />";
        return false;
    }
}
if ($mechanics['end_step'] == 'calculateValsReport') {
    // recalculate base defence
    $bdrResult = recalculateBaseDefence($gameReady);
    if ( !$bdrResult){
        echo __FUNCTION__."(): Failed to recalculate base defence: recalculateBaseDefence <br />";
        return false;
    }
    changeEndTurnState($gameReady, 'recalculateBaseDefence', $mechanics);
    $mechanics['end_step'] = 'recalculateBaseDefence';
}

// set Controlled by IA actions
// $IAResult = aiMechanic($gameReady);
if ($mechanics['end_step'] == 'recalculateBaseDefence') {
    // check attacks
    $attackResult = attackMechanic($gameReady, $mechanics);
    if ( !$attackResult){
        echo __FUNCTION__."(): Failed to attack: attackMechanic <br />";
        return false;
    }
    changeEndTurnState($gameReady, 'attackMechanic', $mechanics);
    $mechanics['end_step'] = 'attackMechanic';
}
if ($mechanics['end_step'] == 'attackMechanic') {
    // check investigations
    $investigateResult = investigateMechanic($gameReady, $mechanics);
    if ( !$investigateResult){
        echo __FUNCTION__."(): Failed to investigate: investigateMechanic <br />";
        return false;
    }
    changeEndTurnState($gameReady, 'investigateMechanic', $mechanics);
    $mechanics['end_step'] = 'investigateMechanic';
}
if ($mechanics['end_step'] == 'investigateMechanic') {
    // check locations seach
    $locationsearchResult = locationSearchMechanic($gameReady, $mechanics);
    if ( !$locationsearchResult){
        echo __FUNCTION__."(): Failed to location search: locationSearchMechanic <br />";
        return false;
    }
    changeEndTurnState($gameReady, 'locationSearchMechanic', $mechanics);
    $mechanics['end_step'] = 'locationSearchMechanic';
}
if ($mechanics['end_step'] == 'locationSearchMechanic') {
    // check claiming territory
    $claimResult = claimMechanic($gameReady, $mechanics);
    if ( !$claimResult){
        echo __FUNCTION__."(): Failed to claim: claimMechanic <br />";
        return false;
    }
    changeEndTurnState($gameReady, 'claimMechanic', $mechanics);
    $mechanics['end_step'] = 'claimMechanic';
}
// update turn counter
$turn = (INT)$mechanics['turncounter'] + 1;
if ($mechanics['end_step'] == 'claimMechanic') {
    // create new turn lines
    $turnLinesResult = createNewTurnLines($gameReady, $turn);
    if ( !$turnLinesResult){
        echo __FUNCTION__."(): Failed to create new turn lines: createNewTurnLines <br />";
        return false;
    }
    changeEndTurnState($gameReady, 'createNewTurnLines', $mechanics);
    $mechanics['end_step'] = 'createNewTurnLines';
}
if ($mechanics['end_step'] == 'createNewTurnLines') {
    $restartRecrutementCount = restartTurnRecrutementCount($gameReady);
    if ( !$restartRecrutementCount){
        echo __FUNCTION__."(): Failed to restart turn recrutement count: restartTurnRecrutementCount <br />";
        return false;
    }
    changeEndTurnState($gameReady, 'restartTurnRecrutementCount', $mechanics);
    $mechanics['end_step'] = 'restartTurnRecrutementCount';
}
if ($mechanics['end_step'] == 'restartTurnRecrutementCount') {
    try{
        // SQL query to select username from the players table
        $sql = "UPDATE mechanics set turncounter = :turncounter, end_step = '' WHERE id = :id";
        // Prepare and execute SQL query
        $stmt = $gameReady->prepare($sql);
        $stmt->execute([':turncounter' => $turn, ':id' => $mechanics['id'] ]);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE mechanics Failed: " . $e->getMessage()."<br />";
    }
    echo ucfirst(getConfig($gameReady, 'timeValue')).": $turn";
}