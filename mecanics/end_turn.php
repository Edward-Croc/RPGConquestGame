<?php

// require_once '../BDD/db_connector.php';
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
                // enquete
                // attack
                // claim
                //
            // generate JSon reports
            $turn = $mecanics['turncounter'] + 1;
            $turnLinesResult = createNewTurnLines($gameReady, $turn);
            if ($valsResult) {
                try{
                    // SQL query to select username from the players table
                    $sql = "UPDATE mecanics set turncounter ='".$turn."' WHERE ID='".$mecanics['id']."'";
                    // Prepare and execute SQL query
                    $stmt = $gameReady->prepare($sql);
                    $stmt->execute();
                } catch (PDOException $e) {
                    echo "UPDATE config Failed: " . $e->getMessage()."<br />";
                }
                // Fetch the result
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }


        echo "Semaine : $turn";