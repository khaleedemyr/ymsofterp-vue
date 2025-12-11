<?php

/**
 * Script untuk memperbaiki data approval yang sudah disetujui supervisor
 * tapi tidak muncul di HRD karena hrd_status belum diupdate
 * 
 * Cara menjalankan:
 * php fix_missing_hrd_approvals.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "FIX MISSING HRD APPROVALS\n";
echo "========================================\n\n";

try {
    DB::beginTransaction();
    
    // Step 1: Get HRD approver
    $hrdApprover = DB::table('users')
        ->where('division_id', 6)
        ->where('status', 'A')
        ->first();
    
    if (!$hrdApprover) {
        echo "ERROR: Tidak ada user HRD yang aktif ditemukan!\n";
        echo "Pastikan ada user dengan division_id = 6 dan status = 'A'\n";
        exit(1);
    }
    
    echo "HRD Approver: {$hrdApprover->nama_lengkap} (ID: {$hrdApprover->id})\n\n";
    
    // Step 2: Fix new flow (ada absent_request_approval_flows)
    echo "Step 1: Memperbaiki new flow approvals...\n";
    
    $newFlowUpdated = DB::table('approval_requests as ar')
        ->join('absent_requests as abr', 'ar.id', '=', 'abr.approval_request_id')
        ->leftJoin(DB::raw('(
            SELECT DISTINCT absent_request_id
            FROM absent_request_approval_flows
            WHERE status = "PENDING"
        ) as pending_flows'), 'abr.id', '=', 'pending_flows.absent_request_id')
        ->where('ar.status', 'approved')
        ->where(function($query) {
            $query->whereNull('ar.hrd_status')
                  ->orWhere('ar.hrd_status', '!=', 'pending');
        })
        ->whereNull('ar.hrd_approver_id')
        ->whereNull('pending_flows.absent_request_id')
        ->where('abr.status', 'supervisor_approved')
        ->update([
            'ar.hrd_status' => 'pending',
            'ar.hrd_approver_id' => $hrdApprover->id,
            'ar.updated_at' => now()
        ]);
    
    echo "   ✓ {$newFlowUpdated} approval (new flow) diperbaiki\n\n";
    
    // Step 3: Fix old flow (tidak ada absent_request_approval_flows)
    echo "Step 2: Memperbaiki old flow approvals...\n";
    
    $oldFlowUpdated = DB::table('approval_requests as ar')
        ->leftJoin('absent_requests as abr', 'ar.id', '=', 'abr.approval_request_id')
        ->leftJoin('absent_request_approval_flows as arf', 'abr.id', '=', 'arf.absent_request_id')
        ->where('ar.status', 'approved')
        ->where(function($query) {
            $query->whereNull('ar.hrd_status')
                  ->orWhere('ar.hrd_status', '!=', 'pending');
        })
        ->whereNull('ar.hrd_approver_id')
        ->whereNull('arf.id')
        ->update([
            'ar.hrd_status' => 'pending',
            'ar.hrd_approver_id' => $hrdApprover->id,
            'ar.updated_at' => now()
        ]);
    
    echo "   ✓ {$oldFlowUpdated} approval (old flow) diperbaiki\n\n";
    
    // Step 4: Update absent_requests status
    echo "Step 3: Memperbarui status absent_requests...\n";
    
    $absentUpdated = DB::table('absent_requests as abr')
        ->join('approval_requests as ar', 'abr.approval_request_id', '=', 'ar.id')
        ->where('abr.status', '!=', 'approved')
        ->where('abr.status', '!=', 'rejected')
        ->where('ar.status', 'approved')
        ->where('ar.hrd_status', 'pending')
        ->update([
            'abr.status' => 'supervisor_approved',
            'abr.updated_at' => now()
        ]);
    
    echo "   ✓ {$absentUpdated} absent_request diperbarui\n\n";
    
    // Step 4b: Fix absent_requests yang status supervisor_approved tapi approved_by NULL (new flow)
    echo "Step 3b: Memperbaiki approved_by yang NULL untuk new flow...\n";
    
    // Get absent_requests yang perlu diperbaiki dengan mengambil approver terakhir (highest level)
    $absentRequestsToFix = DB::select("
        SELECT 
            abr.id,
            last_approver.approved_by,
            last_approver.approved_at
        FROM absent_requests abr
        INNER JOIN (
            SELECT 
                absent_request_id,
                approved_by,
                approved_at,
                approval_level
            FROM absent_request_approval_flows
            WHERE status = 'APPROVED'
            AND (absent_request_id, approval_level) IN (
                SELECT absent_request_id, MAX(approval_level)
                FROM absent_request_approval_flows
                WHERE status = 'APPROVED'
                GROUP BY absent_request_id
            )
        ) last_approver ON abr.id = last_approver.absent_request_id
        WHERE abr.status = 'supervisor_approved'
        AND abr.approved_by IS NULL
    ");
    
    $newFlowFixed = 0;
    foreach ($absentRequestsToFix as $item) {
        DB::table('absent_requests')
            ->where('id', $item->id)
            ->whereNull('approved_by')
            ->update([
                'approved_by' => $item->approved_by,
                'approved_at' => $item->approved_at,
                'updated_at' => now()
            ]);
        $newFlowFixed++;
    }
    
    echo "   ✓ {$newFlowFixed} absent_request (new flow) diperbaiki\n\n";
    
    // Step 4c: Fix absent_requests untuk old flow (tidak ada approval_flows)
    echo "Step 3c: Memperbaiki approved_by yang NULL untuk old flow...\n";
    
    $oldFlowFixed = DB::table('absent_requests as abr')
        ->join('approval_requests as ar', 'abr.approval_request_id', '=', 'ar.id')
        ->leftJoin('absent_request_approval_flows as arf', 'abr.id', '=', 'arf.absent_request_id')
        ->where('abr.status', 'supervisor_approved')
        ->whereNull('abr.approved_by')
        ->whereNull('arf.id')
        ->whereNotNull('ar.approver_id')
        ->update([
            'abr.approved_by' => DB::raw('ar.approver_id'),
            'abr.approved_at' => DB::raw('ar.approved_at'),
            'abr.updated_at' => now()
        ]);
    
    echo "   ✓ {$oldFlowFixed} absent_request (old flow) diperbaiki\n\n";
    
    // Step 5: Create notifications for HRD (only for recently updated)
    echo "Step 4: Membuat notifikasi untuk HRD...\n";
    
    $recentApprovals = DB::table('approval_requests as ar')
        ->join('users as req_user', 'ar.user_id', '=', 'req_user.id')
        ->where('ar.status', 'approved')
        ->where('ar.hrd_status', 'pending')
        ->where('ar.updated_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 1 HOUR)'))
        ->select([
            'ar.id as approval_id',
            'ar.user_id',
            'req_user.nama_lengkap as user_name',
            'ar.date_from',
            'ar.date_to'
        ])
        ->get();
    
    $hrdUsers = DB::table('users')
        ->where('division_id', 6)
        ->where('status', 'A')
        ->pluck('id');
    
    $notificationsCreated = 0;
    $appUrl = config('app.url', 'http://localhost');
    
    foreach ($recentApprovals as $approval) {
        foreach ($hrdUsers as $hrdUserId) {
            // Check if notification already exists
            $exists = DB::table('notifications')
                ->where('user_id', $hrdUserId)
                ->where('type', 'leave_hrd_approval_request')
                ->where('approval_id', $approval->approval_id)
                ->where('is_read', 0)
                ->exists();
            
            if (!$exists) {
                DB::table('notifications')->insert([
                    'user_id' => $hrdUserId,
                    'type' => 'leave_hrd_approval_request',
                    'message' => "Permohonan izin/cuti dari {$approval->user_name} untuk periode {$approval->date_from} - {$approval->date_to} telah disetujui oleh semua atasan dan membutuhkan persetujuan HRD Anda.",
                    'url' => $appUrl . '/home',
                    'is_read' => 0,
                    'approval_id' => $approval->approval_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $notificationsCreated++;
            }
        }
    }
    
    echo "   ✓ {$notificationsCreated} notifikasi dibuat\n\n";
    
    DB::commit();
    
    // Summary
    echo "========================================\n";
    echo "SUMMARY\n";
    echo "========================================\n";
    echo "New flow approvals fixed: {$newFlowUpdated}\n";
    echo "Old flow approvals fixed: {$oldFlowUpdated}\n";
    echo "Absent requests updated: {$absentUpdated}\n";
    echo "Absent requests approved_by fixed (new flow): {$newFlowFixed}\n";
    echo "Absent requests approved_by fixed (old flow): {$oldFlowFixed}\n";
    echo "Notifications created: {$notificationsCreated}\n";
    echo "Total approvals fixed: " . ($newFlowUpdated + $oldFlowUpdated) . "\n";
    echo "Total absent_requests fixed: " . ($newFlowFixed + $oldFlowFixed) . "\n\n";
    
    // Verification
    $totalPendingHrd = DB::table('approval_requests')
        ->where('status', 'approved')
        ->where('hrd_status', 'pending')
        ->count();
    
    echo "Total pending HRD approvals: {$totalPendingHrd}\n";
    echo "========================================\n";
    echo "SUCCESS: Data berhasil diperbaiki!\n";
    echo "========================================\n";
    
} catch (\Exception $e) {
    DB::rollback();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

