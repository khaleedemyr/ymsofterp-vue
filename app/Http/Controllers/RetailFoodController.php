<?php

namespace App\Http\Controllers;

use App\Models\RetailFood;
use App\Models\RetailFoodItem;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RetailFoodController extends Controller
{
    private function generateRetailNumber()
    {
        $prefix = 'RF';
        $date = date('Ymd');
        
        // Cari nomor terakhir hari ini
        $lastNumber = RetailFood::where('retail_number', 'like', $prefix . $date . '%')
            ->orderBy('retail_number', 'desc')
            ->first();
            
        if ($lastNumber) {
            $sequence = (int) substr($lastNumber->retail_number, -4) + 1;
        } else {
            $sequence = 1;
        }
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function index()
    {
        $user = auth()->user()->load('outlet');
        // Validasi outlet user
        $userOutletId = $user->id_outlet;
        $outletExists = \DB::table('tbl_data_outlet')->where('id_outlet', $userOutletId)->exists();
        if (!$outletExists && $userOutletId != 1) {
            abort(403, 'Outlet tidak terdaftar');
        }
        $query = RetailFood::with(['outlet', 'creator', 'items'])->orderByDesc('created_at');
        if ($userOutletId != 1) {
            $query->where('outlet_id', $userOutletId);
        }
        $retailFoods = $query->paginate(10);
        return Inertia::render('RetailFood/Index', [
            'user' => $user,
            'retailFoods' => $retailFoods,
        ]);
    }

    public function create()
    {
        $user = auth()->user()->load('outlet');
        $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);
        return Inertia::render('RetailFood/Form', [
            'user' => $user,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'transaction_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0',
            'items.*.unit' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Generate nomor retail food
            $retailNumber = $this->generateRetailNumber();

            // Hitung total amount
            $totalAmount = collect($request->items)->sum(function ($item) {
                return $item['qty'] * $item['price'];
            });

            // Cek total transaksi hari ini
            $dailyTotal = RetailFood::whereDate('transaction_date', $request->transaction_date)
                ->where('status', 'approved')
                ->sum('total_amount');

            // Buat retail food
            $retailFood = RetailFood::create([
                'retail_number' => $retailNumber,
                'outlet_id' => $request->outlet_id,
                'created_by' => auth()->id(),
                'transaction_date' => $request->transaction_date,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'status' => 'draft'
            ]);

            // Simpan items dan proses inventory outlet
            foreach ($request->items as $item) {
                // 1. Cari item master
                $itemMaster = DB::table('items')->where('name', $item['item_name'])->first();
                if (!$itemMaster) {
                    throw new \Exception('Item tidak ditemukan: ' . $item['item_name']);
                }
                // 2. Cek/insert outlet_food_inventory_items
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $itemMaster->id)
                    ->first();
                if (!$inventoryItem) {
                    $inventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                        'item_id' => $itemMaster->id,
                        'small_unit_id' => $itemMaster->small_unit_id,
                        'medium_unit_id' => $itemMaster->medium_unit_id,
                        'large_unit_id' => $itemMaster->large_unit_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $inventoryItemId = $inventoryItem->id;
                }
                // 3. Konversi qty ke small, medium, large
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                $qty_small = 0; $qty_medium = 0; $qty_large = 0; $qty_small_for_value = 0;
                if ($item['unit_id'] == $itemMaster->large_unit_id) {
                    $qty_large = (float) $item['qty'];
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_small_for_value = $qty_small;
                } elseif ($item['unit_id'] == $itemMaster->medium_unit_id) {
                    $qty_medium = (float) $item['qty'];
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_small_for_value = $qty_small;
                } elseif ($item['unit_id'] == $itemMaster->small_unit_id) {
                    $qty_small = (float) $item['qty'];
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    $qty_small_for_value = $qty_small;
                }
                // 4. Hitung cost
                $cost = $item['price'];
                $cost_small = $cost;
                if ($item['unit_id'] == $itemMaster->large_unit_id) {
                    $cost_small = $cost / (($itemMaster->small_conversion_qty ?: 1) * ($itemMaster->medium_conversion_qty ?: 1));
                } elseif ($item['unit_id'] == $itemMaster->medium_unit_id) {
                    $cost_small = $cost / ($itemMaster->small_conversion_qty ?: 1);
                }
                $cost_medium = $cost_small * ($itemMaster->small_conversion_qty ?: 1);
                $cost_large = $cost_medium * ($itemMaster->medium_conversion_qty ?: 1);
                // 5. Insert/update outlet_food_inventory_stocks (MAC)
                $existingStock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $request->outlet_id)
                    ->first();
                $qty_lama = $existingStock ? $existingStock->qty_small : 0;
                $nilai_lama = $existingStock ? $existingStock->value : 0;
                $qty_baru = $qty_small;
                $nilai_baru = $qty_small_for_value * $cost_small;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $cost_small;
                if ($existingStock) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $existingStock->id)
                        ->update([
                            'qty_small' => $total_qty,
                            'qty_medium' => $existingStock->qty_medium + $qty_medium,
                            'qty_large' => $existingStock->qty_large + $qty_large,
                            'value' => $total_nilai,
                            'last_cost_small' => $mac,
                            'last_cost_medium' => $cost_medium,
                            'last_cost_large' => $cost_large,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'id_outlet' => $request->outlet_id,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $nilai_baru,
                        'last_cost_small' => $cost_small,
                        'last_cost_medium' => $cost_medium,
                        'last_cost_large' => $cost_large,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                // 6. Hitung saldo kartu stok (stock card)
                $lastCard = DB::table('outlet_food_inventory_cards')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $request->outlet_id)
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->first();
                if ($lastCard) {
                    $saldo_qty_small = $lastCard->saldo_qty_small + $qty_small;
                    $saldo_qty_medium = $lastCard->saldo_qty_medium + $qty_medium;
                    $saldo_qty_large = $lastCard->saldo_qty_large + $qty_large;
                } else {
                    $saldo_qty_small = $qty_small;
                    $saldo_qty_medium = $qty_medium;
                    $saldo_qty_large = $qty_large;
                }
                // 7. Insert ke outlet_food_inventory_cards
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $request->outlet_id,
                    'date' => $request->transaction_date,
                    'reference_type' => 'retail_food',
                    'reference_id' => $retailFood->id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'out_qty_small' => 0,
                    'out_qty_medium' => 0,
                    'out_qty_large' => 0,
                    'cost_per_small' => $cost_small,
                    'cost_per_medium' => $cost_medium,
                    'cost_per_large' => $cost_large,
                    'value_in' => $qty_small_for_value * $cost_small,
                    'value_out' => 0,
                    'saldo_qty_small' => $saldo_qty_small,
                    'saldo_qty_medium' => $saldo_qty_medium,
                    'saldo_qty_large' => $saldo_qty_large,
                    'saldo_value' => ($existingStock ? ($existingStock->qty_small + $qty_small) : $qty_small) * $cost_small,
                    'description' => 'Retail Food: ' . $retailNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                // 8. Insert ke outlet_food_inventory_cost_histories
                $lastCostHistory = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $request->outlet_id)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $request->outlet_id,
                    'date' => $request->transaction_date,
                    'old_cost' => $old_cost,
                    'new_cost' => $cost_small,
                    'mac' => $mac,
                    'type' => 'retail_food',
                    'reference_type' => 'retail_food',
                    'reference_id' => $retailFood->id,
                    'created_at' => now(),
                ]);
                // 9. Simpan item retail food
                RetailFoodItem::create([
                    'retail_food_id' => $retailFood->id,
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price']
                ]);
            }

            DB::commit();

            // Cek apakah total hari ini sudah melebihi 500rb
            if ($dailyTotal + $totalAmount >= 500000) {
                return response()->json([
                    'message' => 'Transaksi berhasil disimpan, namun total pembelian hari ini sudah melebihi Rp 500.000',
                    'data' => $retailFood->load('items')
                ], 201);
            }

            return response()->json([
                'message' => 'Transaksi berhasil disimpan',
                'data' => $retailFood->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan transaksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $retailFood = RetailFood::with(['outlet', 'creator', 'items'])
            ->findOrFail($id);

        return Inertia::render('RetailFood/Detail', [
            'retailFood' => $retailFood
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $retailFood = RetailFood::with('items')->findOrFail($id);
            if ($retailFood->status === 'approved') {
                return response()->json([
                    'message' => 'Tidak dapat menghapus transaksi yang sudah diapprove'
                ], 422);
            }
            $outletId = $retailFood->outlet_id;
            foreach ($retailFood->items as $item) {
                $itemMaster = DB::table('items')->where('name', $item->item_name)->first();
                if (!$itemMaster) continue;
                $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $itemMaster->id)->first();
                if (!$inventoryItem) continue;
                // Konversi qty ke small, medium, large
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                if ($item->unit == DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name')) {
                    $qty_large = (float) $item->qty;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } elseif ($item->unit == DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name')) {
                    $qty_medium = (float) $item->qty;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    $qty_small = $qty_medium * $smallConv;
                } elseif ($item->unit == DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name')) {
                    $qty_small = (float) $item->qty;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                }
                // Rollback stok
                $stock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('id_outlet', $outletId)
                    ->first();
                if ($stock) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => $stock->qty_small - $qty_small,
                            'qty_medium' => $stock->qty_medium - $qty_medium,
                            'qty_large' => $stock->qty_large - $qty_large,
                            'value' => $stock->value - ($qty_small * $item->price),
                            'updated_at' => now(),
                        ]);
                }
                // Hapus kartu stok terkait transaksi ini
                DB::table('outlet_food_inventory_cards')
                    ->where('reference_type', 'retail_food')
                    ->where('reference_id', $retailFood->id)
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('id_outlet', $outletId)
                    ->delete();
                // Hapus cost history terkait transaksi ini
                DB::table('outlet_food_inventory_cost_histories')
                    ->where('reference_type', 'retail_food')
                    ->where('reference_id', $retailFood->id)
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('id_outlet', $outletId)
                    ->delete();
            }
            // Hapus retail food dan items
            RetailFoodItem::where('retail_food_id', $retailFood->id)->delete();
            $retailFood->delete();
            DB::commit();
            return response()->json([
                'message' => 'Transaksi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus transaksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getItemUnits($itemId)
    {
        $item = \DB::table('items')->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['units' => []]);
        }
        $units = [];
        if ($item->small_unit_id) {
            $units[] = [
                'id' => $item->small_unit_id,
                'name' => \DB::table('units')->where('id', $item->small_unit_id)->value('name')
            ];
        }
        if ($item->medium_unit_id) {
            $units[] = [
                'id' => $item->medium_unit_id,
                'name' => \DB::table('units')->where('id', $item->medium_unit_id)->value('name')
            ];
        }
        if ($item->large_unit_id) {
            $units[] = [
                'id' => $item->large_unit_id,
                'name' => \DB::table('units')->where('id', $item->large_unit_id)->value('name')
            ];
        }
        return response()->json(['units' => $units]);
    }

    public function dailyTotal(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'transaction_date' => 'required|date',
        ]);
        $total = RetailFood::where('outlet_id', $request->outlet_id)
            ->whereDate('transaction_date', $request->transaction_date)
            ->sum('total_amount');
        return response()->json(['total' => $total]);
    }
} 