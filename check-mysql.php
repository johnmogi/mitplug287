<?php
echo "=== MySQL Server Check ===\n";

// Common MySQL ports to check
$ports = [3306, 3307, 3308, 8889, 33060];
$hosts = ['localhost', '127.0.0.1', 'mysql', 'db'];

foreach ($hosts as $host) {
    echo "\nTrying host: $host\n";
    
    foreach ($ports as $port) {
        echo "  Port $port: ";
        
        // Test connection
        $connection = @fsockopen($host, $port, $errno, $errstr, 1);
        
        if (is_resource($connection)) {
            echo "✅ Open\n";
            
            // Try to connect with common credentials
            $users = [
                'root' => ['', 'root', 'password', 'admin'],
                'admin' => ['admin', 'password'],
                'wordpress' => ['wordpress', 'password'],
                'user' => ['user', 'password']
            ];
            
            foreach ($users as $user => $passwords) {
                if (!is_array($passwords)) {
                    $passwords = [$passwords];
                }
                
                foreach ($passwords as $password) {
                    try {
                        $mysqli = @new mysqli($host, $user, $password, '', $port);
                        
                        if (!$mysqli->connect_error) {
                            echo "  ✅ Connected as $user with password " . (empty($password) ? '(empty)' : '***') . "\n";
                            
                            // List databases
                            $result = $mysqli->query("SHOW DATABASES");
                            if ($result) {
                                $dbs = [];
                                while ($row = $result->fetch_row()) {
                                    $dbs[] = $row[0];
                                }
                                echo "  📊 Found " . count($dbs) . " databases\n";
                                
                                // Show first few databases
                                if (count($dbs) > 0) {
                                    echo "  Sample databases:\n";
                                    foreach (array_slice($dbs, 0, 5) as $db) {
                                        echo "    - $db\n";
                                    }
                                    if (count($dbs) > 5) {
                                        echo "    ... and " . (count($dbs) - 5) . " more\n";
                                    }
                                }
                            }
                            
                            $mysqli->close();
                            break 3; // Exit all loops
                        }
                    } catch (Exception $e) {
                        // Continue to next attempt
                    }
                }
            }
            
            fclose($connection);
        } else {
            echo "❌ Closed or unreachable\n";
        }
    }
}

echo "\n=== Check Complete ===\n";
