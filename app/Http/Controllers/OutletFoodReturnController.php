<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OutletFoodReturnController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user()->load('outlet');
        $userOutletId = $user->id_outlet;
        
        // Check delete permission: only superadmin or warehouse division can delete
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        
        // Get filter parameters
        $search = $request->get('search', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        
        // Query returns
        $query = DB::table('outlet_food_returns as ofr')
            ->leftJoin('outlet_food_good_receives as ofgr', 'ofr.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->leftJoin('tbl_data_outlet as o', 'ofr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'ofr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'ofr.created_by', '=', 'u.id')
            ->select(
                'ofr.id',
                'ofr.return_number',
                'ofr.return_date',
                'ofr.status',
                'ofr.notes',
                'ofr.return_mode',
                'ofgr.number as gr_number',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as created_by_name',
                'ofr.created_at'
            )
            ->orderByDesc('ofr.created_at');
            
        // Apply outlet filter
        if ($userOutletId != 1) {
            $query->where('ofr.outlet_id', $userOutletId);
        }
        
        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ofr.return_number', 'like', "%{$search}%")
                  ->orWhere('ofgr.number', 'like', "%{$search}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$search}%");
            });
        }
        
        // Apply date filter
        if ($dateFrom) {
            $query->whereDate('ofr.return_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('ofr.return_date', '<=', $dateTo);
        }
        
        $returns = $query->paginate(10)->withQueryString();
        
        return Inertia::render('OutletFoodReturn/Index', [
            'user' => $user,
            'returns' => $returns,
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'canDelete' => $canDelete,
        ]);
    }

    public function create()
    {
        $user = auth()->user()->load('outlet');
        $userOutletId = $user->id_outlet;
        
        // Get outlets - admin can see all, others only their outlet
        if ($userOutletId == 1) {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->orderBy('nama_outlet', 'asc')
                ->get(['id_outlet', 'nama_outlet']);
        } else {
            $outlets = DB::table('tbl_data_outlet')
                ->where('id_outlet', $userOutletId)
                ->get(['id_outlet', 'nama_outlet']);
        }
        
        // Get warehouse outlets for the user's outlet using the same method as Floor Order
        $warehouseOutlets = \App\Models\WarehouseOutlet::where('outlet_id', $userOutletId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'outlet_id', 'location', 'status']);
        
        // Get good receives - only from last 24 hours
        $query = DB::table('outlet_food_good_receives as ofgr')
            ->leftJoin('tbl_data_outlet as o', 'ofgr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'ofgr.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'ofgr.id',
                'ofgr.number',
                'ofgr.receive_date',
                'ofgr.outlet_id',
                'ofgr.warehouse_outlet_id',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            )
            ->where('ofgr.status', 'completed') // Use 'completed' status instead of 'approved'
            ->where('ofgr.created_at', '>=', now()->subHours(24)) // Max 24 hours
            ->orderByDesc('ofgr.created_at');
            
        if ($userOutletId != 1) {
            $query->where('ofgr.outlet_id', $userOutletId);
        }
        
        $goodReceives = $query->get();
        
        return Inertia::render('OutletFoodReturn/Form', [
            'user' => $user,
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'goodReceives' => $goodReceives
        ]);
    }

    public function getWarehouseOutlets(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet'
        ]);
        
        try {
            $warehouseOutlets = DB::table('warehouse_outlets')
                ->where('outlet_id', $request->outlet_id)
                ->where('status', 'active') // Only active warehouse outlets
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code']);
                
            \Log::info('WAREHOUSE_OUTLETS_QUERY', [
                'outlet_id' => $request->outlet_id,
                'count' => $warehouseOutlets->count(),
                'warehouses' => $warehouseOutlets->toArray()
            ]);
                
            return response()->json($warehouseOutlets);
        } catch (\Exception $e) {
            \Log::error('WAREHOUSE_OUTLETS_ERROR', [
                'error' => $e->getMessage(),
                'outlet_id' => $request->outlet_id
            ]);
            
            return response()->json(['error' => 'Gagal memuat warehouse outlet: ' . $e->getMessage()], 500);
        }
    }

    public function getGoodReceives(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'warehouse_outlet_id' => 'required|integer|exists:warehouse_outlets,id'
        ]);
        
        // For API calls, we don't need authentication check
        // The route will handle authentication via middleware
        
        $goodReceives = DB::table('outlet_food_good_receives as ofgr')
            ->leftJoin('tbl_data_outlet as o', 'ofgr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'ofgr.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'ofgr.id',
                'ofgr.number',
                'ofgr.receive_date',
                'ofgr.outlet_id',
                'ofgr.warehouse_outlet_id',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            )
            ->where('ofgr.outlet_id', $request->outlet_id)
            ->where('ofgr.warehouse_outlet_id', $request->warehouse_outlet_id)
            ->where('ofgr.status', 'completed') // Use 'completed' status instead of 'approved'
            ->where('ofgr.created_at', '>=', now()->subHours(24)) // Max 24 hours
            ->orderByDesc('ofgr.created_at')
            ->get();
            
        return response()->json($goodReceives);
    }

    public function getGoodReceiveItems(Request $request)
    {
        $request->validate([
            'good_receive_id' => 'required|integer|exists:outlet_food_good_receives,id',
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'warehouse_outlet_id' => 'required|integer|exists:warehouse_outlets,id'
        ]);
        
        $items = DB::table('outlet_food_good_receive_items as ofgri')
            ->leftJoin('items as i', 'ofgri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'ofgri.unit_id', '=', 'u.id')
            ->leftJoin('units as su', 'i.small_unit_id', '=', 'su.id') // Join untuk small unit
            ->leftJoin('outlet_food_inventory_items as ofii', 'ofgri.item_id', '=', 'ofii.item_id')
            ->leftJoin('outlet_food_inventory_stocks as ofis', function($join) use ($request) {
                $join->on('ofii.id', '=', 'ofis.inventory_item_id')
                     ->where('ofis.id_outlet', '=', $request->outlet_id)
                     ->where('ofis.warehouse_outlet_id', '=', $request->warehouse_outlet_id);
            })
            ->select(
                'ofgri.id as gr_item_id',
                'ofgri.item_id',
                'i.name as item_name',
                'i.sku',
                'ofgri.qty as gr_qty',
                'ofgri.received_qty',
                'ofgri.remaining_qty',
                'u.name as unit_name',
                'ofgri.unit_id',
                'ofis.qty_small as current_stock',
                'su.name as small_unit_name', // Nama unit small
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->where('ofgri.outlet_food_good_receive_id', $request->good_receive_id)
            ->where('ofgri.received_qty', '>', 0) // Only items that have been received
            ->get();
            
        return response()->json($items);
    }

    public function validateSerialForOFR(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string|max:50',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'outlet_food_good_receive_id' => 'nullable|integer',
        ]);

        $serialNumber = trim($request->serial_number);
        $outletId = (string) $request->outlet_id;
        $warehouseOutletId = (int) $request->warehouse_outlet_id;
        $grId = $request->filled('outlet_food_good_receive_id') ? (int) $request->outlet_food_good_receive_id : null;

        $serial = DB::table('inventory_item_serials as s')
            ->leftJoin('items as i', 'i.id', '=', 's.item_id')
            ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
            ->leftJoin('units as ru', 'ru.id', '=', 's.repack_unit_id')
            ->where('s.serial_number', $serialNumber)
            ->select(
                's.id',
                's.serial_number',
                's.item_id',
                's.unit_id',
                's.is_out',
                's.is_received',
                's.out_outlet_id',
                's.out_warehouse_outlet_id',
                's.repack_unit_id',
                's.repack_qty',
                'i.name as item_name',
                'u.name as unit_name',
                'ru.name as repack_unit_name'
            )
            ->first();

        if (! $serial) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri tidak ditemukan.']);
        }
        if (! $serial->is_received) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri belum diterima di outlet (belum GR Serial).']);
        }
        if ((string) $serial->out_outlet_id !== $outletId) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri tidak berada di outlet yang dipilih.']);
        }
        if ((int) $serial->out_warehouse_outlet_id !== $warehouseOutletId) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri tidak berada di warehouse outlet yang dipilih.']);
        }

        $pending = DB::table('outlet_food_return_serial_items as osi')
            ->join('outlet_food_returns as ofr', 'ofr.id', '=', 'osi.outlet_food_return_id')
            ->where('osi.serial_id', $serial->id)
            ->where('ofr.status', 'pending')
            ->exists();
        if ($pending) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri sedang dipakai di return lain yang belum di-approve.']);
        }

        $grItemId = null;
        if ($grId) {
            $grItem = DB::table('outlet_food_good_receive_items')
                ->where('outlet_food_good_receive_id', $grId)
                ->where('item_id', $serial->item_id)
                ->where('received_qty', '>', 0)
                ->first();
            if (! $grItem) {
                return response()->json(['valid' => false, 'message' => 'Item serial tidak ada di Good Receive yang dipilih.']);
            }
            $grItemId = $grItem->id;
        }

        $qty = 1.0;
        $unitId = $serial->unit_id;
        $unitName = $serial->unit_name ?? '';
        if ($serial->repack_qty && $serial->repack_unit_id) {
            $qty = (float) $serial->repack_qty;
            $unitId = $serial->repack_unit_id;
            $unitName = $serial->repack_unit_name ?? $unitName;
        }

        return response()->json([
            'valid' => true,
            'message' => 'Nomor seri valid.',
            'serial' => [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'item_id' => $serial->item_id,
                'item_name' => $serial->item_name,
                'unit_id' => $unitId,
                'unit_name' => $unitName,
                'qty' => $qty,
                'gr_item_id' => $grItemId,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->validateOutletFoodReturnStoreRequest($request);

        try {
            DB::beginTransaction();

            $returnId = $this->createOutletFoodReturnHeader($request);

            if (! empty($request->serial_items)) {
                $this->saveSerialItemsForOFR($returnId, $request->serial_items, $request);
            }

            if (! empty($request->items)) {
                $this->saveQtyItemsForOFR($returnId, $request->items);
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Return berhasil disimpan dan menunggu approval gudang',
                'return_id' => $returnId
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan return: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approve($id)
    {
        try {
            DB::beginTransaction();
            
            // Get return data
            $return = DB::table('outlet_food_returns')->where('id', $id)->first();
            if (!$return) {
                return response()->json(['success' => false, 'message' => 'Return tidak ditemukan'], 404);
            }
            
            if ($return->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Return sudah diproses'], 400);
            }
            
            // Get return items
            $returnItems = DB::table('outlet_food_return_items as ofri')
                ->leftJoin('items as i', 'ofri.item_id', '=', 'i.id')
                ->leftJoin('outlet_food_inventory_items as ofii', 'ofri.item_id', '=', 'ofii.item_id')
                ->select(
                    'ofri.*',
                    'ofii.id as inventory_item_id',
                    'i.small_unit_id',
                    'i.medium_unit_id',
                    'i.large_unit_id',
                    'i.small_conversion_qty',
                    'i.medium_conversion_qty',
                    'i.name as item_name'
                )
                ->where('ofri.outlet_food_return_id', $id)
                ->get();
            
            $serialRows = DB::table('outlet_food_return_serial_items')
                ->where('outlet_food_return_id', $id)
                ->get();
            foreach ($serialRows as $serialRow) {
                $this->processSerialReturnForOFR($return, $serialRow, $id);
            }

            // Process each return item for stock reduction
            foreach ($returnItems as $item) {
                // Convert return quantity to small unit
                $returnQty = $item->return_qty;
                $unitId = $item->unit_id;
                $smallConv = $item->small_conversion_qty ?: 1;
                $mediumConv = $item->medium_conversion_qty ?: 1;
                
                $qty_small = 0;
                if ($unitId == $item->small_unit_id) {
                    $qty_small = $returnQty;
                } elseif ($unitId == $item->medium_unit_id) {
                    $qty_small = $returnQty * $smallConv;
                } elseif ($unitId == $item->large_unit_id) {
                    $qty_small = $returnQty * $smallConv * $mediumConv;
                }
                
                // Check current stock
                $currentStock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $item->inventory_item_id)
                    ->where('id_outlet', $return->outlet_id)
                    ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
                    ->first();
                    
                if (!$currentStock || $currentStock->qty_small < $qty_small) {
                    throw new \Exception("Stok tidak mencukupi untuk item: {$item->item_name}. Stok tersedia: " . ($currentStock ? $currentStock->qty_small : 0));
                }
                
                // Update inventory stock (decrease)
                DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $item->inventory_item_id)
                    ->where('id_outlet', $return->outlet_id)
                    ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
                    ->update([
                        'qty_small' => $currentStock->qty_small - $qty_small,
                        'updated_at' => now()
                    ]);
                
                // Update remaining qty (jumlah yang sudah di-return) and received qty in good receive item
                $grItem = DB::table('outlet_food_good_receive_items')->where('id', $item->outlet_food_good_receive_item_id)->first();
                if ($grItem) {
                    DB::table('outlet_food_good_receive_items')
                        ->where('id', $item->outlet_food_good_receive_item_id)
                        ->update([
                            'remaining_qty' => $grItem->remaining_qty + $returnQty, // Tambah remaining_qty (jumlah yang sudah di-return)
                            'received_qty' => $grItem->received_qty - $returnQty, // Kurangi received_qty (sisa yang tersedia)
                            'updated_at' => now()
                        ]);
                }
                
                // Insert inventory card (OUT)
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $item->item_id,
                    'id_outlet' => $return->outlet_id,
                    'warehouse_outlet_id' => $return->warehouse_outlet_id,
                    'date' => $return->return_date,
                    'reference_type' => 'outlet_food_return',
                    'reference_id' => $id,
                    'out_qty_small' => $qty_small,
                    'cost_per_small' => $currentStock->last_cost_small,
                    'value_out' => $qty_small * $currentStock->last_cost_small,
                    'saldo_qty_small' => $currentStock->qty_small - $qty_small,
                    'saldo_value' => ($currentStock->qty_small - $qty_small) * $currentStock->last_cost_small,
                    'description' => 'Stock Out - Outlet Food Return (Approved)',
                    'created_at' => now()
                ]);
            }
            
            // Update return status to approved
            DB::table('outlet_food_returns')
                ->where('id', $id)
                ->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'updated_at' => now()
                ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Return berhasil diapprove dan stock berhasil dikurangi'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal approve return: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: List returns for mobile app (JSON paginated)
     */
    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;

        $search = $request->get('search', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $perPage = (int) $request->get('per_page', 20);
        $page = (int) $request->get('page', 1);

        $query = DB::table('outlet_food_returns as ofr')
            ->leftJoin('outlet_food_good_receives as ofgr', 'ofr.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->leftJoin('tbl_data_outlet as o', 'ofr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'ofr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'ofr.created_by', '=', 'u.id')
            ->select(
                'ofr.id',
                'ofr.return_number',
                'ofr.return_date',
                'ofr.status',
                'ofr.notes',
                'ofr.return_mode',
                'ofgr.number as gr_number',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as created_by_name',
                'u.avatar as created_by_avatar',
                'ofr.created_at'
            )
            ->orderByDesc('ofr.created_at');

        if ($userOutletId != 1) {
            $query->where('ofr.outlet_id', $userOutletId);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ofr.return_number', 'like', "%{$search}%")
                    ->orWhere('ofgr.number', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%");
            });
        }
        if ($dateFrom) {
            $query->whereDate('ofr.return_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('ofr.return_date', '<=', $dateTo);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'can_delete' => $canDelete,
        ]);
    }

    /**
     * API: Create form data for mobile app
     */
    public function apiCreateData()
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;

        if ($userOutletId == 1) {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->orderBy('nama_outlet', 'asc')
                ->get(['id_outlet', 'nama_outlet']);
        } else {
            $outlets = DB::table('tbl_data_outlet')
                ->where('id_outlet', $userOutletId)
                ->where('status', 'A')
                ->get(['id_outlet', 'nama_outlet']);
        }

        return response()->json([
            'success' => true,
            'outlets' => $outlets,
            'user_outlet_id' => $userOutletId,
        ]);
    }

    /**
     * API: Show single return for mobile app
     */
    public function apiShow($id)
    {
        $user = auth()->user();
        $return = DB::table('outlet_food_returns as ofr')
            ->leftJoin('outlet_food_good_receives as ofgr', 'ofr.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->leftJoin('tbl_data_outlet as o', 'ofr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'ofr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'ofr.created_by', '=', 'u.id')
            ->select(
                'ofr.*',
                'ofgr.number as gr_number',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as created_by_name'
            )
            ->where('ofr.id', $id)
            ->first();

        if (!$return) {
            return response()->json(['success' => false, 'message' => 'Return tidak ditemukan'], 404);
        }

        if ($user->id_outlet != 1 && $return->outlet_id != $user->id_outlet) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $items = DB::table('outlet_food_return_items as ofri')
            ->leftJoin('items as i', 'ofri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'ofri.unit_id', '=', 'u.id')
            ->select(
                'ofri.*',
                'i.name as item_name',
                'i.sku',
                'u.name as unit_name'
            )
            ->where('ofri.outlet_food_return_id', $id)
            ->get();

        $return->items = $items;
        $return->serialItems = $this->loadSerialItemsForOFR($id);
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        return response()->json([
            'success' => true,
            'return' => $return,
            'can_delete' => $canDelete,
        ]);
    }

    public function show($id)
    {
        $return = DB::table('outlet_food_returns as ofr')
            ->leftJoin('outlet_food_good_receives as ofgr', 'ofr.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->leftJoin('tbl_data_outlet as o', 'ofr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'ofr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'ofr.created_by', '=', 'u.id')
            ->select(
                'ofr.*',
                'ofgr.number as gr_number',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as created_by_name'
            )
            ->where('ofr.id', $id)
            ->first();
            
        if (!$return) {
            abort(404, 'Return tidak ditemukan');
        }
        
        $items = DB::table('outlet_food_return_items as ofri')
            ->leftJoin('items as i', 'ofri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'ofri.unit_id', '=', 'u.id')
            ->select(
                'ofri.*',
                'i.name as item_name',
                'i.sku',
                'u.name as unit_name'
            )
            ->where('ofri.outlet_food_return_id', $id)
            ->get();
            
        $return->items = $items;
        $return->serialItems = $this->loadSerialItemsForOFR($id);

        return Inertia::render('OutletFoodReturn/Detail', [
            'return' => $return
        ]);
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            
            // Check permission: only superadmin or warehouse division can delete
            if ($user->id_role !== '5af56935b011a' && $user->division_id != 11) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk menghapus data ini'
                ], 403);
            }

            $return = DB::table('outlet_food_returns')->where('id', $id)->first();
            if (!$return) {
                return response()->json([
                    'success' => false,
                    'message' => 'Return tidak ditemukan'
                ], 404);
            }

            \Log::info('OUTLET_FOOD_RETURN_DELETE: Starting deletion process', [
                'user_id' => $user->id,
                'return_id' => $id,
                'return_number' => $return->return_number
            ]);

            DB::beginTransaction();
            
            if ($return->status === 'approved') {
                $returnItems = DB::table('outlet_food_return_items')
                    ->where('outlet_food_return_id', $id)
                    ->get();
                foreach ($returnItems as $item) {
                    $this->rollbackInventory($item, $return->outlet_id, $return->warehouse_outlet_id);
                }
                $serialRows = DB::table('outlet_food_return_serial_items')
                    ->where('outlet_food_return_id', $id)
                    ->get();
                foreach ($serialRows as $serialRow) {
                    $this->rollbackSerialInventoryForOFR($serialRow, $return);
                }
            } else {
                $this->rollbackSerialReservationForOFR($id);
            }

            DB::table('outlet_food_return_serial_items')->where('outlet_food_return_id', $id)->delete();
            DB::table('outlet_food_return_items')->where('outlet_food_return_id', $id)->delete();
            
            // Delete return
            DB::table('outlet_food_returns')->where('id', $id)->delete();
            
            DB::commit();

            \Log::info('OUTLET_FOOD_RETURN_DELETE: Deletion completed successfully', [
                'return_id' => $id,
                'return_number' => $return->return_number
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Return berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('OUTLET_FOOD_RETURN_DELETE: Deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'return_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus return: ' . $e->getMessage()
            ], 500);
        }
    }

    private function validateOutletFoodReturnStoreRequest(Request $request): void
    {
        $request->validate([
            'outlet_food_good_receive_id' => 'required|integer|exists:outlet_food_good_receives,id',
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'warehouse_outlet_id' => 'required|integer|exists:warehouse_outlets,id',
            'return_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.gr_item_id' => 'required_with:items|integer',
            'items.*.item_id' => 'required_with:items|integer',
            'items.*.return_qty' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_id' => 'required_with:items|integer',
            'serial_items' => 'nullable|array',
            'serial_items.*.serial_id' => 'required_with:serial_items|integer',
            'serial_items.*.serial_number' => 'required_with:serial_items|string',
            'serial_items.*.item_id' => 'required_with:serial_items|integer',
            'serial_items.*.unit_id' => 'nullable|integer',
            'serial_items.*.qty' => 'nullable|numeric|min:0.01',
            'serial_items.*.gr_item_id' => 'nullable|integer',
        ]);

        $hasItems = is_array($request->items) && count($request->items) > 0;
        $hasSerials = is_array($request->serial_items) && count($request->serial_items) > 0;
        if (! $hasItems && ! $hasSerials) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => ['Minimal satu baris item qty atau nomor seri harus diisi.'],
            ]);
        }
    }

    private function resolveReturnMode(Request $request): string
    {
        $hasItems = is_array($request->items) && count($request->items) > 0;
        $hasSerials = is_array($request->serial_items) && count($request->serial_items) > 0;
        if ($hasItems && $hasSerials) {
            return 'mixed';
        }
        if ($hasSerials) {
            return 'serial';
        }

        return 'normal';
    }

    private function createOutletFoodReturnHeader(Request $request): int
    {
        $dateStr = date('Ymd', strtotime($request->return_date));
        $countToday = DB::table('outlet_food_returns')
            ->whereDate('return_date', $request->return_date)
            ->count();
        $returnNumber = 'RET-'.$dateStr.'-'.str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);

        return DB::table('outlet_food_returns')->insertGetId([
            'return_number' => $returnNumber,
            'outlet_food_good_receive_id' => $request->outlet_food_good_receive_id,
            'outlet_id' => $request->outlet_id,
            'warehouse_outlet_id' => $request->warehouse_outlet_id,
            'return_date' => $request->return_date,
            'notes' => $request->notes,
            'return_mode' => $this->resolveReturnMode($request),
            'status' => 'pending',
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function saveQtyItemsForOFR(int $returnId, array $items): void
    {
        foreach ($items as $item) {
            $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
            if (! $itemMaster) {
                throw new \Exception('Item tidak ditemukan: '.$item['item_id']);
            }
            $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item['item_id'])->first();
            if (! $inventoryItem) {
                throw new \Exception('Inventory item tidak ditemukan: '.$item['item_id']);
            }

            DB::table('outlet_food_return_items')->insert([
                'outlet_food_return_id' => $returnId,
                'outlet_food_good_receive_item_id' => $item['gr_item_id'],
                'item_id' => $item['item_id'],
                'unit_id' => $item['unit_id'],
                'return_qty' => $item['return_qty'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function saveSerialItemsForOFR(int $returnId, array $serialItems, Request $request): void
    {
        $now = now();
        foreach ($serialItems as $row) {
            $serialId = (int) $row['serial_id'];
            $serial = DB::table('inventory_item_serials')->where('id', $serialId)->first();
            if (! $serial || ! $serial->is_received) {
                throw new \Exception('Serial '.$row['serial_number'].' tidak valid untuk return.');
            }
            if ((string) $serial->out_outlet_id !== (string) $request->outlet_id
                || (int) $serial->out_warehouse_outlet_id !== (int) $request->warehouse_outlet_id) {
                throw new \Exception('Serial '.$row['serial_number'].' tidak sesuai outlet/warehouse.');
            }

            $qty = (float) ($row['qty'] ?? 1);
            $unitId = $row['unit_id'] ?? $serial->unit_id;
            $unitName = DB::table('units')->where('id', $unitId)->value('name');
            $itemMaster = DB::table('items')->where('id', $row['item_id'])->first();
            $qtySmall = $this->convertQtyToSmallForOFR($qty, (int) $unitId, $itemMaster);

            $grItemId = $row['gr_item_id'] ?? null;
            if (! $grItemId && $request->outlet_food_good_receive_id) {
                $grItemId = DB::table('outlet_food_good_receive_items')
                    ->where('outlet_food_good_receive_id', $request->outlet_food_good_receive_id)
                    ->where('item_id', $row['item_id'])
                    ->value('id');
            }

            DB::table('outlet_food_return_serial_items')->insert([
                'outlet_food_return_id' => $returnId,
                'serial_id' => $serialId,
                'serial_number' => $row['serial_number'],
                'item_id' => $row['item_id'],
                'unit_id' => $unitId,
                'unit_name' => $unitName,
                'return_qty' => $qty,
                'qty_small' => $qtySmall,
                'outlet_food_good_receive_item_id' => $grItemId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('inventory_item_serials')->where('id', $serialId)->update([
                'out_outlet_food_return_id' => $returnId,
                'updated_at' => $now,
            ]);
        }
    }

    private function loadSerialItemsForOFR(int $returnId)
    {
        return DB::table('outlet_food_return_serial_items as osi')
            ->leftJoin('items as i', 'i.id', '=', 'osi.item_id')
            ->where('osi.outlet_food_return_id', $returnId)
            ->select('osi.*', 'i.name as item_name', 'i.sku')
            ->orderBy('osi.id')
            ->get();
    }

    private function processSerialReturnForOFR($return, $serialRow, int $returnId): void
    {
        $itemMaster = DB::table('items')->where('id', $serialRow->item_id)->first();
        if (! $itemMaster) {
            throw new \Exception("Item master tidak ditemukan untuk serial {$serialRow->serial_number}");
        }

        $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $serialRow->item_id)->first();
        if (! $inventoryItem) {
            throw new \Exception("Inventory item tidak ditemukan: {$serialRow->item_id}");
        }

        $returnQty = (float) $serialRow->return_qty;
        $unitId = (int) $serialRow->unit_id;
        $qty_small = (float) $serialRow->qty_small;
        if ($qty_small <= 0) {
            $qty_small = $this->convertQtyToSmallForOFR($returnQty, $unitId, $itemMaster);
        }

        $currentStock = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('id_outlet', $return->outlet_id)
            ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
            ->first();

        if (! $currentStock || $currentStock->qty_small < $qty_small) {
            throw new \Exception("Stok tidak mencukupi untuk serial {$serialRow->serial_number}.");
        }

        DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('id_outlet', $return->outlet_id)
            ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
            ->update([
                'qty_small' => $currentStock->qty_small - $qty_small,
                'updated_at' => now(),
            ]);

        if ($serialRow->outlet_food_good_receive_item_id) {
            $grItem = DB::table('outlet_food_good_receive_items')
                ->where('id', $serialRow->outlet_food_good_receive_item_id)
                ->first();
            if ($grItem) {
                DB::table('outlet_food_good_receive_items')
                    ->where('id', $serialRow->outlet_food_good_receive_item_id)
                    ->update([
                        'remaining_qty' => $grItem->remaining_qty + $returnQty,
                        'received_qty' => $grItem->received_qty - $returnQty,
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::table('outlet_food_inventory_cards')->insert([
            'inventory_item_id' => $serialRow->item_id,
            'id_outlet' => $return->outlet_id,
            'warehouse_outlet_id' => $return->warehouse_outlet_id,
            'date' => $return->return_date,
            'reference_type' => 'outlet_food_return',
            'reference_id' => $returnId,
            'out_qty_small' => $qty_small,
            'cost_per_small' => $currentStock->last_cost_small,
            'value_out' => $qty_small * $currentStock->last_cost_small,
            'saldo_qty_small' => $currentStock->qty_small - $qty_small,
            'saldo_value' => ($currentStock->qty_small - $qty_small) * $currentStock->last_cost_small,
            'description' => 'Stock Out - Outlet Food Return Serial (Approved)',
            'created_at' => now(),
        ]);

        $now = now();
        DB::table('inventory_item_serials')->where('id', $serialRow->serial_id)->update([
            'is_received' => 0,
            'out_outlet_food_return_id' => null,
            'updated_at' => $now,
        ]);

        DB::table('inventory_serial_movements')->insert([
            'serial_id' => $serialRow->serial_id,
            'serial_number' => $serialRow->serial_number,
            'movement_type' => 'ofrt_out',
            'outlet_food_return_id' => $returnId,
            'item_id' => $serialRow->item_id,
            'qty' => $returnQty,
            'unit_id' => $serialRow->unit_id,
            'moved_by' => Auth::id(),
            'moved_at' => $now,
            'notes' => 'Outlet food return serial',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function rollbackSerialReservationForOFR(int $returnId): void
    {
        DB::table('inventory_item_serials')
            ->where('out_outlet_food_return_id', $returnId)
            ->update([
                'out_outlet_food_return_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function rollbackSerialInventoryForOFR($serialRow, $return): void
    {
        $virtual = (object) [
            'item_id' => $serialRow->item_id,
            'unit_id' => $serialRow->unit_id,
            'return_qty' => $serialRow->return_qty,
            'outlet_food_return_id' => $return->id,
        ];
        $this->rollbackInventory($virtual, $return->outlet_id, $return->warehouse_outlet_id);

        if ($serialRow->outlet_food_good_receive_item_id) {
            $grItem = DB::table('outlet_food_good_receive_items')
                ->where('id', $serialRow->outlet_food_good_receive_item_id)
                ->first();
            if ($grItem) {
                DB::table('outlet_food_good_receive_items')
                    ->where('id', $serialRow->outlet_food_good_receive_item_id)
                    ->update([
                        'remaining_qty' => max(0, $grItem->remaining_qty - $serialRow->return_qty),
                        'received_qty' => $grItem->received_qty + $serialRow->return_qty,
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::table('inventory_item_serials')->where('id', $serialRow->serial_id)->update([
            'is_received' => 1,
            'out_outlet_food_return_id' => null,
            'updated_at' => now(),
        ]);
    }

    private function convertQtyToSmallForOFR(float $qty, int $unitId, ?object $itemMaster): float
    {
        if (! $itemMaster) {
            return $qty;
        }
        $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);
        if ($unitId === (int) $itemMaster->small_unit_id) {
            return $qty;
        }
        if (! empty($itemMaster->medium_unit_id) && $unitId === (int) $itemMaster->medium_unit_id) {
            return $qty * $smallConv;
        }
        if (! empty($itemMaster->large_unit_id) && $unitId === (int) $itemMaster->large_unit_id) {
            return $qty * $smallConv * $mediumConv;
        }

        return $qty;
    }

    private function rollbackInventory($item, $outletId, $warehouseOutletId)
    {
        try {
            // Find item master
            $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
            if (!$itemMaster) {
                \Log::warning('OUTLET_FOOD_RETURN_DELETE: Item master not found', ['item_id' => $item->item_id]);
                return;
            }

            // Find inventory item
            $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item->item_id)->first();
            if (!$inventoryItem) {
                \Log::warning('OUTLET_FOOD_RETURN_DELETE: Inventory item not found', ['item_id' => $item->item_id]);
                return;
            }

            // Convert return quantity to small unit
            $returnQty = $item->return_qty;
            $unitId = $item->unit_id;
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
            
            $qty_small = 0;
            if ($unitId == $itemMaster->small_unit_id) {
                $qty_small = $returnQty;
            } elseif ($unitId == $itemMaster->medium_unit_id) {
                $qty_small = $returnQty * $smallConv;
            } elseif ($unitId == $itemMaster->large_unit_id) {
                $qty_small = $returnQty * $smallConv * $mediumConv;
            }

            // Find outlet inventory stock
            $stock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->first();

            if ($stock) {
                // Rollback stock (add back the quantity)
                DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId)
                    ->update([
                        'qty_small' => $stock->qty_small + $qty_small,
                        'updated_at' => now()
                    ]);

                \Log::info('OUTLET_FOOD_RETURN_DELETE: Inventory rolled back', [
                    'item_name' => $itemMaster->name,
                    'qty_added_back' => $qty_small,
                    'new_stock' => $stock->qty_small + $qty_small
                ]);
            } else {
                \Log::warning('OUTLET_FOOD_RETURN_DELETE: Stock not found for rollback', [
                    'inventory_item_id' => $inventoryItem->id,
                    'outlet_id' => $outletId,
                    'warehouse_outlet_id' => $warehouseOutletId
                ]);
            }

            // Delete inventory card
            DB::table('outlet_food_inventory_cards')
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->where('reference_type', 'outlet_food_return')
                ->where('reference_id', $item->outlet_food_return_id)
                ->delete();

        } catch (\Exception $e) {
            \Log::error('OUTLET_FOOD_RETURN_DELETE: Rollback inventory failed', [
                'error' => $e->getMessage(),
                'item_id' => $item->item_id
            ]);
        }
    }
}
