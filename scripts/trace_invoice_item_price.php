<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', [
    'item::',
    'gr::',
    'ro::',
    'outlet::',
    'from::',
    'to::',
    'limit::',
]);

$itemName = $options['item'] ?? 'Sauce Blueberry';
$grNumber = $options['gr'] ?? null;
$roNumber = $options['ro'] ?? null;
$outletName = $options['outlet'] ?? null;
$fromDate = $options['from'] ?? null;
$toDate = $options['to'] ?? null;
$limit = isset($options['limit']) ? max((int) $options['limit'], 1) : 20;

$grQuery = DB::table('outlet_food_good_receive_items as gri')
    ->join('items as i', 'gri.item_id', '=', 'i.id')
    ->join('units as u', 'gri.unit_id', '=', 'u.id')
    ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
    ->leftJoin('food_floor_order_items as ffoi', function ($join) {
        $join->on('do.floor_order_id', '=', 'ffoi.floor_order_id')
            ->on('gri.item_id', '=', 'ffoi.item_id');
    })
    ->whereNull('gr.deleted_at')
    ->where('gr.status', 'completed')
    ->where('i.name', 'like', '%' . $itemName . '%');

if ($grNumber) {
    $grQuery->where('gr.number', $grNumber);
}
if ($roNumber) {
    $grQuery->where('ffo.order_number', $roNumber);
}
if ($outletName) {
    $grQuery->where('o.nama_outlet', 'like', '%' . $outletName . '%');
}
if ($fromDate) {
    $grQuery->whereDate('gr.created_at', '>=', $fromDate);
}
if ($toDate) {
    $grQuery->whereDate('gr.created_at', '<=', $toDate);
}

$grRows = $grQuery
    ->orderByDesc('gr.created_at')
    ->limit($limit)
    ->select([
        'gr.id as gr_id',
        'gr.number as gr_number',
        'gr.created_at as gr_created_at',
        'gr.receive_date as gr_receive_date',
        'o.nama_outlet as outlet_name',
        'do.id as delivery_order_id',
        'do.number as delivery_order_number',
        'ffo.id as floor_order_id',
        'ffo.order_number as ro_number',
        'gri.id as gr_item_id',
        'gri.item_id',
        'i.name as item_name',
        'gri.received_qty',
        'u.name as unit_name',
        'ffoi.id as floor_order_item_id',
        'ffoi.price as floor_order_item_price',
        DB::raw('COALESCE(gri.received_qty, 0) * COALESCE(ffoi.price, 0) as subtotal_by_report_logic'),
    ])
    ->get();

$gsrQuery = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('delivery_orders as do', 'si.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
    ->whereNull('h.deleted_at')
    ->where('h.status', 'completed')
    ->where('it.name', 'like', '%' . $itemName . '%');

if ($grNumber) {
    $gsrQuery->where('h.number', $grNumber);
}
if ($roNumber) {
    $gsrQuery->where('ffo.order_number', $roNumber);
}
if ($outletName) {
    $gsrQuery->where('o.nama_outlet', 'like', '%' . $outletName . '%');
}
if ($fromDate) {
    $gsrQuery->whereDate('h.created_at', '>=', $fromDate);
}
if ($toDate) {
    $gsrQuery->whereDate('h.created_at', '<=', $toDate);
}

$gsrRows = $gsrQuery
    ->orderByDesc('h.created_at')
    ->limit($limit)
    ->select([
        'h.id as gsr_id',
        'h.number as gsr_number',
        'h.created_at as gsr_created_at',
        'h.receive_date as gsr_receive_date',
        'o.nama_outlet as outlet_name',
        'do.id as delivery_order_id',
        'do.number as delivery_order_number',
        'ffo.id as floor_order_id',
        'ffo.order_number as ro_number',
        'si.id as gsr_item_id',
        'si.item_id',
        'it.name as item_name',
        'si.qty as received_qty',
        'si.cost_small',
        'si.unit_id as receive_unit_id',
        'it.small_unit_id',
        'it.medium_unit_id',
        'it.large_unit_id',
        'it.small_conversion_qty',
        'it.medium_conversion_qty',
        DB::raw('COALESCE(u.name, "-") as unit_name'),
        DB::raw('(CASE
            WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1) * COALESCE(it.medium_conversion_qty, 1)
            WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
            ELSE COALESCE(si.cost_small, 0)
        END) as effective_price_by_report_logic'),
        DB::raw('(si.qty * (CASE
            WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1) * COALESCE(it.medium_conversion_qty, 1)
            WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
            ELSE COALESCE(si.cost_small, 0)
        END)) as subtotal_by_report_logic'),
    ])
    ->get();

if ($grRows->isEmpty() && $gsrRows->isEmpty()) {
    echo "Tidak ada data ditemukan dengan filter saat ini.\n";
    echo "Coba jalankan tanpa filter gr/ro/outlet atau atur rentang tanggal.\n";
    exit(0);
}

$itemIds = $grRows->pluck('item_id')->merge($gsrRows->pluck('item_id'))->unique()->values();
$floorOrderIds = $grRows->pluck('floor_order_id')->merge($gsrRows->pluck('floor_order_id'))->filter()->unique()->values();
$grIds = $grRows->pluck('gr_id')->unique()->values();
$outletIds = DB::table('outlet_food_good_receives as gr')
    ->whereIn('gr.id', $grIds)
    ->pluck('gr.outlet_id')
    ->unique()
    ->values();

$gsrOutletIds = $gsrRows->pluck('outlet_name')->filter()->values();
if ($outletIds->isEmpty() && $gsrOutletIds->isNotEmpty()) {
    $outletIds = DB::table('tbl_data_outlet')
        ->whereIn('nama_outlet', $gsrOutletIds)
        ->pluck('id_outlet')
        ->unique()
        ->values();
}

$foItemDuplicates = collect();
if ($floorOrderIds->isNotEmpty() && $itemIds->isNotEmpty()) {
    $foItemDuplicates = DB::table('food_floor_order_items')
        ->whereIn('floor_order_id', $floorOrderIds)
        ->whereIn('item_id', $itemIds)
        ->select('floor_order_id', 'item_id', DB::raw('COUNT(*) as row_count'))
        ->groupBy('floor_order_id', 'item_id')
        ->havingRaw('COUNT(*) > 1')
        ->get();
}

$foItemRawRows = collect();
if ($floorOrderIds->isNotEmpty() && $itemIds->isNotEmpty()) {
    $foItemRawRows = DB::table('food_floor_order_items')
        ->whereIn('floor_order_id', $floorOrderIds)
        ->whereIn('item_id', $itemIds)
        ->select('id', 'floor_order_id', 'item_id', 'price', 'qty', 'unit', 'created_at', 'updated_at')
        ->orderBy('floor_order_id')
        ->orderBy('item_id')
        ->orderBy('id')
        ->get();
}

$itemPriceRows = collect();
if ($itemIds->isNotEmpty()) {
    $itemPriceColumns = Schema::hasTable('item_prices') ? Schema::getColumnListing('item_prices') : [];
    $selectedItemPriceColumns = array_values(array_intersect(
        ['id', 'item_id', 'outlet_id', 'availability_price_type', 'price', 'effective_date', 'created_at', 'updated_at'],
        $itemPriceColumns
    ));

    if (!empty($selectedItemPriceColumns)) {
        $itemPriceQuery = DB::table('item_prices')
            ->whereIn('item_id', $itemIds)
            ->where(function ($query) use ($outletIds) {
                if ($outletIds->isNotEmpty()) {
                    $query->whereIn('outlet_id', $outletIds)->orWhereNull('outlet_id');
                } else {
                    $query->whereNull('outlet_id');
                }
            })
            ->select($selectedItemPriceColumns);

        if (in_array('effective_date', $itemPriceColumns, true)) {
            $itemPriceQuery->orderByDesc('effective_date');
        }
        if (in_array('created_at', $itemPriceColumns, true)) {
            $itemPriceQuery->orderByDesc('created_at');
        }
        if (in_array('id', $itemPriceColumns, true)) {
            $itemPriceQuery->orderByDesc('id');
        }

        $itemPriceRows = $itemPriceQuery->get();
    }
}

$promoRows = collect();
if ($itemIds->isNotEmpty()) {
    $promoColumns = Schema::hasTable('promos') ? Schema::getColumnListing('promos') : [];
    $promoNameColumn = null;
    foreach (['nama_promo', 'name', 'promo_name', 'title'] as $candidate) {
        if (in_array($candidate, $promoColumns, true)) {
            $promoNameColumn = $candidate;
            break;
        }
    }

    $promoSelect = [
        'pip.id',
        'pip.promo_id',
        'pip.item_id',
        'pip.outlet_id',
        'pip.region_id',
        'pip.old_price',
        'pip.new_price',
        'pip.created_at',
    ];

    if ($promoNameColumn) {
        $promoSelect[] = DB::raw('p.' . $promoNameColumn . ' as promo_name');
    }

    $promoRows = DB::table('promo_item_prices as pip')
        ->leftJoin('promos as p', 'pip.promo_id', '=', 'p.id')
        ->whereIn('pip.item_id', $itemIds)
        ->select($promoSelect)
        ->orderByDesc('pip.id')
        ->limit(100)
        ->get();
}

$result = [
    'filters' => [
        'item' => $itemName,
        'gr' => $grNumber,
        'ro' => $roNumber,
        'outlet' => $outletName,
        'from' => $fromDate,
        'to' => $toDate,
        'limit' => $limit,
    ],
    'summary' => [
        'matched_gr_rows' => $grRows->count(),
        'matched_gsr_rows' => $gsrRows->count(),
        'distinct_gr' => $grRows->pluck('gr_id')->unique()->count(),
        'distinct_gsr' => $gsrRows->pluck('gsr_id')->unique()->count(),
        'distinct_floor_orders' => $floorOrderIds->count(),
        'distinct_items' => $itemIds->count(),
        'duplicate_floor_order_item_pairs' => $foItemDuplicates->count(),
    ],
    'gr_rows_used_by_report_join' => $grRows,
    'gsr_rows_used_by_report_formula' => $gsrRows,
    'duplicate_pairs_floor_order_item' => $foItemDuplicates,
    'raw_food_floor_order_items_for_matched_pairs' => $foItemRawRows,
    'related_item_prices' => $itemPriceRows,
    'related_promo_item_prices' => $promoRows,
    'note' => [
        'Di Report Invoice Outlet tipe GR, harga item diambil dari food_floor_order_items.price lewat join floor_order_id + item_id.',
        'Di Report Invoice Outlet tipe GSR, harga item diambil dari outlet_serial_receive_items.cost_small yang dikonversi berdasarkan unit (small/medium/large).',
        'Jika ada lebih dari 1 baris food_floor_order_items untuk pasangan floor_order_id + item_id yang sama, join dapat menggandakan baris detail.',
        'item_prices dan promo_item_prices ditampilkan sebagai referensi, bukan sumber harga utama report ini.',
    ],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
