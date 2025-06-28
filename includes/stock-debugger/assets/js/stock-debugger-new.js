/**
 * Stock Debugger JavaScript
 * Handles the rental calendar debugging functionality
 */

(function($) {
    'use strict';

    /**
     * Format date as YYYY-MM-DD
     * @param {Date|string} date - Date to format (Date object or date string)
     * @return {string} Formatted date string in YYYY-MM-DD format
     */
    function formatDate(date) {
        if (!date) return '';
        
        // If input is already a string in DD.MM.YYYY format, convert it to Date first
        if (typeof date === 'string' && date.includes('.')) {
            var parts = date.split('.');
            if (parts.length === 3) {
                date = new Date(parts[2], parts[1] - 1, parts[0]);
            }
        }
        
        // If we still don't have a valid Date object, try to create one
        if (!(date instanceof Date) || isNaN(date)) {
            date = new Date(date);
            if (isNaN(date.getTime())) return '';
        }
        
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    /**
     * Parse date string in DD.MM.YYYY or YYYY-MM-DD format
     * @param {string} dateStr - Date string to parse
     * @return {Date|null} Parsed date or null if invalid
     */
    function parseDate(dateStr) {
        if (!dateStr) return null;
        
        try {
            // Handle DD.MM.YYYY format
            if (dateStr.includes('.')) {
                var parts = dateStr.split('.');
                if (parts.length === 3) {
                    // Ensure we have valid date parts
                    var day = parseInt(parts[0], 10);
                    var month = parseInt(parts[1], 10) - 1; // Months are 0-based in JS
                    var year = parseInt(parts[2], 10);
                    
                    // Basic validation
                    if (day < 1 || day > 31 || month < 0 || month > 11 || year < 1000) {
                        console.warn('Invalid date parts:', { day: day, month: month, year: year });
                        return null;
                    }
                    
                    var date = new Date(year, month, day);
                    
                    // Check if the date is valid (handles cases like Feb 31)
                    if (date.getDate() === day && date.getMonth() === month && date.getFullYear() === year) {
                        return date;
                    }
                    
                    console.warn('Invalid date after parsing:', dateStr, '->', date);
                    return null;
                }
            }
            
            // Handle YYYY-MM-DD format or other formats
            var date = new Date(dateStr);
            return isNaN(date.getTime()) ? null : date;
            if (isNaN(date.getTime())) return null;
            
            return date;
        } catch (error) {
            console.error('Error parsing date:', dateStr, error);
            return null;
        }
    }
    
    /**
     * Highlight calendar dates based on reservations
     * @param {Object} dateReservations - Object with date reservations data
     * @param {number} stock - Total available stock
     */
    function highlightCalendarDates(dateReservations, stock) {
        if (!dateReservations || !$) {
            console.warn('No date reservations data or jQuery not available');
            return;
        }
        
        console.log('Highlighting calendar dates with stock:', stock, 'reservations:', dateReservations);
        
        // First, remove any existing highlights
        $('.day-cell').removeClass('fully-booked partially-booked');
        
        // Apply new highlights
        Object.keys(dateReservations).forEach(function(dateKey) {
            try {
                var data = dateReservations[dateKey];
                if (!data) {
                    console.warn('No data for date key:', dateKey);
                    return;
                }
                
                // Use formatted date for comparison if available, otherwise use the key
                var dateToCheck = data.formatted_start || data.start || dateKey;
                
                // Find all day cells that match this date
                var $dateCells = $('.day-cell').filter(function() {
                    var cellDate = $(this).data('date');
                    if (!cellDate) return false;
                    
                    // Format both dates consistently for comparison
                    var formattedCellDate = formatDate(cellDate);
                    var formattedReservationDate = formatDate(dateToCheck);
                    
                    return formattedCellDate === formattedReservationDate;
                });
                
                if ($dateCells.length) {
                    var isFullyBooked = data.isFullyBooked || (data.count >= stock);
                    var title = isFullyBooked 
                        ? 'Fully booked' 
                        : (data.count || 0) + ' of ' + (stock || 0) + ' booked';
                    
                    if (isFullyBooked) {
                        $dateCells.addClass('fully-booked');
                    } else {
                        $dateCells.addClass('partially-booked');
                    }
                    $dateCells.attr('title', title);
                    
                    console.log('Highlighted date:', dateToCheck, {
                        cells: $dateCells.length,
                        isFullyBooked: isFullyBooked,
                        count: data.count,
                        stock: stock
                    });
                } else {
                    console.warn('No calendar cells found for date:', dateToCheck, 'Key:', dateKey);
                }
            } catch (error) {
                console.error('Error highlighting date:', dateKey, 'Error:', error);
            }
        });
    }
    
    /**
     * Format a date string for display
     * @param {string} dateStr - Date string in YYYY-MM-DD format
     * @return {string} Formatted date string
     */
    function formatDisplayDate(dateStr) {
        if (!dateStr) return '';
        
        try {
            var parts = dateStr.split('-');
            if (parts.length === 3) {
                return parts[2] + '.' + parts[1] + '.' + parts[0];
            }
            return dateStr;
        } catch (e) {
            console.error('Error formatting date:', dateStr, e);
            return dateStr;
        }
    }
    
    /**
     * Render the stock and reservation data in the debug panel
     */
    function renderStockData() {
        if (!window.rentalReservedData) {
            console.warn('No rental reservation data available');
            return;
        }
        
        var data = window.rentalReservedData;
        var $content = $('.debug-content');
        
        // Clear existing content
        $content.empty();
        
        // Add summary section
        var summaryHtml = `
            <div class="debug-section">
                <h3>Stock & Availability Summary</h3>
                <div class="debug-grid">
                    <div class="debug-item">
                        <span class="label">Product:</span>
                        <span class="value">${data.product_name} (ID: ${data.product_id})</span>
                    </div>
                    <div class="debug-item">
                        <span class="label">Initial Stock:</span>
                        <span class="value">${data.initial_stock} units</span>
                    </div>
                    <div class="debug-item">
                        <span class="label">Current Available:</span>
                        <span class="value">${data.current_stock} units (of ${data.initial_stock})</span>
                    </div>
                    <div class="debug-item">
                        <span class="label">Current Time:</span>
                        <span class="value">${data.current_date}</span>
                    </div>
                </div>
                <div class="debug-grid">
                    <div class="debug-item">
                        <span class="label">Active Reservations:</span>
                        <span class="value">${data.summary.total_reserved_dates}</span>
                    </div>
                    <div class="debug-item">
                        <span class="label">Fully Booked Dates:</span>
                        <span class="value">${data.summary.fully_booked_dates}</span>
                    </div>
                    <div class="debug-item">
                        <span class="label">Available Dates:</span>
                        <span class="value">${data.summary.available_dates}</span>
                    </div>
                </div>
            </div>
        `;
        
        // Add date availability section
        var availabilityHtml = `
            <div class="debug-section">
                <h3>Date Availability</h3>
                <div class="debug-scrollable">
                    <table class="debug-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reserved</th>
                                <th>Available</th>
                                <th>Remaining</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        // Add rows for each date
        Object.entries(data.date_availability).forEach(([date, info]) => {
            var remaining = data.initial_stock - info.reserved;
            var statusClass = info.is_fully_booked ? 'status-fully-booked' : 
                                 remaining < data.initial_stock ? 'status-partial' : 'status-available';
            var statusText = info.is_fully_booked ? 'מלא לחלוטין' : 
                                 remaining < data.initial_stock ? 'מאויש חלקית' : 'זמין';
            
            availabilityHtml += `
                <tr class="${statusClass}">
                    <td>${formatDisplayDate(date)}</td>
                    <td>${info.reserved} יחידות</td>
                    <td>${info.available} יחידות</td>
                    <td>${remaining} מתוך ${data.initial_stock}</td>
                    <td>${statusText}</td>
                </tr>
            `;
        });
        
        availabilityHtml += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        // Add reservations section
        var reservationsHtml = `
            <div class="debug-section">
                <h3>Active Reservations</h3>
                <div class="debug-scrollable">
                    <table class="debug-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        // Add rows for each reservation
        data.reservations.forEach(reservation => {
            reservationsHtml += `
                <tr>
                    <td>${reservation.order_id}</td>
                    <td>${reservation.status}</td>
                    <td>${formatDisplayDate(reservation.start)}</td>
                    <td>${formatDisplayDate(reservation.end)}</td>
                    <td>${reservation.quantity}</td>
                </tr>
            `;
        });
        
        reservationsHtml += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        // Combine all sections
        $content.append(summaryHtml + availabilityHtml + reservationsHtml);
    }
    
    /**
     * Set up the debug panel with reservation data
     */
    function setupDebugPanel() {
        var $debugPanel = $('#stock-debugger');
        if (!$debugPanel.length) {
            return;
        }
        
        try {
            // Add click handler for close button
            $debugPanel.find('.debug-close').on('click', function() {
                $debugPanel.toggleClass('collapsed');
            });
            
            // Add click handler for refresh button
            $('#refresh-debug-data').on('click', function() {
                location.reload();
            });
            
            // Set up debug mode toggle
            $('#toggle-debug-mode').on('click', function() {
                $('body').toggleClass('debug-mode');
                $(this).text(function(i, text) {
                    return text === 'Enable Debug Mode' ? 'Disable Debug Mode' : 'Enable Debug Mode';
                });
            });
            
            // Render the stock and reservation data
            renderStockData();
            
            console.log('Stock Debugger: Debug panel initialized');
        } catch (error) {
            console.error('Error initializing debug panel:', error);
        }
    }
    
    /**
     * Set up event listeners for calendar interaction
     * @param {Object} dateReservations - Object with date reservations data
     * @param {number} stock - Total available stock
     */
    function setupEventListeners(dateReservations, stock) {
        if (!dateReservations) return;
        
        try {
            // Handle Ctrl+Click on calendar dates
            $(document).on('click', '.day-cell', function(e) {
                if (e.ctrlKey) {
                    e.preventDefault();
                    var $cell = $(this);
                    var date = $cell.data('date');
                    
                    if (!date) return;
                    
                    var dateObj = new Date(date);
                    if (isNaN(dateObj.getTime())) return;
                    
                    var dateStr = formatDate(dateObj);
                    if (!dateStr) return;
                    
                    var reservation = dateReservations[dateStr];
                    
                    if (reservation) {
                        var statuses = [];
                        if (reservation.statuses && typeof reservation.statuses.forEach === 'function') {
                            reservation.statuses.forEach(function(status) {
                                statuses.push(status);
                            });
                        }
                        
                        alert('Date: ' + dateStr + '\n' +
                              'Booked: ' + (reservation.count || 0) + ' of ' + (stock || 0) + '\n' +
                              'Status: ' + (statuses.length ? statuses.join(', ') : 'N/A') + '\n' +
                              'Fully Booked: ' + (reservation.isFullyBooked ? 'Yes' : 'No'));
                    } else {
                        alert('Date: ' + dateStr + '\nNo reservations found');
                    }
                }
            });
            
            // Add keyboard shortcut (Ctrl+Shift+D) to toggle debug mode
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.shiftKey && e.key && e.key.toLowerCase() === 'd') {
                    e.preventDefault();
                    var $toggle = $('#toggle-debug-mode');
                    if ($toggle.length) {
                        $toggle.trigger('click');
                    }
                }
            });
            
            console.log('Stock Debugger: Event listeners set up');
        } catch (error) {
            console.error('Error setting up event listeners:', error);
        }
    }
    
    /**
     * Get stock quantity from the DOM
     * @return {number} The stock quantity or 0 if not found
     */
    function getStockFromDOM() {
        try {
            var stockText = $('.stock-availability').text().trim();
            var stockMatch = stockText && stockText.match(/(\d+)/);
            var stock = stockMatch ? parseInt(stockMatch[1], 10) : 0;
            console.log('Stock Debugger: Found stock in DOM:', stock);
            return stock;
        } catch (error) {
            console.error('Error getting stock from DOM:', error);
            return 0;
        }
    }
    
    /**
     * Initialize the stock debugger
     */
    function initStockDebugger() {
        try {
            if (!window.rentalReservedData) {
                console.warn('No rental reservation data available');
                return;
            }
            
            var data = window.rentalReservedData;
            var $content = $('.debug-content');
            
            // Clear existing content
            $content.empty();
            
            // Get initial stock from DOM
            var stock = getStockFromDOM();
            var reservedData = window.rentalReservedData || [];
            var dateReservations = {};
            
            console.log('Stock Debugger: Initializing with stock', stock, 'and', reservedData.length, 'reservations');
            
            // Process reservations
            reservedData.forEach(function(item) {
                try {
                    if (!item) return;
                    
                    var startDate = parseDate(item.start);
                    var endDate = parseDate(item.end);
                    
                    // Skip if dates are invalid
                    if (!startDate || !endDate) return;
                    
                    // Mark each date in the range
                    var currentDate = new Date(startDate);
                    while (currentDate <= endDate) {
                        var dateStr = formatDate(currentDate);
                        if (dateStr) {
                            // Initialize date in reservations if not exists
                            if (!dateReservations[dateStr]) {
                                dateReservations[dateStr] = {
                                    count: 0,
                                    isFullyBooked: false
                                };
                            }
                            
                            // Increment reservation count
                            dateReservations[dateStr].count += item.quantity || 1;
                            
                            // Check if this date is fully booked
                            if (dateReservations[dateStr].count >= stock) {
                                dateReservations[dateStr].isFullyBooked = true;
                            }
                        }
                        
                        // Move to next day
                        currentDate.setDate(currentDate.getDate() + 1);
                    }
                } catch (error) {
                    console.error('Error processing reservation:', item, error);
                }
            });
            
            // Highlight calendar dates
            highlightCalendarDates(dateReservations, stock);
            
            // Set up event listeners
            setupEventListeners(dateReservations, stock);
            
            console.log('Stock Debugger: Initialization complete');
        } catch (error) {
            console.error('Error in initStockDebugger:', error);
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        try {
            // Initial debugger setup
            initStockDebugger();
            
            // Set up MutationObserver to watch for stock changes
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                        var newStock = getStockFromDOM();
                        if (newStock !== window.lastStock) {
                            console.log('Stock changed from', window.lastStock, 'to', newStock);
                            window.lastStock = newStock;
                            // Reinitialize debugger with new stock value
                            initStockDebugger();
                        }
                    }
                });
            });
            
            // Start observing the stock element for changes
        var stockElement = document.querySelector('.stock-availability');
        if (stockElement) {
            observer.observe(stockElement, {
                childList: true,
                subtree: true,
                characterData: true
            });
        }
    });

})(jQuery);
