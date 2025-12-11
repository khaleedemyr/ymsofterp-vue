<?php

/**
 * Script untuk memperbaiki approval_requests yang hrd_status sudah 'pending'
 * tapi status masih 'pending' (seharusnya 'approved')
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "FIX APPROVAL STATUS MISMATCH\n";
echo "========================================\n";
echo "Memperbaiki approval_requests yang:\n";
echo "- hrd_status = 'pending'\n";
echo "- hrd_approver_id sudah diisi\n";
echo "- Tapi status masih 'pending' (harus 'approved')\n\n";

try {
    DB::beginTransaction();
    
    // Find approval_requests yang perlu diperbaiki
    $approvalsToFix = DB::table('approval_requests as ar')
        ->leftJoin('absent_requests as abr', 'ar.id', '=', 'abr.approval_request_id')
        ->leftJoin(DB::raw('(
            SELECT DISTINCT absent_request_id
            FROM absent_request_approval_flows
            WHERE status = "PENDING"
        ) as pending_flows'), 'abr.id', '=', 'pending_flows.absent_request_id')
        ->where('ar.status', 'pending')
        ->where('ar.hrd_status', 'pending')
        ->whereNotNull('ar.hrd_approver_id')
        ->whereNull('pending_flows.absent_request_id') // Tidak ada pending flows
        ->select('ar.id', 'ar.user_id', 'ar.date_from', 'ar.date_to')
        ->get();
    
    echo "Ditemukan {$approvalsToFix->count()} approval yang perlu diperbaiki:\n\n";
    
    if ($approvalsToFix->isEmpty()) {
        echo "✓ Tidak ada data yang perlu diperbaiki.\n";
        DB::commit();
        exit(0);
    }
    
    $fixed = 0;
    foreach ($approvalsToFix as $approval) {
        // Double check: pastikan tidak ada pending flows
        $hasPendingFlows = DB::table('absent_requests as abr')
            ->join('absent_request_approval_flows as arf', 'abr.id', '=', 'arf.absent_request_id')
            ->where('abr.approval_request_id', $approval->id)
            ->where('arf.status', 'PENDING')
            ->exists();
        
        if ($hasPendingFlows) {
            echo "  ⚠ Approval ID {$approval->id}: Masih ada pending flows, skip\n";
            continue;
        }
        
        // Update status
        DB::table('approval_requests')
            ->where('id', $approval->id)
            ->update([
                'status' => 'approved',
                'updated_at' => now()
            ]);
        
        $fixed++;
        echo "  ✓ Approval ID {$approval->id}: Status diupdate menjadi 'approved'\n";
    }
    
    DB::commit();
    
    echo "\n========================================\n";
    echo "SUMMARY\n";
    echo "========================================\n";
    echo "Total approval diperbaiki: {$fixed}\n";
    echo "========================================\n";
    echo "SUCCESS: Data berhasil diperbaiki!\n";
    echo "========================================\n";
    
} catch (\Exception $e) {
    DB::rollback();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

