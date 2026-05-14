<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MKProductionController extends Controller
{
    /**
     * API: list MK production for mobile app (approval-app).
     */
    public function apiIndex(Request $request)
    {
        $query = DB::table('mk_productions')
            ->leftJoin('items', 'mk_productions.item_id', '=', 'items.id')
            ->leftJoin('units', 'mk_productions.unit_id', '=', 'units.id')
            ->leftJoin('users', 'mk_productions.created_by', '=', 'users.id')
            ->select(
                'mk_productions.*',
                'items.name as item_name',
                'units.name as unit_name',
                'users.nama_lengkap as created_by_name',
                'users.avatar as created_by_avatar'
            );

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('items.name', 'like', $search)
                    ->orWhere('mk_productions.batch_number', 'like', $search)
                    ->orWhere('users.nama_lengkap', 'like', $search)
                    ->orWhere('mk_productions.notes', 'like', $search);
            });
        }
        if ($request->filled('item_id')) {
            $query->where('mk_productions.item_id', $request->item_id);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('mk_productions.production_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('mk_productions.production_date', '<=', $request->to_date);
        }

        $perPage = (int) $request->get('per_page', 15);
        $paginated = $query->orderByDesc('mk_productions.production_date')
            ->orderByDesc('mk_productions.id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * API: create data for mobile app.
     */
    public function apiCreateData()
    {
        $items = DB::table('items')
            ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
            ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
            ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
            ->where('items.composition_type', 'composed')
            ->where('items.status', 'active')
            ->select(
                'items.*',
                'small_unit.name as small_unit_name',
                'medium_unit.name as medium_unit_name',
                'large_unit.name as large_unit_name'
            )
            ->get();

        $itemsWithBom = DB::table('items')
            ->join('item_bom', 'items.id', '=', 'item_bom.item_id')
            ->where('items.composition_type', 'composed')
            ->where('items.status', 'active')
            ->select('items.id', 'items.name')
            ->distinct()
            ->get();

        $warehouses = DB::table('warehouses')
            ->where('status', 'active')
            ->get();

        return response()->json([
            'success' => true,
            'items' => $items,
            'items_with_bom' => $itemsWithBom,
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * API: show one MK production detail for mobile app.
     */
    public function apiShow($id)
    {
        $prod = DB::table('mk_productions')->where('id', $id)->first();
        if (!$prod) {
            return response()->json(['success' => false, 'message' => 'Data produksi tidak ditemukan'], 404);
        }

        $item = DB::table('items')
            ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
            ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
            ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
            ->where('items.id', $prod->item_id)
            ->select(
                'items.*',
                'small_unit.name as small_unit_name',
                'medium_unit.name as medium_unit_name',
                'large_unit.name as large_unit_name'
            )
            ->first();

        $warehouse = DB::table('warehouses')->where('id', $prod->warehouse_id)->first();
        $stockCard = DB::table('food_inventory_cards')
            ->where('reference_type', 'mk_production')
            ->where('reference_id', $id)
            ->get();
        $bom = DB::table('item_bom')
            ->join('items as material', 'item_bom.material_item_id', '=', 'material.id')
            ->join('units', 'item_bom.unit_id', '=', 'units.id')
            ->where('item_bom.item_id', $prod->item_id)
            ->select('item_bom.*', 'material.name as material_name', 'units.name as unit_name')
            ->get();

        return response()->json([
            'success' => true,
            'prod' => $prod,
            'item' => $item,
            'warehouse' => $warehouse,
            'stock_card' => $stockCard,
            'bom' => $bom,
        ]);
    }

    public function index(Request $request)
    {
        // Ambil item hasil produksi (composed & aktif) beserta nama unit
        $items = DB::table('items')
            ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
            ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
            ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
            ->where('items.composition_type', 'composed')
            ->where('items.status', 'active')
            ->select(
                'items.*',
                'small_unit.name as small_unit_name',
                'medium_unit.name as medium_unit_name',
                'large_unit.name as large_unit_name'
            )
            ->get();
        $warehouses = DB::table('warehouses')
            ->where('status', 'active')
            ->get();
        
        // Query untuk histori produksi dengan filter
        $query = DB::table('mk_productions')
            ->leftJoin('items', 'mk_productions.item_id', '=', 'items.id')
            ->leftJoin('units', 'mk_productions.unit_id', '=', 'units.id')
            ->leftJoin('users', 'mk_productions.created_by', '=', 'users.id')
            ->select(
                'mk_productions.*',
                'items.name as item_name',
                'units.name as unit_name',
                'users.nama_lengkap as created_by_name'
            );

        // Apply filters
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('items.name', 'like', $search)
                  ->orWhere('mk_productions.batch_number', 'like', $search)
                  ->orWhere('users.nama_lengkap', 'like', $search)
                  ->orWhere('mk_productions.notes', 'like', $search);
            });
        }

        if ($request->filled('item_id')) {
            $query->where('mk_productions.item_id', $request->item_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('mk_productions.production_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('mk_productions.production_date', '<=', $request->to_date);
        }

        // Pagination dengan per page
        $perPage = $request->get('per_page', 15);
        $productions = $query->orderByDesc('mk_productions.production_date')
            ->orderByDesc('mk_productions.id')
            ->paginate($perPage);

        return inertia('MKProduction/Index', [
            'items' => $items,
            'warehouses' => $warehouses,
            'productions' => $productions,
            'filters' => $request->only(['search', 'item_id', 'from_date', 'to_date', 'per_page'])
        ]);
    }

    public function getBomAndStock(Request $request)
    {
        $item_id = $request->input('item_id');
        $qty = $request->input('qty', 1);
        $warehouse_id = $request->input('warehouse_id');
        
        // Validasi input
        if (!$item_id || !$qty || !$warehouse_id) {
            return response()->json([]);
        }
        
        // Cek apakah item ada dan memiliki composition_type = 'composed'
        $item = DB::table('items')
            ->where('id', $item_id)
            ->where('composition_type', 'composed')
            ->where('status', 'active')
            ->first();
            
        if (!$item) {
            return response()->json([]);
        }
        
        // Ambil BOM untuk item tersebut
        $bom = DB::table('item_bom')
            ->join('items as material', 'item_bom.material_item_id', '=', 'material.id')
            ->join('units', 'item_bom.unit_id', '=', 'units.id')
            ->where('item_bom.item_id', $item_id)
            ->select(
                'item_bom.*',
                'material.name as material_name',
                'units.name as unit_name'
            )
            ->get();
            
        if ($bom->isEmpty()) {
            return response()->json([
                'error' => 'Item tidak memiliki BOM (Bill of Materials). Silakan pilih item lain atau tambahkan BOM untuk item ini.',
                'item_name' => $item->name
            ]);
        }
        
        // Ambil inventory items untuk bahan baku
        $inventoryItems = DB::table('food_inventory_items')
            ->whereIn('item_id', $bom->pluck('material_item_id'))
            ->pluck('id', 'item_id');
            
        // Ambil stok untuk bahan baku
        $stocks = DB::table('food_inventory_stocks')
            ->whereIn('inventory_item_id', $inventoryItems->values())
            ->where('warehouse_id', $warehouse_id)
            ->pluck('qty_small', 'inventory_item_id');
            
        // Mapping stok berdasarkan item_id
        $stok = [];
        foreach ($inventoryItems as $item_id => $inventory_item_id) {
            $stok[$item_id] = $stocks[$inventory_item_id] ?? 0;
        }
        
        // Buat response BOM data
        $bomData = $bom->map(function($b) use ($qty, $stok) {
            $qty_total = $b->qty * $qty;
            $stok_now = $stok[$b->material_item_id] ?? 0;
            return [
                'material_item_id' => $b->material_item_id,
                'material_name' => $b->material_name,
                'qty_per_1' => $b->qty,
                'unit_name' => $b->unit_name,
                'qty_total' => $qty_total,
                'stok' => $stok_now,
                'sisa' => $stok_now - $qty_total,
            ];
        });
        
        return response()->json($bomData);
    }

    public function store(Request $request)
    {
        $item_id = $request->input('item_id');
        $qty_produksi = $request->input('qty');
        $qty_jadi = $request->input('qty_jadi');
        $unit_jadi = $request->input('unit_jadi');
        $production_date = $request->input('production_date');
        $batch_number = $request->input('batch_number');
        $notes = $request->input('notes');
        $unit_id = $request->input('unit_id');
        $bom = DB::table('item_bom')->where('item_id', $item_id)->get();
        
        // OPTIMIZED: Pre-load semua data yang dibutuhkan untuk menghindari N+1 query
        $materialItemIds = $bom->pluck('material_item_id')->toArray();
        
        // Pre-load inventory items
        $bomInventories = DB::table('food_inventory_items')
            ->whereIn('item_id', $materialItemIds)
            ->get()
            ->keyBy('item_id');
        
        // Pre-load material items
        $materialItems = DB::table('items')
            ->whereIn('id', $materialItemIds)
            ->get()
            ->keyBy('id');
        
        // Pre-load inventory stocks
        $inventoryItemIds = $bomInventories->pluck('id')->toArray();
        $stocksData = DB::table('food_inventory_stocks')
            ->whereIn('inventory_item_id', $inventoryItemIds)
            ->where('warehouse_id', $request->warehouse_id)
            ->get()
            ->keyBy('inventory_item_id');
        
        // Validasi stok cukup
        foreach ($bom as $b) {
            $bomInventory = $bomInventories->get($b->material_item_id);
            $bomInventoryId = $bomInventory ? $bomInventory->id : null;
            $stok = 0;
            if ($bomInventoryId && isset($stocksData[$bomInventoryId])) {
                $stok = $stocksData[$bomInventoryId]->qty_small;
            }
            $qty_total = $b->qty * $qty_produksi;
            if ($stok < $qty_total) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok bahan {$b->material_item_id} tidak cukup",
                    ], 422);
                }
                return back()->with('error', "Stok bahan {$b->material_item_id} tidak cukup");
            }
        }
        DB::beginTransaction();
        try {
            foreach ($bom as $b) {
                // Ambil/insert inventory_item_id bahan baku (gunakan data yang sudah di-load)
                $bomInventory = $bomInventories->get($b->material_item_id);
                // Ambil/insert inventory_item_id bahan baku (gunakan data yang sudah di-load)
                $bomInventory = $bomInventories->get($b->material_item_id);
                if (!$bomInventory) {
                    $bomInventoryId = DB::table('food_inventory_items')->insertGetId([
                        'item_id' => $b->material_item_id,
                        'small_unit_id' => $b->unit_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    // Tambahkan ke collection untuk digunakan di loop berikutnya
                    $bomInventories->put($b->material_item_id, (object)['id' => $bomInventoryId, 'item_id' => $b->material_item_id]);
                } else {
                    $bomInventoryId = $bomInventory->id;
                }
                $qty_total = $b->qty * $qty_produksi;
                
                // Ambil data konversi satuan dari item bahan baku (gunakan data yang sudah di-load)
                $materialItem = $materialItems->get($b->material_item_id);
                $smallConv = $materialItem->small_conversion_qty ?: 1;
                $mediumConv = $materialItem->medium_conversion_qty ?: 1;
                $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                // Konversi qty_total ke small/medium/large sesuai unit_id BOM
                if ($b->unit_id == $materialItem->small_unit_id) {
                    $qty_small = $qty_total;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                } elseif ($b->unit_id == $materialItem->medium_unit_id) {
                    $qty_medium = $qty_total;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                } elseif ($b->unit_id == $materialItem->large_unit_id) {
                    $qty_large = $qty_total;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } else {
                    $qty_small = $qty_total;
                }
                
                // Kurangi stok bahan baku (gunakan data yang sudah di-load)
                $stockBahan = $stocksData->get($bomInventoryId);
                if ($stockBahan) {
                DB::table('food_inventory_stocks')
                        ->where('inventory_item_id', $bomInventoryId)
                        ->where('warehouse_id', $request->warehouse_id)
                        ->update([
                            'qty_small' => $stockBahan->qty_small - $qty_small,
                            'qty_medium' => ($stockBahan->qty_medium ?? 0) - $qty_medium,
                            'qty_large' => ($stockBahan->qty_large ?? 0) - $qty_large,
                        ]);
                    // Update collection untuk digunakan di query selanjutnya
                    $stocksData->put($bomInventoryId, (object)[
                        'inventory_item_id' => $bomInventoryId,
                        'qty_small' => $stockBahan->qty_small - $qty_small,
                        'qty_medium' => ($stockBahan->qty_medium ?? 0) - $qty_medium,
                        'qty_large' => ($stockBahan->qty_large ?? 0) - $qty_large,
                        'last_cost_small' => $stockBahan->last_cost_small ?? 0,
                        'last_cost_medium' => $stockBahan->last_cost_medium ?? 0,
                        'last_cost_large' => $stockBahan->last_cost_large ?? 0,
                    ]);
                } else {
                    DB::table('food_inventory_stocks')->insert([
                        'inventory_item_id' => $bomInventoryId,
                        'warehouse_id' => $request->warehouse_id,
                        'qty_small' => 0 - $qty_small,
                        'qty_medium' => 0 - $qty_medium,
                        'qty_large' => 0 - $qty_large,
                        'value' => 0,
                        'last_cost_small' => 0,
                        'last_cost_medium' => 0,
                        'last_cost_large' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    // Tambahkan ke collection
                    $stocksData->put($bomInventoryId, (object)[
                        'inventory_item_id' => $bomInventoryId,
                        'qty_small' => 0 - $qty_small,
                        'qty_medium' => 0 - $qty_medium,
                        'qty_large' => 0 - $qty_large,
                        'last_cost_small' => 0,
                        'last_cost_medium' => 0,
                        'last_cost_large' => 0,
                    ]);
                }
                
                // Ambil cost terakhir bahan baku (dari collection yang sudah di-update)
                $stockBahanUpdated = $stocksData->get($bomInventoryId);
                $last_cost_small = $stockBahanUpdated->last_cost_small ?? 0;
                $last_cost_medium = $stockBahanUpdated->last_cost_medium ?? 0;
                $last_cost_large = $stockBahanUpdated->last_cost_large ?? 0;
                
                // Insert kartu stok OUT bahan baku
                $saldo = $stocksData->get($bomInventoryId);
                DB::table('food_inventory_cards')->insert([
                    'inventory_item_id' => $bomInventoryId,
                    'warehouse_id' => $request->warehouse_id,
                    'date' => $production_date,
                    'reference_type' => 'mk_production',
                    'reference_id' => null,
                    'out_qty_small' => $qty_small,
                    'out_qty_medium' => $qty_medium,
                    'out_qty_large' => $qty_large,
                    'cost_per_small' => $last_cost_small,
                    'cost_per_medium' => $last_cost_medium,
                    'cost_per_large' => $last_cost_large,
                    'value_out' => $qty_small * $last_cost_small,
                    'saldo_qty_small' => $saldo ? $saldo->qty_small : 0,
                    'saldo_qty_medium' => $saldo ? $saldo->qty_medium : 0,
                    'saldo_qty_large' => $saldo ? $saldo->qty_large : 0,
                    'saldo_value' => $saldo ? $saldo->qty_small * $last_cost_small : 0,
                    'description' => "Produksi $qty_produksi x $item_id (MK Production)",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            // Ambil/insert inventory_item_id hasil produksi
            $prodInventory = DB::table('food_inventory_items')->where('item_id', $item_id)->first();
            if (!$prodInventory) {
                $prodInventoryId = DB::table('food_inventory_items')->insertGetId([
                    'item_id' => $item_id,
                    'small_unit_id' => $unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $prodInventoryId = $prodInventory->id;
            }
            // Ambil data konversi satuan dari item hasil produksi
            $itemMaster = DB::table('items')->where('id', $item_id)->first();
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
            $qty_small = 0; $qty_medium = 0; $qty_large = 0;
            // Konversi qty_jadi ke small/medium/large sesuai unit_jadi
            if ($unit_jadi == $itemMaster->small_unit_id) {
                $qty_small = $qty_jadi;
                $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
            } elseif ($unit_jadi == $itemMaster->medium_unit_id) {
                $qty_medium = $qty_jadi;
                $qty_small = $qty_medium * $smallConv;
                $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
            } elseif ($unit_jadi == $itemMaster->large_unit_id) {
                $qty_large = $qty_jadi;
                $qty_medium = $qty_large * $mediumConv;
                $qty_small = $qty_medium * $smallConv;
            } else {
                $qty_small = $qty_jadi;
            }
            // Update/insert stok hasil produksi
            $stock = DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $prodInventoryId)
                ->where('warehouse_id', $request->warehouse_id)
                ->first();
            $oldCostProdForHistory = DB::table('food_inventory_cost_histories')
                ->where('inventory_item_id', $prodInventoryId)
                ->where('warehouse_id', $request->warehouse_id)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->value('new_cost');
            if ($oldCostProdForHistory === null && $stock) {
                $oldCostProdForHistory = $stock->last_cost_small ?? 0;
            }
            $oldCostProdForHistory = (float) ($oldCostProdForHistory ?? 0);
            $last_cost_small = 0;
            // Hitung MAC dari total cost bahan baku dibagi qty hasil produksi
            $total_bom_cost = 0;
            foreach ($bom as $b) {
                $bomInventory = $bomInventories->get($b->material_item_id);
                $bomInventoryId = $bomInventory ? $bomInventory->id : null;
                $qty_total = $b->qty * $qty_produksi;
                $stockBahan = $stocksData->get($bomInventoryId);
                $cost = $stockBahan ? ($stockBahan->last_cost_small ?? 0) : 0;
                $total_bom_cost += $qty_total * $cost;
            }
            if ($qty_jadi > 0) {
                $last_cost_small = $total_bom_cost / $qty_jadi;
            }
            // Hitung cost medium/large
            $last_cost_medium = $last_cost_small * $smallConv;
            $last_cost_large = $last_cost_medium * $mediumConv;
            if ($stock) {
                $qty_lama_small = $stock->qty_small;
                $qty_lama_medium = $stock->qty_medium ?? 0;
                $qty_lama_large = $stock->qty_large ?? 0;
                $nilai_lama = $stock->value ?? 0;
                $qty_baru_small = $qty_lama_small + $qty_small;
                $qty_baru_medium = $qty_lama_medium + $qty_medium;
                $qty_baru_large = $qty_lama_large + $qty_large;
                $nilai_baru = $nilai_lama + ($qty_small * $last_cost_small);
                $mac = $qty_baru_small > 0 ? $nilai_baru / $qty_baru_small : $last_cost_small;
            DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $prodInventoryId)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->update([
                        'qty_small' => $qty_baru_small,
                        'qty_medium' => $qty_baru_medium,
                        'qty_large' => $qty_baru_large,
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $last_cost_medium,
                        'last_cost_large' => $last_cost_large,
                        'value' => $nilai_baru,
                    ]);
            } else {
                DB::table('food_inventory_stocks')->insert([
                    'inventory_item_id' => $prodInventoryId,
                    'warehouse_id' => $request->warehouse_id,
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'last_cost_small' => $last_cost_small,
                    'last_cost_medium' => $last_cost_medium,
                    'last_cost_large' => $last_cost_large,
                    'value' => $qty_small * $last_cost_small,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            }
            // Insert mk_productions dan ambil ID-nya
            $productionId = DB::table('mk_productions')->insertGetId([
                'production_date' => $production_date,
                'batch_number' => $batch_number,
                'item_id' => $item_id,
                'qty' => $qty_produksi,
                'qty_jadi' => $qty_jadi,
                'unit_id' => $unit_id,
                'warehouse_id' => $request->warehouse_id,
                'notes' => $notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Insert kartu stok IN hasil produksi
            DB::table('food_inventory_cards')->insert([
                'inventory_item_id' => $prodInventoryId,
                'warehouse_id' => $request->warehouse_id,
                'date' => $production_date,
                'reference_type' => 'mk_production',
                'reference_id' => $productionId,
                'in_qty_small' => $qty_small,
                'in_qty_medium' => $qty_medium,
                'in_qty_large' => $qty_large,
                'cost_per_small' => $last_cost_small,
                'cost_per_medium' => $last_cost_medium,
                'cost_per_large' => $last_cost_large,
                'value_in' => $qty_small * $last_cost_small,
                'saldo_qty_small' => isset($qty_baru_small) ? $qty_baru_small : $qty_small,
                'saldo_qty_medium' => isset($qty_baru_medium) ? $qty_baru_medium : $qty_medium,
                'saldo_qty_large' => isset($qty_baru_large) ? $qty_baru_large : $qty_large,
                'saldo_value' => isset($nilai_baru) ? $nilai_baru : ($qty_small * $last_cost_small),
                'description' => "Hasil produksi $qty_jadi x $item_id (MK Production)",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $macResult = isset($mac) ? (float) $mac : (float) $last_cost_small;
            $warehouseDivisionIdProd = $itemMaster->warehouse_division_id ?? null;
            DB::table('food_inventory_cost_histories')->insert([
                'inventory_item_id' => $prodInventoryId,
                'warehouse_id' => $request->warehouse_id,
                'warehouse_division_id' => $warehouseDivisionIdProd,
                'date' => $production_date,
                'old_cost' => $oldCostProdForHistory,
                'new_cost' => $last_cost_small,
                'mac' => $macResult,
                'type' => 'mk_production',
                'reference_type' => 'mk_production',
                'reference_id' => $productionId,
                'created_at' => now(),
            ]);
            // Activity log CREATE
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'mk_production',
                'description' => 'Membuat produksi MK: ' . $productionId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode(['mk_production_id' => $productionId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::commit();
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produksi berhasil dicatat',
                    'id' => $productionId,
                ]);
            }
            return redirect()->route('mk-production.index')->with('success', 'Produksi berhasil dicatat');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function create()
    {
        $items = DB::table('items')
            ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
            ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
            ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
            ->where('items.composition_type', 'composed')
            ->where('items.status', 'active')
            ->select(
                'items.*',
                'small_unit.name as small_unit_name',
                'medium_unit.name as medium_unit_name',
                'large_unit.name as large_unit_name'
            )
            ->get();
            
        // Cari item yang memiliki BOM
        $itemsWithBom = DB::table('items')
            ->join('item_bom', 'items.id', '=', 'item_bom.item_id')
            ->where('items.composition_type', 'composed')
            ->where('items.status', 'active')
            ->select('items.id', 'items.name')
            ->distinct()
            ->get();
            
        $warehouses = DB::table('warehouses')
            ->where('status', 'active')
            ->get();
            
        return inertia('MKProduction/Create', [
            'items' => $items,
            'warehouses' => $warehouses,
            'itemsWithBom' => $itemsWithBom,
        ]);
    }

    public function show($id)
    {
        $prod = DB::table('mk_productions')->where('id', $id)->first();
        if (!$prod) {
            return redirect()->route('mk-production.index')->with('error', 'Data produksi tidak ditemukan');
        }
        
        // Join item dengan units untuk mendapatkan nama unit
        $item = DB::table('items')
            ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
            ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
            ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
            ->where('items.id', $prod->item_id)
            ->select(
                'items.*',
                'small_unit.name as small_unit_name',
                'medium_unit.name as medium_unit_name',
                'large_unit.name as large_unit_name'
            )
            ->first();
            
        $warehouse = DB::table('warehouses')->where('id', $prod->warehouse_id)->first();
        $details = [];
        
        // Ambil kartu stok hasil produksi
        $stockCard = DB::table('food_inventory_cards')
            ->where('reference_type', 'mk_production')
            ->where('reference_id', $id)
            ->get();
            
        // Ambil BOM
        $bom = DB::table('item_bom')
            ->join('items as material', 'item_bom.material_item_id', '=', 'material.id')
            ->join('units', 'item_bom.unit_id', '=', 'units.id')
            ->where('item_bom.item_id', $prod->item_id)
            ->select('item_bom.*', 'material.name as material_name', 'units.name as unit_name')
            ->get();
            
        return inertia('MKProduction/Show', [
            'prod' => $prod,
            'item' => $item,
            'warehouse' => $warehouse,
            'stockCard' => $stockCard,
            'bom' => $bom,
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $prod = DB::table('mk_productions')->where('id', $id)->first();
            if (!$prod) throw new \Exception('Data tidak ditemukan');
            // Rollback stok hasil produksi (kurangi qty hasil produksi dari stok)
            $prodInventory = DB::table('food_inventory_items')->where('item_id', $prod->item_id)->first();
            if ($prodInventory) {
                $stock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $prodInventory->id)
                    ->where('warehouse_id', $prod->warehouse_id)
                    ->first();
                if ($stock) {
                    // Ambil qty hasil produksi (qty_jadi) dan konversi ke semua satuan
                    $itemMaster = DB::table('items')->where('id', $prod->item_id)->first();
                    $smallConv = $itemMaster->small_conversion_qty ?: 1;
                    $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                    $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                    if ($prod->unit_id == $itemMaster->small_unit_id) {
                        $qty_small = $prod->qty_jadi;
                        $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                        $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    } elseif ($prod->unit_id == $itemMaster->medium_unit_id) {
                        $qty_medium = $prod->qty_jadi;
                        $qty_small = $qty_medium * $smallConv;
                        $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    } elseif ($prod->unit_id == $itemMaster->large_unit_id) {
                        $qty_large = $prod->qty_jadi;
                        $qty_medium = $qty_large * $mediumConv;
                        $qty_small = $qty_medium * $smallConv;
                    } else {
                        $qty_small = $prod->qty_jadi;
                    }
                    DB::table('food_inventory_stocks')
                        ->where('inventory_item_id', $prodInventory->id)
                        ->where('warehouse_id', $prod->warehouse_id)
                        ->update([
                            'qty_small' => $stock->qty_small - $qty_small,
                            'qty_medium' => ($stock->qty_medium ?? 0) - $qty_medium,
                            'qty_large' => ($stock->qty_large ?? 0) - $qty_large,
                        ]);
                }
            }
            // Rollback stok bahan baku (tambah kembali qty_total ke stok)
            $bom = DB::table('item_bom')->where('item_id', $prod->item_id)->get();
            foreach ($bom as $b) {
                $bomInventory = DB::table('food_inventory_items')->where('item_id', $b->material_item_id)->first();
                if ($bomInventory) {
                    $stockBahan = DB::table('food_inventory_stocks')
                        ->where('inventory_item_id', $bomInventory->id)
                        ->where('warehouse_id', $prod->warehouse_id)
                        ->first();
                    // Hitung qty_total bahan baku sesuai konversi satuan
                    $materialItem = DB::table('items')->where('id', $b->material_item_id)->first();
                    $smallConv = $materialItem->small_conversion_qty ?: 1;
                    $mediumConv = $materialItem->medium_conversion_qty ?: 1;
                    $qty_total = $b->qty * $prod->qty; // prod->qty = qty produksi
                    $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                    if ($b->unit_id == $materialItem->small_unit_id) {
                        $qty_small = $qty_total;
                        $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                        $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    } elseif ($b->unit_id == $materialItem->medium_unit_id) {
                        $qty_medium = $qty_total;
                        $qty_small = $qty_medium * $smallConv;
                        $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    } elseif ($b->unit_id == $materialItem->large_unit_id) {
                        $qty_large = $qty_total;
                        $qty_medium = $qty_large * $mediumConv;
                        $qty_small = $qty_medium * $smallConv;
                    } else {
                        $qty_small = $qty_total;
                    }
                    if ($stockBahan) {
                        DB::table('food_inventory_stocks')
                            ->where('inventory_item_id', $bomInventory->id)
                            ->where('warehouse_id', $prod->warehouse_id)
                            ->update([
                                'qty_small' => $stockBahan->qty_small + $qty_small,
                                'qty_medium' => ($stockBahan->qty_medium ?? 0) + $qty_medium,
                                'qty_large' => ($stockBahan->qty_large ?? 0) + $qty_large,
                            ]);
                    }
                }
            }
            // Hapus kartu stok terkait produksi ini
            DB::table('food_inventory_cards')
                ->where('reference_type', 'mk_production')
                ->where('reference_id', $id)
                ->delete();
            // Hapus data produksi
            DB::table('mk_productions')->where('id', $id)->delete();
            // Activity log DELETE
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'mk_production',
                'description' => 'Menghapus produksi MK: ' . $id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($prod),
                'new_data' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function serialSummary($id)
    {
        $total = DB::table('inventory_item_serials')
            ->where('source_type', 'mk_production')
            ->where('source_id', $id)
            ->count();

        return response()->json([
            'total' => $total,
        ]);
    }

    public function serialList($id)
    {
        $rows = DB::table('inventory_item_serials as s')
            ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
            ->select(
                's.id',
                's.serial_number',
                's.ref_pr_number',
                's.ref_po_number',
                's.ref_gr_number',
                's.generated_at',
                'u.name as unit_name'
            )
            ->where('s.source_type', 'mk_production')
            ->where('s.source_id', $id)
            ->orderBy('s.id')
            ->get();

        return response()->json($rows);
    }

    public function rollbackSerials($id)
    {
        $deleted = DB::table('inventory_item_serials')
            ->where('source_type', 'mk_production')
            ->where('source_id', $id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Rollback serial MK Production berhasil. Terhapus: {$deleted}",
            'deleted' => $deleted,
        ]);
    }

    public function generateSerials($id)
    {
        $prod = DB::table('mk_productions as mp')
            ->join('items as i', 'i.id', '=', 'mp.item_id')
            ->select(
                'mp.id',
                'mp.item_id',
                'mp.unit_id',
                'mp.qty_jadi',
                'mp.warehouse_id',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->where('mp.id', $id)
            ->first();

        if (!$prod) {
            return response()->json(['message' => 'Data MK Production tidak ditemukan'], 404);
        }

        $qtyIn = (float) ($prod->qty_jadi ?: 0);
        $serialCount = (int) round($qtyIn);
        if ($serialCount <= 0 || abs($qtyIn - $serialCount) > 0.00001) {
            return response()->json([
                'message' => 'Qty In (qty jadi) harus bilangan bulat positif agar bisa generate serial.',
                'qty_in' => round($qtyIn, 4),
            ], 422);
        }

        $inventoryItemId = DB::table('food_inventory_items')
            ->where('item_id', $prod->item_id)
            ->value('id');

        $card = DB::table('food_inventory_cards')
            ->where('reference_type', 'mk_production')
            ->where('reference_id', $prod->id)
            ->where('warehouse_id', $prod->warehouse_id)
            ->where('in_qty_small', '>', 0)
            ->orderByDesc('id')
            ->first();

        $smallConv = (float) ($prod->small_conversion_qty ?: 1);
        $mediumConv = (float) ($prod->medium_conversion_qty ?: 1);

        $costSmall = (float) ($card->cost_per_small ?? 0);
        $costMedium = (float) ($card->cost_per_medium ?? ($costSmall * $smallConv));
        $costLarge = (float) ($card->cost_per_large ?? ($costMedium * $mediumConv));

        DB::beginTransaction();
        try {
            DB::table('inventory_item_serials')
                ->where('source_type', 'mk_production')
                ->where('source_id', $prod->id)
                ->delete();

            $now = now();
            $rows = [];
            for ($i = 0; $i < $serialCount; $i++) {
                $rows[] = [
                    'source_type' => 'mk_production',
                    'source_id' => $prod->id,
                    'source_item_id' => $prod->id,
                    'warehouse_id' => $prod->warehouse_id,
                    'inventory_item_id' => $inventoryItemId,
                    'item_id' => $prod->item_id,
                    'unit_id' => $prod->unit_id,
                    'serial_number' => $this->generateUniqueSerialNumber(),
                    'source_qty' => $qtyIn,
                    'source_unit_id' => $prod->unit_id,
                    'generated_qty_unit' => $qtyIn,
                    'cost_small' => $costSmall,
                    'cost_medium' => $costMedium,
                    'cost_large' => $costLarge,
                    'generated_by' => Auth::id(),
                    'generated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('inventory_item_serials')->insert($rows);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil generate {$serialCount} serial MK Production.",
                'total' => $serialCount,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function report(Request $request)
    {
        $query = DB::table('mk_productions')
            ->leftJoin('items', 'mk_productions.item_id', '=', 'items.id')
            ->leftJoin('warehouses', 'mk_productions.warehouse_id', '=', 'warehouses.id')
            ->select(
                'mk_productions.*',
                'items.name as item_name',
                'items.exp as item_exp',
                'warehouses.name as warehouse_name'
            )
            ->orderByDesc('mk_productions.production_date');

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('mk_productions.production_date', [$request->start_date, $request->end_date]);
        }

        $productions = $query->get();

        // Hitung exp_date di backend
        $productions = $productions->map(function ($row) {
            $exp_days = $row->item_exp ?? 0;
            $prod_date = $row->production_date;
            $exp_date = $prod_date ? (\Carbon\Carbon::parse($prod_date)->addDays($exp_days)->toDateString()) : null;
            $row->exp_date = $exp_date;
            return $row;
        });

        return inertia('MKProduction/Report', [
            'productions' => $productions,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);
    }

    public function testBomData()
    {
        // Test 1: Cek item yang memiliki composition_type = 'composed'
        $composedItems = DB::table('items')
            ->where('composition_type', 'composed')
            ->where('status', 'active')
            ->select('id', 'name', 'composition_type')
            ->get();
            
        // Test 2: Cek data BOM
        $bomData = DB::table('item_bom')
            ->join('items as parent', 'item_bom.item_id', '=', 'parent.id')
            ->join('items as material', 'item_bom.material_item_id', '=', 'material.id')
            ->select(
                'item_bom.item_id',
                'parent.name as parent_name',
                'item_bom.material_item_id',
                'material.name as material_name',
                'item_bom.qty',
                'item_bom.unit_id'
            )
            ->get();
            
        // Test 3: Cek warehouse
        $warehouses = DB::table('warehouses')
            ->where('status', 'active')
            ->select('id', 'name', 'status')
            ->get();
            
        return response()->json([
            'composed_items_count' => $composedItems->count(),
            'bom_data_count' => $bomData->count(),
            'warehouses_count' => $warehouses->count(),
            'composed_items' => $composedItems,
            'bom_data' => $bomData,
            'warehouses' => $warehouses
        ]);
    }

    private function generateUniqueSerialNumber(): string
    {
        $prefix = now()->format('ymdHi');

        for ($i = 0; $i < 10; $i++) {
            $serial = $prefix . strtoupper(Str::random(4));
            $exists = DB::table('inventory_item_serials')
                ->where('serial_number', $serial)
                ->exists();
            if (!$exists) {
                return $serial;
            }
        }

        return $prefix . strtoupper(Str::random(6));
    }
} 