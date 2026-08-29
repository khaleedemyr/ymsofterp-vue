<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$userId = 1436;
$ts = '2025-10-17 10:27:44';

echo "=== DENDEN saldo input batch {$ts} ===\n";

$ph = DB::table('holiday_attendance_compensations')->where('id', 43)->first();
echo "PH #43 created={$ph->created_at} amt={$ph->compensation_amount} notes=" . ($ph->notes ?: '-') . "\n";

$eob = DB::table('extra_off_balance')->where('user_id', $userId)->first();
echo "extra_off_balance created={$eob->created_at} balance={$eob->balance}\n";

$eoTx = DB::table('extra_off_transactions')
    ->where('user_id', $userId)
    ->where('source_type', 'manual_adjustment')
    ->orderBy('created_at')
    ->get();

echo "\nExtra off manual_adjustment tx: {$eoTx->count()}\n";
foreach ($eoTx as $t) {
    $approver = $t->approved_by ? DB::table('users')->where('id', $t->approved_by)->value('nama_lengkap') : '-';
    echo "  #{$t->id} {$t->created_at} amt={$t->amount} approved_by={$t->approved_by} ({$approver}) desc={$t->description}\n";
}

echo "\n=== Semua record created ~{$ts} (±1 menit) ===\n";
$windowStart = '2025-10-17 10:26:00';
$windowEnd = '2025-10-17 10:29:00';

$phBatch = DB::table('holiday_attendance_compensations as h')
    ->join('users as u', 'u.id', '=', 'h.user_id')
    ->whereBetween('h.created_at', [$windowStart, $windowEnd])
    ->where('h.compensation_description', 'like', '%Manual adjustment%')
    ->select('h.*', 'u.nama_lengkap')
    ->orderBy('h.created_at')
    ->get();

echo "Manual PH in window: {$phBatch->count()}\n";
foreach ($phBatch as $r) {
    echo "  {$r->created_at} #{$r->id} {$r->nama_lengkap} amt={$r->compensation_amount}\n";
}

$eoBatch = DB::table('extra_off_balance as e')
    ->join('users as u', 'u.id', '=', 'e.user_id')
    ->whereBetween('e.created_at', [$windowStart, $windowEnd])
    ->select('e.*', 'u.nama_lengkap')
    ->get();

echo "\nextra_off_balance created in window: {$eoBatch->count()}\n";
foreach ($eoBatch as $r) {
    echo "  {$r->created_at} {$r->nama_lengkap} balance={$r->balance}\n";
}

$eoTxBatch = DB::table('extra_off_transactions as t')
    ->join('users as u', 'u.id', '=', 't.user_id')
    ->whereBetween('t.created_at', [$windowStart, $windowEnd])
    ->where('t.source_type', 'manual_adjustment')
    ->select('t.*', 'u.nama_lengkap')
    ->get();

echo "\nextra_off manual tx in window: {$eoTxBatch->count()}\n";
$approvers = [];
foreach ($eoTxBatch as $t) {
    $approver = $t->approved_by ? DB::table('users')->where('id', $t->approved_by)->value('nama_lengkap') : '-';
    if ($t->approved_by) {
        $approvers[$t->approved_by] = $approver;
    }
    echo "  {$t->created_at} {$t->nama_lengkap} amt={$t->amount} by={$approver}\n";
}

if ($approvers) {
    echo "\nKemungkinan admin yang input (dari approved_by EO tx batch):\n";
    foreach ($approvers as $id => $name) {
        echo "  user_id={$id} {$name}\n";
    }
}

echo "\n=== PH #43 pemakaian (used_amount) ===\n";
echo "used_amount={$ph->used_amount} used_date={$ph->used_date}\n";
$phLeaves = DB::table('approval_requests as ar')
    ->join('leave_types as lt', 'lt.id', '=', 'ar.leave_type_id')
    ->where('ar.user_id', $userId)
    ->where('lt.name', 'like', '%Public Holiday%')
    ->where('ar.status', 'approved')
    ->orderBy('ar.date_from')
    ->get(['ar.id', 'ar.date_from', 'ar.date_to', 'ar.status', 'ar.created_at', 'ar.approved_at']);
foreach ($phLeaves as $l) {
    $days = (strtotime((string) $l->date_to) - strtotime((string) $l->date_from)) / 86400 + 1;
    echo "  #{$l->id} {$l->date_from} s/d {$l->date_to} ({$days}d) approved_at={$l->approved_at} created={$l->created_at}\n";
}
