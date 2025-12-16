<?php

/**
 * Script untuk cek apakah approval 563 terpotong karena limit
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$approvalId = 563;
$limit = 10; // Default limit

echo "========================================\n";
echo "CEK LIMIT ISSUE UNTUK APPROVAL 563\n";
echo "========================================\n\n";

// Simulate exact query from getPendingApprovals
$isSuperadmin = true;
$userId = 109;

// Get pending approvals from approval flows (new flow)
$approvalFlowsQuery = DB::table('absent_request_approval_flows as arf')
    ->join('absent_requests as ar', 'arf.absent_request_id', '=', 'ar.id')
    ->join('approval_requests as apr', 'ar.approval_request_id', '=', 'apr.id')
    ->join('users', 'apr.user_id', '=', 'users.id')
    ->join('leave_types', 'apr.leave_type_id', '=', 'leave_types.id')
    ->where('arf.status', 'PENDING');

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

// Filter: Only show if current user is the next approver in line (skip for superadmin)
$filteredApprovals = $approvalFlows->filter(function($approval) use ($isSuperadmin) {
    if ($isSuperadmin) {
        return true; // Superadmin can see all
    }
    
    $pendingFlows = DB::table('absent_request_approval_flows')
        ->where('absent_request_id', $approval->absent_request_id)
        ->where('absent_request_approval_flows.status', 'PENDING')
        ->orderBy('approval_level')
        ->get();
    
    if ($pendingFlows->isEmpty()) return false;
    $nextApprover = $pendingFlows->first();
    return $nextApprover->approver_id == auth()->id();
});

// Also get old flow approvals
$oldApprovalsQuery = DB::table('approval_requests')
    ->join('users', 'approval_requests.user_id', '=', 'users.id')
    ->join('leave_types', 'approval_requests.leave_type_id', '=', 'leave_types.id')
    ->leftJoin('absent_requests', 'approval_requests.id', '=', 'absent_requests.approval_request_id')
    ->leftJoin('absent_request_approval_flows', function($join) use ($userId) {
        $join->on('absent_requests.id', '=', 'absent_request_approval_flows.absent_request_id')
             ->where('absent_request_approval_flows.approver_id', '=', $userId);
    })
    ->where('approval_requests.status', 'pending')
    ->whereNull('absent_request_approval_flows.id');

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

// Combine and format (EXACT COPY FROM CONTROLLER)
$allApprovals = $filteredApprovals->merge($oldApprovals)->take($limit);

echo "Total filtered approvals (new flow): " . $filteredApprovals->count() . "\n";
echo "Total old flow approvals: " . $oldApprovals->count() . "\n";
echo "Total setelah merge: " . ($filteredApprovals->count() + $oldApprovals->count()) . "\n";
echo "Limit: {$limit}\n";
echo "Total setelah take({$limit}): " . $allApprovals->count() . "\n\n";

// Check position of approval 563
$position = 0;
$found = false;
$foundPosition = 0;

echo "Daftar approval setelah merge dan take({$limit}):\n";
foreach ($allApprovals as $index => $approval) {
    $position++;
    $marker = '';
    if ($approval->id == $approvalId) {
        $found = true;
        $foundPosition = $position;
        $marker = " <-- APPROVAL 563";
    }
    echo "  #{$position}: ID {$approval->id} - " . ($approval->user_name ?? 'N/A') . " ({$approval->date_from}){$marker}\n";
}

echo "\n";

if ($found) {
    echo "✓ Approval 563 DITEMUKAN di posisi #{$foundPosition}\n";
    if ($foundPosition > $limit) {
        echo "⚠️  TAPI posisi #{$foundPosition} melebihi limit {$limit}, jadi TIDAK AKAN MUNCUL!\n";
    } else {
        echo "✓ Posisi #{$foundPosition} masih dalam limit {$limit}, seharusnya MUNCUL\n";
    }
} else {
    echo "✗ Approval 563 TIDAK DITEMUKAN dalam {$limit} pertama\n";
    echo "   Ini berarti approval 563 terpotong karena limit!\n";
}

// Check if approval 563 is in filteredApprovals but not in first $limit
$inFiltered = false;
$positionInFiltered = 0;
foreach ($filteredApprovals as $index => $fa) {
    $positionInFiltered++;
    if ($fa->id == $approvalId) {
        $inFiltered = true;
        break;
    }
}

if ($inFiltered) {
    echo "\nApproval 563 ada di filtered approvals di posisi: #{$positionInFiltered}\n";
    echo "Total filtered approvals: " . $filteredApprovals->count() . "\n";
    
    if ($positionInFiltered > $limit) {
        echo "⚠️  MASALAH: Posisi #{$positionInFiltered} melebihi limit {$limit}\n";
        echo "   Solusi: Perlu request dengan limit yang lebih besar atau pagination\n";
    }
}

echo "\n";

