<?php

/**
 * Trace Potato Waffle & Potato Wedges stock cards for DO2609160037 race.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');

echo "===== Find items =====\n";
$items = DB::table('items')
    ->whereIn('name', ['Potato Waffle', 'Potato Wedges'])
    ->select('id', 'name', 'sku')
    ->get();
foreach ($items as $i) {
    echo "item id={$i->id} name={$i->name} sku={$i->sku}\n";
}

echo "\n===== Inventory items =====\n";
$invItems = DB::table('food_inventory_items as fii')
    ->join('items as it', 'it.id', '=', 'fii.item_id')
    ->whereIn('it.name', ['Potato Waffle', 'Potato Wedges'])
    ->select('fii.id as inv_id', 'fii.item_id', 'it.name')
    ->get();
foreach ($invItems as $r) {
    echo "inv_id={$r->inv_id} item_id={$r->item_id} name={$r->name}\n";
}

echo "\n===== Warehouses =====\n";
$whs = DB::table('warehouses')
    ->where(function ($q) {
        $q->where('name', 'like', '%Main Store%')
            ->orWhere('name', 'like', '%Perishable%');
    })
    ->get(['id', 'name']);
foreach ($whs as $w) {
    echo "wh id={$w->id} name={$w->name}\n";
}

// UI label "Main Store - Perishable" = warehouse Main Store (id=1); perishable is item category
$whId = 1;
echo "Using warehouse_id={$whId} (Main Store)\n";

echo "\n===== Two DO2609160037 headers =====\n";
$dos = DB::table('delivery_orders as do')
    ->leftJoin('food_floor_orders as ffo', 'ffo.id', '=', 'do.floor_order_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->where('do.number', 'DO2609160037')
    ->select('do.*', 'ffo.id_outlet', 'o.nama_outlet', 'ffo.warehouse_outlet_id')
    ->get();
foreach ($dos as $do) {
    echo "DO id={$do->id} packing_list={$do->packing_list_id} fo={$do->floor_order_id} outlet={$do->nama_outlet} created={$do->created_at}\n";
}

$itemIds = $items->pluck('id')->all();
$invIds = $invItems->pluck('inv_id')->all();

if (empty($invIds)) {
    echo "No inventory items found.\n";
    exit(1);
}

foreach ($invItems as $inv) {
    $invId = (int) $inv->inv_id;
    $itemId = (int) $inv->item_id;
    echo "\n########## {$inv->name} (item={$itemId} inv={$invId}) ##########\n";

    echo "----- Stock -----\n";
    $stock = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->first();
    if ($stock) {
        echo "stock small={$fmt($stock->qty_small)} med={$fmt($stock->qty_medium)} large={$fmt($stock->qty_large)}\n";
    } else {
        echo "NO STOCK ROW\n";
    }

    echo "----- Cards mentioning DO2609160037 -----\n";
    $doCards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->where('description', 'like', '%DO2609160037%')
        ->orderBy('id')
        ->get();
    foreach ($doCards as $c) {
        echo sprintf(
            "id=%s created=%s date=%s in=%s out=%s saldo=%s ref=%s/%s\n  desc=%s\n",
            $c->id,
            $c->created_at,
            $c->date,
            $fmt($c->in_qty_small),
            $fmt($c->out_qty_small),
            $fmt($c->saldo_qty_small),
            $c->reference_type,
            $c->reference_id,
            $c->description
        );
    }

    echo "----- Sep 16 card chain (cascade with previous card saldo) -----\n";
    $chain = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->whereDate('date', '>=', '2026-09-15')
        ->whereDate('date', '<=', '2026-09-17')
        ->orderBy('date')
        ->orderBy('id')
        ->get();

    $prevSaldo = null;
    $badCount = 0;
    foreach ($chain as $c) {
        if ($prevSaldo === null) {
            $boot = DB::table('food_inventory_cards')
                ->where('inventory_item_id', $invId)
                ->where('warehouse_id', $whId)
                ->where(function ($q) use ($c) {
                    $q->where('date', '<', $c->date)
                        ->orWhere(function ($q2) use ($c) {
                            $q2->where('date', $c->date)->where('id', '<', $c->id);
                        });
                })
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first();
            $prevSaldo = $boot ? (float) $boot->saldo_qty_small : 0.0;
        }
        $should = $prevSaldo + (float) $c->in_qty_small - (float) $c->out_qty_small;
        $actual = (float) $c->saldo_qty_small;
        $ok = abs($should - $actual) < 0.01 ? 'OK' : 'BAD';
        if ($ok === 'BAD') {
            $badCount++;
        }
        $mark = (stripos((string) $c->description, 'DO2609160037') !== false) ? ' <<DO37' : '';
        echo sprintf(
            "  [%s] id=%s prev=%s in=%s out=%s should=%s card=%s%s\n",
            $ok,
            $c->id,
            $fmt($prevSaldo),
            $fmt($c->in_qty_small),
            $fmt($c->out_qty_small),
            $fmt($should),
            $fmt($actual),
            $mark
        );
        $prevSaldo = $actual;
    }
    echo "bad_cards={$badCount}\n";

    echo "----- Last card vs stock -----\n";
    $last = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();
    if ($last && $stock) {
        $diff = (float) $stock->qty_small - (float) $last->saldo_qty_small;
        echo "last card id={$last->id} saldo={$fmt($last->saldo_qty_small)}\n";
        echo "stock={$fmt($stock->qty_small)} diff(stock-card)={$fmt($diff)}\n";
    }

    // Reconstruct correct expected: if Festival card didn't deduct, lost qty = its out
    if ($doCards->count() >= 2) {
        $first = $doCards->first();
        $second = $doCards->skip(1)->first();
        $sameSaldo = abs((float) $first->saldo_qty_small - (float) $second->saldo_qty_small) < 0.01;
        echo "----- Race diagnosis -----\n";
        echo "two DO cards same saldo? ".($sameSaldo ? 'YES (race lost update on card saldo)' : 'NO')."\n";
        echo "second out_qty={$fmt($second->out_qty_small)} (this amount may be missing from stock)\n";
    }

    // Packing list qty for this item on both DOs
    echo "----- Packing list items on DO2609160037 PLs -----\n";
    foreach ($dos as $do) {
        $plItems = DB::table('food_packing_list_items as pli')
            ->where('pli.packing_list_id', $do->packing_list_id)
            ->where('pli.item_id', $itemId)
            ->get();
        echo "PL {$do->packing_list_id} (DO {$do->id} → {$do->nama_outlet}):\n";
        foreach ($plItems as $r) {
            echo "  qty_small={$fmt($r->qty_small ?? $r->quantity ?? 0)} ".json_encode($r)."\n";
        }
        if ($plItems->isEmpty()) {
            echo "  (no line)\n";
        }
    }
}

echo "\nDONE\n";
