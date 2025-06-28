<?php
/**
 * Simple test script to verify PHP execution and WordPress connectivity
 */

echo "=== Test Script ===\n";

try {
    // Test basic PHP execution
    echo "PHP is executing correctly.\n";
    
    // Try to load WordPress
    $wp_load_path = __DIR__ . '/../../../wp-load.php';
    
    if (file_exists($wp_load_path)) {
        echo "WordPress load file found at: $wp_load_path\n";
        
        // Try to include WordPress
        require_once($wp_load_path);
        
        // If we get here, WordPress loaded successfully
        echo "WordPress loaded successfully.\n";
        echo "Site URL: " . get_site_url() . "\n";
        
        // Test database connection
        global $wpdb;
        $result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
        echo "Total posts in database: " . intval($result) . "\n";
        
    } else {
        echo "Error: WordPress load file not found at: $wp_load_path\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (isset($wpdb)) {
        echo "Last database error: " . $wpdb->last_error . "\n";
    }
}

echo "=== End of Test ===\n";
