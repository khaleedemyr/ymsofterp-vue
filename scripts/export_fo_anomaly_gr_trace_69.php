<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$from = $argv[1] ?? '2026-06-01';
$to = $argv[2] ?? '2026-06-30';
$outlet = $argv[3] ?? 'Justus Steakhouse SMB';
$list = require __DIR__ . '/item_list_69.php';

function grLineFromRow(object $item, object $row): array
{
    $po = (float) ($row->po_price ?? 0);
    $unitId = $row->unit_id ? (int) $row->unit_id : null;
    $small = (float) ($item->small_conversion_qty ?: 1);
    $medium = (float) ($item->medium_conversion_qty ?: 1);
    $costSmall = $po;
    if ($unitId === (int) $item->large_unit_id) {
        $costSmall = $po / ($small * $medium);
    } elseif ($unitId === (int) $item->medium_unit_id) {
        $costSmall = $po / $small;
    }
    $costLarge = $costSmall * $small * $medium;

    return [
        'gr_number' => $row->gr_number,
        'receive_date' => (string) $row->receive_date,
        'po_number' => $row->po_number,
        'po_price' => $po,
        'po_unit' => $row->po_unit,
        'cost_large' => $costLarge,
        'sell_large' => FloorOrderItemPriceResolver::roundUpToHundred($costLarge * 1.12),
    ];
}

function lineSell(object $item, array $gr, string $unit): float
{
    $sellLarge = (float) $gr['sell_large'];
    $tier = FloorOrderItemPriceResolver::detectUnitTier($item, $unit);
    $raw = match ($tier) {
        'large' => $sellLarge,
        'small' => FloorOrderItemPriceResolver::largeToSmallPrice($sellLarge, $item),
        default => FloorOrderItemPriceResolver::largeToMediumPrice($sellLarge, $item),
    };

    return FloorOrderItemPriceResolver::roundUpToHundred($raw);
}

function lineCost(object $item, array $gr, string $unit): float
{
    $costLarge = (float) $gr['cost_large'];
    $tier = FloorOrderItemPriceResolver::detectUnitTier($item, $unit);

    return match ($tier) {
        'large' => $costLarge,
        'small' => FloorOrderItemPriceResolver::largeToSmallPrice($costLarge, $item),
        default => FloorOrderItemPriceResolver::largeToMediumPrice($costLarge, $item),
    };
}

function matchGr(object $item, int $itemId, float $foPrice, string $unit, string $asOf): ?array
{
    $rows = DB::table('food_good_receive_items as gri')
        ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
        ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
        ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
        ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
        ->where('gri.item_id', $itemId)
        ->whereDate('gr.receive_date', '<=', $asOf)
        ->orderByDesc('gr.receive_date')
        ->orderByDesc('gr.id')
        ->select('gr.gr_number', 'gr.receive_date', 'po.number as po_number', 'poi.price as po_price', 'gri.unit_id', 'u.name as po_unit')
        ->limit(200)
        ->get();

    $targetCost = $foPrice / 1.12;
    $best = null;
    $bestMethod = null;
    $bestDiff = PHP_FLOAT_MAX;

    foreach ($rows as $row) {
        $gr = grLineFromRow($item, $row);
        $sell = lineSell($item, $gr, $unit);
        $cost = lineCost($item, $gr, $unit);
        foreach ([
            ['diff' => abs($sell - $foPrice), 'method' => 'sell_line≈fo_stored'],
            ['diff' => abs($cost - $targetCost), 'method' => 'cost_line≈fo÷1.12'],
        ] as $m) {
            if ($m['diff'] < $bestDiff && $m['diff'] <= max(100, $foPrice * 0.02)) {
                $bestDiff = $m['diff'];
                $best = $gr;
                $bestMethod = $m['method'];
            }
        }
    }

    return $best ? ['gr' => $best, 'method' => $bestMethod] : null;
}

$items = DB::table('items as i')
    ->leftJoin('warehouse_division as wd', 'wd.id', '=', 'i.warehouse_division_id')
    ->leftJoin('warehouses as w', 'w.id', '=', 'wd.warehouse_id')
    ->whereIn('i.name', $list)->where('i.status', 'active')
    ->select('i.*', 'w.name as warehouse_name')->get()->keyBy('name');

$reportRows = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('units as u', 'i.unit_id', '=', 'u.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', fn ($j) => $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id'))
    ->leftJoin('food_floor_orders as ffo', 'ffo.id', '=', 'fo.floor_order_id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', $outlet)
    ->whereIn('it.name', $list)
    ->whereDate('gr.receive_date', '>=', $from)
    ->whereDate('gr.receive_date', '<=', $to)
    ->whereNull('gr.deleted_at')
    ->select('it.name as item_name', 'u.name as unit', DB::raw('SUM(i.received_qty) as qty'), DB::raw('AVG(COALESCE(fo.price,0)) as fo_price'), DB::raw('MAX(ffo.tanggal) as fo_tanggal'))
    ->groupBy('it.name', 'u.name')
    ->get()->keyBy('item_name');

$out = __DIR__ . '/item_fo_anomaly_gr_trace_69.csv';
$fh = fopen($out, 'w');
fputcsv($fh, [
    'no', 'item_name', 'item_id', 'sku', 'outlet', 'warehouse', 'period_from', 'period_to',
    'report_unit', 'report_qty', 'fo_price_stored', 'cost_implied_fo_div_112',
    'matched_gr_number', 'matched_gr_date', 'matched_po_number', 'matched_po_price', 'matched_po_unit',
    'matched_hpp_large', 'matched_sell_large', 'fo_tanggal_ref', 'match_method', 'price_reason',
]);

$stats = ['ok' => 0, 'skip' => 0, 'miss' => 0];
foreach ($list as $i => $name) {
    $item = $items->get($name);
    $rep = $reportRows->get($name);
    if (! $item || ! $rep || (float) $rep->fo_price <= 0) {
        $stats['skip']++;
        fputcsv($fh, [$i + 1, $name, $item->id ?? '', $item->sku ?? '', $outlet, $item->warehouse_name ?? '', $from, $to, '', '', '', '', '', '', '', '', '', '', '', '', '', 'Tidak ada data FO/GR outlet']);
        continue;
    }
    $fo = round((float) $rep->fo_price, 2);
    $cost = round($fo / 1.12, 2);
    $asOf = $rep->fo_tanggal ?: $to;
    $hit = matchGr($item, (int) $item->id, $fo, (string) $rep->unit, $asOf);
    if ($hit) {
        $stats['ok']++;
        $g = $hit['gr'];
        $reason = sprintf(
            'FO simpan Rp %s = jual +12%% dari GR %s (%s), PO %s, beli Rp %s/%s (HPP Rp %s). Angka ~%s di laporan = FO÷1,12.',
            number_format($fo, 0, ',', '.'), $g['gr_number'], $g['receive_date'], $g['po_number'] ?? '-',
            number_format($g['po_price'], 0, ',', '.'), $g['po_unit'] ?? '-',
            number_format($g['cost_large'], 0, ',', '.'), number_format($cost, 0, ',', '.')
        );
        fputcsv($fh, [
            $i + 1, $name, $item->id, $item->sku, $outlet, $item->warehouse_name, $from, $to,
            $rep->unit, number_format((float) $rep->qty, 3, '.', ''),
            number_format($fo, 2, '.', ''), number_format($cost, 2, '.', ''),
            $g['gr_number'], $g['receive_date'], $g['po_number'] ?? '', number_format($g['po_price'], 2, '.', ''), $g['po_unit'] ?? '',
            number_format($g['cost_large'], 2, '.', ''), number_format($g['sell_large'], 2, '.', ''),
            $asOf, $hit['method'], $reason,
        ]);
    } else {
        $stats['miss']++;
        fputcsv($fh, [
            $i + 1, $name, $item->id, $item->sku, $outlet, $item->warehouse_name, $from, $to,
            $rep->unit, number_format((float) $rep->qty, 3, '.', ''),
            number_format($fo, 2, '.', ''), number_format($cost, 2, '.', ''),
            '', '', '', '', '', '', '', $asOf, 'not_found',
            "FO Rp " . number_format($fo, 0, ',', '.') . " belum cocok ke GR pusat ≤ {$asOf}",
        ]);
    }
}
fclose($fh);
echo "CSV: {$out}\nOutlet: {$outlet} | {$from}..{$to}\nok={$stats['ok']} skip={$stats['skip']} miss={$stats['miss']}\n";
