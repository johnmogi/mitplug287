<?php
// Script to test various database connection parameters

// Common database configurations to try
$configs = [
    // Default XAMPP settings
    [
        'name' => 'XAMPP Default',
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'db'   => 'local',
        'prefix' => 'edc_'
    ],
    // Common WordPress settings
    [
        'name' => 'Common WordPress',
        'host' => 'localhost',
        'user' => 'wordpress',
        'pass' => 'wordpress',
        'db'   => 'wordpress',
        'prefix' => 'wp_'
    ],
    // Common WAMP settings
    [
        'name' => 'WAMP Default',
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'db'   => 'local',
        'prefix' => 'wp_'
    ],
    // Common MAMP settings
    [
        'name' => 'MAMP Default',
        'host' => 'localhost',
        'user' => 'root',
        'pass' => 'root',
        'db'   => 'local',
        'prefix' => 'wp_'
    ],
    // Local by Flywheel
    [
        'name' => 'Local by Flywheel',
        'host' => 'localhost',
        'user' => 'root',
        'pass' => 'root',
        'db'   => 'local',
        'prefix' => 'wp_'
    ],
    // Docker default
    [
        'name' => 'Docker Default',
        'host' => 'db',
        'user' => 'wordpress',
        'pass' => 'wordpress',
        'db'   => 'wordpress',
        'prefix' => 'wp_'
    ]
];

// Add config from wp-config.php if it exists
$wp_config_path = __DIR__ . '/wp-config-path.txt';
if (file_exists($wp_config_path)) {
    $wp_config_path = trim(file_get_contents($wp_config_path));
    if (file_exists($wp_config_path)) {
        $wp_config = file_get_contents($wp_config_path);
        
        // Extract database credentials
        preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']+)'/i", $wp_config, $db_name);
        preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']+)'/i", $wp_config, $db_user);
        preg_match("/define\s*\(\s*'DB_PASSWORD'\s*,\s*'([^']*)'/i", $wp_config, $db_pass);
        preg_match("/define\s*\(\s*'DB_HOST'\s*,\s*'([^']+)'/i", $wp_config, $db_host);
        preg_match("/\$table_prefix\s*=\s*'([^']+)'/i", $wp_config, $table_prefix);
        
        if (!empty($db_name[1]) && !empty($db_user[1])) {
            $configs[] = [
                'name' => 'From wp-config.php',
                'host' => !empty($db_host[1]) ? $db_host[1] : 'localhost',
                'user' => $db_user[1],
                'pass' => !empty($db_pass[1]) ? $db_pass[1] : '',
                'db'   => $db_name[1],
                'prefix' => !empty($table_prefix[1]) ? $table_prefix[1] : 'wp_'
            ];
        }
    }
}

echo "=== Database Connection Tester ===\n\n";

$success = false;

foreach ($configs as $config) {
    echo "Testing: {$config['name']}... ";
    echo "mysql://{$config['user']}@{$config['host']}/{$config['db']}... ";
    
    try {
        // Test connection to MySQL server
        $mysqli = @new mysqli($config['host'], $config['user'], $config['pass']);
        
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        echo "✅ Connected to MySQL. ";
        
        // Check if database exists
        $result = $mysqli->query("SHOW DATABASES LIKE '{$config['db']}'");
        if ($result->num_rows === 0) {
            throw new Exception("Database '{$config['db']}' does not exist.");
        }
        
        // Select the database
        if (!$mysqli->select_db($config['db'])) {
            throw new Exception("Could not select database '{$config['db']}': " . $mysqli->error);
        }
        
        echo "✅ Database '{$config['db']}' exists. ";
        
        // Check if the posts table exists
        $posts_table = $config['prefix'] . 'posts';
        $result = $mysqli->query("SHOW TABLES LIKE '$posts_table'");
        
        if ($result->num_rows === 0) {
            echo "❌ Table '$posts_table' does not exist. ";
            
            // List available tables
            $tables = $mysqli->query("SHOW TABLES");
            $table_list = [];
            while ($row = $tables->fetch_row()) {
                $table_list[] = $row[0];
            }
            
            if (!empty($table_list)) {
                echo "Available tables: " . implode(', ', array_slice($table_list, 0, 10));
                if (count($table_list) > 10) {
                    echo "... and " . (count($table_list) - 10) . " more";
                }
            } else {
                echo "No tables found in the database.";
            }
            
            echo "\n";
        } else {
            // Count products
            $result = $mysqli->query("SELECT COUNT(*) as count FROM $posts_table WHERE post_type = 'product'");
            $row = $result->fetch_assoc();
            $product_count = $row['count'];
            
            echo "✅ Found $product_count products. ";
            
            // Get a list of product categories
            $terms_table = $config['prefix'] . 'terms';
            $term_taxonomy_table = $config['prefix'] . 'term_taxonomy';
            $result = $mysqli->query("
                SELECT t.name, COUNT(p.ID) as product_count
                FROM $terms_table t
                JOIN $term_taxonomy_table tt ON t.term_id = tt.term_id
                JOIN {$config['prefix']}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                JOIN $posts_table p ON tr.object_id = p.ID
                WHERE tt.taxonomy = 'product_cat'
                AND p.post_type = 'product'
                GROUP BY t.term_id
                ORDER BY product_count DESC
                LIMIT 5
            ");
            
            if ($result && $result->num_rows > 0) {
                echo "\nTop product categories:\n";
                while ($row = $result->fetch_assoc()) {
                    echo "- {$row['name']} ({$row['product_count']} products)\n";
                }
            }
            
            $success = true;
            echo "\n";
            break; // Stop after first successful connection
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo "❌ " . $e->getMessage() . "\n";
    }
}

if (!$success) {
    echo "\n❌ Could not establish a successful database connection with any of the tested configurations.\n";
    echo "Please check your database server is running and the credentials are correct.\n";
}

echo "\n=== Test Complete ===\n";
