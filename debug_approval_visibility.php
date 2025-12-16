<?php

/**
 * Script untuk debug mengapa approval tidak muncul di daftar pending approval
 * 
 * Usage: php debug_approval_visibility.php [--approval-id=ID] [--approver-id=ID]
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$approvalId = null;
$approverId = null;

// Parse arguments
foreach ($argv as $arg) {
    if (strpos($arg, '--approval-id=') === 0) {
        $approvalId = str_replace('--approval-id=', '', $arg);
    }
    if (strpos($arg, '--approver-id=') === 0) {
        $approverId = str_replace('--approver-id=', '', $arg);
    }
}

echo "========================================\n";
echo "DEBUG APPROVAL VISIBILITY\n";
echo "========================================\n\n";

// Get approval request ID 563
if (!$approvalId) {
    $approvalId = 563; // Default untuk case ini
}

echo "Approval Request ID: {$approvalId}\n\n";

// 1. Get approval request details
$approvalRequest = DB::table('approval_requests')
    ->where('id', $approvalId)
    ->first();

if (!$approvalRequest) {
    echo "ERROR: Approval request tidak ditemukan!\n";
    exit(1);
}

echo "1. APPROVAL REQUEST DETAILS:\n";
echo "   - ID: {$approvalRequest->id}\n";
echo "   - Status: {$approvalRequest->status}\n";
echo "   - HRD Status: " . ($approvalRequest->hrd_status ?? 'NULL') . "\n";
echo "   - User ID: {$approvalRequest->user_id}\n";
$user = DB::table('users')->where('id', $approvalRequest->user_id)->first();
echo "   - User: " . ($user ? $user->nama_lengkap : 'NULL') . "\n\n";

// 2. Get absent request
$absentRequest = DB::table('absent_requests')
    ->where('approval_request_id', $approvalId)
    ->first();

if (!$absentRequest) {
    echo "ERROR: Absent request tidak ditemukan!\n";
    exit(1);
}

echo "2. ABSENT REQUEST DETAILS:\n";
echo "   - ID: {$absentRequest->id}\n";
echo "   - Status: {$absentRequest->status}\n\n";

// 3. Get all approval flows
echo "3. APPROVAL FLOWS:\n";
$allFlows = DB::table('absent_request_approval_flows')
    ->where('absent_request_id', $absentRequest->id)
    ->orderBy('approval_level')
    ->get();

foreach ($allFlows as $flow) {
    $approver = DB::table('users')->where('id', $flow->approver_id)->first();
    $approvedBy = null;
    if ($flow->approved_by) {
        $approvedBy = DB::table('users')->where('id', $flow->approved_by)->first();
    }
    
    echo "   Level {$flow->approval_level}:\n";
    echo "   - Status: {$flow->status}\n";
    echo "   - Approver: " . ($approver ? $approver->nama_lengkap : 'NULL') . " (ID: {$flow->approver_id})\n";
    if ($flow->approved_by) {
        echo "   - Approved By: " . ($approvedBy ? $approvedBy->nama_lengkap : 'NULL') . " (ID: {$flow->approved_by})\n";
        echo "   - Approved At: {$flow->approved_at}\n";
    }
    echo "\n";
}

// 4. Simulate getPendingApprovals query for each approver
echo "4. SIMULASI QUERY getPendingApprovals UNTUK SETIAP APPROVER:\n\n";

foreach ($allFlows as $flow) {
    $testApproverId = $flow->approver_id;
    $testApprover = DB::table('users')->where('id', $testApproverId)->first();
    
    echo "   --- Testing untuk: " . ($testApprover ? $testApprover->nama_lengkap : 'NULL') . " (ID: {$testApproverId}) ---\n";
    
    // Simulate query from getPendingApprovals
    $approvalFlowsQuery = DB::table('absent_request_approval_flows as arf')
        ->join('absent_requests as ar', 'arf.absent_request_id', '=', 'ar.id')
        ->join('approval_requests as apr', 'ar.approval_request_id', '=', 'apr.id')
        ->join('users', 'apr.user_id', '=', 'users.id')
        ->join('leave_types', 'apr.leave_type_id', '=', 'leave_types.id')
        ->where('arf.status', 'PENDING')
        ->where('arf.approver_id', $testApproverId);
    
    $approvalFlows = $approvalFlowsQuery
        ->select([
            'apr.id',
            'apr.user_id',
            'apr.date_from',
            'apr.date_to',
            'apr.reason',
            'apr.created_at',
            'users.nama_lengkap as user_name',
            'leave_types.name as leave_type_name',
            'leave_types.id as leave_type_id',
            'arf.approval_level',
            'ar.id as absent_request_id'
        ])
        ->orderBy('apr.created_at', 'desc')
        ->get();
    
    echo "   Query result count: " . $approvalFlows->count() . "\n";
    
    if ($approvalFlows->count() > 0) {
        $found = false;
        foreach ($approvalFlows as $af) {
            if ($af->id == $approvalId) {
                $found = true;
                echo "   ✓ Approval request {$approvalId} DITEMUKAN di query\n";
                break;
            }
        }
        if (!$found) {
            echo "   ✗ Approval request {$approvalId} TIDAK ditemukan di query\n";
        }
    } else {
        echo "   ✗ Tidak ada approval flows yang ditemukan\n";
    }
    
    // Check filter logic (next approver in line)
    $pendingFlows = DB::table('absent_request_approval_flows')
        ->where('absent_request_id', $absentRequest->id)
        ->where('absent_request_approval_flows.status', 'PENDING')
        ->orderBy('approval_level')
        ->get();
    
    echo "   Pending flows untuk absent_request_id {$absentRequest->id}:\n";
    foreach ($pendingFlows as $pf) {
        $pfApprover = DB::table('users')->where('id', $pf->approver_id)->first();
        echo "     - Level {$pf->approval_level}: " . ($pfApprover ? $pfApprover->nama_lengkap : 'NULL') . " (ID: {$pf->approver_id})\n";
    }
    
    if ($pendingFlows->isEmpty()) {
        echo "   ⚠️  Tidak ada pending flows - akan di-filter out\n";
    } else {
        $nextApprover = $pendingFlows->first();
        if ($nextApprover->approver_id == $testApproverId) {
            echo "   ✓ User adalah next approver in line (Level {$nextApprover->approval_level})\n";
            echo "   ✓ Approval request AKAN MUNCUL di daftar pending\n";
        } else {
            $nextApproverUser = DB::table('users')->where('id', $nextApprover->approver_id)->first();
            echo "   ✗ User BUKAN next approver in line\n";
            echo "   ✗ Next approver adalah: " . ($nextApproverUser ? $nextApproverUser->nama_lengkap : 'NULL') . " (Level {$nextApprover->approval_level})\n";
            echo "   ✗ Approval request TIDAK AKAN MUNCUL di daftar pending\n";
        }
    }
    
    echo "\n";
}

// 5. Check specific approver if provided
if ($approverId) {
    echo "5. CEK SPESIFIK UNTUK APPROVER ID: {$approverId}\n";
    $specificApprover = DB::table('users')->where('id', $approverId)->first();
    if ($specificApprover) {
        echo "   Approver: {$specificApprover->nama_lengkap}\n";
        
        // Check if this approver has a pending flow
        $approverFlow = DB::table('absent_request_approval_flows')
            ->where('absent_request_id', $absentRequest->id)
            ->where('approver_id', $approverId)
            ->first();
        
        if ($approverFlow) {
            echo "   Flow ditemukan:\n";
            echo "   - Level: {$approverFlow->approval_level}\n";
            echo "   - Status: {$approverFlow->status}\n";
            
            if ($approverFlow->status === 'PENDING') {
                // Check if this is the next approver
                $pendingFlows = DB::table('absent_request_approval_flows')
                    ->where('absent_request_id', $absentRequest->id)
                    ->where('absent_request_approval_flows.status', 'PENDING')
                    ->orderBy('approval_level')
                    ->get();
                
                if (!$pendingFlows->isEmpty()) {
                    $nextApprover = $pendingFlows->first();
                    if ($nextApprover->approver_id == $approverId) {
                        echo "   ✓ User adalah next approver in line\n";
                        echo "   ✓ Seharusnya MUNCUL di daftar pending approval\n";
                    } else {
                        $nextApproverUser = DB::table('users')->where('id', $nextApprover->approver_id)->first();
                        echo "   ✗ User BUKAN next approver in line\n";
                        echo "   ✗ Next approver adalah: " . ($nextApproverUser ? $nextApproverUser->nama_lengkap : 'NULL') . " (Level {$nextApprover->approval_level})\n";
                        echo "   ✗ TIDAK AKAN MUNCUL di daftar pending approval\n";
                    }
                }
            } else {
                echo "   ⚠️  Status flow bukan PENDING, jadi tidak akan muncul\n";
            }
        } else {
            echo "   ✗ Tidak ada flow untuk approver ini\n";
        }
    } else {
        echo "   ✗ Approver tidak ditemukan\n";
    }
    echo "\n";
}

// 6. Summary and recommendations
echo "========================================\n";
echo "SUMMARY & REKOMENDASI\n";
echo "========================================\n";

$pendingFlows = DB::table('absent_request_approval_flows')
    ->where('absent_request_id', $absentRequest->id)
    ->where('absent_request_approval_flows.status', 'PENDING')
    ->orderBy('approval_level')
    ->get();

if ($pendingFlows->isEmpty()) {
    echo "⚠️  Tidak ada pending flows - semua sudah di-approve atau di-reject\n";
    echo "   Seharusnya sudah masuk ke HRD approval\n";
} else {
    $nextApprover = $pendingFlows->first();
    $nextApproverUser = DB::table('users')->where('id', $nextApprover->approver_id)->first();
    echo "Next approver yang seharusnya melihat approval ini:\n";
    echo "- " . ($nextApproverUser ? $nextApproverUser->nama_lengkap : 'NULL') . " (ID: {$nextApprover->approver_id}, Level: {$nextApprover->approval_level})\n";
    echo "\n";
    echo "Jika approver ini tidak melihat approval:\n";
    echo "1. Cek apakah user ID benar\n";
    echo "2. Cek apakah ada masalah dengan autentikasi\n";
    echo "3. Cek log untuk error\n";
}

echo "\n";

