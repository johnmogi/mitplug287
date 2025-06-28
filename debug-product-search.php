<?php
/**
 * Debug script to search for WooCommerce products
 */

define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

if (!class_exists('WooCommerce')) {
    die('WooCommerce is not active');
}

// Search for products by name
$args = array(
    'post_type'      => 'product',
    'posts_per_page' => -1,
    's'              => 'מגה סלייד דקלים',
    'post_status'    => 'any',
);

$query = new WP_Query($args);

echo "Found {$query->found_posts} products matching 'מגה סלייד דקלים':\n\n";

if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        $product = wc_get_product(get_the_ID());
        
        if (!$product) continue;
        
        echo "ID: " . $product->get_id() . "\n";
        echo "Name: " . $product->get_name() . "\n";
        echo "Status: " . $product->get_status() . "\n";
        echo "Type: " . $product->get_type() . "\n";
        echo "SKU: " . $product->get_sku() . "\n";
        echo "Price: " . $product->get_price() . "\n";
        echo "Stock status: " . $product->get_stock_status() . "\n";
        echo "Stock quantity: " . $product->get_stock_quantity() . "\n";
        echo "Manage stock: " . ($product->get_manage_stock() ? 'Yes' : 'No') . "\n\n";
    }
    wp_reset_postdata();
} else {
    echo "No products found. Trying broader search...\n\n";
    
    // Try a broader search
    $args['s'] = 'דקלים'; // Just search for part of the name
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        echo "Found {$query->found_posts} products matching 'דקלים':\n\n";
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            echo "ID: " . $product->get_id() . " - " . $product->get_name() . " (Status: " . $product->get_status() . ")\n";
        }
        wp_reset_postdata();
    } else {
        echo "No products found with partial name match.\n";
    }
}
