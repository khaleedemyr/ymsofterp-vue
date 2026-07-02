<?php
/**
 * Perbaiki harga FO per bulan (hindari timeout memori scan penuh).
 *
 * Usage:
 *   php scripts/apply_fo_prices_by_month.php
 *   php scripts/apply_fo_prices_by_month.php --from=2025-06 --to=2026-06
 *   php scripts/apply_fo_prices_by_month.php --dry-run
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderPriceAuditor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$opts = getopt('', ['from::', 'to::', 'dry-run']);
$fromYm = $opts['from'] ?? '2025-06';
$toYm = $opts['to'] ?? Carbon::now()->format('Y-m');
$dryRun = array_key_exists('dry-run', $opts);

$start = Carbon::createFromFormat('Y-m', $fromYm)->startOfMonth();
$end = Carbon::createFromFormat('Y-m', $toYm)->startOfMonth();

echo "=== Apply FO prices by month ===\n";
echo 'Mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo "Range: {$fromYm} .. {$toYm}\n\n";

$auditor = new FloorOrderPriceAuditor();
$totalUpdated = 0;
$totalMismatches = 0;

while ($start->lte($end)) {
    $from = $start->toDateString();
    $to = $start->copy()->endOfMonth()->toDateString();
    $label = $start->format('Y-m');

    echo "--- {$label} ---\n";
    $result = $auditor->scan($from, $to, true);
    $mismatches = $result['mismatches'];
    $count = count($mismatches);
    $totalMismatches += $count;

    echo "  scanned={$result['rows_scanned']} matched={$result['matched']} selisih={$count} items=" . count($result['summary_by_item']) . "\n";

    if ($count > 0 && ! $dryRun) {
        DB::beginTransaction();
        try {
            $stats = $auditor->applyFixes($mismatches);
            DB::commit();
            $totalUpdated += $stats['updated'];
            echo "  updated={$stats['updated']}\n";
        } catch (Throwable $e) {
            DB::rollBack();
            echo "  ERROR: {$e->getMessage()}\n";
            exit(1);
        }
    }

    $start->addMonth();
}

echo "\nTotal selisih: {$totalMismatches}\n";
echo 'Total baris diupdate: ' . ($dryRun ? 0 : $totalUpdated) . "\n";

if ($dryRun && $totalMismatches > 0) {
    echo "\nJalankan tanpa --dry-run untuk apply.\n";
}
