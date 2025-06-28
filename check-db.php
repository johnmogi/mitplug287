<?php
// Minimal database connection test

// Path to wp-config.php
$wp_config_path = __DIR__ . '/../../../wp-config.php';

if (!file_exists($wp_config_path)) {
    die("Error: wp-config.php not found at $wp_config_path\n");
}

// Extract database credentials from wp-config.php
$wp_config = file_get_contents($wp_config_path);

// Define variables to store database credentials
$db_name = '';
$db_user = '';
$db_password = '';
$db_host = '';
$table_prefix = 'wp_';

// Extract database credentials using regex
if (preg_match("/define\s*\(\s*['"]DB_NAME['"]\s*,\s*['"]([^'"]+)['"]/i", $wp_config, $matches)) {
    $db_name = $matches[1];
}
if (preg_match("/define\s*\(\s*['"]DB_USER['"]\s*,\s*['"]([^'"]+)['"]/i", $wp_config, $matches)) {
    $db_user = $matches[1];
}
if (preg_match("/define\s*\(\s*['"]DB_PASSWORD['"]\s*,\s*['"]([^'"]*)['"]/i", $wp_config, $matches)) {
    $db_password = $matches[1];
}
if (preg_match("/define\s*\(\s*['"]DB_HOST['"]\s*,\s*['"]([^'"]+)['"]/i", $wp_config, $matches)) {
    $db_host = $matches[1];
}
if (preg_match("/\$table_prefix\s*=\s*['"]([^'"]+)['"]/i", $wp_config, $matches)) {
    $table_prefix = $matches[1];
}

// Output extracted information
echo "=== Database Configuration ===\n";
echo "DB Name: $db_name\n";
echo "DB User: $db_user\n";
echo "DB Host: $db_host\n";
echo "Table Prefix: $table_prefix\n\n";

// Try to connect to the database
try {
    $mysqli = new mysqli($db_host, $db_user, $db_password, $db_name);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "Successfully connected to the database.\n\n";
    
    // List all tables
    $result = $mysqli->query("SHOW TABLES");
    if ($result) {
        $tables = [];
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        echo "=== Database Tables (" . count($tables) . ") ===\n";
        
        // Group tables by prefix
        $grouped_tables = [];
        foreach ($tables as $table) {
            $prefix = preg_match('/^([a-zA-Z0-9_]+_)/', $table, $matches) ? $matches[1] : 'other_';
            if (!isset($grouped_tables[$prefix])) {
                $grouped_tables[$prefix] = [];
            }
            $grouped_tables[$prefix][] = $table;
        }
        
        // Output tables by prefix
        foreach ($grouped_tables as $prefix => $tables) {
            echo "\n=== Tables with prefix: $prefix (" . count($tables) . ") ===\n";
            sort($tables);
            foreach (array_chunk($tables, 4) as $chunk) {
                echo implode(", ", $chunk) . "\n";
            }
        }
        
        // Check for WordPress core tables
        $core_tables = [
            'posts', 'comments', 'links', 'options', 
            'postmeta', 'terms', 'term_taxonomy', 'term_relationships',
            'commentmeta', 'users', 'usermeta'
        ];
        
        $missing_tables = [];
        foreach ($core_tables as $table) {
            $full_table_name = $table_prefix . $table;
            if (!in_array($full_table_name, $tables)) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            echo "\n=== Missing Core WordPress Tables ===\n";
            echo "The following core WordPress tables were not found:\n";
            echo "- " . implode("\n- ", $missing_tables) . "\n";
        } else {
            echo "\nAll core WordPress tables are present.\n";
        }
        
        // Check for WooCommerce tables
        $woo_tables = array_filter($tables, function($table) {
            return strpos($table, 'woocommerce') !== false || 
                   strpos($table, 'wc_') === 0 ||
                   strpos($table, '_wc_') !== false;
        });
        
        if (!empty($woo_tables)) {
            echo "\n=== WooCommerce Tables (" . count($woo_tables) . ") ===\n";
            sort($woo_tables);
            foreach (array_chunk($woo_tables, 3) as $chunk) {
                echo "- " . implode(", ", $chunk) . "\n";
            }
        } else {
            echo "\nNo WooCommerce tables found.\n";
        }
        
        // Count posts
        $posts_table = $table_prefix . 'posts';
        if (in_array($posts_table, $tables)) {
            $result = $mysqli->query("SELECT post_type, COUNT(*) as count FROM $posts_table GROUP BY post_type");
            if ($result) {
                echo "\n=== Post Counts by Type ===\n";
                while ($row = $result->fetch_assoc()) {
                    echo "- {$row['post_type']}: {$row['count']}\n";
                }
            }
        }
        
    } else {
        throw new Exception("Failed to list tables: " . $mysqli->error);
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
}

echo "\n=== Script Complete ===\n";
