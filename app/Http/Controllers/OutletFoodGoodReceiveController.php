<?php

namespace App\Http\Controllers;

use App\Models\OutletFoodGoodReceive;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class OutletFoodGoodReceiveController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = DB::table('outlet_food_good_receives as gr')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'gr.created_by', '=', 'u.id')
            ->whereNull('gr.deleted_at') // Exclude soft deleted records
            ->select(
                'gr.id',
                'gr.number',
                'gr.receive_date',
                'gr.status',
                'gr.outlet_id',
                'o.nama_outlet as outlet_name',
                'gr.delivery_order_id',
                'do.number as delivery_order_number',
                'do.source_type',
                'gr.warehouse_outlet_id',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name',
                'gr.created_at'
            );
        if ($user->id_outlet != 1) {
            $query->where('gr.outlet_id', $user->id_outlet);
        }
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('gr.number', 'like', "%$search%")
                  ->orWhere('do.number', 'like', "%$search%")
                  ->orWhere('o.nama_outlet', 'like', "%$search%")
                  ->orWhere('wo.name', 'like', "%$search%")
                ;
            });
        }
        if ($request->outlet_id) {
            $query->where('gr.outlet_id', $request->outlet_id);
        }
        if ($request->from) {
            $query->whereDate('gr.receive_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('gr.receive_date', '<=', $request->to);
        }
        $page = $request->input('page', 1);
        $perPage = 10;
        $goodReceives = $query->orderBy('gr.created_at', 'desc')->paginate($perPage)->appends($request->all());
        return Inertia::render('OutletFoodGoodReceive/Index', [
            'goodReceives' => $goodReceives,
            'outlets' => Outlet::select('id_outlet as id', 'nama_outlet as name')->get(),
            'filters' => $request->only(['search', 'outlet_id', 'from', 'to']),
            'user_id_outlet' => $user->id_outlet,
        ]);
    }

    public function create()
    {
        return Inertia::render('OutletFoodGoodReceive/Create', [
            'outlets' => Outlet::select('id_outlet as id', 'nama_outlet as name')->get()
        ]);
    }

    public function store(Request $request)
    {
        try {
        $validated = $request->validate([
            'delivery_order_id' => 'required|exists:delivery_orders,id',
            'receive_date' => 'required|date',
                'notes' => 'nullable|string',
                'items' => 'required|array',
                'items.*.item_id' => 'required|integer',
                'items.*.qty' => 'required|numeric',
                'items.*.unit_id' => 'required|integer',
                'items.*.received_qty' => 'required|numeric|min:0',
            ]);
            
            // Check for duplicate submission within last 30 seconds
            $user = auth()->user();
            $recentSubmission = DB::table('outlet_food_good_receives')
                ->where('delivery_order_id', $validated['delivery_order_id'])
                ->where('created_by', $user->id)
                ->where('created_at', '>=', now()->subSeconds(30))
                ->first();
                
            if ($recentSubmission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data sudah pernah disubmit dalam 30 detik terakhir. Silakan tunggu sebentar atau refresh halaman.',
                    'duplicate_info' => [
                        'existing_id' => $recentSubmission->id,
                        'existing_number' => $recentSubmission->number,
                        'submitted_at' => $recentSubmission->created_at,
                        'time_diff' => now()->diffInSeconds($recentSubmission->created_at),
                        'delivery_order_id' => $recentSubmission->delivery_order_id
                    ]
                ], 422);
            }
            
            // Additional check: Check if there's already a GR for this DO from this user
            $existingGR = DB::table('outlet_food_good_receives')
                ->where('delivery_order_id', $validated['delivery_order_id'])
                ->where('created_by', $user->id)
                ->whereNull('deleted_at')
                ->first();
                
            if ($existingGR) {
                return response()->json([
                    'success' => false,
                    'message' => 'Good Receive untuk Delivery Order ini sudah pernah dibuat sebelumnya.',
                    'duplicate_info' => [
                        'existing_id' => $existingGR->id,
                        'existing_number' => $existingGR->number,
                        'submitted_at' => $existingGR->created_at,
                        'delivery_order_id' => $existingGR->delivery_order_id
                    ]
                ], 422);
            }
            
            DB::beginTransaction();
            $do = DB::table('delivery_orders')->where('id', $validated['delivery_order_id'])->first();
            if (!$do) throw new \Exception('Delivery Order tidak ditemukan');
            
            $outletId = $user->id_outlet;
            $warehouseOutletId = null;
            $floorOrderId = null; // Initialize floorOrderId
            
            if ($do->source_type === 'ro_supplier_gr') {
                // Untuk RO Supplier GR, ambil data dari purchase order
                $gr = DB::table('food_good_receives')->where('id', $do->ro_supplier_gr_id)->first();
                if ($gr) {
                    $po = DB::table('purchase_order_foods')->where('id', $gr->po_id)->first();
                    if ($po) {
                        $floorOrderId = $po->source_id; // Set floorOrderId dari PO source_id
                        $floorOrder = DB::table('food_floor_orders')->where('id', $floorOrderId)->first();
                        if ($floorOrder && isset($floorOrder->warehouse_outlet_id)) {
                            $warehouseOutletId = $floorOrder->warehouse_outlet_id;
                        }
                    }
                }
            } else {
                // Untuk Packing List biasa
                $floorOrderId = $do->floor_order_id;
                $floorOrder = DB::table('food_floor_orders')->where('id', $floorOrderId)->first();
                if ($floorOrder && isset($floorOrder->warehouse_outlet_id)) {
                    $warehouseOutletId = $floorOrder->warehouse_outlet_id;
                }
            }
            $today = date('Ymd');
            $prefix = 'OGR-' . $today . '-';
            
            // Cari nomor terakhir hari ini (termasuk data yang soft deleted)
            $lastNumber = DB::table('outlet_food_good_receives')
                ->where('number', 'like', $prefix . '%')
                ->orderBy('number', 'desc')
                ->first();
                
            if ($lastNumber) {
                $sequence = (int) substr($lastNumber->number, -4) + 1;
            } else {
                $sequence = 1;
            }
            
            $number = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            $grId = DB::table('outlet_food_good_receives')->insertGetId([
                'number' => $number,
            'delivery_order_id' => $validated['delivery_order_id'],
                'outlet_id' => $outletId,
            'warehouse_outlet_id' => $warehouseOutletId,
            'receive_date' => $validated['receive_date'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($validated['items'] as $item) {
                
                // Selalu insert ke outlet_food_good_receive_items
                DB::table('outlet_food_good_receive_items')->insert([
                    'outlet_food_good_receive_id' => $grId,
                    'item_id' => $item['item_id'],
                    'unit_id' => $item['unit_id'],
                    'qty' => $item['qty'],
                    'received_qty' => $item['received_qty'],
                    'remaining_qty' => $item['qty'] - $item['received_qty'],
                    'receive_date' => $validated['receive_date'], // Tambahkan receive_date yang sama dengan header
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Jika received_qty = 0, skip proses inventory
                if ($item['received_qty'] <= 0) {
                    continue;
                }
                
                // Ambil cost berdasarkan source type
                $ffoi = null;
                if ($do->source_type === 'ro_supplier_gr') {
                    // Untuk RO Supplier GR, ambil dari PO items (karena GR items tidak ada price)
                    $grItem = DB::table('food_good_receive_items')
                        ->where('good_receive_id', $do->ro_supplier_gr_id)
                        ->where('item_id', $item['item_id'])
                        ->first();
                    
                    if ($grItem) {
                        // Ambil price dari PO item
                        $poItem = DB::table('purchase_order_food_items')
                            ->where('id', $grItem->po_item_id)
                            ->first();
                        $cost = $poItem ? $poItem->price : 0;
                    } else {
                        $cost = 0;
                    }
                } else {
                    // Untuk Packing List biasa
                    $ffoi = DB::table('food_floor_order_items')
                        ->where('floor_order_id', $do->floor_order_id)
                        ->where('item_id', $item['item_id'])
                        ->first();
                    $cost = $ffoi ? $ffoi->price : 0;
                }
                $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item['item_id'])->first();
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
                $unitId = $item['unit_id'];
                $qtyInput = $item['received_qty'];
                $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                $unitSmall = $itemMaster->small_unit_id;
                $unitMedium = $itemMaster->medium_unit_id;
                $unitLarge = $itemMaster->large_unit_id;
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                if ($unitId == $unitSmall) {
                    $qty_small = $qtyInput;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                } elseif ($unitId == $unitMedium) {
                    $qty_medium = $qtyInput;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                } elseif ($unitId == $unitLarge) {
                    $qty_large = $qtyInput;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } else {
                    $qty_small = $qtyInput;
                }
                // Konversi cost ke small/medium/large
                $cost_small = $cost;
                if ($unitId == $itemMaster->large_unit_id) {
                    $cost_small = $cost / (($itemMaster->small_conversion_qty ?: 1) * ($itemMaster->medium_conversion_qty ?: 1));
                } elseif ($unitId == $itemMaster->medium_unit_id) {
                    $cost_small = $cost / ($itemMaster->small_conversion_qty ?: 1);
                }
                $cost_medium = $cost_small * ($itemMaster->small_conversion_qty ?: 1);
                $cost_large = $cost_medium * ($itemMaster->medium_conversion_qty ?: 1);
                // Konversi qty ke semua unit
                $qty_small_for_value = 0;
                if ($unitId == $itemMaster->large_unit_id) {
                    $qty_large = (float) $qtyInput;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_small_for_value = $qty_small;
                } elseif ($unitId == $itemMaster->medium_unit_id) {
                    $qty_medium = (float) $qtyInput;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_small_for_value = $qty_small;
                } elseif ($unitId == $itemMaster->small_unit_id) {
                    $qty_small = (float) $qtyInput;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    $qty_small_for_value = $qty_small;
                }
                // Update/insert stok
                $stock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId)
                    ->first();
                $qty_lama = $stock ? $stock->qty_small : 0;
                $nilai_lama = $stock ? $stock->value : 0;
                $qty_baru = $qty_small;
                $nilai_baru = $qty_small_for_value * $cost_small;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $cost_small;
                if ($stock) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => $total_qty,
                            'qty_medium' => $stock->qty_medium + $qty_medium,
                            'qty_large' => $stock->qty_large + $qty_large,
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
                        'warehouse_outlet_id' => $warehouseOutletId,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $nilai_baru,
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $cost_medium,
                        'last_cost_large' => $cost_large,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                // Insert kartu stok
                $lastCard = DB::table('outlet_food_inventory_cards')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId)
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
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'date' => $validated['receive_date'],
                    'reference_type' => 'good_receive_outlet',
                    'reference_id' => $grId,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'out_qty_small' => 0,
                    'out_qty_medium' => 0,
                    'out_qty_large' => 0,
                    'cost_per_small' => $mac,
                    'cost_per_medium' => $cost_medium,
                    'cost_per_large' => $cost_large,
                    'value_in' => $qty_small_for_value * $cost_small,
                    'value_out' => 0,
                    'saldo_qty_small' => $saldo_qty_small,
                    'saldo_qty_medium' => $saldo_qty_medium,
                    'saldo_qty_large' => $saldo_qty_large,
                    'saldo_value' => $saldo_qty_small * $mac,
                    'description' => 'Good Receive Outlet',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                // Insert cost history
                $lastCostHistory = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $outletId,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'date' => $validated['receive_date'],
                    'old_cost' => $old_cost,
                    'new_cost' => $cost_small,
                    'mac' => $mac,
                    'type' => 'good_receive_outlet',
                    'reference_type' => 'good_receive_outlet',
                    'reference_id' => $grId,
                    'created_at' => now(),
                ]);
            }
            DB::commit();
            
            // Update floor order status jika floorOrderId tersedia
            // TAMBAHAN VALIDASI: Hanya update jika semua DO yang terkait sudah di-GR
            if ($floorOrderId) {
                // Cek apakah semua DO sudah di-GR sebelum update status
                if ($this->checkAllDOsReceived($floorOrderId)) {
                    DB::table('food_floor_orders')
                        ->where('id', $floorOrderId)
                        ->update(['status' => 'received']);
                }
            }
            
            return response()->json(['success' => true, 'message' => 'Good Receive Outlet berhasil disimpan']);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('STORE OUTLET GR ERROR', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        // Ambil header
        $header = DB::table('outlet_food_good_receives as gr')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->select(
                'gr.*',
                'o.nama_outlet as outlet_name',
                'do.number as delivery_order_number',
                'do.floor_order_id',
                'do.packing_list_id'
            )
            ->where('gr.id', $id)
            ->first();
        // Ambil detail - qty DO harus dari delivery_order_items, bukan dari outlet_food_good_receive_items
        $details = DB::table('outlet_food_good_receive_items as gri')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
            ->leftJoin('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
            ->leftJoin('delivery_order_items as doi', function($join) {
                $join->on('doi.delivery_order_id', '=', 'gr.delivery_order_id')
                     ->on('doi.item_id', '=', 'gri.item_id');
            })
            ->select(
                'gri.*',
                'i.name as item_name',
                'u.name as unit_name',
                // Qty DO harus dari delivery_order_items.qty_packing_list, bukan dari gri.qty (yang mungkin dari floor order)
                // Gunakan MAX untuk menghindari duplikasi jika ada multiple rows (seharusnya tidak terjadi)
                DB::raw('COALESCE(MAX(doi.qty_packing_list), gri.qty) as qty_do')
            )
            ->where('gri.outlet_food_good_receive_id', $id)
            ->groupBy('gri.id', 'gri.outlet_food_good_receive_id', 'gri.item_id', 'gri.unit_id', 'gri.qty', 'gri.received_qty', 'gri.remaining_qty', 'gri.receive_date', 'gri.created_at', 'gri.updated_at', 'i.name', 'u.name')
            ->get();
        // Ambil delivery order beserta floor order & packing list
        $deliveryOrder = null;
        if ($header && $header->delivery_order_id) {
            $deliveryOrder = DB::table('delivery_orders as do')
                ->leftJoin('food_floor_orders as fo', 'do.floor_order_id', '=', 'fo.id')
                ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
                ->select(
                    'do.id as do_id', 'do.number as do_number',
                    'fo.order_number as floor_order_number', 'fo.tanggal as floor_order_date', 'fo.description as floor_order_desc',
                    'pl.packing_number', 'pl.reason as packing_reason'
                )
                ->where('do.id', $header->delivery_order_id)
                ->first();
        }
        
        
        return Inertia::render('OutletFoodGoodReceive/Show', [
            'goodReceive' => $header,
            'details' => $details,
            'deliveryOrder' => $deliveryOrder,
        ]);
    }

    public function destroy($id)
    {
        
        // Find the model manually to avoid route model binding issues
        $outletFoodGoodReceive = OutletFoodGoodReceive::withTrashed()->find($id);
        
        if (!$outletFoodGoodReceive) {
            \Log::error('DEBUG DESTROY - MODEL NOT FOUND', [
                'requested_id' => $id
            ]);
            return response()->json(['success' => false, 'message' => 'Good Receive tidak ditemukan'], 404);
        }
        
        
        // Check if already deleted
        if ($outletFoodGoodReceive->trashed()) {
            return response()->json(['success' => false, 'message' => 'Good Receive sudah dihapus'], 400);
        }
        
        // Check if GR has payment
        if ($outletFoodGoodReceive->outletPayment && $outletFoodGoodReceive->outletPayment->status !== 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Tidak dapat menghapus GR yang sudah memiliki payment aktif'], 400);
        }
        
        // Test simple delete first
        try {
            $outletFoodGoodReceive->delete();
            return response()->json(['success' => true, 'message' => 'Good Receive berhasil dihapus']);
        } catch (\Exception $e) {
            \Log::error('DEBUG DESTROY SIMPLE DELETE FAILED', [
                'gr_id' => $outletFoodGoodReceive->id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeScan(Request $request)
    {
        $request->validate([
            'good_receive_id' => 'required|exists:outlet_food_good_receives,id',
            'barcode' => 'required|string',
            'qty' => 'nullable|numeric', // hanya diisi jika unit kg
        ]);

        DB::beginTransaction();
        try {
            $gr = DB::table('outlet_food_good_receives')->where('id', $request->good_receive_id)->first();
            if (!$gr) throw new \Exception('Good Receive tidak ditemukan');

            // Cari item di DO berdasarkan barcode
            $item = DB::table('delivery_order_items as doi')
                ->join('items as i', 'doi.item_id', '=', 'i.id')
                ->where('doi.delivery_order_id', $gr->delivery_order_id)
                ->where('i.barcode', $request->barcode)
                ->select('doi.*', 'i.name as item_name', 'doi.unit')
                ->first();
            if (!$item) throw new \Exception('Item tidak ditemukan di DO');

            // Cek satuan
            if ($item->unit == 'pcs') {
                $qtyToAdd = 1;
            } else {
                // kg, gr, liter, dst
                if (!$request->qty || $request->qty <= 0) throw new \Exception('Qty harus diisi untuk item kiloan');
                $qtyToAdd = $request->qty;
            }

            // Update qty_scan
            $newQtyScan = $item->qty_scan + $qtyToAdd;
            if ($newQtyScan > $item->qty_packing_list) {
                throw new \Exception('Qty scan melebihi qty DO');
            }
            DB::table('delivery_order_items')->where('id', $item->id)->update([
                'qty_scan' => $newQtyScan,
                'updated_at' => now(),
            ]);

            // Simpan ke outlet_food_good_receive_items jika belum ada
            $grItem = DB::table('outlet_food_good_receive_items')
                ->where('outlet_food_good_receive_id', $gr->id)
                ->where('item_id', $item->item_id)
                ->first();
            if (!$grItem) {
                DB::table('outlet_food_good_receive_items')->insert([
                    'outlet_food_good_receive_id' => $gr->id,
                    'item_id' => $item->item_id,
                    'unit_id' => null, // bisa diisi jika perlu
                    'qty' => $item->qty_packing_list,
                    'received_qty' => $qtyToAdd,
                    'remaining_qty' => $item->qty_packing_list - $qtyToAdd,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('outlet_food_good_receive_items')
                    ->where('id', $grItem->id)
                    ->update([
                        'received_qty' => $grItem->received_qty + $qtyToAdd,
                        'remaining_qty' => $item->qty_packing_list - ($grItem->received_qty + $qtyToAdd),
                        'updated_at' => now(),
                    ]);
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function scanDO()
    {
        return Inertia::render('OutletFoodGoodReceive/ScanDO');
    }

    public function validateDO(Request $request)
    {
        $number = $request->number;
        $do = \DB::table('delivery_orders')->where('number', $number)->first();
        if ($do) {
            return response()->json(['success' => true, 'delivery_order_id' => $do->id]);
        } else {
            return response()->json(['success' => false, 'message' => 'Delivery Order tidak ditemukan']);
        }
    }

    public function createFromDO($delivery_order_id)
    {
        // Ambil data DO + outlet
        $do = \DB::table('delivery_orders')
            ->leftJoin('food_floor_orders', 'delivery_orders.floor_order_id', '=', 'food_floor_orders.id')
            ->leftJoin('tbl_data_outlet', 'food_floor_orders.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            // Join untuk RO Supplier GR
            ->leftJoin('food_good_receives as gr_ro', 'delivery_orders.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo_ro', 'po.source_id', '=', 'fo_ro.id')
            ->leftJoin('tbl_data_outlet as o_ro', 'fo_ro.id_outlet', '=', 'o_ro.id_outlet')
            ->select(
                'delivery_orders.*',
                'food_floor_orders.id_outlet',
                'tbl_data_outlet.nama_outlet as outlet_name',
                // Data untuk RO Supplier GR
                'fo_ro.id_outlet as ro_id_outlet',
                'o_ro.nama_outlet as ro_outlet_name'
            )
            ->where('delivery_orders.id', $delivery_order_id)
            ->first();
        if (!$do) abort(404);

        // Ambil item DO
        $items = \DB::table('delivery_order_items')
            ->where('delivery_order_id', $delivery_order_id)
            ->join('items', 'delivery_order_items.item_id', '=', 'items.id')
            ->select(
                'delivery_order_items.id',
                'items.name',
                'delivery_order_items.barcode',
                'delivery_order_items.qty_packing_list as qty'
            )
            ->get()
            ->map(function ($item) {
                $item->received_qty = 0; // selalu mulai dari 0
                return $item;
            });

        // Kirim data DO dan items dalam satu objek deliveryOrder
        return Inertia::render('OutletFoodGoodReceive/CreateFromDO', [
            'deliveryOrder' => [
                'id' => $do->id,
                'number' => $do->number,
                'date' => $do->created_at,
                'outlet_name' => $do->source_type === 'ro_supplier_gr' ? ($do->ro_outlet_name ?? '-') : ($do->outlet_name ?? '-'),
                'status' => $do->status ?? '-',
                'items' => $items,
            ]
        ]);
    }

    public function availableDOs(Request $request)
    {
        $q = $request->input('q');
        $user = auth()->user();
        $idOutlet = $user->id_outlet; // Ambil id_outlet user login

        $query = DB::table('delivery_orders as do')
            ->leftJoin('outlet_food_good_receives as gr', function($join) {
                $join->on('gr.delivery_order_id', '=', 'do.id')->whereNull('gr.deleted_at');
            })
            ->leftJoin('food_floor_orders as fo', 'do.floor_order_id', '=', 'fo.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            // Join untuk RO Supplier GR
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo_ro', 'po.source_id', '=', 'fo_ro.id')
            ->whereNull('gr.id')
            ->where(function($q) use ($idOutlet) {
                // Jika user bukan admin (id_outlet != 1), filter berdasarkan outlet
                if ($idOutlet != 1) {
                    // Untuk packing list biasa
                    $q->where('fo.id_outlet', $idOutlet)
                      // Atau untuk RO Supplier GR
                      ->orWhere('fo_ro.id_outlet', $idOutlet);
                }
                // Jika user admin (id_outlet = 1), tidak ada filter outlet (bisa lihat semua)
            });

        if ($q) {
            $query->where('do.number', 'like', "%$q%");
        }
        $dos = $query->orderByDesc('do.created_at')
            ->select(
                'do.id', 
                'do.number', 
                'do.created_at as do_date', 
                'do.source_type',
                DB::raw('COALESCE(wd.name, "Perishable") as division_name')
            )
            ->limit(20)
            ->get();
       // // \Log::info('DEBUG DO OUTLET', [
       //     'id_outlet' => $idOutlet,
       //     'result' => $dos,
       //     'query_sql' => $query->toSql(),
       //        'query_bindings' => $query->getBindings()
        //]);
        return response()->json($dos);
    }

    public function doDetail($do_id)
    {
        // Info DO, Packing List, FO
        $do = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'do.floor_order_id', '=', 'fo.id')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            // Join untuk RO Supplier GR
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo_ro', 'po.source_id', '=', 'fo_ro.id')
            ->leftJoin('warehouse_outlets as wo_ro', 'fo_ro.warehouse_outlet_id', '=', 'wo_ro.id')
            ->select(
                'do.id as do_id', 'do.number as do_number', 'do.packing_list_id', 'do.floor_order_id',
                'do.source_type', 'do.ro_supplier_gr_id',
                'pl.packing_number', 'pl.reason as packing_reason',
                'fo.order_number as floor_order_number', 'fo.tanggal as floor_order_date', 'fo.description as floor_order_desc',
                'do.created_at as do_created_at',
                'fo.warehouse_outlet_id', 'wo.name as warehouse_outlet_name',
                // Data untuk RO Supplier GR
                'gr_ro.gr_number as ro_gr_number',
                'fo_ro.order_number as ro_floor_order_number', 'fo_ro.tanggal as ro_floor_order_date', 'fo_ro.description as ro_floor_order_desc',
                'fo_ro.warehouse_outlet_id as ro_warehouse_outlet_id', 'wo_ro.name as ro_warehouse_outlet_name',
                // Data PO untuk source type dan outlet
                'po.id as po_id', 'po.number as po_number', 'po.source_type as po_source_type'
            )
            ->where('do.id', $do_id)
            ->first();

        // List item DO
        $items = DB::table('delivery_order_items as doi')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'doi.unit', '=', 'u.name')
            ->leftJoin('item_barcodes as ib', 'i.id', '=', 'ib.item_id')
            ->select(
                'doi.id as delivery_order_item_id',
                'doi.item_id',
                'i.name as item_name',
                'doi.qty_packing_list',
                'doi.qty_scan',
                'doi.unit',
                DB::raw('GROUP_CONCAT(ib.barcode) as barcodes'),
                'u.name as unit_name',
                'u.type as unit_type',
                'u.id as unit_id'
            )
            ->where('doi.delivery_order_id', $do_id)
            ->groupBy('doi.id', 'doi.item_id', 'i.name', 'doi.qty_packing_list', 'doi.qty_scan', 'doi.unit', 'u.name', 'u.type', 'u.id')
            ->get();
        // Mapping barcodes ke array
        $items = $items->map(function($item) {
            $item->barcodes = $item->barcodes ? explode(',', $item->barcodes) : [];
            return $item;
        });

        // Add PO source type and outlet information
        $poInfo = null;
        if ($do && $do->po_id) {
            $poInfo = [
                'po_id' => $do->po_id,
                'po_number' => $do->po_number,
                'source_type' => $do->po_source_type,
                'source_type_display' => 'PR Foods',
                'outlet_names' => []
            ];
            
            if ($do->po_source_type === 'ro_supplier') {
                $poInfo['source_type_display'] = 'RO Supplier';
                // Get outlet names for RO Supplier
                $outletData = DB::table('food_floor_orders as fo')
                    ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                    ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                    ->where('poi.purchase_order_food_id', $do->po_id)
                    ->select('o.nama_outlet')
                    ->distinct()
                    ->get();
                
                $poInfo['outlet_names'] = $outletData->pluck('nama_outlet')->filter()->unique()->toArray();
            }
        }

        return response()->json([
            'do' => $do,
            'items' => $items,
            'po_info' => $poInfo,
        ]);
    }

    public function submit(Request $request, $id)
    {
        $gr = DB::table('outlet_food_good_receives')->where('id', $id)->first();
        if (!$gr) {
            return response()->json(['success' => false, 'message' => 'Good Receive tidak ditemukan'], 404);
        }
        // Ambil semua item DO
        $items = DB::table('delivery_order_items')
            ->where('delivery_order_id', $gr->delivery_order_id)
            ->get();
        foreach ($items as $item) {
            if (floatval($item->qty_scan) < floatval($item->qty_packing_list)) {
                return response()->json(['success' => false, 'message' => 'Masih ada item yang belum discan sesuai qty DO!'], 400);
            }
        }
        // Update status GR
        DB::table('outlet_food_good_receives')->where('id', $id)->update([
            'status' => 'done',
            'updated_at' => now(),
            'updated_by' => auth()->id(),
        ]);
        return response()->json(['success' => true]);
    }

    public function processStock($id)
    {
        DB::beginTransaction();
        try {
            $gr = DB::table('outlet_food_good_receives')->where('id', $id)->first();
            if (!$gr) throw new \Exception('Good Receive tidak ditemukan');
            if ($gr->status !== 'done') throw new \Exception('GR belum disubmit atau sudah diproses');

            $items = DB::table('outlet_food_good_receive_items')->where('outlet_food_good_receive_id', $id)->get();
            foreach ($items as $item) {
                // Ambil master item
                $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
                if (!$itemMaster) continue;

                // Insert/update outlet_food_inventory_items
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $item->item_id)
                    ->where('outlet_id', $gr->outlet_id)
                    ->where('warehouse_outlet_id', $gr->warehouse_outlet_id)
                    ->first();
                if (!$inventoryItem) {
                    $inventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                        'item_id' => $item->item_id,
                        'outlet_id' => $gr->outlet_id,
                        'warehouse_outlet_id' => $gr->warehouse_outlet_id,
                        'small_unit_id' => $itemMaster->small_unit_id,
                        'medium_unit_id' => $itemMaster->medium_unit_id,
                        'large_unit_id' => $itemMaster->large_unit_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $inventoryItemId = $inventoryItem->id;
                }

                // Konversi qty ke small/medium/large
                $unit = DB::table('units')->where('id', $item->unit_id)->value('name');
                $qty_input = $item->received_qty;
                $qty_small = 0; $qty_medium = 0; $qty_large = 0;
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

                // Update/insert outlet_food_inventory_stocks
                $stock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $gr->outlet_id)
                    ->where('warehouse_outlet_id', $gr->warehouse_outlet_id)
                    ->first();
                if ($stock) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => $stock->qty_small + $qty_small,
                            'qty_medium' => $stock->qty_medium + $qty_medium,
                            'qty_large' => $stock->qty_large + $qty_large,
                            'value' => ($stock->qty_small + $qty_small) * $stock->last_cost_small,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'id_outlet' => $gr->outlet_id,
                        'warehouse_outlet_id' => $gr->warehouse_outlet_id,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $qty_small * 0, // cost bisa diisi jika ada
                        'last_cost_small' => 0,
                        'last_cost_medium' => 0,
                        'last_cost_large' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Insert kartu stok (stock in)
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $gr->outlet_id,
                    'warehouse_outlet_id' => $gr->warehouse_outlet_id,
                    'date' => $gr->receive_date,
                    'reference_type' => 'good_receive_outlet',
                    'reference_id' => $gr->id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'out_qty_small' => 0,
                    'out_qty_medium' => 0,
                    'out_qty_large' => 0,
                    'cost_per_small' => 0,
                    'cost_per_medium' => 0,
                    'cost_per_large' => 0,
                    'value_in' => 0,
                    'value_out' => 0,
                    'saldo_qty_small' => $stock ? $stock->qty_small + $qty_small : $qty_small,
                    'saldo_qty_medium' => $stock ? $stock->qty_medium + $qty_medium : $qty_medium,
                    'saldo_qty_large' => $stock ? $stock->qty_large + $qty_large : $qty_large,
                    'saldo_value' => 0,
                    'description' => 'Good Receive Outlet',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            // Update status GR jadi 'stocked'
            DB::table('outlet_food_good_receives')->where('id', $id)->update([
                'status' => 'stocked',
                'updated_at' => now(),
            ]);
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $gr = DB::table('outlet_food_good_receives as gr')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'gr.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name'
            )
            ->where('gr.id', $id)
            ->first();
        // Tidak boleh edit jika sudah ada payment
        $hasPayment = DB::table('outlet_payments')->where('gr_id', $id)->exists();
        if ($hasPayment) {
            return redirect()->route('outlet-food-good-receives.index')->with('error', 'GR sudah di-payment, tidak bisa diedit!');
        }
        $items = \DB::table('outlet_food_good_receive_items as gri')
            ->join('items as i', 'gri.item_id', '=', 'i.id')
            ->join('units as u', 'gri.unit_id', '=', 'u.id')
            ->where('gri.outlet_food_good_receive_id', $id)
            ->select('gri.*', 'i.name as item_name', 'u.name as unit_name')
            ->get();
        return Inertia::render('OutletFoodGoodReceive/Edit', [
            'goodReceive' => $gr,
            'items' => $items,
        ]);
    }

    public function update(Request $request, $id)
    {
        $gr = \App\Models\OutletFoodGoodReceive::with(['items', 'outletPayment'])->findOrFail($id);
        if ($gr->outletPayment) {
            return response()->json(['success' => false, 'message' => 'GR sudah di-payment, tidak bisa diedit!'], 400);
        }
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0',
        ]);
        \DB::beginTransaction();
        try {
            // Rollback efek inventory lama
            // 1. Ambil semua kartu stok dan cost history terkait GR ini
            $cards = \DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'good_receive_outlet')
                ->where('reference_id', $gr->id)
                ->get();
            foreach ($cards as $card) {
                // Rollback stok
                $stock = \DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $card->inventory_item_id)
                    ->where('id_outlet', $gr->outlet_id)
                    ->where('warehouse_outlet_id', $gr->warehouse_outlet_id)
                    ->first();
                if ($stock) {
                    \DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => $stock->qty_small - $card->in_qty_small,
                            'qty_medium' => $stock->qty_medium - $card->in_qty_medium,
                            'qty_large' => $stock->qty_large - $card->in_qty_large,
                            'updated_at' => now(),
                        ]);
                }
            }
            // Hapus kartu stok dan cost history
            \DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'good_receive_outlet')
                ->where('reference_id', $gr->id)
                ->delete();
            \DB::table('outlet_food_inventory_cost_histories')
                ->where('reference_type', 'good_receive_outlet')
                ->where('reference_id', $gr->id)
                ->delete();
            // Update qty di item GR
            foreach ($validated['items'] as $item) {
                \DB::table('outlet_food_good_receive_items')
                    ->where('id', $item['id'])
                    ->update(['received_qty' => $item['qty'], 'updated_at' => now()]);
            }
            // Proses ulang efek inventory dengan qty baru (reuse logic processStock)
            $this->processStock($gr->id);
            \DB::commit();
            return response()->json(['success' => true, 'message' => 'Qty GR & inventory berhasil diupdate']);
        } catch (\Throwable $e) {
            \DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper function: Cek apakah semua DO yang terkait dengan floor order sudah di-GR
     * Hanya return true jika:
     * 1. Floor order status sudah "delivered" (barang sudah dikirim)
     * 2. Ada DO yang terkait dengan floor order
     * 3. Semua DO sudah punya GR (outlet_food_good_receives)
     */
    private function checkAllDOsReceived($floorOrderId)
    {
        // Cek status floor order harus "delivered" dulu
        $floorOrder = DB::table('food_floor_orders')->where('id', $floorOrderId)->first();
        if (!$floorOrder || $floorOrder->status !== 'delivered') {
            return false; // Belum dikirim, jadi belum bisa received
        }

        // Cek semua DO yang terkait dengan floor order ini
        $allDOs = DB::table('delivery_orders')
            ->where(function($q) use ($floorOrderId) {
                // Untuk Packing List biasa
                $q->where('floor_order_id', $floorOrderId)
                  ->where('source_type', 'packing_list');
            })
            ->orWhere(function($q) use ($floorOrderId) {
                // Untuk RO Supplier GR, cek via PO
                $q->where('source_type', 'ro_supplier_gr')
                  ->whereExists(function($subQ) use ($floorOrderId) {
                      $subQ->select(DB::raw(1))
                          ->from('food_good_receives as gr_ro')
                          ->join('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
                          ->whereColumn('gr_ro.id', 'delivery_orders.ro_supplier_gr_id')
                          ->where('po.source_id', $floorOrderId);
                  });
            })
            ->get();

        // Jika tidak ada DO, berarti belum dikirim, jadi tidak bisa received
        if (count($allDOs) === 0) {
            return false;
        }

        // Cek apakah semua DO sudah punya GR
        foreach ($allDOs as $deliveryOrder) {
            $hasGR = DB::table('outlet_food_good_receives')
                ->where('delivery_order_id', $deliveryOrder->id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$hasGR) {
                return false; // Ada DO yang belum di-GR
            }
        }

        return true; // Semua DO sudah di-GR
    }
} 