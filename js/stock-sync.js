(function($) {
    'use strict';

    // Initialize when document is ready
    $(function() {
        console.log('Stock sync script loaded');
        
        // Initialize tooltips
        initTooltips();
        
        // Handle bulk stock sync button click
        $('#sync-stock-btn').on('click', handleBulkSync);
        
        // Handle individual product sync button click with event delegation
        $(document).on('click', '.sync-single-stock:not(.syncing)', handleSingleSync);
        
        // Handle window resize for responsive adjustments
        let resizeTimer;
        $(window).on('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(handleResize, 250);
        });
        
        // Initial call to set mobile class if needed
        handleResize();
    });
    
    /**
     * Handle window resize events for responsive adjustments
     */
    function handleResize() {
        if (window.innerWidth <= 782) {
            $('body').addClass('mitnafun-mobile');
        } else {
            $('body').removeClass('mitnafun-mobile');
        }
    }
    
    /**
     * Check if element is in viewport
     */
    function isElementInViewport(el) {
        if (!el) return false;
        
        const rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
    
    /**
     * Show error message in results container
     */
    function showError($container, message) {
        $container.html(
            '<div class="notice notice-error">' +
            '<p><span class="dashicons dashicons-warning"></span> ' + 
            message + 
            '</p></div>'
        );
    }
    
    /**
     * Update product row with new stock data
     */
    function updateProductRow(productId, stock, status) {
        const $row = $(`button[data-product-id="${productId}"]`).closest('tr');
        
        if ($row.length) {
            // Update WooCommerce stock with animation
            const $stockCell = $row.find('.woocommerce-stock');
            const oldStock = parseInt($stockCell.text()) || 0;
            const newStock = parseInt(stock.woocommerce) || 0;
            
            if (oldStock !== newStock) {
                $stockCell.addClass('updating');
                
                // Animate the number change
                $({ count: oldStock }).animate({ count: newStock }, {
                    duration: 500,
                    easing: 'swing',
                    step: function() {
                        $stockCell.text(Math.round(this.count));
                    },
                    complete: function() {
                        $stockCell.text(newStock).removeClass('updating');
                    }
                });
            }
            
            // Update status with animation
            const $statusBadge = $row.find('.stock-status');
            if ($statusBadge.length) {
                $statusBadge.fadeOut(150, function() {
                    $(this).removeClass('in-stock out-of-stock')
                           .addClass(status.class)
                           .text(status.text)
                           .fadeIn(150);
                });
            }
        }
    }
    
    /**
     * Initialize tooltips for better UX
     */
    function initTooltips() {
        if (typeof mitnafunAdmin !== 'undefined' && mitnafunAdmin.i18n) {
            $('.sync-single-stock').attr('title', mitnafunAdmin.i18n.syncTooltip || 'Sync stock');
        }
        
        if ($.fn.tooltip) {
            $('[title]').tooltip({
                position: { my: 'left+15 center', at: 'right center' },
                tooltipClass: 'mitnafun-tooltip',
                show: { effect: 'fadeIn', duration: 150 },
                hide: { effect: 'fadeOut', duration: 150 }
            });
        }
    }
    
    /**
     * Handle bulk stock synchronization
     */
    function handleBulkSync(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $spinner = $button.siblings('.spinner');
        const $results = $('#sync-results');
        const $resultsContent = $('#sync-results-content');
        
        // Get localized strings with fallbacks
        const i18n = window.mitnafunAdmin?.i18n || {};
        const strings = {
            syncing: i18n.syncing || 'Syncing...',
            syncingInProgress: i18n.syncingInProgress || 'Synchronizing stock data, please wait...',
            productUpdated: i18n.productUpdated || 'product updated',
            productsUpdated: i18n.productsUpdated || 'products updated',
            syncAllStock: i18n.syncAllStock || 'Synchronize All Stock',
            unknownError: i18n.unknownError || 'An unknown error occurred',
            connectionError: i18n.connectionError || 'Connection error:'
        };
        
        // Disable button and show spinner
        $button.prop('disabled', true)
               .addClass('updating-message')
               .find('> span')
               .text(strings.syncing);
        
        $spinner.addClass('is-active');
        
        // Show loading state in results area
        $resultsContent.html(
            '<div class="notice notice-info"><p>' + 
            '<span class="spinner is-active" style="margin: 0 10px 0 0; float: none;"></span> ' +
            strings.syncingInProgress + 
            '</p></div>'
        );
        
        $results.slideDown();
        
        // Scroll to results if not in view
        if (!isElementInViewport($results[0])) {
            $('html, body').animate({
                scrollTop: $results.offset().top - 20
            }, 300);
        }
        
        // Make AJAX request
        $.ajax({
            url: window.ajaxurl || window.mitnafunAdmin?.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mitnafun_bulk_sync_stock',
                _ajax_nonce: window.mitnafunAdmin?.nonce || ''
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    // Show success message
                    $resultsContent.html(
                        '<div class="notice notice-success">' +
                        '<p><span class="dashicons dashicons-yes-alt"></span> ' + 
                        (response.data?.message || 'Stock synchronized successfully') + 
                        '</p><p>' + 
                        (response.data?.updated || 0) + ' ' + 
                        (response.data?.updated === 1 ? strings.productUpdated : strings.productsUpdated) +
                        '</p></div>'
                    );
                    
                    // Update the stock display for each updated product
                    if (response.data?.products?.length > 0) {
                        let updatedCount = 0;
                        const totalProducts = response.data.products.length;
                        
                        // Add progress bar
                        $resultsContent.append(
                            '<div class="progress" style="margin: 15px 0 0; height: 20px; background: #f0f0f1; border-radius: 3px; overflow: hidden;">' +
                            '<div class="progress-bar" role="progressbar" style="width: 0%; height: 100%; background: #2271b1; transition: width 0.3s ease;" ' +
                            'aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div></div>'
                        );
                        
                        // Process updates with a small delay between each to prevent UI freeze
                        const processUpdate = (index) => {
                            if (index < totalProducts) {
                                const product = response.data.products[index];
                                updateProductRow(product.id, product.stock, product.status);
                                updatedCount++;
                                
                                // Update progress
                                const percent = Math.round((updatedCount / totalProducts) * 100);
                                $resultsContent.find('.progress-bar')
                                    .css('width', percent + '%')
                                    .attr('aria-valuenow', percent);
                                
                                // Process next product
                                setTimeout(() => processUpdate(index + 1), 50);
                            }
                        };
                        
                        // Start processing updates
                        processUpdate(0);
                    }
                } else {
                    // Show error message
                    showError($resultsContent, 
                        response?.data?.message || 
                        strings.unknownError
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                showError($resultsContent, 
                    strings.connectionError + ' ' + 
                    (xhr.status ? '(' + xhr.status + ' ' + xhr.statusText + ')' : '')
                );
            },
            complete: function() {
                // Re-enable button and hide spinner
                $button.prop('disabled', false)
                       .removeClass('updating-message')
                       .find('> span')
                       .text(strings.syncAllStock);
                
                $spinner.removeClass('is-active');
            }
        });
    }
    
    /**
     * Handle single product synchronization
     */
    function handleSingleSync(e) {
        e.preventDefault();
        
        const $button = $(this);
        const productId = $button.data('product-id');
        const $row = $button.closest('tr');
        const $result = $button.siblings('.sync-result');
        
        // Get localized strings with fallbacks
        const i18n = window.mitnafunAdmin?.i18n || {};
        const strings = {
            syncing: i18n.syncing || 'Syncing...',
            synced: i18n.synced || 'Synced!',
            sync: i18n.sync || 'Sync',
            syncFailed: i18n.syncFailed || 'Sync failed',
            connectionError: i18n.connectionError || 'Connection error'
        };
        
        // Set loading state
        $button.addClass('syncing').prop('disabled', true);
        $result.removeClass('success error visible').text('');
        
        // Show loading indicator
        $button.html('<span class="screen-reader-text">' + strings.syncing + '</span>');
        
        // Make AJAX request
        $.ajax({
            url: window.ajaxurl || window.mitnafunAdmin?.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mitnafun_sync_single_stock',
                product_id: productId,
                _ajax_nonce: window.mitnafunAdmin?.nonce || ''
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    // Update the row with new stock data
                    updateProductRow(
                        productId, 
                        response.data?.stock || { woocommerce: 0 }, 
                        response.data?.status || { class: '', text: '' }
                    );
                    
                    // Show success message
                    $result.text(strings.synced).addClass('success');
                    
                    // Add animation to the updated row
                    $row.addClass('updated');
                    setTimeout(() => $row.removeClass('updated'), 1500);
                } else {
                    // Show error message
                    $result.text(
                        (response?.data?.message || strings.syncFailed)
                    ).addClass('error');
                }
            },
            error: function(xhr) {
                console.error('AJAX Error:', xhr.statusText);
                $result.text(strings.connectionError).addClass('error');
            },
            complete: function() {
                $button.html(strings.sync).prop('disabled', false);
                // Clear the result message after 3 seconds
                setTimeout(function() {
                    $result.fadeOut();
                }, 3000);
            }
        });
    }
    
    /**
     * Handle single product synchronization
     */
    function handleSingleSync(e) {
        e.preventDefault();
        
        const $button = $(this);
        const productId = $button.data('product-id');
        const $row = $button.closest('tr');
        const $result = $button.siblings('.sync-result');
        
        // Get localized strings with fallbacks
        const i18n = window.mitnafunAdmin?.i18n || {};
        const strings = {
            syncing: i18n.syncing || 'Syncing...',
            synced: i18n.synced || 'Synced!',
            sync: i18n.sync || 'Sync',
            syncFailed: i18n.syncFailed || 'Sync failed',
            connectionError: i18n.connectionError || 'Connection error'
        };
        
        // Set loading state
        $button.addClass('syncing').prop('disabled', true);
        $result.removeClass('success error visible').text('');
        
        // Show loading indicator
        $button.html('<span class="screen-reader-text">' + strings.syncing + '</span>');
        
        // Make AJAX request
        $.ajax({
            url: window.ajaxurl || window.mitnafunAdmin?.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mitnafun_sync_single_stock',
                product_id: productId,
                _ajax_nonce: window.mitnafunAdmin?.nonce || ''
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    // Update the row with new stock data
                    updateProductRow(
                        productId, 
                        response.data?.stock || { woocommerce: 0 }, 
                        response.data?.status || { class: '', text: '' }
                    );
                    
                    // Show success message
                    $result.text(strings.synced).addClass('success');
                    
                    // Add animation to the updated row
                    $row.addClass('updated');
                    setTimeout(() => $row.removeClass('updated'), 1500);
                } else {
                    // Show error message
                    $result.text(
                        response?.data?.message || 
                        strings.syncFailed
                    ).addClass('error');
                }
            },
            error: function(xhr) {
                console.error('AJAX Error:', xhr.statusText);
                $result.text(strings.connectionError).addClass('error');
            },
            complete: function() {
                $button.html(strings.sync).prop('disabled', false);
                // Clear the result message after 3 seconds
                setTimeout(function() {
                    $result.fadeOut();
                }, 3000);
            }
        });
    } // End of handleSingleSync function
    
    // Close the IIFE and document ready
})(jQuery);
