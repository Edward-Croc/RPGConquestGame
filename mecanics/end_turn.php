<?php

// require_once '../BDD/db_connector.php';
require_once '../base/base_php.php';

        if ($mecanics['gamestat'] == false) {
            // SQL query to update gamestat
            $sql = "UPDATE mecanics SET gamestat = TRUE WHERE ID = '".$mecanics['id']."'";
            // Prepare and execute SQL query
            $stmt = $gameReady->prepare($sql);
            $stmt->execute();
        }

        $turn = $mecanics['turncounter'] + 1;
        /*
        // SQL query to select username from the players table
        $sql = "UPDATE mecanics set turn ='".$turn."' WHERE ID='".$mecanics['ID']."'";
        // Prepare and execute SQL query
        $stmt = $gameReady->prepare($sql);
        $stmt->execute();
        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($_SESSION['DEBUG'] == true){
        }
        echo var_export($result);
*/
        return "Semaine : $turn";