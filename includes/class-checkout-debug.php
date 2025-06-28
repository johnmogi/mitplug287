<?php
/**
 * Checkout Debug functionality
 *
 * @package Mitnafun_Order_Admin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Mitnafun_Checkout_Debug {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
        
        // Enqueue scripts on checkout page
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'));
    }
    
    /**
     * Register scripts and styles
     * Disabled to prevent 404 errors for missing files
     */
    public function register_scripts() {
        // Script registration disabled as checkout-debug.js file is missing
        // Uncomment if the file is restored
        /*
        // Register checkout debug script
        wp_register_script(
            'mitnafun-checkout-debug',
            plugin_dir_url(dirname(__FILE__)) . 'js/checkout-debug.js',
            array('jquery'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'js/checkout-debug.js'),
            true
        );
        
        // Localize the script with data
        wp_localize_script(
            'mitnafun-checkout-debug',
            'mitnafun_checkout_debug',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mitnafun_checkout_nonce'),
                'is_checkout' => is_checkout()
            )
        );
        */
    }
    
    /**
     * Enqueue scripts for checkout page
     * Disabled to prevent 404 errors for missing files
     */
    public function enqueue_checkout_scripts() {
        // Script enqueue disabled as checkout-debug.js file is missing
        // Uncomment if the file is restored
        /*
        if (function_exists('is_checkout') && is_checkout()) {
            wp_enqueue_script('mitnafun-checkout-debug');
            
            // Localize script with data
            wp_localize_script('mitnafun-checkout-debug', 'mitnafun_checkout_debug', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mitnafun_checkout_nonce'),
                'is_checkout' => true
            ));
        }
        */
    }
}

// Initialize the checkout debug functionality
function mitnafun_init_checkout_debug() {
    new Mitnafun_Checkout_Debug();
}
add_action('plugins_loaded', 'mitnafun_init_checkout_debug');
