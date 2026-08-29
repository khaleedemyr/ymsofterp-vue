<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$userId = 1436;

$user = DB::table('users')->where('id', $userId)->first();
if (!$user) {
    echo "User tidak ditemukan\n";
    exit(1);
}

echo "=== SALDO CUTI: {$user->nama_lengkap} (id={$userId}) ===\n";
echo "tanggal_masuk: {$user->tanggal_masuk}\n";
echo "status: {$user->status}\n";
echo "users.cuti (saldo di DB): {$user->cuti}\n\n";

$annualLeaveTypeId = DB::table('leave_types')
    ->where('name', 'like', '%Annual%')
    ->value('id');

$tx = DB::table('leave_transactions')
    ->where('user_id', $userId)
    ->orderBy('created_at')
    ->orderBy('id')
    ->get();

echo "=== LEAVE TRANSACTIONS ({$tx->count()}) ===\n";
$byType = [];
$running = 0.0;
$lastBalanceAfter = null;
foreach ($tx as $t) {
    $byType[$t->transaction_type] = ($byType[$t->transaction_type] ?? 0) + (float) $t->amount;
    $creator = $t->created_by ? DB::table('users')->where('id', $t->created_by)->value('nama_lengkap') : 'system';
    echo sprintf(
        "#%s %s | %s | amt=%s bal_after=%s | %s-%s | by=%s | %s\n",
        $t->id,
        substr((string) $t->created_at, 0, 19),
        $t->transaction_type,
        number_format((float) $t->amount, 2, ',', '.'),
        number_format((float) $t->balance_after, 2, ',', '.'),
        $t->year,
        $t->month,
        $creator,
        substr((string) ($t->description ?? ''), 0, 80)
    );
    $lastBalanceAfter = (float) $t->balance_after;
}

echo "\nRingkasan per tipe:\n";
foreach ($byType as $type => $sum) {
    echo "  {$type}: " . number_format($sum, 2, ',', '.') . "\n";
}

$sumAll = (float) $tx->sum('amount');
echo "\nSUM semua amount: " . number_format($sumAll, 2, ',', '.') . "\n";
echo "balance_after transaksi terakhir: " . number_format((float) ($lastBalanceAfter ?? 0), 2, ',', '.') . "\n";
echo "users.cuti sekarang: " . number_format((float) $user->cuti, 2, ',', '.') . "\n";
$diff = (float) $user->cuti - (float) ($lastBalanceAfter ?? 0);
echo "Selisih users.cuti vs balance_after terakhir: " . number_format($diff, 2, ',', '.') . "\n";

echo "\n=== PENGAJUAN ANNUAL LEAVE ===\n";
$annualReqs = DB::table('approval_requests as ar')
    ->leftJoin('leave_types as lt', 'lt.id', '=', 'ar.leave_type_id')
    ->where('ar.user_id', $userId)
    ->where('ar.leave_type_id', $annualLeaveTypeId)
    ->select('ar.*', 'lt.name as leave_type')
    ->orderBy('ar.date_from')
    ->get();

$approvedDays = 0;
$pendingDays = 0;
$hrdApprovedDays = 0;
foreach ($annualReqs as $r) {
    $days = (int) ((strtotime((string) $r->date_to) - strtotime((string) $r->date_from)) / 86400 + 1);
    if ($r->status === 'approved' && ($r->hrd_status ?? '') === 'approved') {
        $hrdApprovedDays += $days;
    }
    if ($r->status === 'approved') {
        $approvedDays += $days;
    } elseif ($r->status === 'pending') {
        $pendingDays += $days;
    }
    echo sprintf(
        "  #%s %s s/d %s (%dd) status=%s hrd_status=%s | %s\n",
        $r->id,
        substr((string) $r->date_from, 0, 10),
        substr((string) $r->date_to, 0, 10),
        $days,
        $r->status,
        $r->hrd_status ?? 'null',
        substr((string) ($r->hrd_approval_notes ?? ''), 0, 50)
    );
}
echo "Total hari supervisor-approved: {$approvedDays}\n";
echo "Total hari HRD-approved (yang harus kepotong): {$hrdApprovedDays}\n";
echo "Total hari Annual Leave pending: {$pendingDays}\n";

$usageFromTx = abs((float) ($byType['leave_usage'] ?? 0));
echo "\nTotal leave_usage dari transaksi: {$usageFromTx}\n";
echo "Selisih approved vs usage tx: " . ($approvedDays - $usageFromTx) . " hari\n";

echo "\n=== KREDIT BULANAN (monthly_credit) per tahun ===\n";
$credits = DB::table('leave_transactions')
    ->where('user_id', $userId)
    ->where('transaction_type', 'monthly_credit')
    ->selectRaw('year, COUNT(*) as n, SUM(amount) as total')
    ->groupBy('year')
    ->orderBy('year')
    ->get();
foreach ($credits as $c) {
    echo "  {$c->year}: {$c->n} bulan, +{$c->total} hari\n";
}

$join = $user->tanggal_masuk ? date('Y-m', strtotime((string) $user->tanggal_masuk)) : null;
echo "\ntanggal_masuk: {$join}\n";
echo "Aturan sistem: +1 hari/bulan (monthly_credit) untuk karyawan aktif\n";

echo "\n=== VERDIK ===\n";
$okBalance = abs($diff) < 0.01;
$okUsage = abs($hrdApprovedDays - $usageFromTx) < 0.01;
echo ($okBalance ? 'OK' : 'FAIL') . " users.cuti cocok dengan balance_after transaksi terakhir\n";
echo ($okUsage ? 'OK' : 'PERLU CEK') . " jumlah cuti HRD-approved vs leave_usage transaction\n";

// Cross-check: approved tanpa transaksi pemotongan
echo "\n=== APPROVED TANPA LEAVE_USAGE MATCH ===\n";
foreach ($annualReqs as $r) {
    if ($r->status !== 'approved' || ($r->hrd_status ?? '') !== 'approved') {
        continue;
    }
    $from = substr((string) $r->date_from, 0, 10);
    $to = substr((string) $r->date_to, 0, 10);
    $match = DB::table('leave_transactions')
        ->where('user_id', $userId)
        ->where('transaction_type', 'leave_usage')
        ->where('description', 'like', "%{$from}%")
        ->where('description', 'like', "%{$to}%")
        ->exists();
    if (!$match) {
        echo "  MISSING deduction for approval #{$r->id} {$from} s/d {$to}\n";
    }
}
