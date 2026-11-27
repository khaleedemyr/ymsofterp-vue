<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$outletId = 18;
$whId = 41;
$cutAt = '2026-11-19 22:35:59';

echo "=== 1) Items at cut 12032 with exact signature drift == -needed (unrestored prior cut) ===\n";
$details = DB::table('stock_cut_details as d')
    ->join('items as i', 'i.id', '=', 'd.item_id')
    ->where('d.stock_cut_log_id', 12032)
    ->where('d.warehouse_outlet_id', $whId)
    ->select('d.*', 'i.name')
    ->get();

// Preload last card before cut for all inv ids in one query
$invIds = $details->pluck('inventory_item_id')->unique()->values()->all();
$prevCards = [];
$cards = DB::table('outlet_food_inventory_cards')
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereIn('inventory_item_id', $invIds)
    ->where('created_at', '<', $cutAt)
    ->orderBy('inventory_item_id')
    ->orderByDesc('id')
    ->get(['id', 'inventory_item_id', 'reference_type', 'saldo_qty_small', 'created_at', 'out_qty_small', 'in_qty_small']);

foreach ($cards as $c) {
    if (!isset($prevCards[$c->inventory_item_id])) {
        $prevCards[$c->inventory_item_id] = $c;
    }
}

$exact = [];
$neg = [];
$pos = [];
$ok = 0;
$noPrev = 0;
foreach ($details as $d) {
    $prev = $prevCards[$d->inventory_item_id] ?? null;
    if (!$prev) {
        $noPrev++;
        continue;
    }
    $drift = (float) $d->stock_before_small - (float) $prev->saldo_qty_small;
    $need = (float) $d->qty_small;
    if (abs($drift) < 0.011) {
        $ok++;
        continue;
    }
    $row = [
        'name' => $d->name,
        'item_id' => (int) $d->item_id,
        'inv' => (int) $d->inventory_item_id,
        'stock_before' => (float) $d->stock_before_small,
        'card' => (float) $prev->saldo_qty_small,
        'drift' => $drift,
        'need' => $need,
        'prev_id' => $prev->id,
        'prev_ref' => $prev->reference_type,
        'exact_neg_need' => abs($drift + $need) < 0.011,
    ];
    if ($row['exact_neg_need']) {
        $exact[] = $row;
    } elseif ($drift < 0) {
        $neg[] = $row;
    } else {
        $pos[] = $row;
    }
}

echo "ok={$ok} exact_neg_need=" . count($exact) . " other_neg=" . count($neg) . " pos=" . count($pos) . " no_prev={$noPrev}\n";
echo "-- exact_neg_need items (smoking gun for unrestored cut qty) --\n";
usort($exact, fn ($a, $b) => abs($b['drift']) <=> abs($a['drift']));
foreach ($exact as $r) {
    echo sprintf(
        "  %s(%d) stock=%s card=%s drift=%s need=%s prev=#%d/%s\n",
        $r['name'],
        $r['item_id'],
        $r['stock_before'],
        $r['card'],
        $r['drift'],
        $r['need'],
        $r['prev_id'],
        $r['prev_ref']
    );
}
echo "-- tenderloin row --\n";
foreach (array_merge($exact, $neg, $pos) as $r) {
    if ($r['item_id'] === 52984) {
        echo json_encode($r) . "\n";
    }
}
// also if ok
$tPrev = $prevCards[5080] ?? null;
$tDet = $details->firstWhere('item_id', 52984);
if ($tDet && $tPrev) {
    echo sprintf(
        "tenderloin: stock_before=%s card=%s drift=%s need=%s exact=%s\n",
        $tDet->stock_before_small,
        $tPrev->saldo_qty_small,
        (float) $tDet->stock_before_small - (float) $tPrev->saldo_qty_small,
        $tDet->qty_small,
        abs(((float) $tDet->stock_before_small - (float) $tPrev->saldo_qty_small) + (float) $tDet->qty_small) < 0.011 ? 'Y' : 'N'
    );
}

echo "\n=== 2) Transfers for item 52984 on 19 Nov (fixed FK) ===\n";
$xf = DB::table('outlet_transfer_items as d')
    ->join('outlet_transfers as h', 'h.id', '=', 'd.outlet_transfer_id')
    ->where('h.outlet_id', $outletId)
    ->whereDate('h.transfer_date', '2026-11-19')
    ->where('d.item_id', 52984)
    ->get(['h.id', 'h.transfer_number', 'h.status', 'h.created_at', 'd.qty_small', 'd.quantity']);
echo "transfers=" . $xf->count() . "\n";
foreach ($xf as $r) {
    echo json_encode($r) . "\n";
}

echo "\n=== 3) Who created stock_cut logs — activity create for stock_cut? ===\n";
$creates = DB::table('activity_logs')
    ->where('module', 'stock_cut')
    ->whereBetween('created_at', ['2026-11-19 21:50:00', '2026-11-19 23:00:00'])
    ->orderBy('id')
    ->get(['id', 'created_at', 'user_id', 'activity_type', 'description', 'old_data', 'new_data']);
echo "stock_cut activity rows=" . $creates->count() . "\n";
foreach ($creates as $r) {
    echo "{$r->created_at} u={$r->user_id} {$r->activity_type} {$r->description}\n";
}

echo "\n=== 4) Prove #12015 was partial: compare material coverage ===\n";
echo "12015 cards_affected(from rollback activity)=83\n";
echo "12032 cards with out>0=" . DB::table('outlet_food_inventory_cards')->where('stock_cut_log_id', 12032)->where('out_qty_small', '>', 0)->count() . "\n";
echo "12032 details=" . DB::table('stock_cut_details')->where('stock_cut_log_id', 12032)->count() . "\n";
echo "Delta suggests 12015 did not finish full material set (or many skipped) before rollback.\n";

echo "\n=== 5) Waste controller saldo source for comparison ===\n";
// read a few lines of waste process if needed — already know from code search

echo "\n=== 6) Receive cost history: can we recover qtyLama from value fields? ===\n";
$recvCard = DB::table('outlet_food_inventory_cards')->where('id', 3830900)->first();
echo "value_in={$recvCard->value_in} saldo_value={$recvCard->saldo_value}\n";
echo "saldo_value/mac_approx=" . ((float) $recvCard->saldo_value / 429.9995) . "\n";
echo "If saldo_value tracks CARD saldo 4350, not stock — expected.\n";

echo "\n=== 7) Any stock_cut_variances left for log 12015? ===\n";
if (Schema::hasTable('stock_cut_variances')) {
    echo "variances 12015=" . DB::table('stock_cut_variances')->where('stock_cut_log_id', 12015)->count() . "\n";
    echo "variances 12032=" . DB::table('stock_cut_variances')->where('stock_cut_log_id', 12032)->count() . "\n";
}

echo "\n=== 8) Timeline summary for root cause ===\n";
echo "2026-11-18 23:07:55 cut#11993 OUT 750 after=2850 (stock synced with card)\n";
echo "2026-11-19 11:00:46 serial_receive#22872 IN 1500 card_saldo=4350 (card=lastCard+in; stock=stock+in)\n";
echo "2026-11-19 ~22:03-22:07 stock_cut#12015 food created (id gap 12014@22:02:48 .. 12016@22:08:03)\n";
echo "2026-11-19 22:08:04 waste CIU processed by user 724 (no tenderloin)\n";
echo "2026-11-19 22:31:44 ROLLBACK #12015 by user 724 cards_affected=83 (hard-deletes log/details/cards)\n";
echo "2026-11-19 22:35:59 stock_cut#12032 food by user 724; tenderloin stock_before=3150 not 4350\n";
echo "CONCLUSION candidate: #12015 reduced stock for tenderloin (-1200) without a restorable card, OR card restored incorrectly; non-transactional cut updates stock before card insert.\n";

echo "\nDONE\n";
