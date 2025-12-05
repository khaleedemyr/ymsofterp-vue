<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Set up Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST OUTLET REJECTION USER DATA ===\n\n";

// Test 1: Cek data outlet rejection dengan relationships
echo "1. OUTLET REJECTION DENGAN RELATIONSHIPS:\n";
$rejections = DB::table('outlet_rejections as or')
    ->leftJoin('users as created_by', 'or.created_by', '=', 'created_by.id')
    ->leftJoin('users as approved_by', 'or.approved_by', '=', 'approved_by.id')
    ->leftJoin('users as completed_by', 'or.completed_by', '=', 'completed_by.id')
    ->leftJoin('users as assistant_ssd', 'or.assistant_ssd_manager_approved_by', '=', 'assistant_ssd.id')
    ->leftJoin('users as ssd_manager', 'or.ssd_manager_approved_by', '=', 'ssd_manager.id')
    ->leftJoin('tbl_data_outlet as outlet', 'or.outlet_id', '=', 'outlet.id_outlet')
    ->leftJoin('warehouses as warehouse', 'or.warehouse_id', '=', 'warehouse.id')
    ->select(
        'or.id',
        'or.number',
        'or.status',
        'or.rejection_date',
        'outlet.nama_outlet',
        'warehouse.name as warehouse_name',
        'created_by.nama_lengkap as created_by_name',
        'or.created_at',
        'assistant_ssd.nama_lengkap as assistant_ssd_name',
        'or.assistant_ssd_manager_approved_at',
        'ssd_manager.nama_lengkap as ssd_manager_name',
        'or.ssd_manager_approved_at',
        'completed_by.nama_lengkap as completed_by_name',
        'or.completed_at'
    )
    ->orderBy('or.created_at', 'desc')
    ->limit(5)
    ->get();

foreach ($rejections as $rejection) {
    echo "Rejection ID: {$rejection->id}\n";
    echo "Number: {$rejection->number}\n";
    echo "Status: {$rejection->status}\n";
    echo "Outlet: {$rejection->nama_outlet}\n";
    echo "Warehouse: {$rejection->warehouse_name}\n";
    echo "Created By: {$rejection->created_by_name}\n";
    echo "Created At: {$rejection->created_at}\n";
    echo "Assistant SSD Manager: {$rejection->assistant_ssd_name}\n";
    echo "Assistant SSD At: {$rejection->assistant_ssd_manager_approved_at}\n";
    echo "SSD Manager: {$rejection->ssd_manager_name}\n";
    echo "SSD Manager At: {$rejection->ssd_manager_approved_at}\n";
    echo "Completed By: {$rejection->completed_by_name}\n";
    echo "Completed At: {$rejection->completed_at}\n";
    echo "---\n";
}

echo "\n";

// Test 2: Cek data user yang ada
echo "2. DATA USER YANG ADA:\n";
$users = DB::table('users')
    ->select('id', 'nama_lengkap', 'email')
    ->whereIn('id', [1, 2, 3, 4, 5])
    ->get();

foreach ($users as $user) {
    echo "User ID: {$user->id}, Name: {$user->nama_lengkap}, Email: {$user->email}\n";
}

echo "\n";

// Test 3: Cek relationship di model
echo "3. TEST RELATIONSHIP DI MODEL:\n";
try {
    $rejection = \App\Models\OutletRejection::with([
        'createdBy:id,nama_lengkap',
        'approvedBy:id,nama_lengkap',
        'completedBy:id,nama_lengkap',
        'assistantSsdManager:id,nama_lengkap',
        'ssdManager:id,nama_lengkap'
    ])->first();

    if ($rejection) {
        echo "Rejection Number: {$rejection->number}\n";
        echo "Created By: " . ($rejection->createdBy ? $rejection->createdBy->nama_lengkap : 'null') . "\n";
        echo "Approved By: " . ($rejection->approvedBy ? $rejection->approvedBy->nama_lengkap : 'null') . "\n";
        echo "Completed By: " . ($rejection->completedBy ? $rejection->completedBy->nama_lengkap : 'null') . "\n";
        echo "Assistant SSD Manager: " . ($rejection->assistantSsdManager ? $rejection->assistantSsdManager->nama_lengkap : 'null') . "\n";
        echo "SSD Manager: " . ($rejection->ssdManager ? $rejection->ssdManager->nama_lengkap : 'null') . "\n";
    } else {
        echo "Tidak ada data outlet rejection\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Cek data approval info yang akan dikirim ke frontend
echo "4. DATA APPROVAL INFO UNTUK FRONTEND:\n";
$rejection = \App\Models\OutletRejection::with([
    'createdBy:id,nama_lengkap',
    'approvedBy:id,nama_lengkap',
    'completedBy:id,nama_lengkap',
    'assistantSsdManager:id,nama_lengkap',
    'ssdManager:id,nama_lengkap'
])->first();

if ($rejection) {
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
} else {
    echo "Tidak ada data outlet rejection\n";
}

echo "\n=== TEST SELESAI ===\n";
