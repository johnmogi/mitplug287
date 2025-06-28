# Stock Display Implementation Documentation

## Simplified Stock Display Implementation

### Overview
Implemented a clean, minimal stock display showing only the total inventory count on product pages. This replaces the previous stock manager container with a simpler, more focused display.

### Implementation Details

#### Location
File: `wp-content/themes/mitnafun-upro/woocommerce/content-single-product.php`

#### Code Implementation
```php
<?php if ($product->get_manage_stock()) : 
    $stock_quantity = $product->get_stock_quantity();
    $stock_status = $product->get_stock_status();
    $initial_stock = get_post_meta($product->get_id(), '_initial_stock', true);
    $initial_stock = !empty($initial_stock) ? $initial_stock : $stock_quantity;
    ?>
    <li class="stock-availability" style="margin-top: 10px;">
        <div style="font-size: 1em; color: #000; font-weight: bold;">
            <div>סה"כ מלאי: <?php echo $initial_stock; ?> יחידות</div>
        </div>
    </li>
<?php endif; ?>
```

#### Key Components
1. **Stock Data Retrieval**
   - Gets current stock quantity using WooCommerce's `get_stock_quantity()`
   - Retrieves initial stock value from post meta field '_initial_stock'
   - Falls back to current stock if initial stock is not set

2. **Display Logic**
   - Only shows for products with stock management enabled
   - Displays in a simple, bold format for maximum visibility
   - Uses Hebrew text for consistency with the site's language

3. **Styling**
   - Clean, minimal design that matches the site's aesthetic
   - Bold text for better readability
   - Proper spacing with margin-top for visual separation

### Changes Made
1. Removed the previous stock manager container by disabling its creation in `stock-manager.js`
2. Simplified the stock display to show only the total inventory count
3. Maintained fallback to current stock if initial stock is not available

### Technical Notes
- The stock value is retrieved directly from WooCommerce's product data
- The display updates automatically when the page loads
- No additional JavaScript is required for the basic display
- The implementation is lightweight and doesn't impact page performance

### Future Considerations
1. Add caching for stock values if needed for performance
2. Consider adding a refresh button for manual updates
3. Potentially add color coding based on stock levels (e.g., red for low stock)
4. Consider adding a tooltip with more detailed stock information

---

## Stock Display Solution

## Stock Display Overview
Implemented a solution to display stock information for rental products, showing both initial stock and current availability. The solution includes a debug panel that appears on the frontend to help with testing and verification.

## Key Components

### 1. Stock Data Retrieval

#### `get_initial_stock($product_id)`
- **Purpose**: Retrieves the initial stock value for a product
- **Location**: `mitnafun-order-admin.php`
- **Query**:
  ```php
  get_post_meta($product_id, '_initial_stock', true);
  ```
- **Returns**: Integer value of initial stock or false if not set

#### `get_available_stock($product_id)`
- **Purpose**: Gets current available stock from WooCommerce
- **Location**: `mitnafun-order-admin.php`
- **Query**:
  ```php
  $product = wc_get_product($product_id);
  return $product->get_stock_quantity();
  ```
- **Returns**: Integer value of available stock

### 2. Frontend Display

#### Debug Panel HTML
```html
<div id="stock-debug-info" 
     style="position: fixed; 
            bottom: 0px; 
            right: 0px; 
            background: rgba(0, 0, 0, 0.7); 
            color: white; 
            padding: 10px; 
            font-size: 12px; 
            z-index: 9999; 
            border-top-left-radius: 5px;">
    Stock: {current} (Initial: {initial}) | Avail: {available} | {status} | Count: {reserved}
</div>
```

### 3. JavaScript Implementation

#### Initialization
```javascript
jQuery(document).ready(function($) {
    // Get product ID from the page
    const productId = getProductIdFromPage();
    
    if (productId) {
        updateStockDisplay(productId);
    }
});
```

#### Stock Update Function
```javascript
function updateStockDisplay(productId) {
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'get_stock_data',
            product_id: productId,
            nonce: mitnafun_vars.nonce
        },
        success: function(response) {
            if (response.success) {
                updateDebugPanel(response.data);
            }
        }
    });
}
```

### 4. AJAX Handler

#### PHP (mitnafun-order-admin.php)
```php
add_action('wp_ajax_get_stock_data', 'handle_stock_data_request');
add_action('wp_ajax_nopriv_get_stock_data', 'handle_stock_data_request');

function handle_stock_data_request() {
    check_ajax_referer('mitnafun_nonce', 'nonce');
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if (!$product_id) {
        wp_send_json_error('Invalid product ID');
    }
    
    $initial_stock = get_initial_stock($product_id);
    $available_stock = get_available_stock($product_id);
    $reserved_count = get_reserved_count($product_id);
    
    wp_send_json_success([
        'initial' => $initial_stock,
        'available' => $available_stock,
        'reserved' => $reserved_count,
        'status' => $available_stock > 0 ? '✅ In Stock' : '❌ Out of Stock'
    ]);
}
```

## Implementation Notes

1. **Caching**: Consider implementing transient caching for stock data that updates infrequently
2. **Performance**: Queries are optimized to only retrieve necessary data
3. **Security**: All AJAX requests include nonce verification
4. **Compatibility**: Works with both simple and variable products
5. **Localization**: Ready for translation with proper text domains

## Testing

1. Navigate to any product page
2. The debug panel should appear in the bottom-right corner
3. Verify stock numbers match expected values
4. Test with different products and stock statuses

## Future Improvements

1. Add refresh button to manually update stock
2. Implement WebSocket for real-time updates
3. Add stock history tracking
4. Create admin interface for stock management

## Troubleshooting

- If stock doesn't update, check:
  - Product has the `_initial_stock` meta set
  - WooCommerce stock management is enabled
  - User has proper permissions
  - No JavaScript errors in console
  - AJAX requests are returning successfully



  // Get initial stock (custom meta)
  $initial_stock = (int) get_post_meta($product_id, '_initial_stock', true);
  
  // Get current WooCommerce stock
  $current_stock = (int) $product->get_stock_quantity();
  
  // Calculate reserved stock
  $reserved_stock = $initial_stock - $current_stock;
  
  // Get count of active reservations
  $reserved_count = $wpdb->get_var($wpdb->prepare("
      SELECT COUNT(DISTINCT oi.order_id)
      FROM {$wpdb->prefix}woocommerce_order_items oi
      JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim 
          ON oi.order_item_id = oim.order_item_id
      JOIN {$wpdb->prefix}wc_orders o 
          ON oi.order_id = o.id
      WHERE oim.meta_key = '_product_id' 
      AND oim.meta_value = %d
      AND o.status IN ('wc-processing', 'wc-pending', 'wc-on-hold')
  ", $product_id));


  Here's the function that retrieves the initial stock data:

php
CopyInsert
/**
 * Retrieves the initial stock value for a product
 * 
 * @param int $product_id The ID of the product
 * @return int|false The initial stock value or false if not set
 */
function get_initial_stock($product_id) {
    // Get the initial stock from post meta
    $initial_stock = get_post_meta($product_id, '_initial_stock', true);
    
    // If initial stock is not set, get the current stock as fallback
    if (empty($initial_stock)) {
        $product = wc_get_product($product_id);
        if ($product) {
            $initial_stock = $product->get_stock_quantity();
        }
    }
    
    // Ensure we return an integer or false
    return is_numeric($initial_stock) ? (int)$initial_stock : false;
}
Usage Example:
php
CopyInsert
$product_id = get_the_ID(); // Or any product ID
$initial_stock = get_initial_stock($product_id);

if ($initial_stock !== false) {
    echo "Initial Stock: " . $initial_stock . " units";
} else {
    echo "Stock information not available";
}
Key Features:
Retrieves the initial stock value from the '_initial_stock' meta field
Falls back to the current stock quantity if initial stock is not set
Returns false if no stock information is available
Ensures the return value is an integer for consistency
Includes proper PHPDoc comments for documentation
This function is used in the product template to display the initial stock value while providing a fallback to current stock if needed.