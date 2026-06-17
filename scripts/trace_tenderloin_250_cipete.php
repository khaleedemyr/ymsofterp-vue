<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FoodGrLastPurchaseForItem;
use App\Support\ItemUnitCost;
use Illuminate\Support\Facades\DB;

$itemId = 52985;
$customer = 'Justus Steak House Cipete';

$item = DB::table('items')->where('id', $itemId)->first();
if (! $item) {
    echo "Item not found\n";
    exit(1);
}

echo "=== {$item->name} (ID {$itemId}) ===\n";
echo "item small_unit_id={$item->small_unit_id} medium_unit_id={$item->medium_unit_id} large_unit_id={$item->large_unit_id}\n\n";

$prices = DB::table('item_prices')->where('item_id', $itemId)->get();
foreach ($prices as $p) {
    echo "item_prices id={$p->id} type={$p->availability_price_type} price={$p->price} mode=" . ($p->pricing_mode ?? 'n/a') . "\n";
}

$priceLarge = (float) (DB::table('item_prices')->where('item_id', $itemId)->where('availability_price_type', 'all')->value('price') ?? 0);
$smallConv = (float) ($item->small_conversion_qty ?: 1);
$expectedCostSmall = round($priceLarge / $smallConv, 4);
$expectedPcs = ItemUnitCost::priceForUnit($expectedCostSmall, $item, $item->small_unit_id);
echo "\nExpected manual: large={$priceLarge} => cost_small={$expectedCostSmall} => Rp " . number_format($expectedPcs, 2) . "/Pcs\n";

$gr = FoodGrLastPurchaseForItem::lastLine($itemId);
$autoLarge = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
echo "FGR+12% suggested large: " . ($autoLarge ? number_format($autoLarge, 2) : 'n/a') . "\n";
if ($gr) {
    $autoPcs = ItemUnitCost::priceForUnit((float) $gr['cost_small'], $item, $item->small_unit_id);
    echo "Last FGR cost_small={$gr['cost_small']} => Rp " . number_format($autoPcs, 2) . "/Pcs (before markup on large)\n";
}

echo "\n=== GSR {$customer} ===\n";

$rows = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', $customer)
    ->where('si.item_id', $itemId)
    ->whereNull('h.deleted_at')
    ->select('h.receive_date', 'si.qty', 'si.cost_small', 'si.cost_source', 'si.unit_id', 'u.name as unit_name')
    ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
    ->orderByDesc('h.receive_date')
    ->limit(15)
    ->get();

foreach ($rows as $r) {
    $unitPrice = ItemUnitCost::priceForUnit((float) $r->cost_small, $item, $r->unit_id);
    echo "{$r->receive_date} qty={$r->qty} unit={$r->unit_name}({$r->unit_id}) cost_small={$r->cost_small} src={$r->cost_source} => Rp " . number_format($unitPrice, 2) . "/unit\n";
}

$dist = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', $customer)
    ->where('si.item_id', $itemId)
    ->whereNull('h.deleted_at')
    ->select('si.cost_small', 'si.cost_source', DB::raw('SUM(si.qty) as qty'))
    ->groupBy('si.cost_small', 'si.cost_source')
    ->orderByDesc('qty')
    ->get();

echo "\nDistinct GSR cost_small (all time):\n";
$totalQty = 0;
$totalSub = 0.0;
foreach ($dist as $d) {
    $up = ItemUnitCost::priceForUnit((float) $d->cost_small, $item, $item->small_unit_id);
    $sub = $up * (float) $d->qty;
    $totalQty += (float) $d->qty;
    $totalSub += $sub;
    echo "  cost_small={$d->cost_small} src={$d->cost_source} qty={$d->qty} => Rp " . number_format($up, 2) . "/Pcs sub=" . number_format($sub, 2) . "\n";
}

if ($totalQty > 0) {
    echo "\nWeighted avg unit price: Rp " . number_format($totalSub / $totalQty, 2) . " (qty {$totalQty})\n";
}

// Report-style AVG (same SQL as rekapFj)
$effectivePriceExpr = "(CASE
    WHEN si.unit_id = it.small_unit_id THEN COALESCE(si.cost_small, 0)
    WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
    ELSE COALESCE(si.cost_small, 0)
END)";

$reportRow = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', $customer)
    ->where('si.item_id', $itemId)
    ->whereNull('h.deleted_at')
    ->selectRaw("SUM(si.qty) as received_qty, AVG({$effectivePriceExpr}) as price, SUM(si.qty * {$effectivePriceExpr}) as subtotal")
    ->first();

echo "\nReport detail (AVG price): qty={$reportRow->received_qty} price=" . number_format((float) $reportRow->price, 2) . " subtotal=" . number_format((float) $reportRow->subtotal, 2) . "\n";

echo "\nDone.\n";
