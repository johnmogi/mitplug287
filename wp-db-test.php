<?php
// Simple script to test WordPress database connection and list tables

// Path to wp-config.php
$wp_config_path = __DIR__ . '/../../../wp-config.php';

if (!file_exists($wp_config_path)) {
    die("Error: wp-config.php not found at $wp_config_path\n");
}

// Parse wp-config.php to get database credentials
$wp_config = file_get_contents($wp_config_path);

// Extract database credentials
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

// Get table prefix
$table_prefix = 'wp_';
if (preg_match("/\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/i", $wp_config, $matches)) {
    $table_prefix = $matches[1];
}

// Output configuration
echo "=== WordPress Database Test ===\n";
echo "DB_NAME: " . ($db_name ?: 'Not found') . "\n";
echo "DB_USER: " . ($db_user ?: 'Not found') . "\n";
echo "DB_PASSWORD: " . ($db_password ? '*** (set)' : 'Not found') . "\n";
echo "DB_HOST: " . ($db_host ?: 'Not found') . "\n";
echo "Table Prefix: " . $table_prefix . "\n\n";

// Test database connection
if ($db_name && $db_user && $db_host) {
    echo "=== Testing Database Connection ===\n";
    
    try {
        // Connect to MySQL server
        $mysqli = new mysqli($db_host, $db_user, $db_password);
        
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        echo "✅ Connected to MySQL server.\n";
        echo "MySQL Server Version: " . $mysqli->server_info . "\n\n";
        
        // List all databases
        echo "=== Available Databases ===\n";
        $result = $mysqli->query("SHOW DATABASES");
        $databases = [];
        while ($row = $result->fetch_row()) {
            $databases[] = $row[0];
        }
        
        foreach ($databases as $db) {
            echo "- $db\n";
        }
        
        // Check if the WordPress database exists
        if (in_array($db_name, $databases)) {
            echo "\n✅ Database '$db_name' exists.\n";
            
            // Select the database
            if ($mysqli->select_db($db_name)) {
                echo "✅ Selected database '$db_name'.\n";
                
                // List all tables
                $result = $mysqli->query("SHOW TABLES");
                $tables = [];
                while ($row = $result->fetch_row()) {
                    $tables[] = $row[0];
                }
                
                echo "\n=== Tables in '$db_name' (" . count($tables) . ") ===\n";
                foreach ($tables as $table) {
                    echo "- $table\n";
                }
                
                // Check for WordPress tables
                $wp_tables = array_filter($tables, function($table) use ($table_prefix) {
                    return strpos($table, $table_prefix) === 0;
                });
                
                echo "\n=== WordPress Tables (" . count($wp_tables) . ") ===\n";
                if (count($wp_tables) > 0) {
                    foreach ($wp_tables as $table) {
                        echo "- $table\n";
                    }
                } else {
                    echo "No WordPress tables found with prefix: $table_prefix\n";
                }
                
                // Check for products
                if (in_array($table_prefix . 'posts', $tables)) {
                    echo "\n=== Checking for Products ===\n";
                    
                    // Count products
                    $result = $mysqli->query("SELECT COUNT(*) as count FROM {$table_prefix}posts WHERE post_type = 'product'");
                    if ($result) {
                        $row = $result->fetch_assoc();
                        echo "Total Products: " . $row['count'] . "\n";
                    }
                    
                    // Search for our product
                    $search_term = 'מגה סלייד דקלים';
                    $query = $mysqli->prepare("SELECT ID, post_title, post_status FROM {$table_prefix}posts 
                                             WHERE post_type = 'product' AND post_title LIKE ?");
                    $search_param = "%$search_term%";
                    $query->bind_param('s', $search_param);
                    
                    if ($query->execute()) {
                        $result = $query->get_result();
                        
                        if ($result->num_rows > 0) {
                            echo "\n=== Found Products ===\n";
                            while ($row = $result->fetch_assoc()) {
                                echo "- ID: {$row['ID']}, Title: {$row['post_title']}, Status: {$row['post_status']}\n";
                            }
                        } else {
                            echo "\nNo products found matching: $search_term\n";
                        }
                    } else {
                        echo "\nError searching for products: " . $mysqli->error . "\n";
                    }
                }
            } else {
                echo "❌ Could not select database '$db_name': " . $mysqli->error . "\n";
            }
        } else {
            echo "\n❌ Database '$db_name' does not exist.\n";
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Incomplete database configuration. Cannot test connection.\n";
}

echo "\n=== Test Complete ===\n";
