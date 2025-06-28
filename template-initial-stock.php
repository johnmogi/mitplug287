<?php
/**
 * Template Name: Initial Stock Display
 * Description: Displays initial stock for a product
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get the product ID from URL parameter or use default
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
if (!$product_id) {
    $product_id = 636; // Default product ID
}

// Get the initial stock value
$initial_stock = get_post_meta($product_id, '_initial_stock', true);
$product = $product_id ? wc_get_product($product_id) : null;

get_header();
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1>Stock Information</h1>
            
            <?php if ($product) : ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h4"><?php echo esc_html($product->get_name()); ?></h2>
                        
                        <div class="stock-info mt-3 p-3 bg-light rounded">
                            <h3 class="h5">Stock Details</h3>
                            <table class="table">
                                <tr>
                                    <th>Initial Stock:</th>
                                    <td>
                                        <?php if ($initial_stock !== '') : ?>
                                            <strong><?php echo esc_html($initial_stock); ?></strong>
                                        <?php else : ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Current Stock:</th>
                                    <td>
                                        <?php if ($product->managing_stock()) : ?>
                                            <?php echo esc_html($product->get_stock_quantity()); ?>
                                        <?php else : ?>
                                            <span class="text-muted">Not managed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Stock Status:</th>
                                    <td>
                                        <?php echo esc_html(ucfirst($product->get_stock_status())); ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <?php if ($initial_stock === '') : ?>
                            <div class="alert alert-warning mt-3">
                                No initial stock value found for this product.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="alert alert-danger">
                    Product not found. Please check the product ID.
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Test Different Product</h2>
                    <form method="get" action="" class="mt-3">
                        <input type="hidden" name="p" value="<?php echo get_the_ID(); ?>">
                        <div class="input-group">
                            <input type="number" 
                                   name="product_id" 
                                   class="form-control" 
                                   placeholder="Enter Product ID" 
                                   value="<?php echo esc_attr($product_id); ?>">
                            <button type="submit" class="btn btn-primary">Show Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stock-info {
    border-left: 4px solid #2271b1;
}
.alert {
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: .25rem;
}
.alert-warning {
    color: #856404;
    background-color: #fff3cd;
    border-color: #ffeeba;
}
.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}
</style>

<?php
get_footer();