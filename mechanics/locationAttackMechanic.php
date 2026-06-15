<?php

/**
 * Mark a queued end-turn location attack as failed and write an
 * attacker-only log entry. Used by both the cascade-destroyed branch
 * in locationAttackMechanic() and the cancel-on-move path in moveBase().
 *
 * @param PDO    $pdo
 * @param array  $queue_row controller_location_attacks row (needs id,
 *                          location_name, attacker_controller_id)
 * @param int    $turn_number
 * @param string $reason     'destroyed' | 'moved'
 *
 * @return bool
 */
function failQueuedLocationAttack($pdo, array $queue_row, $turn_number, $reason) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $textKey = $reason === 'moved' ? 'textLocationAttackMoved' : 'textLocationAttackDestroyed';
    $attackerText = sprintf((string)getConfig($pdo, $textKey), $queue_row['location_name']);

    try {
        $u = $pdo->prepare("UPDATE {$prefix}controller_location_attacks
            SET success = :success, resolved_turn = :turn
            WHERE id = :id");
        $false = false;
        $u->bindParam(':success', $false, PDO::PARAM_BOOL);
        $u->bindParam(':turn', $turn_number, PDO::PARAM_INT);
        $u->bindParam(':id', $queue_row['id'], PDO::PARAM_INT);
        $u->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): UPDATE controller_location_attacks Failed: " . $e->getMessage()."<br />";
        return false;
    }

    try {
        $log = $pdo->prepare("INSERT INTO {$prefix}location_attack_logs
            (target_controller_id, location_name, attacker_id, attack_val, defence_val,
             turn, success, target_result_text, attacker_result_text)
            VALUES (NULL, :location_name, :attacker_id, 0, 0, :turn, 0, '', :attacker_text)");
        $log->bindParam(':location_name', $queue_row['location_name'], PDO::PARAM_STR);
        $log->bindParam(':attacker_id', $queue_row['attacker_controller_id'], PDO::PARAM_INT);
        $log->bindParam(':turn', $turn_number, PDO::PARAM_INT);
        $log->bindParam(':attacker_text', $attackerText, PDO::PARAM_STR);
        $log->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): INSERT location_attack_logs Failed: " . $e->getMessage()."<br />";
        return false;
    }

    return true;
}

/**
 *
 * @param PDO $pdo
 * @param int $turn_number
 *
 * @return array $return
 */
function locationAttackMechanic($pdo, $turn_number) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $mode = getConfig($pdo, 'locationAttackMode');

    echo "<div><h3>locationAttackMechanic : mode '".htmlspecialchars((string)$mode)."'</h3>" ;
    if (!in_array($mode, ['endTurn'], true)) {
        echo " not supported, skipped</div>";
        return true;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, location_id, location_name, attacker_controller_id
            FROM {$prefix}controller_location_attacks
            WHERE queued_turn = :turn AND success IS NULL
            ORDER BY id ASC");
        $stmt->bindParam(':turn', $turn_number, PDO::PARAM_INT);
        $stmt->execute();
        $queued = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): SELECT queued attacks failed: " . $e->getMessage()."<br />";
        return false;
    }

    foreach ($queued as $row) {
        $locStmt = $pdo->prepare("SELECT l.*, z.id AS zone_id, z.name AS zone_name
            FROM {$prefix}locations l
            JOIN {$prefix}zones z ON l.zone_id = z.id
            WHERE l.id = :id LIMIT 1");
        $locStmt->execute([':id' => $row['location_id']]);
        $location = $locStmt->fetch(PDO::FETCH_ASSOC);
        if (!$location) {
            failQueuedLocationAttack($pdo, $row, $turn_number, 'destroyed');
            continue;
        }

        $zone_id = $location['zone_id'];
        $resolvedAttack = calculatecontrollerAttack($pdo, $zone_id, $row['attacker_controller_id']);
        $resolvedDefence = calculateSecretLocationDefence($pdo, $zone_id, $row['location_id'], $location['controller_id']);

        $result = resolveLocationAttackEffects(
            $pdo, $location, $row['attacker_controller_id'], $turn_number,
            $resolvedAttack, $resolvedDefence
        );

        try {
            $success = !empty($result['success']);
            $u = $pdo->prepare("UPDATE {$prefix}controller_location_attacks
                SET attack_val_resolved = :att, defence_val_resolved = :def,
                    success = :success, resolved_turn = :turn
                WHERE id = :id");
            $u->bindParam(':att', $resolvedAttack, PDO::PARAM_INT);
            $u->bindParam(':def', $resolvedDefence, PDO::PARAM_INT);
            $u->bindParam(':success', $success, PDO::PARAM_BOOL);
            $u->bindParam(':turn', $turn_number, PDO::PARAM_INT);
            $u->bindParam(':id', $row['id'], PDO::PARAM_INT);
            $u->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__."(): UPDATE queue row failed: " . $e->getMessage()."<br />";
            return false;
        }
    }

    echo '<p> locationAttackMechanic : DONE </p> </div>';
    return true;
}
