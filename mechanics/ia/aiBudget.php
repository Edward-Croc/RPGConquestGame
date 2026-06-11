<?php
// Ressource budgeting scope: offence / defence / reserve buckets per state

function aiBudget($pdo, $c, $turn_number) {
    $defaultJson = '{"passive":[0,70,30],"searching":[20,50,30],"aggressive":[60,30,10],"violent":[80,10,10]}';
    $raw = getConfig($pdo, 'aiBudgetByState');
    if ($raw === null || $raw === false || $raw === '') {
        $raw = $defaultJson;
    }
    $map = json_decode($raw, true);
    if (!is_array($map)) {
        $map = json_decode($defaultJson, true);
    }
    $state = isset($c['ia_type']) ? $c['ia_type'] : 'passive';
    $ratios = isset($map[$state]) && is_array($map[$state]) ? $map[$state] : $map['passive'];
    $offencePct = isset($ratios[0]) ? (int)$ratios[0] : 0;
    $defencePct = isset($ratios[1]) ? (int)$ratios[1] : 0;
    $reservePct = isset($ratios[2]) ? (int)$ratios[2] : 0;

    $workers = aiAliveWorkers($pdo, $c['id'], $turn_number);
    $workersTotal = count($workers);

    if ($workersTotal === 0) {
        return [
            'state' => $state,
            'workers_total' => 0,
            'offence' => 0,
            'defence' => 0,
            'reserve' => 0,
        ];
    }

    // Percents used as-given; no normalization if sum != 100
    $offenceCount = (int)floor($workersTotal * $offencePct / 100);
    $defenceCount = (int)floor($workersTotal * $defencePct / 100);
    $reserveCount = (int)floor($workersTotal * $reservePct / 100);
    $remainder = $workersTotal - ($offenceCount + $defenceCount + $reserveCount);
    $defenceCount += $remainder;

    return [
        'state' => $state,
        'workers_total' => $workersTotal,
        'offence' => $offenceCount,
        'defence' => $defenceCount,
        'reserve' => $reserveCount,
    ];
}
