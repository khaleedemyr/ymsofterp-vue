<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Harga jual RO per satuan medium (unit pesanan standar).
 * item_prices menyimpan harga per satuan large; mode auto = Food GR pusat +12%.
 */
final class FloorOrderItemPriceResolver
{
    public static function resolveMediumUnitPrice(
        int $itemId,
        ?int $regionId = null,
        ?string $outletId = null,
        ?object $itemMaster = null,
    ): float {
        $item = $itemMaster ?? DB::table('items')->where('id', $itemId)->first();
        if (! $item) {
            return 0.0;
        }

        $priceLarge = self::resolvePriceLarge($itemId, self::resolvePriceRow($itemId, $regionId, $outletId));

        return self::roundUpToHundred(self::largeToMediumPrice($priceLarge, $item));
    }

    public static function resolvePriceRow(int $itemId, ?int $regionId, ?string $outletId): ?object
    {
        if ($outletId && ! $regionId) {
            $regionId = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('region_id');
            $regionId = $regionId ? (int) $regionId : null;
        }

        return DB::table('item_prices')
            ->where('item_id', $itemId)
            ->where(function ($q) use ($regionId, $outletId) {
                $q->where('availability_price_type', 'all');
                if ($regionId) {
                    $q->orWhere(function ($q2) use ($regionId) {
                        $q2->where('availability_price_type', 'region')->where('region_id', $regionId);
                    });
                }
                if ($outletId) {
                    $q->orWhere(function ($q2) use ($outletId) {
                        $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outletId);
                    });
                }
            })
            ->orderByRaw("CASE
                WHEN availability_price_type = 'outlet' THEN 1
                WHEN availability_price_type = 'region' THEN 2
                ELSE 3 END")
            ->orderByDesc('id')
            ->first();
    }

    public static function resolvePriceLarge(int $itemId, ?object $priceRow): float
    {
        $mode = 'manual';
        if ($priceRow && Schema::hasColumn('item_prices', 'pricing_mode')) {
            $mode = ($priceRow->pricing_mode === 'auto') ? 'auto' : 'manual';
        }

        if ($mode === 'auto') {
            $computed = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
            if ($computed !== null && $computed > 0) {
                return (float) $computed;
            }
        }

        if ($priceRow && (float) $priceRow->price > 0) {
            return (float) $priceRow->price;
        }

        $fallback = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);

        return $fallback ? (float) $fallback : 0.0;
    }

    public static function largeToMediumPrice(float $priceLarge, object $item): float
    {
        if ($priceLarge <= 0) {
            return 0.0;
        }

        $mediumConv = (float) ($item->medium_conversion_qty ?? 1) ?: 1;

        return round($priceLarge / $mediumConv, 2);
    }

    public static function roundUpToHundred(float $price): float
    {
        if ($price <= 0) {
            return 0.0;
        }

        return (float) (ceil($price / 100) * 100);
    }

    public static function isAssetItem(int $itemId): bool
    {
        return (bool) DB::table('items as i')
            ->join('categories as c', 'c.id', '=', 'i.category_id')
            ->where('i.id', $itemId)
            ->where('c.is_asset', '1')
            ->exists();
    }
}
