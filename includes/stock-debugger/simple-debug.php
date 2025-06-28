<?php
/**
 * Simple Stock Debugger
 * 
 * Prints debug information in the footer
 * 
 * @package Mitnafun_Order_Admin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add footer debug output
add_action('wp_footer', 'mitnafun_simple_stock_debug', 9999);

/**
 * Print debug information in the footer
 */
function mitnafun_simple_stock_debug() {
    // Only run on product pages for admins
    if (!is_product() || !current_user_can('manage_woocommerce')) {
        return;
    }
    
    global $product;
    
    if (!$product) {
        return;
    }
    
    // Get product ID
    $product_id = $product->get_id();
    
    // Get stock information
    $stock_quantity = $product->get_stock_quantity();
    $manage_stock = $product->get_manage_stock();
    $stock_status = $product->get_stock_status();
    
    // Get reserved dates from product meta
    $reserved_dates = get_post_meta($product_id, '_rental_dates', true);
    if (empty($reserved_dates)) {
        $reserved_dates = array();
    }
    
    // Get buffer dates from product meta
    $buffer_dates = get_post_meta($product_id, '_buffer_dates', true);
    if (empty($buffer_dates)) {
        $buffer_dates = array();
    }
    
    // Get active rentals
    $active_rentals = array();
    $orders = wc_get_orders(array(
        'limit' => -1,
        'status' => array('wc-processing', 'wc-on-hold', 'wc-completed'),
        'meta_key' => '_rental_product_id',
        'meta_value' => $product_id,
    ));
    
    if (!empty($orders)) {
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $start_date = get_post_meta($order_id, '_rental_start_date', true);
            $end_date = get_post_meta($order_id, '_rental_end_date', true);
            
            if ($start_date && $end_date) {
                $active_rentals[] = array(
                    'order_id' => $order_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                );
            }
        }
    }
    
    // Output debug information
    ?>
    <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 20px; margin: 20px 0; font-family: monospace; direction: ltr; text-align: left;">
        <h3 style="margin-top: 0;">Stock Debugger</h3>
        
        <h4>Basic Stock Information</h4>
        <pre style="background: #fff; padding: 10px; overflow: auto;">
Product ID: <?php echo $product_id; ?>
Stock Quantity: <?php echo $stock_quantity; ?>
Manage Stock: <?php echo $manage_stock ? 'Yes' : 'No'; ?>
Stock Status: <?php echo $stock_status; ?>
        </pre>
        
        <h4>Reserved Dates</h4>
        <pre style="background: #fff; padding: 10px; overflow: auto;">
<?php print_r($reserved_dates); ?>
        </pre>
        
        <h4>Buffer Dates</h4>
        <pre style="background: #fff; padding: 10px; overflow: auto;">
<?php print_r($buffer_dates); ?>
        </pre>
        
        <h4>Active Rentals</h4>
        <pre style="background: #fff; padding: 10px; overflow: auto;">
<?php print_r($active_rentals); ?>
        </pre>
        
        <h4>JavaScript Variables</h4>
        <div id="js-debug-output" style="background: #fff; padding: 10px; overflow: auto;">Loading...</div>
    </div>
    
    <script>
        jQuery(document).ready(function($) {
            // Extract JavaScript variables
            var jsDebug = {
                initialStock: window.initialStock || 'undefined',
                stockQuantity: window.stockQuantity || 'undefined',
                reservedDates: window.reservedDates || 'undefined',
                reservedDatesCounts: window.reservedDatesCounts || 'undefined',
                bufferDates: window.bufferDates || 'undefined',
                disabledDates: window.disabledDates || 'undefined'
            };
            
            // Display JavaScript variables
            $('#js-debug-output').html('<pre>' + JSON.stringify(jsDebug, null, 2) + '</pre>');
            
            // Extract calendar disabled dates
            var calendarDisabledDates = [];
            
            // Check for fallback calendar
            $('.fallback-calendar .day-cell.disabled').each(function() {
                var date = $(this).data('date');
                if (date) {
                    calendarDisabledDates.push(date);
                }
            });
            
            // Check for Air Datepicker
            $('.air-datepicker-cell.-disabled-').each(function() {
                var dateAttr = $(this).data('date');
                if (dateAttr) {
                    calendarDisabledDates.push(dateAttr);
                }
            });
            
            // Append calendar disabled dates
            if (calendarDisabledDates.length > 0) {
                $('#js-debug-output').append('<h4>Calendar Disabled Dates</h4><pre>' + JSON.stringify(calendarDisabledDates, null, 2) + '</pre>');
            }
        });
    </script>
    <?php
}
