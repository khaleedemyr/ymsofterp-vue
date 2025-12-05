<?php
/**
 * Check Recent Laravel Logs
 * This script checks the most recent entries in the Laravel log file
 */

$logFile = 'storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "Log file not found: $logFile\n";
    exit(1);
}

echo "=== CHECKING RECENT LARAVEL LOGS ===\n";
echo "Log file: $logFile\n";
echo "File size: " . number_format(filesize($logFile)) . " bytes\n\n";

// Read last 50 lines of the log file
$lines = file($logFile);
$recentLines = array_slice($lines, -50);

echo "=== LAST 50 LOG ENTRIES ===\n";
foreach ($recentLines as $line) {
    echo trim($line) . "\n";
}

echo "\n=== SEARCHING FOR SPECIFIC PATTERNS ===\n";

// Search for specific patterns
$patterns = [
    'STORE COURSE' => 'Course creation',
    'ERROR' => 'Error messages',
    'WARNING' => 'Warning messages',
    'Exception' => 'Exceptions',
    'SQLSTATE' => 'Database errors',
    'Timeout' => 'Timeout issues',
    'Loading' => 'Loading state issues'
];

foreach ($patterns as $pattern => $description) {
    $count = 0;
    foreach ($lines as $line) {
        if (stripos($line, $pattern) !== false) {
            $count++;
        }
    }
    echo "$description ($pattern): $count occurrences\n";
}

echo "\n=== CHECKING FOR STUCK REQUESTS ===\n";

// Look for requests that might be stuck
$stuckPatterns = [
    'REQUEST STARTED',
    'REQUEST SUCCESS',
    'REQUEST ERROR',
    'Loading state',
    'FINALLY BLOCK'
];

foreach ($stuckPatterns as $pattern) {
    $found = false;
    foreach (array_reverse($lines) as $line) {
        if (stripos($line, $pattern) !== false) {
            echo "Found '$pattern': " . trim($line) . "\n";
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "Pattern '$pattern' not found in recent logs\n";
    }
}

echo "\n=== CHECKING LOG FILE PERMISSIONS ===\n";
echo "File readable: " . (is_readable($logFile) ? 'Yes' : 'No') . "\n";
echo "File writable: " . (is_writable($logFile) ? 'Yes' : 'No') . "\n";
echo "Last modified: " . date('Y-m-d H:i:s', filemtime($logFile)) . "\n";

echo "\n=== RECOMMENDATIONS ===\n";
echo "1. Check if the Laravel application is running\n";
echo "2. Check database connection\n";
echo "3. Check if there are any long-running database queries\n";
echo "4. Check server resources (CPU, memory, disk space)\n";
echo "5. Check if there are any infinite loops in the code\n";
echo "6. Check if the request is hitting a timeout\n";
?>
