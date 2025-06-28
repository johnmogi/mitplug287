<?php
// Direct database query script to find a product by name

// Database configuration - update these with your actual database credentials
$db_host = 'localhost';
$db_user = 'root';      // Default XAMPP username
$db_pass = '';          // Default XAMPP password (empty)
$db_name = 'local';    // Database name from the error message
$table_prefix = 'edc_'; // Table prefix from the error message

// Product to search for
$search_term = 'מגה סלייד דקלים';

echo "=== Direct Product Query ===\n";
echo "Database: $db_name@$db_host\n";
echo "Table Prefix: $table_prefix\n";
echo "Searching for: $search_term\n\n";

try {
    // Connect to MySQL server
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    // Set charset to handle special characters
    $mysqli->set_charset('utf8mb4');
    
    echo "✅ Connected to database.\n";
    
    // Check if the posts table exists
    $result = $mysqli->query("SHOW TABLES LIKE '{$table_prefix}posts'");
    if ($result->num_rows === 0) {
        throw new Exception("Table {$table_prefix}posts does not exist.");
    }
    
    // Search for products with similar names
    $query = $mysqli->prepare("
        SELECT p.ID, p.post_title, p.post_status, p.post_type
        FROM {$table_prefix}posts p
        WHERE p.post_type = 'product' 
        AND p.post_title LIKE ?
        ORDER BY p.ID
    ");
    
    // Try different search patterns if needed
    $search_patterns = [
        "%$search_term%",
        "%" . substr($search_term, 0, 5) . "%",
        "%" . substr($search_term, 5) . "%"
    ];
    
    $found_products = [];
    
    foreach ($search_patterns as $pattern) {
        echo "\nSearching with pattern: '$pattern'...\n";
        
        $query->bind_param('s', $pattern);
        
        if (!$query->execute()) {
            echo "Query failed: " . $mysqli->error . "\n";
            continue;
        }
        
        $result = $query->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $found_products[$row['ID']] = $row;
            }
        }
    }
    
    if (empty($found_products)) {
        echo "\n❌ No products found matching: $search_term\n";
        
        // List all products to help debug
        echo "\n=== Listing Recent Products ===\n";
        
        $result = $mysqli->query("
            SELECT ID, post_title, post_status, post_type
            FROM {$table_prefix}posts
            WHERE post_type = 'product'
            ORDER BY ID DESC
            LIMIT 10
        ");
        
        if ($result && $result->num_rows > 0) {
            echo "Recent products in database:\n";
            while ($row = $result->fetch_assoc()) {
                echo "- ID: {$row['ID']}, Title: {$row['post_title']}, Status: {$row['post_status']}\n";
            }
        } else {
            echo "No products found in the database.\n";
        }
    } else {
        echo "\n=== Found Products ===\n";
        
        foreach ($found_products as $product) {
            echo "\nID: {$product['ID']}\n";
            echo "Title: {$product['post_title']}\n";
            echo "Status: {$product['post_status']}\n";
            echo "Type: {$product['post_type']}\n";
            
            // Get product meta
            $meta_query = $mysqli->prepare("
                SELECT meta_key, meta_value 
                FROM {$table_prefix}postmeta 
                WHERE post_id = ?
                AND meta_key IN ('_sku', '_price', '_regular_price', '_sale_price', '_stock_status', '_stock')
            ");
            
            $meta_query->bind_param('i', $product['ID']);
            
            if ($meta_query->execute()) {
                $meta_result = $meta_query->get_result();
                
                if ($meta_result->num_rows > 0) {
                    echo "\nMeta Data:\n";
                    while ($meta = $meta_result->fetch_assoc()) {
                        echo "- {$meta['meta_key']}: {$meta['meta_value']}\n";
                    }
                }
            }
            
            // Get product categories
            $cat_query = $mysqli->prepare("
                SELECT t.name
                FROM {$table_prefix}term_relationships tr
                JOIN {$table_prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$table_prefix}terms t ON tt.term_id = t.term_id
                WHERE tr.object_id = ?
                AND tt.taxonomy = 'product_cat'
            ");
            
            $cat_query->bind_param('i', $product['ID']);
            
            if ($cat_query->execute()) {
                $cat_result = $cat_query->get_result();
                
                if ($cat_result->num_rows > 0) {
                    $categories = [];
                    while ($cat = $cat_result->fetch_row()) {
                        $categories[] = $cat[0];
                    }
                    echo "\nCategories: " . implode(', ', $categories) . "\n";
                }
            }
            
            echo "\n" . str_repeat("-", 50) . "\n";
        }
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Query Complete ===\n";
