<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');

echo "===== Warehouses Main Store =====\n";
$warehouses = DB::table('warehouses')
    ->where('name', 'like', '%Main Store%')
    ->get(['id', 'name']);
foreach ($warehouses as $w) {
    echo "  id={$w->id} name={$w->name}\n";
}

echo "\n===== Items containing udang =====\n";
$items = DB::table('items')
    ->whereRaw('LOWER(name) LIKE ?', ['%udang%'])
    ->limit(30)
    ->get(['id', 'name', 'sku']);
foreach ($items as $it) {
    echo "  id={$it->id} sku=".($it->sku ?? '-')." name={$it->name}\n";
}

// Prefer exact-ish udang names; user said "udang"
$itemIds = $items->pluck('id')->all();
if ($itemIds === []) {
    echo "No udang items found\n";
    exit(1);
}

$whId = (int) ($warehouses->first()->id ?? 0);
if ($whId <= 0) {
    echo "No Main Store warehouse\n";
    exit(1);
}

echo "\n===== DO DO2609160037 =====\n";
$doCols = Schema::getColumnListing('delivery_orders');
echo 'DO columns: '.implode(', ', $doCols)."\n";

$numberCol = null;
foreach (['number', 'do_number', 'delivery_order_number', 'no_do'] as $c) {
    if (in_array($c, $doCols, true)) {
        $numberCol = $c;
        break;
    }
}

$dos = collect();
if ($numberCol) {
    $dos = DB::table('delivery_orders')->where($numberCol, 'like', '%DO2609160037%')->get();
} else {
    // fallback search any string-ish columns
    $dos = DB::table('delivery_orders')->whereDate('created_at', '2026-09-16')->limit(5)->get();
}
echo "Found DOS: {$dos->count()}\n";
foreach ($dos as $do) {
    echo json_encode($do, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
}

echo "\n===== food_inventory_items for udang =====\n";
$invItems = DB::table('food_inventory_items')->whereIn('item_id', $itemIds)->get();
foreach ($invItems as $inv) {
    $name = $items->firstWhere('id', $inv->item_id)->name ?? '?';
    echo "  inv_id={$inv->id} item_id={$inv->item_id} name={$name}\n";
}

$invIds = $invItems->pluck('id')->all();

echo "\n===== Stock cards around 15-17 Sep 2026 (Main Store) =====\n";
$cardCols = Schema::getColumnListing('food_inventory_cards');
echo 'card cols: '.implode(', ', array_slice($cardCols, 0, 40))."\n";

$cards = DB::table('food_inventory_cards')
    ->whereIn('inventory_item_id', $invIds)
    ->where('warehouse_id', $whId)
    ->whereBetween(DB::raw('DATE(date)'), ['2026-09-15', '2026-09-17'])
    ->orderBy('inventory_item_id')
    ->orderBy('date')
    ->orderBy('id')
    ->get();

echo "cards count={$cards->count()}\n";
foreach ($cards as $c) {
    $ref = $c->reference_type ?? ($c->ref_type ?? '?');
    $refId = $c->reference_id ?? ($c->ref_id ?? '?');
    $desc = $c->description ?? ($c->note ?? ($c->notes ?? ''));
    $outS = $c->out_qty_small ?? ($c->qty_out_small ?? ($c->out_small ?? null));
    $inS = $c->in_qty_small ?? ($c->qty_in_small ?? ($c->in_small ?? null));
    $saldoS = $c->saldo_qty_small ?? null;
    echo sprintf(
        "  id=%s inv=%s date=%s in_s=%s out_s=%s saldo_s=%s saldo_m=%s saldo_l=%s ref=%s/%s desc=%s\n",
        $c->id,
        $c->inventory_item_id,
        $c->date,
        $fmt($inS ?? 0),
        $fmt($outS ?? 0),
        $fmt($saldoS ?? 0),
        $fmt($c->saldo_qty_medium ?? 0),
        $fmt($c->saldo_qty_large ?? 0),
        $ref,
        $refId,
        substr((string) $desc, 0, 120)
    );
}

echo "\n===== Cards mentioning DO2609160037 =====\n";
$doCards = DB::table('food_inventory_cards')
    ->whereIn('inventory_item_id', $invIds)
    ->where('warehouse_id', $whId)
    ->where(function ($q) {
        $q->where('description', 'like', '%DO2609160037%')
            ->orWhere('notes', 'like', '%DO2609160037%')
            ->orWhere('reference_number', 'like', '%DO2609160037%');
    })
    ->orderBy('id')
    ->get();

// notes/reference_number may not exist — catch by try each
if ($doCards->isEmpty()) {
    $q = DB::table('food_inventory_cards')
        ->whereIn('inventory_item_id', $invIds)
        ->where('warehouse_id', $whId)
        ->whereDate('date', '2026-09-16');
    if (in_array('description', $cardCols, true)) {
        $q->where('description', 'like', '%DO2609160037%');
        $doCards = $q->orderBy('id')->get();
    }
}

foreach ($doCards as $c) {
    echo json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
}

echo "\n===== food_inventory_stocks current =====\n";
$stocks = DB::table('food_inventory_stocks')
    ->whereIn('inventory_item_id', $invIds)
    ->where('warehouse_id', $whId)
    ->get();
foreach ($stocks as $s) {
    echo "  inv={$s->inventory_item_id} small={$fmt($s->qty_small)} med={$fmt($s->qty_medium)} large={$fmt($s->qty_large)} value={$s->value}\n";
}

echo "\nDone.\n";
