<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Set up Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST FRONTEND DATA STRUCTURE ===\n\n";

// Test 1: Simulate index method data
echo "1. INDEX METHOD DATA STRUCTURE:\n";
$rejections = \App\Models\OutletRejection::with([
    'outlet', 
    'warehouse', 
    'deliveryOrder', 
    'createdBy:id,nama_lengkap',
    'approvedBy:id,nama_lengkap',
    'completedBy:id,nama_lengkap',
    'assistantSsdManager:id,nama_lengkap',
    'ssdManager:id,nama_lengkap'
])
->orderBy('created_at', 'desc')
->limit(3)
->get();

foreach ($rejections as $rejection) {
    echo "Rejection ID: {$rejection->id}\n";
    echo "Number: {$rejection->number}\n";
    echo "Status: {$rejection->status}\n";
    
    // Simulate approval_info transformation
    $approval_info = [
        'created_by' => $rejection->createdBy ? $rejection->createdBy->nama_lengkap : null,
        'created_at' => $rejection->created_at ? $rejection->created_at->format('d/m/Y H:i') : null,
        'assistant_ssd_manager' => $rejection->assistantSsdManager ? $rejection->assistantSsdManager->nama_lengkap : null,
        'assistant_ssd_manager_at' => $rejection->assistant_ssd_manager_approved_at ? $rejection->assistant_ssd_manager_approved_at->format('d/m/Y H:i') : null,
        'ssd_manager' => $rejection->ssdManager ? $rejection->ssdManager->nama_lengkap : null,
        'ssd_manager_at' => $rejection->ssd_manager_approved_at ? $rejection->ssd_manager_approved_at->format('d/m/Y H:i') : null,
        'completed_by' => $rejection->completedBy ? $rejection->completedBy->nama_lengkap : null,
        'completed_at' => $rejection->completed_at ? $rejection->completed_at->format('d/m/Y H:i') : null,
    ];
    
    echo "Approval Info:\n";
    foreach ($approval_info as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    
    echo "Frontend will display:\n";
    echo "  Created By: " . ($approval_info['created_by'] ?: '-') . "\n";
    echo "  Created At: " . ($approval_info['created_at'] ?: '-') . "\n";
    echo "  SSD Manager: " . ($approval_info['ssd_manager'] ?: '-') . "\n";
    echo "  SSD Manager At: " . ($approval_info['ssd_manager_at'] ?: '-') . "\n";
    echo "  Completed By: " . ($approval_info['completed_by'] ?: '-') . "\n";
    echo "  Completed At: " . ($approval_info['completed_at'] ?: '-') . "\n";
    echo "---\n";
}

echo "\n";

// Test 2: Simulate show method data
echo "2. SHOW METHOD DATA STRUCTURE:\n";
$rejection = \App\Models\OutletRejection::with([
    'outlet', 
    'warehouse', 
    'deliveryOrder', 
    'createdBy:id,nama_lengkap', 
    'approvedBy:id,nama_lengkap', 
    'completedBy:id,nama_lengkap',
    'assistantSsdManager:id,nama_lengkap',
    'ssdManager:id,nama_lengkap',
    'items.item',
    'items.unit'
])->first();

if ($rejection) {
    echo "Rejection ID: {$rejection->id}\n";
    echo "Number: {$rejection->number}\n";
    echo "Status: {$rejection->status}\n";
    
    // Simulate approval_info addition
    $rejection->approval_info = [
        'created_by' => $rejection->createdBy ? $rejection->createdBy->nama_lengkap : null,
        'created_at' => $rejection->created_at ? $rejection->created_at->format('d/m/Y H:i') : null,
        'assistant_ssd_manager' => $rejection->assistantSsdManager ? $rejection->assistantSsdManager->nama_lengkap : null,
        'assistant_ssd_manager_at' => $rejection->assistant_ssd_manager_approved_at ? $rejection->assistant_ssd_manager_approved_at->format('d/m/Y H:i') : null,
        'ssd_manager' => $rejection->ssdManager ? $rejection->ssdManager->nama_lengkap : null,
        'ssd_manager_at' => $rejection->ssd_manager_approved_at ? $rejection->ssd_manager_approved_at->format('d/m/Y H:i') : null,
        'completed_by' => $rejection->completedBy ? $rejection->completedBy->nama_lengkap : null,
        'completed_at' => $rejection->completed_at ? $rejection->completed_at->format('d/m/Y H:i') : null,
    ];
    
    echo "Frontend will display in workflow section:\n";
    echo "  Created By: " . ($rejection->approval_info['created_by'] ?: '-') . "\n";
    echo "  Created At: " . ($rejection->approval_info['created_at'] ?: '-') . "\n";
    echo "  Assistant SSD Manager: " . ($rejection->approval_info['assistant_ssd_manager'] ?: '-') . "\n";
    echo "  Assistant SSD Manager At: " . ($rejection->approval_info['assistant_ssd_manager_at'] ?: '-') . "\n";
    echo "  SSD Manager: " . ($rejection->approval_info['ssd_manager'] ?: '-') . "\n";
    echo "  SSD Manager At: " . ($rejection->approval_info['ssd_manager_at'] ?: '-') . "\n";
    echo "  Completed By: " . ($rejection->approval_info['completed_by'] ?: '-') . "\n";
    echo "  Completed At: " . ($rejection->approval_info['completed_at'] ?: '-') . "\n";
} else {
    echo "Tidak ada data outlet rejection\n";
}

echo "\n";

// Test 3: Check if data matches frontend expectations
echo "3. FRONTEND DATA VALIDATION:\n";
$rejection = \App\Models\OutletRejection::with([
    'createdBy:id,nama_lengkap',
    'assistantSsdManager:id,nama_lengkap',
    'ssdManager:id,nama_lengkap',
    'completedBy:id,nama_lengkap'
])->first();

if ($rejection) {
    echo "Testing frontend data access:\n";
    
    // Test Index.vue data access
    echo "Index.vue will access:\n";
    echo "  rejection.approval_info?.created_by: " . ($rejection->createdBy ? $rejection->createdBy->nama_lengkap : 'null') . "\n";
    echo "  rejection.approval_info?.ssd_manager: " . ($rejection->ssdManager ? $rejection->ssdManager->nama_lengkap : 'null') . "\n";
    echo "  rejection.approval_info?.completed_by: " . ($rejection->completedBy ? $rejection->completedBy->nama_lengkap : 'null') . "\n";
    
    // Test Show.vue data access
    echo "Show.vue will access:\n";
    echo "  rejection.createdBy?.nama_lengkap: " . ($rejection->createdBy ? $rejection->createdBy->nama_lengkap : 'null') . "\n";
    echo "  rejection.assistantSsdManager?.nama_lengkap: " . ($rejection->assistantSsdManager ? $rejection->assistantSsdManager->nama_lengkap : 'null') . "\n";
    echo "  rejection.ssdManager?.nama_lengkap: " . ($rejection->ssdManager ? $rejection->ssdManager->nama_lengkap : 'null') . "\n";
    echo "  rejection.completedBy?.nama_lengkap: " . ($rejection->completedBy ? $rejection->completedBy->nama_lengkap : 'null') . "\n";
} else {
    echo "Tidak ada data outlet rejection\n";
}

echo "\n=== TEST SELESAI ===\n";
