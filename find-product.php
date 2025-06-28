<?php
// Script to find a product by name using the correct table prefix

// Database configuration - update these with your actual database credentials
$db_host = 'localhost';
$db_user = 'root';      // Default XAMPP username
$db_pass = '';          // Default XAMPP password (empty)
$db_name = 'local';    // Database name from the error message
$table_prefix = 'edc_'; // Table prefix from the error message

// Product to search for
$search_term = 'מגה סלייד דקלים';

echo "=== Product Search ===\n";
echo "Database: $db_name@$db_host\n";
echo "Table Prefix: $table_prefix\n";
echo "Searching for: $search_term\n\n";

try {
    // Connect to MySQL server
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "✅ Connected to database.\n";
    
    // Check if the posts table exists
    $result = $mysqli->query("SHOW TABLES LIKE '{$table_prefix}posts'");
    if ($result->num_rows === 0) {
        throw new Exception("Table {$table_prefix}posts does not exist.");
    }
    
    // Search for the product
    $query = $mysqli->prepare("
        SELECT p.ID, p.post_title, p.post_status, p.post_type, 
               pm.meta_key, pm.meta_value
        FROM {$table_prefix}posts p
        LEFT JOIN {$table_prefix}postmeta pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product' 
        AND p.post_title LIKE ?
        ORDER BY p.ID
    ");
    
    $search_param = "%$search_term%";
    $query->bind_param('s', $search_param);
    
    if (!$query->execute()) {
        throw new Exception("Query failed: " . $mysqli->error);
    }
    
    $result = $query->get_result();
    $products = [];
    
    // Group meta data by product
    while ($row = $result->fetch_assoc()) {
        $product_id = $row['ID'];
        if (!isset($products[$product_id])) {
            $products[$product_id] = [
                'ID' => $row['ID'],
                'post_title' => $row['post_title'],
                'post_status' => $row['post_status'],
                'post_type' => $row['post_type'],
                'meta' => []
            ];
        }
        
        if ($row['meta_key']) {
            $products[$product_id]['meta'][$row['meta_key']] = $row['meta_value'];
        }
    }
    
    if (empty($products)) {
        echo "\n❌ No products found matching: $search_term\n";
        
        // Try a broader search
        echo "\n=== Trying a broader search ===\n";
        
        $query = $mysqli->prepare("
            SELECT ID, post_title, post_status, post_type
            FROM {$table_prefix}posts
            WHERE post_type = 'product'
            ORDER BY ID DESC
            LIMIT 10
        ");
        
        if (!$query->execute()) {
            throw new Exception("Query failed: " . $mysqli->error);
        }
        
        $result = $query->get_result();
        
        if ($result->num_rows === 0) {
            echo "No products found in the database.\n";
        } else {
            echo "Recent products in database:\n";
            while ($row = $result->fetch_assoc()) {
                echo "- ID: {$row['ID']}, Title: {$row['post_title']}, Status: {$row['post_status']}\n";
            }
        }
    } else {
        echo "\n=== Found Products ===\n";
        
        foreach ($products as $product) {
            echo "\nID: {$product['ID']}\n";
            echo "Title: {$product['post_title']}\n";
            echo "Status: {$product['post_status']}\n";
            echo "Type: {$product['post_type']}\n";
            
            // Display important meta data
            $important_meta = [
                '_sku',
                '_price',
                '_regular_price',
                '_sale_price',
                '_stock_status',
                '_stock',
                '_manage_stock',
                '_backorders',
                '_sold_individually',
                '_virtual',
                '_downloadable',
                '_product_attributes'
            ];
            
            echo "\nMeta Data:\n";
            foreach ($important_meta as $meta_key) {
                if (isset($product['meta'][$meta_key])) {
                    echo "- $meta_key: " . $product['meta'][$meta_key] . "\n";
                }
            }
            
            // Check if product is a variation
            if ($product['post_type'] === 'product_variation') {
                $parent_id = $product['meta']['_parent_id'] ?? 'Unknown';
                echo "\nThis is a variation of product ID: $parent_id\n";
            }
            
            // Check product categories and tags
            $categories = [];
            $tags = [];
            
            $term_query = $mysqli->prepare("
                SELECT t.name, tt.taxonomy
                FROM {$table_prefix}term_relationships tr
                JOIN {$table_prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$table_prefix}terms t ON tt.term_id = t.term_id
                WHERE tr.object_id = ?
            ");
            
            $term_query->bind_param('i', $product['ID']);
            
            if ($term_query->execute()) {
                $term_result = $term_query->get_result();
                
                while ($term = $term_result->fetch_assoc()) {
                    if ($term['taxonomy'] === 'product_cat') {
                        $categories[] = $term['name'];
                    } elseif ($term['taxonomy'] === 'product_tag') {
                        $tags[] = $term['name'];
                    }
                }
                
                if (!empty($categories)) {
                    echo "\nCategories: " . implode(', ', $categories) . "\n";
                }
                
                if (!empty($tags)) {
                    echo "Tags: " . implode(', ', $tags) . "\n";
                }
            }
            
            echo "\n" . str_repeat("-", 50) . "\n";
        }
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Search Complete ===\n";
