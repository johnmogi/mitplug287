/**
 * Mitnafun Rental Reservation Frontend Script
 * Handles display of product reservation and availability data
 */
jQuery(document).ready(function($) {
    // Check if we're on a product page or using the availability shortcode
    const isProductPage = $('body').hasClass('single-product');
    const hasAvailabilityShortcode = $('.mitnafun-availability').length > 0;
    
    // If neither, exit
    if (!isProductPage && !hasAvailabilityShortcode) {
        return;
    }
    
    // Get product ID from the page or shortcode
    let productId = 0;
    
    if (isProductPage) {
        // Get product ID using the add-to-cart input value
        productId = $('input[name="add-to-cart"]').val();
        
        // Add our availability container if it doesn't exist
        if ($('#mitnafun-availability-container').length === 0) {
            $('.product_meta').after('<div id="mitnafun-availability-container"><h3>Availability</h3><div class="mitnafun-availability-loading">Checking availability...</div></div>');
        }
    } else if (hasAvailabilityShortcode) {
        // Get product ID from the shortcode container
        const $shortcodeContainer = $('.mitnafun-availability');
        if ($shortcodeContainer.length) {
            productId = $shortcodeContainer.data('product-id');
        }
    }
    
    // If we have a product ID, load its data
    if (productId) {
        loadProductAvailability(productId);
    } else if (hasAvailabilityShortcode) {
        // If no specific product ID, load all products
        loadAllProductsAvailability();
    }
    
    /**
     * Load availability for a single product
     */
    function loadProductAvailability(productId, $container = null) {
        if (!$container) {
            $container = isProductPage ? 
                $('#mitnafun-availability-container') : 
                $(`.mitnafun-product-card[data-product-id="${productId}"] .mitnafun-product-availability`);
        }
        
        // Show loading state
        const $loadingEl = $container.find('.mitnafun-availability-loading');
        if ($loadingEl.length === 0) {
            $container.append('<div class="mitnafun-availability-loading">Checking availability...</div>');
        } else {
            $loadingEl.show();
        }
        
        // Make the AJAX request
        $.ajax({
            url: mitnafunFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'mitnafun_get_product_data',
                product_id: productId,
                nonce: mitnafunFrontend.nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderProductAvailability(productId, response.data, $container);
                } else {
                    showError('Error loading product data', $container);
                }
            },
            error: function(xhr, status, error) {
                showError('Error loading product data: ' + error, $container);
            },
            complete: function() {
                $container.find('.mitnafun-availability-loading').hide();
            }
        });
    }
    
    /**
     * Load availability for all products
     */
    function loadAllProductsAvailability() {
        const $container = $('.mitnafun-product-list');
        if ($container.length === 0) return;
        
        // Show loading state for each product card
        $container.find('.mitnafun-product-card').each(function() {
            const $card = $(this);
            const productId = $card.data('product-id');
            const $availabilityContainer = $card.find('.mitnafun-product-availability');
            
            // Show loading state
            $card.find('.mitnafun-loading-small').show();
            
            // Load product data
            loadProductAvailability(productId, $availabilityContainer);
        });
        
        // Show the container
        $container.show().prev('.mitnafun-loading').hide();
    }
    
    /**
     * Render product availability in the container
     */
    function renderProductAvailability(productId, productData, $container) {
        // Clear any existing content
        $container.empty();
        
        // Get the initial stock (total available)
        const initialStock = productData.initial_stock || 0;
        const currentStock = parseInt(productData.stock_quantity) || 0;
        const isLowStock = currentStock <= 2;
        
        // Create the availability HTML
        let html = `
            <div class="mitnafun-availability-info">
                <div class="mitnafun-availability-status">
                    <span class="mitnafun-availability-label">Status:</span>
                    <span class="mitnafun-availability-value ${isLowStock ? 'low-stock' : 'in-stock'}">
                        ${isLowStock ? 'Low Stock' : 'In Stock'}
                    </span>
                </div>
                <div class="mitnafun-availability-quantity">
                    <span class="mitnafun-availability-label">Available:</span>
                    <span class="mitnafun-availability-value ${isLowStock ? 'low-stock' : ''}">
                        ${currentStock} of ${initialStock}
                    </span>
                </div>
            </div>
        `;
        
        // Add rental dates if available
        if (productData.rental_dates && productData.rental_dates.length > 0) {
            // Sort dates to find first and last
            const sortedDates = [...productData.rental_dates].sort((a, b) => 
                new Date(a.start_date) - new Date(b.start_date)
            );
            
            const firstRental = sortedDates[0];
            const lastRental = sortedDates[sortedDates.length - 1];
            
            html += `
                <div class="mitnafun-rental-dates">
                    <div class="mitnafun-rental-dates-title">Rental Period:</div>
                    <div class="mitnafun-rental-dates-list">
                        <div class="mitnafun-rental-date">
                            <span class="mitnafun-availability-label">First Day:</span>
                            <span class="mitnafun-rental-date-range">
                                ${formatDate(firstRental.start_date)}
                            </span>
                        </div>
                        <div class="mitnafun-rental-date">
                            <span class="mitnafun-availability-label">Last Day:</span>
                            <span class="mitnafun-rental-date-range">
                                ${formatDate(lastRental.end_date)}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mitnafun-upcoming-rentals">
                    <div class="mitnafun-rental-dates-title">Upcoming Rentals:</div>
                    <div class="mitnafun-rental-dates-list">
                        ${sortedDates.map(date => `
                            <div class="mitnafun-rental-date">
                                <span class="mitnafun-rental-date-range">
                                    ${formatDate(date.start_date)} - ${formatDate(date.end_date)}
                                </span>
                                <span class="mitnafun-rental-status ${date.status}">
                                    ${formatStatus(date.status)}
                                </span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="mitnafun-no-rentals">
                    No upcoming rentals. Available for booking.
                </div>
            `;
        }
        
        // Add the HTML to the container
        $container.html(html);
        
        // Show the container if it was hidden
        $container.show();
    }
    
    /**
     * Helper function to format stock status
     */
    function formatStockStatus(status) {
        const statusMap = {
            'instock': 'In Stock',
            'outofstock': 'Out of Stock',
            'onbackorder': 'On Backorder'
        };
        return statusMap[status] || status;
    }
    
    /**
     * Helper function to format date
     */
    function formatDate(dateString) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }
    
    /**
     * Helper function to format status
     */
    function formatStatus(status) {
        return status.charAt(0).toUpperCase() + status.slice(1);
    }
    
    /**
     * Show error message
     */
    function showError(message, $container) {
        $container.html(`
            <div class="mitnafun-error">
                <strong>Error:</strong> ${message}
            </div>
        `);
    }
    
    // Check if the theme's date functionality is already present
    const hasExistingDateFunctionality = $('#datepicker-container').length > 0;
    
    // Only add our reservation container if the theme doesn't already handle it
    if (!hasExistingDateFunctionality) {
        // Add reservations container to the product page
        // Prioritize placing after short description, but fallback to placing after the price
        const $shortDescription = $('.woocommerce-product-details__short-description');
        const $price = $('.price');
        
        if ($shortDescription.length) {
            $shortDescription.after('<div id="aviv-reservations" class="aviv-reservations-container"><div class="aviv-loading">Loading reservation data...</div></div>');
        } else if ($price.length) {
            $price.after('<div id="aviv-reservations" class="aviv-reservations-container"><div class="aviv-loading">Loading reservation data...</div></div>');
        }
    }
    
    // Get reserved dates for this product - this will be useful for both our UI and potentially for the theme's date picker
    $.ajax({
        url: avivFrontend.ajaxUrl,
        type: 'POST',
        data: {
            action: 'get_product_reserved_dates',
            product_id: productId,
            nonce: avivFrontend.nonce
        },
        success: function(response) {
            if (response.success && response.data) {
                // If the theme has its own date handling, just make our data available globally
                if (hasExistingDateFunctionality) {
                    // Make our data available to the theme's code if needed
                    window.avivReservationData = response.data;
                    // Don't display our UI
                    return;
                }
                
                // Otherwise, update our UI with reservation data
                displayReservationData(response.data);
            } else if (!hasExistingDateFunctionality) {
                $('#aviv-reservations').html('<p class="aviv-error">Error loading reservation data.</p>');
            }
        },
        error: function(xhr, status, error) {
            if (!hasExistingDateFunctionality) {
                $('#aviv-reservations').html('<p class="aviv-error">Error loading reservation data.</p>');
            }
        }
    });
    
    /**
     * Display reservation data in the product page
     */
    function displayReservationData(data) {
        const $container = $('#aviv-reservations');
        
        if (!$container.length) {
            return;
        }
        
        // Clear loading indicator
        $container.empty();
        
        // Add title
        $container.append('<h3 class="aviv-reservations-title">Product Reservation Status</h3>');
        
        // Upcoming reservations section
        const upcomingDates = data.upcoming_dates || [];
        const reservedDates = data.reserved_dates || [];
        
        $container.append('<div class="aviv-upcoming-dates"></div>');
        const $upcomingContainer = $('.aviv-upcoming-dates');
        
        if (upcomingDates.length > 0) {
            $upcomingContainer.append('<h4>Upcoming Reserved Dates:</h4>');
            
            // Sort upcoming dates by start date
            upcomingDates.sort(function(a, b) {
                return new Date(a.start_date) - new Date(b.start_date);
            });
            
            // Display each upcoming date range
            const $datesList = $('<div class="aviv-dates-list"></div>');
            upcomingDates.forEach(function(dateRange) {
                $datesList.append(`
                    <span class="aviv-reservation-date unavailable">
                        ${dateRange.start_display} - ${dateRange.end_display}
                    </span>
                `);
            });
            
            $upcomingContainer.append($datesList);
            
            // Add note about unavailable dates
            $upcomingContainer.append('<p class="aviv-note">The product is unavailable during these dates.</p>');
        } else {
            $upcomingContainer.append('<p class="aviv-no-reservations">No upcoming reservations. The product is currently available for all dates.</p>');
        }
        
        // Create a simple calendar view
        createCalendarView(reservedDates);
    }
    
    /**
     * Create a calendar view showing current month and reserved dates
     */
    function createCalendarView(reservedDates) {
        const $container = $('#aviv-reservations');
        const today = new Date();
        const currentMonth = today.getMonth();
        const currentYear = today.getFullYear();
        
        // Create reserved dates lookup for faster checking
        const reservedDatesLookup = {};
        
        reservedDates.forEach(function(dateRange) {
            const start = new Date(dateRange.start_date);
            const end = new Date(dateRange.end_date);
            
            // For each day in the range, mark as reserved
            const currentDate = new Date(start);
            while (currentDate <= end) {
                const dateString = currentDate.toISOString().split('T')[0];
                reservedDatesLookup[dateString] = true;
                
                // Move to next day
                currentDate.setDate(currentDate.getDate() + 1);
            }
        });
        
        // Generate calendar for current month
        $container.append('<h4>Availability Calendar (Current Month):</h4>');
        $container.append(generateMonthCalendar(currentMonth, currentYear, reservedDatesLookup));
        
        // Generate calendar for next month
        let nextMonth = currentMonth + 1;
        let nextMonthYear = currentYear;
        
        if (nextMonth > 11) {
            nextMonth = 0;
            nextMonthYear += 1;
        }
        
        $container.append('<h4>Availability Calendar (Next Month):</h4>');
        $container.append(generateMonthCalendar(nextMonth, nextMonthYear, reservedDatesLookup));
    }
    
    /**
     * Generate HTML for a month calendar view
     */
    function generateMonthCalendar(month, year, reservedDatesLookup) {
        const today = new Date();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDayOfMonth = new Date(year, month, 1).getDay();
        
        // Month names
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        
        // Day names
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        // Start building calendar
        let calendarHtml = `
            <div class="aviv-month-name">${monthNames[month]} ${year}</div>
            <table class="aviv-calendar">
                <thead>
                    <tr>
                        ${dayNames.map(day => `<th>${day}</th>`).join('')}
                    </tr>
                </thead>
                <tbody>
        `;
        
        // Add empty cells for days before the first day of the month
        let dayCount = 1;
        
        for (let week = 0; week < 6; week++) {
            if (dayCount > daysInMonth) break;
            
            calendarHtml += '<tr>';
            
            for (let dayOfWeek = 0; dayOfWeek < 7; dayOfWeek++) {
                if ((week === 0 && dayOfWeek < firstDayOfMonth) || dayCount > daysInMonth) {
                    // Empty cell
                    calendarHtml += '<td></td>';
                } else {
                    // Check if date is reserved
                    const date = new Date(year, month, dayCount);
                    const dateString = date.toISOString().split('T')[0];
                    const isReserved = reservedDatesLookup[dateString];
                    const isToday = date.toDateString() === today.toDateString();
                    
                    let classes = 'day';
                    if (isReserved) classes += ' reserved';
                    else classes += ' available';
                    if (isToday) classes += ' today';
                    
                    calendarHtml += `<td class="${classes}">${dayCount}</td>`;
                    dayCount++;
                }
            }
            
            calendarHtml += '</tr>';
        }
        
        calendarHtml += `
                </tbody>
            </table>
            <div class="aviv-calendar-legend">
                <span class="aviv-reservation-date">Available</span>
                <span class="aviv-reservation-date unavailable">Reserved</span>
            </div>
        `;
        
        return calendarHtml;
    }
});
