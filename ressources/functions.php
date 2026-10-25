<?php

/**
 * Update the ressources for a controller.
 *
 * @param PDO   $pdo        : DB connection
 * @param array $mechanics  : mechanics row (turncounter etc.)
 *
 * @return bool true on success
 */
function updateRessources(PDO $pdo, array $mechanics): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START', ['mechanics' => $mechanics], 'debug');

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
 * Get the ressources for a controller.
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return array joined controller_ressources + ressources_config rows
 */
function getRessources(PDO $pdo, int $controller_id): array
{
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
 *
 * @param array $ressources    : rows from getRessources
 * @param array $gainEstimate  : optional map ressource_id => ['total' => int]
 *
 * @return array filtered rows (0-indexed)
 */
function filterVisibleRessources(array $ressources, array $gainEstimate = []): array
{
    return array_values(array_filter($ressources, function ($r) use ($gainEstimate) {
        if (empty($r['hide_when_zero'])) {
            return true;
        }
        if ((int)$r['amount'] !== 0 || (int)$r['amount_stored'] !== 0 || (int)$r['end_turn_gain'] !== 0) {
            return true;
        }
        $rid = (int)$r['ressource_id'];
        return (int)($gainEstimate[$rid]['total'] ?? 0) > 0;
    }));
}

/**
 * List the configured ressources an action costs, with the controller's stock of each.
 * A ressource the controller holds no row for is dropped when the freeWithoutRessource
 * config lists the cost field, and kept with a null amount otherwise, which no stock covers.
 *
 * @param PDO    $pdo            : DB connection
 * @param int    $controller_id  : controller id
 * @param string $costField      : one of base_building_cost, base_moving_cost, location_repaire_cost
 *
 * @return array|null rows ['ressource_id' => int, 'ressource_name' => string, 'cost' => int, 'amount' => int|null],
 *                    [] when ressource management is off, null on an unknown field or a SQL failure
 */
function getRessourceCostsByCostField(PDO $pdo, int $controller_id, string $costField): array|null
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, ['costField' => $costField], 'debug');

    static $allowed = ['base_building_cost', 'base_moving_cost', 'location_repaire_cost'];
    if (!in_array($costField, $allowed, true)) {
        game_error_log(__FUNCTION__, 'unknown cost field', ['costField' => $costField]);
        return null;
    }
    if (getConfig($pdo, 'ressource_management') !== 'TRUE') {
        return [];
    }

    // An unreadable list frees nothing, so a missing ressource refuses the action
    $freeWithoutRessource = [];
    $freeConfig = getConfig($pdo, 'freeWithoutRessource');
    if ($freeConfig !== null && trim($freeConfig) !== '') {
        $decoded = json_decode($freeConfig, true);
        if (is_array($decoded) && array_values($decoded) === $decoded) {
            $freeWithoutRessource = $decoded;
        } else {
            game_error_log(__FUNCTION__, 'freeWithoutRessource is not a JSON list, nothing is free', ['freeWithoutRessource' => $freeConfig], 'warning');
        }
    }
    $isFreeWithout = in_array($costField, $freeWithoutRessource, true);

    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare("SELECT r.id AS ressource_id, r.ressource_name, r.{$costField} AS cost, cr.amount
            FROM {$prefix}ressources_config r
            LEFT JOIN {$prefix}controller_ressources cr ON cr.ressource_id = r.id AND cr.controller_id = :controller_id
            WHERE r.{$costField} > 0
            ORDER BY r.id");
        $stmt->execute([':controller_id' => $controller_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT costs failed : ' . $e->getMessage(), ['controller_id' => $controller_id, 'costField' => $costField], 'error');
        return null;
    }

    $costs = [];
    foreach ($rows as $row) {
        if ($row['amount'] === null && $isFreeWithout) {
            continue;
        }
        $costs[] = [
            'ressource_id' => (int)$row['ressource_id'],
            'ressource_name' => (string)$row['ressource_name'],
            'cost' => (int)$row['cost'],
            'amount' => $row['amount'] === null ? null : (int)$row['amount'],
        ];
    }
    game_error_log(__FUNCTION__, 'DONE with controller_id : ' . $controller_id, ['costField' => $costField, 'costs' => $costs], 'debug');
    return $costs;
}

/**
 * Does the controller hold every ressource the action costs, in sufficient amount ?
 *
 * @param PDO    $pdo            : DB connection
 * @param int    $controller_id  : controller id
 * @param string $costField      : one of base_building_cost, base_moving_cost, location_repaire_cost
 *
 * @return bool true when every cost is covered (or nothing is costed), false otherwise or on failure
 */
function hasEnoughRessourcesByCostField(PDO $pdo, int $controller_id, string $costField): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, ['costField' => $costField], 'debug');

    $costs = getRessourceCostsByCostField($pdo, $controller_id, $costField);
    if ($costs === null) {
        return false;
    }
    foreach ($costs as $cost) {
        if ($cost['amount'] === null || $cost['amount'] < $cost['cost']) {
            return false;
        }
    }
    return true;
}

/**
 * Render the cost of an action, listing every configured ressource it requires.
 *
 * @param PDO    $pdo            : DB connection
 * @param int    $controller_id  : controller id
 * @param string $costField      : one of base_building_cost, base_moving_cost, location_repaire_cost
 * @param string $label          : text placed before the costs
 *
 * @return string HTML fragment, empty when the action costs nothing
 */
function ressourceCostHTMLByCostField(PDO $pdo, int $controller_id, string $costField, string $label): string
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, ['costField' => $costField], 'debug');

    $costs = getRessourceCostsByCostField($pdo, $controller_id, $costField);
    if (empty($costs)) {
        return '';
    }
    $parts = [];
    foreach ($costs as $cost) {
        $parts[] = sprintf('%d %s', $cost['cost'], htmlspecialchars($cost['ressource_name']));
    }
    return '<br> ' . $label . ' ' . implode(', ', $parts);
}

/**
 * Does the controller have enough ressources to build a base ?
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return bool true if all base_building_cost fields are covered
 */
function hasEnoughRessourcesToBuildBase(PDO $pdo, int $controller_id): bool
{
    return hasEnoughRessourcesByCostField($pdo, $controller_id, 'base_building_cost');
}

/**
 * Spend the ressources to build a base. Atomic across all costed ressources:
 * if any consumeRessource fails (insufficient stock), the whole transaction
 * rolls back and the caller receives false. Returns true also when
 * ressource_management is off, or when no ressource carries a positive
 * base_building_cost (nothing to spend).
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return bool true on full deduction (or no-op), false on any shortfall.
 */
function spendRessourcesToBuildBase(PDO $pdo, int $controller_id): bool
{
    return spendRessourcesByCostField($pdo, $controller_id, 'base_building_cost');
}

/**
 * Get the cost HTML to build a base.
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return string HTML fragment listing positive base_building_cost entries
 */
function buildBaseCostHTML(PDO $pdo, int $controller_id): string
{
    return ressourceCostHTMLByCostField($pdo, $controller_id, 'base_building_cost', 'Coût de construction :');
}

/**
 * Does the controller have enough ressources to move a base ?
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return bool true if all base_moving_cost fields are covered
 */
function hasEnoughRessourcesToMoveBase(PDO $pdo, int $controller_id): bool
{
    return hasEnoughRessourcesByCostField($pdo, $controller_id, 'base_moving_cost');
}

/**
 * Spend the ressources to move a base. Atomic; rolls back on any shortfall.
 * See spendRessourcesToBuildBase for the contract.
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return bool true on full deduction (or no-op), false on any shortfall.
 */
function spendRessourcesToMoveBase(PDO $pdo, int $controller_id): bool
{
    return spendRessourcesByCostField($pdo, $controller_id, 'base_moving_cost');
}

/**
 * Get the cost HTML to move a base.
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return string HTML fragment listing positive base_moving_cost entries
 */
function moveBaseCostHTML(PDO $pdo, int $controller_id): string
{
    return ressourceCostHTMLByCostField($pdo, $controller_id, 'base_moving_cost', 'Coût :');
}

/**
 * Does the controller have enough ressources to repair a location ?
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return bool true if all location_repaire_cost fields are covered
 */
function hasEnoughRessourcesToRepairLocation(PDO $pdo, int $controller_id): bool
{
    return hasEnoughRessourcesByCostField($pdo, $controller_id, 'location_repaire_cost');
}

/**
 * Spend the ressources to repair a location. Atomic; rolls back on any shortfall.
 * See spendRessourcesToBuildBase for the contract.
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return bool true on full deduction (or no-op), false on any shortfall.
 */
function spendRessourcesToRepairLocation(PDO $pdo, int $controller_id): bool
{
    return spendRessourcesByCostField($pdo, $controller_id, 'location_repaire_cost');
}

/**
 * Shared deducting primitive for the three spend* operations. Reads the
 * action's costs from getRessourceCostsByCostField, refuses when the
 * controller lacks a costed ressource that freeWithoutRessource does not
 * free, and composes consumeRessource calls inside a single transaction.
 * Any shortfall (or `consumeRessource` returning false for any other
 * reason) triggers a rollback so partial deductions cannot ship.
 *
 * @param PDO    $pdo            : DB connection
 * @param int    $controller_id  : controller id
 * @param string $costField      : one of base_building_cost, base_moving_cost,
 *                                 location_repaire_cost. Whitelist enforced.
 *
 * @return bool true on full deduction (or no-op), false on any shortfall.
 */
function spendRessourcesByCostField(PDO $pdo, int $controller_id, string $costField): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, ['costField' => $costField], 'debug');

    $costs = getRessourceCostsByCostField($pdo, $controller_id, $costField);
    if ($costs === null) {
        return false;
    }
    if (empty($costs)) {
        return true;
    }
    foreach ($costs as $cost) {
        if ($cost['amount'] === null) {
            game_error_log(__FUNCTION__, 'controller holds no row for a costed ressource', ['controller_id' => $controller_id, 'costField' => $costField, 'ressource_id' => $cost['ressource_id']], 'warning');
            return false;
        }
    }

    $pdo->beginTransaction();
    foreach ($costs as $cost) {
        if (!consumeRessource($pdo, $controller_id, $cost['ressource_id'], $cost['cost'])) {
            $pdo->rollBack();
            return false;
        }
    }
    $pdo->commit();
    return true;
}

/**
 * Get the cost HTML to repair a location.
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return string HTML fragment listing positive location_repaire_cost entries
 */
function repairLocationCostHTML(PDO $pdo, int $controller_id): string
{
    return ressourceCostHTMLByCostField($pdo, $controller_id, 'location_repaire_cost', 'Coût :');
}

/**
 * Natural-French rendering of a gain rule's condition for display.
 * $zoneTypeLabel is the resolved `textForZoneType` config ("zone" / "quartier"
 * / "territoire") and $locationTypeNames the [tag, name] pairs of
 * getLocationTypeNames. Caller fetches both once and passes them in.
 *
 * @param array  $rule              : gain rule structure
 * @param string $zoneTypeLabel     : resolved zone-type label
 * @param array  $locationTypeNames : [tag, name] pairs naming the location types
 *
 * @return string natural-French condition rendering
 */
function gainRuleToText(array $rule, string $zoneTypeLabel, array $locationTypeNames): string
{
    $type = $rule['condition']['type'] ?? '';
    switch ($type) {
        case 'holds_zone':
            if (isset($rule['condition']['zone_id'])) {
                return sprintf('pour le %s tenu (id %d)', $zoneTypeLabel, (int)$rule['condition']['zone_id']);
            }
            if (isset($rule['condition']['zone_name'])) {
                return sprintf('pour le %s tenu (%s)', $zoneTypeLabel, (string)$rule['condition']['zone_name']);
            }
            return sprintf('par %s tenu', $zoneTypeLabel);
        case 'claims_zone':
            if (isset($rule['condition']['zone_id'])) {
                return sprintf('pour le %s revendiqué (id %d)', $zoneTypeLabel, (int)$rule['condition']['zone_id']);
            }
            if (isset($rule['condition']['zone_name'])) {
                return sprintf('pour le %s revendiqué (%s)', $zoneTypeLabel, (string)$rule['condition']['zone_name']);
            }
            return sprintf('par %s sous notre bannière ce tour', $zoneTypeLabel);
        case 'owns_location_type':
            $tag = $rule['condition']['location_type'] ?? null;
            if ($tag === null) {
                return 'par lieu possédé';
            }
            foreach ($locationTypeNames as [$typeTag, $typeName]) {
                if ($typeTag === $tag) {
                    return sprintf('par %s que vous possédez', $typeName);
                }
            }
            return sprintf('par lieu de type "%s" que vous possédez', $tag);
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
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 *
 * @return array grouped gain estimate, empty on DB error
 */
function ressourceGainEstimateForController(PDO $pdo, int $controller_id): array
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, [], 'debug');

    require_once __DIR__ . '/../mechanics/ressourceGainMechanic.php';
    $prefix = $_SESSION['GAME_PREFIX'];
    $estimate = [];
    $zoneTypeLabel = getConfig($pdo, 'textForZoneType') ?: 'zone';
    $locationTypeNames = getLocationTypeNames($pdo);

    $mechanics = getMechanics($pdo);
    $currentTurn = (int)($mechanics['turncounter'] ?? 0);

    $controllersArray = getControllers($pdo, null, $controller_id);
    if ($controllersArray === null) {
        return [];
    }
    $factionContext = ['worker' => null, 'controllersArray' => $controllersArray, 'zonesArray' => [], 'zonesArrayHolder' => [], 'ressourcesArray' => []];

    try {
        $stmt = $pdo->prepare("SELECT id AS ressource_id, gain_rules
            FROM {$prefix}ressources_config
            WHERE gain_rules IS NOT NULL");
        $stmt->execute();
        $configRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT gain_rules failed', ['error' => $e->getMessage()]);
        return [];
    }

    foreach ($configRows as $row) {
        $ressourceId = (int)$row['ressource_id'];
        $rules = json_decode($row['gain_rules'], true);
        if (!is_array($rules)) {
            continue;
        }
        $bucket = ['before_claim' => [], 'after_claim' => [], 'total' => 0];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if (!isset($rule['amount']) || !is_numeric($rule['amount'])) {
                continue;
            }
            if (!isset($rule['timing']) || !in_array($rule['timing'], ['before_claim', 'after_claim'], true)) {
                continue;
            }
            if (!isset($rule['condition']['type'])) {
                continue;
            }
            if (isset($rule['unlock_turn']) && (int)$rule['unlock_turn'] > $currentTurn) {
                continue;
            }
            // A rule kept from the controller's faction is neither counted nor listed
            $factionKeys = array_intersect_key($rule, array_flip(RESSOURCE_GAIN_FACTION_KEYS));
            if (!empty($factionKeys) && !evaluateRuleKeysAllMatch($factionKeys, $factionContext, $currentTurn)) {
                continue;
            }

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
                'text'   => gainRuleToText($rule, $zoneTypeLabel, $locationTypeNames),
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
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : controller id
 * @param int $ressource_id   : ressource id
 * @param int $amount         : quantity to subtract (must be > 0)
 *
 * @return bool true on success; false on insufficient amount, missing row, or DB error.
 */
function consumeRessource(PDO $pdo, int $controller_id, int $ressource_id, int $amount): bool
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, ['ressource_id' => $ressource_id, 'amount' => $amount], 'debug');

    if ($amount <= 0 || $controller_id <= 0 || $ressource_id <= 0) {
        return false;
    }
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
        game_error_log(__FUNCTION__, 'consume ressource failed', ['error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Process a ressource gift from $giver_id to $post['target_controller_id'].
 * Validates inputs, wraps the decrement / increment / log writes in a
 * transaction so partial failure cannot vanish ressources.
 *
 * @param PDO   $pdo       : DB connection
 * @param int   $giver_id  : giver controller id
 * @param array $post      : expects 'ressource_id', 'target_controller_id', 'amount'
 *
 * @return array ['success' => bool, 'message' => string]
 */
function giftRessource(PDO $pdo, int $giver_id, array $post): array
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with giver_id : ' . $giver_id, ['post' => $post], 'debug');

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
        game_error_log(__FUNCTION__, 'gift validation failed', ['error' => $e->getMessage()]);
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
        game_error_log(__FUNCTION__, 'gift transaction failed', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'Erreur lors du don.'];
    }
}

/**
 * Fetch ressource gifts received by a controller, newest first.
 * Each row: ['turn', 'amount', 'giver', 'ressource'].
 *
 * @param PDO $pdo            : DB connection
 * @param int $controller_id  : recipient controller id
 *
 * @return array rows ordered by (turn DESC, created_at DESC), empty on DB error
 */
function getRessourceGiftsReceived(PDO $pdo, int $controller_id): array
{
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $sql = "SELECT
            l.turn,
            l.amount,
            CONCAT(c.firstname, ' ', c.lastname, ' (', f.name, ')') AS giver,
            rc.ressource_name AS ressource
        FROM {$prefix}ressource_gift_logs l
        JOIN {$prefix}controllers c ON l.giver_controller_id = c.id
        LEFT JOIN {$prefix}factions f ON c.fake_faction_id = f.ID
        JOIN {$prefix}ressources_config rc ON l.ressource_id = rc.id
        WHERE l.recipient_controller_id = :recipient_id
        ORDER BY l.turn DESC, l.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':recipient_id' => $controller_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT ressource_gift_logs failed', ['error' => $e->getMessage()]);
        return [];
    }
}
