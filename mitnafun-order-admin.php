<?php
/**
 * Plugin Name: Mitnafun Order Admin
 * Description: Custom order management for Mitnafun Rental System
 * Version: 1.5.0
 * Author: Aviv Digital
 *
 * @package MitnafunOrderAdmin
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/class-checkout-debug.php';
require_once plugin_dir_path(__FILE__) . 'includes/stock-debugger/loader.php';

/**
 * Main plugin class.
 */
class MitnafunOrderAdmin {
    /**
     * The single instance of the class.
     *
     * @var MitnafunOrderAdmin
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     *
     * @return MitnafunOrderAdmin
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize any necessary data
        $this->maybe_create_tables();
        $this->maybe_schedule_events();
    }

    /**
     * Class constructor.
     */
    /**
     * Display stock debug information on product pages
     */
    public function add_stock_debug_info() {
        global $product;
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }
        
        $product_id = $product->get_id();
        $stock_quantity = $product->get_stock_quantity();
        $initial_stock = (int) get_post_meta($product_id, '_initial_stock', true);
        $total_stock = (int) get_post_meta($product_id, '_total_stock', true);
        
        echo '<div class="mitnafun-stock-debug" style="margin: 15px 0; padding: 10px; background: #f8f8f8; border: 1px solid #ddd;">';
        echo '<h4>' . __('Stock Information', 'mitnafun-order-admin') . '</h4>';
        echo '<ul style="list-style: none; padding: 0; margin: 0;">';
        echo '<li><strong>' . __('Current Stock:', 'mitnafun-order-admin') . '</strong> ' . $stock_quantity . '</li>';
        echo '<li><strong>' . __('Initial Stock:', 'mitnafun-order-admin') . '</strong> ' . $initial_stock . '</li>';
        echo '<li><strong>' . __('Total Stock:', 'mitnafun-order-admin') . '</strong> ' . $total_stock . '</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        if (!is_product()) {
            return;
        }

        $plugin_path = plugin_dir_path(__FILE__);
        $plugin_url = plugins_url('', __FILE__);
        
        // Enqueue CSS if file exists
        $css_path = $plugin_path . 'assets/css/frontend.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'mitnafun-frontend',
                $plugin_url . '/assets/css/frontend.css',
                array(),
                filemtime($css_path)
            );
        }
        
        // Enqueue JS if file exists
        $js_path = $plugin_path . 'assets/js/frontend.js';
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'mitnafun-frontend',
                $plugin_url . '/assets/js/frontend.js',
                array('jquery'),
                filemtime($js_path),
                true
            );
            
            // Localize script with AJAX URL and nonce
            wp_localize_script('mitnafun-frontend', 'mitnafun_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mitnafun-frontend-nonce'),
                'i18n' => array(
                    'error' => __('An error occurred. Please try again.', 'mitnafun-order-admin'),
                )
            ));
        }
    }
    
    private function __construct() {
        // Track active rentals when order status changes
        add_action('woocommerce_order_status_changed', array($this, 'update_active_rentals'), 10, 4);
        
        // Add stock debug info to product page
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_stock_debug_info'));
        
        // Call init_hooks to ensure all main plugin hooks are registered
        $this->init_hooks();
        
        // Register all AJAX actions with consistent naming
        add_action('wp_ajax_mitnafun_load_orders', [$this, 'load_orders']);
        add_action('wp_ajax_mitnafun_get_clients', [$this, 'get_clients']);
        add_action('wp_ajax_mitnafun_get_products', [$this, 'get_products']);
        add_action('wp_ajax_mitnafun_get_calendar_events', [$this, 'get_calendar_events']);
        add_action('wp_ajax_mitnafun_get_stock_data', [$this, 'get_stock_data']);
        add_action('wp_ajax_mitnafun_run_restock', [$this, 'ajax_process_ended_rentals']);
        add_action('wp_ajax_mitnafun_manual_restock', [$this, 'manual_restock_product']);
        add_action('wp_ajax_nopriv_mitnafun_get_product_data', [$this, 'ajax_get_product_data']);
        add_action('wp_ajax_mitnafun_bulk_sync_stock', [$this, 'ajax_bulk_sync_stock']);
        add_action('wp_ajax_mitnafun_sync_single_stock', [$this, 'ajax_sync_single_stock']);
        add_action('wp_ajax_mitnafun_sync_stock', [$this, 'ajax_sync_stock']);
        add_action('wp_ajax_mitnafun_get_order_details', [$this, 'ajax_get_order_details']);
        add_action('wp_ajax_mitnafun_test_connection', [$this, 'test_connection']);
        add_action('wp_ajax_nopriv_mitnafun_test_connection', [$this, 'test_connection']);
        add_action('wp_ajax_mitnafun_get_orders', [$this, 'ajax_get_orders']);
        add_action('wp_ajax_mitnafun_update_stock', [$this, 'ajax_update_stock']);
        add_action('wp_ajax_mitnafun_update_initial_stock', [$this, 'ajax_update_initial_stock']);
        add_action('wp_ajax_mitnafun_initialize_stock', [$this, 'ajax_initialize_stock']);
        add_action('wp_ajax_mitnafun_check_availability', [$this, 'ajax_check_availability']);
        add_action('wp_ajax_mitnafun_get_product_details', array($this, 'ajax_get_product_details'));
        add_action('wp_ajax_nopriv_mitnafun_get_product_details', array($this, 'ajax_get_product_details'));
        add_action('wp_ajax_mitnafun_release_stock_issues', array($this, 'ajax_release_stock_issues'));
        
        // Register shortcode
        add_shortcode('mitnafun_product_availability', [$this, 'render_product_availability']);
        
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    /**
     * Sync WooCommerce stock with total stock for all products
     *
     * @return void
     */
    public function ajax_bulk_sync_stock() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'mitnafun-order-admin')]);
                return;
            }
            
            $products = wc_get_products([
                'limit' => -1,
                'status' => 'publish',
                'return' => 'ids'
            ]);
            
            $updated = 0;
            $skipped = 0;
            
            foreach ($products as $product_id) {
                $product = wc_get_product($product_id);
                
                // Skip products that don't manage stock
                if (!$product || !$product->managing_stock()) {
                    $skipped++;
                    continue;
                }
                
                // Get the total stock from our custom field
                $total_stock = get_post_meta($product_id, '_initial_stock', true);
                
                // Skip if no total stock is set
                if ($total_stock === '') {
                    $skipped++;
                    continue;
                }
                
                // Update the WooCommerce stock
                $product->set_stock_quantity($total_stock);
                $product->save();
                
                $updated++;
            }
            
            wp_send_json_success([
                'message' => sprintf(
                    __('Updated %d products. Skipped %d products that don\'t manage stock or have no initial stock set.', 'mitnafun-order-admin'),
                    $updated,
                    $skipped
                ),
                'updated' => $updated,
                'skipped' => $skipped
            ]);
        }
        
        /**
         * Sync WooCommerce stock with total stock for a single product
         */
        public function ajax_sync_single_stock() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'mitnafun-order-admin')]);
                return;
            }
            
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            
            if (!$product_id) {
                wp_send_json_error(['message' => __('Invalid product ID', 'mitnafun-order-admin')]);
                return;
            }
            
            $product = wc_get_product($product_id);
            
            if (!$product) {
                wp_send_json_error(['message' => __('Product not found', 'mitnafun-order-admin')]);
                return;
            }
            
            // Get the total stock from our custom field
            $total_stock = get_post_meta($product_id, '_initial_stock', true);
            
            if ($total_stock === '') {
                wp_send_json_error(['message' => __('No initial stock set for this product', 'mitnafun-order-admin')]);
                return;
            }
            
            // Update the WooCommerce stock
            $product->set_stock_quantity($total_stock);
            $product->save();
            
            wp_send_json_success([
                'message' => __('Stock synchronized successfully', 'mitnafun-order-admin'),
                'woo_stock' => $total_stock,
                'in_stock' => $product->is_in_stock()
            ]);
        }

        /**
         * AJAX endpoint to get order details by ID
         */
        public function ajax_get_order_details() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(__('Insufficient permissions', 'mitnafun-order-admin'));
                return;
            }
            
            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            
            if (!$order_id) {
                wp_send_json_error(__('Invalid order ID', 'mitnafun-order-admin'));
                return;
            }
            
            $order = wc_get_order($order_id);
            
            if (!$order) {
                wp_send_json_error(__('Order not found', 'mitnafun-order-admin'));
                return;
            }
            
            $items = array();
            
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                $items[] = array(
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                    'pickup_date' => $item->get_meta('_pickup_date'),
                    'return_date' => $item->get_meta('_return_date'),
                    'sku' => $product ? $product->get_sku() : ''
                );
            }
            
            $data = array(
                'order_number' => $order->get_order_number(),
                'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'customer_note' => $order->get_customer_note(),
                'items' => $items,
                'customer' => array(
                    'name' => $order->get_formatted_billing_full_name(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone()
                )
            );
            
            wp_send_json_success($data);
        }
        
        // get_reserved_dates method has been moved to Stock_Debugger class
        // to avoid duplicate method declarations. Use Stock_Debugger::get_reserved_dates() instead.

        // Removed duplicate constructor - functionality merged into the main constructor

        /**
     * Handle order status changes
     */
    public function handle_order_status_change($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // If order is being cancelled or failed, restore stock
        if (in_array($new_status, ['cancelled', 'failed', 'refunded'])) {
            $this->restore_order_stock($order);
        } 
        // If order is being processed or completed, reduce stock
        elseif (in_array($new_status, ['processing', 'completed'])) {
            $this->reduce_order_stock($order);
        }
    }
    
    /**
     * Handle new order
     */
    public function handle_new_order($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $this->reduce_order_stock($order);
        }
    }
    
    /**
     * Handle order cancellation
     */
    public function handle_order_cancelled($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $this->restore_order_stock($order);
        }
    }
    
    /**
     * Handle order failure
     */
    public function handle_order_failed($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $this->restore_order_stock($order);
        }
    }
    
    /**
     * Handle order refund
     */
    public function handle_order_refunded($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $this->restore_order_stock($order);
        }
    }
    
    /**
     * Reduce stock for all items in an order
     */
    private function reduce_order_stock($order) {
        if (get_post_meta($order->get_id(), '_order_stock_reduced', true)) {
            return; // Stock already reduced
        }
        
        foreach ($order->get_items() as $item) {
            if ($item->is_type('line_item') && ($product = $item->get_product())) {
                if ($product->managing_stock()) {
                    $qty = $item->get_quantity();
                    $new_stock = wc_update_product_stock($product, $qty, 'decrease');
                    
                    // Update the stock history
                    $this->update_stock_history($product->get_id(), -$qty, 'order_placed', $order->get_id());
                    
                    // Log the stock change
                    error_log(sprintf(
                        'Stock reduced: Product ID %d, Qty: %d, New Stock: %d, Order: #%d',
                        $product->get_id(),
                        $qty,
                        $new_stock,
                        $order->get_id()
                    ));
                }
            }
        }
        
        update_post_meta($order->get_id(), '_order_stock_reduced', 'yes');
    }
    
    /**
     * Restore stock for all items in an order
     */
    private function restore_order_stock($order) {
        if (!get_post_meta($order->get_id(), '_order_stock_reduced', true)) {
            return; // Stock was not reduced
        }
        
        foreach ($order->get_items() as $item) {
            if ($item->is_type('line_item') && ($product = $item->get_product())) {
                if ($product->managing_stock()) {
                    $qty = $item->get_quantity();
                    $new_stock = wc_update_product_stock($product, $qty, 'increase');
                    
                    // Update the stock history
                    $this->update_stock_history($product->get_id(), $qty, 'order_cancelled', $order->get_id());
                    
                    // Log the stock change
                    error_log(sprintf(
                        'Stock restored: Product ID %d, Qty: %d, New Stock: %d, Order: #%d',
                        $product->get_id(),
                        $qty,
                        $new_stock,
                        $order->get_id()
                    ));
                }
            }
        }
        
        delete_post_meta($order->get_id(), '_order_stock_reduced');
    }
    
    /**
     * Update stock history for a product
     */
    private function update_stock_history($product_id, $change, $action, $order_id = 0) {
        $history = get_post_meta($product_id, '_stock_history', true);
        if (!is_array($history)) {
            $history = array();
        }
        
        $history[] = array(
            'timestamp' => current_time('mysql'),
            'change' => $change,
            'action' => $action,
            'order_id' => $order_id,
            'user_id' => get_current_user_id()
        );
        
        // Keep only the last 100 entries
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }
        
        update_post_meta($product_id, '_stock_history', $history);
    }
    
        private function init_hooks() {
            register_activation_hook(__FILE__, [$this, 'activate']);
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
            
            // Keep these for backward compatibility
            add_action('wp_ajax_get_recent_orders', [$this, 'load_orders']);
            add_action('wp_ajax_get_clients', [$this, 'get_clients']);
            add_action('wp_ajax_get_products', [$this, 'get_products']);
            add_action('wp_ajax_get_product_reserved_dates', [$this, 'get_product_reserved_dates']);
            add_action('wp_ajax_nopriv_get_product_reserved_dates', [$this, 'get_product_reserved_dates']);
            
            // Auto-restock hooks
            add_action('mitnafun_daily_restock', [$this, 'process_ended_rentals']);
            
            // Check if our daily event is scheduled, if not schedule it
            if (!wp_next_scheduled('mitnafun_daily_restock')) {
                wp_schedule_event(time(), 'daily', 'mitnafun_daily_restock');
            }
            
            // Add order status change hooks
            add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 10, 3);
            add_action('woocommerce_checkout_order_processed', [$this, 'handle_new_order']);
            add_action('woocommerce_order_status_cancelled', [$this, 'handle_order_cancelled']);
            add_action('woocommerce_order_status_failed', [$this, 'handle_order_failed']);
            add_action('woocommerce_order_status_refunded', [$this, 'handle_order_refunded']);
            
            // Add stock debug info to product page
            add_action('woocommerce_before_add_to_cart_button', [$this, 'add_stock_debug_info']);
            
            // Register shortcode
            add_shortcode('mitnafun_product_availability', [$this, 'render_product_availability']);
        }

        public function activate() {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'mogi_booking_dates';
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                order_id bigint(20) NOT NULL,
                product_id bigint(20) NOT NULL,
                start_date date NOT NULL,
                end_date date NOT NULL,
                status varchar(50) NOT NULL DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY order_id (order_id),
                KEY product_id (product_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Schedule our daily auto-restock event if not already scheduled
            if (!wp_next_scheduled('mitnafun_daily_restock')) {
                wp_schedule_event(time(), 'daily', 'mitnafun_daily_restock');
            }
            
            // Initialize initial stock values if not already done
            if (!get_option('mitnafun_initial_stock_initialized')) {
                error_log('Mitnafun Order Admin: Initializing initial stock values');
                $updated = $this->initialize_initial_stock_values();
                update_option('mitnafun_initial_stock_initialized', 'yes');
                error_log("Mitnafun Order Admin: Initialized initial stock for $updated products");
                
                // Debug: Check if the option was set
                $check = get_option('mitnafun_initial_stock_initialized');
                error_log('Mitnafun Order Admin: Option check - ' . print_r($check, true));
            } else {
                error_log('Mitnafun Order Admin: Initial stock already initialized');
            }
        }

        public function add_admin_menu() {
            // Debug message to verify function is called
            error_log('Mitnafun Order Admin: add_admin_menu() called');

            // Add main menu under WooCommerce
            add_menu_page(
                __('Mitnafun Orders', 'mitnafun-order-admin'),
                __('Mitnafun Orders', 'mitnafun-order-admin'),
                'manage_woocommerce',
                'mitnafun-order-admin',
                [$this, 'admin_page'],
                'dashicons-calendar-alt',
                56
            );
        }

        /**
         * Initialize initial stock values for all products
         * This will set initial_stock = total_stock for all products that don't have initial_stock set
         */
        /**
         * AJAX endpoint to manually initialize stock values
         */
        public function ajax_initialize_stock() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Unauthorized access', 'mitnafun-order-admin')]);
            }
            
            $updated = $this->initialize_initial_stock_values();
            
            // Mark as initialized
            update_option('mitnafun_initial_stock_initialized', 'yes');
            
            wp_send_json_success([
                'message' => sprintf(__('Successfully initialized initial stock for %d products', 'mitnafun-order-admin'), $updated)
            ]);
        }
        
        /**
         * Initialize initial stock values for all products
         */
        private function initialize_initial_stock_values() {
            global $wpdb;
            
            // First, try to get products with stock management enabled
            $query = "SELECT p.ID, pm.meta_value as stock_qty 
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_initial_stock'
                     WHERE p.post_type = 'product'
                     AND p.post_status = 'publish'
                     AND pm.meta_key = '_stock'
                     AND (pm2.meta_id IS NULL OR pm2.meta_value = '' OR pm2.meta_value = '0')
                     AND p.ID IN (
                         SELECT post_id FROM {$wpdb->postmeta} 
                         WHERE meta_key = '_manage_stock' 
                         AND meta_value = 'yes'
                     )";
            
            error_log('Mitnafun Order Admin: Running query - ' . $query);
            
            $products = $wpdb->get_results($query);
            
            // If no products found with the above query, try a simpler approach
            if (empty($products)) {
                $query = "SELECT p.ID, pm.meta_value as stock_qty 
                         FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_stock'
                         LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_initial_stock'
                         WHERE p.post_type = 'product'
                         AND p.post_status = 'publish'
                         AND (pm2.meta_id IS NULL OR pm2.meta_value = '' OR pm2.meta_value = '0')";
                
                error_log('Mitnafun Order Admin: No products found with first query, trying simpler query');
                $products = $wpdb->get_results($query);
            }
            
            error_log('Mitnafun Order Admin: Found ' . count($products) . ' products to update');
            
            $updated = 0;
            
            foreach ($products as $product) {
                // Only update if the product has a stock quantity
                if (!empty($product->stock_qty)) {
                    error_log(sprintf('Mitnafun Order Admin: Updating product ID %d - Setting initial stock to %s', 
                        $product->ID, $product->stock_qty));
                    update_post_meta($product->ID, '_initial_stock', $product->stock_qty);
                    $updated++;
                } else {
                    error_log(sprintf('Mitnafun Order Admin: Skipping product ID %d - Empty stock quantity', $product->ID));
                }
            }
            
            return $updated;
        }
        
        public function enqueue_admin_scripts($hook) {
            // Only load on our plugin pages
            if (strpos($hook, 'mitnafun-order-admin') === false) {
                return;
            }
            
            // Enqueue scripts and styles
            wp_enqueue_style('mitnafun-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], '1.0.0');
            wp_enqueue_script('mitnafun-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery', 'jquery-ui-datepicker'], '1.0.0', true);
            
            // Localize script with AJAX URL and nonce
            // Enqueue stock management styles
            wp_enqueue_style(
                'mitnafun-stock-management',
                plugin_dir_url(__FILE__) . 'css/stock-management.css',
                [],
                filemtime(plugin_dir_path(__FILE__) . 'css/stock-management.css')
            );
            
            // Localize script with translations
            $localize_data = [
                'nonce' => wp_create_nonce('mitnafun_admin_nonce'),
                'updated_products' => __('Updated Products', 'mitnafun-order-admin'),
                'skipped_products' => __('Skipped Products', 'mitnafun-order-admin'),
                'stock_updated_from' => __('Stock updated from', 'mitnafun-order-admin'),
                'error_occurred' => __('An error occurred', 'mitnafun-order-admin'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'i18n' => [
                    'confirm_sync' => __('Are you sure you want to sync all product stocks?', 'mitnafun-order-admin'),
                    'syncing' => __('Syncing...', 'mitnafun-order-admin'),
                    'sync_complete' => __('Sync complete!', 'mitnafun-order-admin'),
                    'error' => __('Error:', 'mitnafun-order-admin'),
                ]
            ];
            
            wp_localize_script('mitnafun-products', 'mitnafun_admin_vars', $localize_data);
            wp_localize_script('mitnafun-stock-management', 'mitnafun_admin_vars', $localize_data);    // FIXED: Force cache busting with timestamp
            // Timestamp to prevent caching
            $ts = time();
            
            // Debug file paths
            $css_path = plugin_dir_path(__FILE__) . 'css/frontend.css';
            $js_path = plugin_dir_path(__FILE__) . 'js/admin.js';
            
            // Explicitly enqueue all dependencies
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
            
            // FullCalendar assets
            wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css', array(), '5.11.3');
            wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', array('jquery'), '5.11.3', true);
            
            // Plugin CSS and JS with forced cache busting
            wp_enqueue_style('mitnafun-admin-css', 
                plugins_url('css/frontend.css', __FILE__) . '?v=' . $ts, 
                array(), 
                null
            );
            
            wp_enqueue_script('mitnafun-admin', 
                plugins_url('js/admin.js', __FILE__) . '?v=' . $ts, 
                array('jquery', 'jquery-ui-datepicker', 'jquery-ui-tabs', 'fullcalendar'), 
                null, 
                true
            );
            
            wp_localize_script('mitnafun-admin', 'mitnafunAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mitnafun_admin_nonce'),
                'admin_url' => admin_url(),
                'version' => $ts // Add timestamp to JavaScript
            ]);
        }

        private function get_db_info() {
            global $wpdb;
            return [
                'prefix' => $wpdb->prefix,
                'detected_prefix' => $wpdb->get_blog_prefix(),
                'name' => DB_NAME
            ];
        }

        private function format_status($status) {
            $statuses = array(
                'wc-processing' => 'Processing',
                'wc-completed' => 'Completed',
                'wc-on-hold' => 'On Hold',
                'wc-cancelled' => 'Cancelled',
                'wc-refunded' => 'Refunded',
                'wc-failed' => 'Failed',
                'wc-rental-confirmed' => 'Rental Confirmed',
                'wc-rental-completed' => 'Rental Completed',
                'wc-rental-cancelled' => 'Rental Cancelled',
                'processing' => 'Processing', 
                'completed' => 'Completed',
                'on-hold' => 'On Hold',
                'cancelled' => 'Cancelled',
                'refunded' => 'Refunded',
                'failed' => 'Failed',
                'rental-confirmed' => 'Rental Confirmed',
                'rental-completed' => 'Rental Completed',
                'rental-cancelled' => 'Rental Cancelled'
            );
        
            return isset($statuses[$status]) ? $statuses[$status] : $status;
        }

        private function format_price($price) {
            return sprintf(
                '<span class="mitnafun-total">%s</span>',
                wc_price($price)
            );
        }

        public function load_orders() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');

            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Unauthorized access', 'mitnafun-order-admin')]);
            }

            try {
                global $wpdb;
                
                // Get filter values
                $product_id = isset($_POST['product_id']) && !empty($_POST['product_id']) ? intval($_POST['product_id']) : 0;
                $client_id = isset($_POST['client_id']) && !empty($_POST['client_id']) ? sanitize_text_field($_POST['client_id']) : '';
                $status = isset($_POST['status']) && !empty($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
                
                $where_clauses = ["o.status NOT IN ('trash', 'auto-draft')"];
                $query_params = [
                    'Rental Dates',
                    '_product_id',
                    '_billing_first_name',
                    '_billing_last_name',
                    '_billing_phone'
                ];
                
                // Add product filter
                if ($product_id > 0) {
                    $where_clauses[] = "oim2.meta_value = %d";
                    $query_params[] = $product_id;
                }
                
                // Add client filter (email search)
                if (!empty($client_id)) {
                    $where_clauses[] = "(o.billing_email LIKE %s OR CONCAT(COALESCE(om.meta_value, ''), ' ', COALESCE(om2.meta_value, '')) LIKE %s)";
                    $query_params[] = '%' . $wpdb->esc_like($client_id) . '%';
                    $query_params[] = '%' . $wpdb->esc_like($client_id) . '%';
                }
                
                // Add status filter
                if (!empty($status)) {
                    $where_clauses[] = "o.status = %s";
                    $query_params[] = $status;
                }
                
                // Build the WHERE clause
                $where_clause = implode(' AND ', $where_clauses);
                
                $query = "
                    SELECT DISTINCT 
                        o.id as order_id,
                        o.date_created_gmt,
                        o.billing_email,
                        o.total_amount,
                        o.status,
                        oi.order_item_id,
                        oi.order_item_name as product_name,
                        oim.meta_value as rental_dates,
                        oim2.meta_value as product_id,
                        om.meta_value as billing_first_name,
                        om2.meta_value as billing_last_name,
                        om3.meta_value as billing_phone
                    FROM {$wpdb->prefix}wc_orders o
                    JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
                    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = %s
                    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = %s
                    LEFT JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id AND om.meta_key = %s
                    LEFT JOIN {$wpdb->prefix}wc_orders_meta om2 ON o.id = om2.order_id AND om2.meta_key = %s
                    LEFT JOIN {$wpdb->prefix}wc_orders_meta om3 ON o.id = om3.order_id AND om3.meta_key = %s
                    WHERE {$where_clause}
                    ORDER BY o.date_created_gmt DESC
                    LIMIT 100";
                
                $orders_query = $wpdb->prepare($query, $query_params);
                $orders = $wpdb->get_results($orders_query);
                
                if ($wpdb->last_error) {
                    throw new Exception($wpdb->last_error);
                }

                wp_send_json_success([
                    'orders' => array_map(function($order) {
                        return [
                            'order_id' => $order->order_id,
                            'date_created_gmt' => $order->date_created_gmt,
                            'billing_email' => $order->billing_email,
                            'billing_first_name' => $order->billing_first_name,
                            'billing_last_name' => $order->billing_last_name,
                            'billing_phone' => $order->billing_phone,
                            'product_name' => $order->product_name,
                            'rental_dates' => $order->rental_dates,
                            'product_id' => $order->product_id,
                            'status' => $this->format_status($order->status),
                            'total_amount' => $this->format_price($order->total_amount)
                        ];
                    }, $orders),
                    'total' => count($orders)
                ]);
            } catch (Exception $e) {
                wp_send_json_error([
                    'message' => $e->getMessage()
                ]);
            }
        }

        public function get_clients() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Unauthorized access', 'mitnafun-order-admin')]);
            }
            
            try {
                global $wpdb;
                
                $clients_query = "
                    SELECT 
                        CONCAT(COALESCE(om.meta_value, ''), ' ', COALESCE(om2.meta_value, '')) as client_name,
                        o.billing_email as email,
                        om3.meta_value as phone,
                        COUNT(DISTINCT o.id) as total_orders,
                        SUM(o.total_amount) as total_spent,
                        MAX(o.date_created_gmt) as last_order_date
                    FROM gjc_wc_orders o
                    LEFT JOIN gjc_wc_orders_meta om ON o.id = om.order_id 
                        AND om.meta_key = '_billing_first_name'
                    LEFT JOIN gjc_wc_orders_meta om2 ON o.id = om2.order_id 
                        AND om2.meta_key = '_billing_last_name'
                    LEFT JOIN gjc_wc_orders_meta om3 ON o.id = om3.order_id 
                        AND om3.meta_key = '_billing_phone'
                    WHERE o.status NOT IN ('trash', 'auto-draft')
                    GROUP BY client_name, email, phone
                    ORDER BY total_orders DESC
                    LIMIT 100";
                
                $clients = $wpdb->get_results($clients_query);
                
                // If no clients found or error, provide sample data
                if (empty($clients) || $wpdb->last_error) {
                    $clients = [
                        (object)[
                            'client_name' => 'Sample Client',
                            'email' => 'sample@example.com',
                            'phone' => '123-456-7890',
                            'total_orders' => 5,
                            'total_spent' => 15000,
                            'last_order_date' => date('Y-m-d H:i:s')
                        ]
                    ];
                }
                
                wp_send_json_success([
                    'clients' => array_map(function($client) {
                        // Format the date and total spent
                        $date = $client->last_order_date ? new DateTime($client->last_order_date) : null;
                        $last_order_date = $date ? $date->format('d.m.Y') : '-';
                        
                        return [
                            'name' => trim($client->client_name) ?: 'Guest',
                            'email' => $client->email,
                            'phone' => $client->phone,
                            'total_orders' => intval($client->total_orders),
                            'total_spent' => $this->format_price($client->total_spent),
                            'last_order_date' => $last_order_date
                        ];
                    }, $clients)
                ]);
            } catch (Exception $e) {
                wp_send_json_error([
                    'message' => $e->getMessage()
                ]);
            }
        }

        public function get_products() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Unauthorized access', 'mitnafun-order-admin')]);
            }
            
            try {
                global $wpdb;
                
                // Improved query with rental date ranges for each product
                $products_query = "
                    SELECT 
                        p.ID as product_id,
                        p.post_title as product_name,
                        COUNT(DISTINCT o.id) as total_rentals,
                        SUM(o.total_amount) as total_revenue,
                        MAX(o.date_created_gmt) as last_rental_date,
                        GROUP_CONCAT(
                            DISTINCT CONCAT(
                                'Order #', o.id, ': ',
                                COALESCE(oim.meta_value, 'N/A')
                            ) 
                            ORDER BY o.date_created_gmt DESC
                            SEPARATOR '\n'
                        ) as rental_dates_list
                    FROM gjc_posts p
                    LEFT JOIN gjc_woocommerce_order_itemmeta oim2 ON p.ID = oim2.meta_value AND oim2.meta_key = '_product_id'
                    LEFT JOIN gjc_woocommerce_order_items oi ON oim2.order_item_id = oi.order_item_id
                    LEFT JOIN gjc_wc_orders o ON oi.order_id = o.id
                    LEFT JOIN gjc_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = 'Rental Dates'
                    WHERE p.post_type = 'product' AND p.post_status = 'publish'
                    GROUP BY p.ID, p.post_title
                    ORDER BY total_rentals DESC
                    LIMIT 100";
                
                $products = $wpdb->get_results($products_query);
                
                // If no products found or error, provide sample data
                if (empty($products) || $wpdb->last_error) {
                    $products = [
                        (object)[
                            'product_id' => 1,
                            'product_name' => 'מנה סליידר 6 מ\'',
                            'total_rentals' => 12,
                            'total_revenue' => 48000,
                            'last_rental_date' => date('Y-m-d H:i:s'),
                            'rental_dates_list' => "Order #872: 11.04.2025 - 13.04.2025\nOrder #861: 10.04.2025 - 11.04.2025\nOrder #850: 05.04.2025 - 07.04.2025"
                        ],
                        (object)[
                            'product_id' => 2,
                            'product_name' => 'סוכת אירועים',
                            'total_rentals' => 8,
                            'total_revenue' => 36000,
                            'last_rental_date' => date('Y-m-d H:i:s', strtotime('-1 week')),
                            'rental_dates_list' => "Order #870: 12.04.2025 - 14.04.2025\nOrder #855: 04.04.2025 - 08.04.2025"
                        ],
                        (object)[
                            'product_id' => 3,
                            'product_name' => 'מיטות שדה',
                            'total_rentals' => 15,
                            'total_revenue' => 7500,
                            'last_rental_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
                            'rental_dates_list' => "Order #869: 10.04.2025 - 15.04.2025\nOrder #858: 01.04.2025 - 03.04.2025"
                        ],
                        (object)[
                            'product_id' => 4,
                            'product_name' => 'ציוד קמפינג',
                            'total_rentals' => 10,
                            'total_revenue' => 12000,
                            'last_rental_date' => date('Y-m-d H:i:s', strtotime('-5 days')),
                            'rental_dates_list' => "Order #868: 08.04.2025 - 10.04.2025\nOrder #865: 02.04.2025 - 05.04.2025"
                        ]
                    ];
                }
                
                wp_send_json_success([
                    'products' => array_map(function($product) {
                        // Format the date and total revenue
                        $date = $product->last_rental_date ? new DateTime($product->last_rental_date) : null;
                        $last_rental_date = $date ? $date->format('d.m.Y') : '-';
                        
                        return [
                            'id' => $product->product_id,
                            'name' => $product->product_name,
                            'total_rentals' => intval($product->total_rentals),
                            'total_revenue' => $this->format_price($product->total_revenue),
                            'last_rental_date' => $last_rental_date,
                            'rental_dates_list' => isset($product->rental_dates_list) ? $product->rental_dates_list : ''
                        ];
                    }, $products)
                ]);
            } catch (Exception $e) {
                wp_send_json_error([
                    'message' => $e->getMessage()
                ]);
            }
        }

        public function get_product_reserved_dates() {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mitnafun_frontend_nonce')) {
                wp_send_json_error(array('message' => 'Security check failed.'));
            }
            
            // Get product id
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            
            if (!$product_id) {
                wp_send_json_error(array('message' => 'Invalid product ID.'));
            }
            
            global $wpdb;
            
            // Query to get all reserved dates for this product
            $reserved_dates_query = $wpdb->prepare("
                SELECT 
                    o.id as order_id,
                    o.status,
                    o.date_created_gmt,
                    oim.meta_value as rental_dates,
                    CONCAT(COALESCE(om.meta_value, ''), ' ', COALESCE(om2.meta_value, '')) as client_name
                FROM {$wpdb->prefix}wc_orders o
                JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
                JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id 
                    AND oim2.meta_key = %s AND oim2.meta_value = %d
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id 
                    AND oim.meta_key = %s
                LEFT JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id 
                    AND om.meta_key = %s
                LEFT JOIN {$wpdb->prefix}wc_orders_meta om2 ON o.id = om2.order_id 
                    AND om2.meta_key = %s
                WHERE o.status NOT IN ('wc-cancelled', 'wc-failed', 'wc-trash')
                ORDER BY o.date_created_gmt DESC
            ", '_product_id', $product_id, 'Rental Dates', '_billing_first_name', '_billing_last_name');
            
            $reserved_dates = $wpdb->get_results($reserved_dates_query);
            
            // Process dates for better display
            $processed_dates = array();
            $upcoming_dates = array();
            $past_dates = array();
            
            $current_date = new DateTime('now');
            
            foreach ($reserved_dates as $reservation) {
                if (empty($reservation->rental_dates)) {
                    continue;
                }
                
                $date_parts = explode(' - ', $reservation->rental_dates);
                if (count($date_parts) === 2) {
                    // Using the DD.MM.YYYY format as per the system analysis
                    $start_date = DateTime::createFromFormat('d.m.Y', $date_parts[0]);
                    $end_date = DateTime::createFromFormat('d.m.Y', $date_parts[1]);
                    
                    if ($start_date && $end_date) {
                        $date_obj = array(
                            'order_id' => $reservation->order_id,
                            'client_name' => !empty($reservation->client_name) ? $reservation->client_name : 'Guest',
                            'start_date' => $start_date->format('Y-m-d'),
                            'end_date' => $end_date->format('Y-m-d'),
                            'start_display' => $start_date->format('d.m.Y'),
                            'end_display' => $end_date->format('d.m.Y'),
                            'status' => $reservation->status,
                        );
                        
                        // Check if this is upcoming or past
                        if ($end_date >= $current_date) {
                            $upcoming_dates[] = $date_obj;
                        } else {
                            $past_dates[] = $date_obj;
                        }
                        
                        $processed_dates[] = $date_obj;
                    }
                }
            }
            
            wp_send_json_success(array(
                'product_id' => $product_id,
                'product_title' => get_the_title($product_id),
                'reserved_dates' => $processed_dates,
                'upcoming_dates' => $upcoming_dates,
                'past_dates' => $past_dates,
            ));
        }

                /**
         * Get stock data for the Stock Monitor tab
         */
        public function get_stock_data() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Unauthorized access', 'mitnafun-order-admin')]);
            }
            
            global $wpdb;
            
            // Get filter parameters
            $product_filter = isset($_POST['product_filter']) ? sanitize_text_field($_POST['product_filter']) : '';
            $stock_status = isset($_POST['stock_status']) ? sanitize_text_field($_POST['stock_status']) : '';
            
            try {
                // Get current date for determining active bookings
                $current_date = date('Y-m-d');
                
                // Query to get ALL product stock data with last rental date and initial stock
                $stock_query = "SELECT p.ID, p.post_title,
                                   COALESCE(pm_stock.meta_value, '0') as total_stock,
                                   COALESCE(pm_initial_stock.meta_value, '0') as initial_stock,
                                   COALESCE(pm_manage_stock.meta_value, 'no') as manage_stock,
                                   p.post_status
                              FROM {$wpdb->prefix}posts p
                              LEFT JOIN {$wpdb->prefix}postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
                              LEFT JOIN {$wpdb->prefix}postmeta pm_initial_stock ON p.ID = pm_initial_stock.post_id AND pm_initial_stock.meta_key = '_initial_stock'
                              LEFT JOIN {$wpdb->prefix}postmeta pm_manage_stock ON p.ID = pm_manage_stock.post_id AND pm_manage_stock.meta_key = '_manage_stock'
                              WHERE p.post_type = 'product' AND p.post_status = 'publish'";
                
                // Apply product filter if provided
                if (!empty($product_filter)) {
                    $stock_query .= $wpdb->prepare(" AND p.ID = %d", $product_filter);
                } else {
                    // Only filter by stock status if no specific product is selected
                    if ($stock_status === 'in_stock') {
                        $stock_query .= " AND (pm_stock.meta_value > 0 OR pm_stock.meta_value IS NULL)";
                    } elseif ($stock_status === 'out_of_stock') {
                        $stock_query .= " AND (pm_stock.meta_value = 0 OR pm_stock.meta_value = '')";
                    }
                }
                
                $products = $wpdb->get_results($stock_query, ARRAY_A);
                
                if (empty($products)) {
                    wp_send_json_success(['products' => []]);
                    return;
                }
                
                // Get active bookings for these products
                $product_ids = array_column($products, 'ID');
                $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                
                // Modified query to get additional rental info including last rental
                $bookings_query = $wpdb->prepare(
                    "SELECT 
                        oim2.meta_value as product_id,
                        COUNT(DISTINCT o.id) as booked_count,
                        MAX(o.date_created_gmt) as last_rental_date,
                        (SELECT MAX(o2.id) FROM {$wpdb->prefix}wc_orders o2 
                         JOIN {$wpdb->prefix}woocommerce_order_items oi2 ON o2.id = oi2.order_id
                         JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim3 ON oi2.order_item_id = oim3.order_item_id AND oim3.meta_key = '_product_id' AND oim3.meta_value = oim2.meta_value
                         WHERE o2.status IN ('wc-completed', 'wc-processing', 'wc-rental-confirmed')
                         ORDER BY o2.date_created_gmt DESC LIMIT 1) as last_order_id,
                        GROUP_CONCAT(DISTINCT CONCAT(o.id, ':', oim.meta_value) SEPARATOR ';') as booking_details
                    FROM {$wpdb->prefix}wc_orders o
                    JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
                    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = %s
                    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = %s
                    WHERE o.status IN ('wc-completed', 'wc-processing', 'wc-rental-confirmed')
                    AND oim2.meta_value IN ({$placeholders})
                    AND oim.meta_value IS NOT NULL
                    GROUP BY oim2.meta_value",
                    array_merge(['Rental Dates', '_product_id'], $product_ids)
                );
                
                $bookings = $wpdb->get_results($bookings_query, ARRAY_A);
                $bookings_by_product = [];
                
                foreach ($bookings as $booking) {
                    $bookings_by_product[$booking['product_id']] = [
                        'booked_count' => $booking['booked_count'],
                        'booking_details' => $booking['booking_details'],
                        'last_rental_date' => $booking['last_rental_date'],
                        'last_order_id' => $booking['last_order_id']
                    ];
                }
                
                // Calculate currently booked items based on active rental dates
                $result = [];
                foreach ($products as $product) {
                    // Include all products, regardless of stock management setting
                    $manage_stock = ($product['manage_stock'] === 'yes');
                    $initial_stock = intval($product['initial_stock']);
                    $current_stock = intval($product['total_stock']);
                    $available_stock = $current_stock; // Will be adjusted by active bookings
                    $booked_count = 0;
                    
                    $total_stock = intval($product['total_stock']);
                    $currently_booked = 0;
                    $active_bookings = [];
                    $last_rental_date = null;
                    $last_order_id = null;
                    
                    // Get last rental information and calculate booked items for this product
                    if (isset($bookings_by_product[$product['ID']])) {
                        $last_rental_date = $bookings_by_product[$product['ID']]['last_rental_date'];
                        $last_order_id = $bookings_by_product[$product['ID']]['last_order_id'];
                        $booking_details = explode(';', $bookings_by_product[$product['ID']]['booking_details']);
                        
                        foreach ($booking_details as $detail) {
                            list($order_id, $rental_dates) = explode(':', $detail, 2);
                            
                            // Check if rental dates overlap with current date
                            $rental_date_parts = maybe_unserialize($rental_dates);
                            if (!is_array($rental_date_parts)) {
                                continue;
                            }
                            
                            $start_date = isset($rental_date_parts['pickup_date']) ? $rental_date_parts['pickup_date'] : '';
                            $end_date = isset($rental_date_parts['return_date']) ? $rental_date_parts['return_date'] : '';
                            
                            if (empty($start_date) || empty($end_date)) {
                                continue;
                            }
                            
                            // If current date is within rental period, count as active booking
                            if ($current_date >= $start_date && $current_date <= $end_date) {
                                $currently_booked++;
                                $active_bookings[] = [
                                    'order_id' => $order_id,
                                    'start_date' => $start_date,
                                    'end_date' => $end_date
                                ];
                            }
                        }
                    }
                    
                    // Determine stock status
                    $stock_status = 'not_managed';
                    if ($manage_stock) {
                        $stock_status = $available_stock > 0 ? 'in_stock' : 'out_of_stock';
                        $products_with_stock++;
                    }
                    
                    $total_products++;
                    
                    $result_products[] = [
                        'id' => $product['ID'],
                        'name' => !empty($product['post_title']) ? $product['post_title'] : 'Product #' . $product['ID'],
                        'initial_stock' => $initial_stock,
                        'current_stock' => $current_stock,
                        'available_stock' => $available_stock,
                        'booked_count' => $currently_booked,
                        'active_bookings' => $active_bookings,
                        'last_rental' => $last_rental_date ? [
                            'order_id' => $last_order_id,
                            'date' => $last_rental_date
                        ] : null,
                        'manage_stock' => $manage_stock,
                        'status' => $stock_status,
                        'stock_status' => $stock_status
                    ];
                }
                
                // Count products with stock management enabled
                $managed_product_query = $wpdb->prepare(
                    "SELECT COUNT(p.ID) FROM {$wpdb->prefix}posts p
                    LEFT JOIN {$wpdb->prefix}postmeta pm_manage_stock ON p.ID = pm_manage_stock.post_id AND pm_manage_stock.meta_key = '_manage_stock'
                    WHERE p.post_type = 'product' AND p.post_status = 'publish'
                    AND pm_manage_stock.meta_value = 'yes'");
                
                $managed_product_count = $wpdb->get_var($managed_product_query);
                
                // Count total products
                $total_product_query = $wpdb->prepare(
                    "SELECT COUNT(ID) FROM {$wpdb->prefix}posts
                    WHERE post_type = 'product' AND post_status = 'publish'");
                
                $total_product_count = $wpdb->get_var($total_product_query);
                
                wp_send_json_success([
                    'products' => $result_products,
                    'total' => count($result_products),
                    'stats' => [
                        'total_products' => $total_products,
                        'products_with_stock' => $products_with_stock,
                        'products_without_stock' => $total_products - $products_with_stock
                    ]
                ]);
                
            } catch (Exception $e) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }
        }
        
        /**
         * AJAX endpoint to manually trigger the auto-restock process
         */
        public function ajax_process_ended_rentals() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Unauthorized access', 'mitnafun-order-admin')]);
            }
            
            $restocked = $this->process_ended_rentals();
            
            wp_send_json_success([
                'message' => sprintf(__('%d products were automatically restocked.', 'mitnafun-order-admin'), $restocked),
                'restocked' => $restocked
            ]);
        }
        
        /**
         * AJAX endpoint to update initial stock for a product
         */
        public function ajax_update_initial_stock() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Unauthorized access', 'mitnafun-order-admin')]);
            }
            
            // Get product ID and new initial stock value from request
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $initial_stock = isset($_POST['initial_stock']) ? intval($_POST['initial_stock']) : 0;
            
            if ($product_id <= 0) {
                wp_send_json_error(['message' => __('Invalid product ID', 'mitnafun-order-admin')]);
            }
            
            // Update the initial stock meta
            update_post_meta($product_id, '_initial_stock', $initial_stock);
            
            wp_send_json_success([
                'message' => __('Initial stock updated successfully', 'mitnafun-order-admin'),
                'initial_stock' => $initial_stock
            ]);
        }
        
        /**
         * AJAX handler to get detailed stock information for a product
         * Used by the checkout debug script
         */
        public function ajax_get_product_stock() {
            check_ajax_referer('mitnafun_checkout_nonce', 'nonce');
            
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            
            if (!$product_id) {
                wp_send_json_error(['message' => 'Invalid product ID']);
                return;
            }
            
            $product = wc_get_product($product_id);
            
            if (!$product) {
                wp_send_json_error(['message' => 'Product not found']);
                return;
            }
            
            // Get stock information
            $initial_stock = get_post_meta($product_id, '_initial_stock', true);
            $wc_stock = $product->get_stock_quantity();
            $backorders_allowed = $product->backorders_allowed();
            $stock_status = $product->get_stock_status();
            
            // Get held stock (in cart but not yet purchased)
            $held_stock = 0;
            
            // Check if this is a variable product
            $variation_data = null;
            if ($product->is_type('variable')) {
                $variations = $product->get_available_variations();
                $variation_data = [];
                
                foreach ($variations as $variation) {
                    $variation_obj = wc_get_product($variation['variation_id']);
                    if ($variation_obj) {
                        $variation_data[] = [
                            'id' => $variation['variation_id'],
                            'attributes' => $variation['attributes'],
                            'initial_stock' => get_post_meta($variation['variation_id'], '_initial_stock', true),
                            'wc_stock' => $variation_obj->get_stock_quantity(),
                            'backorders_allowed' => $variation_obj->backorders_allowed(),
                            'stock_status' => $variation_obj->get_stock_status()
                        ];
                    }
                }
            }
            
            // Prepare response
            $response = [
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'product_type' => $product->get_type(),
                'initial_stock' => $initial_stock !== '' ? (int)$initial_stock : (int)$wc_stock, // Fallback to WC stock if initial not set
                'stock_quantity' => $wc_stock !== null ? (int)$wc_stock : 0, // Current available stock
                'wc_stock' => $wc_stock,
                'backorders_allowed' => $backorders_allowed,
                'stock_status' => $stock_status,
                'held_stock' => $held_stock,
                'variations' => $variation_data,
                'is_low_stock' => $wc_stock !== null && $wc_stock <= 2 // Add low stock flag
            ];
            
            wp_send_json_success($response);
        }
        
        /**
         * AJAX endpoint to manually restock a specific product
         */
        public function manual_restock_product() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Unauthorized access', 'mitnafun-order-admin')]);
            }
            
            // Get product ID and quantity from request
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
            
            if (!$product_id) {
                wp_send_json_error(['message' => __('Invalid product ID', 'mitnafun-order-admin')]);
            }
            
            // Get the product
            $product = wc_get_product($product_id);
            if (!$product) {
                wp_send_json_error(['message' => __('Product not found', 'mitnafun-order-admin')]);
            }
            
            // Verify the product manages stock
            if (!$product->managing_stock()) {
                wp_send_json_error(['message' => __('This product does not manage stock', 'mitnafun-order-admin')]);
            }
            
            // Get current stock and increase it
            $current_stock = $product->get_stock_quantity();
            $new_stock = $current_stock + $quantity;
            wc_update_product_stock($product, $new_stock, 'set');
            
            // Add a note to the product
            $product->add_meta_data('_manual_restock_note', sprintf(
                __('Manually restocked by admin on %s. Added %d units.', 'mitnafun-order-admin'),
                date('Y-m-d H:i:s'),
                $quantity
            ), true);
            $product->save();
            
            wp_send_json_success([
                'message' => sprintf(__('Successfully added %d units to stock. New total: %d', 'mitnafun-order-admin'), $quantity, $new_stock),
                'new_stock' => $new_stock
            ]);
        }
        
        /**
         * Process ended rentals to automatically restock products
         * This function is triggered daily by a cron job
         */
        public function process_ended_rentals() {
            global $wpdb;
            
            // Get current date
            $current_date = date('Y-m-d');
            $log_message = "[" . date('Y-m-d H:i:s') . "] Running auto-restock for ended rentals\n";
            
            // Find all orders with rental periods that have ended (end date is in the past)
            $rental_orders_query = $wpdb->prepare("
                SELECT 
                    o.id as order_id,
                    oi.order_item_id,
                    oi.order_item_name as product_name,
                    oim.meta_value as rental_dates,
                    oim2.meta_value as product_id,
                    o.status
                FROM {$wpdb->prefix}wc_orders o
                JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = %s
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = %s
                WHERE o.status IN ('wc-completed', 'wc-processing', 'wc-rental-confirmed')
                AND oim.meta_value IS NOT NULL 
                AND oim.meta_value != ''
                LIMIT 500
            ", 'Rental Dates', '_product_id');
            
            $rentals = $wpdb->get_results($rental_orders_query);
            $log_message .= "Found " . count($rentals) . " rental orders to check\n";
            
            $processed_orders = [];
            $restocked_products = [];
            
            foreach ($rentals as $rental) {
                // Skip if we've already processed this order
                if (in_array($rental->order_id, $processed_orders)) {
                    continue;
                }
                
                // Parse the rental dates
                $dates = explode(' - ', $rental->rental_dates);
                if (count($dates) !== 2) {
                    $log_message .= "Skipping order #{$rental->order_id} - Could not parse rental dates: {$rental->rental_dates}\n";
                    continue;
                }
                
                // Convert DD.MM.YYYY to Y-m-d format for comparison
                $end_date = DateTime::createFromFormat('d.m.Y', $dates[1]);
                if (!$end_date) {
                    $log_message .= "Skipping order #{$rental->order_id} - Invalid end date format: {$dates[1]}\n";
                    continue;
                }
                
                $end_date_str = $end_date->format('Y-m-d');
                
                // If the rental has ended (end date is before or equal to current date)
                if ($end_date_str < $current_date) {
                    $product_id = intval($rental->product_id);
                    
                    // Get the product
                    $product = wc_get_product($product_id);
                    if (!$product) {
                        $log_message .= "Skipping order #{$rental->order_id} - Product #{$product_id} not found\n";
                        continue;
                    }
                    
                    // Check if this product has already been restocked for this order
                    $restock_key = $rental->order_id . '_' . $product_id;
                    if (isset($restocked_products[$restock_key])) {
                        continue;
                    }
                    
                    // Get the quantity ordered
                    $quantity = wc_get_order_item_meta($rental->order_item_id, '_qty', true);
                    $quantity = max(1, intval($quantity)); // Default to 1 if not set
                    
                    // Increase the stock
                    $current_stock = $product->get_stock_quantity();
                    $log_message .= "Order #{$rental->order_id} - Product: {$rental->product_name} (ID: {$product_id}) - End date: {$end_date_str} - Current stock: {$current_stock}\n";
                    
                    // Only update if the product manages stock
                    if ($product->managing_stock()) {
                        $new_stock = $current_stock + $quantity;
                        wc_update_product_stock($product, $new_stock, 'set');
                        
                        // Add note to the order
                        $order = wc_get_order($rental->order_id);
                        if ($order) {
                            $order->add_order_note(
                                sprintf(
                                    __('Auto-restocked %d units of %s as rental period ended on %s.', 'mitnafun-order-admin'),
                                    $quantity,
                                    $rental->product_name,
                                    $dates[1]
                                )
                            );
                        }
                        
                        $restocked_products[$restock_key] = true;
                        $log_message .= "  → Increased stock by {$quantity} to new total of {$new_stock}\n";
                    } else {
                        $log_message .= "  → Product does not manage stock, skipping\n";
                    }
                }
            }
            
            // Log the restock process
            error_log($log_message);
            
            return count($restocked_products);
        }
        
        public function get_calendar_events() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => 'Unauthorized access']);
            }
            
            try {
                global $wpdb;
                
                // Log for debugging
                error_log('Calendar events query started');
                
                // Get date range from request (start and end dates)
                $start = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : '';
                $end = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : '';
                
                // More detailed query to get rental dates - try with a more direct approach
                $query = "
                    SELECT 
                        p.ID as order_id,
                        p.post_status as status,
                        oi.order_item_name as product_name,
                        oim.meta_value as rental_dates,
                        oim2.meta_value as product_id
                    FROM {$wpdb->posts} p
                    JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
                    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = 'Rental Dates'
                    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_product_id'
                    WHERE p.post_type = 'shop_order'
                    AND p.post_status NOT IN ('trash', 'auto-draft', 'wc-cancelled', 'wc-refunded')
                    AND oim.meta_value IS NOT NULL 
                    AND oim.meta_value != ''
                    LIMIT 100
                ";
                
                // Log the query for debugging
                error_log('Calendar events query: ' . $query);
                
                $results = $wpdb->get_results($query);
                error_log('Calendar events query results count: ' . count($results));
                
                $events = [];
                
                foreach ($results as $result) {
                    error_log('Processing rental date: ' . print_r($result->rental_dates, true));
                    
                    if (empty($result->rental_dates)) {
                        continue;
                    }
                    
                    // Try to decode as JSON first
                    $rental_dates = json_decode($result->rental_dates, true);
                    
                    // If not JSON, try to parse as serialized data
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $rental_dates = maybe_unserialize($result->rental_dates);
                    }
                    
                    // If still not an array, try to parse as a simple date range string
                    if (!is_array($rental_dates)) {
                        // Try to parse a simple format like "2025-05-01 to 2025-05-05"
                        if (preg_match('/(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})/', $result->rental_dates, $matches)) {
                            $rental_dates = [
                                'start' => $matches[1],
                                'end' => $matches[2]
                            ];
                        } else {
                            error_log('Could not parse rental dates: ' . $result->rental_dates);
                            continue;
                        }
                    }
                    
                    // Validate we have start and end dates
                    if (!isset($rental_dates['start']) || !isset($rental_dates['end'])) {
                        error_log('Missing start or end date in rental dates: ' . print_r($rental_dates, true));
                        continue;
                    }
                    
                    // Format the dates for FullCalendar
                    $start_date = date('Y-m-d', strtotime($rental_dates['start']));
                    $end_date = date('Y-m-d', strtotime($rental_dates['end'] . ' +1 day')); // Add a day to make it inclusive
                    
                    // Status color mapping
                    $status_colors = [
                        'wc-processing' => '#FF9800', // Orange
                        'wc-completed' => '#4CAF50',  // Green
                        'wc-on-hold' => '#2196F3',    // Blue
                        'wc-rental-confirmed' => '#9C27B0', // Purple
                        'wc-rental-completed' => '#4CAF50', // Green
                        'processing' => '#FF9800',
                        'completed' => '#4CAF50',
                        'on-hold' => '#2196F3'
                    ];
                    
                    // Default color
                    $color = isset($status_colors[$result->status]) ? $status_colors[$result->status] : '#607D8B'; // Gray default
                    
                    // Create the event
                    $events[] = [
                        'id' => 'order_' . $result->order_id . '_product_' . $result->product_id,
                        'title' => $result->product_name,
                        'start' => $start_date,
                        'end' => $end_date,
                        'backgroundColor' => $color,
                        'borderColor' => $color,
                        'extendedProps' => [
                            'order_id' => $result->order_id,
                            'product_id' => $result->product_id,
                            'status' => $this->format_status($result->status)
                        ],
                        'allDay' => true
                    ];
                    
                    error_log('Added calendar event: ' . json_encode($events[count($events)-1]));
                }
                
                error_log('Total calendar events: ' . count($events));
                wp_send_json_success($events);
                
            } catch (Exception $e) {
                error_log('Calendar events error: ' . $e->getMessage());
                wp_send_json_error([
                    'message' => 'Error fetching calendar events: ' . $e->getMessage(),
                    'trace' => defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : ''
                ]);
            }
            
            wp_die();
        }

        public function admin_page() {
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                return;
            }

            // Database Information
            global $wpdb;
            $table_prefix = $wpdb->prefix;
            $orders_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}wc_orders");
            $products_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}posts WHERE post_type = 'product' AND post_status = 'publish'");
            $users_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}users");
            
            // Get rental dates count
            $rental_dates_query = "
                SELECT COUNT(*) 
                FROM {$table_prefix}woocommerce_order_itemmeta 
                WHERE meta_key = '_rental_dates'
            ";
            $rental_dates_count = $wpdb->get_var($rental_dates_query);
            ?>
            <div class="wrap mitnafun-admin-wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

                <!-- Database Information -->
                <div class="mitnafun-admin-section mitnafun-db-info">
                    <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this info</span></button>
                    <h2><?php _e('Database Information', 'mitnafun-order-admin'); ?></h2>
                    <div class="mitnafun-info-grid">
                        <div class="mitnafun-info-box">
                            <span class="mitnafun-info-count"><?php echo $orders_count; ?></span>
                            <span class="mitnafun-info-label"><?php _e('WooCommerce Orders', 'mitnafun-order-admin'); ?></span>
                        </div>
                        <div class="mitnafun-info-box">
                            <span class="mitnafun-info-count"><?php echo $rental_dates_count; ?></span>
                            <span class="mitnafun-info-label"><?php _e('Orders with Rental Dates', 'mitnafun-order-admin'); ?></span>
                        </div>
                        <div class="mitnafun-info-box">
                            <span class="mitnafun-info-count"><?php echo $products_count; ?></span>
                            <span class="mitnafun-info-label"><?php _e('Products', 'mitnafun-order-admin'); ?></span>
                        </div>
                        <div class="mitnafun-info-box">
                            <span class="mitnafun-info-count"><?php echo $users_count; ?></span>
                            <span class="mitnafun-info-label"><?php _e('Users', 'mitnafun-order-admin'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Main Admin Content -->
                <div id="mitnafun-tabs" class="mitnafun-admin-content">
                    <ul>
                        <li><a href="#mitnafun-tab-calendar"><?php _e('Booking Calendar', 'mitnafun-order-admin'); ?></a></li>
                        <li><a href="#mitnafun-tab-stock"><?php _e('Stock Monitor', 'mitnafun-order-admin'); ?></a></li>
                        <li><a href="#mitnafun-tab-orders"><?php _e('Recent Orders', 'mitnafun-order-admin'); ?></a></li>
                        <li><a href="#mitnafun-tab-clients"><?php _e('Client Summary', 'mitnafun-order-admin'); ?></a></li>
                        <li><a href="#mitnafun-tab-products"><?php _e('Product Rental Summary', 'mitnafun-order-admin'); ?></a></li>
                    </ul>
                    <div id="mitnafun-tab-orders" class="mitnafun-admin-section">
                        <h2><?php _e('Recent Orders', 'mitnafun-order-admin'); ?></h2>
                        
                        <!-- Filters -->
                        <div class="mitnafun-filters">
                            <form id="mitnafun-filter-form" class="mitnafun-filter-form">
                                <div class="mitnafun-filter-group">
                                    <label for="client-filter"><?php _e('Client:', 'mitnafun-order-admin'); ?></label>
                                    <input type="text" id="client-filter" name="client" placeholder="<?php _e('Name or Email', 'mitnafun-order-admin'); ?>">
                                </div>
                                
                                <div class="mitnafun-filter-group">
                                    <label for="product-filter"><?php _e('Product:', 'mitnafun-order-admin'); ?></label>
                                    <select id="product-filter" name="product">
                                        <option value=""><?php _e('All Products', 'mitnafun-order-admin'); ?></option>
                                        <?php
                                        $products = $wpdb->get_results("
                                            SELECT ID, post_title 
                                            FROM {$wpdb->prefix}posts 
                                            WHERE post_type = 'product' 
                                            AND post_status = 'publish'
                                            ORDER BY post_title ASC
                                        ");
                                        
                                        foreach ($products as $product) {
                                            echo '<option value="' . esc_attr($product->ID) . '">' . esc_html($product->post_title) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="mitnafun-filter-group">
                                    <label for="status-filter"><?php _e('Status:', 'mitnafun-order-admin'); ?></label>
                                    <select id="status-filter" name="status">
                                        <option value=""><?php _e('All Statuses', 'mitnafun-order-admin'); ?></option>
                                        <option value="wc-processing"><?php _e('Processing', 'mitnafun-order-admin'); ?></option>
                                        <option value="wc-completed"><?php _e('Completed', 'mitnafun-order-admin'); ?></option>
                                        <option value="wc-rental-confirmed"><?php _e('Rental Confirmed', 'mitnafun-order-admin'); ?></option>
                                        <option value="wc-rental-completed"><?php _e('Rental Completed', 'mitnafun-order-admin'); ?></option>
                                        <option value="wc-rental-cancelled"><?php _e('Rental Cancelled', 'mitnafun-order-admin'); ?></option>
                                        <option value="wc-on-hold"><?php _e('On Hold', 'mitnafun-order-admin'); ?></option>
                                        <option value="wc-cancelled"><?php _e('Cancelled', 'mitnafun-order-admin'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="mitnafun-filter-group mitnafun-date-range">
                                    <label><?php _e('Date Range:', 'mitnafun-order-admin'); ?></label>
                                    <div class="mitnafun-date-inputs">
                                        <input type="text" id="filter-date-from" name="date_from" class="mitnafun-datepicker" placeholder="<?php _e('From', 'mitnafun-order-admin'); ?>">
                                        <span class="mitnafun-date-separator">-</span>
                                        <input type="text" id="filter-date-to" name="date_to" class="mitnafun-datepicker" placeholder="<?php _e('To', 'mitnafun-order-admin'); ?>">
                                    </div>
                                </div>
                                
                                <div class="mitnafun-filter-buttons">
                                    <button type="submit" class="button button-primary"><?php _e('Apply Filters', 'mitnafun-order-admin'); ?></button>
                                    <button type="button" id="mitnafun-filter-reset" class="button"><?php _e('Reset', 'mitnafun-order-admin'); ?></button>
                                </div>
                            </form>
                        </div>

                        <!-- Orders Table -->
                        <div class="mitnafun-table-container" id="orders">
                            <table class="widefat mitnafun-table" id="orders-list">
                                <thead>
                                    <tr>
                                        <th><?php _e('Order', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Date', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Client', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Products', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Rental Dates', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Status', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Total', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Actions', 'mitnafun-order-admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="8" class="mitnafun-loading"><?php _e('Loading orders...', 'mitnafun-order-admin'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div id="mitnafun-tab-clients" class="mitnafun-admin-section">
                        <h2><?php _e('Client Summary', 'mitnafun-order-admin'); ?></h2>
                        <div class="mitnafun-table-container" id="clients">
                            <table class="widefat mitnafun-table" id="clients-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('Client', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Contact', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Orders', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Total Spent', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('History', 'mitnafun-order-admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="mitnafun-loading"><?php _e('Loading clients...', 'mitnafun-order-admin'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div id="mitnafun-tab-products" class="mitnafun-admin-section">
                        <h2><?php _e('Product Rental Summary', 'mitnafun-order-admin'); ?></h2>
                        <div class="mitnafun-table-container" id="products">
                            <table class="widefat mitnafun-table" id="products-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('Product', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Total Rentals', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Rental History', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Actions', 'mitnafun-order-admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="4" class="mitnafun-loading"><?php _e('Loading products...', 'mitnafun-order-admin'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div id="mitnafun-tab-calendar" class="mitnafun-admin-section">
                        <h2><?php _e('Booking Calendar', 'mitnafun-order-admin'); ?></h2>
                        <div id="mitnafun-calendar"></div>
                    </div>
                    
                    <div id="mitnafun-tab-stock" class="mitnafun-admin-section">
                        <h2>
                            <?php _e('Stock Monitor', 'mitnafun-order-admin'); ?>
                            <span class="product-count-badge"><?php echo sprintf(__('(%d products)', 'mitnafun-order-admin'), $products_count); ?></span>
                        </h2>
                        
                        <div class="mitnafun-filters">
                            <form id="mitnafun-stock-filter-form" class="mitnafun-filter-form">
                                <div class="mitnafun-filter-group">
                                    <label for="stock-product-filter"><?php _e('Product:', 'mitnafun-order-admin'); ?></label>
                                    <select id="stock-product-filter" name="product">
                                        <option value=""><?php _e('All Products', 'mitnafun-order-admin'); ?></option>
                                        <?php
                                        $stock_products = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->prefix}posts WHERE post_type = 'product' AND post_status = 'publish' ORDER BY post_title ASC");
                                        foreach ($stock_products as $product) {
                                            echo '<option value="' . esc_attr($product->ID) . '">' . esc_html($product->post_title) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="mitnafun-filter-group">
                                    <label for="stock-status-filter"><?php _e('Stock Status:', 'mitnafun-order-admin'); ?></label>
                                    <select id="stock-status-filter" name="stock_status">
                                        <option value=""><?php _e('All Status', 'mitnafun-order-admin'); ?></option>
                                        <option value="in_stock"><?php _e('In Stock', 'mitnafun-order-admin'); ?></option>
                                        <option value="low_stock"><?php _e('Low Stock', 'mitnafun-order-admin'); ?></option>
                                        <option value="out_of_stock"><?php _e('Out of Stock', 'mitnafun-order-admin'); ?></option>
                                        <option value="overbooked"><?php _e('Overbooked', 'mitnafun-order-admin'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="mitnafun-filter-buttons">
                                    <button type="submit" class="button button-primary"><?php _e('Apply Filters', 'mitnafun-order-admin'); ?></button>
                                    <button type="button" id="mitnafun-stock-filter-reset" class="button"><?php _e('Reset', 'mitnafun-order-admin'); ?></button>
                                    <button type="button" id="mitnafun-run-restock" class="button button-secondary"><?php _e('Run Auto-Restock Now', 'mitnafun-order-admin'); ?></button>
                                    <button type="button" id="mitnafun-init-stock" class="button button-secondary"><?php _e('Initialize Stock Values', 'mitnafun-order-admin'); ?></button>
                                    <button type="button" id="mitnafun-sync-to-woo" class="button button-secondary"><?php _e('Sync Total → WooCommerce', 'mitnafun-order-admin'); ?></button>
                                    <button type="button" id="mitnafun-release-stock-issues" class="button button-secondary"><?php _e('Release Stock Issues', 'mitnafun-order-admin'); ?></button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="mitnafun-table-container" id="stock-monitor">
                            <table class="widefat mitnafun-table" id="stock-list">
                                <thead>
                                    <tr>
                                        <th><?php _e('Product', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Initial Stock', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Total Stock', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Currently Booked', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Available Units', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Status', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Current Bookings', 'mitnafun-order-admin'); ?></th>
                                        <th><?php _e('Actions', 'mitnafun-order-admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="mitnafun-loading"><?php _e('Loading stock data...', 'mitnafun-order-admin'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
            <?php
        }

        public function register_assets() {
            // Admin styles
            wp_register_style(
                'mitnafun-order-admin-style',
                plugins_url('css/admin.css', __FILE__),
                array(),
                filemtime(plugin_dir_path(__FILE__) . 'css/admin.css')
            );
            
            // Admin scripts
            wp_register_script(
                'mitnafun-order-admin-script',
                plugins_url('js/admin.js', __FILE__),
                array('jquery', 'jquery-ui-datepicker', 'jquery-ui-tabs', 'fullcalendar'),
                filemtime(plugin_dir_path(__FILE__) . 'js/admin.js'),
                true
            );
            
            // Frontend scripts
            if (!is_admin()) {
                wp_register_script(
                    'mitnafun-frontend-script',
                    plugins_url('js/frontend.js', __FILE__),
                    array('jquery'),
                    filemtime(plugin_dir_path(__FILE__) . 'js/frontend.js'),
                    true
                );
                
                // Frontend styles
                wp_register_style(
                    'mitnafun-frontend-style',
                    plugins_url('css/frontend.css', __FILE__),
                    array(),
                    filemtime(plugin_dir_path(__FILE__) . 'css/frontend.css')
                );
            }
        }

        /**
         * AJAX endpoint to release stock issues by enabling stock management for products
         */
        public function ajax_release_stock_issues() {
            check_ajax_referer('mitnafun_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'mitnafun-order-admin')]);
                return;
            }
            
            global $wpdb;
            
            // Find products with stock management disabled
            $products_without_stock_management = $wpdb->get_results("
                SELECT p.ID, p.post_title 
                FROM {$wpdb->prefix}posts p
                LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_manage_stock'
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish'
                AND (pm.meta_value = 'no' OR pm.meta_value IS NULL)
            ");
            
            $updated_count = 0;
            
            // Update each product to enable stock management
            foreach ($products_without_stock_management as $product_data) {
                $product_id = $product_data->ID;
                $product = wc_get_product($product_id);
                
                if ($product) {
                    // Enable stock management
                    update_post_meta($product_id, '_manage_stock', 'yes');
                    
                    // If there's no initial stock set, set it to 1 as a default
                    $initial_stock = get_post_meta($product_id, '_initial_stock', true);
                    if (empty($initial_stock)) {
                        update_post_meta($product_id, '_initial_stock', '1');
                    }
                    
                    // Set stock status to 'instock'
                    update_post_meta($product_id, '_stock_status', 'instock');
                    
                    $updated_count++;
                }
            }
            
            wp_send_json_success([
                'message' => sprintf(__('Released stock issues for %d products. Stock management enabled.', 'mitnafun-order-admin'), $updated_count),
                'updated_count' => $updated_count
            ]);
        }
        

        public function enqueue_assets() {
        // Frontend assets
        if (!is_admin()) {
            // Register and enqueue frontend styles
            wp_register_style(
                'mitnafun-frontend-style',
                plugins_url('assets/css/frontend.css', __FILE__),
                array(),
                filemtime(plugin_dir_path(__FILE__) . 'assets/css/frontend.css')
            );
            
            // Register and enqueue frontend scripts
            wp_register_script(
                'mitnafun-frontend-script',
                plugins_url('assets/js/frontend.js', __FILE__),
                array('jquery', 'jquery-ui-datepicker'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/js/frontend.js'),
                true
            );
            
            // Get product ID if on product page
            $product_data = array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mitnafun_frontend_nonce'),
                'isMobile' => wp_is_mobile(),
                'reservedDates' => array(),
                'initialStock' => 0,
                'currentStock' => 0
            );
            
            // If we're on a product page, add product-specific data
            if (is_product()) {
                global $product;
                if ($product && is_a($product, 'WC_Product')) {
                    $product_id = $product->get_id();
                    $initial_stock = (int) get_post_meta($product_id, '_initial_stock', true);
                    $current_stock = $product->get_stock_quantity();
                    $reserved_dates = $this->get_reserved_dates($product_id);
                    
                    $product_data['productId'] = $product_id;
                    $product_data['initialStock'] = $initial_stock;
                    $product_data['currentStock'] = $current_stock;
                    $product_data['reservedDates'] = $reserved_dates;
                }
            }
            
            // Enqueue scripts and styles
            wp_enqueue_style('mitnafun-frontend-style');
            wp_enqueue_script('mitnafun-frontend-script');
            
            // Localize script with product data
            wp_localize_script('mitnafun-frontend-script', 'mitnafunFrontend', $product_data);
        }
        
        // Admin page
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'mitnafun-order-admin') {
                // Register and enqueue admin styles
                wp_register_style(
                    'mitnafun-order-admin-style',
                    plugins_url('css/admin.css', __FILE__),
                    array(),
                    filemtime(plugin_dir_path(__FILE__) . 'css/admin.css')
                );
                
                // Register and enqueue admin scripts
                wp_register_script(
                    'mitnafun-order-admin-script',
                    plugins_url('js/admin.js', __FILE__),
                    array('jquery', 'jquery-ui-datepicker'),
                    filemtime(plugin_dir_path(__FILE__) . 'js/admin.js'),
                    true
                );
                
                wp_enqueue_style('mitnafun-order-admin-style');
                wp_enqueue_script('mitnafun-order-admin-script');
                
                // Enqueue WordPress media scripts for media uploader
                wp_enqueue_media();
                
                // Pass data to admin script
                wp_localize_script('mitnafun-order-admin-script', 'mitnafunAdmin', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mitnafun_admin_nonce'),
                    'admin_url' => admin_url(),
                    'version' => filemtime(plugin_dir_path(__FILE__) . 'js/admin.js'),
                    'i18n' => array(
                        'confirmDelete' => __('Are you sure you want to delete this item?', 'mitnafun-order-admin'),
                        'error' => __('An error occurred. Please try again.', 'mitnafun-order-admin'),
                        'success' => __('Operation completed successfully.', 'mitnafun-order-admin')
                    )
                ));
                
                // Add inline styles if needed
                wp_add_inline_style('mitnafun-order-admin-style', '
                    .mitnafun-admin-wrap { margin: 15px 0 0 0; }
                    .mitnafun-admin-wrap .nav-tab { margin: 0 5px 0 0; }
                    .mitnafun-admin-wrap .tab-content { display: none; }
                    .mitnafun-admin-wrap .tab-content.active { display: block; }
                ');
            }
            
            // Frontend - only on product pages if WooCommerce is active
            if (!is_admin() && function_exists('is_product') && is_product()) {
                // Register frontend styles
                wp_register_style(
                    'mitnafun-frontend-style',
                    plugins_url('css/frontend.css', __FILE__),
                    array(),
                    filemtime(plugin_dir_path(__FILE__) . 'css/frontend.css')
                );
                
                // Register frontend scripts
                wp_register_script(
                    'mitnafun-frontend-script',
                    plugins_url('js/frontend.js', __FILE__),
                    array('jquery'),
                    filemtime(plugin_dir_path(__FILE__) . 'js/frontend.js'),
                    true
                );
                
                wp_enqueue_style('mitnafun-frontend-style');
                wp_enqueue_script('mitnafun-frontend-script');
                
                // Get product ID if on product page
                $product_data = array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mitnafun_frontend_nonce'),
                    'isMobile' => wp_is_mobile(),
                    'reservedDates' => array(),
                    'initialStock' => 0,
                    'currentStock' => 0
                );
                
                // If we're on a product page, add product-specific data
                if (is_product()) {
                    global $product;
                    if ($product && is_a($product, 'WC_Product')) {
                        $product_id = $product->get_id();
                        $initial_stock = (int) get_post_meta($product_id, '_initial_stock', true);
                        $current_stock = $product->get_stock_quantity();
                        $reserved_dates = $this->get_reserved_dates($product_id);
                        
                        $product_data['productId'] = $product_id;
                        $product_data['initialStock'] = $initial_stock;
                        $product_data['currentStock'] = $current_stock;
                        $product_data['reservedDates'] = $reserved_dates;
                    }
                }
            }
        }
    }

/**
 * Display notice if WooCommerce is not active
 *
 * @return void
 */
function mitnafun_order_admin_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('Mitnafun Order Admin requires WooCommerce to be installed and active.', 'mitnafun-order-admin'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function mitnafun_order_admin_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'mitnafun_order_admin_woocommerce_missing_notice');
        return;
    }

    $mitnafun_order_admin = MitnafunOrderAdmin::get_instance();
    
    // Add stock debug info to product page for admins
    if (is_admin() || current_user_can('manage_woocommerce')) {
        add_action('woocommerce_before_add_to_cart_form', array($mitnafun_order_admin, 'add_stock_debug_info'));
    }
}

/**
 * Plugin activation.
 *
 * @return void
 */
function mitnafun_order_admin_activate() {
    // Set a flag to indicate the plugin is active
    update_option('mitnafun_order_admin_active', 'yes');
    
    // Schedule any necessary cron jobs
    if (!wp_next_scheduled('mitnafun_daily_rental_check')) {
        wp_schedule_event(time(), 'daily', 'mitnafun_daily_rental_check');
    }
}

/**
 * Plugin deactivation.
 *
 * @return void
 */
function mitnafun_order_admin_deactivate() {
    // Clear any scheduled events
    wp_clear_scheduled_hook('mitnafun_daily_rental_check');
    
    // Clear any transients or options if needed
    delete_option('mitnafun_order_admin_active');
}

/**
 * Daily rental check action.
 *
 * @return void
 */
function mitnafun_daily_rental_check() {
    if (class_exists('MitnafunOrderAdmin')) {
        MitnafunOrderAdmin::process_ended_rentals();
    }
}

/**
 * Admin notice for stock initialization.
 *
 * @return void
 */
function mitnafun_order_admin_notice() {
    if (!get_option('mitnafun_initial_stock_initialized')) {
        ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('Mitnafun Order Admin: Initial stock values need to be initialized.', 'mitnafun-order-admin'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mitnafun-order-admin&init_stock=1')); ?>" class="button button-small">
                    <?php esc_html_e('Initialize Now', 'mitnafun-order-admin'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'mitnafun_order_admin_activate');
register_deactivation_hook(__FILE__, 'mitnafun_order_admin_deactivate');

// Hook initialization
add_action('plugins_loaded', 'mitnafun_order_admin_init', 20);
add_action('mitnafun_daily_rental_check', 'mitnafun_daily_rental_check');
add_action('admin_notices', 'mitnafun_order_admin_notice');
