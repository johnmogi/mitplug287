// Creating solution documentation
$doc_content = <<<EOD
# Stock-Aware Rental Calendar Solution

## Problem
The rental calendar was showing dates as fully booked (red) even when there was still available stock. We needed to modify the system to:
1. Only mark dates as fully booked when all available stock is reserved
2. Show partially available dates differently from fully booked dates
3. Maintain the initial stock as the source of truth

## Solution

### 1. Stock Management
- Initial stock is kept as the source of truth in WooCommerce
- Active rentals are tracked separately in `_active_rentals` post meta
- Available stock is calculated as: `initial_stock - active_rentals`

### 2. Calendar Modifications
- Modified calendar to check stock levels before marking dates as unavailable
- Added visual indicators for partially available dates
- Implemented time-based availability for same-day bookings

### 3. Key Files Modified

#### fallback-calendar.js
- Updated date availability logic to consider stock levels
- Added visual feedback for partially available dates
- Implemented time-based availability checks

#### mitnafun-order-admin.php
- Added active rentals tracking
- Implemented stock calculation overrides
- Added debug panel for testing

### 4. Debugging Tools
Added a debug panel that shows:
- Initial stock level
- Active rentals count
- Calculated available stock
- Detailed list of active rentals

### 5. How to Test
1. Set a product's stock to 1
2. Create a test order with rental dates
3. Verify the calendar shows the dates as partially available
4. Create another order to see the dates become fully booked

### 6. Rollback Plan
To revert changes:
1. Remove the debug panel code
2. Restore original calendar logic
3. Remove the active rentals tracking
EOD;

file_put_contents(__DIR__ . '/dev/rental-calendar-solution.md', $doc_content);