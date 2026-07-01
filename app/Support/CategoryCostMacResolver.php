<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MAC khusus modul Category Cost (Biaya Kategori Outlet).
 * Item WIP: utamakan MAC riwayat outlet (per gram/satuan kecil dari opname/produksi).
 * Jika MAC histori masih level 1 resep, normalisasi dengan small_conversion_qty.
 * Item non-WIP: pakai MAC histori seperti biasa.
 */
final class CategoryCostMacResolver
{
    public static function isWipItem(?object $itemMaster): bool
    {
        return $itemMaster !== null
            && strtoupper(trim((string) ($itemMaster->type ?? ''))) === 'WIP';
    }

    /**
     * Ambil MAC per satuan kecil dari baris outlet_food_inventory_cost_histories.
     */
    public static function historyMacPerSmall(?object $costHistoryRow): ?float
    {
        if (!$costHistoryRow) {
            return null;
        }

        $mac = $costHistoryRow->mac ?? null;
        if ($mac !== null && (float) $mac > 0) {
            return (float) $mac;
        }

        $newCost = $costHistoryRow->new_cost ?? null;
        if ($newCost !== null && (float) $newCost > 0) {
            return (float) $newCost;
        }

        return null;
    }

    /**
     * MAC histori per satuan kecil pada/before tanggal transaksi.
     * Jika histori pra-cutover sudah dihapus: transaksi sebelum tanggal saldo awal
     * memakai MAC initial_balance; setelah itu tetap dari rantai histori per tanggal.
     */
    public static function resolveHistoryMacAtDate(
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        string $asOfDate
    ): ?float {
        $row = DB::table('outlet_food_inventory_cost_histories')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('date', '<=', $asOfDate)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if ($row) {
            return self::historyMacPerSmall($row);
        }

        $initialBalanceRow = self::resolveInitialBalanceHistoryRow(
            $inventoryItemId,
            $outletId,
            $warehouseOutletId
        );
        if ($initialBalanceRow) {
            $initialBalanceMac = self::historyMacPerSmall($initialBalanceRow);
            $initialBalanceDate = (string) ($initialBalanceRow->date ?? '');
            if (
                $initialBalanceMac !== null
                && $initialBalanceMac > 0
                && $initialBalanceDate !== ''
                && self::isTransactionBeforeSaldoAwal($asOfDate, $initialBalanceDate)
            ) {
                return $initialBalanceMac;
            }
        }

        $stock = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->first(['last_cost_small']);

        if ($stock && (float) ($stock->last_cost_small ?? 0) > 0) {
            return (float) $stock->last_cost_small;
        }

        return null;
    }

    /**
     * Transaksi pra-saldo-awal: tanggal transaksi sebelum tanggal baris initial_balance.
     */
    private static function isTransactionBeforeSaldoAwal(string $asOfDate, string $saldoAwalDate): bool
    {
        try {
            return Carbon::parse($asOfDate)->startOfDay()->lt(Carbon::parse($saldoAwalDate)->startOfDay());
        } catch (\Throwable) {
            return $asOfDate < $saldoAwalDate;
        }
    }

    /**
     * Baris MAC saldo awal (initial_balance) untuk partisi stok outlet.
     */
    private static function resolveInitialBalanceHistoryRow(
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId
    ): ?object {
        return DB::table('outlet_food_inventory_cost_histories')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('reference_type', 'initial_balance')
            ->orderBy('date')
            ->orderBy('id')
            ->first();
    }

    public static function resolveMacPerSmallUnit(
        object $itemMaster,
        ?float $historyMac,
        int $outletId,
        int $warehouseOutletId,
        string $asOfDate
    ): float {
        if (!self::isWipItem($itemMaster)) {
            return (float) ($historyMac ?? 0);
        }

        $yield = (float) ($itemMaster->small_conversion_qty ?? 0);
        $recipeCost = self::computeRecipeBomCost(
            (int) $itemMaster->id,
            $outletId,
            $warehouseOutletId,
            $asOfDate,
            1.0
        );

        // Riwayat MAC outlet = sumber utama (sudah per satuan kecil setelah opname/produksi).
        if ($historyMac !== null && $historyMac > 0) {
            if (self::wipHistoryMacLooksRecipeLevel($historyMac, $recipeCost, $yield)) {
                $divisor = $yield > 0 ? $yield : 1.0;

                return $historyMac / $divisor;
            }

            return $historyMac;
        }

        if ($recipeCost > 0 && $yield > 0) {
            return $recipeCost / $yield;
        }

        return 0.0;
    }

    /**
     * MAC histori WIP dianggap level 1 resep jika mendekati total BOM resep,
     * atau jika dikonversi ke satuan medium/large menghasilkan biaya tidak masuk akal.
     */
    private static function wipHistoryMacLooksRecipeLevel(float $historyMac, float $recipeCost, float $yield): bool
    {
        if ($recipeCost > 0 && $historyMac >= $recipeCost * 0.5) {
            return true;
        }

        if ($yield > 0 && $historyMac >= 10 && ($historyMac * $yield) > 50_000) {
            return true;
        }

        return $yield > 0 && $historyMac > 10_000;
    }

    public static function convertMacToUnit(float $macPerSmall, object $itemMaster, int $unitId): float
    {
        $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);

        if ($unitId === (int) $itemMaster->medium_unit_id && $smallConv > 0) {
            return $macPerSmall * $smallConv;
        }
        if (
            $unitId === (int) $itemMaster->large_unit_id
            && $smallConv > 0
            && $mediumConv > 0
        ) {
            return $macPerSmall * $smallConv * $mediumConv;
        }

        return $macPerSmall;
    }

    public static function subtotalFromDetail(
        object $itemMaster,
        ?float $historyMac,
        int $unitId,
        float $qty,
        int $outletId,
        int $warehouseOutletId,
        string $asOfDate
    ): float {
        $macPerSmall = self::resolveMacPerSmallUnit(
            $itemMaster,
            $historyMac,
            $outletId,
            $warehouseOutletId,
            $asOfDate
        );
        $macConverted = self::convertMacToUnit($macPerSmall, $itemMaster, $unitId);

        return $macConverted * $qty;
    }

    /**
     * Biaya BOM untuk N resep WIP (default 1 resep) pada tanggal transaksi.
     */
    public static function computeRecipeBomCost(
        int $wipItemId,
        int $outletId,
        int $warehouseOutletId,
        string $asOfDate,
        float $recipeQty = 1.0
    ): float {
        $bom = DB::table('item_bom')->where('item_id', $wipItemId)->get();
        if ($bom->isEmpty()) {
            return 0.0;
        }

        $materialIds = $bom->pluck('material_item_id')->unique()->values()->all();
        $itemMasters = DB::table('items')
            ->whereIn('id', $materialIds)
            ->get()
            ->keyBy('id');
        $inventoryItems = DB::table('outlet_food_inventory_items')
            ->whereIn('item_id', $materialIds)
            ->get()
            ->keyBy('item_id');
        $unitNames = DB::table('units')
            ->whereIn('id', $bom->pluck('unit_id')->merge($itemMasters->pluck('small_unit_id'))->unique()->filter())
            ->pluck('name', 'id');

        $totalCost = 0.0;
        foreach ($bom as $bomLine) {
            $materialMaster = $itemMasters->get($bomLine->material_item_id);
            $inventoryItem = $inventoryItems->get($bomLine->material_item_id);
            if (!$materialMaster || !$inventoryItem) {
                continue;
            }

            $qtySmallBahan = self::bomLineQtyToSmall(
                $bomLine,
                $materialMaster,
                $unitNames,
                $recipeQty
            );
            $materialMac = self::materialMacAtDate(
                (int) $inventoryItem->id,
                $outletId,
                $warehouseOutletId,
                $asOfDate
            );
            $totalCost += $qtySmallBahan * $materialMac;
        }

        return $totalCost;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>|array<int, string>  $unitNames
     */
    private static function bomLineQtyToSmall(
        object $bomLine,
        object $materialMaster,
        $unitNames,
        float $recipeQty
    ): float {
        $qtyInput = (float) $bomLine->qty * $recipeQty;
        $unitName = $unitNames[$bomLine->unit_id] ?? '';
        $unitSmall = $unitNames[$materialMaster->small_unit_id] ?? '';
        $unitMedium = $unitNames[$materialMaster->medium_unit_id] ?? '';
        $unitLarge = $unitNames[$materialMaster->large_unit_id] ?? '';
        $smallConv = (float) ($materialMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($materialMaster->medium_conversion_qty ?: 1);

        if ($unitName === $unitSmall) {
            return $qtyInput;
        }
        if ($unitName === $unitMedium) {
            return $qtyInput * $smallConv;
        }
        if ($unitName === $unitLarge) {
            return $qtyInput * $smallConv * $mediumConv;
        }

        return $qtyInput;
    }

    private static function materialMacAtDate(
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        string $asOfDate
    ): float {
        return (float) (self::resolveHistoryMacAtDate($inventoryItemId, $outletId, $warehouseOutletId, $asOfDate) ?? 0);
    }

    /**
     * @return array{0: float, 1: float, 2: float} [small, medium, large]
     */
    public static function costRatesPerUnit(
        object $itemMaster,
        float $macPerSmall,
        ?object $stockRow = null
    ): array {
        if (!self::isWipItem($itemMaster)) {
            if ($stockRow) {
                return OutletInventoryCostResolver::scaledCostsMediumLargeFromStockRow($macPerSmall, $stockRow);
            }

            $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
            $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);

            return [
                $macPerSmall,
                $smallConv > 0 ? $macPerSmall * $smallConv : $macPerSmall,
                ($smallConv > 0 && $mediumConv > 0) ? $macPerSmall * $smallConv * $mediumConv : $macPerSmall,
            ];
        }

        $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);

        return [
            $macPerSmall,
            $smallConv > 0 ? $macPerSmall * $smallConv : $macPerSmall,
            ($smallConv > 0 && $mediumConv > 0) ? $macPerSmall * $smallConv * $mediumConv : $macPerSmall,
        ];
    }
}
