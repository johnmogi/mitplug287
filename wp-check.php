<?php
// Script to check WordPress installation and database configuration

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
echo "=== WordPress Installation Check ===\n\n";
echo "WordPress Path: " . dirname(dirname(dirname(dirname(__DIR__)))) . "\n";
echo "wp-config.php Path: $wp_config_path\n";

echo "\n=== Database Configuration ===\n";
echo "DB_NAME: " . ($db_name ?: 'Not found') . "\n";
echo "DB_USER: " . ($db_user ?: 'Not found') . "\n";
echo "DB_PASSWORD: " . ($db_password ? '*** (set)' : 'Not found') . "\n";
echo "DB_HOST: " . ($db_host ?: 'Not found') . "\n";
echo "Table Prefix: " . $table_prefix . "\n";

// Test database connection
if ($db_name && $db_user && $db_host) {
    echo "\n=== Testing Database Connection ===\n";
    
    try {
        // Try to connect to MySQL server first
        $mysqli = @new mysqli($db_host, $db_user, $db_password);
        
        if ($mysqli->connect_error) {
            throw new Exception("Connection to MySQL server failed: " . $mysqli->connect_error);
        }
        
        echo "✅ Connected to MySQL server.\n";
        echo "MySQL Server Version: " . $mysqli->server_info . "\n";
        
        // Check if database exists
        $result = $mysqli->query("SHOW DATABASES LIKE '$db_name'");
        if ($result->num_rows === 0) {
            throw new Exception("Database '$db_name' does not exist on the server.");
        }
        
        echo "✅ Database '$db_name' exists.\n";
        
        // Select the database
        if (!$mysqli->select_db($db_name)) {
            throw new Exception("Could not select database '$db_name': " . $mysqli->error);
        }
        
        echo "✅ Selected database: $db_name\n";
        
        // List all tables
        $result = $mysqli->query("SHOW TABLES");
        $tables = [];
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        echo "\n=== Database Tables (" . count($tables) . ") ===\n";
        
        if (count($tables) === 0) {
            echo "No tables found in the database. This appears to be a new or empty database.\n";
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
        
        echo "\n=== WordPress Tables (" . count($wp_tables) . " with prefix '$table_prefix') ===\n";
        
        if (count($wp_tables) === 0) {
            echo "No WordPress tables found with prefix: $table_prefix\n";
            echo "This could mean:\n";
            echo "1. WordPress has not been installed yet\n";
            echo "2. The table prefix in wp-config.php is incorrect\n";
            echo "3. The database was dropped or corrupted\n";
        } else {
            foreach ($wp_tables as $table) {
                echo "- $table\n";
            }
        }
        
        // Check for essential WordPress tables
        $essential_tables = [
            $table_prefix . 'options',
            $table_prefix . 'posts',
            $table_prefix . 'users',
            $table_prefix . 'usermeta',
            $table_prefix . 'postmeta',
            $table_prefix . 'terms',
            $table_prefix . 'term_taxonomy',
            $table_prefix . 'term_relationships',
            $table_prefix . 'commentmeta',
            $table_prefix . 'comments',
            $table_prefix . 'links',
        ];
        
        $missing_tables = [];
        foreach ($essential_tables as $table) {
            if (!in_array($table, $tables)) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            echo "\n❌ Missing essential WordPress tables:\n";
            foreach ($missing_tables as $table) {
                echo "- $table\n";
            }
            
            if (count($missing_tables) === count($essential_tables)) {
                echo "\nThis appears to be a fresh or empty database. You may need to install WordPress.\n";
            } else {
                echo "\nSome WordPress tables are missing. The database may be corrupted.\n";
            }
        } else {
            echo "\n✅ All essential WordPress tables are present.\n";
            
            // Check if WordPress is installed
            $result = $mysqli->query("SELECT option_value FROM {$table_prefix}options WHERE option_name = 'siteurl'");
            if ($result && $result->num_rows > 0) {
                $site_url = $result->fetch_row()[0];
                echo "\nWordPress is installed.\n";
                echo "Site URL: $site_url\n";
                
                // Check for WooCommerce
                $result = $mysqli->query("SELECT option_value FROM {$table_prefix}options WHERE option_name = 'active_plugins'");
                if ($result && $result->num_rows > 0) {
                    $active_plugins = unserialize($result->fetch_row()[0]);
                    $woo_active = false;
                    
                    foreach ($active_plugins as $plugin) {
                        if (strpos($plugin, 'woocommerce') !== false) {
                            $woo_active = true;
                            break;
                        }
                    }
                    
                    if ($woo_active) {
                        echo "✅ WooCommerce is active.\n";
                        
                        // Count products
                        $result = $mysqli->query("SELECT COUNT(*) as count FROM {$table_prefix}posts WHERE post_type = 'product' AND post_status = 'publish'");
                        if ($result) {
                            $row = $result->fetch_assoc();
                            echo "Total Published Products: " . $row['count'] . "\n";
                            
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
                        echo "❌ WooCommerce is not active.\n";
                    }
                }
            } else {
                echo "\nWordPress may not be properly installed. The siteurl option is missing.\n";
            }
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n❌ Incomplete database configuration. Cannot test connection.\n";
}

echo "\n=== Check Complete ===\n";
