# Aviv Order Admin

 dates are still showing as disabled (like June 15, 23, and 25) when they shouldn't be. Let's fix this by checking how the disabled dates are being applied in the frontend JavaScript.

Let me check the calendar initialization code:

Searched codebase
5 results
Searched inc:\Users\USUARIO\Documents\SITES\MIT\app\public\wp-content\themes\mitnafun-upro\jswith queryinitializeFallbackCalendar
Let me check the calendar initialization code to understand how disabled dates are being handled.

Analyzed
calendar-initializer.js:(2-50)
I've found the issue! The calendar is being initialized with sample test data that includes random disabled dates. Let's fix this by updating the calendar initializer to use the actual reserved dates from the server instead of generating random test dates.

Edited
calendar-initializer.js
+49
-23
 23
Open diff

Let me fix the syntax error in the calendar initializer:

Edited
calendar-initializer.js
+0
-4
 68
Open diff
I've made several important changes to fix the random disabled dates issue:

Updated the Calendar Initializer:
Replaced the test data with actual reserved dates from the server
Added proper error handling and user feedback
Included a reload button for users to refresh the calendar if needed
Fixed the Reserved Dates Logic:
The calendar now fetches real reservation data instead of using random test dates
Added better error handling and logging
Improved the user interface with clearer error messages
Next Steps:
Clear your browser cache and refresh the page
The calendar should now show accurate availability based on real reservations
Only actual reserved dates should be disabled
The calendar should now work correctly without random disabled dates. Let me know if you notice any other issues or if you'd like me to make any adjustments to the behavior.

A simple WordPress plugin for managing recent orders with booking dates.

## Features

- View orders from the last 90 days
- Filter orders by product
- Filter orders by client
- See booking date ranges for each order
- Export orders to CSV
- Direct links to edit orders


## Installation

1. Upload the `aviv-order-admin` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access the orders page from the 'Aviv Orders' menu item in the admin panel

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- User must have `manage_woocommerce` capability

## Usage

1. Go to the 'Aviv Orders' page in the WordPress admin
2. Use the dropdown filters to view orders by product or client
3. Click on order numbers to edit individual orders

## Styling & Customization

### Admin Page
The main admin containers are wrapped in `.mitnafun-admin-wrap > .mitnafun-admin-content`. To improve layout, open `css/admin.css` and adjust:

- Widths: modify `.mitnafun-table-container` or add:

```css
.wrap .mitnafun-admin-content { max-width: 100%; }
```

- Filters: use Flexbox for responsive groups:

```css
.mitnafun-filters { display: flex; flex-wrap: wrap; gap: 1rem; }
```

### Tabs
Tabs use jQuery UI Tabs. To customize:

- Load a different jQuery UI theme via `enqueue_admin_scripts()`.
- Override navigation styles in `css/admin.css`:

```css
#mitnafun-tabs .ui-tabs-nav li a { padding: 0.5em 1em; }
#mitnafun-tabs .ui-tabs-panel { padding: 1em; }
```

### Booking Calendar
Powered by FullCalendar (see `js/admin.js`). To tweak appearance:

- Change default view, height, or aspectRatio in the Calendar constructor.
- Override calendar styles:

```css
#mitnafun-calendar .fc { font-size: 0.9rem; }
#mitnafun-calendar .fc-daygrid-event { background-color: #007cba; }
```

## Development & Contributing

1. Entry point: `mitnafun-order-admin.php`.
2. Admin UI: `css/admin.css`, `js/admin.js`.
3. To add calendar events: implement a new AJAX handler (e.g. `wp_ajax_get_calendar_events`) and pass its URL to FullCalendar `events` option.
4. Feel free to open issues or PRs to improve features or styling.

# aviv-admin-order
# plugin-admin-mitnutfun

 # mitfinalstock
# mitplug196
