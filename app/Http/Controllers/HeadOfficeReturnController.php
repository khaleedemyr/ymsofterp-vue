<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HeadOfficeReturnController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;
        
        // Only head office (id_outlet = 1) can access this menu
        if ($userOutletId != 1) {
            abort(403, 'Akses ditolak. Menu ini hanya untuk Head Office.');
        }

        // Get filter parameters
        $search = $request->get('search', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $status = $request->get('status', '');

        // Query returns with joins
        $query = DB::table('outlet_food_returns as ofr')
            ->leftJoin('outlet_food_good_receives as ofgr', 'ofr.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->leftJoin('tbl_data_outlet as o', 'ofr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'ofr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as creator', 'ofr.created_by', '=', 'creator.id')
            ->leftJoin('users as approver', 'ofr.approved_by', '=', 'approver.id')
            ->leftJoin('users as rejector', 'ofr.rejection_by', '=', 'rejector.id')
            ->select(
                'ofr.id',
                'ofr.return_number',
                'ofr.return_date',
                'ofr.status',
                'ofr.notes',
                'ofr.created_at',
                'ofr.approved_at',
                'ofr.rejection_at',
                'ofr.rejection_reason',
                'ofgr.number as gr_number',
                'o.nama_outlet',
                'wo.name as warehouse_name',
                'creator.nama_lengkap as created_by_name',
                'approver.nama_lengkap as approved_by_name',
                'rejector.nama_lengkap as rejection_by_name'
            )
            ->orderByDesc('ofr.created_at');

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ofr.return_number', 'like', "%{$search}%")
                  ->orWhere('ofgr.number', 'like', "%{$search}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                  ->orWhere('wo.name', 'like', "%{$search}%");
            });
        }

        // Apply date filter
        if ($dateFrom) {
            $query->whereDate('ofr.return_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('ofr.return_date', '<=', $dateTo);
        }

        // Apply status filter
        if ($status) {
            $query->where('ofr.status', $status);
        }

        $returns = $query->paginate(10)->withQueryString();

        return Inertia::render('HeadOfficeReturn/Index', [
            'user' => $user,
            'returns' => $returns,
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $status,
            ]
        ]);
    }

    /**
     * API: List returns for mobile app (Head Office only).
     */
    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        if ($user->id_outlet != 1) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Menu ini hanya untuk Head Office.'], 403);
        }

        $search = $request->get('search', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $status = $request->get('status', '');
        $perPage = (int) $request->get('per_page', 20);
        $page = (int) $request->get('page', 1);

        $query = DB::table('outlet_food_returns as ofr')
            ->leftJoin('outlet_food_good_receives as ofgr', 'ofr.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->leftJoin('tbl_data_outlet as o', 'ofr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'ofr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as creator', 'ofr.created_by', '=', 'creator.id')
            ->leftJoin('users as approver', 'ofr.approved_by', '=', 'approver.id')
            ->leftJoin('users as rejector', 'ofr.rejection_by', '=', 'rejector.id')
            ->select(
                'ofr.id',
                'ofr.return_number',
                'ofr.return_date',
                'ofr.status',
                'ofr.notes',
                'ofr.created_at',
                'ofr.approved_at',
                'ofr.rejection_at',
                'ofr.rejection_reason',
                'ofgr.number as gr_number',
                'o.nama_outlet',
                'wo.name as warehouse_name',
                'creator.nama_lengkap as created_by_name',
                'approver.nama_lengkap as approved_by_name',
                'rejector.nama_lengkap as rejection_by_name'
            )
            ->orderByDesc('ofr.created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ofr.return_number', 'like', "%{$search}%")
                    ->orWhere('ofgr.number', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                    ->orWhere('wo.name', 'like', "%{$search}%");
            });
        }
        if ($dateFrom) $query->whereDate('ofr.return_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('ofr.return_date', '<=', $dateTo);
        if ($status) $query->where('ofr.status', $status);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * API: Show single return for mobile app.
     */
    public function apiShow($id)
    {
        $user = auth()->user();
        if ($user->id_outlet != 1) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Menu ini hanya untuk Head Office.'], 403);
        }

        $return = DB::table('outlet_food_returns as ofr')
            ->leftJoin('outlet_food_good_receives as ofgr', 'ofr.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->leftJoin('tbl_data_outlet as o', 'ofr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'ofr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as creator', 'ofr.created_by', '=', 'creator.id')
            ->leftJoin('users as approver', 'ofr.approved_by', '=', 'approver.id')
            ->leftJoin('users as rejector', 'ofr.rejection_by', '=', 'rejector.id')
            ->select(
                'ofr.*',
                'ofgr.number as gr_number',
                'o.nama_outlet',
                'wo.name as warehouse_name',
                'creator.nama_lengkap as created_by_name',
                'approver.nama_lengkap as approved_by_name',
                'rejector.nama_lengkap as rejection_by_name'
            )
            ->where('ofr.id', $id)
            ->first();

        if (!$return) {
            return response()->json(['success' => false, 'message' => 'Return tidak ditemukan'], 404);
        }

        $items = DB::table('outlet_food_return_items as ofri')
            ->leftJoin('items as i', 'ofri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'ofri.unit_id', '=', 'u.id')
            ->select('ofri.id', 'ofri.item_id', 'i.name as item_name', 'i.sku', 'ofri.return_qty', 'u.name as unit_name', 'ofri.unit_id')
            ->where('ofri.outlet_food_return_id', $id)
            ->get();

        $return->items = $items;
        return response()->json(['success' => true, 'return' => $return]);
    }

    public function show($id)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;
        
        // Only head office (id_outlet = 1) can access this menu
        if ($userOutletId != 1) {
            abort(403, 'Akses ditolak. Menu ini hanya untuk Head Office.');
        }

        // Get return data
        $return = DB::table('outlet_food_returns as ofr')
            ->leftJoin('outlet_food_good_receives as ofgr', 'ofr.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->leftJoin('tbl_data_outlet as o', 'ofr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'ofr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as creator', 'ofr.created_by', '=', 'creator.id')
            ->leftJoin('users as approver', 'ofr.approved_by', '=', 'approver.id')
            ->leftJoin('users as rejector', 'ofr.rejection_by', '=', 'rejector.id')
            ->select(
                'ofr.id',
                'ofr.return_number',
                'ofr.return_date',
                'ofr.status',
                'ofr.notes',
                'ofr.created_at',
                'ofr.approved_at',
                'ofr.rejection_at',
                'ofr.rejection_reason',
                'ofgr.number as gr_number',
                'o.nama_outlet',
                'wo.name as warehouse_name',
                'creator.nama_lengkap as created_by_name',
                'approver.nama_lengkap as approved_by_name',
                'rejector.nama_lengkap as rejection_by_name'
            )
            ->where('ofr.id', $id)
            ->first();

        if (!$return) {
            abort(404, 'Return tidak ditemukan');
        }

        // Get return items
        $items = DB::table('outlet_food_return_items as ofri')
            ->leftJoin('items as i', 'ofri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'ofri.unit_id', '=', 'u.id')
            ->select(
                'ofri.id',
                'ofri.item_id',
                'i.name as item_name',
                'i.sku',
                'ofri.return_qty',
                'u.name as unit_name',
                'ofri.unit_id'
            )
            ->where('ofri.outlet_food_return_id', $id)
            ->get();

        return Inertia::render('HeadOfficeReturn/Show', [
            'user' => $user,
            'return' => $return,
            'items' => $items
        ]);
    }

    public function approve($id)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;
        
        // Only head office (id_outlet = 1) can approve
        if ($userOutletId != 1) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Hanya Head Office yang dapat approve return.'], 403);
        }

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
                    'inventory_item_id' => $item->inventory_item_id,
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
                    'description' => 'Stock Out - Outlet Food Return (Approved by Head Office)',
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

    public function reject($id, Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;
        
        // Only head office (id_outlet = 1) can reject
        if ($userOutletId != 1) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Hanya Head Office yang dapat reject return.'], 403);
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

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
            
            // Update return status to rejected
            DB::table('outlet_food_returns')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'rejection_by' => Auth::id(),
                    'rejection_at' => now(),
                    'rejection_reason' => $request->rejection_reason,
                    'updated_at' => now()
                ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Return berhasil direject'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal reject return: ' . $e->getMessage()
            ], 500);
        }
    }
}
