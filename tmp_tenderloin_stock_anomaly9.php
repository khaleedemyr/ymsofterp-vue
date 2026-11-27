<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$outletId = 18;
$whId = 41;

echo "=== Explain positive drifts: waste cards vs stock for sample items ===\n";
// Mushroom sauce item 54673 - waste card 3835043
foreach ([
    ['name' => 'Mushroom Sauce', 'item_id' => 54673, 'waste_card' => 3835043],
    ['name' => 'Tenderloin', 'item_id' => 52984, 'waste_card' => null],
] as $sample) {
    $inv = DB::table('outlet_food_inventory_items')->where('item_id', $sample['item_id'])->value('id');
    echo "\n-- {$sample['name']} inv={$inv} --\n";
    $cards = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $inv)
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $whId)
        ->whereBetween(DB::raw('DATE(date)'), ['2026-11-18', '2026-11-19'])
        ->orderBy('id')
        ->get(['id','date','created_at','reference_type','reference_id','in_qty_small','out_qty_small','saldo_qty_small','description']);
    foreach ($cards as $c) {
        echo sprintf(
            "id=%d %s created=%s ref=%s#%s in=%s out=%s saldo=%s %s\n",
            $c->id, $c->date, $c->created_at, $c->reference_type, $c->reference_id,
            $c->in_qty_small, $c->out_qty_small, $c->saldo_qty_small, substr((string)$c->description,0,50)
        );
    }
}

echo "\n=== Internal use waste on 19 Nov - do they update stock? check waste header times ===\n";
$wasteCards = DB::table('outlet_food_inventory_cards')
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->where('reference_type', 'outlet_internal_use_waste')
    ->whereDate('date', '2026-11-19')
    ->orderBy('id')
    ->limit(5)
    ->get(['id','created_at','reference_id','inventory_item_id','out_qty_small','saldo_qty_small']);
foreach ($wasteCards as $c) echo json_encode($c) . "\n";

echo "\n=== For Mushroom: reconstruct if waste card saldo jumped vs stock ===\n";
$invM = DB::table('outlet_food_inventory_items')->where('item_id', 54673)->value('id');
$seq = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invM)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereBetween(DB::raw('DATE(date)'), ['2026-11-18','2026-11-19'])
    ->orderBy('date')->orderBy('id')
    ->get();
$prev = null;
foreach ($seq as $c) {
    if ($prev !== null) {
        $exp = (float)$prev->saldo_qty_small + (float)$c->in_qty_small - (float)$c->out_qty_small;
        $diff = (float)$c->saldo_qty_small - $exp;
        if (abs($diff) > 0.01) {
            echo "JUMP card {$c->id} ref={$c->reference_type} expected={$exp} stored={$c->saldo_qty_small} diff={$diff}\n";
        }
    }
    $prev = $c;
}

echo "\n=== Critical: does stock cut main method wrap in transaction? already known NO ===\n";
echo "=== Check if log 12032 was ever deleted/recreated - id gaps / activity ===\n";
if (DB::getSchemaBuilder()->hasTable('activity_log')) {
    $acts = DB::table('activity_log')
        ->whereBetween('created_at', ['2026-11-19 22:00:00', '2026-11-19 23:00:00'])
        ->where(function ($q) {
            $q->where('description', 'like', '%Stock Cut%')
              ->orWhere('description', 'like', '%stock cut%')
              ->orWhere('properties', 'like', '%12032%');
        })
        ->get();
    echo "acts=" . $acts->count() . "\n";
    foreach ($acts as $a) {
        echo substr(json_encode($a), 0, 500) . "\n\n";
    }
}

echo "\n=== Count order_items cards for log 12032 vs details count ===\n";
$cardCount = DB::table('outlet_food_inventory_cards')->where('stock_cut_log_id', 12032)->count();
$detailCount = DB::table('stock_cut_details')->where('stock_cut_log_id', 12032)->count();
echo "cards={$cardCount} details={$detailCount} log.total_items_cut=" . DB::table('stock_cut_logs')->where('id',12032)->value('total_items_cut') . "\n";

// Details with qty but no card (physical cut 0 due to shortfall-all?)
$details = DB::table('stock_cut_details')->where('stock_cut_log_id', 12032)->get();
$noCard = 0; $zeroAfter = 0;
foreach ($details as $d) {
    $c = DB::table('outlet_food_inventory_cards')
        ->where('stock_cut_log_id', 12032)
        ->where('inventory_item_id', $d->inventory_item_id)
        ->where('warehouse_outlet_id', $d->warehouse_outlet_id)
        ->first();
    if (!$c) {
        $noCard++;
        if ($noCard <= 10) {
            echo "detail without card: item={$d->item_id} needed={$d->qty_small} before={$d->stock_before_small} after={$d->stock_after_small}\n";
        }
    }
    if ((float)$d->stock_after_small == 0 && (float)$d->stock_before_small == 0) $zeroAfter++;
}
echo "details_without_card={$noCard} (may be zero physical cut)\n";

echo "\nDONE\n";
