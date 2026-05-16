<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setelah Food Good Receive baru, perbarui item_prices dengan pricing_mode=auto
 * hanya untuk scope default (availability_price_type=all, region/outlet null).
 * Harga mengikuti pembelian GR terakhir + markup per satuan large (sama seperti FoodGrLastPurchaseForItem / item_prices).
 */
final class ItemAutoAllPriceFromFoodGr
{
    public static function syncForItemIds(array $itemIds): int
    {
        if (! Schema::hasColumn('item_prices', 'pricing_mode')) {
            return 0;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        $rowsAffected = 0;

        foreach ($ids as $itemId) {
            if ($itemId <= 0) {
                continue;
            }

            $hasAutoAll = DB::table('item_prices')
                ->where('item_id', $itemId)
                ->where('pricing_mode', 'auto')
                ->where('availability_price_type', 'all')
                ->whereNull('region_id')
                ->whereNull('outlet_id')
                ->exists();

            if (! $hasAutoAll) {
                continue;
            }

            $newPrice = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
            if ($newPrice === null || $newPrice <= 0) {
                continue;
            }

            $rowsAffected += DB::table('item_prices')
                ->where('item_id', $itemId)
                ->where('pricing_mode', 'auto')
                ->where('availability_price_type', 'all')
                ->whereNull('region_id')
                ->whereNull('outlet_id')
                ->update([
                    'price' => $newPrice,
                    'updated_at' => now(),
                ]);
        }

        return $rowsAffected;
    }
}
