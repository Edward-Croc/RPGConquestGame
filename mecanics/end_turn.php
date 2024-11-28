<?php

// require_once '../BDD/db_connector.php';
require_once '../base/base_php.php';

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
        $valsOK = calculateVals($gameReady, $mecanics['turncounter']);
        if ($valsOK) {
            // do end_turn actions
                // enquete
                // attack
                // claim
                //
            // generate JSon reports
            // create new turn lines
            try{
                $turn = $mecanics['turncounter'] + 1;
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


        echo "Semaine : $turn";