<?php

require_once __DIR__ . '/aiWorkers.php';

/**
 * Plan a coordinated strike on top-2 ranked targets. Returns the strike
 * plan + workers committed. Reads only COUNT(worker_powers) per enemy
 * (fair) and own actual stats via getWorkers (fair: own). Respects
 * $skipWorkerIds from defensive triage.
 */
function aiPlanStrike($pdo, $c, $turn_number, $rankedTargets, $budget, $skipWorkerIds = []) {
    $result = ['strikes' => [], 'workers_committed' => []];

    $marginPercent = (int) getConfig($pdo, 'aiStrikeMarginPercent');
    if ($marginPercent <= 0) $marginPercent = 10;
    $locationCap = (int) getConfig($pdo, 'aiLocationAttackCap');
    if ($locationCap <= 0) $locationCap = 5;

    if (empty($rankedTargets) || empty($budget) || (int)($budget['offence'] ?? 0) <= 0) {
        return $result;
    }

    $classified = aiClassifyWorkers($pdo, $c['id']);
    $skip = array_flip(array_map('intval', $skipWorkerIds));
    $pool = [];
    foreach ($classified['fighters'] as $wid) {
        if (!isset($skip[(int)$wid])) $pool[(int)$wid] = true;
    }
    foreach ($classified['investigators'] as $wid) {
        if (!isset($skip[(int)$wid])) $pool[(int)$wid] = true;
    }
    if (empty($pool)) return $result;

    $prefix = $_SESSION['GAME_PREFIX'];
    $topTargets = array_slice($rankedTargets, 0, 2);

    foreach ($topTargets as $target) {
        if (empty($pool)) break;
        $kind = $target['kind'] ?? '';
        $target_id = (int)($target['target_id'] ?? 0);
        $zone_id = (int)($target['zone_id'] ?? 0);
        if ($target_id <= 0 || $zone_id <= 0) continue;

        if ($kind === 'worker') {
            $estimated_enemy_defence = 0;
            try {
                $stmt = $pdo->prepare(
                    "SELECT discovered_worker_id FROM {$prefix}controllers_known_enemies
                     WHERE controller_id = :cid AND zone_id = :zid
                       AND discovered_worker_id IS NOT NULL"
                );
                $stmt->execute([':cid' => $c['id'], ':zid' => $zone_id]);
                $enemy_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $enemy_rows = [];
            }
            foreach ($enemy_rows as $erow) {
                $eid = (int) $erow['discovered_worker_id'];
                if ($eid <= 0) continue;
                $estimated_enemy_defence += aiWorkerPowerCount($pdo, $eid);
            }

            $pool_ids = array_keys($pool);
            $own_workers = getWorkers($pdo, $pool_ids);
            if (empty($own_workers)) continue;

            // Prefer fighters already in the target zone; moveWorker forces passive.
            $in_zone = [];
            $elsewhere = [];
            foreach ($own_workers as $w) {
                $wid = (int) $w['id'];
                if (!isset($pool[$wid])) continue;
                if ((int) $w['zone_id'] === $zone_id) {
                    $in_zone[] = $w;
                } else {
                    $elsewhere[] = $w;
                }
            }
            usort($in_zone, function ($a, $b) {
                return ((int)$b['total_attack']) <=> ((int)$a['total_attack']);
            });
            usort($elsewhere, function ($a, $b) {
                return ((int)$b['total_attack']) <=> ((int)$a['total_attack']);
            });

            $own_attack_sum = 0;
            $picked_attackers = [];
            $picked_movers = [];
            foreach ($in_zone as $w) {
                $own_attack_sum += (int) $w['total_attack'];
                $picked_attackers[] = (int) $w['id'];
                $margin = (int) ceil($own_attack_sum * $marginPercent / 100);
                if (($own_attack_sum - $estimated_enemy_defence) >= $margin && $own_attack_sum > 0) {
                    break;
                }
            }
            $margin_now = (int) ceil($own_attack_sum * $marginPercent / 100);
            $needs_more = !($own_attack_sum > 0 && ($own_attack_sum - $estimated_enemy_defence) >= $margin_now);
            if ($needs_more) {
                foreach ($elsewhere as $w) {
                    $own_attack_sum += (int) $w['total_attack'];
                    $picked_movers[] = (int) $w['id'];
                    $margin = (int) ceil($own_attack_sum * $marginPercent / 100);
                    if (($own_attack_sum - $estimated_enemy_defence) >= $margin && $own_attack_sum > 0) {
                        break;
                    }
                }
            }

            $final_margin = (int) ceil($own_attack_sum * $marginPercent / 100);
            if ($own_attack_sum <= 0 || ($own_attack_sum - $estimated_enemy_defence) < $final_margin) {
                continue;
            }

            $strike_workers = [];
            foreach ($picked_attackers as $wid) {
                activateWorker($pdo, $wid, 'attack', ['worker_' . $target_id]);
                $strike_workers[] = $wid;
                unset($pool[$wid]);
                $result['workers_committed'][] = $wid;
            }
            foreach ($picked_movers as $wid) {
                moveWorker($pdo, $wid, $zone_id);
                $strike_workers[] = $wid;
                unset($pool[$wid]);
                $result['workers_committed'][] = $wid;
            }
            $result['strikes'][] = [
                'kind' => 'worker',
                'target_id' => $target_id,
                'zone_id' => $zone_id,
                'workers' => $strike_workers,
            ];
        } elseif ($kind === 'location') {
            $res = attackLocation($pdo, $c['id'], $target_id);
            if (is_array($res) && !empty($res['success'])) {
                $result['strikes'][] = [
                    'kind' => 'location',
                    'target_id' => $target_id,
                    'zone_id' => $zone_id,
                    'workers' => [],
                ];
            }
        }
    }

    return $result;
}

/**
 * Queue or resolve location attacks against known destroyable not-own
 * locations, capped at $cap per controller per turn. attackLocation()
 * dispatches per locationAttackMode.
 */
function aiQueueLocationAttacks($pdo, $c, $cap = 5) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT l.id FROM {$prefix}controller_known_locations ckl
             JOIN {$prefix}locations l ON l.id = ckl.location_id
             WHERE ckl.controller_id = :cid
               AND (l.controller_id IS NULL OR l.controller_id != :cid)
               AND l.can_be_destroyed = True
             ORDER BY l.id ASC LIMIT $cap"
        );
        $stmt->execute([':cid' => $c['id']]);
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }
    foreach ($targets as $t) {
        attackLocation($pdo, $c['id'], $t['id']);
    }
}
