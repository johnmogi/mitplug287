<?php
/**
 * Check WordPress environment and available post types
 */

// Define path to wp-load.php
$wp_load_path = __DIR__ . '/../../../wp-load.php';

// Check if wp-load.php exists
if (!file_exists($wp_load_path)) {
    die("Error: wp-load.php not found at: $wp_load_path\n");
}

echo "=== WordPress Environment Check ===\n";
echo "Loading WordPress from: $wp_load_path\n";

// Try to load WordPress
require_once($wp_load_path);

// Verify WordPress loaded
if (!function_exists('get_bloginfo')) {
    die("Error: WordPress core functions not available.\n");
}

echo "\n=== WordPress Info ===\n";
echo "Site URL: " . get_site_url() . "\n";
echo "Home URL: " . home_url() . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";

// Check if WooCommerce is active
$active_plugins = get_option('active_plugins');
$woo_active = false;

foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'woocommerce.php') !== false) {
        $woo_active = true;
        break;
    }
}

echo "\n=== Plugin Status ===\n";
echo "WooCommerce Active: " . ($woo_active ? 'Yes' : 'No') . "\n";

// List active plugins
echo "\nActive Plugins (" . count($active_plugins) . "):\n";
foreach ($active_plugins as $plugin) {
    echo "- $plugin\n";}

// List post types
echo "\n=== Registered Post Types ===\n";
$post_types = get_post_types([], 'objects');
foreach ($post_types as $post_type) {
    echo $post_type->name . ' (' . $post_type->label . ')\n';
}

// Check if we can query the database
try {
    global $wpdb;
    $table = $wpdb->prefix . 'posts';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    echo "\n=== Database Connection ===\n";
    echo "Database connection successful. Total posts: $count\n";
    
    // Check WooCommerce tables
    $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}woocommerce%'");
    echo "\nWooCommerce Tables (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
} catch (Exception $e) {
    echo "\nDatabase Error: " . $e->getMessage() . "\n";
}

echo "\n=== Environment Check Complete ===\n";
