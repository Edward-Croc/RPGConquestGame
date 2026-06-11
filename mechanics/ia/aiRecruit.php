<?php

/**
 * Recruits per turn by ai_type. aggressive / violent recruit two,
 * passive / searching recruit one.
 */
function aiRecruitsPerTurn($ia_type) {
    switch ($ia_type) {
        case 'aggressive':
        case 'violent':
            return 2;
        default:
            return 1;
    }
}

/**
 * Recruit aiRecruitsPerTurn workers in the given zone. Each call
 * consumes a first_come slot (preferred) or a recrutement slot.
 * If neither is available the inner aiRecruitOneInZone is a no-op.
 */
function aiRecruitForState($pdo, $c, $zone_id, $turn_number) {
    $n = aiRecruitsPerTurn($c['ia_type']);
    for ($i = 0; $i < $n; $i++) {
        aiRecruitOneInZone($pdo, $c, $zone_id, $turn_number);
    }
}

/**
 * Create at most 1 worker this turn in $zone_id, using the per-controller
 * first-come slot if available, else the recrutement slot.
 */
function aiRecruitOneInZone($pdo, $c, $zone_id, $turn_number) {
    $prefix = $_SESSION['GAME_PREFIX'];

    if (canStartFirstCome($pdo, $c['id'])) {
        $proposal = generateNewWorker($pdo, $c['id'], 'first_come');
        if (empty($proposal) || empty($proposal['lastname'])) return;
        aiNormalizeRecruitProposal($pdo, $proposal, $c, $zone_id);
        if (createWorker($pdo, $proposal)) {
            $stmt = $pdo->prepare(
                "UPDATE {$prefix}controllers
                 SET turn_firstcome_workers = turn_firstcome_workers + 1
                 WHERE id = :cid"
            );
            $stmt->execute([':cid' => $c['id']]);
        }
        return;
    }
    if (canStartRecrutement($pdo, $c['id'], $turn_number)) {
        $proposal = generateNewWorker($pdo, $c['id'], 'recrutement');
        if (empty($proposal) || empty($proposal['lastname'])) return;
        aiNormalizeRecruitProposal($pdo, $proposal, $c, $zone_id);
        if (createWorker($pdo, $proposal)) {
            $stmt = $pdo->prepare(
                "UPDATE {$prefix}controllers
                 SET turn_recruited_workers = turn_recruited_workers + 1
                 WHERE id = :cid"
            );
            $stmt->execute([':cid' => $c['id']]);
        }
    }
}

/**
 * Translate generateNewWorker's power_1/power_2 keys to the
 * power_hobby_id/power_metier_id keys createWorker expects, then
 * pre-fill discipline + transformation link_power_type ids from the
 * faction's available pool so the worker gets them on creation.
 */
function aiNormalizeRecruitProposal($pdo, &$proposal, $c, $zone_id) {
    $proposal['zone_id'] = $zone_id;
    $proposal['controller_id'] = $c['id'];
    if (!empty($proposal['power_1']['id'])) $proposal['power_hobby_id']  = $proposal['power_1']['id'];
    if (!empty($proposal['power_2']['id'])) $proposal['power_metier_id'] = $proposal['power_2']['id'];

    $factionLinks = aiFactionPowerLinksByType($pdo, $c['faction_id']);
    if (!empty($factionLinks['Discipline']))     $proposal['discipline']     = $factionLinks['Discipline'];
    if (!empty($factionLinks['Transformation'])) $proposal['transformation'] = $factionLinks['Transformation'];
}

