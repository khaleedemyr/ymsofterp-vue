<?php

namespace App\Support;

/**
 * Konversi cost_small ke harga per unit tampilan (small / medium / large).
 */
class ItemUnitCost
{
    public static function priceForUnit(float $costSmall, ?object $item, $unitId): float
    {
        if ($costSmall <= 0) {
            return 0.0;
        }

        if (! $item) {
            return round($costSmall, 4);
        }

        $unitId = (int) $unitId;
        $smallConv = (float) ($item->small_conversion_qty ?? 1) ?: 1;
        $mediumConv = (float) ($item->medium_conversion_qty ?? 1) ?: 1;

        if ($unitId > 0 && $unitId === (int) ($item->large_unit_id ?? 0)) {
            return round($costSmall * $smallConv * $mediumConv, 4);
        }

        if ($unitId > 0 && $unitId === (int) ($item->medium_unit_id ?? 0)) {
            return round($costSmall * $smallConv, 4);
        }

        return round($costSmall, 4);
    }

    public static function lineSubtotal(float $costSmall, ?object $item, $unitId, float $qty): float
    {
        return round($qty * self::priceForUnit($costSmall, $item, $unitId), 4);
    }
}
