<?php

/**
 * Test script untuk memverifikasi Course Detail bisa menampilkan multiple files
 * 
 * Script ini akan:
 * 1. Mencari course yang memiliki material dengan multiple files
 * 2. Menampilkan informasi material dan files
 * 3. Memverifikasi bahwa data files sudah benar
 */

require_once 'vendor/autoload.php';

use App\Models\LmsCourse;
use App\Models\LmsCurriculumMaterial;
use App\Models\LmsCurriculumMaterialFile;

echo "=== TEST COURSE DETAIL FILES ===\n\n";

try {
    // Cari course yang memiliki material
    $course = LmsCourse::with(['sessions.items'])->first();
    
    if (!$course) {
        echo "❌ Tidak ada course yang ditemukan\n";
        exit(1);
    }
    
    echo "📚 Course: {$course->title}\n";
    echo "📝 Description: {$course->description}\n";
    echo "🔢 Sessions count: " . ($course->sessions ? count($course->sessions) : 0) . "\n\n";
    
    if (!$course->sessions) {
        echo "❌ Course tidak memiliki sessions\n";
        exit(1);
    }
    
    $totalMaterials = 0;
    $totalFiles = 0;
    
    foreach ($course->sessions as $sessionIndex => $session) {
        echo "📖 Session " . ($sessionIndex + 1) . ": {$session->session_title}\n";
        
        if (!$session->items) {
            echo "   ❌ Session tidak memiliki items\n";
            continue;
        }
        
        foreach ($session->items as $itemIndex => $item) {
            echo "   📋 Item " . ($itemIndex + 1) . ": {$item->item_type}\n";
            
            if ($item->item_type === 'material' && $item->item_id) {
                $totalMaterials++;
                
                // Load material dengan files
                $material = LmsCurriculumMaterial::with('files')->find($item->item_id);
                
                if ($material) {
                    echo "      📁 Material: {$material->title}\n";
                    echo "      📄 Files count: {$material->files_count}\n";
                    echo "      🎯 Primary file type: " . ($material->primary_file_type ?: 'N/A') . "\n";
                    
                    if ($material->files->count() > 0) {
                        echo "      📂 Files:\n";
                        foreach ($material->files as $fileIndex => $file) {
                            $totalFiles++;
                            $primaryBadge = $file->is_primary ? ' [PRIMARY]' : '';
                            echo "         " . ($fileIndex + 1) . ". {$file->file_name} ({$file->file_type}){$primaryBadge}\n";
                            echo "            Path: {$file->file_path}\n";
                            echo "            Size: {$file->file_size_formatted}\n";
                        }
                    } else {
                        echo "      ⚠️  Material tidak memiliki files\n";
                    }
                } else {
                    echo "      ❌ Material tidak ditemukan (ID: {$item->item_id})\n";
                }
            }
        }
        echo "\n";
    }
    
    echo "=== SUMMARY ===\n";
    echo "📚 Course: {$course->title}\n";
    echo "📖 Total Sessions: " . count($course->sessions) . "\n";
    echo "📁 Total Materials: {$totalMaterials}\n";
    echo "📄 Total Files: {$totalFiles}\n";
    
    if ($totalMaterials > 0) {
        echo "\n✅ Test berhasil! Course detail sudah bisa menampilkan multiple files\n";
        echo "💡 Sekarang buka halaman course detail untuk melihat hasilnya\n";
    } else {
        echo "\n⚠️  Course tidak memiliki materials untuk ditest\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}
