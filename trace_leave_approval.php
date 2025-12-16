<?php

/**
 * Script untuk trace approval izin/cuti
 * Nama: MOCHAMAD ILYAS GIA SEPTIAN
 * 
 * Usage: php trace_leave_approval.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$searchName = 'MOCHAMAD ILYAS GIA SEPTIAN';

echo "========================================\n";
echo "TRACE APPROVAL IZIN/CUTI\n";
echo "Nama: {$searchName}\n";
echo "========================================\n\n";

// 1. Cari user berdasarkan nama
echo "1. MENCARI USER...\n";
$users = DB::table('users')
    ->where('nama_lengkap', 'like', "%{$searchName}%")
    ->orWhere('nama_lengkap', 'like', "%ILYAS%")
    ->orWhere('nama_lengkap', 'like', "%GIA%")
    ->orWhere('nama_lengkap', 'like', "%SEPTIAN%")
    ->get();

if ($users->isEmpty()) {
    echo "ERROR: User tidak ditemukan!\n";
    echo "Mencari semua user dengan nama mengandung 'ILYAS'...\n";
    $allIlyas = DB::table('users')
        ->where('nama_lengkap', 'like', "%ILYAS%")
        ->get();
    foreach ($allIlyas as $u) {
        echo "   - {$u->nama_lengkap} (ID: {$u->id})\n";
    }
    exit(1);
}

// Find exact match first
$user = null;
foreach ($users as $u) {
    if (stripos($u->nama_lengkap, $searchName) !== false || 
        stripos($u->nama_lengkap, 'MOCHAMAD ILYAS GIA SEPTIAN') !== false) {
        $user = $u;
        break;
    }
}

// If no exact match, use first one
if (!$user) {
    $user = $users->first();
}

if ($users->count() > 1) {
    echo "   Ditemukan beberapa user:\n";
    foreach ($users as $u) {
        $marker = ($u->id === $user->id) ? " <-- DIPILIH" : "";
        echo "   - {$u->nama_lengkap} (ID: {$u->id}){$marker}\n";
    }
    echo "\n";
}

if (!$user) {
    echo "ERROR: User tidak ditemukan!\n";
    exit(1);
}

echo "   User ditemukan:\n";
echo "   - ID: {$user->id}\n";
echo "   - Nama: {$user->nama_lengkap}\n";
echo "   - Email: {$user->email}\n";
echo "   - Status: {$user->status}\n";
$divisionId = isset($user->division_id) ? $user->division_id : 'NULL';
$jabatanId = isset($user->id_jabatan) ? $user->id_jabatan : 'NULL';
echo "   - Division ID: {$divisionId}\n";
echo "   - Jabatan ID: {$jabatanId}\n\n";

// 2. Cari approval_requests untuk user ini
echo "2. MENCARI APPROVAL REQUESTS...\n";
$approvalRequests = DB::table('approval_requests')
    ->where('user_id', $user->id)
    ->orderBy('created_at', 'desc')
    ->get();

echo "   Total approval requests: " . $approvalRequests->count() . "\n\n";

if ($approvalRequests->isEmpty()) {
    echo "   TIDAK ADA APPROVAL REQUEST!\n\n";
} else {
    foreach ($approvalRequests as $index => $ar) {
        echo "   [" . ($index + 1) . "] Approval Request ID: {$ar->id}\n";
        echo "       - Date From: {$ar->date_from}\n";
        echo "       - Date To: {$ar->date_to}\n";
        echo "       - Status: {$ar->status}\n";
        $hrdStatus = isset($ar->hrd_status) ? $ar->hrd_status : 'NULL';
        $approverId = isset($ar->approver_id) ? $ar->approver_id : 'NULL';
        $hrdApproverId = isset($ar->hrd_approver_id) ? $ar->hrd_approver_id : 'NULL';
        $approvedAt = isset($ar->approved_at) ? $ar->approved_at : 'NULL';
        $hrdApprovedAt = isset($ar->hrd_approved_at) ? $ar->hrd_approved_at : 'NULL';
        echo "       - HRD Status: {$hrdStatus}\n";
        echo "       - Approver ID: {$approverId}\n";
        echo "       - HRD Approver ID: {$hrdApproverId}\n";
        echo "       - Approved At: {$approvedAt}\n";
        echo "       - HRD Approved At: {$hrdApprovedAt}\n";
        echo "       - Created At: {$ar->created_at}\n";
        
        // Get leave type
        $leaveType = DB::table('leave_types')->where('id', $ar->leave_type_id)->first();
        echo "       - Leave Type: " . ($leaveType ? $leaveType->name : 'NULL') . "\n";
        
        // Get approver name if exists
        if ($ar->approver_id) {
            $approver = DB::table('users')->where('id', $ar->approver_id)->first();
            echo "       - Approver: " . ($approver ? $approver->nama_lengkap : 'NULL') . "\n";
        }
        
        // Get HRD approver name if exists
        if ($ar->hrd_approver_id) {
            $hrdApprover = DB::table('users')->where('id', $ar->hrd_approver_id)->first();
            echo "       - HRD Approver: " . ($hrdApprover ? $hrdApprover->nama_lengkap : 'NULL') . "\n";
        }
        
        echo "\n";
        
        // 3. Cari absent_request yang terkait
        echo "   3. MENCARI ABSENT REQUEST...\n";
        $absentRequest = DB::table('absent_requests')
            ->where('approval_request_id', $ar->id)
            ->first();
        
        if ($absentRequest) {
            echo "       Absent Request ditemukan:\n";
            echo "       - ID: {$absentRequest->id}\n";
            echo "       - Status: {$absentRequest->status}\n";
            $approvedBy = isset($absentRequest->approved_by) ? $absentRequest->approved_by : 'NULL';
            $approvedAtAr = isset($absentRequest->approved_at) ? $absentRequest->approved_at : 'NULL';
            $hrdApprovedBy = isset($absentRequest->hrd_approved_by) ? $absentRequest->hrd_approved_by : 'NULL';
            $hrdApprovedAtAr = isset($absentRequest->hrd_approved_at) ? $absentRequest->hrd_approved_at : 'NULL';
            echo "       - Approved By: {$approvedBy}\n";
            echo "       - Approved At: {$approvedAtAr}\n";
            echo "       - HRD Approved By: {$hrdApprovedBy}\n";
            echo "       - HRD Approved At: {$hrdApprovedAtAr}\n";
            echo "       - Created At: {$absentRequest->created_at}\n";
            echo "       - Updated At: {$absentRequest->updated_at}\n";
            
            // 4. Cari approval flows
            echo "\n   4. MENCARI APPROVAL FLOWS...\n";
            $approvalFlows = DB::table('absent_request_approval_flows')
                ->where('absent_request_id', $absentRequest->id)
                ->orderBy('approval_level')
                ->get();
            
            echo "       Total approval flows: " . $approvalFlows->count() . "\n";
            
            if ($approvalFlows->isEmpty()) {
                echo "       TIDAK ADA APPROVAL FLOW (menggunakan old flow)\n";
            } else {
                foreach ($approvalFlows as $flow) {
                    $approver = DB::table('users')->where('id', $flow->approver_id)->first();
                    echo "       - Level {$flow->approval_level}: {$flow->status}\n";
                    $approverName = $approver ? $approver->nama_lengkap : 'NULL';
                    $flowApprovedBy = isset($flow->approved_by) ? $flow->approved_by : 'NULL';
                    $flowApprovedAt = isset($flow->approved_at) ? $flow->approved_at : 'NULL';
                    echo "         Approver: {$approverName} (ID: {$flow->approver_id})\n";
                    echo "         Approved By: {$flowApprovedBy}\n";
                    echo "         Approved At: {$flowApprovedAt}\n";
                    if ($flow->notes) {
                        echo "         Notes: {$flow->notes}\n";
                    }
                }
            }
            
            // 5. ANALISIS MASALAH
            echo "\n   5. ANALISIS STATUS...\n";
            
            $issues = [];
            
            // Check if supervisor approved but HRD status not set
            $hrdStatusCheck = isset($ar->hrd_status) ? $ar->hrd_status : null;
            if ($ar->status === 'approved' && ($hrdStatusCheck === null || $hrdStatusCheck === '')) {
                $issues[] = "⚠️  STATUS: approval_requests.status = 'approved' tapi hrd_status NULL/kosong";
            }
            
            // Check if supervisor approved but hrd_status not pending
            if ($ar->status === 'approved' && $hrdStatusCheck !== 'pending') {
                $issues[] = "⚠️  STATUS: approval_requests.status = 'approved' tapi hrd_status = '{$hrdStatusCheck}' (seharusnya 'pending')";
            }
            
            // Check if absent_request status is supervisor_approved but approval_requests status is not approved
            if ($absentRequest->status === 'supervisor_approved' && $ar->status !== 'approved') {
                $issues[] = "⚠️  STATUS: absent_requests.status = 'supervisor_approved' tapi approval_requests.status = '{$ar->status}' (seharusnya 'approved')";
            }
            
            // Check if all approval flows are approved but hrd_status not set
            if (!$approvalFlows->isEmpty()) {
                $allApproved = true;
                foreach ($approvalFlows as $flow) {
                    if ($flow->status !== 'APPROVED') {
                        $allApproved = false;
                        break;
                    }
                }
                
                if ($allApproved && $hrdStatusCheck !== 'pending') {
                    $issues[] = "⚠️  STATUS: Semua approval flows sudah APPROVED tapi hrd_status = '{$hrdStatusCheck}' (seharusnya 'pending')";
                }
            }
            
            // Check if hrd_approver_id is not set
            $hrdApproverIdCheck = isset($ar->hrd_approver_id) ? $ar->hrd_approver_id : null;
            if ($ar->status === 'approved' && !$hrdApproverIdCheck) {
                $issues[] = "⚠️  STATUS: approval_requests.status = 'approved' tapi hrd_approver_id NULL (seharusnya ada HRD user)";
            }
            
            if (empty($issues)) {
                echo "       ✓ Status terlihat normal\n";
            } else {
                echo "       MASALAH DITEMUKAN:\n";
                foreach ($issues as $issue) {
                    echo "       {$issue}\n";
                }
            }
            
            // 6. CEK APAKAH MUNCUL DI HRD APPROVAL
            echo "\n   6. CEK APAKAH MUNCUL DI HRD APPROVAL...\n";
            $hrdApprovalQuery = DB::table('approval_requests')
                ->where('id', $ar->id)
                ->where('status', 'approved')
                ->where('hrd_status', 'pending');
            
            $shouldAppearInHrd = $hrdApprovalQuery->exists();
            
            if ($shouldAppearInHrd) {
                echo "       ✓ SEHARUSNYA MUNCUL di HRD approval\n";
            } else {
                echo "       ✗ TIDAK MUNCUL di HRD approval\n";
                echo "       Query yang digunakan:\n";
                echo "       - status = 'approved': " . ($ar->status === 'approved' ? '✓' : '✗') . "\n";
                $hrdStatusPending = ($hrdStatusCheck === 'pending') ? '✓' : '✗';
                echo "       - hrd_status = 'pending': {$hrdStatusPending}\n";
            }
            
        } else {
            echo "       TIDAK ADA ABSENT REQUEST!\n";
        }
        
        echo "\n" . str_repeat("-", 80) . "\n\n";
    }
}

// 7. CEK HRD USERS
echo "7. CEK HRD USERS...\n";
$hrdUsers = DB::table('users')
    ->where('division_id', 6)
    ->where('status', 'A')
    ->get();

echo "   Total HRD users: " . $hrdUsers->count() . "\n";
foreach ($hrdUsers as $hrd) {
    echo "   - {$hrd->nama_lengkap} (ID: {$hrd->id})\n";
}
echo "\n";

// 8. SUMMARY
echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "User ID: {$user->id}\n";
echo "Total Approval Requests: " . $approvalRequests->count() . "\n";

$supervisorApproved = $approvalRequests->filter(function($ar) {
    return $ar->status === 'approved';
})->count();

$hrdPending = 0;
foreach ($approvalRequests as $ar) {
    $hrdStatusCheck = isset($ar->hrd_status) ? $ar->hrd_status : null;
    if ($ar->status === 'approved' && $hrdStatusCheck === 'pending') {
        $hrdPending++;
    }
}

echo "Supervisor Approved: {$supervisorApproved}\n";
echo "HRD Pending: {$hrdPending}\n";

if ($supervisorApproved > $hrdPending) {
    echo "\n⚠️  PERINGATAN: Ada " . ($supervisorApproved - $hrdPending) . " request yang sudah di-approve supervisor tapi tidak muncul di HRD approval!\n";
}

echo "\n";

