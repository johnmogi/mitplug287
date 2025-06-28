<?php
/**
 * Stock Debugger Loader
 * 
 * Loads the stock debugger functionality
 * 
 * @package Mitnafun_Order_Admin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include the stock debugger class
require_once plugin_dir_path(__FILE__) . 'class-stock-debugger.php';

// Initialize the Stock_Debugger class
$stock_debugger = new Stock_Debugger();

// Hook to add a direct link to the stock debugger in the admin bar
add_action('admin_bar_menu', 'mitnafun_add_stock_debugger_link', 100);

/**
 * Add a link to the stock debugger in the admin bar
 * 
 * @param WP_Admin_Bar $admin_bar
 */
function mitnafun_add_stock_debugger_link($admin_bar) {
    if (!current_user_can('manage_options') || !is_product()) {
        return;
    }
    
    $admin_bar->add_node([
        'id'    => 'stock-debugger',
        'title' => '🛠 Stock Debugger',
        'href'  => '#stock-debugger',
        'meta'  => [
            'title' => 'Jump to Stock Debugger',
        ],
    ]);
}

// Add a filter to modify the calendar rendering for debug purposes
add_action('wp_footer', 'mitnafun_add_calendar_debugger', 100);

/**
 * Add calendar debugger script
 */
function mitnafun_add_calendar_debugger() {
    if (!current_user_can('manage_options') || !is_product()) {
        return;
    }
    
    ?>
    <script>
        jQuery(document).ready(function($) {
            // Wait for calendar to be fully loaded
            setTimeout(function() {
                console.log('🛠 Stock Debugger - Calendar Loaded');
                
                // Extract initial stock from global variable
                if (window.initialStock !== undefined) {
                    console.log('🛠 Stock Debugger - Initial Stock from global:', window.initialStock);
                }
                
                // Extract stock quantity from global variable
                if (window.stockQuantity !== undefined) {
                    console.log('🛠 Stock Debugger - Stock Quantity from global:', window.stockQuantity);
                }
                
                // Extract reserved dates from global variable
                if (window.reservedDates !== undefined) {
                    console.log('🛠 Stock Debugger - Reserved Dates from global:', window.reservedDates);
                }
                
                // Extract reserved dates counts from global variable
                if (window.reservedDatesCounts !== undefined) {
                    console.log('🛠 Stock Debugger - Reserved Dates Counts from global:', window.reservedDatesCounts);
                }
                
                // Extract buffer dates from global variable
                if (window.bufferDates !== undefined) {
                    console.log('🛠 Stock Debugger - Buffer Dates from global:', window.bufferDates);
                }
                
                // Add click handler to calendar cells to show debug info
                $(document).on('click', '.fallback-calendar .day-cell, .air-datepicker-cell', function(e) {
                    // Don't interfere with normal calendar operation
                    if (!e.ctrlKey) {
                        return;
                    }
                    
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Get date from cell
                    const date = $(this).data('date');
                    if (!date) {
                        return;
                    }
                    
                    // Get cell status
                    const isDisabled = $(this).hasClass('disabled') || $(this).hasClass('-disabled-');
                    const isWeekend = $(this).hasClass('weekend') || $(this).hasClass('-weekend-');
                    const isSelected = $(this).hasClass('selected') || $(this).hasClass('-selected-');
                    
                    // Check if date is in reserved dates
                    let reservedCount = 0;
                    if (window.reservedDatesCounts && window.reservedDatesCounts[date]) {
                        reservedCount = window.reservedDatesCounts[date];
                    }
                    
                    // Check if date is in buffer dates
                    const isBuffer = window.bufferDates && window.bufferDates.includes(date);
                    
                    // Calculate available stock
                    const initialStock = window.initialStock || 0;
                    const availableStock = initialStock - reservedCount;
                    
                    // Show debug info
                    alert(`Date: ${date}
Status: ${isDisabled ? 'Disabled' : 'Enabled'}
Weekend: ${isWeekend ? 'Yes' : 'No'}
Selected: ${isSelected ? 'Yes' : 'No'}
Reserved Count: ${reservedCount}
Buffer Date: ${isBuffer ? 'Yes' : 'No'}
Initial Stock: ${initialStock}
Available Stock: ${availableStock}`);
                });
                
                // Add keyboard shortcut to toggle debug mode
                $(document).on('keydown', function(e) {
                    // Ctrl+Shift+D to toggle debug mode
                    if (e.ctrlKey && e.shiftKey && e.keyCode === 68) {
                        $('#toggle-debug-mode').click();
                    }
                });
            }, 2000);
        });
    </script>
    <?php
}
