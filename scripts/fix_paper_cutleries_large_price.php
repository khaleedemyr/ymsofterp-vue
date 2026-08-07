<?php

/**
 * Koreksi Paper Cutleries Large (item 52887):
 * PO/GR harga Pcs 3250 → 325 (typo 10x), lalu rapikan cost history, kartu stok,
 * item_prices, FO (364100→auto benar), dan GSR cost 3700→400.
 *
 * Usage:
 *   php scripts/fix_paper_cutleries_large_price.php
 *   php scripts/fix_paper_cutleries_large_price.php --apply
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FoodGrLastPurchaseForItem;
use App\Support\ItemUnitCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv ?? [], true);

$itemId = 52887;
$poItemId = 34987;
$poId = 15945;
$grId = 13982;
$wrongPoPrice = 3250.0;
$correctPoPrice = 325.0;
$wrongFoPackPrice = 364100.0;
$wrongGsrCost = 3700.0;

echo "=== Fix Paper Cutleries Large price chain ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n\n";

$item = DB::table('items')->where('id', $itemId)->first();
if (! $item) {
    echo "Item not found\n";
    exit(1);
}

$inv = DB::table('food_inventory_items')->where('item_id', $itemId)->first();
$smallConv = (float) ($item->small_conversion_qty ?: 1);

DB::beginTransaction();
try {
    // 1) PO item
    $poi = DB::table('purchase_order_food_items')->where('id', $poItemId)->lockForUpdate()->first();
    if (! $poi || (float) $poi->price != $wrongPoPrice) {
        throw new RuntimeException('PO item price unexpected: ' . ($poi->price ?? 'missing'));
    }
    $qty = (float) $poi->quantity;
    $newPoTotal = round($qty * $correctPoPrice, 2);
    $oldPoTotal = (float) $poi->total;
    echo "1) PO item {$poItemId}: price {$wrongPoPrice} → {$correctPoPrice}, total {$oldPoTotal} → {$newPoTotal}\n";
    if ($apply) {
        DB::table('purchase_order_food_items')->where('id', $poItemId)->update([
            'price' => $correctPoPrice,
            'total' => $newPoTotal,
            'updated_at' => now(),
        ]);
    }

    // 2) PO header totals (single-item PO verified earlier)
    $po = DB::table('purchase_order_foods')->where('id', $poId)->lockForUpdate()->first();
    echo "2) PO header {$po->number}: subtotal/grand {$po->subtotal}/{$po->grand_total} → {$newPoTotal}\n";
    if ($apply) {
        DB::table('purchase_order_foods')->where('id', $poId)->update([
            'subtotal' => $newPoTotal,
            'grand_total' => $newPoTotal,
            'updated_at' => now(),
        ]);
    }

    // 3) Cost history
    if ($inv) {
        $hist = DB::table('food_inventory_cost_histories')
            ->where('inventory_item_id', $inv->id)
            ->where('reference_type', 'good_receive')
            ->where('reference_id', $grId)
            ->first();
        if ($hist) {
            echo "3) Cost history {$hist->id}: new_cost {$hist->new_cost} → {$correctPoPrice}\n";
            if ($apply) {
                DB::table('food_inventory_cost_histories')->where('id', $hist->id)->update([
                    'new_cost' => $correctPoPrice,
                ]);
            }
        } else {
            echo "3) Cost history: not found\n";
        }

        // 4) Inventory card for GR — only this card (rantai kartu berikutnya sudah inkonsisten)
        $card = DB::table('food_inventory_cards')
            ->where('inventory_item_id', $inv->id)
            ->where('reference_type', 'good_receive')
            ->where('reference_id', $grId)
            ->first();
        if ($card) {
            $oldCost = (float) $card->cost_per_small;
            $oldValueIn = (float) $card->value_in;
            $newValueIn = round(((float) $card->in_qty_small) * $correctPoPrice, 4);
            $prevSaldoValue = round((float) $card->saldo_value - $oldValueIn, 4);
            $newSaldoValue = round($prevSaldoValue + $newValueIn, 4);
            echo "4) Card {$card->id}: cost {$oldCost} → {$correctPoPrice}, value_in {$oldValueIn} → {$newValueIn}, saldo_value {$card->saldo_value} → {$newSaldoValue}\n";
            if ($apply) {
                DB::table('food_inventory_cards')->where('id', $card->id)->update([
                    'cost_per_small' => $correctPoPrice,
                    'value_in' => $newValueIn,
                    'saldo_value' => $newSaldoValue,
                ]);
            }

            // 5) Stock: pakai last purchase yang benar (rantai MAC kartu setelah GR sudah rusak)
            $stock = DB::table('food_inventory_stocks')->where('inventory_item_id', $inv->id)->where('warehouse_id', 1)->first();
            if ($stock) {
                $qtySmall = (float) $stock->qty_small;
                $newMac = $correctPoPrice;
                $saldoValue = round($qtySmall * $newMac, 4);
                echo "5) Stock {$stock->id}: last_cost {$stock->last_cost_small} → {$newMac}, value {$stock->value} → {$saldoValue} (qty={$qtySmall})\n";
                if ($apply) {
                    DB::table('food_inventory_stocks')->where('id', $stock->id)->update([
                        'last_cost_small' => $newMac,
                        'last_cost_medium' => round($newMac * $smallConv, 4),
                        'last_cost_large' => round($newMac * $smallConv * ((float) ($item->medium_conversion_qty ?: 1)), 4),
                        'value' => $saldoValue,
                        'updated_at' => now(),
                    ]);
                }
            }
        } else {
            echo "4) Card: not found\n";
        }
    }

    // 6) item_prices cached large price (mode auto)
    $expectedLarge = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
    // During dry-run lastLine still sees old PO price; compute expected manually:
    $expectedLargeFixed = FloorOrderItemPriceResolver::roundUpToHundred($correctPoPrice * $smallConv * 1.12);
    $expectedPcs = FloorOrderItemPriceResolver::roundUpToHundred($expectedLargeFixed / $smallConv);
    echo "6) Expected after fix: Pack/large={$expectedLargeFixed}, Pcs={$expectedPcs}\n";
    // Note: during APPLY, after PO update, suggestedSellingPrice will match.

    $priceRow = DB::table('item_prices')->where('item_id', $itemId)->where('availability_price_type', 'all')->orderByDesc('id')->first();
    if ($priceRow) {
        echo "   item_prices {$priceRow->id}: price {$priceRow->price} → {$expectedLargeFixed} (mode={$priceRow->pricing_mode})\n";
        if ($apply) {
            DB::table('item_prices')->where('id', $priceRow->id)->update([
                'price' => $expectedLargeFixed,
                'updated_at' => now(),
            ]);
        }
    }

    // 7) FO lines with wrong pack price
    $foQuery = DB::table('food_floor_order_items as foi')
        ->join('food_floor_orders as fo', 'foi.floor_order_id', '=', 'fo.id')
        ->where('foi.item_id', $itemId)
        ->where(function ($q) use ($wrongFoPackPrice, $wrongGsrCost) {
            $q->where('foi.price', $wrongFoPackPrice)
                ->orWhere('foi.price', $wrongGsrCost); // pcs sell if any
        });
    $foCount = (clone $foQuery)->count();
    $foPackCount = DB::table('food_floor_order_items')->where('item_id', $itemId)->where('price', $wrongFoPackPrice)->count();
    $foPcsCount = DB::table('food_floor_order_items')->where('item_id', $itemId)->where('price', $wrongGsrCost)->count();
    echo "7) FO lines: Pack@{$wrongFoPackPrice}={$foPackCount}, Pcs@{$wrongGsrCost}={$foPcsCount}\n";
    if ($apply) {
        DB::table('food_floor_order_items')
            ->where('item_id', $itemId)
            ->where('price', $wrongFoPackPrice)
            ->update(['price' => $expectedLargeFixed, 'updated_at' => now()]);
        DB::table('food_floor_order_items')
            ->where('item_id', $itemId)
            ->where('price', $wrongGsrCost)
            ->update(['price' => $expectedPcs, 'updated_at' => now()]);
    }

    // 8) GSR cost_small 3700 → 400
    $gsrItems = DB::table('outlet_serial_receive_items')
        ->where('item_id', $itemId)
        ->where('cost_small', $wrongGsrCost)
        ->get(['id', 'header_id', 'qty', 'unit_id', 'cost_small']);
    echo '8) GSR items cost 3700: ' . $gsrItems->count() . " → {$expectedPcs}\n";
    $headerIds = $gsrItems->pluck('header_id')->unique()->values();
    if ($apply) {
        DB::table('outlet_serial_receive_items')
            ->where('item_id', $itemId)
            ->where('cost_small', $wrongGsrCost)
            ->update([
                'cost_small' => $expectedPcs,
                'updated_at' => now(),
            ]);
    }

    // 9) Recalc outlet_payments.total_amount for affected GSR headers
    if ($headerIds->isNotEmpty() && Schema::hasTable('outlet_payments') && Schema::hasColumn('outlet_payments', 'gsr_id')) {
        $pays = DB::table('outlet_payments')->whereIn('gsr_id', $headerIds)->get(['id', 'gsr_id', 'total_amount']);
        echo '9) outlet_payments to recalc: ' . $pays->count() . "\n";
        if ($apply) {
            foreach ($headerIds as $hid) {
                $rows = DB::table('outlet_serial_receive_items as si')
                    ->join('items as it', 'si.item_id', '=', 'it.id')
                    ->where('si.header_id', $hid)
                    ->select('si.qty', 'si.unit_id', 'si.cost_small', 'it.small_conversion_qty', 'it.medium_conversion_qty', 'it.small_unit_id', 'it.medium_unit_id', 'it.large_unit_id')
                    ->get();
                $total = 0.0;
                foreach ($rows as $r) {
                    $total += ItemUnitCost::lineSubtotal((float) $r->cost_small, $r, $r->unit_id, (float) $r->qty);
                }
                DB::table('outlet_payments')->where('gsr_id', $hid)->update([
                    'total_amount' => round($total, 2),
                    'updated_at' => now(),
                ]);
                echo "   gsr_id={$hid} new payment_total=" . round($total, 2) . "\n";
            }
        }
    } else {
        echo "9) outlet_payments: skip (no gsr_id column or no headers)\n";
    }

    // Verify auto chain after apply
    if ($apply) {
        $verify = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
        $verifyPcs = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, 'Pcs', null, null, $item);
        $verifyPack = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, 'Pack', null, null, $item);
        echo "\nVERIFY suggested={$verify} Pack={$verifyPack} Pcs={$verifyPcs}\n";
    }

    if ($apply) {
        DB::commit();
        echo "\nAPPLIED OK\n";
    } else {
        DB::rollBack();
        echo "\nDRY-RUN only (rolled back). Jalankan dengan --apply untuk simpan.\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
