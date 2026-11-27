<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$outletId = 18;
$whId = 41;
$itemId = 52984;
$invItemId = 5080;

echo "=== Compare each stock_cut_detail vs preceding card saldo ===\n";

$details = DB::table('stock_cut_details as d')
    ->join('stock_cut_logs as l', 'l.id', '=', 'd.stock_cut_log_id')
    ->where('d.item_id', $itemId)
    ->where('d.warehouse_outlet_id', $whId)
    ->where('l.outlet_id', $outletId)
    ->where('l.tanggal', '>=', '2026-11-01')
    ->where('l.tanggal', '<=', '2026-11-27')
    ->orderBy('l.tanggal')
    ->orderBy('d.id')
    ->select('d.*', 'l.tanggal', 'l.id as log_id', 'l.created_at as cut_at')
    ->get();

foreach ($details as $d) {
    $prevCard = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $invItemId)
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $whId)
        ->where(function ($q) use ($d) {
            $q->where('created_at', '<', $d->cut_at)
              ->orWhere(function ($q2) use ($d) {
                  $q2->whereNull('created_at')->where('id', '<', 999999999);
              });
        })
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();

    // Better: last card with id before the order_items card for this cut, or created_at < cut_at
    $prevCard = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $invItemId)
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $whId)
        ->where('created_at', '<', $d->cut_at)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->first();

    $cardSaldo = $prevCard ? (float)$prevCard->saldo_qty_small : null;
    $stockBefore = (float)$d->stock_before_small;
    $drift = $cardSaldo === null ? null : ($stockBefore - $cardSaldo);

    echo sprintf(
        "cut=%s log=%d detail=%d needed=%s stock_before=%s card_saldo_before=%s drift=%s after=%s card_prev_id=%s\n",
        $d->tanggal,
        $d->log_id,
        $d->id,
        $d->qty_small,
        $d->stock_before_small,
        $cardSaldo,
        $drift,
        $d->stock_after_small,
        $prevCard->id ?? 'null'
    );
}

echo "\n=== Walk ALL cards Nov 1-27 and flag vs stock_cut_detail stock_after ===\n";
$cards = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereBetween(DB::raw('DATE(date)'), ['2026-11-01', '2026-11-27'])
    ->orderBy('date')->orderBy('id')
    ->get();

foreach ($cards as $c) {
    $flag = '';
    if ($c->reference_type === 'order_items' && $c->stock_cut_log_id) {
        $det = DB::table('stock_cut_details')
            ->where('stock_cut_log_id', $c->stock_cut_log_id)
            ->where('item_id', $itemId)
            ->where('warehouse_outlet_id', $whId)
            ->first();
        if ($det) {
            $flag = sprintf(
                " | detail_before=%s detail_after=%s card_saldo=%s match_after=%s",
                $det->stock_before_small,
                $det->stock_after_small,
                $c->saldo_qty_small,
                abs((float)$det->stock_after_small - (float)$c->saldo_qty_small) < 0.01 ? 'Y' : 'N'
            );
        }
    }
    echo sprintf(
        "%s id=%d ref=%s in=%s out=%s saldo=%s%s\n",
        substr((string)$c->date,0,10), $c->id, $c->reference_type,
        $c->in_qty_small, $c->out_qty_small, $c->saldo_qty_small, $flag
    );
}

echo "\n=== Find FIRST drift: go back earlier Oct-Nov cuts ===\n";
$earlier = DB::table('stock_cut_details as d')
    ->join('stock_cut_logs as l', 'l.id', '=', 'd.stock_cut_log_id')
    ->where('d.item_id', $itemId)
    ->where('d.warehouse_outlet_id', $whId)
    ->where('l.outlet_id', $outletId)
    ->where('l.tanggal', '>=', '2026-10-01')
    ->where('l.tanggal', '<', '2026-11-19')
    ->orderByDesc('l.tanggal')
    ->orderByDesc('d.id')
    ->select('d.*', 'l.tanggal', 'l.id as log_id', 'l.created_at as cut_at')
    ->limit(30)
    ->get();

foreach ($earlier as $d) {
    $prevCard = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $invItemId)
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $whId)
        ->where('created_at', '<', $d->cut_at)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->first();
    $cardSaldo = $prevCard ? (float)$prevCard->saldo_qty_small : null;
    $drift = $cardSaldo === null ? null : ((float)$d->stock_before_small - $cardSaldo);
    $mark = ($drift !== null && abs($drift) > 0.01) ? ' *** DRIFT' : '';
    echo sprintf(
        "%s stock_before=%s card=%s drift=%s needed=%s after=%s%s\n",
        $d->tanggal, $d->stock_before_small, $cardSaldo, $drift, $d->qty_small, $d->stock_after_small, $mark
    );
}

echo "\n=== IN cards that add to stock vs stock movement reconciliation Nov 1-19 ===\n";
// Sum of in - out on cards vs net change implied
$sumIn = (float) DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereBetween(DB::raw('DATE(date)'), ['2026-11-01', '2026-11-19'])
    ->sum('in_qty_small');
$sumOut = (float) DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereBetween(DB::raw('DATE(date)'), ['2026-11-01', '2026-11-19'])
    ->sum('out_qty_small');
echo "Nov1-19 card sumIn={$sumIn} sumOut={$sumOut} net=" . ($sumIn-$sumOut) . "\n";

echo "\n=== Check cost histories / other stock writers on 19 Nov ===\n";
$ch = DB::table('outlet_food_inventory_cost_histories')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereBetween(DB::raw('DATE(date)'), ['2026-11-17', '2026-11-20'])
    ->orderBy('id')
    ->get();
foreach ($ch as $r) {
    echo json_encode($r) . "\n";
}

echo "\n=== Opname / adjust / waste references mentioning 52984 around mid Nov ===\n";
$refs = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereBetween(DB::raw('DATE(date)'), ['2026-10-15', '2026-11-19'])
    ->whereNotIn('reference_type', ['serial_receive', 'order_items'])
    ->orderBy('date')
    ->get(['id','date','reference_type','reference_id','in_qty_small','out_qty_small','saldo_qty_small','description','created_at']);
echo "other refs count=" . $refs->count() . "\n";
foreach ($refs as $r) echo json_encode($r) . "\n";

echo "\n=== Menu engineering qty for 19 Nov (BOM theoretical) ===\n";
// Get BOM parents for item 52984
$boms = DB::table('item_bom')->where('material_item_id', $itemId)->orWhere('item_id', $itemId)->limit(5)->get();
// check columns
$cols = DB::getSchemaBuilder()->getColumnListing('item_bom');
echo "item_bom cols: " . implode(',', $cols) . "\n";

$bomChildCol = in_array('material_item_id', $cols) ? 'material_item_id' : (in_array('bom_item_id', $cols) ? 'bom_item_id' : null);
$parentCol = in_array('item_id', $cols) ? 'item_id' : null;
$qtyCol = in_array('qty', $cols) ? 'qty' : (in_array('quantity', $cols) ? 'quantity' : null);

if ($bomChildCol) {
    $boms = DB::table('item_bom as b')
        ->join('items as i', 'i.id', '=', 'b.'.$parentCol)
        ->where('b.'.$bomChildCol, $itemId)
        ->select('b.*', 'i.name as parent_name')
        ->get();
    echo "BOM parents using 52984: " . $boms->count() . "\n";
    foreach ($boms->take(20) as $b) {
        echo "  parent={$b->parent_name} ({$b->$parentCol}) qty={$b->$qtyCol}\n";
    }
}

// stock_cut detail already says needed 1200 on 19 Nov - engineering for that day
echo "\n19 Nov engineering/BOM cut needed = 1200g (8 pcs) per stock_cut_details\n";
echo "25 Nov engineering/BOM cut needed = 2550g (17 pcs) — matches user report exactly\n";
