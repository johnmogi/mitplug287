<?php
// Simple database connection test

echo "=== Database Connection Test ===\n";

// Database credentials - replace these with actual values from wp-config.php
define('DB_NAME', 'wordpress');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');

try {
    // Connect to MySQL
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD);
    
    if ($mysqli->connect_error) {
        throw new Exception("MySQL Connection Error: " . $mysqli->connect_error);
    }
    
    echo "Connected to MySQL server successfully.\n";
    
    // List databases
    echo "\n=== Databases ===\n";
    $result = $mysqli->query("SHOW DATABASES");
    while ($row = $result->fetch_row()) {
        echo "- " . $row[0] . "\n";
    }
    
    // Try to select the WordPress database
    if ($mysqli->select_db(DB_NAME)) {
        echo "\nSelected database: " . DB_NAME . "\n";
        
        // List tables
        $result = $mysqli->query("SHOW TABLES");
        echo "\n=== Tables in " . DB_NAME . " ===\n";
        while ($row = $result->fetch_row()) {
            echo "- " . $row[0] . "\n";
        }
    } else {
        echo "\nCould not select database: " . DB_NAME . "\n";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
