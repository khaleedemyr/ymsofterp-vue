<?php

namespace App\Support;

/**
 * Sanitasi & guard cost inventory food agar bug MK Production / value yatim
 * tidak menularkan cost absurd ke transaksi baru.
 */
class FoodInventoryCostGuard
{
    /** Soft cap: cost/unit kecil di atas ini hampir pasti bug (kecuali portion mahal). */
    public const MAX_SANE_COST_SMALL = 500_000.0;

    /** Cap lebih ketat untuk bahan baku saat dihitung ke BOM. */
    public const MAX_MATERIAL_COST_SMALL = 100_000.0;

    /**
     * Ambil cost per small yang aman dari baris stok.
     * Prefer implied (value/qty), deflate pack-as-small via conversion, soft-cap.
     *
     * @param  object|null  $stock  food_inventory_stocks row
     * @param  object|null  $item   items row (butuh small_conversion_qty, optional last_cost_medium di stock)
     */
    public static function sanitizeCostPerSmall(?object $stock, ?object $item = null, bool $asMaterial = false): float
    {
        if (!$stock) {
            return 0.0;
        }

        $cost = (float) ($stock->last_cost_small ?? 0);
        $qty = (float) ($stock->qty_small ?? 0);
        $value = (float) ($stock->value ?? 0);
        $costMed = (float) ($stock->last_cost_medium ?? 0);
        $conv = (float) ($item->small_conversion_qty ?? 1);
        if ($conv <= 0) {
            $conv = 1.0;
        }

        // Implied dari saldo — lebih andal jika value sudah selaras
        if ($qty > 0 && $value >= 0) {
            $implied = $value / $qty;
            if (is_finite($implied) && $implied > 0) {
                if ($cost <= 0) {
                    $cost = $implied;
                } elseif ($cost > $implied * 1.5 && $implied < self::MAX_SANE_COST_SMALL) {
                    // last_cost jauh di atas implied → pakai implied
                    $cost = $implied;
                } else {
                    $cost = min($cost, $implied);
                }
            }
        }

        // Pack cost tersimpan di small: last_cost_small ≈ last_cost_medium padahal conv > 1
        if ($conv > 1 && $costMed > 0) {
            $fromMed = $costMed / $conv;
            if ($fromMed > 0 && $fromMed < $cost && ($cost >= $costMed * 0.5 || $cost > self::MAX_MATERIAL_COST_SMALL)) {
                $cost = $fromMed;
            }
        }

        // Deflate berulang jika masih absurd & ada conversion
        $cap = $asMaterial ? self::MAX_MATERIAL_COST_SMALL : self::MAX_SANE_COST_SMALL;
        $guard = 0;
        while ($conv > 1 && $cost > $cap && $guard < 4) {
            $cost /= $conv;
            $guard++;
        }

        if (!is_finite($cost) || $cost < 0) {
            return 0.0;
        }

        return $cost;
    }

    /**
     * Validasi cost hasil produksi (FG). Lempar exception jika tidak wajar.
     */
    public static function assertFinishedGoodCost(float $costPerSmall, float $smallConv, string $itemName = ''): void
    {
        if (!is_finite($costPerSmall) || $costPerSmall < 0) {
            throw new \InvalidArgumentException(
                'Cost hasil produksi tidak valid' . ($itemName !== '' ? " ({$itemName})" : '')
            );
        }

        // Portion (conv≈1) boleh sampai MAX_SANE; jika conv besar, cost/small harus jauh lebih kecil
        $limit = $smallConv > 10
            ? min(self::MAX_SANE_COST_SMALL, 50_000.0)
            : self::MAX_SANE_COST_SMALL;

        if ($costPerSmall > $limit) {
            throw new \InvalidArgumentException(
                'Cost hasil produksi tidak wajar: Rp ' . number_format($costPerSmall, 2, ',', '.')
                . '/unit kecil'
                . ($itemName !== '' ? " untuk {$itemName}" : '')
                . '. Cek cost bahan baku (kemungkinan masih terinfeksi bug lama).'
            );
        }
    }

    /**
     * Validasi cost bahan sebelum dipakai BOM — tolak jika masih absurd setelah sanitasi.
     */
    public static function assertMaterialCost(float $costPerSmall, string $materialName = ''): void
    {
        if ($costPerSmall > self::MAX_MATERIAL_COST_SMALL * 5) {
            throw new \InvalidArgumentException(
                'Cost bahan baku tidak wajar: '
                . ($materialName !== '' ? "{$materialName} " : '')
                . 'Rp ' . number_format($costPerSmall, 2, ',', '.')
                . '/unit kecil. Perbaiki stok bahan dulu sebelum produksi.'
            );
        }
    }
}
