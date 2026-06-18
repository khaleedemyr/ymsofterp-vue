<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockCutVarianceService
{
    public function tableExists(): bool
    {
        return Schema::hasTable('stock_cut_variances');
    }

    /**
     * Catat baris minus saat stock cut (qty shortfall > 0).
     */
    public function recordVariance(array $data): ?int
    {
        if (! $this->tableExists()) {
            return null;
        }

        $shortfall = (float) ($data['qty_shortfall'] ?? 0);
        if ($shortfall <= 0) {
            return null;
        }

        $costPerSmall = (float) ($data['cost_per_small'] ?? 0);
        $qtyNeeded = (float) ($data['qty_needed'] ?? 0);

        $id = DB::table('stock_cut_variances')->insertGetId([
            'stock_cut_log_id' => $data['stock_cut_log_id'],
            'outlet_id' => $data['outlet_id'],
            'warehouse_outlet_id' => $data['warehouse_outlet_id'],
            'inventory_item_id' => $data['inventory_item_id'],
            'item_id' => $data['item_id'],
            'tanggal' => $data['tanggal'],
            'type_filter' => $data['type_filter'] ?? null,
            'qty_needed' => $qtyNeeded,
            'qty_available_before' => (float) ($data['qty_available_before'] ?? 0),
            'qty_shortfall' => $shortfall,
            'qty_after' => (float) ($data['qty_after'] ?? 0),
            'cost_per_small' => $costPerSmall,
            'value_booked' => (float) ($data['value_booked'] ?? ($qtyNeeded * $costPerSmall)),
            'shortfall_value_info' => (float) ($data['shortfall_value_info'] ?? ($shortfall * $costPerSmall)),
            'executed_by' => $data['executed_by'] ?? auth()->id(),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasColumn('stock_cut_logs', 'has_variance')) {
            DB::table('stock_cut_logs')
                ->where('id', $data['stock_cut_log_id'])
                ->update([
                    'has_variance' => 1,
                    'total_variance_items' => DB::raw('total_variance_items + 1'),
                    'total_variance_qty' => DB::raw('total_variance_qty + ' . $shortfall),
                    'updated_at' => now(),
                ]);
        }

        return $id;
    }

    /**
     * Tutup minus setelah stok masuk (transfer / retail food / GRN).
     */
    public function closeAfterInbound(
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        string $closedVia,
        string $referenceType,
        int $referenceId,
        ?int $closedBy = null
    ): int {
        return $this->closeOpenIfStockNonNegative(
            $inventoryItemId,
            $outletId,
            $warehouseOutletId,
            $closedVia,
            $referenceType,
            $referenceId,
            $closedBy
        );
    }

    public function closeAfterInternalWarehouseTransferIn(
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        int $transferId
    ): int {
        return $this->closeAfterInbound(
            $inventoryItemId,
            $outletId,
            $warehouseOutletId,
            'transfer_in',
            'internal_warehouse_transfer',
            $transferId
        );
    }

    public function closeAfterOutletTransferIn(
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        int $transferId
    ): int {
        return $this->closeAfterInbound(
            $inventoryItemId,
            $outletId,
            $warehouseOutletId,
            'transfer_in',
            'outlet_transfer',
            $transferId
        );
    }

    public function closeAfterRetailFoodIn(
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        int $retailFoodId
    ): int {
        return $this->closeAfterInbound(
            $inventoryItemId,
            $outletId,
            $warehouseOutletId,
            'retail_food',
            'retail_food',
            $retailFoodId
        );
    }

    public function closeAfterOutletWipProductionIn(
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        int $productionHeaderId
    ): int {
        return $this->closeAfterInbound(
            $inventoryItemId,
            $outletId,
            $warehouseOutletId,
            'wip_production',
            'outlet_wip_production',
            $productionHeaderId
        );
    }

    public function closeAfterSerialReceiveIn(
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        int $serialReceiveHeaderId
    ): int {
        return $this->closeAfterInbound(
            $inventoryItemId,
            $outletId,
            $warehouseOutletId,
            'serial_receive',
            'serial_receive',
            $serialReceiveHeaderId
        );
    }

    /**
     * Tutup minus untuk item hasil produksi WIP (type WIP) setelah stok masuk.
     */
    public function closeAfterOutletWipItemIn(
        int $itemId,
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        int $productionHeaderId
    ): int {
        $itemType = DB::table('items')->where('id', $itemId)->value('type');
        if (strtoupper((string) $itemType) !== 'WIP') {
            return 0;
        }

        return $this->closeAfterOutletWipProductionIn(
            $inventoryItemId,
            $outletId,
            $warehouseOutletId,
            $productionHeaderId
        );
    }

    /**
     * Tutup variance open jika stok qty_small sudah tidak negatif.
     */
    public function closeOpenIfStockNonNegative(
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        string $closedVia,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $closedBy = null
    ): int {
        if (! $this->tableExists()) {
            return 0;
        }

        $stock = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->first();

        if (! $stock || (float) $stock->qty_small < 0) {
            return 0;
        }

        return DB::table('stock_cut_variances')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('outlet_id', $outletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('status', 'open')
            ->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_via' => $closedVia,
                'closed_reference_type' => $referenceType,
                'closed_reference_id' => $referenceId,
                'closed_by' => $closedBy ?? auth()->id(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Tutup semua variance terkait satu stock cut log (rollback).
     */
    public function closeByStockCutLogId(int $stockCutLogId, string $closedVia = 'rollback'): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        return DB::table('stock_cut_variances')
            ->where('stock_cut_log_id', $stockCutLogId)
            ->where('status', 'open')
            ->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_via' => $closedVia,
                'closed_by' => auth()->id(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Hapus variance saat rollback stock cut dihapus.
     */
    public function deleteByStockCutLogId(int $stockCutLogId): void
    {
        if (! $this->tableExists()) {
            return;
        }

        DB::table('stock_cut_variances')->where('stock_cut_log_id', $stockCutLogId)->delete();
    }
}
