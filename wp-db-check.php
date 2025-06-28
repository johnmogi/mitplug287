<?php
// Simple script to check WordPress database connection

echo "=== WordPress Database Check ===\n";

// Path to wp-config.php
$wp_config_path = __DIR__ . '/../../../wp-config.php';

if (!file_exists($wp_config_path)) {
    die("Error: wp-config.php not found at $wp_config_path\n");
}

// Include wp-config.php to get database credentials
require_once($wp_config_path);

// Check if database constants are defined
if (!defined('DB_NAME') || !defined('DB_USER') || !defined('DB_HOST')) {
    die("Error: Database credentials not found in wp-config.php\n");
}

echo "Database: " . DB_NAME . "\n";
echo "User: " . DB_USER . "\n";
echo "Host: " . DB_HOST . "\n";

// Get table prefix
global $table_prefix;
if (!isset($table_prefix)) {
    $table_prefix = 'wp_';
}
echo "Table Prefix: $table_prefix\n\n";

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Database connection failed: " . $mysqli->connect_error);
    }
    
    echo "✅ Successfully connected to the database.\n";
    echo "MySQL Server Version: " . $mysqli->server_info . "\n\n";
    
    // Check WordPress tables
    $tables = [
        $table_prefix . 'options',
        $table_prefix . 'posts',
        $table_prefix . 'postmeta',
        $table_prefix . 'users',
        $table_prefix . 'usermeta',
        $table_prefix . 'terms',
        $table_prefix . 'term_taxonomy',
        $table_prefix . 'term_relationships'
    ];
    
    echo "=== Checking WordPress Tables ===\n";
    $missing_tables = [];
    
    foreach ($tables as $table) {
        $result = $mysqli->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $missing_tables[] = $table;
            echo "❌ Missing: $table\n";
        } else {
            echo "✅ Found: $table\n";
        }
    }
    
    if (!empty($missing_tables)) {
        echo "\n❌ Missing " . count($missing_tables) . " essential WordPress tables.\n";
    } else {
        echo "\n✅ All essential WordPress tables are present.\n";
    }
    
    // Check for WooCommerce tables
    $woo_tables = [
        $table_prefix . 'woocommerce_sessions',
        $table_prefix . 'woocommerce_order_items',
        $table_prefix . 'woocommerce_order_itemmeta'
    ];
    
    echo "\n=== Checking WooCommerce Tables ===\n";
    $missing_woo_tables = [];
    
    foreach ($woo_tables as $table) {
        $result = $mysqli->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $missing_woo_tables[] = $table;
            echo "❌ Missing: $table\n";
        } else {
            echo "✅ Found: $table\n";
        }
    }
    
    if (!empty($missing_woo_tables)) {
        echo "\nℹ Missing " . count($missing_woo_tables) . " WooCommerce tables.\n";
    } else {
        echo "\n✅ All WooCommerce tables are present.\n";
    }
    
    // Count products
    $products = $mysqli->query("SELECT COUNT(*) as count FROM {$table_prefix}posts WHERE post_type = 'product' AND post_status = 'publish'");
    if ($products) {
        $product_count = $products->fetch_assoc()['count'];
        echo "\n=== Products ===\n";
        echo "Total Published Products: $product_count\n";
        
        // Search for our product
        $search_term = 'מגה סלייד דקלים';
        $product_query = $mysqli->prepare("SELECT ID, post_title, post_status FROM {$table_prefix}posts 
                                         WHERE post_type = 'product' AND post_title LIKE ?");
        $search_param = "%$search_term%";
        $product_query->bind_param('s', $search_param);
        
        echo "\nSearching for product: $search_term\n";
        
        if ($product_query->execute()) {
            $result = $product_query->get_result();
            
            if ($result->num_rows > 0) {
                echo "\n=== Found Products ===\n";
                while ($row = $result->fetch_assoc()) {
                    echo "ID: {$row['ID']}, Title: {$row['post_title']}, Status: {$row['post_status']}\n";
                }
            } else {
                echo "No products found matching: $search_term\n";
            }
        } else {
            echo "Error searching for products: " . $mysqli->error . "\n";
        }
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
