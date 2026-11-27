<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== 1) Resolve outlet / warehouse / item ===\n";

$outlets = DB::table('tbl_data_outlet')
    ->where('nama_outlet', 'like', '%Buah Batu%')
    ->orWhere('nama_outlet', 'like', '%Justus%Buah%')
    ->select('id_outlet', 'nama_outlet')
    ->get();
echo "Outlets:\n";
foreach ($outlets as $o) {
    echo "  {$o->id_outlet} | {$o->nama_outlet}\n";
}

// Prefer exact Buah Batu steak house if present
$outlet = DB::table('tbl_data_outlet')
    ->where('nama_outlet', 'like', '%Buah Batu%')
    ->orderBy('id_outlet')
    ->first();

if (!$outlet) {
    echo "OUTLET NOT FOUND\n";
    exit(1);
}
$outletId = $outlet->id_outlet;
echo "Using outlet: {$outletId} | {$outlet->nama_outlet}\n";

$warehouses = DB::table('warehouse_outlets')
    ->where('outlet_id', $outletId)
    ->select('id', 'name', 'status')
    ->get();
echo "Warehouses:\n";
foreach ($warehouses as $w) {
    echo "  {$w->id} | {$w->name} | {$w->status}\n";
}

$kitchen = $warehouses->first(function ($w) {
    return stripos($w->name, 'kitchen') !== false || stripos($w->name, 'dapur') !== false;
});
$whId = $kitchen ? $kitchen->id : ($warehouses->first()->id ?? null);
echo "Using warehouse: {$whId} | " . ($kitchen->name ?? 'FIRST') . "\n";

$items = DB::table('items')
    ->where(function ($q) {
        $q->where('name', 'like', '%Tenderloin%Aussie%150%')
          ->orWhere('name', 'like', '%Aussie%Tenderloin%150%')
          ->orWhere('name', 'like', '%Beef Tenderloin Aussie%')
          ->orWhere('name', 'like', '%Tenderloin Aussie 150%');
    })
    ->select('id', 'name', 'small_unit_id', 'medium_unit_id', 'large_unit_id', 'small_conversion_qty', 'medium_conversion_qty')
    ->get();
echo "Items:\n";
foreach ($items as $i) {
    echo "  {$i->id} | {$i->name} | sc={$i->small_conversion_qty} mc={$i->medium_conversion_qty}\n";
}

if ($items->isEmpty()) {
    $items = DB::table('items')
        ->where('name', 'like', '%Tenderloin%Aussie%')
        ->orWhere('name', 'like', '%Aussie%Tenderloin%')
        ->select('id', 'name', 'small_unit_id', 'medium_unit_id', 'large_unit_id', 'small_conversion_qty', 'medium_conversion_qty')
        ->limit(20)
        ->get();
    echo "Fallback items:\n";
    foreach ($items as $i) {
        echo "  {$i->id} | {$i->name}\n";
    }
}

$item = $items->first(function ($i) {
    return stripos($i->name, '150') !== false;
}) ?: $items->first();

if (!$item) {
    echo "ITEM NOT FOUND\n";
    exit(1);
}
$itemId = $item->id;
echo "Using item: {$itemId} | {$item->name}\n";

$invItem = DB::table('outlet_food_inventory_items')->where('item_id', $itemId)->first();
echo "inventory_item_id: " . ($invItem->id ?? 'NULL') . "\n";

$units = DB::table('units')->whereIn('id', array_filter([
    $item->small_unit_id, $item->medium_unit_id, $item->large_unit_id
]))->pluck('name', 'id');
$uSmall = isset($units[$item->small_unit_id]) ? $units[$item->small_unit_id] : '?';
$uMed = isset($units[$item->medium_unit_id]) ? $units[$item->medium_unit_id] : '?';
$uLarge = isset($units[$item->large_unit_id]) ? $units[$item->large_unit_id] : '?';
echo "Units: small={$uSmall} medium={$uMed} large={$uLarge}\n";

$stock = DB::table('outlet_food_inventory_stocks')
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->where('inventory_item_id', $invItem->id ?? 0)
    ->first();
if ($stock) {
    echo "Current stock: small={$stock->qty_small} med={$stock->qty_medium} large={$stock->qty_large} value={$stock->value}\n";
} else {
    echo "Current stock: NONE\n";
}

echo "\n=== 2) ALL cards 2026-11-17 .. 2026-11-27 ===\n";
$cards = DB::table('outlet_food_inventory_cards as c')
    ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
    ->where('fi.item_id', $itemId)
    ->where('c.id_outlet', $outletId)
    ->where('c.warehouse_outlet_id', $whId)
    ->whereBetween(DB::raw('DATE(c.date)'), ['2026-11-17', '2026-11-27'])
    ->orderBy('c.date')
    ->orderBy('c.id')
    ->select('c.*')
    ->get();

echo "card_count=" . $cards->count() . "\n";
$prevSaldo = null;
foreach ($cards as $c) {
    $deltaSmall = null;
    if ($prevSaldo !== null) {
        $expected = $prevSaldo + (float)$c->in_qty_small - (float)$c->out_qty_small;
        $deltaSmall = (float)$c->saldo_qty_small - $expected;
    }
    $flag = ($deltaSmall !== null && abs($deltaSmall) > 0.01) ? " *** JUMP delta={$deltaSmall}" : '';
    echo sprintf(
        "id=%d date=%s ref=%s#%s in_s=%s out_s=%s saldo_s=%s/%s/%s desc=%s%s\n",
        $c->id,
        $c->date,
        $c->reference_type,
        $c->reference_id,
        $c->in_qty_small,
        $c->out_qty_small,
        $c->saldo_qty_small,
        $c->saldo_qty_medium,
        $c->saldo_qty_large,
        substr((string)$c->description, 0, 60),
        $flag
    );
    $prevSaldo = (float)$c->saldo_qty_small;
}

echo "\n=== 3) Opening before 2026-11-19 ===\n";
$open = DB::table('outlet_food_inventory_cards as c')
    ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
    ->where('fi.item_id', $itemId)
    ->where('c.id_outlet', $outletId)
    ->where('c.warehouse_outlet_id', $whId)
    ->whereDate('c.date', '<', '2026-11-19')
    ->orderByDesc('c.date')
    ->orderByDesc('c.id')
    ->select('c.*')
    ->first();
if ($open) {
    echo sprintf(
        "id=%d date=%s ref=%s#%s saldo_s=%s/%s/%s\n",
        $open->id, $open->date, $open->reference_type, $open->reference_id,
        $open->saldo_qty_small, $open->saldo_qty_medium, $open->saldo_qty_large
    );
} else {
    echo "NO OPENING CARD\n";
}

echo "\n=== 4) Focus 19 Nov & 25-26 Nov raw cards ===\n";
foreach (['2026-11-19', '2026-11-25', '2026-11-26'] as $d) {
    echo "-- {$d} --\n";
    $day = $cards->filter(fn ($c) => substr((string)$c->date, 0, 10) === $d);
    foreach ($day as $c) {
        echo sprintf(
            "  id=%d created=%s updated=%s ref=%s#%s in=%s/%s/%s out=%s/%s/%s saldo=%s/%s/%s value_in=%s value_out=%s saldo_val=%s\n",
            $c->id,
            $c->created_at ?? '-',
            $c->updated_at ?? '-',
            $c->reference_type,
            $c->reference_id,
            $c->in_qty_small, $c->in_qty_medium, $c->in_qty_large,
            $c->out_qty_small, $c->out_qty_medium, $c->out_qty_large,
            $c->saldo_qty_small, $c->saldo_qty_medium, $c->saldo_qty_large,
            $c->value_in, $c->value_out, $c->saldo_value
        );
    }
    if ($day->isEmpty()) echo "  (none)\n";
}

echo "\n=== 5) serial_receive #22872 ===\n";
$sr = DB::table('outlet_serial_receive_headers')->where('id', 22872)->first();
if ($sr) {
    echo json_encode($sr, JSON_PRETTY_PRINT) . "\n";
    $sri = DB::table('outlet_serial_receive_items')->where('header_id', 22872)->where('item_id', $itemId)->get();
    echo "items matching tenderloin: " . $sri->count() . "\n";
    foreach ($sri as $row) {
        echo json_encode($row) . "\n";
    }
    // also all items on header if none matched
    if ($sri->isEmpty()) {
        $all = DB::table('outlet_serial_receive_items')->where('header_id', 22872)->get(['id','item_id','qty','unit_id']);
        echo "all header items:\n";
        foreach ($all as $row) echo "  " . json_encode($row) . "\n";
    }
} else {
    // maybe reference_id is item line id
    $card22872 = DB::table('outlet_food_inventory_cards')->where('id', 22872)->first();
    echo "No header 22872. Card id 22872? " . ($card22872 ? json_encode($card22872) : 'no') . "\n";
    $byRef = DB::table('outlet_food_inventory_cards')
        ->where('reference_type', 'like', '%serial%')
        ->where('reference_id', 22872)
        ->get();
    echo "cards ref serial* #22872 count=" . $byRef->count() . "\n";
}

echo "\n=== 6) order_items / stock_cut OUT on those days ===\n";
$outCards = $cards->filter(fn ($c) => (float)$c->out_qty_small > 0 || stripos((string)$c->reference_type, 'order') !== false);
foreach ($outCards as $c) {
    echo sprintf(
        "card=%d date=%s ref=%s#%s out_s=%s saldo_s=%s\n",
        $c->id, $c->date, $c->reference_type, $c->reference_id, $c->out_qty_small, $c->saldo_qty_small
    );
}

echo "\n=== DONE KEYS ===\n";
echo "outlet_id={$outletId} warehouse_id={$whId} item_id={$itemId} inv_item_id=" . ($invItem->id ?? 'null') . "\n";
