<?php

declare(strict_types=1);

/**
 * Koreksi Vinegar Dixi (item 52521): PO Botol 150.000 → 15.000 (typo 10x).
 * Rantai: PO, GR cost, kartu/stok, serial HPP, GSR jual +12%, item_prices auto, FO.
 *
 * Usage:
 *   php scripts/fix_vinegar_dixi_price.php
 *   php scripts/fix_vinegar_dixi_price.php --apply
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FoodGrLastPurchaseForItem;
use App\Support\ItemAutoAllPriceFromFoodGr;
use App\Support\ItemUnitCost;
use App\Support\SerialReceiveItemPriceResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv ?? [], true);

$itemId = 52521;
$wrongPoPrice = 150000.0;
$correctPoPrice = 15000.0;
$deltaLine = 810000.0; // 6 * (150000-15000)
$wrongCostSmall = 230.7692;
$correctCostSmall = 23.0769; // 15000 / 650
$wrongGsrCost = 258.4615; // 168000 / 650
$wrongSellLarge = 168100.0;

echo "=== Fix Vinegar Dixi price chain (150.000 → 15.000) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n\n";

$item = DB::table('items')->where('id', $itemId)->first();
if (! $item || $item->name !== 'Vinegar Dixi') {
    echo "Item Vinegar Dixi tidak ditemukan.\n";
    exit(1);
}

$smallConv = (float) ($item->small_conversion_qty ?: 1);
$mediumConv = (float) ($item->medium_conversion_qty ?: 1);
$correctSellLarge = FloorOrderItemPriceResolver::roundUpToHundred($correctPoPrice * 1.12);
$correctSellCostSmall = SerialReceiveItemPriceResolver::itemPriceLargeToCostSmall($correctSellLarge, $item);

echo "Item {$itemId} {$item->name}: 1 Botol = {$smallConv} ml\n";
echo "HPP/ml {$wrongCostSmall} → {$correctCostSmall}\n";
echo "Jual/Botol {$wrongSellLarge} → {$correctSellLarge} (cost_small {$correctSellCostSmall})\n\n";

$poItems = DB::table('purchase_order_food_items as poi')
    ->join('purchase_order_foods as po', 'po.id', '=', 'poi.purchase_order_food_id')
    ->where('poi.item_id', $itemId)
    ->where('poi.price', $wrongPoPrice)
    ->select('poi.id as poi_id', 'poi.purchase_order_food_id as po_id', 'poi.quantity', 'poi.price', 'poi.total', 'po.number', 'po.status', 'po.subtotal', 'po.grand_total')
    ->get();

if ($poItems->isEmpty()) {
    echo "Tidak ada PO line @150.000. Mungkin sudah dikoreksi.\n";
    exit(0);
}

DB::beginTransaction();
try {
    foreach ($poItems as $poi) {
        $qty = (float) $poi->quantity;
        $newTotal = round($qty * $correctPoPrice, 2);
        $oldTotal = (float) $poi->total;
        $diff = $oldTotal - $newTotal;
        echo "1) PO {$poi->number} ({$poi->status}) poi={$poi->poi_id}: price {$poi->price} → {$correctPoPrice}, line {$oldTotal} → {$newTotal}\n";
        if ($apply) {
            DB::table('purchase_order_food_items')->where('id', $poi->poi_id)->update([
                'price' => $correctPoPrice,
                'total' => $newTotal,
                'updated_at' => now(),
            ]);
        }

        $newSub = round((float) $poi->subtotal - $diff, 2);
        $newGrand = round((float) $poi->grand_total - $diff, 2);
        echo "   header subtotal/grand {$poi->subtotal}/{$poi->grand_total} → {$newSub}/{$newGrand}\n";
        if ($apply) {
            DB::table('purchase_order_foods')->where('id', $poi->po_id)->update([
                'subtotal' => $newSub,
                'grand_total' => $newGrand,
                'updated_at' => now(),
            ]);
        }
    }

    $inv = DB::table('food_inventory_items')->where('item_id', $itemId)->first();
    $grId = 14914;

    if ($inv) {
        $hist = DB::table('food_inventory_cost_histories')
            ->where('inventory_item_id', $inv->id)
            ->where('reference_type', 'good_receive')
            ->where('reference_id', $grId)
            ->first();
        if ($hist) {
            echo "2) Cost history {$hist->id}: new_cost {$hist->new_cost} → {$correctCostSmall}, mac {$hist->mac} → {$correctCostSmall}\n";
            if ($apply) {
                DB::table('food_inventory_cost_histories')->where('id', $hist->id)->update([
                    'new_cost' => $correctCostSmall,
                    'mac' => $correctCostSmall,
                ]);
            }
        } else {
            echo "2) Cost history GR {$grId}: tidak ada\n";
        }

        $card = DB::table('food_inventory_cards')
            ->where('inventory_item_id', $inv->id)
            ->where('reference_type', 'good_receive')
            ->where('reference_id', $grId)
            ->first();
        if ($card) {
            $oldCost = (float) $card->cost_per_small;
            $oldValueIn = (float) $card->value_in;
            $newValueIn = round(((float) $card->in_qty_small) * $correctCostSmall, 4);
            $prevSaldoValue = round((float) $card->saldo_value - $oldValueIn, 4);
            $newSaldoValue = round($prevSaldoValue + $newValueIn, 4);
            echo "3) Card {$card->id}: cost {$oldCost} → {$correctCostSmall}, value_in {$oldValueIn} → {$newValueIn}\n";
            if ($apply) {
                DB::table('food_inventory_cards')->where('id', $card->id)->update([
                    'cost_per_small' => $correctCostSmall,
                    'value_in' => $newValueIn,
                    'saldo_value' => $newSaldoValue,
                ]);
            }
        } else {
            echo "3) Card GR {$grId}: tidak ada\n";
        }

        $stocks = DB::table('food_inventory_stocks')->where('inventory_item_id', $inv->id)->get();
        foreach ($stocks as $stock) {
            $qtySmall = (float) $stock->qty_small;
            $newValue = round($qtySmall * $correctCostSmall, 4);
            echo "4) Stock {$stock->id} wh={$stock->warehouse_id}: last_cost {$stock->last_cost_small} → {$correctCostSmall}, value {$stock->value} → {$newValue} (qty={$qtySmall})\n";
            if ($apply) {
                DB::table('food_inventory_stocks')->where('id', $stock->id)->update([
                    'last_cost_small' => $correctCostSmall,
                    'last_cost_medium' => round($correctCostSmall * $smallConv, 4),
                    'last_cost_large' => round($correctCostSmall * $smallConv * $mediumConv, 4),
                    'value' => $newValue,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    $serials = DB::table('inventory_item_serials')
        ->where('item_id', $itemId)
        ->where('cost_small', $wrongCostSmall)
        ->get(['id', 'serial_number', 'source_type', 'out_outlet_id']);
    echo '5) Serial HPP 230.7692: ' . $serials->count() . " → {$correctCostSmall}\n";
    foreach ($serials as $s) {
        echo "   {$s->serial_number} {$s->source_type} outlet={$s->out_outlet_id}\n";
    }
    if ($apply && $serials->isNotEmpty()) {
        DB::table('inventory_item_serials')
            ->where('item_id', $itemId)
            ->where('cost_small', $wrongCostSmall)
            ->update(['cost_small' => $correctCostSmall]);
    }

    $gsrItems = DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
        ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
        ->where('si.item_id', $itemId)
        ->where('si.cost_small', $wrongGsrCost)
        ->whereNull('h.deleted_at')
        ->get(['si.id', 'si.header_id', 'si.qty', 'h.number', 'h.receive_date', 'o.nama_outlet']);
    echo '6) GSR cost 258.4615: ' . $gsrItems->count() . " → {$correctSellCostSmall} (jual {$correctSellLarge}/Botol)\n";
    foreach ($gsrItems as $g) {
        echo "   {$g->receive_date} {$g->number} {$g->nama_outlet} qty={$g->qty}\n";
    }
    $headerIds = $gsrItems->pluck('header_id')->unique()->values();
    if ($apply && $gsrItems->isNotEmpty()) {
        DB::table('outlet_serial_receive_items')
            ->where('item_id', $itemId)
            ->where('cost_small', $wrongGsrCost)
            ->update([
                'cost_small' => $correctSellCostSmall,
                'updated_at' => now(),
            ]);
    }

    if ($headerIds->isNotEmpty() && Schema::hasTable('outlet_payments') && Schema::hasColumn('outlet_payments', 'gsr_id')) {
        echo '7) Recalc outlet_payments for GSR headers: ' . $headerIds->count() . "\n";
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
                echo "   gsr_id={$hid} payment_total=" . round($total, 2) . "\n";
            }
        }
    } else {
        echo "7) outlet_payments: skip\n";
    }

    $priceRow = DB::table('item_prices')->where('item_id', $itemId)->where('availability_price_type', 'all')->orderByDesc('id')->first();
    if ($priceRow) {
        echo "8) item_prices {$priceRow->id}: {$priceRow->price} → {$correctSellLarge} (mode={$priceRow->pricing_mode})\n";
        if ($apply) {
            DB::table('item_prices')->where('id', $priceRow->id)->update([
                'price' => $correctSellLarge,
                'updated_at' => now(),
            ]);
        }
    }

    $foHigh = DB::table('food_floor_order_items as foi')
        ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
        ->where('foi.item_id', $itemId)
        ->where('foi.price', '>=', 100000)
        ->select('foi.id', 'foi.price', 'foi.unit', 'foi.qty', 'fo.order_number', 'fo.tanggal', 'fo.status')
        ->orderByDesc('fo.tanggal')
        ->get();
    echo '9) FO harga >= 100.000: ' . $foHigh->count() . " → {$correctSellLarge}\n";
    foreach ($foHigh as $fo) {
        echo "   {$fo->tanggal} {$fo->order_number} {$fo->status} {$fo->qty} {$fo->unit} @{$fo->price}\n";
    }
    if ($apply && $foHigh->isNotEmpty()) {
        DB::table('food_floor_order_items')
            ->where('item_id', $itemId)
            ->where('price', '>=', 100000)
            ->update([
                'price' => $correctSellLarge,
                'updated_at' => now(),
            ]);
    }

    if ($apply) {
        ItemAutoAllPriceFromFoodGr::syncForItemIds([$itemId]);
        $verify = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
        $last = FoodGrLastPurchaseForItem::lastLine($itemId);
        echo "\nVERIFY last GR {$last['gr_number']} cost_large={$last['cost_large']} suggested={$verify}\n";
        DB::commit();
        echo "\nAPPLIED OK\n";
    } else {
        DB::rollBack();
        echo "\nDRY-RUN only. Jalankan dengan --apply untuk simpan.\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
