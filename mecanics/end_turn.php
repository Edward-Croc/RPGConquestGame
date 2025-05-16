<?php
$pageName = 'End Turn';

require_once '../base/base_php.php';

require_once '../base/base_html.php';

        if ($mecanics['gamestat'] == 0) {
            try{
                // SQL query to update gamestat
                $sql = "UPDATE mecanics SET gamestat = 1 WHERE ID = '".$mecanics['id']."'";
                // Prepare and execute SQL query
                $stmt = $gameReady->prepare($sql);
                $stmt->execute();
            } catch (PDOException $e) {
                echo __FUNCTION__."():UPDATE mecanics Failed: " . $e->getMessage()."<br />";
            }
        }
        $valsResult = calculateVals($gameReady, $mecanics['turncounter']);
        if ($valsResult) {
            // do end_turn actions
            // TODO : Save End Turn step to restart after bug ?
            $bdrResult = recalculateBaseDefence($gameReady);

            // set Controlled by IA actions
            $IAResult = aiMecanic($gameReady);

            // check attacks
            $attackResult = attackMecanic($gameReady);

            // check investigations
            $investigateResult = investigateMecanic($gameReady);

            // check locations seach
            $locationsearchResult = locationSearchMecanic($gameReady);

            // check claiming territory
            $claimResult = claimMecanic($gameReady);

            // update turn counter
            $turn = (INT)$mecanics['turncounter'] + 1;

            // if no errors occured create new turn lines
            // and advance turn counter
            if ($attackResult &&  $investigateResult && $claimResult && $IAResult && $locationsearchResult) {
                $turnLinesResult = createNewTurnLines($gameReady, $turn);
                $restartRecrutementCount = restartTurnRecrutementCount($gameReady);

                // Advance Turn counter
                try{
                    // SQL query to select username from the players table
                    $sql = "UPDATE mecanics set turncounter ='".$turn."' WHERE ID='".$mecanics['id']."'";
                    // Prepare and execute SQL query
                    $stmt = $gameReady->prepare($sql);
                    $stmt->execute();
                } catch (PDOException $e) {
                    echo __FUNCTION__."(): UPDATE mecanics Failed: " . $e->getMessage()."<br />";
                }
            }
            echo ucfirst(getConfig($gameReady, 'timeValue')).": $turn";
        }

