<?php

/**
 * Script untuk cek detail approval 563 dan posisinya di daftar
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "CEK DETAIL APPROVAL 563\n";
echo "========================================\n\n";

$approverId = 109; // Erkan
$approvalId = 563;

// Get all approvals that should appear for Erkan (as superadmin)
$approvalFlowsQuery = DB::table('absent_request_approval_flows as arf')
    ->join('absent_requests as ar', 'arf.absent_request_id', '=', 'ar.id')
    ->join('approval_requests as apr', 'ar.approval_request_id', '=', 'apr.id')
    ->join('users', 'apr.user_id', '=', 'users.id')
    ->join('leave_types', 'apr.leave_type_id', '=', 'leave_types.id')
    ->where('arf.status', 'PENDING');

// Superadmin sees all
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
        'arf.approval_level',
        'ar.id as absent_request_id'
    ])
    ->orderBy('apr.created_at', 'desc')
    ->get();

// Filter: Only show if current user is the next approver in line (but superadmin sees all)
$filteredApprovals = $approvalFlows->filter(function($approval) use ($approverId) {
    // Superadmin can see all
    // But we still check if they are next approver for debugging
    $pendingFlows = DB::table('absent_request_approval_flows')
        ->where('absent_request_id', $approval->absent_request_id)
        ->where('absent_request_approval_flows.status', 'PENDING')
        ->orderBy('approval_level')
        ->get();
    
    if ($pendingFlows->isEmpty()) return false;
    $nextApprover = $pendingFlows->first();
    return $nextApprover->approver_id == $approverId;
});

// Find position of approval 563
$position = 0;
$found = false;
foreach ($filteredApprovals as $index => $fa) {
    $position++;
    if ($fa->id == $approvalId) {
        $found = true;
        break;
    }
}

echo "Total approvals untuk Erkan (superadmin): " . $filteredApprovals->count() . "\n";
echo "Posisi approval 563: " . ($found ? "#{$position}" : "TIDAK DITEMUKAN") . "\n\n";

if ($found) {
    $approval563 = $filteredApprovals->firstWhere('id', $approvalId);
    echo "Detail Approval 563:\n";
    echo "- User: {$approval563->user_name}\n";
    echo "- Date: {$approval563->date_from} - {$approval563->date_to}\n";
    echo "- Leave Type: {$approval563->leave_type_name}\n";
    echo "- Created At: {$approval563->created_at}\n";
    echo "- Approval Level: {$approval563->approval_level}\n";
    echo "- Position in list: #{$position}\n\n";
    
    // Check if there are duplicates
    $duplicates = $filteredApprovals->where('id', $approvalId);
    if ($duplicates->count() > 1) {
        echo "⚠️  PERINGATAN: Approval 563 muncul " . $duplicates->count() . " kali di daftar!\n";
        echo "   Ini bisa menyebabkan masalah di frontend\n\n";
    }
    
    // Show surrounding approvals
    echo "Approvals di sekitar posisi #{$position}:\n";
    $start = max(0, $position - 3);
    $end = min($filteredApprovals->count(), $position + 3);
    $index = 0;
    foreach ($filteredApprovals as $fa) {
        $index++;
        if ($index >= $start && $index <= $end) {
            $marker = ($fa->id == $approvalId) ? " <-- INI" : "";
            echo "   #{$index}: ID {$fa->id} - {$fa->user_name} ({$fa->date_from}){$marker}\n";
        }
    }
    
    echo "\n";
    echo "Rekomendasi:\n";
    if ($position > 20) {
        echo "- Approval 563 ada di posisi #{$position}, mungkin perlu scroll atau pindah halaman\n";
    }
    if ($duplicates->count() > 1) {
        echo "- Ada duplikasi, perlu dicek kenapa approval ini muncul beberapa kali\n";
    }
} else {
    echo "✗ Approval 563 TIDAK ditemukan di filtered approvals\n";
    echo "   Tapi seharusnya muncul karena Erkan adalah superadmin\n";
    echo "   Cek apakah ada masalah dengan filter\n";
}

echo "\n";

