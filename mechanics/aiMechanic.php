<?php

/**
 * AI mechanic — invoked as a phase of mechanics/endTurn.php.
 *
 * Iterates every controller with ia_type IN ('passive','searching',
 * 'aggressive','violent') in controllers.id ASC. For each: ensures a
 * base exists (universal pre-step, resource-gated), computes the state
 * transition, then dispatches per-state behaviour. 
 *
 * State transitions:
 *   passive    → searching   if any own worker died or was captured this turn
 *   searching  → aggressive  if known enemies count ≥ aiAggressionThreshold
 *   aggressive → violent     if own base or own location attacked this turn
 *   aggressive → searching   if no enemy workers known this turn
 *   violent    → aggressive  if no enemy workers AND no enemy locations known this turn
 */
function aiMechanic($pdo, $mechanics) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $turn_number = (int) $mechanics['turncounter'];
    $debug = (strtolower((string) getConfig($pdo, 'DEBUG_IA')) === 'true');

    echo "<div><h3>aiMechanic — turn $turn_number</h3>";

    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM {$prefix}controllers
             WHERE is_ia = TRUE
               AND ia_type IN ('passive','searching','aggressive','violent')
             ORDER BY id ASC"
        );
        $stmt->execute();
        $aiControllers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT failed: ".$e->getMessage()."<br />";
        return false;
    }

    foreach ($aiControllers as $c) {
        echo sprintf("<p><strong>%s %s</strong> (%s)</p>",
            $c['firstname'], $c['lastname'], $c['ia_type']);

        aiEnsureBase($pdo, $c);

        $newType = aiCheckStateTransition($pdo, $c, $turn_number);
        if ($newType !== $c['ia_type']) {
            if ($debug) echo sprintf("aiM: %s → %s<br>", $c['ia_type'], $newType);
            aiUpdateIaType($pdo, $c['id'], $newType);
            $c['ia_type'] = $newType;
        }

        switch ($c['ia_type']) {
            case 'passive':    aiPassiveBehaviour($pdo, $c, $turn_number); break;
            case 'searching':  aiSearchingBehaviour($pdo, $c, $turn_number); break;
            case 'aggressive': aiAggressiveBehaviour($pdo, $c, $turn_number); break;
            case 'violent':    aiViolentBehaviour($pdo, $c, $turn_number); break;
        }

        aiRebuildOwnedLocations($pdo, $c);
    }

    echo "</div>";
    return true;
}

/* ------------------------------------------------------------------ */
/*  Universal pre-step                                                 */
/* ------------------------------------------------------------------ */

/**
 * Ensure the controller has a base. Picks origin_zone_id if set,
 * else a claimed/held zone, else zone id 1.
 */
function aiEnsureBase($pdo, $c) {
    if (empty($c['can_build_base'])) return;
    if (!hasEnoughRessourcesToBuildBase($pdo, $c['id'])) return;

    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM {$prefix}locations
             WHERE controller_id = :cid AND is_base = True LIMIT 1"
        );
        $stmt->execute([':cid' => $c['id']]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) return;

        $zoneId = !empty($c['origin_zone_id']) ? (int)$c['origin_zone_id'] : null;
        if ($zoneId === null) {
            $stmt = $pdo->prepare(
                "SELECT id FROM {$prefix}zones
                 WHERE claimer_controller_id = :cid OR holder_controller_id = :cid
                 ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute([':cid' => $c['id']]);
            $zone = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($zone) $zoneId = (int)$zone['id'];
        }
        if ($zoneId === null) {
            $stmt = $pdo->prepare("SELECT id FROM {$prefix}zones ORDER BY id ASC LIMIT 1");
            $stmt->execute();
            $zone = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($zone) $zoneId = (int)$zone['id'];
        }
        if ($zoneId !== null) createBase($pdo, $c['id'], $zoneId);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): failed: ".$e->getMessage()."<br />";
    }
}

/**
 * Rebuild any owned, repairable locations while resources allow.
 */
function aiRebuildOwnedLocations($pdo, $c) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM {$prefix}locations
             WHERE controller_id = :cid AND can_be_repaired = True
             ORDER BY id ASC"
        );
        $stmt->execute([':cid' => $c['id']]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT failed: ".$e->getMessage()."<br />";
        return;
    }
    foreach ($locations as $location) {
        if (!hasEnoughRessourcesToRepairLocation($pdo, $c['id'])) break;
        $activate_json = json_decode($location['activate_json'], true);
        spendRessourcesToRepairLocation($pdo, $c['id']);
        updateLocation($pdo, $location, $activate_json);
    }
}

/* ------------------------------------------------------------------ */
/*  State transitions                                                  */
/* ------------------------------------------------------------------ */

function aiCheckStateTransition($pdo, $c, $turn_number) {
    $prefix = $_SESSION['GAME_PREFIX'];

    if ($c['ia_type'] === 'passive') {
        if (aiAnyWorkerLostThisTurn($pdo, $c['id'], $turn_number)) return 'searching';
        return 'passive';
    }
    if ($c['ia_type'] === 'searching') {
        if (aiKnownEnemiesCount($pdo, $c['id']) >= aiAggressionThreshold($pdo)) return 'aggressive';
        return 'searching';
    }
    if ($c['ia_type'] === 'aggressive') {
        if (aiBaseOrLocationAttackedThisTurn($pdo, $c['id'], $turn_number)) return 'violent';
        if (aiKnownEnemiesCount($pdo, $c['id']) === 0) return 'searching';
        return 'aggressive';
    }
    if ($c['ia_type'] === 'violent') {
        if (aiKnownEnemiesCount($pdo, $c['id']) === 0
                && aiKnownEnemyLocationsCount($pdo, $c['id']) === 0) return 'aggressive';
        return 'violent';
    }
    return $c['ia_type'];
}

function aiUpdateIaType($pdo, $controller_id, $newType) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "UPDATE {$prefix}controllers SET ia_type = :ia WHERE id = :cid"
        );
        $stmt->execute([':ia' => $newType, ':cid' => $controller_id]);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE failed: ".$e->getMessage()."<br />";
    }
}

function aiAggressionThreshold($pdo) {
    $v = (int) getConfig($pdo, 'aiAggressionThreshold');
    return $v > 0 ? $v : 2;
}

function aiAnyWorkerLostThisTurn($pdo, $controller_id, $turn_number) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM {$prefix}worker_actions wa
             JOIN {$prefix}controller_worker cw ON cw.worker_id = wa.worker_id
             WHERE cw.controller_id = :cid
               AND wa.turn_number = :turn
               AND wa.action_choice IN ('dead', 'captured')
             LIMIT 1"
        );
        $stmt->execute([':cid' => $controller_id, ':turn' => $turn_number]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function aiKnownEnemiesCount($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS n FROM {$prefix}controllers_known_enemies
             WHERE controller_id = :cid"
        );
        $stmt->execute([':cid' => $controller_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['n'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

function aiKnownEnemyLocationsCount($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS n FROM {$prefix}controller_known_locations ckl
             JOIN {$prefix}locations l ON l.id = ckl.location_id
             WHERE ckl.controller_id = :cid
               AND (l.controller_id IS NULL OR l.controller_id != :cid)
               AND l.can_be_destroyed = True"
        );
        $stmt->execute([':cid' => $controller_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['n'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

function aiBaseOrLocationAttackedThisTurn($pdo, $controller_id, $turn_number) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM {$prefix}location_attack_logs
             WHERE target_controller_id = :cid AND turn = :turn
             LIMIT 1"
        );
        $stmt->execute([':cid' => $controller_id, ':turn' => $turn_number]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/* ------------------------------------------------------------------ */
/*  Per-state behaviours                                               */
/* ------------------------------------------------------------------ */

/**
 * Passive: create at most 1 worker per turn in base zone; leave all
 * existing workers on action_choice='passive' (carried forward by the
 * end-turn pipeline's createNewTurnLines step).
 */
function aiPassiveBehaviour($pdo, $c, $turn_number) {
    $base_zone = aiBaseZone($pdo, $c['id']);
    if ($base_zone === null) return;
    aiRecruitOneInZone($pdo, $c, $base_zone, $turn_number);
}

/**
 * Searching: investigate workers, recruit 1 per turn, optionally move
 * idle workers to an unexplored adjacent (= claimed/held) zone.
 */
function aiSearchingBehaviour($pdo, $c, $turn_number) {
    $base_zone = aiBaseZone($pdo, $c['id']);
    if ($base_zone !== null) {
        aiRecruitOneInZone($pdo, $c, $base_zone, $turn_number);
    }
    aiSetWorkerActionsForState($pdo, $c, 'investigate', $turn_number);
    aiEquipPowers($pdo, $c);
}

/**
 * Aggressive: attack enemy workers in own/adjacent zones, others
 * investigate, recruit 1 per turn. Equip powers.
 */
function aiAggressiveBehaviour($pdo, $c, $turn_number) {
    $base_zone = aiBaseZone($pdo, $c['id']);
    if ($base_zone !== null) {
        aiRecruitOneInZone($pdo, $c, $base_zone, $turn_number);
    }
    aiSetAggressiveWorkerActions($pdo, $c, $turn_number, false);
    aiEquipPowers($pdo, $c);
}

/**
 * Violent: attack enemies anywhere known, attack discovered enemy
 * locations (capped at 5/turn per audit §12), recruit, equip.
 */
function aiViolentBehaviour($pdo, $c, $turn_number) {
    $base_zone = aiBaseZone($pdo, $c['id']);
    if ($base_zone !== null) {
        aiRecruitOneInZone($pdo, $c, $base_zone, $turn_number);
    }
    aiSetAggressiveWorkerActions($pdo, $c, $turn_number, true);
    aiQueueLocationAttacks($pdo, $c, 5);
    aiEquipPowers($pdo, $c);
}

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

function aiBaseZone($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT zone_id FROM {$prefix}locations
             WHERE controller_id = :cid AND is_base = True LIMIT 1"
        );
        $stmt->execute([':cid' => $controller_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['zone_id'] : null;
    } catch (PDOException $e) {
        return null;
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
        $proposal['zone_id'] = $zone_id;
        $proposal['controller_id'] = $c['id'];
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
        $proposal['zone_id'] = $zone_id;
        $proposal['controller_id'] = $c['id'];
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
 * Teach the first available faction Discipline + Transformation to
 * every worker missing one. Idempotent.
 */
function aiEquipPowers($pdo, $c) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT lpt.id AS link_id, pt.name AS type_name
             FROM {$prefix}link_power_type lpt
             JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.id
             JOIN {$prefix}faction_powers fp ON fp.power_id = lpt.power_id
             WHERE fp.faction_id = :fid
               AND pt.name IN ('Discipline', 'Transformation')
             ORDER BY pt.name, lpt.id ASC"
        );
        $stmt->execute([':fid' => $c['faction_id']]);
        $powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    $disciplineLink = null;
    $transformLink = null;
    foreach ($powers as $p) {
        if ($p['type_name'] === 'Discipline' && $disciplineLink === null) $disciplineLink = $p['link_id'];
        if ($p['type_name'] === 'Transformation' && $transformLink === null) $transformLink = $p['link_id'];
    }
    if ($disciplineLink === null && $transformLink === null) return;

    try {
        $stmt = $pdo->prepare(
            "SELECT w.id FROM {$prefix}workers w
             JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
             WHERE cw.controller_id = :cid"
        );
        $stmt->execute([':cid' => $c['id']]);
        $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    foreach ($workers as $w) {
        if ($disciplineLink !== null && !aiWorkerHasPowerType($pdo, $w['id'], 'Discipline')) {
            upgradeWorker($pdo, $w['id'], $disciplineLink);
        }
        if ($transformLink !== null && !aiWorkerHasPowerType($pdo, $w['id'], 'Transformation')) {
            upgradeWorker($pdo, $w['id'], $transformLink);
        }
    }
}

function aiWorkerHasPowerType($pdo, $worker_id, $type_name) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM {$prefix}worker_powers wp
             JOIN {$prefix}link_power_type lpt ON lpt.id = wp.link_power_type_id
             JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.id
             WHERE wp.worker_id = :wid AND pt.name = :tn LIMIT 1"
        );
        $stmt->execute([':wid' => $worker_id, ':tn' => $type_name]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return true;
    }
}

/**
 * Set every alive worker's action_choice to the given single action.
 * Used by searching (investigate) and passive carry-forward fallback.
 */
function aiSetWorkerActionsForState($pdo, $c, $action, $turn_number) {
    $workers = aiAliveWorkers($pdo, $c['id'], $turn_number);
    foreach ($workers as $w) {
        activateWorker($pdo, $w['id'], $action, null);
    }
}

/**
 * Aggressive/violent worker action assignment.
 * $globalAttack=false (aggressive): only attack enemies in worker's
 *   current zone or controller's claimer/holder zones.
 * $globalAttack=true (violent): attack any known enemy worker anywhere.
 * Workers without an attack target fall back to investigate.
 */
function aiSetAggressiveWorkerActions($pdo, $c, $turn_number, $globalAttack) {
    $workers = aiAliveWorkers($pdo, $c['id'], $turn_number);
    if (empty($workers)) return;

    $prefix = $_SESSION['GAME_PREFIX'];
    $ownZones = $globalAttack ? null : aiOwnZoneIds($pdo, $c['id']);

    foreach ($workers as $w) {
        try {
            if ($globalAttack) {
                $eStmt = $pdo->prepare(
                    "SELECT discovered_worker_id FROM {$prefix}controllers_known_enemies
                     WHERE controller_id = :cid
                     ORDER BY discovered_worker_id ASC LIMIT 1"
                );
                $eStmt->execute([':cid' => $c['id']]);
            } else {
                $eStmt = $pdo->prepare(
                    "SELECT discovered_worker_id FROM {$prefix}controllers_known_enemies
                     WHERE controller_id = :cid AND zone_id = :zid
                     ORDER BY discovered_worker_id ASC LIMIT 1"
                );
                $eStmt->execute([':cid' => $c['id'], ':zid' => $w['zone_id']]);
            }
            $enemy = $eStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $enemy = null;
        }
        if ($enemy) {
            activateWorker($pdo, $w['id'], 'attack', ['worker_'.$enemy['discovered_worker_id']]);
        } else {
            activateWorker($pdo, $w['id'], 'investigate', null);
        }
    }
}

function aiAliveWorkers($pdo, $controller_id, $turn_number) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $inactive = "'".implode("','", INACTIVE_ACTIONS)."'";
    try {
        $sql = sprintf(
            "SELECT w.id, w.zone_id FROM {$prefix}workers w
             JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
             LEFT JOIN {$prefix}worker_actions wa
                 ON wa.worker_id = w.id AND wa.turn_number = :turn
             WHERE cw.controller_id = :cid AND cw.is_primary_controller = %s
               AND (wa.action_choice IS NULL OR wa.action_choice NOT IN (%s))
             ORDER BY w.id ASC",
            ($_SESSION['DBTYPE'] == 'mysql') ? '1' : 'true',
            $inactive
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cid' => $controller_id, ':turn' => $turn_number]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function aiOwnZoneIds($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM {$prefix}zones
             WHERE claimer_controller_id = :cid OR holder_controller_id = :cid"
        );
        $stmt->execute([':cid' => $controller_id]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Return the deduped int[] of zone ids the controller owns plus one
 * hop of adjacency from each.
 */
function aiOwnedAndAdjacentZoneIds($pdo, $controller_id) {
    $owned = aiOwnZoneIds($pdo, $controller_id);
    $set = [];
    foreach ($owned as $z) {
        $set[(int)$z] = true;
        foreach (getAdjacentZoneIds($pdo, (int)$z) as $adj) {
            $set[(int)$adj] = true;
        }
    }
    return array_keys($set);
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
