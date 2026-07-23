<?php

require_once '../controllers/functions.php';

/**
 * Resolve the on-behalf controller name for the fail/success view template (%4$s).
 *
 * Mirrors the success-write semantics: unset → claim for self ($selfControllerId),
 * 'null' string sentinel → "Personne (Sans bannière)", real id → controller name.
 * 
 * @param PDO $pdo : database connection
 * 
 */
function _claimResolveOnBehalfName(PDO $pdo, array $params, int $selfControllerId): string {
    $claimControllerId = $params['claim_controller_id'] ?? null;
    if ($claimControllerId === 'null') return 'Personne (Sans bannière)';
    if (empty($claimControllerId)) return (string) getControllerName($pdo, $selfControllerId);
    return (string) getControllerName($pdo, (int)$claimControllerId);
}

/**
 * Resolve the claimer_controller_id to write to zones on a successful claim.
 *
 * Defaults to $selfControllerId (the claiming controller). The leader's
 * action_params.claim_controller_id overrides it; the 'null' string sentinel
 * means "remove visible claim" (write SQL NULL).
 * 
 * @return int|null
 */
function _claimResolveClaimerControllerIdForWrite(array $params, int $selfControllerId): int|null {
    if (empty($params['claim_controller_id'])) return $selfControllerId;
    if ($params['claim_controller_id'] === 'null') return null;
    return (int) $params['claim_controller_id'];
}

/**
 * Dispatch claimMode and run the shared post-resolution side effects.
 *
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics array
 *
 * @return bool : success
 *
 * Modes:
 *   - 'worker'        → claimByWorkerMath (per-worker resolution, first-claim-wins)
 *   - 'worker_leader' → claimByWorkerLeaderMath (per-group leader resolution)
 *
 * Both math functions return an array of resolution descriptors with the
 * shape:
 *   [
 *     'zone_id'               => int,
 *     'zone_name'             => string,
 *     'cid'                   => int,    // claiming controller_id (= new holder on success)
 *     'self_controller_id'    => int,    // fallback for %4$s + claimer_controller_id write
 *     'success'               => bool,
 *     'fire_observer_reports' => bool,   // mode A: false on discrete success; mode B: always true
 *     'leader_worker_id'      => int,    // self-report recipient
 *     'leader_name'           => string, // %1$s
 *     'claimer_worker_ids'    => int[],  // CKE writes (1 in mode A, N in mode B)
 *     'co_claimer_names'      => string, // %3$s
 *     'params'                => array,  // decoded action_params (for %4$s + write override)
 *     'observers'             => array,  // active-non-cid workers in zone (worker_id + controller_id rows)
 *   ]
 */
function claimMechanic(PDO $pdo, array $mechanics): bool {
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true')
        $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;
    game_error_log(__FUNCTION__, 'START with turn_number : ' . $mechanics['turncounter'], ['mechanics' => $mechanics], 'debug');

    $mode = getConfig($pdo, 'claimMode');
    if (!in_array($mode, ['worker', 'worker_leader'], true)) {
        echo "<div><h3>claimMechanic : mode '".htmlspecialchars((string)$mode)."' not supported, skipped</h3></div>";
        return true;
    }

    $turn_number = $mechanics['turncounter'];
    $prefix = $_SESSION['GAME_PREFIX'];

    echo "<div><h3>claimMechanic (mode: ".htmlspecialchars((string)$mode).") : </h3>";
    echo "turn_number : $turn_number <br>";

    $resolutions = ($mode === 'worker_leader')
        ? claimByWorkerLeaderMath($pdo, $mechanics)
        : claimByWorkerMath($pdo, $mechanics);

    foreach ($resolutions as $r) {
        $onBehalfName = _claimResolveOnBehalfName($pdo, $r['params'], (int)$r['self_controller_id']);

        if ($r['fire_observer_reports']) {
            $textesView = json_decode(getConfig($pdo, $r['success'] ? 'textesClaimSuccessViewArray' : 'textesClaimFailViewArray'), true) ?: [];
            // (nom) - %1$s
            // (zone) - %2$s
            // (co-claimer names) - %3$s
            // (claim_controller_id target name) - %4$s
            foreach ($r['observers'] as $observer) {
                if (empty($textesView)) continue;
                $tpl = $textesView[array_rand($textesView)];
                $report = sprintf($tpl, $r['leader_name'], $r['zone_name'], $r['co_claimer_names'], $onBehalfName).'<br/>';
                updateWorkerAction($pdo, (int)$observer['worker_id'], $turn_number, NULL, ['claim_report' => $report]);
            }
            $observerControllerIds = array_unique(array_column($r['observers'], 'controller_id'));
            foreach ($observerControllerIds as $observerCid) {
                foreach ($r['claimer_worker_ids'] as $cwid) {
                    addWorkerToCKE($pdo, (int)$observerCid, (int)$cwid, $turn_number, (int)$r['zone_id']);
                }
            }
        }

        // Self-report (always, 2 args)
        // (nom) - %1$s
        // (zone) - %2$s
        $textesSelf = json_decode(getConfig($pdo, $r['success'] ? 'textesClaimSuccessArray' : 'textesClaimFailArray'), true) ?: [];
        if (!empty($textesSelf)) {
            $tpl = $textesSelf[array_rand($textesSelf)];
            $report = sprintf($tpl, $r['leader_name'], $r['zone_name']);
            updateWorkerAction($pdo, (int)$r['leader_worker_id'], $turn_number, NULL, ['claim_report' => $report]);
        }

        if ($r['success']) {
            $claimer_controller_id = _claimResolveClaimerControllerIdForWrite($r['params'], (int)$r['self_controller_id']);
            try {
                $uStmt = $pdo->prepare("UPDATE {$prefix}zones SET claimer_controller_id = :claimer, holder_controller_id = :holder WHERE id = :zid");
                $uStmt->bindValue(':claimer', $claimer_controller_id, $claimer_controller_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $uStmt->bindValue(':holder', (int)$r['cid'], PDO::PARAM_INT);
                $uStmt->bindValue(':zid', (int)$r['zone_id'], PDO::PARAM_INT);
                $uStmt->execute();
            } catch (PDOException $e) {
                game_error_log(__FUNCTION__, 'UPDATE zones failed', ['error' => $e->getMessage(), 'zone_id' => (int)$r['zone_id']]);
            }
        }
    }

    echo '<p>claimMechanic : DONE</p></div>';
    game_error_log(__FUNCTION__, 'DONE', ['resolutions_count' => count($resolutions)], 'debug');
    return true;
}

/**
 * Solo Worker Mode math (per-worker resolution).
 *
 * Iterates every claim-action worker for this turn ordered by (zone, attack DESC).
 * The first claimer per zone whose discrete_claim or violent_claim clears the
 * configured threshold wins; subsequent claimers for that zone still produce
 * resolutions (so their fail-reports + CKE leaks fire) but with success=false.
 *
 * "Discrete success" (first-and-only claimer in zone with discrete_claim clearing
 * DISCRETECLAIMDIFF) sets fire_observer_reports=false so observers stay unaware.
 *
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics array
 * 
 * @return array list of resolution descriptors (see claimMechanic doc)
 */
function claimByWorkerMath(PDO $pdo, array $mechanics): array {
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true')
        $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;
    game_error_log(__FUNCTION__, 'START with turn_number : ' . $mechanics['turncounter'], ['mechanics' => $mechanics], 'debug');

    $turn_number = $mechanics['turncounter'];
    $prefix = $_SESSION['GAME_PREFIX'];

    $DISCRETECLAIMDIFF = (int) getConfig($pdo, 'DISCRETECLAIMDIFF');
    $VIOLENTCLAIMDIFF  = (int) getConfig($pdo, 'VIOLENTCLAIMDIFF');

    $sql = "SELECT
            wa.worker_id AS claimer_id,
            CONCAT(w.firstname, ' ', w.lastname) AS claimer_name,
            wa.action_params AS claimer_params,
            wa.controller_id AS claimer_controller_id,
            z.id AS zone_id,
            z.name AS zone_name,
            z.holder_controller_id AS zone_holder_controller_id,
            (wa.enquete_val - z.calculated_defence_val) AS discrete_claim,
            (wa.attack_val  - z.calculated_defence_val) AS violent_claim
        FROM {$prefix}worker_actions wa
        JOIN {$prefix}zones z ON z.id = wa.zone_id
        JOIN {$prefix}workers w ON w.id = wa.worker_id
        WHERE wa.action_choice = 'claim' AND wa.turn_number = :turn_number
        ORDER BY z.id, wa.attack_val DESC";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmt->execute();
        $claimerArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT claimers failed', ['error' => $e->getMessage()]);
        return [];
    }
    game_error_log(__FUNCTION__, sprintf('%d claimers', count($claimerArray)), [], 'debug');

    $allActiveByZone = [];
    $zoneIds = array_values(array_unique(array_column($claimerArray, 'zone_id')));
    if (!empty($zoneIds)) {
        $active_actions = "'".implode("','", ACTIVE_ACTIONS)."'";
        $placeholders = implode(',', array_fill(0, count($zoneIds), '?'));
        try {
            $oSql = "SELECT cw.worker_id, cw.controller_id, w.zone_id
                FROM {$prefix}workers w
                JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
                JOIN {$prefix}worker_actions wa ON wa.worker_id = w.id AND wa.turn_number = ?
                WHERE w.zone_id IN ($placeholders) AND wa.action_choice IN ($active_actions)";
            $oStmt = $pdo->prepare($oSql);
            $oStmt->execute(array_merge([$turn_number], $zoneIds));
            foreach ($oStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $allActiveByZone[(int)$row['zone_id']][] = $row;
            }
        } catch (PDOException $e) {
            game_error_log(__FUNCTION__, 'SELECT active workers by zone failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    $resolutions = [];
    $zoneClaimed = [];

    foreach ($claimerArray as $key => $claimer) {
        $zone_id     = (int) $claimer['zone_id'];
        $claimer_cid = (int) $claimer['claimer_controller_id'];

        $isViolent = true;
        $success   = false;

        $isFirstAndOnlyForUnclaimedZone = (
            $claimer['zone_holder_controller_id'] == NULL
            && empty($zoneClaimed[$zone_id])
            && ($key - 1 < 0 || $claimerArray[$key-1]['zone_id'] != $claimer['zone_id'])
            && (empty($claimerArray[$key+1]) || $claimerArray[$key+1]['zone_id'] != $claimer['zone_id'])
        );

        if ($isFirstAndOnlyForUnclaimedZone) {
            if ((int)$claimer['discrete_claim'] >= $DISCRETECLAIMDIFF || (int)$claimer['violent_claim'] >= $VIOLENTCLAIMDIFF) {
                $success = true;
                $zoneClaimed[$zone_id] = true;
                if ((int)$claimer['discrete_claim'] >= $DISCRETECLAIMDIFF) {
                    $isViolent = false;
                }
            }
        } elseif (empty($zoneClaimed[$zone_id])) {
            if ((int)$claimer['discrete_claim'] >= $DISCRETECLAIMDIFF || (int)$claimer['violent_claim'] >= $VIOLENTCLAIMDIFF) {
                $success = true;
                $zoneClaimed[$zone_id] = true;
            }
        }

        $claimerParams = !empty($claimer['claimer_params']) ? json_decode($claimer['claimer_params'], true) : array();
        if (json_last_error() !== JSON_ERROR_NONE) $claimerParams = array();

        $observers = array_values(array_filter(
            $allActiveByZone[$zone_id] ?? [],
            fn($w) => (int)$w['controller_id'] !== $claimer_cid
        ));

        $resolutions[] = [
            'zone_id'                => $zone_id,
            'zone_name'              => $claimer['zone_name'],
            'cid'                    => $claimer_cid,
            'self_controller_id'     => $claimer_cid,
            'success'                => $success,
            'fire_observer_reports'  => $isViolent,
            'leader_worker_id'       => (int)$claimer['claimer_id'],
            'leader_name'            => (string)$claimer['claimer_name'],
            'claimer_worker_ids'     => [(int)$claimer['claimer_id']],
            'co_claimer_names'       => '',
            'params'                 => $claimerParams,
            'observers'              => $observers,
        ];

        game_error_log(__FUNCTION__, sprintf('zone %d c %d w %d : violent=%s success=%s',
            $zone_id, $claimer_cid, $claimer['claimer_id'],
            $isViolent ? 'true' : 'false', $success ? 'true' : 'false'), [], 'debug');
    }

    return $resolutions;
}

/**
 * WorkerLeader Mode math (deterministic leader-based group resolution).
 *
 * Groups all claim-action workers for this turn by (controller_id, zone_id),
 * picks the leader of each group by the highest (attack_val, defence_val,
 * enquete_val, worker_id) tiebreak, then iterates groups in decreasing
 * leader-stats order (controller_id ASC tiebreak). The first group whose
 * claim_val clears claimDiff wins the zone — subsequent passing groups for
 * the same zone are forced to lose, but their fail-reports + CKE leaks
 * still fire (observers saw all attempts).
 *
 * Resolves via calculateControllerValue('Claim') vs zones.calculated_defence_val.
 * Groups whose claiming controller already holds the zone are skipped (the
 * defender bonus is already applied via recalculateZoneDefence supporting term).
 *
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics array
 * 
 * @return array list of resolution descriptors (see claimMechanic doc)
 */
function claimByWorkerLeaderMath(PDO $pdo, array $mechanics): array {
    if (strtolower(getConfig($pdo, 'DEBUG')) == 'true')
        $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;
    game_error_log(__FUNCTION__, 'START with turn_number : ' . $mechanics['turncounter'], ['mechanics' => $mechanics], 'debug');

    $turn_number = $mechanics['turncounter'];
    $prefix = $_SESSION['GAME_PREFIX'];

    $claimDiff = (int) getConfig($pdo, 'claimDiff');

    try {
        $sql = "SELECT wa.worker_id, cw.controller_id, w.zone_id, z.name AS zone_name,
                       CONCAT(w.firstname, ' ', w.lastname) AS worker_name,
                       wa.attack_val, wa.defence_val, wa.enquete_val
                FROM {$prefix}worker_actions wa
                JOIN {$prefix}workers w ON w.id = wa.worker_id
                JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
                JOIN {$prefix}zones z ON z.id = w.zone_id
                WHERE wa.turn_number = :turn_number
                  AND wa.action_choice = 'claim'
                  AND cw.is_primary_controller = " . (($_SESSION['DBTYPE'] == 'mysql') ? '1' : 'true');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmt->execute();
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT candidates failed', ['error' => $e->getMessage()]);
        return [];
    }

    $nameByWorkerId = [];
    foreach ($candidates as $c) {
        $nameByWorkerId[(int)$c['worker_id']] = (string)$c['worker_name'];
    }

    $groupsByKey = [];
    foreach ($candidates as $c) {
        $key = $c['controller_id'].'|'.$c['zone_id'];
        $candidateRank = [(int)$c['attack_val'], (int)$c['defence_val'], (int)$c['enquete_val'], -((int)$c['worker_id'])];
        if (!isset($groupsByKey[$key])) {
            $groupsByKey[$key] = [
                'controller_id' => (int)$c['controller_id'],
                'zone_id' => (int)$c['zone_id'],
                'zone_name' => $c['zone_name'],
                'leader_worker_id' => (int)$c['worker_id'],
                'leader_name' => (string)$c['worker_name'],
                'leader_attack_val' => (int)$c['attack_val'],
                'leader_defence_val' => (int)$c['defence_val'],
                'leader_enquete_val' => (int)$c['enquete_val'],
            ];
            continue;
        }
        $cur = $groupsByKey[$key];
        $curRank = [$cur['leader_attack_val'], $cur['leader_defence_val'], $cur['leader_enquete_val'], -$cur['leader_worker_id']];
        if ($candidateRank > $curRank) {
            $groupsByKey[$key]['leader_worker_id']   = (int)$c['worker_id'];
            $groupsByKey[$key]['leader_name']        = (string)$c['worker_name'];
            $groupsByKey[$key]['leader_attack_val']  = (int)$c['attack_val'];
            $groupsByKey[$key]['leader_defence_val'] = (int)$c['defence_val'];
            $groupsByKey[$key]['leader_enquete_val'] = (int)$c['enquete_val'];
        }
    }
    $groups = array_values($groupsByKey);
    usort($groups, function ($a, $b) {
        if ($a['leader_attack_val']  !== $b['leader_attack_val'])  return $b['leader_attack_val']  - $a['leader_attack_val'];
        if ($a['leader_defence_val'] !== $b['leader_defence_val']) return $b['leader_defence_val'] - $a['leader_defence_val'];
        if ($a['leader_enquete_val'] !== $b['leader_enquete_val']) return $b['leader_enquete_val'] - $a['leader_enquete_val'];
        return $a['controller_id'] - $b['controller_id'];
    });

    $zoneClaimed = [];
    $zoneIds = array_values(array_unique(array_column($groups, 'zone_id')));
    $activeByZone = [];
    $paramsByLeader = [];
    if (!empty($zoneIds)) {
        $active_actions_list = "'".implode("','", ACTIVE_ACTIONS)."'";
        $placeholders = implode(',', array_fill(0, count($zoneIds), '?'));
        try {
            $aSql = "SELECT cw.worker_id, cw.controller_id, w.zone_id, wa.action_params
                    FROM {$prefix}workers w
                    JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
                    JOIN {$prefix}worker_actions wa ON wa.worker_id = w.id AND wa.turn_number = ?
                    WHERE w.zone_id IN ($placeholders)
                      AND wa.action_choice IN ($active_actions_list)";
            $aStmt = $pdo->prepare($aSql);
            $aStmt->execute(array_merge([$turn_number], $zoneIds));
            foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $activeByZone[(int)$row['zone_id']][] = $row;
                $paramsByLeader[(int)$row['worker_id']] = $row['action_params'];
            }
        } catch (PDOException $e) {
            game_error_log(__FUNCTION__, 'SELECT active workers by zone failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    $resolutions = [];

    foreach ($groups as $group) {
        $zone_id   = (int) $group['zone_id'];
        $cid       = (int) $group['controller_id'];
        $leader_id = (int) $group['leader_worker_id'];

        try {
            $zStmt = $pdo->prepare("SELECT * FROM {$prefix}zones WHERE id = :zid");
            $zStmt->bindParam(':zid', $zone_id, PDO::PARAM_INT);
            $zStmt->execute();
            $zone = $zStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            game_error_log(__FUNCTION__, 'SELECT zones failed', ['error' => $e->getMessage(), 'zone_id' => $zone_id]);
            continue;
        }
        if ((int)$zone['holder_controller_id'] === $cid) {
            game_error_log(__FUNCTION__, sprintf('zone %d c %d : holder == cid, claim path skipped (defender bonus already applied via recalculateZoneDefence supporting term)', $zone_id, $cid), [], 'debug');
            continue;
        }
        $calculated_defence_val = (int) $zone['calculated_defence_val'];

        $leaderParamsRaw = $paramsByLeader[$leader_id] ?? '';
        $leaderParams = !empty($leaderParamsRaw) ? json_decode($leaderParamsRaw, true) : array();
        if (json_last_error() !== JSON_ERROR_NONE) $leaderParams = array();

        $claimVal = calculateControllerValue($pdo, 'Claim', $zone_id, $cid);

        $threshold_passed = ($claimVal - $calculated_defence_val) >= $claimDiff;
        $success = $threshold_passed && empty($zoneClaimed[$zone_id]);
        $outcome = $success ? 'WIN' : ($threshold_passed ? 'lose (zone already claimed this turn)' : 'lose');
        game_error_log(__FUNCTION__, sprintf('zone %d c %d : claim_val=%d calculated_defence_val=%d  >=  claimDiff=%d => %s',
            $zone_id, $cid, $claimVal, $calculated_defence_val, $claimDiff, $outcome), [], 'debug');

        $observers = array_values(array_filter(
            $activeByZone[$zone_id] ?? [],
            fn($w) => (int)$w['controller_id'] !== $cid
        ));

        $claimerWorkerIds = [];
        foreach ($candidates as $c) {
            if ((int)$c['controller_id'] === $cid && (int)$c['zone_id'] === $zone_id) {
                $claimerWorkerIds[] = (int)$c['worker_id'];
            }
        }
        if (empty($claimerWorkerIds)) $claimerWorkerIds = [$leader_id];

        $coClaimerIds = array_values(array_diff($claimerWorkerIds, [$leader_id]));
        if (empty($coClaimerIds)) {
            $coClaimerNames = "d'autres agents";
        } else {
            $coClaimerNames = implode(', ', array_map(
                fn($wid) => (string)($nameByWorkerId[$wid] ?? "?"),
                $coClaimerIds
            ));
        }

        $resolutions[] = [
            'zone_id'                => $zone_id,
            'zone_name'              => $group['zone_name'],
            'cid'                    => $cid,
            'self_controller_id'     => $cid,
            'success'                => $success,
            'fire_observer_reports'  => true,
            'leader_worker_id'       => $leader_id,
            'leader_name'            => (string)$group['leader_name'],
            'claimer_worker_ids'     => $claimerWorkerIds,
            'co_claimer_names'       => $coClaimerNames,
            'params'                 => $leaderParams,
            'observers'              => $observers,
        ];

        if ($success) $zoneClaimed[$zone_id] = $cid;
    }

    return $resolutions;
}
