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
    // Create page
    public function create()
    {
        return Inertia::render('GoodReceiveOutletSupplier/Create');
    }

    // List Good Receive
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = DB::table('good_receive_outlet_suppliers as gr')
            ->leftJoin('food_floor_order_supplier_headers as ro', 'gr.ro_supplier_id', '=', 'ro.id')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            // Join untuk delivery orders dari RO Supplier GR
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo_ro', 'po.source_id', '=', 'fo_ro.id')
            ->select(
                'gr.id',
                'gr.receive_date',
                'gr.gr_number',
                'ro.supplier_fo_number as ro_number',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as received_by_name',
                'gr.status',
                'gr.warehouse_outlet_id',
                'wo.name as warehouse_outlet_name',
                // Data untuk delivery orders dari RO Supplier GR
                'do.number as do_number',
                'gr_ro.gr_number as ro_gr_number',
                'fo_ro.order_number as ro_floor_order_number'
            );
        if ($user->id_outlet != 1) {
            $query->where('gr.outlet_id', $user->id_outlet);
        }
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('gr.gr_number', 'like', "%$search%")
                  ->orWhere('ro.supplier_fo_number', 'like', "%$search%")
                  ->orWhere('do.number', 'like', "%$search%")
                  ->orWhere('gr_ro.gr_number', 'like', "%$search%")
                  ->orWhere('fo_ro.order_number', 'like', "%$search%")
                  ->orWhere('o.nama_outlet', 'like', "%$search%")
                  ->orWhere('u.nama_lengkap', 'like', "%$search%")
                  ->orWhere('wo.name', 'like', "%$search%")
                ;
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
            ->leftJoin('tbl_data_outlet as o', 'f.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'f.warehouse_outlet_id', '=', 'wo.id')
            ->where('h.supplier_fo_number', $request->ro_number)
            ->select(
                'h.*',
                's.name as supplier_name',
                'h.supplier_fo_number as ro_number',
                'f.tanggal',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name'
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

            // Ambil warehouse_outlet_id dari food_floor_orders
            $warehouseOutletId = null;
            $roHeader = DB::table('food_floor_order_supplier_headers')->where('id', $request->ro_supplier_id)->first();
            if ($roHeader && $roHeader->floor_order_id) {
                $floorOrder = DB::table('food_floor_orders')->where('id', $roHeader->floor_order_id)->first();
                if ($floorOrder && isset($floorOrder->warehouse_outlet_id)) {
                    $warehouseOutletId = $floorOrder->warehouse_outlet_id;
                }
            }

            $goodReceiveId = DB::table('good_receive_outlet_suppliers')->insertGetId([
                'gr_number' => $grNumber,
                'ro_supplier_id' => $request->ro_supplier_id,
                'outlet_id' => $outletId,
                'warehouse_outlet_id' => $warehouseOutletId,
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
                            'warehouse_outlet_id' => $warehouseOutletId,
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
                        'warehouse_outlet_id' => $warehouseOutletId,
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
                    'warehouse_outlet_id' => $warehouseOutletId,
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
                    'warehouse_outlet_id' => $warehouseOutletId,
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

            // Update floor order status jika semua DO sudah di-GR
            // TAMBAHAN VALIDASI: Hanya update jika semua DO yang terkait sudah di-GR
            $roHeader = DB::table('food_floor_order_supplier_headers')->where('id', $request->ro_supplier_id)->first();
            if ($roHeader && $roHeader->floor_order_id) {
                // Cek apakah semua DO sudah di-GR sebelum update status
                if ($this->checkAllDOsReceivedForROSupplier($roHeader->floor_order_id)) {
                    DB::table('food_floor_orders')
                        ->where('id', $roHeader->floor_order_id)
                        ->update(['status' => 'received']);
                }
            }

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
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo_ro', 'po.source_id', '=', 'fo_ro.id')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->leftJoin('food_floor_orders as f', 'ro.floor_order_id', '=', 'f.id')
            ->leftJoin('warehouse_outlets as wo_ro', 'f.warehouse_outlet_id', '=', 'wo_ro.id')
            ->leftJoin('warehouse_outlets as wo_do', 'fo_ro.warehouse_outlet_id', '=', 'wo_do.id')
            ->leftJoin('suppliers as s', 'ro.supplier_id', '=', 's.id')
            ->leftJoin('suppliers as po_supplier', 'po.supplier_id', '=', 'po_supplier.id')
            ->leftJoin('users as po_creator', 'po.created_by', '=', 'po_creator.id')
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'ro.supplier_fo_number as ro_number',
                'po.number as po_number',
                'po.date as po_date',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as received_by_name',
                'gr.status',
                'gr.notes',
                DB::raw('COALESCE(wo_ro.name, wo_do.name) as warehouse_outlet_name'),
                DB::raw('COALESCE(s.name, po_supplier.name) as supplier_name'),
                DB::raw('COALESCE(f.tanggal, fo_ro.tanggal) as ro_date'),
                'po_creator.nama_lengkap as po_creator_name',
                'gr.delivery_order_id',
                'gr.ro_supplier_id'
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
            ->leftJoin('warehouse_outlets as wo', 'f.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('good_receive_outlet_suppliers as gr', 'h.id', '=', 'gr.ro_supplier_id')
            ->select(
                'h.id',
                'h.supplier_fo_number as ro_number',
                'h.floor_order_id',
                'f.order_number as floor_order_number',
                'f.tanggal',
                'h.supplier_id',
                'wo.name as warehouse_outlet_name'
            )
            ->whereNull('gr.id');

        if ($idOutlet != 1) {
            $query->where('f.id_outlet', $idOutlet);
        }

        $ros = $query->orderByDesc('f.tanggal')->orderByDesc('h.supplier_fo_number')->get();
        return response()->json($ros);
    }

    public function getAvailableDeliveryOrders(Request $request)
    {
        $user = auth()->user();
        $idOutlet = $user->id_outlet;

        $query = DB::table('delivery_orders as do')
            ->leftJoin('good_receive_outlet_suppliers as gr', 'do.id', '=', 'gr.delivery_order_id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo_ro', 'po.source_id', '=', 'fo_ro.id')
            ->leftJoin('tbl_data_outlet as o', 'fo_ro.id_outlet', '=', 'o.id_outlet')
            ->where('do.source_type', 'ro_supplier_gr')
            ->whereNull('gr.id');

        if ($idOutlet != 1) {
            $query->where('fo_ro.id_outlet', $idOutlet);
        }

        $dos = $query->select(
            'do.id',
            'do.number as do_number',
            'do.created_at as do_date',
            'gr_ro.gr_number as ro_gr_number',
            'fo_ro.order_number as ro_floor_order_number',
            'o.nama_outlet as outlet_name'
        )
        ->orderByDesc('do.created_at')
        ->limit(20)
        ->get();

        return response()->json($dos);
    }

    public function createFromDeliveryOrder($delivery_order_id)
    {
        $do = DB::table('delivery_orders as do')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo_ro', 'po.source_id', '=', 'fo_ro.id')
            ->leftJoin('tbl_data_outlet as o', 'fo_ro.id_outlet', '=', 'o.id_outlet')
            ->select(
                'do.id',
                'do.number as do_number',
                'do.created_at as do_date',
                'gr_ro.gr_number as ro_gr_number',
                'fo_ro.order_number as ro_floor_order_number',
                'o.nama_outlet as outlet_name',
                'fo_ro.id_outlet'
            )
            ->where('do.id', $delivery_order_id)
            ->where('do.source_type', 'ro_supplier_gr')
            ->first();

        if (!$do) {
            abort(404, 'Delivery Order tidak ditemukan');
        }

        // Ambil items dari delivery order
        $items = DB::table('delivery_order_items as doi')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->select(
                'doi.id',
                'doi.item_id',
                'i.name as name',
                'doi.qty_packing_list as qty',
                'doi.unit as unit_name'
            )
            ->where('doi.delivery_order_id', $delivery_order_id)
            ->get();

        return Inertia::render('GoodReceiveOutletSupplier/CreateFromDO', [
            'deliveryOrder' => [
                'id' => $do->id,
                'number' => $do->do_number,
                'date' => $do->do_date,
                'outlet_name' => $do->outlet_name,
                'ro_gr_number' => $do->ro_gr_number,
                'ro_floor_order_number' => $do->ro_floor_order_number,
                'items' => $items
            ]
        ]);
    }

    public function storeFromDeliveryOrder(Request $request)
    {
        \Log::info('=== STORE FROM DELIVERY ORDER START ===');
        \Log::info('Request data:', $request->all());
        
        $request->validate([
            'delivery_order_id' => 'required|exists:delivery_orders,id',
            'receive_date' => 'required|date',
            'items' => 'required|array',
            'items.*.item_id' => 'required|integer',
            'items.*.qty_received' => 'required|numeric|min:0',
            'items.*.unit_id' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            $user = auth()->user();
            \Log::info('User:', ['id' => $user->id, 'name' => $user->nama_lengkap]);
            
            $do = DB::table('delivery_orders')->where('id', $request->delivery_order_id)->first();
            \Log::info('Delivery Order:', (array) $do);
            
            if (!$do || $do->source_type !== 'ro_supplier_gr') {
                throw new \Exception('Delivery Order tidak valid');
            }

            // Ambil data RO Supplier GR
            $gr = DB::table('food_good_receives')->where('id', $do->ro_supplier_gr_id)->first();
            \Log::info('Good Receive:', (array) $gr);
            
            $po = DB::table('purchase_order_foods')->where('id', $gr->po_id)->first();
            \Log::info('Purchase Order:', (array) $po);
            
            $floorOrder = DB::table('food_floor_orders')->where('id', $po->source_id)->first();
            \Log::info('Floor Order:', (array) $floorOrder);

            // Generate gr_number
            $dateStr = date('Ymd', strtotime($request->receive_date));
            $countToday = DB::table('good_receive_outlet_suppliers')
                ->whereDate('receive_date', $request->receive_date)
                ->count();
            $grNumber = 'GRS-' . $dateStr . '-' . str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);
            \Log::info('Generated GR Number:', ['gr_number' => $grNumber]);

            // Insert good receive header
            $grData = [
                'gr_number' => $grNumber,
                'ro_supplier_id' => null,
                'delivery_order_id' => $do->id,
                'outlet_id' => $floorOrder->id_outlet,
                'warehouse_outlet_id' => $floorOrder->warehouse_outlet_id,
                'receive_date' => $request->receive_date,
                'status' => 'completed',
                'received_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            \Log::info('Inserting GR Header:', $grData);
            
            $grId = DB::table('good_receive_outlet_suppliers')->insertGetId($grData);
            \Log::info('GR Header inserted with ID:', ['gr_id' => $grId]);

            // Insert items
            $insertedItems = 0;
            foreach ($request->items as $item) {
                \Log::info('Processing item:', $item);
                
                if ($item['qty_received'] > 0) {
                    // Ambil price dari food_floor_order_items
                    $floorOrderItem = DB::table('food_floor_order_items')
                        ->where('floor_order_id', $po->source_id)
                        ->where('item_id', $item['item_id'])
                        ->first();
                    
                    $price = $floorOrderItem ? $floorOrderItem->price : 0;
                    
                    $itemData = [
                        'good_receive_id' => $grId,
                        'ro_item_id' => null,
                        'item_id' => $item['item_id'],
                        'qty_ordered' => $item['qty_ordered'],
                        'qty_received' => $item['qty_received'],
                        'unit_id' => $item['unit_id'],
                        'price' => $price,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    \Log::info('Inserting item:', $itemData);
                    
                    DB::table('good_receive_outlet_supplier_items')->insert($itemData);
                    $insertedItems++;
                    
                    // === INVENTORY LOGIC ===
                    try {
                        $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                        \Log::info('DEBUG ITEM MASTER', ['itemMaster' => $itemMaster]);
                        
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
                        \Log::info('DEBUG INVENTORY ITEM', ['inventoryItemId' => $inventoryItemId]);
                        
                        $unitId = $item['unit_id'];
                        $qtyInput = $item['qty_received'];
                        $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                        $unitSmall = $itemMaster->small_unit_id;
                        $unitMedium = $itemMaster->medium_unit_id;
                        $unitLarge = $itemMaster->large_unit_id;
                        $smallConv = $itemMaster->small_conversion_qty ?: 1;
                        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                        
                        if ($unitId == $unitSmall) {
                            $qty_small = $qtyInput;
                            $qty_medium = $qtyInput / $smallConv;
                            $qty_large = $qtyInput / ($smallConv * $mediumConv);
                        } elseif ($unitId == $unitMedium) {
                            $qty_small = $qtyInput * $smallConv;
                            $qty_medium = $qtyInput;
                            $qty_large = $qtyInput / $mediumConv;
                        } elseif ($unitId == $unitLarge) {
                            $qty_small = $qtyInput * $smallConv * $mediumConv;
                            $qty_medium = $qtyInput * $mediumConv;
                            $qty_large = $qtyInput;
                        }
                        
                        // Ambil cost dari food_floor_order_items
                        $floorOrderItem = DB::table('food_floor_order_items')
                            ->where('floor_order_id', $po->source_id)
                            ->where('item_id', $item['item_id'])
                            ->first();
                        
                        $cost = $floorOrderItem ? $floorOrderItem->price : 0;
                        
                        // Hitung MAC (Moving Average Cost)
                        $existingStock = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $inventoryItemId)
                            ->where('id_outlet', $floorOrder->id_outlet)
                            ->where('warehouse_outlet_id', $floorOrder->warehouse_outlet_id)
                            ->first();
                        
                        $mac = 0;
                        if ($existingStock && $existingStock->qty_small > 0) {
                            $mac = ($existingStock->value + ($qty_small * $cost)) / ($existingStock->qty_small + $qty_small);
                        } else {
                            $mac = $cost;
                        }
                        
                        // Update atau insert stock
                        if ($existingStock) {
                            DB::table('outlet_food_inventory_stocks')
                                ->where('id', $existingStock->id)
                                ->update([
                                    'qty_small' => $existingStock->qty_small + $qty_small,
                                    'qty_medium' => $existingStock->qty_medium + $qty_medium,
                                    'qty_large' => $existingStock->qty_large + $qty_large,
                                    'value' => ($existingStock->qty_small + $qty_small) * $mac,
                                    'last_cost_small' => $mac,
                                    'updated_at' => now(),
                                ]);
                        } else {
                            DB::table('outlet_food_inventory_stocks')->insert([
                                'inventory_item_id' => $inventoryItemId,
                                'id_outlet' => $floorOrder->id_outlet,
                                'warehouse_outlet_id' => $floorOrder->warehouse_outlet_id,
                                'qty_small' => $qty_small,
                                'qty_medium' => $qty_medium,
                                'qty_large' => $qty_large,
                                'value' => $qty_small * $mac,
                                'last_cost_small' => $mac,
                                'last_cost_medium' => $mac * $smallConv,
                                'last_cost_large' => $mac * $smallConv * $mediumConv,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        
                        // Insert kartu stok
                        $lastCard = DB::table('outlet_food_inventory_cards')
                            ->where('inventory_item_id', $inventoryItemId)
                            ->where('id_outlet', $floorOrder->id_outlet)
                            ->where('warehouse_outlet_id', $floorOrder->warehouse_outlet_id)
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
                            'id_outlet' => $floorOrder->id_outlet,
                            'warehouse_outlet_id' => $floorOrder->warehouse_outlet_id,
                            'date' => $request->receive_date,
                            'reference_type' => 'good_receive_outlet_supplier',
                            'reference_id' => $grId,
                            'in_qty_small' => $qty_small,
                            'in_qty_medium' => $qty_medium,
                            'in_qty_large' => $qty_large,
                            'out_qty_small' => 0,
                            'out_qty_medium' => 0,
                            'out_qty_large' => 0,
                            'cost_per_small' => $mac,
                            'cost_per_medium' => $mac * $smallConv,
                            'cost_per_large' => $mac * $smallConv * $mediumConv,
                            'value_in' => $qty_small * $mac,
                            'value_out' => 0,
                            'saldo_qty_small' => $saldo_qty_small,
                            'saldo_qty_medium' => $saldo_qty_medium,
                            'saldo_qty_large' => $saldo_qty_large,
                            'saldo_value' => $saldo_qty_small * $mac,
                            'description' => 'Good Receive Outlet Supplier',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        \Log::info('DEBUG KARTU STOK INSERTED');
                        
                        // Insert cost history
                        $lastCostHistory = DB::table('outlet_food_inventory_cost_histories')
                            ->where('inventory_item_id', $inventoryItemId)
                            ->where('id_outlet', $floorOrder->id_outlet)
                            ->where('warehouse_outlet_id', $floorOrder->warehouse_outlet_id)
                            ->orderByDesc('date')
                            ->orderByDesc('created_at')
                            ->first();
                        $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                        DB::table('outlet_food_inventory_cost_histories')->insert([
                            'inventory_item_id' => $inventoryItemId,
                            'id_outlet' => $floorOrder->id_outlet,
                            'warehouse_outlet_id' => $floorOrder->warehouse_outlet_id,
                            'date' => $request->receive_date,
                            'old_cost' => $old_cost,
                            'new_cost' => $mac,
                            'reference_type' => 'good_receive_outlet_supplier',
                            'reference_id' => $grId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        \Log::info('DEBUG COST HISTORY INSERTED');
                        
                    } catch (\Exception $inventoryError) {
                        \Log::error('Inventory error for item ' . $item['item_id'] . ':', ['error' => $inventoryError->getMessage()]);
                        \Log::error('Inventory error trace:', ['trace' => $inventoryError->getTraceAsString()]);
                        // Don't throw error, just log it
                    }
                }
            }
            \Log::info('Items inserted:', ['count' => $insertedItems]);

            DB::commit();
            \Log::info('=== STORE FROM DELIVERY ORDER SUCCESS ===');
            return response()->json(['success' => true, 'message' => 'Good Receive berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('=== STORE FROM DELIVERY ORDER ERROR ===');
            \Log::error('Error message:', ['message' => $e->getMessage()]);
            \Log::error('Error file:', ['file' => $e->getFile()]);
            \Log::error('Error line:', ['line' => $e->getLine()]);
            \Log::error('Error trace:', ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper function: Cek apakah semua DO yang terkait dengan floor order (RO Supplier) sudah di-GR
     * Hanya return true jika:
     * 1. Floor order status sudah "delivered" (barang sudah dikirim)
     * 2. Ada DO yang terkait dengan floor order
     * 3. Semua DO sudah punya GR Supplier (good_receive_outlet_suppliers)
     */
    private function checkAllDOsReceivedForROSupplier($floorOrderId)
    {
        // Cek status floor order harus "delivered" dulu
        $floorOrder = DB::table('food_floor_orders')->where('id', $floorOrderId)->first();
        if (!$floorOrder || $floorOrder->status !== 'delivered') {
            return false; // Belum dikirim, jadi belum bisa received
        }

        // Cek semua DO yang terkait dengan floor order ini (untuk RO Supplier)
        // DO untuk RO Supplier memiliki ro_supplier_gr_id yang terkait dengan PO yang source_id = floor_order_id
        $allDOs = DB::table('delivery_orders')
            ->where('source_type', 'ro_supplier_gr')
            ->whereExists(function($q) use ($floorOrderId) {
                $q->select(DB::raw(1))
                    ->from('food_good_receives as gr_ro')
                    ->join('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
                    ->whereColumn('gr_ro.id', 'delivery_orders.ro_supplier_gr_id')
                    ->where('po.source_id', $floorOrderId);
            })
            ->get();

        // Jika tidak ada DO, berarti belum dikirim, jadi tidak bisa received
        if (count($allDOs) === 0) {
            return false;
        }

        // Cek apakah semua DO sudah punya GR Supplier
        foreach ($allDOs as $deliveryOrder) {
            $hasGR = DB::table('good_receive_outlet_suppliers')
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