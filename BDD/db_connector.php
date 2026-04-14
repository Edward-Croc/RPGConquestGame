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

        // Separate columns into: regular, FK lookups, and linkTable_ junction inserts
        $dbColumns = [];
        $lookupMaps = [];
        $linkTableDefs = [];

        foreach ($columns as $col) {
            if (strpos($col, 'linkTable_') === 0) {
                // linkTable_ column: e.g. linkTable_power_types__name->link_power_type__power_type_id
                // Creates a row in a junction table after the main INSERT.
                // Format: linkTable_{lookupTable}__{lookupCol}->{junctionTable}__{junctionCol}
                $spec = substr($col, strlen('linkTable_'));
                list($lookupPart, $junctionPart) = explode('->', $spec);
                list($lookupTable, $lookupCol) = explode('__', $lookupPart);
                list($junctionTable, $junctionCol) = explode('__', $junctionPart);

                $linkTableDefs[] = [
                    'csvHeader'     => $col,
                    'lookupTable'   => $lookupTable,
                    'lookupCol'     => $lookupCol,
                    'junctionTable' => $junctionTable,
                    'junctionCol'   => $junctionCol,
                ];
            } elseif (strpos($col, '->') !== false) {
                list($originCol, $targetPart) = explode('->', $col);
                list($table, $column) = explode('__', $originCol);

                if (strpos($targetPart, '__') !== false) {
                    // Compound lookup: table__col->junctionTable__junctionCol
                    // Resolves: lookup table by col → get id → find junction row where junctionCol = id → return junction.id
                    list($junctionTable, $junctionCol) = explode('__', $targetPart);
                    $dbColumns[] = $junctionTable . '_id';
                    $lookupMaps[$junctionTable . '_id'] = [
                        'table' => $table, 'column' => $column,
                        'compound' => true, 'junctionTable' => $junctionTable, 'junctionCol' => $junctionCol
                    ];
                } else {
                    // Simple lookup: table__col->targetCol
                    $dbColumns[] = $targetPart;
                    $lookupMaps[$targetPart] = ['table' => $table, 'column' => $column];
                }
            } else {
                $dbColumns[] = $col;
            }
        }

        // Build lookup caches for FK columns
        $lookupCaches = [];
        foreach ($lookupMaps as $targetCol => $lookupInfo) {
            $lookupTable = $prefix . $lookupInfo['table'];
            $lookupNameCol = $lookupInfo['column'];

            if (!empty($lookupInfo['compound'])) {
                // Compound lookup: resolve through a junction table
                // e.g. powers.name → powers.id → link_power_type.power_id → link_power_type.id
                $jt = $prefix . $lookupInfo['junctionTable'];
                $jc = $lookupInfo['junctionCol'];
                try {
                    $stmt = $pdo->query("SELECT jt.id, lt.{$lookupNameCol}
                        FROM {$jt} jt
                        JOIN {$lookupTable} lt ON lt.id = jt.{$jc}");
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($results as $row) {
                        $lookupCaches[$targetCol][$row[$lookupNameCol]] = $row['id'];
                    }
                } catch (PDOException $e) {
                    echo "Warning: Could not build compound lookup cache for {$targetCol}: " . $e->getMessage() . "<br />";
                }
            } else {
                // Simple lookup
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
        }

        // Build lookup caches for linkTable_ columns
        $linkTableCaches = [];
        foreach ($linkTableDefs as $def) {
            $ltTable = $prefix . $def['lookupTable'];
            $ltCol = $def['lookupCol'];
            try {
                $stmt = $pdo->query("SELECT id, {$ltCol} FROM {$ltTable}");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($results as $row) {
                    $linkTableCaches[$def['csvHeader']][$row[$ltCol]] = $row['id'];
                }
            } catch (PDOException $e) {
                echo "Warning: Could not build linkTable cache for {$def['csvHeader']}: " . $e->getMessage() . "<br />";
            }
        }

        // Detect back-reference column for each junction table
        // The junction table has: id, {junctionCol}, and a back-reference to the main table
        // The back-reference column is the one referencing the main table (e.g. power_id for powers)
        foreach ($linkTableDefs as &$def) {
            $jtPrefixed = $prefix . $def['junctionTable'];
            try {
                $stmt = $pdo->query("DESCRIBE {$jtPrefixed}");
                $jtColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                // Find the column that isn't 'id' and isn't the junction lookup column
                foreach ($jtColumns as $jtCol) {
                    if ($jtCol !== 'id' && $jtCol !== $def['junctionCol']) {
                        $def['backRefCol'] = $jtCol;
                        break;
                    }
                }
            } catch (PDOException $e) {
                echo "Warning: Could not detect back-ref column for {$def['junctionTable']}: " . $e->getMessage() . "<br />";
            }
        }
        unset($def);

        // Prepare main insert statement
        // Use upsert for config table (base defaults + scenario overrides coexist)
        $placeholders = implode(',', array_fill(0, count($dbColumns), '?'));
        $columnList = implode(',', $dbColumns);
        $sql = "INSERT INTO {$prefixedTable} ({$columnList}) VALUES ({$placeholders})";
        $upsertTables = ['config', 'power_types'];
        if (in_array($tableName, $upsertTables)) {
            $updateCols = array_map(fn($col) => "{$col}=VALUES({$col})", $dbColumns);
            $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $updateCols);
        }
        $stmt = $pdo->prepare($sql);

        // Prepare junction table insert statements
        $linkTableStmts = [];
        foreach ($linkTableDefs as $def) {
            if (!empty($def['backRefCol'])) {
                $jtPrefixed = $prefix . $def['junctionTable'];
                $ltSql = "INSERT INTO {$jtPrefixed} ({$def['junctionCol']}, {$def['backRefCol']}) VALUES (?, ?)";
                $linkTableStmts[$def['csvHeader']] = $pdo->prepare($ltSql);
            }
        }

        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                echo "Warning: Row " . ($rowCount + 1) . " " . var_export($row, true) . " has " . count($row) . " columns, expected " . count($header) . " in $csvFile.<br />";
                continue;
            }

            // Map CSV columns to values
            $rowData = array_combine($header, $row);

            // Build values array for main table insert
            $values = [];
            foreach ($columns as $col) {
                if (strpos($col, 'linkTable_') === 0) {
                    continue; // handled after main insert
                } elseif (strpos($col, '->') !== false) {
                    list($csvCol, $targetPart) = explode('->', $col);
                    // For compound lookups, the cache key is {junctionTable}_id
                    if (strpos($targetPart, '__') !== false) {
                        list($junctionTable, ) = explode('__', $targetPart);
                        $dbCol = $junctionTable . '_id';
                    } else {
                        $dbCol = $targetPart;
                    }
                    $lookupValue = $rowData[$col] ?? '';
                    if (isset($lookupCaches[$dbCol][$lookupValue])) {
                        $values[] = $lookupCaches[$dbCol][$lookupValue];
                    } else {
                        if ($lookupValue !== '') {
                            echo "Warning: Lookup value '{$lookupValue}' not found for {$col} in row " . ($rowCount + 1) . ".<br />";
                            echo "from: " . var_export($rowData, true) . "<br />";
                        }
                        $values[] = null;
                    }
                } else {
                    $value = $rowData[$col] ?? '';
                    $values[] = ($value === '' || $value === null) ? null : $value;
                }
            }

            $stmt->execute($values);
            $newId = $pdo->lastInsertId();

            // Process linkTable_ junction inserts
            foreach ($linkTableDefs as $def) {
                $csvHeader = $def['csvHeader'];
                $lookupValue = $rowData[$csvHeader] ?? '';
                if ($lookupValue === '') continue;

                if (isset($linkTableCaches[$csvHeader][$lookupValue])) {
                    $lookupId = $linkTableCaches[$csvHeader][$lookupValue];
                    if (isset($linkTableStmts[$csvHeader])) {
                        $linkTableStmts[$csvHeader]->execute([$lookupId, $newId]);
                    }
                } else {
                    echo "Warning: linkTable lookup '{$lookupValue}' not found for {$csvHeader} in row " . ($rowCount + 1) . ".<br />";
                }
            }

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
 * Load workers from CSV into 4 tables: workers, controller_worker,
 * worker_actions, worker_powers. One row per worker in the CSV.
 *
 * CSV columns:
 *   firstname, lastname,
 *   worker_origins__name->origin_id,
 *   zones__name->zone_id,
 *   controllers__lastname->controller_id,
 *   action_choice, action_params,
 *   powers (pipe-separated list of power names)
 *
 * @param PDO $pdo Database connection
 * @param string $csvFile Path to CSV file
 * @return bool Success status
 */
function loadWorkersCSV($pdo, $csvFile) {
    $prefix = $_SESSION['GAME_PREFIX'];

    if (!file_exists($csvFile)) {
        echo "Workers CSV file $csvFile not found.<br />";
        return false;
    }

    try {
        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            echo "Failed to open workers CSV $csvFile.<br />";
            return false;
        }
        $header = fgetcsv($handle);

        // Build lookup caches
        $originCache = [];
        $stmt = $pdo->query("SELECT id, name FROM {$prefix}worker_origins");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $originCache[$r['name']] = $r['id'];
        }
        $zoneCache = [];
        $stmt = $pdo->query("SELECT id, name FROM {$prefix}zones");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $zoneCache[$r['name']] = $r['id'];
        }
        $controllerCache = [];
        $stmt = $pdo->query("SELECT id, lastname FROM {$prefix}controllers");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $controllerCache[$r['lastname']] = $r['id'];
        }
        // Compound lookup: power name -> link_power_type.id
        $linkPowerCache = [];
        $stmt = $pdo->query("SELECT lpt.id, p.name
            FROM {$prefix}link_power_type lpt
            JOIN {$prefix}powers p ON p.id = lpt.power_id");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $linkPowerCache[$r['name']] = $r['id'];
        }

        // Prepared statements for the 4 tables
        $insertWorker = $pdo->prepare(
            "INSERT INTO {$prefix}workers (firstname, lastname, origin_id, zone_id) VALUES (?, ?, ?, ?)"
        );
        $insertCW = $pdo->prepare(
            "INSERT INTO {$prefix}controller_worker (controller_id, worker_id) VALUES (?, ?)"
        );
        $insertAction = $pdo->prepare(
            "INSERT INTO {$prefix}worker_actions
             (worker_id, controller_id, turn_number, zone_id, action_choice, action_params)
             VALUES (?, ?, 0, ?, ?, ?)"
        );
        $insertPower = $pdo->prepare(
            "INSERT INTO {$prefix}worker_powers (worker_id, link_power_type_id) VALUES (?, ?)"
        );

        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                echo "Warning: Row " . ($rowCount + 1) . " has wrong column count in $csvFile.<br />";
                continue;
            }
            $data = array_combine($header, $row);

            $originName = $data['worker_origins__name->origin_id'] ?? '';
            $zoneName = $data['zones__name->zone_id'] ?? '';
            $controllerName = $data['controllers__lastname->controller_id'] ?? '';

            $originId = $originCache[$originName] ?? null;
            $zoneId = $zoneCache[$zoneName] ?? null;
            $controllerId = $controllerCache[$controllerName] ?? null;

            if ($originId === null || $zoneId === null || $controllerId === null) {
                echo "Warning: Row " . ($rowCount + 1) . " has unresolved lookups (origin=$originName, zone=$zoneName, ctrl=$controllerName).<br />";
                continue;
            }

            // 1. Insert worker
            $insertWorker->execute([$data['firstname'], $data['lastname'], $originId, $zoneId]);
            $workerId = $pdo->lastInsertId();

            // 2. Insert controller_worker junction
            $insertCW->execute([$controllerId, $workerId]);

            // 3. Insert worker_actions for turn 0
            $insertAction->execute([
                $workerId, $controllerId, $zoneId,
                $data['action_choice'] ?? 'passive',
                $data['action_params'] ?? '{}',
            ]);

            // 4. Insert worker_powers (pipe-separated list)
            $powersList = $data['powers'] ?? '';
            if ($powersList !== '') {
                foreach (explode('|', $powersList) as $powerName) {
                    $powerName = trim($powerName);
                    if ($powerName === '') continue;
                    if (isset($linkPowerCache[$powerName])) {
                        $insertPower->execute([$workerId, $linkPowerCache[$powerName]]);
                    } else {
                        echo "Warning: Power '{$powerName}' not found for worker {$data['lastname']}.<br />";
                    }
                }
            }

            $rowCount++;
        }

        fclose($handle);
        echo "Workers CSV $csvFile loaded successfully ($rowCount rows).<br />";
        return true;
    } catch (PDOException $e) {
        echo __FUNCTION__."(): Error loading workers CSV $csvFile: " . $e->getMessage()."<br />";
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
                        'power_types' => ['id', 'name', 'description'],
                        'factions' => ['name'],
                        'players' => ['username', 'passwd', 'is_privileged'],
                        'controllers' => ['firstname', 'lastname', 'ia_type', 'secret_controller', 'url', 'story', 'can_build_base', 'start_workers', 'turn_recruited_workers', 'turn_firstcome_workers', 'factions__name->faction_id', 'factions__name->fake_faction_id'],
                        'player_controller' => ['players__username->player_id', 'controllers__lastname->controller_id'],
                        'ressources_config' => ['ressource_name', 'presentation', 'stored_text', 'is_rollable', 'is_stored', 'base_building_cost', 'base_moving_cost', 'location_repaire_cost'],
                        'controller_ressources' => ['controllers__lastname->controller_id', 'ressources_config__ressource_name->ressource_id', 'amount', 'amount_stored', 'end_turn_gain'],
                        'zones' => ['name', 'description', 'hide_turn_zero', 'controllers__lastname->claimer_controller_id', 'controllers__lastname->holder_controller_id'],
                        'locations' => ['name', 'description', 'hidden_description', 'discovery_diff', 'zones__name->zone_id', 'controllers__lastname->controller_id', 'is_base', 'can_be_destroyed', 'can_be_repaired', 'activate_json'],
                        'artefacts' => ['name', 'description', 'full_description', 'locations__name->location_id'],
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
                            // Map file names to actual table names where they differ
                            $tableNameMap = ['textes' => 'config'];
                            if (in_array($fileName, ['power_types', 'factions', 'players', 'controllers', 'player_controller', 'ressources_config', 'controller_ressources', 'locations', 'artefacts', 'worker_origins', 'worker_names', 'textes', 'zones'])) {
                                $tableName = $tableNameMap[$fileName] ?? $fileName;
                                loadCSVFile($pdo, $csvFile, $tableName, $columns);
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

                    // Auto-assign every controller to every privileged player.
                    // This replaces the need to list gm->all_controllers links in the
                    // player_controller CSV — admins get full access automatically.
                    try {
                        $prefix = $_SESSION['GAME_PREFIX'];
                        $sql = "INSERT IGNORE INTO {$prefix}player_controller (player_id, controller_id)
                                SELECT p.id, c.id
                                FROM {$prefix}players p
                                CROSS JOIN {$prefix}controllers c
                                WHERE p.is_privileged = 1";
                        $pdo->exec($sql);
                        echo "Auto-assigned all controllers to privileged players.<br />";
                    } catch (PDOException $e) {
                        echo "Warning: auto-assign privileged players failed: " . $e->getMessage() . "<br />";
                    }

                    // Load powers from CSV or SQL files for each power type.
                    // CSV files with linkTable_ column handle link_power_type automatically.
                    // SQL fallback uses post-processing to link unlinked powers to their type.
                    $powerFileTypes = [
                        'hobbys'          => 'Hobby',
                        'jobs'            => 'Metier',
                        'disciplines'     => 'Discipline',
                        'transformations' => 'Transformation',
                    ];
                    $powerCsvColumns = ['name', 'description', 'enquete', 'attack', 'defence', 'other',
                        'linkTable_power_types__name->link_power_type__power_type_id'];

                    foreach ($powerFileTypes as $powerFileName => $powerTypeName) {
                        $csvFile = sprintf('%s/var/csv/setup%s_%s.csv', $path, $_POST['config_name'], $powerFileName);
                        $sqlFile = sprintf('%s/var/%s/setup%s_%s.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name'], $powerFileName);

                        $loaded = false;
                        $usedCsv = false;
                        if (file_exists($csvFile)) {
                            echo "Loading CSV file $csvFile ...<br />";
                            echo 'Start <br />';
                            loadCSVFile($pdo, $csvFile, 'powers', $powerCsvColumns);
                            echo "CSV file $csvFile loaded successfully.<br />";
                            $loaded = true;
                            $usedCsv = true;
                        } elseif (file_exists($sqlFile)) {
                            echo "Loading $sqlFile ...<br />";
                            echo 'Start <br />';
                            $sqlQueries = file_get_contents($sqlFile);
                            $sqlQueries = str_replace('{prefix}', $_SESSION['GAME_PREFIX'], $sqlQueries);
                            $pdo->exec($sqlQueries);
                            echo "SQL file $sqlFile executed successfully.<br />";
                            $loaded = true;
                        } else {
                            echo "Neither CSV nor SQL file found for $powerFileName.<br />";
                        }

                        // SQL fallback: link unlinked powers to their power_type.
                        // Not needed for CSV with linkTable_ column (handled automatically).
                        if ($loaded && !$usedCsv) {
                            $prefix = $_SESSION['GAME_PREFIX'];
                            try {
                                $sql = "SELECT id FROM {$prefix}power_types WHERE name = '{$powerTypeName}'";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                            } catch (PDOException $e) {
                                echo __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                                return NULL;
                            }
                            $powerTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($powerTypes)) {
                                try {
                                    $sql = "SELECT id FROM {$prefix}powers WHERE id NOT IN
                                        ( SELECT power_id FROM {$prefix}link_power_type )";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute();
                                } catch (PDOException $e) {
                                    echo __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                                    return NULL;
                                }
                                $powers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                if (!empty($powers)) {
                                    $sql = "INSERT INTO {$prefix}link_power_type(power_id, power_type_id) VALUES ";
                                    $firstIter = true;
                                    foreach ($powers as $power) {
                                        $sql .= sprintf("%s(%s,%s)", $firstIter ? '' : ',', $power['id'], $powerTypes[0]['id']);
                                        $firstIter = false;
                                    }
                                    try {
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute();
                                    } catch (PDOException $e) {
                                        echo __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
                                        return NULL;
                                    }
                                }
                                echo "SQL INSERT link_power_type for $powerTypeName executed successfully.<br />";
                            }
                        }
                    }

                    // Load faction_powers (after powers + link_power_type are populated)
                    $csvFile = sprintf('%s/var/csv/setup%s_faction_powers.csv', $path, $_POST['config_name']);
                    $sqlFile = sprintf('%s/var/%s/setup%s_faction_powers.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name']);
                    if (file_exists($csvFile)) {
                        echo "Loading CSV file $csvFile ...<br />";
                        echo 'Start <br />';
                        loadCSVFile($pdo, $csvFile, 'faction_powers',
                            ['factions__name->faction_id', 'powers__name->link_power_type__power_id']);
                        echo "CSV file $csvFile loaded successfully.<br />";
                    } elseif (file_exists($sqlFile)) {
                        echo "Loading $sqlFile ...<br />";
                        echo 'Start <br />';
                        $sqlQueries = file_get_contents($sqlFile);
                        $sqlQueries = str_replace('{prefix}', $_SESSION['GAME_PREFIX'], $sqlQueries);
                        $pdo->exec($sqlQueries);
                        echo "SQL file $sqlFile executed successfully.<br />";
                    } else {
                        echo "Neither CSV nor SQL file found for faction_powers.<br />";
                    }

                    // Load workers (advanced): workers + controller_worker + worker_actions + worker_powers
                    $csvFile = sprintf('%s/var/csv/setup%s_advanced.csv', $path, $_POST['config_name']);
                    $sqlFile = sprintf('%s/var/%s/setup%s_advanced.sql', $path, $_SESSION['DBTYPE'], $_POST['config_name']);

                    if (file_exists($csvFile)) {
                        echo "Loading CSV file $csvFile ...<br />";
                        echo 'Start <br />';
                        loadWorkersCSV($pdo, $csvFile);
                    } else if (file_exists($sqlFile)) {
                        echo 'Start <br />';
                        $sqlQueries = file_get_contents($sqlFile);
                        $sqlQueries = str_replace('{prefix}', $_SESSION['GAME_PREFIX'], $sqlQueries);
                        $pdo->exec($sqlQueries);
                        echo "SQL file $sqlFile executed successfully.<br />";
                    } else {
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
