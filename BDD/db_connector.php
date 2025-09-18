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
 * Loads DB config from file or defaults
 * @param string $path
 * @param string $configFile
 * @return array
 */
function loadDBConfig($path, $configFile) {
    // Default values
    $config = [
        'host' => 'localhost',
        'dbname' => 'rpgconquestgame',
        'username' => 'postgres',
        'password' => 'postgres',
        'db_type' => 'postgres',
        'folder' => 'RPGConquestGame'
    ];

    // Check if the file exists
    if (file_exists($path . $configFile)) {
        $fileConfig = parse_ini_file($path . $configFile);
        foreach ($config as $key => $value) {
            if (isset($fileConfig[$key])) {
                $config[$key] = $fileConfig[$key];
            }
        }
    }
    return $config;
}

/**
 * Get and Check connection to the database
 *
 * @return PDO $pdo
 */
function getDBConnection ($path, $configFile) {
    $debug = strtolower($_SESSION['DEBUG']) === 'true';

    $config = loadDBConfig($path, $configFile);
    $_SESSION['DBNAME'] = $config['dbname'];
    $_SESSION['DBTYPE'] = $config['db_type'] ;
    $_SESSION['FOLDER'] = $config['folder'];

    if ( $config['db_type'] == 'mysql' ) {
        // Attempt to connect to PostgreSQL database
        try {
            $pdo = new PDO(
                sprintf("mysql:host=%s;dbname=%s", $config['host'], $config['dbname']),
                $config['username'],  $config['password']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if ($debug){
                echo sprintf("Connected successfully to database %s.<br />", $config['dbname']);
            }
            return $pdo;

        } catch (PDOException $e) {
            echo __FUNCTION__."(): Connection failed: " . $e->getMessage()."<br />";
            return NULL;
        }
    }else{
        // Attempt to connect to PostgreSQL database
        try {
            $pdo = new PDO(
                sprintf("pgsql:host=%s;dbname=%s", $config['host'], $config['dbname']),
                $config['username'],  $config['password']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if ($debug){
                echo sprintf("Connected successfully to database %s.<br />", $config['dbname']);
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
        if ($_SESSION['DBTYPE'] == 'postgres'){
            $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'";
            // Get list of tables in the database
            $stmt = $pdo->query($sql);
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        if ($_SESSION['DBTYPE'] == 'mysql'){
            $tables = array(
                'location_attack_logs',
                'controllers_known_enemies',
                'controller_known_locations', 
                'controller_worker',
                'worker_actions',
                'worker_powers',
                'workers',
                'worker_names',
                'worker_origins',
                'faction_powers',
                'link_power_type',
                'power_types',
                'powers',
                'player_controller',
                'artefacts',
                'locations',
                'zones',
                'controllers', 
                'factions',
                'players',
                'mechanics',
                'config'
            );
        }

        echo "tables: " . var_export($tables, true) . "<br />";

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
    $debug = false;

    // Path to the config.ini file
    $configFiles = array ("/var/local_config.ini", "/var/config.ini");
    $path = null;
    foreach ($configFiles AS $configFile) {
        $path = getPath($configFile);
        if ($path != null) break;
    }
    $_SESSION['PATH'] = $path;
    $_SESSION['configFile'] = $configFile;
    if (!empty($_SESSION['DEBUG']) && $_SESSION['DEBUG'] == true) {
        echo "Config Path built : " . $path.$configFile . " </ br>";
        $debug = true;
    }

    $pdo = getDBConnection($path, $configFile);
    if ($debug)
        echo "getDBConnection: </ br>";
    if ($pdo != null) {
        try {
            // Check if table exists
            $tableName = 'players';
            $exists = tableExists($pdo, $tableName);

            if ($debug)
                echo "tableExists players: " . $exists . " </ br>";
            if (!$exists) {
                echo "Table '$tableName' Does not exist. Loading Database...<br />";

                if ($debug)
                    echo 'dbtype : '.$_SESSION['DBTYPE']."; <br>";
                $sqlFile = sprintf('%s/var/%s/setupBDD.sql', $path, $_SESSION['DBTYPE']);
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
                        $sqlFile =  sprintf('%s/var/%s/setup%s_%s.sql',  $path, $_SESSION['DBTYPE'], $_POST['config_name'], $fileName);
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

                    $sqlFile =  sprintf('%s/var/%s/setup%s_hobbys.sql',$path, $_SESSION['DBTYPE'], $_POST['config_name']);
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

                    $sqlFile = sprintf('%s/var/%s/setup%s_jobs.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name']);
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

                    $sqlFile = sprintf('%s/var/%s/setup%s_advanced.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name']);
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
                        $sqlFile = sprintf('%s/var/%s/setup%s_advanced_tests.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name']);
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

function exportBDD() {

    $config = loadDBConfig($_SESSION['PATH'], $_SESSION['configFile']);

    // Output file
    $exportFile = sprintf('%s_export_%s.sql', $config['dbname'], date('Ymd_His'));
    $exportPath = sys_get_temp_dir() . '/' . $exportFile;

    // export BDD to file via command line
    if ( $config['db_type'] == 'mysql'){
        $command = sprintf('mysqldump -h %s -u %s -p%s %s > %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['dbname']),
            escapeshellarg($exportPath)
        );
    } else {
        $command = sprintf('PGPASSWORD=%s pg_dump -h %s -U %s -F p %s > %s',
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['username']),
            escapeshellarg($config['dbname']),
            escapeshellarg($exportPath)
        );
    }

    // Run export
    $output = shell_exec($command);

    // Check if file was created
    if (file_exists($exportPath)) {
        // Send file for download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($exportFile) . '"');
        header('Content-Length: ' . filesize($exportPath));
        readfile($exportPath);
        // Optionally delete the file after download
        unlink($exportPath);
        exit;
    } else {
        echo "<div class='notification is-danger'>Export failed. Check server permissions and availability.</div>";
        if ($output) echo "<pre>$output</pre>";
    }

}

/**
 * Import BDD from file.sql
 * @param PDO $pdo
 * @param string $file
 * @return bool
 */
function importBDD($pdo, $file){

    if ($file['error'] === UPLOAD_ERR_OK) {
        $tmpFilePath = $file['tmp_name'];
        $fileType = mime_content_type($tmpFilePath);
        $allowedTypes = ['application/sql', 'text/plain'];

        if (in_array($fileType, $allowedTypes)) {  
            $config = loadDBConfig($_SESSION['PATH'], $_SESSION['configFile']);

            // import BDD from file via command line
            if ($config['db_type'] == 'mysql'){
                $command = sprintf('mysql -h %s -u %s -p%s %s < %s',
                    escapeshellarg($config['host']),
                    escapeshellarg($config['username']),
                    escapeshellarg($config['password']),
                    escapeshellarg($config['dbname']),
                    escapeshellarg($tmpFilePath)
                );
            } else {
                $command = sprintf('PGPASSWORD=%s psql -h %s -U %s -d %s -f %s',
                    escapeshellarg($config['password']),
                    escapeshellarg($config['host']),
                    escapeshellarg($config['username']),
                    escapeshellarg($config['dbname']),
                    escapeshellarg($tmpFilePath)
                );
            }

            // Empty BDD
            destroyAllTables($pdo);
            // Run import
            $output = shell_exec($command);
            if ($output) echo "<pre>$output</pre>";
            // Success message
            echo "<div class='notification is-success'>Import completed successfully.</div>";
            return true;
        } else {
            echo "<div class='notification is-danger'>Invalid file type. Please upload a valid .sql file.</div>";
        }
    } else {
        echo "<div class='notification is-danger'>File upload error. Please try again.</div>";
    }

    return false;
}
