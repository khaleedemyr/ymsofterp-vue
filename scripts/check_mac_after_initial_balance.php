<?php
/**
 * Cek MAC item yang ada stock-in (GR/dll) SETELAH upload saldo awal pada tanggal cutover.
 *
 * Usage:
 *   php scripts/check_mac_after_initial_balance.php
 *   php scripts/check_mac_after_initial_balance.php --date=2026-07-01
 *   php scripts/check_mac_after_initial_balance.php --date=2026-07-01 --outlet=5
 *   php scripts/check_mac_after_initial_balance.php --date=2026-07-01 --csv=scripts/mac_check.csv
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\OutletInventoryCostResolver;
use Illuminate\Support\Facades\DB;

$opts = getopt('', ['date::', 'outlet::', 'csv::', 'tolerance::']);
$cutoverDate = $opts['date'] ?? date('Y-m-d');
$outletFilter = isset($opts['outlet']) ? (int) $opts['outlet'] : null;
$csvPath = $opts['csv'] ?? null;
$tolerancePct = isset($opts['tolerance']) ? (float) $opts['tolerance'] : 1.0;

const INBOUND_CARD_REFS = [
    'initial_balance',
    'good_receive_outlet',
    'good_receive_supplier',
    'good_receive_outlet_supplier',
    'retail_food',
    'serial_receive',
    'internal_warehouse_transfer',
    'outlet_transfer',
    'mac_correction',
];

const INBOUND_HISTORY_REFS = [
    'good_receive_outlet',
    'good_receive_supplier',
    'good_receive_outlet_supplier',
    'retail_food',
    'serial_receive',
    'internal_warehouse_transfer',
    'outlet_transfer',
    'mac_correction',
];

$fmt = static fn (float $n): string => number_format($n, 4, '.', ',');

echo "=== Cek MAC setelah Saldo Awal ===\n";
echo "Cutover date : {$cutoverDate}\n";
echo "Outlet filter: " . ($outletFilter !== null ? (string) $outletFilter : 'semua') . "\n";
echo "Toleransi MAC: {$tolerancePct}%\n\n";

$ibTotalQuery = DB::table('outlet_food_inventory_cost_histories')
    ->where('reference_type', 'initial_balance')
    ->whereDate('date', $cutoverDate);
if ($outletFilter !== null) {
    $ibTotalQuery->where('id_outlet', $outletFilter);
}
$ibTotal = (int) $ibTotalQuery->count();

$candidatesQuery = DB::table('outlet_food_inventory_cost_histories as ib')
    ->join('outlet_food_inventory_cost_histories as aft', function ($join) {
        $join->on('aft.inventory_item_id', '=', 'ib.inventory_item_id')
            ->on('aft.id_outlet', '=', 'ib.id_outlet')
            ->on('aft.warehouse_outlet_id', '=', 'ib.warehouse_outlet_id')
            ->whereIn('aft.reference_type', INBOUND_HISTORY_REFS)
            ->whereRaw('(aft.date > ib.date OR (DATE(aft.date) = DATE(ib.date) AND aft.id > ib.id))');
    })
    ->join('outlet_food_inventory_items as ofii', 'ofii.id', '=', 'ib.inventory_item_id')
    ->join('items as i', 'i.id', '=', 'ofii.item_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ib.id_outlet')
    ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 'ib.warehouse_outlet_id')
    ->where('ib.reference_type', 'initial_balance')
    ->whereDate('ib.date', $cutoverDate)
    ->select([
        'ib.id as ib_hist_id',
        'ib.inventory_item_id',
        'ib.id_outlet',
        'ib.warehouse_outlet_id',
        'ib.date as ib_date',
        'ib.new_cost as ib_cost',
        'ib.mac as ib_mac',
        'i.id as item_id',
        'i.sku',
        'i.name as item_name',
        'o.nama_outlet',
        'wo.name as warehouse_name',
    ])
    ->distinct()
    ->orderBy('ib.id_outlet')
    ->orderBy('ib.warehouse_outlet_id')
    ->orderBy('i.name');

if ($outletFilter !== null) {
    $candidatesQuery->where('ib.id_outlet', $outletFilter);
}

$candidates = $candidatesQuery->get();

echo "Saldo awal total               : {$ibTotal}\n";
echo 'Ada stock-in setelah saldo awal: ' . $candidates->count() . " partisi\n\n";

if ($candidates->isEmpty()) {
    echo "Tidak ada item dengan stock-in setelah saldo awal pada {$cutoverDate}.\n";
    exit(0);
}

$inventoryIds = $candidates->pluck('inventory_item_id')->unique()->values()->all();
$outletIds = $candidates->pluck('id_outlet')->unique()->values()->all();

$preCutoverRows = DB::table('outlet_food_inventory_cost_histories')
    ->whereIn('inventory_item_id', $inventoryIds)
    ->whereIn('id_outlet', $outletIds)
    ->whereDate('date', '<', $cutoverDate)
    ->selectRaw('inventory_item_id, id_outlet, warehouse_outlet_id, COUNT(*) as cnt')
    ->groupBy('inventory_item_id', 'id_outlet', 'warehouse_outlet_id')
    ->get();
$preCutoverMap = [];
foreach ($preCutoverRows as $r) {
    $preCutoverMap[(int) $r->inventory_item_id . '|' . (int) $r->id_outlet . '|' . (int) $r->warehouse_outlet_id] = (int) $r->cnt;
}

$inboundBeforeRows = DB::table('outlet_food_inventory_cost_histories as aft')
    ->join('outlet_food_inventory_cost_histories as ib', function ($join) use ($cutoverDate) {
        $join->on('ib.inventory_item_id', '=', 'aft.inventory_item_id')
            ->on('ib.id_outlet', '=', 'aft.id_outlet')
            ->on('ib.warehouse_outlet_id', '=', 'aft.warehouse_outlet_id')
            ->where('ib.reference_type', 'initial_balance')
            ->whereDate('ib.date', $cutoverDate);
    })
    ->whereIn('aft.reference_type', INBOUND_HISTORY_REFS)
    ->whereDate('aft.date', $cutoverDate)
    ->whereColumn('aft.id', '<', 'ib.id')
    ->selectRaw('aft.inventory_item_id, aft.id_outlet, aft.warehouse_outlet_id, COUNT(*) as cnt')
    ->groupBy('aft.inventory_item_id', 'aft.id_outlet', 'aft.warehouse_outlet_id')
    ->get();
$inboundBeforeMap = [];
foreach ($inboundBeforeRows as $r) {
    $inboundBeforeMap[(int) $r->inventory_item_id . '|' . (int) $r->id_outlet . '|' . (int) $r->warehouse_outlet_id] = (int) $r->cnt;
}

$inboundAfterCounts = DB::table('outlet_food_inventory_cost_histories as aft')
    ->join('outlet_food_inventory_cost_histories as ib', function ($join) use ($cutoverDate) {
        $join->on('ib.inventory_item_id', '=', 'aft.inventory_item_id')
            ->on('ib.id_outlet', '=', 'aft.id_outlet')
            ->on('ib.warehouse_outlet_id', '=', 'aft.warehouse_outlet_id')
            ->where('ib.reference_type', 'initial_balance')
            ->whereDate('ib.date', $cutoverDate);
    })
    ->whereIn('aft.reference_type', INBOUND_HISTORY_REFS)
    ->whereRaw('(aft.date > ib.date OR (DATE(aft.date) = DATE(ib.date) AND aft.id > ib.id))')
    ->selectRaw('aft.inventory_item_id, aft.id_outlet, aft.warehouse_outlet_id, COUNT(*) as cnt')
    ->groupBy('aft.inventory_item_id', 'aft.id_outlet', 'aft.warehouse_outlet_id')
    ->get();
$inboundAfterMap = [];
foreach ($inboundAfterCounts as $r) {
    $inboundAfterMap[(int) $r->inventory_item_id . '|' . (int) $r->id_outlet . '|' . (int) $r->warehouse_outlet_id] = (int) $r->cnt;
}

$ibCards = DB::table('outlet_food_inventory_cards')
    ->where('reference_type', 'initial_balance')
    ->whereDate('date', $cutoverDate)
    ->whereIn('inventory_item_id', $inventoryIds)
    ->whereIn('id_outlet', $outletIds)
    ->orderBy('id')
    ->get(['id', 'inventory_item_id', 'id_outlet', 'warehouse_outlet_id']);
$ibCardMap = [];
foreach ($ibCards as $c) {
    $k = (int) $c->inventory_item_id . '|' . (int) $c->id_outlet . '|' . (int) $c->warehouse_outlet_id;
    if (! isset($ibCardMap[$k])) {
        $ibCardMap[$k] = (int) $c->id;
    }
}

$allCards = DB::table('outlet_food_inventory_cards')
    ->whereIn('inventory_item_id', $inventoryIds)
    ->whereIn('id_outlet', $outletIds)
    ->whereDate('date', '>=', $cutoverDate)
    ->orderBy('date')
    ->orderBy('id')
    ->get([
        'id',
        'inventory_item_id',
        'id_outlet',
        'warehouse_outlet_id',
        'date',
        'reference_type',
        'reference_id',
        'in_qty_small',
        'out_qty_small',
        'cost_per_small',
    ]);
$cardsByPartition = [];
foreach ($allCards as $card) {
    $k = (int) $card->inventory_item_id . '|' . (int) $card->id_outlet . '|' . (int) $card->warehouse_outlet_id;
    $cardsByPartition[$k][] = $card;
}

$stocks = DB::table('outlet_food_inventory_stocks')
    ->whereIn('inventory_item_id', $inventoryIds)
    ->whereIn('id_outlet', $outletIds)
    ->get(['inventory_item_id', 'id_outlet', 'warehouse_outlet_id', 'qty_small', 'last_cost_small', 'value']);
$stockMap = [];
foreach ($stocks as $s) {
    $stockMap[(int) $s->inventory_item_id . '|' . (int) $s->id_outlet . '|' . (int) $s->warehouse_outlet_id] = $s;
}

$lastHists = DB::table('outlet_food_inventory_cost_histories')
    ->whereIn('inventory_item_id', $inventoryIds)
    ->whereIn('id_outlet', $outletIds)
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->get(['inventory_item_id', 'id_outlet', 'warehouse_outlet_id', 'mac', 'new_cost', 'reference_type', 'reference_id']);
$lastHistMap = [];
foreach ($lastHists as $h) {
    $k = (int) $h->inventory_item_id . '|' . (int) $h->id_outlet . '|' . (int) $h->warehouse_outlet_id;
    if (! isset($lastHistMap[$k])) {
        $lastHistMap[$k] = $h;
    }
}

$rows = [];
$stats = [
    'with_inbound_after' => $candidates->count(),
    'mac_ok' => 0,
    'mac_mismatch' => 0,
    'qty_mismatch' => 0,
    'has_pre_cutover_history' => 0,
    'gr_before_saldo' => 0,
];

foreach ($candidates as $ib) {
    $k = (int) $ib->inventory_item_id . '|' . (int) $ib->id_outlet . '|' . (int) $ib->warehouse_outlet_id;

    $preCutoverCount = $preCutoverMap[$k] ?? 0;
    $inboundBeforeHist = $inboundBeforeMap[$k] ?? 0;
    $inboundAfterCount = $inboundAfterMap[$k] ?? 0;

    if ($preCutoverCount > 0) {
        $stats['has_pre_cutover_history']++;
    }
    if ($inboundBeforeHist > 0) {
        $stats['gr_before_saldo']++;
    }

    $ibCardId = $ibCardMap[$k] ?? 0;
    $partitionCards = $cardsByPartition[$k] ?? [];

    $qtySim = 0.0;
    $macSim = 0.0;
    $inboundEvents = [];

    foreach ($partitionCards as $card) {
        if ($ibCardId > 0 && (int) $card->id < $ibCardId) {
            continue;
        }

        $inQty = (float) ($card->in_qty_small ?? 0);
        $outQty = (float) ($card->out_qty_small ?? 0);
        $costIn = (float) ($card->cost_per_small ?? 0);

        if ($inQty > 0 && in_array($card->reference_type, INBOUND_CARD_REFS, true)) {
            $macBefore = $macSim;
            $macSim = OutletInventoryCostResolver::weightedAverageMacPerSmall(
                $qtySim,
                $macSim,
                $inQty,
                $costIn
            );
            $qtySim += $inQty;

            if ($card->reference_type !== 'initial_balance') {
                $inboundEvents[] = sprintf(
                    '%s#%d in=%s cost=%s mac:%s->%s',
                    $card->reference_type,
                    (int) $card->reference_id,
                    $fmt($inQty),
                    $fmt($costIn),
                    $fmt($macBefore),
                    $fmt($macSim)
                );
            }
        } elseif ($outQty > 0) {
            $qtySim = max(0.0, $qtySim - $outQty);
        }
    }

    $stock = $stockMap[$k] ?? null;
    $stockMac = $stock ? (float) $stock->last_cost_small : 0.0;
    $stockQty = $stock ? (float) $stock->qty_small : 0.0;
    $stockValue = $stock ? (float) $stock->value : 0.0;

    $lastHist = $lastHistMap[$k] ?? null;
    $lastHistMac = $lastHist ? (float) ($lastHist->mac ?? $lastHist->new_cost ?? 0) : 0.0;

    $macDiffPct = ($macSim > 0)
        ? abs($stockMac - $macSim) / $macSim * 100
        : ($stockMac > 0 ? 100.0 : 0.0);

    $qtyDiff = abs($stockQty - $qtySim);
    $macOk = $macSim > 0 && $macDiffPct <= $tolerancePct;
    $qtyOk = $qtyDiff < 0.0001;

    if ($macOk) {
        $stats['mac_ok']++;
    } else {
        $stats['mac_mismatch']++;
    }
    if (! $qtyOk) {
        $stats['qty_mismatch']++;
    }

    $status = $macOk && $qtyOk ? 'OK' : ($macOk ? 'MAC_OK_QTY_DIFF' : ($qtyOk ? 'MAC_MISMATCH' : 'MAC_QTY_MISMATCH'));

    $rows[] = [
        'status' => $status,
        'outlet_id' => (int) $ib->id_outlet,
        'outlet' => (string) ($ib->nama_outlet ?? '-'),
        'warehouse_id' => (int) $ib->warehouse_outlet_id,
        'warehouse' => (string) ($ib->warehouse_name ?? '-'),
        'item_id' => (int) $ib->item_id,
        'inventory_item_id' => (int) $ib->inventory_item_id,
        'sku' => (string) ($ib->sku ?? ''),
        'item_name' => (string) $ib->item_name,
        'ib_cost' => (float) ($ib->ib_cost ?? 0),
        'inbound_after_count' => $inboundAfterCount,
        'inbound_before_same_day' => $inboundBeforeHist,
        'pre_cutover_hist_count' => $preCutoverCount,
        'mac_simulated' => round($macSim, 4),
        'mac_stock' => round($stockMac, 4),
        'mac_last_hist' => round($lastHistMac, 4),
        'mac_diff_pct' => round($macDiffPct, 2),
        'qty_simulated' => round($qtySim, 4),
        'qty_stock' => round($stockQty, 4),
        'value_stock' => round($stockValue, 2),
        'last_hist_ref' => $lastHist ? ($lastHist->reference_type . '#' . $lastHist->reference_id) : '-',
        'inbound_events' => implode(' | ', $inboundEvents),
    ];
}

echo "--- Ringkasan ---\n";
echo "Saldo awal total               : {$ibTotal}\n";
echo "Ada stock-in setelah saldo awal: {$stats['with_inbound_after']}\n";
echo "MAC OK (simulasi vs stok)      : {$stats['mac_ok']}\n";
echo "MAC mismatch                   : {$stats['mac_mismatch']}\n";
echo "Qty mismatch (simulasi vs stok): {$stats['qty_mismatch']}\n";
echo "Partisi punya history pre-cutover: {$stats['has_pre_cutover_history']}\n";
echo "Partisi ada GR sebelum saldo (hari sama): {$stats['gr_before_saldo']}\n\n";

usort($rows, static function (array $a, array $b): int {
    $rank = ['MAC_MISMATCH' => 0, 'MAC_QTY_MISMATCH' => 1, 'MAC_OK_QTY_DIFF' => 2, 'OK' => 3];
    $ra = $rank[$a['status']] ?? 9;
    $rb = $rank[$b['status']] ?? 9;
    if ($ra !== $rb) {
        return $ra <=> $rb;
    }

    return $b['mac_diff_pct'] <=> $a['mac_diff_pct'];
});

$mismatchRows = array_values(array_filter($rows, static fn (array $r): bool => $r['status'] !== 'OK'));
$showLimit = 40;

echo "--- Detail mismatch (max {$showLimit} baris) ---\n";
if ($mismatchRows === []) {
    echo "Semua partisi dengan stock-in setelah saldo awal: MAC & qty OK.\n";
} else {
    foreach (array_slice($mismatchRows, 0, $showLimit) as $r) {
        echo sprintf(
            "[%s] %s | WH:%s | SKU:%s %s\n",
            $r['status'],
            $r['outlet'],
            $r['warehouse'],
            $r['sku'],
            $r['item_name']
        );
        echo sprintf(
            "  saldo_awal=%s | inbound_setelah=%d | pre_cutover_hist=%d | GR_sebelum_saldo=%d\n",
            $fmt($r['ib_cost']),
            $r['inbound_after_count'],
            $r['pre_cutover_hist_count'],
            $r['inbound_before_same_day']
        );
        echo sprintf(
            "  MAC sim=%s | stok=%s | last_hist=%s | diff=%s%% | qty sim=%s stok=%s\n",
            $fmt($r['mac_simulated']),
            $fmt($r['mac_stock']),
            $fmt($r['mac_last_hist']),
            number_format($r['mac_diff_pct'], 2, '.', ','),
            $fmt($r['qty_simulated']),
            $fmt($r['qty_stock'])
        );
        if ($r['inbound_events'] !== '') {
            echo '  events: ' . $r['inbound_events'] . "\n";
        }
        echo "\n";
    }
    if (count($mismatchRows) > $showLimit) {
        echo '... dan ' . (count($mismatchRows) - $showLimit) . " mismatch lainnya (lihat CSV).\n\n";
    }
}

if ($csvPath !== null) {
    $dir = dirname($csvPath);
    if ($dir !== '' && $dir !== '.' && ! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fp = fopen($csvPath, 'w');
    if ($fp === false) {
        echo "Gagal menulis CSV: {$csvPath}\n";
        exit(1);
    }
    fputcsv($fp, array_keys($rows[0]));
    foreach ($rows as $r) {
        fputcsv($fp, $r);
    }
    fclose($fp);
    echo "CSV ditulis: {$csvPath}\n";
}

exit($stats['mac_mismatch'] > 0 ? 2 : 0);
