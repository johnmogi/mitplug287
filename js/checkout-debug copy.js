jQuery(document).ready(function($) {
    // Prevent duplicate execution
    if (window.mitnafunDebugInitialized) {
        console.log('Mitnafun checkout debug already initialized');
        return;
    }
    window.mitnafunDebugInitialized = true;

    // Add visible debug panel
    const debugPanel = $('<div id="mitnafun-debug-panel" style="position: fixed; bottom: 20px; right: 20px; width: 300px; max-height: 400px; overflow-y: auto; background: rgba(255,255,255,0.9); border: 1px solid #ccc; padding: 10px; z-index: 999999; font-size: 12px; box-shadow: 0 0 10px rgba(0,0,0,0.2); direction: rtl;">' +
        '<h3 style="margin: 0 0 10px; font-size: 14px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">בדיקת עגלת השכרה</h3>' +
        '<div id="mitnafun-debug-content"></div>' +
    '</div>');
    $('body').append(debugPanel);

    // Log to console for debugging
    console.group('=== Mitnafun Order Admin: Checkout Debug ===');
    console.log('Debug script loaded successfully');
    $('#mitnafun-debug-content').append('<p style="color: green;">✓ סקריפט בדיקה נטען בהצלחה</p>');
    
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
    
    // Function to extract product ID from cart item with multiple fallback methods
    function extractProductId(item) {
        try {
            // Method 1: Try to get from data-product-id attribute
            let productElement = item.querySelector('[data-product_id]');
            if (productElement && productElement.dataset.product_id) {
                return parseInt(productElement.dataset.product_id, 10);
            }
            
            // Method 2: Try to get from class like 'product-1234'
            const classes = item.className.split(' ');
            for (const cls of classes) {
                if (cls.startsWith('product-')) {
                    const id = parseInt(cls.replace('product-', ''), 10);
                    if (!isNaN(id)) return id;
                }
            }
            
            // Method 3: Try to get from remove link URL
            const removeLink = item.querySelector('.remove');
            if (removeLink && removeLink.href) {
                const match = removeLink.href.match(/[?&]remove_item=([^&]+)/) ||
                             removeLink.href.match(/[?&]product_id=(\d+)/) ||
                             removeLink.href.match(/[?&]add-to-cart=(\d+)/);
                if (match && match[1]) {
                    return parseInt(match[1], 10);
                }
            }
            
            // Method 4: Try to find it in any product link
            const productLinks = item.querySelectorAll('a[href*="product_id="], a[href*="/product/"]');
            for (const link of productLinks) {
                const idMatch = link.href.match(/[?&]product_id=(\d+)/) ||
                               link.href.match(/\/product\/(.*?)\/(\d+)/);
                if (idMatch && idMatch[1]) {
                    return parseInt(idMatch[1], 10);
                }
            }
            
            // Method 5: Try to find hidden inputs with product data
            const hiddenInputs = document.querySelectorAll('input[name*="cart"][type="hidden"]');
            for (const input of hiddenInputs) {
                try {
                    const data = JSON.parse(input.value);
                    if (data && data.product_id) {
                        return parseInt(data.product_id, 10);
                    }
                } catch (e) {
                    // Not valid JSON, ignore
                }
            }
            
            // Method 6: Check if the item has a data-item_key attribute and find it in cart fragments
            const itemKey = item.dataset ? item.dataset.key : null;
            if (itemKey && window.wc_cart_fragments_params && window.wc_cart_fragments_params.cart) {
                const cartData = window.wc_cart_fragments_params.cart;
                if (cartData[itemKey] && cartData[itemKey].product_id) {
                    return parseInt(cartData[itemKey].product_id, 10);
                }
            }
            
            return null;
        } catch (err) {
            console.error('Error extracting product ID:', err);
            return null;
        }
    }
    
    // Function to extract rental dates from cart item with multiple fallback methods
    function extractRentalDates(item) {
        try {
            // Method 1: Try to get from data attribute first
            if (item.dataset && item.dataset.rental_dates) {
                return item.dataset.rental_dates;
            }
            
            // Method 2: Try to find in variation description
            const variationDescription = item.querySelector('.variation-description');
            if (variationDescription && variationDescription.textContent.trim()) {
                return variationDescription.textContent.trim();
            }
            
            // Method 3: Try to find in metadata - commonly used format
            const metaItems = item.querySelectorAll('.wc-item-meta li, .variation dd');
            for (const meta of metaItems) {
                if (meta.textContent.includes('תאריך') || meta.textContent.includes('date') || meta.textContent.includes('Rental')) {
                    return meta.textContent.replace(/^.*?:\s*/, '').trim();
                }
            }
            
            // Method 4: Try to find in any text content using regex pattern for dates
            const datePattern = /\d{2}\.\d{2}\.\d{4}\s*[-–]\s*\d{2}\.\d{2}\.\d{4}/;
            const allText = item.textContent;
            const dateMatch = allText.match(datePattern);
            if (dateMatch) {
                return dateMatch[0];
            }
            
            return 'לא נמצא תאריך';
        } catch (err) {
            console.error('Error extracting rental dates:', err);
            return 'שגיאה במציאת תאריכים';
        }
    }
    
    // Function to extract quantity
    function extractQuantity(item) {
        try {
            // Method 1: Try to get from quantity element
            const qtyElement = item.querySelector('.product-quantity');
            if (qtyElement) {
                const match = qtyElement.textContent.match(/×\s*(\d+)/);
                if (match) {
                    return parseInt(match[1], 10);
                }
            }
            
            // Method 2: Try to get from any element with quantity class
            const qtyElements = item.querySelectorAll('.quantity');
            for (const el of qtyElements) {
                if (el.textContent) {
                    const match = el.textContent.match(/(\d+)/);
                    if (match) {
                        return parseInt(match[1], 10);
                    }
                }
            }
            
            // Method 3: Try to find input with quantity
            const qtyInput = item.querySelector('input.qty');
            if (qtyInput && qtyInput.value) {
                return parseInt(qtyInput.value, 10);
            }
            
            return 1; // Default to 1 if not found
        } catch (err) {
            console.error('Error extracting quantity:', err);
            return 1;
        }
    }
    
    // Function to extract product name
    function extractProductName(item) {
        try {
            // Method 1: Try to get from product-name element
            const nameElement = item.querySelector('.product-name');
            if (nameElement) {
                // Filter out child elements' text
                let name = '';
                for (const node of nameElement.childNodes) {
                    if (node.nodeType === Node.TEXT_NODE) {
                        name += node.textContent.trim();
                    }
                }
                if (name) return name;
                
                // If no direct text node, get the first heading or fallback to full text
                const heading = nameElement.querySelector('a, h3, h4, h5');
                if (heading) return heading.textContent.trim();
                return nameElement.textContent.trim();
            }
            
            // Method 2: Try to get from any element with name in class
            const nameElements = item.querySelectorAll('.name, .item-title, .product-title');
            if (nameElements.length > 0) {
                return nameElements[0].textContent.trim();
            }
            
            return 'מוצר לא ידוע';
        } catch (err) {
            console.error('Error extracting product name:', err);
            return 'שגיאה במציאת שם המוצר';
        }
    }
    
    // Function to wait for checkout elements to be fully loaded
    function waitForCheckout(callback, attempts = 0) {
        const reviewTable = document.querySelector('.woocommerce-checkout-review-order-table');
        
        if (reviewTable && reviewTable.querySelector('.cart_item')) {
            callback();
        } else if (attempts < 20) { // Try for 10 seconds (20 * 500ms)
            setTimeout(() => waitForCheckout(callback, attempts + 1), 500);
        } else {
            console.warn('Checkout review table not found after multiple attempts');
            $('#mitnafun-debug-content').append('<p style="color: red;">⚠️ שולחן סקירת התשלום לא נמצא</p>');
        }
    }
    
    // Process cart items from the DOM
    function processCartItems() {
        // Clear previous content
        $('#mitnafun-debug-content').html('<p style="color: green;">✓ מתחיל סריקת פריטי עגלה</p>');
        console.group('=== Cart Items with Stock Info ===');

        // Get all cart items from the DOM
        const cartItems = document.querySelectorAll('.woocommerce-checkout-review-order-table .cart_item');
        
        if (cartItems.length === 0) {
            console.log('No cart items found in the DOM');
            $('#mitnafun-debug-content').append('<p style="color: orange;">⚠️ לא נמצאו פריטים בעגלה</p>');
            console.groupEnd();
            return;
        }
        
        $('#mitnafun-debug-content').append(`<p>נמצאו ${cartItems.length} פריטים בעגלה</p>`);
        
        // Process each cart item
        for (let i = 0; i < cartItems.length; i++) {
            const item = cartItems[i];
            console.group(`Processing cart item ${i + 1} of ${cartItems.length}`);
            
            try {
                // Extract product info with fallbacks
                const productName = extractProductName(item);
                const productId = extractProductId(item);
                const quantity = extractQuantity(item);
                const rentalDates = extractRentalDates(item);
                
                // Add to debug panel
                $('#mitnafun-debug-content').append(
                    `<div style="margin: 8px 0; padding: 5px; border-left: 3px solid #0073aa;">
                        <strong>${productName}</strong><br>
                        <span style="display: inline-block; margin-right: 10px;">מזהה: ${productId ? productId : '<span style="color:red;">לא נמצא</span>'}</span>
                        <span style="display: inline-block; margin-right: 10px;">כמות: ${quantity}</span><br>
                        <span>תאריכי השכרה: ${rentalDates}</span>
                    </div>`
                );
                
                // Log to console
                console.group(`📦 ${productName}${productId ? ` (ID: ${productId})` : ' (ID: לא נמצא)'}`);
                console.log('Quantity in cart:', quantity);
                console.log('Rental Dates:', rentalDates);
                
                // If we have a valid product ID, get stock info (non-blocking)
                if (productId && !isNaN(productId)) {
                    getProductStockInfo(productId).then(stockInfo => {
                        if (stockInfo) {
                            console.group('Stock Information:');
                            console.log('Total Stock (_initial_stock):', stockInfo.initial_stock || 'Not set');
                            console.log('WooCommerce Stock:', stockInfo.wc_stock || 'Not managed');
                            console.log('Backorders Allowed:', stockInfo.backorders_allowed ? 'Yes' : 'No');
                            console.log('Stock Status:', stockInfo.stock_status || 'N/A');
                            
                            // Calculate available stock
                            if (stockInfo.initial_stock !== undefined) {
                                const available = stockInfo.initial_stock - (stockInfo.held_stock || 0);
                                console.log('Available Stock:', available);
                                
                                // Check if current cart quantity exceeds available stock
                                if (quantity > available) {
                                    console.warn('⚠️ Quantity in cart exceeds available stock!');
                                    $('#mitnafun-debug-content').append(
                                        `<p style="margin-left: 10px; color: red;">⚠️ הכמות בעגלה (${quantity}) עולה על המלאי הזמין (${available})</p>`
                                    );
                                }
                            }
                            
                            console.groupEnd();
                        }
                    }).catch(error => {
                        console.error('Error getting stock info:', error);
                    });
                } else {
                    console.warn('Could not determine product ID for stock check');
                }
                
                console.groupEnd(); // End product group
            } catch (error) {
                console.error('Error processing cart item:', error);
                $('#mitnafun-debug-content').append(`<p style="color: red;">שגיאה בעיבוד פריט ${i+1}: ${error.message}</p>`);
            }
            
            console.groupEnd(); // End processing group
        }
        
        console.groupEnd(); // End cart items group
    }
    
    // Initialize when checkout page is loaded
    waitForCheckout(processCartItems);
    
    // Setup listeners for when cart updates
    $(document.body).on('updated_checkout', function() {
        console.log('Checkout updated, refreshing debug info');
        waitForCheckout(processCartItems);
    });
    
    // Add listener for WooCommerce fragment refresh events
    $(document.body).on('wc_fragments_refreshed wc_fragments_loaded', function() {
        console.log('WooCommerce fragments updated, refreshing debug info');
        setTimeout(processCartItems, 500);
    });
    
    // Add refresh button to debug panel
    $('#mitnafun-debug-panel').append(
        '<button id="mitnafun-refresh-debug" style="margin-top: 10px; padding: 5px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer;">' +
        'רענן מידע עגלה' +
        '</button>'
    );
    
    // Add handler for manual refresh
    $(document).on('click', '#mitnafun-refresh-debug', function() {
        $('#mitnafun-debug-content').append('<p>מרענן מידע...</p>');
        processCartItems();
    });
    
    // Add minimize/maximize functionality
    $('#mitnafun-debug-panel h3').css('cursor', 'pointer').on('click', function() {
        $('#mitnafun-debug-content, #mitnafun-refresh-debug').toggle();
    });
    
    console.log('Mitnafun checkout debug fully initialized');
    console.groupEnd(); // End main group
});
