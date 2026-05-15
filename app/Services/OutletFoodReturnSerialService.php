<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OutletFoodReturnSerialService
{
    public function loadSerialItems(int $returnId)
    {
        return DB::table('outlet_food_return_serial_items as osi')
            ->leftJoin('items as i', 'i.id', '=', 'osi.item_id')
            ->where('osi.outlet_food_return_id', $returnId)
            ->select('osi.*', 'i.name as item_name', 'i.sku')
            ->orderBy('osi.id')
            ->get();
    }

    public function processSerialOnApprove(object $return, object $serialRow, int $returnId, int $movedBy, string $approvedByLabel = 'Approved'): void
    {
        $itemMaster = DB::table('items')->where('id', $serialRow->item_id)->first();
        if (! $itemMaster) {
            throw new \Exception("Item master tidak ditemukan untuk serial {$serialRow->serial_number}");
        }

        $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $serialRow->item_id)->first();
        if (! $inventoryItem) {
            throw new \Exception("Inventory item tidak ditemukan: {$serialRow->item_id}");
        }

        $returnQty = (float) $serialRow->return_qty;
        $unitId = (int) $serialRow->unit_id;
        $qtySmall = (float) $serialRow->qty_small;
        if ($qtySmall <= 0) {
            $qtySmall = $this->convertQtyToSmall($returnQty, $unitId, $itemMaster);
        }

        $currentStock = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('id_outlet', $return->outlet_id)
            ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
            ->first();

        if (! $currentStock || $currentStock->qty_small < $qtySmall) {
            throw new \Exception("Stok tidak mencukupi untuk serial {$serialRow->serial_number}.");
        }

        DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('id_outlet', $return->outlet_id)
            ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
            ->update([
                'qty_small' => $currentStock->qty_small - $qtySmall,
                'updated_at' => now(),
            ]);

        if ($serialRow->outlet_food_good_receive_item_id) {
            $grItem = DB::table('outlet_food_good_receive_items')
                ->where('id', $serialRow->outlet_food_good_receive_item_id)
                ->first();
            if ($grItem) {
                DB::table('outlet_food_good_receive_items')
                    ->where('id', $serialRow->outlet_food_good_receive_item_id)
                    ->update([
                        'remaining_qty' => $grItem->remaining_qty + $returnQty,
                        'received_qty' => $grItem->received_qty - $returnQty,
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::table('outlet_food_inventory_cards')->insert([
            'inventory_item_id' => $serialRow->item_id,
            'id_outlet' => $return->outlet_id,
            'warehouse_outlet_id' => $return->warehouse_outlet_id,
            'date' => $return->return_date,
            'reference_type' => 'outlet_food_return',
            'reference_id' => $returnId,
            'out_qty_small' => $qtySmall,
            'cost_per_small' => $currentStock->last_cost_small,
            'value_out' => $qtySmall * $currentStock->last_cost_small,
            'saldo_qty_small' => $currentStock->qty_small - $qtySmall,
            'saldo_value' => ($currentStock->qty_small - $qtySmall) * $currentStock->last_cost_small,
            'description' => "Stock Out - Outlet Food Return Serial ({$approvedByLabel})",
            'created_at' => now(),
        ]);

        $now = now();
        DB::table('inventory_item_serials')->where('id', $serialRow->serial_id)->update([
            'is_received' => 0,
            'out_outlet_food_return_id' => null,
            'updated_at' => $now,
        ]);

        DB::table('inventory_serial_movements')->insert([
            'serial_id' => $serialRow->serial_id,
            'serial_number' => $serialRow->serial_number,
            'movement_type' => 'ofrt_out',
            'outlet_food_return_id' => $returnId,
            'item_id' => $serialRow->item_id,
            'qty' => $returnQty,
            'unit_id' => $serialRow->unit_id,
            'moved_by' => $movedBy,
            'moved_at' => $now,
            'notes' => 'Outlet food return serial',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function releaseReservationsOnReject(int $returnId): void
    {
        DB::table('inventory_item_serials')
            ->where('out_outlet_food_return_id', $returnId)
            ->update([
                'out_outlet_food_return_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function convertQtyToSmall(float $qty, int $unitId, ?object $itemMaster): float
    {
        if (! $itemMaster) {
            return $qty;
        }
        $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);
        if ($unitId === (int) $itemMaster->small_unit_id) {
            return $qty;
        }
        if (! empty($itemMaster->medium_unit_id) && $unitId === (int) $itemMaster->medium_unit_id) {
            return $qty * $smallConv;
        }
        if (! empty($itemMaster->large_unit_id) && $unitId === (int) $itemMaster->large_unit_id) {
            return $qty * $smallConv * $mediumConv;
        }

        return $qty;
    }
}
