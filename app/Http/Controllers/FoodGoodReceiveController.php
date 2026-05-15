<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\ActivityLog;
use App\Support\InventorySerialInUse;
use Carbon\Carbon;

class FoodGoodReceiveController extends Controller
{
    // List Good Receive
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Check if user can delete good receive
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        
        $query = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'gr.notes',
                'gr.received_by',
                'gr.supplier_id',
                'gr.created_at',
                'gr.updated_at',
                'po.id as po_id',
                'po.number as po_number',
                's.name as supplier_name',
                'u.nama_lengkap as received_by_name'
            );
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('gr.gr_number', 'like', "%$search%")
                  ->orWhere('po.number', 'like', "%$search%")
                  ->orWhere('s.name', 'like', "%$search%")
                  ->orWhere('u.nama_lengkap', 'like', "%$search%")
                  ->orWhere('gr.notes', 'like', "%$search%")
                ;
            });
        }
        if ($request->from) {
            $query->whereDate('gr.receive_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('gr.receive_date', '<=', $request->to);
        }
        
        // Check if request wants JSON (from API)
        if ($request->expectsJson() || $request->is('api/*')) {
            $perPage = $request->input('per_page', 20);
            $list = $query->orderByDesc('gr.created_at')->paginate($perPage);
            
            // Load items for each good receive
            $goodReceivesWithItems = $list->map(function($gr) {
                $items = DB::table('food_good_receive_items as gri')
                    ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
                    ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
                    ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
                    ->select(
                        'gri.id',
                        'gri.good_receive_id',
                        'gri.item_id',
                        'i.name as item_name',
                        'gri.qty_ordered',
                        'gri.qty_received',
                        'gri.unit_id',
                        'u.name as unit_name',
                        'gri.notes',
                        'gri.created_at',
                        'gri.updated_at'
                    )
                    ->where('gri.good_receive_id', $gr->id)
                    ->get();
                
                return (object)[
                    'id' => $gr->id,
                    'gr_number' => $gr->gr_number,
                    'receive_date' => $gr->receive_date,
                    'po_id' => $gr->po_id,
                    'po_number' => $gr->po_number,
                    'supplier_id' => $gr->supplier_id,
                    'supplier_name' => $gr->supplier_name,
                    'received_by' => $gr->received_by,
                    'received_by_name' => $gr->received_by_name,
                    'notes' => $gr->notes,
                    'items' => $items,
                    'created_at' => $gr->created_at,
                    'updated_at' => $gr->updated_at,
                ];
            });
            
            return response()->json([
                'data' => $goodReceivesWithItems,
                'current_page' => $list->currentPage(),
                'last_page' => $list->lastPage(),
                'per_page' => $list->perPage(),
                'total' => $list->total(),
            ]);
        }
        
        // Return inertia for web
        $list = $query->orderByDesc('gr.created_at')->paginate(10)->withQueryString();
        return inertia('FoodGoodReceive/Index', [
            'goodReceives' => $list,
            'filters' => $request->only(['search', 'from', 'to']),
            'canDelete' => $canDelete,
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
        // Cek source_type dari PO untuk menentukan cara mengambil data items
        if ($po->source_type === 'ro_supplier') {
            // Untuk source_type = 'ro_supplier', ambil data items tanpa join ke pr_foods
            $items = DB::table('purchase_order_food_items as poi')
                ->leftJoin('items as i', 'poi.item_id', '=', 'i.id')
                ->leftJoin('units as u', 'poi.unit_id', '=', 'u.id')
                ->where('poi.purchase_order_food_id', $po->id)
                ->select('poi.*', 'i.name as item_name', 'u.name as unit_name')
                ->get();
            
            // Set warehouse_division_name = 'Perishable' untuk semua items ro_supplier
            $items = $items->map(function($item) {
                $item->warehouse_division_name = 'Perishable';
                return $item;
            });
        } else {
            // Untuk source_type selain 'ro_supplier', gunakan alur yang ada sekarang
            $items = DB::table('purchase_order_food_items as poi')
                ->leftJoin('items as i', 'poi.item_id', '=', 'i.id')
                ->leftJoin('units as u', 'poi.unit_id', '=', 'u.id')
                ->leftJoin('pr_food_items as pfi', 'poi.pr_food_item_id', '=', 'pfi.id')
                ->leftJoin('pr_foods as pf', 'pfi.pr_food_id', '=', 'pf.id')
                ->leftJoin('warehouse_division as wd', 'pf.warehouse_division_id', '=', 'wd.id')
                ->where('poi.purchase_order_food_id', $po->id)
                ->select('poi.*', 'i.name as item_name', 'u.name as unit_name', 'wd.name as warehouse_division_name')
                ->get();
        }
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
            'notes' => 'nullable|string|max:1000',
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
            // Ambil informasi PO untuk mengecek source_type
            $po = DB::table('purchase_order_foods')->where('id', $request->po_id)->first();
            
            foreach ($request->items as $item) {
                DB::table('food_good_receive_items')->insert([
                    'good_receive_id' => $goodReceiveId,
                    'po_item_id' => $item['po_item_id'],
                    'item_id' => $item['item_id'],
                    'qty_ordered' => $item['qty_ordered'],
                    'qty_received' => $item['qty_received'],
                    'used_qty' => $item['qty_received'],
                    'unit_id' => $item['unit_id'],
                    'notes' => $item['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // === INVENTORY LOGIC ===
                // Ambil poItem untuk semua kasus (diperlukan untuk cost calculation)
                $poItem = DB::table('purchase_order_food_items')->where('id', $item['po_item_id'])->first();
                
                // Cek source_type dari PO
                if ($po && $po->source_type === 'ro_supplier') {
                    // Untuk PO dengan source_type = 'ro_supplier', set warehouse = 1 dan warehouse_division = 1
                    $warehouseId = 1;
                    $warehouseDivisionId = 1;
                    
                    \Log::info('DEBUG: Using fixed warehouse for ro_supplier', [
                        'po_id' => $request->po_id,
                        'source_type' => $po->source_type,
                        'warehouse_id' => $warehouseId,
                        'warehouse_division_id' => $warehouseDivisionId,
                        'item_id' => $item['item_id']
                    ]);
                } else {
                    // Untuk source_type selain 'ro_supplier', gunakan alur yang ada sekarang
                    $prFoodItem = $poItem ? DB::table('pr_food_items')->where('id', $poItem->pr_food_item_id)->first() : null;
                    $pr = $prFoodItem ? DB::table('pr_foods')->where('id', $prFoodItem->pr_food_id)->first() : null;
                    $warehouseId = $pr ? $pr->warehouse_id : null;
                    $warehouseDivisionId = $pr ? $pr->warehouse_division_id : null;
                    
                    \Log::info('DEBUG: Using existing warehouse logic', [
                        'po_id' => $request->po_id,
                        'source_type' => $po ? $po->source_type : 'null',
                        'po_item_id' => $item['po_item_id'],
                        'poItem' => $poItem ? $poItem->id : 'null',
                        'pr_food_item_id' => $poItem ? $poItem->pr_food_item_id : 'null',
                        'prFoodItem' => $prFoodItem ? $prFoodItem->id : 'null',
                        'pr_food_id' => $prFoodItem ? $prFoodItem->pr_food_id : 'null',
                        'pr' => $pr ? $pr->id : 'null',
                        'warehouse_id' => $warehouseId,
                        'warehouse_division_id' => $warehouseDivisionId,
                        'item_id' => $item['item_id']
                    ]);
                }
                
                // Jika warehouse_id tidak ditemukan (untuk source_type selain ro_supplier), coba ambil dari warehouse default atau berikan error yang lebih informatif
                if (!$warehouseId && (!$po || $po->source_type !== 'ro_supplier')) {
                    // Coba ambil warehouse default (warehouse pertama)
                    $defaultWarehouse = DB::table('warehouses')->first();
                    if ($defaultWarehouse) {
                        $warehouseId = $defaultWarehouse->id;
                        \Log::warning('Using default warehouse for item', [
                            'item_id' => $item['item_id'],
                            'po_item_id' => $item['po_item_id'],
                            'default_warehouse_id' => $warehouseId
                        ]);
                    } else {
                        throw new \Exception('warehouse_id tidak ditemukan di PR terkait item. Item ID: ' . $item['item_id'] . ', PO Item ID: ' . $item['po_item_id'] . '. Silakan periksa data PR terkait.');
                    }
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
                // 1. Hitung cost
                $cost = $poItem ? $poItem->price : 0;
                $unit_id = $item['unit_id'];
                $cost_small = $cost;
                if ($unit_id == $itemMaster->large_unit_id) {
                    $cost_small = $cost / (($itemMaster->small_conversion_qty ?: 1) * ($itemMaster->medium_conversion_qty ?: 1));
                } elseif ($unit_id == $itemMaster->medium_unit_id) {
                    $cost_small = $cost / ($itemMaster->small_conversion_qty ?: 1);
                }
                $cost_medium = $cost_small * ($itemMaster->small_conversion_qty ?: 1);
                $cost_large = $cost_medium * ($itemMaster->medium_conversion_qty ?: 1);
                // 2. Hitung qty (konversi ke semua unit)
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
                // 4. Insert/update ke food_inventory_stocks dengan Moving Average Cost
                $existingStock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                $qty_lama = $existingStock ? $existingStock->qty_small : 0;
                $nilai_lama = $existingStock ? $existingStock->value : 0;
                $qty_baru = $qty_small;
                $nilai_baru = $qty_small_for_value * $cost_small;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $cost_small;
                if ($existingStock) {
                    DB::table('food_inventory_stocks')
                        ->where('id', $existingStock->id)
                        ->update([
                            'qty_small' => $total_qty,
                            'qty_medium' => $existingStock->qty_medium + $qty_medium,
                            'qty_large' => $existingStock->qty_large + $qty_large,
                            'value' => $total_nilai,
                            'last_cost_small' => $mac,
                            'last_cost_medium' => $cost_medium, // opsional, bisa juga pakai konversi dari MAC
                            'last_cost_large' => $cost_large,   // opsional
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'warehouse_id' => $warehouseId,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $nilai_baru,
                        'last_cost_small' => $cost_small,
                        'last_cost_medium' => $cost_medium,
                        'last_cost_large' => $cost_large,
                        'updated_at' => now(),
                    ]);
                }
                // 5. Hitung saldo kartu stok (stock card)
                $lastCard = DB::table('food_inventory_cards')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $warehouseId)
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
                // 6. Insert ke food_inventory_cards (stock card)
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
                    'value_in' => $qty_small_for_value * $cost_small,
                    'value_out' => 0,
                    'saldo_qty_small' => $saldo_qty_small,
                    'saldo_qty_medium' => $saldo_qty_medium,
                    'saldo_qty_large' => $saldo_qty_large,
                    'saldo_value' => ($existingStock ? ($existingStock->qty_small + $qty_small) : $qty_small) * $cost_small,
                    'description' => 'Good Receive',
                    'created_at' => now(),
                ]);
                // 7. Insert ke food_inventory_cost_histories pakai MAC
                $lastCostHistory = DB::table('food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $warehouseId)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                DB::table('food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'warehouse_id' => $warehouseId,
                    'warehouse_division_id' => $warehouseDivisionId,
                    'date' => $request->receive_date,
                    'old_cost' => $old_cost,
                    'new_cost' => $cost_small, // Harga pembelian terakhir
                    'mac' => $mac, // Moving Average Cost
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
            // Update status PO menjadi received
            DB::table('purchase_order_foods')->where('id', $request->po_id)->update(['status' => 'received']);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Good Receive berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $gr = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'gr.po_id',
                'gr.supplier_id',
                'gr.received_by',
                'gr.notes',
                'gr.created_at',
                'gr.updated_at',
                'po.number as po_number',
                's.name as supplier_name',
                'u.nama_lengkap as received_by_name'
            )
            ->where('gr.id', $id)
            ->first();
        if (!$gr) {
            return response()->json(['message' => 'Good Receive tidak ditemukan'], 404);
        }
        $items = DB::table('food_good_receive_items as gri')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
            ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
            ->select(
                'gri.id',
                'gri.good_receive_id',
                'gri.item_id',
                'i.name as item_name',
                'gri.qty_ordered',
                'gri.qty_received',
                'gri.unit_id',
                'u.name as unit_name',
                'gri.notes',
                'gri.created_at',
                'gri.updated_at',
                'poi.price'
            )
            ->where('gri.good_receive_id', $id)
            ->get();
        $gr->items = $items;
        return response()->json($gr);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.qty_received' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            // 1. Get current GR data
            $gr = DB::table('food_good_receives')->where('id', $id)->first();
            if (!$gr) {
                throw new \Exception('Good Receive tidak ditemukan');
            }

            // 2. Get current items
            $currentItems = DB::table('food_good_receive_items')
                ->where('good_receive_id', $id)
                ->get();

            // 3. Hapus semua record inventory terkait GR ini
            foreach ($currentItems as $currentItem) {
                // Get PO item untuk warehouse
                $poItem = DB::table('purchase_order_food_items')->where('id', $currentItem->po_item_id)->first();
                $prFoodItem = $poItem ? DB::table('pr_food_items')->where('id', $poItem->pr_food_item_id)->first() : null;
                $pr = $prFoodItem ? DB::table('pr_foods')->where('id', $prFoodItem->pr_food_id)->first() : null;
                $warehouseId = $pr ? $pr->warehouse_id : null;
                $inventoryItem = DB::table('food_inventory_items')->where('item_id', $currentItem->item_id)->first();
                if ($inventoryItem && $warehouseId) {
                    // Hapus stok (reset ke 0, atau bisa juga diadjust sesuai kebutuhan)
                    DB::table('food_inventory_stocks')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('warehouse_id', $warehouseId)
                        ->delete();
                }
                // Hapus kartu stok
                DB::table('food_inventory_cards')
                    ->where('reference_type', 'good_receive')
                    ->where('reference_id', $id)
                    ->where('inventory_item_id', $inventoryItem ? $inventoryItem->id : 0)
                    ->delete();
                // Hapus cost history
                DB::table('food_inventory_cost_histories')
                    ->where('reference_type', 'good_receive')
                    ->where('reference_id', $id)
                    ->where('inventory_item_id', $inventoryItem ? $inventoryItem->id : 0)
                    ->delete();
            }

            // 4. Update good_receive_items sesuai input
            foreach ($request->items as $item) {
                DB::table('food_good_receive_items')
                    ->where('id', $item['id'])
                    ->update([
                        'qty_received' => $item['qty_received'],
                        'updated_at' => now()
                    ]);
            }

            // 5. Insert ulang inventory (mirip proses store)
            foreach ($request->items as $item) {
                $currentItem = DB::table('food_good_receive_items')->where('id', $item['id'])->first();
                $poItem = DB::table('purchase_order_food_items')->where('id', $currentItem->po_item_id)->first();
                $prFoodItem = $poItem ? DB::table('pr_food_items')->where('id', $poItem->pr_food_item_id)->first() : null;
                $pr = $prFoodItem ? DB::table('pr_foods')->where('id', $prFoodItem->pr_food_id)->first() : null;
                $warehouseId = $pr ? $pr->warehouse_id : null;
                
                // Jika warehouse_id tidak ditemukan, coba ambil dari warehouse default
                if (!$warehouseId) {
                    $defaultWarehouse = DB::table('warehouses')->first();
                    if ($defaultWarehouse) {
                        $warehouseId = $defaultWarehouse->id;
                        \Log::warning('Using default warehouse for update item', [
                            'item_id' => $currentItem->item_id,
                            'po_item_id' => $currentItem->po_item_id,
                            'default_warehouse_id' => $warehouseId
                        ]);
                    } else {
                        throw new \Exception('warehouse_id tidak ditemukan di PR terkait item untuk update. Item ID: ' . $currentItem->item_id . ', PO Item ID: ' . $currentItem->po_item_id);
                    }
                }
                $itemMaster = DB::table('items')->where('id', $currentItem->item_id)->first();
                $inventoryItem = DB::table('food_inventory_items')->where('item_id', $currentItem->item_id)->first();
                if (!$inventoryItem) {
                    $inventoryItemId = DB::table('food_inventory_items')->insertGetId([
                        'item_id' => $currentItem->item_id,
                        'small_unit_id' => $itemMaster->small_unit_id,
                        'medium_unit_id' => $itemMaster->medium_unit_id,
                        'large_unit_id' => $itemMaster->large_unit_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $inventoryItemId = $inventoryItem->id;
                }
                // 1. Hitung cost
                $cost = $poItem ? $poItem->price : 0;
                $unit_id = $item['unit_id'];
                $cost_small = $cost;
                if ($unit_id == $itemMaster->large_unit_id) {
                    $cost_small = $cost / (($itemMaster->small_conversion_qty ?: 1) * ($itemMaster->medium_conversion_qty ?: 1));
                } elseif ($unit_id == $itemMaster->medium_unit_id) {
                    $cost_small = $cost / ($itemMaster->small_conversion_qty ?: 1);
                }
                $cost_medium = $cost_small * ($itemMaster->small_conversion_qty ?: 1);
                $cost_large = $cost_medium * ($itemMaster->medium_conversion_qty ?: 1);
                // 2. Hitung qty (konversi ke semua unit)
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                $qty_small = 0; $qty_medium = 0; $qty_large = 0; $qty_small_for_value = 0;
                if ($currentItem->unit_id == $itemMaster->large_unit_id) {
                    $qty_large = (float) $item['qty_received'];
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_small_for_value = $qty_small;
                } elseif ($currentItem->unit_id == $itemMaster->medium_unit_id) {
                    $qty_medium = (float) $item['qty_received'];
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_small_for_value = $qty_small;
                } elseif ($currentItem->unit_id == $itemMaster->small_unit_id) {
                    $qty_small = (float) $item['qty_received'];
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    $qty_small_for_value = $qty_small;
                }
                // 4. Insert/update ke food_inventory_stocks dengan Moving Average Cost
                $existingStock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                $qty_lama = $existingStock ? $existingStock->qty_small : 0;
                $nilai_lama = $existingStock ? $existingStock->value : 0;
                $qty_baru = $qty_small;
                $nilai_baru = $qty_small_for_value * $cost_small;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $cost_small;
                if ($existingStock) {
                    DB::table('food_inventory_stocks')
                        ->where('id', $existingStock->id)
                        ->update([
                            'qty_small' => $total_qty,
                            'qty_medium' => $existingStock->qty_medium + $qty_medium,
                            'qty_large' => $existingStock->qty_large + $qty_large,
                            'value' => $total_nilai,
                            'last_cost_small' => $mac,
                            'last_cost_medium' => $cost_medium, // opsional, bisa juga pakai konversi dari MAC
                            'last_cost_large' => $cost_large,   // opsional
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'warehouse_id' => $warehouseId,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $nilai_baru,
                        'last_cost_small' => $cost_small,
                        'last_cost_medium' => $cost_medium,
                        'last_cost_large' => $cost_large,
                        'updated_at' => now(),
                    ]);
                }
                // 5. Hitung saldo kartu stok (stock card)
                $lastCard = DB::table('food_inventory_cards')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $warehouseId)
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
                // 6. Insert ke food_inventory_cards (stock card)
                DB::table('food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'warehouse_id' => $warehouseId,
                    'date' => $gr->receive_date,
                    'reference_type' => 'good_receive',
                    'reference_id' => $id,
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
                    'description' => 'Good Receive',
                    'created_at' => now(),
                ]);
                // Insert food_inventory_cost_histories
                $lastCostHistory = DB::table('food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $warehouseId)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                DB::table('food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'warehouse_id' => $warehouseId,
                    'date' => $gr->receive_date,
                    'old_cost' => $old_cost,
                    'new_cost' => $cost_small, // Harga pembelian terakhir
                    'mac' => $mac, // Moving Average Cost
                    'type' => 'good_receive',
                    'reference_type' => 'good_receive',
                    'reference_id' => $id,
                    'created_at' => now(),
                ]);
            }

            // 6. Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'good_receive',
                'description' => 'Update Good Receive: ' . $id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => json_encode($currentItems),
                'new_data' => json_encode($request->items),
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Good Receive berhasil diupdate']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            
            // Cek authorization: hanya superadmin atau user dengan division_id=11
            $isSuperAdmin = $user && $user->id_role === '5af56935b011a';
            $isWarehouseDivision11 = $user && $user->division_id == 11;
            
            if (!$isSuperAdmin && !$isWarehouseDivision11) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk menghapus Good Receive. Hanya superadmin atau user dengan division warehouse yang dapat menghapus.'
                ], 403);
            }
            
            $gr = DB::table('food_good_receives')->where('id', $id)->first();
            if (!$gr) {
                return response()->json(['success' => false, 'message' => 'Good Receive tidak ditemukan'], 404);
            }
            
            // Ambil semua item GR
            $items = DB::table('food_good_receive_items')->where('good_receive_id', $id)->get();
            
            foreach ($items as $item) {
                // Get PO item untuk warehouse
                $poItem = DB::table('purchase_order_food_items')->where('id', $item->po_item_id)->first();
                $prFoodItem = $poItem ? DB::table('pr_food_items')->where('id', $poItem->pr_food_item_id)->first() : null;
                $pr = $prFoodItem ? DB::table('pr_foods')->where('id', $prFoodItem->pr_food_id)->first() : null;
                $warehouseId = $pr ? $pr->warehouse_id : null;
                
                // Jika warehouse_id tidak ditemukan, coba ambil dari warehouse default
                if (!$warehouseId) {
                    $defaultWarehouse = DB::table('warehouses')->first();
                    if ($defaultWarehouse) {
                        $warehouseId = $defaultWarehouse->id;
                        \Log::warning('Using default warehouse for destroy item', [
                            'item_id' => $item->item_id,
                            'po_item_id' => $item->po_item_id,
                            'default_warehouse_id' => $warehouseId
                        ]);
                    } else {
                        throw new \Exception('Warehouse ID tidak ditemukan untuk item: ' . $item->item_id . '. PO Item ID: ' . $item->po_item_id);
                    }
                }
                
                $inventoryItem = DB::table('food_inventory_items')->where('item_id', $item->item_id)->first();
                if (!$inventoryItem) {
                    continue; // Skip jika item tidak ada di inventory
                }
                
                // === ROLLBACK INVENTORY LOGIC ===
                // 1. Ambil data item master untuk konversi unit
                $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
                if (!$itemMaster) continue;
                
                // 2. Konversi qty received ke small unit untuk rollback
                $unitId = $item->unit_id;
                $qtyReceived = $item->qty_received;
                $qty_small_to_rollback = 0;
                
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                
                if ($unitId == $itemMaster->small_unit_id) {
                    $qty_small_to_rollback = $qtyReceived;
                } elseif ($unitId == $itemMaster->medium_unit_id) {
                    $qty_small_to_rollback = $qtyReceived * $smallConv;
                } elseif ($unitId == $itemMaster->large_unit_id) {
                    $qty_small_to_rollback = $qtyReceived * $smallConv * $mediumConv;
                } else {
                    $qty_small_to_rollback = $qtyReceived;
                }
                
                // 3. Rollback inventory stocks
                $currentStock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                
                if ($currentStock) {
                    $newQtySmall = max(0, $currentStock->qty_small - $qty_small_to_rollback);
                    $newQtyMedium = max(0, $currentStock->qty_medium - ($qty_small_to_rollback / $smallConv));
                    $newQtyLarge = max(0, $currentStock->qty_large - ($qty_small_to_rollback / ($smallConv * $mediumConv)));
                    
                    // Update atau delete stock
                    if ($newQtySmall <= 0 && $newQtyMedium <= 0 && $newQtyLarge <= 0) {
                        DB::table('food_inventory_stocks')
                            ->where('inventory_item_id', $inventoryItem->id)
                            ->where('warehouse_id', $warehouseId)
                            ->delete();
                    } else {
                        DB::table('food_inventory_stocks')
                            ->where('inventory_item_id', $inventoryItem->id)
                            ->where('warehouse_id', $warehouseId)
                            ->update([
                                'qty_small' => $newQtySmall,
                                'qty_medium' => $newQtyMedium,
                                'qty_large' => $newQtyLarge,
                                'value' => $newQtySmall * ($currentStock->last_cost_small ?: 0),
                                'updated_at' => now()
                            ]);
                    }
                }
                
                // 4. Hapus kartu stok terkait GR ini
                DB::table('food_inventory_cards')
                    ->where('reference_type', 'good_receive')
                    ->where('reference_id', $id)
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->delete();
                
                // 5. Hapus cost history terkait GR ini
                DB::table('food_inventory_cost_histories')
                    ->where('reference_type', 'good_receive')
                    ->where('reference_id', $id)
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->delete();
            }
            
            // Hapus detail GR
            DB::table('food_good_receive_items')->where('good_receive_id', $id)->delete();
            
            // Hapus GR
            DB::table('food_good_receives')->where('id', $id)->delete();
            
            // Update status PO menjadi approved (karena GR dihapus)
            DB::table('purchase_order_foods')->where('id', $gr->po_id)->update(['status' => 'approved']);
            
            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'good_receive',
                'description' => 'Delete Good Receive: ' . $id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($gr),
                'new_data' => null,
                'created_at' => now(),
            ]);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Good Receive berhasil dihapus dan inventory telah di-rollback']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting good receive: ' . $e->getMessage(), [
                'good_receive_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // API untuk mengambil data struk Good Receive
    public function strukData($id)
    {
        $gr = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.gr_number as grNumber',
                'gr.receive_date as date',
                'gr.notes',
                's.name as supplier',
                'u.nama_lengkap as receivedByName',
                'po.number as poNumber'
            )
            ->where('gr.id', $id)
            ->first();
            
        if (!$gr) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        
        $items = DB::table('food_good_receive_items as gri')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
            ->select(
                'gri.id',
                'i.name',
                'gri.qty_received',
                'gri.unit_id',
                'u.name as unit',
                'u.code as unit_code'
            )
            ->where('gri.good_receive_id', $id)
            ->get();
            
        return response()->json([
            'grNumber' => $gr->grNumber,
            'date' => $gr->date,
            'supplier' => $gr->supplier,
            'receivedByName' => $gr->receivedByName,
            'poNumber' => $gr->poNumber,
            'notes' => $gr->notes,
            'items' => $items
        ]);
    }

    public function serialSummary($goodReceiveId)
    {
        $case = InventorySerialInUse::mysqlSumInUseCase('s');
        $summary = DB::table('inventory_item_serials as s')
            ->select(
                's.source_item_id as good_receive_item_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("{$case} as in_use")
            )
            ->where('s.source_type', 'good_receive')
            ->where('s.source_id', $goodReceiveId)
            ->groupBy('s.source_item_id')
            ->get();

        return response()->json($summary);
    }

    public function serialUnits($goodReceiveItemId)
    {
        $grItem = DB::table('food_good_receive_items as gri')
            ->join('items as i', 'i.id', '=', 'gri.item_id')
            ->leftJoin('units as u_received', 'u_received.id', '=', 'gri.unit_id')
            ->select(
                'gri.id',
                'gri.good_receive_id',
                'gri.item_id',
                'gri.po_item_id',
                'gri.qty_received',
                'gri.unit_id as received_unit_id',
                'u_received.name as received_unit_name',
                'i.name as item_name',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->where('gri.id', $goodReceiveItemId)
            ->first();

        if (!$grItem) {
            return response()->json(['message' => 'Item GR tidak ditemukan'], 404);
        }

        $smallConv = (float) ($grItem->small_conversion_qty ?: 1);
        $mediumConv = (float) ($grItem->medium_conversion_qty ?: 1);
        $qtyReceived = (float) ($grItem->qty_received ?: 0);

        $qtySmall = $qtyReceived;
        if ((int) $grItem->received_unit_id === (int) $grItem->medium_unit_id) {
            $qtySmall = $qtyReceived * $smallConv;
        } elseif ((int) $grItem->received_unit_id === (int) $grItem->large_unit_id) {
            $qtySmall = $qtyReceived * $smallConv * $mediumConv;
        }

        $unitIds = collect([
            $grItem->small_unit_id,
            $grItem->medium_unit_id,
            $grItem->large_unit_id,
        ])->filter()->unique()->values();

        $unitsMaster = DB::table('units')
            ->whereIn('id', $unitIds)
            ->pluck('name', 'id');

        $units = [];
        foreach ($unitIds as $unitId) {
            $unitIdInt = (int) $unitId;
            $convertedQty = $qtySmall;
            if ($unitIdInt === (int) $grItem->medium_unit_id) {
                $convertedQty = $smallConv > 0 ? ($qtySmall / $smallConv) : 0;
            } elseif ($unitIdInt === (int) $grItem->large_unit_id) {
                $divider = $smallConv * $mediumConv;
                $convertedQty = $divider > 0 ? ($qtySmall / $divider) : 0;
            }

            $units[] = [
                'unit_id' => $unitIdInt,
                'unit_name' => $unitsMaster[$unitIdInt] ?? "Unit {$unitIdInt}",
                'converted_qty' => round($convertedQty, 4),
            ];
        }

        return response()->json([
            'good_receive_item_id' => (int) $grItem->id,
            'item_name' => $grItem->item_name,
            'qty_received' => round($qtyReceived, 4),
            'received_unit_name' => $grItem->received_unit_name,
            'units' => $units,
        ]);
    }

    public function generateSerials(Request $request, $goodReceiveItemId)
    {
        $validated = $request->validate([
            'unit_id' => 'required|integer|exists:units,id',
            'repack_unit_id' => 'nullable|integer|exists:units,id',
            'repack_qty' => 'nullable|numeric|min:0.01',
        ]);

        $grItem = DB::table('food_good_receive_items as gri')
            ->join('food_good_receives as gr', 'gr.id', '=', 'gri.good_receive_id')
            ->join('purchase_order_foods as po', 'po.id', '=', 'gr.po_id')
            ->join('items as i', 'i.id', '=', 'gri.item_id')
            ->leftJoin('purchase_order_food_items as poi', 'poi.id', '=', 'gri.po_item_id')
            ->leftJoin('pr_food_items as pfi', 'pfi.id', '=', 'poi.pr_food_item_id')
            ->leftJoin('pr_foods as pf', 'pf.id', '=', 'pfi.pr_food_id')
            ->select(
                'gri.id',
                'gri.good_receive_id',
                'gri.item_id',
                'gri.po_item_id',
                'gri.qty_received',
                'gri.unit_id as received_unit_id',
                'gr.gr_number',
                'gr.po_id',
                'po.number as po_number',
                'po.source_type as po_source_type',
                'poi.unit_id as po_item_unit_id',
                'poi.price as po_item_price',
                'pf.id as pr_food_id',
                'pf.pr_number as pr_number',
                'pf.warehouse_id as warehouse_id',
                'pfi.id as pr_food_item_id',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->where('gri.id', $goodReceiveItemId)
            ->first();

        if (!$grItem) {
            return response()->json(['message' => 'Item GR tidak ditemukan'], 404);
        }

        $targetUnitId = (int) $validated['unit_id'];
        $validUnitIds = collect([$grItem->small_unit_id, $grItem->medium_unit_id, $grItem->large_unit_id])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (!in_array($targetUnitId, $validUnitIds, true)) {
            return response()->json(['message' => 'Unit tidak sesuai konversi item'], 422);
        }

        $smallConv = (float) ($grItem->small_conversion_qty ?: 1);
        $mediumConv = (float) ($grItem->medium_conversion_qty ?: 1);
        $qtyReceived = (float) ($grItem->qty_received ?: 0);

        $qtySmall = $qtyReceived;
        if ((int) $grItem->received_unit_id === (int) $grItem->medium_unit_id) {
            $qtySmall = $qtyReceived * $smallConv;
        } elseif ((int) $grItem->received_unit_id === (int) $grItem->large_unit_id) {
            $qtySmall = $qtyReceived * $smallConv * $mediumConv;
        }

        $convertedQty = $qtySmall;
        if ($targetUnitId === (int) $grItem->medium_unit_id) {
            $convertedQty = $smallConv > 0 ? ($qtySmall / $smallConv) : 0;
        } elseif ($targetUnitId === (int) $grItem->large_unit_id) {
            $divider = $smallConv * $mediumConv;
            $convertedQty = $divider > 0 ? ($qtySmall / $divider) : 0;
        }

        $repackUnitId = $request->input('repack_unit_id');
        $repackQty = (float) $request->input('repack_qty', 0);

        if ($repackUnitId && $repackQty > 0) {
            $serialCount = (int) ceil($convertedQty / $repackQty);
        } else {
            $repackUnitId = null;
            $repackQty = null;
            $serialCount = (int) round($convertedQty);
            if ($serialCount <= 0 || abs($convertedQty - $serialCount) > 0.00001) {
                return response()->json([
                    'message' => 'Qty hasil konversi harus bilangan bulat positif agar bisa generate serial.',
                    'converted_qty' => round($convertedQty, 4),
                ], 422);
            }
        }

        if ($serialCount <= 0) {
            return response()->json([
                'message' => 'Jumlah serial yang akan digenerate harus lebih dari 0.',
            ], 422);
        }

        $warehouseId = null;
        if ($grItem->po_source_type === 'ro_supplier') {
            $warehouseId = 1;
        } else {
            $warehouseId = $grItem->warehouse_id;
        }

        if (!$warehouseId) {
            $warehouseId = DB::table('warehouses')->value('id');
        }

        $inventoryItemId = DB::table('food_inventory_items')
            ->where('item_id', $grItem->item_id)
            ->value('id');

        $price = (float) ($grItem->po_item_price ?: 0);
        $priceUnitId = (int) ($grItem->po_item_unit_id ?: $grItem->received_unit_id);
        $costSmall = $price;
        if ($priceUnitId === (int) $grItem->large_unit_id) {
            $divider = ($smallConv ?: 1) * ($mediumConv ?: 1);
            $costSmall = $divider > 0 ? ($price / $divider) : 0;
        } elseif ($priceUnitId === (int) $grItem->medium_unit_id) {
            $costSmall = ($smallConv ?: 1) > 0 ? ($price / ($smallConv ?: 1)) : 0;
        }
        $costMedium = $costSmall * ($smallConv ?: 1);
        $costLarge = $costMedium * ($mediumConv ?: 1);

        DB::beginTransaction();
        try {
            if (InventorySerialInUse::existsInUseFor(function ($q) use ($grItem, $targetUnitId) {
                $q->where('source_type', 'good_receive')
                    ->where('source_item_id', $grItem->id)
                    ->where('unit_id', $targetUnitId);
            })) {
                DB::rollBack();

                return response()->json([
                    'message' => InventorySerialInUse::failureMessage(),
                ], 422);
            }

            DB::table('inventory_item_serials')
                ->where('source_type', 'good_receive')
                ->where('source_item_id', $grItem->id)
                ->where('unit_id', $targetUnitId)
                ->delete();

            $now = now();
            $rows = [];
            for ($i = 0; $i < $serialCount; $i++) {
                $rows[] = [
                    'source_type' => 'good_receive',
                    'source_id' => $grItem->good_receive_id,
                    'source_item_id' => $grItem->id,
                    'warehouse_id' => $warehouseId,
                    'inventory_item_id' => $inventoryItemId,
                    'item_id' => $grItem->item_id,
                    'unit_id' => $targetUnitId,
                    'serial_number' => $this->generateUniqueSerialNumber(),
                    'source_qty' => $qtyReceived,
                    'source_unit_id' => (int) $grItem->received_unit_id,
                    'generated_qty_unit' => $convertedQty,
                    'cost_small' => $costSmall,
                    'cost_medium' => $costMedium,
                    'cost_large' => $costLarge,
                    'ref_gr_number' => $grItem->gr_number,
                    'ref_po_number' => $grItem->po_number,
                    'ref_pr_number' => $grItem->pr_number,
                    'repack_unit_id' => $repackUnitId,
                    'repack_qty' => $repackQty,
                    'generated_by' => Auth::id(),
                    'generated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('inventory_item_serials')->insert($rows);
            DB::commit();

            $repackUnitName = $repackUnitId
                ? DB::table('units')->where('id', $repackUnitId)->value('name')
                : null;
            $fmtRepackQty = $repackQty !== null ? rtrim(rtrim(number_format($repackQty, 4, '.', ''), '0'), '.') : '';
            $modeLabel = $repackUnitName
                ? "(1 {$repackUnitName} = {$fmtRepackQty} unit asal)"
                : "(tanpa konversi)";

            return response()->json([
                'success' => true,
                'message' => "Berhasil generate {$serialCount} serial {$modeLabel}.",
                'total' => $serialCount,
                'converted_qty' => round($convertedQty, 4),
                'repack_unit_id' => $repackUnitId,
                'repack_qty' => $repackQty,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function rollbackSerials(Request $request, $goodReceiveItemId)
    {
        $validated = $request->validate([
            'unit_id' => 'nullable|integer|exists:units,id',
        ]);

        $query = DB::table('inventory_item_serials')
            ->where('source_type', 'good_receive')
            ->where('source_item_id', $goodReceiveItemId);

        if (!empty($validated['unit_id'])) {
            $query->where('unit_id', (int) $validated['unit_id']);
        }

        if (InventorySerialInUse::existsInUseFor(function ($q) use ($goodReceiveItemId, $validated) {
            $q->where('source_type', 'good_receive')
                ->where('source_item_id', $goodReceiveItemId);
            if (! empty($validated['unit_id'])) {
                $q->where('unit_id', (int) $validated['unit_id']);
            }
        })) {
            return response()->json([
                'success' => false,
                'message' => InventorySerialInUse::failureMessage(),
            ], 422);
        }

        $deleted = $query->delete();

        return response()->json([
            'success' => true,
            'message' => "Rollback serial berhasil. Terhapus: {$deleted}",
            'deleted' => $deleted,
        ]);
    }

    public function serialList($goodReceiveItemId)
    {
        $rows = DB::table('inventory_item_serials as s')
            ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
            ->leftJoin('units as ru', 'ru.id', '=', 's.repack_unit_id')
            ->select(
                's.id',
                's.serial_number',
                's.ref_gr_number as gr_number',
                's.ref_po_number as po_number',
                's.ref_pr_number as pr_number',
                's.generated_at',
                's.repack_unit_id',
                's.repack_qty',
                'u.name as unit_name',
                'ru.name as repack_unit_name'
            )
            ->where('s.source_type', 'good_receive')
            ->where('s.source_item_id', $goodReceiveItemId)
            ->orderBy('s.id')
            ->get();

        return response()->json($rows);
    }

    public function getSerialUnits()
    {
        $units = cache()->remember('active_units', 300, function() {
            return DB::table('units')
                ->where('status', 'active')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        });

        return response()->json($units);
    }

    private function generateUniqueSerialNumber(): string
    {
        $prefix = 'F' . now()->format('ymdHi');

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