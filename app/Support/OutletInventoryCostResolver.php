<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Menghubungkan stok outlet dengan kolom new_cost di outlet_food_inventory_cost_histories.
 */
final class OutletInventoryCostResolver
{
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
}
