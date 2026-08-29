<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$compId = 43;
$userId = 1436;

echo "=== holiday_attendance_compensations #{$compId} ===\n";
$row = DB::table('holiday_attendance_compensations')->where('id', $compId)->first();
if (!$row) {
    echo "Row tidak ditemukan\n";
    exit(1);
}
foreach ((array) $row as $k => $v) {
    echo "  {$k}: {$v}\n";
}

$emp = DB::table('users')->where('id', $row->user_id)->first(['id', 'nama_lengkap', 'nik']);
echo "\nKaryawan: {$emp->nama_lengkap} (id={$emp->id}, nik={$emp->nik})\n";

echo "\n=== Semua manual PH bonus untuk user {$userId} ===\n";
$manual = DB::table('holiday_attendance_compensations')
    ->where('user_id', $userId)
    ->where('compensation_type', 'bonus')
    ->where('compensation_description', 'like', '%Manual adjustment%')
    ->orderBy('id')
    ->get();
foreach ($manual as $m) {
    echo "#{$m->id} date={$m->holiday_date} amt={$m->compensation_amount} used={$m->used_amount} created={$m->created_at} notes=" . ($m->notes ?? '-') . "\n";
}

echo "\n=== Cek tabel audit / activity ===\n";
$candidates = ['activity_log', 'audit_logs', 'user_activity_logs', 'system_logs'];
foreach ($candidates as $tbl) {
    if (!Schema::hasTable($tbl)) {
        echo "  {$tbl}: tidak ada\n";
        continue;
    }
    $cols = Schema::getColumnListing($tbl);
    echo "  {$tbl}: " . implode(', ', array_slice($cols, 0, 15)) . (count($cols) > 15 ? '...' : '') . "\n";
}

if (Schema::hasTable('activity_log')) {
    $acts = DB::table('activity_log')
        ->where('subject_type', 'like', '%Holiday%')
        ->orWhere('description', 'like', '%public holiday%')
        ->orWhere('description', 'like', '%saldo%')
        ->orderByDesc('created_at')
        ->limit(20)
        ->get();
    echo "\nactivity_log sample (holiday/saldo): {$acts->count()}\n";
    foreach ($acts as $a) {
        echo "  {$a->created_at} causer={$a->causer_id} {$a->description}\n";
    }
}

echo "\n=== Laravel log (Public Holiday Balance user {$userId}) ===\n";
$logPath = storage_path('logs/laravel.log');
if (!is_file($logPath)) {
    echo "laravel.log tidak ada\n";
} else {
    $needle = '"user_id":' . $userId;
    $lines = [];
    $fh = fopen($logPath, 'r');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            if (stripos($line, 'Public Holiday Balance') !== false && str_contains($line, (string) $userId)) {
                $lines[] = trim($line);
            }
        }
        fclose($fh);
    }
    echo 'Matches in laravel.log: ' . count($lines) . "\n";
    foreach (array_slice($lines, -10) as $ln) {
        echo "  {$ln}\n";
    }

    // rotated logs
    foreach (glob(storage_path('logs/laravel-*.log')) ?: [] as $rot) {
        $fh = fopen($rot, 'r');
        $n = 0;
        if ($fh) {
            while (($line = fgets($fh)) !== false) {
                if (stripos($line, 'Public Holiday Balance') !== false && str_contains($line, (string) $userId)) {
                    $n++;
                    if ($n <= 3) {
                        echo '  [' . basename($rot) . '] ' . trim($line) . "\n";
                    }
                }
            }
            fclose($fh);
        }
        if ($n > 0) {
            echo "  " . basename($rot) . ": {$n} match(es)\n";
        }
    }
}

echo "\n=== Route: Input Saldo via Data Karyawan ===\n";
echo "Endpoint: UserController::updateSaldo -> updatePublicHolidayBalance()\n";
echo "UI: Users > SaldoModal (Input Saldo HRD)\n";
echo "Catatan: tabel compensations TIDAK punya created_by; pelacakan via laravel.log saat input.\n";
