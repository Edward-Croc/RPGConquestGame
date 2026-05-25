<?php

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
        $stmt = $pdo->prepare("SELECT id, location_id, attacker_controller_id
            FROM {$prefix}controller_location_attacks
            WHERE queued_turn = :turn AND success IS NULL");
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
        if (!$location) continue;

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
