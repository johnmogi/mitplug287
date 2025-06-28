<?php
// Script to locate wp-config.php and check WordPress installation

// Possible locations to check for wp-config.php
$possible_locations = [
    __DIR__ . '/../../../wp-config.php',  // Standard location
    __DIR__ . '/../../../../wp-config.php', // One level up
    __DIR__ . '/../../../../../wp-config.php', // Two levels up
    dirname(dirname(dirname(__DIR__))) . '/wp-config.php', // Absolute path
    'C:\\xampp\\htdocs\\wp-config.php', // Common XAMPP location
    'C:\\wamp64\\www\\wp-config.php', // Common WAMP location
    'C:\\xampp\\htdocs\\BMIT\\wp-config.php', // Possible BMIT location
    'C:\\xampp\\htdocs\\BMIT\\app\\public\\wp-config.php', // Possible BMIT app location
];

echo "=== WordPress Configuration Locator ===\n\n";

$found = false;

foreach ($possible_locations as $location) {
    echo "Checking: $location... ";
    
    if (file_exists($location)) {
        echo "✅ Found!\n";
        $found = true;
        
        // Read the first few lines to confirm it's a WordPress config
        $handle = fopen($location, 'r');
        $first_line = fgets($handle);
        fclose($handle);
        
        if (strpos($first_line, '<?php') !== false) {
            echo "This appears to be a PHP file.\n";
            
            // Check if it contains WordPress configuration
            $content = file_get_contents($location);
            if (strpos($content, 'DB_NAME') !== false && 
                strpos($content, 'DB_USER') !== false && 
                strpos($content, 'DB_PASSWORD') !== false) {
                echo "✅ This is a WordPress configuration file.\n";
                
                // Extract database credentials
                preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']+)'/i", $content, $db_name);
                preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']+)'/i", $content, $db_user);
                preg_match("/define\s*\(\s*'DB_PASSWORD'\s*,\s*'([^']*)'/i", $content, $db_password);
                preg_match("/define\s*\(\s*'DB_HOST'\s*,\s*'([^']+)'/i", $content, $db_host);
                preg_match("/\$table_prefix\s*=\s*'([^']+)'/i", $content, $table_prefix);
                
                echo "\n=== WordPress Configuration ===\n";
                echo "DB_NAME: " . (!empty($db_name[1]) ? $db_name[1] : 'Not found') . "\n";
                echo "DB_USER: " . (!empty($db_user[1]) ? $db_user[1] : 'Not found') . "\n";
                echo "DB_PASSWORD: " . (!empty($db_password[1]) ? '*** (set)' : 'Not found') . "\n";
                echo "DB_HOST: " . (!empty($db_host[1]) ? $db_host[1] : 'Not found') . "\n";
                echo "Table Prefix: " . (!empty($table_prefix[1]) ? $table_prefix[1] : 'wp_') . "\n";
                
                // Save the path to a file for other scripts to use
                file_put_contents(__DIR__ . '/wp-config-path.txt', $location);
                echo "\n✅ Configuration saved to wp-config-path.txt\n";
                
                break;
            } else {
                echo "This PHP file doesn't appear to be a WordPress configuration file.\n";
            }
        } else {
            echo "This file doesn't appear to be a PHP file.\n";
        }
    } else {
        echo "Not found.\n";
    }
}

if (!$found) {
    echo "\n❌ Could not locate wp-config.php in any of the standard locations.\n";
    echo "Please check the file exists and the web server has permission to read it.\n";
    
    // Try to list files in parent directories to help locate it
    echo "\nAttempting to list parent directories to help locate wp-config.php...\n";
    
    $dirs_to_check = [
        dirname(__DIR__, 3), // wp-content/plugins/..
        dirname(__DIR__, 4), // wp-content/../..
        dirname(__DIR__, 5), // wp-content/../../..
        'C:\\xampp\\htdocs',
        'C:\\wamp64\\www',
    ];
    
    foreach ($dirs_to_check as $dir) {
        if (is_dir($dir)) {
            echo "\nContents of $dir:\n";
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo "- $file" . (is_dir("$dir/$file") ? '/' : '') . "\n";
                }
            }
        } else {
            echo "\nDirectory does not exist: $dir\n";
        }
    }
}

echo "\n=== Script Complete ===\n";
