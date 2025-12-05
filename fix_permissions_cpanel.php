<?php
/**
 * Script untuk fix permissions di cPanel server
 * Jalankan via browser atau CLI: php fix_permissions_cpanel.php
 */

echo "=== FIXING FILE PERMISSIONS (cPanel) ===\n\n";

$basePath = __DIR__;

// Directories yang perlu writable
$writableDirs = [
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache'
];

echo "1. Creating directories if they don't exist...\n";
foreach ($writableDirs as $dir) {
    $fullPath = $basePath . '/' . $dir;
    if (!is_dir($fullPath)) {
        if (mkdir($fullPath, 0755, true)) {
            echo "  ✓ Created: $dir\n";
        } else {
            echo "  ✗ Failed to create: $dir\n";
        }
    } else {
        echo "  ✓ Exists: $dir\n";
    }
}

echo "\n2. Setting permissions...\n";
foreach ($writableDirs as $dir) {
    $fullPath = $basePath . '/' . $dir;
    if (is_dir($fullPath)) {
        if (chmod($fullPath, 0755)) {
            echo "  ✓ Set 755: $dir\n";
        } else {
            echo "  ✗ Failed to set permission: $dir\n";
        }
    }
}

echo "\n3. Creating log file if it doesn't exist...\n";
$logFile = $basePath . '/storage/logs/laravel.log';
if (!file_exists($logFile)) {
    if (touch($logFile)) {
        echo "  ✓ Created: storage/logs/laravel.log\n";
    } else {
        echo "  ✗ Failed to create: storage/logs/laravel.log\n";
    }
}

if (file_exists($logFile)) {
    if (chmod($logFile, 0644)) {
        echo "  ✓ Set permission: storage/logs/laravel.log\n";
    } else {
        echo "  ✗ Failed to set permission: storage/logs/laravel.log\n";
    }
}

echo "\n4. Testing write permission...\n";
$testFile = $basePath . '/storage/logs/test_write_' . time() . '.log';
if (file_put_contents($testFile, 'test') !== false) {
    echo "  ✓ Write test PASSED\n";
    unlink($testFile);
} else {
    echo "  ✗ Write test FAILED\n";
    echo "  You may need to set permissions manually via cPanel File Manager:\n";
    echo "  - Right click on 'storage' folder -> Change Permissions -> 755\n";
    echo "  - Right click on 'bootstrap/cache' folder -> Change Permissions -> 755\n";
    echo "  - Or use SSH: chmod -R 755 storage bootstrap/cache\n";
}

echo "\n=== VERIFICATION ===\n";
foreach ($writableDirs as $dir) {
    $fullPath = $basePath . '/' . $dir;
    if (is_dir($fullPath)) {
        $isWritable = is_writable($fullPath);
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        echo sprintf("  %s %s: %s\n", 
            $isWritable ? '✓' : '✗', 
            $dir, 
            $perms . ($isWritable ? ' (writable)' : ' (NOT writable)')
        );
    }
}

echo "\n=== DONE ===\n";
echo "\nIf permissions are still not working:\n";
echo "1. Via cPanel File Manager:\n";
echo "   - Select 'storage' folder -> Change Permissions -> 755\n";
echo "   - Select 'bootstrap/cache' folder -> Change Permissions -> 755\n";
echo "\n2. Via SSH (if available):\n";
echo "   chmod -R 755 storage bootstrap/cache\n";
echo "   chmod -R 777 storage/logs (if 755 doesn't work)\n";
echo "\n";

