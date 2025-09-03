<?php
/**
 * Test Script untuk Material Files Structure Baru
 * 
 * Script ini untuk testing struktur baru yang tidak menggunakan JSON
 * untuk menyimpan multiple files per material
 */

require_once 'vendor/autoload.php';

use App\Models\LmsCurriculumMaterial;
use App\Models\LmsCurriculumMaterialFile;
use Illuminate\Support\Facades\Storage;

echo "=== TESTING MATERIAL FILES STRUCTURE ===\n\n";

try {
    // 1. Test create material tanpa file
    echo "1. Testing create material tanpa file...\n";
    $material = LmsCurriculumMaterial::create([
        'title' => 'Test Material Tanpa File',
        'description' => 'Material untuk testing tanpa file',
        'estimated_duration_minutes' => 15,
        'status' => 'active',
        'created_by' => 1, // Assuming user ID 1 exists
    ]);
    
    echo "   ✅ Material created: ID {$material->id}\n";
    echo "   📁 Files count: {$material->files_count}\n";
    echo "   🔗 Primary file URL: " . ($material->primary_file_url ?: 'None') . "\n\n";
    
    // 2. Test create material dengan single file
    echo "2. Testing create material dengan single file...\n";
    $materialWithFile = LmsCurriculumMaterial::create([
        'title' => 'Test Material dengan Single File',
        'description' => 'Material untuk testing dengan single file',
        'estimated_duration_minutes' => 30,
        'status' => 'active',
        'created_by' => 1,
    ]);
    
    // Simulate file upload (create a dummy file record)
    $fileRecord = LmsCurriculumMaterialFile::create([
        'material_id' => $materialWithFile->id,
        'file_path' => 'lms/materials/test_file.pdf',
        'file_name' => 'test_file.pdf',
        'file_size' => 1024000, // 1MB
        'file_mime_type' => 'application/pdf',
        'file_type' => 'pdf',
        'order_number' => 1,
        'is_primary' => true,
        'status' => 'active',
        'created_by' => 1,
    ]);
    
    echo "   ✅ Material created: ID {$materialWithFile->id}\n";
    echo "   📁 Files count: {$materialWithFile->files_count}\n";
    echo "   🔗 Primary file URL: " . ($materialWithFile->primary_file_url ?: 'None') . "\n";
    echo "   📄 Primary file type: " . ($materialWithFile->primary_file_type ?: 'None') . "\n\n";
    
    // 3. Test create material dengan multiple files
    echo "3. Testing create material dengan multiple files...\n";
    $materialWithMultipleFiles = LmsCurriculumMaterial::create([
        'title' => 'Test Material dengan Multiple Files',
        'description' => 'Material untuk testing dengan multiple files',
        'estimated_duration_minutes' => 45,
        'status' => 'active',
        'created_by' => 1,
    ]);
    
    // Add multiple files
    $files = [
        ['path' => 'lms/materials/doc1.pdf', 'name' => 'document1.pdf', 'type' => 'pdf', 'size' => 2048000],
        ['path' => 'lms/materials/img1.jpg', 'name' => 'image1.jpg', 'type' => 'image', 'size' => 512000],
        ['path' => 'lms/materials/vid1.mp4', 'name' => 'video1.mp4', 'type' => 'video', 'size' => 10485760],
    ];
    
    foreach ($files as $index => $fileData) {
        LmsCurriculumMaterialFile::create([
            'material_id' => $materialWithMultipleFiles->id,
            'file_path' => $fileData['path'],
            'file_name' => $fileData['name'],
            'file_size' => $fileData['size'],
            'file_mime_type' => getMimeType($fileData['type']),
            'file_type' => $fileData['type'],
            'order_number' => $index + 1,
            'is_primary' => $index === 0, // First file is primary
            'status' => 'active',
            'created_by' => 1,
        ]);
    }
    
    echo "   ✅ Material created: ID {$materialWithMultipleFiles->id}\n";
    echo "   📁 Files count: {$materialWithMultipleFiles->files_count}\n";
    echo "   🔗 Primary file URL: " . ($materialWithMultipleFiles->primary_file_url ?: 'None') . "\n";
    echo "   📄 Primary file type: " . ($materialWithMultipleFiles->primary_file_type ?: 'None') . "\n";
    
    // Show all files
    echo "   📋 All files:\n";
    foreach ($materialWithMultipleFiles->files as $file) {
        echo "      - {$file->file_name} ({$file->file_type_text}) - " . 
             ($file->is_primary ? 'PRIMARY' : 'Secondary') . "\n";
    }
    echo "\n";
    
    // 4. Test querying materials by file type
    echo "4. Testing query materials by file type...\n";
    $pdfMaterials = LmsCurriculumMaterial::byFileType('pdf')->get();
    $imageMaterials = LmsCurriculumMaterial::byFileType('image')->get();
    $videoMaterials = LmsCurriculumMaterial::byFileType('video')->get();
    
    echo "   📄 PDF materials: {$pdfMaterials->count()}\n";
    echo "   🖼️  Image materials: {$imageMaterials->count()}\n";
    echo "   🎥 Video materials: {$videoMaterials->count()}\n\n";
    
    // 5. Test querying materials with files
    echo "5. Testing query materials with files...\n";
    $materialsWithFiles = LmsCurriculumMaterial::hasFiles()->get();
    $materialsWithoutFiles = LmsCurriculumMaterial::whereDoesntHave('files')->get();
    
    echo "   📁 Materials with files: {$materialsWithFiles->count()}\n";
    echo "   ❌ Materials without files: {$materialsWithoutFiles->count()}\n\n";
    
    // 6. Test file management methods
    echo "6. Testing file management methods...\n";
    
    // Test reordering files
    $material = $materialWithMultipleFiles;
    $fileIds = $material->files->pluck('id')->toArray();
    $reversedOrder = array_reverse($fileIds);
    
    echo "   🔄 Reordering files...\n";
    foreach ($reversedOrder as $index => $fileId) {
        LmsCurriculumMaterialFile::where('id', $fileId)->update(['order_number' => $index + 1]);
    }
    
    // Refresh and show new order
    $material->refresh();
    echo "   📋 Files after reordering:\n";
    foreach ($material->files as $file) {
        echo "      {$file->order_number}. {$file->file_name}\n";
    }
    echo "\n";
    
    // 7. Test summary
    echo "7. Testing summary...\n";
    $totalMaterials = LmsCurriculumMaterial::count();
    $totalFiles = LmsCurriculumMaterialFile::count();
    $materialsWithFilesCount = LmsCurriculumMaterial::hasFiles()->count();
    
    echo "   📊 Total materials: {$totalMaterials}\n";
    echo "   📁 Total files: {$totalFiles}\n";
    echo "   ✅ Materials with files: {$materialsWithFilesCount}\n";
    echo "   ❌ Materials without files: " . ($totalMaterials - $materialsWithFilesCount) . "\n\n";
    
    echo "=== TESTING COMPLETED SUCCESSFULLY ===\n";
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

/**
 * Helper function to get MIME type
 */
function getMimeType($fileType) {
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'image' => 'image/jpeg',
        'video' => 'video/mp4',
        'document' => 'application/msword',
        'link' => 'text/plain'
    ];
    
    return $mimeTypes[$fileType] ?? 'application/octet-stream';
}
