// Simplified checkout debug script
jQuery(document).ready(function($) {
    // Prevent duplicate execution
    if (typeof window.mitnafunDebugInitialized === 'undefined') {
        window.mitnafunDebugInitialized = true;
    } else {
        return;
    }

    // Log to console for debugging
    console.log('Debug script loaded successfully');

    // Function to get stock information via AJAX
    function getProductStockInfo(productId) {
        return new Promise((resolve) => {
            $.ajax({
                url: mitnafunCheckout.ajax_url,
                type: 'POST',
                data: {
                    action: 'mitnafun_get_product_stock',
                    product_id: productId,
                    nonce: mitnafunCheckout.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        console.warn('Error getting stock for product', productId, response);
                        resolve(null);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error getting stock:', error);
                    resolve(null);
                }
            });
        });
    }

    // Function to extract product info from cart item
    function extractProductInfo($item) {
        const productName = $item.find('.product-name').text().trim();
        const quantity = $item.find('.product-quantity').text().trim();
        const productId = $item.data('product_id');
        return { productName, quantity, productId };
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
            const { productName, quantity, productId } = extractProductInfo($item);
            
            console.log(`Processing item: ${productName}`);
            console.log(`Quantity: ${quantity}`);
            
            if (productId) {
                getProductStockInfo(productId).then(stockInfo => {
                    if (stockInfo) {
                        console.log('Stock Info:', stockInfo);
                    }
                }).catch(error => {
                    console.error('Error getting stock info:', error);
                });
            }
        });
    }

    // Initialize
    processCartItems();

    // Handle checkout updates
    $(document.body).on('updated_checkout', function() {
        console.log('Order review updated - refreshing cart data...');
        processCartItems();
    });
});
