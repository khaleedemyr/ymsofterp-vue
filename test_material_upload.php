<?php
/**
 * Test Script untuk Material File Upload
 * Jalankan script ini untuk test apakah file upload berfungsi
 */

// Include Laravel bootstrap
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Models\LmsCurriculumMaterial;

echo "=== TEST MATERIAL FILE UPLOAD ===\n\n";

try {
    // 1. Test storage directory
    echo "1. Testing storage directory...\n";
    $storagePath = 'lms/materials';
    $fullPath = storage_path("app/public/{$storagePath}");
    
    if (!file_exists($fullPath)) {
        echo "   Creating directory: {$fullPath}\n";
        mkdir($fullPath, 0755, true);
    }
    
    if (is_dir($fullPath) && is_writable($fullPath)) {
        echo "   ✅ Storage directory exists and writable: {$fullPath}\n";
    } else {
        echo "   ❌ Storage directory not writable: {$fullPath}\n";
        exit(1);
    }
    
    // 2. Test file creation
    echo "\n2. Testing file creation...\n";
    $testContent = "This is a test file for material upload\nCreated at: " . date('Y-m-d H:i:s');
    $testFileName = 'test_material_' . time() . '.txt';
    $testFilePath = "{$storagePath}/{$testFileName}";
    
    if (Storage::disk('public')->put($testFilePath, $testContent)) {
        echo "   ✅ Test file created: {$testFilePath}\n";
        
        // Check if file exists
        if (Storage::disk('public')->exists($testFilePath)) {
            echo "   ✅ Test file exists in storage\n";
            
            // Get file URL
            $fileUrl = Storage::disk('public')->url($testFilePath);
            echo "   ✅ File URL: {$fileUrl}\n";
            
            // Clean up test file
            Storage::disk('public')->delete($testFilePath);
            echo "   ✅ Test file cleaned up\n";
        } else {
            echo "   ❌ Test file not found in storage\n";
        }
    } else {
        echo "   ❌ Failed to create test file\n";
    }
    
    // 3. Test database connection
    echo "\n3. Testing database connection...\n";
    try {
        $materialsCount = LmsCurriculumMaterial::count();
        echo "   ✅ Database connected. Materials count: {$materialsCount}\n";
    } catch (Exception $e) {
        echo "   ❌ Database error: " . $e->getMessage() . "\n";
    }
    
    // 4. Test model creation
    echo "\n4. Testing model creation...\n";
    try {
        $testMaterial = LmsCurriculumMaterial::create([
            'title' => 'Test Material ' . time(),
            'description' => 'Test description',
            'file_type' => 'document',
            'estimated_duration_minutes' => 30,
            'status' => 'active',
            'created_by' => 1, // Assuming user ID 1 exists
        ]);
        
        echo "   ✅ Test material created with ID: {$testMaterial->id}\n";
        
        // Clean up test material
        $testMaterial->delete();
        echo "   ✅ Test material cleaned up\n";
        
    } catch (Exception $e) {
        echo "   ❌ Model creation error: " . $e->getMessage() . "\n";
    }
    
    // 5. Test storage link
    echo "\n5. Testing storage link...\n";
    $publicPath = public_path('storage');
    $storageLink = public_path('storage');
    
    if (is_link($storageLink)) {
        echo "   ✅ Storage link exists: {$storageLink}\n";
        $target = readlink($storageLink);
        echo "   ✅ Storage link target: {$target}\n";
    } else {
        echo "   ❌ Storage link not found: {$storageLink}\n";
        echo "   💡 Run: php artisan storage:link\n";
    }
    
    // 6. Test file permissions
    echo "\n6. Testing file permissions...\n";
    $storageAppPath = storage_path('app/public');
    $storageAppPermissions = substr(sprintf('%o', fileperms($storageAppPath)), -4);
    echo "   Storage app permissions: {$storageAppPermissions}\n";
    
    if (is_writable($storageAppPath)) {
        echo "   ✅ Storage app directory is writable\n";
    } else {
        echo "   ❌ Storage app directory is not writable\n";
    }
    
    echo "\n=== TEST COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
