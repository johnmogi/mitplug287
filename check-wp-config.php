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
$table_prefix = 'wp_';

if (preg_match("/\$table_prefix\s*=\s*['"]([^'"]+)['"]/i", $wp_config, $matches)) {
    $table_prefix = $matches[1];
}

// Output configuration
echo "=== WordPress Database Configuration ===\n";
echo "DB_NAME: $db_name\n";
echo "DB_USER: $db_user\n";
echo "DB_PASSWORD: " . ($db_password ? '*** (set)' : 'not set') . "\n";
echo "DB_HOST: $db_host\n";
echo "Table Prefix: $table_prefix\n\n";

// Test database connection
if ($db_name && $db_user && $db_host) {
    echo "Attempting to connect to database...\n";
    
    try {
        $mysqli = new mysqli($db_host, $db_user, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        echo "Successfully connected to the database.\n";
        
        // Check WordPress tables
        $tables = [
            $table_prefix . 'options',
            $table_prefix . 'posts',
            $table_prefix . 'postmeta',
            $table_prefix . 'users',
            $table_prefix . 'usermeta'
        ];
        
        $missing_tables = [];
        foreach ($tables as $table) {
            $result = $mysqli->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            echo "\n=== Missing WordPress Tables ===\n";
            foreach ($missing_tables as $table) {
                echo "- $table\n";
            }
        } else {
            echo "\nAll essential WordPress tables exist.\n";
        }
        
        // Check site URL and home URL
        $site_url = $mysqli->query("SELECT option_value FROM {$table_prefix}options WHERE option_name = 'siteurl'");
        $home_url = $mysqli->query("SELECT option_value FROM {$table_prefix}options WHERE option_name = 'home'");
        
        echo "\n=== Site URLs ===\n";
        if ($site_url && $site_url->num_rows > 0) {
            $site_url = $site_url->fetch_row()[0];
            echo "Site URL: $site_url\n";
        } else {
            echo "Site URL: Not found in database\n";
        }
        
        if ($home_url && $home_url->num_rows > 0) {
            $home_url = $home_url->fetch_row()[0];
            echo "Home URL: $home_url\n";
        } else {
            echo "Home URL: Not found in database\n";
        }
        
        // Check WooCommerce tables
        $woo_tables = $mysqli->query("SHOW TABLES LIKE '{$table_prefix}woocommerce%'");
        $woo_table_count = $woo_tables ? $woo_tables->num_rows : 0;
        echo "\nWooCommerce Tables Found: $woo_table_count\n";
        
        // Check for our product
        $search_term = 'מגה סלייד דקלים';
        $product_query = $mysqli->prepare("SELECT ID, post_title, post_status FROM {$table_prefix}posts WHERE post_type = 'product' AND post_title LIKE ?");
        $search_param = "%$search_term%";
        $product_query->bind_param('s', $search_param);
        
        echo "\n=== Searching for Product: $search_term ===\n";
        
        if ($product_query->execute()) {
            $result = $product_query->get_result();
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "Found Product: ID={$row['ID']}, Title={$row['post_title']}, Status={$row['post_status']}\n";
                }
            } else {
                echo "No products found matching: $search_term\n";
            }
        } else {
            echo "Error searching for products: " . $mysqli->error . "\n";
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Incomplete database configuration. Cannot connect.\n";
}

echo "\n=== Script Complete ===\n";
