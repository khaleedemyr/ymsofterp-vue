<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Konversi qty antar satuan small / medium / large untuk item master.
 */
final class ItemUnitQtyConverter
{
    public static function unitMatches($selectedUnit, $unitId, ?string $unitName): bool
    {
        $selected = strtolower(trim((string) $selectedUnit));
        if ($selected === '') {
            return false;
        }

        $name = strtolower(trim((string) $unitName));
        $id = (string) $unitId;

        return $selected === $name || $selected === $id;
    }

    /**
     * @return array{qty_small: float, qty_medium: float, qty_large: float}
     */
    public static function toQtyLayers(?object $itemMaster, $unitHint, float $qtyInput, ?string $itemLabel = null): array
    {
        if (! $itemMaster) {
            throw new InvalidArgumentException('Item master tidak ditemukan untuk konversi satuan.');
        }

        $label = $itemLabel ?: (string) ($itemMaster->name ?? 'item');
        $smallConv = (float) ($itemMaster->small_conversion_qty ?? 1) ?: 1;
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?? 1) ?: 1;

        $unitSmall = $itemMaster->small_unit_name ?? optional($itemMaster->smallUnit ?? null)->name ?? null;
        $unitMedium = $itemMaster->medium_unit_name ?? optional($itemMaster->mediumUnit ?? null)->name ?? null;
        $unitLarge = $itemMaster->large_unit_name ?? optional($itemMaster->largeUnit ?? null)->name ?? null;

        if (self::unitMatches($unitHint, $itemMaster->small_unit_id ?? 0, $unitSmall)) {
            $qtySmall = $qtyInput;

            return [
                'qty_small' => $qtySmall,
                'qty_medium' => $smallConv > 0 ? $qtySmall / $smallConv : 0.0,
                'qty_large' => ($smallConv > 0 && $mediumConv > 0) ? $qtySmall / ($smallConv * $mediumConv) : 0.0,
            ];
        }

        if (self::unitMatches($unitHint, $itemMaster->medium_unit_id ?? 0, $unitMedium)) {
            $qtyMedium = $qtyInput;
            $qtySmall = $qtyMedium * $smallConv;

            return [
                'qty_small' => $qtySmall,
                'qty_medium' => $qtyMedium,
                'qty_large' => $mediumConv > 0 ? $qtyMedium / $mediumConv : 0.0,
            ];
        }

        if (self::unitMatches($unitHint, $itemMaster->large_unit_id ?? 0, $unitLarge)) {
            $qtyLarge = $qtyInput;
            $qtyMedium = $qtyLarge * $mediumConv;
            $qtySmall = $qtyMedium * $smallConv;

            return [
                'qty_small' => $qtySmall,
                'qty_medium' => $qtyMedium,
                'qty_large' => $qtyLarge,
            ];
        }

        throw new InvalidArgumentException(
            "Satuan '{$unitHint}' tidak valid untuk item '{$label}'. "
            . 'Pilih satuan small / medium / large yang terdaftar di master item.'
        );
    }

    public static function toSmallQty(?object $itemMaster, $unitHint, float $qtyInput, ?string $itemLabel = null): float
    {
        return self::toQtyLayers($itemMaster, $unitHint, $qtyInput, $itemLabel)['qty_small'];
    }
}
