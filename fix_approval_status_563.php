<?php

/**
 * Script untuk fix status approval 563
 * Masalah: absent_requests.status = 'supervisor_approved' padahal masih ada Level 2 yang pending
 * 
 * Usage: php fix_approval_status_563.php [--dry-run]
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv);

if ($dryRun) {
    echo "=== DRY RUN MODE - Tidak akan mengubah data ===\n\n";
}

$approvalId = 563;

echo "========================================\n";
echo "FIX STATUS APPROVAL 563\n";
echo "========================================\n\n";

// Get approval request
$approvalRequest = DB::table('approval_requests')
    ->where('id', $approvalId)
    ->first();

if (!$approvalRequest) {
    echo "ERROR: Approval request tidak ditemukan!\n";
    exit(1);
}

echo "Approval Request ID: {$approvalRequest->id}\n";
echo "Current Status: {$approvalRequest->status}\n";
echo "HRD Status: " . ($approvalRequest->hrd_status ?? 'NULL') . "\n\n";

// Get absent request
$absentRequest = DB::table('absent_requests')
    ->where('approval_request_id', $approvalId)
    ->first();

if (!$absentRequest) {
    echo "ERROR: Absent request tidak ditemukan!\n";
    exit(1);
}

echo "Absent Request ID: {$absentRequest->id}\n";
echo "Current Status: {$absentRequest->status}\n\n";

// Get all approval flows
$allFlows = DB::table('absent_request_approval_flows')
    ->where('absent_request_id', $absentRequest->id)
    ->orderBy('approval_level')
    ->get();

echo "Approval Flows:\n";
$totalFlows = $allFlows->count();
$approvedFlows = 0;
$pendingFlows = 0;
$rejectedFlows = 0;

foreach ($allFlows as $flow) {
    $approver = DB::table('users')->where('id', $flow->approver_id)->first();
    echo "  Level {$flow->approval_level}: {$flow->status} - " . ($approver ? $approver->nama_lengkap : 'NULL') . "\n";
    
    if ($flow->status === 'APPROVED') {
        $approvedFlows++;
    } elseif ($flow->status === 'PENDING') {
        $pendingFlows++;
    } elseif ($flow->status === 'REJECTED') {
        $rejectedFlows++;
    }
}

echo "\nSummary:\n";
echo "  Total: {$totalFlows}\n";
echo "  Approved: {$approvedFlows}\n";
echo "  Pending: {$pendingFlows}\n";
echo "  Rejected: {$rejectedFlows}\n\n";

// Determine correct status
$correctAbsentStatus = null;
$correctApprovalStatus = null;
$correctHrdStatus = null;
$needsFix = false;

if ($rejectedFlows > 0) {
    echo "⚠️  Ada approval yang di-reject, status seharusnya 'rejected'\n";
    $correctAbsentStatus = 'rejected';
    $correctApprovalStatus = 'rejected';
    $needsFix = true;
} elseif ($pendingFlows > 0) {
    // Masih ada yang pending - status HARUS 'pending', BUKAN 'supervisor_approved'
    // Status 'supervisor_approved' hanya digunakan ketika SEMUA supervisor sudah approve
    echo "⚠️  Masih ada {$pendingFlows} approval yang pending\n";
    echo "  Status HARUS 'pending', bukan 'supervisor_approved'\n";
    echo "  Status 'supervisor_approved' hanya untuk ketika SEMUA supervisor sudah approve\n";
    
    if ($absentRequest->status !== 'pending') {
        $correctAbsentStatus = 'pending';
        $needsFix = true;
        echo "  → Perlu ubah absent_requests.status dari '{$absentRequest->status}' ke 'pending'\n";
    }
    
    if ($approvalRequest->status !== 'pending') {
        $correctApprovalStatus = 'pending';
        $needsFix = true;
        echo "  → Perlu ubah approval_requests.status dari '{$approvalRequest->status}' ke 'pending'\n";
    }
} else {
    // Semua sudah approve atau reject
    if ($approvedFlows === $totalFlows) {
        // Semua supervisor sudah approve, sekarang menunggu HRD
        echo "✓ Semua supervisor sudah approve, status seharusnya 'supervisor_approved' dan siap untuk HRD\n";
        
        if ($absentRequest->status !== 'supervisor_approved') {
            $correctAbsentStatus = 'supervisor_approved';
            $needsFix = true;
        }
        
        if ($approvalRequest->status !== 'approved') {
            $correctApprovalStatus = 'approved';
            $needsFix = true;
        }
        
        if (empty($approvalRequest->hrd_status) || $approvalRequest->hrd_status !== 'pending') {
            $correctHrdStatus = 'pending';
            $needsFix = true;
        }
        
        // Set HRD approver if not set
        if (!$approvalRequest->hrd_approver_id) {
            $hrdApprover = DB::table('users')
                ->where('division_id', 6)
                ->where('status', 'A')
                ->first();
            if ($hrdApprover) {
                $needsFix = true;
            }
        }
    }
}

if (!$needsFix) {
    echo "✓ Status sudah benar, tidak perlu perbaikan\n";
    exit(0);
}

echo "\nPerbaikan yang diperlukan:\n";
if ($correctAbsentStatus) {
    echo "  - absent_requests.status: '{$absentRequest->status}' -> '{$correctAbsentStatus}'\n";
}
if ($correctApprovalStatus) {
    echo "  - approval_requests.status: '{$approvalRequest->status}' -> '{$correctApprovalStatus}'\n";
}
if ($correctHrdStatus) {
    echo "  - approval_requests.hrd_status: '" . ($approvalRequest->hrd_status ?? 'NULL') . "' -> '{$correctHrdStatus}'\n";
}

if ($dryRun) {
    echo "\n[DRY RUN] Akan melakukan perbaikan di atas\n";
    exit(0);
}

// Perform fix
try {
    DB::beginTransaction();
    
    if ($correctAbsentStatus) {
        DB::table('absent_requests')
            ->where('id', $absentRequest->id)
            ->update([
                'status' => $correctAbsentStatus,
                'updated_at' => now()
            ]);
        echo "\n✓ absent_requests.status updated\n";
    }
    
    $approvalUpdates = [];
    if ($correctApprovalStatus) {
        $approvalUpdates['status'] = $correctApprovalStatus;
    }
    if ($correctHrdStatus) {
        $approvalUpdates['hrd_status'] = $correctHrdStatus;
    }
    if (!$approvalRequest->hrd_approver_id) {
        $hrdApprover = DB::table('users')
            ->where('division_id', 6)
            ->where('status', 'A')
            ->first();
        if ($hrdApprover) {
            $approvalUpdates['hrd_approver_id'] = $hrdApprover->id;
        }
    }
    
    if (!empty($approvalUpdates)) {
        $approvalUpdates['updated_at'] = now();
        DB::table('approval_requests')
            ->where('id', $approvalId)
            ->update($approvalUpdates);
        echo "✓ approval_requests updated\n";
    }
    
    DB::commit();
    echo "\n✓ Fix berhasil!\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

