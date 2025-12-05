<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Item;
use Illuminate\Support\Facades\Log;

class DeliveryOrderControllerOptimized extends Controller
{
    /**
     * OPTIMIZED: Index method dengan query yang lebih efisien
     */
    public function index(Request $request)
    {
        // OPTIMIZED: Pisahkan query untuk packing list dan RO Supplier GR
        $packingListQuery = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->whereNotNull('do.packing_list_id')
            ->select(
                'do.*',
                'u.nama_lengkap as created_by_name',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            );

        $roSupplierQuery = DB::table('delivery_orders as do')
            ->leftJoin('food_good_receives as gr', 'do.ro_supplier_gr_id', '=', 'gr.id')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo', 'po.source_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->whereNotNull('do.ro_supplier_gr_id')
            ->select(
                'do.*',
                'u.nama_lengkap as created_by_name',
                'gr.gr_number as packing_number',
                'fo.order_number as floor_order_number',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            );

        // Apply filters to both queries
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $packingListQuery->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
            
            $roSupplierQuery->where(function($q) use ($search) {
                $q->where('gr.gr_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }

        if ($request->filled('dateFrom')) {
            $packingListQuery->whereDate('do.created_at', '>=', $request->dateFrom);
            $roSupplierQuery->whereDate('do.created_at', '>=', $request->dateFrom);
        }

        if ($request->filled('dateTo')) {
            $packingListQuery->whereDate('do.created_at', '<=', $request->dateTo);
            $roSupplierQuery->whereDate('do.created_at', '<=', $request->dateTo);
        }

        // OPTIMIZED: Union queries instead of complex JOIN
        $orders = $packingListQuery->union($roSupplierQuery)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('DeliveryOrder/Index', [
            'orders' => $orders,
            'search' => $request->search,
            'dateFrom' => $request->dateFrom,
            'dateTo' => $request->dateTo,
        ]);
    }

    /**
     * OPTIMIZED: Create method dengan batch queries
     */
    public function create(Request $request)
    {
        // OPTIMIZED: Single query untuk packing lists yang belum digunakan
        $packingLists = DB::select("
            SELECT 
                pl.id,
                pl.packing_number,
                pl.created_at,
                fo.order_number as floor_order_number,
                fo.tanggal as floor_order_date,
                o.nama_outlet,
                u.nama_lengkap as creator_name,
                wd.name as division_name,
                wd.id as warehouse_division_id,
                w.name as warehouse_name,
                wo.name as warehouse_outlet_name
            FROM food_packing_lists pl
            LEFT JOIN food_floor_orders fo ON pl.food_floor_order_id = fo.id
            LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
            LEFT JOIN users u ON pl.created_by = u.id
            LEFT JOIN warehouse_division wd ON pl.warehouse_division_id = wd.id
            LEFT JOIN warehouses w ON wd.warehouse_id = w.id
            LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
            WHERE pl.id NOT IN (
                SELECT DISTINCT packing_list_id 
                FROM delivery_orders 
                WHERE packing_list_id IS NOT NULL
            )
            ORDER BY pl.created_at DESC
        ");

        // OPTIMIZED: Single query untuk RO Supplier GRs
        $roSupplierGRs = DB::select("
            SELECT 
                gr.id as gr_id,
                gr.gr_number as packing_number,
                gr.receive_date as created_at,
                fo.order_number as floor_order_number,
                fo.tanggal as floor_order_date,
                o.nama_outlet,
                u.nama_lengkap as creator_name,
                'Perishable' as division_name,
                1 as warehouse_division_id,
                'Warehouse 1' as warehouse_name,
                wo.name as warehouse_outlet_name,
                'ro_supplier_gr' as source_type,
                s.name as supplier_name
            FROM food_good_receives gr
            LEFT JOIN purchase_order_foods po ON gr.po_id = po.id
            LEFT JOIN food_floor_orders fo ON po.source_id = fo.id
            LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
            LEFT JOIN suppliers s ON gr.supplier_id = s.id
            LEFT JOIN users u ON gr.received_by = u.id
            LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
            WHERE po.source_type = 'ro_supplier'
            AND gr.id NOT IN (
                SELECT DISTINCT ro_supplier_gr_id 
                FROM delivery_orders 
                WHERE ro_supplier_gr_id IS NOT NULL
            )
            ORDER BY gr.receive_date DESC
        ");

        $warehouseDivisions = DB::table('warehouse_division')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('DeliveryOrder/Form', [
            'packingLists' => $packingLists,
            'roSupplierGRs' => $roSupplierGRs,
            'warehouseDivisions' => $warehouseDivisions
        ]);
    }

    /**
     * OPTIMIZED: Store method dengan batch operations
     */
    public function store(Request $request)
    {
        Log::info('Mulai proses store Delivery Order (OPTIMIZED)', $request->all());
        
        $isROSupplierGR = strpos($request->packing_list_id, 'gr_') === 0;
        $grId = $isROSupplierGR ? substr($request->packing_list_id, 3) : null;
        
        DB::beginTransaction();
        try {
            // Generate DO number
            $doNumber = $this->generateDONumber();
            
            // Insert delivery order
            $doId = DB::table('delivery_orders')->insertGetId([
                'number' => $doNumber,
                'floor_order_id' => $this->getFloorOrderId($request->packing_list_id, $isROSupplierGR, $grId),
                'packing_list_id' => $isROSupplierGR ? 0 : $request->packing_list_id,
                'ro_supplier_gr_id' => $isROSupplierGR ? $grId : null,
                'source_type' => $isROSupplierGR ? 'ro_supplier_gr' : 'packing_list',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // OPTIMIZED: Batch insert delivery order items
            $this->insertDeliveryOrderItemsBatch($doId, $request->items, $isROSupplierGR, $grId);
            
            // OPTIMIZED: Batch update inventory
            $this->updateInventoryBatch($doId, $request->items, $isROSupplierGR, $grId);
            
            // Log activity
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'module' => 'delivery_order',
                'description' => 'Membuat delivery order untuk ' . ($isROSupplierGR ? 'RO Supplier GR #' . $grId : 'packing list #' . $request->packing_list_id),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);
            
            DB::commit();
            Log::info('Sukses simpan Delivery Order (OPTIMIZED)');
            
            return response()->json([
                'success' => true,
                'message' => 'Delivery Order berhasil disimpan!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal simpan Delivery Order (OPTIMIZED): ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan Delivery Order: ' . $e->getMessage()]);
        }
    }

    /**
     * OPTIMIZED: Batch insert delivery order items
     */
    private function insertDeliveryOrderItemsBatch($doId, $items, $isROSupplierGR, $grId)
    {
        $insertData = [];
        
        foreach ($items as $item) {
            $realItemId = $this->getRealItemId($item['id'], $isROSupplierGR);
            $barcode = $this->extractBarcode($item['barcode'] ?? null);
            
            $insertData[] = [
                'delivery_order_id' => $doId,
                'item_id' => $realItemId,
                'barcode' => $barcode,
                'qty_packing_list' => $item['qty'],
                'qty_scan' => $item['qty_scan'],
                'unit' => $item['unit'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // OPTIMIZED: Single batch insert
        DB::table('delivery_order_items')->insert($insertData);
    }

    /**
     * OPTIMIZED: Batch update inventory
     */
    private function updateInventoryBatch($doId, $items, $isROSupplierGR, $grId)
    {
        $warehouseId = $this->getWarehouseId($isROSupplierGR, $grId);
        if (!$warehouseId) return;

        $inventoryUpdates = [];
        $inventoryCards = [];
        
        foreach ($items as $item) {
            $realItemId = $this->getRealItemId($item['id'], $isROSupplierGR);
            $stockData = $this->calculateStockQuantities($realItemId, $item['qty_scan'], $item['unit'] ?? null);
            
            if ($stockData) {
                $inventoryUpdates[] = [
                    'inventory_item_id' => $stockData['inventory_item_id'],
                    'warehouse_id' => $warehouseId,
                    'qty_small' => $stockData['qty_small'],
                    'qty_medium' => $stockData['qty_medium'],
                    'qty_large' => $stockData['qty_large'],
                    'stock' => $stockData['stock']
                ];
                
                $inventoryCards[] = [
                    'inventory_item_id' => $stockData['inventory_item_id'],
                    'warehouse_id' => $warehouseId,
                    'date' => now()->toDateString(),
                    'reference_type' => 'delivery_order',
                    'reference_id' => $doId,
                    'out_qty_small' => $stockData['qty_small'],
                    'out_qty_medium' => $stockData['qty_medium'],
                    'out_qty_large' => $stockData['qty_large'],
                    'cost_per_small' => $stockData['stock']->last_cost_small,
                    'cost_per_medium' => $stockData['stock']->last_cost_medium,
                    'cost_per_large' => $stockData['stock']->last_cost_large,
                    'value_out' => $stockData['qty_small'] * $stockData['stock']->last_cost_small,
                    'saldo_qty_small' => $stockData['stock']->qty_small - $stockData['qty_small'],
                    'saldo_qty_medium' => $stockData['stock']->qty_medium - $stockData['qty_medium'],
                    'saldo_qty_large' => $stockData['stock']->qty_large - $stockData['qty_large'],
                    'saldo_value' => ($stockData['stock']->qty_small - $stockData['qty_small']) * $stockData['stock']->last_cost_small,
                    'description' => 'Stock Out - Delivery Order',
                    'created_at' => now(),
                ];
            }
        }
        
        // OPTIMIZED: Batch update stocks
        foreach ($inventoryUpdates as $update) {
            DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $update['inventory_item_id'])
                ->where('warehouse_id', $update['warehouse_id'])
                ->update([
                    'qty_small' => DB::raw('qty_small - ' . $update['qty_small']),
                    'qty_medium' => DB::raw('qty_medium - ' . $update['qty_medium']),
                    'qty_large' => DB::raw('qty_large - ' . $update['qty_large']),
                    'updated_at' => now(),
                ]);
        }
        
        // OPTIMIZED: Batch insert inventory cards
        if (!empty($inventoryCards)) {
            DB::table('food_inventory_cards')->insert($inventoryCards);
        }
    }

    /**
     * Helper methods untuk optimasi
     */
    private function getFloorOrderId($packingListId, $isROSupplierGR, $grId)
    {
        if ($isROSupplierGR) {
            return DB::table('food_good_receives as gr')
                ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
                ->where('gr.id', $grId)
                ->value('po.source_id');
        } else {
            return DB::table('food_packing_lists')
                ->where('id', $packingListId)
                ->value('food_floor_order_id');
        }
    }

    private function getWarehouseId($isROSupplierGR, $grId)
    {
        if ($isROSupplierGR) {
            return 1; // Warehouse 1 untuk RO Supplier
        } else {
            return DB::table('food_packing_lists as pl')
                ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
                ->where('pl.id', request()->packing_list_id)
                ->value('wd.warehouse_id');
        }
    }

    private function getRealItemId($itemId, $isROSupplierGR)
    {
        if ($isROSupplierGR) {
            return DB::table('food_good_receive_items')
                ->where('id', $itemId)
                ->value('item_id');
        } else {
            $packingListItem = DB::table('food_packing_list_items')
                ->where('id', $itemId)
                ->first();
            
            return DB::table('food_floor_order_items')
                ->where('id', $packingListItem->food_floor_order_item_id)
                ->value('item_id');
        }
    }

    private function extractBarcode($barcode)
    {
        if (is_array($barcode) && count($barcode) > 0) {
            return $barcode[0];
        } elseif (is_string($barcode)) {
            return $barcode;
        }
        return null;
    }

    private function calculateStockQuantities($itemId, $qtyInput, $unit)
    {
        $itemMaster = DB::table('items')->where('id', $itemId)->first();
        if (!$itemMaster) return null;

        $inventoryItem = DB::table('food_inventory_items')->where('item_id', $itemId)->first();
        if (!$inventoryItem) return null;

        $stock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $this->getWarehouseId(request()->has('gr_'), null))
            ->first();

        if (!$stock) return null;

        // Calculate quantities based on unit
        $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
        $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
        $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
        
        $smallConv = $itemMaster->small_conversion_qty ?: 1;
        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

        $qty_small = $qty_medium = $qty_large = 0;

        if ($unit === $unitSmall) {
            $qty_small = $qtyInput;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
        } elseif ($unit === $unitMedium) {
            $qty_medium = $qtyInput;
            $qty_small = $qty_medium * $smallConv;
            $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
        } elseif ($unit === $unitLarge) {
            $qty_large = $qtyInput;
            $qty_medium = $qty_large * $mediumConv;
            $qty_small = $qty_medium * $smallConv;
        } else {
            $qty_small = $qtyInput;
        }

        return [
            'inventory_item_id' => $inventoryItem->id,
            'qty_small' => $qty_small,
            'qty_medium' => $qty_medium,
            'qty_large' => $qty_large,
            'stock' => $stock
        ];
    }

    private function generateDONumber()
    {
        $prefix = 'DO';
        $date = now()->format('ymd');
        
        $lastDO = DB::table('delivery_orders')
            ->where('number', 'like', $prefix . $date . '%')
            ->orderBy('number', 'desc')
            ->first();
        
        if ($lastDO) {
            $lastNumber = (int) substr($lastDO->number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
