<?php
// Simple script to list database tables

// Database credentials - update these with your actual database credentials
$db_host = 'localhost';
$db_user = 'root';      // Default XAMPP username
$db_pass = '';          // Default XAMPP password (empty)
$db_name = 'wordpress'; // Default WordPress database name

try {
    // Connect to MySQL server
    $mysqli = new mysqli($db_host, $db_user, $db_pass);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "Connected to MySQL server successfully.\n";
    
    // List all databases
    echo "\n=== Databases ===\n";
    $result = $mysqli->query("SHOW DATABASES");
    while ($row = $result->fetch_row()) {
        echo "- " . $row[0] . "\n";
    }
    
    // Try to select the WordPress database
    if ($mysqli->select_db($db_name)) {
        echo "\nSelected database: $db_name\n";
        
        // List tables
        $result = $mysqli->query("SHOW TABLES");
        echo "\n=== Tables in $db_name ===\n";
        while ($row = $result->fetch_row()) {
            echo "- " . $row[0] . "\n";
        }
    } else {
        echo "\nCould not select database: $db_name\n";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Script Complete ===\n";
