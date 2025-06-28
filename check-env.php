<?php
echo "=== Basic Environment Check ===\n";

// Basic PHP info
echo "PHP Version: " . phpversion() . "\n";
echo "Current Directory: " . __DIR__ . "\n";

// Check if we can include wp-load.php
$wp_load_path = __DIR__ . '/../../../wp-load.php';
echo "\nLooking for wp-load.php at: $wp_load_path\n";

if (file_exists($wp_load_path)) {
    echo "wp-load.php found. Attempting to include...\n";
    
    // Try to include wp-load.php with error handling
    try {
        require_once($wp_load_path);
        echo "Successfully included wp-load.php\n";
        
        // Test WordPress functions
        if (function_exists('get_bloginfo')) {
            echo "WordPress Version: " . get_bloginfo('version') . "\n";
            echo "Site URL: " . get_site_url() . "\n";
            
            // Test database connection
            global $wpdb;
            $result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
            echo "Total posts in database: " . intval($result) . "\n";
            
            // Check if WooCommerce is active
            if (class_exists('WooCommerce')) {
                echo "WooCommerce Version: " . WC_VERSION . "\n";
                
                // Count products
                $product_count = wp_count_posts('product');
                echo "Total Products: " . $product_count->publish . "\n";
            } else {
                echo "WooCommerce is not active\n";
            }
        } else {
            echo "WordPress functions not available after including wp-load.php\n";
        }
        
    } catch (Exception $e) {
        echo "Error including wp-load.php: " . $e->getMessage() . "\n";
    }
} else {
    echo "wp-load.php not found.\n";
    
    // Try to find wp-config.php
    $wp_config_path = __DIR__ . '/../../../wp-config.php';
    if (file_exists($wp_config_path)) {
        echo "\nFound wp-config.php at: $wp_config_path\n";
    } else {
        echo "\nwp-config.php not found.\n";
    }
}

echo "\n=== Check Complete ===\n";
