<?php

declare(strict_types=1);

/**
 * Audit harga item di report Rekap FJ detail vs item_prices (SMB, Juni 2026).
 *
 * Usage:
 *   php scripts/check_fj_items_june_2026_smb.php
 *   php scripts/check_fj_items_june_2026_smb.php --csv
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$outletName = 'Justus Steakhouse SMB';
$from = '2026-06-01';
$to = '2026-06-30';
$exportCsv = in_array('--csv', $argv, true);

$itemNames = [
    'Artisan Tea Ceylon Green Tea',
    'Artisan Tea Ginger & Mint',
    'Bawang Goreng',
    'Beef Black Angus Striploin 250gr',
    'Beras Jepang',
    'Beras Lokal',
    'Beras Pandan Wangi',
    'Black Pepper',
    'Black Truffle Paste',
    'Buah Lychee Can',
    'Buah Peach Can',
    'Butter Salted',
    'Chicken Powder',
    'Chilli Flake',
    'Chilli Sachet',
    'Chocolate Compound',
    'Coca Cola Can',
    'Coca Cola Zero Can',
    'Cokelat Powder',
    'Cream Soup Powder',
    'Creamer',
    'Cup 12 Logo SH',
    'Donut Dusting',
    'Equil',
    'Equil Sparkling',
    'Fryall',
    'Garam Refina',
    'Gas Whipped',
    'Gerkin',
    'Guacamole',
    'Gula Pasir',
    'Gula Putih Sachet SH',
    'House Blend',
    'Kacang Arab',
    'Kacang Tanah',
    'Kecap Asin',
    'Kecap Asin Angsa',
    'Kerupuk Emping',
    'Madu',
    'Marjan Lychee',
    'Marjan Strawberry',
    'Marjan Vanilla',
    'Mayonaise',
    'Merica Halus',
    'Milk Evaporate',
    'Minyak Goreng',
    'Minyak Klentik',
    'Nestea Powder',
    'Nutmeg',
    'Olive Oil 500Mili liter',
    'Onion',
    'Oregano',
    'Oyster Sauce',
    'Pasta Fettucine',
    'Pasta Macaroni',
    'Pasta Spaghetti',
    'Plastik Roll Buah 30x45',
    'Plastik Wrap',
    'SKM Putih',
    'Salad Bowl',
    'Salad Oil',
    'Santan',
    'Sauce Chilli Botol',
    'Sauce Chocolate',
    'Sauce Cup',
    'Sauce Cup Dessert',
    'Sea Salt Baby Pyramid',
    'Sedotan',
    'Skewer Burger',
    'Skewer Rotan Simpul',
    'Soda Water',
    'Spinach Bowl',
    'Sticker Botol Aren',
    'Sunquick',
    'Syrup Green Apple',
    'Syrup Gula Aren',
    'Syrup Hazelnut',
    'Syrup Kawista',
    'Syrup Mint',
    'Syrup Peach',
    'Teh Celup',
    'Tepung Beras',
    'Tepung Easy Mix',
    'Tepung Roti Kasar',
    'Tepung Terigu',
    'Thyme',
    'Tomato Peeled',
    'Truffle Oil',
    'Vetcin Powder',
    'Vinegar Dixi',
    'Dishwash',
    'General Cleaner',
    'Greezaway',
    'Hairnet',
    'Hand Soap',
    'Hands Glove Rubber',
    'Masker 3 Ply',
    'HVS 1 Ply 75x65',
    'Label Preparation Roll',
];

function serialEffectivePriceSql(string $itemAlias = 'it'): string
{
    $costSmall = 'COALESCE(si.cost_small, 0)';
    $smallConv = "COALESCE({$itemAlias}.small_conversion_qty, 1)";
    $mediumConv = "COALESCE({$itemAlias}.medium_conversion_qty, 1)";

    return "(CASE
        WHEN si.unit_id = {$itemAlias}.large_unit_id THEN {$costSmall} * {$smallConv} * {$mediumConv}
        WHEN si.unit_id = {$itemAlias}.medium_unit_id THEN {$costSmall} * {$smallConv}
        ELSE {$costSmall}
    END)";
}

function fetchFoodGrReportPrice(int $itemId, string $outlet, string $from, string $to): ?object
{
    return DB::table('outlet_food_good_receives as gr')
        ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
        ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
        ->leftJoin('food_floor_order_items as fo', function ($join) {
            $join->on('i.item_id', '=', 'fo.item_id')
                ->on('fo.floor_order_id', '=', 'do.floor_order_id');
        })
        ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
        ->where('o.nama_outlet', $outlet)
        ->where('i.item_id', $itemId)
        ->whereBetween('gr.receive_date', [$from, $to])
        ->whereNull('gr.deleted_at')
        ->selectRaw('SUM(i.received_qty) as qty, SUM(i.received_qty * COALESCE(fo.price,0)) as subtotal, AVG(COALESCE(fo.price,0)) as avg_price, COUNT(*) as joined_rows')
        ->first();
}

function fetchSerialGrReportPrice(int $itemId, string $outlet, string $from, string $to): ?object
{
    if (! DB::getSchemaBuilder()->hasTable('outlet_serial_receive_headers')) {
        return null;
    }

    $expr = serialEffectivePriceSql();

    return DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
        ->where('o.nama_outlet', $outlet)
        ->where('si.item_id', $itemId)
        ->whereBetween('h.receive_date', [$from, $to])
        ->whereNull('h.deleted_at')
        ->selectRaw("SUM(si.qty) as qty, SUM(si.qty * {$expr}) as subtotal, AVG({$expr}) as avg_price, COUNT(*) as joined_rows")
        ->first();
}

function fetchFoPriceStats(int $itemId, string $from, string $to): object
{
    return DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->whereBetween('ffo.tanggal', [$from, $to])
        ->selectRaw('COUNT(*) as cnt, MIN(ffoi.price) as min_price, MAX(ffoi.price) as max_price')
        ->first();
}

function fetchBadSerialRows(int $itemId, string $outlet, string $from, string $to, float $targetCostSmall): int
{
    if ($targetCostSmall <= 0 || ! DB::getSchemaBuilder()->hasTable('outlet_serial_receive_headers')) {
        return 0;
    }

    return (int) DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
        ->where('si.item_id', $itemId)
        ->where('o.nama_outlet', $outlet)
        ->whereBetween('h.receive_date', [$from, $to])
        ->whereRaw('ABS(COALESCE(si.cost_small,0) - ?) > 0.0001', [$targetCostSmall])
        ->count();
}

echo "=== Audit Rekap FJ vs item_prices ===\n";
echo "Outlet: {$outletName}\n";
echo "Periode: {$from} .. {$to}\n\n";

$rows = [];
$notFound = [];
$ok = 0;
$reportMismatch = 0;
$foMismatch = 0;
$serialMismatch = 0;

foreach ($itemNames as $name) {
    $item = DB::table('items')->where('name', $name)->first();
    if (! $item) {
        $notFound[] = $name;
        continue;
    }

    $itemId = (int) $item->id;
    $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name') ?? 'Pack';
    $expected = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, (string) $mediumUnit);
    $priceRow = DB::table('item_prices')
        ->where('item_id', $itemId)
        ->where('availability_price_type', 'all')
        ->orderByDesc('id')
        ->first();
    $itemPriceLarge = $priceRow ? (float) $priceRow->price : 0.0;

    $food = fetchFoodGrReportPrice($itemId, $outletName, $from, $to);
    $serial = fetchSerialGrReportPrice($itemId, $outletName, $from, $to);
    $foStats = fetchFoPriceStats($itemId, $from, $to);

    $foodQty = (float) ($food->qty ?? 0);
    $serialQty = (float) ($serial->qty ?? 0);
    $totalQty = $foodQty + $serialQty;
    $totalSubtotal = (float) ($food->subtotal ?? 0) + (float) ($serial->subtotal ?? 0);
    $reportPrice = $totalQty > 0 ? $totalSubtotal / $totalQty : 0.0;

    $targetCostSmall = $itemPriceLarge > 0 && (float) ($item->small_conversion_qty ?? 1) > 0
        ? round($itemPriceLarge / (float) $item->small_conversion_qty, 4)
        : 0.0;
    $badSerial = fetchBadSerialRows($itemId, $outletName, $from, $to, $targetCostSmall);

    $foWrong = $expected > 0 && (
        ((float) ($foStats->min_price ?? 0) > 0 && abs((float) $foStats->min_price - $expected) > 1)
        || ((float) ($foStats->max_price ?? 0) > 0 && abs((float) $foStats->max_price - $expected) > 1)
    );

    $reportWrong = $expected > 0 && $totalQty > 0 && abs($reportPrice - $expected) > 100;

    $issue = 'OK';
    if ($reportWrong) {
        $issue = $badSerial > 0 ? 'SERIAL_GR' : ($foWrong ? 'FO' : 'REPORT_MIX');
        $reportMismatch++;
        if ($badSerial > 0) {
            $serialMismatch++;
        }
        if ($foWrong) {
            $foMismatch++;
        }
    } elseif ($foWrong) {
        $issue = 'FO_ONLY';
        $foMismatch++;
    } else {
        $ok++;
    }

    $rows[] = [
        'item_id' => $itemId,
        'item_name' => $name,
        'expected_fo' => $expected,
        'item_prices_large' => $itemPriceLarge,
        'report_price' => round($reportPrice, 2),
        'selisih' => round($reportPrice - $expected, 2),
        'food_qty' => $foodQty,
        'serial_qty' => $serialQty,
        'fo_min' => (float) ($foStats->min_price ?? 0),
        'fo_max' => (float) ($foStats->max_price ?? 0),
        'fo_rows' => (int) ($foStats->cnt ?? 0),
        'bad_serial_rows' => $badSerial,
        'issue' => $issue,
    ];
}

usort($rows, fn ($a, $b) => abs($b['selisih']) <=> abs($a['selisih']));

printf("%-32s %10s %10s %10s %8s %s\n", 'Item', 'Report', 'Expected', 'Selisih', 'Issue', 'Detail');
echo str_repeat('-', 100) . "\n";

foreach ($rows as $r) {
    if ($r['issue'] === 'OK' && abs($r['selisih']) <= 100) {
        continue;
    }
    $detail = "food_qty={$r['food_qty']} serial_qty={$r['serial_qty']} fo={$r['fo_min']}-{$r['fo_max']} bad_serial={$r['bad_serial_rows']}";
    printf(
        "%-32s %10s %10s %10s %8s %s\n",
        mb_substr($r['item_name'], 0, 32),
        number_format($r['report_price'], 0, ',', '.'),
        number_format($r['expected_fo'], 0, ',', '.'),
        number_format($r['selisih'], 0, ',', '.'),
        $r['issue'],
        $detail,
    );
}

echo "\nSummary:\n";
echo 'Items checked: ' . count($rows) . "\n";
echo 'Not found: ' . count($notFound) . "\n";
echo "OK: {$ok}\n";
echo "Report mismatch (>100): {$reportMismatch}\n";
echo "FO mismatch: {$foMismatch}\n";
echo "Serial GR bad rows: {$serialMismatch} items\n";

if ($notFound !== []) {
    echo "\nNot found:\n";
    foreach ($notFound as $n) {
        echo "  - {$n}\n";
    }
}

if ($exportCsv) {
    $path = __DIR__ . '/fj_items_audit_june_2026_smb.csv';
    $fp = fopen($path, 'w');
    fputcsv($fp, array_keys($rows[0] ?? []));
    foreach ($rows as $r) {
        fputcsv($fp, $r);
    }
    fclose($fp);
    echo "\nCSV: {$path}\n";
}
