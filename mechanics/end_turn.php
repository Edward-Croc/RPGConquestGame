<?php
$pageName = 'End Turn';

require_once '../base/base_php.php';

require_once '../base/base_html.php';

        if ($mechanics['gamestate'] == 0) {
            try{
                // SQL query to update gamestate
                $sql = "UPDATE mechanics SET gamestate = 1 WHERE ID = '".$mechanics['id']."'";
                // Prepare and execute SQL query
                $stmt = $gameReady->prepare($sql);
                $stmt->execute();
            } catch (PDOException $e) {
                echo __FUNCTION__."():UPDATE mechanics Failed: " . $e->getMessage()."<br />";
            }
        }
        $valsResult = calculateVals($gameReady, $mechanics['turncounter']);

        // Add calculated values to worker report
        $sql = "SELECT w.id AS worker_id, wa.enquete_val AS enquete_val, wa.attack_val AS attack_val, wa.defence_val AS defence_val
            FROM workers w
            JOIN worker_actions wa ON wa.worker_id = w.id AND turn_number = :turn_number
            WHERE is_alive = True AND is_active = True";
        $stmt = $gameReady->prepare($sql);
        $stmt->execute([':turn_number' => $mechanics['turncounter']]);
        $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $timeValue = ucfirst(getConfig($gameReady, 'timeValue'));

        foreach ($workers as $worker) {
            // Example: retrieve stats from a helper function or inline logic
            $investigation = $worker['enquete_val'];
            $attack = $worker['attack_val'];
            $defense = $worker['defence_val'];

            // Format the life report string
            $reportAppendArray = [
                'life_report' => sprintf(
                    "Ce %s j'ai %s en investigation et %s/%s en attaque/défense.",
                $timeValue, $investigation, $attack, $defense
                )
            ];

            // Update the report using your existing game context
            updateWorkerAction($gameReady, $worker['worker_id'], $mechanics['turncounter'], null, $reportAppendArray);
        }

        $bdrResult = recalculateBaseDefence($gameReady);

        if ($valsResult && $bdrResult) {
            // do end_turn actions

            // TODO : Save End Turn step to restart after bug ?

            // set Controlled by IA actions
            $IAResult = aiMechanic($gameReady);

            // check attacks
            $attackResult = attackMechanic($gameReady);

            // check investigations
            $investigateResult = investigateMechanic($gameReady);

            // check locations seach
            $locationsearchResult = locationSearchMechanic($gameReady);

            // check claiming territory
            $claimResult = claimMechanic($gameReady);

            // update turn counter
            $turn = (INT)$mechanics['turncounter'] + 1;

            // if no errors occured create new turn lines
            // and advance turn counter
            if ($attackResult &&  $investigateResult && $claimResult && $IAResult && $locationsearchResult) {
                $turnLinesResult = createNewTurnLines($gameReady, $turn);
                $restartRecrutementCount = restartTurnRecrutementCount($gameReady);

                // Advance Turn counter
                try{
                    // SQL query to select username from the players table
                    $sql = "UPDATE mechanics set turncounter ='".$turn."' WHERE ID='".$mechanics['id']."'";
                    // Prepare and execute SQL query
                    $stmt = $gameReady->prepare($sql);
                    $stmt->execute();
                } catch (PDOException $e) {
                    echo __FUNCTION__."(): UPDATE mechanics Failed: " . $e->getMessage()."<br />";
                }
            }
            echo ucfirst(getConfig($gameReady, 'timeValue')).": $turn";
        }

