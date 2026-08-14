<?php

declare(strict_types=1);

/**
 * Koreksi HPP Butter Portion (item 54693) di MK2:
 * 5 produksi 13–14 Agu memakai Daun Dill @ MAC 2.886,29 (harusnya 65,6947).
 *
 * Usage:
 *   php scripts/fix_butter_portion_dill_hpp.php
 *   php scripts/fix_butter_portion_dill_hpp.php --apply
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv ?? [], true);

$itemId = 54693;
$invId = 4783;
$warehouseId = 5;
$prodIds = [11440, 11441, 11520, 11521, 11522];
$wrongDillCost = 2886.2939;
$correctDillCost = 65.6947;
$dillQtyPerBatch = 40.0; // 2 gram × qty produksi 20
$smallConv = 14.0;
$extraBom = $dillQtyPerBatch * ($wrongDillCost - $correctDillCost); // 112823.968

echo "=== Fix Butter Portion HPP (Daun Dill MAC 2886.2939 → 65.6947) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo 'Extra BOM per batch: ' . round($extraBom, 4) . "\n\n";

$item = DB::table('items')->where('id', $itemId)->first();
if (! $item || $item->name !== 'Butter Portion') {
    echo "Item Butter Portion tidak ditemukan.\n";
    exit(1);
}

$prods = DB::table('mk_productions')->whereIn('id', $prodIds)->orderBy('id')->get()->keyBy('id');
$prodCards = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $warehouseId)
    ->where('reference_type', 'mk_production')
    ->whereIn('reference_id', $prodIds)
    ->orderBy('id')
    ->get()
    ->keyBy('reference_id');

$firstProdCard = $prodCards[11440] ?? null;
if (! $firstProdCard) {
    echo "Kartu produksi 11440 tidak ditemukan.\n";
    exit(1);
}

$nilaiLama11440 = round((float) $firstProdCard->saldo_value - (float) $firstProdCard->value_in, 4);
$qtyBefore = (float) $firstProdCard->saldo_qty_small - (float) $firstProdCard->in_qty_small;
echo "Baseline sebelum prod 11440: qty={$qtyBefore} nilai_lama={$nilaiLama11440}\n\n";

$corrected = [];
foreach ($prodIds as $pid) {
    $prod = $prods[$pid];
    $card = $prodCards[$pid];
    $qtyJadi = (float) $prod->qty_jadi;
    $oldCostSmall = (float) $card->cost_per_small;
    $oldBom = $oldCostSmall * $qtyJadi;
    $newBom = $oldBom - $extraBom;
    $newCostSmall = round($newBom / $qtyJadi, 4);
    $newCostMedium = round($newCostSmall * $smallConv, 4);
    $newCostLarge = $newCostMedium;
    $qtySmall = (float) $card->in_qty_small;
    $newValueIn = round($qtySmall * $newCostSmall, 4);
    $corrected[$pid] = [
        'card' => $card,
        'prod' => $prod,
        'new_cost_small' => $newCostSmall,
        'new_cost_medium' => $newCostMedium,
        'new_cost_large' => $newCostLarge,
        'new_value_in' => $newValueIn,
        'old_cost_small' => $oldCostSmall,
        'old_value_in' => (float) $card->value_in,
    ];
    echo "prod {$pid} jadi={$qtyJadi}: cost {$oldCostSmall} → {$newCostSmall}, value_in {$card->value_in} → {$newValueIn}\n";
}

$cards = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $warehouseId)
    ->where('id', '>=', $firstProdCard->id)
    ->orderBy('id')
    ->get();

$serials = DB::table('inventory_item_serials')
    ->where('item_id', $itemId)
    ->where('source_type', 'mk_production')
    ->whereIn('source_id', $prodIds)
    ->get(['id', 'serial_number', 'source_id', 'cost_small', 'cost_large', 'is_out', 'out_outlet_id']);
echo "\nSerial dari 5 produksi: " . $serials->count() . "\n";
$serialBySrc = $serials->groupBy('source_id');
foreach ($prodIds as $pid) {
    $cnt = isset($serialBySrc[$pid]) ? $serialBySrc[$pid]->count() : 0;
    echo "  prod {$pid}: {$cnt} serial\n";
}

$outletStocks = DB::table('outlet_food_inventory_stocks as s')
    ->join('outlet_food_inventory_items as oi', 'oi.id', '=', 's.inventory_item_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 's.id_outlet')
    ->where('oi.item_id', $itemId)
    ->where('s.qty_small', '>', 0)
    ->select('s.id', 's.id_outlet', 'o.nama_outlet', 's.qty_small', 's.last_cost_small', 's.value')
    ->orderBy('s.id_outlet')
    ->get();
echo "\nOutlet stok qty>0: " . $outletStocks->count() . "\n";
foreach ($outletStocks as $os) {
    echo "  {$os->nama_outlet} qty={$os->qty_small} last_s={$os->last_cost_small} value={$os->value}\n";
}

DB::beginTransaction();
try {
    $runningQty = $qtyBefore;
    $stockValue = $nilaiLama11440; // nilai yang dipakai rumus produksi (tidak berkurang saat DO)
    $currentMac = 0.0;
    $lastBatchMedium = 0.0;
    $lastBatchLarge = 0.0;
    $finalMac = 0.0;
    $finalStockValue = 0.0;

    echo "\nReplay kartu MK2:\n";
    foreach ($cards as $card) {
        $in = (float) $card->in_qty_small;
        $out = (float) $card->out_qty_small;
        $update = [];

        if ($card->reference_type === 'mk_production' && isset($corrected[(int) $card->reference_id])) {
            $fix = $corrected[(int) $card->reference_id];
            $runningQty += $in;
            $stockValue = round($stockValue + $fix['new_value_in'], 4);
            $currentMac = $runningQty > 0 ? round($stockValue / $runningQty, 4) : $fix['new_cost_small'];
            $lastBatchMedium = $fix['new_cost_medium'];
            $lastBatchLarge = $fix['new_cost_large'];
            $update = [
                'cost_per_small' => $fix['new_cost_small'],
                'cost_per_medium' => $fix['new_cost_medium'],
                'cost_per_large' => $fix['new_cost_large'],
                'value_in' => $fix['new_value_in'],
                'saldo_value' => $stockValue,
            ];
            $hist = DB::table('food_inventory_cost_histories')
                ->where('inventory_item_id', $invId)
                ->where('reference_type', 'mk_production')
                ->where('reference_id', $card->reference_id)
                ->first();
            if ($hist) {
                echo "  hist {$hist->id} prod {$card->reference_id}: new {$hist->new_cost} → {$fix['new_cost_small']}, mac {$hist->mac} → {$currentMac}\n";
                if ($apply) {
                    DB::table('food_inventory_cost_histories')->where('id', $hist->id)->update([
                        'new_cost' => $fix['new_cost_small'],
                        'mac' => $currentMac,
                    ]);
                }
            }
            $finalMac = $currentMac;
            $finalStockValue = $stockValue;
        } else {
            $runningQty -= $out;
            $valueOut = round($out * $currentMac, 4);
            $saldoVal = round($runningQty * $currentMac, 4);
            $update = [
                'cost_per_small' => $currentMac,
                'cost_per_medium' => $lastBatchMedium,
                'cost_per_large' => $lastBatchLarge,
                'value_out' => $valueOut,
                'saldo_value' => $saldoVal,
            ];
        }

        echo sprintf(
            "  card %d %s ref=%s in=%s out=%s cost %s→%s val_in %s→%s val_out %s→%s saldo %s→%s\n",
            $card->id,
            $card->reference_type,
            $card->reference_id,
            $card->in_qty_small,
            $card->out_qty_small,
            $card->cost_per_small,
            $update['cost_per_small'] ?? $card->cost_per_small,
            $card->value_in,
            $update['value_in'] ?? $card->value_in,
            $card->value_out,
            $update['value_out'] ?? $card->value_out,
            $card->saldo_value,
            $update['saldo_value'] ?? $card->saldo_value
        );

        if ($apply && $update !== []) {
            DB::table('food_inventory_cards')->where('id', $card->id)->update($update);
        }
    }

    $stock = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $warehouseId)
        ->first();
    echo "\nStock MK2 id={$stock->id}: last_s {$stock->last_cost_small} → {$finalMac}, last_m {$stock->last_cost_medium} → {$lastBatchMedium}, value {$stock->value} → {$finalStockValue} (qty={$stock->qty_small}, card_qty={$runningQty})\n";
    if ($apply) {
        DB::table('food_inventory_stocks')->where('id', $stock->id)->update([
            'last_cost_small' => $finalMac,
            'last_cost_medium' => $lastBatchMedium,
            'last_cost_large' => $lastBatchLarge,
            'value' => $finalStockValue,
            'updated_at' => now(),
        ]);
    }

    echo "\nSerial:\n";
    foreach ($serials as $s) {
        $fix = $corrected[(int) $s->source_id] ?? null;
        if (! $fix) {
            continue;
        }
        echo "  {$s->serial_number} prod={$s->source_id} cost_s {$s->cost_small} → {$fix['new_cost_small']} out={$s->is_out} outlet={$s->out_outlet_id}\n";
        if ($apply) {
            DB::table('inventory_item_serials')->where('id', $s->id)->update([
                'cost_small' => $fix['new_cost_small'],
                'cost_medium' => $fix['new_cost_medium'],
                'cost_large' => $fix['new_cost_large'],
                'updated_at' => now(),
            ]);
        }
    }

    if ($apply) {
        DB::commit();
        echo "\nAPPLIED OK\n";
        $vCard = DB::table('food_inventory_cards')->where('id', $firstProdCard->id)->first();
        $vStock = DB::table('food_inventory_stocks')->where('id', $stock->id)->first();
        echo "VERIFY prod11440 cost={$vCard->cost_per_small} value_in={$vCard->value_in}\n";
        echo "VERIFY stock last_s={$vStock->last_cost_small} last_m={$vStock->last_cost_medium} value={$vStock->value}\n";
    } else {
        DB::rollBack();
        echo "\nDRY-RUN only. Jalankan dengan --apply untuk simpan.\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
