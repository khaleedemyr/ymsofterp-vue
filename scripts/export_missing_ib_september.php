<?php
/**
 * Export Excel: item bergerak / bermuatan yang TIDAK punya initial_balance September.
 * Per baris = outlet + warehouse + item.
 *
 * Definisi "ikut list":
 *  A) Ada kartu non-IB di Sep (bergerak), ATAU
 *  B) Saldo qty>0 dari kartu terakhir sebelum 1 Sep (stok awal tanpa IB)
 * DAN tidak ada kartu initial_balance tanggal 1 Sep untuk outlet+wh+item tsb.
 *
 * Usage:
 *   php scripts/export_missing_ib_september.php
 *   php scripts/export_missing_ib_september.php --month=2026-09 --to=2026-09-22
 *   php scripts/export_missing_ib_september.php --outlet=25
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$month = '2026-09';
$to = null;
$outletOpt = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--month=')) {
        $month = substr($arg, 8);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    }
    if (str_starts_with($arg, '--outlet=')) {
        $outletOpt = (int) substr($arg, 9);
    }
}

$from = $month . '-01';
$to = $to ?: date('Y-m-t', strtotime($from));
$ibDate = $from;

echo "=== Missing IB export ===\n";
echo "IB date: {$ibDate}\n";
echo "Movement window: {$from} .. {$to}\n";
echo 'Outlet: ' . ($outletOpt ?: 'ALL') . "\n\n";

$outletsQ = DB::table('tbl_data_outlet as o')
    ->where('o.status', 'A')
    ->orderBy('o.nama_outlet');
if ($outletOpt) {
    $outletsQ->where('o.id_outlet', $outletOpt);
}
$outlets = $outletsQ->get(['o.id_outlet', 'o.nama_outlet']);

$rows = [];
foreach ($outlets as $outlet) {
    $outletId = (int) $outlet->id_outlet;
    $warehouses = DB::table('warehouse_outlets')
        ->where('outlet_id', $outletId)
        ->where('status', 'active')
        ->get(['id', 'name']);
    if ($warehouses->isEmpty()) {
        continue;
    }
    $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();
    $whName = $warehouses->pluck('name', 'id');

    echo "  {$outlet->nama_outlet} (#{$outletId})...\n";

    // IB keys on day-1
    $ibKeys = DB::table('outlet_food_inventory_cards')
        ->where('id_outlet', $outletId)
        ->whereIn('warehouse_outlet_id', $warehouseIds)
        ->where('reference_type', 'initial_balance')
        ->whereDate('date', $ibDate)
        ->selectRaw('CONCAT(warehouse_outlet_id, "|", inventory_item_id) as k')
        ->pluck('k')
        ->flip()
        ->all();

    // Keys that moved in period (non-IB cards)
    $moved = DB::table('outlet_food_inventory_cards')
        ->where('id_outlet', $outletId)
        ->whereIn('warehouse_outlet_id', $warehouseIds)
        ->whereBetween(DB::raw('DATE(date)'), [$from, $to])
        ->where('reference_type', '!=', 'initial_balance')
        ->selectRaw('
            warehouse_outlet_id,
            inventory_item_id,
            SUM(COALESCE(value_in,0)) as vin,
            SUM(COALESCE(value_out,0)) as vout,
            SUM(COALESCE(in_qty_small,0)) as in_qty,
            SUM(COALESCE(out_qty_small,0)) as out_qty,
            COUNT(*) as card_count
        ')
        ->groupBy('warehouse_outlet_id', 'inventory_item_id')
        ->get();

    $movedMap = [];
    foreach ($moved as $m) {
        $k = $m->warehouse_outlet_id . '|' . $m->inventory_item_id;
        $movedMap[$k] = $m;
    }

    // Last card before IB date (opening position)
    $beforeLatest = DB::table('outlet_food_inventory_cards as c')
        ->where('c.id_outlet', $outletId)
        ->whereIn('c.warehouse_outlet_id', $warehouseIds)
        ->whereDate('c.date', '<', $ibDate)
        ->selectRaw("c.inventory_item_id, c.warehouse_outlet_id, MAX(CONCAT(DATE(c.date),' ',LPAD(c.id,20,'0'))) as mk")
        ->groupBy('c.inventory_item_id', 'c.warehouse_outlet_id');

    $beforeCards = DB::table('outlet_food_inventory_cards as c')
        ->joinSub($beforeLatest, 'b', function ($j) {
            $j->on('b.inventory_item_id', '=', 'c.inventory_item_id')
                ->on('b.warehouse_outlet_id', '=', 'c.warehouse_outlet_id');
        })
        ->whereRaw("CONCAT(DATE(c.date),' ',LPAD(c.id,20,'0')) = b.mk")
        ->get([
            'c.inventory_item_id',
            'c.warehouse_outlet_id',
            'c.date',
            'c.reference_type',
            'c.saldo_qty_small',
            'c.saldo_value',
            'c.cost_per_small',
        ]);

    $beforeMap = [];
    foreach ($beforeCards as $c) {
        $k = $c->warehouse_outlet_id . '|' . $c->inventory_item_id;
        $beforeMap[$k] = $c;
    }

    // Candidate keys = moved OR before qty>0
    $candidateKeys = array_unique(array_merge(array_keys($movedMap), array_keys($beforeMap)));

    $invIds = [];
    foreach ($candidateKeys as $k) {
        if (isset($ibKeys[$k])) {
            continue;
        }
        $before = $beforeMap[$k] ?? null;
        $movedRow = $movedMap[$k] ?? null;
        $beforeQty = $before ? (float) $before->saldo_qty_small : 0.0;
        $hasMove = $movedRow !== null;
        $hasOpeningStock = abs($beforeQty) > 0.00005;
        if (! $hasMove && ! $hasOpeningStock) {
            continue;
        }
        [, $invId] = explode('|', $k);
        $invIds[(int) $invId] = true;
    }
    $invIdList = array_keys($invIds);

    $itemMeta = [];
    if ($invIdList !== []) {
        foreach (array_chunk($invIdList, 500) as $chunk) {
            $meta = DB::table('outlet_food_inventory_items as ii')
                ->join('items as i', 'ii.item_id', '=', 'i.id')
                ->leftJoin('categories as cat', 'i.category_id', '=', 'cat.id')
                ->whereIn('ii.id', $chunk)
                ->get([
                    'ii.id as inventory_item_id',
                    'i.id as item_id',
                    'i.sku',
                    'i.name as item_name',
                    'cat.name as category_name',
                ]);
            foreach ($meta as $m) {
                $itemMeta[(int) $m->inventory_item_id] = $m;
            }
        }
    }

    foreach ($candidateKeys as $k) {
        if (isset($ibKeys[$k])) {
            continue;
        }
        [$whId, $invId] = array_map('intval', explode('|', $k));
        $before = $beforeMap[$k] ?? null;
        $movedRow = $movedMap[$k] ?? null;
        $beforeQty = $before ? (float) $before->saldo_qty_small : 0.0;
        $hasMove = $movedRow !== null;
        $hasOpeningStock = abs($beforeQty) > 0.00005;
        if (! $hasMove && ! $hasOpeningStock) {
            continue;
        }

        $meta = $itemMeta[$invId] ?? null;
        $flags = [];
        if ($hasOpeningStock) {
            $flags[] = 'stok_awal_tanpa_ib';
        }
        if ($hasMove) {
            $flags[] = 'bergerak_sep';
        }

        $rows[] = [
            'outlet_id' => $outletId,
            'outlet_name' => $outlet->nama_outlet,
            'warehouse_id' => $whId,
            'warehouse_name' => (string) ($whName[$whId] ?? $whId),
            'inventory_item_id' => $invId,
            'item_id' => $meta->item_id ?? null,
            'sku' => $meta->sku ?? '',
            'item_name' => $meta->item_name ?? ('inv#' . $invId),
            'category' => $meta->category_name ?? '',
            'flag' => implode('+', $flags),
            'before_date' => $before ? substr((string) $before->date, 0, 10) : '',
            'before_ref' => $before->reference_type ?? '',
            'before_qty' => $beforeQty,
            'before_value' => $before ? (float) $before->saldo_value : 0.0,
            'before_cost' => $before ? (float) $before->cost_per_small : 0.0,
            'sep_cards' => $movedRow ? (int) $movedRow->card_count : 0,
            'sep_in_qty' => $movedRow ? (float) $movedRow->in_qty : 0.0,
            'sep_out_qty' => $movedRow ? (float) $movedRow->out_qty : 0.0,
            'sep_value_in' => $movedRow ? (float) $movedRow->vin : 0.0,
            'sep_value_out' => $movedRow ? (float) $movedRow->vout : 0.0,
        ];
    }
}

usort($rows, function ($a, $b) {
    return [$a['outlet_name'], $a['warehouse_name'], $a['item_name']]
        <=> [$b['outlet_name'], $b['warehouse_name'], $b['item_name']];
});

echo "\nTotal rows: ".count($rows)."\n";

$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Missing IB Sep');

$headers = [
    'Outlet ID', 'Outlet', 'Warehouse ID', 'Warehouse',
    'Inv Item ID', 'Item ID', 'SKU', 'Item Name', 'Category',
    'Flag',
    'Last Card Before IB Date', 'Last Ref Before IB',
    'Opening Qty (before IB)', 'Opening Value', 'Opening Cost/Small',
    'Sep Card Count', 'Sep In Qty', 'Sep Out Qty', 'Sep Value In', 'Sep Value Out',
];
$sheet->fromArray($headers, null, 'A1');

$r = 2;
foreach ($rows as $row) {
    $sheet->fromArray([
        $row['outlet_id'],
        $row['outlet_name'],
        $row['warehouse_id'],
        $row['warehouse_name'],
        $row['inventory_item_id'],
        $row['item_id'],
        $row['sku'],
        $row['item_name'],
        $row['category'],
        $row['flag'],
        $row['before_date'],
        $row['before_ref'],
        $row['before_qty'],
        $row['before_value'],
        $row['before_cost'],
        $row['sep_cards'],
        $row['sep_in_qty'],
        $row['sep_out_qty'],
        $row['sep_value_in'],
        $row['sep_value_out'],
    ], null, 'A' . $r);
    $r++;
}

$headerStyle = $sheet->getStyle('A1:T1');
$headerStyle->getFont()->setBold(true);
$headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
$headerStyle->getFont()->getColor()->setRGB('FFFFFF');
$headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

foreach (range('A', 'T') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Summary sheet
$sum = $ss->createSheet();
$sum->setTitle('Summary');
$sum->fromArray(['Outlet', 'Warehouse', 'Item Count', 'Opening Value (no IB)', 'Sep Value In', 'Sep Value Out'], null, 'A1');
$sum->getStyle('A1:F1')->getFont()->setBold(true);

$agg = [];
foreach ($rows as $row) {
    $key = $row['outlet_name'] . '|' . $row['warehouse_name'];
    if (! isset($agg[$key])) {
        $agg[$key] = [
            'outlet' => $row['outlet_name'],
            'wh' => $row['warehouse_name'],
            'n' => 0,
            'open' => 0.0,
            'vin' => 0.0,
            'vout' => 0.0,
        ];
    }
    $agg[$key]['n']++;
    $agg[$key]['open'] += $row['before_value'];
    $agg[$key]['vin'] += $row['sep_value_in'];
    $agg[$key]['vout'] += $row['sep_value_out'];
}
ksort($agg);
$sr = 2;
foreach ($agg as $a) {
    $sum->fromArray([$a['outlet'], $a['wh'], $a['n'], $a['open'], $a['vin'], $a['vout']], null, 'A' . $sr);
    $sr++;
}
foreach (range('A', 'F') as $col) {
    $sum->getColumnDimension($col)->setAutoSize(true);
}

$dir = storage_path('app/exports');
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$file = $dir . '/missing_ib_' . str_replace('-', '', $month) . '_' . date('Ymd_His') . '.xlsx';
(new Xlsx($ss))->save($file);

echo "Saved: {$file}\n";
echo "Summary groups: ".count($agg)."\n";
