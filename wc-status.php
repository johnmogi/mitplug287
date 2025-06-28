<?php
/**
 * Display WooCommerce system status
 */

define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    die('WooCommerce is not active');
}

// Check if we're in admin context
if (!function_exists('wc_get_system_status_report')) {
    require_once(WP_PLUGIN_DIR . '/woocommerce/includes/admin/class-wc-admin-status.php');
    require_once(WP_PLUGIN_DIR . '/woocommerce/includes/admin/reports/class-wc-admin-report.php');
}

// Get system status
$system_status = new WC_Admin_Status();
$report = $system_status->get_system_status_report();

// Output basic info
echo "=== WooCommerce System Status ===\n";
echo "WC Version: " . WC_VERSION . "\n";
echo "WP Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . phpversion() . "\n";

// Output database info
global $wpdb;
echo "\n=== Database ===\n";
echo "WC Database Version: " . get_option('woocommerce_db_version') . "\n";
$tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%woocommerce%'");
echo "WooCommerce Tables: " . count($tables) . " found\n";

// Check if products table exists
$products_table = $wpdb->prefix . 'wc_products';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$products_table'");
echo "Products table exists: " . ($table_exists ? 'Yes' : 'No') . "\n";

// Count products
try {
    $product_count = wp_count_posts('product');
    echo "\n=== Product Counts ===\n";
    echo "Published: " . $product_count->publish . "\n";
    echo "Draft: " . $product_count->draft . "\n";
    echo "Private: " . $product_count->private . "\n";
    echo "Pending: " . $product_count->pending . "\n";
} catch (Exception $e) {
    echo "\nError getting product counts: " . $e->getMessage() . "\n";
}

// Check for our specific product
try {
    echo "\n=== Searching for Product ===\n";
    $search_term = 'מגה סלייד דקלים';
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => 1,
        's'              => $search_term,
        'post_status'    => 'any',
    );
    
    $query = new WP_Query($args);
    echo "Found " . $query->found_posts . " products matching: $search_term\n";
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            echo "\nProduct Found:\n";
            echo "ID: " . $product->get_id() . "\n";
            echo "Name: " . $product->get_name() . "\n";
            echo "Status: " . $product->get_status() . "\n";
            echo "Type: " . $product->get_type() . "\n";
            echo "SKU: " . $product->get_sku() . "\n";
        }
        wp_reset_postdata();
    } else {
        echo "No products found matching: $search_term\n";
    }
} catch (Exception $e) {
    echo "Error searching for product: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
