/**
 * Simple test script to verify loading
 */
console.log('TEST SCRIPT LOADED AND EXECUTED - ' + new Date().toISOString());
jQuery(document).ready(function($) {
    console.log('TEST SCRIPT JQUERY READY - ' + new Date().toISOString());
    
    // Try to directly bind to add-to-cart button
    $(document).on('click', '.single_add_to_cart_button', function(e) {
        console.log('TEST SCRIPT: Add to cart button clicked');
        // Don't prevent default, just log
    });
});
