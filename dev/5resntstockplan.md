Rental Date and Stock Debugging Plan
Based on my analysis, I'll create a comprehensive debugging system to track rental dates and stock values during the checkout process. Here's the plan:

1. Create a Debug Logging System
Let's create a dedicated debug logging class to track rental date and stock information during checkout.

php
CopyInsert
// File: includes/class-mitnafun-debug-logger.php
class Mitnafun_Debug_Logger {
    private static $instance = null;
    private $log_file;
    private $enabled = true;

    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/mitnafun-rental-debug.log';
        $this->enabled = defined('WP_DEBUG') && WP_DEBUG;
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log($message, $data = null) {
        if (!$this->enabled) return;

        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
        
        if ($data !== null) {
            $log_entry .= print_r($data, true) . PHP_EOL;
        }
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
    }

    public function log_checkout_item($product_id, $quantity, $rental_dates, $stock_data) {
        $this->log('Checkout Item Details', [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'rental_dates' => $rental_dates,
            'stock_data' => $stock_data,
            'current_time' => current_time('mysql')
        ]);
    }
}
2. Hook into Checkout Process
We'll add hooks to track rental date selection and stock validation during checkout:

php
CopyInsert
// File: includes/class-mitnafun-checkout-manager.php
class Mitnafun_Checkout_Manager {
    private $logger;

    public function __construct() {
        $this->logger = Mitnafun_Debug_Logger::get_instance();
        
        // Log when items are added to cart
        add_action('woocommerce_add_to_cart', [$this, 'log_add_to_cart'], 10, 6);
        
        // Log during checkout validation
        add_action('woocommerce_after_checkout_validation', [$this, 'log_checkout_validation'], 10, 2);
        
        // Log before order processing
        add_action('woocommerce_before_checkout_process', [$this, 'log_before_checkout_process']);
        
        // Log after order is created
        add_action('woocommerce_checkout_update_order_meta', [$this, 'log_order_created'], 10, 2);
    }

    public function log_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        $product = wc_get_product($product_id);
        $rental_dates = isset($cart_item_data['rental_dates']) ? $cart_item_data['rental_dates'] : 'Not set';
        
        $stock_data = [
            'total_stock' => get_post_meta($product_id, '_initial_stock', true),
            'wc_stock' => $product->get_stock_quantity(),
            'backorders' => $product->backorders_allowed() ? 'Yes' : 'No'
        ];

        $this->logger->log("Product added to cart", [
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'quantity' => $quantity,
            'rental_dates' => $rental_dates,
            'stock_data' => $stock_data
        ]);
    }

    public function log_checkout_validation($data, $errors) {
        if (empty($errors->errors)) return;

        $this->logger->log("Checkout validation errors", [
            'errors' => $errors->errors,
            'post_data' => $_POST
        ]);
    }

    public function log_before_checkout_process() {
        $cart = WC()->cart->get_cart();
        $cart_contents = [];

        foreach ($cart as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $cart_contents[] = [
                'product_id' => $product->get_id(),
                'name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
                'rental_dates' => isset($cart_item['rental_dates']) ? $cart_item['rental_dates'] : 'Not set',
                'total_stock' => get_post_meta($product->get_id(), '_initial_stock', true),
                'wc_stock' => $product->get_stock_quantity()
            ];
        }

        $this->logger->log("Before checkout process", [
            'cart_contents' => $cart_contents
        ]);
    }

    public function log_order_created($order_id, $data) {
        $order = wc_get_order($order_id);
        $items = $order->get_items();
        $order_items = [];

        foreach ($items as $item) {
            $product = $item->get_product();
            $order_items[] = [
                'product_id' => $product->get_id(),
                'name' => $product->get_name(),
                'quantity' => $item->get_quantity(),
                'total_stock' => get_post_meta($product->get_id(), '_initial_stock', true),
                'wc_stock_after' => $product->get_stock_quantity()
            ];
        }

        $this->logger->log("Order created", [
            'order_id' => $order_id,
            'order_items' => $order_items
        ]);
    }
}
3. Create Admin Interface for Debug Logs
Let's add a debug log viewer in the admin panel:

php
CopyInsert
// File: includes/class-mitnafun-debug-viewer.php
class Mitnafun_Debug_Viewer {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_debug_page']);
        add_action('admin_init', [$this, 'handle_debug_actions']);
    }

    public function add_debug_page() {
        add_submenu_page(
            'mitnafun-order-admin',
            'Rental Debug Logs',
            'Debug Logs',
            'manage_woocommerce',
            'mitnafun-debug-logs',
            [$this, 'render_debug_page']
        );
    }

    public function handle_debug_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mitnafun-debug-logs') {
            return;
        }

        if (isset($_POST['clear_logs']) && check_admin_referer('clear_debug_logs')) {
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/mitnafun-rental-debug.log';
            
            if (file_exists($log_file)) {
                file_put_contents($log_file, '');
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>Debug logs cleared successfully.</p></div>';
                });
            }
        }
    }

    public function render_debug_page() {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/mitnafun-rental-debug.log';
        $log_content = '';

        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
        }

        ?>
        <div class="wrap">
            <h1>Rental System Debug Logs</h1>
            
            <form method="post" style="margin-bottom: 20px;">
                <?php wp_nonce_field('clear_debug_logs'); ?>
                <input type="submit" name="clear_logs" class="button button-secondary" value="Clear Logs">
            </form>

            <div style="background: #1d2327; color: #f0f0f1; padding: 15px; border-radius: 4px; max-height: 600px; overflow-y: auto; font-family: monospace; white-space: pre-wrap;">
                <?php echo esc_html($log_content ?: 'No debug logs found.'); ?>
            </div>
        </div>
        <?php
    }
}
4. Initialize the Debug System
Add this to your main plugin file:

php
CopyInsert
// Initialize debug logger
require_once plugin_dir_path(__FILE__) . 'includes/class-mitnafun-debug-logger.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mitnafun-checkout-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mitnafun-debug-viewer.php';

// Initialize debug components
function init_mitnafun_debug() {
    new Mitnafun_Checkout_Manager();
    new Mitnafun_Debug_Viewer();
}
add_action('plugins_loaded', 'init_mitnafun_debug');
5. Add Stock Validation for Rental Dates
Let's add a function to validate stock based on rental dates:

php
CopyInsert
// File: includes/class-mitnafun-stock-validator.php
class Mitnafun_Stock_Validator {
    private $logger;

    public function __construct() {
        $this->logger = Mitnafun_Debug_Logger::get_instance();
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_rental_stock'], 10, 3);
    }

    public function validate_rental_stock($passed, $product_id, $quantity) {
        $product = wc_get_product($product_id);
        
        // Skip if not a rental product or stock management is disabled
        if (!$product || !$product->managing_stock()) {
            return $passed;
        }

        // Get rental dates from request
        $rental_dates = isset($_POST['rental_dates']) ? wc_clean($_POST['rental_dates']) : '';
        
        if (empty($rental_dates)) {
            return $passed; // No date validation needed
        }

        try {
            $dates = json_decode($rental_dates, true);
            if (!is_array($dates) || !isset($dates['start']) || !isset($dates['end'])) {
                throw new Exception('Invalid rental dates format');
            }

            $start_date = new DateTime($dates['start']);
            $end_date = new DateTime($dates['end']);
            $total_stock = (int) get_post_meta($product_id, '_initial_stock', true);
            $current_stock = $product->get_stock_quantity();

            // Log the validation attempt
            $this->logger->log("Validating rental stock", [
                'product_id' => $product_id,
                'start_date' => $start_date->format('Y-m-d'),
                'end_date' => $end_date->format('Y-m-d'),
                'total_stock' => $total_stock,
                'current_stock' => $current_stock,
                'requested_quantity' => $quantity
            ]);

            // Check if requested quantity exceeds available stock
            if ($quantity > $current_stock) {
                $message = sprintf(
                    __('Sorry, we do not have enough "%s" in stock. Only %d available.', 'mitnafun-order-admin'),
                    $product->get_name(),
                    $current_stock
                );
                wc_add_notice($message, 'error');
                return false;
            }

            // Additional validation for overlapping rentals could be added here

            return $passed;

        } catch (Exception $e) {
            $this->logger->log("Error validating rental stock", [
                'error' => $e->getMessage(),
                'product_id' => $product_id,
                'rental_dates' => $rental_dates
            ]);
            
            wc_add_notice(__('Error validating rental dates. Please try again.', 'mitnafun-order-admin'), 'error');
            return false;
        }
    }
}
6. Update Main Plugin File
Add this to your main plugin file to initialize everything:

php
CopyInsert
// Initialize stock validator
require_once plugin_dir_path(__FILE__) . 'includes/class-mitnafun-stock-validator.php';

function init_mitnafun_rental_system() {
    new Mitnafun_Stock_Validator();
}
add_action('plugins_loaded', 'init_mitnafun_rental_system');
How to Use This Debugging System
View Logs: Go to WordPress admin → Mitnafun Order Admin → Debug Logs
Clear Logs: Use the "Clear Logs" button to start fresh
Monitor: The system will log:
When products are added to cart
Stock levels at various points
Checkout validation
Order creation
Any errors that occur
Next Steps
Test the system by:
Adding products to cart with different rental dates
Checking the debug logs for detailed information
Verifying stock levels are being tracked correctly
Review the logs to identify any discrepancies in stock calculations
Based on the logs, we can further enhance the validation and logging as needed
Would you like me to implement any specific part of this system first, or would you like to review the plan before we proceed?