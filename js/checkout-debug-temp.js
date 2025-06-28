jQuery(document).ready(function($) {
    // Add a simple debug message
    $('<div id="mitnafun-debug-message" style="position: fixed; top: 20px; right: 20px; background: #fff; padding: 10px; border: 1px solid #ccc; z-index: 9999;">
        <h3 style="color: #0073aa;">Debug Script Loaded</h3>
        <p style="color: #23282d;">Version: ' + mitnafunCheckout.nonce + '</p>
    </div>').appendTo('body');

    // Add a console log
    console.log('Checkout debug script initialized');
});
