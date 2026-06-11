<?php

require_once __DIR__ . '/aiBase.php';
require_once __DIR__ . '/aiState.php';
require_once __DIR__ . '/aiBehaviour.php';
require_once __DIR__ . '/aiDefence.php';

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

    if (empty($aiControllers)) return true;

    $debug = (strtolower((string) getConfig($pdo, 'DEBUG_IA')) === 'true');
    echo "<div><h3>aiMechanic — turn $turn_number</h3>";

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
