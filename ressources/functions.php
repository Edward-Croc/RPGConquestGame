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
            if ($controllerRessource['is_stored'] === TRUE) {
                $controllerRessource['amount_stored'] += $controllerRessource['amount'];
            }
            //    - If ressource_config.is_rollable is FALSE, set amount to 0
            if ($controllerRessource['is_rollable'] === FALSE) {
                $controllerRessource['amount'] = 0;
            }
            //    - Add end_turn_gain to amount
            $controllerRessource['amount'] += $controllerRessource['end_turn_gain'];
            $prefix = $_SESSION['GAME_PREFIX'];
            // Update the ressources_controller table
            $sql = "UPDATE {$prefix}controller_ressources SET amount = :amount, amount_stored = :amount_stored WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':amount' => $controllerRessource['amount'], ':amount_stored' => $controllerRessource['amount_stored'], ':id' => $controllerRessource['rc_id']]);
            if (!$stmt->rowCount()) {
                echo __FUNCTION__."(): Failed to update controller_ressources: " . $controllerRessource['rc_id'] . "<br />";
            }
        }
    }
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