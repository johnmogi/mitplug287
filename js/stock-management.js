jQuery(document).ready(function($) {
    // Handle stock synchronization
    $('#sync-stock-btn').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $results = $('#sync-results');
        var $resultsContent = $('#sync-results-content');
        
        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $results.hide();
        $resultsContent.empty();
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mitnafun_sync_stock',
                nonce: mitnafun_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    var html = '<div class="notice notice-success"><p>' + 
                              response.data.message + '</p></div>';
                    
                    if (response.data.updated_products && response.data.updated_products.length > 0) {
                        html += '<h4>' + mitnafun_admin_vars.updated_products + ':</h4>';
                        html += '<ul style="margin-left: 20px;">';
                        
                        $.each(response.data.updated_products, function(index, product) {
                            html += '<li>' + product.name + ' (ID: ' + product.id + '): ' + 
                                    mitnafun_admin_vars.stock_updated_from + ' ' + 
                                    product.old_stock + ' → ' + product.new_stock + '</li>';
                        });
                        
                        html += '</ul>';
                    }
                    
                    if (response.data.skipped_products && response.data.skipped_products.length > 0) {
                        html += '<h4>' + mitnafun_admin_vars.skipped_products + ':</h4>';
                        html += '<ul style="margin-left: 20px;">';
                        
                        $.each(response.data.skipped_products, function(index, product) {
                            html += '<li>' + product.name + ' (ID: ' + product.id + '): ' + 
                                    product.reason + '</li>';
                        });
                        
                        html += '</ul>';
                    }
                    
                    $resultsContent.html(html);
                } else {
                    $resultsContent.html('<div class="notice notice-error"><p>' + 
                        (response.data && response.data.message ? response.data.message : mitnafun_admin_vars.error_occurred) + 
                        '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                $resultsContent.html('<div class="notice notice-error"><p>' + 
                    mitnafun_admin_vars.error_occurred + ': ' + error + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
                $results.show();
                
                // Scroll to results
                $('html, body').animate({
                    scrollTop: $results.offset().top - 50
                }, 500);
            }
        });
    });
});
