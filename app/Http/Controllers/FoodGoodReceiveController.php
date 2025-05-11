<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use Carbon\Carbon;

class FoodGoodReceiveController extends Controller
{
    // List Good Receive
    public function index()
    {
        $list = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->orderByDesc('gr.created_at')
            ->select(
                'gr.id',
                'gr.receive_date',
                'po.number as po_number',
                's.name as supplier_name',
                'u.nama_lengkap as received_by_name'
            )
            ->get();
        return inertia('FoodGoodReceive/Index', [
            'goodReceives' => $list
        ]);
    }

    // Fetch PO by number (for scan/manual input)
    public function fetchPO(Request $request)
    {
        $request->validate(['po_number' => 'required|string']);
        $po = DB::table('purchase_order_foods')
            ->where('number', $request->po_number)
            ->first();
        if (!$po) {
            return response()->json(['message' => 'PO tidak ditemukan'], 404);
        }
        // Cek apakah PO sudah pernah diterima
        $alreadyReceived = DB::table('food_good_receives')->where('po_id', $po->id)->exists();
        if ($alreadyReceived) {
            return response()->json(['message' => 'PO sudah pernah diterima'], 400);
        }
        $items = DB::table('purchase_order_food_items as poi')
            ->leftJoin('items as i', 'poi.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'poi.unit_id', '=', 'u.id')
            ->where('poi.purchase_order_food_id', $po->id)
            ->select('poi.*', 'i.name as item_name', 'u.name as unit_name')
            ->get();
        return response()->json([
            'po' => $po,
            'items' => $items
        ]);
    }

    // Store Good Receive
    public function store(Request $request)
    {
        $request->validate([
            'po_id' => 'required|integer',
            'receive_date' => 'required|date',
            'items' => 'required|array',
            'items.*.po_item_id' => 'required|integer',
            'items.*.item_id' => 'required|integer',
            'items.*.qty_ordered' => 'required|numeric',
            'items.*.qty_received' => 'required|numeric',
            'items.*.unit_id' => 'required|integer',
        ]);
        DB::beginTransaction();
        try {
            $goodReceiveId = DB::table('food_good_receives')->insertGetId([
                'po_id' => $request->po_id,
                'receive_date' => $request->receive_date,
                'received_by' => Auth::id(),
                'supplier_id' => $request->supplier_id,
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Generate gr_number
            $dateStr = date('Ymd', strtotime($request->receive_date));
            $countToday = DB::table('food_good_receives')
                ->whereDate('receive_date', $request->receive_date)
                ->count();
            $grNumber = 'GR-' . $dateStr . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);
            DB::table('food_good_receives')->where('id', $goodReceiveId)->update(['gr_number' => $grNumber]);
            foreach ($request->items as $item) {
                DB::table('food_good_receive_items')->insert([
                    'good_receive_id' => $goodReceiveId,
                    'po_item_id' => $item['po_item_id'],
                    'item_id' => $item['item_id'],
                    'qty_ordered' => $item['qty_ordered'],
                    'qty_received' => $item['qty_received'],
                    'unit_id' => $item['unit_id'],
                    'notes' => $item['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // === INVENTORY LOGIC ===
                // Ambil warehouse_id dari PR terkait item
                $poItem = DB::table('purchase_order_food_items')->where('id', $item['po_item_id'])->first();
                $prFoodItem = $poItem ? DB::table('pr_food_items')->where('id', $poItem->pr_food_item_id)->first() : null;
                $pr = $prFoodItem ? DB::table('pr_foods')->where('id', $prFoodItem->pr_food_id)->first() : null;
                $warehouseId = $pr ? $pr->warehouse_id : null;
                if (!$warehouseId) {
                    throw new \Exception('warehouse_id tidak ditemukan di PR terkait item');
                }
                // 1. Cek/insert food_inventory_items
                $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                $inventoryItem = DB::table('food_inventory_items')->where('item_id', $item['item_id'])->first();
                if (!$inventoryItem) {
                    $inventoryItemId = DB::table('food_inventory_items')->insertGetId([
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
                // 2. Update/insert food_inventory_stocks
                $stock = DB::table('food_inventory_stocks')->where('inventory_item_id', $inventoryItemId)->where('warehouse_id', $warehouseId)->first();
                // Ambil harga dari PO item
                $cost = $poItem ? $poItem->price : 0;
                // Konversi cost ke semua satuan
                $cost_small = 0; $cost_medium = 0; $cost_large = 0;
                if ($item['unit_id'] == $itemMaster->small_unit_id) {
                    $cost_small = $cost;
                    $cost_large = $itemMaster->small_conversion_qty ? $cost * $itemMaster->small_conversion_qty : $cost;
                    $cost_medium = ($itemMaster->medium_conversion_qty && $itemMaster->small_conversion_qty) ? $cost * ($itemMaster->small_conversion_qty / $itemMaster->medium_conversion_qty) : $cost;
                } elseif ($item['unit_id'] == $itemMaster->medium_unit_id) {
                    $cost_medium = $cost;
                    $cost_large = $itemMaster->medium_conversion_qty ? $cost * $itemMaster->medium_conversion_qty : $cost;
                    $cost_small = ($itemMaster->medium_conversion_qty && $itemMaster->small_conversion_qty) ? $cost / ($itemMaster->small_conversion_qty / $itemMaster->medium_conversion_qty) : $cost;
                } elseif ($item['unit_id'] == $itemMaster->large_unit_id) {
                    $cost_large = $cost;
                    $cost_medium = $itemMaster->medium_conversion_qty ? $cost / $itemMaster->medium_conversion_qty : $cost;
                    $cost_small = $itemMaster->small_conversion_qty ? $cost / $itemMaster->small_conversion_qty : $cost;
                }
                // Konversi qty ke small/medium/large
                $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                if ($item['unit_id'] == $itemMaster->small_unit_id) {
                    $qty_small = $item['qty_received'];
                } elseif ($item['unit_id'] == $itemMaster->medium_unit_id) {
                    $qty_medium = $item['qty_received'];
                } elseif ($item['unit_id'] == $itemMaster->large_unit_id) {
                    $qty_large = $item['qty_received'];
                }
                if (!$stock) {
                    DB::table('food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'warehouse_id' => $warehouseId,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $cost_small * $qty_small + $cost_medium * $qty_medium + $cost_large * $qty_large,
                        'last_cost_small' => $cost_small,
                        'last_cost_medium' => $cost_medium,
                        'last_cost_large' => $cost_large,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('food_inventory_stocks')->where('id', $stock->id)->update([
                        'qty_small' => $stock->qty_small + $qty_small,
                        'qty_medium' => $stock->qty_medium + $qty_medium,
                        'qty_large' => $stock->qty_large + $qty_large,
                        'value' => $stock->value + ($cost_small * $qty_small + $cost_medium * $qty_medium + $cost_large * $qty_large),
                        'last_cost_small' => $cost_small,
                        'last_cost_medium' => $cost_medium,
                        'last_cost_large' => $cost_large,
                        'updated_at' => now(),
                    ]);
                }
                // 3. Insert ke food_inventory_cards
                DB::table('food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'warehouse_id' => $warehouseId,
                    'date' => $request->receive_date,
                    'reference_type' => 'good_receive',
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
                    'value_in' => $cost_small * $qty_small + $cost_medium * $qty_medium + $cost_large * $qty_large,
                    'value_out' => 0,
                    'saldo_qty_small' => 0,
                    'saldo_qty_medium' => 0,
                    'saldo_qty_large' => 0,
                    'saldo_value' => 0,
                    'description' => 'Good Receive',
                    'created_at' => now(),
                ]);
                // 4. Insert ke food_inventory_cost_histories
                DB::table('food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'warehouse_id' => $warehouseId,
                    'date' => $request->receive_date,
                    'old_cost' => 0, // Anda bisa update logic ini jika ingin ambil cost sebelumnya
                    'new_cost' => $cost_small, // Simpan cost_small sebagai acuan utama
                    'type' => 'good_receive',
                    'reference_type' => 'good_receive',
                    'reference_id' => $goodReceiveId,
                    'created_at' => now(),
                ]);
            }
            // Insert activity log
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'good_receive',
                'description' => 'Create Good Receive: ' . $goodReceiveId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode([
                    'good_receive_id' => $goodReceiveId,
                    'po_id' => $request->po_id,
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
} 