<?php
/**
 * Custom AJAX handlers for Mitnafun Order Admin
 */

defined('ABSPATH') || exit;

/**
 * Get detailed product information including stock status
 * Used by the checkout debug script
 */
function mitnafun_ajax_get_product_details() {
    check_ajax_referer('mitnafun_checkout_nonce', 'nonce');
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if (!$product_id) {
        wp_send_json_error(array('message' => 'No product ID provided'));
        return;
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error(array('message' => 'Product not found'));
        return;
    }
    
    // Get stock information
    $stock_quantity = $product->get_stock_quantity();
    $stock_status = $product->get_stock_status();
    
    // Get initial stock with fallback methods
    $initial_stock = null;
    $initial_stock_debug = '';
    
    // Method 1: Try standard post meta
    $initial_stock = get_post_meta($product_id, '_initial_stock', true);
    $initial_stock_debug .= "Standard meta lookup: " . ($initial_stock ? $initial_stock : 'not found') . ". ";
    
    // Method 2: If not found or empty, try direct database query
    if (empty($initial_stock)) {
        global $wpdb;
        $initial_stock_query = $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_initial_stock' LIMIT 1",
            $product_id
        );
        $db_initial_stock = $wpdb->get_var($initial_stock_query);
        
        if (!empty($db_initial_stock)) {
            $initial_stock = $db_initial_stock;
            $initial_stock_debug .= "Retrieved from direct DB query: {$db_initial_stock}. ";
        } else {
            $initial_stock_debug .= "DB query found no results. ";
        }
    }
    
    // Method 3: Special case for product ID 4217 (מגה סלייד דקלים)
    if ($product_id == 4217 && empty($initial_stock)) {
        // Set a default initial stock for this product
        $initial_stock = 20; // Adjust this value as needed
        $initial_stock_debug .= "Using default value 20 for product ID 4217 (מגה סלייד דקלים). ";
        
        // Save this value to the database for future use
        update_post_meta($product_id, '_initial_stock', $initial_stock);
        $initial_stock_debug .= "Saved default value to database. ";
    }
    
    $manage_stock = $product->managing_stock();
    $backorders_allowed = $product->backorders_allowed();
    
    // Get product details
    $product_name = $product->get_name();
    $product_type = $product->get_type();
    $product_sku = $product->get_sku();
    
    // Prepare response
    $response = [
        'id' => $product_id,
        'name' => $product_name,
        'type' => $product_type,
        'sku' => $product_sku,
        'stock_quantity' => $stock_quantity,
        'stock_status' => $stock_status,
        'initial_stock' => $initial_stock ? intval($initial_stock) : null,
        'initial_stock_debug' => $initial_stock_debug,
        'manage_stock' => $manage_stock,
        'backorders_allowed' => $backorders_allowed
    ];
    
    wp_send_json_success($response);
}

/**
 * Handle logging of basket actions for debugging
 */
function mitnafun_ajax_log_basket_action() {
    // Verify nonce for logged-in users
    if (is_user_logged_in()) {
        check_ajax_referer('wc_ajax_nonce', 'nonce');
    }
    
    // Get and validate log data
    $log_data = isset($_POST['log_data']) ? json_decode(wp_unslash($_POST['log_data']), true) : null;
    
    if (!$log_data || !is_array($log_data)) {
        wp_send_json_error(array('message' => 'Invalid log data'));
        return;
    }
    
    // Ensure logs directory exists
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/mitnafun-logs';
    
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
        // Add .htaccess to prevent direct access
        file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
    }
    
    // Set up log file with daily rotation
    $log_file = $log_dir . '/basket_actions-' . date('Y-m-d') . '.log';
    
    // Format the log entry
    $timestamp = isset($log_data['timestamp']) ? $log_data['timestamp'] : current_time('mysql');
    $action = isset($log_data['action']) ? $log_data['action'] : 'unknown_action';
    $data = isset($log_data['data']) ? $log_data['data'] : array();
    
    $log_entry = sprintf(
        "[%s] %s: %s\n",
        $timestamp,
        $action,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    
    // Write to log file
    $result = file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    if ($result === false) {
        wp_send_json_error(array('message' => 'Failed to write to log file'));
        return;
    }
    
    // Clean up old log files (keep last 7 days)
    $log_files = glob($log_dir . '/basket_actions-*.log');
    $keep_days = 7;
    $now = time();
    
    foreach ($log_files as $file) {
        if (is_file($file) && ($now - filemtime($file)) >= ($keep_days * DAY_IN_SECONDS)) {
            @unlink($file);
        }
    }
    
    wp_send_json_success(array('message' => 'Log entry added'));
}

// Register AJAX handlers
add_action('wp_ajax_mitnafun_get_product_details', 'mitnafun_ajax_get_product_details');
add_action('wp_ajax_nopriv_mitnafun_get_product_details', 'mitnafun_ajax_get_product_details');
add_action('wp_ajax_log_basket_action', 'mitnafun_ajax_log_basket_action');
add_action('wp_ajax_nopriv_log_basket_action', 'mitnafun_ajax_log_basket_action');
