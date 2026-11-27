<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$outletId = 18;
$whId = 41;
$itemId = 52984;
$invItemId = 5080;

$qr = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('qr_code');
echo "qr={$qr}\n";
$oiCols = Schema::getColumnListing('order_items');
echo "order_items cols: " . implode(',', $oiCols) . "\n";

$itemCol = in_array('item_id', $oiCols) ? 'item_id' : null;
$qtyCol = in_array('qty', $oiCols) ? 'qty' : null;

echo "\n=== Engineering 19 Nov & 25 Nov from order_items × BOM ===\n";
$parents = DB::table('item_bom as b')
    ->join('items as i', 'i.id', '=', 'b.item_id')
    ->where('b.material_item_id', $itemId)
    ->where(function ($q) {
        $q->whereNull('b.stock_cut')->orWhere('b.stock_cut', 0);
    })
    ->get(['b.item_id', 'b.qty', 'b.unit_id', 'b.stock_cut', 'i.name', 'i.type']);

foreach (['2026-11-19', '2026-11-25'] as $day) {
    echo "-- {$day} --\n";
    $total = 0;
    foreach ($parents as $p) {
        $q = DB::table('order_items')
            ->where('kode_outlet', $qr)
            ->whereDate('created_at', $day)
            ->where('item_id', $p->item_id);
        if (in_array('stock_cut', $oiCols)) {
            // show both
        }
        $sold = (float) $q->sum('qty');
        $need = $sold * (float)$p->qty;
        $total += $need;
        if ($sold > 0) {
            echo sprintf("  %s sold=%s × bom=%s = %sg (%s pcs)\n", $p->name, $sold, $p->qty, $need, $need/150);
        }
    }
    echo "  TOTAL material grams={$total} pcs=" . ($total/150) . "\n";
}

echo "\n=== Modifier BOM contribution? ===\n";
// Check if modifiers table exists and uses this material
foreach (['modifier_bom', 'item_modifier_bom', 'order_modifiers'] as $t) {
    if (Schema::hasTable($t)) echo "has {$t}: " . implode(',', Schema::getColumnListing($t)) . "\n";
}

echo "\n=== stock_cut_details for ALL items that could explain 1200 silent loss - cards missing same day? ===\n";
// Compare: for outlet 18 kitchen on 19 Nov, find items where stock_cut detail stock_before != previous card saldo
$details = DB::table('stock_cut_details as d')
    ->join('stock_cut_logs as l', 'l.id', '=', 'd.stock_cut_log_id')
    ->join('items as i', 'i.id', '=', 'd.item_id')
    ->where('l.id', 12032)
    ->where('d.warehouse_outlet_id', $whId)
    ->select('d.*', 'i.name')
    ->get();

$driftItems = [];
foreach ($details as $d) {
    $prev = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $d->inventory_item_id)
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $whId)
        ->where('created_at', '<', '2026-11-19 22:35:59')
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->first();
    if (!$prev) continue;
    $drift = (float)$d->stock_before_small - (float)$prev->saldo_qty_small;
    if (abs($drift) > 0.01) {
        $driftItems[] = [
            'item' => $d->name,
            'item_id' => $d->item_id,
            'stock_before' => $d->stock_before_small,
            'card_saldo' => $prev->saldo_qty_small,
            'drift' => $drift,
            'needed' => $d->qty_small,
            'prev_card' => $prev->id,
            'prev_ref' => $prev->reference_type,
        ];
    }
}
echo "items with stock_before != card_saldo at food cut 19 Nov: " . count($driftItems) . "\n";
usort($driftItems, fn($a,$b) => abs($b['drift']) <=> abs($a['drift']));
foreach (array_slice($driftItems, 0, 30) as $r) {
    echo sprintf(
        "  %s(%d) stock=%s card=%s drift=%s needed=%s prev_card=%d/%s\n",
        $r['item'], $r['item_id'], $r['stock_before'], $r['card_saldo'], $r['drift'], $r['needed'], $r['prev_card'], $r['prev_ref']
    );
}

echo "\n=== Check ReconcileOutletFoodInventoryCardSaldo runs / schedule ===\n";
// just note command exists

echo "\n=== PosSync or other writers around 19 Nov for stock id 23315 ===\n";
if (Schema::hasTable('activity_log')) {
    $rows = DB::table('activity_log')
        ->whereBetween('created_at', ['2026-11-19 10:00:00', '2026-11-19 23:00:00'])
        ->where(function ($q) {
            $q->where('properties', 'like', '%23315%')
              ->orWhere('properties', 'like', '%52984%')
              ->orWhere('properties', 'like', '%inventory_item_id\":5080%')
              ->orWhere('properties', 'like', '%"inventory_item_id":5080%');
        })
        ->limit(20)
        ->get();
    echo "activity hits=" . $rows->count() . "\n";
    foreach ($rows as $r) echo substr(json_encode($r), 0, 350) . "\n";
}

echo "\n=== Serial receive headers completed 19 Nov outlet 18 for this item besides 22872 ===\n";
$hrs = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as i', 'i.header_id', '=', 'h.id')
    ->where('h.outlet_id', $outletId)
    ->whereDate('h.receive_date', '2026-11-19')
    ->where('i.item_id', $itemId)
    ->select('h.*', 'i.qty', 'i.serial_number', 'i.warehouse_outlet_id')
    ->get();
foreach ($hrs as $h) echo json_encode($h) . "\n";

echo "\nDONE\n";
