<?php
/**
 * Debug Stuck Request
 * This script helps identify what might be causing the request to get stuck
 */

echo "=== DEBUG STUCK REQUEST ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Check if we can connect to database
echo "=== DATABASE CONNECTION TEST ===\n";
try {
    // Try to connect to database using Laravel's config
    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $database = env('DB_DATABASE', 'forge');
    $username = env('DB_USERNAME', 'forge');
    $password = env('DB_PASSWORD', '');
    
    echo "Database config:\n";
    echo "  Host: $host\n";
    echo "  Port: $port\n";
    echo "  Database: $database\n";
    echo "  Username: $username\n";
    echo "  Password: " . (empty($password) ? 'empty' : 'set') . "\n\n";
    
    // Try to connect
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Database connection successful\n\n";
    
    // Check for long-running queries
    echo "=== CHECKING FOR LONG-RUNNING QUERIES ===\n";
    $stmt = $pdo->query("SHOW PROCESSLIST");
    $processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $longRunning = 0;
    foreach ($processes as $process) {
        if ($process['Time'] > 10) { // More than 10 seconds
            $longRunning++;
            echo "Long-running query found:\n";
            echo "  ID: " . $process['Id'] . "\n";
            echo "  User: " . $process['User'] . "\n";
            echo "  Host: " . $process['Host'] . "\n";
            echo "  Database: " . $process['db'] . "\n";
            echo "  Command: " . $process['Command'] . "\n";
            echo "  Time: " . $process['Time'] . " seconds\n";
            echo "  State: " . $process['State'] . "\n";
            echo "  Info: " . substr($process['Info'], 0, 100) . "...\n\n";
        }
    }
    
    if ($longRunning == 0) {
        echo "✓ No long-running queries found\n\n";
    }
    
    // Check table locks
    echo "=== CHECKING FOR TABLE LOCKS ===\n";
    $stmt = $pdo->query("SHOW ENGINE INNODB STATUS");
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (strpos($status['Status'], 'TRANSACTIONS') !== false) {
        echo "InnoDB transactions found in status\n";
        // Extract transaction info
        $transactions = explode('TRANSACTIONS', $status['Status'])[1];
        $transactions = explode('---TRANSACTION', $transactions)[0];
        echo "Transaction info: " . substr($transactions, 0, 200) . "...\n\n";
    } else {
        echo "✓ No transaction information found\n\n";
    }
    
    // Check for deadlocks
    if (strpos($status['Status'], 'LATEST DETECTED DEADLOCK') !== false) {
        echo "⚠ DEADLOCK DETECTED!\n";
        $deadlock = explode('LATEST DETECTED DEADLOCK', $status['Status'])[1];
        $deadlock = explode('TRANSACTIONS', $deadlock)[0];
        echo "Deadlock info: " . substr($deadlock, 0, 300) . "...\n\n";
    } else {
        echo "✓ No deadlocks detected\n\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n\n";
}

// Check file system
echo "=== FILE SYSTEM CHECK ===\n";
$storagePath = 'storage';
$logsPath = 'storage/logs';
$cachePath = 'storage/framework/cache';

echo "Storage directory exists: " . (is_dir($storagePath) ? 'Yes' : 'No') . "\n";
echo "Logs directory exists: " . (is_dir($logsPath) ? 'Yes' : 'No') . "\n";
echo "Cache directory exists: " . (is_dir($cachePath) ? 'Yes' : 'No') . "\n";

if (is_dir($storagePath)) {
    echo "Storage directory writable: " . (is_writable($storagePath) ? 'Yes' : 'No') . "\n";
    echo "Storage directory size: " . number_format(dirSize($storagePath)) . " bytes\n";
}

if (is_dir($cachePath)) {
    echo "Cache directory writable: " . (is_writable($cachePath) ? 'Yes' : 'No') . "\n";
    echo "Cache directory size: " . number_format(dirSize($cachePath)) . " bytes\n";
}

echo "\n";

// Check PHP configuration
echo "=== PHP CONFIGURATION CHECK ===\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . " seconds\n";
echo "Max input time: " . ini_get('max_input_time') . " seconds\n";
echo "Post max size: " . ini_get('post_max_size') . "\n";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Max file uploads: " . ini_get('max_file_uploads') . "\n";

// Check if there are any large files in uploads
echo "\n=== UPLOAD FILES CHECK ===\n";
$uploadPath = 'storage/app/public';
if (is_dir($uploadPath)) {
    $largeFiles = findLargeFiles($uploadPath, 10 * 1024 * 1024); // 10MB
    if (empty($largeFiles)) {
        echo "✓ No unusually large files found in uploads\n";
    } else {
        echo "⚠ Large files found in uploads:\n";
        foreach ($largeFiles as $file) {
            echo "  " . $file['path'] . " (" . number_format($file['size']) . " bytes)\n";
        }
    }
}

echo "\n=== RECOMMENDATIONS ===\n";
echo "1. Check if there are any infinite loops in the course creation logic\n";
echo "2. Check if there are any database transactions that are not being committed\n";
echo "3. Check if there are any file upload operations that are hanging\n";
echo "4. Check server resources (CPU, memory, disk space)\n";
echo "5. Check if there are any external API calls that are timing out\n";
echo "6. Check if there are any validation rules that are causing infinite loops\n";

// Helper functions
function dirSize($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : dirSize($each);
    }
    return $size;
}

function findLargeFiles($dir, $minSize) {
    $largeFiles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getSize() > $minSize) {
            $largeFiles[] = [
                'path' => $file->getPathname(),
                'size' => $file->getSize()
            ];
        }
    }
    
    return $largeFiles;
}
?>
