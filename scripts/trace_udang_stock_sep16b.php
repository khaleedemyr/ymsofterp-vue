<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');

$invId = 5572; // Udang
$whId = 1;

echo "===== Two DO2609160037 headers =====\n";
$dos = DB::table('delivery_orders as do')
    ->leftJoin('food_floor_orders as ffo', 'ffo.id', '=', 'do.floor_order_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->where('do.number', 'DO2609160037')
    ->select('do.*', 'ffo.id_outlet', 'o.nama_outlet', 'ffo.warehouse_outlet_id')
    ->get();
foreach ($dos as $do) {
    echo "DO id={$do->id} packing_list={$do->packing_list_id} fo={$do->floor_order_id} outlet={$do->nama_outlet} created={$do->created_at} by={$do->created_by}\n";
}

echo "\n===== Cards id 777003 & 777038 detail =====\n";
$cards = DB::table('food_inventory_cards')
    ->whereIn('id', [777003, 777038, 776705, 777073])
    ->orderBy('id')
    ->get();
foreach ($cards as $c) {
    echo sprintf(
        "id=%s created=%s date=%s out_s=%s saldo_s=%s ref=%s/%s desc=%s\n",
        $c->id,
        $c->created_at,
        $c->date,
        $fmt($c->out_qty_small),
        $fmt($c->saldo_qty_small),
        $c->reference_type,
        $c->reference_id,
        $c->description
    );
}

echo "\n===== Reconstruct expected saldo chain from 15 Sep =====\n";
$chain = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->where('date', '>=', '2026-09-15')
    ->orderBy('date')
    ->orderBy('id')
    ->get();

$prev = null;
$expected = null;
$errors = 0;
foreach ($chain as $c) {
    if ($expected === null) {
        // bootstrap from first card's implied prev
        $expected = (float) $c->saldo_qty_small + (float) $c->out_qty_small - (float) $c->in_qty_small;
    }
    $calc = $expected + (float) $c->in_qty_small - (float) $c->out_qty_small;
    $actual = (float) $c->saldo_qty_small;
    $ok = abs($calc - $actual) < 0.001 ? 'OK' : 'BAD';
    if ($ok === 'BAD') {
        $errors++;
    }
    if ($c->date >= '2026-09-15' && $c->date <= '2026-09-16') {
        echo sprintf(
            "  [%s] id=%s out=%s in=%s saldo_card=%s expected=%s delta=%s\n",
            $ok,
            $c->id,
            $fmt($c->out_qty_small),
            $fmt($c->in_qty_small),
            $fmt($actual),
            $fmt($calc),
            $fmt($actual - $calc)
        );
    }
    $expected = $calc; // continue chain from CORRECT expected, not broken card
    // Actually to show cascading: use actual for next? User wants to see both.
    // Continue with correct calc so we see cumulative drift if cards used broken prev.
}

echo "errors_vs_correct_chain={$errors}\n";

echo "\n===== Cascade if each card trusted previous card saldo =====\n";
$prevSaldo = null;
foreach ($chain as $c) {
    if ($c->date < '2026-09-15' || $c->date > '2026-09-17') {
        $prevSaldo = (float) $c->saldo_qty_small;
        continue;
    }
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
    $ok = abs($should - $actual) < 0.001 ? 'OK' : 'BAD';
    echo sprintf(
        "  [%s] id=%s prev=%s out=%s should=%s card=%s | %s\n",
        $ok,
        $c->id,
        $fmt($prevSaldo),
        $fmt($c->out_qty_small),
        $fmt($should),
        $fmt($actual),
        substr($c->description, 0, 80)
    );
    $prevSaldo = $actual; // next uses what card stored (broken chain)
}

echo "\n===== Current stock vs last card =====\n";
$stock = DB::table('food_inventory_stocks')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->first();
$last = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->first();
echo "stock small={$fmt($stock->qty_small)} med={$fmt($stock->qty_medium)} large={$fmt($stock->qty_large)}\n";
echo "last card id={$last->id} date={$last->date} saldo_s={$fmt($last->saldo_qty_small)} created={$last->created_at}\n";
echo "diff stock-card=".($fmt((float)$stock->qty_small - (float)$last->saldo_qty_small))."\n";

// Sum all outs from 16 Sep for udang
$sumOut16 = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->whereDate('date', '2026-09-16')
    ->sum('out_qty_small');
echo "sum out 16 Sep={$fmt($sumOut16)} (= ".($sumOut16/1000)." kg)\n";

echo "\n===== Serial movements for these DOs if any =====\n";
foreach ([65749, 65750] as $doId) {
    $serialCount = 0;
    if (DB::getSchemaBuilder()->hasTable('delivery_order_serial_items')) {
        $serialCount = DB::table('delivery_order_serial_items')->where('delivery_order_id', $doId)->count();
    } elseif (DB::getSchemaBuilder()->hasTable('delivery_order_items')) {
        $rows = DB::table('delivery_order_items')->where('delivery_order_id', $doId)->get();
        echo "DO {$doId} items:\n";
        foreach ($rows as $r) {
            echo '  '.json_encode($r)."\n";
        }
        continue;
    }
    echo "DO {$doId} serial_items count={$serialCount}\n";
}

// packing list items for udang
echo "\n===== Packing list items for udang on these PLs =====\n";
foreach ($dos as $do) {
    $plItems = DB::table('food_packing_list_items as pli')
        ->join('items as it', 'it.id', '=', 'pli.item_id')
        ->where('pli.packing_list_id', $do->packing_list_id)
        ->where('pli.item_id', 53330)
        ->select('pli.*', 'it.name')
        ->get();
    echo "PL {$do->packing_list_id} (DO {$do->id} → {$do->nama_outlet}):\n";
    foreach ($plItems as $r) {
        echo '  '.json_encode($r)."\n";
    }
}
