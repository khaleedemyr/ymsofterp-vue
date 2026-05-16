<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Harga beli terakhir dari Food Good Receive, dikonversi ke basis large
 * (mirrors cost_small → cost_medium → cost_large di FoodGoodReceiveController).
 * Kolom item_prices menyimpan harga per satuan besar (large).
 */
final class FoodGrLastPurchaseForItem
{
    /**
     * @return array{cost_small: float, cost_medium: float, cost_large: float, cost_po_unit: float, po_unit_id: int|null, receive_date: string|null, gr_number: string|null}|null
     */
    public static function lastLine(int $itemId): ?array
    {
        $line = DB::table('food_good_receive_items as gri')
            ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
            ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
            ->where('gri.item_id', $itemId)
            ->orderByDesc('gr.receive_date')
            ->orderByDesc('gr.id')
            ->orderByDesc('gri.id')
            ->select(
                'poi.price as po_price',
                'gri.unit_id as gr_unit_id',
                'gr.receive_date',
                'gr.gr_number'
            )
            ->first();

        if (! $line) {
            return null;
        }

        $itemMaster = DB::table('items')->where('id', $itemId)->first();
        if (! $itemMaster) {
            return null;
        }

        $cost = (float) ($line->po_price ?? 0);
        $unitId = $line->gr_unit_id ? (int) $line->gr_unit_id : null;

        $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);

        $costSmall = $cost;
        if ($unitId && (int) $itemMaster->large_unit_id === $unitId) {
            $costSmall = $cost / (($smallConv ?: 1) * ($mediumConv ?: 1));
        } elseif ($unitId && (int) $itemMaster->medium_unit_id === $unitId) {
            $costSmall = $cost / ($smallConv ?: 1);
        }

        $costMedium = $costSmall * ($smallConv ?: 1);
        $costLarge = $costMedium * ($mediumConv ?: 1);

        return [
            'cost_po_unit' => $cost,
            'po_unit_id' => $unitId,
            'cost_small' => $costSmall,
            'cost_medium' => $costMedium,
            'cost_large' => $costLarge,
            'receive_date' => $line->receive_date ? (string) $line->receive_date : null,
            'gr_number' => $line->gr_number ? (string) $line->gr_number : null,
        ];
    }

    /**
     * Harga jual saran per satuan large = HPP large terakhir × (1 + markup), mis. 12%.
     * Selaras dengan cara penyimpanan item_prices.
     */
    public static function suggestedSellingPrice(int $itemId, float $markupPct = 12.0): ?float
    {
        $last = self::lastLine($itemId);
        if (! $last || ($last['cost_large'] ?? 0) <= 0) {
            return null;
        }

        return round($last['cost_large'] * (1 + ($markupPct / 100.0)), 2);
    }

    /**
     * @deprecated Gunakan suggestedSellingPrice — basis large.
     */
    public static function suggestedSellingPriceMedium(int $itemId, float $markupPct = 12.0): ?float
    {
        return self::suggestedSellingPrice($itemId, $markupPct);
    }
}
