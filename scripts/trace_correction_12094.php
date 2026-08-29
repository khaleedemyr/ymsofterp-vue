<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\AttendancePayrollPeriod;
use App\Support\HrdApprovalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$correctionId = (int) ($argv[1] ?? 12094);

echo "=== TRACE CORRECTION #{$correctionId} ===\n\n";

$approval = DB::table('schedule_attendance_correction_approvals as saca')
    ->leftJoin('users as requester', 'saca.requested_by', '=', 'requester.id')
    ->leftJoin('users as employee', 'saca.user_id', '=', 'employee.id')
    ->leftJoin('tbl_data_outlet', 'saca.outlet_id', '=', 'tbl_data_outlet.id_outlet')
    ->where('saca.id', $correctionId)
    ->select([
        'saca.*',
        'requester.nama_lengkap as requested_by_name',
        'employee.nama_lengkap as employee_name',
        'tbl_data_outlet.nama_outlet',
    ])
    ->first();

if (!$approval) {
    echo "Correction tidak ditemukan.\n";
    exit(1);
}

echo "Type: {$approval->type}\n";
echo "Status: {$approval->status}\n";
echo "Tanggal: {$approval->tanggal}\n";
echo "Employee: {$approval->employee_name} (user_id={$approval->user_id})\n";
echo "Requested by: {$approval->requested_by_name} (requested_by={$approval->requested_by})\n";
echo "Outlet: {$approval->nama_outlet}\n";
echo "Old -> New: {$approval->old_value} -> {$approval->new_value}\n";
echo "Reason: {$approval->reason}\n";
echo "Created: {$approval->created_at}\n";
if (property_exists($approval, 'source')) {
    echo "Source: " . ($approval->source ?? 'null') . "\n";
}

$period = AttendancePayrollPeriod::forHrdApprovalQueue();
echo "\nPeriod HRD queue: {$period['start']} s/d {$period['end']}\n";
$inPeriod = $approval->tanggal >= $period['start'] && $approval->tanggal <= $period['end'];
echo "In current period: " . ($inPeriod ? 'YES' : 'NO') . "\n";

echo "\n=== APPROVAL FLOWS ===\n";
if (!Schema::hasTable('schedule_attendance_correction_approval_flows')) {
    echo "Table schedule_attendance_correction_approval_flows TIDAK ADA\n";
} else {
    $flows = DB::table('schedule_attendance_correction_approval_flows as f')
        ->leftJoin('users as u', 'f.approver_id', '=', 'u.id')
        ->where('f.approval_id', $correctionId)
        ->orderBy('f.approval_level')
        ->select(['f.*', 'u.nama_lengkap as approver_name'])
        ->get();

    if ($flows->isEmpty()) {
        echo "Tidak ada supervisor flow (langsung HRD)\n";
    } else {
        foreach ($flows as $flow) {
            echo sprintf(
                "  L%d id=%s approver=%s (id=%s) status=%s approved_by=%s approved_at=%s\n",
                $flow->approval_level,
                $flow->id,
                $flow->approver_name,
                $flow->approver_id,
                $flow->status,
                $flow->approved_by ?? 'null',
                $flow->approved_at ?? 'null'
            );
        }
        $current = $flows->firstWhere('status', 'PENDING');
        echo "\nCurrent PENDING flow: " . ($current ? "L{$current->approval_level} approver_id={$current->approver_id}" : 'NONE') . "\n";
    }
}

echo "\n=== USERS: Rizal ===\n";
$rizals = DB::table('users')
    ->where('nama_lengkap', 'like', '%Rizal%')
    ->where('status', 'A')
    ->select('id', 'nama_lengkap', 'id_jabatan', 'id_role', 'id_outlet')
    ->orderBy('nama_lengkap')
    ->get();
foreach ($rizals as $u) {
    $canHrd = HrdApprovalAccess::canAccessHrdApprovals(
        \App\Models\User::find($u->id)
    ) ? 'YES' : 'NO';
    echo "  id={$u->id} | {$u->nama_lengkap} | jabatan={$u->id_jabatan} | HRD access={$canHrd}\n";
}

echo "\n=== SIMULASI approveCorrection untuk setiap Rizal ===\n";
foreach ($rizals as $u) {
    $user = \App\Models\User::find($u->id);
    $currentFlow = null;
    if (Schema::hasTable('schedule_attendance_correction_approval_flows')) {
        $currentFlow = DB::table('schedule_attendance_correction_approval_flows')
            ->where('approval_id', $correctionId)
            ->orderBy('approval_level')
            ->get()
            ->firstWhere('status', 'PENDING');
    }

    $canHrd = HrdApprovalAccess::canAccessHrdApprovals($user);
    $isCurrentApprover = $currentFlow && (int) $currentFlow->approver_id === (int) $user->id;

    $path = 'UNKNOWN';
    if ($currentFlow && $approval->status === 'pending') {
        $path = $isCurrentApprover || HrdApprovalAccess::isSuperadmin($user)
            ? 'SUPERVISOR_OK'
            : 'SUPERVISOR_DENIED (bukan current approver)';
    } elseif ($canHrd) {
        $path = $approval->status === 'supervisor_approved' || !$currentFlow
            ? 'HRD_OK'
            : 'HRD_DENIED (masih menunggu atasan)';
    } else {
        $path = 'UNAUTHORIZED (Unauthorized access)';
    }

    echo "  {$u->nama_lengkap} (id={$u->id}): {$path}\n";
}
