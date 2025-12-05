<?php
/**
 * Script untuk menjalankan migration material files structure
 * 
 * Script ini akan:
 * 1. Membuat tabel lms_curriculum_material_files
 * 2. Migrate data dari JSON fields ke tabel baru
 * 3. Verifikasi hasil migration
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== RUNNING MATERIAL FILES MIGRATION ===\n\n";

try {
    // 1. Check if table exists
    echo "1. Checking if lms_curriculum_material_files table exists...\n";
    $tableExists = Schema::hasTable('lms_curriculum_material_files');
    
    if ($tableExists) {
        echo "   ✅ Table already exists\n";
    } else {
        echo "   ❌ Table does not exist, creating...\n";
        
        // Create table
        DB::statement("
            CREATE TABLE IF NOT EXISTS `lms_curriculum_material_files` (
              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `material_id` bigint(20) unsigned NOT NULL COMMENT 'ID material yang terkait',
              `file_path` varchar(500) NOT NULL COMMENT 'Path file di storage',
              `file_name` varchar(255) NOT NULL COMMENT 'Nama file asli',
              `file_size` bigint(20) DEFAULT NULL COMMENT 'Ukuran file dalam bytes',
              `file_mime_type` varchar(100) DEFAULT NULL COMMENT 'MIME type file',
              `file_type` enum('pdf','image','video','document','link') DEFAULT 'document' COMMENT 'Tipe file',
              `order_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Urutan file dalam material',
              `is_primary` tinyint(1) DEFAULT 0 COMMENT 'Apakah file utama (untuk thumbnail/preview)',
              `status` enum('active','inactive') DEFAULT 'active' COMMENT 'Status file',
              `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              `deleted_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_material_files_material_id` (`material_id`),
              KEY `idx_material_files_file_type` (`file_type`),
              KEY `idx_material_files_order_number` (`order_number`),
              KEY `idx_material_files_status` (`status`),
              KEY `idx_material_files_created_by` (`created_by`),
              CONSTRAINT `fk_material_files_material_id` FOREIGN KEY (`material_id`) REFERENCES `lms_curriculum_materials`(`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_material_files_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Multiple files untuk setiap curriculum material'
        ");
        
        // Create indexes
        DB::statement("CREATE INDEX `idx_material_files_material_order` ON `lms_curriculum_material_files`(`material_id`, `order_number`)");
        DB::statement("CREATE INDEX `idx_material_files_type_status` ON `lms_curriculum_material_files`(`file_type`, `status`)");
        
        echo "   ✅ Table created successfully\n";
    }
    
    // 2. Check existing data
    echo "\n2. Checking existing data in lms_curriculum_materials...\n";
    $materialsWithFiles = DB::table('lms_curriculum_materials')
        ->whereNotNull('file_path')
        ->where('file_path', '!=', '')
        ->where('file_path', '!=', 'null')
        ->get();
    
    echo "   📊 Found {$materialsWithFiles->count()} materials with file_path data\n";
    
    if ($materialsWithFiles->count() > 0) {
        echo "   📋 Sample data:\n";
        foreach ($materialsWithFiles->take(3) as $material) {
            echo "      - ID: {$material->id}, Title: {$material->title}\n";
            echo "        File path: {$material->file_path}\n";
            echo "        File type: {$material->file_type}\n";
        }
    }
    
    // 3. Migrate data if exists
    if ($materialsWithFiles->count() > 0) {
        echo "\n3. Migrating data from JSON fields to new table...\n";
        
        $migratedCount = 0;
        $errors = [];
        
        foreach ($materialsWithFiles as $material) {
            try {
                // Check if JSON is valid
                if (!empty($material->file_path) && $material->file_path !== 'null') {
                    $filePaths = json_decode($material->file_path, true);
                    $fileTypes = json_decode($material->file_type ?? '[]', true);
                    
                    if (is_array($filePaths) && !empty($filePaths)) {
                        foreach ($filePaths as $index => $filePath) {
                            if (!empty($filePath) && $filePath !== 'null') {
                                $fileType = $fileTypes[$index] ?? 'document';
                                $fileName = basename($filePath);
                                
                                // Insert into new table
                                DB::table('lms_curriculum_material_files')->insert([
                                    'material_id' => $material->id,
                                    'file_path' => $filePath,
                                    'file_name' => $fileName,
                                    'file_size' => null, // Will be updated later if file exists
                                    'file_mime_type' => null, // Will be updated later if file exists
                                    'file_type' => $fileType,
                                    'order_number' => $index + 1,
                                    'is_primary' => $index === 0, // First file is primary
                                    'status' => 'active',
                                    'created_by' => $material->created_by ?? 1,
                                    'created_at' => $material->created_at ?? now(),
                                    'updated_at' => now(),
                                ]);
                                
                                $migratedCount++;
                            }
                        }
                        
                        echo "   ✅ Migrated material ID {$material->id} with " . count($filePaths) . " files\n";
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Error migrating material ID {$material->id}: " . $e->getMessage();
                echo "   ❌ Error migrating material ID {$material->id}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "   📊 Total files migrated: {$migratedCount}\n";
        
        if (!empty($errors)) {
            echo "   ⚠️  Errors encountered: " . count($errors) . "\n";
        }
    } else {
        echo "\n3. No existing data to migrate\n";
    }
    
    // 4. Verify migration
    echo "\n4. Verifying migration results...\n";
    
    $totalMaterials = DB::table('lms_curriculum_materials')->count();
    $totalFiles = DB::table('lms_curriculum_material_files')->count();
    $materialsWithFilesCount = DB::table('lms_curriculum_materials')
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                  ->from('lms_curriculum_material_files')
                  ->whereRaw('lms_curriculum_material_files.material_id = lms_curriculum_materials.id');
        })
        ->count();
    
    echo "   📊 Total materials: {$totalMaterials}\n";
    echo "   📁 Total files: {$totalFiles}\n";
    echo "   ✅ Materials with files: {$materialsWithFilesCount}\n";
    echo "   ❌ Materials without files: " . ($totalMaterials - $materialsWithFilesCount) . "\n";
    
    // 5. Show sample migrated data
    if ($totalFiles > 0) {
        echo "\n5. Sample migrated data:\n";
        $sampleFiles = DB::table('lms_curriculum_material_files')
            ->join('lms_curriculum_materials', 'lms_curriculum_material_files.material_id', '=', 'lms_curriculum_materials.id')
            ->select('lms_curriculum_material_files.*', 'lms_curriculum_materials.title as material_title')
            ->orderBy('lms_curriculum_material_files.material_id')
            ->orderBy('lms_curriculum_material_files.order_number')
            ->limit(10)
            ->get();
        
        foreach ($sampleFiles as $file) {
            echo "   📄 {$file->material_title} -> {$file->file_name} ({$file->file_type}) - " . 
                 ($file->is_primary ? 'PRIMARY' : 'Secondary') . "\n";
        }
    }
    
    echo "\n=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
    
} catch (Exception $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
