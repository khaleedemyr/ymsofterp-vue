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

echo "=== 1) Delivery order 76857 / DO2611190050 ===\n";
$do = DB::table('delivery_orders')->where('id', 76857)->first();
if (!$do) {
    // try alternate table names
    foreach (['delivery_order', 'food_delivery_orders'] as $t) {
        if (Schema::hasTable($t)) {
            $do = DB::table($t)->where('id', 76857)->first();
            echo "found in {$t}\n";
            break;
        }
    }
}
echo "DO header: " . json_encode($do) . "\n";

$doItems = DB::table('delivery_order_items')->where('delivery_order_id', 76857)->where('item_id', $itemId)->get();
echo "DO items for tenderloin:\n";
foreach ($doItems as $i) echo json_encode($i) . "\n";

echo "\n=== 2) Inventory cards for this DO / outlet 18 ===\n";
$cards = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where(function ($q) {
        $q->where('reference_type', 'delivery_order')->where('reference_id', 76857);
    })
    ->get();
echo "outlet cards for DO: " . $cards->count() . "\n";

// Also warehouse food_inventory_cards
if (Schema::hasTable('food_inventory_cards')) {
    $wc = DB::table('food_inventory_cards')
        ->where('reference_type', 'like', '%delivery%')
        ->where('reference_id', 76857)
        ->get();
    echo "warehouse cards ref DO: " . $wc->count() . "\n";
    foreach ($wc->take(5) as $c) echo json_encode($c) . "\n";
}

echo "\n=== 3) All serial movements for item 52984 outlet 18 on 19 Nov ===\n";
$movs = DB::table('inventory_serial_movements')
    ->where('item_id', $itemId)
    ->where('outlet_id', $outletId)
    ->whereDate('moved_at', '2026-11-19')
    ->orderBy('id')
    ->get();
foreach ($movs as $m) echo json_encode($m) . "\n";

echo "\n=== 4) Serial B2611161031KYWI full history ===\n";
$serial = DB::table('inventory_serials')->where('serial_number', 'B2611161031KYWI')->first();
if (!$serial && Schema::hasTable('item_serials')) {
    $serial = DB::table('item_serials')->where('serial_number', 'B2611161031KYWI')->first();
}
// find serial table
foreach (['inventory_serials', 'item_serials', 'serial_numbers', 'outlet_serials', 'warehouse_serials'] as $t) {
    if (Schema::hasTable($t)) {
        $cols = Schema::getColumnListing($t);
        if (in_array('serial_number', $cols)) {
            $s = DB::table($t)->where('serial_number', 'B2611161031KYWI')->first();
            if ($s) {
                echo "serial table={$t}: " . json_encode($s) . "\n";
            }
        }
    }
}

$allMov = DB::table('inventory_serial_movements')
    ->where('serial_number', 'B2611161031KYWI')
    ->orderBy('id')
    ->get();
echo "all movements for serial:\n";
foreach ($allMov as $m) {
    echo sprintf(
        "id=%d %s type=%s do=%s outlet=%s wh=%s qty=%s unit=%s at=%s\n",
        $m->id, $m->serial_number, $m->movement_type, $m->delivery_order_id,
        $m->outlet_id, $m->warehouse_outlet_id, $m->qty, $m->unit_id, $m->moved_at
    );
}

echo "\n=== 5) Serial receive of B2611161031KYWI (card 3840147 / header 22934) ===\n";
$h = DB::table('outlet_serial_receive_headers')->where('id', 22934)->first();
echo json_encode($h) . "\n";
$hi = DB::table('outlet_serial_receive_items')->where('header_id', 22934)->where('serial_number', 'B2611161031KYWI')->first();
echo json_encode($hi) . "\n";

echo "\n=== 6) Did DO 76857 reduce outlet stock without card? Search code path evidence via related cards same minute ===\n";
$near = DB::table('outlet_food_inventory_cards')
    ->where('id_outlet', $outletId)
    ->whereBetween('created_at', ['2026-11-19 13:53:00', '2026-11-19 13:54:00'])
    ->orderBy('id')
    ->limit(30)
    ->get(['id','inventory_item_id','warehouse_outlet_id','reference_type','reference_id','in_qty_small','out_qty_small','description']);
echo "cards created same minute as DO movement:\n";
foreach ($near as $c) echo json_encode($c) . "\n";

// Specifically any card for inv 5080 around that time anywhere
$any = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->whereBetween('created_at', ['2026-11-19 13:00:00', '2026-11-19 14:30:00'])
    ->get();
echo "cards for tenderloin 13:00-14:30: " . $any->count() . "\n";

echo "\n=== 7) Warehouse inventory cards around DO for item ===\n";
if (Schema::hasTable('food_inventory_cards')) {
    $fi = DB::table('food_inventory_items')->where('item_id', $itemId)->value('id');
    echo "food_inventory_item_id={$fi}\n";
    if ($fi) {
        $fc = DB::table('food_inventory_cards')
            ->where('inventory_item_id', $fi)
            ->whereDate('date', '2026-11-19')
            ->orderBy('id')
            ->get();
        foreach ($fc as $c) {
            echo sprintf(
                "id=%d wh=%s ref=%s#%s in=%s out=%s saldo=%s desc=%s\n",
                $c->id, $c->warehouse_id ?? '-', $c->reference_type, $c->reference_id,
                $c->in_qty_small, $c->out_qty_small, $c->saldo_qty_small, substr((string)$c->description,0,60)
            );
        }
    }
}

echo "\n=== 8) ALL inventory_serial_movements OUT for item 52984 outlet 18 that have NO matching outlet card ===\n";
$outs = DB::table('inventory_serial_movements')
    ->where('item_id', $itemId)
    ->where('outlet_id', $outletId)
    ->where('movement_type', 'out')
    ->whereBetween('moved_at', ['2026-11-01', '2026-11-27'])
    ->orderBy('moved_at')
    ->get();

foreach ($outs as $m) {
    $card = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $invItemId)
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $m->warehouse_outlet_id)
        ->where(function ($q) use ($m) {
            $q->where(function ($q2) use ($m) {
                $q2->where('reference_type', 'delivery_order')->where('reference_id', $m->delivery_order_id);
            })->orWhere(function ($q2) use ($m) {
                $q2->where('description', 'like', '%'.$m->serial_number.'%');
            });
        })
        ->first();
    $qtySmall = (float)$m->qty * 150; // unit is pcs/medium
    $mark = $card ? 'HAS_CARD' : 'NO_CARD';
    echo sprintf(
        "%s %s do=%s serial=%s qty=%s(~%sg) wh=%s %s card=%s\n",
        $m->moved_at, $m->movement_type, $m->delivery_order_id, $m->serial_number,
        $m->qty, $qtySmall, $m->warehouse_outlet_id, $mark, $card->id ?? '-'
    );
}

echo "\n=== 9) Who created DO / is it reverse logistics from outlet? ===\n";
if ($do) {
    $cols = array_keys((array)$do);
    echo "DO cols: " . implode(',', $cols) . "\n";
}
