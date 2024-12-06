<?php

function getAttackerComparisons($pdo, $turn_number = NULL, $attacker_id = NULL) {
    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true') $debug = TRUE;

    if (empty($turn_number)) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
    }
    echo "turn_number : $turn_number <br>";
    try{
        // Define the SQL query
        $sql = "SELECT
                wa.worker_id AS attacker_id,
                wa.action_params AS params,
                wa.controler_id,
                wa.zone_id,
                wa.turn_number
            FROM
                worker_actions wa
            WHERE
                wa.action_choice IN ('attack')
                AND turn_number = :turn_number";

        // Add Limit to only 1 caracter
        if ( !EMPTY($attacker_id) ) $sql .= " AND s.attacker_id = :attacker_id";

        // Prepare and execute the statement
        $stmt = $pdo->prepare($sql);
        if ( !EMPTY($attacker_id) ) $stmt->bindParam(':attacker_id', $attacker_id);
        $stmt->bindParam(':turn_number', $turn_number);
        $stmt->execute();
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Failed to SELECT list of attackers: " . $e->getMessage() . "<br />";
    }
    $attackersActionArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($debug)
        echo sprintf("attackersActionArray : %s <br/>", var_export($attackersActionArray, true));

    $attackArray = array();
    foreach ($attackersActionArray AS $attackAction){
        if (!empty($attackAction['params'])) {
            $attackArray[$attackAction['attacker_id']]=array();
            $attackParams = json_decode($attackAction['params'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "JSON decoding error: " . json_last_error_msg() . "<br />";
            }
            foreach($attackParams AS $param){
                if ($param['attackScope'] == 'network'){
                    try{
                        $sqlNetworkSearch ="SELECT discovered_worker_id FROM controlers_known_enemies
                            WHERE zone_id = :zone_id AND discovered_controler_id = :network_id AND controler_id = :controler_id";
                        $stmtNetworkSearch = $pdo->prepare($sqlNetworkSearch);
                        $stmtNetworkSearch->bindParam(':network_id', $param['attackID']);
                        $stmtNetworkSearch->bindParam(':zone_id', $attackAction['zone_id']);
                        $stmtNetworkSearch->bindParam(':controler_id', $attackAction['controler_id']);
                        $stmtNetworkSearch->execute();
                    } catch (PDOException $e) {
                        echo __FUNCTION__."(): Failed to SELECT list of attackers for network : " . $e->getMessage() . "<br />";
                    }
                    $networkWorkersList = $stmtNetworkSearch->fetchAll(PDO::FETCH_COLUMN);
                    if ($debug)
                        echo sprintf("networkWorkersList : %s <br/>", var_export($networkWorkersList, true));
                    foreach($networkWorkersList AS $woker_id){
                        $attackArray[$attackAction['attacker_id']][] = $woker_id;
                    }
                } elseif ($param['attackScope'] == 'worker') {
                    if (!in_array($param['attackID'], $attackArray) ) {
                        $attackArray[$attackAction['attacker_id']][] = $param['attackID'];
                    }
                }
            }
        }
    }

    if ($debug)
        echo sprintf("attackArray : %s <br/>", var_export($attackArray, true));

    $sqlValCompare = "
    WITH attackers AS (
        SELECT
            wa.worker_id AS attacker_id,
            CONCAT(w.firstname, ' ', w.lastname) AS attacker_name,
            wa.attack_val AS attacker_attack_val,
            wa.defence_val AS attacker_defence_val,
            wa.controler_id AS attacker_controler_id,
            wa.zone_id,
            CONCAT(c.firstname, ' ', c.lastname) AS attacker_controler_name
        FROM
            worker_actions wa
            JOIN workers w ON w.id = wa.worker_id
            JOIN controlers c ON wa.controler_id = c.ID
        WHERE
            wa.worker_id = :attacker_id
            AND turn_number = :turn_number
    )
    SELECT
        a.attacker_id,
        a.attacker_name,
        a.attacker_attack_val,
        a.attacker_defence_val,
        a.attacker_controler_id,
        a.attacker_controler_name,
        z.id AS zone_id,
        z.name AS zone_name,
        wa.turn_number,
        wa.worker_id AS defender_id,
        wa.attack_val AS defender_attack_val,
        wa.defence_val AS defender_defence_val,
        CONCAT(w.firstname, ' ', w.lastname) AS defender_name,
        wo.id AS defender_origin_id,
        wo.name AS defender_origin_name,
        cw.controler_id as defender_controler_id,
        (a.attacker_attack_val - wa.defence_val) AS attack_difference,
        (wa.attack_val - a.attacker_defence_val) AS riposte_difference,
        cke.id AS defender_knows_enemy
    FROM attackers a
    JOIN zones z ON z.id = a.zone_id
    JOIN worker_actions wa ON
            a.zone_id = wa.zone_id AND wa.turn_number = :turn_number
    JOIN workers w ON wa.worker_id = w.ID
    JOIN worker_origins wo ON wo.id = w.origin_id
    JOIN controler_worker cw ON wa.worker_id = cw.worker_id AND is_primary_controler = true
    LEFT JOIN controlers_known_enemies cke ON cke.controler_id = cw.controler_id AND cke.discovered_worker_id = a.attacker_id
    WHERE w.id IN (%s)
    ";
    $final_attacks_aggregate = array();
    foreach ($attackArray AS $compared_attacker_id => $defender_ids ) {
        try {
            if ($debug)
                echo sprintf("compared_attacker_id : %s => defender_ids : %s <br/>", $compared_attacker_id, var_export($defender_ids, true));

            $stmtValCompare = $pdo->prepare(
                sprintf($sqlValCompare, implode(',', $defender_ids))
            );
            $stmtValCompare->bindParam(':turn_number', $turn_number);
            $stmtValCompare->bindParam(':attacker_id', $compared_attacker_id);
            $stmtValCompare->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__."():Failed to SELECT compare attackers to defenders : " . $e->getMessage() . "<br />";
        }
        $final_attacks_aggregate[$compared_attacker_id] = $stmtValCompare->fetchAll(PDO::FETCH_ASSOC);
        if ($debug)
            echo sprintf("final_attacks_aggregate : %s <br/>", var_export($final_attacks_aggregate[$compared_attacker_id], true));
    }
    return $final_attacks_aggregate;

}


function attackMecanic($pdo){
    echo '<div> <h3>  attackMecanic : </h3> ';

    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true') $debug = TRUE;
    $ATTACKDIFF0 = getConfig($pdo, 'ATTACKDIFF0');
    $ATTACKDIFF1 = getConfig($pdo, 'ATTACKDIFF1');
    $RIPOSTDIFF = getConfig($pdo, 'RIPOSTDIFF');
    $RIPOSTONDEATH = getConfig($pdo, 'RIPOSTONDEATH');
    $RIPOSTACTIVE = getConfig($pdo, 'RIPOSTACTIVE');

    if ($debug) {
        echo "ATTACKDIFF0 : $ATTACKDIFF0 <br/>";
        echo "ATTACKDIFF1 : $ATTACKDIFF1 <br/>";
        echo "RIPOSTDIFF : $RIPOSTDIFF <br/>";
        echo "RIPOSTONDEATH : $RIPOSTONDEATH <br/>";
        echo "RIPOSTACTIVE : $RIPOSTACTIVE <br/>";
    }

    $attacksArray = getAttackerComparisons($pdo, NULL, NULL, (INT)$ATTACKDIFF0 , (INT)$RIPOSTDIFF);
    if ($debug)
        echo sprintf("attacksArray : %s <br/>", var_export($attacksArray, true));
    if (empty($attacksArray)) { echo 'All is calm </div>'; return TRUE;}

    $disapearenceTextes = array(
        'Cet agent a disparu sans laisser de trace à partir de la semaine %s. ',
        'Depuis la semaine %s, plus aucun signal ni message de cet agent. ',
        'La connexion avec l\'agent s\'est perdue la semaine %s, et nous ignorons où il se trouve. ',
        'À partir de la semaine %s, cet agent semble s\'être volatilisé dans la nature. ',
        'Nous avons perdu toute communication avec cet agent depuis la semaine %s. ',
        'La dernière trace de cet agent remonte à la semaine %s, depuis il est aux abonnés absents. ',
        'La semaine %s marque la disparition totale de cet agent. Aucun indice sur sa situation actuelle. ',
        'L\'agent s\'est évanoui dans la nature après la semaine %s. Aucune nouvelle depuis. ',
        'Depuis la semaine %s, cet agent est un fantôme, insaisissable et introuvable. ',
        'La semaine %s signe le début du silence radio complet de cet agent. '
    );
    $attackSuccessTextes = array(
        'J\'ai pu mener à bien ma mission sur %1$s son silence est assuré. ',
        'J\'ai accompli l\'attaque sur %1$s, il a trouvé son repos final. ',
        'Notre cible %1$s as été accompatgner a l\'hopital dans un état critique, nous n\avons plus rien à craindre. ',
        'Il y aura un succidé retrouvé dans l\Arno demain, %1$s n\'est plus des notres. ',
        'Je confirme que %1$s ne posera plus jamais problème, il a rejoint le silence éternel. ',
        'Le dossier %1$s est officiellement clos. Son existence appartient désormais au passé. ',
        'Mission accomplie : %1$s est désormais une simple note dans les annales de l\'histoire. '
    );
    $captureSuccessTextes = array(
        'La mission est un succès total %1$s est désormais entre nos mains et nous allons le questionner. ',
        'La mission s\'est déroulée comme prévu : %1$s est capturé et prêt à livrer ses secrets. ',
        'Succès complet sur %1$s, il est désormais sous notre garde et n\'aura d\'autre choix que de parler. ',
        'Nous avons maîtrisé %1$s, il est maintenant entre nos mains, prêt pour l\'interrogatoire. ',
        'Mission accomplie : %1$s est capturé et en sécurité pour un débriefing approfondi. ',
        'L\'objectif %1$s est neutralisé et sous notre contrôle. L\'interrogatoire peut commencer. ',
        'Nous avons pris %1$s sans heurt : il est désormais à notre merci pour un échange d\'informations. ',
        'Le succès est total : %1$s est retenu, et ses paroles seront bientôt nôtres. ',
        'Mission terminée avec brio : %1$s est capturé et ne nous échappera plus.'
    );
    $failedAttackTextes = array(
        'Malheureusement, %1$s a réussi à nous échapper et reste en vie. ',
        'L\'opération contre %1$s a échoué. La cible a survécu et demeure une menace. ',
        'Notre tentative contre %1$s s\'est soldée par un échec. Il est toujours actif. ',
        'L\'attaque n\'a pas atteint son objectif : %1$s a survécu et garde sa liberté. ',
        'Nous n\'avons pas pu neutraliser %1$s. Il reste introuvable après l\'affrontement. ',
        'La mission a été un revers : %1$s est toujours debout et hors de notre portée. ',
        'Malgré nos efforts, %1$s s\'est défendu avec succès et a réussi à fuir. ',
        'Notre assaut n\'a pas suffi : %1$s a survécu et continue d\'agir. ',
        'La cible %1$s s\'est montrée plus résistante que prévu. Elle a échappé à notre emprise. ',
        'Nous avons échoué à neutraliser %1$s. Il demeure vivant et peut encore riposter. '
    );
    $escapeTextes = array(
            'J\'ai été pris pour cible par %1$s, mais j\'ai réussi à leur échapper de justesse. ',
            'Une attaque orchestrée par %1$s a failli m\'avoir, mais j\'ai pu me faufiler hors de leur portée. ',
            'L\'embuscade tendue par %1$s n\'a pas suffi à me retenir, j\'ai pu m\'échapper. ',
            'J\'ai croisé %1$s sur ma route, ils ont tenté de m\'intercepter, mais j\'ai fui avant qu\'il ne soit trop tard. ',
            'L\'attaque de %1$s a échoué, je suis sain et sauf et hors de danger. ',
            'Un assaut surprise de %1$s m\'a pris au dépourvu, mais j\'ai esquivé leurs griffes à temps. ',
            'Malgré une attaque menée par %1$s, j\'ai gardé mon calme et trouvé un chemin pour m\'échapper. ',
            'J\'ai senti %1$s venir et, bien qu\'ils m\'aient surpris, j\'ai su échapper à leur piège. ',
            'Ils ont tenté de me capturer sous la conduite de %1$s, mais ma fuite a été rapide et efficace. ',
            'L\'assaut de %1$s n\'a pas eu le résultat escompté, je suis parvenu à m\'enfuir indemne. '
    );
    $attackFailedAndCountered = array(
        'Je part mettre en route le plan d\assassinat de %s. ',
        'Début de la mission : %s. [Le rapport n\'as jamais été terminer.] '
    );
    $counterAttackTexts = array(
        '%1$s m\'a attaqué, j\'ai survécu et ma riposte l\'a anéanti, j\'ai jetter son cadavre dans l\'Arno. ',
        'Après avoir été attaqué par %1$s, j\'ai non seulement survécu, mais ma riposte a fait saigner leur ego. ',
        '%1$s a cru m\'avoir, mais ma riposte a brisé leurs espoirs et les a détruits. ',
        'Ils ont tenté de me réduire au silence, mais après avoir survécu à l\'attaque de %1$s, j\'ai répondu avec une riposte fatale. ',
        'Malgré l\'assaut de %1$s, ma riposte a non seulement sauvé ma vie, mais a mis fin à leurs ambitions. ',
        'Attaqué par %1$s, j\'ai résisté et ma riposte les a anéantis sans retour. ',
        'Ils ont cherché à me faire tomber, mais ma riposte après l\'attaque de %1$s a effacé toute menace. ',
        'L\'attaque de %1$s a échoué, et ma réponse a été rapide, fatale et décisive. ',
        'Je me suis retrouvé face à %1$s, mais après avoir survécu à leur attaque, ma riposte a scellé leur destin. ',
        'Après une attaque brutale de %1$s, ma survie et ma riposte ont fait en sorte qu\'ils n\'aient plus rien à revendiquer. '
    );
    
    
    foreach ($attacksArray as $attacker_id => $defenders) {
        // Build report :
        if ($debug)
            echo sprintf("attacker_id: %s =>row %s <br/>", $attacker_id, var_export($defenders, true));
        foreach ($defenders as $defender) {
            $attackerReport= array();
            $defenderReport= array();
            $survived = true;
            $is_alive = TRUE;
            $is_active = TRUE;
            $defender_status = NULL;
            if ($defender['attack_difference'] >= (INT)$ATTACKDIFF0 ){
                echo $defender['defender_name']. ' HAS DIED !';
                $survived = false;
                $is_alive = FALSE;
                $is_active = FALSE;
                $defender_status = 'dead';
                $attackerReport['attack_report'] = sprintf($attackSuccessTextes[array_rand($attackSuccessTextes)], $defender['defender_name']);
                $defenderReport['life_report'] = sprintf($disapearenceTextes[array_rand($disapearenceTextes)], $defender['turn_number'] );
                if ($defender['attack_difference'] >= (INT)$ATTACKDIFF1 ){
                    echo $defender['defender_name']. ' Was Captured !';
                    $is_alive=TRUE;
                    $defender_status = 'captured';
                    $attackerReport['attack_report'] = sprintf($captureSuccessTextes[array_rand($captureSuccessTextes)], $defender['defender_name']);
                    // in controler_worker update defender_controler_id, defender_id, is_primary_controler = false
                    $stmt = $pdo->prepare("UPDATE controler_worker SET is_primary_controler = :is_primary WHERE controler_id = :controler_id AND worker_id = :worker_id");
                    $stmt->execute([
                        'controler_id' => $defender['defender_controler_id'],
                        'worker_id' => $defender['defender_id'],
                        'is_primary' => FALSE
                    ]);
                    // in controler_worker insert attacker_controler_id, defender_id, is_primary_controler = true
                    $stmt = $pdo->prepare("INSERT INTO controler_worker (controler_id, worker_id, is_primary_controler) VALUES (:controler_id, :worker_id, :is_primary)");
                    $stmt->execute([
                        'controler_id' => $defender['attacker_controler_id'],
                        'worker_id' => $defender['defender_id'],
                        'is_primary' => TRUE
                    ]);
                }
                updateWorkerStatus($pdo, $defender['defender_id'], $is_alive, $is_active);
                updateWorkerAction($pdo, $defender['attacker_id'], $defender['turn_number'], NULL, $attackerReport );
                updateWorkerAction($pdo, $defender['defender_id'], $defender['turn_number'], $defender_status , $defenderReport );
            }
            if ($defender['attack_difference'] < (INT)$ATTACKDIFF0 ){
                echo $defender['defender_name']. ' Escaped !';
                $attackerReport['attack_report'] = sprintf($failedAttackTextes[array_rand($failedAttackTextes)], $defender['defender_name']);
                $defenderReport['life_report'] = sprintf($escapeTextes[array_rand($escapeTextes)], $defender['turn_number'] );
                updateWorkerAction($pdo, $defender['defender_id'], $defender['turn_number'], NULL, $defenderReport);
                updateWorkerAction($pdo, $defender['attacker_id'], $defender['turn_number'], NULL, $attackerReport );
            }
            if ((BOOL)$RIPOSTACTIVE && ($survived || (BOOL)$RIPOSTONDEATH) && $defender['riposte_difference'] >= (INT)$RIPOSTDIFF ){
                echo $defender['defender_name']. ' RIPOSTE !';
                $attackerReport['attack_report'] = sprintf($attackFailedAndCountered[array_rand($attackFailedAndCountered)], $defender['defender_name']);
                $attackerReport['life_report'] = sprintf($disapearenceTextes[array_rand($disapearenceTextes)], $defender['turn_number'] );
                updateWorkerStatus($pdo, $defender['attacker_id'], FALSE, FALSE);
                updateWorkerAction($pdo, $defender['attacker_id'], $defender['turn_number'], 'dead', $attackerReport);
                $defenderReport['life_report'] = sprintf($counterAttackTexts[array_rand($counterAttackTexts)], $defender['attacker_name']);
                updateWorkerAction($pdo, $defender['defender_id'], $defender['turn_number'], NULL, $defenderReport );
            }
        }
    }

    echo '</div>';
    return TRUE;
}