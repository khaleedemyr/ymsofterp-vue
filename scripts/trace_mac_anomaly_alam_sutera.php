<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacAnomalyDetectionService;
use Illuminate\Support\Facades\DB;

$outletId = 22;
$outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first(['nama_outlet']);
echo "=== MAC Anomaly Trace: {$outlet->nama_outlet} (id={$outletId}) ===\n\n";

$service = app(MacAnomalyDetectionService::class);
$result = $service->scan([
    'id_outlet' => $outletId,
    'date_from' => '2024-01-01',
    'date_to' => '2026-12-31',
    'min_spike_percent' => 50,
    'spike_multiplier' => 2,
    'max_mac' => 10000000,
    'types' => ['negative_mac', 'negative_new_cost', 'spike_percent', 'spike_multiplier', 'absolute_high'],
    'per_page' => 5000,
]);

$anomalies = $result['anomalies'];
echo 'Total anomalies: ' . count($anomalies) . "\n";
echo 'History rows in period: ' . ($result['summary']['history_rows_in_period'] ?? 0) . "\n\n";

echo "--- By reference_type ---\n";
$byRef = [];
foreach ($anomalies as $a) {
    $rt = $a['reference_type'] ?? '(null)';
    $byRef[$rt] = ($byRef[$rt] ?? 0) + 1;
}
arsort($byRef);
foreach ($byRef as $rt => $cnt) {
    echo sprintf("  %-35s %5d\n", $rt, $cnt);
}

echo "\n--- By anomaly type ---\n";
foreach ($result['summary']['type_breakdown'] ?? [] as $t => $c) {
    echo sprintf("  %-25s %5d\n", $t, $c);
}

$focusTypes = [
    'outlet_stock_adjustment',
    'serial_receive',
    'serial_receive_rollback',
    'internal_warehouse_transfer',
    'outlet_transfer',
];

echo "\n=== SAMPLE per modul fokus (max 3 each) ===\n";
foreach ($focusTypes as $ft) {
    $samples = array_values(array_filter($anomalies, fn ($a) => ($a['reference_type'] ?? '') === $ft));
    if (empty($samples)) {
        echo "\n[$ft] tidak ada sample\n";
        continue;
    }
    echo "\n[$ft] count=" . count($samples) . "\n";
    foreach (array_slice($samples, 0, 3) as $s) {
        echo "  date={$s['date']} item={$s['item_name']} wh={$s['warehouse_name']}\n";
        echo "    ref_id={$s['reference_id']} txn={$s['transaction_number']}\n";
        echo "    prev_mac={$s['prev_mac']} mac={$s['mac']} new_cost={$s['new_cost']} old_cost={$s['old_cost']}\n";
        echo "    flags=" . implode(',', $s['anomaly_types']) . "\n";
    }
}

// Negative MAC deep dive
echo "\n=== NEGATIVE MAC rows (all) ===\n";
$neg = DB::table('outlet_food_inventory_cost_histories as h')
    ->leftJoin('outlet_food_inventory_items as ofii', 'ofii.id', '=', 'h.inventory_item_id')
    ->leftJoin('items as i', 'i.id', '=', 'ofii.item_id')
    ->where('h.id_outlet', $outletId)
    ->where('h.mac', '<', 0)
    ->orderByDesc('h.date')
    ->limit(15)
    ->get(['h.id', 'h.date', 'h.reference_type', 'h.reference_id', 'h.mac', 'h.new_cost', 'h.old_cost', 'i.name as item_name']);

foreach ($neg as $r) {
    echo "  {$r->date} | {$r->reference_type} #{$r->reference_id} | {$r->item_name} | mac={$r->mac} new={$r->new_cost}\n";
}

// Spike on transfer: check if receiver gets sender MAC wrong
echo "\n=== outlet_transfer spikes (mac/prev > 5x or mac<0) sample ===\n";
$transferHist = DB::table('outlet_food_inventory_cost_histories as h')
    ->leftJoin('items as i', 'i.id', '=', DB::raw('(SELECT item_id FROM outlet_food_inventory_items WHERE id = h.inventory_item_id LIMIT 1)'))
    ->where('h.id_outlet', $outletId)
    ->where('h.reference_type', 'outlet_transfer')
    ->where('h.date', '>=', '2025-01-01')
    ->orderByDesc('h.id')
    ->limit(200)
    ->get(['h.id', 'h.date', 'h.reference_id', 'h.mac', 'h.new_cost', 'h.old_cost', 'h.inventory_item_id']);

$spikeTransfers = [];
foreach ($transferHist as $h) {
    if ((float) $h->mac < 0 || (float) $h->new_cost < 0) {
        $spikeTransfers[] = $h;
    }
}
echo 'Transfer rows with negative mac/new_cost in last 200: ' . count($spikeTransfers) . "\n";
foreach (array_slice($spikeTransfers, 0, 5) as $h) {
    echo "  id={$h->id} date={$h->date} ref={$h->reference_id} mac={$h->mac} new={$h->new_cost} old={$h->old_cost}\n";
}
