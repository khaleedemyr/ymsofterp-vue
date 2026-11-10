<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Harga jual outlet dari nomor seri DO.
 * Hanya aktif jika item_prices mode auto (Food GR + markup%).
 * Basis: rata-rata cost_medium serial × (1 + markup), dibulatkan ke ratusan.
 */
final class DoSerialSellPriceResolver
{
    /**
     * @param  list<string|mixed>  $serialNumbers
     */
    public static function resolveUnitSellPrice(
        int $itemId,
        array $serialNumbers,
        ?int $regionId = null,
        ?string $outletId = null,
        float $markupPct = 12.0,
    ): ?float {
        if ($itemId <= 0) {
            return null;
        }

        $serialNumbers = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $serialNumbers
        ), static fn ($s) => $s !== '')));

        if ($serialNumbers === []) {
            return null;
        }

        if (! self::isAutoPricing($itemId, $regionId, $outletId)) {
            return null;
        }

        $costs = DB::table('inventory_item_serials')
            ->where('item_id', $itemId)
            ->whereIn('serial_number', $serialNumbers)
            ->where('cost_medium', '>', 0)
            ->pluck('cost_medium');

        if ($costs->isEmpty()) {
            return null;
        }

        $avgCostMedium = (float) $costs->avg();
        if ($avgCostMedium <= 0) {
            return null;
        }

        $factor = 1 + (max(0.0, $markupPct) / 100);

        return FloorOrderItemPriceResolver::roundUpToHundred($avgCostMedium * $factor);
    }

    /**
     * @param  object{item_id?: mixed, serial_numbers?: mixed}  $doItem
     */
    public static function resolveFromDoItem(
        object $doItem,
        ?int $regionId = null,
        ?string $outletId = null,
        float $markupPct = 12.0,
    ): ?float {
        $itemId = (int) ($doItem->item_id ?? 0);
        $raw = $doItem->serial_numbers ?? null;
        $nums = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        return self::resolveUnitSellPrice($itemId, is_array($nums) ? $nums : [], $regionId, $outletId, $markupPct);
    }

    public static function isAutoPricing(int $itemId, ?int $regionId = null, ?string $outletId = null): bool
    {
        if (! Schema::hasColumn('item_prices', 'pricing_mode')) {
            return false;
        }

        $priceRow = FloorOrderItemPriceResolver::resolvePriceRow($itemId, $regionId, $outletId);

        return $priceRow && ($priceRow->pricing_mode ?? '') === 'auto';
    }
}
