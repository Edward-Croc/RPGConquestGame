<?php

if (!defined('RESSOURCE_GAIN_LOCATION_FILTER_WHITELIST')) {
    define('RESSOURCE_GAIN_LOCATION_FILTER_WHITELIST', ['is_base', 'can_be_destroyed', 'zone_id', 'location_id', 'location_type']);
}
if (!defined('RESSOURCE_GAIN_CONDITION_TYPES')) {
    define('RESSOURCE_GAIN_CONDITION_TYPES', ['holds_zone', 'claims_zone', 'owns_location_type']);
}

/**
 * Apply end-of-turn ressource gain rules whose timing matches $timing.
 * Each rule produces amount × COUNT(matching entities for the controller).
 * Malformed rules are logged and skipped (graceful degradation).
 *
 * @param PDO    $pdo
 * @param string $timing 'before_claim' | 'after_claim'
 *
 * @return bool
 */
function ressourceGainMechanic($pdo, $timing) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $hasErrors = false;
    echo "<div><h3>ressourceGainMechanic : timing '".htmlspecialchars($timing)."'</h3>";

    // Get the list of ressources
    try {
        $stmt = $pdo->prepare("SELECT id AS ressource_id, gain_rules
            FROM {$prefix}ressources_config
            WHERE gain_rules IS NOT NULL");
        $stmt->execute();
        $configRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT gain_rules failed: ".$e->getMessage()."<br />";
        return false;
    }

    // For each Ressource
    foreach ($configRows as $row) {
        $ressourceId = (int)$row['ressource_id'];

        // Extract the gain rules 
        $rules = json_decode($row['gain_rules'], true);
        if (!is_array($rules)) {
            echo __FUNCTION__."(): Invalid gain_rules JSON for ressource_id={$ressourceId}, skipping<br />";
            continue;
        }

        foreach ($rules as $rule) {
            // Check if rule is correctly written
            if (!ressourceGainRuleIsValid($rule, $timing)) continue;
            $multiplier = (int)$rule['amount'];
            if ($multiplier === 0) continue;

            // Check if the rule's condition apply's to à controller
            // list of ['controller_id' => int, 'match_count' => int]
            $matches = ressourceGainEvaluateCondition($pdo, $rule['condition']);
            // For each contoller found
            foreach ($matches as $match) {
                $controllerId = (int)$match['controller_id'];
                $matchCount = (int)$match['match_count'];
                if ($matchCount <= 0) continue;
                $gainAmount = $multiplier * $matchCount;
                try {
                    $u = $pdo->prepare("UPDATE {$prefix}controller_ressources
                        SET amount = amount + :gain
                        WHERE controller_id = :cid AND ressource_id = :rid");
                    $u->bindValue(':gain', $gainAmount, PDO::PARAM_INT);
                    $u->bindValue(':cid', $controllerId, PDO::PARAM_INT);
                    $u->bindValue(':rid', $ressourceId, PDO::PARAM_INT);
                    $u->execute();
                    if ($u->rowCount() === 0) {
                        $hasErrors = true;
                        echo __FUNCTION__."(): no controller_ressources row for c={$controllerId},r={$ressourceId}<br />";
                    }
                } catch (PDOException $e) {
                    $hasErrors = true;
                    echo __FUNCTION__."(): UPDATE failed for c={$controllerId},r={$ressourceId}: ".$e->getMessage()."<br />";
                }
            }
        }
    }

    echo "<p>ressourceGainMechanic : DONE</p></div>";
    return !$hasErrors;
}

/**
 * Validate a single gain rule's structural shape and timing match.
 *
 * @param mixed  $rule
 * @param string $timing
 *
 * @return bool
 */
function ressourceGainRuleIsValid($rule, $timing) {
    if (!is_array($rule)) return false;
    if (!isset($rule['amount']) || !is_numeric($rule['amount'])) return false;
    if (!isset($rule['timing']) || $rule['timing'] !== $timing) return false;
    if (!isset($rule['condition']['type'])) return false;
    if (!in_array($rule['condition']['type'], RESSOURCE_GAIN_CONDITION_TYPES, true)) return false;
    return true;
}

/**
 * Evaluate a condition and return per-controller match counts.
 * Filter keys outside the whitelist are silently dropped.
 *
 * @param PDO   $pdo
 * @param array $condition
 *
 * @return array list of ['controller_id' => int, 'match_count' => int]
 */
function ressourceGainEvaluateCondition($pdo, array $condition) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $conditionType = $condition['type'];

    if ($conditionType === 'holds_zone' || $conditionType === 'claims_zone') {
        $fkColumn = $conditionType === 'holds_zone' ? 'holder_controller_id' : 'claimer_controller_id';
        $extra = '';
        $params = [];
        if (isset($condition['zone_id'])) {
            $extra = ' AND z.id = :zone_id';
            $params[':zone_id'] = (int)$condition['zone_id'];
        }
        try {
            $sql = "SELECT c.id AS controller_id, COUNT(z.id) AS match_count
                FROM {$prefix}controllers c
                LEFT JOIN {$prefix}zones z ON z.{$fkColumn} = c.id{$extra}
                GROUP BY c.id
                HAVING COUNT(z.id) > 0";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) $stmt->bindValue($key, $value, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo __FUNCTION__."(): zone SELECT failed: ".$e->getMessage()."<br />";
            return [];
        }
    }

    if ($conditionType === 'owns_location_type') {
        // Scalar filters resolved in SQL; location_type (JSON-array containment) resolved in PHP after fetch.
        $extras = [];
        $params = [];
        foreach (RESSOURCE_GAIN_LOCATION_FILTER_WHITELIST as $allowedKey) {
            if ($allowedKey === 'location_type') continue;
            if (isset($condition[$allowedKey])) {
                $sqlColumn = $allowedKey === 'location_id' ? 'id' : $allowedKey;
                $extras[] = "l.{$sqlColumn} = :{$allowedKey}";
                $params[":{$allowedKey}"] = $condition[$allowedKey];
            }
        }
        $extra = empty($extras) ? '' : ' AND '.implode(' AND ', $extras);
        try {
            $sql = "SELECT c.id AS controller_id, l.id AS location_id, l.location_types
                FROM {$prefix}controllers c
                JOIN {$prefix}locations l ON l.controller_id = c.id{$extra}";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $paramType = is_bool($value) ? PDO::PARAM_BOOL : PDO::PARAM_INT;
                $stmt->bindValue($key, $value, $paramType);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo __FUNCTION__."(): location SELECT failed: ".$e->getMessage()."<br />";
            return [];
        }

        $requiredTag = $condition['location_type'] ?? null;
        $counts = [];
        foreach ($rows as $row) {
            if ($requiredTag !== null) {
                $tags = json_decode($row['location_types'] ?? '[]', true);
                if (!is_array($tags) || !in_array($requiredTag, $tags, true)) continue;
            }
            $cid = (int)$row['controller_id'];
            $counts[$cid] = ($counts[$cid] ?? 0) + 1;
        }
        $matches = [];
        foreach ($counts as $cid => $count) {
            $matches[] = ['controller_id' => $cid, 'match_count' => $count];
        }
        return $matches;
    }

    return [];
}
