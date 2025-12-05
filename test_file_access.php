<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SharedDocument;

echo "=== TESTING FILE ACCESS ===\n";

// Get first document
$doc = SharedDocument::first();

if ($doc) {
    echo "Document found:\n";
    echo "- ID: " . $doc->id . "\n";
    echo "- Title: " . $doc->title . "\n";
    echo "- Filename: " . $doc->filename . "\n";
    echo "- File path: " . $doc->file_path . "\n";
    echo "- File type: " . $doc->file_type . "\n";
    echo "- File size: " . $doc->file_size . "\n";
    
    // Test file path
    $fullPath = storage_path('app/public/' . $doc->file_path);
    echo "- Full path: " . $fullPath . "\n";
    echo "- File exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
    
    if (file_exists($fullPath)) {
        echo "- File size on disk: " . filesize($fullPath) . " bytes\n";
        echo "- File readable: " . (is_readable($fullPath) ? 'YES' : 'NO') . "\n";
    }
    
    // Test URLs
    echo "\n=== URL TESTING ===\n";
    echo "- Download URL: http://localhost:8000/shared-documents/" . $doc->id . "/download\n";
    echo "- Callback URL: http://localhost:8000/shared-documents/" . $doc->id . "/callback\n";
    
    // Test OnlyOffice config
    echo "\n=== ONLYOFFICE CONFIG ===\n";
    $config = [
        'document' => [
            'fileType' => $doc->file_type,
            'key' => $doc->id,
            'title' => $doc->title,
            'url' => 'http://localhost:8000/shared-documents/' . $doc->id . '/download'
        ],
        'documentType' => in_array($doc->file_type, ['xlsx', 'xls']) ? 'spreadsheet' : 'text',
        'editorConfig' => [
            'mode' => 'edit',
            'callbackUrl' => 'http://localhost:8000/shared-documents/' . $doc->id . '/callback'
        ]
    ];
    
    echo json_encode($config, JSON_PRETTY_PRINT) . "\n";
    
} else {
    echo "No documents found in database!\n";
}

echo "\n=== STORAGE TESTING ===\n";
echo "- Storage path: " . storage_path() . "\n";
echo "- Public storage path: " . storage_path('app/public') . "\n";
echo "- Public storage exists: " . (is_dir(storage_path('app/public')) ? 'YES' : 'NO') . "\n";

if (is_dir(storage_path('app/public'))) {
    echo "- Shared documents dir: " . storage_path('app/public/shared-documents') . "\n";
    echo "- Shared documents exists: " . (is_dir(storage_path('app/public/shared-documents')) ? 'YES' : 'NO') . "\n";
    
    if (is_dir(storage_path('app/public/shared-documents'))) {
        $files = scandir(storage_path('app/public/shared-documents'));
        echo "- Files in shared-documents:\n";
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $filePath = storage_path('app/public/shared-documents/' . $file);
                echo "  * $file (" . filesize($filePath) . " bytes)\n";
            }
        }
    }
} 