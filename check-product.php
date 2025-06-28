<?php
// Script to check for a specific product in the WordPress database

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

echo "=== Product Search ===\n";
echo "Database: " . DB_NAME . "\n";
echo "User: " . DB_USER . "\n";
echo "Host: " . DB_HOST . "\n\n";

// Get table prefix
global $table_prefix;
if (!isset($table_prefix)) {
    $table_prefix = 'wp_';
}

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Database connection failed: " . $mysqli->connect_error);
    }
    
    echo "Connected to database successfully.\n";
    
    // Search for product by name
    $product_name = 'מגה סלייד דקלים';
    $query = $mysqli->prepare("SELECT ID, post_title, post_status, post_type FROM {$table_prefix}posts 
                             WHERE post_type = 'product' AND post_title LIKE ?");
    $search_term = "%$product_name%";
    $query->bind_param('s', $search_term);
    
    echo "\nSearching for product: $product_name\n";
    
    if ($query->execute()) {
        $result = $query->get_result();
        
        if ($result->num_rows > 0) {
            echo "\n=== Found Products ===\n";
            while ($row = $result->fetch_assoc()) {
                echo "ID: {$row['ID']}\n";
                echo "Title: {$row['post_title']}\n";
                echo "Status: {$row['post_status']}\n";
                echo "Type: {$row['post_type']}\n";
                
                // Get product meta
                $meta_query = $mysqli->prepare("SELECT meta_key, meta_value FROM {$table_prefix}postmeta 
                                              WHERE post_id = ? AND meta_key IN ('_stock_status', '_stock', '_price')");
                $meta_query->bind_param('i', $row['ID']);
                
                if ($meta_query->execute()) {
                    $meta_result = $meta_query->get_result();
                    
                    if ($meta_result->num_rows > 0) {
                        echo "\nProduct Meta:\n";
                        while ($meta = $meta_result->fetch_assoc()) {
                            echo "- {$meta['meta_key']}: {$meta['meta_value']}\n";
                        }
                    }
                    
                    $meta_result->close();
                }
                
                echo "\n" . str_repeat("-", 40) . "\n";
            }
        } else {
            echo "No products found matching: $product_name\n";
            
            // Try a broader search
            echo "\nTrying broader search...\n";
            $query = $mysqli->query("SELECT ID, post_title, post_status, post_type 
                                   FROM {$table_prefix}posts 
                                   WHERE post_type = 'product' LIMIT 5");
            
            if ($query && $query->num_rows > 0) {
                echo "\n=== Sample Products ===\n";
                while ($row = $query->fetch_assoc()) {
                    echo "- ID: {$row['ID']}, Title: {$row['post_title']}, Status: {$row['post_status']}\n";
                }
            } else {
                echo "No products found in the database.\n";
            }
        }
    } else {
        throw new Exception("Query failed: " . $mysqli->error);
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Script Complete ===\n";
