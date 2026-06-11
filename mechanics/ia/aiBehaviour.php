<?php

require_once __DIR__ . '/aiBase.php';
require_once __DIR__ . '/aiRecruit.php';
require_once __DIR__ . '/aiWorkers.php';
require_once __DIR__ . '/aiZones.php';
require_once __DIR__ . '/aiPowers.php';
require_once __DIR__ . '/aiStrike.php';
require_once __DIR__ . '/aiBudget.php';
require_once __DIR__ . '/aiTargets.php';
require_once __DIR__ . '/aiDefence.php';
require_once __DIR__ . '/aiTerritory.php';
require_once __DIR__ . '/aiIntel.php';

// Placement is implicit via strike / territory / intel steps; aiDistributeWorkers is skipped.

function aiPassiveBehaviour($pdo, $c, $turn_number) {
    $base_zone = aiBaseZone($pdo, $c['id']);
    if ($base_zone === null) {
        aiRelocateBase($pdo, $c);
        $base_zone = aiBaseZone($pdo, $c['id']);
    }
    if ($base_zone === null) return;
    aiRecruitForState($pdo, $c, $base_zone, $turn_number);

    $budget = aiBudget($pdo, $c, $turn_number);
    $ranked = aiRankTargets($pdo, $c);

    $defResult = aiDefensiveConsolidate($pdo, $c, $turn_number, $budget);
    $skipWorkers = array_merge($defResult['moved'], $defResult['hidden']);

    $available = aiAvailableWorkerIds($pdo, $c, $turn_number, $skipWorkers);
    $terrResult = aiAllocateClaimAndContest($pdo, $c, $turn_number, $available);
    $skipWorkers = array_merge($skipWorkers, $terrResult['contest_actions']);

    $available = aiAvailableWorkerIds($pdo, $c, $turn_number, $skipWorkers);
    $intelResult = aiPreStrikeIntel($pdo, $c, $turn_number, $available, []);
    $skipWorkers = array_merge($skipWorkers, $intelResult);

    aiSetWorkerActionsForState($pdo, $c, 'investigate', $turn_number, $skipWorkers);
    aiEquipPowers($pdo, $c);
}

function aiSearchingBehaviour($pdo, $c, $turn_number) {
    $base_zone = aiBaseZone($pdo, $c['id']);
    if ($base_zone === null) {
        aiRelocateBase($pdo, $c);
        $base_zone = aiBaseZone($pdo, $c['id']);
    }
    if ($base_zone !== null) {
        aiRecruitForState($pdo, $c, $base_zone, $turn_number);
    }

    $budget = aiBudget($pdo, $c, $turn_number);
    $ranked = aiRankTargets($pdo, $c);

    $defResult = aiDefensiveConsolidate($pdo, $c, $turn_number, $budget);
    $skipWorkers = array_merge($defResult['moved'], $defResult['hidden']);

    $available = aiAvailableWorkerIds($pdo, $c, $turn_number, $skipWorkers);
    $terrResult = aiAllocateClaimAndContest($pdo, $c, $turn_number, $available);
    $skipWorkers = array_merge($skipWorkers, $terrResult['contest_actions']);

    $available = aiAvailableWorkerIds($pdo, $c, $turn_number, $skipWorkers);
    $intelResult = aiPreStrikeIntel($pdo, $c, $turn_number, $available, []);
    $skipWorkers = array_merge($skipWorkers, $intelResult);

    aiSetWorkerActionsForState($pdo, $c, 'investigate', $turn_number, $skipWorkers);
    aiEquipPowers($pdo, $c);
}

function aiAggressiveBehaviour($pdo, $c, $turn_number) {
    $base_zone = aiBaseZone($pdo, $c['id']);
    if ($base_zone === null) {
        aiRelocateBase($pdo, $c);
        $base_zone = aiBaseZone($pdo, $c['id']);
    }
    if ($base_zone !== null) {
        aiRecruitForState($pdo, $c, $base_zone, $turn_number);
    }

    $budget = aiBudget($pdo, $c, $turn_number);
    $ranked = aiRankTargets($pdo, $c);

    $defResult = aiDefensiveConsolidate($pdo, $c, $turn_number, $budget);
    $skipWorkers = array_merge($defResult['moved'], $defResult['hidden']);

    $strikeResult = aiPlanStrike($pdo, $c, $turn_number, $ranked, $budget, $skipWorkers);
    $skipWorkers = array_merge($skipWorkers, $strikeResult['workers_committed']);
    $strikeZones = array_column($strikeResult['strikes'], 'zone_id');

    $available = aiAvailableWorkerIds($pdo, $c, $turn_number, $skipWorkers);
    $terrResult = aiAllocateClaimAndContest($pdo, $c, $turn_number, $available);
    $skipWorkers = array_merge($skipWorkers, $terrResult['contest_actions']);

    $available = aiAvailableWorkerIds($pdo, $c, $turn_number, $skipWorkers);
    $intelResult = aiPreStrikeIntel($pdo, $c, $turn_number, $available, $strikeZones);
    $skipWorkers = array_merge($skipWorkers, $intelResult);

    aiSetWorkerActionsForState($pdo, $c, 'investigate', $turn_number, $skipWorkers);
    aiEquipPowers($pdo, $c);
}

function aiViolentBehaviour($pdo, $c, $turn_number) {
    $base_zone = aiBaseZone($pdo, $c['id']);
    if ($base_zone === null) {
        aiRelocateBase($pdo, $c);
        $base_zone = aiBaseZone($pdo, $c['id']);
    }
    if ($base_zone !== null) {
        aiRecruitForState($pdo, $c, $base_zone, $turn_number);
    }

    $budget = aiBudget($pdo, $c, $turn_number);
    $ranked = aiRankTargets($pdo, $c);

    $defResult = aiDefensiveConsolidate($pdo, $c, $turn_number, $budget);
    $skipWorkers = array_merge($defResult['moved'], $defResult['hidden']);

    $strikeResult = aiPlanStrike($pdo, $c, $turn_number, $ranked, $budget, $skipWorkers);
    $skipWorkers = array_merge($skipWorkers, $strikeResult['workers_committed']);
    $strikeZones = array_column($strikeResult['strikes'], 'zone_id');

    aiQueueLocationAttacks($pdo, $c, 5);

    $available = aiAvailableWorkerIds($pdo, $c, $turn_number, $skipWorkers);
    $terrResult = aiAllocateClaimAndContest($pdo, $c, $turn_number, $available);
    $skipWorkers = array_merge($skipWorkers, $terrResult['contest_actions']);

    $available = aiAvailableWorkerIds($pdo, $c, $turn_number, $skipWorkers);
    $intelResult = aiPreStrikeIntel($pdo, $c, $turn_number, $available, $strikeZones);
    $skipWorkers = array_merge($skipWorkers, $intelResult);

    aiSetWorkerActionsForState($pdo, $c, 'investigate', $turn_number, $skipWorkers);
    aiEquipPowers($pdo, $c);
}

// Helper: alive worker ids minus the given skip set.
function aiAvailableWorkerIds($pdo, $c, $turn_number, $skipWorkerIds) {
    $skip = array_flip(array_map('intval', $skipWorkerIds));
    $alive = aiAliveWorkers($pdo, $c['id'], $turn_number);
    $ids = [];
    foreach ($alive as $row) {
        $wid = (int)$row['id'];
        if (!isset($skip[$wid])) $ids[] = $wid;
    }
    return $ids;
}
