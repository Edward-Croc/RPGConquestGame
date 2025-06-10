<?php


function getPath ($file) {

    $path = null;
    $paths = array( __DIR__ ,"..", ".", "/var/www/RPGConquestGame");
    foreach ( $paths AS $tmpPath ) {
        if (file_exists ($tmpPath.$file)) {
            $path = $tmpPath;
            break;
        }
        if (file_exists ($tmpPath."/..".$file)) {
            $path = $tmpPath."/..";
            break;
        }
    }
    return $path ; 
}

/**
 * Get and Check connection to the database
 * 
 * @return PDO $pdo
 */
function getDBConnection ($path, $configFile) {

    // PostgreSQL database credentials
    // Default values
    $host = 'localhost';
    $dbname = 'rpgconquestgame';
    $username = 'postgres';
    $password = 'postgres';
    $db_type = 'postgres';
    $folder = 'RPGConquestGame';

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
        if ( isset($config['db_type']) ) {
            $db_type = $config['db_type'];
        }
        if ( isset($config['folder']) ) {
            $folder = $config['folder'];
        }
    }
    $_SESSION['FOLDER'] = $folder;

    if ( $db_type == 'mysql' ) {
        // Attempt to connect to PostgreSQL database
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if (isset($_SESSION['DEBUG']) && $_SESSION['DEBUG'] == true){
                echo "Connected successfully to database $dbname.<br />";
            }
            return $pdo;

        } catch (PDOException $e) {
            echo __FUNCTION__."(): Connection failed: " . $e->getMessage()."<br />";
            return NULL;
        }
    }else{
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
}

/**
 * Checks that the database table does exist
 * 
 * @param PDO $pdo
 * @param string $tableName
 * 
 * @return bool
 * 
 */
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

/**
 * Searches and destroys all tables in database
 * 
 * @param PDO $pdo
 * 
 * @return bool 
 */
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
        return false;
    }
    return true;
}

/**
 * Check that the game is ready:
 *  - databse is accessible
 *  - tables are loaded
 *  - load tables if $_POST['config_name'] is set
 * 
 * @return PDO $pdo
 */
function gameReady() {

    // Path to the config.ini file
    $configFiles = array ("/var/local_config.ini", "/var/config.ini");
    $path = null;
    foreach ($configFiles AS $configFile) {
        $path = getPath($configFile);
        if ($path != null) break;
    }
    if ($_SESSION['DEBUG'] == true) echo "Config Path built : " . $path.$configFile . " </ br>";

    $pdo = getDBConnection($path, $configFile);
    if ($pdo != null) {
        try {
            // Check if table exists
            $tableName = 'players';
            $exists = tableExists($pdo, $tableName);

            if (!$exists) {
                echo "Table '$tableName' Does not exist. Loading Database...<br />";

                $config = parse_ini_file($path.$configFile);
                $dbtype = (!empty($config['db_type'])) ? $config['db_type'] : 'postgres';

                $sqlFile = sprintf('%s/var/%s/setupBDD.sql', $path, $dbtype);
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
                        $sqlFile =  sprintf('%s/var/%s/setup%s_%s.sql',  $path, $dbtype, $_POST['config_name'], $fileName);
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

                    $sqlFile =  sprintf('%s/var/%s/setup%s_hobbys.sql',$path, $dbtype, $_POST['config_name']);
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
                        $powerTypes = $stmt->fetchALL(PDO::FETCH_ASSOC);
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
                            $firstIter = true;
                            foreach ($powers as $power){
                                $sql .= sprintf(
                                    "%s(%s,%s)",
                                    $firstIter ? '' : ',',
                                    $power['id'],
                                    $powerTypes[0]['id']
                                );
                                $firstIter = false;
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

                    $sqlFile = sprintf('%s/var/%s/setup%s_jobs.sql', $path, $dbtype, $_POST['config_name']);
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
                        $powerTypes = $stmt->fetchALL(PDO::FETCH_ASSOC);
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
                            $firstIter = true;
                            foreach ($powers as $power){
                                $sql .= sprintf(
                                    "%s(%s,%s)",
                                    $firstIter ? '' : ',',
                                    $power['id'],
                                    $powerTypes[0]['id']
                                );
                                $firstIter = false;
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

                    $sqlFile = sprintf('%s/var/%s/setup%s_advanced.sql', $path, $dbtype, $_POST['config_name']);
                    if (file_exists($sqlFile)) {
                        echo 'Start <br />';
                        // Read SQL file
                        $sqlQueries = file_get_contents($sqlFile);
                        // Execute SQL queries
                        $pdo->exec($sqlQueries);
                        echo "SQL file $sqlFile executed successfully.<br />";
                    } else echo "SQL file $sqlFile UNFOUND.<br />";

                    if (
                        (strtolower(getConfig($pdo, 'DEBUG')) == 'true')
                        ||(strtolower(getConfig($pdo, 'DEBUG_REPORT')) == 'true')
                        || (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true')
                        || (strtolower(getConfig($pdo, 'DEBUG_TRANSFORM')) == 'true')
                        || (strtolower(getConfig($pdo, 'ACTIVATE_TESTS')) == 'true')
                    ) {
                        $sqlFile = sprintf('%s/var/%s/setup%s_advanced_tests.sql', $path, $dbtype, $_POST['config_name']);
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
                }

                echo 'END <br />';
            }
        } catch (PDOException $e) {
            echo __FUNCTION__."(): Check Database failed: " . $e->getMessage()."<br />";
            return null;
        }
    } else {
        echo 'Impssible de se connecter a la base de donnée.';
        if ($path == null) echo 'Aucun fichier de configuration trouvée.';
    }
    return $pdo;
}

