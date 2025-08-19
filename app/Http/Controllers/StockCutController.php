<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\StockCutLog;

class StockCutController extends Controller
{
    /**
     * Potong stock berdasarkan order_items yang belum dipotong stock (stock_cut = 0)
     * - Kalkulasi kebutuhan bahan baku dari item_bom
     * - Cek stok di outlet_food_inventory_stocks (per outlet & warehouse)
     * - Jika stok kurang, tampilkan list kekurangan
     * - Jika stok cukup, update stok, catat di outlet_food_inventory_cards, update flag stock_cut
     */
    public function potongStockOrderItems(Request $request)
    {
        $tanggal = $request->input('tanggal');
        $id_outlet = $request->input('id_outlet');
        $type_filter = $request->input('type'); // Filter berdasarkan type
        
        if (!$tanggal || !$id_outlet) {
            return response()->json(['status' => 'error', 'message' => 'Tanggal dan id_outlet wajib diisi'], 422);
        }

        // Ambil qr_code dari tbl_data_outlet
        $qr_code = DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('qr_code');
        
        if (!$qr_code) {
            return response()->json(['status' => 'error', 'message' => 'QR Code outlet tidak ditemukan'], 422);
        }

        // 1. Ambil order_items yang belum dipotong stock
        $query = DB::table('order_items')
            ->join('items', 'order_items.item_id', '=', 'items.id')
            ->whereDate('order_items.created_at', $tanggal)
            ->where('order_items.kode_outlet', $qr_code)
            ->where('order_items.stock_cut', 0)
            ->select('order_items.*', 'items.type');
        
        // Filter berdasarkan type jika ada
        if ($type_filter) {
            if ($type_filter === 'food') {
                $query->whereIn('items.type', ['Food Asian', 'Food Western', 'Food']);
            } elseif ($type_filter === 'beverages') {
                $query->where('items.type', 'Beverages');
            }
        }
        
        $orderItems = $query->get();

        if ($orderItems->isEmpty()) {
            return response()->json(['status' => 'success', 'message' => 'Tidak ada order_items yang perlu dipotong stock']);
        }

        // 2. Mapping kebutuhan bahan baku & warehouse
        $kebutuhanBahan = [];
        $warehouseMap = [];
        
        // Ambil warehouse yang tersedia untuk outlet ini
        $kitchenWarehouse = DB::table('warehouse_outlets')
            ->where('outlet_id', $id_outlet)
            ->where('name', 'Kitchen')
            ->where('status', 'active')
            ->first();
            
        $barWarehouse = DB::table('warehouse_outlets')
            ->where('outlet_id', $id_outlet)
            ->where('name', 'Bar')
            ->where('status', 'active')
            ->first();
        
        foreach ($orderItems as $oi) {
            // Tentukan warehouse berdasarkan type item
            $warehouse = null;
            if (in_array($oi->type, ['Food Asian', 'Food Western', 'Food'])) {
                $warehouse = $kitchenWarehouse;
            } elseif ($oi->type == 'Beverages') {
                $warehouse = $barWarehouse;
            } else {
                continue; // skip jika type tidak dikenali
            }
            
            if (!$warehouse) {
                // Log warning jika warehouse tidak ditemukan
                \Log::warning("Warehouse tidak ditemukan untuk type: {$oi->type}, outlet: {$id_outlet}");
                continue;
            }
            
            $warehouseMap[$oi->item_id] = $warehouse->id;
            
            // Ambil BOM
            $boms = DB::table('item_bom')->where('item_id', $oi->item_id)->get();
            foreach ($boms as $bom) {
                $key = $bom->material_item_id . '-' . $warehouse->id;
                $kebutuhanBahan[$key] = ($kebutuhanBahan[$key] ?? 0) + ($bom->qty * $oi->qty);
            }
            
            // Ambil BOM dari modifier jika ada
            if ($oi->modifiers) {
                $modifiers = json_decode($oi->modifiers, true);
                if (is_array($modifiers)) {
                    foreach ($modifiers as $group) {
                        if (is_array($group)) {
                            foreach ($group as $modifierName => $modifierQty) {
                                // Cari modifier option berdasarkan nama
                                $modifierOption = DB::table('modifier_options')
                                    ->where('name', $modifierName)
                                    ->whereNotNull('modifier_bom_json')
                                    ->where('modifier_bom_json', '!=', '')
                                    ->where('modifier_bom_json', '!=', '[]')
                                    ->first();
                                
                                // Skip modifier dengan modifier_id = 1
                                if ($modifierOption && $modifierOption->modifier_id == 1) {
                                    continue;
                                }
                                
                                if ($modifierOption && $modifierOption->modifier_bom_json) {
                                    $modifierBom = json_decode($modifierOption->modifier_bom_json, true);
                                    if (is_array($modifierBom)) {
                                        foreach ($modifierBom as $bomItem) {
                                            if (isset($bomItem['item_id']) && isset($bomItem['qty'])) {
                                                $key = $bomItem['item_id'] . '-' . $warehouse->id;
                                                $kebutuhanBahan[$key] = ($kebutuhanBahan[$key] ?? 0) + ($bomItem['qty'] * $modifierQty * $oi->qty);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // 3. Cek stok
        $kurang = [];
        foreach ($kebutuhanBahan as $key => $qty) {
            [$item_id, $warehouse_id] = explode('-', $key);
            $stock = DB::table('outlet_food_inventory_items')
                ->join('outlet_food_inventory_stocks', 'outlet_food_inventory_items.id', '=', 'outlet_food_inventory_stocks.inventory_item_id')
                ->where('outlet_food_inventory_items.item_id', $item_id)
                ->where('outlet_food_inventory_stocks.id_outlet', $id_outlet)
                ->where('outlet_food_inventory_stocks.warehouse_outlet_id', $warehouse_id)
                ->first();
            if (!$stock || $stock->qty_small < $qty) {
                $kurang[] = [
                    'item_id' => $item_id,
                    'warehouse_id' => $warehouse_id,
                    'kurang' => $qty - ($stock->qty_small ?? 0)
                ];
            }
        }
        if (count($kurang) > 0) {
            // Catat log error stock cut
            StockCutLog::updateOrCreate(
                [
                    'outlet_id' => $id_outlet,
                    'tanggal' => $tanggal,
                    'type_filter' => $type_filter
                ],
                [
                    'total_items_cut' => 0,
                    'total_modifiers_cut' => 0,
                    'status' => 'failed',
                    'error_message' => 'Stock tidak cukup untuk beberapa item',
                    'created_by' => auth()->id()
                ]
            );
            
            return response()->json(['status' => 'error', 'kurang' => $kurang]);
        }

        // 4. Potong stock & catat kartu stok
        foreach ($kebutuhanBahan as $key => $qty) {
            [$item_id, $warehouse_id] = explode('-', $key);
            // Ambil inventory_item_id
            $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item_id)->first();
            if (!$inventoryItem) continue;
            $inventory_item_id = $inventoryItem->id;
            // Update stok (qty_small saja, sesuaikan jika multi-unit)
            DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('id_outlet', $id_outlet)
                ->where('warehouse_outlet_id', $warehouse_id)
                ->decrement('qty_small', $qty);
            // Catat kartu stok
            DB::table('outlet_food_inventory_cards')->insert([
                'inventory_item_id' => $inventory_item_id,
                'id_outlet' => $id_outlet,
                'warehouse_outlet_id' => $warehouse_id,
                'date' => $tanggal,
                'reference_type' => 'order_items',
                'reference_id' => null,
                'in_qty_small' => 0,
                'out_qty_small' => $qty,
                'saldo_qty_small' => null, // bisa diisi jika ingin update saldo
                'description' => 'Potong stock otomatis dari order_items',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Update flag stock_cut di order_items
        DB::table('order_items')
            ->whereDate('created_at', $tanggal)
            ->where('kode_outlet', $qr_code)
            ->where('stock_cut', 0)
            ->update(['stock_cut' => 1]);

        // 6. Catat log stock cut
        $totalItemsCut = count($orderItems);
        $totalModifiersCut = 0;
        
        // Hitung total modifier yang dipotong
        foreach ($orderItems as $oi) {
            if ($oi->modifiers) {
                $modifiers = json_decode($oi->modifiers, true);
                if (is_array($modifiers)) {
                    foreach ($modifiers as $group) {
                        if (is_array($group)) {
                            foreach ($group as $modifierName => $modifierQty) {
                                // Skip modifier dengan modifier_id = 1
                                $modifierOption = DB::table('modifier_options')
                                    ->where('name', $modifierName)
                                    ->first();
                                
                                if ($modifierOption && $modifierOption->modifier_id == 1) {
                                    continue;
                                }
                                
                                $totalModifiersCut += $modifierQty;
                            }
                        }
                    }
                }
            }
        }

        // Insert atau update log stock cut
        StockCutLog::updateOrCreate(
            [
                'outlet_id' => $id_outlet,
                'tanggal' => $tanggal,
                'type_filter' => $type_filter
            ],
            [
                'total_items_cut' => $totalItemsCut,
                'total_modifiers_cut' => $totalModifiersCut,
                'status' => 'success',
                'error_message' => null,
                'created_by' => auth()->id()
            ]
        );

        return response()->json(['status' => 'success', 'message' => 'Potong stock berhasil']);
    }

    /**
     * API: Cek kebutuhan stock (tampilkan kebutuhan vs stock tersedia) - V2
     */
    public function cekKebutuhanStockV2(Request $request)
    {
        $tanggal = $request->input('tanggal');
        $id_outlet = $request->input('id_outlet');
        $type_filter = $request->input('type');
        
        if (!$tanggal || !$id_outlet) {
            return response()->json(['status' => 'error', 'message' => 'Tanggal dan id_outlet wajib diisi'], 422);
        }

        // Ambil qr_code dari tbl_data_outlet
        $qr_code = DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('qr_code');
        
        if (!$qr_code) {
            return response()->json(['status' => 'error', 'message' => 'QR Code outlet tidak ditemukan'], 422);
        }

        // Debug: Log untuk melihat parameter
        \Log::info('CekKebutuhanStockV2 Debug', [
            'tanggal' => $tanggal,
            'id_outlet' => $id_outlet,
            'qr_code' => $qr_code,
            'type_filter' => $type_filter
        ]);
        
        // Debug: Cek apakah outlet ada
        $outletExists = DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->first();
        \Log::info('Outlet check V2', [
            'id_outlet' => $id_outlet,
            'outlet_exists' => $outletExists ? 'YES' : 'NO',
            'outlet_data' => $outletExists
        ]);

        // 1. Ambil order_items yang belum dipotong stock
        $query = DB::table('order_items')
            ->join('items', 'order_items.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->leftJoin('sub_categories', 'items.sub_category_id', '=', 'sub_categories.id')
            ->whereDate('order_items.created_at', $tanggal)
            ->where('order_items.kode_outlet', $qr_code)
            ->where('order_items.stock_cut', 0)
            ->select(
                'order_items.*', 
                'items.type',
                'items.category_id',
                'items.sub_category_id',
                'categories.name as category_name',
                'sub_categories.name as sub_category_name'
            );
        
        // Filter berdasarkan type jika ada
        if ($type_filter) {
            if ($type_filter === 'food') {
                $query->whereIn('items.type', ['Food Asian', 'Food Western', 'Food']);
            } elseif ($type_filter === 'beverages') {
                $query->where('items.type', 'Beverages');
            }
        }
        
        $orderItems = $query->get();

        \Log::info('Order Items Found V2', [
            'count' => $orderItems->count(),
            'outlet_id' => $id_outlet,
            'tanggal' => $tanggal
        ]);
        
        // Debug: Log detail order items jika ada
        if ($orderItems->count() > 0) {
            \Log::info('Order Items Detail V2', [
                'items' => $orderItems->take(3)->map(function($item) {
                    return [
                        'item_id' => $item->item_id,
                        'item_name' => $item->item_name,
                        'type' => $item->type,
                        'qty' => $item->qty
                    ];
                })->toArray()
            ]);
        }

        if ($orderItems->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak ada order_items yang perlu dipotong stock',
                'laporan_stock' => [],
                'total_item_dicek' => 0,
                'total_kurang' => 0,
                'total_cukup' => 0
            ]);
        }

        // 2. Mapping kebutuhan bahan baku & warehouse dengan tracking menu yang berkontribusi
        $kebutuhanBahan = [];
        $warehouseMap = [];
        
        // Debug: Log warehouse info untuk V2
        \Log::info('Warehouse check V2', [
            'id_outlet' => $id_outlet,
            'kitchen_warehouse' => $kitchenWarehouse ? $kitchenWarehouse->id : 'NOT_FOUND',
            'bar_warehouse' => $barWarehouse ? $barWarehouse->id : 'NOT_FOUND',
            'kitchen_warehouse_data' => $kitchenWarehouse,
            'bar_warehouse_data' => $barWarehouse
        ]);
        
        // Debug: Log semua warehouse untuk outlet ini
        $allWarehouses = DB::table('warehouse_outlets')
            ->where('outlet_id', $id_outlet)
            ->get();
        \Log::info('All warehouses for outlet V2', [
            'id_outlet' => $id_outlet,
            'warehouses' => $allWarehouses->toArray()
        ]);
        
        // Debug: Log semua warehouse untuk outlet ini
        $allWarehouses = DB::table('warehouse_outlets')
            ->where('outlet_id', $id_outlet)
            ->get();
        \Log::info('All warehouses for outlet V2', [
            'id_outlet' => $id_outlet,
            'warehouses' => $allWarehouses->toArray()
        ]);
        
        // Ambil warehouse yang tersedia untuk outlet ini
        $kitchenWarehouse = DB::table('warehouse_outlets')
            ->where('outlet_id', $id_outlet)
            ->where('name', 'Kitchen')
            ->where('status', 'active')
            ->first();
            
        $barWarehouse = DB::table('warehouse_outlets')
            ->where('outlet_id', $id_outlet)
            ->where('name', 'Bar')
            ->where('status', 'active')
            ->first();
        
        foreach ($orderItems as $oi) {
            // Tentukan warehouse berdasarkan type item
            $warehouse = null;
            if (in_array($oi->type, ['Food Asian', 'Food Western', 'Food'])) {
                $warehouse = $kitchenWarehouse;
            } elseif ($oi->type == 'Beverages') {
                $warehouse = $barWarehouse;
            } else {
                continue;
            }
            
            if (!$warehouse) {
                continue;
            }
            
            $warehouseMap[$oi->item_id] = $warehouse->id;
            
            // Ambil BOM dengan unit information
            $boms = DB::table('item_bom')
                ->join('units', 'item_bom.unit_id', '=', 'units.id')
                ->where('item_bom.item_id', $oi->item_id)
                ->select('item_bom.*', 'units.name as unit_name')
                ->get();
            
                    // Debug: Log BOM count V2
        \Log::info('BOM found for item V2', [
            'item_id' => $oi->item_id,
            'item_name' => $oi->item_name,
            'bom_count' => $boms->count(),
            'outlet_id' => $id_outlet
        ]);
                
            foreach ($boms as $bom) {
                $key = $bom->material_item_id . '-' . $warehouse->id . '-' . $bom->unit_id;
                if (!isset($kebutuhanBahan[$key])) {
                    $kebutuhanBahan[$key] = [
                        'qty' => 0,
                        'unit_id' => $bom->unit_id,
                        'unit_name' => $bom->unit_name,
                        'contributing_menus' => []
                    ];
                }
                $kebutuhanBahan[$key]['qty'] += ($bom->qty * $oi->qty);
                
                // Track menu yang berkontribusi - group by menu name
                $menuKey = $oi->item_name;
                $foundMenu = false;
                
                if (!isset($kebutuhanBahan[$key]['contributing_menus']) || !is_array($kebutuhanBahan[$key]['contributing_menus'])) {
                    $kebutuhanBahan[$key]['contributing_menus'] = [];
                }
                
                foreach ($kebutuhanBahan[$key]['contributing_menus'] as &$menu) {
                    if ($menu['menu_name'] === $menuKey && $menu['type'] === 'menu') {
                        $menu['menu_qty'] += $oi->qty;
                        $menu['total_contributed'] += ($bom->qty * $oi->qty);
                        $foundMenu = true;
                        break;
                    }
                }
                unset($menu);
                
                if (!$foundMenu) {
                    $kebutuhanBahan[$key]['contributing_menus'][] = [
                        'menu_name' => $oi->item_name,
                        'menu_qty' => $oi->qty,
                        'bom_qty_per_menu' => $bom->qty,
                        'total_contributed' => $bom->qty * $oi->qty,
                        'type' => 'menu'
                    ];
                }
            }
            
            // Ambil BOM dari modifier jika ada
            if ($oi->modifiers) {
                $modifiers = json_decode($oi->modifiers, true);
                if (is_array($modifiers)) {
                    foreach ($modifiers as $group) {
                        if (is_array($group)) {
                            foreach ($group as $modifierName => $modifierQty) {
                                // Cari modifier option berdasarkan nama
                                $modifierOption = DB::table('modifier_options')
                                    ->where('name', $modifierName)
                                    ->whereNotNull('modifier_bom_json')
                                    ->where('modifier_bom_json', '!=', '')
                                    ->where('modifier_bom_json', '!=', '[]')
                                    ->first();
                                
                                // Skip modifier dengan modifier_id = 1
                                if ($modifierOption && $modifierOption->modifier_id == 1) {
                                    continue;
                                }
                                
                                if ($modifierOption && $modifierOption->modifier_bom_json) {
                                    $modifierBom = json_decode($modifierOption->modifier_bom_json, true);
                                    if (is_array($modifierBom)) {
                                        foreach ($modifierBom as $bomItem) {
                                            if (isset($bomItem['item_id']) && isset($bomItem['qty']) && isset($bomItem['unit_id'])) {
                                                $key = $bomItem['item_id'] . '-' . $warehouse->id . '-' . $bomItem['unit_id'];
                                                if (!isset($kebutuhanBahan[$key])) {
                                                    $unitName = DB::table('units')->where('id', $bomItem['unit_id'])->value('name');
                                                    $kebutuhanBahan[$key] = [
                                                        'qty' => 0,
                                                        'unit_id' => $bomItem['unit_id'],
                                                        'unit_name' => $unitName ?: 'Unknown Unit',
                                                        'contributing_menus' => []
                                                    ];
                                                }
                                                
                                                // Ensure contributing_menus is always an array
                                                if (!isset($kebutuhanBahan[$key]['contributing_menus']) || !is_array($kebutuhanBahan[$key]['contributing_menus'])) {
                                                    $kebutuhanBahan[$key]['contributing_menus'] = [];
                                                }
                                                $kebutuhanBahan[$key]['qty'] += ($bomItem['qty'] * $modifierQty * $oi->qty);
                                                
                                                // Track menu yang berkontribusi (dengan modifier) - group by menu name + modifier
                                                $menuKey = $oi->item_name . ' + ' . $modifierName;
                                                $foundMenu = false;
                                                
                                                if (!isset($kebutuhanBahan[$key]['contributing_menus']) || !is_array($kebutuhanBahan[$key]['contributing_menus'])) {
                                                    $kebutuhanBahan[$key]['contributing_menus'] = [];
                                                }
                                                
                                                foreach ($kebutuhanBahan[$key]['contributing_menus'] as &$menu) {
                                                    if ($menu['menu_name'] === $menuKey && $menu['type'] === 'modifier') {
                                                        $menu['menu_qty'] += $oi->qty;
                                                        $menu['modifier_qty'] += $modifierQty;
                                                        $menu['total_contributed'] += ($bomItem['qty'] * $modifierQty * $oi->qty);
                                                        $foundMenu = true;
                                                        break;
                                                    }
                                                }
                                                unset($menu);
                                                
                                                if (!$foundMenu) {
                                                    $kebutuhanBahan[$key]['contributing_menus'][] = [
                                                        'menu_name' => $oi->item_name . ' + ' . $modifierName,
                                                        'menu_qty' => $oi->qty,
                                                        'modifier_qty' => $modifierQty,
                                                        'bom_qty_per_modifier' => $bomItem['qty'],
                                                        'total_contributed' => $bomItem['qty'] * $modifierQty * $oi->qty,
                                                        'type' => 'modifier'
                                                    ];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        \Log::info('Kebutuhan Bahan Calculated V2', [
            'count' => count($kebutuhanBahan),
            'outlet_id' => $id_outlet,
            'tanggal' => $tanggal
        ]);

        // 3. Cek stok dan buat laporan kebutuhan vs stock
        $laporanStock = [];
        $totalKurang = 0;
        
        // Group by item_id and warehouse_id to aggregate all requirements
        $aggregatedItems = [];
        
        foreach ($kebutuhanBahan as $key => $data) {
            [$item_id, $warehouse_id, $unit_id] = explode('-', $key);
            
            // Create unique key for item per warehouse
            $uniqueKey = $item_id . '-' . $warehouse_id;
            
            if (!isset($aggregatedItems[$uniqueKey])) {
                $aggregatedItems[$uniqueKey] = [
                    'item_id' => $item_id,
                    'warehouse_id' => $warehouse_id,
                    'total_qty' => 0,
                    'contributing_menus' => [],
                    'units' => []
                ];
            }
            
            // Aggregate quantities
            $aggregatedItems[$uniqueKey]['total_qty'] += $data['qty'];
            
            // Merge contributing menus
            if (!isset($data['contributing_menus']) || !is_array($data['contributing_menus'])) {
                $data['contributing_menus'] = [];
            }
            foreach ($data['contributing_menus'] as $menu) {
                $menuKey = $menu['menu_name'] . '-' . $menu['type'];
                $found = false;
                
                foreach ($aggregatedItems[$uniqueKey]['contributing_menus'] as &$existingMenu) {
                    if ($existingMenu['menu_name'] === $menu['menu_name'] && $existingMenu['type'] === $menu['type']) {
                        $existingMenu['menu_qty'] += $menu['menu_qty'];
                        $existingMenu['total_contributed'] += $menu['total_contributed'];
                        if (isset($menu['modifier_qty'])) {
                            $existingMenu['modifier_qty'] += $menu['modifier_qty'];
                        }
                        $found = true;
                        break;
                    }
                }
                unset($existingMenu);
                
                if (!$found) {
                    $aggregatedItems[$uniqueKey]['contributing_menus'][] = $menu;
                }
            }
            
            // Track units
            $aggregatedItems[$uniqueKey]['units'][$unit_id] = $data['unit_name'];
        }
        
        // Process aggregated items
        foreach ($aggregatedItems as $uniqueKey => $aggregatedData) {
            $item_id = $aggregatedData['item_id'];
            $warehouse_id = $aggregatedData['warehouse_id'];
            
            // Ambil info item
            $item = DB::table('items')
                ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
                ->leftJoin('sub_categories', 'items.sub_category_id', '=', 'sub_categories.id')
                ->where('items.id', $item_id)
                ->select(
                    'items.*',
                    'categories.name as category_name',
                    'sub_categories.name as sub_category_name'
                )
                ->first();
            
            // Ambil info warehouse
            $warehouse = DB::table('warehouse_outlets')->where('id', $warehouse_id)->first();
            
            // Cek stock
            $stock = DB::table('outlet_food_inventory_items')
                ->join('outlet_food_inventory_stocks', 'outlet_food_inventory_items.id', '=', 'outlet_food_inventory_stocks.inventory_item_id')
                ->where('outlet_food_inventory_items.item_id', $item_id)
                ->where('outlet_food_inventory_stocks.id_outlet', $id_outlet)
                ->where('outlet_food_inventory_stocks.warehouse_outlet_id', $warehouse_id)
                ->first();
            
            $stockTersedia = $stock ? $stock->qty_small : 0;
            $selisih = $aggregatedData['total_qty'] - $stockTersedia;
            
            if ($selisih > 0) {
                $totalKurang++;
            }
            
            // Use the first unit as primary unit for display
            $primaryUnitId = array_keys($aggregatedData['units'])[0];
            $primaryUnitName = $aggregatedData['units'][$primaryUnitId];
            
            $laporanStock[] = [
                'item_id' => $item_id,
                'item_name' => $item ? $item->name : 'Unknown Item',
                'category_name' => $item ? ($item->category_name ?: 'Tanpa Kategori') : 'Unknown Category',
                'sub_category_name' => $item ? ($item->sub_category_name ?: 'Tanpa Sub Kategori') : 'Unknown Sub Category',
                'warehouse_id' => $warehouse_id,
                'warehouse_name' => $warehouse ? $warehouse->name : 'Unknown Warehouse',
                'unit_id' => $primaryUnitId,
                'unit_name' => $primaryUnitName,
                'kebutuhan' => $aggregatedData['total_qty'],
                'stock_tersedia' => $stockTersedia,
                'selisih' => $selisih,
                'status' => $selisih > 0 ? 'kurang' : 'cukup',
                'contributing_menus' => $aggregatedData['contributing_menus']
            ];
        }

        \Log::info('Laporan Stock Generated V2', [
            'count' => count($laporanStock),
            'outlet_id' => $id_outlet,
            'tanggal' => $tanggal
        ]);

        return response()->json([
            'status' => 'success',
            'laporan_stock' => $laporanStock,
            'total_item_dicek' => count($laporanStock),
            'total_kurang' => $totalKurang,
            'total_cukup' => count($laporanStock) - $totalKurang
        ]);
    }

    /**
     * API: Cek kebutuhan stock (tampilkan kebutuhan vs stock tersedia)
     */
    public function cekKebutuhanStock(Request $request)
    {
        $tanggal = $request->input('tanggal');
        $id_outlet = $request->input('id_outlet');
        $type_filter = $request->input('type');
        
        if (!$tanggal || !$id_outlet) {
            return response()->json(['status' => 'error', 'message' => 'Tanggal dan id_outlet wajib diisi'], 422);
        }

        // Ambil qr_code dari tbl_data_outlet
        $qr_code = DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('qr_code');
        
        if (!$qr_code) {
            return response()->json(['status' => 'error', 'message' => 'QR Code outlet tidak ditemukan'], 422);
        }

        // 1. Ambil order_items yang belum dipotong stock
        $query = DB::table('order_items')
            ->join('items', 'order_items.item_id', '=', 'items.id')
            ->whereDate('order_items.created_at', $tanggal)
            ->where('order_items.kode_outlet', $qr_code)
            ->where('order_items.stock_cut', 0)
            ->select('order_items.*', 'items.type');
        
        // Filter berdasarkan type jika ada
        if ($type_filter) {
            if ($type_filter === 'food') {
                $query->whereIn('items.type', ['Food Asian', 'Food Western', 'Food']);
            } elseif ($type_filter === 'beverages') {
                $query->where('items.type', 'Beverages');
            }
        }
        
        $orderItems = $query->get();

        if ($orderItems->isEmpty()) {
            return response()->json(['status' => 'success', 'message' => 'Tidak ada order_items yang perlu dipotong stock']);
        }

        // 2. Mapping kebutuhan bahan baku & warehouse
        $kebutuhanBahan = [];
        $warehouseMap = [];
        
        // Ambil warehouse yang tersedia untuk outlet ini
        $kitchenWarehouse = DB::table('warehouse_outlets')
            ->where('outlet_id', $id_outlet)
            ->where('name', 'Kitchen')
            ->where('status', 'active')
            ->first();
            
        $barWarehouse = DB::table('warehouse_outlets')
            ->where('outlet_id', $id_outlet)
            ->where('name', 'Bar')
            ->where('status', 'active')
            ->first();
        
        foreach ($orderItems as $oi) {
            // Tentukan warehouse berdasarkan type item
            $warehouse = null;
            if (in_array($oi->type, ['Food Asian', 'Food Western', 'Food'])) {
                $warehouse = $kitchenWarehouse;
            } elseif ($oi->type == 'Beverages') {
                $warehouse = $barWarehouse;
            } else {
                continue;
            }
            
            if (!$warehouse) {
                continue;
            }
            
            $warehouseMap[$oi->item_id] = $warehouse->id;
            
            // Ambil BOM
            $boms = DB::table('item_bom')->where('item_id', $oi->item_id)->get();
            foreach ($boms as $bom) {
                $key = $bom->material_item_id . '-' . $warehouse->id;
                $kebutuhanBahan[$key] = ($kebutuhanBahan[$key] ?? 0) + ($bom->qty * $oi->qty);
            }
            
            // Ambil BOM dari modifier jika ada
            if ($oi->modifiers) {
                $modifiers = json_decode($oi->modifiers, true);
                if (is_array($modifiers)) {
                    foreach ($modifiers as $group) {
                        if (is_array($group)) {
                            foreach ($group as $modifierName => $modifierQty) {
                                // Cari modifier option berdasarkan nama
                                $modifierOption = DB::table('modifier_options')
                                    ->where('name', $modifierName)
                                    ->whereNotNull('modifier_bom_json')
                                    ->where('modifier_bom_json', '!=', '')
                                    ->where('modifier_bom_json', '!=', '[]')
                                    ->first();
                                
                                // Skip modifier dengan modifier_id = 1
                                if ($modifierOption && $modifierOption->modifier_id == 1) {
                                    continue;
                                }
                                
                                if ($modifierOption && $modifierOption->modifier_bom_json) {
                                    $modifierBom = json_decode($modifierOption->modifier_bom_json, true);
                                    if (is_array($modifierBom)) {
                                        foreach ($modifierBom as $bomItem) {
                                            if (isset($bomItem['item_id']) && isset($bomItem['qty'])) {
                                                $key = $bomItem['item_id'] . '-' . $warehouse->id;
                                                $kebutuhanBahan[$key] = ($kebutuhanBahan[$key] ?? 0) + ($bomItem['qty'] * $modifierQty * $oi->qty);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // 3. Cek stok dan buat laporan kebutuhan vs stock
        $laporanStock = [];
        $totalKurang = 0;
        
        foreach ($kebutuhanBahan as $key => $qty) {
            [$item_id, $warehouse_id] = explode('-', $key);
            
            // Ambil info item
            $item = DB::table('items')->where('id', $item_id)->first();
            
            // Ambil info warehouse
            $warehouse = DB::table('warehouse_outlets')->where('id', $warehouse_id)->first();
            
            // Cek stock
            $stock = DB::table('outlet_food_inventory_items')
                ->join('outlet_food_inventory_stocks', 'outlet_food_inventory_items.id', '=', 'outlet_food_inventory_stocks.inventory_item_id')
                ->where('outlet_food_inventory_items.item_id', $item_id)
                ->where('outlet_food_inventory_stocks.id_outlet', $id_outlet)
                ->where('outlet_food_inventory_stocks.warehouse_outlet_id', $warehouse_id)
                ->first();
            
            $stockTersedia = $stock ? $stock->qty_small : 0;
            $selisih = $qty - $stockTersedia;
            
            if ($selisih > 0) {
                $totalKurang++;
            }
            
            $laporanStock[] = [
                'item_id' => $item_id,
                'item_name' => $item ? $item->name : 'Unknown Item',
                'warehouse_id' => $warehouse_id,
                'warehouse_name' => $warehouse ? $warehouse->name : 'Unknown Warehouse',
                'kebutuhan' => $qty,
                'stock_tersedia' => $stockTersedia,
                'selisih' => $selisih,
                'status' => $selisih > 0 ? 'kurang' : 'cukup'
            ];
        }

        return response()->json([
            'status' => 'success',
            'laporan_stock' => $laporanStock,
            'total_item_dicek' => count($laporanStock),
            'total_kurang' => $totalKurang,
            'total_cukup' => count($laporanStock) - $totalKurang
        ]);
    }

    /**
     * API: Cek apakah outlet sudah melakukan stock cut pada tanggal tertentu
     */
    public function checkStockCutStatus(Request $request)
    {
        $tanggal = $request->input('tanggal');
        $id_outlet = $request->input('id_outlet');
        $type_filter = $request->input('type');
        
        if (!$tanggal || !$id_outlet) {
            return response()->json(['status' => 'error', 'message' => 'Tanggal dan id_outlet wajib diisi'], 422);
        }

        $log = StockCutLog::where('outlet_id', $id_outlet)
            ->where('tanggal', $tanggal)
            ->where('type_filter', $type_filter)
            ->first();

        if ($log) {
            return response()->json([
                'status' => 'success',
                'has_stock_cut' => true,
                'log' => [
                    'id' => $log->id,
                    'status' => $log->status,
                    'total_items_cut' => $log->total_items_cut,
                    'total_modifiers_cut' => $log->total_modifiers_cut,
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at,
                    'created_by' => $log->created_by
                ]
            ]);
        }

        return response()->json([
            'status' => 'success',
            'has_stock_cut' => false,
            'log' => null
        ]);
    }

    /**
     * API: List log potong stock
     */
    public function getLogs()
    {
        $logs = \DB::table('stock_cut_logs as scl')
            ->join('tbl_data_outlet as o', 'scl.id_outlet', '=', 'o.id_outlet')
            ->join('users as u', 'scl.user_id', '=', 'u.id')
            ->select(
                'scl.id',
                'scl.tanggal',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as user_name',
                'scl.created_at'
            )
            ->orderByDesc('scl.created_at')
            ->get();
        return response()->json($logs);
    }

    /**
     * API: Rollback potong stock (hapus log dan kembalikan stock)
     */
    public function rollback($id)
    {
        $log = \DB::table('stock_cut_logs')->where('id', $id)->first();
        if (!$log) {
            return response()->json(['error' => 'Log tidak ditemukan'], 404);
        }
        $tanggal = $log->tanggal;
        $id_outlet = $log->id_outlet;
        // Ambil semua kartu stok out pada tanggal & outlet tsb
        $cards = \DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $id_outlet)
            ->where('date', $tanggal)
            ->where('reference_type', 'order_items')
            ->get();
        // Rollback: tambahkan kembali qty yang sudah dipotong
        foreach ($cards as $card) {
            \DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $card->inventory_item_id)
                ->where('id_outlet', $id_outlet)
                ->where('warehouse_outlet_id', $card->warehouse_outlet_id)
                ->increment('qty_small', $card->out_qty_small);
        }
        // Hapus kartu stok
        \DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $id_outlet)
            ->where('date', $tanggal)
            ->where('reference_type', 'order_items')
            ->delete();
        // Ambil qr_code dari tbl_data_outlet untuk rollback
        $qr_code = \DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('qr_code');
        
        // Reset flag stock_cut di order_items
        \DB::table('order_items')
            ->whereDate('created_at', $tanggal)
            ->where('kode_outlet', $qr_code)
            ->update(['stock_cut' => 0]);
        // Hapus log
        \DB::table('stock_cut_logs')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * API: Engineering - Rekap item terjual pada tanggal & outlet (group by nama, sum qty)
     */
    public function engineering(Request $request)
    {
        $tanggal = $request->input('tanggal');
        $id_outlet = $request->input('id_outlet');
        $type_filter = $request->input('type'); // Filter berdasarkan type
        
        // Ambil qr_code dari tbl_data_outlet
        $qr_code = \DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('qr_code');
        
        $query = \DB::table('order_items as oi')
            ->join('items as i', 'oi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->select(
                'i.id as item_id',
                'i.type',
                'c.name as category_name',
                'sc.name as sub_category_name',
                'i.name as item_name',
                \DB::raw('SUM(oi.qty) as total_qty')
            )
            ->whereDate('oi.created_at', $tanggal)
            ->where('oi.kode_outlet', $qr_code)
            ->groupBy('i.id', 'i.type', 'c.name', 'sc.name', 'i.name');
        
        // Filter berdasarkan type jika ada
        if ($type_filter) {
            if ($type_filter === 'food') {
                $query->whereIn('i.type', ['Food Asian', 'Food Western', 'Food']);
            } elseif ($type_filter === 'beverages') {
                $query->where('i.type', 'Beverages');
            }
        }
        
        $rows = $query->orderBy('i.type')
            ->orderBy('c.name')
            ->orderBy('sc.name')
            ->orderBy('i.name')
            ->get();
        // Strukturkan hasil group by type > category > sub_category > item
        $result = [];
        $itemIds = [];
        foreach ($rows as $row) {
            $type = $row->type ?: 'Tanpa Type';
            $cat = $row->category_name ?: 'Tanpa Kategori';
            $subcat = $row->sub_category_name ?: 'Tanpa Sub Kategori';
            if (!isset($result[$type])) $result[$type] = [];
            if (!isset($result[$type][$cat])) $result[$type][$cat] = [];
            if (!isset($result[$type][$cat][$subcat])) $result[$type][$cat][$subcat] = [];
            $result[$type][$cat][$subcat][] = [
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'total_qty' => $row->total_qty
            ];
            $itemIds[] = $row->item_id;
        }
        // Cek item yang tidak ada di item_bom
        $itemIds = array_unique($itemIds);
        $itemBomIds = \DB::table('item_bom')->whereIn('item_id', $itemIds)->pluck('item_id')->unique()->toArray();
        $missingBom = [];
        foreach ($rows as $row) {
            if (!in_array($row->item_id, $itemBomIds)) {
                $missingBom[] = [
                    'item_id' => $row->item_id,
                    'item_name' => $row->item_name
                ];
            }
        }
        // MODIFIER ENGINEERING
        $orderItems = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(['order_items.modifiers', 'order_items.qty'])
            ->whereDate('orders.created_at', $tanggal)
            ->where('orders.kode_outlet', $qr_code)
            ->get();
            
        $modifierMap = [];
        foreach ($orderItems as $oi) {
            if (!$oi->modifiers) continue;
            $mods = json_decode($oi->modifiers, true);
            if (!is_array($mods)) continue;
            foreach ($mods as $group) {
                if (is_array($group)) {
                    foreach ($group as $name => $qty) {
                        if (!isset($modifierMap[$name])) $modifierMap[$name] = 0;
                        $modifierMap[$name] += $qty;
                    }
                }
            }
        }
        
        $modifiers = [];
        $modifiersByGroup = [];
        
        foreach ($modifierMap as $name => $qty) {
            // Cek modifier_id di tabel modifier_options, skip jika modifier_id = 1
            $modifierOption = DB::table('modifier_options')
                ->where('name', $name)
                ->first();
            
            if ($modifierOption && $modifierOption->modifier_id == 1) {
                continue; // Skip modifier dengan modifier_id = 1
            }
            
            // Ambil info modifier group
            $modifierGroup = DB::table('modifiers')
                ->where('id', $modifierOption->modifier_id)
                ->first();
            
            $groupName = $modifierGroup ? $modifierGroup->name : 'Unknown Group';
            $modifierId = $modifierOption ? $modifierOption->modifier_id : 0;
            
            if (!isset($modifiersByGroup[$modifierId])) {
                $modifiersByGroup[$modifierId] = [
                    'group_id' => $modifierId,
                    'group_name' => $groupName,
                    'modifiers' => []
                ];
            }
            
            $modifiersByGroup[$modifierId]['modifiers'][] = [
                'name' => $name,
                'qty' => $qty
            ];
        }
        
        // Sort modifiers within each group by qty
        foreach ($modifiersByGroup as &$group) {
            usort($group['modifiers'], function($a, $b) { 
                return $b['qty'] <=> $a['qty']; 
            });
        }
        
        // Convert to array and sort groups by total qty
        $modifiers = array_values($modifiersByGroup);
        usort($modifiers, function($a, $b) {
            $totalA = array_sum(array_column($a['modifiers'], 'qty'));
            $totalB = array_sum(array_column($b['modifiers'], 'qty'));
            return $totalB <=> $totalA;
        });

        // Cek modifier yang tidak ada BOM
        $missingModifierBom = [];
        foreach ($modifierMap as $name => $qty) {
            // Skip modifier dengan modifier_id = 1
            $modifierOption = DB::table('modifier_options')
                ->where('name', $name)
                ->first();
            
            if ($modifierOption && $modifierOption->modifier_id == 1) {
                continue;
            }
            
            // Cek apakah modifier punya BOM
            if (!$modifierOption || 
                !$modifierOption->modifier_bom_json || 
                $modifierOption->modifier_bom_json == '' || 
                $modifierOption->modifier_bom_json == '[]') {
                
                $missingModifierBom[] = [
                    'name' => $name,
                    'modifier_id' => $modifierOption ? $modifierOption->modifier_id : null
                ];
            }
        }

        return response()->json([
            'engineering' => $result,
            'missing_bom' => $missingBom,
            'modifiers' => $modifiers,
            'missing_modifier_bom' => $missingModifierBom
        ]);
    }

    /**
     * API: Hitung cost per menu setelah stock cut
     */
    public function calculateMenuCost(Request $request)
    {
        $tanggal = $request->input('tanggal');
        $id_outlet = $request->input('id_outlet');
        $type_filter = $request->input('type');
        
        if (!$tanggal || !$id_outlet) {
            return response()->json(['status' => 'error', 'message' => 'Tanggal dan id_outlet wajib diisi'], 422);
        }

        // Ambil qr_code dari tbl_data_outlet
        $qr_code = DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('qr_code');
        
        if (!$qr_code) {
            return response()->json(['status' => 'error', 'message' => 'QR Code outlet tidak ditemukan'], 422);
        }

        // 1. Ambil order_items yang sudah dipotong stock
        $query = DB::table('order_items')
            ->join('items', 'order_items.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->leftJoin('sub_categories', 'items.sub_category_id', '=', 'sub_categories.id')
            ->whereDate('order_items.created_at', $tanggal)
            ->where('order_items.kode_outlet', $qr_code)
            ->where('order_items.stock_cut', 1) // Hanya yang sudah dipotong stock
            ->select(
                'order_items.*', 
                'items.type',
                'items.category_id',
                'items.sub_category_id',
                'categories.name as category_name',
                'sub_categories.name as sub_category_name'
            );
        
        // Filter berdasarkan type jika ada
        if ($type_filter) {
            if ($type_filter === 'food') {
                $query->whereIn('items.type', ['Food Asian', 'Food Western', 'Food']);
            } elseif ($type_filter === 'beverages') {
                $query->where('items.type', 'Beverages');
            }
        }
        
        $orderItems = $query->get();

        if ($orderItems->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak ada order_items yang sudah dipotong stock',
                'menu_costs' => [],
                'total_menu' => 0,
                'total_cost' => 0
            ]);
        }

        // 2. Hitung cost per menu
        $menuCosts = [];
        $totalCost = 0;
        
        foreach ($orderItems as $oi) {
            $menuCost = 0;
            $bomDetails = [];
            
            // Ambil BOM untuk menu
            $boms = DB::table('item_bom')
                ->join('units', 'item_bom.unit_id', '=', 'units.id')
                ->where('item_bom.item_id', $oi->item_id)
                ->select('item_bom.*', 'units.name as unit_name')
                ->get();
                
            foreach ($boms as $bom) {
                // Ambil harga bahan baku dari inventory
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $bom->material_item_id)
                    ->first();
                
                if ($inventoryItem) {
                    // Tentukan warehouse berdasarkan type item
                    $warehouse = null;
                    if (in_array($oi->type, ['Food Asian', 'Food Western', 'Food'])) {
                        $warehouse = DB::table('warehouse_outlets')
                            ->where('outlet_id', $id_outlet)
                            ->where('name', 'Kitchen')
                            ->where('status', 'active')
                            ->first();
                    } elseif ($oi->type == 'Beverages') {
                        $warehouse = DB::table('warehouse_outlets')
                            ->where('outlet_id', $id_outlet)
                            ->where('name', 'Bar')
                            ->where('status', 'active')
                            ->first();
                    }
                    
                    if ($warehouse) {
                        // Ambil stock dan cost dari warehouse yang sesuai
                        $stock = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $inventoryItem->id)
                            ->where('id_outlet', $id_outlet)
                            ->where('warehouse_outlet_id', $warehouse->id)
                            ->first();
                        
                        if ($stock) {
                            // Hitung cost berdasarkan last_cost_small
                            $costPerUnit = $stock->last_cost_small ?? 0;
                            $qtyNeeded = $bom->qty * $oi->qty;
                            $totalCostForBom = $costPerUnit * $qtyNeeded;
                            $menuCost += $totalCostForBom;
                            
                            // Ambil nama bahan baku
                            $materialName = DB::table('items')
                                ->where('id', $bom->material_item_id)
                                ->value('name') ?? 'Unknown Material';
                            
                            $bomDetails[] = [
                                'material_name' => $materialName,
                                'qty_needed' => $qtyNeeded,
                                'unit_name' => $bom->unit_name,
                                'cost_per_unit' => $costPerUnit,
                                'total_cost' => $totalCostForBom
                            ];
                        }
                    }
                }
            }
            
            // Hitung cost dari modifier jika ada
            if ($oi->modifiers) {
                $modifiers = json_decode($oi->modifiers, true);
                if (is_array($modifiers)) {
                    foreach ($modifiers as $group) {
                        if (is_array($group)) {
                            foreach ($group as $modifierName => $modifierQty) {
                                // Cari modifier option berdasarkan nama
                                $modifierOption = DB::table('modifier_options')
                                    ->where('name', $modifierName)
                                    ->whereNotNull('modifier_bom_json')
                                    ->where('modifier_bom_json', '!=', '')
                                    ->where('modifier_bom_json', '!=', '[]')
                                    ->first();
                                
                                // Skip modifier dengan modifier_id = 1
                                if ($modifierOption && $modifierOption->modifier_id == 1) {
                                    continue;
                                }
                                
                                if ($modifierOption && $modifierOption->modifier_bom_json) {
                                    $modifierBom = json_decode($modifierOption->modifier_bom_json, true);
                                    if (is_array($modifierBom)) {
                                        foreach ($modifierBom as $bomItem) {
                                            if (isset($bomItem['item_id']) && isset($bomItem['qty']) && isset($bomItem['unit_id'])) {
                                                // Ambil harga bahan baku modifier
                                                $inventoryItem = DB::table('outlet_food_inventory_items')
                                                    ->where('item_id', $bomItem['item_id'])
                                                    ->first();
                                                
                                                if ($inventoryItem) {
                                                    // Tentukan warehouse berdasarkan type item
                                                    $warehouse = null;
                                                    if (in_array($oi->type, ['Food Asian', 'Food Western', 'Food'])) {
                                                        $warehouse = DB::table('warehouse_outlets')
                                                            ->where('outlet_id', $id_outlet)
                                                            ->where('name', 'Kitchen')
                                                            ->where('status', 'active')
                                                            ->first();
                                                    } elseif ($oi->type == 'Beverages') {
                                                        $warehouse = DB::table('warehouse_outlets')
                                                            ->where('outlet_id', $id_outlet)
                                                            ->where('name', 'Bar')
                                                            ->where('status', 'active')
                                                            ->first();
                                                    }
                                                    
                                                    if ($warehouse) {
                                                        $stock = DB::table('outlet_food_inventory_stocks')
                                                            ->where('inventory_item_id', $inventoryItem->id)
                                                            ->where('id_outlet', $id_outlet)
                                                            ->where('warehouse_outlet_id', $warehouse->id)
                                                            ->first();
                                                        
                                                        if ($stock) {
                                                        $costPerUnit = $stock->last_cost_small ?? 0;
                                                        $qtyNeeded = $bomItem['qty'] * $modifierQty * $oi->qty;
                                                        $totalCostForModifier = $costPerUnit * $qtyNeeded;
                                                        $menuCost += $totalCostForModifier;
                                                        
                                                        $materialName = DB::table('items')
                                                            ->where('id', $bomItem['item_id'])
                                                            ->value('name') ?? 'Unknown Material';
                                                        
                                                        $unitName = DB::table('units')
                                                            ->where('id', $bomItem['unit_id'])
                                                            ->value('name') ?? 'Unknown Unit';
                                                        
                                                        $bomDetails[] = [
                                                            'material_name' => $materialName . ' (Modifier: ' . $modifierName . ')',
                                                            'qty_needed' => $qtyNeeded,
                                                            'unit_name' => $unitName,
                                                            'cost_per_unit' => $costPerUnit,
                                                            'total_cost' => $totalCostForModifier
                                                        ];
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            $totalCost += $menuCost;
            
            $menuCosts[] = [
                'item_id' => $oi->item_id,
                'item_name' => $oi->item_name,
                'category_name' => $oi->category_name ?: 'Tanpa Kategori',
                'sub_category_name' => $oi->sub_category_name ?: 'Tanpa Sub Kategori',
                'qty_ordered' => $oi->qty,
                'total_cost' => round($menuCost, 2),
                'cost_per_unit' => $oi->qty > 0 ? round($menuCost / $oi->qty, 2) : 0,
                'bom_details' => $bomDetails
            ];
        }

        return response()->json([
            'status' => 'success',
            'menu_costs' => $menuCosts,
            'total_menu' => count($menuCosts),
            'total_cost' => round($totalCost, 2),
            'periode' => $tanggal,
            'outlet_id' => $id_outlet
        ]);
    }
} 