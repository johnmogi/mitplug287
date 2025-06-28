<?php
// Script to check WordPress installation path and environment

// Try to find wp-load.php in common locations
$possible_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
    'C:\\xampp\\htdocs\\wp-load.php',
    'C:\\wamp64\\www\\wp-load.php',
    'C:\\xampp\\htdocs\\BMIT\\wp-load.php',
    'C:\\xampp\\htdocs\\BMIT\\app\\public\\wp-load.php',
];

echo "=== WordPress Path Checker ===\n\n";

$wp_loaded = false;

foreach ($possible_paths as $path) {
    echo "Checking: $path... ";
    
    if (file_exists($path)) {
        echo "✅ Found!\n";
        
        // Try to include wp-load.php
        try {
            define('WP_USE_THEMES', false);
            require_once($path);
            
            // If we get here, WordPress loaded successfully
            $wp_loaded = true;
            
            echo "✅ WordPress loaded successfully!\n\n";
            
            // Display WordPress information
            echo "=== WordPress Information ===\n";
            echo "Site URL: " . site_url() . "\n";
            echo "Home URL: " . home_url() . "\n";
            echo "WordPress Version: " . get_bloginfo('version') . "\n";
            echo "Active Theme: " . wp_get_theme()->get('Name') . " " . wp_get_theme()->get('Version') . "\n\n";
            
            // Check if WooCommerce is active
            if (class_exists('WooCommerce')) {
                echo "✅ WooCommerce is active.\n";
                echo "WooCommerce Version: " . WC_VERSION . "\n\n";
                
                // Count products
                $product_count = wp_count_posts('product');
                echo "Total Products: " . $product_count->publish . " published\n";
                
                // Search for our product
                $search_term = 'מגה סלייד דקלים';
                echo "\nSearching for product: $search_term\n";
                
                $args = [
                    'post_type' => 'product',
                    'post_status' => 'any',
                    's' => $search_term,
                    'posts_per_page' => 10,
                ];
                
                $query = new WP_Query($args);
                
                if ($query->have_posts()) {
                    echo "\n=== Found Products ===\n";
                    
                    while ($query->have_posts()) {
                        $query->the_post();
                        $product = wc_get_product(get_the_ID());
                        
                        echo "\nID: " . get_the_ID() . "\n";
                        echo "Title: " . get_the_title() . "\n";
                        echo "Status: " . get_post_status() . "\n";
                        echo "Type: " . $product->get_type() . "\n";
                        echo "Price: " . $product->get_price() . "\n";
                        echo "Stock Status: " . $product->get_stock_status() . "\n";
                        
                        if ($product->is_type('variation')) {
                            echo "Parent ID: " . $product->get_parent_id() . "\n";
                        }
                        
                        // Get categories
                        $categories = get_the_terms(get_the_ID(), 'product_cat');
                        if ($categories && !is_wp_error($categories)) {
                            $category_names = wp_list_pluck($categories, 'name');
                            echo "Categories: " . implode(', ', $category_names) . "\n";
                        }
                        
                        echo str_repeat("-", 50) . "\n";
                    }
                    
                    wp_reset_postdata();
                } else {
                    echo "No products found matching: $search_term\n";
                }
            } else {
                echo "❌ WooCommerce is not active.\n";
            }
            
            break; // Stop checking other paths
            
        } catch (Exception $e) {
            echo "❌ Error loading WordPress: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Not found.\n";
    }
}

if (!$wp_loaded) {
    echo "\n❌ Could not load WordPress. Please check the installation path.\n";
    
    // Try to list files in the current directory to help debug
    echo "\nCurrent directory: " . __DIR__ . "\n";
    
    if (is_dir(__DIR__)) {
        echo "Contents of current directory:\n";
        $files = scandir(__DIR__);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "- $file" . (is_dir(__DIR__ . "/$file") ? "/" : "") . "\n";
            }
        }
    } else {
        echo "Cannot read current directory.\n";
    }
}

echo "\n=== Check Complete ===\n";
