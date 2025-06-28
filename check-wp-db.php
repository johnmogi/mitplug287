<?php
// Script to check WordPress database configuration and test connection

// Path to wp-config.php
$wp_config_path = __DIR__ . '/../../../wp-config.php';

if (!file_exists($wp_config_path)) {
    die("Error: wp-config.php not found at $wp_config_path\n");
}

// Read wp-config.php
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
echo "=== WordPress Database Configuration ===\n";
echo "DB_NAME: " . ($db_name ?: 'Not found') . "\n";
echo "DB_USER: " . ($db_user ?: 'Not found') . "\n";
echo "DB_PASSWORD: " . ($db_password ? '*** (set)' : 'Not found') . "\n";
echo "DB_HOST: " . ($db_host ?: 'Not found') . "\n";
echo "Table Prefix: " . $table_prefix . "\n\n";

// Test database connection
if ($db_name && $db_user && $db_host) {
    echo "=== Testing Database Connection ===\n";
    
    try {
        // Try to connect without database first
        $mysqli = @new mysqli($db_host, $db_user, $db_password);
        
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        echo "✅ Connected to MySQL server.\n";
        echo "MySQL Server Version: " . $mysqli->server_info . "\n";
        
        // Check if database exists
        $result = $mysqli->query("SHOW DATABASES LIKE '$db_name'");
        if ($result->num_rows === 0) {
            throw new Exception("Database '$db_name' does not exist.");
        }
        
        echo "✅ Database '$db_name' exists.\n";
        
        // Select the database
        if (!$mysqli->select_db($db_name)) {
            throw new Exception("Could not select database: " . $mysqli->error);
        }
        
        echo "✅ Selected database: $db_name\n";
        
        // List tables
        $result = $mysqli->query("SHOW TABLES");
        $tables = [];
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        echo "\n=== Database Tables (" . count($tables) . ") ===\n";
        
        if (count($tables) === 0) {
            echo "No tables found in the database.\n";
        } else {
            // Show first 10 tables
            foreach (array_slice($tables, 0, 10) as $table) {
                echo "- $table\n";
            }
            if (count($tables) > 10) {
                echo "... and " . (count($tables) - 10) . " more\n";
            }
        }
        
        // Check for WordPress tables
        $wp_tables = array_filter($tables, function($table) use ($table_prefix) {
            return strpos($table, $table_prefix) === 0;
        });
        
        echo "\n=== WordPress Tables (" . count($wp_tables) . ") ===\n";
        
        if (count($wp_tables) === 0) {
            echo "No WordPress tables found with prefix: $table_prefix\n";
        } else {
            foreach ($wp_tables as $table) {
                echo "- $table\n";
            }
        }
        
        // Check for posts table
        $posts_table = $table_prefix . 'posts';
        if (in_array($posts_table, $tables)) {
            // Count products
            $result = $mysqli->query("SELECT COUNT(*) as count FROM $posts_table WHERE post_type = 'product'");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "\n=== Products ===\n";
                echo "Total Products: " . $row['count'] . "\n";
                
                // Search for our product
                $search_term = 'מגה סלייד דקלים';
                $query = $mysqli->prepare("SELECT ID, post_title, post_status FROM $posts_table 
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
            echo "\n❌ WordPress posts table '$posts_table' not found.\n";
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Incomplete database configuration. Cannot test connection.\n";
}

echo "\n=== Check Complete ===\n";
