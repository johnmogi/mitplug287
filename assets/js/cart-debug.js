jQuery(document).ready(function($) {
    'use strict';

    // Debug object to store information
    window.cartDebug = {
        logs: [],
        log: function(message, data) {
            var timestamp = new Date().toISOString();
            var logEntry = {
                time: timestamp,
                message: message,
                data: data || null
            };
            this.logs.push(logEntry);
            console.log('[' + timestamp + '] ' + message, data || '');
        },
        getLogs: function() {
            return this.logs;
        }
    };

    // Log initial cart state
    cartDebug.log('Initial cart state', {
        cart: typeof wc_cart_fragments_params !== 'undefined' ? wc_cart_fragments_params : 'Not loaded',
        ajaxUrl: typeof wc_add_to_cart_params !== 'undefined' ? wc_add_to_cart_params.ajax_url : 'Not loaded'
    });

    // Monitor form submissions
    $(document).on('submit', 'form.cart', function(e) {
        var $form = $(this);
        var formData = $form.serialize();
        
        cartDebug.log('Form submission started', {
            formData: formData,
            rentalStart: $('#rental_start_date').val(),
            rentalEnd: $('#rental_end_date').val(),
            quantity: $('input.qty', $form).val()
        });

        // Store form data for later reference
        $(this).data('form-data', formData);
    });

    // Monitor AJAX requests
    $(document).ajaxSend(function(event, xhr, settings) {
        if (settings.url && settings.url.includes('wc-ajax=add_to_cart')) {
            cartDebug.log('AJAX Request - Add to Cart', {
                url: settings.url,
                data: settings.data,
                type: settings.type
            });
        }
    });

    // Monitor AJAX responses
    $(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.url && settings.url.includes('wc-ajax=add_to_cart')) {
            try {
                var response = xhr.responseJSON;
                cartDebug.log('AJAX Response - Add to Cart', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    response: response,
                    responseText: xhr.responseText
                });

                // If there's an error, log additional details
                if (xhr.status !== 200 || (response && !response.success)) {
                    cartDebug.log('Add to Cart Error', {
                        status: xhr.status,
                        response: response,
                        requestData: settings.data
                    });
                }
            } catch (e) {
                cartDebug.log('Error parsing AJAX response', e);
            }
        }
    });

    // Monitor cart fragments updates
    $(document).on('updated_cart_totals updated_shipping_method', function() {
        cartDebug.log('Cart updated', {
            cartHash: typeof wc_cart_fragments_params !== 'undefined' ? wc_cart_fragments_params.cart_hash_key : 'N/A',
            fragments: typeof wc_cart_fragments_params !== 'undefined' ? wc_cart_fragments_params.fragment_name : 'N/A'
        });
    });

    // Add debug panel to the page
    function addDebugPanel() {
        if ($('#cart-debug-panel').length) return;

        var $panel = $('<div id="cart-debug-panel" style="position:fixed;bottom:0;right:0;width:100%;max-width:600px;max-height:300px;overflow:auto;background:#fff;border:2px solid #ccc;z-index:9999;padding:10px;font-family:monospace;font-size:12px;"></div>');
        var $toggle = $('<button style="position:absolute;top:5px;right:5px;padding:2px 5px;font-size:10px;">Toggle</button>');
        var $clear = $('<button style="margin-right:10px;padding:2px 5px;font-size:10px;">Clear</button>');
        var $logs = $('<div id="cart-debug-logs" style="margin-top:20px;max-height:250px;overflow-y:auto;"></div>');

        $panel.append('<h3 style="margin:0 0 10px 0;padding:0;font-size:14px;">Cart Debug Panel</h3>');
        $panel.append($clear).append($toggle);
        $panel.append($logs);
        $('body').append($panel);

        // Toggle panel
        $toggle.on('click', function() {
            $logs.slideToggle();
        });

        // Clear logs
        $clear.on('click', function() {
            $logs.empty();
            cartDebug.logs = [];
            cartDebug.log('Logs cleared');
        });

        // Update logs in panel
        function updateLogs() {
            $logs.empty();
            cartDebug.logs.forEach(function(log) {
                var $entry = $('<div style="padding:5px;border-bottom:1px solid #eee;font-family:monospace;font-size:11px;"></div>');
                $entry.append($('<div style="color:#666;">' + log.time + '</div>'));
                $entry.append($('<div style="font-weight:bold;">' + log.message + '</div>'));
                if (log.data) {
                    $entry.append($('<pre style="margin:5px 0 0 0;padding:5px;background:#f5f5f5;max-height:100px;overflow:auto;font-size:10px;white-space:pre-wrap;">' + JSON.stringify(log.data, null, 2) + '</pre>'));
                }
                $logs.prepend($entry);
            });
        }

        // Update logs every second
        setInterval(updateLogs, 1000);
    }

    // Initialize debug panel
    addDebugPanel();
    cartDebug.log('Debug panel initialized');
});
