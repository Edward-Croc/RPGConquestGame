<?php

/**
 * Recruit ALL available slots this turn: first-come first, then recrutement.
 * Counters reset at EOT, so this drains both per-turn caps every turn.
 */
function aiRecruit($pdo, $c, $zone_id, $turn_number) {
    while (canStartFirstCome($pdo, $c['id'])) {
        if (!aiRecruitOneSlot($pdo, $c, $zone_id, 'first_come')) break;
    }
    while (canStartRecrutement($pdo, $c['id'], $turn_number)) {
        if (!aiRecruitOneSlot($pdo, $c, $zone_id, 'recrutement')) break;
    }
}

/**
 * Recruit one worker into the given slot type. Generates recrutement_nb_choices
 * proposals, fetches the discipline pool once, then picks the (proposal,
 * discipline) combo scoring best for the controller's recruit_strategy.
 */
function aiRecruitOneSlot($pdo, $c, $zone_id, $slot_type) {
    $prefix = $_SESSION['GAME_PREFIX'];

    $nbChoices = (int) getConfig($pdo, 'recrutement_nb_choices');
    if ($nbChoices < 1) $nbChoices = 1;

    $proposals = [];
    for ($i = 0; $i < $nbChoices; $i++) {
        $cand = generateNewWorker($pdo, $c['id'], $slot_type);
        if (empty($cand) || empty($cand['lastname'])) continue;
        if (aiWorkerExistsForController($pdo, (int)$c['id'], (string)$cand['firstname'], (string)$cand['lastname'], (int)$cand['origin_id'])) {
            continue;
        }
        $proposals[] = $cand;
    }
    if (empty($proposals)) return false;

    $nbDisciplines = (int) getConfig($pdo, 'recrutement_disciplines');
    $disciplinePool = [];
    if ($nbDisciplines > 0) {
        $pool = getPowersByType($pdo, '3', $c['id'], true);
        if (is_array($pool)) $disciplinePool = $pool;
    }

    $strategy = aiRecruitStrategy($pdo, (int) $c['id']);
    $poolSums = ($strategy === 'balance') ? aiPoolStatSums($pdo, (int) $c['id']) : ['enquete' => 0, 'attack' => 0];
    $postEquip = ($strategy === 'balance' && $nbDisciplines === 0)
        ? aiPostRecruitDisciplineStats($pdo, (int)($c['faction_id'] ?? 0))
        : ['enquete' => 0, 'attack' => 0];

    $best = null;
    $bestScore = null;
    $bestTie = null;
    foreach ($proposals as $prop) {
        $propEnq = (int)($prop['power_1']['enquete'] ?? 0) + (int)($prop['power_2']['enquete'] ?? 0);
        $propAtk = (int)($prop['power_1']['attack']  ?? 0) + (int)($prop['power_2']['attack']  ?? 0);

        $discOptions = (!empty($disciplinePool)) ? $disciplinePool : [null];
        foreach ($discOptions as $disc) {
            $discEnq = ($disc !== null) ? (int)($disc['enquete'] ?? 0) : 0;
            $discAtk = ($disc !== null) ? (int)($disc['attack']  ?? 0) : 0;
            $comboEnq = $propEnq + $discEnq + $postEquip['enquete'];
            $comboAtk = $propAtk + $discAtk + $postEquip['attack'];

            if ($strategy === 'balance') {
                $newEnq = $poolSums['enquete'] + $comboEnq;
                $newAtk = $poolSums['attack']  + $comboAtk;
                $score = -abs($newEnq - $newAtk);
                $tie = $comboEnq + $comboAtk;
            } else {
                $score = max($comboEnq, $comboAtk);
                $tie = $comboEnq + $comboAtk;
            }

            if ($bestScore === null
                || $score > $bestScore
                || ($score === $bestScore && $tie > $bestTie)) {
                $bestScore = $score;
                $bestTie = $tie;
                $best = ['proposal' => $prop, 'discipline' => $disc];
            }
        }
    }
    if ($best === null) return false;

    $proposal = $best['proposal'];
    $proposal['zone_id'] = $zone_id;
    $proposal['controller_id'] = $c['id'];
    if (!empty($proposal['power_1']['id'])) $proposal['power_hobby_id']  = $proposal['power_1']['id'];
    if (!empty($proposal['power_2']['id'])) $proposal['power_metier_id'] = $proposal['power_2']['id'];
    if ($best['discipline'] !== null && !empty($best['discipline']['link_power_type_id'])) {
        $proposal['discipline'] = $best['discipline']['link_power_type_id'];
    }

    if (!createWorker($pdo, $proposal)) return false;

    $col = $slot_type === 'first_come' ? 'turn_firstcome_workers' : 'turn_recruited_workers';
    $stmt = $pdo->prepare(
        "UPDATE {$prefix}controllers SET {$col} = {$col} + 1 WHERE id = :cid"
    );
    $stmt->execute([':cid' => $c['id']]);
    return true;
}

/**
 * Stats of the faction discipline that aiEquipPowers will add to this
 * worker after recruit. Lets the balance picker preview the final
 * stat profile when recrutement_disciplines = 0.
 */
function aiPostRecruitDisciplineStats($pdo, $faction_id) {
    if (empty($faction_id)) return ['enquete' => 0, 'attack' => 0];
    $prefix = $_SESSION['GAME_PREFIX'];
    $sql = "SELECT COALESCE(p.enquete, 0) AS enquete, COALESCE(p.attack, 0) AS attack
            FROM {$prefix}faction_powers fp
            JOIN {$prefix}link_power_type lpt ON lpt.id = fp.link_power_type_id
            JOIN {$prefix}power_types pt ON pt.id = lpt.power_type_id
            JOIN {$prefix}powers p ON p.id = lpt.power_id
            WHERE fp.faction_id = :fid AND pt.name = 'Discipline'
            ORDER BY lpt.id ASC LIMIT 1";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':fid' => $faction_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['enquete' => 0, 'attack' => 0];
    }
    if (!$row) return ['enquete' => 0, 'attack' => 0];
    return [
        'enquete' => (int) ($row['enquete'] ?? 0),
        'attack'  => (int) ($row['attack']  ?? 0),
    ];
}

/**
 * Sum of enquete + attack across this controller's alive workers' powers.
 * Used by the 'balance' recruit strategy to score a candidate combo by
 * how close it pushes the pool toward |attack - enquete| = 0.
 */
function aiPoolStatSums($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $sql = "SELECT
              COALESCE(SUM(p.enquete), 0) AS enquete,
              COALESCE(SUM(p.attack),  0) AS attack
            FROM {$prefix}workers w
            JOIN {$prefix}controller_worker cw ON cw.worker_id = w.id
            LEFT JOIN {$prefix}worker_powers wp ON wp.worker_id = w.id
            LEFT JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.id
            LEFT JOIN {$prefix}powers p ON p.id = lpt.power_id
            WHERE cw.controller_id = :cid";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cid' => $controller_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['enquete' => 0, 'attack' => 0];
    }
    return [
        'enquete' => (int) ($row['enquete'] ?? 0),
        'attack'  => (int) ($row['attack']  ?? 0),
    ];
}

// Returns true if a worker with the same firstname+lastname+origin_id is already linked to this controller.
function aiWorkerExistsForController(PDO $pdo, int $controller_id, string $firstname, string $lastname, int $origin_id): bool {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare("SELECT w.id AS id FROM {$prefix}workers AS w
        INNER JOIN {$prefix}controller_worker AS cw ON cw.worker_id = w.id
        WHERE w.firstname = :firstname AND w.lastname = :lastname AND w.origin_id = :origin_id AND cw.controller_id = :controller_id");
        $stmt->bindParam(':firstname', $firstname, PDO::PARAM_STR);
        $stmt->bindParam(':lastname', $lastname, PDO::PARAM_STR);
        $stmt->bindParam(':origin_id', $origin_id, PDO::PARAM_INT);
        $stmt->bindParam(':controller_id', $controller_id, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        return false;
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return !empty($rows);
}
