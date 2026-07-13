<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LostBreakageStockService
{
    public function hasStockBeenApplied(int $headerId): bool
    {
        return DB::table('asset_inventory_cards')
            ->where('reference_type', 'lost_breakage')
            ->where('reference_id', $headerId)
            ->exists();
    }

    /**
     * @param  array<int, object>  $details
     */
    public function applyStockOut(object $header, array $details): void
    {
        if ($this->hasStockBeenApplied((int) $header->id)) {
            return;
        }

        if (empty($details)) {
            return;
        }

        $ownerOutletId = (int) $header->owner_outlet_id;
        $warehouseOutletId = $header->warehouse_outlet_id ? (int) $header->warehouse_outlet_id : null;
        $locationOutletId = AssetInventoryStockService::resolveLocationOutletId(
            (int) $header->outlet_id,
            $warehouseOutletId
        );

        foreach ($details as $detail) {
            $itemMaster = DB::table('items')->where('id', $detail->item_id)->first();
            if (!$itemMaster) {
                throw new \Exception('Item tidak ditemukan untuk detail ID: ' . $detail->id);
            }

            $inventoryItem = DB::table('asset_inventory_items')
                ->where('item_id', $detail->item_id)
                ->first();

            if (!$inventoryItem) {
                $inventoryItemId = DB::table('asset_inventory_items')->insertGetId([
                    'item_id' => $detail->item_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $inventoryItemId = (int) $inventoryItem->id;
            }

            $converted = $this->convertUnitsByUnitId($itemMaster, (int) $detail->unit_id, (float) $detail->qty);

            $stock = AssetInventoryStockService::findStock(
                $inventoryItemId,
                $ownerOutletId,
                $warehouseOutletId
            );

            if (!$stock) {
                throw new \Exception('Stok tidak ditemukan untuk item: ' . $itemMaster->name);
            }

            $costSmall = $stock->last_cost_small ?? 0;
            $costMedium = $stock->last_cost_medium ?? 0;
            $costLarge = $stock->last_cost_large ?? 0;

            $saldoSmall = (float) $stock->qty_small - $converted['qty_small'];
            $saldoMedium = (float) $stock->qty_medium - $converted['qty_medium'];
            $saldoLarge = (float) $stock->qty_large - $converted['qty_large'];

            DB::table('asset_inventory_stocks')
                ->where('id', $stock->id)
                ->update([
                    'qty_small' => $saldoSmall,
                    'qty_medium' => $saldoMedium,
                    'qty_large' => $saldoLarge,
                    'value' => $saldoSmall * $costSmall,
                    'updated_at' => now(),
                ]);

            $typeLabel = $detail->type ?? 'lost';

            DB::table('asset_inventory_cards')->insert([
                'inventory_item_id' => $inventoryItemId,
                'owner_outlet_id' => $ownerOutletId,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $warehouseOutletId,
                'date' => $header->date,
                'reference_type' => 'lost_breakage',
                'reference_id' => $header->id,
                'in_qty_small' => 0,
                'in_qty_medium' => 0,
                'in_qty_large' => 0,
                'out_qty_small' => $converted['qty_small'],
                'out_qty_medium' => $converted['qty_medium'],
                'out_qty_large' => $converted['qty_large'],
                'cost_per_small' => $costSmall,
                'cost_per_medium' => $costMedium,
                'cost_per_large' => $costLarge,
                'value_in' => 0,
                'value_out' => $converted['qty_small'] * $costSmall,
                'saldo_qty_small' => $saldoSmall,
                'saldo_qty_medium' => $saldoMedium,
                'saldo_qty_large' => $saldoLarge,
                'saldo_value' => $saldoSmall * $costSmall,
                'description' => 'Stock Out - Lost & Breakage (' . $typeLabel . ') ' . ($header->number ?? ''),
                'created_at' => now(),
            ]);

            $lastCostQuery = DB::table('asset_inventory_cost_histories')
                ->where('inventory_item_id', $inventoryItemId);
            AssetInventoryStockService::applyOwnerWarehouseScope(
                $lastCostQuery,
                $ownerOutletId,
                $warehouseOutletId
            );
            $lastCostHistory = $lastCostQuery
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->first();

            DB::table('asset_inventory_cost_histories')->insert([
                'inventory_item_id' => $inventoryItemId,
                'owner_outlet_id' => $ownerOutletId,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $warehouseOutletId,
                'date' => $header->date,
                'reference_type' => 'lost_breakage',
                'reference_id' => $header->id,
                'old_cost_small' => $lastCostHistory ? $lastCostHistory->new_cost_small : $costSmall,
                'old_cost_medium' => $lastCostHistory ? $lastCostHistory->new_cost_medium : $costMedium,
                'old_cost_large' => $lastCostHistory ? $lastCostHistory->new_cost_large : $costLarge,
                'new_cost_small' => $costSmall,
                'new_cost_medium' => $costMedium,
                'new_cost_large' => $costLarge,
                'qty' => $converted['qty_small'],
                'value' => $converted['qty_small'] * $costSmall,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @return array{qty_small: float, qty_medium: float, qty_large: float}
     */
    private function convertUnitsByUnitId(object $itemMaster, int $unitId, float $qty): array
    {
        $smallConv = $itemMaster->small_conversion_qty ?: 1;
        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

        if ($unitId === (int) $itemMaster->medium_unit_id) {
            $qtyMedium = $qty;
            $qtySmall = $qty * $mediumConv;
            $qtyLarge = $smallConv > 0 ? ($qty * $mediumConv) / $smallConv : 0;
        } elseif ($unitId === (int) $itemMaster->large_unit_id) {
            $qtyLarge = $qty;
            $qtySmall = $qty * $smallConv;
            $qtyMedium = $mediumConv > 0 ? ($qty * $smallConv) / $mediumConv : 0;
        } else {
            $qtySmall = $qty;
            $qtyMedium = $mediumConv > 0 ? $qty / $mediumConv : 0;
            $qtyLarge = $smallConv > 0 ? $qty / $smallConv : 0;
        }

        return [
            'qty_small' => $qtySmall,
            'qty_medium' => $qtyMedium,
            'qty_large' => $qtyLarge,
        ];
    }
}
