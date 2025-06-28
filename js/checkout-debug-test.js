// Test file to verify core functionality
jQuery(document).ready(function($) {
    // Prevent duplicate execution
    if (typeof window.mitnafunDebugInitialized === 'undefined') {
        window.mitnafunDebugInitialized = true;
    } else {
        return;
    }

    // Log to console
    console.log('Debug script loaded successfully');

    // Function to get product ID from cart item
    function getProductId($item) {
        const dataProductId = $item.data('product_id');
        if (dataProductId !== undefined && !isNaN(dataProductId)) {
            return parseInt(dataProductId, 10);
        }
        return null;
    }

    // Process cart items
    function processCartItems() {
        const $cartItems = $('.woocommerce-checkout-review-order-table .cart_item');
        if ($cartItems.length === 0) {
            console.log('No cart items found');
            return;
        }

        $cartItems.each(function() {
            const $item = $(this);
            const productId = getProductId($item);
            console.log(`Processing item with product ID: ${productId}`);
        });
    }

    // Initialize
    processCartItems();
});
