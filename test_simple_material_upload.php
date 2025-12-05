<?php
/**
 * Simple Test Script untuk Material File Upload
 * Mengikuti pola MaintenanceTaskController
 */

// Include Laravel bootstrap
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\UploadedFile;
use App\Models\LmsCurriculumMaterial;

echo "=== SIMPLE MATERIAL FILE UPLOAD TEST ===\n\n";

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
    
    // 2. Test file creation (simulating UploadedFile)
    echo "\n2. Testing file creation...\n";
    $testContent = "This is a test PDF content for material upload\nCreated at: " . date('Y-m-d H:i:s');
    $testFileName = 'test_material_' . time() . '.pdf';
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
    
    // 4. Test model creation with file path
    echo "\n4. Testing model creation with file path...\n";
    try {
        $testMaterial = LmsCurriculumMaterial::create([
            'title' => 'Test Material with File ' . time(),
            'description' => 'Test description with file path',
            'file_path' => 'lms/materials/test_file.pdf', // Set file_path directly
            'file_type' => 'pdf',
            'estimated_duration_minutes' => 30,
            'status' => 'active',
            'created_by' => 1, // Assuming user ID 1 exists
        ]);
        
        echo "   ✅ Test material created with ID: {$testMaterial->id}\n";
        echo "   ✅ File path: {$testMaterial->file_path}\n";
        echo "   ✅ File type: {$testMaterial->file_type}\n";
        
        // Clean up test material
        $testMaterial->delete();
        echo "   ✅ Test material cleaned up\n";
        
    } catch (Exception $e) {
        echo "   ❌ Model creation error: " . $e->getMessage() . "\n";
    }
    
    // 5. Test file upload simulation
    echo "\n5. Testing file upload simulation...\n";
    try {
        // Simulate file upload like MaintenanceTaskController
        $simulatedFilePath = 'lms/materials/simulated_upload.pdf';
        
        // Create a simulated file
        Storage::disk('public')->put($simulatedFilePath, 'Simulated PDF content');
        
        if (Storage::disk('public')->exists($simulatedFilePath)) {
            echo "   ✅ Simulated file uploaded: {$simulatedFilePath}\n";
            
            // Create material record with file path (like MaintenanceTaskController)
            $materialWithFile = LmsCurriculumMaterial::create([
                'title' => 'Simulated Upload Material',
                'description' => 'This material was created with file path',
                'file_path' => $simulatedFilePath,
                'file_type' => 'pdf',
                'estimated_duration_minutes' => 45,
                'status' => 'active',
                'created_by' => 1,
            ]);
            
            echo "   ✅ Material record created with file: ID {$materialWithFile->id}\n";
            echo "   ✅ File path saved: {$materialWithFile->file_path}\n";
            
            // Clean up
            $materialWithFile->delete();
            Storage::disk('public')->delete($simulatedFilePath);
            echo "   ✅ Simulated material and file cleaned up\n";
            
        } else {
            echo "   ❌ Failed to create simulated file\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ File upload simulation error: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== TEST COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
