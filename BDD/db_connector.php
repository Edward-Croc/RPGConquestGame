<?php

function getDBConnection () {

    $path = '';
    $path1 = "/var/www/RPGConquestGame";
    $path2 = "..";

    // Path to the config.ini file
    $configFile = "/var/config.ini";
    if (file_exists ($path1.$configFile)) {
        $path = $path1;
    }
    if (file_exists ($path2.$configFile)) {
        $path = $path2;
    }
    if (file_exists ('.'.$configFile)) {
        $path = '.';
    }

    // PostgreSQL database credentials
    // Default values
    $host = 'localhost';
    $dbname = 'rpgconquestgame';
    $username = 'postgres';
    $password = 'postgres';

    // Check if the file exists
    if (file_exists($path.$configFile)) {
        // Parse the INI file
        $config = parse_ini_file($path.$configFile);

        // Check if the required keys exist
        if ( isset($config['host']) ){
            $host = $config['host'];
        }
        if ( isset($config['dbname']) ){
            $dbname = $config['dbname'];
        }
        if ( isset($config['username']) ){
            $username = $config['username'];
        }
        if ( isset($config['password']) ) {
            $password = $config['password'];
        }
    }

    // Attempt to connect to PostgreSQL database
    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (isset($_SESSION['DEBUG']) && $_SESSION['DEBUG'] == true){
            echo "Connected successfully to database $dbname.<br />";
        }
        return $pdo;

    } catch (PDOException $e) {
        echo __FUNCTION__."(): Connection failed: " . $e->getMessage()."<br />";
        return NULL;
    }
}

function tableExists($pdo, $tableName) {
    try{
        $stmt = $pdo->prepare("SELECT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_name = :tableName
        )");
        $stmt->execute([':tableName' => $tableName]);
    } catch (PDOException $e) {
        echo __FUNCTION__."(): $tableName failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    return $stmt->fetchColumn();
}

function destroyAllTables($pdo) {
    try {
        // Get list of tables in the database
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Drop each table
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS $table CASCADE");
            echo "Table $table dropped successfully.<br \>";
        }

        // Success message
        echo "All tables in database have been destroyed successfully.<br />";
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Error: " . $e->getMessage();
    }
}

function gameReady() {

    $path = '';
    $path1 = "/var/www/RPGConquestGame";
    $path2 = "..";

    // Path to the config.ini file
    $configFile = "/var/config.ini";
    if (file_exists ($path1.$configFile)) {
        $path = $path1;
    }
    if (file_exists ($path2.$configFile)) {
        $path = $path2;
    }

    $pdo = getDBConnection();
    if ($pdo != NUll) {
        try {
            // Check if table exists
            $tableName = 'players';
            $exists = tableExists($pdo, $tableName);

            if (!$exists) {
                echo "Table '$tableName' Does not exist. Loading Database...<br />";

                $sqlFile = $path.'/var/setupBDD.sql';
                echo "Loading $sqlFile ... ";
                //$sqlFile = '../BDD/setupBDD.sql';
                if (file_exists($sqlFile)) {
                    echo 'Start <br />';
                    // Read SQL file
                    $sqlQueries = file_get_contents($sqlFile);
                    // Execute SQL queries
                    $pdo->exec($sqlQueries);
                    echo "SQL file executed successfully.<br />";
                }
                echo 'END <br />';


                if ( isset($_POST['config_name']) ) {
                    $fileNames = ['base', 'textes', 'worker_names'];
                    foreach ( $fileNames as $fileName ) {
                        $sqlFile =  sprintf('%s/var/setup%s_%s.sql',  $path, $_POST['config_name'], $fileName);
                        echo "Loading $sqlFile ...<br />";
                        if (file_exists($sqlFile)) {
                            echo 'Start <br />';
                            // Read SQL file
                            $sqlQueries = file_get_contents($sqlFile);
                            // Execute SQL queries
                            $pdo->exec($sqlQueries);
                            echo "SQL file $sqlFile executed successfully.<br />";
                        } else echo "SQL file $sqlFile UNFOUND.<br />";
                    }

                    $sqlFile =  $path.'/var/setup'.$_POST['config_name'].'_hobbys.sql';
                    echo "Loading $sqlFile ...<br />";
                    if (file_exists($sqlFile)) {
                        echo 'Start <br />';
                        // Read SQL file
                        $sqlQueries = file_get_contents($sqlFile);
                        // Execute SQL queries
                        $pdo->exec($sqlQueries);
                        echo "SQL file $sqlFile executed successfully.<br />";
                        try{
                            // Get all powers with no link_power_type
                            $sql = "SELECT id FROM power_types WHERE name = 'Hobby'";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (PDOException $e) {
                            echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                            return NULL;
                        }
                        // Fetch the results
                        $power_types = $stmt->fetchALL(PDO::FETCH_ASSOC);
                        try{
                            // Get all powers with no link_power_type
                            $sql = "SELECT id FROM powers WHERE id NOT IN
                                ( SELECT power_id FROM link_power_type )";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (PDOException $e) {
                            echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                            return NULL;
                        }
                        // Fetch the results
                        $powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            // Get all powers with no link_power_type
                            $sql = "INSERT INTO link_power_type(power_id, power_type_id) VALUES ";
                            $firstIter = True;
                            foreach ($powers as $power){
                                $sql .= sprintf(
                                    "%s(%s,%s)",
                                    $firstIter ? '' : ',',
                                    $power['id'],
                                    $power_types[0]['id']
                                );
                                $firstIter = FALSE;
                            }
                            try{
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                            } catch (PDOException $e) {
                                echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                                return NULL;
                            }
                        echo "SQL INSERT link_power_type executed successfully.<br />";
                    } else echo "SQL file $sqlFile UNFOUND.<br />";

                    $sqlFile =  $path.'/var/setup'.$_POST['config_name'].'_jobs.sql';
                    echo "Loading $sqlFile ...<br />";
                    if (file_exists($sqlFile)) {
                        echo 'Start <br />';
                        // Read SQL file
                        $sqlQueries = file_get_contents($sqlFile);
                        // Execute SQL queries
                        $pdo->exec($sqlQueries);
                        echo "SQL file $sqlFile executed successfully.<br />";
                        try{
                            // Get all powers with no link_power_type
                            $sql = "SELECT id FROM power_types WHERE name = 'Metier'";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (PDOException $e) {
                            echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                            return NULL;
                        }
                        // Fetch the results
                        $power_types = $stmt->fetchALL(PDO::FETCH_ASSOC);
                        try{
                            // Get all powers with no link_power_type
                            $sql = "SELECT id FROM powers WHERE id NOT IN
                                ( SELECT power_id FROM link_power_type )";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (PDOException $e) {
                            echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                            return NULL;
                        }
                        // Fetch the results
                        $powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            // Get all powers with no link_power_type
                            $sql = "INSERT INTO link_power_type(power_id, power_type_id) VALUES ";
                            $firstIter = True;
                            foreach ($powers as $power){
                                $sql .= sprintf(
                                    "%s(%s,%s)",
                                    $firstIter ? '' : ',',
                                    $power['id'],
                                    $power_types[0]['id']
                                );
                                $firstIter = FALSE;
                            }
                            try{
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                            } catch (PDOException $e) {
                                echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                                return NULL;
                            }
                        echo "SQL INSERT link_power_type executed successfully.<br />";
                    } else echo "SQL file $sqlFile UNFOUND.<br />";

                    $sqlFile =  $path.'/var/setup'.$_POST['config_name'].'_advanced.sql';
                    if (
                        (strtolower(getConfig($pdo, 'DEBUG')) == 'true')
                        ||(strtolower(getConfig($pdo, 'DEBUG_REPORT')) == 'true')
                        || (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true')
                    )
                        $sqlFile =  $path.'/var/setup'.$_POST['config_name'].'_advanced_tests.sql';

                    echo "Loading $sqlFile ...<br />";
                    if (file_exists($sqlFile)) {
                        echo 'Start <br />';
                        // Read SQL file
                        $sqlQueries = file_get_contents($sqlFile);
                        // Execute SQL queries
                        $pdo->exec($sqlQueries);
                        echo "SQL file $sqlFile executed successfully.<br />";
                    } else echo "SQL file $sqlFile UNFOUND.<br />";
                }

                echo 'END <br />';
            }
        } catch (PDOException $e) {
            echo __FUNCTION__."(): Check Database failed: " . $e->getMessage()."<br />";
            return NULL;
        }
    }
    return $pdo;
}

