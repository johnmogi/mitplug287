<?php
// Script to find a product by name using the edc_ table prefix

// Database configuration - update these with your actual database credentials
$db_host = 'localhost';
$db_user = 'root';      // Common default
$db_pass = '';          // Common default (empty password)
$db_name = 'local';    // Database name from wp-config.php
$table_prefix = 'edc_'; // Table prefix from wp-config.php

// Product to search for
$search_term = 'מגה סלייד דקלים';

echo "=== Product Search with edc_ Prefix ===\n";
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
    
    // Check if the posts table exists with the edc_ prefix
    $posts_table = $table_prefix . 'posts';
    $result = $mysqli->query("SHOW TABLES LIKE '$posts_table'");
    if ($result->num_rows === 0) {
        throw new Exception("Table $posts_table does not exist.");
    }
    
    echo "✅ Table $posts_table exists.\n";
    
    // Search for the product in posts table
    $query = $mysqli->prepare("
        SELECT ID, post_title, post_status, post_type
        FROM {$table_prefix}posts
        WHERE post_type IN ('product', 'product_variation')
        AND post_title LIKE ?
        ORDER BY ID
    ");
    
    // Try different search patterns
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
            
            // List all post types to see what's in the database
            $result = $mysqli->query("
                SELECT post_type, COUNT(*) as count
                FROM {$table_prefix}posts
                GROUP BY post_type
                ORDER BY count DESC
            ");
            
            if ($result && $result->num_rows > 0) {
                echo "\nPost types in database:\n";
                while ($row = $result->fetch_assoc()) {
                    echo "- {$row['post_type']}: {$row['count']} posts\n";
                }
            }
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
            
            echo "\n" . str_repeat("-", 50) . "\n";
        }
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    
    // If connection fails, try to provide more detailed error information
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "\nAuthentication failed. Please check your database credentials.\n";
        echo "Tried connecting with: $db_user@$db_host\n";
    }
}

echo "\n=== Search Complete ===\n";
