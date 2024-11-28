<?php

// require_once '../BDD/db_connector.php';
require_once '../base/base_php.php';

        if ($mecanics['gamestat'] == 0) {
            // SQL query to update gamestat
            $sql = "UPDATE mecanics SET gamestat = 1 WHERE ID = '".$mecanics['id']."'";
            // Prepare and execute SQL query
            $stmt = $gameReady->prepare($sql);
            $stmt->execute();
        }

        // calculate the vals
        // do end_turn actions
        // generate JSon reports
        // create new turn lines

        $turn = $mecanics['turncounter'] + 1;
        // SQL query to select username from the players table
        $sql = "UPDATE mecanics set turncounter ='".$turn."' WHERE ID='".$mecanics['id']."'";
        // Prepare and execute SQL query
        $stmt = $gameReady->prepare($sql);
        $stmt->execute();
        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "Semaine : $turn";