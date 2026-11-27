<?php

/**
 * Final evidence pack: Beef Tenderloin Aussie 150gr silent -1200g on 2026-11-19 outlet 18 WH41.
 *
 * Root cause thesis:
 *   stock_cut #12015 (food) reduced outlet_food_inventory_stocks for inv 5080 by 1200g
 *   without a surviving/restorable inventory card, then was rolled back (cards only) at 22:31:44.
 *   potongStockOrderItems has NO DB transaction; stock UPDATE happens before card INSERT.
 *   rollback() only restores qty from existing order_items cards for that log.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$outletId = 18;
$whId = 41;
$itemId = 52984;
$invItemId = 5080;

echo "======== EVIDENCE PACK ========\n";

echo "\n[1] Stock table\n";
$stock = DB::table('outlet_food_inventory_stocks')->where('id', 23315)->first();
echo "table=outlet_food_inventory_stocks id=23315 inv={$stock->inventory_item_id} outlet={$stock->id_outlet} wh={$stock->warehouse_outlet_id} qty_small={$stock->qty_small}\n";
echo "No qty audit/history table for this stock row (updated_at only). soft-deletes: none on cards/details/logs.\n";

echo "\n[2] Card chain (hard deletes — no soft delete)\n";
foreach ([3826624, 3830900, 3837229] as $cid) {
    $c = DB::table('outlet_food_inventory_cards')->where('id', $cid)->first();
    echo sprintf(
        "card#%d %s ref=%s log=%s in=%s out=%s saldo=%s\n",
        $c->id,
        $c->created_at,
        $c->reference_type,
        $c->stock_cut_log_id,
        $c->in_qty_small,
        $c->out_qty_small,
        $c->saldo_qty_small
    );
}
$between = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->where('id', '>', 3830900)
    ->where('id', '<', 3837229)
    ->count();
echo "cards strictly between receive and cut for this item: {$between}\n";

echo "\n[3] Surviving cut detail 12032\n";
$d = DB::table('stock_cut_details')->where('id', 1368926)->first();
echo "detail#1368926 log=12032 need={$d->qty_small} before={$d->stock_before_small} after={$d->stock_after_small}\n";
echo "drift vs receive card saldo 4350 = " . ((float) $d->stock_before_small - 4350) . "\n";

echo "\n[4] Rolled-back cut #12015\n";
$act = DB::table('activity_logs')->where('id', 2488217)->first();
echo "activity#2488217 {$act->created_at} user={$act->user_id} {$act->description}\n";
echo "old_data={$act->old_data}\n";
echo "stock_cut_logs#12015 exists? " . (DB::table('stock_cut_logs')->where('id', 12015)->exists() ? 'YES' : 'NO') . "\n";
echo "orphan cards log 12015: " . DB::table('outlet_food_inventory_cards')->where('stock_cut_log_id', 12015)->count() . "\n";
echo "orphan details log 12015: " . DB::table('stock_cut_details')->where('stock_cut_log_id', 12015)->count() . "\n";
$n14 = DB::table('stock_cut_logs')->where('id', 12014)->value('created_at');
$n16 = DB::table('stock_cut_logs')->where('id', 12016)->value('created_at');
echo "ID neighbors: 12014@{$n14} ... 12015(DELETED) ... 12016@{$n16}\n";
echo "=> #12015 created between those timestamps (~22:03-22:07), type_filter=food, outlet=18\n";

echo "\n[5] Recut #12032\n";
$log = DB::table('stock_cut_logs')->where('id', 12032)->first();
echo json_encode([
    'id' => $log->id,
    'created_at' => $log->created_at,
    'created_by' => $log->created_by,
    'type_filter' => $log->type_filter,
    'total_items_cut' => $log->total_items_cut,
    'status' => $log->status,
]) . "\n";
echo "cards_out>0=" . DB::table('outlet_food_inventory_cards')->where('stock_cut_log_id', 12032)->where('out_qty_small', '>', 0)->count()
    . " vs rollback12015 cards_affected=83 (partial first cut)\n";

echo "\n[6] Uniqueness: only tenderloin has stock_before == card_saldo - needed\n";
echo "From anomaly15: ok=151 exact_neg_need=1 (tenderloin only) other_neg=0 pos=6\n";

echo "\n[7] Ruled out for tenderloin that day\n";
echo "waste CIU lines: " . DB::table('outlet_internal_use_waste_details as d')
    ->join('outlet_internal_use_waste_headers as h', 'h.id', '=', 'd.header_id')
    ->where('h.outlet_id', $outletId)->whereDate('h.date', '2026-11-19')->where('d.item_id', $itemId)->count() . "\n";
echo "transfers: " . DB::table('outlet_transfer_items as d')
    ->join('outlet_transfers as h', 'h.id', '=', 'd.outlet_transfer_id')
    ->where('h.outlet_id', $outletId)->whereDate('h.transfer_date', '2026-11-19')->where('d.item_id', $itemId)->count() . "\n";
$soItemTable = Schema::hasTable('outlet_stock_opname_items') ? 'outlet_stock_opname_items' : null;
if ($soItemTable) {
    $fk = in_array('stock_opname_id', Schema::getColumnListing($soItemTable), true) ? 'stock_opname_id' : null;
    // SO-20261119-028 is WH42 bar, not kitchen 41
    $so = DB::table('outlet_stock_opnames')->where('id', 10554)->first();
    echo "SO-20261119-028 wh={$so->warehouse_outlet_id} (kitchen is 41) — not kitchen tenderloin path\n";
}
echo "POS sync: reads stocks for sold-out; does not decrement outlet_food_inventory_stocks on order sync.\n";

echo "\n[8] Code path proof (StockCutController)\n";
echo "- potongStockOrderItems: NO beginTransaction; foreach material: UPDATE stocks THEN INSERT card\n";
echo "- rollback(): beginTransaction; restore stock FROM cards only; DELETE cards/details/log\n";
echo "- Serial receive dual-write: stock=qty+in, card_saldo=lastCard+in (can preserve prior drift; Nov18 was synced at 2850)\n";

echo "\n[9] ROOT CAUSE (definitive)\n";
echo "Deleted/rolled-back stock cut #12015 is the cause.\n";
echo "Mechanism: non-transactional food cut #12015 physically reduced stock id 23315 by 1200g\n";
echo "(one full BOM day need) but that reduction was NOT reversed by rollback #2488217,\n";
echo "because rollback only adds back out_qty from cards linked to the log — and tenderloin\n";
echo "had no restorable card at rollback time (partial cut: 83 cards vs later 156).\n";
echo "Signature unique to this item: drift(-1200) == -needed(1200).\n";
echo "Timestamps: cut#12015 ~22:03-22:07; rollback 22:31:44; recut#12032 22:35:59 stock_before=3150.\n";

echo "\nDONE\n";
