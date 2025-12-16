<?php

/**
 * Script untuk test query approval langsung seperti di API
 * 
 * Usage: php test_approval_query.php [--approver-id=ID]
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$approverId = null;

// Parse arguments
foreach ($argv as $arg) {
    if (strpos($arg, '--approver-id=') === 0) {
        $approverId = str_replace('--approver-id=', '', $arg);
    }
}

if (!$approverId) {
    $approverId = 109; // Erkan default
}

echo "========================================\n";
echo "TEST APPROVAL QUERY\n";
echo "========================================\n\n";

$approver = DB::table('users')->where('id', $approverId)->first();
if (!$approver) {
    echo "ERROR: Approver tidak ditemukan!\n";
    exit(1);
}

echo "Testing untuk: {$approver->nama_lengkap} (ID: {$approverId})\n";
echo "Role ID: " . ($approver->id_role ?? 'NULL') . "\n";
echo "Division ID: " . ($approver->division_id ?? 'NULL') . "\n\n";

// Simulate exact query from getPendingApprovals
$userId = $approverId;
$isSuperadmin = $approver->id_role === '5af56935b011a';

echo "1. QUERY APPROVAL FLOWS (NEW FLOW):\n";
$approvalFlowsQuery = DB::table('absent_request_approval_flows as arf')
    ->join('absent_requests as ar', 'arf.absent_request_id', '=', 'ar.id')
    ->join('approval_requests as apr', 'ar.approval_request_id', '=', 'apr.id')
    ->join('users', 'apr.user_id', '=', 'users.id')
    ->join('leave_types', 'apr.leave_type_id', '=', 'leave_types.id')
    ->where('arf.status', 'PENDING');

if (!$isSuperadmin) {
    $approvalFlowsQuery->where('arf.approver_id', $userId);
}

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

echo "   Total hasil query: " . $approvalFlows->count() . "\n";

if ($approvalFlows->count() > 0) {
    echo "   Approval requests ditemukan:\n";
    foreach ($approvalFlows as $af) {
        echo "   - ID: {$af->id}, User: {$af->user_name}, Level: {$af->approval_level}\n";
    }
} else {
    echo "   Tidak ada approval flows yang ditemukan\n";
}

echo "\n";

// Filter: Only show if current user is the next approver in line
echo "2. FILTER: NEXT APPROVER IN LINE:\n";
$filteredApprovals = $approvalFlows->filter(function($approval) use ($isSuperadmin, $userId) {
    if ($isSuperadmin) {
        return true;
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

if ($filteredApprovals->count() > 0) {
    echo "   Approval requests yang akan muncul:\n";
    foreach ($filteredApprovals as $fa) {
        echo "   - ID: {$fa->id}, User: {$fa->user_name}, Date: {$fa->date_from} - {$fa->date_to}\n";
        
        // Check specifically for approval 563
        if ($fa->id == 563) {
            echo "     ✓ Approval request 563 DITEMUKAN!\n";
        }
    }
} else {
    echo "   Tidak ada approval requests yang akan muncul\n";
    
    // Debug why approval 563 is not showing
    $approval563 = $approvalFlows->firstWhere('id', 563);
    if ($approval563) {
        echo "\n   DEBUG: Approval 563 ada di query tapi tidak muncul setelah filter\n";
        $pendingFlows = DB::table('absent_request_approval_flows')
            ->where('absent_request_id', $approval563->absent_request_id)
            ->where('absent_request_approval_flows.status', 'PENDING')
            ->orderBy('approval_level')
            ->get();
        
        echo "   Pending flows untuk absent_request_id {$approval563->absent_request_id}:\n";
        foreach ($pendingFlows as $pf) {
            $pfUser = DB::table('users')->where('id', $pf->approver_id)->first();
            echo "     - Level {$pf->approval_level}: " . ($pfUser ? $pfUser->nama_lengkap : 'NULL') . " (ID: {$pf->approver_id})\n";
        }
        
        if (!$pendingFlows->isEmpty()) {
            $nextApprover = $pendingFlows->first();
            if ($nextApprover->approver_id != $userId) {
                $nextApproverUser = DB::table('users')->where('id', $nextApprover->approver_id)->first();
                echo "   Next approver: " . ($nextApproverUser ? $nextApproverUser->nama_lengkap : 'NULL') . " (ID: {$nextApprover->approver_id})\n";
                echo "   Current user ID: {$userId}\n";
                echo "   ✗ User ID tidak match dengan next approver\n";
            }
        }
    } else {
        echo "\n   DEBUG: Approval 563 tidak ada di query sama sekali\n";
        echo "   Cek apakah ada approval flow dengan status PENDING untuk approver_id {$userId}\n";
        
        $userFlows = DB::table('absent_request_approval_flows')
            ->where('approver_id', $userId)
            ->where('status', 'PENDING')
            ->get();
        
        echo "   Total pending flows untuk user {$userId}: " . $userFlows->count() . "\n";
        foreach ($userFlows as $uf) {
            $ar = DB::table('absent_requests')->where('id', $uf->absent_request_id)->first();
            if ($ar) {
                $apr = DB::table('approval_requests')->where('id', $ar->approval_request_id)->first();
                if ($apr) {
                    $reqUser = DB::table('users')->where('id', $apr->user_id)->first();
                    echo "     - Approval ID: {$apr->id}, User: " . ($reqUser ? $reqUser->nama_lengkap : 'NULL') . ", Level: {$uf->approval_level}\n";
                }
            }
        }
    }
}

echo "\n";

// Also check old flow
echo "3. QUERY OLD FLOW (BACKWARD COMPATIBILITY):\n";
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

if (!$isSuperadmin) {
    $oldApprovalsQuery->where('approval_requests.approver_id', $userId);
}

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

echo "   Total old flow approvals: " . $oldApprovals->count() . "\n\n";

// Final result
echo "========================================\n";
echo "HASIL AKHIR\n";
echo "========================================\n";
$totalApprovals = $filteredApprovals->count() + $oldApprovals->count();
echo "Total approval requests yang akan muncul: {$totalApprovals}\n";

$has563 = $filteredApprovals->contains(function($fa) {
    return $fa->id == 563;
});

if ($has563) {
    echo "✓ Approval request 563 AKAN MUNCUL di daftar pending approval\n";
} else {
    echo "✗ Approval request 563 TIDAK AKAN MUNCUL di daftar pending approval\n";
    echo "\nKemungkinan penyebab:\n";
    echo "1. User ID yang login berbeda dengan yang diharapkan\n";
    echo "2. Ada masalah dengan autentikasi\n";
    echo "3. Ada filter tambahan di frontend\n";
    echo "4. Ada masalah dengan cache\n";
}

echo "\n";

