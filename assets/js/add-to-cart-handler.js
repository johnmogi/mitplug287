jQuery(document).ready(function($) {
    'use strict';

    // Override the add to cart form submission
    $(document).on('submit', 'form.cart', function(e) {
        e.preventDefault(); // Prevent default form submission
        
        var $form = $(this);
        var $button = $form.find('.single_add_to_cart_button');
        
        // Disable button to prevent multiple clicks
        $button.prop('disabled', true).addClass('loading');
        
        // Get the form data
        var formData = $form.serialize();
        
        // Log the form data for debugging
        console.log('Form data:', formData);
        
        // Get the product ID from the button value
        var product_id = $button.val();
        
        // Get the quantity
        var quantity = $form.find('input.qty').val() || 1;
        
        // Get rental dates
        var rental_start_date = $form.find('input[name="rental_start_date"]').val();
        var rental_end_date = $form.find('input[name="rental_end_date"]').val();
        
        // Prepare the data for AJAX
        var data = {
            action: 'woocommerce_ajax_add_to_cart',
            product_id: product_id,
            product_sku: '',
            quantity: quantity,
            rental_start_date: rental_start_date,
            rental_end_date: rental_end_date,
            variation_id: 0,
            variation: []
        };
        
        // Add any additional form data
        $form.serializeArray().forEach(function(field) {
            if (field.name && !data[field.name]) {
                data[field.name] = field.value;
            }
        });
        
        // Make the AJAX request
        $.ajax({
            type: 'POST',
            url: wc_add_to_cart_params.ajax_url,
            data: data,
            // Ensure we're using the right action
            beforeSend: function() {
                // Make sure action is set correctly for our handler
                if (data.action !== 'woocommerce_ajax_add_to_cart') {
                    data.action = 'woocommerce_ajax_add_to_cart';
                }
                
                // Log attempt to console
                console.log('Sending add to cart request:', data);
                
                // Temporarily store cart data in sessionStorage as backup
                try {
                    sessionStorage.setItem('wc_cart_backup', JSON.stringify(data));
                } catch(e) {
                    console.error('Could not store cart backup', e);
                }
            },
            success: function(response) {
                if (response.error && response.product_url) {
                    // If there's an error but NOT empty cart error, redirect to the product page
                    if (!response.message || response.message.indexOf('empty') === -1) {
                        window.location = response.product_url;
                        return;
                    } else {
                        // If it's an empty cart error, try again with a delay
                        setTimeout(function() {
                            // Try again with the original data
                            $form.trigger('submit');
                        }, 1000);
                        return;
                    }
                }
                
                // If successful, update fragments
                if (response.fragments) {
                    // Check if fragments are valid before applying
                    var fragmentsValid = false;
                    
                    try {
                        // Basic validation of fragments
                        $.each(response.fragments, function(key, value) {
                            if (typeof key === 'string' && typeof value === 'string' && value.length > 0) {
                                fragmentsValid = true;
                                $(key).replaceWith(value);
                            }
                        });
                        
                        // If no valid fragments, force refresh
                        if (!fragmentsValid) {
                            console.warn('Invalid fragments received, will refresh cart manually');
                            $('body').trigger('wc_fragment_refresh');
                        }
                    } catch(e) {
                        console.error('Error processing fragments:', e);
                        // Force refresh on error
                        $('body').trigger('wc_fragment_refresh');
                    }
                }
                
                // Update cart count
                if (response.cart_hash) {
                    $('.cart-contents-count').text(response.cart_hash);
                }
                
                // Show added to cart message
                if (response.message) {
                    // You might want to show a nice notification here
                    console.log('Product added to cart:', response.message);
                }
                
                // Re-enable the button
                $button.prop('disabled', false).removeClass('loading');
                
                // Optional: Show a success message
                showAddedToCartMessage('המוצר נוסף בהצלחה לסל הקניות');
                
            },
            error: function(xhr, status, error) {
                console.error('Error adding to cart:', error);
                // Re-enable the button on error
                $button.prop('disabled', false).removeClass('loading');
                
                // Show error message
                showAddedToCartMessage('אירעה שגיאה בהוספת המוצר לסל', 'error');
                
                // Try to recover - attempt to manually update fragments
                setTimeout(function() {
                    $('body').trigger('wc_fragment_refresh');
                }, 1000);
            }
        });
        
        return false;
    });
    
    // Function to show added to cart message
    function showAddedToCartMessage(message, type = 'success') {
        // Remove any existing messages
        $('.wc-ajax-message').remove();
        
        // Create message element
        var $message = $('<div class="woocommerce-message wc-ajax-message">' + message + '</div>');
        
        // Add appropriate class based on message type
        if (type === 'error') {
            $message.removeClass('woocommerce-message').addClass('woocommerce-error');
        }
        
        // Add close button
        $message.append('<span class="close-message">×</span>');
        
        // Add to page
        $message.prependTo('.woocommerce-notices-wrapper').hide().fadeIn(300);
        
        // Auto-hide after 5 seconds
        var messageTimer = setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Close on click
        $message.on('click', '.close-message', function() {
            clearTimeout(messageTimer);
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    // Handle direct clicks on add to cart buttons
    $(document).on('click', '.single_add_to_cart_button:not(.btn-redirect)', function(e) {
        e.preventDefault();
        $(this).closest('form').trigger('submit');
    });
    
    // Handle redirect buttons (for direct checkout)
    $(document).on('click', '.btn-redirect', function(e) {
        e.preventDefault();
        
        var $form = $(this).closest('form');
        var $button = $(this);
        
        // Disable button to prevent multiple clicks
        $button.prop('disabled', true).addClass('loading');
        
        // Add hidden input for redirect to checkout
        if ($form.find('input[name="redirect"]').length) {
            $form.find('input[name="redirect"]').val('checkout');
        } else {
            $form.append('<input type="hidden" name="redirect" value="checkout">');
        }
        
        // Get the form data
        var formData = $form.serialize();
        
        // Make sure we have the product ID
        var product_id = $button.val();
        var quantity = $form.find('input.qty').val() || 1;
        var rental_start_date = $form.find('input[name="rental_start_date"]').val();
        var rental_end_date = $form.find('input[name="rental_end_date"]').val();
        
        // Prepare the data for AJAX
        var data = {
            action: 'woocommerce_ajax_add_to_cart',
            product_id: product_id,
            quantity: quantity,
            rental_start_date: rental_start_date,
            rental_end_date: rental_end_date,
            redirect_to_checkout: true  // Special flag to indicate redirection
        };
        
        // Add any additional form data
        $form.serializeArray().forEach(function(field) {
            if (field.name && !data[field.name]) {
                data[field.name] = field.value;
            }
        });
        
        // Make the AJAX request
        $.ajax({
            type: 'POST',
            url: wc_add_to_cart_params.ajax_url,
            data: data,
            success: function(response) {
                // If there's an error, show it
                if (response.error) {
                    showAddedToCartMessage('אירעה שגיאה בהוספת המוצר לסל', 'error');
                    $button.prop('disabled', false).removeClass('loading');
                    return;
                }
                
                // If successful, redirect to checkout
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                } else {
                    // Fallback to default checkout URL
                    window.location.href = wc_add_to_cart_params.cart_url + '?checkout';
                }
            },
            error: function() {
                // On error, enable the button and show message
                $button.prop('disabled', false).removeClass('loading');
                showAddedToCartMessage('אירעה שגיאה בהוספת המוצר לסל', 'error');
            }
        });
    });
});
