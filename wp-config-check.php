<?php
// Simple script to check wp-config.php and test database connection

// Path to wp-config.php
$wp_config_path = __DIR__ . '/../../../wp-config.php';

if (!file_exists($wp_config_path)) {
    die("Error: wp-config.php not found at $wp_config_path\n");
}

// Read wp-config.php
$wp_config = file_get_contents($wp_config_path);

// Extract database credentials
function get_define_value($config, $constant) {
    if (preg_match("/define\s*\(\s*['"]" . preg_quote($constant, '/') . "['"]\s*,\s*['"]([^'"]*)['"]/i", $config, $matches)) {
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
echo "=== Database Configuration ===\n";
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
        
        echo "✅ Successfully connected to the database.\n";
        echo "Server version: " . $mysqli->server_info . "\n";
        
        // List tables
        $result = $mysqli->query("SHOW TABLES");
        if ($result) {
            $tables = [];
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
            echo "\nFound " . count($tables) . " tables in the database.\n";
            
            // Show first few tables
            if (count($tables) > 0) {
                echo "Sample tables:\n";
                foreach (array_slice($tables, 0, 5) as $table) {
                    echo "- $table\n";
                }
                if (count($tables) > 5) {
                    echo "... and " . (count($tables) - 5) . " more\n";
                }
            }
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n❌ Incomplete database configuration. Cannot test connection.\n";
}

echo "\n=== Script Complete ===\n";
