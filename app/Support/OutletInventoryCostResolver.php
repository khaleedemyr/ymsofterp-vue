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

    /** Referensi transaksi masuk yang new_cost-nya dianggap andal untuk anchor MAC. */
    private const TRUSTED_INBOUND_REFERENCE_TYPES = [
        'serial_receive',
        'good_receive_outlet',
        'outlet_food_good_receive',
        'mac_correction',
    ];
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
     * Satuan biaya masuk per unit kecil untuk transfer (antar outlet / internal WH):
     * prefer jejak new_cost terbaru di gudang asal, fallback MAC pada stok asal.
     */
    public static function resolveInboundUnitSmallCost(int $outletId, int $warehouseOutletId, int $inventoryItemId, object $stockFallbackRow): float
    {
        $fromHist = self::latestNewCostPerSmallUnit($outletId, $warehouseOutletId, $inventoryItemId);
        if ($fromHist !== null) {
            return $fromHist;
        }

        return (float) ($stockFallbackRow->last_cost_small ?? 0);
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
        if ($base > 0) {
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
     * new_cost terbaru dari transaksi masuk terpercaya (GR/serial receive/koreksi MAC).
     */
    public static function latestTrustedNewCostPerSmallUnit(
        int $outletId,
        int $warehouseOutletId,
        int $inventoryItemId
    ): ?float {
        $row = DB::table('outlet_food_inventory_cost_histories')
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('inventory_item_id', $inventoryItemId)
            ->whereIn('reference_type', self::TRUSTED_INBOUND_REFERENCE_TYPES)
            ->whereNotNull('new_cost')
            ->where('new_cost', '>', 0)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['new_cost']);

        return $row ? (float) $row->new_cost : null;
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
}
