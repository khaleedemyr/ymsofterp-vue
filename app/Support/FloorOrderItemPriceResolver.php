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

        $priceRow = self::resolvePriceRow($itemId, $regionId, $outletId);
        $priceLarge = self::resolvePriceLarge($itemId, $priceRow);

        return self::roundUpToHundred(self::largeToMediumPrice($priceLarge, $item));
    }

    /**
     * Harga jual per satuan baris FO (small / medium / large) dari item_prices (large).
     */
    public static function resolveLineUnitPrice(
        int $itemId,
        ?string $unitName,
        ?int $regionId = null,
        ?string $outletId = null,
        ?object $itemMaster = null,
    ): float {
        $item = $itemMaster ?? DB::table('items')->where('id', $itemId)->first();
        if (! $item) {
            return 0.0;
        }

        $priceRow = self::resolvePriceRow($itemId, $regionId, $outletId);
        $priceLarge = self::resolvePriceLarge($itemId, $priceRow);
        if ($priceLarge <= 0) {
            return 0.0;
        }

        $tier = self::detectUnitTier($item, $unitName);

        return match ($tier) {
            'large' => self::roundUpToHundred($priceLarge),
            'small' => self::roundUpToHundred(self::largeToSmallPrice($priceLarge, $item)),
            default => self::roundUpToHundred(self::largeToMediumPrice($priceLarge, $item)),
        };
    }

    /** @return 'small'|'medium'|'large' */
    public static function detectUnitTier(object $item, ?string $unitName, ?array $unitNameById = null): string
    {
        $normalized = strtolower(trim((string) $unitName));
        if ($normalized === '') {
            return 'medium';
        }

        $smallId = $item->small_unit_id ?? null;
        $mediumId = $item->medium_unit_id ?? null;
        $largeId = $item->large_unit_id ?? null;

        // FO pesan per satuan medium; cek medium sebelum small. Jika small & medium sama
        // (master data duplikat), hindari tier small agar konversi tidak pakai small_conversion_qty.
        $unitIds = array_filter([
            'medium' => $mediumId,
            'small' => ($smallId && $smallId !== $mediumId) ? $smallId : null,
            'large' => $largeId,
        ]);

        if ($unitIds !== []) {
            if ($unitNameById === null) {
                $unitNameById = DB::table('units')->whereIn('id', array_values($unitIds))->pluck('name', 'id')->all();
            }
            foreach ($unitIds as $tier => $unitId) {
                $name = strtolower(trim((string) ($unitNameById[$unitId] ?? '')));
                if ($name !== '' && $name === $normalized) {
                    return $tier;
                }
            }
        }

        return 'medium';
    }

    public static function largeToSmallPrice(float $priceLarge, object $item): float
    {
        if ($priceLarge <= 0) {
            return 0.0;
        }

        $mediumConv = (float) ($item->medium_conversion_qty ?? 1) ?: 1;
        $smallConv = (float) ($item->small_conversion_qty ?? 1) ?: 1;
        $divisor = $mediumConv * $smallConv;
        if ($divisor <= 0) {
            return 0.0;
        }

        return round($priceLarge / $divisor, 2);
    }

    public static function resolvePriceRow(int $itemId, ?int $regionId, ?string $outletId): ?object
    {
        if ($outletId && ! $regionId) {
            $regionId = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('region_id');
            $regionId = $regionId ? (int) $regionId : null;
        }

        $rows = DB::table('item_prices')
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
            ->orderByDesc('id')
            ->get();

        $pick = static function (string $type, ?int $region = null, ?string $outlet = null, bool $pricedOnly = true) use ($rows): ?object {
            return $rows->first(function ($row) use ($type, $region, $outlet, $pricedOnly) {
                if (($row->availability_price_type ?? '') !== $type) {
                    return false;
                }
                if ($type === 'region' && (int) ($row->region_id ?? 0) !== (int) $region) {
                    return false;
                }
                if ($type === 'outlet' && (string) ($row->outlet_id ?? '') !== (string) $outlet) {
                    return false;
                }
                if ($pricedOnly && (float) ($row->price ?? 0) <= 0) {
                    return false;
                }

                return true;
            });
        };

        return $pick('outlet', null, $outletId)
            ?? $pick('region', $regionId, null)
            ?? $pick('all')
            ?? $pick('outlet', null, $outletId, false)
            ?? $pick('region', $regionId, null, false)
            ?? $pick('all', null, null, false);
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

    /**
     * Guard: cegah harga large (Recipe) tertempel ke baris unit medium/small.
     * Kasus historis: FO unit=Pack/Pcs tapi price = item_prices large.
     */
    public static function guardAgainstLargePriceOnMediumUnit(
        float $resolvedPrice,
        int $itemId,
        ?string $unitName,
        ?int $regionId = null,
        ?string $outletId = null,
        ?object $itemMaster = null,
        ?array $unitNameById = null,
    ): float {
        $item = $itemMaster ?? DB::table('items')->where('id', $itemId)->first();
        if (! $item) {
            return $resolvedPrice;
        }

        $tier = self::detectUnitTier($item, $unitName, $unitNameById);
        if ($tier === 'large') {
            return $resolvedPrice;
        }

        $mediumConv = (float) ($item->medium_conversion_qty ?? 1) ?: 1;
        if ($mediumConv <= 1.01) {
            return $resolvedPrice;
        }

        $priceRow = self::resolvePriceRow($itemId, $regionId, $outletId);
        $priceLarge = self::resolvePriceLarge($itemId, $priceRow);
        if ($priceLarge <= 0) {
            return $resolvedPrice;
        }

        $largeRounded = self::roundUpToHundred($priceLarge);
        if (abs($resolvedPrice - $largeRounded) > 150) {
            return $resolvedPrice;
        }

        // Harga hasil resolve = large, padahal unit medium/small → paksa konversi UoM.
        return match ($tier) {
            'small' => self::roundUpToHundred(self::largeToSmallPrice($priceLarge, $item)),
            default => self::roundUpToHundred(self::largeToMediumPrice($priceLarge, $item)),
        };
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
