<?php
/**
 * Plugin Name: Mitnafun Order Admin
 * Description: Custom order management and cart fixer for Mitnafun Rental System
 * Version: 1.1.0
 * Author: Aviv Digital
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WC_Cart_Debug_Helper {
    private static $instance = null;

    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Log and fix add to cart requests
        add_action('wp_ajax_woocommerce_add_to_cart', array($this, 'log_add_to_cart'), 5);
        add_action('wp_ajax_nopriv_woocommerce_add_to_cart', array($this, 'log_add_to_cart'), 5);
        
        // Log cart validation
        add_filter('woocommerce_add_to_cart_validation', array($this, 'log_cart_validation'), 10, 5);
        
        // Log and fix cart fragments updates
        add_filter('woocommerce_add_to_cart_fragments', array($this, 'log_cart_fragments'), 9999);
        
        // Add debug info to the page
        add_action('wp_footer', array($this, 'output_debug_info'), 9999);
        
        // Fix cart sessions
        add_action('woocommerce_init', array($this, 'fix_cart_sessions'));
        add_action('woocommerce_cart_loaded_from_session', array($this, 'check_cart_contents'));
        
        // Handle AJAX add to cart specifically for our implementation
        add_action('wp_ajax_woocommerce_ajax_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_woocommerce_ajax_add_to_cart', array($this, 'ajax_add_to_cart'));
        
        // Ensure cart is not emptied incorrectly
        add_action('woocommerce_cart_emptied', array($this, 'check_cart_emptied'), 10, 1);
    }

    public function enqueue_scripts() {
        if (!is_admin()) {
            // Enqueue the debug script
            wp_enqueue_script(
                'wc-cart-debug',
                plugins_url('assets/js/cart-debug.js', __FILE__),
                array('jquery'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/js/cart-debug.js'),
                true
            );
            
            // Enqueue the add to cart handler
            wp_enqueue_script(
                'wc-add-to-cart-handler',
                plugins_url('assets/js/add-to-cart-handler.js', __FILE__),
                array('jquery', 'wc-add-to-cart'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/js/add-to-cart-handler.js'),
                true
            );
            
            // Enqueue the styles
            wp_enqueue_style(
                'wc-add-to-cart-styles',
                plugins_url('assets/css/add-to-cart-messages.css', __FILE__),
                array(),
                filemtime(plugin_dir_path(__FILE__) . 'assets/css/add-to-cart-messages.css')
            );
            
            // Localize script with debug data
            wp_localize_script('wc-cart-debug', 'wcCartDebug', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'debug' => true
            ));
            
            // Localize script with WooCommerce parameters
            wp_localize_script('wc-add-to-cart-handler', 'wc_add_to_cart_params', array(
                'ajax_url' => WC()->ajax_url(),
                'wc_ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
                'i18n_view_cart' => esc_attr__('View cart', 'woocommerce'),
                'cart_url' => apply_filters('woocommerce_add_to_cart_redirect', wc_get_cart_url(), null),
                'is_cart' => is_cart(),
                'cart_redirect_after_add' => 'no' // Force disable redirect after add to cart
            ));
        }
    }
    
    public function log_add_to_cart() {
        $this->log('Add to cart request: ' . print_r($_REQUEST, true));
    }
    
    /**
     * Handle our custom AJAX add to cart request
     * This fixes issues with the standard WooCommerce AJAX
     */
    public function ajax_add_to_cart() {
        ob_start();
        
        $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
        $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount($_POST['quantity']);
        $variation_id = absint($_POST['variation_id']);
        $variations = array();
        $passed_validation = true;
        
        // Check if we should redirect to checkout after adding to cart
        $redirect_to_checkout = isset($_POST['redirect_to_checkout']) && ($_POST['redirect_to_checkout'] === 'true' || $_POST['redirect_to_checkout'] === true || $_POST['redirect_to_checkout'] == 1);
        
        // Get rental dates if they exist
        if (!empty($_POST['rental_start_date']) && !empty($_POST['rental_end_date'])) {
            $rental_start_date = sanitize_text_field($_POST['rental_start_date']);
            $rental_end_date = sanitize_text_field($_POST['rental_end_date']);
            
            // Add as cart item data
            $cart_item_data = array(
                'rental_start_date' => $rental_start_date,
                'rental_end_date' => $rental_end_date
            );
        } else {
            $cart_item_data = array();
        }
        
        // Build variations array
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'attribute_') === 0) {
                $variations[$key] = $value;
            }
        }
        
        // Add custom data that should be preserved
        $cart_item_data = apply_filters('woocommerce_add_cart_item_data', $cart_item_data, $product_id, $variation_id, $quantity);
        
        // Add to cart validation
        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations);
        
        if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations, $cart_item_data)) {
            do_action('woocommerce_ajax_added_to_cart', $product_id);
            
            // Get mini cart
            $fragments = $this->get_refreshed_fragments();
            
            // Response
            $data = array(
                'success' => true,
                'fragments' => $fragments,
                'cart_hash' => WC()->cart->get_cart_contents_count(),
                'message' => __('Product added to cart successfully', 'woocommerce')
            );
            
            // If redirect to checkout is requested
            if ($redirect_to_checkout) {
                // Get the checkout URL
                $checkout_url = wc_get_checkout_url();
                
                // Add to response
                $data['redirect_url'] = $checkout_url;
                
                $this->log('Adding to cart with redirect to checkout: ' . $checkout_url);
            }
            
            // Log success
            $this->log('AJAX add to cart success: ' . print_r($data, true));
        } else {
            // Log failure
            $this->log('AJAX add to cart failed for product ' . $product_id);
            
            $data = array(
                'error' => true,
                'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id),
                'message' => __('Failed to add product to cart', 'woocommerce')
            );
        }
        
        wp_send_json($data);
        die();
    }
    
    /**
     * Get refreshed fragments for cart updates
     */
    private function get_refreshed_fragments() {
        // Get mini cart
        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();

        // Fragments and mini cart are returned
        $fragments = array(
            'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>'
        );
        
        return apply_filters('woocommerce_add_to_cart_fragments', $fragments);
    }
    
    public function log_cart_validation($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
        $this->log(sprintf(
            'Cart validation - Product ID: %d, Variation ID: %d, Quantity: %d, Passed: %s',
            $product_id,
            $variation_id,
            $quantity,
            $passed ? 'Yes' : 'No'
        ));
        
        if (isset($_POST['rental_start_date']) || isset($_POST['rental_end_date'])) {
            $this->log('Rental dates - Start: ' . ($_POST['rental_start_date'] ?? 'not set') . 
                      ', End: ' . ($_POST['rental_end_date'] ?? 'not set'));
        }
        
        return $passed;
    }
    
    public function log_cart_fragments($fragments) {
        $this->log('Cart fragments updated');
        $this->log('Cart contents: ' . print_r(WC()->cart->get_cart_contents(), true));
        
        // Make sure the fragments are properly formatted
        if (empty($fragments) || !is_array($fragments)) {
            $this->log('Empty or invalid fragments - fixing');
            $fragments = $this->get_refreshed_fragments();
        }
        
        return $fragments;
    }
    
    /**
     * Fix cart sessions to prevent empty cart issues
     */
    public function fix_cart_sessions() {
        if (is_admin()) {
            return;
        }

        // Force cart to load if not loaded
        if (!WC()->cart || !did_action('woocommerce_cart_loaded_from_session')) {
            $this->log('Cart not properly loaded - triggering load from session');
            WC()->cart->get_cart();
        }
        
        // Check if cart is empty but shouldn't be
        if (WC()->session && WC()->session->get('cart', null) && WC()->cart->is_empty()) {
            $this->log('Cart is empty but session has items - restoring');
            $cart = WC()->session->get('cart');
            if (!empty($cart)) {
                foreach ($cart as $cart_item_key => $cart_item) {
                    if (isset($cart_item['product_id'])) {
                        WC()->cart->restore_cart_item($cart_item_key);
                    }
                }
            }
        }
    }
    
    /**
     * Check cart contents after loading from session
     */
    public function check_cart_contents() {
        if (WC()->cart && WC()->cart->is_empty() && WC()->session && WC()->session->get('cart')) {
            $this->log('Empty cart detected after session load - attempting recovery');
            // Set a cookie to track cart recovery attempts
            if (!isset($_COOKIE['cart_recovery_attempt'])) {
                setcookie('cart_recovery_attempt', '1', time() + 3600, '/');
                $this->fix_cart_sessions();
            }
        }
    }
    
    /**
     * Check when cart is emptied to prevent accidental emptying
     */
    public function check_cart_emptied($clear_persistent_cart) {
        // Check if cart should actually be emptied or if this is an error
        if (!is_checkout() && !isset($_REQUEST['empty_cart']) && !isset($_POST['clear_cart'])) {
            $this->log('Cart emptied unexpectedly - this may be an error');
            
            // If this is a suspicious cart emptying, try to restore from session
            if (WC()->session && WC()->session->get('saved_cart')) {
                $this->log('Attempting to restore cart from saved_cart session');
                WC()->cart->set_cart_contents(WC()->session->get('saved_cart'));
            }
        } else {
            $this->log('Cart emptied normally');
        }
    }
    
    public function output_debug_info() {
        // Save cart to session for recovery on unexpected emptying
        if (WC()->cart && WC()->session && !WC()->cart->is_empty()) {
            WC()->session->set('saved_cart', WC()->cart->get_cart_contents());
        }
        
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        echo '<div id="cart-debug-info" style="position:fixed;bottom:0;left:0;right:0;background:#fff;z-index:9999;padding:10px;border-top:2px solid #ccc;max-height:200px;overflow:auto;">';
        echo '<h3>Cart Debug Info</h3>';
        
        if (WC()->cart) {
            echo '<div><strong>Cart Contents:</strong> ' . print_r(WC()->cart->get_cart_contents(), true) . '</div>';
            echo '<div><strong>Cart Total:</strong> ' . WC()->cart->get_cart_total() . '</div>';
            echo '<div><strong>Cart is Empty:</strong> ' . (WC()->cart->is_empty() ? 'Yes' : 'No') . '</div>';
        } else {
            echo '<div>Cart not initialized</div>';
        }
        
        if (!empty($_POST)) {
            echo '<div><strong>Last POST data:</strong> ' . print_r(array_map('esc_html', $_POST), true) . '</div>';
        }
        
        echo '</div>';
    }
    
    private function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }
}

// Initialize the plugin
function init_wc_cart_debug_helper() {
    if (class_exists('WooCommerce')) {
        WC_Cart_Debug_Helper::get_instance();
    }
}
add_action('plugins_loaded', 'init_wc_cart_debug_helper');
