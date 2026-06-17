<?php
/**
 * Audit MAC anomalies across all outlets.
 *
 * Usage:
 *   php scripts/trace_mac_anomalies_all_outlets.php
 *   php scripts/trace_mac_anomalies_all_outlets.php --from=2026-01-01 --to=2026-06-17
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$opts = getopt('', ['from::', 'to::']);
$dateTo = $opts['to'] ?? date('Y-m-d');
$dateFrom = $opts['from'] ?? date('Y-m-d', strtotime('-180 days', strtotime($dateTo)));

$minSpikePercent = 100.0;
$spikeMultiplier = 5.0;
$maxMac = 10_000_000.0;

$fmt = static fn (float $n): string => number_format($n, 2, '.', ',');

echo "=== MAC Anomaly Audit (All Outlets) ===\n";
echo "Period: {$dateFrom} s/d {$dateTo}\n";
echo "Rules : spike >= {$minSpikePercent}% OR >= {$spikeMultiplier}x, mac<0, new_cost<0, mac>{$maxMac}\n\n";

$outletNames = DB::table('tbl_data_outlet')
    ->pluck('nama_outlet', 'id_outlet')
    ->toArray();

$rows = DB::table('outlet_food_inventory_cost_histories as h')
    ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 'h.warehouse_outlet_id')
    ->leftJoin('outlet_food_inventory_items as ofii', 'ofii.id', '=', 'h.inventory_item_id')
    ->leftJoin('items as i', 'i.id', '=', 'ofii.item_id')
    ->whereBetween('h.date', [$dateFrom, $dateTo])
    ->orderBy('h.id_outlet')
    ->orderBy('h.warehouse_outlet_id')
    ->orderBy('h.inventory_item_id')
    ->orderBy('h.date')
    ->orderBy('h.id')
    ->select([
        'h.id',
        'h.id_outlet',
        'h.warehouse_outlet_id',
        'h.inventory_item_id',
        'h.date',
        'h.old_cost',
        'h.new_cost',
        'h.mac',
        'h.reference_type',
        'h.reference_id',
        'wo.name as warehouse_name',
        'i.name as item_name',
    ])
    ->cursor();

$lastMacByKey = [];
$byOutlet = [];
$byRefType = [];
$samplesByRef = [];
$globalTopSpikes = [];

foreach ($rows as $row) {
    $partitionKey = (int) $row->id_outlet . '|' . (int) $row->warehouse_outlet_id . '|' . (int) $row->inventory_item_id;
    $prevMac = array_key_exists($partitionKey, $lastMacByKey) ? $lastMacByKey[$partitionKey] : null;
    $mac = (float) ($row->mac ?? 0);
    $newCost = (float) ($row->new_cost ?? 0);
    $oldCost = (float) ($row->old_cost ?? 0);
    $lastMacByKey[$partitionKey] = $mac;

    $flags = [];
    $changePct = null;

    if ($mac < 0) {
        $flags[] = 'negative_mac';
    }
    if ($newCost < 0) {
        $flags[] = 'negative_new_cost';
    }
    if ($mac > $maxMac) {
        $flags[] = 'absolute_high';
    }
    if ($prevMac !== null && $prevMac > 0) {
        $changePct = (($mac - $prevMac) / $prevMac) * 100;
        if (abs($changePct) >= $minSpikePercent) {
            $flags[] = 'spike_percent';
        }
        if ($mac >= $prevMac * $spikeMultiplier) {
            $flags[] = 'spike_multiplier';
        }
    }
    if ($oldCost > 0) {
        $newCostChangePct = abs((($newCost - $oldCost) / $oldCost) * 100);
        if ($newCostChangePct >= $minSpikePercent && !in_array('spike_percent', $flags, true)) {
            $flags[] = 'spike_percent';
        }
    }

    if (empty($flags)) {
        continue;
    }

    $outletId = (int) $row->id_outlet;
    $refType = $row->reference_type ?: '(null)';
    $flagKey = implode(',', array_unique($flags));

    if (!isset($byOutlet[$outletId])) {
        $byOutlet[$outletId] = [
            'count' => 0,
            'negative_mac' => 0,
            'negative_new_cost' => 0,
            'spike_percent' => 0,
            'spike_multiplier' => 0,
            'absolute_high' => 0,
        ];
    }
    $byOutlet[$outletId]['count']++;
    foreach (array_unique($flags) as $f) {
        if (isset($byOutlet[$outletId][$f])) {
            $byOutlet[$outletId][$f]++;
        }
    }

    $byRefType[$refType] = ($byRefType[$refType] ?? 0) + 1;

    if (!isset($samplesByRef[$refType])) {
        $samplesByRef[$refType] = [];
    }
    if (count($samplesByRef[$refType]) < 5) {
        $samplesByRef[$refType][] = [
            'id' => (int) $row->id,
            'date' => (string) $row->date,
            'outlet_id' => $outletId,
            'outlet_name' => $outletNames[$outletId] ?? '-',
            'warehouse_name' => $row->warehouse_name ?: '-',
            'item_name' => $row->item_name ?: '-',
            'reference_id' => (int) ($row->reference_id ?? 0),
            'mac' => $mac,
            'new_cost' => $newCost,
            'old_cost' => $oldCost,
            'prev_mac' => $prevMac,
            'change_pct' => $changePct,
            'flags' => $flagKey,
        ];
    }

    if ($changePct !== null) {
        $globalTopSpikes[] = [
            'history_id' => (int) $row->id,
            'date' => (string) $row->date,
            'outlet_id' => $outletId,
            'outlet_name' => $outletNames[$outletId] ?? '-',
            'reference_type' => $refType,
            'reference_id' => (int) ($row->reference_id ?? 0),
            'item_name' => $row->item_name ?: '-',
            'warehouse_name' => $row->warehouse_name ?: '-',
            'prev_mac' => (float) $prevMac,
            'mac' => $mac,
            'change_pct' => $changePct,
            'flags' => $flagKey,
        ];
    }
}

if (empty($byOutlet)) {
    echo "Tidak ada anomali pada periode ini.\n";
    exit(0);
}

arsort($byRefType);
uasort($byOutlet, static fn ($a, $b) => $b['count'] <=> $a['count']);
usort($globalTopSpikes, static fn ($a, $b) => abs($b['change_pct']) <=> abs($a['change_pct']));

echo "Total outlet with anomalies: " . count($byOutlet) . "\n";
echo "Total anomaly rows         : " . array_sum(array_column($byOutlet, 'count')) . "\n\n";

echo "--- Top 15 Outlet (by anomaly count) ---\n";
$i = 0;
foreach ($byOutlet as $outletId => $stat) {
    $i++;
    echo sprintf(
        "%2d) [%d] %s | total=%d | neg_mac=%d | neg_new=%d | spike=%d | spike5x=%d | high=%d\n",
        $i,
        $outletId,
        $outletNames[$outletId] ?? '-',
        $stat['count'],
        $stat['negative_mac'],
        $stat['negative_new_cost'],
        $stat['spike_percent'],
        $stat['spike_multiplier'],
        $stat['absolute_high']
    );
    if ($i >= 15) {
        break;
    }
}

echo "\n--- Top Reference Type (sumber transaksi) ---\n";
$j = 0;
foreach ($byRefType as $refType => $cnt) {
    $j++;
    echo sprintf("%2d) %-35s %8d\n", $j, $refType, $cnt);
    if ($j >= 15) {
        break;
    }
}

echo "\n--- Top 20 Extreme MAC Spikes ---\n";
foreach (array_slice($globalTopSpikes, 0, 20) as $k => $s) {
    echo sprintf(
        "%2d) %s | [%d] %s | %s #%d\n",
        $k + 1,
        $s['date'],
        $s['outlet_id'],
        $s['outlet_name'],
        $s['reference_type'],
        $s['reference_id']
    );
    echo sprintf(
        "    item=%s | wh=%s | prev=%s -> mac=%s | change=%s%% | flags=%s | history_id=%d\n",
        $s['item_name'],
        $s['warehouse_name'],
        $fmt($s['prev_mac']),
        $fmt($s['mac']),
        number_format($s['change_pct'], 2, '.', ''),
        $s['flags'],
        $s['history_id']
    );
}

echo "\n--- Sample per Reference Type (up to 5 each) ---\n";
foreach (array_slice(array_keys($byRefType), 0, 10) as $refType) {
    echo "\n[$refType]\n";
    foreach ($samplesByRef[$refType] as $s) {
        echo sprintf(
            "  %s | [%d] %s | %s | %s | ref_id=%d | prev=%s mac=%s new=%s old=%s | flags=%s | history_id=%d\n",
            $s['date'],
            $s['outlet_id'],
            $s['outlet_name'],
            $s['warehouse_name'],
            $s['item_name'],
            $s['reference_id'],
            $fmt((float) ($s['prev_mac'] ?? 0)),
            $fmt((float) $s['mac']),
            $fmt((float) $s['new_cost']),
            $fmt((float) $s['old_cost']),
            $s['flags'],
            $s['id']
        );
    }
}

echo "\nDone.\n";
