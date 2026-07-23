<?php
/**
 * Get the description for a power_type
 *
 * @param PDO $pdo : database connection
 * @param string $name : power_type name
 * @return string|null : description
 */
function getPowerTypesDescription(PDO $pdo, string $name): string|null {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with name : ' . $name, [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    try{
        $stmt = $pdo->prepare("SELECT description
            FROM {$prefix}power_types
            WHERE name = :name
        ");
        $stmt->execute([':name' => $name]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : NULL;
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT description failed', ['name' => $name, 'error' => $e->getMessage()]);
        return NULL;
    }
}

/**
 * Get the format for a power text
 *
 * @param bool $short : short format flag
 * @return string : SQL fragment
 */
function getSQLPowerText(bool $short = true): string {
    $sql = "CONCAT(p.name, ' (', p.enquete, ', ', p.attack, '/', p.defence, ')') AS power_text";
    if (!$short) {
        $sql = "CONCAT('<strong>', p.name, ' (', p.enquete, ', ', p.attack, '/', p.defence, ')</strong> ', p.description) AS power_text";
        if ($_SESSION['DBTYPE'] == 'mysql')
            $sql = "CONCAT('<strong>', p.name, ' (', p.enquete, ', ', p.attack, '/', p.defence, ')</strong> ', IFNULL(p.description,'')) AS power_text";
    }
    
    return $sql;
}

/**
 * Gets array of powers for a worker id
 *
 * @param PDO $pdo : database connection
 * @param int|string $worker_id_str : one worker id or comma-separated ids for the IN clause
 * @return array : worker_powers rows
 */
function getPowersByWorkers(PDO $pdo, int|string $worker_id_str): array {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with worker_id_str : ' . $worker_id_str, [], 'debug');

    $power_text = getSQLPowerText(false);
    $prefix = $_SESSION['GAME_PREFIX'];
    $sql = "SELECT
        w.id AS worker_id,
        p.*,
        $power_text,
        pt.name AS power_type_name
    FROM {$prefix}workers w
    JOIN {$prefix}worker_powers wp ON w.id = wp.worker_id
    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
    JOIN {$prefix}powers p ON lpt.power_id = p.ID
    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
    WHERE w.id IN ($worker_id_str)
    ORDER BY w.id ASC
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT powers by workers failed', ['sql' => $sql, 'error' => $e->getMessage()]);
        return array();
    }
    // Fetch the results
    $workers_powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    game_error_log(__FUNCTION__, 'DONE', ['workers_powers' => $workers_powers], 'debug');

    return $workers_powers;
}

/**
 *  get a number of random elements from the type of power given
 *
 * @param PDO $pdo : database connection
 * @param string $type : link_power_type id
 * @param array $newWorker : worker being built
 * @return array|null : $newWorker with power_<type> filled, or NULL on SQL error
 */
function randomPowersByType(PDO $pdo, string $type, array $newWorker): array|null {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with type : ' . $type, ['newWorker' => $newWorker], 'debug');

    // TODO : Add a select limit by controller_id like in the getPowersByType function
    // TODO : Allow locking certain Hobbys/Metiers by origin or controler !

    $power_text = getSQLPowerText(false);
    $randCommand = 'RANDOM()';
    $unlockTurnExpr = "p.other->'on_random_pick'->>'unlock_turn'";
    $unlockTurnCast = "({$unlockTurnExpr})::INT";
    if ($_SESSION['DBTYPE'] == 'mysql') {
        $randCommand = 'RAND()';
        $unlockTurnExpr = "JSON_UNQUOTE(JSON_EXTRACT(p.other, '$.on_random_pick.unlock_turn'))";
        $unlockTurnCast = "CAST({$unlockTurnExpr} AS SIGNED)";
    }
    $mechanics = getMechanics($pdo);
    $turn_number = (int)($mechanics['turncounter'] ?? 0);

    // Skip rows whose on_random_pick.unlock_turn > current turn.
    $unlockTurnFilter = sprintf(
        "AND (%s IS NULL OR %s <= :turn)",
        $unlockTurnExpr,
        $unlockTurnCast
    );

    $prefix = $_SESSION['GAME_PREFIX'];
    try{
        // Get x random values from powers for a power_type
        $sql = sprintf("SELECT p.*, %s FROM {$prefix}powers AS p
            INNER JOIN {$prefix}link_power_type lpt ON lpt.power_id = p.id
            WHERE lpt.power_type_id = %s %s ORDER BY %s LIMIT 1",
            $power_text,
            $type,
            $unlockTurnFilter,
            $randCommand
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':turn' => $turn_number]);
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT random power failed', ['sql' => $sql, 'error' => $e->getMessage()]);
        return NULL;
    }

    // Fetch the results
    $powerArray = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $newWorker['power_'.$type] = $powerArray[0];

    return $newWorker;
}

/**
 * get power from a type with options :
 *  - powers linked to a controller by faction
 *  - base power from config
 *
 * @param PDO $pdo : database connection
 * @param string $type_list : link_power_type id list  // TODO change from ID of link_power_type to a type name ?
 * @param int|null $controller_id : controller id, or NULL to skip controller filter
 * @param bool $add_base : whether to add basePowerNames from config
 * @return array|null : $powerArray
 */
function getPowersByType(PDO $pdo, string $type_list, int|null $controller_id = NULL, bool $add_base = true): array|null {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with type_list : ' . $type_list, ['controller_id' => $controller_id, 'add_base' => $add_base], 'debug');

    $powerArray = array();
    $power_text = getSQLPowerText();

    $basePowerNames = '';
    if ( $add_base ){
        $configBasePowerNames = getConfig($pdo, 'basePowerNames');
        if ( !empty($configBasePowerNames) ) {
            $basePowerNames = $configBasePowerNames;
        }
    }

    $conditions = '';
    if ($controller_id != "" || $basePowerNames != "") {
        $conditions = sprintf("AND ( %s %s %s)",
        $basePowerNames != "" ? "sp.name IN ($basePowerNames)" : '',
        ($controller_id != "" && $basePowerNames != "") ? "OR" : '',
        $controller_id != "" ? "sc.id IN ($controller_id)" : '');
    }

    $prefix = $_SESSION['GAME_PREFIX'];
    // Get all powers from a type_list
    $sql = sprintf("SELECT p.*, %3\$s, lpt.id as link_power_type_id
        FROM {$prefix}powers AS p
        JOIN {$prefix}link_power_type AS lpt ON lpt.power_id = p.id
        WHERE p.id IN (
            SELECT distinct(sp.id)
            FROM {$prefix}powers AS sp
            JOIN {$prefix}link_power_type AS slpt ON slpt.power_id = sp.id
            LEFT JOIN {$prefix}faction_powers AS sfp ON sfp.link_power_type_id = slpt.id
            LEFT JOIN {$prefix}factions sf ON sf.id = sfp.faction_id
            LEFT JOIN {$prefix}controllers sc ON sc.faction_id = sf.id
            WHERE slpt.power_type_id IN ( %1\$s )
            %2\$s
        )",
        $type_list,
        $conditions,
        $power_text,
    );
    game_error_log(__FUNCTION__, 'SQL', ['sql' => $sql], 'debug');

    try{
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT powers by type failed', ['sql' => $sql, 'error' => $e->getMessage()]);
        return NULL;
    }

    // Fetch the results
    $powerArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $powerArray;
}

/**
 * builds the discipline select field from an array of Disciplines
 *
 * @param PDO $pdo : database connection
 * @param array $powerDisciplineArray
 * @param bool $showText default: true
 *
 * @return string: $showDisciplineSelect
 */
function showDisciplineSelect(PDO $pdo, array $powerDisciplineArray, bool $showText = true): string {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START', ['powerDisciplineArray' => $powerDisciplineArray, 'showText' => $showText], 'debug');

    if (empty($powerDisciplineArray)) return '';

    $disciplinesOptions = '';
    foreach ($powerDisciplineArray as $powerDiscipline) {
        $disciplinesOptions .= "<option value='" . htmlspecialchars($powerDiscipline['link_power_type_id']) . "'>" . htmlspecialchars($powerDiscipline['power_text']) . "</option>";
    }

    $label = $showText ? getPowerTypesDescription($pdo, 'Discipline').' :' : '';

    $showDisciplineSelect = sprintf('
            %s
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="disciplineSelect" name="discipline">
                        <option value="">Sélectionner %s</option>
                        %s
                    </select>
                </div>
            </div>
        ',
        $label ? 'Enseigner un.e '.$label : '',
        htmlspecialchars(getPowerTypesDescription($pdo, 'Discipline')),
        $disciplinesOptions
    );

    game_error_log(__FUNCTION__, 'DONE', ['showDisciplineSelect' => $showDisciplineSelect], 'debug');

    return $showDisciplineSelect;
}

/**
 * Filter a list of powers by JSON-driven unlock rules at `[$state_text]`.
 * Pure-check: never mutates DB. Delegates per-power gating to findMatchingBranch.
 * 
 * @param PDO $pdo : database connection
 * @param array $powerArray : power array
 * @param int $controller_id : controller id
 * @param int $worker_id : worker id
 * @param int $turn_number : turn number
 * @param string $state_text : state text
 * @return array|null surviving powers (NULL when empty)
 */
function cleanPowerListFromJsonConditions(PDO $pdo, array $powerArray, int $controller_id, int|null $worker_id, int $turn_number, string $state_text): array|null {
    if (strtolower(getConfig($pdo, 'DEBUG_TRANSFORM')) == 'true')
        $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, ['powerArray' => $powerArray, 'worker_id' => $worker_id, 'turn_number' => $turn_number, 'state_text' => $state_text], 'debug');

    $workersPowersList = array();
    if (!empty($worker_id)){
        $workersPowersArray = getPowersByWorkers($pdo, $worker_id);
        foreach ($workersPowersArray as $workerPower){
            $workersPowersList[] = $workerPower['id'];
        }
    }

    foreach ( $powerArray AS $key => $power ) {
        if (!empty($worker_id) && in_array($power['id'], $workersPowersList, true)){
            game_error_log(__FUNCTION__, 'kill power(' . $key . ') — already possessed', [], 'debug');
            unset($powerArray[$key]);
            continue;
        }

        $powerConditions = json_decode($power['other'] ?? '', true);
        if (!is_array($powerConditions)) continue;

        $match = findMatchingBranch($pdo, $powerConditions, $controller_id, $worker_id, $turn_number, $state_text);
        if (!$match['keep']){
            game_error_log(__FUNCTION__, 'kill power(' . $key . ')', [], 'debug');
            unset($powerArray[$key]);
        }
    }

    return empty($powerArray) ? NULL : $powerArray ;
}

/**
 * Decide whether a power's `[$state_text]` rule satisfies, and surface which OR
 * branch fired. Single source of truth for gate semantics across display
 * (cleanPowerListFromJsonConditions) and commit (getRuleCostForPower).
 *
 * @param PDO $pdo : database connection
 * @param array $powerConditions : power conditions
 * @param int $controller_id : controller id
 * @param int $worker_id : worker id
 * @param int $turn_number : turn number
 * @param string $state_text : state text
 * @return array ['keep' => bool, 'matching_branch' => ?array]
 */
function findMatchingBranch(PDO $pdo, array $powerConditions, int $controller_id, int|null $worker_id, int $turn_number, string $state_text): array {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, ['powerConditions' => $powerConditions, 'worker_id' => $worker_id, 'turn_number' => $turn_number, 'state_text' => $state_text], 'debug');

    static $contextCache = [];

    $result = ['keep' => true, 'matching_branch' => null];

    if (empty($powerConditions[$state_text])) return $result;
    $rule = $powerConditions[$state_text];

    if (gettype($rule) === 'string' && strtolower($rule) === 'false'){
        $result['keep'] = false;
        return $result;
    }
    if (!is_array($rule)) return $result;

    $cacheKey = "{$controller_id}_{$worker_id}_{$turn_number}";
    if (!isset($contextCache[$cacheKey])){
        $contextCache[$cacheKey] = buildRuleEvaluationContext($pdo, $controller_id, $worker_id);
    }
    $context = $contextCache[$cacheKey];

    $direct = $rule;
    $orBranches = null;
    if (array_key_exists('OR', $direct)){
        $orBranches = $direct['OR'];
        unset($direct['OR']);
    }

    if (!evaluateRuleKeysAllMatch($direct, $context, $turn_number)){
        $result['keep'] = false;
        return $result;
    }

    if ($orBranches !== null){
        if (!is_array($orBranches) || array_keys($orBranches) !== range(0, count($orBranches) - 1)){
            // OR must be array-of-objects, not single object
            $result['keep'] = false;
            return $result;
        }
        $matched = null;
        foreach ($orBranches as $branch){
            if (!is_array($branch)) continue;
            if (evaluateRuleKeysAllMatch($branch, $context, $turn_number)){
                $matched = $branch;
                break;
            }
        }
        if ($matched === null){
            $result['keep'] = false;
            return $result;
        }
        $result['matching_branch'] = $matched;
    } else {
        $result['matching_branch'] = $direct;
    }

    return $result;
}

/**
 * Resolve the ressource cost owed at commit time for an unlocked power: walks
 * both the direct-level controller_has_ressource and the matched OR branch
 * (via findMatchingBranch). Direct precedence on cross-resource collision +
 * error_log warning. Returns null when no deducting cost applies.
 * 
 * @param PDO $pdo : database connection
 * @param array $power : power to evaluate
 * @param int $controller_id : controller id
 * @param int $worker_id : worker id
 * @param int $turn_number : turn number
 * @param string $state_text : state text
 * @return array|null ['ressource_id' => int, 'ressource_name' => string, 'amount' => int]
 */
function getRuleCostForPower(PDO $pdo, array $power, int $controller_id, int|null $worker_id, int $turn_number, string $state_text): array|null {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, ['power' => $power, 'worker_id' => $worker_id, 'state_text' => $state_text], 'debug');

    if (empty($power['other'])) return null;
    $powerConditions = json_decode($power['other'], true);
    if (!is_array($powerConditions) || empty($powerConditions[$state_text]) || !is_array($powerConditions[$state_text])) return null;
    $rule = $powerConditions[$state_text];

    // Gate via the shared matcher first; no cost if the rule does not pass.
    $match = findMatchingBranch($pdo, $powerConditions, $controller_id, $worker_id, $turn_number, $state_text);
    if (!$match['keep']) return null;

    $direct_cost = extractRessourceCostFromRule($rule['controller_has_ressource'] ?? null);

    $or_cost = null;
    if (isset($rule['OR']) && is_array($match['matching_branch'])){
        $or_cost = extractRessourceCostFromRule($match['matching_branch']['controller_has_ressource'] ?? null);
    }

    if ($direct_cost !== null && $or_cost !== null){
        if ($direct_cost['ressource_name'] !== $or_cost['ressource_name']){
            game_error_log(__FUNCTION__, 'cross-resource cost not supported, using direct',
                ['direct' => $direct_cost['ressource_name'], 'or' => $or_cost['ressource_name']],
                'warning');
        }
        $or_cost = null;
    }
    $cost = $direct_cost ?? $or_cost;
    if ($cost === null) return null;

    $rid = resolveRessourceIdByName($pdo, $cost['ressource_name']);
    if ($rid === null){
        game_error_log(__FUNCTION__, 'ressource not found in ressources_config',
            ['ressource_name' => $cost['ressource_name']], 'warning');
        return null;
    }
    return ['ressource_id' => $rid, 'ressource_name' => $cost['ressource_name'], 'amount' => $cost['amount']];
}

/**
 * Pre-fetch all per-controller / per-worker data the rule keys need.
 * Cached inside findMatchingBranch for the request.
 *
 * @param PDO $pdo : database connection
 * @param int $controller_id : controller id
 * @param int|null $worker_id : worker id, or NULL when no worker context
 * @return array : ['worker', 'controllersArray', 'zonesArray', 'zonesArrayHolder', 'ressourcesArray']
 */
function buildRuleEvaluationContext(PDO $pdo, int $controller_id, int|null $worker_id): array {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with controller_id : ' . $controller_id, ['worker_id' => $worker_id], 'debug');

    $worker = null;
    if (!empty($worker_id)){
        $workersArray = getWorkers($pdo, [$worker_id]);
        if (!empty($workersArray[0])) $worker = $workersArray[0];
    }
    $controllersArray = [];
    $zonesArray = [];
    $zonesArrayHolder = [];
    $ressourcesArray = [];
    if (!empty($controller_id)){
        $controllersArray = getControllers($pdo, NULL, $controller_id);
        $zonesArray = getZonesArray($pdo, $controller_id, null, null);
        $zonesArrayHolder = getZonesArray($pdo, null, $controller_id, null);
        $ressourcesArray = getRessources($pdo, $controller_id);
    }
    return [
        'worker' => $worker,
        'controllersArray' => $controllersArray,
        'zonesArray' => $zonesArray,
        'zonesArrayHolder' => $zonesArrayHolder,
        'ressourcesArray' => $ressourcesArray,
    ];
}

/**
 * AND-of-all-keys evaluator. Used for both direct rules and OR branches.
 * Fail-closed on unknown keys: a typo like `controller_has_resource` (English
 * single-`s` spelling) would otherwise unlock the power instead of hiding it.
 * When the rule grammar is extended, add the new key here.
 * 
 * @param array $keys : keys to evaluate
 * @param array $context : context to evaluate the keys
 * @param int $turn_number : turn number
 * @return bool true if all keys match, false otherwise
 */
function evaluateRuleKeysAllMatch(array $keys, array $context, int $turn_number): bool {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with turn_number : ' . $turn_number, ['keys_count' => count($keys), 'context' => $context], 'debug');

    static $ALLOWED_KEYS = [
        'age', 'worker_is_alive', 'unlock_turn', 'controller_faction',
        'controller_has_zone', 'worker_in_zone', 'controller_has_ressource',
    ];
    $worker = $context['worker'];
    $controllersArray = $context['controllersArray'];
    $zonesArray = $context['zonesArray'];
    $zonesArrayHolder = $context['zonesArrayHolder'];
    $ressourcesArray = $context['ressourcesArray'];

    foreach ($keys as $key => $value){
        if ($key === 'OR') continue;
        if (!in_array($key, $ALLOWED_KEYS, true)){
            game_error_log(__FUNCTION__, 'unknown rule key — failing closed', ['key' => (string)$key], 'debug');
            return false;
        }

        if ($key === 'age'){
            if (!empty($worker) && !empty($worker['age']) && ((int)$value > (int)$worker['age'])) return false;
        }
        elseif ($key === 'worker_is_alive'){
            if (!empty($worker) && isset($worker['actions'][$turn_number]['action_choice'])){
                $should_be_alive = ($value != "0");
                $is_alive = in_array($worker['actions'][$turn_number]['action_choice'], ACTIVE_ACTIONS);
                if ($is_alive !== $should_be_alive) return false;
            }
        }
        elseif ($key === 'unlock_turn'){
            if ((int)$value > (int)$turn_number) return false;
        }
        elseif ($key === 'controller_faction'){
            if (!empty($value) && !empty($controllersArray) && $value !== $controllersArray[0]['faction_name']) return false;
        }
        elseif ($key === 'controller_has_zone'){
            if (empty($value)) continue;
            $found = false;
            foreach ($zonesArray as $zone){
                if ($zone['name'] === $value){ $found = true; break; }
            }
            if (!$found){
                foreach ($zonesArrayHolder as $zone){
                    if ($zone['name'] === $value){ $found = true; break; }
                }
            }
            if (!$found) return false;
        }
        elseif ($key === 'worker_in_zone'){
            if (!empty($value) && !empty($worker) && ($worker['zone_name'] ?? null) !== $value) return false;
        }
        elseif ($key === 'controller_has_ressource'){
            if (!is_array($value)){
                game_error_log(__FUNCTION__, 'controller_has_ressource: malformed rule (not an object)');
                return false;
            }
            $rname = $value['ressource_name'] ?? null;
            $rawAmount = $value['amount'] ?? null;
            $amountIsStrictInt = is_int($rawAmount) || (is_string($rawAmount) && ctype_digit($rawAmount));
            if (!is_string($rname) || $rname === '' || !$amountIsStrictInt || (int)$rawAmount <= 0){
                game_error_log(__FUNCTION__, 'controller_has_ressource: invalid ressource_name or amount',
                    ['ressource_name' => var_export($rname, true), 'amount' => var_export($rawAmount, true)], 'debug');
                return false;
            }
            if (array_key_exists('consume', $value) && !is_bool($value['consume'])){
                game_error_log(__FUNCTION__, 'controller_has_ressource: consume must be bool if present',
                    ['consume' => var_export($value['consume'], true)], 'debug');
                return false;
            }
            $amount = (int)$rawAmount;
            $found = false;
            foreach ($ressourcesArray as $r){
                if (($r['ressource_name'] ?? null) === $rname && (int)($r['amount'] ?? 0) >= $amount){
                    $found = true; break;
                }
            }
            if (!$found) return false;
        }
    }
    return true;
}

/**
 * Extract the gate/cost form of a controller_has_ressource value.
 * Returns null when consume === false (gate-only) or malformed.
 *
 * @param array|null $ressourceRule : controller_has_ressource sub-object, or null
 * @return array|null : ['ressource_name' => string, 'amount' => int]
 */
function extractRessourceCostFromRule(array|null $ressourceRule): array|null {
    if (!is_array($ressourceRule)) return null;
    if (array_key_exists('consume', $ressourceRule)){
        if (!is_bool($ressourceRule['consume'])) return null;
        if ($ressourceRule['consume'] === false) return null;
    }
    $rname = $ressourceRule['ressource_name'] ?? null;
    $rawAmount = $ressourceRule['amount'] ?? null;
    $amountIsStrictInt = is_int($rawAmount) || (is_string($rawAmount) && ctype_digit($rawAmount));
    if (!is_string($rname) || $rname === '' || !$amountIsStrictInt || (int)$rawAmount <= 0) return null;
    return ['ressource_name' => $rname, 'amount' => (int)$rawAmount];
}

/**
 * Resolve a ressource_id from its ressource_name.
 * @param PDO $pdo : database connection
 * @param string $ressource_name : ressource name
 * @return int|null ressource id
 */
function resolveRessourceIdByName(PDO $pdo, string $ressource_name): int|null {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with ressource_name : ' . $ressource_name, [], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    try {
        $stmt = $pdo->prepare("SELECT id FROM {$prefix}ressources_config WHERE ressource_name = :name LIMIT 1");
        $stmt->execute([':name' => $ressource_name]);
        $rid = $stmt->fetchColumn();
        return $rid !== false ? (int)$rid : null;
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT ressource_id by name failed', ['error' => $e->getMessage()]);
        return null;
    }
}

/**
 * Build select field for Transformations in array
 *
 * @param PDO $pdo : database connection
 * @param array $powerTransformationArray : transformation rows with power_text
 * @param bool $showText : whether to show the leading label, default true
 * @return string : $showTransformationSelect
 */
function showTransformationSelect(PDO $pdo, array $powerTransformationArray, bool $showText = true): string {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START', ['powerTransformationArray' => $powerTransformationArray, 'showText' => $showText], 'debug');

    if (empty($powerTransformationArray)) return '';

    $transformationsOptions = '';
    foreach ($powerTransformationArray as $powerTransformation) {
        $transformationsOptions .= "<option value='" . $powerTransformation['link_power_type_id'] . "'>" . $powerTransformation['power_text'] . "</option>";
    }

    $label = $showText ? getPowerTypesDescription($pdo, 'Transformation').' :' : '';

    $showTransformationSelect = sprintf('
            %s
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="transformationSelect" name="transformation">
                        <option value="">Sélectionner %s</option>
                        %s
                    </select>
                </div>
            </div>
        ',
        $label ? 'Ajouter un.e '.$label.'' : '',
        getPowerTypesDescription($pdo, 'Transformation'),
        $transformationsOptions
    );

    game_error_log(__FUNCTION__, 'DONE', ['showTransformationSelect' => $showTransformationSelect], 'debug');

    return $showTransformationSelect;
}
