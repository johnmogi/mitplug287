<?php
/**
 * Adds debug information to the WooCommerce checkout review
 */
class Mitnafun_Checkout_Debug {
    
    public function __construct() {
        // Add debug info to order review
        add_action('woocommerce_review_order_before_order_total', [$this, 'add_debug_info_to_checkout']);
        
        // Add custom CSS
        add_action('wp_head', [$this, 'add_debug_styles']);
    }
    
    /**
     * Add debug information to the checkout review
     */
    public function add_debug_info_to_checkout() {
        // Only show to admins
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        if (!is_checkout()) {
            return;
        }
        
        $cart = WC()->cart;
        if (empty($cart)) {
            return;
        }
        
        echo '<tr class="mitnafun-debug-header"><th colspan="2">' . __('Rental Debug Info', 'mitnafun-order-admin') . '</th></tr>';
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            
            // Get rental dates if they exist
            $rental_dates = isset($cart_item['rental_dates']) ? $cart_item['rental_dates'] : __('No rental dates found', 'mitnafun-order-admin');
            
            // Get stock information
            $total_stock = get_post_meta($product_id, '_initial_stock', true);
            $wc_stock = $product->get_stock_quantity();
            $backorders = $product->backorders_allowed() ? __('Yes', 'mitnafun-order-admin') : __('No', 'mitnafun-order-admin');
            
            // Display the information
            echo '<tr class="mitnafun-debug-product">';
            echo '<td colspan="2">';
            echo '<strong>' . $product->get_name() . '</strong><br>';
            echo __('Rental Dates:', 'mitnafun-order-admin') . ' ' . (is_array($rental_dates) ? print_r($rental_dates, true) : esc_html($rental_dates)) . '<br>';
            echo __('Quantity:', 'mitnafun-order-admin') . ' ' . $quantity . '<br>';
            echo __('Total Stock:', 'mitnafun-order-admin') . ' ' . ($total_stock !== '' ? $total_stock : 'N/A') . '<br>';
            echo __('WC Stock:', 'mitnafun-order-admin') . ' ' . ($wc_stock !== null ? $wc_stock : 'N/A') . '<br>';
            echo __('Backorders Allowed:', 'mitnafun-order-admin') . ' ' . $backorders . '<br>';
            echo '</td>';
            echo '</tr>';
            
            // Log this information
            error_log(sprintf(
                'Checkout Debug - Product: %s, Qty: %d, Total Stock: %s, WC Stock: %s, Dates: %s',
                $product->get_name(),
                $quantity,
                $total_stock,
                $wc_stock,
                is_array($rental_dates) ? print_r($rental_dates, true) : $rental_dates
            ));
        }
    }
    
    /**
     * Add some basic styles for the debug info
     */
    public function add_debug_styles() {
        if (!is_checkout() || !current_user_can('manage_woocommerce')) {
            return;
        }
        ?>
        <style>
            .mitnafun-debug-header th {
                background-color: #f8d7da !important;
                color: #721c24 !important;
                font-weight: bold !important;
                text-transform: uppercase;
                font-size: 0.9em;
                padding: 15px !important;
                border-bottom: 2px solid #f5c6cb !important;
            }
            .mitnafun-debug-product td {
                background-color: #f8f9fa !important;
                border-bottom: 1px solid #e9ecef !important;
                padding: 15px !important;
                font-size: 0.9em;
                color: #495057;
            }
            .mitnafun-debug-product:nth-child(even) td {
                background-color: #f1f3f5 !important;
            }
            .mitnafun-debug-product strong {
                color: #212529;
                display: block;
                margin-bottom: 5px;
            }
        </style>
        <?php
    }
}

// Initialize the debug functionality
function init_mitnafun_checkout_debug() {
    if (current_user_can('manage_woocommerce')) {
        new Mitnafun_Checkout_Debug();
    }
}
add_action('wp_loaded', 'init_mitnafun_checkout_debug');
