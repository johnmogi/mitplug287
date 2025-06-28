<?php
// Simple script to extract and display database credentials from wp-config.php

// Path to wp-config.php
$wp_config_path = __DIR__ . '/../../../wp-config.php';

if (!file_exists($wp_config_path)) {
    die("Error: wp-config.php not found at $wp_config_path\n");
}

// Read wp-config.php
$wp_config = file_get_contents($wp_config_path);

// Function to extract define values
function get_define_value($config, $constant) {
    if (preg_match("/define\s*\(\s*['\"]" . preg_quote($constant, '/') . "['\"]\s*,\s*['\"]([^'\"]*)['\"]/i", $config, $matches)) {
        return $matches[1];
    }
    return null;
}

// Get database credentials
$db_name = get_define_value($wp_config, 'DB_NAME');
$db_user = get_define_value($wp_config, 'DB_USER');
$db_password = get_define_value($wp_config, 'DB_PASSWORD');
$db_host = get_define_value($wp_config, 'DB_HOST');

// Output credentials
echo "=== Database Credentials from wp-config.php ===\n";
echo "DB_NAME: " . ($db_name ?: 'Not found') . "\n";
echo "DB_USER: " . ($db_user ?: 'Not found') . "\n";
echo "DB_PASSWORD: " . ($db_password ? '*** (set)' : 'Not found') . "\n";
echo "DB_HOST: " . ($db_host ?: 'Not found') . "\n";

// Test connection if we have credentials
if ($db_name && $db_user && $db_host) {
    echo "\n=== Testing Database Connection ===\n";
    
    try {
        $mysqli = @new mysqli($db_host, $db_user, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        echo "Successfully connected to the database.\n";
        
        // Get database info
        echo "Server version: " . $mysqli->server_info . "\n";
        echo "Host info: " . $mysqli->host_info . "\n";
        
        // List tables
        $result = $mysqli->query("SHOW TABLES");
        if ($result) {
            $tables = [];
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
            echo "\nFound " . count($tables) . " tables in the database.\n";
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\nIncomplete database configuration. Cannot test connection.\n";
}

echo "\n=== Script Complete ===\n";
