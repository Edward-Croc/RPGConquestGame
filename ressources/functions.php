<?php

/**
 * Update the ressources for a controller
 *
 * @param PDO $pdo
 * @param array $mechanics
 *
 * @return bool
 */
function updateRessources($pdo, $mechanics) {
    echo '<div> <h3>  updateRessources : </h3> ';

    /** Foreach controller :
     *  - If corresponding line controller_ressources exists,
     *    - If ressource_config.is_stored is TRUE, add amount to amount_stored
     *    - If ressource_config.is_rollable is FALSE, set amount to 0
     *    - Add end_turn_gain to amount
    */
    $prefix = $_SESSION['GAME_PREFIX'];
    // Get all controllers from controllers table
    $sql = "SELECT * FROM {$prefix}controllers";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $controllers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // For each controller :
    foreach ($controllers as $controller) {
        //  - Get the corresponding lines controller_ressources
        $controllerRessources = getRessources($pdo, $controller['id']);
        // For each ressource :
        foreach ($controllerRessources as $controllerRessource) {
            //    - If ressource_config.is_stored is TRUE, add amount to amount_stored
            if (!empty($controllerRessource['is_stored'])) {
                $controllerRessource['amount_stored'] += $controllerRessource['amount'];
            }
            //    - If ressource_config.is_rollable is FALSE, set amount to 0
            if (empty($controllerRessource['is_rollable'])) {
                $controllerRessource['amount'] = 0;
            }
            //    - Add end_turn_gain to amount
            $controllerRessource['amount'] += $controllerRessource['end_turn_gain'];
            $prefix = $_SESSION['GAME_PREFIX'];
            // Update the ressources_controller table
            $sql = "UPDATE {$prefix}controller_ressources SET amount = :amount, amount_stored = :amount_stored WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':amount' => $controllerRessource['amount'], ':amount_stored' => $controllerRessource['amount_stored'], ':id' => $controllerRessource['rc_id']]);
        }
    }
    echo '</div> ';
    return true;
}

/**
 * Get the ressources for a controller
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return array
 * 
 */
function getRessources($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $sql = "SELECT rc.id as rc_id, rc.*, r.id as ressource_id, r.*
        FROM {$prefix}controller_ressources rc
        JOIN {$prefix}ressources_config r ON rc.ressource_id = r.id
        WHERE rc.controller_id = :controller_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':controller_id' => $controller_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Apply the hide_when_zero render filter (strict no-presence): drop rows
 * whose config flag is set AND amount, amount_stored and end_turn_gain
 * are all zero. Caller responsibility — internal checks (cost, gating)
 * must NOT filter, since a hidden ressource at non-zero stays usable.
 *
 * When $gainEstimate is provided (map of ressource_id => ['total' => int]),
 * a positive next-turn gain prediction also keeps the row visible — used
 * by the Ressources page so a ressource the controller is about to acquire
 * surfaces ahead of its first non-zero turn. The faction-page recap omits
 * this param and stays strict.
 */
function filterVisibleRessources(array $ressources, array $gainEstimate = []): array {
    return array_values(array_filter($ressources, function ($r) use ($gainEstimate) {
        if (empty($r['hide_when_zero'])) return true;
        if ((int)$r['amount'] !== 0 || (int)$r['amount_stored'] !== 0 || (int)$r['end_turn_gain'] !== 0) {
            return true;
        }
        $rid = (int)$r['ressource_id'];
        return (int)($gainEstimate[$rid]['total'] ?? 0) > 0;
    }));
}

/**
 * Does the controller have enought ressources to build a base ?
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return bool
 */
function hasEnoughRessourcesToBuildBase($pdo, $controller_id) {
    if (getConfig($pdo, 'ressource_management') === 'TRUE') {
        $controllerRessources = getRessources($pdo, $controller_id);
        foreach ($controllerRessources as $controllerRessource) {
            if ($controllerRessource['base_building_cost'] > 0) {
                if ($controllerRessource['amount'] < $controllerRessource['base_building_cost']) {
                    return false;
                }
            }
        }
    }
    return true;
}

/**
 * Spend the ressources to build a base. Atomic across all costed ressources:
 * if any consumeRessource fails (insufficient stock), the whole transaction
 * rolls back and the caller receives false. Returns true also when
 * ressource_management is off, or when no ressource carries a positive
 * base_building_cost (nothing to spend).
 *
 * @return bool true on full deduction (or no-op), false on any shortfall.
 */
function spendRessourcesToBuildBase(PDO $pdo, int $controller_id): bool {
    return spendRessourcesByCostField($pdo, $controller_id, 'base_building_cost');
}

/**
 * Get the cost HTML to build a base
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return string
 */
function buildBaseCostHTML($pdo, $controller_id) {
    $html = '';
    if (getConfig($pdo, 'ressource_management') === 'TRUE') {
        $controllerRessources = getRessources($pdo, $controller_id);
        if (!empty($controllerRessources)) {
            $html = '<br> Coût de construction : ';
            foreach ($controllerRessources as $controllerRessource) {
                if ($controllerRessource['base_building_cost'] > 0) {
                    $html .= sprintf('%s %s', $controllerRessource['base_building_cost'], $controllerRessource['ressource_name']);
                }
            }
        }
    }
    return $html;
}

/**
 * Does the controller have enought ressources to move a base ?
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return bool
 */
function hasEnoughRessourcesToMoveBase($pdo, $controller_id) {
    if (getConfig($pdo, 'ressource_management') === 'TRUE') {
        $controllerRessources = getRessources($pdo, $controller_id);
        foreach ($controllerRessources as $controllerRessource) {
            if ($controllerRessource['base_moving_cost'] > 0) {
                if ($controllerRessource['amount'] < $controllerRessource['base_moving_cost']) {
                    return false;
                }
            }
        }
    }
    return true;
}

/**
 * Spend the ressources to move a base. Atomic; rolls back on any shortfall.
 * See spendRessourcesToBuildBase for the contract.
 *
 * @return bool true on full deduction (or no-op), false on any shortfall.
 */
function spendRessourcesToMoveBase(PDO $pdo, int $controller_id): bool {
    return spendRessourcesByCostField($pdo, $controller_id, 'base_moving_cost');
}

/**
 * Get the cost HTML to move a base
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return string
 */
function moveBaseCostHTML($pdo, $controller_id) {
    $html = '';
    if (getConfig($pdo, 'ressource_management') === 'TRUE') {
        $controllerRessources = getRessources($pdo, $controller_id);
        if (!empty($controllerRessources)) {
            $html = '<br> Coût : ';
            foreach ($controllerRessources as $controllerRessource) {
                if ($controllerRessource['base_moving_cost'] > 0) {
                    $html .= sprintf('%s %s', $controllerRessource['base_moving_cost'], $controllerRessource['ressource_name']);
                }
            }
        }
    }
    return $html;
}

/**
 * Does the controller have enought ressources to repair a location ?
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return bool
 */
function hasEnoughRessourcesToRepairLocation($pdo, $controller_id) {

    if (getConfig($pdo, 'ressource_management') === 'TRUE') {
        $controllerRessources = getRessources($pdo, $controller_id);
        foreach ($controllerRessources as $controllerRessource) {
            if ($controllerRessource['location_repaire_cost'] > 0) {
                if ($controllerRessource['amount'] < $controllerRessource['location_repaire_cost']) {
                    return false;
                }
            }
        }
    }
    return true;
}

/**
 * Spend the ressources to repair a location
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return bool
 */
function spendRessourcesToRepairLocation(PDO $pdo, int $controller_id): bool {
    return spendRessourcesByCostField($pdo, $controller_id, 'location_repaire_cost');
}

/**
 * Shared deducting primitive for the three spend* operations. Loops over
 * the controller's ressources_config, filters to the cost field passed in,
 * and composes consumeRessource calls inside a single transaction. Any
 * shortfall (or `consumeRessource` returning false for any other reason)
 * triggers a rollback so partial deductions cannot ship.
 *
 * Cost-zero ressources never reach consumeRessource — the filter elides
 * them, so the old "rowCount on no-op UPDATE" noise is impossible by
 * construction.
 *
 * @param string $costField one of: base_building_cost, base_moving_cost,
 *                          location_repaire_cost. Whitelist enforced.
 */
function spendRessourcesByCostField(PDO $pdo, int $controller_id, string $costField): bool {
    static $allowed = ['base_building_cost', 'base_moving_cost', 'location_repaire_cost'];
    if (!in_array($costField, $allowed, true)) {
        error_log(__FUNCTION__.": unknown cost field {$costField}");
        return false;
    }
    if (getConfig($pdo, 'ressource_management') !== 'TRUE') return true;

    $costed = [];
    foreach (getRessources($pdo, $controller_id) as $r) {
        if ((int)$r[$costField] > 0) {
            $costed[] = ['ressource_id' => (int)$r['ressource_id'], 'amount' => (int)$r[$costField]];
        }
    }
    if (empty($costed)) return true;

    $pdo->beginTransaction();
    foreach ($costed as $row) {
        if (!consumeRessource($pdo, $controller_id, $row['ressource_id'], $row['amount'])) {
            $pdo->rollBack();
            return false;
        }
    }
    $pdo->commit();
    return true;
}

/**
 * Get the cost HTML to repair a location
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return string
 */
function repairLocationCostHTML($pdo, $controller_id) {
    $html = '';
    if (getConfig($pdo, 'ressource_management') === 'TRUE') {
        $controllerRessources = getRessources($pdo, $controller_id);
        if (!empty($controllerRessources)) {
            $html = '<br> Coût : ';
            foreach ($controllerRessources as $controllerRessource) {
                if ($controllerRessource['location_repaire_cost'] > 0) {
                    $html .= sprintf('%s %s', $controllerRessource['location_repaire_cost'], $controllerRessource['ressource_name']);
                }
            }
        }
    }
    return $html;
}

/**
 * Natural-French rendering of a gain rule's condition for display.
 * $zoneTypeLabel is the resolved `textForZoneType` config ("zone" / "quartier"
 * / "territoire"). Caller fetches it once and passes it in.
 *
 * @param array  $rule
 * @param string $zoneTypeLabel
 *
 * @return string
 */
function gainRuleToText(array $rule, $zoneTypeLabel = 'zone') {
    $type = $rule['condition']['type'] ?? '';
    switch ($type) {
        case 'holds_zone':
            if (isset($rule['condition']['zone_id'])) {
                return sprintf('pour le %s tenu (id %d)', $zoneTypeLabel, (int)$rule['condition']['zone_id']);
            }
            return sprintf('par %s tenu', $zoneTypeLabel);
        case 'claims_zone':
            if (isset($rule['condition']['zone_id'])) {
                return sprintf('pour le %s revendiqué (id %d)', $zoneTypeLabel, (int)$rule['condition']['zone_id']);
            }
            return sprintf('par %s sous notre bannière ce tour', $zoneTypeLabel);
        case 'owns_location_type':
            $tag = $rule['condition']['location_type'] ?? null;
            if ($tag === 'temple')   return 'par temple possédé';
            if ($tag === 'fortress') return 'par forteresse possédée';
            if ($tag !== null)       return sprintf('par lieu de type "%s" possédé', $tag);
            return 'par lieu possédé';
        default:
            return 'selon règle';
    }
}

/**
 * Compute the next-turn gain estimate for a single controller, grouped by timing.
 * Returns a map: ressource_id => [
 *   'before_claim' => [ ['amount', 'text', 'count', 'total'], ... ],
 *   'after_claim'  => [ ... ],
 *   'total'        => sum of all rule contributions for this ressource,
 * ].
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return array
 */
function ressourceGainEstimateForController($pdo, $controller_id) {
    require_once __DIR__ . '/../mechanics/ressourceGainMechanic.php';
    $prefix = $_SESSION['GAME_PREFIX'];
    $estimate = [];
    $zoneTypeLabel = getConfig($pdo, 'textForZoneType') ?: 'zone';

    $mechanics = getMechanics($pdo);
    $currentTurn = (int)($mechanics['turncounter'] ?? 0);

    try {
        $stmt = $pdo->prepare("SELECT id AS ressource_id, gain_rules
            FROM {$prefix}ressources_config
            WHERE gain_rules IS NOT NULL");
        $stmt->execute();
        $configRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT gain_rules failed: ".$e->getMessage()."<br />";
        return [];
    }

    foreach ($configRows as $row) {
        $ressourceId = (int)$row['ressource_id'];
        $rules = json_decode($row['gain_rules'], true);
        if (!is_array($rules)) continue;
        $bucket = ['before_claim' => [], 'after_claim' => [], 'total' => 0];

        foreach ($rules as $rule) {
            if (!is_array($rule)) continue;
            if (!isset($rule['amount']) || !is_numeric($rule['amount'])) continue;
            if (!isset($rule['timing']) || !in_array($rule['timing'], ['before_claim', 'after_claim'], true)) continue;
            if (!isset($rule['condition']['type'])) continue;
            if (isset($rule['unlock_turn']) && (int)$rule['unlock_turn'] > $currentTurn) continue;

            $matches = ressourceGainEvaluateCondition($pdo, $rule['condition']);
            $count = 0;
            foreach ($matches as $match) {
                if ((int)$match['controller_id'] === (int)$controller_id) {
                    $count = (int)$match['match_count'];
                    break;
                }
            }
            $amount = (int)$rule['amount'];
            $total = $amount * $count;
            $bucket[$rule['timing']][] = [
                'amount' => $amount,
                'text'   => gainRuleToText($rule, $zoneTypeLabel),
                'count'  => $count,
                'total'  => $total,
            ];
            $bucket['total'] += $total;
        }
        $estimate[$ressourceId] = $bucket;
    }
    return $estimate;
}

/**
 * Atomically decrement a controller's ressource amount by $amount.
 * Single-row UPDATE with the `WHERE amount >= :amt` guard (TOCTOU-safe).
 * Caller is responsible for the surrounding transaction.
 *
 * @return bool true on success; false on insufficient amount, missing row, or DB error.
 */
function consumeRessource(PDO $pdo, int $controller_id, int $ressource_id, int $amount): bool {
    if ($amount <= 0 || $controller_id <= 0 || $ressource_id <= 0) return false;
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare("UPDATE {$prefix}controller_ressources
            SET amount = amount - :amt
            WHERE controller_id = :cid AND ressource_id = :rid AND amount >= :amt2");
        $stmt->execute([
            ':amt'  => $amount,
            ':amt2' => $amount,
            ':cid'  => $controller_id,
            ':rid'  => $ressource_id,
        ]);
        return $stmt->rowCount() === 1;
    } catch (PDOException $e) {
        error_log(__FUNCTION__.": ".$e->getMessage());
        return false;
    }
}

/**
 * Process a ressource gift from $giver_id to $post['target_controller_id'].
 * Validates inputs, wraps the decrement / increment / log writes in a
 * transaction so partial failure cannot vanish ressources.
 *
 * @param PDO   $pdo
 * @param int   $giver_id
 * @param array $post  expects 'ressource_id', 'target_controller_id', 'amount'
 *
 * @return array ['success' => bool, 'message' => string]
 */
function giftRessource($pdo, $giver_id, array $post) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $ressource_id = (int)($post['ressource_id'] ?? 0);
    $target_id    = (int)($post['target_controller_id'] ?? 0);
    $amount       = (int)($post['amount'] ?? 0);
    $giver_id     = (int)$giver_id;

    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Quantité invalide.'];
    }
    if ($target_id <= 0 || $target_id === $giver_id) {
        return ['success' => false, 'message' => 'Faction cible invalide.'];
    }
    if ($ressource_id <= 0) {
        return ['success' => false, 'message' => 'Ressource invalide.'];
    }

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM {$prefix}controllers WHERE id = :id AND secret_controller IS NOT TRUE");
        $stmt->execute([':id' => $target_id]);
        if (!$stmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Faction cible invalide.'];
        }

        $stmt = $pdo->prepare("SELECT 1 FROM {$prefix}ressources_config WHERE id = :id");
        $stmt->execute([':id' => $ressource_id]);
        if (!$stmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Ressource introuvable.'];
        }

        $stmt = $pdo->prepare("SELECT amount FROM {$prefix}controller_ressources WHERE controller_id = :cid AND ressource_id = :rid");
        $stmt->execute([':cid' => $giver_id, ':rid' => $ressource_id]);
        $giverAmount = $stmt->fetchColumn();
        if ($giverAmount === false) {
            return ['success' => false, 'message' => 'Vous ne possédez pas cette ressource.'];
        }
        if ((int)$giverAmount < $amount) {
            return ['success' => false, 'message' => 'Quantité supérieure à votre stock actuel.'];
        }

        $stmt = $pdo->query("SELECT turncounter FROM {$prefix}mechanics LIMIT 1");
        $turn = (int)($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur de validation.'];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE {$prefix}controller_ressources
            SET amount = amount - :amt
            WHERE controller_id = :cid AND ressource_id = :rid AND amount >= :amt2");
        $stmt->execute([':amt' => $amount, ':amt2' => $amount, ':cid' => $giver_id, ':rid' => $ressource_id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Échec : stock insuffisant ou modifié.'];
        }

        $stmt = $pdo->prepare("UPDATE {$prefix}controller_ressources
            SET amount = amount + :amt
            WHERE controller_id = :cid AND ressource_id = :rid");
        $stmt->execute([':amt' => $amount, ':cid' => $target_id, ':rid' => $ressource_id]);
        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("INSERT INTO {$prefix}controller_ressources (controller_id, ressource_id, amount, amount_stored, end_turn_gain)
                VALUES (:cid, :rid, :amt, 0, 0)");
            $stmt->execute([':cid' => $target_id, ':rid' => $ressource_id, ':amt' => $amount]);
        }

        $stmt = $pdo->prepare("INSERT INTO {$prefix}ressource_gift_logs
            (giver_controller_id, recipient_controller_id, ressource_id, amount, turn)
            VALUES (:giver, :recipient, :rid, :amt, :turn)");
        $stmt->execute([':giver' => $giver_id, ':recipient' => $target_id, ':rid' => $ressource_id, ':amt' => $amount, ':turn' => $turn]);

        $pdo->commit();
        return ['success' => true, 'message' => sprintf('Don de %d effectué.', $amount)];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Erreur lors du don.'];
    }
}

/**
 * Fetch ressource gifts received by a controller, newest first.
 * Each row: ['turn', 'amount', 'giver', 'ressource'].
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return array
 */
function getRessourceGiftsReceived($pdo, $controller_id) {
    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $sql = "SELECT
            l.turn,
            l.amount,
            CONCAT(c.firstname, ' ', c.lastname, ' (', f.name, ')') AS giver,
            rc.ressource_name AS ressource
        FROM {$prefix}ressource_gift_logs l
        JOIN {$prefix}controllers c ON l.giver_controller_id = c.id
        LEFT JOIN {$prefix}factions f ON c.faction_id = f.ID
        JOIN {$prefix}ressources_config rc ON l.ressource_id = rc.id
        WHERE l.recipient_controller_id = :recipient_id
        ORDER BY l.turn DESC, l.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':recipient_id' => $controller_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT ressource_gift_logs failed: ".$e->getMessage()."<br />";
        return [];
    }
}