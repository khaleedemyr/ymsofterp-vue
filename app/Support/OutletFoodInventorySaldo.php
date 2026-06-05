<?php

namespace App\Support;

/**
 * Saldo kartu stok outlet food harus selalu mengikuti qty stok riil setelah transaksi.
 */
class OutletFoodInventorySaldo
{
    /**
     * @return array{saldo_qty_small: float, saldo_qty_medium: float, saldo_qty_large: float, saldo_value: float}
     */
    public static function fromStockQty(
        float $qtySmall,
        float $qtyMedium,
        float $qtyLarge,
        float $value
    ): array {
        return [
            'saldo_qty_small' => round($qtySmall, 4),
            'saldo_qty_medium' => round($qtyMedium, 4),
            'saldo_qty_large' => round($qtyLarge, 4),
            'saldo_value' => round(max(0, $value), 2),
        ];
    }
}
