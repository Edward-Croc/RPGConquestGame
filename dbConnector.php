
<?php

function getDBConnection () {
    // PostgreSQL database credentials
    $host = 'localhost'; // Change this to your database host if it's different
    $dbname = 'rpgconquestgame';
    $username = 'php_gamedev';
    $password = 'php_gamedev';

    // Attempt to connect to PostgreSQL database
    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected successfully to database $dbname.<br />";
        return $pdo;

    } catch (PDOException $e) {
        echo "Connection failed: " . $e->getMessage()."<br />";
        return NULL;
    }
}

function getConfig($pdo, $configName) {
    $stmt = $pdo->prepare("SELECT value 
        FROM config 
        WHERE name = :configName
    ");
    $stmt->execute([':configName' => $configName]);
    return $stmt->fetchColumn();
}

function tableExists($pdo, $tableName) {
    $stmt = $pdo->prepare("SELECT EXISTS (
        SELECT 1 
        FROM information_schema.tables 
        WHERE table_name = :tableName
    )");
    $stmt->execute([':tableName' => $tableName]);
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
        echo "Error: " . $e->getMessage();
    }
}

function gameReady() {
    $pdo = getDBConnection();
    if ($pdo != NUll) {
        try {
            // Check if table exists
            $tableName = 'players';
            $exists = tableExists($pdo, $tableName);
    
            if (!$exists) {
                echo "Table '$tableName' Does not exist. Loading Database...<br />";

                $sqlFile = './setupBDD.sql';
                echo "Loading $sqlFile ...<br />";
                // Read SQL file
                $sqlQueries = file_get_contents($sqlFile);
                // Execute SQL queries
                $pdo->exec($sqlQueries);
                echo "SQL file executed successfully.<br />";

                $sqlFile = './setupVampire1966.sql';
                echo "Loading $sqlFile ...<br />";
                // Read SQL file
                $sqlQueries = file_get_contents($sqlFile);
                // Execute SQL queries
                $pdo->exec($sqlQueries);
                echo "SQL file executed successfully.<br />";
            }
        } catch (PDOException $e) {
            echo "Check Database failed: " . $e->getMessage()."<br />";
            return NULL;
        }
    }
    return $pdo;
}
    
