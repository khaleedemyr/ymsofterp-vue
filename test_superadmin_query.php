<?php

/**
 * Script untuk test query superadmin secara detail
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$approvalId = 563;

echo "========================================\n";
echo "TEST QUERY SUPERADMIN UNTUK APPROVAL 563\n";
echo "========================================\n\n";

// Simulate superadmin query (id_role = '5af56935b011a')
$isSuperadmin = true;
$userId = 109; // Erkan (superadmin)

echo "Testing sebagai superadmin (ID: {$userId})\n\n";

// Step 1: Get approval flows query (exact copy from ApprovalController)
echo "1. QUERY APPROVAL FLOWS (NEW FLOW):\n";
$approvalFlowsQuery = DB::table('absent_request_approval_flows as arf')
    ->join('absent_requests as ar', 'arf.absent_request_id', '=', 'ar.id')
    ->join('approval_requests as apr', 'ar.approval_request_id', '=', 'apr.id')
    ->join('users', 'apr.user_id', '=', 'users.id')
    ->join('leave_types', 'apr.leave_type_id', '=', 'leave_types.id')
    ->where('arf.status', 'PENDING');

// Superadmin can see all, so no filter by approver_id
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
        'ar.id as absent_request_id',
        'ar.status as absent_status'
    ])
    ->orderBy('apr.created_at', 'desc')
    ->get();

echo "   Total hasil query: " . $approvalFlows->count() . "\n";

// Check if approval 563 is in the results
$found563 = false;
foreach ($approvalFlows as $af) {
    if ($af->id == $approvalId) {
        $found563 = true;
        echo "   ✓ Approval 563 DITEMUKAN di query!\n";
        echo "      - User: {$af->user_name}\n";
        echo "      - Date: {$af->date_from} - {$af->date_to}\n";
        echo "      - Level: {$af->approval_level}\n";
        echo "      - Absent Status: {$af->absent_status}\n";
        break;
    }
}

if (!$found563) {
    echo "   ✗ Approval 563 TIDAK ditemukan di query\n";
    echo "\n   Debug: Cek apakah ada approval flow dengan status PENDING untuk approval 563\n";
    
    $absentRequest = DB::table('absent_requests')
        ->where('approval_request_id', $approvalId)
        ->first();
    
    if ($absentRequest) {
        echo "   Absent Request ID: {$absentRequest->id}\n";
        echo "   Absent Request Status: {$absentRequest->status}\n";
        
        $flows = DB::table('absent_request_approval_flows')
            ->where('absent_request_id', $absentRequest->id)
            ->get();
        
        echo "   Approval Flows:\n";
        foreach ($flows as $flow) {
            echo "     - Level {$flow->approval_level}: {$flow->status}\n";
        }
        
        $pendingFlows = DB::table('absent_request_approval_flows')
            ->where('absent_request_id', $absentRequest->id)
            ->where('status', 'PENDING')
            ->get();
        
        echo "   Pending Flows: " . $pendingFlows->count() . "\n";
        if ($pendingFlows->isEmpty()) {
            echo "   ⚠️  TIDAK ADA PENDING FLOWS! Ini masalahnya!\n";
        }
    }
}

echo "\n";

// Step 2: Filter logic (next approver in line)
echo "2. FILTER: NEXT APPROVER IN LINE (untuk superadmin, semua akan muncul):\n";
$filteredApprovals = $approvalFlows->filter(function($approval) use ($isSuperadmin, $userId) {
    if ($isSuperadmin) {
        return true; // Superadmin can see all
    }
    
    // Get all pending flows for this absent request
    $pendingFlows = DB::table('absent_request_approval_flows')
        ->where('absent_request_id', $approval->absent_request_id)
        ->where('absent_request_approval_flows.status', 'PENDING')
        ->orderBy('approval_level')
        ->get();
    
    // Check if current user is the first pending approver (next in line)
    if ($pendingFlows->isEmpty()) return false;
    $nextApprover = $pendingFlows->first();
    return $nextApprover->approver_id == $userId;
});

echo "   Setelah filter: " . $filteredApprovals->count() . " approval requests\n";

$found563Filtered = false;
foreach ($filteredApprovals as $fa) {
    if ($fa->id == $approvalId) {
        $found563Filtered = true;
        echo "   ✓ Approval 563 DITEMUKAN setelah filter!\n";
        break;
    }
}

if (!$found563Filtered) {
    echo "   ✗ Approval 563 TIDAK ditemukan setelah filter\n";
}

echo "\n";

// Step 3: Check old flow
echo "3. QUERY OLD FLOW:\n";
$oldApprovalsQuery = DB::table('approval_requests')
    ->join('users', 'approval_requests.user_id', '=', 'users.id')
    ->join('leave_types', 'approval_requests.leave_type_id', '=', 'leave_types.id')
    ->leftJoin('absent_requests', 'approval_requests.id', '=', 'absent_requests.approval_request_id')
    ->leftJoin('absent_request_approval_flows', function($join) use ($userId) {
        $join->on('absent_requests.id', '=', 'absent_request_approval_flows.absent_request_id')
             ->where('absent_request_approval_flows.approver_id', '=', $userId);
    })
    ->where('approval_requests.status', 'pending')
    ->whereNull('absent_request_approval_flows.id'); // Only old flow (no approval flows)

$oldApprovals = $oldApprovalsQuery
    ->select([
        'approval_requests.id',
        'approval_requests.user_id',
        'approval_requests.date_from',
        'approval_requests.date_to',
        'approval_requests.reason',
        'approval_requests.created_at',
        'users.nama_lengkap as user_name',
        'leave_types.name as leave_type_name',
        'leave_types.id as leave_type_id'
    ])
    ->orderBy('approval_requests.created_at', 'desc')
    ->get();

echo "   Total old flow: " . $oldApprovals->count() . "\n";

$found563Old = false;
foreach ($oldApprovals as $oa) {
    if ($oa->id == $approvalId) {
        $found563Old = true;
        echo "   ✓ Approval 563 ditemukan di old flow\n";
        break;
    }
}

if (!$found563Old) {
    echo "   ✗ Approval 563 tidak di old flow (benar, karena pakai new flow)\n";
}

echo "\n";

// Step 4: Final check - direct query
echo "4. DIRECT QUERY UNTUK APPROVAL 563:\n";
$directCheck = DB::table('approval_requests as apr')
    ->join('absent_requests as ar', 'apr.id', '=', 'ar.approval_request_id')
    ->join('absent_request_approval_flows as arf', 'ar.id', '=', 'arf.absent_request_id')
    ->where('apr.id', $approvalId)
    ->where('arf.status', 'PENDING')
    ->select([
        'apr.id',
        'apr.status as apr_status',
        'ar.id as ar_id',
        'ar.status as ar_status',
        'arf.id as arf_id',
        'arf.status as arf_status',
        'arf.approval_level'
    ])
    ->get();

echo "   Direct query result: " . $directCheck->count() . " rows\n";
if ($directCheck->count() > 0) {
    foreach ($directCheck as $dc) {
        echo "   - Approval ID: {$dc->id}\n";
        echo "     Approval Status: {$dc->apr_status}\n";
        echo "     Absent Status: {$dc->ar_status}\n";
        echo "     Flow Status: {$dc->arf_status}\n";
        echo "     Flow Level: {$dc->approval_level}\n";
    }
} else {
    echo "   ✗ TIDAK ADA ROW YANG DITEMUKAN!\n";
    echo "   Ini berarti tidak ada approval flow dengan status PENDING untuk approval 563\n";
}

echo "\n";

// Summary
echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Approval 563 di query new flow: " . ($found563 ? "✓ YA" : "✗ TIDAK") . "\n";
echo "Approval 563 setelah filter: " . ($found563Filtered ? "✓ YA" : "✗ TIDAK") . "\n";
echo "Approval 563 di old flow: " . ($found563Old ? "✓ YA" : "✗ TIDAK") . "\n";
echo "Direct query result: " . ($directCheck->count() > 0 ? "✓ ADA DATA" : "✗ TIDAK ADA DATA") . "\n";

if (!$found563 && $directCheck->count() == 0) {
    echo "\n⚠️  MASALAH: Tidak ada approval flow dengan status PENDING untuk approval 563!\n";
    echo "   Kemungkinan:\n";
    echo "   1. Semua approval flow sudah di-approve atau di-reject\n";
    echo "   2. Approval flow tidak dibuat dengan benar\n";
    echo "   3. Status approval flow bukan 'PENDING'\n";
}

echo "\n";

