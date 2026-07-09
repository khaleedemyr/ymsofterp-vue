<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FoodGrLastPurchaseForItem;
use App\Support\FloorOrderItemPriceResolver;
use App\Support\ItemUnitCost;
use App\Support\SerialReceiveItemPriceResolver;
use Illuminate\Support\Facades\DB;

$itemId = 52337;
$gsrNumber = $argv[1] ?? 'GSR-20260702-0079';

$item = DB::table('items as i')
    ->leftJoin('units as us', 'us.id', '=', 'i.small_unit_id')
    ->leftJoin('units as um', 'um.id', '=', 'i.medium_unit_id')
    ->leftJoin('units as ul', 'ul.id', '=', 'i.large_unit_id')
    ->where('i.id', $itemId)
    ->select('i.*', 'us.name as small_unit', 'um.name as medium_unit', 'ul.name as large_unit')
    ->first();

echo "=== Trace Serial GR: {$gsrNumber} | Black Pepper ===\n\n";

$gsi = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
    ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
    ->where('h.number', $gsrNumber)
    ->where('si.item_id', $itemId)
    ->select('si.*', 'h.number', 'h.receive_date', 'o.nama_outlet', 'u.name as unit_name')
    ->first();

if (! $gsi) {
    echo "GSR item not found\n";
    exit(1);
}

echo "GSR: {$gsi->number} | {$gsi->receive_date} | {$gsi->nama_outlet}\n";
echo "Stored cost_small={$gsi->cost_small} | cost_source={$gsi->cost_source} | qty={$gsi->qty} {$gsi->unit_name}\n";
$lineCost = ItemUnitCost::priceForUnit((float) $gsi->cost_small, $item, $gsi->unit_id);
echo "Line cost (priceForUnit) = " . number_format($lineCost, 2) . " per {$gsi->unit_name}\n\n";

$serialIds = json_decode($gsi->serial_ids ?? '[]', true);
if (! is_array($serialIds)) {
    $serialIds = [];
}
if ($serialIds === [] && ! empty($gsi->serial_id)) {
    $serialIds = [(int) $gsi->serial_id];
}

echo "--- inventory_item_serials (sumber cost) ---\n";
$serials = DB::table('inventory_item_serials as s')
    ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
    ->whereIn('s.id', $serialIds ?: [0])
    ->select('s.id', 's.serial_number', 's.cost_small', 's.source_type', 's.unit_id', 'u.name as unit', 's.out_delivery_order_id', 's.created_at')
    ->get();

foreach ($serials as $s) {
    echo json_encode($s, JSON_UNESCAPED_UNICODE) . "\n";
    if ($s->out_delivery_order_id) {
        $do = DB::table('delivery_orders as do')
            ->leftJoin('food_floor_orders as fo', 'fo.id', '=', 'do.floor_order_id')
            ->leftJoin('food_floor_order_items as foi', function ($j) use ($itemId) {
                $j->on('foi.floor_order_id', '=', 'fo.id')->where('foi.item_id', '=', $itemId);
            })
            ->where('do.id', $s->out_delivery_order_id)
            ->select('do.number as do_number', 'fo.order_number', 'fo.tanggal', 'foi.price as fo_price', 'foi.id as foi_id')
            ->first();
        echo "  DO: " . json_encode($do, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n--- Resolver recompute sekarang ---\n";
$serial = $serials->first();
if ($serial) {
    $serialObj = DB::table('inventory_item_serials as s')
        ->join('items as i', 'i.id', '=', 's.item_id')
        ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
        ->where('s.id', $serial->id)
        ->select('s.*', 'i.name as item_name', 'u.name as unit_name')
        ->first();
    [$cost, $key, $label] = SerialReceiveItemPriceResolver::resolveCostSmall($itemId, $item, $serialObj, $gsi->outlet_id ?? null);
    echo "resolveCostSmall => cost_small={$cost} | source={$key} | label={$label}\n";
    echo "unit_price=" . ItemUnitCost::priceForUnit($cost, $item, $gsi->unit_id) . "\n";
}

echo "\n--- GR Pusat saat GSR dibuat ({$gsi->receive_date}) ---\n";
$grAtDate = DB::table('food_good_receive_items as gri')
    ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
    ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
    ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
    ->where('gri.item_id', $itemId)
    ->whereDate('gr.receive_date', '<=', $gsi->receive_date)
    ->orderByDesc('gr.receive_date')
    ->orderByDesc('gr.id')
    ->select('gr.gr_number', 'gr.receive_date', 'po.number as po_number', 'poi.price as po_price')
    ->first();
if ($grAtDate) {
    $sell = FloorOrderItemPriceResolver::roundUpToHundred((float) $grAtDate->po_price * 1.12);
    $costSmallCalc = $sell / ((float) $item->small_conversion_qty * (float) $item->medium_conversion_qty);
    echo "GR terakhir ≤ {$gsi->receive_date}: {$grAtDate->gr_number} | beli=" . number_format((float) $grAtDate->po_price, 0) . " | sell+12%=" . number_format($sell, 0) . "\n";
    echo "cost_small dari sell/1000 = {$costSmallCalc}\n";
}

echo "\n--- FoodGrLastPurchaseForItem sekarang ---\n";
$last = FoodGrLastPurchaseForItem::lastLine($itemId);
$sellNow = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
echo "last GR: " . ($last['gr_number'] ?? '-') . " | cost_large=" . ($last['cost_large'] ?? 0) . " | sell=" . $sellNow . "\n";
echo "cost_small from sell now = " . ($sellNow / 1000) . "\n";
