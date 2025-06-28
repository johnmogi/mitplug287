<?php
// Direct database check script

// Database configuration - update these with your actual database credentials
$db_host = 'localhost';
$db_user = 'root';      // Default XAMPP username
$db_pass = '';          // Default XAMPP password (empty)
$db_name = 'wordpress'; // Default WordPress database name
$table_prefix = 'wp_';  // Default table prefix

echo "=== Direct Database Check ===\n";
echo "Database: $db_name@$db_host\n";
echo "User: $db_user\n\n";

try {
    // Connect to MySQL server
    $mysqli = new mysqli($db_host, $db_user, $db_pass);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "✅ Connected to MySQL server.\n";
    echo "MySQL Server Version: " . $mysqli->server_info . "\n\n";
    
    // List all databases
    echo "=== Available Databases ===\n";
    $result = $mysqli->query("SHOW DATABASES");
    $databases = [];
    while ($row = $result->fetch_row()) {
        $databases[] = $row[0];
        echo "- " . $row[0] . "\n";
    }
    
    // If our database doesn't exist, suggest creating it
    if (!in_array($db_name, $databases)) {
        echo "\n❌ Database '$db_name' does not exist.\n";
        echo "You may need to create it or restore from a backup.\n";
        exit(1);
    }
    
    // Select the database
    if (!$mysqli->select_db($db_name)) {
        throw new Exception("Could not select database '$db_name': " . $mysqli->error);
    }
    
    echo "\n✅ Selected database: $db_name\n";
    
    // List all tables
    $result = $mysqli->query("SHOW TABLES");
    $tables = [];
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    echo "\n=== Tables in '$db_name' (" . count($tables) . ") ===\n";
    
    if (count($tables) === 0) {
        echo "No tables found in the database.\n";
        echo "This appears to be a fresh WordPress installation or the database is empty.\n";
        exit(1);
    }
    
    // Show first 20 tables
    foreach (array_slice($tables, 0, 20) as $table) {
        echo "- $table\n";
    }
    
    if (count($tables) > 20) {
        echo "... and " . (count($tables) - 20) . " more tables\n";
    }
    
    // Check for WordPress tables
    $wp_tables = array_filter($tables, function($table) use ($table_prefix) {
        return strpos($table, $table_prefix) === 0;
    });
    
    echo "\n=== WordPress Tables (" . count($wp_tables) . ") ===\n";
    
    if (count($wp_tables) === 0) {
        echo "No WordPress tables found with prefix: $table_prefix\n";
        echo "This doesn't appear to be a WordPress database or the prefix is incorrect.\n";
        exit(1);
    }
    
    // Show first 10 WordPress tables
    foreach (array_slice($wp_tables, 0, 10) as $table) {
        echo "- $table\n";
    }
    
    if (count($wp_tables) > 10) {
        echo "... and " . (count($wp_tables) - 10) . " more WordPress tables\n";
    }
    
    // Check for posts table
    $posts_table = $table_prefix . 'posts';
    if (!in_array($posts_table, $tables)) {
        echo "\n❌ WordPress posts table '$posts_table' not found.\n";
        exit(1);
    }
    
    // Count products
    $result = $mysqli->query("SELECT COUNT(*) as count FROM $posts_table WHERE post_type = 'product'");
    if ($result) {
        $row = $result->fetch_assoc();
        $product_count = $row['count'];
        echo "\n=== Products ===\n";
        echo "Total Products: $product_count\n";
        
        if ($product_count > 0) {
            // Show first 5 products
            $result = $mysqli->query("SELECT ID, post_title, post_status FROM $posts_table 
                                    WHERE post_type = 'product' ORDER BY ID DESC LIMIT 5");
            
            echo "\n=== Recent Products ===\n";
            while ($row = $result->fetch_assoc()) {
                echo "- ID: {$row['ID']}, Title: {$row['post_title']}, Status: {$row['post_status']}\n";
            }
        }
    }
    
    // Search for our product
    $search_term = 'מגה סלייד דקלים';
    $query = $mysqli->prepare("SELECT ID, post_title, post_status FROM $posts_table 
                             WHERE post_type = 'product' AND post_title LIKE ?");
    $search_param = "%$search_term%";
    $query->bind_param('s', $search_param);
    
    echo "\n=== Searching for Product: $search_term ===\n";
    
    if ($query->execute()) {
        $result = $query->get_result();
        
        if ($result->num_rows > 0) {
            echo "\n=== Found Products ===\n";
            while ($row = $result->fetch_assoc()) {
                echo "- ID: {$row['ID']}, Title: {$row['post_title']}, Status: {$row['post_status']}\n";
                
                // Get product meta
                $meta_query = $mysqli->prepare("SELECT meta_key, meta_value FROM {$table_prefix}postmeta 
                                              WHERE post_id = ? AND meta_key IN ('_stock_status', '_stock', '_price')");
                $meta_query->bind_param('i', $row['ID']);
                
                if ($meta_query->execute()) {
                    $meta_result = $meta_query->get_result();
                    
                    if ($meta_result->num_rows > 0) {
                        echo "  Product Meta:\n";
                        while ($meta = $meta_result->fetch_assoc()) {
                            echo "  - {$meta['meta_key']}: {$meta['meta_value']}\n";
                        }
                    }
                }
            }
        } else {
            echo "No products found matching: $search_term\n";
        }
    } else {
        echo "Error searching for products: " . $mysqli->error . "\n";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Check Complete ===\n";
