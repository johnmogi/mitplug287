/**
 * Stock Debugger JavaScript
 * Handles the rental calendar debugging functionality
 */

(function($) {
    'use strict';

    /**
     * Format date as YYYY-MM-DD
     * @param {Date} date - Date to format
     * @return {string} Formatted date string
     */
    function formatDate(date) {
        if (!(date instanceof Date) || isNaN(date)) {
            return '';
        }
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    /**
     * Parse date string in DD.MM.YYYY format
     * @param {string} dateStr - Date string to parse
     * @return {Date|null} Parsed date or null if invalid
     */
    function parseDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        
        try {
            var parts = dateStr.split('.');
            if (parts.length !== 3) {
                return null;
            }
            
            var day = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10) - 1; // Months are 0-based in JS
            var year = parseInt(parts[2], 10);
            
            // Validate date components
            if (isNaN(day) || isNaN(month) || isNaN(year)) {
                return null;
            }
            if (day < 1 || day > 31) {
                return null;
            }
            if (month < 0 || month > 11) {
                return null;
            }
            
            var date = new Date(year, month, day);
            
            // Check if date is valid
            if (isNaN(date.getTime())) {
                return null;
            }
            
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
            return;
        }
        
        try {
            // First, remove any existing highlights
            $('.day-cell').removeClass('fully-booked partially-booked');
            
            // Apply new highlights
            Object.keys(dateReservations).forEach(function(date) {
                var data = dateReservations[date];
                if (!data) {
                    return;
                }
                
                // Escape dots in the date string for jQuery selector
                var selectorDate = date.replace(/\./g, '\\\\');
                var $dateCell = $('.day-cell[data-date*="' + selectorDate + '"]');
                
                if ($dateCell.length) {
                    if (data.isFullyBooked) {
                        $dateCell.addClass('fully-booked');
                        $dateCell.attr('title', 'Fully booked');
                    } else if (data.count > 0) {
                        $dateCell.addClass('partially-booked');
                        $dateCell.attr('title', data.count + ' of ' + stock + ' booked');
                    }
                }
            });
        } catch (error) {
            console.error('Error highlighting calendar dates:', error);
        }
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
        if (!dateReservations) {
            return;
        }
        
        try {
            // Handle Ctrl+Click on calendar dates
            $(document).on('click', '.day-cell', function(e) {
                if (e.ctrlKey) {
                    e.preventDefault();
                    var $cell = $(this);
                    var date = $cell.data('date');
                    
                    if (!date) {
                        return;
                    }
                    
                    var dateObj = new Date(date);
                    if (isNaN(dateObj.getTime())) {
                        return;
                    }
                    
                    var dateStr = formatDate(dateObj);
                    if (!dateStr) {
                        return;
                    }
                    
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
     * Initialize the stock debugger
     */
    function initStockDebugger() {
        try {
            // Check if we're on a product page and have the debug panel
            if (!$('#mitnafun-stock-debugger').length) {
                return;
            }
            
            // Initialize debug panel
            setupDebugPanel();
            
            // Check if we have the required data
            if (typeof window.rentalReservedData === 'undefined' || !window.stockDebugger) {
                console.warn('Stock Debugger: Required data not found');
                setupEventListeners({}, 0);
                return;
            }
            
            var stock = parseInt(window.stockDebugger.stock, 10) || 0;
            var reservedData = window.rentalReservedData || [];
            var dateReservations = {};

            console.log('Stock Debugger: Initializing with stock', stock, 'and', reservedData.length, 'reservations');

            // Process reservations
            reservedData.forEach(function(item) {
                try {
                    if (!item) {
                        return;
                    }
                    
                    var startDate = parseDate(item.start);
                    var endDate = parseDate(item.end);
                    
                    // Skip if dates are invalid
                    if (!startDate || !endDate) {
                        return;
                    }
                    
                    // Mark each date in the range
                    var currentDate = new Date(startDate);
                    while (currentDate <= endDate) {
                        var dateStr = formatDate(currentDate);
                        if (!dateStr) {
                            currentDate.setDate(currentDate.getDate() + 1);
                            continue;
                        }
                        
                        // Initialize date entry if it doesn't exist
                        if (!dateReservations[dateStr]) {
                            dateReservations[dateStr] = {
                                count: 0,
                                statuses: new Set(),
                                isFullyBooked: false
                            };
                        }
                        
                        // Only count confirmed reservations
                        if (item.status === 'wc-rental-confirmed' || item.status === '1' || item.status === 1) {
                            dateReservations[dateStr].count++;
                            dateReservations[dateStr].statuses.add(String(item.status));
                            
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
            console.error('Error initializing stock debugger:', error);
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initStockDebugger();
    });

})(jQuery);
                this.showReservedDates = $('#show-reserved-dates').is(':checked');
                this.updateUI();
            });
            
            // Show buffer dates
            $('#show-buffer-dates').on('change', () => {
                this.showBufferDates = $('#show-buffer-dates').is(':checked');
                this.updateUI();
            });
        }
        
        /**
         * Update UI with current data
         */
        updateUI() {
            // Update stock information
            this.initialStockEl.text(this.initialStock);
            this.currentStockEl.text(this.currentStock);
            
            // Count reserved dates
            const reservedCount = Object.keys(this.reservedDates).length;
            this.reservedCountEl.text(reservedCount);
            
            // Update dates information
            this.updateDatesInfo();
            
            // Highlight disabled dates in calendar if debug mode is enabled
            if (this.debugMode) {
                this.highlightDatesInCalendar();
            }
        }
        
        /**
         * Update dates information panel
         */
        updateDatesInfo() {
            let html = '';
            
            // Add disabled dates
            if (this.disabledDates.length > 0) {
                html += '<h5>Disabled Dates (' + this.disabledDates.length + ')</h5>';
                html += '<div class="dates-list">';
                
                // Only show first 10 dates to avoid overwhelming the UI
                const displayDates = this.disabledDates.slice(0, 10);
                html += displayDates.map(date => `<span class="date-tag disabled">${date}</span>`).join('');
                
                if (this.disabledDates.length > 10) {
                    html += `<span class="date-tag more">+${this.disabledDates.length - 10} more</span>`;
                }
                
                html += '</div>';
            }
            
            // Add reserved dates if enabled
            if (this.showReservedDates && Object.keys(this.reservedDates).length > 0) {
                html += '<h5>Reserved Dates (' + Object.keys(this.reservedDates).length + ')</h5>';
                html += '<div class="dates-list">';
                
                // Only show first 10 dates to avoid overwhelming the UI
                const displayDates = Object.keys(this.reservedDates).slice(0, 10);
                html += displayDates.map(date => {
                    const count = this.reservedDates[date];
                    return `<span class="date-tag reserved">${date} (${count})</span>`;
                }).join('');
                
                if (Object.keys(this.reservedDates).length > 10) {
                    html += `<span class="date-tag more">+${Object.keys(this.reservedDates).length - 10} more</span>`;
                }
                
                html += '</div>';
            }
            
            // Add buffer dates if enabled
            if (this.showBufferDates && this.bufferDates.length > 0) {
                html += '<h5>Buffer Dates (' + this.bufferDates.length + ')</h5>';
                html += '<div class="dates-list">';
                
                // Only show first 10 dates to avoid overwhelming the UI
                const displayDates = this.bufferDates.slice(0, 10);
                html += displayDates.map(date => `<span class="date-tag buffer">${date}</span>`).join('');
                
                if (this.bufferDates.length > 10) {
                    html += `<span class="date-tag more">+${this.bufferDates.length - 10} more</span>`;
                }
                
                html += '</div>';
            }
            
            if (!html) {
                html = '<p>No date information available</p>';
            }
            
            this.datesInfoEl.html(html);
        }
        
        /**
         * Highlight dates in calendar
         */
        highlightDatesInCalendar() {
            // Remove previous highlights
            $('.debug-highlight').removeClass('debug-highlight');
            
            // Highlight disabled dates
            this.disabledDates.forEach(date => {
                $(`.fallback-calendar .day-cell[data-date="${date}"]`).addClass('debug-highlight');
                $(`.air-datepicker-cell[data-date="${date}"]`).addClass('debug-highlight');
            });
        }
        
        /**
         * Log message to debug console
         */
        log(message) {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = `<div class="log-entry"><span class="timestamp">${timestamp}</span> ${message}</div>`;
            this.debugConsole.prepend(logEntry);
            
            // Limit log entries
            if (this.debugConsole.children().length > 50) {
                this.debugConsole.children().last().remove();
            }
            
    }
    
    /**
     * Extract data from DOM
     */
    extractDataFromDOM() {
        // Extract initial stock
        const initialStockText = $('#initial-stock').text();
        this.initialStock = parseInt(initialStockText.replace(/[^0-9]/g, ''));
        
        // Extract current stock
        const currentStockText = $('#current-stock').text();
        this.currentStock = parseInt(currentStockText.replace(/[^0-9]/g, ''));
        
        // Extract reserved dates
        const reservedDates = {};
        $('.reserved-date').each((index, el) => {
            const dateText = $(el).text();
            const date = this.formatDateISO(new Date(dateText));
            const countText = $(el).data('count');
            const count = parseInt(countText);
            reservedDates[date] = count;
        });
        this.reservedDates = reservedDates;
        
        // Extract buffer dates
        const bufferDates = [];
        $('.buffer-date').each((index, el) => {
            const dateText = $(el).text();
            const date = this.formatDateISO(new Date(dateText));
            bufferDates.push(date);
        });
        this.bufferDates = bufferDates;
    }
    
    /**
     * Update UI with current data
     */
    updateUI() {
        // Update stock information
        this.initialStockEl.text(this.initialStock);
        this.currentStockEl.text(this.currentStock);
        
        // Count reserved dates
        const reservedCount = Object.keys(this.reservedDates).length;
        this.reservedCountEl.text(reservedCount);
        
        // Update dates information
        this.updateDatesInfo();
        
        // Highlight disabled dates in calendar if debug mode is enabled
        if (this.debugMode) {
            this.highlightDatesInCalendar();
        }
    }
    
    /**
     * Update dates information panel
     */
    updateDatesInfo() {
        let html = '';
        
        // Add disabled dates
        if (this.disabledDates.length > 0) {
            html += '<h5>Disabled Dates (' + this.disabledDates.length + ')</h5>';
            html += '<div class="dates-list">';
            
            // Only show first 10 dates to avoid overwhelming the UI
            const displayDates = this.disabledDates.slice(0, 10);
            html += displayDates.map(date => `<span class="date-tag disabled">${date}</span>`).join('');
            
            if (this.disabledDates.length > 10) {
                html += `<span class="date-tag more">+${this.disabledDates.length - 10} more</span>`;
            }
            
            html += '</div>';
        }
        
        // Add reserved dates if enabled
        if (this.showReservedDates && Object.keys(this.reservedDates).length > 0) {
            html += '<h5>Reserved Dates (' + Object.keys(this.reservedDates).length + ')</h5>';
            html += '<div class="dates-list">';
            
            // Only show first 10 dates to avoid overwhelming the UI
            const displayDates = Object.keys(this.reservedDates).slice(0, 10);
            html += displayDates.map(date => {
                const count = this.reservedDates[date];
                return `<span class="date-tag reserved">${date} (${count})</span>`;
            }).join('');
            
            if (Object.keys(this.reservedDates).length > 10) {
                html += `<span class="date-tag more">+${Object.keys(this.reservedDates).length - 10} more</span>`;
            }
            
            html += '</div>';
        }
        
        // Add buffer dates if enabled
        if (this.showBufferDates && this.bufferDates.length > 0) {
            html += '<h5>Buffer Dates (' + this.bufferDates.length + ')</h5>';
            html += '<div class="dates-list">';
            
            // Only show first 10 dates to avoid overwhelming the UI
            const displayDates = this.bufferDates.slice(0, 10);
            html += displayDates.map(date => `<span class="date-tag buffer">${date}</span>`).join('');
            
            if (this.bufferDates.length > 10) {
                html += `<span class="date-tag more">+${this.bufferDates.length - 10} more</span>`;
            }
            
            html += '</div>';
        }
        
        if (!html) {
            html = '<p>No date information available</p>';
        }
        
        this.datesInfoEl.html(html);
    }
    
    /**
     * Highlight dates in calendar
     */
    highlightDatesInCalendar() {
        // Remove previous highlights
        $('.debug-highlight').removeClass('debug-highlight');
        
        // Highlight disabled dates
        this.disabledDates.forEach(date => {
            $(`.fallback-calendar .day-cell[data-date="${date}"]`).addClass('debug-highlight');
            $(`.air-datepicker-cell[data-date="${date}"]`).addClass('debug-highlight');
        });
    }
    
    /**
     * Extract reserved dates from calendar
     */
    extractReservedDatesFromCalendar() {
        // Look for disabled dates in the calendar
        const disabledDates = [];
        
        // Check for fallback calendar
        $('.fallback-calendar .day-cell.disabled').each((index, el) => {
            const date = $(el).data('date');
            if (date) {
                disabledDates.push(date);
            }
        });
        
        // Check for Air Datepicker
        $('.air-datepicker-cell.-disabled-').each((index, el) => {
            const dateAttr = $(el).data('date');
            if (dateAttr) {
                const date = this.formatDateISO(new Date(dateAttr));
                disabledDates.push(date);
            }
        });
        
        this.disabledDates = disabledDates;
        this.log('Extracted ' + disabledDates.length + ' disabled dates from calendar');
    }
    
    /**
     * Format date as YYYY-MM-DD
     */
    formatDateISO(date) {
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Toggle debug mode
        $('#toggle-debug-mode').on('click', () => {
            this.debugMode = !this.debugMode;
            $('#toggle-debug-mode').text(this.debugMode ? 'Disable Debug Mode' : 'Enable Debug Mode');
            this.log('Debug mode ' + (this.debugMode ? 'enabled' : 'disabled'));
            this.updateUI();
        });
        
        // Refresh data
        $('#refresh-debug-data').on('click', () => {
            this.extractDataFromDOM();
            this.updateUI();
            this.log('Data refreshed');
        });
        
        // Show reserved dates
        $('#show-reserved-dates').on('change', () => {
            this.showReservedDates = $('#show-reserved-dates').is(':checked');
            this.updateUI();
        });
        
        // Show buffer dates
        $('#show-buffer-dates').on('change', () => {
            this.showBufferDates = $('#show-buffer-dates').is(':checked');
            this.updateUI();
        });
    }
    
    /**
     * Log message to debug console
     */
    log(message) {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = `<div class="log-entry"><span class="timestamp">${timestamp}</span> ${message}</div>`;
        this.debugConsole.prepend(logEntry);
        
        // Limit log entries
        if (this.debugConsole.children().length > 50) {
 * Set up the debug panel with reservation data
 */
function setupDebugPanel(dateReservations, stock) {
    var $debugPanel = $('#stock-debugger');
    if (!$debugPanel.length) return;
    
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
    
    // Log debug info
    console.log('Stock Debugger: Debug panel initialized');
}

/**
 * Set up event listeners for calendar interaction
 */
function setupEventListeners(dateReservations, stock) {
    // Handle Ctrl+Click on calendar dates
    $(document).on('click', '.day-cell', function(e) {
        if (e.ctrlKey) {
            e.preventDefault();
            var date = $(this).data('date');
            var dateStr = formatDate(new Date(date));
            var reservation = dateReservations[dateStr];
            
            if (reservation) {
                var statuses = [];
                reservation.statuses.forEach(function(status) {
                    statuses.push(status);
                });
                
                alert('Date: ' + dateStr + '\n' +
                      'Booked: ' + reservation.count + ' of ' + stock + '\n' +
                      'Status: ' + statuses.join(', ') + '\n' +
                      'Fully Booked: ' + (reservation.isFullyBooked ? 'Yes' : 'No'));
            } else {
                alert('Date: ' + dateStr + '\nNo reservations found');
            }
        }
    });
    
    // Add keyboard shortcut (Ctrl+Shift+D) to toggle debug mode
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'd') {
            e.preventDefault();
            $('#toggle-debug-mode').trigger('click');
        }
    });
    
    console.log('Stock Debugger: Event listeners set up');
}

// Initialize when document is ready
$(document).ready(function() {
    // Only initialize if we're on a product page and the debug panel exists
    if ($('#mitnafun-stock-debugger').length) {
        window.stockDebugger = {}; // Initialize empty object for backward compatibility
        setupDebugPanel({}, 0);
        setupEventListeners({}, 0);
    }
});
