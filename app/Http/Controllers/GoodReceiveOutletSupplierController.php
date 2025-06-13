<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Inertia\Inertia;

class GoodReceiveOutletSupplierController extends Controller
{
    // List Good Receive
    public function index(Request $request)
    {
        $query = DB::table('good_receive_outlet_suppliers as gr')
            ->leftJoin('food_floor_order_supplier_headers as ro', 'gr.ro_supplier_id', '=', 'ro.id')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.id',
                'gr.receive_date',
                'gr.gr_number',
                'ro.supplier_fo_number as ro_number',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as received_by_name',
                'gr.status'
            );

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('gr.gr_number', 'like', "%$search%")
                  ->orWhere('ro.supplier_fo_number', 'like', "%$search%")
                  ->orWhere('o.nama_outlet', 'like', "%$search%")
                  ->orWhere('u.nama_lengkap', 'like', "%$search%");
            });
        }

        if ($request->from) {
            $query->whereDate('gr.receive_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('gr.receive_date', '<=', $request->to);
        }

        $list = $query->orderByDesc('gr.created_at')->paginate(10)->withQueryString();
        
        return Inertia::render('GoodReceiveOutletSupplier/Index', [
            'goodReceives' => $list,
            'filters' => $request->only(['search', 'from', 'to']),
        ]);
    }

    // Fetch RO Supplier by number
    public function fetchRO(Request $request)
    {
        $request->validate(['ro_number' => 'required|string']);
        
        $ro = DB::table('food_floor_order_supplier_headers as h')
            ->leftJoin('suppliers as s', 'h.supplier_id', '=', 's.id')
            ->leftJoin('food_floor_orders as f', 'h.floor_order_id', '=', 'f.id')
            ->where('h.supplier_fo_number', $request->ro_number)
            ->select(
                'h.*',
                's.name as supplier_name',
                'h.supplier_fo_number as ro_number',
                'f.tanggal'
            )
            ->first();

        if (!$ro) {
            return response()->json(['message' => 'RO Supplier tidak ditemukan'], 404);
        }

        // Cek apakah RO sudah pernah diterima
        $alreadyReceived = DB::table('good_receive_outlet_suppliers')
            ->where('ro_supplier_id', $ro->id)
            ->exists();

        if ($alreadyReceived) {
            return response()->json(['message' => 'RO Supplier sudah pernah diterima'], 400);
        }

        $items = DB::table('food_floor_order_supplier_items')
            ->where('floor_order_id', $ro->floor_order_id)
            ->select(
                'id',
                'item_id',
                'item_name',
                'qty as qty_ordered',
                'unit as unit_name',
                'price',
                'subtotal'
            )
            ->get();

        return response()->json([
            'ro' => $ro,
            'items' => $items
        ]);
    }

    // Store Good Receive
    public function store(Request $request)
    {
        $request->validate([
            'ro_supplier_id' => 'required|integer',
            'receive_date' => 'required|date',
            'items' => 'required|array',
            'items.*.ro_item_id' => 'required|integer',
            'items.*.item_id' => 'required|integer',
            'items.*.qty_ordered' => 'required|numeric',
            'items.*.qty_received' => 'required|numeric',
            'items.*.unit_id' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            $user = auth()->user();
            $outletId = $user->id_outlet;

            // Generate gr_number
            $dateStr = date('Ymd', strtotime($request->receive_date));
            $countToday = DB::table('good_receive_outlet_suppliers')
                ->whereDate('receive_date', $request->receive_date)
                ->count();
            $grNumber = 'GRS-' . $dateStr . '-' . str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);

            $goodReceiveId = DB::table('good_receive_outlet_suppliers')->insertGetId([
                'gr_number' => $grNumber,
                'ro_supplier_id' => $request->ro_supplier_id,
                'outlet_id' => $outletId,
                'receive_date' => $request->receive_date,
                'received_by' => $user->id,
                'status' => 'completed',
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $item) {
                DB::table('good_receive_outlet_supplier_items')->insert([
                    'good_receive_id' => $goodReceiveId,
                    'ro_item_id' => $item['ro_item_id'],
                    'item_id' => $item['item_id'],
                    'qty_ordered' => $item['qty_ordered'],
                    'qty_received' => $item['qty_received'],
                    'unit_id' => $item['unit_id'],
                    'price' => $item['price'],
                    'notes' => $item['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // === INVENTORY LOGIC ===
                $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $item['item_id'])
                    ->first();
                if (!$inventoryItem) {
                    $inventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                        'item_id' => $item['item_id'],
                        'small_unit_id' => $itemMaster->small_unit_id,
                        'medium_unit_id' => $itemMaster->medium_unit_id,
                        'large_unit_id' => $itemMaster->large_unit_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $inventoryItemId = $inventoryItem->id;
                }
                // Perhitungan konversi qty ke semua unit
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                $qty_small = 0; $qty_medium = 0; $qty_large = 0; $qty_small_for_value = 0;
                if ($item['unit_id'] == $itemMaster->large_unit_id) {
                    $qty_large = (float) $item['qty_received'];
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_small_for_value = $qty_small;
                } elseif ($item['unit_id'] == $itemMaster->medium_unit_id) {
                    $qty_medium = (float) $item['qty_received'];
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_small_for_value = $qty_small;
                } elseif ($item['unit_id'] == $itemMaster->small_unit_id) {
                    $qty_small = (float) $item['qty_received'];
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    $qty_small_for_value = $qty_small;
                }
                // Perhitungan cost
                $cost = $item['price'];
                $cost_small = $cost;
                if ($item['unit_id'] == $itemMaster->large_unit_id) {
                    $cost_small = $cost / (($itemMaster->small_conversion_qty ?: 1) * ($itemMaster->medium_conversion_qty ?: 1));
                } elseif ($item['unit_id'] == $itemMaster->medium_unit_id) {
                    $cost_small = $cost / ($itemMaster->small_conversion_qty ?: 1);
                }
                $cost_medium = $cost_small * $smallConv;
                $cost_large = $cost_medium * $mediumConv;
                // Update stok (Moving Average Cost)
                $existingStock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
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
                        'id_outlet' => $outletId,
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
                // Kartu stok
                $lastCard = DB::table('outlet_food_inventory_cards')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
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
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $outletId,
                    'date' => $request->receive_date,
                    'reference_type' => 'good_receive_supplier',
                    'reference_id' => $goodReceiveId,
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
                    'saldo_value' => $saldo_qty_small * $mac,
                    'description' => 'Good Receive Supplier',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                // Cost history
                $lastCostHistory = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $outletId,
                    'date' => $request->receive_date,
                    'old_cost' => $old_cost,
                    'new_cost' => $cost_small,
                    'mac' => $mac,
                    'type' => 'good_receive_supplier',
                    'reference_type' => 'good_receive_supplier',
                    'reference_id' => $goodReceiveId,
                    'created_at' => now(),
                ]);
            }

            // Insert activity log
            ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => 'create',
                'module' => 'good_receive_supplier',
                'description' => 'Create Good Receive Supplier: ' . $goodReceiveId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode([
                    'good_receive_id' => $goodReceiveId,
                    'ro_supplier_id' => $request->ro_supplier_id,
                    'items' => $request->items
                ]),
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Good Receive berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $gr = DB::table('good_receive_outlet_suppliers as gr')
            ->leftJoin('food_floor_order_supplier_headers as ro', 'gr.ro_supplier_id', '=', 'ro.id')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'ro.supplier_fo_number as ro_number',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as received_by_name',
                'gr.status',
                'gr.notes'
            )
            ->where('gr.id', $id)
            ->first();

        if (!$gr) {
            abort(404, 'Good Receive tidak ditemukan');
        }

        $items = DB::table('good_receive_outlet_supplier_items as gri')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
            ->select(
                'gri.id',
                'i.name as item_name',
                'gri.qty_ordered',
                'gri.qty_received',
                'u.name as unit_name',
                'gri.price'
            )
            ->where('gri.good_receive_id', $id)
            ->get();

        $gr->items = $items;
        return Inertia::render('GoodReceiveOutletSupplier/Show', [
            'gr' => $gr
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $gr = DB::table('good_receive_outlet_suppliers')->where('id', $id)->first();
            if (!$gr) {
                return response()->json(['success' => false, 'message' => 'Good Receive tidak ditemukan'], 404);
            }

            // Ambil semua item GR
            $items = DB::table('good_receive_outlet_supplier_items')->where('good_receive_id', $id)->get();
            foreach ($items as $item) {
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $item->item_id)
                    ->where('outlet_id', $gr->outlet_id)
                    ->first();

                if ($inventoryItem) {
                    // Hapus stok
                    DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('outlet_id', $gr->outlet_id)
                        ->delete();

                    // Hapus kartu stok
                    DB::table('outlet_food_inventory_cards')
                        ->where('reference_type', 'good_receive_supplier')
                        ->where('reference_id', $id)
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->delete();
                }
            }

            // Hapus detail GR
            DB::table('good_receive_outlet_supplier_items')->where('good_receive_id', $id)->delete();
            // Hapus GR
            DB::table('good_receive_outlet_suppliers')->where('id', $id)->delete();

            // Log activity
            ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'good_receive_supplier',
                'description' => 'Delete Good Receive Supplier: ' . $id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($gr),
                'new_data' => null,
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Good Receive berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getROSuppliers(Request $request)
    {
        $user = auth()->user();
        $idOutlet = $user->id_outlet;

        $query = DB::table('food_floor_order_supplier_headers as h')
            ->leftJoin('food_floor_orders as f', 'h.floor_order_id', '=', 'f.id')
            ->leftJoin('good_receive_outlet_suppliers as gr', 'h.id', '=', 'gr.ro_supplier_id')
            ->select(
                'h.id',
                'h.supplier_fo_number as ro_number',
                'h.floor_order_id',
                'f.order_number as floor_order_number',
                'f.tanggal',
                'h.supplier_id'
            )
            ->whereNull('gr.id');

        if ($idOutlet != 1) {
            $query->where('f.id_outlet', $idOutlet);
        }

        $ros = $query->orderByDesc('f.tanggal')->orderByDesc('h.supplier_fo_number')->get();
        return response()->json($ros);
    }
} 