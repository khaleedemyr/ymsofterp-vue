<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FoodPackingList;
use App\Models\FoodFloorOrder;
use App\Models\WarehouseDivision;
use App\Models\FoodPackingListItem;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class PackingListControllerOptimized extends Controller
{
    /**
     * OPTIMIZED VERSION - Fix N+1 Query Problem
     * Menggunakan eager loading dan batch queries
     */
    public function create(Request $request)
    {
        // OPTIMIZED: Gunakan eager loading untuk menghindari N+1 queries
        $query = FoodFloorOrder::whereIn('food_floor_orders.status', ['approved', 'packing'])
            ->where('food_floor_orders.fo_mode', '!=', 'RO Supplier')
            ->with([
                'outlet', 
                'user', 
                'items.item.smallUnit', 
                'items.item.mediumUnit', 
                'items.item.largeUnit', 
                'warehouseDivisions', 
                'warehouseOutlet'
            ])
            ->join('tbl_data_outlet', 'food_floor_orders.id_outlet', '=', 'tbl_data_outlet.id_outlet');

        if ($request->filled('arrival_date')) {
            $query->whereDate('food_floor_orders.arrival_date', $request->arrival_date);
        }

        $floorOrders = $query->orderBy('food_floor_orders.tanggal', 'desc')
            ->orderBy('tbl_data_outlet.nama_outlet')
            ->get();

        // OPTIMIZED: Batch query untuk cek packed items
        $floorOrderIds = $floorOrders->pluck('id')->toArray();
        $packedItems = $this->getPackedItemsBatch($floorOrderIds);

        // Filter FO yang masih memiliki item yang belum di-packing
        $floorOrders = $floorOrders->filter(function($fo) use ($packedItems) {
            $foDivisions = $fo->warehouseDivisions->pluck('id')->toArray();
            
            foreach ($foDivisions as $divisionId) {
                $itemsInDivision = $fo->items->filter(function($item) use ($divisionId) {
                    return $item->item && $item->item->warehouse_division_id == $divisionId;
                });
                
                if ($itemsInDivision->count() > 0) {
                    $packedItemIds = $packedItems->where('food_floor_order_id', $fo->id)
                        ->where('warehouse_division_id', $divisionId)
                        ->pluck('food_floor_order_item_id')
                        ->toArray();
                    
                    if ($itemsInDivision->whereNotIn('id', $packedItemIds)->count() > 0) {
                        return true;
                    }
                }
            }
            
            return false;
        })->values();

        $warehouseDivisions = WarehouseDivision::all();
        return inertia('PackingList/Form', [
            'floorOrders' => $floorOrders,
            'warehouseDivisions' => $warehouseDivisions,
        ]);
    }

    /**
     * OPTIMIZED: Batch query untuk mendapatkan packed items
     * Menggantikan N+1 queries dengan 1 query
     */
    private function getPackedItemsBatch($floorOrderIds)
    {
        return FoodPackingListItem::whereHas('packingList', function($q) use ($floorOrderIds) {
            $q->whereIn('food_floor_order_id', $floorOrderIds)
              ->where('status', 'packing');
        })
        ->with('packingList:id,food_floor_order_id,warehouse_division_id')
        ->get()
        ->map(function($item) {
            return [
                'food_floor_order_id' => $item->packingList->food_floor_order_id,
                'warehouse_division_id' => $item->packingList->warehouse_division_id,
                'food_floor_order_item_id' => $item->food_floor_order_item_id
            ];
        });
    }

    /**
     * OPTIMIZED: Summary method dengan batch queries
     */
    public function summary(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        // OPTIMIZED: Gunakan raw query untuk performa maksimal
        $summaryData = DB::select("
            SELECT 
                wd.name as warehouse_division_name,
                i.id as item_id,
                i.name as item_name,
                foi.unit,
                SUM(foi.qty) as total_qty
            FROM food_floor_orders fo
            JOIN food_floor_order_items foi ON fo.id = foi.floor_order_id
            JOIN items i ON foi.item_id = i.id
            JOIN warehouse_division wd ON i.warehouse_division_id = wd.id
            LEFT JOIN food_packing_list_items pli ON foi.id = pli.food_floor_order_item_id
            LEFT JOIN food_packing_lists pl ON pli.packing_list_id = pl.id AND pl.status = 'packing'
            WHERE fo.status IN ('approved', 'packing')
            AND DATE(fo.tanggal) = ?
            AND pli.id IS NULL
            GROUP BY wd.name, i.id, i.name, foi.unit
            ORDER BY wd.name, i.name
        ", [$request->tanggal]);

        // Group by division
        $summaryByDivision = [];
        foreach ($summaryData as $row) {
            if (!isset($summaryByDivision[$row->warehouse_division_name])) {
                $summaryByDivision[$row->warehouse_division_name] = [
                    'warehouse_division_name' => $row->warehouse_division_name,
                    'items' => []
                ];
            }
            
            $summaryByDivision[$row->warehouse_division_name]['items'][] = [
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'total_qty' => $row->total_qty,
                'unit' => $row->unit,
            ];
        }

        return response()->json([
            'divisions' => array_values($summaryByDivision)
        ]);
    }

    /**
     * OPTIMIZED: Available items dengan batch stock checking
     */
    public function availableItems(Request $request)
    {
        $foId = $request->input('fo_id');
        $divisionId = $request->input('division_id');
        
        $division = WarehouseDivision::find($divisionId);
        $warehouse_id = $division ? $division->warehouse_id : null;

        // OPTIMIZED: Single query untuk semua data
        $items = DB::select("
            SELECT 
                foi.id,
                foi.qty,
                foi.unit,
                i.name,
                i.id as item_id,
                COALESCE(fis.qty_small, 0) as stock
            FROM food_floor_order_items foi
            JOIN items i ON foi.item_id = i.id
            LEFT JOIN food_inventory_items fii ON i.id = fii.item_id
            LEFT JOIN food_inventory_stocks fis ON fii.id = fis.inventory_item_id AND fis.warehouse_id = ?
            LEFT JOIN food_packing_list_items pli ON foi.id = pli.food_floor_order_item_id
            LEFT JOIN food_packing_lists pl ON pli.packing_list_id = pl.id AND pl.status = 'packing'
            WHERE foi.floor_order_id = ?
            AND i.warehouse_division_id = ?
            AND pli.id IS NULL
            ORDER BY i.name
        ", [$warehouse_id, $foId, $divisionId]);

        return response()->json(['items' => $items]);
    }

    /**
     * OPTIMIZED: Matrix method dengan single query
     */
    public function matrix(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        // OPTIMIZED: Single complex query instead of multiple queries
        $matrixData = DB::select("
            SELECT 
                o.id as outlet_id,
                o.nama_outlet,
                i.id as item_id,
                i.name as item_name,
                foi.unit as item_unit,
                SUM(foi.qty) as total_qty
            FROM food_floor_orders fo
            JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
            JOIN food_floor_order_items foi ON fo.id = foi.floor_order_id
            JOIN items i ON foi.item_id = i.id
            LEFT JOIN food_packing_list_items pli ON foi.id = pli.food_floor_order_item_id
            LEFT JOIN food_packing_lists pl ON pli.packing_list_id = pl.id AND pl.status = 'packing'
            WHERE fo.status IN ('approved', 'packing')
            AND fo.fo_mode != 'RO Supplier'
            AND DATE(fo.tanggal) = ?
            AND pli.id IS NULL
            GROUP BY o.id, o.nama_outlet, i.id, i.name, foi.unit
            ORDER BY o.nama_outlet, i.name
        ", [$request->tanggal]);

        // Process data for matrix format
        $outlets = [];
        $items = [];
        $matrix = [];

        foreach ($matrixData as $row) {
            if (!isset($outlets[$row->outlet_id])) {
                $outlets[$row->outlet_id] = [
                    'id' => $row->outlet_id,
                    'nama_outlet' => $row->nama_outlet
                ];
            }

            if (!isset($items[$row->item_id])) {
                $items[$row->item_id] = [
                    'id' => $row->item_id,
                    'name' => $row->item_name,
                    'unit' => $row->item_unit
                ];
            }

            $matrix[] = [
                'outlet_id' => $row->outlet_id,
                'item_id' => $row->item_id,
                'qty' => $row->total_qty
            ];
        }

        return response()->json([
            'outlets' => array_values($outlets),
            'items' => array_values($items),
            'matrix' => $matrix
        ]);
    }
}
