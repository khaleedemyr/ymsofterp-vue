<?php

/**
 * Storage Test Script
 * 
 * This script tests the storage configuration and file access.
 * Run this to diagnose storage issues.
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== STORAGE TEST SCRIPT ===\n";
echo "Testing storage configuration...\n\n";

// Test 1: Storage paths
echo "1. Testing Storage Paths:\n";
$storagePath = storage_path('app/public');
$publicStoragePath = public_path('storage');

echo "   Storage path: {$storagePath}\n";
echo "   Public storage path: {$publicStoragePath}\n";
echo "   Storage exists: " . (file_exists($storagePath) ? 'YES' : 'NO') . "\n";
echo "   Public storage exists: " . (file_exists($publicStoragePath) ? 'YES' : 'NO') . "\n";
echo "   Storage readable: " . (is_readable($storagePath) ? 'YES' : 'NO') . "\n";
echo "   Public storage readable: " . (is_readable($publicStoragePath) ? 'YES' : 'NO') . "\n\n";

// Test 2: Storage link
echo "2. Testing Storage Link:\n";
if (is_link($publicStoragePath)) {
    $linkTarget = readlink($publicStoragePath);
    echo "   Storage link exists: YES\n";
    echo "   Link target: {$linkTarget}\n";
    echo "   Target exists: " . (file_exists($linkTarget) ? 'YES' : 'NO') . "\n";
} else {
    echo "   Storage link exists: NO\n";
    echo "   Creating storage link...\n";
    
    try {
        // Try to create storage link
        $result = shell_exec('php artisan storage:link 2>&1');
        echo "   Result: " . trim($result) . "\n";
        
        if (is_link($publicStoragePath)) {
            echo "   Storage link created successfully!\n";
        } else {
            echo "   Failed to create storage link\n";
        }
    } catch (Exception $e) {
        echo "   Error creating storage link: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test 3: LMS materials folder
echo "3. Testing LMS Materials Folder:\n";
$lmsMaterialsPath = $storagePath . '/lms/materials';
echo "   LMS materials path: {$lmsMaterialsPath}\n";
echo "   Folder exists: " . (file_exists($lmsMaterialsPath) ? 'YES' : 'NO') . "\n";

if (file_exists($lmsMaterialsPath)) {
    echo "   Folder readable: " . (is_readable($lmsMaterialsPath) ? 'YES' : 'NO') . "\n";
    echo "   Folder writable: " . (is_writable($lmsMaterialsPath) ? 'YES' : 'NO') . "\n";
    
    // List files
    $files = scandir($lmsMaterialsPath);
    $fileCount = count(array_filter($files, function($file) {
        return $file !== '.' && $file !== '..';
    }));
    echo "   Files count: {$fileCount}\n";
    
    if ($fileCount > 0) {
        echo "   Sample files:\n";
        $sampleFiles = array_slice(array_filter($files, function($file) {
            return $file !== '.' && $file !== '..';
        }), 0, 5);
        
        foreach ($sampleFiles as $file) {
            $filePath = $lmsMaterialsPath . '/' . $file;
            $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
            $fileSizeFormatted = $fileSize > 0 ? formatBytes($fileSize) : 'N/A';
            echo "     - {$file} ({$fileSizeFormatted})\n";
        }
    }
} else {
    echo "   Creating LMS materials folder...\n";
    try {
        if (!file_exists($storagePath . '/lms')) {
            mkdir($storagePath . '/lms', 0755, true);
        }
        if (!file_exists($lmsMaterialsPath)) {
            mkdir($lmsMaterialsPath, 0755, true);
        }
        echo "   LMS materials folder created successfully!\n";
    } catch (Exception $e) {
        echo "   Error creating folder: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test 4: Database check
echo "4. Testing Database File References:\n";
try {
    $materials = \App\Models\LmsCurriculumMaterial::whereNotNull('file_path')->get();
    echo "   Materials with files: " . $materials->count() . "\n";
    
    if ($materials->count() > 0) {
        echo "   Sample material files:\n";
        foreach ($materials->take(3) as $material) {
            echo "     Material ID: {$material->id}\n";
            echo "     Title: {$material->title}\n";
            echo "     File path: {$material->file_path}\n";
            
            // Test file existence
            if (!empty($material->file_path)) {
                $filePaths = json_decode($material->file_path, true);
                if (is_array($filePaths)) {
                    foreach ($filePaths as $filePath) {
                        $fullPath = $storagePath . '/' . $filePath;
                        $exists = file_exists($fullPath);
                        $readable = is_readable($fullPath);
                        echo "       - {$filePath}: " . ($exists ? 'EXISTS' : 'MISSING') . 
                             " (" . ($readable ? 'READABLE' : 'NOT READABLE') . ")\n";
                    }
                }
            }
            echo "\n";
        }
    }
} catch (Exception $e) {
    echo "   Error checking database: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: File permissions
echo "5. Testing File Permissions:\n";
echo "   Current user: " . get_current_user() . "\n";
echo "   Current user ID: " . getmyuid() . "\n";
echo "   Current group ID: " . getmygid() . "\n";

// Test creating a test file
$testFile = $lmsMaterialsPath . '/test_file.txt';
echo "   Testing file creation...\n";
try {
    file_put_contents($testFile, 'Test content');
    echo "   Test file created: YES\n";
    echo "   Test file readable: " . (is_readable($testFile) ? 'YES' : 'NO') . "\n";
    
    // Clean up
    unlink($testFile);
    echo "   Test file cleaned up\n";
} catch (Exception $e) {
    echo "   Error creating test file: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== STORAGE TEST COMPLETED ===\n";

// Helper function
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
