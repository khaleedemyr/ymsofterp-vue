<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Menghubungkan stok outlet dengan kolom new_cost di outlet_food_inventory_cost_histories.
 */
final class OutletInventoryCostResolver
{
    /** MAC lama dianggap korup jika menyimpang >5x dari biaya masuk / anchor terpercaya. */
    private const MAC_SPIKE_MULTIPLIER = 5.0;

    /** Referensi pembelian/penerimaan — prioritas tertinggi untuk anchor MAC. */
    private const TRUSTED_PURCHASE_REFERENCE_TYPES = [
        'serial_receive',
        'good_receive_outlet',
        'outlet_food_good_receive',
        'retail_food',
        'mac_correction',
    ];

    /** Referensi masuk yang boleh dipakai, termasuk saldo awal (prioritas lebih rendah). */
    private const TRUSTED_INBOUND_REFERENCE_TYPES = [
        'serial_receive',
        'good_receive_outlet',
        'outlet_food_good_receive',
        'retail_food',
        'initial_balance',
        'mac_correction',
    ];

    private const TRUSTED_COST_SOFT_CAP = 500_000.0;
    /**
     * new_cost positif terbaru untuk outlet + warehouse + inventory item (urut tanggal lalu id).
     */
    public static function latestNewCostPerSmallUnit(int $outletId, int $warehouseOutletId, int $inventoryItemId): ?float
    {
        $row = DB::table('outlet_food_inventory_cost_histories')
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('inventory_item_id', $inventoryItemId)
            ->whereNotNull('new_cost')
            ->where('new_cost', '>', 0)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['new_cost']);

        if ($row) {
            return (float) $row->new_cost;
        }

        return null;
    }

    /**
     * Histori new_cost terbaru jika ada, selain itu last_cost_small pada baris stok outlet.
     */
    public static function latestNewCostPerSmallUnitOrStockFallback(int $outletId, int $warehouseOutletId, int $inventoryItemId): float
    {
        $fromHist = self::latestNewCostPerSmallUnit($outletId, $warehouseOutletId, $inventoryItemId);
        if ($fromHist !== null) {
            return $fromHist;
        }

        $stock = DB::table('outlet_food_inventory_stocks')
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('inventory_item_id', $inventoryItemId)
            ->first(['last_cost_small']);

        return (float) ($stock->last_cost_small ?? 0);
    }

    /**
     * Satuan biaya masuk per unit kecil untuk transfer (antar outlet / internal WH).
     * Jangan pakai new_cost historis sembarangan — sering terkontaminasi MAC transfer/produksi rusak.
     * Urutan: MAC stok tersanitasi → pembelian terpercaya → last_cost stok → histori (hanya jika <= soft cap).
     */
    public static function resolveInboundUnitSmallCost(int $outletId, int $warehouseOutletId, int $inventoryItemId, object $stockFallbackRow): float
    {
        // Pastikan resolveMacFromStockRow bisa baca anchor terpercaya
        if (! isset($stockFallbackRow->id_outlet)) {
            $stockFallbackRow->id_outlet = $outletId;
        }
        if (! isset($stockFallbackRow->warehouse_outlet_id)) {
            $stockFallbackRow->warehouse_outlet_id = $warehouseOutletId;
        }
        if (! isset($stockFallbackRow->inventory_item_id)) {
            $stockFallbackRow->inventory_item_id = $inventoryItemId;
        }

        $stockMac = self::resolveMacFromStockRow($stockFallbackRow);
        $trusted = self::latestTrustedNewCostPerSmallUnit($outletId, $warehouseOutletId, $inventoryItemId);

        if ($stockMac > 0) {
            if ($trusted !== null && $trusted > 0 && self::macLooksAnomalousVsAnchor($stockMac, $trusted)) {
                return $trusted;
            }

            return $stockMac;
        }

        if ($trusted !== null && $trusted > 0) {
            return $trusted;
        }

        $lastCost = (float) ($stockFallbackRow->last_cost_small ?? 0);
        if ($lastCost > 0 && $lastCost <= 500_000) {
            return $lastCost;
        }

        $fromHist = self::latestNewCostPerSmallUnit($outletId, $warehouseOutletId, $inventoryItemId);
        if ($fromHist !== null && $fromHist > 0 && $fromHist <= 500_000) {
            if ($trusted !== null && $trusted > 0 && self::macLooksAnomalousVsAnchor($fromHist, $trusted)) {
                return $trusted;
            }

            return $fromHist;
        }

        if ($trusted !== null && $trusted > 0) {
            return $trusted;
        }

        return $lastCost > 0 ? $lastCost : 0.0;
    }

    /**
     * Proporsi cost medium/large mengikuti rasio pada baris stok (agar konsisten dengan kartu),
     * fallback flat ke cost_small jika last_cost_small stok 0.
     *
     * @return array{0: float, 1: float, 2: float} [small, medium, large]
     */
    public static function scaledCostsMediumLargeFromStockRow(float $costSmall, object $stockRow): array
    {
        $base = (float) ($stockRow->last_cost_small ?? 0);
        if ($base > 0 && $costSmall > 0 && ! self::macLooksAnomalousVsAnchor($base, $costSmall)) {
            return [
                $costSmall,
                $costSmall * ((float) ($stockRow->last_cost_medium ?? 0) / $base),
                $costSmall * ((float) ($stockRow->last_cost_large ?? 0) / $base),
            ];
        }

        return [$costSmall, $costSmall, $costSmall];
    }

    /**
     * Biaya lapisan masuk (per unit kecil/med/large) untuk mutasi transfer outlet / internal warehouse.
     *
     * @return array{0: float, 1: float, 2: float} [small, medium, large]
     */
    public static function transferInboundCostRates(object $stockFrom, int $fromOutletId, int $fromWarehouseOutletId, int $inventoryItemId): array
    {
        $small = self::resolveInboundUnitSmallCost($fromOutletId, $fromWarehouseOutletId, $inventoryItemId, $stockFrom);

        // Soft guard: jangan salin cost absurd ke gudang tujuan
        if ($small > 500_000) {
            $trusted = self::latestTrustedNewCostPerSmallUnit($fromOutletId, $fromWarehouseOutletId, $inventoryItemId);
            if ($trusted !== null && $trusted > 0) {
                $small = $trusted;
            }
        }

        return self::scaledCostsMediumLargeFromStockRow($small, $stockFrom);
    }

    public static function macLooksAnomalousVsAnchor(float $mac, float $anchor): bool
    {
        if ($mac <= 0 || $anchor <= 0) {
            return false;
        }

        $ratio = $mac / $anchor;

        return $ratio > self::MAC_SPIKE_MULTIPLIER || $ratio < (1.0 / self::MAC_SPIKE_MULTIPLIER);
    }

    /**
     * new_cost terbaru dari transaksi masuk terpercaya.
     * Prioritas: pembelian/GR/serial (WH sama) → pembelian outlet lain / WH lain
     * → initial_balance WH sama → initial_balance lintas WH/outlet.
     * initial_balance sengaja di-deprioritaskan karena sering ikut cost MK/produksi yang meledak.
     */
    public static function latestTrustedNewCostPerSmallUnit(
        int $outletId,
        int $warehouseOutletId,
        int $inventoryItemId
    ): ?float {
        $purchase = self::TRUSTED_PURCHASE_REFERENCE_TYPES;
        $allTrusted = self::TRUSTED_INBOUND_REFERENCE_TYPES;

        $scopes = [
            // 1) Pembelian di WH yang sama
            fn ($q) => $q->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->whereIn('reference_type', $purchase),
            // 2) Pembelian di outlet yang sama (WH mana saja)
            fn ($q) => $q->where('id_outlet', $outletId)
                ->whereIn('reference_type', $purchase),
            // 3) Pembelian di outlet mana saja (item yang sama)
            fn ($q) => $q->whereIn('reference_type', $purchase),
            // 4) Termasuk IB di WH sama
            fn ($q) => $q->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->whereIn('reference_type', $allTrusted),
            // 5) IB / trusted di outlet sama
            fn ($q) => $q->where('id_outlet', $outletId)
                ->whereIn('reference_type', $allTrusted),
        ];

        foreach ($scopes as $scope) {
            $query = DB::table('outlet_food_inventory_cost_histories')
                ->where('inventory_item_id', $inventoryItemId)
                ->whereNotNull('new_cost')
                ->where('new_cost', '>', 0)
                ->where('new_cost', '<=', self::TRUSTED_COST_SOFT_CAP);
            $scope($query);
            $row = $query->orderByDesc('date')->orderByDesc('id')->first(['new_cost']);
            if ($row) {
                return (float) $row->new_cost;
            }
        }

        // Fallback kartu inventory (jika histori kosong tapi ada GR/serial di cards)
        $cardScopes = [
            fn ($q) => $q->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->whereIn('reference_type', $purchase),
            fn ($q) => $q->where('id_outlet', $outletId)
                ->whereIn('reference_type', $purchase),
            fn ($q) => $q->whereIn('reference_type', $purchase),
        ];
        foreach ($cardScopes as $scope) {
            $query = DB::table('outlet_food_inventory_cards')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('cost_per_small', '>', 0)
                ->where('cost_per_small', '<=', self::TRUSTED_COST_SOFT_CAP);
            $scope($query);
            $row = $query->orderByDesc('date')->orderByDesc('id')->first(['cost_per_small']);
            if ($row) {
                return (float) $row->cost_per_small;
            }
        }

        return null;
    }

    /**
     * Koreksi MAC lama sebelum rata-rata tertimbang agar nilai korup tidak menular.
     */
    public static function sanitizeMacForWeightedAverage(float $macLama, float $costInbound): float
    {
        if ($macLama <= 0) {
            return $costInbound > 0 ? $costInbound : 0.0;
        }
        if ($costInbound <= 0) {
            return $macLama;
        }
        if (self::macLooksAnomalousVsAnchor($macLama, $costInbound)) {
            return $costInbound;
        }

        return $macLama;
    }

    /**
     * MAC rata-rata tertimbang per unit kecil setelah sanitasi MAC lama.
     */
    public static function weightedAverageMacPerSmall(
        float $qtyLama,
        float $macLama,
        float $qtyBaru,
        float $costInbound
    ): float {
        $macLamaEffective = self::sanitizeMacForWeightedAverage($macLama, $costInbound);
        $totalQty = $qtyLama + $qtyBaru;
        if ($totalQty <= 0) {
            return $costInbound > 0 ? $costInbound : $macLamaEffective;
        }

        $totalNilai = ($qtyLama * $macLamaEffective) + ($qtyBaru * $costInbound);

        return $totalNilai / $totalQty;
    }

    /**
     * MAC per unit kecil dari baris stok outlet.
     * Jika value/qty tidak selaras dengan last_cost_small (>5%), pakai last_cost_small
     * agar tidak memperparah anomali dari field value yang stale.
     * Jika last_cost_small menyimpang dari anchor terpercaya, pakai anchor.
     */
    public static function resolveMacFromStockRow(?object $stock): float
    {
        if (!$stock) {
            return 0.0;
        }

        $qty = (float) ($stock->qty_small ?? 0);
        $lastCost = (float) ($stock->last_cost_small ?? 0);

        $trustedAnchor = null;
        if (
            isset($stock->id_outlet, $stock->warehouse_outlet_id, $stock->inventory_item_id)
            && (int) $stock->warehouse_outlet_id > 0
        ) {
            $trustedAnchor = self::latestTrustedNewCostPerSmallUnit(
                (int) $stock->id_outlet,
                (int) $stock->warehouse_outlet_id,
                (int) $stock->inventory_item_id
            );
        }

        if ($trustedAnchor !== null && $lastCost > 0 && self::macLooksAnomalousVsAnchor($lastCost, $trustedAnchor)) {
            $lastCost = $trustedAnchor;
        }

        if ($qty <= 0) {
            return $lastCost;
        }

        $value = (float) ($stock->value ?? 0);
        $implied = $value / $qty;
        if ($lastCost > 0) {
            $divergence = abs($implied - $lastCost) / max($lastCost, 1e-9);
            if ($implied <= 0 || $divergence > 0.05) {
                return $lastCost;
            }
        }

        $resolved = $implied > 0 ? $implied : $lastCost;
        if ($trustedAnchor !== null && $resolved > 0 && self::macLooksAnomalousVsAnchor($resolved, $trustedAnchor)) {
            return $trustedAnchor;
        }

        return $resolved;
    }

    /**
     * Sanitasi MAC hasil resolve (opname / fallback histori) terhadap anchor pembelian.
     */
    public static function sanitizeResolvedMac(float $mac, ?float $anchorFromBatch, ?float $trustedAnchor): float
    {
        $anchor = ($anchorFromBatch !== null && $anchorFromBatch > 0)
            ? $anchorFromBatch
            : (($trustedAnchor !== null && $trustedAnchor > 0) ? $trustedAnchor : null);

        if ($anchor === null || $mac <= 0) {
            return $mac;
        }

        return self::macLooksAnomalousVsAnchor($mac, $anchor) ? $anchor : $mac;
    }

    public static function stockTotalValue(float $qtySmall, float $macPerSmall): float
    {
        return max(0.0, $qtySmall * $macPerSmall);
    }

    /**
     * MAC per satuan kecil/med/large konsisten dari MAC small (setelah WAC).
     *
     * @return array{0: float, 1: float, 2: float} [small, medium, large]
     */
    public static function macRatesPerSmallMediumLarge(float $macPerSmall, object $itemMaster): array
    {
        $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);

        return [
            $macPerSmall,
            $smallConv > 0 ? $macPerSmall * $smallConv : $macPerSmall,
            ($smallConv > 0 && $mediumConv > 0) ? $macPerSmall * $smallConv * $mediumConv : $macPerSmall,
        ];
    }
}
