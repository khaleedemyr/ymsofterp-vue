<?php
/**
 * Hapus outlet_food_inventory_cost_histories sebelum tanggal cutover (go-live saldo awal).
 *
 * Usage:
 *   php scripts/delete_mac_history_before_cutover.php --date=2026-07-01
 *   php scripts/delete_mac_history_before_cutover.php --date=2026-07-01 --apply
 *   php scripts/delete_mac_history_before_cutover.php --date=2026-07-01 --apply --backup=scripts/backup_mac_hist_pre_cutover.csv
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$opts = getopt('', ['date::', 'apply', 'backup::', 'outlet::', 'chunk::']);
$cutoverDate = $opts['date'] ?? '2026-07-01';
$apply = array_key_exists('apply', $opts);
$backupPath = $opts['backup'] ?? null;
$outletFilter = isset($opts['outlet']) ? (int) $opts['outlet'] : null;
$chunkSize = isset($opts['chunk']) ? max(100, (int) $opts['chunk']) : 5000;

echo "=== Hapus MAC history sebelum cutover ===\n";
echo "Cutover date : {$cutoverDate}\n";
echo 'Outlet filter: ' . ($outletFilter !== null ? (string) $outletFilter : 'semua') . "\n";
echo 'Mode         : ' . ($apply ? 'APPLY (DELETE)' : 'DRY-RUN') . "\n\n";

$baseQuery = DB::table('outlet_food_inventory_cost_histories')
    ->whereDate('date', '<', $cutoverDate);

if ($outletFilter !== null) {
    $baseQuery->where('id_outlet', $outletFilter);
}

$total = (clone $baseQuery)->count();
echo "Total baris akan dihapus: {$total}\n\n";

if ($total === 0) {
    echo "Tidak ada data untuk dihapus.\n";
    exit(0);
}

$byRef = (clone $baseQuery)
    ->selectRaw('reference_type, COUNT(*) as cnt')
    ->groupBy('reference_type')
    ->orderByDesc('cnt')
    ->get();

echo "--- Per reference_type ---\n";
foreach ($byRef as $row) {
    echo sprintf("  %-35s %s\n", $row->reference_type ?? '(null)', number_format((int) $row->cnt));
}

$byOutlet = (clone $baseQuery)
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'outlet_food_inventory_cost_histories.id_outlet')
    ->selectRaw('outlet_food_inventory_cost_histories.id_outlet, o.nama_outlet, COUNT(*) as cnt')
    ->groupBy('outlet_food_inventory_cost_histories.id_outlet', 'o.nama_outlet')
    ->orderByDesc('cnt')
    ->limit(15)
    ->get();

echo "\n--- Top 15 outlet ---\n";
foreach ($byOutlet as $row) {
    echo sprintf(
        "  outlet=%d %-40s %s\n",
        (int) $row->id_outlet,
        (string) ($row->nama_outlet ?? '-'),
        number_format((int) $row->cnt)
    );
}

$afterCutover = DB::table('outlet_food_inventory_cost_histories')
    ->whereDate('date', '>=', $cutoverDate);
if ($outletFilter !== null) {
    $afterCutover->where('id_outlet', $outletFilter);
}
$remain = (clone $afterCutover)->count();
$initialBalance = (clone $afterCutover)->where('reference_type', 'initial_balance')->count();

echo "\n--- Setelah hapus (estimasi) ---\n";
echo 'Baris tersisa (date >= cutover): ' . number_format($remain) . "\n";
echo '  di antaranya initial_balance   : ' . number_format($initialBalance) . "\n";

if (! $apply) {
    echo "\nDry-run selesai. Tambahkan --apply untuk eksekusi delete.\n";
    exit(0);
}

if ($backupPath !== null) {
    $dir = dirname($backupPath);
    if ($dir !== '' && $dir !== '.' && ! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    echo "\nBackup ke CSV: {$backupPath}\n";
    $fp = fopen($backupPath, 'w');
    if ($fp === false) {
        echo "Gagal buat file backup.\n";
        exit(1);
    }

    $headersWritten = false;
    $backupQuery = (clone $baseQuery)->orderBy('id');
    $backupQuery->chunk($chunkSize, function ($rows) use ($fp, &$headersWritten) {
        foreach ($rows as $row) {
            $arr = (array) $row;
            if (! $headersWritten) {
                fputcsv($fp, array_keys($arr));
                $headersWritten = true;
            }
            fputcsv($fp, $arr);
        }
    });
    fclose($fp);
    echo "Backup selesai.\n";
}

echo "\nMenghapus dalam chunk {$chunkSize}...\n";
$deleted = 0;

do {
    $ids = (clone $baseQuery)
        ->orderBy('id')
        ->limit($chunkSize)
        ->pluck('id')
        ->all();

    if ($ids === []) {
        break;
    }

    $batch = DB::table('outlet_food_inventory_cost_histories')->whereIn('id', $ids)->delete();
    $deleted += $batch;
    echo "  deleted {$deleted} / {$total}\r";
} while (count($ids) === $chunkSize);

echo "\nBerhasil hapus {$deleted} baris history MAC sebelum {$cutoverDate}.\n";

$verifyRemain = DB::table('outlet_food_inventory_cost_histories')
    ->whereDate('date', '<', $cutoverDate)
    ->when($outletFilter !== null, fn ($q) => $q->where('id_outlet', $outletFilter))
    ->count();

if ($verifyRemain > 0) {
    echo "PERINGATAN: masih ada {$verifyRemain} baris pre-cutover.\n";
    exit(1);
}

echo "Verifikasi: 0 baris pre-cutover tersisa.\n";
exit(0);
