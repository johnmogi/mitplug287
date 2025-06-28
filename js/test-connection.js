jQuery(document).ready(function($) {
    $('#run-test').on('click', function(e) {
        e.preventDefault();
        
        const productId = $('#test-product-select').val();
        
        if (!productId) {
            alert('Please select a product to test');
            return;
        }
        
        // Show loading state
        const $button = $(this);
        const originalText = $button.text();
        $button.prop('disabled', true).text('Testing...');
        
        // Clear previous results
        $('#test-output').html('Running tests...');
        $('#test-results').show();
        $('#test-details').hide();
        
        // Make the AJAX request
        $.ajax({
            url: mitnafunTest.ajax_url,
            type: 'POST',
            data: {
                action: 'mitnafun_test_connection',
                nonce: mitnafunTest.nonce,
                product_id: productId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayTestResults(response.data);
                } else {
                    displayTestError(response.data || { message: 'An unknown error occurred' });
                }
            },
            error: function(xhr, status, error) {
                displayTestError({
                    message: 'AJAX Error: ' + error,
                    status: xhr.status,
                    statusText: xhr.statusText
                });
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Toggle detailed view
    $('#show-details').on('click', function(e) {
        e.preventDefault();
        $('#test-details').toggle();
    });
    
    function displayTestResults(data) {
        let output = '';
        let details = '';
        
        // Basic connection test
        output += '<div class="test-result">';
        output += '<strong>✓ Connection to WordPress:</strong> <span class="success">Success</span><br>';
        output += '<strong>✓ WooCommerce Active:</strong> ' + 
                 (data.server_info.woocommerce_version !== 'Not active' ? 
                 '<span class="success">Yes (v' + data.server_info.woocommerce_version + ')</span>' : 
                 '<span class="error">No</span>') + '<br>';
        
        // Product data test
        if (data.product) {
            output += '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
            output += '<strong>Product Data:</strong><br>';
            output += '- ID: ' + data.product.id + '<br>';
            output += '- Name: ' + data.product.name + '<br>';
            output += '- Type: ' + data.product.type + '<br>';
            output += '- Price: ' + data.product.price + '<br>';
            output += '- Stock Status: ' + data.product.stock_status + '<br>';
            output += '- Stock Qty: ' + (data.product.stock_quantity || 'N/A') + '<br>';
            output += '- Rental Dates: ' + (data.product.rental_dates && data.product.rental_dates.length ? data.product.rental_dates.length + ' entries' : 'None') + '<br>';
            output += '</div>';
            
            // Add product data to details
            details += '<h4>Product Data:</h4>';
            details += JSON.stringify(data.product, null, 2) + '\n\n';
        }
        
        // Product page test
        if (data.product_page) {
            output += '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
            output += '<strong>Product Page Access:</strong> ';
            
            if (data.product_page.accessible) {
                output += '<span class="success">Accessible (Status: ' + data.product_page.status_code + ')</span><br>';
                output += 'URL: <a href="' + data.product_page.url + '" target="_blank">' + data.product_page.url + '</a>';
            } else {
                output += '<span class="error">Not Accessible (Status: ' + data.product_page.status_code + ')</span><br>';
                if (data.product_page.url) {
                    output += 'URL: ' + data.product_page.url;
                } else {
                    output += 'No product URL found';
                }
            }
            output += '</div>';
            
            // Add product page data to details
            details += '<h4>Product Page Data:</h4>';
            details += JSON.stringify(data.product_page, null, 2) + '\n\n';
        }
        
        // Server info
        details += '<h4>Server Information:</h4>';
        details += JSON.stringify(data.server_info, null, 2) + '\n\n';
        
        // Display the results
        $('#test-output').html(output);
        $('#detailed-output').text(details);
        $('#test-details').show();
    }
    
    function displayTestError(error) {
        let output = '<div class="error-message">';
        output += '<strong>✗ Test Failed</strong><br>';
        output += 'Error: ' + (error.message || 'Unknown error') + '<br>';
        
        if (error.status) {
            output += 'Status: ' + error.status + ' ' + (error.statusText || '') + '<br>';
        }
        
        if (error.data) {
            output += '<pre style="white-space: pre-wrap; background: #f5f5f5; padding: 10px; border-radius: 3px; margin-top: 10px;">' + 
                    JSON.stringify(error.data, null, 2) + '</pre>';
        }
        
        output += '</div>';
        
        $('#test-output').html(output);
        $('#test-details').hide();
    }
});
