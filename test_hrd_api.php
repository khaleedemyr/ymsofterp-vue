<?php

/**
 * Script untuk test API HRD approvals
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

echo "========================================\n";
echo "TEST HRD APPROVALS API\n";
echo "========================================\n\n";

// Cek user HRD
echo "1. CEK USER HRD:\n";
echo "----------------------------------------\n";
$hrdUsers = DB::table('users')
    ->where('division_id', 6)
    ->where('status', 'A')
    ->select('id', 'nama_lengkap', 'division_id', 'id_role')
    ->get();

if ($hrdUsers->isEmpty()) {
    echo "ERROR: Tidak ada user HRD yang aktif!\n";
    exit(1);
}

echo "Total HRD users: {$hrdUsers->count()}\n";
foreach ($hrdUsers as $hrdUser) {
    echo "  - ID: {$hrdUser->id}, Name: {$hrdUser->nama_lengkap}, Role: {$hrdUser->id_role}\n";
}
echo "\n";

// Test dengan user HRD pertama
$testUserId = $hrdUsers->first()->id;
echo "Testing dengan user ID: {$testUserId} ({$hrdUsers->first()->nama_lengkap})\n\n";

// Simulasi login
$user = DB::table('users')->where('id', $testUserId)->first();
if (!$user) {
    echo "ERROR: User tidak ditemukan!\n";
    exit(1);
}

// Test query langsung
echo "2. TEST QUERY LANGSUNG:\n";
echo "----------------------------------------\n";
$approvals = DB::table('approval_requests as ar')
    ->join('users', 'ar.user_id', '=', 'users.id')
    ->join('leave_types', 'ar.leave_type_id', '=', 'leave_types.id')
    ->where('ar.status', 'approved')
    ->where('ar.hrd_status', 'pending')
    ->select([
        'ar.id',
        'ar.user_id',
        'ar.date_from',
        'ar.date_to',
        'ar.reason',
        'ar.created_at',
        'ar.status',
        'ar.hrd_status',
        'users.nama_lengkap as user_name',
        'leave_types.name as leave_type_name',
        'leave_types.id as leave_type_id'
    ])
    ->orderBy('ar.created_at', 'desc')
    ->limit(10)
    ->get();

echo "Total approvals found: {$approvals->count()}\n\n";

if ($approvals->isEmpty()) {
    echo "WARNING: Tidak ada approval yang ditemukan!\n";
    echo "\nCek kondisi:\n";
    $checkStatus = DB::table('approval_requests')
        ->where('status', 'approved')
        ->where('hrd_status', 'pending')
        ->count();
    echo "  - approval_requests dengan status='approved' AND hrd_status='pending': {$checkStatus}\n";
} else {
    echo "Approvals yang ditemukan:\n";
    foreach ($approvals as $approval) {
        echo "\n  Approval ID: {$approval->id}\n";
        echo "  User: {$approval->user_name}\n";
        echo "  Leave Type: {$approval->leave_type_name}\n";
        echo "  Date: {$approval->date_from} - {$approval->date_to}\n";
        echo "  Status: {$approval->status}\n";
        echo "  HRD Status: {$approval->hrd_status}\n";
        echo "  Created: {$approval->created_at}\n";
    }
}

// Test khusus untuk approval ID 503
echo "\n3. TEST APPROVAL ID 503 (absent_request 531):\n";
echo "----------------------------------------\n";
$approval503 = DB::table('approval_requests as ar')
    ->join('users', 'ar.user_id', '=', 'users.id')
    ->join('leave_types', 'ar.leave_type_id', '=', 'leave_types.id')
    ->where('ar.id', 503)
    ->where('ar.status', 'approved')
    ->where('ar.hrd_status', 'pending')
    ->select([
        'ar.id',
        'ar.user_id',
        'ar.date_from',
        'ar.date_to',
        'ar.reason',
        'ar.created_at',
        'ar.status',
        'ar.hrd_status',
        'users.nama_lengkap as user_name',
        'leave_types.name as leave_type_name'
    ])
    ->first();

if ($approval503) {
    echo "✓ Approval ID 503 DITEMUKAN di query!\n";
    echo "  User: {$approval503->user_name}\n";
    echo "  Leave Type: {$approval503->leave_type_name}\n";
    echo "  Date: {$approval503->date_from} - {$approval503->date_to}\n";
    echo "  Status: {$approval503->status}\n";
    echo "  HRD Status: {$approval503->hrd_status}\n";
} else {
    echo "✗ Approval ID 503 TIDAK DITEMUKAN di query!\n";
    
    // Cek kenapa tidak ditemukan
    $check503 = DB::table('approval_requests')
        ->where('id', 503)
        ->first();
    
    if ($check503) {
        echo "\nData approval_requests ID 503:\n";
        echo "  Status: {$check503->status}\n";
        echo "  HRD Status: " . ($check503->hrd_status ?? 'NULL') . "\n";
        echo "  HRD Approver ID: " . ($check503->hrd_approver_id ?? 'NULL') . "\n";
    } else {
        echo "  ERROR: Approval request ID 503 tidak ditemukan di database!\n";
    }
}

// Test controller method
echo "\n4. TEST CONTROLLER METHOD:\n";
echo "----------------------------------------\n";
echo "Simulasi memanggil ApprovalController::getPendingHrdApprovals()\n";

// Simulasi request
$request = new \Illuminate\Http\Request();
$request->merge(['limit' => 10]);

// Simulasi auth
Auth::loginUsingId($testUserId);

try {
    $controller = new \App\Http\Controllers\ApprovalController();
    $response = $controller->getPendingHrdApprovals($request);
    $responseData = json_decode($response->getContent(), true);
    
    if ($responseData['success']) {
        $approvals = $responseData['approvals'] ?? [];
        echo "✓ Controller mengembalikan " . count($approvals) . " approvals\n";
        
        // Cek apakah ID 503 ada
        $has503 = collect($approvals)->contains('id', 503);
        if ($has503) {
            echo "✓ Approval ID 503 ada di response controller\n";
        } else {
            echo "✗ Approval ID 503 TIDAK ada di response controller\n";
        }
        
        if (count($approvals) > 0) {
            echo "\nApprovals dari controller:\n";
            foreach (array_slice($approvals, 0, 5) as $approval) {
                echo "  - ID: {$approval['id']}, User: {$approval['user']['nama_lengkap']}, Date: {$approval['date_from']} - {$approval['date_to']}\n";
            }
        }
    } else {
        echo "✗ Controller mengembalikan error: " . ($responseData['message'] ?? 'Unknown error') . "\n";
    }
} catch (\Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n========================================\n";

