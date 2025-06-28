<?php
// Simple MySQL connection test with detailed error reporting

echo "=== MySQL Connection Test ===\n";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials - try common defaults
$hosts = [
    'localhost',
    '127.0.0.1',
    'mysql',
    'db',
    'database'
];

$users = [
    'root' => '',           // Default XAMPP/empty password
    'admin' => 'admin',      // Common admin password
    'wordpress' => 'wordpress',
    'user' => 'password',
    'root' => 'root'        // Common root password
];

$databases = [
    'wordpress',
    'wp',
    'bmit',
    'site',
    'database'
];

$connected = false;

// Test each combination
foreach ($hosts as $host) {
    if ($connected) break;
    
    echo "\nTrying host: $host\n";
    
    foreach ($users as $user => $password) {
        if ($connected) break;
        
        echo "  Trying user: $user\n";
        
        // Test connection without database first
        try {
            $mysqli = @new mysqli($host, $user, $password);
            
            if ($mysqli->connect_error) {
                echo "    Connection failed: " . $mysqli->connect_error . "\n";
                continue;
            }
            
            echo "    ✔ Connected to MySQL server as '$user'@'$host'\n";
            
            // List databases
            echo "    \n    === Available Databases ===\n";
            $result = $mysqli->query("SHOW DATABASES");
            $dbs = [];
            while ($row = $result->fetch_row()) {
                $dbs[] = $row[0];
                echo "    - " . $row[0] . "\n";
            }
            
            // Try to find WordPress database
            $wp_db = null;
            foreach ($dbs as $db) {
                if (preg_match('/(wordpress|wp_|bmit|site)/i', $db)) {
                    $wp_db = $db;
                    break;
                }
            }
            
            if ($wp_db) {
                echo "    \n    Found potential WordPress database: $wp_db\n";
                
                // Try to select the database
                if ($mysqli->select_db($wp_db)) {
                    echo "    ✔ Selected database: $wp_db\n";
                    
                    // List tables
                    $result = $mysqli->query("SHOW TABLES");
                    $tables = [];
                    while ($row = $result->fetch_row()) {
                        $tables[] = $row[0];
                    }
                    
                    echo "    \n    === Tables in $wp_db (" . count($tables) . ") ===\n";
                    foreach (array_chunk($tables, 4) as $chunk) {
                        echo "    " . implode(", ", $chunk) . "\n";
                    }
                    
                    // Check for WordPress tables
                    $wp_tables = array_filter($tables, function($table) {
                        return preg_match('/(wp_|posts$|users$|usermeta$|options$)/i', $table);
                    });
                    
                    echo "    \n    === WordPress Tables (" . count($wp_tables) . ") ===\n";
                    foreach ($wp_tables as $table) {
                        echo "    - $table\n";
                    }
                    
                    $connected = true;
                } else {
                    echo "    ✗ Could not select database: $wp_db\n";
                }
            } else {
                echo "    ℹ No WordPress database found.\n";
            }
            
            $mysqli->close();
            
        } catch (Exception $e) {
            echo "    Error: " . $e->getMessage() . "\n";
        }
    }
}

if (!$connected) {
    echo "\n❌ Could not connect to any MySQL server with the tested credentials.\n";
    echo "Please check your MySQL server is running and update the credentials in this script.\n";
}

echo "\n=== Test Complete ===\n";
