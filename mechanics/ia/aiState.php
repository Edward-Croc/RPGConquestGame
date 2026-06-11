<?php

require_once __DIR__ . '/aiBase.php';
require_once __DIR__ . '/aiKnowledge.php';

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
