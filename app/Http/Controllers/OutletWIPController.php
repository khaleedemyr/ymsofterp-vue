<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OutletWIPController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Ambil item hasil produksi (composed & aktif)
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

        // Ambil warehouse outlets berdasarkan user
        if ($user->id_outlet == 1) {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        } else {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('outlet_id', $user->id_outlet)
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        }

        // Ambil histori produksi WIP
        $query = DB::table('outlet_wip_productions')
            ->leftJoin('items', 'outlet_wip_productions.item_id', '=', 'items.id')
            ->leftJoin('units', 'outlet_wip_productions.unit_id', '=', 'units.id')
            ->leftJoin('users', 'outlet_wip_productions.created_by', '=', 'users.id')
            ->leftJoin('tbl_data_outlet as o', 'outlet_wip_productions.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'outlet_wip_productions.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'outlet_wip_productions.*',
                'items.name as item_name',
                'units.name as unit_name',
                'users.nama_lengkap as created_by_name',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name'
            );

        // Filter berdasarkan outlet user
        if ($user->id_outlet != 1) {
            $query->where('outlet_wip_productions.outlet_id', $user->id_outlet);
        }

        $productions = $query->orderByDesc('outlet_wip_productions.production_date')
            ->orderByDesc('outlet_wip_productions.id')
            ->paginate(10);

        return Inertia::render('OutletWIP/Index', [
            'items' => $items,
            'warehouse_outlets' => $warehouse_outlets,
            'productions' => $productions,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        
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

        // Ambil warehouse outlets berdasarkan user
        if ($user->id_outlet == 1) {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        } else {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('outlet_id', $user->id_outlet)
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        }

        // Ambil outlets untuk superuser
        $outlets = [];
        if ($user->id_outlet == 1) {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();
        }

        return Inertia::render('OutletWIP/Create', [
            'items' => $items,
            'warehouse_outlets' => $warehouse_outlets,
            'outlets' => $outlets,
            'user_outlet_id' => $user->id_outlet,
        ]);
    }

    public function getBomAndStock(Request $request)
    {
        $item_id = $request->input('item_id');
        $qty = $request->input('qty');
        $outlet_id = $request->input('outlet_id');
        $warehouse_outlet_id = $request->input('warehouse_outlet_id');

        if (!$item_id || !$qty || !$outlet_id || !$warehouse_outlet_id) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        // Ambil BOM untuk item tersebut
        $bom = DB::table('item_bom')
            ->leftJoin('items as material', 'item_bom.material_item_id', '=', 'material.id')
            ->leftJoin('units as material_unit', 'item_bom.unit_id', '=', 'material_unit.id')
            ->where('item_bom.item_id', $item_id)
            ->select(
                'item_bom.*',
                'material.name as material_name',
                'material_unit.name as material_unit_name'
            )
            ->get();

        $result = [];
        foreach ($bom as $b) {
            // Cari inventory item untuk material
            $inventoryItem = DB::table('outlet_food_inventory_items')
                ->where('item_id', $b->material_item_id)
                ->first();

            $stock = 0;
            $stock_medium = 0;
            $stock_large = 0;
            $last_cost_small = 0;
            $last_cost_medium = 0;
            $last_cost_large = 0;

            if ($inventoryItem) {
                $stockData = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('id_outlet', $outlet_id)
                    ->where('warehouse_outlet_id', $warehouse_outlet_id)
                    ->first();

                if ($stockData) {
                    $stock = $stockData->qty_small;
                    $stock_medium = $stockData->qty_medium;
                    $stock_large = $stockData->qty_large;
                    $last_cost_small = $stockData->last_cost_small;
                    $last_cost_medium = $stockData->last_cost_medium;
                    $last_cost_large = $stockData->last_cost_large;
                }
            }

            // Hitung qty yang dibutuhkan
            $qty_needed = $b->qty * $qty;

            $result[] = [
                'material_item_id' => $b->material_item_id,
                'material_name' => $b->material_name,
                'qty' => $b->qty,
                'qty_needed' => $qty_needed,
                'unit_id' => $b->unit_id,
                'material_unit_name' => $b->material_unit_name,
                'stock' => $stock,
                'stock_medium' => $stock_medium,
                'stock_large' => $stock_large,
                'last_cost_small' => $last_cost_small,
                'last_cost_medium' => $last_cost_medium,
                'last_cost_large' => $last_cost_large,
                'sufficient' => $stock >= $qty_needed
            ];
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        Log::info('[OutletWIP] Payload request', $request->all());
        
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'qty' => 'required|numeric|min:0',
            'qty_jadi' => 'required|numeric|min:0',
            'unit_id' => 'required|exists:units,id',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'warehouse_outlet_id' => 'required|exists:warehouse_outlets,id',
            'production_date' => 'required|date',
            'batch_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $item_id = $request->input('item_id');
        $qty_produksi = $request->input('qty');
        $qty_jadi = $request->input('qty_jadi');
        $unit_jadi = $request->input('unit_id');
        $production_date = $request->input('production_date');
        $batch_number = $request->input('batch_number');
        $notes = $request->input('notes');
        $outlet_id = $request->input('outlet_id');
        $warehouse_outlet_id = $request->input('warehouse_outlet_id');

        $bom = DB::table('item_bom')->where('item_id', $item_id)->get();
        Log::info('[OutletWIP] BOM', ['bom' => $bom]);

        // Validasi stok cukup
        foreach ($bom as $b) {
            $bomInventory = DB::table('outlet_food_inventory_items')->where('item_id', $b->material_item_id)->first();
            $bomInventoryId = $bomInventory ? $bomInventory->id : null;
            $stok = 0;
            if ($bomInventoryId) {
                $stok = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $bomInventoryId)
                    ->where('id_outlet', $outlet_id)
                    ->where('warehouse_outlet_id', $warehouse_outlet_id)
                    ->value('qty_small');
            }
            $qty_total = $b->qty * $qty_produksi;
            Log::info('[OutletWIP] Validasi stok', ['material_item_id' => $b->material_item_id, 'stok' => $stok, 'qty_total' => $qty_total]);
            if ($stok < $qty_total) {
                Log::warning('[OutletWIP] Stok bahan tidak cukup', ['material_item_id' => $b->material_item_id]);
                return back()->with('error', "Stok bahan tidak cukup");
            }
        }

        DB::beginTransaction();
        try {
            // Proses pengurangan stok bahan baku
            foreach ($bom as $b) {
                $bomInventory = DB::table('outlet_food_inventory_items')->where('item_id', $b->material_item_id)->first();
                if (!$bomInventory) {
                    throw new \Exception("Item bahan baku tidak ditemukan di inventory outlet");
                }

                $stock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $bomInventory->id)
                    ->where('id_outlet', $outlet_id)
                    ->where('warehouse_outlet_id', $warehouse_outlet_id)
                    ->first();

                if (!$stock) {
                    throw new \Exception("Stok bahan baku tidak ditemukan");
                }

                $itemMaster = DB::table('items')->where('id', $b->material_item_id)->first();
                $unit = DB::table('units')->where('id', $b->unit_id)->value('name');
                $qty_input = $b->qty * $qty_produksi;
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

                // Update stok bahan baku (kurangi)
                DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $bomInventory->id)
                    ->where('id_outlet', $outlet_id)
                    ->where('warehouse_outlet_id', $warehouse_outlet_id)
                    ->update([
                        'qty_small' => $stock->qty_small - $qty_small,
                        'qty_medium' => $stock->qty_medium - $qty_medium,
                        'qty_large' => $stock->qty_large - $qty_large,
                        'updated_at' => now(),
                    ]);

                // Insert kartu stok OUT untuk bahan baku
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $bomInventory->id,
                    'id_outlet' => $outlet_id,
                    'warehouse_outlet_id' => $warehouse_outlet_id,
                    'date' => $production_date,
                    'reference_type' => 'outlet_wip_production',
                    'reference_id' => 0, // Akan diupdate setelah insert header
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
                    'description' => 'Stock Out - WIP Production',
                    'created_at' => now(),
                ]);
            }

            // Cari atau buat inventory item untuk hasil produksi
            $prodInventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item_id)->first();
            if (!$prodInventoryItem) {
                $itemMaster = DB::table('items')->where('id', $item_id)->first();
                $prodInventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                    'item_id' => $item_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $prodInventoryItemId = $prodInventoryItem->id;
            }

            // Hitung qty hasil produksi dalam unit kecil
            $itemMaster = DB::table('items')->where('id', $item_id)->first();
            $unit = DB::table('units')->where('id', $unit_jadi)->value('name');
            $qty_small = 0;
            $qty_medium = 0;
            $qty_large = 0;

            $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
            $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
            $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

            if ($unit === $unitSmall) {
                $qty_small = $qty_jadi;
                $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
            } elseif ($unit === $unitMedium) {
                $qty_medium = $qty_jadi;
                $qty_small = $qty_medium * $smallConv;
                $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
            } elseif ($unit === $unitLarge) {
                $qty_large = $qty_jadi;
                $qty_medium = $qty_large * $mediumConv;
                $qty_small = $qty_medium * $smallConv;
            } else {
                $qty_small = $qty_jadi;
            }

            // Hitung cost hasil produksi (rata-rata cost bahan baku)
            $total_cost = 0;
            $total_qty_small = 0;
            foreach ($bom as $b) {
                $bomInventory = DB::table('outlet_food_inventory_items')->where('item_id', $b->material_item_id)->first();
                if ($bomInventory) {
                    $stock = DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $bomInventory->id)
                        ->where('id_outlet', $outlet_id)
                        ->where('warehouse_outlet_id', $warehouse_outlet_id)
                        ->first();
                    if ($stock) {
                        $itemMaster = DB::table('items')->where('id', $b->material_item_id)->first();
                        $unit = DB::table('units')->where('id', $b->unit_id)->value('name');
                        $qty_input = $b->qty * $qty_produksi;
                        $qty_small_bahan = 0;

                        $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                        $smallConv = $itemMaster->small_conversion_qty ?: 1;

                        if ($unit === $unitSmall) {
                            $qty_small_bahan = $qty_input;
                        } elseif ($unit === $unitMedium) {
                            $qty_small_bahan = $qty_input * $smallConv;
                        } elseif ($unit === $unitLarge) {
                            $qty_small_bahan = $qty_input * $smallConv * $mediumConv;
                        } else {
                            $qty_small_bahan = $qty_input;
                        }

                        $total_cost += $qty_small_bahan * $stock->last_cost_small;
                        $total_qty_small += $qty_small_bahan;
                    }
                }
            }

            $last_cost_small = $total_qty_small > 0 ? $total_cost / $total_qty_small : 0;
            $last_cost_medium = $smallConv > 0 ? $last_cost_small / $smallConv : 0;
            $last_cost_large = ($smallConv > 0 && $mediumConv > 0) ? $last_cost_small / ($smallConv * $mediumConv) : 0;

            // Cek stok hasil produksi yang sudah ada
            $existingStock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $prodInventoryItemId)
                ->where('id_outlet', $outlet_id)
                ->where('warehouse_outlet_id', $warehouse_outlet_id)
                ->first();

            if ($existingStock) {
                // Update stok yang sudah ada
                $qty_baru_small = $existingStock->qty_small + $qty_small;
                $qty_baru_medium = $existingStock->qty_medium + $qty_medium;
                $qty_baru_large = $existingStock->qty_large + $qty_large;
                $nilai_baru = $qty_baru_small * $last_cost_small;

                DB::table('outlet_food_inventory_stocks')
                    ->where('id', $existingStock->id)
                    ->update([
                        'qty_small' => $qty_baru_small,
                        'qty_medium' => $qty_baru_medium,
                        'qty_large' => $qty_baru_large,
                        'value' => $nilai_baru,
                        'last_cost_small' => $last_cost_small,
                        'last_cost_medium' => $last_cost_medium,
                        'last_cost_large' => $last_cost_large,
                        'updated_at' => now(),
                    ]);
            } else {
                // Insert stok baru
                DB::table('outlet_food_inventory_stocks')->insert([
                    'inventory_item_id' => $prodInventoryItemId,
                    'id_outlet' => $outlet_id,
                    'warehouse_outlet_id' => $warehouse_outlet_id,
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'value' => $qty_small * $last_cost_small,
                    'last_cost_small' => $last_cost_small,
                    'last_cost_medium' => $last_cost_medium,
                    'last_cost_large' => $last_cost_large,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert outlet_wip_productions dan ambil ID-nya
            $productionId = DB::table('outlet_wip_productions')->insertGetId([
                'production_date' => $production_date,
                'batch_number' => $batch_number,
                'item_id' => $item_id,
                'qty' => $qty_produksi,
                'qty_jadi' => $qty_jadi,
                'unit_id' => $unit_jadi,
                'outlet_id' => $outlet_id,
                'warehouse_outlet_id' => $warehouse_outlet_id,
                'notes' => $notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update reference_id di kartu stok bahan baku
            DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'outlet_wip_production')
                ->where('reference_id', 0)
                ->update(['reference_id' => $productionId]);

            // Insert kartu stok IN hasil produksi
            DB::table('outlet_food_inventory_cards')->insert([
                'inventory_item_id' => $prodInventoryItemId,
                'id_outlet' => $outlet_id,
                'warehouse_outlet_id' => $warehouse_outlet_id,
                'date' => $production_date,
                'reference_type' => 'outlet_wip_production',
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
                'description' => "Hasil produksi WIP $qty_jadi x $item_id",
                'created_at' => now(),
            ]);

            // Activity log CREATE
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'outlet_wip_production',
                'description' => 'Membuat produksi WIP outlet: ' . $productionId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode(['outlet_wip_production_id' => $productionId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            Log::info('[OutletWIP] Commit transaksi sukses');
            return redirect()->route('outlet-wip.index')->with('success', 'Produksi WIP berhasil dicatat');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[OutletWIP] ERROR', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        
        $prod = DB::table('outlet_wip_productions')->where('id', $id)->first();
        if (!$prod) {
            return redirect()->route('outlet-wip.index')->with('error', 'Data produksi tidak ditemukan');
        }

        // Cek akses berdasarkan outlet
        if ($user->id_outlet != 1 && $prod->outlet_id != $user->id_outlet) {
            return redirect()->route('outlet-wip.index')->with('error', 'Tidak memiliki akses ke data ini');
        }

        $item = DB::table('items')->where('id', $prod->item_id)->first();
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $prod->outlet_id)->first();
        $warehouse_outlet = DB::table('warehouse_outlets')->where('id', $prod->warehouse_outlet_id)->first();

        // Ambil kartu stok hasil produksi
        $stockCard = DB::table('outlet_food_inventory_cards')
            ->where('reference_type', 'outlet_wip_production')
            ->where('reference_id', $id)
            ->get();

        // Ambil BOM
        $bom = DB::table('item_bom')
            ->join('items as material', 'item_bom.material_item_id', '=', 'material.id')
            ->join('units', 'item_bom.unit_id', '=', 'units.id')
            ->where('item_bom.item_id', $prod->item_id)
            ->select('item_bom.*', 'material.name as material_name', 'units.name as unit_name')
            ->get();

        return Inertia::render('OutletWIP/Show', [
            'prod' => $prod,
            'item' => $item,
            'outlet' => $outlet,
            'warehouse_outlet' => $warehouse_outlet,
            'stockCard' => $stockCard,
            'bom' => $bom,
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        
        DB::beginTransaction();
        try {
            $prod = DB::table('outlet_wip_productions')->where('id', $id)->first();
            if (!$prod) {
                return response()->json(['success' => false, 'message' => 'Data produksi tidak ditemukan'], 404);
            }

            // Cek akses berdasarkan outlet
            if ($user->id_outlet != 1 && $prod->outlet_id != $user->id_outlet) {
                return response()->json(['success' => false, 'message' => 'Tidak memiliki akses ke data ini'], 403);
            }

            // Hapus kartu stok terkait
            DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'outlet_wip_production')
                ->where('reference_id', $id)
                ->delete();

            // Hapus data produksi
            DB::table('outlet_wip_productions')->where('id', $id)->delete();

            // Activity log DELETE
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'outlet_wip_production',
                'description' => 'Menghapus produksi WIP outlet: ' . $id,
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

    public function report(Request $request)
    {
        $user = auth()->user();
        
        $query = DB::table('outlet_wip_productions')
            ->leftJoin('items', 'outlet_wip_productions.item_id', '=', 'items.id')
            ->leftJoin('tbl_data_outlet as o', 'outlet_wip_productions.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'outlet_wip_productions.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'outlet_wip_productions.*',
                'items.name as item_name',
                'items.exp as item_exp',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name'
            );

        // Filter berdasarkan outlet user
        if ($user->id_outlet != 1) {
            $query->where('outlet_wip_productions.outlet_id', $user->id_outlet);
        }

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('outlet_wip_productions.production_date', [$request->start_date, $request->end_date]);
        }

        $productions = $query->orderByDesc('outlet_wip_productions.production_date')->get();

        // Hitung exp_date di backend
        $productions = $productions->map(function ($row) {
            $exp_days = $row->item_exp ?? 0;
            $prod_date = $row->production_date;
            $exp_date = $prod_date ? (\Carbon\Carbon::parse($prod_date)->addDays($exp_days)->toDateString()) : null;
            $row->exp_date = $exp_date;
            return $row;
        });

        return Inertia::render('OutletWIP/Report', [
            'productions' => $productions,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);
    }
}
