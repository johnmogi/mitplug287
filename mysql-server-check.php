<?php
// Script to check MySQL server status and list databases

// Common credentials to try
$credentials = [
    ['user' => 'root', 'pass' => ''],      // Default XAMPP/WAMP
    ['user' => 'root', 'pass' => 'root'],   // Common MAMP/other
    ['user' => 'wordpress', 'pass' => 'wordpress'], // Common WordPress
    ['user' => 'admin', 'pass' => 'admin'], // Common admin
    ['user' => 'bmit', 'pass' => 'bmit'],   // BMIT specific
    ['user' => 'mitnafun', 'pass' => 'mitnafun'], // Mitnafun specific
];

$hosts = ['localhost', '127.0.0.1', 'db', 'mysql'];

echo "=== MySQL Server Check ===\n\n";

$connected = false;

foreach ($hosts as $host) {
    echo "Trying host: $host\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach ($credentials as $cred) {
        $user = $cred['user'];
        $pass = $cred['pass'];
        
        echo "Testing: $user@$host... ";
        
        try {
            // Try to connect without selecting a database first
            $mysqli = @new mysqli($host, $user, $pass);
            
            if ($mysqli->connect_error) {
                echo "❌ Failed: " . $mysqli->connect_error . "\n";
                continue;
            }
            
            echo "✅ Connected!\n";
            echo "MySQL Server Version: " . $mysqli->server_info . "\n\n";
            
            // List all databases
            echo "Available databases:\n";
            $result = $mysqli->query("SHOW DATABASES");
            
            if ($result) {
                $databases = [];
                while ($row = $result->fetch_row()) {
                    $databases[] = $row[0];
                }
                
                foreach ($databases as $db) {
                    echo "- $db\n";
                }
                
                $connected = true;
                
                // If we found the 'local' database, check its tables
                if (in_array('local', $databases)) {
                    echo "\n=== Checking 'local' database ===\n";
                    
                    if ($mysqli->select_db('local')) {
                        echo "✅ Selected 'local' database.\n";
                        
                        // List all tables with the edc_ prefix
                        $result = $mysqli->query("SHOW TABLES LIKE 'edc_%'");
                        $tables = [];
                        
                        while ($row = $result->fetch_row()) {
                            $tables[] = $row[0];
                        }
                        
                        if (!empty($tables)) {
                            echo "\nFound " . count($tables) . " tables with prefix 'edc_':\n";
                            foreach ($tables as $table) {
                                echo "- $table\n";
                            }
                            
                            // Check for the posts table
                            if (in_array('edc_posts', $tables)) {
                                $result = $mysqli->query("SELECT COUNT(*) as count FROM edc_posts WHERE post_type = 'product'");
                                if ($result) {
                                    $row = $result->fetch_assoc();
                                    echo "\nFound {$row['count']} products in edc_posts\n";
                                }
                            }
                        } else {
                            echo "No tables with prefix 'edc_' found.\n";
                            
                            // List all tables to see what's there
                            $result = $mysqli->query("SHOW TABLES");
                            $all_tables = [];
                            
                            while ($row = $result->fetch_row()) {
                                $all_tables[] = $row[0];
                            }
                            
                            if (!empty($all_tables)) {
                                echo "\nAll tables in 'local' database:\n";
                                foreach ($all_tables as $table) {
                                    echo "- $table\n";
                                }
                            }
                        }
                    } else {
                        echo "❌ Could not select 'local' database: " . $mysqli->error . "\n";
                    }
                }
                
                break 2; // Exit both loops on successful connection
            } else {
                echo "❌ Failed to list databases: " . $mysqli->error . "\n";
            }
            
            $mysqli->close();
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
}

if (!$connected) {
    echo "\n❌ Could not connect to MySQL server with any of the provided credentials.\n";
    echo "Please check that your MySQL server is running and the credentials are correct.\n";
}

echo "\n=== Check Complete ===\n";
