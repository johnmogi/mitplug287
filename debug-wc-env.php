<?php
/**
 * Debug WooCommerce environment and products
 */

define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    die('WooCommerce is not active');
}

echo "=== WooCommerce Environment ===\n";
echo "WC Version: " . WC_VERSION . "\n";
echo "WC Database Version: " . get_option('woocommerce_db_version') . "\n\n";

// Check if products table exists
global $wpdb;
$products_table = $wpdb->prefix . 'wc_products';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$products_table'");
echo "Products table exists: " . ($table_exists ? 'Yes' : 'No') . "\n";

// List all product types
$product_types = wc_get_product_types();
echo "\n=== Product Types ===\n";
print_r($product_types);

// Try to get all products
$args = array(
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
);

$query = new WP_Query($args);
$total_products = $query->found_posts;

echo "\n=== Products Summary ===\n";
echo "Total Products: $total_products\n";

// If we have products, show some details
if ($total_products > 0) {
    echo "\nFirst 5 products (ID - Name - Status):\n";
    $count = 0;
    
    foreach ($query->posts as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            echo $product_id . ' - ' . $product->get_name() . ' - ' . $product->get_status() . "\n";
            $count++;
            if ($count >= 5) break;
        }
    }
    
    // Search for our specific product
    $search_term = 'מגה סלייד דקלים';
    echo "\nSearching for product: $search_term\n";
    
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        's'              => $search_term,
        'post_status'    => 'any',
    );
    
    $search_query = new WP_Query($args);
    
    if ($search_query->have_posts()) {
        echo "Found " . $search_query->found_posts . " matching products\n";
        while ($search_query->have_posts()) {
            $search_query->the_post();
            $product = wc_get_product(get_the_ID());
            echo "ID: " . $product->get_id() . " - " . $product->get_name() . " (Status: " . $product->get_status() . ")\n";
        }
    } else {
        echo "No products found matching: $search_term\n";
    }
    
    wp_reset_postdata();
} else {
    echo "\nNo products found in the database.\n";
}

echo "\nDebug complete.\n";
