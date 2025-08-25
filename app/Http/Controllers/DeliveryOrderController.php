<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Item;
use Illuminate\Support\Facades\Log;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            // Join untuk RO Supplier GR
            ->leftJoin('food_good_receives as gr', 'do.ro_supplier_gr_id', '=', 'gr.id')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo_gr', 'po.source_id', '=', 'fo_gr.id')
            ->leftJoin('tbl_data_outlet as o_gr', 'fo_gr.id_outlet', '=', 'o_gr.id_outlet')
            ->leftJoin('warehouse_outlets as wo_gr', 'fo_gr.warehouse_outlet_id', '=', 'wo_gr.id')
            ->select(
                'do.*',
                'u.nama_lengkap as created_by_name',
                // Gunakan COALESCE untuk mengambil data dari packing list atau RO Supplier GR
                DB::raw('COALESCE(pl.packing_number, gr.gr_number) as packing_number'),
                DB::raw('COALESCE(fo.order_number, fo_gr.order_number) as floor_order_number'),
                DB::raw('COALESCE(o.nama_outlet, o_gr.nama_outlet) as nama_outlet'),
                DB::raw('COALESCE(wo.name, wo_gr.name) as warehouse_outlet_name')
            );

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $query->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('do.created_at', '<=', $request->dateTo);
        }

        $orders = $query->orderByDesc('do.created_at')->paginate(15)->withQueryString();
        return Inertia::render('DeliveryOrder/Index', [
            'orders' => $orders,
            'search' => $request->search,
            'dateFrom' => $request->dateFrom,
            'dateTo' => $request->dateTo,
        ]);
    }

    public function create(Request $request)
    {
        // Ambil daftar packing list yang belum/do belum dibuat
        $usedPackingListIds = DB::table('delivery_orders')->whereNotNull('packing_list_id')->pluck('packing_list_id')->toArray();
        $packingLists = DB::table('food_packing_lists as pl')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'pl.created_by', '=', 'u.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id');
        
        // Hanya apply whereNotIn jika ada data yang sudah digunakan
        if (!empty($usedPackingListIds)) {
            $packingLists = $packingLists->whereNotIn('pl.id', $usedPackingListIds);
        }
        
                $packingLists = $packingLists->select(
            'pl.id',
            'pl.packing_number',
            'pl.created_at',
            'fo.order_number as floor_order_number',
            'fo.tanggal as floor_order_date',
            'o.nama_outlet',
            'u.nama_lengkap as creator_name',
            'wd.name as division_name',
            'w.name as warehouse_name',
            'wo.name as warehouse_outlet_name'
        )
        ->orderByDesc('pl.created_at')
        ->get();

        Log::info('Packing lists found', ['count' => $packingLists->count(), 'packingLists' => $packingLists->toArray()]);
        
        // Convert to array to ensure proper JSON serialization
        $packingLists = $packingLists->toArray();

        // Ambil data RO Supplier yang sudah di-GR dan belum dibuat DO
        $roSupplierGRs = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as fo', 'po.source_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->where('po.source_type', 'ro_supplier')
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('delivery_orders as do')
                      ->whereRaw('do.ro_supplier_gr_id = gr.id');
            })
            ->select(
                'gr.id as gr_id',
                'gr.gr_number as packing_number',
                'gr.receive_date as created_at',
                'fo.order_number as floor_order_number',
                'fo.tanggal as floor_order_date',
                'o.nama_outlet',
                'u.nama_lengkap as creator_name',
                DB::raw("'Perishable' as division_name"),
                DB::raw("'Warehouse 1' as warehouse_name"),
                'wo.name as warehouse_outlet_name',
                DB::raw("'ro_supplier_gr' as source_type"),
                's.name as supplier_name'
            )
            ->orderByDesc('gr.receive_date')
            ->get();

        Log::info('RO Supplier GRs found', ['count' => $roSupplierGRs->count(), 'roSupplierGRs' => $roSupplierGRs->toArray()]);
        
        // Convert to array to ensure proper JSON serialization
        $roSupplierGRs = $roSupplierGRs->toArray();

        return Inertia::render('DeliveryOrder/Form', [
            'packingLists' => $packingLists,
            'roSupplierGRs' => $roSupplierGRs
        ]);
    }

    public function show($id)
    {
        $order = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'do.floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->select(
                'do.*',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'pl.created_at as packing_date',
                'fo.tanggal as floor_order_date',
                'u.nama_lengkap as created_by_name'
            )
            ->where('do.id', $id)
            ->first();
        $items = DB::table('delivery_order_items as doi')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->select(
                'doi.id',
                'i.name as item_name',
                'doi.qty_packing_list',
                'doi.qty_scan',
                'doi.unit'
            )
            ->where('doi.delivery_order_id', $id)
            ->get();
        return Inertia::render('DeliveryOrder/Show', [
            'order' => $order,
            'items' => $items
        ]);
    }

    private function generateDONumber()
    {
        $prefix = 'DO';
        $date = now()->format('ymd');
        
        // Get the last DO number for today
        $lastDO = DB::table('delivery_orders')
            ->where('number', 'like', $prefix . $date . '%')
            ->orderBy('number', 'desc')
            ->first();
        
        if ($lastDO) {
            $lastNumber = (int) substr($lastDO->number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {
        Log::info('Mulai proses store Delivery Order', $request->all());
        
        // Cek apakah ini adalah RO Supplier GR atau Packing List biasa
        $isROSupplierGR = false;
        $grId = null;
        $floorOrderId = null;
        $warehouseDivisionId = null;
        $warehouseId = null;
        
        if (strpos($request->packing_list_id, 'gr_') === 0) {
            // Ini adalah RO Supplier GR
            $isROSupplierGR = true;
            $grId = substr($request->packing_list_id, 3); // Hapus prefix 'gr_'
            
            // Ambil data dari GR
            $gr = DB::table('food_good_receives as gr')
                ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
                ->leftJoin('food_floor_orders as fo', 'po.source_id', '=', 'fo.id')
                ->where('gr.id', $grId)
                ->first();
            
            if (!$gr) {
                throw new \Exception('RO Supplier GR tidak ditemukan');
            }
            
            $floorOrderId = $gr->id; // source_id dari PO
            $warehouseDivisionId = 1; // Perishable
            $warehouseId = 1; // Warehouse 1
            
            Log::info('RO Supplier GR warehouse info', [
                'gr_id' => $grId,
                'warehouse_id' => $warehouseId,
                'warehouse_division_id' => $warehouseDivisionId
            ]);
        } else {
            // Ini adalah Packing List biasa
            $packingList = DB::table('food_packing_lists')->where('id', $request->packing_list_id)->first();
            $floorOrderId = $packingList->food_floor_order_id ?? null;
            $warehouseDivisionId = $packingList->warehouse_division_id ?? null;
            if ($warehouseDivisionId) {
                $warehouseId = DB::table('warehouse_division')->where('id', $warehouseDivisionId)->value('warehouse_id');
            }
        }
        DB::beginTransaction();
        try {
            Log::info('Insert delivery_orders', ['packing_list_id' => $request->packing_list_id, 'isROSupplierGR' => $isROSupplierGR]);
            
            $insertData = [
                'number' => $this->generateDONumber(),
                'floor_order_id' => $floorOrderId,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            if ($isROSupplierGR) {
                $insertData['ro_supplier_gr_id'] = $grId;
                // Untuk RO Supplier GR, set packing_list_id ke 0 atau nilai default
                $insertData['packing_list_id'] = 0; // Atau gunakan nilai default yang sesuai
                $insertData['source_type'] = 'ro_supplier_gr';
            } else {
                $insertData['packing_list_id'] = $request->packing_list_id;
                $insertData['ro_supplier_gr_id'] = null;
                $insertData['source_type'] = 'packing_list';
            }
            
            $doId = DB::table('delivery_orders')->insertGetId($insertData);
            Log::info('DO ID: ' . $doId);
            foreach ($request->items as $item) {
                // Ambil item_id berdasarkan jenis source
                $realItemId = null;
                if ($isROSupplierGR) {
                    // Untuk RO Supplier GR, ambil dari food_good_receive_items
                    $grItem = DB::table('food_good_receive_items')->where('id', $item['id'])->first();
                    if (!$grItem) throw new \Exception('GR item tidak ditemukan untuk id: ' . $item['id']);
                    $realItemId = $grItem->item_id;
                } else {
                    // Untuk Packing List biasa, ambil dari food_floor_order_items via packing list item
                    $packingListItem = DB::table('food_packing_list_items')->where('id', $item['id'])->first();
                    if (!$packingListItem) throw new \Exception('Packing list item tidak ditemukan untuk id: ' . $item['id']);
                    $floorOrderItem = DB::table('food_floor_order_items')->where('id', $packingListItem->food_floor_order_item_id)->first();
                    if (!$floorOrderItem) throw new \Exception('Floor order item tidak ditemukan untuk id: ' . $packingListItem->food_floor_order_item_id);
                    $realItemId = $floorOrderItem->item_id;
                }
                // Ambil barcode hasil scan dari frontend (ambil barcode pertama jika array, string jika satu, null jika tidak ada)
                $barcode = null;
                if (isset($item['barcode'])) {
                    if (is_array($item['barcode']) && count($item['barcode']) > 0) {
                        $barcode = $item['barcode'][0];
                    } elseif (is_string($item['barcode'])) {
                        $barcode = $item['barcode'];
                    }
                }
                DB::table('delivery_order_items')->insert([
                    'delivery_order_id' => $doId,
                    'item_id' => $realItemId,
                    'barcode' => $barcode,
                    'qty_packing_list' => $item['qty'],
                    'qty_scan' => $item['qty_scan'],
                    'unit' => $item['unit'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($warehouseId) {
                    // Ambil item_id berdasarkan jenis source
                    $realItemId = null;
                    if ($isROSupplierGR) {
                        // Untuk RO Supplier GR, ambil dari food_good_receive_items
                        $grItem = DB::table('food_good_receive_items')->where('id', $item['id'])->first();
                        if (!$grItem) throw new \Exception('GR item tidak ditemukan untuk id: ' . $item['id']);
                        $realItemId = $grItem->item_id;
                    } else {
                        // Untuk Packing List biasa, ambil dari food_floor_order_items via food_packing_list_items
                        $packingListItem = DB::table('food_packing_list_items')->where('id', $item['id'])->first();
                        if (!$packingListItem) throw new \Exception('Packing list item tidak ditemukan untuk id: ' . $item['id']);
                        $floorOrderItem = DB::table('food_floor_order_items')->where('id', $packingListItem->food_floor_order_item_id)->first();
                        if (!$floorOrderItem) throw new \Exception('Floor order item tidak ditemukan untuk id: ' . $packingListItem->food_floor_order_item_id);
                        $realItemId = $floorOrderItem->item_id;
                    }
                    // Pastikan item_id valid
                    $itemMaster = DB::table('items')->where('id', $realItemId)->first();
                    if (!$itemMaster) throw new \Exception('Item master tidak ditemukan di tabel items untuk item_id: ' . $realItemId);
                    // Cari inventory_item_id, insert jika belum ada
                    $inventoryItem = DB::table('food_inventory_items')->where('item_id', $realItemId)->first();
                    if (!$inventoryItem) {
                        $inventoryItemId = DB::table('food_inventory_items')->insertGetId([
                            'item_id' => $realItemId,
                            'small_unit_id' => $itemMaster->small_unit_id,
                            'medium_unit_id' => $itemMaster->medium_unit_id,
                            'large_unit_id' => $itemMaster->large_unit_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $inventoryItem = DB::table('food_inventory_items')->where('id', $inventoryItemId)->first();
                        if (!$inventoryItem) throw new \Exception('Gagal insert food_inventory_items untuk item_id: ' . $realItemId);
                    }
                    $inventory_item_id = $inventoryItem->id;
                    // Ambil data konversi dari tabel items
                    $itemMaster = DB::table('items')->where('id', $realItemId)->first();
                    if (!$itemMaster) throw new \Exception('Item master not found for item_id: ' . $realItemId);
                    $unit = $item['unit'] ?? null;
                    $qty_input = $item['qty_scan'];
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
                    // Tambahkan log sebelum cek stok tersedia
                    Log::info('Cek stok inventory', [
                        'inventory_item_id' => $inventory_item_id,
                        'warehouse_id' => $warehouseId,
                        'item_id' => $realItemId,
                        'qty_small' => $qty_small,
                        'isROSupplierGR' => $isROSupplierGR,
                    ]);
                    $stock = DB::table('food_inventory_stocks')
                        ->where('inventory_item_id', $inventory_item_id)
                        ->where('warehouse_id', $warehouseId)
                        ->first();
                    if (!$stock) {
                        Log::error('Stok tidak ditemukan di gudang', [
                            'inventory_item_id' => $inventory_item_id,
                            'warehouse_id' => $warehouseId,
                            'item_id' => $realItemId
                        ]);
                        throw new \Exception('Stok tidak ditemukan di gudang');
                    }
                    // Tambahkan log sebelum validasi qty
                    Log::info('Validasi qty vs stok', [
                        'qty_small' => $qty_small,
                        'stok_tersedia' => $stock->qty_small,
                        'unit' => $unitSmall
                    ]);
                    if ($qty_small > $stock->qty_small) {
                        Log::error('Qty melebihi stok yang tersedia', [
                            'qty_small' => $qty_small,
                            'stok_tersedia' => $stock->qty_small,
                            'unit' => $unitSmall
                        ]);
                        throw new \Exception("Qty melebihi stok yang tersedia. Stok tersedia: {$stock->qty_small} {$unitSmall}");
                    }
                    // Update stok di warehouse (kurangi)
                    DB::table('food_inventory_stocks')
                        ->where('inventory_item_id', $inventory_item_id)
                        ->where('warehouse_id', $warehouseId)
                        ->update([
                            'qty_small' => $stock->qty_small - $qty_small,
                            'qty_medium' => $stock->qty_medium - $qty_medium,
                            'qty_large' => $stock->qty_large - $qty_large,
                            'updated_at' => now(),
                        ]);
                    // Insert kartu stok OUT
                    DB::table('food_inventory_cards')->insert([
                        'inventory_item_id' => $inventory_item_id,
                        'warehouse_id' => $warehouseId,
                        'date' => now()->toDateString(),
                        'reference_type' => 'delivery_order',
                        'reference_id' => $doId,
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
                        'description' => 'Stock Out - Delivery Order',
                        'created_at' => now(),
                    ]);
                }
            }
            
            // Update status RO menjadi delivered
            if ($isROSupplierGR) {
                // Untuk RO Supplier GR, update status RO dari purchase order
                $po = DB::table('purchase_order_foods')->where('id', $gr->po_id)->first();
                if ($po && $po->source_id) {
                    DB::table('food_floor_orders')
                        ->where('id', $po->source_id)
                        ->update([
                            'status' => 'delivered',
                            'updated_at' => now()
                        ]);
                    Log::info('Updated RO status to delivered for RO Supplier GR', ['ro_id' => $po->source_id]);
                }
            } else {
                // Untuk Packing List biasa, update status RO
                if ($floorOrderId) {
                    DB::table('food_floor_orders')
                        ->where('id', $floorOrderId)
                        ->update([
                            'status' => 'delivered',
                            'updated_at' => now()
                        ]);
                    Log::info('Updated RO status to delivered for regular packing list', ['ro_id' => $floorOrderId]);
                }
            }
            
            Log::info('Insert activity_logs');
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'module' => 'delivery_order',
                'description' => 'Membuat delivery order untuk ' . ($isROSupplierGR ? 'RO Supplier GR #' . $grId : 'packing list #' . $request->packing_list_id),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);
            DB::commit();
            Log::info('Sukses simpan Delivery Order');
            $kasirName = DB::table('users')->where('id', auth()->id())->value('nama_lengkap');
            // Ambil warehouse division dan warehouse name
            $packingListFull = DB::table('food_packing_lists as pl')
                ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
                ->leftJoin('users as u', 'fo.user_id', '=', 'u.id')
                ->where('pl.id', $request->packing_list_id)
                ->select(
                    'wd.name as division_name',
                    'w.name as warehouse_name',
                    'fo.order_number as ro_number',
                    'fo.tanggal as ro_date',
                    'u.nama_lengkap as ro_creator_name'
                )
                ->first();
            return response()->json([
                'success' => true,
                'message' => 'Delivery Order berhasil disimpan!',
                'kasir_name' => $kasirName,
                'division_name' => $packingListFull->division_name ?? null,
                'warehouse_name' => $packingListFull->warehouse_name ?? null,
                'ro_number' => $packingListFull->ro_number ?? null,
                'ro_date' => $packingListFull->ro_date ?? null,
                'ro_creator_name' => $packingListFull->ro_creator_name ?? null
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal simpan Delivery Order: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan Delivery Order: ' . $e->getMessage()]);
        }
    }

    public function getPackingListItems($id)
    {
        Log::info('=== getPackingListItems START ===');
        Log::info('getPackingListItems called', ['id' => $id, 'type' => gettype($id)]);
        
        // Cek apakah ini adalah RO Supplier GR atau Packing List biasa
        if (strpos($id, 'gr_') === 0) {
            // Ini adalah RO Supplier GR
            $grId = substr($id, 3); // Hapus prefix 'gr_'
            Log::info('Processing RO Supplier GR', ['grId' => $grId]);
            $result = $this->getROSupplierGRItems($grId);
            Log::info('=== getPackingListItems END (RO Supplier GR) ===');
            return $result;
        } else {
            // Ini adalah Packing List biasa
            Log::info('Processing regular Packing List', ['id' => $id]);
            $result = $this->getPackingListItemsRegular($id);
            Log::info('=== getPackingListItems END (Regular Packing List) ===');
            return $result;
        }
    }

    private function getROSupplierGRItems($grId)
    {
        // Ambil items dari RO Supplier GR
        $items = DB::table('food_good_receive_items as fgri')
            ->join('items', 'fgri.item_id', '=', 'items.id')
            ->join('units as u', 'fgri.unit_id', '=', 'u.id')
            ->select(
                'fgri.id',
                'fgri.qty_received as qty',
                'u.name as unit',
                'items.name',
                'items.id as item_id'
            )
            ->where('fgri.good_receive_id', $grId)
            ->where('fgri.qty_received', '>', 0)
            ->orderBy('items.name')
            ->get();

        // Ambil semua barcode untuk setiap item
        $itemIds = $items->pluck('item_id')->unique()->values();
        $barcodeMap = DB::table('item_barcodes')
            ->whereIn('item_id', $itemIds)
            ->select('item_id', 'barcode')
            ->get()
            ->groupBy('item_id')
            ->map(function($rows) {
                return $rows->pluck('barcode')->values();
            });

        // Ambil stock untuk setiap item (warehouse_id = 1 untuk RO Supplier)
        $warehouse_id = 1;
        $itemStocks = [];
        if ($warehouse_id) {
            foreach ($items as $item) {
                $stock = DB::table('food_inventory_stocks as fis')
                    ->join('food_inventory_items as fii', 'fis.inventory_item_id', '=', 'fii.id')
                    ->where('fis.warehouse_id', $warehouse_id)
                    ->where('fii.item_id', $item->item_id)
                    ->value('fis.qty_small') ?? 0;
                $itemStocks[$item->item_id] = $stock;
            }
        }

        // Tambahkan barcode dan stock ke setiap item
        $items = $items->map(function($item) use ($barcodeMap, $itemStocks) {
            $item->barcodes = $barcodeMap->get($item->item_id, []);
            $item->stock = $itemStocks[$item->item_id] ?? 0;
            return $item;
        });

        return response()->json(['items' => $items]);
    }

    private function getPackingListItemsRegular($id)
    {
        Log::info('=== getPackingListItemsRegular START ===');
        Log::info('getPackingListItemsRegular called', ['id' => $id]);
        
        // Ambil packing list untuk dapat warehouse_division_id
        $packingList = DB::table('food_packing_lists')->where('id', $id)->first();
        Log::info('Packing list found', ['packingList' => $packingList]);
        
        if (!$packingList) {
            Log::error('Packing list not found', ['id' => $id]);
            return response()->json(['items' => []]);
        }
        
        $warehouse_division_id = $packingList->warehouse_division_id ?? null;
        $warehouse_id = null;
        if ($warehouse_division_id) {
            $warehouse_id = DB::table('warehouse_division')->where('id', $warehouse_division_id)->value('warehouse_id');
        }
        
        Log::info('Warehouse info', ['warehouse_division_id' => $warehouse_division_id, 'warehouse_id' => $warehouse_id]);
        
        // Ambil items dari food_packing_list_items (bukan dari GR)
        $items = DB::table('food_packing_list_items as fpli')
            ->join('food_floor_order_items as ffoi', 'fpli.food_floor_order_item_id', '=', 'ffoi.id')
            ->join('items', 'ffoi.item_id', '=', 'items.id')
            ->select('fpli.id', 'fpli.qty', 'fpli.unit', 'items.name', 'items.id as item_id')
            ->where('fpli.packing_list_id', $id)
            ->where('fpli.qty', '>', 0)
            ->orderBy('items.name')
            ->get();
        
        Log::info('Items found', ['count' => $items->count(), 'items' => $items->toArray()]);
        
        if ($items->count() == 0) {
            Log::warning('No items found for packing list', ['packing_list_id' => $id]);
            return response()->json(['items' => []]);
        }
        // Ambil semua barcode untuk setiap item
        $itemIds = $items->pluck('item_id')->unique()->values();
        $barcodeMap = DB::table('item_barcodes')
            ->whereIn('item_id', $itemIds)
            ->select('item_id', 'barcode')
            ->get()
            ->groupBy('item_id')
            ->map(function($rows) {
                return $rows->pluck('barcode')->values();
            });
        // Ambil stock untuk setiap item sesuai unit
        $itemStocks = [];
        if ($warehouse_id) {
            $inventoryItems = DB::table('food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->get()->keyBy('item_id');
            $inventoryItemIds = $inventoryItems->pluck('id')->unique()->values();
            $stocks = DB::table('food_inventory_stocks')
                ->whereIn('inventory_item_id', $inventoryItemIds)
                ->where('warehouse_id', $warehouse_id)
                ->get()->keyBy('inventory_item_id');
            foreach ($items as $item) {
                $inv = $inventoryItems[$item->item_id] ?? null;
                $stock = $inv ? $stocks[$inv->id] ?? null : null;
                $unit = $item->unit;
                $stockQty = null;
                if ($stock) {
                    // Ambil nama unit dari tabel units
                    $unitNameSmall = DB::table('units')->where('id', $inv->small_unit_id)->value('name');
                    $unitNameMedium = DB::table('units')->where('id', $inv->medium_unit_id)->value('name');
                    $unitNameLarge = DB::table('units')->where('id', $inv->large_unit_id)->value('name');
                    if ($unit == $unitNameSmall) {
                        $stockQty = $stock->qty_small;
                    } elseif ($unit == $unitNameMedium) {
                        $stockQty = $stock->qty_medium;
                    } elseif ($unit == $unitNameLarge) {
                        $stockQty = $stock->qty_large;
                    }
                }
                $itemStocks[$item->id] = $stockQty !== null ? (float)$stockQty : 0;
            }
        }
        $items = $items->map(function($item) use ($barcodeMap, $itemStocks) {
            $item->barcodes = $barcodeMap[$item->item_id] ?? collect();
            $item->stock = $itemStocks[$item->id] ?? 0;
            return $item;
        });
        
        Log::info('Final items before response', ['count' => $items->count(), 'items' => $items->toArray()]);
        $response = response()->json(['items' => $items]);
        Log::info('=== getPackingListItemsRegular END ===');
        return $response;
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $order = DB::table('delivery_orders')->where('id', $id)->first();
            if (!$order) {
                return redirect()->route('delivery-order.index')->with('error', 'Delivery Order tidak ditemukan');
            }
            $items = DB::table('delivery_order_items')->where('delivery_order_id', $id)->get();
            $packingList = DB::table('food_packing_lists')->where('id', $order->packing_list_id)->first();
            $warehouseDivisionId = $packingList->warehouse_division_id ?? null;
            $warehouseId = null;
            if ($warehouseDivisionId) {
                $warehouseId = DB::table('warehouse_division')->where('id', $warehouseDivisionId)->value('warehouse_id');
            }
            // Rollback inventory
            foreach ($items as $item) {
                $realItemId = $item->item_id;
                $itemMaster = DB::table('items')->where('id', $realItemId)->first();
                if (!$itemMaster) continue;
                $inventoryItem = DB::table('food_inventory_items')->where('item_id', $realItemId)->first();
                if (!$inventoryItem) continue;
                $inventory_item_id = $inventoryItem->id;
                $unit = $item->unit ?? null;
                $qty_input = $item->qty_scan;
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
                $stock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                if ($stock) {
                    DB::table('food_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => $stock->qty_small + $qty_small,
                            'qty_medium' => $stock->qty_medium + $qty_medium,
                            'qty_large' => $stock->qty_large + $qty_large,
                            'updated_at' => now(),
                        ]);
                } else {
                    \Log::warning('Rollback stok gagal: stok tidak ditemukan', [
                        'inventory_item_id' => $inventory_item_id,
                        'warehouse_id' => $warehouseId,
                        'item_id' => $realItemId
                    ]);
                }
                // Hapus kartu stok OUT
                DB::table('food_inventory_cards')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $warehouseId)
                    ->where('reference_type', 'delivery_order')
                    ->where('reference_id', $id)
                    ->delete();
            }
            // Hapus delivery_order_items
            DB::table('delivery_order_items')->where('delivery_order_id', $id)->delete();
            // Hapus delivery_order
            DB::table('delivery_orders')->where('id', $id)->delete();
            // Log activity
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'delivery_order',
                'description' => 'Menghapus delivery order #' . $id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($order),
                'new_data' => null,
                'created_at' => now(),
            ]);
            DB::commit();
            return redirect()->route('delivery-order.index')->with('success', 'Delivery Order berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function strukData($id)
    {
        $order = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as ufo', 'fo.user_id', '=', 'ufo.id')
            ->leftJoin('users as kasir', 'do.created_by', '=', 'kasir.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->select(
                'do.number as orderNumber',
                'do.created_at as date',
                'o.nama_outlet as outlet',
                'kasir.nama_lengkap as kasirName',
                'wd.name as divisionName',
                'w.name as warehouseName',
                'fo.order_number as roNumber',
                'fo.tanggal as roDate',
                'ufo.nama_lengkap as roCreatorName'
            )
            ->where('do.id', $id)
            ->first();
        if (!$order) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        $items = DB::table('delivery_order_items as doi')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'doi.unit', '=', 'u.name')
            ->select(
                'doi.id',
                'i.name',
                'doi.qty_scan',
                'doi.unit',
                'u.code as unit_code'
            )
            ->where('doi.delivery_order_id', $id)
            ->get();
        return response()->json([
            'orderNumber' => $order->orderNumber,
            'date' => $order->date ? date('d/m/Y', strtotime($order->date)) : '',
            'outlet' => $order->outlet,
            'kasirName' => $order->kasirName,
            'divisionName' => $order->divisionName,
            'warehouseName' => $order->warehouseName,
            'roNumber' => $order->roNumber,
            'roDate' => $order->roDate ? date('d/m/Y', strtotime($order->roDate)) : '',
            'roCreatorName' => $order->roCreatorName,
            'items' => $items
        ]);
    }

    public function export(Request $request)
    {
        $query = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'do.id',
                'do.number',
                'do.created_at',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'u.nama_lengkap as created_by_name',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            );

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $query->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('do.created_at', '<=', $request->dateTo);
        }

        $orders = $query->orderByDesc('do.created_at')->get();

        // Get items for each order
        $orderItems = [];
        foreach ($orders as $order) {
            $items = DB::table('delivery_order_items as doi')
                ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
                ->select(
                    'doi.id',
                    'i.name as item_name',
                    'doi.qty_packing_list',
                    'doi.qty_scan',
                    'doi.unit'
                )
                ->where('doi.delivery_order_id', $order->id)
                ->get();
            $orderItems[$order->id] = $items;
        }

        // Create Excel file
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers for delivery orders
        $headers = [
            'No',
            'No DO',
            'Tanggal',
            'Outlet',
            'Warehouse Outlet',
            'Packing List',
            'Floor Order',
            'User'
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        // Style headers
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3B82F6'],
            ],
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // Add delivery order data
        $currentRow = 2;
        foreach ($orders as $index => $order) {
            $sheet->setCellValueByColumnAndRow(1, $currentRow, $index + 1);
            $sheet->setCellValueByColumnAndRow(2, $currentRow, $order->number ?? '-');
            $sheet->setCellValueByColumnAndRow(3, $currentRow, $order->created_at ? date('d/m/Y', strtotime($order->created_at)) : '-');
            $sheet->setCellValueByColumnAndRow(4, $currentRow, $order->nama_outlet ?? '-');
            $sheet->setCellValueByColumnAndRow(5, $currentRow, $order->warehouse_outlet_name ?? '-');
            $sheet->setCellValueByColumnAndRow(6, $currentRow, $order->packing_number ?? '-');
            $sheet->setCellValueByColumnAndRow(7, $currentRow, $order->floor_order_number ?? '-');
            $sheet->setCellValueByColumnAndRow(8, $currentRow, $order->created_by_name ?? '-');
            
            $currentRow++;
            
            // Add items for this order
            if (isset($orderItems[$order->id]) && $orderItems[$order->id]->count() > 0) {
                // Add item headers
                $itemHeaders = [
                    '',
                    'Item Name',
                    'Qty Packing List',
                    'Qty Scan',
                    'Unit'
                ];
                
                foreach ($itemHeaders as $col => $header) {
                    $sheet->setCellValueByColumnAndRow($col + 1, $currentRow, $header);
                }
                
                // Style item headers
                $itemHeaderStyle = [
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '10B981'],
                    ],
                ];
                $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->applyFromArray($itemHeaderStyle);
                $currentRow++;
                
                // Add item data
                foreach ($orderItems[$order->id] as $item) {
                    $sheet->setCellValueByColumnAndRow(1, $currentRow, '');
                    $sheet->setCellValueByColumnAndRow(2, $currentRow, $item->item_name ?? '-');
                    $sheet->setCellValueByColumnAndRow(3, $currentRow, $item->qty_packing_list ?? 0);
                    $sheet->setCellValueByColumnAndRow(4, $currentRow, $item->qty_scan ?? 0);
                    $sheet->setCellValueByColumnAndRow(5, $currentRow, $item->unit ?? '-');
                    $currentRow++;
                }
                
                // Add empty row after items
                $currentRow++;
            }
        }

        // Auto size columns
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Create writer
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Set headers for download
        $filename = 'delivery-order-' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // Output file
        $writer->save('php://output');
        exit;
    }

    public function exportSummary(Request $request)
    {
        // Get delivery orders with filters
        $query = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'do.id',
                'do.number',
                'do.created_at',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'u.nama_lengkap as created_by_name',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            );

        // Apply filters
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $query->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('do.created_at', '<=', $request->dateTo);
        }

        $orders = $query->orderByDesc('do.created_at')->get();

        // Get summary data - sum qty per item across all DOs
        $summaryData = DB::table('delivery_order_items as doi')
            ->leftJoin('delivery_orders as do', 'doi.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('units as u_small', 'i.small_unit_id', '=', 'u_small.id')
            ->leftJoin('units as u_medium', 'i.medium_unit_id', '=', 'u_medium.id')
            ->leftJoin('units as u_large', 'i.large_unit_id', '=', 'u_large.id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'i.sku as item_sku',
                'c.name as category_name',
                'sc.name as sub_category_name',
                'doi.unit',
                'u_small.name as small_unit_name',
                'u_medium.name as medium_unit_name',
                'u_large.name as large_unit_name',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                DB::raw('SUM(doi.qty_packing_list) as total_qty_packing_list'),
                DB::raw('SUM(doi.qty_scan) as total_qty_scan'),
                DB::raw('COUNT(DISTINCT do.id) as total_do_count'),
                DB::raw('GROUP_CONCAT(DISTINCT o.nama_outlet ORDER BY o.nama_outlet SEPARATOR ", ") as outlets'),
                DB::raw('GROUP_CONCAT(DISTINCT wo.name ORDER BY wo.name SEPARATOR ", ") as warehouse_outlets')
            )
            ->groupBy('i.id', 'i.name', 'i.sku', 'c.name', 'sc.name', 'doi.unit', 'u_small.name', 'u_medium.name', 'u_large.name', 'i.small_conversion_qty', 'i.medium_conversion_qty');

        // Apply same filters to summary query
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $summaryData->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $summaryData->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $summaryData->whereDate('do.created_at', '<=', $request->dateTo);
        }

        $summaryResults = $summaryData->orderBy('i.name')->get();

        // Return Excel export using the Responsable interface
        return (new \App\Exports\DeliveryOrderSummaryExport($summaryResults))->toResponse($request);
    }

    public function exportDetail(Request $request)
    {
        // Get delivery orders with filters
        $query = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'do.id',
                'do.number',
                'do.created_at',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'u.nama_lengkap as created_by_name',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            );

        // Apply filters
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $query->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('do.created_at', '<=', $request->dateTo);
        }

        $orders = $query->orderByDesc('do.created_at')->get();

        // Get detailed items data per outlet & warehouse per date
        $detailData = DB::table('delivery_order_items as doi')
            ->leftJoin('delivery_orders as do', 'doi.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->select(
                'do.number as do_number',
                'do.created_at as do_date',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as created_by_name',
                'i.name as item_name',
                'i.sku as item_sku',
                'c.name as category_name',
                'sc.name as sub_category_name',
                'doi.qty_packing_list',
                'doi.qty_scan',
                'doi.unit',
                DB::raw('DATE(do.created_at) as delivery_date')
            )
            ->orderBy('do.created_at', 'desc')
            ->orderBy('o.nama_outlet')
            ->orderBy('wo.name')
            ->orderBy('i.name');

        // Apply same filters to detail query
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $detailData->where(function($q) use ($search) {
                $q->where('pl.packing_number', 'like', $search)
                  ->orWhere('fo.order_number', 'like', $search)
                  ->orWhere('u.nama_lengkap', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('wo.name', 'like', $search);
            });
        }
        if ($request->filled('dateFrom')) {
            $detailData->whereDate('do.created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $detailData->whereDate('do.created_at', '<=', $request->dateTo);
        }

        $detailResults = $detailData->get();

        // Return Excel export using the Responsable interface
        return (new \App\Exports\DeliveryOrderDetailExport($detailResults))->toResponse($request);
    }
} 