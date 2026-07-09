<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FoodGrLastPurchaseForItem;
use Illuminate\Support\Facades\DB;

$itemId = 52337;

echo "=== GR HISTORY ===\n";
$grLines = DB::table('food_good_receive_items as gri')
    ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
    ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
    ->leftJoin('units as u', 'u.id', '=', 'gri.unit_id')
    ->where('gri.item_id', $itemId)
    ->orderByDesc('gr.receive_date')
    ->orderByDesc('gr.id')
    ->select('gr.gr_number', 'gr.receive_date', 'poi.price as po_price', 'gri.unit_id', 'u.name as gr_unit', 'gri.id as gri_id')
    ->get();

$item = DB::table('items')->where('id', $itemId)->first();
foreach ($grLines as $g) {
    $cost = (float) $g->po_price;
    $unitId = (int) $g->unit_id;
    $smallConv = (float) ($item->small_conversion_qty ?: 1);
    $mediumConv = (float) ($item->medium_conversion_qty ?: 1);
    $costSmall = $cost;
    if ($unitId === (int) $item->large_unit_id) {
        $costSmall = $cost / ($smallConv * $mediumConv);
    } elseif ($unitId === (int) $item->medium_unit_id) {
        $costSmall = $cost / $smallConv;
    }
    $costLarge = $costSmall * $smallConv * $mediumConv;
    $sell = ceil($costLarge * 1.12 / 100) * 100;
    echo sprintf(
        "%s | %s | po=%s unit=%s(%d) | cost_large=%s | sell+12%%=%s\n",
        $g->receive_date,
        $g->gr_number,
        number_format($cost, 2),
        $g->gr_unit,
        $unitId,
        number_format($costLarge, 2),
        number_format($sell, 2)
    );
}

$last = FoodGrLastPurchaseForItem::lastLine($itemId);
echo "\nlastLine cost_large=" . ($last['cost_large'] ?? '-') . " gr=" . ($last['gr_number'] ?? '-') . "\n";
echo "suggested=" . FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId) . "\n";

echo "\n=== FO PRICES (distinct) ===\n";
$foPrices = DB::table('food_floor_order_items as foi')
    ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
    ->where('foi.item_id', $itemId)
    ->select('foi.price', 'foi.unit', DB::raw('count(*) as cnt'), DB::raw('max(fo.tanggal) as last_date'))
    ->groupBy('foi.price', 'foi.unit')
    ->orderByDesc('last_date')
    ->get();
foreach ($foPrices as $p) {
    echo "price={$p->price} unit={$p->unit} cnt={$p->cnt} last={$p->last_date}\n";
}

echo "\n=== FO near 180000 ===\n";
$near = DB::table('food_floor_order_items as foi')
    ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
    ->where('foi.item_id', $itemId)
    ->whereBetween('foi.price', [175000, 185000])
    ->orderByDesc('fo.tanggal')
    ->limit(10)
    ->get(['fo.order_number', 'fo.tanggal', 'fo.status', 'foi.price', 'foi.unit', 'foi.created_at']);
foreach ($near as $n) {
    echo json_encode($n) . "\n";
}

echo "\n=== PO prices distinct ===\n";
$poPrices = DB::table('purchase_order_food_items as poi')
    ->join('purchase_order_foods as po', 'po.id', '=', 'poi.purchase_order_food_id')
    ->where('poi.item_id', $itemId)
    ->select('poi.price', 'poi.unit_id', 'po.po_number', 'po.order_date')
    ->orderByDesc('po.order_date')
    ->limit(15)
    ->get();
foreach ($poPrices as $p) {
    $u = DB::table('units')->where('id', $p->unit_id)->value('name');
    echo "{$p->order_date} | {$p->po_number} | price={$p->price} unit={$u}\n";
}
