<?php

/**
 * Script untuk fix approval izin/cuti yang tidak muncul di HRD approval
 * 
 * Usage: php fix_leave_approval_status.php [--dry-run] [--approval-id=ID]
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv);
$approvalId = null;

// Parse arguments
foreach ($argv as $arg) {
    if (strpos($arg, '--approval-id=') === 0) {
        $approvalId = str_replace('--approval-id=', '', $arg);
    }
}

if ($dryRun) {
    echo "=== DRY RUN MODE - Tidak akan mengubah data ===\n\n";
}

echo "========================================\n";
echo "FIX APPROVAL IZIN/CUTI STATUS\n";
echo "========================================\n\n";

// Find problematic approval requests
$query = DB::table('approval_requests as ar')
    ->join('absent_requests as abr', 'ar.id', '=', 'abr.approval_request_id')
    ->leftJoin('absent_request_approval_flows as arf', 'abr.id', '=', 'arf.absent_request_id')
    ->where(function($q) {
        // Case 1: absent_requests.status = 'supervisor_approved' but approval_requests.status != 'approved'
        $q->where(function($subQ) {
            $subQ->where('abr.status', 'supervisor_approved')
                 ->where('ar.status', '!=', 'approved');
        })
        // Case 2: approval_requests.status = 'approved' but hrd_status is NULL or empty
        ->orWhere(function($subQ) {
            $subQ->where('ar.status', 'approved')
                 ->where(function($subQ2) {
                     $subQ2->whereNull('ar.hrd_status')
                           ->orWhere('ar.hrd_status', '');
                 });
        });
    });

if ($approvalId) {
    $query->where('ar.id', $approvalId);
}

$problematicApprovals = $query
    ->select([
        'ar.id as approval_request_id',
        'ar.status as approval_status',
        'ar.hrd_status',
        'ar.hrd_approver_id',
        'abr.id as absent_request_id',
        'abr.status as absent_status',
        'abr.approved_by',
        'abr.approved_at'
    ])
    ->groupBy('ar.id', 'ar.status', 'ar.hrd_status', 'ar.hrd_approver_id', 
              'abr.id', 'abr.status', 'abr.approved_by', 'abr.approved_at')
    ->get();

echo "Ditemukan " . $problematicApprovals->count() . " approval request yang bermasalah\n\n";

$fixed = 0;
$skipped = 0;

foreach ($problematicApprovals as $approval) {
    echo "--- Approval Request ID: {$approval->approval_request_id} ---\n";
    echo "   Current Status:\n";
    echo "   - approval_requests.status: {$approval->approval_status}\n";
    echo "   - approval_requests.hrd_status: " . ($approval->hrd_status ?? 'NULL') . "\n";
    echo "   - absent_requests.status: {$approval->absent_status}\n";
    
    // Check approval flows
    $approvalFlows = DB::table('absent_request_approval_flows')
        ->where('absent_request_id', $approval->absent_request_id)
        ->orderBy('approval_level')
        ->get();
    
    $totalFlows = $approvalFlows->count();
    $approvedFlows = $approvalFlows->where('status', 'APPROVED')->count();
    $pendingFlows = $approvalFlows->where('status', 'PENDING')->count();
    $rejectedFlows = $approvalFlows->where('status', 'REJECTED')->count();
    
    echo "   Approval Flows:\n";
    echo "   - Total: {$totalFlows}\n";
    echo "   - Approved: {$approvedFlows}\n";
    echo "   - Pending: {$pendingFlows}\n";
    echo "   - Rejected: {$rejectedFlows}\n";
    
    // Determine what needs to be fixed
    $needsFix = false;
    $fixAction = [];
    
    if ($rejectedFlows > 0) {
        echo "   ⚠️  Ada approval yang di-reject, skip\n\n";
        $skipped++;
        continue;
    }
    
    // Case 1: All flows approved but approval_requests.status is not 'approved'
    if ($totalFlows > 0 && $approvedFlows === $totalFlows && $pendingFlows === 0) {
        if ($approval->approval_status !== 'approved') {
            $needsFix = true;
            $fixAction[] = "Update approval_requests.status to 'approved'";
        }
        
        if (empty($approval->hrd_status) || $approval->hrd_status === '') {
            $needsFix = true;
            $fixAction[] = "Set approval_requests.hrd_status to 'pending'";
        }
        
        if (!$approval->hrd_approver_id) {
            $needsFix = true;
            $fixAction[] = "Set approval_requests.hrd_approver_id";
        }
    }
    
    // Case 2: absent_requests.status = 'supervisor_approved' but approval_requests.status = 'pending'
    // and all flows are approved
    if ($approval->absent_status === 'supervisor_approved' && 
        $approval->approval_status === 'pending' &&
        $totalFlows > 0 && $approvedFlows === $totalFlows) {
        $needsFix = true;
        $fixAction[] = "Update approval_requests.status from 'pending' to 'approved'";
        $fixAction[] = "Set approval_requests.hrd_status to 'pending'";
    }
    
    if (!$needsFix) {
        echo "   ✓ Status sudah benar atau masih dalam proses approval\n\n";
        $skipped++;
        continue;
    }
    
    echo "   Aksi perbaikan:\n";
    foreach ($fixAction as $action) {
        echo "   - {$action}\n";
    }
    
    if ($dryRun) {
        echo "   [DRY RUN] Akan melakukan perbaikan di atas\n\n";
        $fixed++;
        continue;
    }
    
    // Perform fix
    try {
        DB::beginTransaction();
        
        // Get HRD approver if not set
        $hrdApprover = null;
        if (!$approval->hrd_approver_id) {
            $hrdApprover = DB::table('users')
                ->where('division_id', 6)
                ->where('status', 'A')
                ->first();
        }
        
        $updates = [];
        
        // Update approval_requests
        if (in_array("Update approval_requests.status to 'approved'", $fixAction) ||
            in_array("Update approval_requests.status from 'pending' to 'approved'", $fixAction)) {
            $updates['status'] = 'approved';
            $updates['approved_at'] = $approval->approved_at ?? now();
        }
        
        if (in_array("Set approval_requests.hrd_status to 'pending'", $fixAction)) {
            $updates['hrd_status'] = 'pending';
        }
        
        if ($hrdApprover && in_array("Set approval_requests.hrd_approver_id", $fixAction)) {
            $updates['hrd_approver_id'] = $hrdApprover->id;
        }
        
        if (!empty($updates)) {
            $updates['updated_at'] = now();
            DB::table('approval_requests')
                ->where('id', $approval->approval_request_id)
                ->update($updates);
            echo "   ✓ approval_requests updated\n";
        }
        
        DB::commit();
        echo "   ✓ Fix berhasil!\n\n";
        $fixed++;
        
    } catch (\Exception $e) {
        DB::rollBack();
        echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Total problematic: " . $problematicApprovals->count() . "\n";
echo "Fixed: {$fixed}\n";
echo "Skipped: {$skipped}\n";

if ($dryRun) {
    echo "\n⚠️  DRY RUN MODE - Tidak ada data yang diubah\n";
    echo "Jalankan tanpa --dry-run untuk melakukan perbaikan\n";
}

echo "\n";

