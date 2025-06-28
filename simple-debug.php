<?php
/**
 * Simple debug script to check PHP and basic database connectivity
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to safely get environment variable
function get_env($key, $default = 'Not Set') {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

echo "=== PHP Environment ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "PHP SAPI: " . PHP_SAPI . "\n";
echo "PHP Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";
echo "Error Reporting: " . ini_get('error_reporting') . "\n";
echo "Display Errors: " . ini_get('display_errors') . "\n";
echo "Document Root: " . get_env('DOCUMENT_ROOT') . "\n";
echo "Script Filename: " . get_env('SCRIPT_FILENAME') . "\n";

// Check if we can connect to MySQL
if (extension_loaded('mysqli')) {
    echo "\n=== MySQLi Check ===\n";
    
    // Try to get DB credentials from wp-config.php
    $wp_config_path = dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-config.php';
    
    if (file_exists($wp_config_path)) {
        echo "Found wp-config.php at: $wp_config_path\n";
        
        // Extract DB credentials using regex
        $wp_config = file_get_contents($wp_config_path);
        
        $db_name = '';
        $db_user = '';
        $db_password = '';
        $db_host = '';
        
        if (preg_match("/define\s*\(\s*['"]DB_NAME['"]\s*,\s*['"]([^'"]+)['"]/i", $wp_config, $matches)) {
            $db_name = $matches[1];
        }
        if (preg_match("/define\s*\(\s*['"]DB_USER['"]\s*,\s*['"]([^'"]+)['"]/i", $wp_config, $matches)) {
            $db_user = $matches[1];
        }
        if (preg_match("/define\s*\(\s*['"]DB_PASSWORD['"]\s*,\s*['"]([^'"]*)['"]/i", $wp_config, $matches)) {
            $db_password = $matches[1];
        }
        if (preg_match("/define\s*\(\s*['"]DB_HOST['"]\s*,\s*['"]([^'"]+)['"]/i", $wp_config, $matches)) {
            $db_host = $matches[1];
        }
        
        echo "DB Name: $db_name\n";
        echo "DB User: $db_user\n";
        echo "DB Host: $db_host\n";
        
        // Try to connect to the database
        if ($db_name && $db_user && $db_host) {
            echo "\nAttempting to connect to database...\n";
            
            $mysqli = @new mysqli($db_host, $db_user, $db_password, $db_name);
            
            if ($mysqli->connect_error) {
                echo "Database connection failed: " . $mysqli->connect_error . "\n";
            } else {
                echo "Database connection successful!\n";
                
                // List tables
                $result = $mysqli->query("SHOW TABLES");
                if ($result) {
                    $tables = [];
                    while ($row = $result->fetch_row()) {
                        $tables[] = $row[0];
                    }
                    echo "\nFound " . count($tables) . " tables in database.\n";
                    
                    // Count posts
                    $wp_posts = $mysqli->query("SELECT COUNT(*) FROM {$db_name}.wp_posts");
                    if ($wp_posts) {
                        $post_count = $wp_posts->fetch_row()[0];
                        echo "Total posts: $post_count\n";
                    }
                    
                    // Check WooCommerce tables
                    $woo_tables = array_filter($tables, function($table) {
                        return strpos($table, 'woocommerce') !== false;
                    });
                    
                    echo "\nWooCommerce tables (" . count($woo_tables) . "):\n";
                    foreach ($woo_tables as $table) {
                        echo "- $table\n";
                    }
                } else {
                    echo "Could not list tables: " . $mysqli->error . "\n";
                }
                
                $mysqli->close();
            }
        }
    } else {
        echo "wp-config.php not found at: $wp_config_path\n";
    }
} else {
    echo "\nMySQLi extension not loaded.\n";
}

echo "\n=== File System Check ===\n";
$wp_load_path = dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';
echo "wp-load.php exists: " . (file_exists($wp_load_path) ? 'Yes' : 'No') . "\n";

$wp_admin_path = dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-admin';
echo "wp-admin directory exists: " . (is_dir($wp_admin_path) ? 'Yes' : 'No') . "\n";

$wp_includes_path = dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-includes';
echo "wp-includes directory exists: " . (is_dir($wp_includes_path) ? 'Yes' : 'No') . "\n";

echo "\n=== Debug Complete ===\n";
