<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('Aviv Order Management', 'aviv-order-admin'); ?></h1>

    <h2 class="nav-tab-wrapper">
        <a href="#orders" class="nav-tab nav-tab-active"><?php echo esc_html__('Orders', 'aviv-order-admin'); ?></a>
        <a href="#clients" class="nav-tab"><?php echo esc_html__('Clients', 'aviv-order-admin'); ?></a>
        <a href="#products" class="nav-tab"><?php echo esc_html__('Products', 'aviv-order-admin'); ?></a>
        <a href="#stock-management" class="nav-tab"><?php echo esc_html__('Stock Management', 'aviv-order-admin'); ?></a>
    </h2>

    <div id="loading-message"><?php echo esc_html__('Loading...', 'aviv-order-admin'); ?></div>
    <div id="error-message"></div>

    <!-- Orders Tab -->
    <div id="orders" class="tab-content">
        <form id="orders-filter" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="product_filter"><?php echo esc_html__('Product', 'aviv-order-admin'); ?></label>
                    <select id="product_filter" name="product_filter">
                        <option value=""><?php echo esc_html__('All Products', 'aviv-order-admin'); ?></option>
                        <?php
                        $products = get_posts([
                            'post_type' => 'product',
                            'posts_per_page' => -1,
                            'orderby' => 'title',
                            'order' => 'ASC'
                        ]);
                        foreach ($products as $product) {
                            printf(
                                '<option value="%s">%s</option>',
                                esc_attr($product->ID),
                                esc_html($product->post_title)
                            );
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status_filter"><?php echo esc_html__('Status', 'aviv-order-admin'); ?></label>
                    <select id="status_filter" name="status_filter">
                        <option value=""><?php echo esc_html__('All Statuses', 'aviv-order-admin'); ?></option>
                        <?php
                        foreach (wc_get_order_statuses() as $status => $label) {
                            printf(
                                '<option value="%s">%s</option>',
                                esc_attr($status),
                                esc_html($label)
                            );
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from"><?php echo esc_html__('From Date', 'aviv-order-admin'); ?></label>
                    <input type="text" id="date_from" name="date_from" class="datepicker" />
                </div>
                <div class="form-group">
                    <label for="date_to"><?php echo esc_html__('To Date', 'aviv-order-admin'); ?></label>
                    <input type="text" id="date_to" name="date_to" class="datepicker" />
                </div>
            </div>
            <div class="button-row">
                <button type="submit" class="button button-primary"><?php echo esc_html__('Apply Filters', 'aviv-order-admin'); ?></button>
                <button type="button" id="reset-filter" class="button"><?php echo esc_html__('Reset', 'aviv-order-admin'); ?></button>
            </div>
        </form>

        <table id="orders-table" class="aviv-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Order', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Date', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Client', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Contact', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Product', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Rental Dates', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Total', 'aviv-order-admin'); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- Clients Tab -->
    <div id="clients" class="tab-content">
        <table id="clients-table" class="aviv-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Client Name', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Email', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Phone', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Total Orders', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Total Spent', 'aviv-order-admin'); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- Products Tab -->
    <div id="products" class="tab-content">
        <div class="tab-actions" style="margin-bottom: 15px;">
            <button type="button" id="sync-all-stock" class="button button-primary">
                <?php echo esc_html__('Sync All Stock to WooCommerce', 'aviv-order-admin'); ?>
            </button>
            <span class="spinner" style="float: none; margin-left: 10px;"></span>
            <span id="sync-result" style="margin-left: 10px; color: #46b450; font-weight: bold; display: none;"></span>
        </div>
        
        <table id="products-table" class="aviv-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('ID', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Product Name', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Total Stock', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('WooCommerce Stock', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Status', 'aviv-order-admin'); ?></th>
                    <th><?php echo esc_html__('Actions', 'aviv-order-admin'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $products = wc_get_products([
                    'limit' => -1,
                    'status' => 'publish',
                    'orderby' => 'title',
                    'order' => 'ASC'
                ]);
                
                foreach ($products as $product) {
                    $product_id = $product->get_id();
                    $total_stock = get_post_meta($product_id, '_initial_stock', true);
                    $woo_stock = $product->get_stock_quantity();
                    $stock_status = $product->get_stock_status();
                    $manage_stock = $product->managing_stock();
                    
                    // Skip products that don't have stock management enabled or don't have initial stock set
                    if (!$manage_stock || $total_stock === '') continue;
                    
                    $status_class = $stock_status === 'instock' ? 'in-stock' : 'out-of-stock';
                    $status_text = $stock_status === 'instock' ? __('In Stock', 'aviv-order-admin') : __('Out of Stock', 'aviv-order-admin');
                    $woo_stock = $woo_stock !== null ? $woo_stock : 0;
                    
                    echo '<tr class="' . esc_attr($status_class) . '" data-product-id="' . esc_attr($product_id) . '">';
                    echo '<td>' . esc_html($product_id) . '</td>';
                    echo '<td>' . esc_html($product->get_name()) . '</td>';
                    echo '<td class="total-stock">' . esc_html($total_stock) . '</td>';
                    echo '<td class="woocommerce-stock">' . esc_html($woo_stock) . '</td>';
                    echo '<td><span class="stock-status ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
                    echo '<td class="sync-actions">';
                    echo '<button type="button" class="button button-small sync-single-stock" data-product-id="' . esc_attr($product_id) . '">' . esc_html__('Sync', 'aviv-order-admin') . '</button>';
                    echo '<span class="sync-result"></span>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Stock Management Tab -->
    <div id="stock-management" class="tab-content">
        <div class="stock-management-container">
            <h2><?php echo esc_html__('Stock Management', 'mitnafun-order-admin'); ?></h2>
            
            <div class="stock-sync-section">
                <h3><?php echo esc_html__('Bulk Stock Synchronization', 'mitnafun-order-admin'); ?></h3>
                <p class="description"><?php echo esc_html__('Synchronize WooCommerce stock levels with total stock values for all products.', 'mitnafun-order-admin'); ?></p>
                
                <div class="stock-sync-actions">
                    <button type="button" id="sync-stock-btn" class="button button-primary">
                        <?php echo esc_html__('Synchronize All Stock', 'mitnafun-order-admin'); ?>
                    </button>
                    <button type="button" id="mitnafun-release-stock-issues" class="button button-secondary">
                        <?php echo esc_html__('Release Stock Issues', 'mitnafun-order-admin'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
                
                <div id="sync-results">
                    <h4><?php echo esc_html__('Synchronization Results', 'mitnafun-order-admin'); ?></h4>
                    <div id="sync-results-content">
                        <!-- Results will be displayed here -->
                    </div>
                </div>
                
                <div class="stock-info">
                    <h4><?php echo esc_html__('How It Works', 'mitnafun-order-admin'); ?></h4>
                    <ul>
                        <li><?php echo esc_html__('Total Stock: The source of truth for product inventory', 'mitnafun-order-admin'); ?></li>
                        <li><?php echo esc_html__('WooCommerce Stock: Will be updated to match Total Stock', 'mitnafun-order-admin'); ?></li>
                        <li><?php echo esc_html__('Only products with stock management enabled will be updated', 'mitnafun-order-admin'); ?></li>
                    </ul>
                </div>
            </div>
            
            <div class="products-table-section">
                <h3><?php echo esc_html__('Product Stock Overview', 'mitnafun-order-admin'); ?></h3>
                <p class="description"><?php echo esc_html__('View and manage stock levels for individual products.', 'mitnafun-order-admin'); ?></p>
                
                <?php
                // Count products with stock management enabled
                $products_query = wc_get_products([
                    'limit' => -1,
                    'status' => 'publish',
                    'meta_key' => '_manage_stock',
                    'meta_value' => 'yes'
                ]);
                $managed_product_count = count($products_query);
                
                // Count total products
                $total_products = wp_count_posts('product');
                $total_product_count = $total_products->publish;
                ?>
                
                <div class="product-count-info">
                    <p>
                        <strong><?php echo esc_html__('Products with Stock Management:', 'mitnafun-order-admin'); ?></strong> 
                        <span id="managed-product-count"><?php echo esc_html($managed_product_count); ?></span> 
                        <?php echo esc_html__('of', 'mitnafun-order-admin'); ?> 
                        <span id="total-product-count"><?php echo esc_html($total_product_count); ?></span> 
                        <?php echo esc_html__('total products', 'mitnafun-order-admin'); ?>
                    </p>
                    <p>
                        <strong><?php echo esc_html__('Currently Displayed:', 'mitnafun-order-admin'); ?></strong> 
                        <span id="displayed-product-count">0</span> 
                        <?php echo esc_html__('products in table', 'mitnafun-order-admin'); ?>
                    </p>
                    <div id="product-count-mismatch" class="notice notice-warning inline" style="display: none; margin: 5px 0; padding: 5px 10px;">
                        <p>
                            <span class="dashicons dashicons-warning"></span> 
                            <?php echo esc_html__('Some products with stock management are not displayed. Click "Release Stock Issues" to fix.', 'mitnafun-order-admin'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th data-label="<?php esc_attr_e('Product', 'mitnafun-order-admin'); ?>"><?php echo esc_html__('Product', 'mitnafun-order-admin'); ?></th>
                                <th data-label="<?php esc_attr_e('Total Stock', 'mitnafun-order-admin'); ?>"><?php echo esc_html__('Total Stock', 'mitnafun-order-admin'); ?></th>
                                <th data-label="<?php esc_attr_e('WooCommerce Stock', 'mitnafun-order-admin'); ?>"><?php echo esc_html__('WooCommerce Stock', 'mitnafun-order-admin'); ?></th>
                                <th data-label="<?php esc_attr_e('Status', 'mitnafun-order-admin'); ?>"><?php echo esc_html__('Status', 'mitnafun-order-admin'); ?></th>
                                <th data-label="<?php esc_attr_e('Actions', 'mitnafun-order-admin'); ?>"><?php echo esc_html__('Actions', 'mitnafun-order-admin'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $products = wc_get_products([
                                'limit' => -1,
                                'status' => 'publish',
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ]);
                            
                            foreach ($products as $product) {
                                $product_id = $product->get_id();
                                $total_stock = get_post_meta($product_id, '_initial_stock', true);
                                $woo_stock = $product->get_stock_quantity();
                                $stock_status = $product->get_stock_status();
                                $status_class = $stock_status === 'instock' ? 'in-stock' : 'out-of-stock';
                                $status_text = $stock_status === 'instock' ? __('In Stock', 'mitnafun-order-admin') : __('Out of Stock', 'mitnafun-order-admin');
                                
                                // Skip products that don't manage stock and don't have total stock set
                                if (!$product->managing_stock() && $total_stock === '') {
                                    continue;
                                }
                                ?>
                                <tr>
                                    <td data-label="<?php esc_attr_e('Product', 'mitnafun-order-admin'); ?>">
                                        <a href="<?php echo esc_url(get_edit_post_link($product_id)); ?>" target="_blank">
                                            <?php echo esc_html($product->get_name()); ?>
                                        </a>
                                    </td>
                                    <td class="total-stock" data-label="<?php esc_attr_e('Total Stock', 'mitnafun-order-admin'); ?>">
                                        <?php echo esc_html($total_stock !== '' ? $total_stock : '—'); ?>
                                    </td>
                                    <td class="woocommerce-stock" data-label="<?php esc_attr_e('WooCommerce Stock', 'mitnafun-order-admin'); ?>">
                                        <?php echo esc_html($woo_stock !== null ? $woo_stock : '—'); ?>
                                    </td>
                                    <td data-label="<?php esc_attr_e('Status', 'mitnafun-order-admin'); ?>">
                                        <span class="stock-status <?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </td>
                                    <td data-label="<?php esc_attr_e('Actions', 'mitnafun-order-admin'); ?>">
                                        <?php if ($product->managing_stock() && $total_stock !== '') : ?>
                                            <button type="button" class="button button-small sync-single-stock" 
                                                    data-product-id="<?php echo esc_attr($product_id); ?>">
                                                <?php echo esc_html__('Sync', 'mitnafun-order-admin'); ?>
                                            </button>
                                            <span class="sync-result"></span>
                                        <?php else : ?>
                                            <span class="description"><?php echo esc_html__('Stock not managed', 'mitnafun-order-admin'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($products)) : ?>
                    <div class="notice notice-warning">
                        <p><?php echo esc_html__('No products found with stock management enabled.', 'mitnafun-order-admin'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Make table rows clickable on mobile
        $('.wp-list-table tbody tr').on('click', function(e) {
            if ($(window).width() <= 782) {
                $(this).toggleClass('expanded');
            }
        });
    });
    </script>
</div>
