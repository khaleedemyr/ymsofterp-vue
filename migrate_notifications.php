<?php

/**
 * Script untuk migrate semua DB::table('notifications')->insert() 
 * menjadi NotificationService::create()
 * 
 * Usage: php migrate_notifications.php
 */

$basePath = __DIR__ . '/app/Http/Controllers';
$backupDir = __DIR__ . '/backup_notifications_migration_' . date('Ymd_His');

// Create backup directory
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    echo "Created backup directory: $backupDir\n";
}

// Find all PHP files in Controllers directory
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath)
);

$files = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}

echo "Found " . count($files) . " PHP files to check\n\n";

$updatedFiles = [];
$skippedFiles = [];

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $modified = false;
    
    // Check if file uses DB::table('notifications')->insert
    if (preg_match('/DB::table\([\'"]notifications[\'"]\)->insert/i', $content)) {
        echo "Processing: " . basename($filePath) . "\n";
        
        // 1. Add use statement if not exists
        if (!preg_match('/use\s+App\\\Services\\\NotificationService;/', $content)) {
            // Find the last use statement
            if (preg_match_all('/^use\s+[^;]+;/m', $content, $matches)) {
                $lastUse = end($matches[0]);
                $lastUsePos = strrpos($content, $lastUse);
                $insertPos = $lastUsePos + strlen($lastUse);
                $content = substr_replace($content, "\nuse App\Services\NotificationService;", $insertPos, 0);
                $modified = true;
                echo "  ✓ Added use statement\n";
            }
        }
        
        // 2. Replace DB::table('notifications')->insert with NotificationService::create
        // Pattern 1: Simple insert
        $content = preg_replace(
            '/DB::table\([\'"]notifications[\'"]\)->insert\s*\(/i',
            'NotificationService::create(',
            $content
        );
        
        // Pattern 2: insertGetId
        $content = preg_replace(
            '/\$(\w+)\s*=\s*DB::table\([\'"]notifications[\'"]\)->insertGetId\s*\(/i',
            '$notification = NotificationService::create(',
            $content
        );
        
        // 3. Remove created_at and updated_at from arrays
        // Remove 'created_at' => now(), or 'created_at' => \Carbon\Carbon::now(), etc
        $content = preg_replace(
            '/[\'"]created_at[\'"]\s*=>\s*[^,)]+,?\s*/i',
            '',
            $content
        );
        $content = preg_replace(
            '/[\'"]updated_at[\'"]\s*=>\s*[^,)]+,?\s*/i',
            '',
            $content
        );
        
        // 4. Handle insertGetId - replace with NotificationService::create and get id
        // Pattern: $var = DB::table('notifications')->insertGetId([...]);
        // Becomes: $notification = NotificationService::create([...]); $var = $notification->id;
        $content = preg_replace_callback(
            '/\$(\w+)\s*=\s*NotificationService::create\s*\(([^)]+)\);/s',
            function($matches) {
                $varName = $matches[1];
                $params = $matches[2];
                // Check if variable name suggests it's an ID (contains 'id', 'Id', or 'ID')
                if (preg_match('/id|Id|ID/', $varName) && $varName !== 'notification') {
                    return "\$notification = NotificationService::create($params);\n            \$$varName = \$notification ? \$notification->id : null;";
                }
                return $matches[0];
            },
            $content
        );
        
        // 5. Clean up trailing commas in arrays (might be left after removing created_at/updated_at)
        $content = preg_replace('/,\s*,/', ',', $content); // Remove double commas
        $content = preg_replace('/,\s*\)/', ')', $content); // Remove trailing comma before closing paren
        $content = preg_replace('/,\s*\]/', ']', $content); // Remove trailing comma before closing bracket
        
        if ($content !== $originalContent) {
            // Create backup
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $filePath);
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . str_replace(DIRECTORY_SEPARATOR, '_', $relativePath);
            $backupDirPath = dirname($backupPath);
            if (!is_dir($backupDirPath)) {
                mkdir($backupDirPath, 0755, true);
            }
            file_put_contents($backupPath, $originalContent);
            
            // Write modified content
            file_put_contents($filePath, $content);
            $updatedFiles[] = $filePath;
            echo "  ✓ Updated and backed up\n\n";
        } else {
            echo "  ⚠ No changes made (might need manual review)\n\n";
            $skippedFiles[] = $filePath;
        }
    }
}

echo "\n========================================\n";
echo "Migration Summary:\n";
echo "========================================\n";
echo "Updated files: " . count($updatedFiles) . "\n";
echo "Skipped files: " . count($skippedFiles) . "\n";
echo "Backup location: $backupDir\n";
echo "\n";

if (count($updatedFiles) > 0) {
    echo "Updated files:\n";
    foreach ($updatedFiles as $file) {
        echo "  - " . basename($file) . "\n";
    }
    echo "\n";
}

if (count($skippedFiles) > 0) {
    echo "Files that might need manual review:\n";
    foreach ($skippedFiles as $file) {
        echo "  - " . basename($file) . "\n";
    }
    echo "\n";
}

echo "Migration completed!\n";
echo "Please test your application and check the logs for any errors.\n";

