jQuery(document).ready(function($) {
    // Handle bulk stock sync
    $('#sync-all-stock').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        
        if (!confirm('Are you sure you want to sync all product stocks? This will update WooCommerce stock levels to match the total stock values.')) {
            return;
        }
        
        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mitnafun_bulk_sync_stock',
                nonce: mitnafun_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    // Reload the page to show updated stock values
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error occurred'));
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Handle individual product stock sync
    $(document).on('click', '.sync-single-stock', function() {
        var $button = $(this);
        var productId = $button.data('product-id');
        var $row = $button.closest('tr');
        
        $button.prop('disabled', true).text('Syncing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mitnafun_sync_single_stock',
                product_id: productId,
                nonce: mitnafun_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the row with new stock data
                    $row.find('.woocommerce-stock').text(response.data.woocommerce_stock);
                    $row.find('.stock-status').text(response.data.stock_status).removeClass('in-stock out-of-stock')
                        .addClass(response.data.stock_status === 'In Stock' ? 'in-stock' : 'out-of-stock');
                    
                    // Show success message
                    $row.find('.sync-result')
                        .text('Synced successfully')
                        .removeClass('error')
                        .fadeIn()
                        .delay(2000)
                        .fadeOut();
                } else {
                    $row.find('.sync-result')
                        .text('Error: ' + (response.data.message || 'Unknown error'))
                        .addClass('error')
                        .fadeIn();
                }
            },
            error: function() {
                $row.find('.sync-result')
                    .text('Error: AJAX request failed')
                    .addClass('error')
                    .fadeIn();
            },
            complete: function() {
                $button.prop('disabled', false).text('Sync');
            }
        });
    });
});
