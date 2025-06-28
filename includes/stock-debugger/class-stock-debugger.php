<?php
/**
 * Stock Debugger Class
 * 
 * Provides debugging tools for rental calendar stock and reserved dates
 * 
 * @package Mitnafun_Order_Admin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stock Debugger Class
 */
class Stock_Debugger {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add debug output to footer for admins
        add_action('wp_footer', [$this, 'render_debug_output']);
        
        // Add reservation data to page
        add_action('wp_footer', [$this, 'output_reserved_dates_json'], 5);
        
        // Add calendar availability data for all users
        add_action('wp_footer', [$this, 'output_calendar_availability_data'], 20);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Add AJAX handlers
        add_action('wp_ajax_get_stock_debug_data', [$this, 'ajax_get_stock_debug_data']);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_admin() && is_product() && current_user_can('manage_options')) {
            global $post;
            
            // Enqueue the debugger CSS
            wp_enqueue_style(
                'stock-debugger-css',
                plugin_dir_url(__FILE__) . 'assets/css/stock-debugger.css',
                array(),
                filemtime(plugin_dir_path(__FILE__) . 'assets/css/stock-debugger.css')
            );
            
            // Enqueue the debugger JS
            wp_enqueue_script(
                'stock-debugger-js',
                plugin_dir_url(__FILE__) . 'assets/js/stock-debugger-new.js',
                array('jquery'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/js/stock-debugger-new.js'),
                true
            );
            
            // Get product stock
            $stock = $this->get_current_stock();
            
            // Localize script with data
            wp_localize_script(
                'stock-debugger-js',
                'stockDebugger',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'product_id' => $post->ID,
                    'nonce' => wp_create_nonce('stock_debugger_nonce'),
                    'stock' => $stock,
                    'isAdmin' => current_user_can('manage_options') ? 'yes' : 'no',
                )
            );
        }
    }
    
    /**
     * Get current product stock
     */
    private function get_current_stock() {
        global $product;
        return $product ? $product->get_stock_quantity() : 0;
    }
    
    /**
     * Output reserved dates as JSON for frontend with detailed availability information
     */
    public function output_reserved_dates_json() {
        if (!is_product() || !current_user_can('manage_options')) {
            return;
        }

        global $wpdb, $product;
        
        if (!$product) {
            return;
        }

        $product_id = $product->get_id();
        $today = new DateTime();
        
        // Get initial stock from custom meta or use current stock if not set
        $current_stock = (int) $product->get_stock_quantity();
        $initial_stock = (int) get_post_meta($product_id, '_initial_stock', true);
        
        // If initial stock is not set, use current stock as initial
        if (!$initial_stock) {
            $initial_stock = $current_stock;
            update_post_meta($product_id, '_initial_stock', $initial_stock);
        }
        
        $debug_info = [
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'initial_stock' => $initial_stock,
            'current_stock' => $current_stock,
            'current_date' => $today->format('Y-m-d H:i:s'),
            'query' => []
        ];
        
        // Get all reservations for this product
        $sql = $wpdb->prepare("
            SELECT 
                o.id as order_id, 
                o.status, 
                oim.meta_value as rental_dates,
                oi.order_item_id,
                oi.order_item_name,
                oim2.meta_value as product_id,
                oim3.meta_value as variation_id,
                oim4.meta_value as item_quantity
            FROM {$wpdb->prefix}woocommerce_order_items oi
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim 
                ON oi.order_item_id = oim.order_item_id 
                AND oim.meta_key = 'Rental Dates'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 
                ON oi.order_item_id = oim2.order_item_id 
                AND oim2.meta_key = '_product_id'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim3 
                ON oi.order_item_id = oim3.order_item_id 
                AND oim3.meta_key = '_variation_id'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim4 
                ON oi.order_item_id = oim4.order_item_id 
                AND oim4.meta_key = '_qty'
            JOIN {$wpdb->prefix}wc_orders o 
                ON oi.order_id = o.id
            WHERE oim.meta_key IS NOT NULL
              AND oim2.meta_key IS NOT NULL
              AND oim2.meta_value = %d
              AND o.status IN ('wc-processing', 'wc-on-hold', 'wc-pending', 'wc-rental-confirmed')
            ORDER BY o.id DESC, oi.order_item_id
        ", $product_id);
        
        $debug_info['query']['sql'] = $sql;
        $rows = $wpdb->get_results($sql);
        $debug_info['query']['rows_found'] = count($rows);
        $debug_info['query']['results'] = [];

        $all_reservations = [];
        $date_counts = [];
        
        foreach ($rows as $index => $row) {
            $debug_row = [
                'order_id' => $row->order_id,
                'status' => $row->status,
                'rental_dates' => $row->rental_dates,
                'item_name' => $row->order_item_name,
                'quantity' => (int)$row->item_quantity
            ];
            
            // Handle different date formats (DD.MM.YYYY or YYYY-MM-DD)
            $date_range = explode(' - ', $row->rental_dates);
            $debug_row['raw_dates'] = $date_range;
            
            if (count($date_range) === 2) {
                $start = trim($date_range[0]);
                $end = trim($date_range[1]);
                
                // Convert DD.MM.YYYY to YYYY-MM-DD if needed
                $format_date = function($date_str) {
                    // If date is already in YYYY-MM-DD format
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
                        return $date_str;
                    }
                    
                    // Convert DD.MM.YYYY to YYYY-MM-DD
                    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date_str, $matches)) {
                        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                    }
                    
                    return $date_str;
                };
                
                $formatted_start = $format_date($start);
                $formatted_end = $format_date($end);
                
                $reservation = [
                    'start' => $start,
                    'end' => $end,
                    'formatted_start' => $formatted_start,
                    'formatted_end' => $formatted_end,
                    'status' => $row->status,
                    'order_id' => $row->order_id,
                    'item_id' => $row->order_item_id,
                    'quantity' => (int)$row->item_quantity,
                    'raw_dates' => $row->rental_dates
                ];
                
                $all_reservations[] = $reservation;
                
                // Debug: Log the current reservation being processed
                error_log('Processing reservation - Start: ' . $formatted_start . ', End: ' . $formatted_end);
                
                try {
                    // Create DateTime objects
                    $start_date = new DateTime($formatted_start);
                    $end_date = new DateTime($formatted_end);
                    $today = new DateTime('now', new DateTimeZone('Asia/Jerusalem')); // Using Israel timezone
                    $today->setTime(0, 0, 0); // Reset time to start of day
                    
                    // Debug: Log the dates being compared
                    error_log('Today: ' . $today->format('Y-m-d H:i:s'));
                    error_log('Reservation end date: ' . $end_date->format('Y-m-d H:i:s'));
                    
                    // Only process if the reservation ends today or in the future
                    if ($end_date >= $today) {
                        $end_date_for_count = clone $end_date;
                        $end_date_for_count->modify('+1 day'); // Include end date in the range
                        
                        $interval = new DateInterval('P1D');
                        $period = new DatePeriod($start_date, $interval, $end_date_for_count);
                        
                        $has_future_dates = false;
                        $debug_dates = [];
                        
                        foreach ($period as $date) {
                            $date->setTime(0, 0, 0); // Reset time to start of day
                            $is_future = ($date >= $today);
                            $debug_dates[] = $date->format('Y-m-d') . ($is_future ? ' (future)' : ' (past)');
                            
                            // Only count future or today's dates
                            if ($is_future) {
                                $has_future_dates = true;
                                $date_str = $date->format('Y-m-d');
                                if (!isset($date_counts[$date_str])) {
                                    $date_counts[$date_str] = 0;
                                }
                                $date_counts[$date_str] += $reservation['quantity'];
                            }
                        }
                        
                        // Debug: Log the dates being processed
                        error_log('Processed dates: ' . implode(', ', $debug_dates));
                        error_log('Has future dates: ' . ($has_future_dates ? 'yes' : 'no'));
                        
                        // Only add to all_reservations if it has future dates
                        if ($has_future_dates) {
                            $all_reservations[] = $reservation;
                            error_log('Added to all_reservations');
                        } else {
                            error_log('Skipped - no future dates in this reservation');
                        }
                    } else {
                        error_log('Skipped - reservation ends in the past');
                    }
                } catch (Exception $e) {
                    error_log('Error processing date range: ' . $e->getMessage());
                }
                
                $debug_row['processed'] = true;
            } else {
                $debug_row['error'] = 'Invalid date format';
                $debug_row['processed'] = false;
            }
            
            $debug_info['query']['results'][$index] = $debug_row;
        }
        
        // Sort dates chronologically
        ksort($date_counts);
        
        // Calculate availability for each date
        $availability = [];
        foreach ($date_counts as $date => $count) {
            $availability[$date] = [
                'reserved' => $count,
                'available' => max(0, $initial_stock - $count),
                'is_available' => ($initial_stock - $count) > 0,
                'is_fully_booked' => $count >= $initial_stock,
                'initial_stock' => $initial_stock // Add initial stock to each date for reference
            ];
        }
        
        // Prepare output data
        $output = [
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'initial_stock' => $initial_stock,
            'current_stock' => $current_stock,
            'current_date' => $today->format('Y-m-d H:i:s'),
            'reservations' => $all_reservations,
            'date_availability' => $availability,
            'summary' => [
                'total_reserved_dates' => count($all_reservations),
                'fully_booked_dates' => count(array_filter($availability, function($item) use ($initial_stock) {
                    return $item['reserved'] >= $initial_stock;
                })),
                'available_dates' => count(array_filter($availability, function($item) use ($initial_stock) {
                    return $item['reserved'] < $initial_stock;
                }))
            ]
        ];
        
        // Output the data to the page
        echo '<script>';
        echo '//<![CDATA[\n';
        echo 'window.rentalReservedData = ' . wp_json_encode($output) . ';\n';
        
        // Only include debug info in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo 'console.groupCollapsed("Stock Debugger: Reservation Data");\n';
            echo 'console.log(' . wp_json_encode($output, JSON_PRETTY_PRINT) . ');\n';
            echo 'console.groupEnd();\n';
            
            // Log to PHP error log
            error_log('Stock Debugger - Product ID: ' . $product_id);
            error_log('Stock Debugger - Initial Stock: ' . $initial_stock);
            error_log('Stock Debugger - Found ' . count($all_reservations) . ' reservations');
            error_log(print_r($output, true));
        }
        
        echo '//]]>';
        echo '</script>';
    }
    
    /**
     * Output calendar availability data for all users
     * This makes the stock and reservation data available to the calendar
     */
    public function output_calendar_availability_data() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        $reserved_dates = $this->get_reserved_dates($product_id);
        
        // Get initial stock from custom meta or use current stock if not set
        $initial_stock = (int) get_post_meta($product_id, '_initial_stock', true);
        if (!$initial_stock) {
            $initial_stock = $product->get_stock_quantity();
            if (!$initial_stock) {
                $initial_stock = 1; // Default to 1 if no stock is set
            }
            update_post_meta($product_id, '_initial_stock', $initial_stock);
        }
        
        // Format reservation data for the calendar
        $calendar_data = array(
            'stock' => $initial_stock,
            'reservations' => array()
        );
        
        // Process reservations
        if (!empty($reserved_dates)) {
            foreach ($reserved_dates as $date => $quantity) {
                $calendar_data['reservations'][$date] = $quantity;
            }
        }
        
        // Output the data as a JSON object in a script tag
        echo '<script id="calendar-availability-data" type="application/json">';
        echo wp_json_encode($calendar_data);
        echo '</script>';
    }
    
    /**
     * Render debug output in footer
     */
    public function render_debug_output() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        $stock = $product->get_stock_quantity();
        $reserved_dates = $this->get_reserved_dates($product_id);
        $buffer_dates = $this->get_buffer_dates($product_id);
        $active_rentals = $this->get_active_rentals($product_id);
        
        // Debug panel HTML
        echo '<div id="stock-debugger" class="stock-debugger-panel">';
        echo '<div class="debug-header">';
        echo '<h3>🛠 Stock Debugger</h3>';
        echo '<button class="debug-close">×</button>';
        echo '</div>';
        
        echo '<div class="debug-content">';
        
        // Get initial stock from custom meta or use current stock if not set
        $initial_stock = (int) get_post_meta($product_id, '_initial_stock', true);
        if (!$initial_stock) {
            $initial_stock = $stock;
            update_post_meta($product_id, '_initial_stock', $initial_stock);
        }
        
        // Basic info section
        echo '<div class="debug-section">';
        echo '<h4>Basic Information</h4>';
        echo '<div class="debug-grid">';
        echo '<div class="debug-item"><span class="label">Product ID:</span> <span class="value">' . esc_html($product_id) . '</span></div>';
        echo '<div class="debug-item"><span class="label">Initial Stock:</span> <span class="value">' . esc_html($initial_stock) . ' <small>(from _initial_stock meta)</small></span></div>';
        echo '<div class="debug-item"><span class="label">Current Stock:</span> <span class="value">' . esc_html($stock) . ' <small>(from WooCommerce)</small></span></div>';
        echo '<div class="debug-item"><span class="label">Manage Stock:</span> <span class="value">' . ($product->get_manage_stock() ? 'Yes' : 'No') . '</span></div>';
        echo '<div class="debug-item"><span class="label">Stock Status:</span> <span class="value">' . esc_html($product->get_stock_status()) . '</span></div>';
        echo '</div>'; // Close debug-grid
        
        // Reserved dates section
        echo '<div class="debug-section">';
        echo '<h4>Reserved Dates <span class="debug-count">(' . count($reserved_dates) . ')</span></h4>';
        
        if (!empty($reserved_dates)) {
            echo '<div class="debug-scrollable">';
            echo '<table class="debug-table">';
            echo '<thead><tr><th>Order ID</th><th>Status</th><th>Start Date</th><th>End Date</th><th>Quantity</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($reserved_dates as $reservation) {
                echo '<tr>';
                echo '<td><a href="' . admin_url('post.php?post=' . $reservation['order_id'] . '&action=edit') . '" target="_blank">#' . $reservation['order_id'] . '</a></td>';
                echo '<td><span class="status-badge status-' . esc_attr(str_replace('wc-', '', $reservation['status'])) . '">' . ucfirst(str_replace('wc-', '', $reservation['status'])) . '</span></td>';
                echo '<td>' . esc_html($reservation['start_date']) . '</td>';
                echo '<td>' . esc_html($reservation['end_date']) . '</td>';
                echo '<td>' . esc_html($reservation['quantity']) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<p class="no-data">No reserved dates found for this product.</p>';
        }
        
        echo '</div>';
        
        // Buffer dates section (currently not used, but keeping the section for future use)
        echo '<div class="debug-section">';
        echo '<h4>Buffer Dates <span class="debug-count">(0)</span></h4>';
        echo '<p class="no-data">Buffer dates are not currently in use.</p>';
        echo '</div>';
        
        // Active rentals section
        echo '<div class="debug-section">';
        echo '<h4>Active Rentals <span class="debug-count">(' . count($active_rentals) . ')</span></h4>';
        
        if (!empty($active_rentals)) {
            echo '<div class="debug-scrollable">';
            echo '<table class="debug-table">';
            echo '<thead><tr><th>Order ID</th><th>Status</th><th>Dates</th><th>Qty</th><th>Order Date</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($active_rentals as $rental) {
                echo '<tr>';
                echo '<td><a href="' . admin_url('post.php?post=' . $rental['order_id'] . '&action=edit') . '" target="_blank">#' . $rental['order_id'] . '</a></td>';
                echo '<td><span class="status-badge status-' . esc_attr(str_replace('wc-', '', $rental['status'])) . '">' . ucfirst(str_replace('wc-', '', $rental['status'])) . '</span></td>';
                echo '<td>' . esc_html($rental['start_date'] . ' to ' . $rental['end_date']) . '</td>';
                echo '<td>' . esc_html($rental['quantity']) . '</td>';
                echo '<td>' . date_i18n(get_option('date_format'), strtotime($rental['order_date'])) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<p class="no-data">No active rentals found for this product.</p>';
        }
        
        echo '</div>';
        
        // Debug controls
        echo '<div class="debug-section">';
        echo '<h4>Debug Controls</h4>';
        echo '<div class="debug-controls">';
        echo '<button id="toggle-debug-mode" class="debug-button"><span class="dashicons dashicons-admin-generic"></span> Toggle Debug Mode</button>';
        echo '<button id="refresh-debug-data" class="debug-button primary"><span class="dashicons dashicons-update"></span> Refresh Data</button>';
        echo '</div>';
        echo '</div>';
        
        // Debug legend
        echo '<div class="debug-section">';
        echo '<h4>Legend</h4>';
        echo '<div class="debug-legend">';
        echo '<div><span class="legend-box fully-booked"></span> Fully Booked</div>';
        echo '<div><span class="legend-box partially-booked"></span> יש הזמנה קודמת</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // Close debug-content
        echo '</div>'; // Close stock-debugger-panel
    }
    
    /**
     * Get reserved dates for a product
     * 
     * @param int $product_id
     * @return array
     */
    public function get_reserved_dates($product_id) {
        global $wpdb;
        
        // Get current date in Israel timezone
        $today = new DateTime('now', new DateTimeZone('Asia/Jerusalem'));
        $today->setTime(0, 0, 0);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oi.order_id,
                o.status,
                oim.meta_value as rental_dates,
                oim_qty.meta_value as quantity
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_pid ON oi.order_item_id = oim_pid.order_item_id
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
            WHERE oim.meta_key = 'Rental Dates'
            AND oim_pid.meta_key = '_product_id' 
            AND oim_pid.meta_value = %d
            AND o.status IN ('wc-processing', 'wc-on-hold', 'wc-pending', 'wc-rental-confirmed', 'on-hold', 'pending', 'processing')
            ORDER BY o.id DESC",
            $product_id
        ));
        
        // Create an associative array with dates as keys and quantities as values
        $reservations = [];
        
        foreach ($results as $row) {
            $dates = explode(' - ', $row->rental_dates);
            if (count($dates) !== 2) {
                continue;
            }
            
            try {
                // Parse start and end dates
                $start_date_str = trim($dates[0]);
                $end_date_str = trim($dates[1]);
                
                // Convert from d.m.Y format to Y-m-d for JavaScript
                $start_parts = explode('.', $start_date_str);
                $end_parts = explode('.', $end_date_str);
                
                if (count($start_parts) !== 3 || count($end_parts) !== 3) {
                    error_log('Invalid date format: ' . $row->rental_dates);
                    continue;
                }
                
                $start_date = new DateTime($start_parts[2] . '-' . $start_parts[1] . '-' . $start_parts[0]);
                $end_date = new DateTime($end_parts[2] . '-' . $end_parts[1] . '-' . $end_parts[0]);
                
                // Skip if the reservation has already ended
                if ($end_date < $today) {
                    continue;
                }
                
                // Get quantity from order
                $quantity = (int)$row->quantity;
                if ($quantity <= 0) {
                    $quantity = 1; // Default to 1 if quantity is not set
                }
                
                // Create a period iterator for the date range
                $interval = new DateInterval('P1D');
                $daterange = new DatePeriod($start_date, $interval, $end_date->modify('+1 day')); // Include end date
                
                // Add each date in the range to the reservations array
                foreach ($daterange as $date) {
                    $date_str = $date->format('Y-m-d');
                    
                    // Add or increment the quantity for this date
                    if (isset($reservations[$date_str])) {
                        $reservations[$date_str] += $quantity;
                    } else {
                        $reservations[$date_str] = $quantity;
                    }
                }
                
                // Log for debugging
                error_log("Processed reservation: Order #{$row->order_id}, Dates: {$row->rental_dates}, Quantity: {$quantity}");
                
            } catch (Exception $e) {
                error_log('Error processing reservation dates: ' . $e->getMessage());
                continue;
            }
        }
        
        return $reservations;
    }
    
    /**
     * Get buffer dates for a product
     * 
     * @param int $product_id
     * @return array
     */
    public function get_buffer_dates($product_id) {
        // For now, return an empty array as we don't have buffer dates in the database
        // This can be implemented later if needed
        return [];
    }
    
    /**
     * Get active rentals for a product
     * 
     * @param int $product_id
     * @return array
     */
    public function get_active_rentals($product_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oi.order_id,
                o.status,
                oim.meta_value as rental_dates,
                oim_qty.meta_value as quantity,
                o.date_created
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_pid ON oi.order_item_id = oim_pid.order_item_id
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
            WHERE oim.meta_key = 'Rental Dates'
            AND oim_pid.meta_key = '_product_id' 
            AND oim_pid.meta_value = %d
            AND o.status IN ('wc-processing', 'wc-completed', 'wc-rental-confirmed')
            ORDER BY o.date_created DESC
            LIMIT 10",
            $product_id
        ));
        
        $active_rentals = [];
        
        foreach ($results as $row) {
            $dates = explode(' - ', $row->rental_dates);
            if (count($dates) === 2) {
                $active_rentals[] = [
                    'order_id' => $row->order_id,
                    'status' => $row->status,
                    'start_date' => trim($dates[0]),
                    'end_date' => trim($dates[1]),
                    'quantity' => (int)$row->quantity,
                    'order_date' => $row->date_created
                ];
            }
        }
        
        return $active_rentals;
    }
    
    /**
     * AJAX handler for getting stock debug data
     */
    public function ajax_get_stock_debug_data() {
        check_ajax_referer('stock_debugger_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
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
        
        $stock = $product->get_stock_quantity();
        $reserved_dates = $this->get_reserved_dates($product_id);
        $buffer_dates = $this->get_buffer_dates($product_id);
        $active_rentals = $this->get_active_rentals($product_id);
        
        wp_send_json_success([
            'stock' => $stock,
            'manage_stock' => $product->get_manage_stock(),
            'stock_status' => $product->get_stock_status(),
            'reserved_dates' => $reserved_dates,
            'buffer_dates' => $buffer_dates,
            'active_rentals' => $active_rentals,
        ]);
    }
}
