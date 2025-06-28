<?php
// Script to check MySQL server status and list databases

// Common MySQL credentials to try
$credentials = [
    ['host' => 'localhost', 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'root'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'root'],
    ['host' => 'localhost', 'user' => 'admin', 'pass' => 'admin'],
    ['host' => '127.0.0.1', 'user' => 'admin', 'pass' => 'admin'],
    ['host' => 'localhost', 'user' => 'wordpress', 'pass' => 'wordpress'],
    ['host' => '127.0.0.1', 'user' => 'wordpress', 'pass' => 'wordpress'],
    ['host' => 'db', 'user' => 'root', 'pass' => ''],
    ['host' => 'mysql', 'user' => 'root', 'pass' => ''],
];

echo "=== MySQL Server Status Check ===\n\n";

$connected = false;

foreach ($credentials as $cred) {
    $host = $cred['host'];
    $user = $cred['user'];
    $pass = $cred['pass'];
    
    echo "Trying: mysql://{$user}@{$host}... ";
    
    try {
        $mysqli = @new mysqli($host, $user, $pass);
        
        if ($mysqli->connect_error) {
            echo "Failed: " . $mysqli->connect_error . "\n";
            continue;
        }
        
        echo "✅ Connected!\n";
        echo "MySQL Server Version: " . $mysqli->server_info . "\n";
        
        // List databases
        $result = $mysqli->query("SHOW DATABASES");
        $databases = [];
        
        echo "\n=== Available Databases ===\n";
        while ($row = $result->fetch_row()) {
            $databases[] = $row[0];
            echo "- " . $row[0] . "\n";
        }
        
        // Check for WordPress database
        $wp_dbs = array_filter($databases, function($db) {
            return preg_match('/(wordpress|wp_|bmit|site)/i', $db);
        });
        
        if (!empty($wp_dbs)) {
            echo "\n=== Potential WordPress Databases ===\n";
            foreach ($wp_dbs as $db) {
                echo "- $db\n";
                
                // List tables in this database
                $mysqli->select_db($db);
                $tables = $mysqli->query("SHOW TABLES");
                $table_count = $tables->num_rows;
                
                echo "  Tables: $table_count\n";
                
                // Show first 5 tables
                $count = 0;
                while ($row = $tables->fetch_row() && $count < 5) {
                    echo "  - " . $row[0] . "\n";
                    $count++;
                }
                
                if ($table_count > 5) {
                    echo "  ... and " . ($table_count - 5) . " more\n";
                }
            }
        } else {
            echo "\nNo WordPress databases found.\n";
        }
        
        $connected = true;
        $mysqli->close();
        break;
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

if (!$connected) {
    echo "❌ Could not connect to MySQL server with any of the provided credentials.\n";
    echo "Please check your MySQL server is running and update the credentials in this script.\n";
}

echo "\n=== Check Complete ===\n";
