<?php

require_once '../mechanics/aiMechanic.php';
require_once '../mechanics/attackMechanic.php';
require_once '../mechanics/claimMechanic.php';
require_once '../mechanics/investigateMechanic.php';
require_once '../mechanics/locationSearchMechanic.php';

if (!defined('WORKER_ACTION_CHOICES_ALLOWED')) {
    define('WORKER_ACTION_CHOICES_ALLOWED', ['passive', 'investigate', 'attack', 'claim', 'hide', 'captured', 'dead', 'trace']);
}
if (!defined('INVESTIGATE_ACTIONS_DEFAULT')) {
    define('INVESTIGATE_ACTIONS_DEFAULT', ['passive', 'investigate']);
}

/**
 * Start or Pause the game state
 * 
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics array
 * @param bool $start : true to start, false to pause
 *
 * @return bool : success
 */
function toggleMechanicsGamestate($pdo, $mechanics, $start = true) {
    $prefix = $_SESSION['GAME_PREFIX'];

    // SQL query to update gamestate
    $sql = '';
    if ($start && $mechanics['gamestate'] == 0) {
        $sql = "UPDATE {$prefix}mechanics SET gamestate = 1 WHERE id = :id ";
    }
    if (!$start && $mechanics['gamestate'] == 1) {
        $sql = "UPDATE {$prefix}mechanics SET gamestate = 0 WHERE id = :id ";
    }
    if (!empty($sql)) {
        try{
            // Prepare and execute SQL query
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $mechanics['id']]);
        } catch (PDOException $e) {
            echo __FUNCTION__."():UPDATE mechanics Failed: " . $e->getMessage()."<br />";
            return false;
        }
    }
    return true;
}

/**
 * Change the end turn state
 *
 * @param PDO $pdo : database connection
 * @param string $state : state to change to
 * @param array $mechanics : mechanics array
 *
 * @return bool : success
 */
function changeEndTurnState($pdo, $state, $mechanics) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $sql = "UPDATE {$prefix}mechanics set end_step = :end_step WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':end_step' => $state, ':id' => $mechanics['id']]);
}

/**
 * Build base randomization SQL
 *
 * @return string : SQL string
 *
 */
function diceSQL() {
    $prefix = $_SESSION['GAME_PREFIX'];
    return sprintf(
        "FLOOR(
            %1\$s * (
                CAST((SELECT value FROM {$prefix}config WHERE name = 'MAXROLL') AS %2\$s)
                - CAST((SELECT value FROM {$prefix}config WHERE name = 'MINROLL') AS %2\$s)
                +  1
            ) + CAST((SELECT value FROM {$prefix}config WHERE name = 'MINROLL') AS %2\$s)
        )",
        ($_SESSION['DBTYPE'] == 'postgres') ? 'RANDOM()' : 'RAND()',
        ($_SESSION['DBTYPE'] == 'postgres') ? 'INT' : 'SIGNED'
    );
}

/**
 * Return value of a dice roll
 *
 * @param PDO $pdo : database connection
 *
 * @return int : rollvalue
 */
function diceRoll($pdo) {
    $diceSQL = diceSQL();
    $sql = "SELECT $diceSQL as roll";

    try{
        // Prepare and execute SQL query
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT diceSQL Failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    $roll = $stmt->fetchALL(PDO::FETCH_ASSOC);
    return $roll[0]['roll'];
}

/**
 * Validate a configurable list of action_choice values and return a SQL-safe IN-list.
 *
 * @param string|null $configValue
 * @param array $allowedActions
 * @param array $defaultActions
 *
 * @return string
 */
function validateActionChoiceListForSql($configValue, $allowedActions, $defaultActions) {
    $parsedActions = [];
    if (!empty($configValue)) {
        $normalized = trim((string)$configValue);

        // Native format used by config rows: 'passive','investigate'
        if (preg_match("/^'[^']+'(?:\\s*,\\s*'[^']+')*$/", $normalized)) {
            preg_match_all("/'([^']+)'/", $normalized, $matches);
            if (!empty($matches[1])) {
                $parsedActions = $matches[1];
            }
        } else {
            // Fallback parser for loosely formatted CSV-like strings.
            $normalized = str_replace(["'", '"'], '', $normalized);
            $rawParts = explode(',', $normalized);
            foreach ($rawParts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $parsedActions[] = $part;
                }
            }
        }
    }

    if (empty($parsedActions)) {
        $parsedActions = $defaultActions;
    }

    $safeActions = [];
    foreach ($parsedActions as $action) {
        if (in_array($action, $allowedActions, true) && !in_array($action, $safeActions, true)) {
            $safeActions[] = $action;
        }
    }

    if (empty($safeActions)) {
        $safeActions = $defaultActions;
    }

    $quotedActions = [];
    foreach ($safeActions as $action) {
        $quotedActions[] = "'".$action."'";
    }

    return implode(',', $quotedActions);
}

/**
 * Return validated investigation actions for SQL usage.
 *
 * @param PDO $pdo
 *
 * @return string
 */
function getValidatedInvestigateActionsForSql($pdo) {
    $configuredActions = getConfig($pdo, 'investigateActionsList');

    return validateActionChoiceListForSql($configuredActions, WORKER_ACTION_CHOICES_ALLOWED, INVESTIGATE_ACTIONS_DEFAULT);
}

/**
 * Calculates the final values for each worker depending on their chosen action.
 *
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics array
 *
 * @return bool : success
 *
 */
function calculateVals($pdo, $mechanics){
    $turn_number =  $mechanics['turncounter'];
    $prefix = $_SESSION['GAME_PREFIX'];

    $sqlArray = [];
    $array = [];
    $array[] = ['enquete', 'passiveInvestigateActions', false];
    $array[] = ['enquete', 'activeInvestigateActions', true];
    $array[] = ['attack', 'passiveAttackActions', false];
    $array[] = ['attack', 'activeAttackActions', true];
    $array[] = ['defence', 'passiveDefenceActions', false];
    $array[] = ['defence', 'activeDefenceActions', true];
    echo '<div> <h3> calculateVals : </h3> <p>';

    // foreach type of action
    foreach ($array as $elements) {
        $config = getConfig($pdo, $elements[1]);
        echo sprintf("Get Config for %s : $config <br /> ", $elements[1]);

        // chose SQL for value generation
        $valBaseSQL = sprintf(
            "(SELECT CAST(value AS %s) FROM {$prefix}config WHERE name = 'PASSIVEVAL')",
            ($_SESSION['DBTYPE'] == 'postgres') ? 'INT' : 'SIGNED'
        );
        if ($elements[2]) {
            $valBaseSQL = diceSQL();
        }

        // Name of configured bonus (ex : ENQUETE_ZONE_BONUS)
        $zoneBonusColumn = strtoupper("{$elements[0]}_zone_bonus");
        $zoneBonusSQL = sprintf(
            "(SELECT CAST(value AS %s) FROM {$prefix}config WHERE name = '%s')",
            ($_SESSION['DBTYPE'] == 'postgres') ? 'INT' : 'SIGNED',
            $zoneBonusColumn
        );

        // Flat bonus to a specific action from config
        $flatBonusSQL = "";
        // Remove single quotes
        $strActions = str_replace("'", "", $config);
        // Now split
        $actions = explode(",", $strActions);
        foreach ( $actions AS $action ) {
            // get flat bonus from config
            $bonusColumn = strtoupper(sprintf("%s_%s_flat_bonus", $action, $elements[0]));
            $flatBonusConfig = getConfig($pdo, $bonusColumn);
            echo sprintf("Get Config for %s : %s <br /> ", $bonusColumn, $flatBonusConfig);
            if (!empty($flatBonusConfig)) {
                $flatBonusSQL .= sprintf("
                    + CASE
                        WHEN wa1.action_choice = '%s'
                        THEN %s
                        ELSE 0
                    END",
                    $action,
                    $flatBonusConfig
                );
            }
        }
        // Build of base SQL request with the conditionnal bonus
        $valSQL = sprintf("%s_val = (
            COALESCE((
                SELECT SUM(p.%s)
                FROM {$prefix}workers AS w
                LEFT JOIN {$prefix}worker_powers wp ON w.id = wp.worker_id
                LEFT JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                LEFT JOIN {$prefix}powers p ON lpt.power_id = p.ID
                WHERE wa1.worker_id = w.id
            ), 0)
            + %s
            + CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM {$prefix}workers w2
                    JOIN {$prefix}zones z2 ON w2.zone_id = z2.id
                    JOIN {$prefix}controller_worker cw2 ON cw2.worker_id = w2.id AND is_primary_controller = True
                    WHERE w2.id = wa1.worker_id
                      AND z2.holder_controller_id = cw2.controller_id
                )
                THEN %s
                ELSE 0
            END
            %s

        )",
            $elements[0],
            $elements[0],
            $valBaseSQL,
            $zoneBonusSQL,
            $flatBonusSQL
        );

        // get list of actions to calibrate
        if (!empty($config)){
            // add to list of updates
            $sqlArray[] = array(
                'sql'=> sprintf(
                    "UPDATE {$prefix}worker_actions wa1 SET %1\$s WHERE turn_number = %2\$s AND action_choice IN (%3\$s)",
                    $valSQL, $turn_number, $config
                ),
                'config' => $config
            );
        }
    }
    echo '</p>';

    // Execute SQLs
    foreach ($sqlArray as $sql) {
        echo sprintf("<p>DO SQL for %s : <br /> %s <br>",  $sql['config'],  $sql['sql'] );
        try {
            // Prepare and execute SQL query
            $stmt = $pdo->prepare($sql['sql']);
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />";
            return false;
        }
        echo "DONE <br /></p>";
    }

    echo '</div>';

    changeEndTurnState($pdo, 'calculateVals', $mechanics);
    return true;
}

/**
 * Creation of new worker action lines for each worker for the new turn
 * maintaining certain action continuation (investigate and claim)
 * setting dead and captured status
 *
 * @param PDO $pdo : database connection
 * @param string $turn_number
 *
 * @return bool : success
 *
 */
function createNewTurnLines($pdo, $turn_number){
    $debug = strtolower(getConfig($pdo, 'DEBUG')) === 'true';
    $prefix = $_SESSION['GAME_PREFIX'];
    echo '<div> <h3>  createNewTurnLines : </h3> ';
    $sqlInsert = "
        INSERT INTO {$prefix}worker_actions (worker_id, turn_number, zone_id, controller_id, action_choice, action_params)
        SELECT
            w.id AS worker_id,
            :turn_number AS turn_number,
            w.zone_id AS zone_id,
            cw.controller_id AS controller_id,
            wa.action_choice,
            wa.action_params
        FROM {$prefix}workers w
        JOIN {$prefix}controller_worker AS cw ON cw.worker_id = w.id AND is_primary_controller = " . ($_SESSION['DBTYPE'] == 'postgres' ? 'true' : '1') . "
        JOIN {$prefix}worker_actions AS wa ON wa.worker_id = w.id AND turn_number = :turn_number_n_1
        WHERE wa.worker_id NOT IN ( SELECT worker_id FROM {$prefix}worker_actions wa2 WHERE wa2.turn_number = :turn_number )
    ";
    try {
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmtInsert->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
        $stmtInsert->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql INSERT FAILED : ".$e->getMessage()."<br/> sql: $sqlInsert<br/>";
        return false;
    }

    $config_continuing_investigate_action = getConfig($pdo, 'continuing_investigate_action');
    if (!$config_continuing_investigate_action){
        $sqlSetInvestigate = "
            UPDATE {$prefix}worker_actions wa1
            JOIN {$prefix}worker_actions wa2 ON wa1.worker_id = wa2.worker_id
            SET wa1.action_choice = 'passive'
            WHERE wa1.turn_number = :turn_number
            AND wa2.action_choice = 'investigate'
            AND wa2.turn_number = :turn_number_n_1
        ";
        try {
            $stmtSetInvestigate = $pdo->prepare($sqlSetInvestigate);
            $stmtSetInvestigate->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
            $stmtSetInvestigate->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
            $stmtSetInvestigate->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql UPDATE investigate FAILED : ".$e->getMessage()."<br />$sqlSetInvestigate<br/>";
            return false;
        }
    }
    $config_continuing_claimed_action = getConfig($pdo, 'continuing_claimed_action');
        if (!$config_continuing_claimed_action){
        $sqlSetClaim = "
            UPDATE {$prefix}worker_actions wa1
            JOIN {$prefix}worker_actions wa2 ON wa1.worker_id = wa2.worker_id
            SET wa1.action_choice = 'passive'
            WHERE wa1.turn_number = :turn_number
            AND wa2.action_choice = 'claim'
            AND wa2.turn_number = :turn_number_n_1
        ";
        try {
            $stmtSetClaim = $pdo->prepare($sqlSetClaim);
            $stmtSetClaim->bindValue(':turn_number', $turn_number, PDO::PARAM_INT);
            $stmtSetClaim->bindValue(':turn_number_n_1', ((INT)$turn_number-1), PDO::PARAM_INT);
            $stmtSetClaim->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql UPDATE claim FAILED : ".$e->getMessage()."<br />$sqlSetClaim<br/>";
            return false;
        }
    }

    echo '<p>createNewTurnLines : DONE</p> </div>';

    return true;
}

