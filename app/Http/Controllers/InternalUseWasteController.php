<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InternalUseWasteController extends Controller
{
    public function index()
    {
        $data = DB::table('internal_use_wastes')
            ->leftJoin('warehouses', 'internal_use_wastes.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('items', 'internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'internal_use_wastes.unit_id', '=', 'units.id')
            ->select(
                'internal_use_wastes.*',
                'warehouses.name as warehouse_name',
                'items.name as item_name',
                'units.name as unit_name'
            )
            ->orderByDesc('internal_use_wastes.date')
            ->orderByDesc('internal_use_wastes.id')
            ->get();
        return inertia('InternalUseWaste/Index', [
            'data' => $data
        ]);
    }
    public function create()
    {
        $warehouses = DB::table('warehouses')->where('status', 'active')->get();
        $items = DB::table('items')->where('status', 'active')->get();
        $units = DB::table('units')->get();
        return inertia('InternalUseWaste/Create', [
            'warehouses' => $warehouses,
            'items' => $items,
            'units' => $units,
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:internal_use,spoil,waste',
            'date' => 'required|date',
            'warehouse_id' => 'required|integer',
            'item_id' => 'required|integer',
            'qty' => 'required|numeric',
            'unit_id' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Simpan header internal use waste
            $internalUseWasteId = DB::table('internal_use_wastes')->insertGetId([
                'type' => $request->type,
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'item_id' => $request->item_id,
                'qty' => $request->qty,
                'unit_id' => $request->unit_id,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Cari inventory_item_id
            $inventoryItem = DB::table('food_inventory_items')->where('item_id', $request->item_id)->first();
            if (!$inventoryItem) throw new \Exception('Inventory item not found for item_id: ' . $request->item_id);
            $inventory_item_id = $inventoryItem->id;

            // Ambil data konversi dari tabel items
            $itemMaster = DB::table('items')->where('id', $request->item_id)->first();
            $unit = DB::table('units')->where('id', $request->unit_id)->value('name');
            $qty_input = $request->qty;
            $qty_small = 0;
            $qty_medium = 0;
            $qty_large = 0;

            // Ambil nama unit dari master
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

            // Cek stok tersedia
            $stock = DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->first();
            if (!$stock) throw new \Exception('Stok tidak ditemukan di gudang');

            // Validasi qty tidak melebihi stok
            if ($qty_small > $stock->qty_small) {
                throw new \Exception("Qty melebihi stok yang tersedia. Stok tersedia: {$stock->qty_small} {$unitSmall}");
            }

            // Update stok di warehouse (kurangi)
            DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->update([
                    'qty_small' => $stock->qty_small - $qty_small,
                    'qty_medium' => $stock->qty_medium - $qty_medium,
                    'qty_large' => $stock->qty_large - $qty_large,
                    'updated_at' => now(),
                ]);

            // Insert kartu stok OUT
            DB::table('food_inventory_cards')->insert([
                'inventory_item_id' => $inventory_item_id,
                'warehouse_id' => $request->warehouse_id,
                'date' => $request->date,
                'reference_type' => 'internal_use_waste',
                'reference_id' => $internalUseWasteId,
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
                'description' => 'Stock Out - ' . $request->type,
                'created_at' => now(),
            ]);

            DB::commit();
            // Activity log CREATE
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'internal_use_waste',
                'description' => 'Membuat internal use/waste: ' . $internalUseWasteId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode(['internal_use_waste_id' => $internalUseWasteId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return redirect()->route('internal-use-waste.index')->with('success', 'Data berhasil disimpan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }
    public function show($id)
    {
        return inertia('InternalUseWaste/Show', ['id' => $id]);
    }
    public function destroy($id)
    {
        $data = DB::table('internal_use_wastes')->where('id', $id)->first();
        DB::table('internal_use_wastes')->where('id', $id)->delete();
        // Activity log DELETE
        DB::table('activity_logs')->insert([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'internal_use_waste',
            'description' => 'Menghapus internal use/waste: ' . $id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => json_encode($data),
            'new_data' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true]);
    }
    public function getItemUnits($itemId)
    {
        $item = DB::table('items')->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['units' => []]);
        }

        $units = [];
        if ($item->small_unit_id) {
            $units[] = [
                'id' => $item->small_unit_id,
                'name' => DB::table('units')->where('id', $item->small_unit_id)->value('name')
            ];
        }
        if ($item->medium_unit_id) {
            $units[] = [
                'id' => $item->medium_unit_id,
                'name' => DB::table('units')->where('id', $item->medium_unit_id)->value('name')
            ];
        }
        if ($item->large_unit_id) {
            $units[] = [
                'id' => $item->large_unit_id,
                'name' => DB::table('units')->where('id', $item->large_unit_id)->value('name')
            ];
        }

        return response()->json(['units' => $units]);
    }
} 