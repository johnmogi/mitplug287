<?php
/**
 * Cart Debugger for WooCommerce
 * Helps debug add to cart issues
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Cart_Debugger {
    public function __construct() {
        // Log add to cart requests
        add_action('wp_ajax_woocommerce_add_to_cart', array($this, 'log_add_to_cart'), 1);
        add_action('wp_ajax_nopriv_woocommerce_add_to_cart', array($this, 'log_add_to_cart'), 1);
        
        // Log cart fragments updates
        add_filter('woocommerce_add_to_cart_fragments', array($this, 'log_cart_fragments'), 9999);
        
        // Log cart validation
        add_filter('woocommerce_add_to_cart_validation', array($this, 'log_cart_validation'), 10, 5);
        
        // Add debug info to the page
        add_action('wp_footer', array($this, 'output_debug_info'), 9999);
    }
    
    public function log_add_to_cart() {
        $this->log('Add to cart request: ' . print_r($_REQUEST, true));
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
        return $fragments;
    }
    
    public function output_debug_info() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        echo '<div id="cart-debug-info" style="position:fixed;bottom:0;left:0;right:0;background:#fff;z-index:9999;padding:10px;border-top:2px solid #ccc;max-height:200px;overflow:auto;">';
        echo '<h3>Cart Debug Info</h3>';
        echo '<div><strong>Cart Contents:</strong> ' . print_r(WC()->cart->get_cart_contents(), true) . '</div>';
        echo '<div><strong>Cart Total:</strong> ' . WC()->cart->get_cart_total() . '</div>';
        echo '<div><strong>Cart is Empty:</strong> ' . (WC()->cart->is_empty() ? 'Yes' : 'No') . '</div>';
        
        if (!empty($_POST)) {
            echo '<div><strong>Last POST data:</strong> ' . print_r($_POST, true) . '</div>';
        }
        
        echo '</div>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Log form submissions
            $('form.cart').on('submit', function(e) {
                console.log('Form submitted', {
                    formData: $(this).serialize(),
                    startDate: $('#rental_start_date').val(),
                    endDate: $('#rental_end_date').val(),
                    quantity: $('input.qty').val()
                });
            });
            
            // Log AJAX responses
            $(document).ajaxComplete(function(event, xhr, settings) {
                if (settings.url.includes('wc-ajax=add_to_cart')) {
                    console.log('Add to cart response:', {
                        status: xhr.status,
                        response: xhr.responseJSON,
                        settings: settings
                    });
                }
            });
        });
        </script>
        <?php
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

// Initialize the debugger
function init_cart_debugger() {
    if (class_exists('WooCommerce')) {
        new Cart_Debugger();
    }
}
add_action('plugins_loaded', 'init_cart_debugger');
