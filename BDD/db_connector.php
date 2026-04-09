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
        'folder' => 'RPGConquestGame',
        'game_prefix' => ''  // e.g., 'rpg1_' or 'demo_'
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

    // Validate config 
    $notNullList = ['host', 'dbname', 'username', 'password', 'db_type', 'folder'];
    foreach ( $notNullList as $key ) {
        if ( !isset($config[$key]) ){
            echo " Config key $key is not set !";
            die();
        }
    }
    foreach ( $config as $key => $value )
    if ( $config['db_type'] == 'postgres'){
        if (strpos( $config['game_prefix'], '.') !== false){
            // split prefix to elements 
            $prefixShards = explode('.', $config['game_prefix']);
            // if there are more than 2 elemet Postgresql will fail 
            if (count($prefixShards) > 2 ){
                echo " Table prefix Must never contain more than one '.' !";
                die();
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
    $_SESSION['DBTYPE'] = $config['db_type'];
    $_SESSION['FOLDER'] = $config['folder'];
    $_SESSION['GAME_PREFIX'] = $config['game_prefix'];

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
    $prefix = $_SESSION['GAME_PREFIX'];
    $prefixedTableName = $prefix . $tableName;
    try{
        if ($_SESSION['DBTYPE'] == 'postgres'){
            $tableSchema = 'public';
            if (strpos($prefix, '.') !== false){
                $tableSchema = explode('.', $prefix)[0];
            } else {
                $tableName = $prefixedTableName;
            }
            $stmt = $pdo->prepare("SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_name = :tableName AND table_schema = :tableSchema
            )");
            $stmt->execute([':tableName' => $tableName, ':tableSchema' => $tableSchema]);
        } else if ($_SESSION['DBTYPE'] == 'mysql'){
            $stmt = $pdo->prepare("SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_name = :tableName
            )");
            $stmt->execute([':tableName' => $prefixedTableName]);
        }
    } catch (PDOException $e) {
        echo __FUNCTION__."(): $prefixedTableName failed: " . $e->getMessage()."<br />";
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
    $prefix = $_SESSION['GAME_PREFIX'];

    try {
        if ($_SESSION['DBTYPE'] == 'postgres'){

            $tableSchema = 'public';
            $prefix_sql = '';
            if (strpos($prefix, '.') !== false){
                // split prefix to elements 
                $prefixShards = explode('.', $prefix);
                $tableSchema = $prefixShards[0];
                if (isset($prefixShards[1]))
                    $prefix_sql = " AND table_name LIKE '{$prefixShards[1]}%'";

            } else {
                $prefix_sql = " AND table_name LIKE '{$prefix}%'";
            }
            $prefix = '';
            $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = :tableSchema AND table_type = 'BASE TABLE' {$prefix_sql}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':tableSchema' => $tableSchema]);
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "tables: " . var_export($tables, true) . "<br />";

            // Drop each table
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS {$tableSchema}.{$prefix}{$table} CASCADE");
                echo "Table {$tableSchema}.{$prefix}{$table} dropped successfully.<br \>";
            }
        }
        if ($_SESSION['DBTYPE'] == 'mysql'){
            $tables = array(
                'controller_ressources',
                'ressources_config',
                'location_attack_logs',
                'controllers_known_enemies',
                'controller_known_locations', 
                'controller_worker',
                'worker_actions',
                'worker_powers',
                'workers_trace_links',
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

            echo "tables: " . var_export($tables, true) . "<br />";

            // Drop each table
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS {$prefix}{$table} CASCADE");
                echo "Table {$prefix}{$table} dropped successfully.<br \>";
            }
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
 * Load CSV file and insert data into database table
 * 
 * @param PDO $pdo Database connection
 * @param string $csvFile Path to CSV file
 * @param string $tableName Target table name (without prefix)
 * @param array $columns Array of column names in CSV order (can include lookup columns like 'origin_name->origin_id')
 * @return bool Success status
 */
function loadCSVFile($pdo, $csvFile, $tableName, $columns) {
    $prefix = $_SESSION['GAME_PREFIX'];
    $prefixedTable = $prefix . $tableName;

    if (!file_exists($csvFile)) {
        echo "CSV file $csvFile not found.<br />";
        return false;
    }

    try {
        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            echo "Failed to open CSV file $csvFile.<br />";
            return false;
        }

        // Read header row
        $header = fgetcsv($handle);

        // Process columns to handle lookups (e.g., 'origin_name->origin_id')
        $dbColumns = [];
        $lookupMaps = [];
        foreach ($columns as $col) {
            if (strpos($col, '->') !== false) {
                list($originCol, $targetCol) = explode('->', $col);
                $dbColumns[] = $targetCol;
                list($table, $column) = explode('__', $originCol);
                $lookupMaps[$targetCol] = ['table' => $table, 'column' => $column];
            } else {
                $dbColumns[] = $col;
            }
        }

        // Build lookup caches for foreign keys
        $lookupCaches = [];
        foreach ($lookupMaps as $targetCol => $lookupInfo) {
            $lookupTable = $prefix . $lookupInfo['table'];
            $lookupNameCol =  $lookupInfo['column'];
            try {
                $stmt = $pdo->query("SELECT id, {$lookupNameCol} FROM {$lookupTable}");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($results as $row) {
                    $lookupCaches[$targetCol][$row[$lookupNameCol]] = $row['id'];
                }
            } catch (PDOException $e) {
                echo "Warning: Could not build lookup cache for {$targetCol}: " . $e->getMessage() . "<br />";
            }
        }
        
        // Prepare insert statement
        $placeholders = implode(',', array_fill(0, count($dbColumns), '?'));
        $columnList = implode(',', array_map(function($col){
            return $col;
        }, $dbColumns));
        $sql = "INSERT INTO {$prefixedTable} ({$columnList}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                echo "Warning: Row " . ($rowCount + 1) . " " . var_export($row, true) . " has " . count($row) . " columns, expected " . count($header) . " in $csvFile.<br />";
                continue;
            }
            
            // Map CSV columns to values
            $rowData = array_combine($header, $row);
            
            // Build values array in column order
            $values = [];
            foreach ($columns as $col) {
                if (strpos($col, '->') !== false) {
                    list($csvCol, $dbCol) = explode('->', $col);
                    $lookupValue = $rowData[$col] ?? '';
                    if (isset($lookupCaches[$dbCol][$lookupValue])) {
                        $values[] = $lookupCaches[$dbCol][$lookupValue];
                    } else {
                        echo "Warning: Lookup value '{$lookupValue}' not found for {$col} in row " . ($rowCount + 1) . ".<br />";
                        echo "from: " . var_export($rowData, true) . "<br />";
                        $values[] = null;
                    }
                } else {
                    $value = $rowData[$col] ?? '';
                    // Convert empty strings to NULL for numeric fields
                    $values[] = ($value === '' || $value === null) ? null : $value;
                }
            }
            
            $stmt->execute($values);
            $rowCount++;
        }
        
        fclose($handle);
        echo "CSV file $csvFile loaded successfully ($rowCount rows).<br />";
        return true;
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Error loading CSV $csvFile: " . $e->getMessage()."<br />";
        return false;
    }
}

/**
 * Execute SQL UPDATE statements from CSV
 * CSV format: table_name,column_name,where_column,where_value,new_value
 * 
 * @param PDO $pdo Database connection
 * @param string $csvFile Path to CSV file
 * @return bool Success status
 */
function loadCSVUpdates($pdo, $csvFile) {
    $prefix = $_SESSION['GAME_PREFIX'];
    
    if (!file_exists($csvFile)) {
        echo "CSV file $csvFile not found.<br />";
        return false;
    }
    
    try {
        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            echo "Failed to open CSV file $csvFile.<br />";
            return false;
        }
        
        // Skip header row
        $header = fgetcsv($handle);
        
        $updateCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) {
                continue;
            }
            
            $tableName = $prefix . trim($row[0]);
            $columnName = trim($row[1]);
            $whereColumn = trim($row[2]);
            $whereValue = trim($row[3]);
            $newValue = trim($row[4]);
            
            $sql = "UPDATE {$tableName} SET {$columnName} = ? WHERE {$whereColumn} = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newValue, $whereValue]);
            $updateCount++;
        }
        
        fclose($handle);
        echo "CSV updates from $csvFile executed successfully ($updateCount updates).<br />";
        return true;
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Error loading CSV updates $csvFile: " . $e->getMessage()."<br />";
        return false;
    }
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
                    $sqlQueries = str_replace('{prefix}', $_SESSION['GAME_PREFIX'], $sqlQueries);
                    // Execute SQL queries
                    $pdo->exec($sqlQueries);
                    echo "SQL file executed successfully.<br />";
                }
                echo 'END <br />';

                if ( isset($_POST['config_name']) ) {
                    $fileNames = [
                        'base' => '',
                        'zones' => ['name', 'description', 'hide_turn_zero', 'controllers__lastname->claimer_controller_id', 'controllers__lastname->holder_controller_id'],
                        'textes' => ['name', 'value', 'description'],
                        'worker_origins' => ['name'],
                        'worker_names' => ['firstname', 'lastname', 'worker_origins__name->origin_id']
                    ];
                    foreach ( $fileNames as $fileName => $columns ) {
                        // Check for CSV file first
                        $csvFile = sprintf('%s/var/csv/setup%s_%s.csv', $path, $_POST['config_name'], $fileName);
                        $sqlFile = sprintf('%s/var/%s/setup%s_%s.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name'], $fileName);

                        if (file_exists($csvFile)) {
                            echo "Loading CSV file $csvFile ...<br />";
                            echo 'Start <br />';
                            
                            // Handle specific file types
                            if (in_array($fileName, ['worker_origins', 'worker_names', 'textes', 'zones'])) {
                                loadCSVFile($pdo, $csvFile, $fileName, $columns);
                            } elseif ($fileName === 'config') {
                                loadCSVUpdates($pdo, $csvFile);
                            } else {
                                // For base and zones, they contain complex SQL with subqueries
                                // CSV support for these would require more complex handling
                                echo "CSV file $csvFile found, but complex SQL operations required. Using SQL fallback.<br />";
                                if (file_exists($sqlFile)) {
                                    $sqlQueries = file_get_contents($sqlFile);
                                    $sqlQueries = str_replace('{prefix}', $_SESSION['GAME_PREFIX'], $sqlQueries);
                                    $pdo->exec($sqlQueries);
                                    echo "SQL file $sqlFile executed successfully.<br />";
                                }
                            }
                        } else if (file_exists($sqlFile)) {
                            // Load SQL file if CSV doesn't exist
                            echo "Loading $sqlFile ...<br />";
                            echo 'Start <br />';
                            // Read SQL file
                            $sqlQueries = file_get_contents($sqlFile);
                            $sqlQueries = str_replace('{prefix}', $_SESSION['GAME_PREFIX'], $sqlQueries);
                            // Execute SQL queries
                            $pdo->exec($sqlQueries);
                            echo "SQL file $sqlFile executed successfully.<br />";
                        } else {
                            echo "Neither CSV nor SQL file found for $fileName.<br />";
                        }
                    }

                    // Check for CSV file first
                    $csvFile = sprintf('%s/var/csv/setup%s_hobbys.csv', $path, $_POST['config_name']);
                    $sqlFile = sprintf('%s/var/%s/setup%s_hobbys.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name']);
                    
                    if (file_exists($csvFile)) {
                        echo "Loading CSV file $csvFile ...<br />";
                        echo 'Start <br />';
                        // Load powers from CSV
                        loadCSVFile($pdo, $csvFile, 'powers', ['name', 'description', 'enquete', 'attack', 'defence', 'other']);
                        echo "CSV file $csvFile loaded successfully.<br />";
                    } else if (file_exists($sqlFile)) {
                        echo "Loading $sqlFile ...<br />";
                        echo 'Start <br />';
                        // Read SQL file
                        $sqlQueries = file_get_contents($sqlFile);
                        $sqlQueries = str_replace('{prefix}', $_SESSION['GAME_PREFIX'], $sqlQueries);
                        // Execute SQL queries
                        $pdo->exec($sqlQueries);
                        echo "SQL file $sqlFile executed successfully.<br />";
                    } else {
                        echo "Neither CSV nor SQL file found for hobbys.<br />";
                    }
                    
                    if (file_exists($csvFile) || file_exists($sqlFile)) {
                        $prefix = $_SESSION['GAME_PREFIX'];
                        try{
                            // Get all powers with no link_power_type
                            $sql = "SELECT id FROM {$prefix}power_types WHERE name = 'Hobby'";
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
                            $sql = "SELECT id FROM {$prefix}powers WHERE id NOT IN
                                ( SELECT power_id FROM {$prefix}link_power_type )";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (PDOException $e) {
                            echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                            return NULL;
                        }
                        // Fetch the results
                        $powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            // Get all powers with no link_power_type
                            $sql = "INSERT INTO {$prefix}link_power_type(power_id, power_type_id) VALUES ";
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

                    // Check for CSV file first
                    $csvFile = sprintf('%s/var/csv/setup%s_jobs.csv', $path, $_POST['config_name']);
                    $sqlFile = sprintf('%s/var/%s/setup%s_jobs.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name']);
                    
                    if (file_exists($csvFile)) {
                        echo "Loading CSV file $csvFile ...<br />";
                        echo 'Start <br />';
                        // Load powers from CSV
                        loadCSVFile($pdo, $csvFile, 'powers', ['name', 'description', 'enquete', 'attack', 'defence', 'other']);
                        echo "CSV file $csvFile loaded successfully.<br />";
                    } else if (file_exists($sqlFile)) {
                        echo "Loading $sqlFile ...<br />";
                        echo 'Start <br />';
                        // Read SQL file
                        $sqlQueries = file_get_contents($sqlFile);
                        $sqlQueries = str_replace('{prefix}', $_SESSION['GAME_PREFIX'], $sqlQueries);
                        // Execute SQL queries
                        $pdo->exec($sqlQueries);
                        echo "SQL file $sqlFile executed successfully.<br />";
                    } else {
                        echo "Neither CSV nor SQL file found for jobs.<br />";
                    }
                    
                    if (file_exists($csvFile) || file_exists($sqlFile)) {
                        try{
                            // Get all powers with no link_power_type
                            $sql = "SELECT id FROM {$prefix}power_types WHERE name = 'Metier'";
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
                            $sql = "SELECT id FROM {$prefix}powers WHERE id NOT IN
                                ( SELECT power_id FROM {$prefix}link_power_type )";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (PDOException $e) {
                            echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                            return NULL;
                        }
                        // Fetch the results
                        $powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            // Get all powers with no link_power_type
                            $sql = "INSERT INTO {$prefix}link_power_type(power_id, power_type_id) VALUES ";
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

                    // Check for CSV file first
                    $csvFile = sprintf('%s/var/csv/setup%s_advanced.csv', $path, $_POST['config_name']);
                    $sqlFile = sprintf('%s/var/%s/setup%s_advanced.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name']);
                    
                    if (file_exists($csvFile)) {
                        echo "Loading CSV file $csvFile ...<br />";
                        echo 'Start <br />';
                        loadCSVFile(
                            $pdo,
                            $csvFile,
                            'advanced',
                            [
                                'firstname',
                                'lastname',
                                'worker_origins__name->origin_id',
                                'zones__name->zone_id',
                                'controllers__lastname->controller_worker__id&new__id',
                                'controllers__lastname->holder_controller_id'
                            ]
                        );
                    } else if (file_exists($sqlFile)) {
                        echo 'Start <br />';
                        // Read SQL file
                        $sqlQueries = file_get_contents($sqlFile);
                        $sqlQueries = str_replace('{prefix}', $_SESSION['GAME_PREFIX'], $sqlQueries);
                        // Execute SQL queries
                        $pdo->exec($sqlQueries);
                        echo "SQL file $sqlFile executed successfully.<br />";
                    } else if (!file_exists($csvFile) && !file_exists($sqlFile)) {
                        echo "Neither CSV nor SQL file found for advanced.<br />";
                    }

                    if (
                        (strtolower(getConfig($pdo, 'DEBUG')) == 'true')
                        ||(strtolower(getConfig($pdo, 'DEBUG_REPORT')) == 'true')
                        || (strtolower(getConfig($pdo, 'DEBUG_ATTACK')) == 'true')
                        || (strtolower(getConfig($pdo, 'DEBUG_TRANSFORM')) == 'true')
                        || (strtolower(getConfig($pdo, 'ACTIVATE_TESTS')) == 'true')
                    ) {
                        // Check for CSV file first
                        $csvFile = sprintf('%s/var/csv/setup%s_advanced_tests.csv', $path, $_SESSION['DBTYPE'], $_POST['config_name']);
                        $sqlFile = sprintf('%s/var/%s/setup%s_advanced_tests.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name']);
                        
                        if (file_exists($csvFile)) {
                            echo "Loading CSV file $csvFile ...<br />";
                            echo 'Start <br />';
                            // Advanced tests CSV files may contain multiple tables, handle as needed
                            echo "CSV file $csvFile found, but specific loader not yet implemented for advanced_tests. Using SQL fallback.<br />";
                        }
                        
                        if (!file_exists($csvFile) && file_exists($sqlFile)) {
                            echo "Loading $sqlFile ...<br />";
                            echo 'Start <br />';
                            // Read SQL file
                            $sqlQueries = file_get_contents($sqlFile);
                            $sqlQueries = str_replace('{prefix}', $_SESSION['GAME_PREFIX'], $sqlQueries);
                            // Execute SQL queries
                            $pdo->exec($sqlQueries);
                            echo "SQL file $sqlFile executed successfully.<br />";
                        } else if (!file_exists($csvFile) && !file_exists($sqlFile)) {
                            echo "Neither CSV nor SQL file found for advanced_tests.<br />";
                        }
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
