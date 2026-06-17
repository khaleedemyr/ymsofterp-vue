<?php

namespace App\Support;

/**
 * Pembagian qty ke nomor seri saat generate dengan konversi unit (repack).
 * Serial terakhir memakai sisa qty, bukan ukuran pack penuh.
 */
class InventorySerialRepackChunk
{
    public static function serialCount(float $totalQty, float $packSize): int
    {
        if ($packSize <= 0) {
            return 0;
        }

        return (int) ceil($totalQty / $packSize);
    }

    public static function qtyForIndex(float $totalQty, float $packSize, int $index): float
    {
        if ($packSize <= 0 || $index < 0) {
            return 0.0;
        }

        $remaining = max(0.0, $totalQty - ($index * $packSize));

        return min($packSize, $remaining);
    }
}
