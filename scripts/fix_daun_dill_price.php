<?php

declare(strict_types=1);

/**
 * Koreksi Daun Dill (item 53126) PO POF26080605:
 * salah input 18.000/Gram (harusnya 20/Gram, setara 20.000/Kg di PO lain).
 *
 * Rantai: PO (+ PR bila sama), GR cost/kartu, MAC stok MK2, serial bila ada.
 * GR tidak punya kolom harga sendiri — UI baca poi.price.
 *
 * Usage:
 *   php scripts/fix_daun_dill_price.php
 *   php scripts/fix_daun_dill_price.php --apply
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv ?? [], true);

$itemId = 53126;
$poId = 16998;
$poiId = 37012;
$grId = 15040;
$grIdLater = 15119;
$invId = 4898;
$warehouseId = 5;
$wrongPrice = 18000.0;
$correctPrice = 20.0;
$correctCostSmall = 20.0;
$correctCostMedium = 20000.0;
$correctCostLarge = 20000.0;

echo "=== Fix Daun Dill price (18.000/Gram → 20/Gram) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n\n";

$item = DB::table('items')->where('id', $itemId)->first();
if (! $item || $item->name !== 'Daun Dill') {
    echo "Item Daun Dill tidak ditemukan.\n";
    exit(1);
}

$po = DB::table('purchase_order_foods')->where('id', $poId)->first();
$poi = DB::table('purchase_order_food_items')->where('id', $poiId)->first();
if (! $po || ! $poi || (int) $poi->item_id !== $itemId) {
    echo "PO/line tidak cocok.\n";
    exit(1);
}

$qty = (float) $poi->quantity;
$newLineTotal = round($qty * $correctPrice, 2);
$oldLineTotal = (float) $poi->total;
$diff = $oldLineTotal - $newLineTotal;

echo "Item {$itemId} {$item->name}: 1 Kg = {$item->small_conversion_qty} Gram\n";
echo "PO {$po->number} poi={$poiId}: price {$poi->price} → {$correctPrice}, line {$oldLineTotal} → {$newLineTotal}\n";

if ((float) $poi->price !== $wrongPrice) {
    echo "Harga PO sudah bukan {$wrongPrice}. Abort agar tidak double-fix.\n";
    exit(0);
}

$newSub = round((float) $po->subtotal - $diff, 2);
$newGrand = round((float) $po->grand_total - $diff, 2);
echo "Header subtotal/grand {$po->subtotal}/{$po->grand_total} → {$newSub}/{$newGrand}\n";

$prItem = null;
if (! empty($poi->pr_food_item_id)) {
    $prItem = DB::table('pr_food_items')->where('id', $poi->pr_food_item_id)->first();
    if ($prItem) {
        echo "PR item {$prItem->id}: qty={$prItem->qty} price=" . ($prItem->price ?? '-') . "\n";
    }
}

$prevCard = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $warehouseId)
    ->where('id', '<', 709140)
    ->orderByDesc('id')
    ->first();

if (! $prevCard) {
    echo "Kartu sebelum GR 15040 tidak ditemukan.\n";
    exit(1);
}

$qtyLama = (float) $prevCard->saldo_qty_small;
$nilaiLama = (float) $prevCard->saldo_value;
$inQty = 500.0;
$qtyAfterGr = $qtyLama + $inQty;
$valueIn = round($inQty * $correctCostSmall, 4);
$nilaiAfterGr = round($nilaiLama + $valueIn, 4);
$macAfterGr = $qtyAfterGr > 0 ? round($nilaiAfterGr / $qtyAfterGr, 4) : $correctCostSmall;
$grSaldoValue = round($qtyAfterGr * $correctCostSmall, 4);

echo "\nBaseline kartu {$prevCard->id}: qty={$qtyLama} val={$nilaiLama}\n";
echo "GR 15040: cost 18000 → {$correctCostSmall}, value_in 9.000.000 → {$valueIn}\n";
echo "MAC setelah GR 15040: {$macAfterGr} (nilai {$nilaiAfterGr} / qty {$qtyAfterGr})\n";
echo "Kartu GR saldo_value (rumus GR qty*harga): {$grSaldoValue}\n";

$cards = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $warehouseId)
    ->where('id', '>=', 709140)
    ->orderBy('id')
    ->get();

$serialCount = DB::table('inventory_item_serials')
    ->where('item_id', $itemId)
    ->where(function ($q) {
        $q->where('source_item_id', 34036)
            ->orWhere('source_id', 15040)
            ->orWhere('ref_gr_number', 'GR-20260813-0010')
            ->orWhere('ref_po_number', 'POF26080605')
            ->orWhere('cost_small', '>=', 1000);
    })
    ->count();
echo "Serial terkait GR/PO/harga >= 1000: {$serialCount}\n";

$productions = DB::table('mk_productions as p')
    ->join('items as i', 'i.id', '=', 'p.item_id')
    ->join('item_bom as b', 'b.item_id', '=', 'p.item_id')
    ->where('b.material_item_id', $itemId)
    ->where('p.warehouse_id', $warehouseId)
    ->whereBetween('p.production_date', ['2026-08-13', '2026-08-14'])
    ->select('p.id', 'p.production_date', 'p.batch_number', 'i.name as fg_name', 'p.qty', 'p.qty_jadi', 'b.qty as bom_qty')
    ->get();
echo 'Produksi MK2 13-14 Agu yang BOM-nya pakai Daun Dill: ' . $productions->count() . "\n";
foreach ($productions as $p) {
    echo "  prod={$p->id} {$p->production_date} {$p->batch_number} FG={$p->fg_name} qty={$p->qty} jadi={$p->qty_jadi} bom_qty={$p->bom_qty}\n";
}

DB::beginTransaction();
try {
    echo "\n1) PO line + header\n";
    if ($apply) {
        DB::table('purchase_order_food_items')->where('id', $poiId)->update([
            'price' => $correctPrice,
            'total' => $newLineTotal,
            'updated_at' => now(),
        ]);
        DB::table('purchase_order_foods')->where('id', $poId)->update([
            'subtotal' => $newSub,
            'grand_total' => $newGrand,
            'updated_at' => now(),
        ]);
    }

    if ($prItem && isset($prItem->price) && (float) $prItem->price === $wrongPrice) {
        echo "2) PR item {$prItem->id}: price {$prItem->price} → {$correctPrice}\n";
        if ($apply) {
            $prUpdate = ['price' => $correctPrice, 'updated_at' => now()];
            if (Schema::hasColumn('pr_food_items', 'total')) {
                $prUpdate['total'] = round((float) $prItem->qty * $correctPrice, 2);
            }
            DB::table('pr_food_items')->where('id', $prItem->id)->update($prUpdate);
        }
    } else {
        echo "2) PR: skip (bukan 18000 atau tidak ada kolom price)\n";
    }

    $hist15040 = DB::table('food_inventory_cost_histories')
        ->where('inventory_item_id', $invId)
        ->where('reference_type', 'good_receive')
        ->where('reference_id', $grId)
        ->first();
    if ($hist15040) {
        echo "3) Cost history {$hist15040->id} GR 15040: new_cost {$hist15040->new_cost} → {$correctCostSmall}, mac {$hist15040->mac} → {$macAfterGr}\n";
        if ($apply) {
            DB::table('food_inventory_cost_histories')->where('id', $hist15040->id)->update([
                'new_cost' => $correctCostSmall,
                'mac' => $macAfterGr,
            ]);
        }
    }

    $runningQty = $qtyLama;
    $runningVal = $nilaiLama;
    $currentMac = $macAfterGr;
    $stockValueForNextGr = $nilaiAfterGr;

    echo "4) Replay kartu stok MK2 sejak GR 15040\n";
    foreach ($cards as $card) {
        $in = (float) $card->in_qty_small;
        $out = (float) $card->out_qty_small;
        $update = [];

        if ($card->reference_type === 'good_receive' && (int) $card->reference_id === $grId) {
            $runningQty += $in;
            $runningVal = $nilaiAfterGr;
            $currentMac = $macAfterGr;
            $update = [
                'cost_per_small' => $correctCostSmall,
                'cost_per_medium' => $correctCostMedium,
                'cost_per_large' => $correctCostLarge,
                'value_in' => $valueIn,
                'saldo_value' => $grSaldoValue,
            ];
        } elseif ($card->reference_type === 'good_receive' && (int) $card->reference_id === $grIdLater) {
            $nilaiLama15119 = $stockValueForNextGr;
            $nilaiAfter15119 = round($nilaiLama15119 + $valueIn, 4);
            $runningQty += $in;
            $mac15119 = $runningQty > 0 ? round($nilaiAfter15119 / $runningQty, 4) : $correctCostSmall;
            $runningVal = $nilaiAfter15119;
            $currentMac = $mac15119;
            $gr15119Saldo = round($runningQty * $correctCostSmall, 4);
            $update = [
                'cost_per_small' => $correctCostSmall,
                'cost_per_medium' => $correctCostMedium,
                'cost_per_large' => $correctCostLarge,
                'value_in' => $valueIn,
                'saldo_value' => $gr15119Saldo,
            ];
            echo "   GR 15119 cost history mac → {$mac15119}, stock.value → {$nilaiAfter15119}\n";
            $hist15119 = DB::table('food_inventory_cost_histories')
                ->where('inventory_item_id', $invId)
                ->where('reference_type', 'good_receive')
                ->where('reference_id', $grIdLater)
                ->first();
            if ($hist15119) {
                echo "5) Cost history {$hist15119->id} GR 15119: old {$hist15119->old_cost} → {$correctCostSmall}, mac {$hist15119->mac} → {$mac15119}\n";
                if ($apply) {
                    DB::table('food_inventory_cost_histories')->where('id', $hist15119->id)->update([
                        'old_cost' => $correctCostSmall,
                        'mac' => $mac15119,
                    ]);
                }
            }
            $finalMac = $mac15119;
            $finalValue = $nilaiAfter15119;
        } else {
            $runningQty -= $out;
            $valueOut = round($out * $currentMac, 4);
            $saldoVal = round($runningQty * $currentMac, 4);
            $update = [
                'cost_per_small' => $currentMac,
                'cost_per_medium' => $correctCostMedium,
                'cost_per_large' => $correctCostLarge,
                'value_out' => $valueOut,
                'saldo_value' => $saldoVal,
            ];
        }

        echo sprintf(
            "   card %d %s ref=%s in=%s out=%s cost %s→%s val_in %s→%s val_out %s→%s saldo_val %s→%s\n",
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

    $stockMk2 = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $warehouseId)
        ->first();
    $finalMac = $finalMac ?? $currentMac;
    $finalValue = $finalValue ?? $runningVal;
    echo "6) Stock MK2 id={$stockMk2->id}: last_cost {$stockMk2->last_cost_small} → {$finalMac}, value {$stockMk2->value} → {$finalValue} (qty={$stockMk2->qty_small})\n";
    if ($apply) {
        DB::table('food_inventory_stocks')->where('id', $stockMk2->id)->update([
            'last_cost_small' => $finalMac,
            'last_cost_medium' => $correctCostMedium,
            'last_cost_large' => $correctCostLarge,
            'value' => $finalValue,
            'updated_at' => now(),
        ]);
    }

    $stockMain = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', 1)
        ->first();
    if ($stockMain && (float) $stockMain->qty_small == 0.0 && (float) $stockMain->last_cost_small == 18.0) {
        echo "7) Stock Main Store id={$stockMain->id}: last_cost 18/18000 → 20/20000 (qty=0)\n";
        if ($apply) {
            DB::table('food_inventory_stocks')->where('id', $stockMain->id)->update([
                'last_cost_small' => $correctCostSmall,
                'last_cost_medium' => $correctCostMedium,
                'last_cost_large' => $correctCostLarge,
                'updated_at' => now(),
            ]);
        }
    } else {
        echo "7) Stock Main Store: skip\n";
    }

    $serials = DB::table('inventory_item_serials')
        ->where('item_id', $itemId)
        ->where(function ($q) {
            $q->where('source_item_id', 34036)
                ->orWhere('source_id', 15040)
                ->orWhere('ref_gr_number', 'GR-20260813-0010')
                ->orWhere('ref_po_number', 'POF26080605')
                ->orWhere('cost_small', '>=', 1000);
        })
        ->get(['id', 'serial_number', 'cost_small', 'cost_large']);
    echo '8) Serial: ' . $serials->count() . " → cost_small {$correctCostSmall}, cost_large {$correctCostLarge}\n";
    foreach ($serials as $s) {
        echo "   {$s->serial_number} cost_s={$s->cost_small} cost_l={$s->cost_large}\n";
    }
    if ($apply && $serials->isNotEmpty()) {
        DB::table('inventory_item_serials')
            ->whereIn('id', $serials->pluck('id'))
            ->update([
                'cost_small' => $correctCostSmall,
                'cost_medium' => $correctCostMedium,
                'cost_large' => $correctCostLarge,
                'updated_at' => now(),
            ]);
    }

    if ($apply) {
        DB::commit();
        echo "\nAPPLIED OK\n";
        $verifyPo = DB::table('purchase_order_food_items')->where('id', $poiId)->first();
        $verifyGrHist = DB::table('food_inventory_cost_histories')->where('id', $hist15040->id ?? 0)->first();
        $verifyStock = DB::table('food_inventory_stocks')->where('id', $stockMk2->id)->first();
        echo "VERIFY poi.price={$verifyPo->price} total={$verifyPo->total}\n";
        echo "VERIFY hist15040 new={$verifyGrHist->new_cost} mac={$verifyGrHist->mac}\n";
        echo "VERIFY stock last_s={$verifyStock->last_cost_small} last_l={$verifyStock->last_cost_large} value={$verifyStock->value}\n";
    } else {
        DB::rollBack();
        echo "\nDRY-RUN only. Jalankan dengan --apply untuk simpan.\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
