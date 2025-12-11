<?php

/**
 * Script untuk cek absent_request id=531 dan kenapa tidak muncul di HRD
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "CHECK ABSENT REQUEST ID=531\n";
echo "========================================\n\n";

$absentRequestId = 531;

// 1. Cek absent_request
echo "1. ABSENT REQUEST DATA:\n";
echo "----------------------------------------\n";
$absentRequest = DB::table('absent_requests')
    ->where('id', $absentRequestId)
    ->first();

if (!$absentRequest) {
    echo "ERROR: Absent request dengan id={$absentRequestId} tidak ditemukan!\n";
    exit(1);
}

echo "ID: {$absentRequest->id}\n";
echo "User ID: {$absentRequest->user_id}\n";
echo "Approval Request ID: {$absentRequest->approval_request_id}\n";
echo "Status: {$absentRequest->status}\n";
echo "Approved By: " . ($absentRequest->approved_by ?? 'NULL') . "\n";
echo "Approved At: " . ($absentRequest->approved_at ?? 'NULL') . "\n";
echo "HRD Approved By: " . ($absentRequest->hrd_approved_by ?? 'NULL') . "\n";
echo "HRD Approved At: " . ($absentRequest->hrd_approved_at ?? 'NULL') . "\n";
echo "Created At: {$absentRequest->created_at}\n";
echo "Updated At: {$absentRequest->updated_at}\n\n";

// 2. Cek approval_request terkait
echo "2. APPROVAL REQUEST DATA:\n";
echo "----------------------------------------\n";
$approvalRequest = DB::table('approval_requests')
    ->where('id', $absentRequest->approval_request_id)
    ->first();

if (!$approvalRequest) {
    echo "ERROR: Approval request dengan id={$absentRequest->approval_request_id} tidak ditemukan!\n";
    exit(1);
}

echo "ID: {$approvalRequest->id}\n";
echo "User ID: {$approvalRequest->user_id}\n";
echo "Approver ID: " . ($approvalRequest->approver_id ?? 'NULL') . "\n";
echo "HRD Approver ID: " . ($approvalRequest->hrd_approver_id ?? 'NULL') . "\n";
echo "Status: {$approvalRequest->status}\n";
echo "HRD Status: " . ($approvalRequest->hrd_status ?? 'NULL') . "\n";
echo "Date From: {$approvalRequest->date_from}\n";
echo "Date To: {$approvalRequest->date_to}\n";
echo "Approved At: " . ($approvalRequest->approved_at ?? 'NULL') . "\n";
echo "HRD Approved At: " . ($approvalRequest->hrd_approved_at ?? 'NULL') . "\n";
echo "Created At: {$approvalRequest->created_at}\n";
echo "Updated At: {$approvalRequest->updated_at}\n\n";

// 3. Cek approval flows
echo "3. APPROVAL FLOWS:\n";
echo "----------------------------------------\n";
$approvalFlows = DB::table('absent_request_approval_flows')
    ->where('absent_request_id', $absentRequestId)
    ->orderBy('approval_level')
    ->get();

if ($approvalFlows->isEmpty()) {
    echo "INFO: Tidak ada approval flows (menggunakan old flow)\n\n";
} else {
    echo "Total flows: {$approvalFlows->count()}\n";
    foreach ($approvalFlows as $flow) {
        echo "\n  Flow ID: {$flow->id}\n";
        echo "  Approval Level: {$flow->approval_level}\n";
        echo "  Approver ID: {$flow->approver_id}\n";
        echo "  Status: {$flow->status}\n";
        echo "  Approved By: " . ($flow->approved_by ?? 'NULL') . "\n";
        echo "  Approved At: " . ($flow->approved_at ?? 'NULL') . "\n";
    }
    echo "\n";
}

// 4. Cek kondisi query HRD
echo "4. CHECK HRD APPROVAL CONDITIONS:\n";
echo "----------------------------------------\n";

$conditions = [
    'approval_requests.status = "approved"' => $approvalRequest->status === 'approved',
    'approval_requests.hrd_status = "pending"' => $approvalRequest->hrd_status === 'pending',
    'approval_requests.hrd_approver_id IS NOT NULL' => !empty($approvalRequest->hrd_approver_id),
];

echo "Kondisi untuk muncul di HRD:\n";
foreach ($conditions as $condition => $met) {
    $status = $met ? '✓' : '✗';
    echo "  {$status} {$condition}\n";
}

// 5. Cek apakah ada pending flows
if ($approvalFlows->isNotEmpty()) {
    $pendingFlows = $approvalFlows->where('status', 'PENDING');
    echo "\nPending approval flows: {$pendingFlows->count()}\n";
    if ($pendingFlows->isNotEmpty()) {
        echo "  ✗ Masih ada approval flow yang PENDING - ini yang menyebabkan tidak muncul di HRD!\n";
        foreach ($pendingFlows as $flow) {
            echo "    - Level {$flow->approval_level}, Approver ID: {$flow->approver_id}\n";
        }
    } else {
        echo "  ✓ Semua approval flows sudah APPROVED\n";
    }
}

// 6. Test query HRD
echo "\n5. TEST HRD QUERY:\n";
echo "----------------------------------------\n";
$hrdApprovals = DB::table('approval_requests as ar')
    ->join('users', 'ar.user_id', '=', 'users.id')
    ->join('leave_types', 'ar.leave_type_id', '=', 'leave_types.id')
    ->where('ar.status', 'approved')
    ->where('ar.hrd_status', 'pending')
    ->where('ar.id', $approvalRequest->id)
    ->select([
        'ar.id',
        'ar.user_id',
        'ar.date_from',
        'ar.date_to',
        'ar.reason',
        'ar.created_at',
        'users.nama_lengkap as user_name',
        'leave_types.name as leave_type_name',
        'leave_types.id as leave_type_id'
    ])
    ->get();

if ($hrdApprovals->isEmpty()) {
    echo "✗ Approval request ini TIDAK muncul di query HRD\n";
    echo "\nAlasan:\n";
    
    if ($approvalRequest->status !== 'approved') {
        echo "  - approval_requests.status = '{$approvalRequest->status}' (harus 'approved')\n";
    }
    if ($approvalRequest->hrd_status !== 'pending') {
        echo "  - approval_requests.hrd_status = " . ($approvalRequest->hrd_status ?? 'NULL') . " (harus 'pending')\n";
    }
    if (empty($approvalRequest->hrd_approver_id)) {
        echo "  - approval_requests.hrd_approver_id = NULL (harus diisi)\n";
    }
} else {
    echo "✓ Approval request ini MUNCUL di query HRD\n";
    $approval = $hrdApprovals->first();
    echo "\nData yang akan muncul:\n";
    echo "  User: {$approval->user_name}\n";
    echo "  Leave Type: {$approval->leave_type_name}\n";
    echo "  Date: {$approval->date_from} - {$approval->date_to}\n";
}

// 7. Rekomendasi perbaikan
echo "\n6. REKOMENDASI PERBAIKAN:\n";
echo "----------------------------------------\n";

$fixes = [];

if ($approvalRequest->status !== 'approved') {
    $fixes[] = "UPDATE approval_requests SET status = 'approved' WHERE id = {$approvalRequest->id};";
}

if ($approvalRequest->hrd_status !== 'pending') {
    $hrdApprover = DB::table('users')
        ->where('division_id', 6)
        ->where('status', 'A')
        ->first();
    
    if ($hrdApprover) {
        $fixes[] = "UPDATE approval_requests SET hrd_status = 'pending', hrd_approver_id = {$hrdApprover->id} WHERE id = {$approvalRequest->id};";
    } else {
        $fixes[] = "ERROR: Tidak ada HRD user yang aktif!";
    }
}

if ($approvalFlows->isNotEmpty()) {
    $pendingFlows = $approvalFlows->where('status', 'PENDING');
    if ($pendingFlows->isNotEmpty()) {
        $fixes[] = "WARNING: Masih ada approval flows yang PENDING. Pastikan semua supervisor sudah approve terlebih dahulu.";
    }
}

if (empty($fixes)) {
    echo "✓ Data sudah benar, seharusnya muncul di HRD.\n";
    echo "  Jika masih tidak muncul, cek:\n";
    echo "  1. Apakah user HRD login dengan akun yang division_id = 6?\n";
    echo "  2. Apakah ada filter lain di frontend?\n";
    echo "  3. Cek browser console untuk error JavaScript\n";
} else {
    echo "SQL untuk memperbaiki:\n\n";
    foreach ($fixes as $fix) {
        echo "{$fix}\n";
    }
}

echo "\n========================================\n";

