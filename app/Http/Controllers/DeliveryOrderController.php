<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Item;
use Illuminate\Support\Facades\Log;

class DeliveryOrderController extends Controller
{
    /**
     * Helper method untuk membuat pagination default
     */
    private function getEmptyPagination()
    {
        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 15,
            'total' => 0,
            'from' => null,
            'to' => null,
            'links' => []
        ];
    }
    public function index(Request $request)
    {
        // Simpan filter di session untuk persist
        if ($request->hasAny(['search', 'dateFrom', 'dateTo', 'load_data', 'per_page'])) {
            session([
                'delivery_order_filters' => [
                    'search' => $request->search,
                    'dateFrom' => $request->dateFrom,
                    'dateTo' => $request->dateTo,
                    'load_data' => $request->load_data,
                    'per_page' => $request->per_page
                ]
            ]);
        }
        
        // Ambil filter dari session jika ada
        $filters = session('delivery_order_filters', []);
        $search = $request->search ?? $filters['search'] ?? '';
        $dateFrom = $request->dateFrom ?? $filters['dateFrom'] ?? '';
        $dateTo = $request->dateTo ?? $filters['dateTo'] ?? '';
        $loadData = $request->load_data ?? $filters['load_data'] ?? '';
        $perPage = $request->per_page ?? $filters['per_page'] ?? 15;
        
        // OPTIMIZED: Tidak load data otomatis, hanya load jika ada filter
        $orders = null;
        
        if ($loadData === '1') {
            // OPTIMIZED: Use single query with conditional joins for better performance
            $orders = $this->getDeliveryOrdersOptimized($search, $dateFrom, $dateTo, $perPage);
        }

        return Inertia::render('DeliveryOrder/Index', [
            'orders' => $orders ?: $this->getEmptyPagination(),
            'filters' => [
                'search' => $search,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'load_data' => $loadData,
                'per_page' => $perPage
            ],
        ]);
    }

    public function clearFilters()
    {
        session()->forget('delivery_order_filters');
        return redirect()->route('delivery-order.index');
    }

    public function create(Request $request)
    {
        // Ambil daftar packing list yang belum/do belum dibuat
        $usedPackingListIds = DB::table('delivery_orders')->whereNotNull('packing_list_id')->pluck('packing_list_id')->toArray();
        $packingLists = DB::table('food_packing_lists as pl')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'pl.created_by', '=', 'u.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id');
        
        // Hanya apply whereNotIn jika ada data yang sudah digunakan
        if (!empty($usedPackingListIds)) {
            $packingLists = $packingLists->whereNotIn('pl.id', $usedPackingListIds);
        }
        
                $packingLists = $packingLists->select(
            'pl.id',
            'pl.packing_number',
            'pl.created_at',
            'fo.order_number as floor_order_number',
            'fo.tanggal as floor_order_date',
            'o.nama_outlet',
            'u.nama_lengkap as creator_name',
            'wd.name as division_name',
            'wd.id as warehouse_division_id',
            'w.name as warehouse_name',
            'wo.name as warehouse_outlet_name'
        )
        ->orderByDesc('pl.created_at')
        ->get();

        // Log removed for performance
        
        // Convert to array to ensure proper JSON serialization
        $packingLists = $packingLists->toArray();

        // Ambil data RO Supplier yang sudah di-GR dan belum dibuat DO
        $roSupplierGRs = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo', 'po.source_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->where('po.source_type', 'ro_supplier')
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('delivery_orders as do')
                      ->whereRaw('do.ro_supplier_gr_id = gr.id');
            })
            ->select(
                'gr.id as gr_id',
                'gr.gr_number as packing_number',
                'gr.receive_date as created_at',
                'fo.order_number as floor_order_number',
                'fo.tanggal as floor_order_date',
                'o.nama_outlet',
                'u.nama_lengkap as creator_name',
                DB::raw("'Perishable' as division_name"),
                DB::raw("1 as warehouse_division_id"),
                DB::raw("'Warehouse 1' as warehouse_name"),
                'wo.name as warehouse_outlet_name',
                DB::raw("'ro_supplier_gr' as source_type"),
                's.name as supplier_name'
            )
            ->orderByDesc('gr.receive_date')
            ->get();

        // Log removed for performance
        
        // Convert to array to ensure proper JSON serialization
        $roSupplierGRs = $roSupplierGRs->toArray();

        // Ambil semua warehouse divisions
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

    public function show($id)
    {
        $order = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'do.floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->select(
                'do.*',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'pl.created_at as packing_date',
                'fo.tanggal as floor_order_date',
                'u.nama_lengkap as created_by_name'
            )
            ->where('do.id', $id)
            ->first();
        $items = DB::table('delivery_order_items as doi')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->select(
                'doi.id',
                'i.name as item_name',
                'doi.qty_packing_list',
                'doi.qty_scan',
                'doi.unit'
            )
            ->where('doi.delivery_order_id', $id)
            ->get();
        return Inertia::render('DeliveryOrder/Show', [
            'order' => $order,
            'items' => $items
        ]);
    }

    private function generateDONumber()
    {
        $prefix = 'DO';
        $date = now()->format('ymd');
        
        // Get the last DO number for today
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

    public function store(Request $request)
    {
        // OPTIMIZED: Pre-validate all data before transaction
        $this->validateDeliveryOrderData($request);
        
        // Cek apakah ini adalah RO Supplier GR atau Packing List biasa
        $isROSupplierGR = false;
        $grId = null;
        $floorOrderId = null;
        $warehouseDivisionId = null;
        $warehouseId = null;
        
        if (strpos($request->packing_list_id, 'gr_') === 0) {
            // Ini adalah RO Supplier GR
            $isROSupplierGR = true;
            $grId = substr($request->packing_list_id, 3); // Hapus prefix 'gr_'
            
            // Ambil data dari GR
            $gr = DB::table('food_good_receives as gr')
                ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
                ->leftJoin('food_floor_orders as fo', 'po.source_id', '=', 'fo.id')
                ->where('gr.id', $grId)
                ->first();
            
            if (!$gr) {
                throw new \Exception('RO Supplier GR tidak ditemukan');
            }
            
            $floorOrderId = $gr->id; // source_id dari PO
            $warehouseDivisionId = 1; // Perishable
            $warehouseId = 1; // Warehouse 1
        } else {
            // Ini adalah Packing List biasa
            $packingList = DB::table('food_packing_lists')->where('id', $request->packing_list_id)->first();
            $floorOrderId = $packingList->food_floor_order_id ?? null;
            $warehouseDivisionId = $packingList->warehouse_division_id ?? null;
            if ($warehouseDivisionId) {
                $warehouseId = DB::table('warehouse_division')->where('id', $warehouseDivisionId)->value('warehouse_id');
            }
        }
        
        DB::beginTransaction();
        try {
            $insertData = [
                'number' => $this->generateDONumber(),
                'floor_order_id' => $floorOrderId,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            if ($isROSupplierGR) {
                $insertData['ro_supplier_gr_id'] = $grId;
                $insertData['packing_list_id'] = 0;
                $insertData['source_type'] = 'ro_supplier_gr';
            } else {
                $insertData['packing_list_id'] = $request->packing_list_id;
                $insertData['ro_supplier_gr_id'] = null;
                $insertData['source_type'] = 'packing_list';
            }
            
            $doId = DB::table('delivery_orders')->insertGetId($insertData);
            
            // OPTIMIZED: Batch process items instead of individual loops
            $this->processDeliveryOrderItemsBatch($doId, $request->items, $isROSupplierGR, $grId, $warehouseId);
            
            // Update status RO menjadi delivered hanya jika semua packing list sudah dibuat DO
                if ($isROSupplierGR) {
                $po = DB::table('purchase_order_foods')->where('id', $gr->po_id)->first();
                if ($po && $po->source_id) {
                    $this->checkAndUpdateFloorOrderStatus($po->source_id, 'RO Supplier GR');
                }
                } else {
                if ($floorOrderId) {
                    $this->checkAndUpdateFloorOrderStatus($floorOrderId, 'Regular Packing List');
                }
            }
            
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
            Log::info('Sukses simpan Delivery Order');
            $kasirName = DB::table('users')->where('id', auth()->id())->value('nama_lengkap');
            
            // Ambil data untuk response berdasarkan jenis source
            if ($isROSupplierGR) {
                $roSupplierData = DB::table('food_good_receives as gr')
                    ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
                    ->leftJoin('food_floor_orders as fo', 'po.source_id', '=', 'fo.id')
                    ->leftJoin('users as u', 'fo.user_id', '=', 'u.id')
                    ->where('gr.id', $grId)
                    ->select(
                        DB::raw("'Perishable' as division_name"),
                        DB::raw("'Warehouse 1' as warehouse_name"),
                        'fo.order_number as ro_number',
                        'fo.tanggal as ro_date',
                        'u.nama_lengkap as ro_creator_name'
                    )
                    ->first();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Delivery Order berhasil disimpan!',
                    'kasir_name' => $kasirName,
                    'division_name' => $roSupplierData->division_name ?? null,
                    'warehouse_name' => $roSupplierData->warehouse_name ?? null,
                    'ro_number' => $roSupplierData->ro_number ?? null,
                    'ro_date' => $roSupplierData->ro_date ?? null,
                    'ro_creator_name' => $roSupplierData->ro_creator_name ?? null
                ]);
            } else {
                $packingListFull = DB::table('food_packing_lists as pl')
                    ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
                    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                    ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
                    ->leftJoin('users as u', 'fo.user_id', '=', 'u.id')
                    ->where('pl.id', $request->packing_list_id)
                    ->select(
                        'wd.name as division_name',
                        'w.name as warehouse_name',
                        'fo.order_number as ro_number',
                        'fo.tanggal as ro_date',
                        'u.nama_lengkap as ro_creator_name'
                    )
                    ->first();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Delivery Order berhasil disimpan!',
                    'kasir_name' => $kasirName,
                    'division_name' => $packingListFull->division_name ?? null,
                    'warehouse_name' => $packingListFull->warehouse_name ?? null,
                    'ro_number' => $packingListFull->ro_number ?? null,
                    'ro_date' => $packingListFull->ro_date ?? null,
                    'ro_creator_name' => $packingListFull->ro_creator_name ?? null
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal simpan Delivery Order: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan Delivery Order: ' . $e->getMessage()]);
        }
    }

    /**
     * OPTIMIZED: Validate delivery order data before processing
     */
    private function validateDeliveryOrderData($request)
    {
        if (empty($request->items) || !is_array($request->items)) {
            throw new \Exception('Items tidak boleh kosong');
        }
        
        // Validate each item has required fields
        foreach ($request->items as $item) {
            if (!isset($item['id']) || !isset($item['qty_scan'])) {
                throw new \Exception('Data item tidak lengkap');
            }
        }
    }

    /**
     * OPTIMIZED: Batch process delivery order items
     */
    private function processDeliveryOrderItemsBatch($doId, $items, $isROSupplierGR, $grId, $warehouseId)
    {
        $deliveryOrderItems = [];
        $inventoryUpdates = [];
        $inventoryCards = [];
        
        // OPTIMIZED: Pre-fetch all required data in batch
        $itemIds = collect($items)->pluck('id')->toArray();
        $itemData = $this->getItemDataBatch($itemIds, $isROSupplierGR);
        $stockData = $this->getStockDataBatch($itemData, $warehouseId);
        
        foreach ($items as $item) {
            try {
                $realItemId = $this->getRealItemId($item['id'], $isROSupplierGR, $itemData);
                $stockInfo = $stockData[$realItemId] ?? null;
                
                if (!$stockInfo) {
                    throw new \Exception('Item tidak ditemukan atau tidak ada stok');
                }
                
                // Calculate quantities
                $quantities = $this->calculateQuantities($realItemId, $item['qty_scan'], $item['unit'] ?? null, $itemData);
                
                // DEBUG: Log stock validation details
                Log::info('Stock validation debug', [
                    'item_id' => $realItemId,
                    'qty_small_needed' => $quantities['qty_small'],
                    'qty_medium_needed' => $quantities['qty_medium'],
                    'qty_large_needed' => $quantities['qty_large'],
                    'stock_small_available' => $stockInfo['qty_small'],
                    'stock_medium_available' => $stockInfo['qty_medium'],
                    'stock_large_available' => $stockInfo['qty_large'],
                    'input_qty' => $item['qty_scan'],
                    'input_unit' => $item['unit'] ?? 'null'
                ]);
                
                // Validate stock availability
                if ($quantities['qty_small'] > $stockInfo['qty_small']) {
                    // Get unit names for better error message
                    $itemDataForError = $itemData->where('item_id', $realItemId)->first();
                    $unitSmall = DB::table('units')->where('id', $itemDataForError->small_unit_id)->value('name');
                    $unitMedium = DB::table('units')->where('id', $itemDataForError->medium_unit_id)->value('name');
                    $unitLarge = DB::table('units')->where('id', $itemDataForError->large_unit_id)->value('name');
                    
                    // Show stock in the unit that user is trying to use
                    $inputUnit = $item['unit'] ?? null;
                    $availableStock = 0;
                    $unitName = '';
                    
                    if ($inputUnit === $unitSmall) {
                        $availableStock = $stockInfo['qty_small'];
                        $unitName = $unitSmall;
                    } elseif ($inputUnit === $unitMedium) {
                        $availableStock = $stockInfo['qty_medium'];
                        $unitName = $unitMedium;
                    } elseif ($inputUnit === $unitLarge) {
                        $availableStock = $stockInfo['qty_large'];
                        $unitName = $unitLarge;
                    } else {
                        $availableStock = $stockInfo['qty_small'];
                        $unitName = $unitSmall;
                    }
                    
                    throw new \Exception("Qty melebihi stok yang tersedia. Stok tersedia: {$availableStock} {$unitName}");
                }
                
                // Prepare delivery order item
                $barcode = $this->processBarcode($item['barcode'] ?? null);
                
                $deliveryOrderItems[] = [
                    'delivery_order_id' => $doId,
                    'item_id' => $realItemId,
                    'barcode' => $barcode,
                    'qty_packing_list' => $item['qty'],
                    'qty_scan' => $item['qty_scan'],
                    'unit' => $item['unit'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                // Prepare inventory update
                $inventoryUpdates[] = [
                    'inventory_item_id' => $stockInfo['inventory_item_id'],
                    'warehouse_id' => $warehouseId,
                    'qty_small' => $quantities['qty_small'],
                    'qty_medium' => $quantities['qty_medium'],
                    'qty_large' => $quantities['qty_large'],
                    'current_stock' => $stockInfo
                ];
                
                // Prepare inventory card with detailed description
                $inventoryCards[] = [
                    'inventory_item_id' => $stockInfo['inventory_item_id'],
                    'warehouse_id' => $warehouseId,
                    'date' => now()->toDateString(),
                    'reference_type' => 'delivery_order',
                    'reference_id' => $doId,
                    'out_qty_small' => $quantities['qty_small'],
                    'out_qty_medium' => $quantities['qty_medium'],
                    'out_qty_large' => $quantities['qty_large'],
                    'cost_per_small' => $stockInfo['last_cost_small'],
                    'cost_per_medium' => $stockInfo['last_cost_medium'],
                    'cost_per_large' => $stockInfo['last_cost_large'],
                    'value_out' => $quantities['qty_small'] * $stockInfo['last_cost_small'],
                    'saldo_qty_small' => $stockInfo['qty_small'] - $quantities['qty_small'],
                    'saldo_qty_medium' => $stockInfo['qty_medium'] - $quantities['qty_medium'],
                    'saldo_qty_large' => $stockInfo['qty_large'] - $quantities['qty_large'],
                    'saldo_value' => ($stockInfo['qty_small'] - $quantities['qty_small']) * $stockInfo['last_cost_small'],
                    'description' => 'Stock Out - Delivery Order ' . $this->getDONumber($doId) . ' to ' . ($this->getOutletName($doId) ?: 'Outlet'),
                    'created_at' => now(),
                ];
            } catch (\Exception $e) {
                // Fallback: Use original method for this item
                Log::warning('Fallback to original method for item', [
                    'item_id' => $item['id'],
                    'error' => $e->getMessage()
                ]);
                
                $this->processItemFallback($doId, $item, $isROSupplierGR, $grId, $warehouseId);
            }
        }
        
        // OPTIMIZED: Batch insert delivery order items
        if (!empty($deliveryOrderItems)) {
            DB::table('delivery_order_items')->insert($deliveryOrderItems);
        }
        
        // OPTIMIZED: Batch update inventory stocks
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
     * FALLBACK: Process item using original method
     */
    private function processItemFallback($doId, $item, $isROSupplierGR, $grId, $warehouseId)
    {
        // Use original method as fallback
                    $realItemId = null;
                    if ($isROSupplierGR) {
                        $grItem = DB::table('food_good_receive_items')->where('id', $item['id'])->first();
                        if (!$grItem) throw new \Exception('GR item tidak ditemukan untuk id: ' . $item['id']);
                        $realItemId = $grItem->item_id;
                    } else {
                        $packingListItem = DB::table('food_packing_list_items')->where('id', $item['id'])->first();
                        if (!$packingListItem) throw new \Exception('Packing list item tidak ditemukan untuk id: ' . $item['id']);
                        $floorOrderItem = DB::table('food_floor_order_items')->where('id', $packingListItem->food_floor_order_item_id)->first();
                        if (!$floorOrderItem) throw new \Exception('Floor order item tidak ditemukan untuk id: ' . $packingListItem->food_floor_order_item_id);
                        $realItemId = $floorOrderItem->item_id;
                    }
        
        // Insert delivery order item
        $barcode = $this->processBarcode($item['barcode'] ?? null);
        
        DB::table('delivery_order_items')->insert([
            'delivery_order_id' => $doId,
                            'item_id' => $realItemId,
            'barcode' => $barcode,
            'qty_packing_list' => $item['qty'],
            'qty_scan' => $item['qty_scan'],
            'unit' => $item['unit'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
        
        // Process inventory with proper unit conversion (CRITICAL FIX)
        if ($warehouseId) {
            $inventoryItem = DB::table('food_inventory_items')->where('item_id', $realItemId)->first();
            if ($inventoryItem) {
                $stock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                
                if ($stock) {
                    // CRITICAL: Use proper unit conversion for fallback too
                    $itemMaster = DB::table('items')->where('id', $realItemId)->first();
                    if ($itemMaster) {
                    $unit = $item['unit'] ?? null;
                    $qty_input = $item['qty_scan'];
                    $qty_small = 0;
                    $qty_medium = 0;
                    $qty_large = 0;
                        
                    $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                    $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                    $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
                    $smallConv = $itemMaster->small_conversion_qty ?: 1;
                    $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                        
                    if ($unit === $unitSmall) {
                        $qty_small = $qty_input;
                        $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                        $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    } elseif ($unit === $unitMedium) {
                        $qty_medium = $qty_input;
                        $qty_small = $qty_medium * $smallConv;
                        $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    } elseif ($unit === $unitLarge) {
                        $qty_large = $qty_input;
                        $qty_medium = $qty_large * $mediumConv;
                        $qty_small = $qty_medium * $smallConv;
                    } else {
                        $qty_small = $qty_input;
                    }
                        
                        // DEBUG: Log stock validation details for fallback
                        Log::info('Fallback stock validation debug', [
                            'item_id' => $realItemId,
                            'qty_small_needed' => $qty_small,
                            'qty_medium_needed' => $qty_medium,
                            'qty_large_needed' => $qty_large,
                            'stock_small_available' => $stock->qty_small,
                            'stock_medium_available' => $stock->qty_medium,
                            'stock_large_available' => $stock->qty_large,
                            'input_qty' => $qty_input,
                            'input_unit' => $item['unit'] ?? 'null'
                        ]);
                        
                        // Validate stock availability
                    if ($qty_small > $stock->qty_small) {
                        // Get unit names for better error message
                        $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                        $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
                        
                        // Show stock in the unit that user is trying to use
                        $inputUnit = $item['unit'] ?? null;
                        $availableStock = 0;
                        $unitName = '';
                        
                        if ($inputUnit === $unitSmall) {
                            $availableStock = $stock->qty_small;
                            $unitName = $unitSmall;
                        } elseif ($inputUnit === $unitMedium) {
                            $availableStock = $stock->qty_medium;
                            $unitName = $unitMedium;
                        } elseif ($inputUnit === $unitLarge) {
                            $availableStock = $stock->qty_large;
                            $unitName = $unitLarge;
                        } else {
                            $availableStock = $stock->qty_small;
                            $unitName = $unitSmall;
                        }
                        
                        throw new \Exception("Qty melebihi stok yang tersedia. Stok tersedia: {$availableStock} {$unitName}");
                    }
                        
                        // Update stock with proper conversion
                    DB::table('food_inventory_stocks')
                            ->where('inventory_item_id', $inventoryItem->id)
                        ->where('warehouse_id', $warehouseId)
                        ->update([
                            'qty_small' => $stock->qty_small - $qty_small,
                            'qty_medium' => $stock->qty_medium - $qty_medium,
                            'qty_large' => $stock->qty_large - $qty_large,
                            'updated_at' => now(),
                        ]);
                        
                        // Insert inventory card with proper conversion
                    DB::table('food_inventory_cards')->insert([
                                'inventory_item_id' => $inventoryItem->id,
                        'warehouse_id' => $warehouseId,
                        'date' => now()->toDateString(),
                        'reference_type' => 'delivery_order',
                        'reference_id' => $doId,
                        'out_qty_small' => $qty_small,
                        'out_qty_medium' => $qty_medium,
                        'out_qty_large' => $qty_large,
                        'cost_per_small' => $stock->last_cost_small,
                        'cost_per_medium' => $stock->last_cost_medium,
                        'cost_per_large' => $stock->last_cost_large,
                        'value_out' => $qty_small * $stock->last_cost_small,
                        'saldo_qty_small' => $stock->qty_small - $qty_small,
                        'saldo_qty_medium' => $stock->qty_medium - $qty_medium,
                        'saldo_qty_large' => $stock->qty_large - $qty_large,
                        'saldo_value' => ($stock->qty_small - $qty_small) * $stock->last_cost_small,
                                'description' => 'Stock Out - Delivery Order ' . $this->getDONumber($doId) . ' to ' . ($this->getOutletName($doId) ?: 'Outlet') . ' (Fallback)',
                        'created_at' => now(),
                    ]);
                    }
                }
            }
        }
    }

    /**
     * Process barcode to ensure it's a string
     */
    private function processBarcode($barcode)
    {
        if (is_null($barcode)) {
            return null;
        }
        
        if (is_array($barcode)) {
            // If it's an array, take the first element or join them
            if (count($barcode) > 0) {
                return is_array($barcode[0]) ? implode(',', $barcode[0]) : $barcode[0];
            }
            return null;
        }
        
        if (is_string($barcode)) {
            return $barcode;
        }
        
        // Convert to string if it's other type
        return (string) $barcode;
    }

    /**
     * OPTIMIZED: Get item data in batch
     */
    private function getItemDataBatch($itemIds, $isROSupplierGR)
    {
            if ($isROSupplierGR) {
            $data = DB::table('food_good_receive_items as fgri')
                ->join('items as i', 'fgri.item_id', '=', 'i.id')
                ->whereIn('fgri.id', $itemIds)
                ->select('fgri.id', 'fgri.item_id', 'i.*')
                ->get()
                ->keyBy('id');
        } else {
            $data = DB::table('food_packing_list_items as fpli')
                ->join('food_floor_order_items as ffoi', 'fpli.food_floor_order_item_id', '=', 'ffoi.id')
                ->join('items as i', 'ffoi.item_id', '=', 'i.id')
                ->whereIn('fpli.id', $itemIds)
                ->select('fpli.id', 'ffoi.item_id', 'i.*')
                ->get()
                ->keyBy('id');
        }
        
        // Debug: Log the fetched data
        Log::info('Item data batch fetched', [
            'itemIds' => $itemIds,
            'isROSupplierGR' => $isROSupplierGR,
            'fetched_count' => $data->count(),
            'fetched_keys' => $data->keys()->toArray()
        ]);
        
        return $data;
    }

    /**
     * OPTIMIZED: Get stock data in batch
     */
    private function getStockDataBatch($itemData, $warehouseId)
    {
        if (!$warehouseId) return [];
        
        $itemIds = $itemData->pluck('item_id')->unique()->toArray();
        
        $inventoryItems = DB::table('food_inventory_items')
            ->whereIn('item_id', $itemIds)
            ->get()
            ->keyBy('item_id');
        
        $inventoryItemIds = $inventoryItems->pluck('id')->toArray();
        
        $stocks = DB::table('food_inventory_stocks')
            ->whereIn('inventory_item_id', $inventoryItemIds)
            ->where('warehouse_id', $warehouseId)
            ->get()
            ->keyBy('inventory_item_id');
        
        $result = [];
        foreach ($itemData as $data) {
            $inventoryItem = $inventoryItems[$data->item_id] ?? null;
            if ($inventoryItem) {
                $stock = $stocks[$inventoryItem->id] ?? null;
                if ($stock) {
                    $result[$data->item_id] = [
                        'inventory_item_id' => $inventoryItem->id,
                        'qty_small' => (float)$stock->qty_small,
                        'qty_medium' => (float)$stock->qty_medium,
                        'qty_large' => (float)$stock->qty_large,
                        'last_cost_small' => (float)$stock->last_cost_small,
                        'last_cost_medium' => (float)$stock->last_cost_medium,
                        'last_cost_large' => (float)$stock->last_cost_large,
                    ];
                }
            }
        }
        
        return $result;
    }

    /**
     * OPTIMIZED: Get real item ID
     */
    private function getRealItemId($itemId, $isROSupplierGR, $itemData)
    {
        $data = $itemData[$itemId] ?? null;
        if (!$data) {
            // Debug: Log the available data
            Log::error('Item data tidak ditemukan', [
                'itemId' => $itemId,
                'isROSupplierGR' => $isROSupplierGR,
                'available_keys' => $itemData->keys()->toArray(),
                'itemData_count' => $itemData->count()
            ]);
            throw new \Exception('Item data tidak ditemukan untuk ID: ' . $itemId);
        }
        return $data->item_id;
    }

    /**
     * OPTIMIZED: Calculate quantities with proper unit conversion
     */
    private function calculateQuantities($itemId, $qtyInput, $unit, $itemData)
    {
        $item = $itemData->where('item_id', $itemId)->first();
        if (!$item) {
            throw new \Exception('Item tidak ditemukan');
        }
        
        // Get unit names from database
        $unitSmall = DB::table('units')->where('id', $item->small_unit_id)->value('name');
        $unitMedium = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
        $unitLarge = DB::table('units')->where('id', $item->large_unit_id)->value('name');
        
        // Get conversion factors
        $smallConv = $item->small_conversion_qty ?: 1;
        $mediumConv = $item->medium_conversion_qty ?: 1;
        
        $qty_small = 0;
        $qty_medium = 0;
        $qty_large = 0;
        
        // CRITICAL: Proper unit conversion logic (same as original)
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
            // Default to small unit if unit doesn't match
            $qty_small = $qtyInput;
        }
        
        // Log conversion for debugging
        Log::info('Unit conversion calculation', [
            'item_id' => $itemId,
            'input_qty' => $qtyInput,
            'input_unit' => $unit,
            'small_unit' => $unitSmall,
            'medium_unit' => $unitMedium,
            'large_unit' => $unitLarge,
            'small_conv' => $smallConv,
            'medium_conv' => $mediumConv,
            'result_small' => $qty_small,
            'result_medium' => $qty_medium,
            'result_large' => $qty_large
        ]);
        
        return [
            'qty_small' => $qty_small,
            'qty_medium' => $qty_medium,
            'qty_large' => $qty_large
        ];
    }

    /**
     * Get DO number for delivery order
     */
    private function getDONumber($doId)
    {
        try {
            $doNumber = DB::table('delivery_orders')
                ->where('id', $doId)
                ->value('number');
            
            return $doNumber ?: 'DO-' . $doId;
        } catch (\Exception $e) {
            Log::warning('Failed to get DO number', [
                'do_id' => $doId,
                'error' => $e->getMessage()
            ]);
            return 'DO-' . $doId;
        }
    }

    /**
     * Get outlet name for delivery order
     */
    private function getOutletName($doId)
    {
        try {
            $outletName = DB::table('delivery_orders as do')
                ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
                ->leftJoin('food_good_receives as gr', 'do.ro_supplier_gr_id', '=', 'gr.id')
                    ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
                ->leftJoin('food_floor_orders as fo', function($join) {
                    $join->on('pl.food_floor_order_id', '=', 'fo.id')
                         ->orOn('po.source_id', '=', 'fo.id');
                })
                ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                ->where('do.id', $doId)
                ->value('o.nama_outlet');
            
            return $outletName ?: 'Outlet';
        } catch (\Exception $e) {
            Log::warning('Failed to get outlet name for DO', [
                'do_id' => $doId,
                'error' => $e->getMessage()
            ]);
            return 'Outlet';
        }
    }

    /**
     * OPTIMIZED: Get delivery orders with optimized query
     */
    private function getDeliveryOrdersOptimized($search, $dateFrom, $dateTo, $perPage)
    {
        // Use raw SQL for better performance with complex joins
        $query = "
            SELECT 
                do.id,
                do.number,
                do.created_at,
                DATE_FORMAT(do.created_at, '%d/%m/%Y') as created_date,
                DATE_FORMAT(do.created_at, '%H:%i:%s') as created_time,
                do.packing_list_id,
                do.ro_supplier_gr_id,
                u.nama_lengkap as created_by_name,
                COALESCE(pl.packing_number, gr.gr_number) as packing_number,
                fo.order_number as floor_order_number,
                o.nama_outlet,
                wo.name as warehouse_outlet_name,
                CONCAT(COALESCE(w.name, ''), CASE WHEN w.name IS NOT NULL AND wd.name IS NOT NULL THEN ' - ' ELSE '' END, COALESCE(wd.name, '')) as warehouse_info
            FROM delivery_orders do
            LEFT JOIN users u ON do.created_by = u.id
            LEFT JOIN food_packing_lists pl ON do.packing_list_id = pl.id
            LEFT JOIN food_good_receives gr ON do.ro_supplier_gr_id = gr.id
            LEFT JOIN purchase_order_foods po ON gr.po_id = po.id
            LEFT JOIN food_floor_orders fo ON (
                (do.packing_list_id IS NOT NULL AND pl.food_floor_order_id = fo.id) OR
                (do.ro_supplier_gr_id IS NOT NULL AND po.source_id = fo.id)
            )
            LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
            LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
            LEFT JOIN warehouse_division wd ON pl.warehouse_division_id = wd.id
            LEFT JOIN warehouses w ON wd.warehouse_id = w.id
            WHERE 1=1
        ";
        
        $bindings = [];
        
        // Apply search filter
        if (!empty($search)) {
            $query .= " AND (
                COALESCE(pl.packing_number, gr.gr_number) LIKE ? OR
                fo.order_number LIKE ? OR
                u.nama_lengkap LIKE ? OR
                o.nama_outlet LIKE ? OR
                wo.name LIKE ? OR
                do.number LIKE ?
            )";
            $searchTerm = '%' . $search . '%';
            $bindings = array_merge($bindings, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        // Apply date filters
        if (!empty($dateFrom)) {
            $query .= " AND DATE(do.created_at) >= ?";
            $bindings[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $query .= " AND DATE(do.created_at) <= ?";
            $bindings[] = $dateTo;
        }
        
        $query .= " ORDER BY do.created_at DESC";
        
        // Get total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM ($query) as count_query";
        $total = DB::select($countQuery, $bindings)[0]->total;
        
        // Apply pagination
        $offset = (request('page', 1) - 1) * $perPage;
        $query .= " LIMIT $perPage OFFSET $offset";
        
        $results = DB::select($query, $bindings);
        
        // Convert to pagination format
        $currentPage = request('page', 1);
        $lastPage = ceil($total / $perPage);
        
        return [
            'data' => $results,
            'current_page' => (int)$currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : null,
            'to' => min($currentPage * $perPage, $total),
            'links' => []
        ];
    }

    public function getPackingListItems($id)
    {
        // Log removed for performance
        
        // Cek apakah ini adalah RO Supplier GR atau Packing List biasa
        if (strpos($id, 'gr_') === 0) {
            // Ini adalah RO Supplier GR
            $grId = substr($id, 3); // Hapus prefix 'gr_'
            // Log removed for performance
            $result = $this->getROSupplierGRItems($grId);
            return $result;
        } else {
            // Ini adalah Packing List biasa
            // Log removed for performance
            $result = $this->getPackingListItemsRegular($id);
            return $result;
        }
    }

    private function getROSupplierGRItems($grId)
    {
        // Ambil items dari RO Supplier GR
        $items = DB::table('food_good_receive_items as fgri')
            ->join('items', 'fgri.item_id', '=', 'items.id')
            ->join('units as u', 'fgri.unit_id', '=', 'u.id')
            ->select(
                'fgri.id',
                'fgri.qty_received as qty',
                'u.name as unit',
                'items.name',
                'items.id as item_id'
            )
            ->where('fgri.good_receive_id', $grId)
            ->where('fgri.qty_received', '>', 0)
            ->orderBy('items.name')
            ->get();

        // Ambil semua barcode untuk setiap item
        $itemIds = $items->pluck('item_id')->unique()->values();
        $barcodeMap = DB::table('item_barcodes')
            ->whereIn('item_id', $itemIds)
            ->select('item_id', 'barcode')
            ->get()
            ->groupBy('item_id')
            ->map(function($rows) {
                return $rows->pluck('barcode')->values();
            });

        // Ambil stock untuk setiap item dengan semua unit (warehouse_id = 1 untuk RO Supplier)
        $warehouse_id = 1;
        $itemStocks = [];
        $itemUnits = [];
        if ($warehouse_id) {
            $inventoryItems = DB::table('food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->get()->keyBy('item_id');
            $inventoryItemIds = $inventoryItems->pluck('id')->unique()->values();
            $stocks = DB::table('food_inventory_stocks')
                ->whereIn('inventory_item_id', $inventoryItemIds)
                ->where('warehouse_id', $warehouse_id)
                ->get()->keyBy('inventory_item_id');
            
            foreach ($items as $item) {
                $inv = $inventoryItems[$item->item_id] ?? null;
                $stock = $inv ? $stocks[$inv->id] ?? null : null;
                
                if ($stock) {
                    // Ambil nama unit dari tabel units
                    $unitNameSmall = DB::table('units')->where('id', $inv->small_unit_id)->value('name');
                    $unitNameMedium = DB::table('units')->where('id', $inv->medium_unit_id)->value('name');
                    $unitNameLarge = DB::table('units')->where('id', $inv->large_unit_id)->value('name');
                    
                    // Simpan semua unit dan stock
                    $itemUnits[$item->id] = [
                        'small_unit' => $unitNameSmall,
                        'medium_unit' => $unitNameMedium,
                        'large_unit' => $unitNameLarge
                    ];
                    
                    $itemStocks[$item->id] = [
                        'small' => (float)$stock->qty_small,
                        'medium' => (float)$stock->qty_medium,
                        'large' => (float)$stock->qty_large
                    ];
                } else {
                    $itemUnits[$item->id] = [
                        'small_unit' => null,
                        'medium_unit' => null,
                        'large_unit' => null
                    ];
                    $itemStocks[$item->id] = [
                        'small' => 0,
                        'medium' => 0,
                        'large' => 0
                    ];
                }
            }
        }

        // Ambil conversion factors untuk setiap item
        $itemConversions = [];
        foreach ($items as $item) {
            $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
            if ($itemMaster) {
                $itemConversions[$item->id] = [
                    'small_conversion_qty' => $itemMaster->small_conversion_qty ?? 1,
                    'medium_conversion_qty' => $itemMaster->medium_conversion_qty ?? 1
                ];
            } else {
                $itemConversions[$item->id] = [
                    'small_conversion_qty' => 1,
                    'medium_conversion_qty' => 1
                ];
            }
        }

        // Tambahkan barcode, stock, units, dan conversion factors ke setiap item
        $items = $items->map(function($item) use ($barcodeMap, $itemStocks, $itemUnits, $itemConversions) {
            $item->barcodes = $barcodeMap->get($item->item_id, []);
            $item->stock = $itemStocks[$item->id] ?? ['small' => 0, 'medium' => 0, 'large' => 0];
            $item->units = $itemUnits[$item->id] ?? ['small_unit' => null, 'medium_unit' => null, 'large_unit' => null];
            $item->small_conversion_qty = $itemConversions[$item->id]['small_conversion_qty'];
            $item->medium_conversion_qty = $itemConversions[$item->id]['medium_conversion_qty'];
            return $item;
        });

        return response()->json(['items' => $items]);
    }

    private function getPackingListItemsRegular($id)
    {
        // Log removed for performance
        
        // Ambil packing list untuk dapat warehouse_division_id
        $packingList = DB::table('food_packing_lists')->where('id', $id)->first();
        if (!$packingList) {
            return response()->json(['items' => []]);
        }
        
        $warehouse_division_id = $packingList->warehouse_division_id ?? null;
        $warehouse_id = null;
        if ($warehouse_division_id) {
            $warehouse_id = DB::table('warehouse_division')->where('id', $warehouse_division_id)->value('warehouse_id');
        }
        
        // Ambil items dari food_packing_list_items (bukan dari GR)
        $items = DB::table('food_packing_list_items as fpli')
            ->join('food_floor_order_items as ffoi', 'fpli.food_floor_order_item_id', '=', 'ffoi.id')
            ->join('items', 'ffoi.item_id', '=', 'items.id')
            ->select('fpli.id', 'fpli.qty', 'fpli.unit', 'items.name', 'items.id as item_id')
            ->where('fpli.packing_list_id', $id)
            ->where('fpli.qty', '>', 0)
            ->orderBy('items.name')
            ->get();
        
        // Log removed for performance
        
        if ($items->count() == 0) {
            return response()->json(['items' => []]);
        }
        // Ambil semua barcode untuk setiap item
        $itemIds = $items->pluck('item_id')->unique()->values();
        $barcodeMap = DB::table('item_barcodes')
            ->whereIn('item_id', $itemIds)
            ->select('item_id', 'barcode')
            ->get()
            ->groupBy('item_id')
            ->map(function($rows) {
                return $rows->pluck('barcode')->values();
            });
        // Ambil stock untuk setiap item dengan semua unit (small, medium, large)
        $itemStocks = [];
        $itemUnits = [];
        if ($warehouse_id) {
            $inventoryItems = DB::table('food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->get()->keyBy('item_id');
            $inventoryItemIds = $inventoryItems->pluck('id')->unique()->values();
            $stocks = DB::table('food_inventory_stocks')
                ->whereIn('inventory_item_id', $inventoryItemIds)
                ->where('warehouse_id', $warehouse_id)
                ->get()->keyBy('inventory_item_id');
            
            foreach ($items as $item) {
                $inv = $inventoryItems[$item->item_id] ?? null;
                $stock = $inv ? $stocks[$inv->id] ?? null : null;
                
                if ($stock) {
                    // Ambil nama unit dari tabel units
                    $unitNameSmall = DB::table('units')->where('id', $inv->small_unit_id)->value('name');
                    $unitNameMedium = DB::table('units')->where('id', $inv->medium_unit_id)->value('name');
                    $unitNameLarge = DB::table('units')->where('id', $inv->large_unit_id)->value('name');
                    
                    // Simpan semua unit dan stock
                    $itemUnits[$item->id] = [
                        'small_unit' => $unitNameSmall,
                        'medium_unit' => $unitNameMedium,
                        'large_unit' => $unitNameLarge
                    ];
                    
                    $itemStocks[$item->id] = [
                        'small' => (float)$stock->qty_small,
                        'medium' => (float)$stock->qty_medium,
                        'large' => (float)$stock->qty_large
                    ];
                } else {
                    $itemUnits[$item->id] = [
                        'small_unit' => null,
                        'medium_unit' => null,
                        'large_unit' => null
                    ];
                    $itemStocks[$item->id] = [
                        'small' => 0,
                        'medium' => 0,
                        'large' => 0
                    ];
                }
            }
        }
        
        // Ambil conversion factors untuk setiap item
        $itemConversions = [];
        foreach ($items as $item) {
            $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
            if ($itemMaster) {
                $itemConversions[$item->id] = [
                    'small_conversion_qty' => $itemMaster->small_conversion_qty ?? 1,
                    'medium_conversion_qty' => $itemMaster->medium_conversion_qty ?? 1
                ];
            } else {
                $itemConversions[$item->id] = [
                    'small_conversion_qty' => 1,
                    'medium_conversion_qty' => 1
                ];
            }
        }

        $items = $items->map(function($item) use ($barcodeMap, $itemStocks, $itemUnits, $itemConversions) {
            $item->barcodes = $barcodeMap[$item->item_id] ?? collect();
            $item->stock = $itemStocks[$item->id] ?? ['small' => 0, 'medium' => 0, 'large' => 0];
            $item->units = $itemUnits[$item->id] ?? ['small_unit' => null, 'medium_unit' => null, 'large_unit' => null];
            $item->small_conversion_qty = $itemConversions[$item->id]['small_conversion_qty'];
            $item->medium_conversion_qty = $itemConversions[$item->id]['medium_conversion_qty'];
            return $item;
        });
        
        // Log removed for performance
        return response()->json(['items' => $items]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $order = DB::table('delivery_orders')->where('id', $id)->first();
            if (!$order) {
                return redirect()->route('delivery-order.index')->with('error', 'Delivery Order tidak ditemukan');
            }
            $items = DB::table('delivery_order_items')->where('delivery_order_id', $id)->get();
            $packingList = DB::table('food_packing_lists')->where('id', $order->packing_list_id)->first();
            $warehouseDivisionId = $packingList->warehouse_division_id ?? null;
            $warehouseId = null;
            if ($warehouseDivisionId) {
                $warehouseId = DB::table('warehouse_division')->where('id', $warehouseDivisionId)->value('warehouse_id');
            }
            // Rollback inventory
            foreach ($items as $item) {
                $realItemId = $item->item_id;
                $itemMaster = DB::table('items')->where('id', $realItemId)->first();
                if (!$itemMaster) continue;
                $inventoryItem = DB::table('food_inventory_items')->where('item_id', $realItemId)->first();
                if (!$inventoryItem) continue;
                $inventory_item_id = $inventoryItem->id;
                $unit = $item->unit ?? null;
                $qty_input = $item->qty_scan;
                $qty_small = 0;
                $qty_medium = 0;
                $qty_large = 0;
                $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                if ($unit === $unitSmall) {
                    $qty_small = $qty_input;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                } elseif ($unit === $unitMedium) {
                    $qty_medium = $qty_input;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                } elseif ($unit === $unitLarge) {
                    $qty_large = $qty_input;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } else {
                    $qty_small = $qty_input;
                }
                $stock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                if ($stock) {
                    DB::table('food_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => $stock->qty_small + $qty_small,
                            'qty_medium' => $stock->qty_medium + $qty_medium,
                            'qty_large' => $stock->qty_large + $qty_large,
                            'updated_at' => now(),
                        ]);
                } else {
                    \Log::warning('Rollback stok gagal: stok tidak ditemukan', [
                        'inventory_item_id' => $inventory_item_id,
                        'warehouse_id' => $warehouseId,
                        'item_id' => $realItemId
                    ]);
                }
                // Hapus kartu stok OUT
                DB::table('food_inventory_cards')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $warehouseId)
                    ->where('reference_type', 'delivery_order')
                    ->where('reference_id', $id)
                    ->delete();
            }
            // Hapus delivery_order_items
            DB::table('delivery_order_items')->where('delivery_order_id', $id)->delete();
            // Hapus delivery_order
            DB::table('delivery_orders')->where('id', $id)->delete();
            
            // Check and update floor order status after DO deletion
            if ($order->packing_list_id) {
                $packingList = DB::table('food_packing_lists')->where('id', $order->packing_list_id)->first();
                if ($packingList && $packingList->food_floor_order_id) {
                    $this->checkAndUpdateFloorOrderStatus($packingList->food_floor_order_id, 'Regular Packing List');
                }
            } elseif ($order->ro_supplier_gr_id) {
                // Untuk RO Supplier GR, ambil floor order ID dari purchase order
                $gr = DB::table('food_good_receives')->where('id', $order->ro_supplier_gr_id)->first();
                if ($gr) {
                    $po = DB::table('purchase_order_foods')->where('id', $gr->po_id)->first();
                    if ($po && $po->source_id) {
                        $this->checkAndUpdateFloorOrderStatus($po->source_id, 'RO Supplier GR');
                    }
                }
            }
            // Log activity
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'delivery_order',
                'description' => 'Menghapus delivery order #' . $id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($order),
                'new_data' => null,
                'created_at' => now(),
            ]);
            DB::commit();
            return redirect()->route('delivery-order.index')->with('success', 'Delivery Order berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function strukData($id)
    {
        $order = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as ufo', 'fo.user_id', '=', 'ufo.id')
            ->leftJoin('users as kasir', 'do.created_by', '=', 'kasir.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->select(
                'do.number as orderNumber',
                'do.created_at as date',
                'o.nama_outlet as outlet',
                'kasir.nama_lengkap as kasirName',
                'wd.name as divisionName',
                'w.name as warehouseName',
                'fo.order_number as roNumber',
                'fo.tanggal as roDate',
                'ufo.nama_lengkap as roCreatorName'
            )
            ->where('do.id', $id)
            ->first();
        if (!$order) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        $items = DB::table('delivery_order_items as doi')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'doi.unit', '=', 'u.name')
            ->select(
                'doi.id',
                'i.name',
                'doi.qty_scan',
                'doi.unit',
                'u.code as unit_code'
            )
            ->where('doi.delivery_order_id', $id)
            ->get();
        return response()->json([
            'orderNumber' => $order->orderNumber,
            'date' => $order->date ? date('d/m/Y', strtotime($order->date)) : '',
            'outlet' => $order->outlet,
            'kasirName' => $order->kasirName,
            'divisionName' => $order->divisionName,
            'warehouseName' => $order->warehouseName,
            'roNumber' => $order->roNumber,
            'roDate' => $order->roDate ? date('d/m/Y', strtotime($order->roDate)) : '',
            'roCreatorName' => $order->roCreatorName,
            'items' => $items
        ]);
    }

    public function export(Request $request)
    {
        $query = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'do.id',
                'do.number',
                'do.created_at',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'u.nama_lengkap as created_by_name',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            );

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $query->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('do.created_at', '<=', $request->dateTo);
        }

        $orders = $query->orderByDesc('do.created_at')->get();

        // Get items for each order
        $orderItems = [];
        foreach ($orders as $order) {
            $items = DB::table('delivery_order_items as doi')
                ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
                ->select(
                    'doi.id',
                    'i.name as item_name',
                    'doi.qty_packing_list',
                    'doi.qty_scan',
                    'doi.unit'
                )
                ->where('doi.delivery_order_id', $order->id)
                ->get();
            $orderItems[$order->id] = $items;
        }

        // Create Excel file
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers for delivery orders
        $headers = [
            'No',
            'No DO',
            'Tanggal',
            'Outlet',
            'Warehouse Outlet',
            'Packing List',
            'Floor Order',
            'User'
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        // Style headers
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3B82F6'],
            ],
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // Add delivery order data
        $currentRow = 2;
        foreach ($orders as $index => $order) {
            $sheet->setCellValueByColumnAndRow(1, $currentRow, $index + 1);
            $sheet->setCellValueByColumnAndRow(2, $currentRow, $order->number ?? '-');
            $sheet->setCellValueByColumnAndRow(3, $currentRow, $order->created_at ? date('d/m/Y', strtotime($order->created_at)) : '-');
            $sheet->setCellValueByColumnAndRow(4, $currentRow, $order->nama_outlet ?? '-');
            $sheet->setCellValueByColumnAndRow(5, $currentRow, $order->warehouse_outlet_name ?? '-');
            $sheet->setCellValueByColumnAndRow(6, $currentRow, $order->packing_number ?? '-');
            $sheet->setCellValueByColumnAndRow(7, $currentRow, $order->floor_order_number ?? '-');
            $sheet->setCellValueByColumnAndRow(8, $currentRow, $order->created_by_name ?? '-');
            
            $currentRow++;
            
            // Add items for this order
            if (isset($orderItems[$order->id]) && $orderItems[$order->id]->count() > 0) {
                // Add item headers
                $itemHeaders = [
                    '',
                    'Item Name',
                    'Qty Packing List',
                    'Qty Scan',
                    'Unit'
                ];
                
                foreach ($itemHeaders as $col => $header) {
                    $sheet->setCellValueByColumnAndRow($col + 1, $currentRow, $header);
                }
                
                // Style item headers
                $itemHeaderStyle = [
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '10B981'],
                    ],
                ];
                $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->applyFromArray($itemHeaderStyle);
                $currentRow++;
                
                // Add item data
                foreach ($orderItems[$order->id] as $item) {
                    $sheet->setCellValueByColumnAndRow(1, $currentRow, '');
                    $sheet->setCellValueByColumnAndRow(2, $currentRow, $item->item_name ?? '-');
                    $sheet->setCellValueByColumnAndRow(3, $currentRow, $item->qty_packing_list ?? 0);
                    $sheet->setCellValueByColumnAndRow(4, $currentRow, $item->qty_scan ?? 0);
                    $sheet->setCellValueByColumnAndRow(5, $currentRow, $item->unit ?? '-');
                    $currentRow++;
                }
                
                // Add empty row after items
                $currentRow++;
            }
        }

        // Auto size columns
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Create writer
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Set headers for download
        $filename = 'delivery-order-' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // Output file
        $writer->save('php://output');
        exit;
    }

    public function exportSummary(Request $request)
    {
        // Get delivery orders with filters
        $query = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'do.id',
                'do.number',
                'do.created_at',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'u.nama_lengkap as created_by_name',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            );

        // Apply filters
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $query->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('do.created_at', '<=', $request->dateTo);
        }

        $orders = $query->orderByDesc('do.created_at')->get();

        // Get summary data - sum qty per item across all DOs
        $summaryData = DB::table('delivery_order_items as doi')
            ->leftJoin('delivery_orders as do', 'doi.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('units as u_small', 'i.small_unit_id', '=', 'u_small.id')
            ->leftJoin('units as u_medium', 'i.medium_unit_id', '=', 'u_medium.id')
            ->leftJoin('units as u_large', 'i.large_unit_id', '=', 'u_large.id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'i.sku as item_sku',
                'c.name as category_name',
                'sc.name as sub_category_name',
                'doi.unit',
                'u_small.name as small_unit_name',
                'u_medium.name as medium_unit_name',
                'u_large.name as large_unit_name',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                DB::raw('SUM(doi.qty_packing_list) as total_qty_packing_list'),
                DB::raw('SUM(doi.qty_scan) as total_qty_scan'),
                DB::raw('COUNT(DISTINCT do.id) as total_do_count'),
                DB::raw('GROUP_CONCAT(DISTINCT o.nama_outlet ORDER BY o.nama_outlet SEPARATOR ", ") as outlets'),
                DB::raw('GROUP_CONCAT(DISTINCT wo.name ORDER BY wo.name SEPARATOR ", ") as warehouse_outlets')
            )
            ->groupBy('i.id', 'i.name', 'i.sku', 'c.name', 'sc.name', 'doi.unit', 'u_small.name', 'u_medium.name', 'u_large.name', 'i.small_conversion_qty', 'i.medium_conversion_qty');

        // Apply same filters to summary query
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $summaryData->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $summaryData->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $summaryData->whereDate('do.created_at', '<=', $request->dateTo);
        }

        $summaryResults = $summaryData->orderBy('i.name')->get();

        // Return Excel export using the Responsable interface
        return (new \App\Exports\DeliveryOrderSummaryExport($summaryResults))->toResponse($request);
    }

    public function exportDetail(Request $request)
    {
        try {
            // Increase memory limit and execution time for large exports
            ini_set('memory_limit', '512M');
            set_time_limit(300); // 5 minutes
            
            \Log::info('DELIVERY_ORDER_EXPORT_DETAIL: Starting export', [
                'request_params' => $request->all(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ]);
            
            // Get delivery orders with filters
            $query = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'do.id',
                'do.number',
                'do.created_at',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'u.nama_lengkap as created_by_name',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            );

        // Apply filters
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $query->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('do.created_at', '<=', $request->dateTo);
        }

        $orders = $query->orderByDesc('do.created_at')->get();

        // Get detailed items data per outlet & warehouse per date
        $detailData = DB::table('delivery_order_items as doi')
            ->leftJoin('delivery_orders as do', 'doi.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->select(
                'do.number as do_number',
                'do.created_at as do_date',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as created_by_name',
                'i.name as item_name',
                'i.sku as item_sku',
                'c.name as category_name',
                'sc.name as sub_category_name',
                'doi.qty_packing_list',
                'doi.qty_scan',
                'doi.unit',
                DB::raw('DATE(do.created_at) as delivery_date')
            )
            ->orderBy('do.created_at', 'desc')
            ->orderBy('o.nama_outlet')
            ->orderBy('wo.name')
            ->orderBy('i.name');

        // Apply same filters to detail query
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $detailData->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $detailData->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $detailData->whereDate('do.created_at', '<=', $request->dateTo);
        }

        // Get data with better error handling for server environment
        try {
            $detailResults = $detailData->get();
            
            // If no data found, log and return empty collection
            if ($detailResults->isEmpty()) {
                \Log::warning('DELIVERY_ORDER_EXPORT_DETAIL: No data found', [
                    'query_sql' => $detailData->toSql(),
                    'query_bindings' => $detailData->getBindings()
                ]);
                $detailResults = collect();
            }
        } catch (\Exception $e) {
            \Log::error('DELIVERY_ORDER_EXPORT_DETAIL: Error getting data', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            $detailResults = collect();
        }

        \Log::info('DELIVERY_ORDER_EXPORT_DETAIL: Data retrieved', [
            'orders_count' => $orders->count(),
            'detail_results_count' => $detailResults->count(),
            'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
            'first_record' => $detailResults->first(),
            'sample_data' => $detailResults->take(3)->toArray()
        ]);

        // Check if we have data before creating export
        if ($detailResults->isEmpty()) {
            \Log::warning('DELIVERY_ORDER_EXPORT_DETAIL: No data to export');
            return response()->json([
                'error' => 'No data found to export',
                'message' => 'Tidak ada data yang ditemukan untuk diekspor'
            ], 404);
        }

        // For server with limited memory, use streaming export
        try {
            return (new \App\Exports\DeliveryOrderDetailExport($detailResults))->toResponse($request);
        } catch (\Exception $exportError) {
            \Log::error('DELIVERY_ORDER_EXPORT_DETAIL: Export failed, trying alternative method', [
                'export_error' => $exportError->getMessage(),
                'data_count' => $detailResults->count()
            ]);
            
            // Fallback: return CSV instead of Excel for server compatibility
            return $this->exportAsCsv($detailResults);
        }
        
        } catch (\Exception $e) {
            \Log::error('DELIVERY_ORDER_EXPORT_DETAIL: Error occurred', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
                'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB',
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ]);
            
            // Return more specific error based on exception type
            if (strpos($e->getMessage(), 'memory') !== false) {
                return response()->json([
                    'error' => 'Export failed: Insufficient memory. Please contact administrator.',
                    'details' => 'Memory limit exceeded during export process.'
                ], 500);
            } elseif (strpos($e->getMessage(), 'timeout') !== false || strpos($e->getMessage(), 'time') !== false) {
                return response()->json([
                    'error' => 'Export failed: Request timeout. Data might be too large.',
                    'details' => 'Export process exceeded time limit.'
                ], 500);
            } else {
                return response()->json([
                    'error' => 'Export failed: ' . $e->getMessage(),
                    'details' => 'Please contact administrator if this error persists.'
                ], 500);
            }
        }
    }

    /**
     * Export data as CSV (fallback for server compatibility)
     */
    private function exportAsCsv($data)
    {
        $filename = 'delivery_order_detail_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Add headers
            fputcsv($file, [
                'No',
                'Delivery Date',
                'DO Number',
                'Packing List',
                'Floor Order',
                'Outlet',
                'Warehouse Outlet',
                'Created By',
                'Item Name',
                'SKU',
                'Category',
                'Sub Category',
                'Qty Packing List',
                'Qty Scan',
                'Unit'
            ]);
            
            // Add data
            $no = 0;
            foreach ($data as $row) {
                $no++;
                fputcsv($file, [
                    $no,
                    $row->delivery_date ? date('d/m/Y', strtotime($row->delivery_date)) : '-',
                    $row->do_number ?? '-',
                    $row->packing_number ?? '-',
                    $row->floor_order_number ?? '-',
                    $row->nama_outlet ?? '-',
                    $row->warehouse_outlet_name ?? '-',
                    $row->created_by_name ?? '-',
                    $row->item_name ?? '-',
                    $row->item_sku ?? '-',
                    $row->category_name ?? '-',
                    $row->sub_category_name ?? '-',
                    number_format($row->qty_packing_list ?? 0, 2),
                    number_format($row->qty_scan ?? 0, 2),
                    $row->unit ?? '-'
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Check and update floor order status to delivered only if all warehouse divisions have been created packing list
     * AND all packing lists have status 'done' or 'delivered'
     */
    private function checkAndUpdateFloorOrderStatus($floorOrderId, $sourceType)
    {
        Log::info('Checking floor order status', [
            'floor_order_id' => $floorOrderId,
            'source_type' => $sourceType
        ]);

        if ($sourceType === 'RO Supplier GR') {
            // Untuk RO Supplier GR, langsung update status ke delivered karena tidak ada packing list
            Log::info('RO Supplier GR detected, updating status to delivered', [
                'floor_order_id' => $floorOrderId
            ]);
            
            DB::table('food_floor_orders')
                ->where('id', $floorOrderId)
                ->update([
                    'status' => 'delivered',
                    'updated_at' => now()
                ]);
            
            Log::info('Updated RO Supplier GR floor order status to delivered', [
                'floor_order_id' => $floorOrderId
            ]);
            return;
        }

        // Get floor order details to check warehouse divisions
        $floorOrder = DB::table('food_floor_orders')
            ->where('id', $floorOrderId)
            ->first();

        if (!$floorOrder) {
            Log::info('Floor order not found', ['floor_order_id' => $floorOrderId]);
            return;
        }

        // Get all warehouse divisions that should have packing lists for this RO
        // Ini bisa dari item-item di floor order atau dari konfigurasi warehouse
        $warehouseDivisions = DB::table('food_floor_order_items as foi')
            ->join('items as i', 'foi.item_id', '=', 'i.id')
            ->where('foi.floor_order_id', $floorOrderId)
            ->select('foi.warehouse_division_id')
            ->distinct()
            ->pluck('warehouse_division_id')
            ->filter() // Remove null values
            ->toArray();

        Log::info('Warehouse divisions for floor order', [
            'floor_order_id' => $floorOrderId,
            'warehouse_divisions' => $warehouseDivisions
        ]);

        // Jika tidak ada warehouse division, berarti ini floor order yang belum dibuat packing list
        if (empty($warehouseDivisions)) {
            Log::info('No warehouse divisions found for floor order, keeping current status', [
                'floor_order_id' => $floorOrderId
            ]);
            return;
        }

        // Get all packing lists for this floor order
        $allPackingLists = DB::table('food_packing_lists')
            ->where('food_floor_order_id', $floorOrderId)
            ->get();

        Log::info('All packing lists for floor order', [
            'floor_order_id' => $floorOrderId,
            'total_packing_lists' => $allPackingLists->count(),
            'packing_lists' => $allPackingLists->pluck('id')->toArray()
        ]);

        // Cek apakah semua warehouse division sudah dibuat packing list
        $packingListWarehouseDivisions = $allPackingLists->pluck('warehouse_division_id')->toArray();
        $missingWarehouseDivisions = array_diff($warehouseDivisions, $packingListWarehouseDivisions);

        if (!empty($missingWarehouseDivisions)) {
            Log::info('Not all warehouse divisions have packing lists yet, keeping floor order status as packing', [
                'floor_order_id' => $floorOrderId,
                'required_warehouse_divisions' => $warehouseDivisions,
                'existing_warehouse_divisions' => $packingListWarehouseDivisions,
                'missing_warehouse_divisions' => $missingWarehouseDivisions
            ]);
            return;
        }

        // Get packing list IDs that have been created DO
        $packingListIds = $allPackingLists->pluck('id')->toArray();
        $packingListsWithDO = DB::table('delivery_orders')
            ->whereIn('packing_list_id', $packingListIds)
            ->pluck('packing_list_id')
            ->toArray();

        Log::info('Packing lists with DO', [
            'floor_order_id' => $floorOrderId,
            'total_packing_lists' => count($packingListIds),
            'packing_lists_with_do' => count($packingListsWithDO),
            'packing_lists_with_do_ids' => $packingListsWithDO
        ]);

        // Check if all packing lists have been created DO
        if (count($packingListsWithDO) === count($packingListIds)) {
            // TAMBAHAN VALIDASI: Cek apakah semua packing list sudah selesai (status 'done' atau 'delivered')
            $completedPackingLists = DB::table('food_packing_lists')
                ->whereIn('id', $packingListIds)
                ->whereIn('status', ['done', 'delivered'])
                ->pluck('id')
                ->toArray();

            Log::info('Completed packing lists check', [
                'floor_order_id' => $floorOrderId,
                'total_packing_lists' => count($packingListIds),
                'completed_packing_lists' => count($completedPackingLists),
                'completed_packing_lists_ids' => $completedPackingLists
            ]);

            // Hanya update ke delivered jika semua packing list sudah selesai
            if (count($completedPackingLists) === count($packingListIds)) {
                Log::info('All warehouse divisions have packing lists, all packing lists have DO AND are completed, updating floor order status to delivered', [
                    'floor_order_id' => $floorOrderId,
                    'source_type' => $sourceType
                ]);
                
                // Update status to delivered
                DB::table('food_floor_orders')
                    ->where('id', $floorOrderId)
                    ->update([
                        'status' => 'delivered',
                        'updated_at' => now()
                    ]);
                
                Log::info('Updated floor order status to delivered', [
                    'floor_order_id' => $floorOrderId,
                    'source_type' => $sourceType
                ]);
            } else {
                Log::info('All packing lists have DO but not all are completed yet, keeping floor order status as packing', [
                    'floor_order_id' => $floorOrderId,
                    'source_type' => $sourceType,
                    'incomplete_packing_lists' => array_diff($packingListIds, $completedPackingLists)
                ]);
            }
        } else {
            Log::info('Not all packing lists have DO yet, keeping floor order status as packing', [
                'floor_order_id' => $floorOrderId,
                'source_type' => $sourceType,
                'packing_lists_without_do' => array_diff($packingListIds, $packingListsWithDO)
            ]);
        }
    }
} 