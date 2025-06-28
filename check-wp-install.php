<?php
// Script to check WordPress installation and database connection

// Path to wp-load.php
$wp_load_path = __DIR__ . '/../../../wp-load.php';

if (!file_exists($wp_load_path)) {
    die("Error: wp-load.php not found at $wp_load_path\n");
}

// Load WordPress
require_once($wp_load_path);

// Check if WordPress loaded successfully
if (!function_exists('get_bloginfo')) {
    die("Error: WordPress did not load correctly.\n");
}

echo "=== WordPress Installation Check ===\n";
echo "Site URL: " . site_url() . "\n";
echo "Home URL: " . home_url() . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "Active Theme: " . wp_get_theme()->get('Name') . " " . wp_get_theme()->get('Version') . "\n";

// Check database connection
global $wpdb;
if (isset($wpdb)) {
    echo "\n=== Database Connection ===\n";
    echo "Database Name: " . DB_NAME . "\n";
    echo "Database User: " . DB_USER . "\n";
    echo "Database Host: " . DB_HOST . "\n";
    
    // Test database connection
    $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
    if ($tables) {
        echo "\n=== Database Tables (" . count($tables) . ") ===\n";
        foreach (array_chunk($tables, 4) as $chunk) {
            echo implode(", ", array_map(function($t) { return $t[0]; }, $chunk)) . "\n";
        }
    } else {
        echo "\nError: Could not list database tables. " . $wpdb->last_error . "\n";
    }
}

// Check WooCommerce
if (class_exists('WooCommerce')) {
    echo "\n=== WooCommerce ===\n";
    echo "WooCommerce Version: " . WC_VERSION . "\n";
    
    // Count products
    $product_count = wp_count_posts('product');
    echo "Total Products: " . $product_count->publish . "\n";
    
    // Search for our product
    $product = get_page_by_title('מגה סלייד דקלים', OBJECT, 'product');
    if ($product) {
        echo "\n=== Product Found ===\n";
        echo "ID: " . $product->ID . "\n";
        echo "Title: " . $product->post_title . "\n";
        echo "Status: " . $product->post_status . "\n";
        
        // Get product details
        $product_obj = wc_get_product($product->ID);
        if ($product_obj) {
            echo "Price: " . $product_obj->get_price() . "\n";
            echo "Stock Status: " . $product_obj->get_stock_status() . "\n";
            echo "Stock Quantity: " . $product_obj->get_stock_quantity() . "\n";
        }
    } else {
        echo "\nProduct 'מגה סלייד דקלים' not found.\n";
    }
} else {
    echo "\nWooCommerce is not active.\n";
}

echo "\n=== Active Plugins ===\n";
$plugins = get_option('active_plugins');
foreach ($plugins as $plugin) {
    echo "- $plugin\n";
}

echo "\n=== Check Complete ===\n";
