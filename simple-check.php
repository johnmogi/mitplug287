<?php
// Simple PHP environment check
echo "=== PHP Environment Check ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Current Directory: " . __DIR__ . "\n";

// Check file system access
$test_file = __DIR__ . '/test_' . time() . '.txt';
$test_content = 'Test content ' . date('Y-m-d H:i:s');

// Test file write
if (file_put_contents($test_file, $test_content)) {
    echo "File write test: SUCCESS\n";
    
    // Test file read
    if (file_exists($test_file) && file_get_contents($test_file) === $test_content) {
        echo "File read test: SUCCESS\n";
    } else {
        echo "File read test: FAILED\n";
    }
    
    // Clean up
    unlink($test_file);
} else {
    echo "File write test: FAILED (check permissions)\n";
}

// List directory contents
echo "\n=== Directory Contents ===\n";
$files = scandir(__DIR__);
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        echo "- $file\n";
    }
}

echo "\n=== Check Complete ===\n";
