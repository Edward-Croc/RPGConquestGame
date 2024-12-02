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
                echo "UPDATE config Failed: " . $e->getMessage()."<br />";
            }
        }
        $valsResult = calculateVals($gameReady, $mecanics['turncounter']);
        if ($valsResult) {
            // do end_turn actions
            // Controlled by IA 
            // attack
            $attackResult = attackMecanic($gameReady);
            // enquete
            $investigateResult = investigateMecanic($gameReady);
            // claim
            $claimResult = claimMecanic($gameReady);
            $turn = $mecanics['turncounter'] + 1;
            /* $turnLinesResult = createNewTurnLines($gameReady, $turn);
            // Advance Turn counter 
            try{
                // SQL query to select username from the players table
                $sql = "UPDATE mecanics set turncounter ='".$turn."' WHERE ID='".$mecanics['id']."'";
                // Prepare and execute SQL query
                $stmt = $gameReady->prepare($sql);
                $stmt->execute();
            } catch (PDOException $e) {
                echo "UPDATE config Failed: " . $e->getMessage()."<br />";
            }
            }*/
        }


        echo "Semaine : $turn";