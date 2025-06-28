/**
 * Mitnafun WooCommerce Checkout Debug Panel
 * 
 * This script adds a debug panel to the WooCommerce checkout page
 * that shows detailed stock information for products in the cart.
 */

jQuery(document).ready(function($) {
    console.group('Mitnafun Checkout Debug');
    console.log('Initializing checkout debug panel...');
    
    // Create debug panel HTML
    const debugPanelHTML = `
    <div id="mitnafun-debug-panel" style="
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #1d2327;
        color: #f0f0f1;
        padding: 15px;
        font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;
        font-size: 13px;
        line-height: 1.5;
        z-index: 99999;
        max-height: 300px;
        overflow-y: auto;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
    ">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0; color: #fff; font-size: 16px;">Ì≥ä Stock Debug Information</h3>
            <button id="mitnafun-debug-toggle" style="background: #2271b1; color: #fff; border: none; padding: 4px 10px; border-radius: 3px; cursor: pointer;">Hide</button>
        </div>
        <div id="mitnafun-debug-content">
            <div class="debug-loading">Loading stock information...</div>
        </div>
    </div>
    `;

    // Add CSS for debug panel
    $('head').append(`
    <style>
        .debug-item { margin-bottom: 10px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 3px; }
        .debug-item h4 { margin: 0 0 5px 0; color: #72aee6; }
        .debug-info { display: flex; flex-wrap: wrap; gap: 10px; }
        .debug-info span { display: inline-block; background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 3px; }
        .stock-warning { color: #ffb900; }
        .stock-error { color: #dc3232; }
        .debug-loading:after { content: '...'; animation: dots 1.5s steps(5, end) infinite; }
        @keyframes dots { 0%, 20% { content: '.'; } 40% { content: '..'; } 60%, 100% { content: '...'; } }
        .debug-tag { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 10px; margin-left: 5px; }
        .rental-tag { background: #2271b1; color: #fff; }
    </style>
    `);

    // Add debug panel to footer
    $('body').append(debugPanelHTML);
    
    // Toggle debug panel
    $('#mitnafun-debug-toggle').on('click', function() {
        const $content = $('#mitnafun-debug-content');
        const $button = $(this);
        
        if ($content.is(':visible')) {
            $content.slideUp();
            $button.text('Show');
        } else {
            $content.slideDown();
            $button.text('Hide');
        }
    });
    
    // Function to update debug panel content
    function updateDebugPanel(content) {
        $('#mitnafun-debug-content').html(content);
    }
    
    // Function to get stock information via AJAX
    function getProductStockInfo(productId) {
        return new Promise((resolve, reject) => {
            console.log('Fetching stock information for product ID:', productId);
            
            $.ajax({
                url: mitnafun_checkout_debug.ajax_url,
                type: 'POST',
                data: {
                    action: 'mitnafun_get_product_details',
                    product_id: productId,
                    nonce: mitnafun_checkout_debug.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        console.log('Got product details:', response.data);
                        resolve(response.data);
                    } else {
                        console.warn('Error getting stock for product', productId, response);
                        reject(response.data && response.data.message ? response.data.message : 'Unknown error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error getting stock:', error);
                    reject(error);
                }
            });
        });
    }
    
    // Process cart items
    async function processCartItems(items) {
        console.log('Processing cart items:', items);
        
        if (!items || items.length === 0) {
            updateDebugPanel('<div class="debug-item">No items found in cart</div>');
            return;
        }
        
        // Show loading state
        updateDebugPanel('<div class="debug-loading">Fetching stock information for ' + items.length + ' items...</div>');
        
        // Track total quantities for aggregation
        let totalInitialStock = 0;
        let totalInCart = 0;
        let totalRemaining = 0;
        let debugContent = '';

        // Process each item
        const itemPromises = [];
        
        items.forEach(function(item) {
            // Extract item data
            const productId = item.product_id;
            const quantity = parseInt(item.quantity || 1);
            const name = item.data && item.data.product_name || item.product_name || 'Unknown Product';
            
            // Skip if no product ID
            if (!productId || productId === 'unknown') {
                console.warn('Skipping item with invalid product ID:', item);
                return;
            }
            
            // Create a promise for getting stock information for this product
            const itemPromise = getProductStockInfo(productId)
                .then(data => {
                    const itemData = {
                        id: productId,
                        name: data.product_name || name,
                        quantity: quantity,
                        initial_stock: data.initial_stock,
                        stock_quantity: data.stock_quantity,
                        stock_status: data.stock_status,
                        backorders_allowed: data.backorders_allowed,
                        is_in_stock: data.is_in_stock,
                        managing_stock: data.managing_stock,
                        is_rental: data.is_rental || false
                    };
                    
                    // Add to totals
                    if (itemData.initial_stock !== null && itemData.initial_stock !== undefined) {
                        totalInitialStock += parseInt(itemData.initial_stock);
                        totalInCart += quantity;
                        totalRemaining += parseInt(itemData.initial_stock) - quantity;
                    }
                    
                    // Generate item HTML
                    let stockInfo = `<div class="debug-item">
                        <h4>${itemData.name} (ID: ${productId})`;
                    
                    if (itemData.is_rental) {
                        stockInfo += ' <span class="debug-tag rental-tag">RENTAL</span>';
                    }
                    
                    stockInfo += `</h4>
                        <div class="debug-info">
                            <span>Quantity: ${quantity}</span>
                            <span>Initial Stock: ${itemData.initial_stock !== null && itemData.initial_stock !== undefined ? itemData.initial_stock : 'Not set'}</span>
                            <span>WC Stock: ${itemData.stock_quantity !== null && itemData.stock_quantity !== undefined ? itemData.stock_quantity : 'Not set'}</span>
                            <span>Status: ${itemData.stock_status}</span>
                            <span>In Stock: ${itemData.is_in_stock ? 'Yes' : 'No'}</span>
                            <span>Managing Stock: ${itemData.managing_stock ? 'Yes' : 'No'}</span>
                            <span>Backorders: ${itemData.backorders_allowed ? 'Allowed' : 'Not allowed'}</span>
                        </div>`;
                    
                    // Calculate remaining stock
                    if (itemData.initial_stock !== null && itemData.initial_stock !== undefined) {
                        const remaining = parseInt(itemData.initial_stock) - quantity;
                        let remainingClass = '';
                        
                        if (remaining < 0) {
                            remainingClass = 'stock-error';
                        } else if (remaining < 3) {
                            remainingClass = 'stock-warning';
                        }
                        
                        stockInfo += `<div class="${remainingClass}" style="margin-top: 5px;">
                            Remaining: <strong>${remaining}</strong>
                        </div>`;
                    }
                    
                    stockInfo += '</div>';
                    debugContent += stockInfo;
                    
                    return itemData;
                })
                .catch(error => {
                    const errorMsg = `<div class="debug-item stock-error">
                        <h4>Error: ${name} (ID: ${productId})</h4>
                        <div>${error}</div>
                    </div>`;
                    debugContent += errorMsg;
                    console.error('Error fetching product details:', error);
                });
            
            itemPromises.push(itemPromise);
        });
        
        // Wait for all product requests to complete
        try {
            await Promise.allSettled(itemPromises);
            
            // Add cart totals section after all items processed
            debugContent = `
            <div class="debug-item" style="margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 15px;">
                <h4>Ì≥ä Cart Totals</h4>
                <div class="debug-info">
                    <span>Total Initial Stock: <strong>${totalInitialStock}</strong></span>
                    <span>Total Items in Cart: <strong>${totalInCart}</strong></span>
                    <span>Remaining Stock: <strong class="${totalRemaining < 0 ? 'stock-error' : totalRemaining < 3 ? 'stock-warning' : ''}">${totalRemaining}</strong></span>
                </div>
            </div>` + debugContent;
            
            // Update the debug panel with all product information
            updateDebugPanel(debugContent || '<div class="debug-item">No stock information available</div>');
        } catch (error) {
            console.error('Error processing cart items:', error);
            updateDebugPanel('<div class="debug-item stock-error">Error processing cart items: ' + error + '</div>');
        }
    }
    
    // Function to fetch cart contents
    function fetchCartContents() {
        console.log('Attempting to fetch cart contents...');
        
        // First, try to use the WooCommerce cart object if it exists
        if (typeof wc_cart_fragments !== 'undefined' && wc_cart_fragments.cart) {
            console.log('WooCommerce cart fragments found');
            
            if (wc_cart_fragments.cart.cart_contents) {
                const cartItems = Object.values(wc_cart_fragments.cart.cart_contents);
                console.log('Cart contents found:', cartItems);
                processCartItems(cartItems);
                return;
            }
        }
        
        // If no cart data yet, try to get it from the page
        fetchCartFromPage();
    }
    
    // Function to get cart data from the page
    function fetchCartFromPage() {
        console.log('Looking for cart data in the page...');
        
        // Try to find the order review table
        const $orderReview = $('.woocommerce-checkout-review-order-table');
        
        if ($orderReview.length) {
            console.log('Found order review table, extracting items...');
            extractCartItems($orderReview);
        } else {
            console.log('Order review table not found, checking for cart items...');
            
            // Try to find cart items in other common locations
            const $cartItems = $('.cart_item, .woocommerce-cart-form__cart-item');
            
            if ($cartItems.length) {
                console.log(`Found ${$cartItems.length} cart items in the page`);
                extractCartItems($cartItems);
            } else {
                showDebugError('No cart items found on the page. Please add items to your cart.');
            }
        }
    }
    
    // Extract cart items from a jQuery element
    function extractCartItems($container) {
        const cartItems = [];
        let foundItems = false;
        
        try {
            // Find all cart item rows
            $container.find('tr.cart_item, .woocommerce-cart-form__cart-item').each(function() {
                const $item = $(this);
                const productName = $('.product-name', $item).text().trim() || 
                                $('.product-title', $item).text().trim() ||
                                $('.product__name', $item).text().trim();
                
                if (!productName) {
                    console.warn('Could not find product name for item:', $item);
                    return; // Skip items without a name
                }
                
                // Get quantity
                let quantity = 1;
                const quantityEl = $('.product-quantity .quantity, .product-quantity .qty', $item);
                if (quantityEl.length) {
                    quantity = parseInt(quantityEl.text().trim()) || 
                              parseInt(quantityEl.val()) || 1;
                }
                
                // Get product ID from various possible locations
                let productId = $item.data('product_id') || 
                               $item.find('[data-product_id]').data('product_id') || 
                               $item.attr('class').match(/cart_item_([0-9]+)/)?.[1] ||
                               $item.data('product-id') ||
                               'unknown';
                
                cartItems.push({
                    key: 'cart_item_' + productId + '_' + Date.now(),
                    product_id: productId,
                    quantity: quantity,
                    data: {
                        product_name: productName
                    }
                });
                
                foundItems = true;
            });
        } catch (error) {
            console.error('Error extracting cart items:', error);
        }
        
        if (foundItems) {
            console.log('Extracted cart items:', cartItems);
            processCartItems(cartItems);
        } else {
            showDebugError('Could not extract any cart items from the page.');
        }
    }
    
    // Show debug error
    function showDebugError(message) {
        console.error(message);
        updateDebugPanel(`
            <div class="debug-item stock-error">
                <h4>‚ö†Ô∏è Error Loading Cart Data</h4>
                <div style="margin-top: 5px;">${message}</div>
                <div style="margin-top: 10px;">
                    <p>Debug information:</p>
                    <ul style="margin: 5px 0 0 15px;">
                        <li>Page: ${window.location.href}</li>
                        <li>WC Loaded: ${typeof wc_cart_fragments !== 'undefined' ? 'Yes' : 'No'}</li>
                        <li>jQuery Version: ${$.fn.jquery || 'Not loaded'}</li>
                    </ul>
                </div>
            </div>
        `);
    }
    
    // Initialize debug panel
    function initializeDebugPanel() {
        console.log('Initializing debug panel...');
        updateDebugPanel('<div class="debug-loading">Loading product stock information...</div>');
        
        // Check if WooCommerce is loaded
        if (typeof wc_cart_fragments !== 'undefined') {
            console.log('WooCommerce cart fragments found');
            
            if (wc_cart_fragments.cart && wc_cart_fragments.cart.cart_contents) {
                console.log('Cart contents found, processing items...');
                const cartItems = Object.values(wc_cart_fragments.cart.cart_contents);
                processCartItems(cartItems);
            } else {
                console.log('No cart contents found in fragments, trying AJAX...');
                fetchCartContents();
            }
        } else {
            console.log('WooCommerce cart fragments not found, trying AJAX...');
            fetchCartContents();
        }
    }
    
    // Initialize when document is ready
    $(function() {
        // Wait a bit for WooCommerce to initialize
        setTimeout(initializeDebugPanel, 500);
        
        // Also try after a longer delay in case of slow loading
        setTimeout(initializeDebugPanel, 2000);
        
        // Listen for WooCommerce cart updates
        $(document.body).on('updated_wc_div updated_cart_totals updated_checkout', function() {
            console.log('WooCommerce updated, refreshing debug panel...');
            initializeDebugPanel();
        });
    });
    
    // Log when the order review updates
    $(document.body).on('updated_checkout', function() {
        console.log('Order review updated - refreshing cart data...');
        initializeDebugPanel();
    });
    
    console.groupEnd(); // End main group
});
