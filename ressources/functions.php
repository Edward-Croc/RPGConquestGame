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
 * Spend the ressources to build a base
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return bool
 */
function spendRessourcesToBuildBase($pdo, $controller_id) {
    if (getConfig($pdo, 'ressource_management') === 'TRUE') {
        $controllerRessources = getRessources($pdo, $controller_id);
        foreach ($controllerRessources as $controllerRessource) {
            if ($controllerRessource['base_building_cost'] > 0) {
                $controllerRessource['amount'] -= $controllerRessource['base_building_cost'];
            }
            $prefix = $_SESSION['GAME_PREFIX'];
            $sql = "UPDATE {$prefix}controller_ressources SET amount = :amount WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':amount' => $controllerRessource['amount'], ':id' => $controllerRessource['rc_id']]);
            if (!$stmt->rowCount()) {
                echo __FUNCTION__."(): Failed to update controller_ressources: " . $controllerRessource['rc_id'] . "<br />";
            }
        }
    }
    return true;
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
 * Spend the ressources to move a base
 *
 * @param PDO $pdo
 * @param int $controller_id
 *
 * @return bool
 */
function spendRessourcesToMoveBase($pdo, $controller_id) {
    if (getConfig($pdo, 'ressource_management') === 'TRUE') {
        $prefix = $_SESSION['GAME_PREFIX'];
        $controllerRessources = getRessources($pdo, $controller_id);
        foreach ($controllerRessources as $controllerRessource) {
            if ($controllerRessource['base_moving_cost'] > 0) {
                $controllerRessource['amount'] -= $controllerRessource['base_moving_cost'];
            }
            $sql = "UPDATE {$prefix}controller_ressources SET amount = :amount WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':amount' => $controllerRessource['amount'], ':id' => $controllerRessource['rc_id']]);
            if (!$stmt->rowCount()) {
                echo __FUNCTION__."(): Failed to update controller_ressources: " . $controllerRessource['rc_id'] . "<br />";
            }
        }
    }
    return true;
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
function spendRessourcesToRepairLocation($pdo, $controller_id) {
    if (getConfig($pdo, 'ressource_management') === 'TRUE') {
        $controllerRessources = getRessources($pdo, $controller_id);
        foreach ($controllerRessources as $controllerRessource) {
            if ($controllerRessource['location_repaire_cost'] > 0) {
                $controllerRessource['amount'] -= $controllerRessource['location_repaire_cost'];
            }
            $prefix = $_SESSION['GAME_PREFIX'];
            $sql = "UPDATE {$prefix}controller_ressources SET amount = :amount WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':amount' => $controllerRessource['amount'], ':id' => $controllerRessource['rc_id']]);
            if (!$stmt->rowCount()) {
                echo __FUNCTION__."(): Failed to update controller_ressources: " . $controllerRessource['rc_id'] . "<br />";
            }
        }
    }
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