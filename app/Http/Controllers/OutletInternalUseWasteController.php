<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class OutletInternalUseWasteController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = DB::table('outlet_internal_use_waste_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select(
                'h.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );
        
        // Filter by outlet (if user is not admin)
        if ($user->id_outlet != 1) {
            $query->where('h.outlet_id', $user->id_outlet);
        }
        
        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('h.number', 'like', "%{$search}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                  ->orWhere('wo.name', 'like', "%{$search}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$search}%");
            });
        }
        
        // Filter by outlet (only if user is admin)
        if ($user->id_outlet == 1 && $request->filled('outlet_id')) {
            $query->where('h.outlet_id', $request->outlet_id);
        }
        
        // Filter by type
        if ($request->filled('type')) {
            $query->where('h.type', $request->type);
        }
        
        // Filter by date from
        if ($request->filled('date_from')) {
            $query->whereDate('h.date', '>=', $request->date_from);
        }
        
        // Filter by date to
        if ($request->filled('date_to')) {
            $query->whereDate('h.date', '<=', $request->date_to);
        }
        
        // Per page
        $perPage = $request->input('per_page', 10);
        
        // Order and paginate
        $data = $query->orderByDesc('h.date')
            ->orderByDesc('h.id')
            ->paginate($perPage)
            ->withQueryString();
        
        // Get approval flows for each header
        $headerIds = collect($data->items())->pluck('id')->toArray();
        $approvalFlows = [];
        if (!empty($headerIds)) {
            $approvalFlowsData = DB::table('outlet_internal_use_waste_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->whereIn('af.header_id', $headerIds)
                ->select(
                    'af.header_id',
                    'af.approval_level',
                    'af.status',
                    'af.approved_at',
                    'af.rejected_at',
                    'af.comments',
                    'u.nama_lengkap as approver_name',
                    'j.nama_jabatan as approver_jabatan'
                )
                ->orderBy('af.header_id')
                ->orderBy('af.approval_level')
                ->get();
            
            // Group by header_id
            foreach ($approvalFlowsData as $flow) {
                $headerId = $flow->header_id;
                if (!isset($approvalFlows[$headerId])) {
                    $approvalFlows[$headerId] = [];
                }
                $approvalFlows[$headerId][] = $flow;
            }
        }
        
        // Attach approval flows to each data item
        $data->getCollection()->transform(function ($item) use ($approvalFlows) {
            $item->approval_flows = $approvalFlows[$item->id] ?? [];
            return $item;
        });
        
        // Get outlets for filter dropdown (only if user is admin)
        $outlets = [];
        if ($user->id_outlet == 1) {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();
        }
        
        return inertia('OutletInternalUseWaste/Index', [
            'data' => $data,
            'outlets' => $outlets,
            'filters' => $request->only(['search', 'outlet_id', 'type', 'date_from', 'date_to', 'per_page'])
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();
        $items = DB::table('items')->where('status', 'active')->get();
        $units = DB::table('units')->get();
        $rukos = DB::table('tbl_data_ruko')->get();
        if ($user->id_outlet == 1) {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        } else {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('outlet_id', $user->id_outlet)
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        }
        return inertia('OutletInternalUseWaste/Create', [
            'outlets' => $outlets,
            'items' => $items,
            'units' => $units,
            'rukos' => $rukos,
            'warehouse_outlets' => $warehouse_outlets,
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('OutletInternalUseWaste store method called with data:', $request->all());
        try {
            $request->validate([
                'type' => 'required|in:internal_use,spoil,waste,r_and_d,marketing,non_commodity,guest_supplies,wrong_maker',
                'date' => 'required|date',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'warehouse_outlet_id' => 'required|exists:warehouse_outlets,id',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|exists:items,id',
                'items.*.qty' => 'required|numeric|min:0',
                'items.*.unit_id' => 'required|exists:units,id',
                'items.*.note' => 'nullable|string'
            ]);

            // Get user ID with multiple fallback methods
            $userId = Auth::id() ?? auth()->id();
            if (!$userId) {
                $user = auth()->user();
                $userId = $user ? $user->id : null;
            }
            
            \Log::info('OutletInternalUseWaste store - User info:', [
                'auth_id' => Auth::id(),
                'auth_user_id' => auth()->id(),
                'user_id' => $userId,
                'user_exists' => $userId ? 'yes' : 'no',
                'request_user' => $request->user() ? $request->user()->id : null
            ]);
            
            if (!$userId) {
                \Log::error('OutletInternalUseWaste store - No user ID found!', [
                    'all_request' => $request->all(),
                    'session' => session()->all()
                ]);
                throw new \Exception('User tidak terautentikasi. Silakan login ulang.');
            }
            
            DB::beginTransaction();
            
            // Determine if approval is required based on type
            // Types that require approval: internal_use, r_and_d, marketing, non_commodity, guest_supplies, wrong_maker
            // Types that don't require approval: spoil, waste
            $typesRequiringApproval = ['internal_use', 'r_and_d', 'marketing', 'non_commodity', 'guest_supplies', 'wrong_maker'];
            $requiresApproval = in_array($request->type, $typesRequiringApproval);
            
            // Determine status
            $status = 'PROCESSED'; // Default for types that don't need approval
            if ($requiresApproval) {
                $status = 'SUBMITTED'; // Needs approval first
            }
            
            $headerId = DB::table('outlet_internal_use_waste_headers')->insertGetId([
                'type' => $request->type,
                'date' => $request->date,
                'outlet_id' => $request->outlet_id,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'notes' => $request->notes,
                'status' => $status,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            \Log::info('OutletInternalUseWaste store - Header created:', [
                'header_id' => $headerId,
                'created_by' => $userId
            ]);

            foreach ($request->items as $item) {
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $item['item_id'])
                    ->first();
                if (!$inventoryItem) {
                    throw new \Exception("Item tidak ditemukan di inventory outlet");
                }
                $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                $unit = DB::table('units')->where('id', $item['unit_id'])->value('name');
                $qty_input = $item['qty'];
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
                $stock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('id_outlet', $request->outlet_id)
                    ->first();
                if (!$stock) {
                    throw new \Exception("Stok item tidak ditemukan");
                }
                if ($qty_small > $stock->qty_small) {
                    throw new \Exception("Qty melebihi stok yang tersedia. Stok tersedia: {$stock->qty_small} {$unitSmall}");
                }
                DB::table('outlet_internal_use_waste_details')->insert([
                    'header_id' => $headerId,
                    'item_id' => $item['item_id'],
                    'qty' => $item['qty'],
                    'unit_id' => $item['unit_id'],
                    'note' => $item['note'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                // Only process stock if status is PROCESSED (no approval needed)
                if ($status === 'PROCESSED') {
                    // Update stok di outlet (kurangi)
                    DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('id_outlet', $request->outlet_id)
                        ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
                        ->update([
                            'qty_small' => $stock->qty_small - $qty_small,
                            'qty_medium' => $stock->qty_medium - $qty_medium,
                            'qty_large' => $stock->qty_large - $qty_large,
                            'updated_at' => now(),
                        ]);
                    // Insert kartu stok OUT
                    DB::table('outlet_food_inventory_cards')->insert([
                        'inventory_item_id' => $inventoryItem->id,
                        'id_outlet' => $request->outlet_id,
                        'warehouse_outlet_id' => $request->warehouse_outlet_id,
                        'date' => $request->date,
                        'reference_type' => 'outlet_internal_use_waste',
                        'reference_id' => $headerId,
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
                }
            }
            
            // Create approval flows if approvers provided
            if ($requiresApproval && !empty($request->approvers)) {
                foreach ($request->approvers as $index => $approverId) {
                    DB::table('outlet_internal_use_waste_approval_flows')->insert([
                        'header_id' => $headerId,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1, // Level 1 = terendah, level terakhir = tertinggi
                        'status' => 'PENDING',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            DB::commit();
            
            \Log::info('OutletInternalUseWaste store - Successfully saved:', [
                'header_id' => $headerId,
                'type' => $request->type,
                'outlet_id' => $request->outlet_id,
                'date' => $request->date,
                'status' => $status
            ]);
            
            // Send notification after commit (so it doesn't cause rollback if it fails)
            if ($requiresApproval && !empty($request->approvers)) {
                try {
                    $this->sendNotificationToNextApprover($headerId);
                } catch (\Exception $notifError) {
                    \Log::warning('OutletInternalUseWaste store - Notification failed (but data saved):', [
                        'header_id' => $headerId,
                        'error' => $notifError->getMessage()
                    ]);
                    // Don't throw - data is already saved
                }
            }
            
            return redirect()->route('outlet-internal-use-waste.index')->with('success', 'Data berhasil disimpan');
        } catch (\Exception $e) {
            \Log::error('Error in OutletInternalUseWaste store method:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['items']) // Exclude items to avoid log spam
            ]);
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $header = DB::table('outlet_internal_use_waste_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->select('h.*', 'o.nama_outlet as outlet_name')
            ->where('h.id', $id)
            ->first();
        if (!$header) {
            abort(404, 'Data tidak ditemukan');
        }
        $details = DB::table('outlet_internal_use_waste_details as d')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name')
            ->where('d.header_id', $id)
            ->get();
        return inertia('OutletInternalUseWaste/Show', [
            'id' => $id,
            'header' => $header,
            'details' => $details
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            // Ambil data header
            $header = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
            if (!$header) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
            }

            // Ambil semua detail untuk header ini
            $details = DB::table('outlet_internal_use_waste_details')->where('header_id', $id)->get();

            // Proses rollback stok untuk setiap detail
            foreach ($details as $detail) {
                // Cari inventory_item_id
                $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $detail->item_id)->first();
                if (!$inventoryItem) {
                    throw new \Exception('Inventory item not found for item_id: ' . $detail->item_id);
                }
                $inventory_item_id = $inventoryItem->id;

                // Ambil data konversi dari tabel items
                $itemMaster = DB::table('items')->where('id', $detail->item_id)->first();
                $unit = DB::table('units')->where('id', $detail->unit_id)->value('name');
                $qty_input = $detail->qty;
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

                // Rollback stok di outlet_food_inventory_stocks
                $stock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $header->outlet_id)
                    ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                    ->first();
                if ($stock) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $inventory_item_id)
                        ->where('id_outlet', $header->outlet_id)
                        ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                        ->update([
                            'qty_small' => $stock->qty_small + $qty_small,
                            'qty_medium' => $stock->qty_medium + $qty_medium,
                            'qty_large' => $stock->qty_large + $qty_large,
                            'updated_at' => now(),
                        ]);
                }

                // Hapus kartu stok OUT terkait untuk setiap detail
                DB::table('outlet_food_inventory_cards')
                    ->where('reference_type', 'outlet_internal_use_waste')
                    ->where('reference_id', $id) // Menggunakan header ID, bukan detail ID
                    ->delete();
            }

            // Hapus semua detail terlebih dahulu
            DB::table('outlet_internal_use_waste_details')->where('header_id', $id)->delete();
            
            // Hapus header
            DB::table('outlet_internal_use_waste_headers')->where('id', $id)->delete();

            // Activity log DELETE
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'outlet_internal_use_waste',
                'description' => 'Menghapus internal use/waste outlet: ' . $id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode(['header' => $header, 'details' => $details]),
                'new_data' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
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

    public function report(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $ruko_id = $request->input('ruko_id');

        $query = DB::table('outlet_internal_use_wastes')
            ->leftJoin('tbl_data_outlet', 'outlet_internal_use_wastes.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('items', 'outlet_internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'outlet_internal_use_wastes.unit_id', '=', 'units.id')
            ->leftJoin('tbl_data_ruko', 'outlet_internal_use_wastes.ruko_id', '=', 'tbl_data_ruko.id_ruko')
            ->select(
                'outlet_internal_use_wastes.*',
                'tbl_data_outlet.nama_outlet as outlet_name',
                'items.name as item_name',
                'units.name as unit_name',
                'tbl_data_ruko.nama_ruko'
            )
            ->where('outlet_internal_use_wastes.type', 'internal_use');

        if ($from) {
            $query->where('outlet_internal_use_wastes.date', '>=', $from);
        }
        if ($to) {
            $query->where('outlet_internal_use_wastes.date', '<=', $to);
        }
        if ($ruko_id) {
            $query->where('outlet_internal_use_wastes.ruko_id', $ruko_id);
        }
        $data = $query->orderByDesc('outlet_internal_use_wastes.date')->orderByDesc('outlet_internal_use_wastes.id')->get();

        $rukos = DB::table('tbl_data_ruko')->get();

        return inertia('OutletInternalUseWaste/Report', [
            'data' => $data,
            'rukos' => $rukos,
            'filters' => $request->only(['from', 'to', 'ruko_id'])
        ]);
    }

    public function reportWasteSpoil(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $outlet_id = $request->input('outlet_id');

        $query = DB::table('outlet_internal_use_wastes')
            ->leftJoin('tbl_data_outlet', 'outlet_internal_use_wastes.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('items', 'outlet_internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'outlet_internal_use_wastes.unit_id', '=', 'units.id')
            ->select(
                'outlet_internal_use_wastes.*',
                'tbl_data_outlet.nama_outlet as outlet_name',
                'items.name as item_name',
                'units.name as unit_name'
            )
            ->whereIn('outlet_internal_use_wastes.type', ['spoil', 'waste']);

        if ($from) {
            $query->where('outlet_internal_use_wastes.date', '>=', $from);
        }
        if ($to) {
            $query->where('outlet_internal_use_wastes.date', '<=', $to);
        }
        if ($outlet_id) {
            $query->where('outlet_internal_use_wastes.outlet_id', $outlet_id);
        }
        $data = $query->orderByDesc('outlet_internal_use_wastes.date')->orderByDesc('outlet_internal_use_wastes.id')->get();

        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return inertia('OutletInternalUseWaste/ReportWasteSpoil', [
            'data' => $data,
            'outlets' => $outlets,
            'filters' => $request->only(['from', 'to', 'outlet_id'])
        ]);
    }

    /**
     * Universal report for internal use, spoil, waste (with filter type, warehouse, date, outlet)
     */
    public function reportUniversal(Request $request)
    {
        $user = auth()->user();
        $type = $request->input('type');
        $warehouse_outlet_id = $request->input('warehouse_outlet_id');
        $from = $request->input('from');
        $to = $request->input('to');
        $selected_outlet_id = $request->input('outlet_id');

        $query = DB::table('outlet_internal_use_waste_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select(
                'h.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );
        if ($user->id_outlet != 1) {
            $query->where('h.outlet_id', $user->id_outlet);
        } else if ($request->filled('outlet_id')) {
            $query->where('h.outlet_id', $request->input('outlet_id'));
        }
        if ($type) {
            $query->where('h.type', $type);
        }
        if ($warehouse_outlet_id) {
            $query->where('h.warehouse_outlet_id', $warehouse_outlet_id);
        }
        if ($from) {
            $query->where('h.date', '>=', $from);
        }
        if ($to) {
            $query->where('h.date', '<=', $to);
        }
        $data = $query->orderByDesc('h.date')->orderByDesc('h.id')->get();

        // Hitung total per type
        $headerIds = $data->pluck('id')->all();
        $totalPerType = [];
        if ($headerIds) {
            $details = DB::table('outlet_internal_use_waste_details as d')
                ->join('outlet_internal_use_waste_headers as h', 'd.header_id', '=', 'h.id')
                ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
                ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
                ->select('d.*', 'h.type as header_type', 'h.date as header_date', 'h.outlet_id as header_outlet_id', 'h.warehouse_outlet_id as header_warehouse_outlet_id', 'i.small_unit_id', 'i.medium_unit_id', 'i.large_unit_id', 'i.small_conversion_qty', 'i.medium_conversion_qty')
                ->whereIn('d.header_id', $headerIds)
                ->get();
            foreach ($details as $item) {
                // Cari inventory_item_id
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $item->item_id)
                    ->first();
                $mac = null;
                if ($inventoryItem) {
                    $macRow = DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('id_outlet', $item->header_outlet_id)
                        ->where('warehouse_outlet_id', $item->header_warehouse_outlet_id)
                        ->where('date', '<=', $item->header_date)
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->first();
                    if ($macRow) {
                        $mac = $macRow->mac;
                    }
                }
                $mac_converted = null;
                if ($mac !== null) {
                    $mac_converted = $mac;
                    if ($item->unit_id == $item->medium_unit_id && $item->small_conversion_qty > 0) {
                        $mac_converted = $mac * $item->small_conversion_qty;
                    } elseif ($item->unit_id == $item->large_unit_id && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                        $mac_converted = $mac * $item->small_conversion_qty * $item->medium_conversion_qty;
                    }
                }
                $subtotal_mac = ($mac_converted !== null) ? ($mac_converted * $item->qty) : 0;
                $type = $item->header_type;
                if (!isset($totalPerType[$type])) $totalPerType[$type] = 0;
                $totalPerType[$type] += $subtotal_mac;
            }
        }

        $types = [
            ['value' => '', 'label' => 'Semua'],
            ['value' => 'internal_use', 'label' => 'Internal Use'],
            ['value' => 'spoil', 'label' => 'Spoil'],
            ['value' => 'waste', 'label' => 'Waste'],
            ['value' => 'r_and_d', 'label' => 'R & D'],
            ['value' => 'marketing', 'label' => 'Marketing'],
            ['value' => 'non_commodity', 'label' => 'Non Commodity'],
            ['value' => 'guest_supplies', 'label' => 'Guest Supplies'],
        ];
        
        // Filter warehouse outlets based on selected outlet or user's outlet
        $warehouse_outlets_query = DB::table('warehouse_outlets')->where('status', 'active');
        if ($user->id_outlet == 1) {
            // For superuser, filter by selected outlet if any
            if ($selected_outlet_id) {
                $warehouse_outlets_query->where('outlet_id', $selected_outlet_id);
            }
        } else {
            // For regular user, only show warehouse outlets for their outlet
            $warehouse_outlets_query->where('outlet_id', $user->id_outlet);
        }
        $warehouse_outlets = $warehouse_outlets_query->select('id', 'name', 'outlet_id')->orderBy('name')->get();
        
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return inertia('OutletInternalUseWaste/ReportUniversal', [
            'data' => $data,
            'types' => $types,
            'warehouse_outlets' => $warehouse_outlets,
            'outlets' => $outlets,
            'filters' => $request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']),
            'total_per_type' => $totalPerType,
        ]);
    }

    /**
     * Get detail items for a header (for report expand/collapse)
     */
    public function details($id)
    {
        // Ambil data header
        $header = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
        if (!$header) {
            \Log::debug('DETAILS: Header not found for id ' . $id);
            return response()->json(['details' => []]);
        }
        $details = DB::table('outlet_internal_use_waste_details as d')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name', 'i.small_unit_id', 'i.medium_unit_id', 'i.large_unit_id', 'i.small_conversion_qty', 'i.medium_conversion_qty')
            ->where('d.header_id', $id)
            ->get();
        \Log::debug('DETAILS: Found ' . count($details) . ' detail(s) for header_id ' . $id);
        \Log::debug('DETAILS: Detail data', $details->toArray());
        $result = [];
        foreach ($details as $item) {
            try {
                // Cari inventory_item_id
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $item->item_id)
                    ->first();
                \Log::debug('DETAILS: inventory_item_id for item_id ' . $item->item_id . ': ' . ($inventoryItem ? $inventoryItem->id : 'NOT FOUND'));
                $mac = null;
                if ($inventoryItem) {
                    // Ambil MAC terakhir sebelum/tanggal transaksi
                    $macRow = DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('id_outlet', $header->outlet_id)
                        ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                        ->where('date', '<=', $header->date)
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->first();
                    if ($macRow) {
                        $mac = $macRow->mac;
                    }
                }
                \Log::debug('DETAILS: MAC for item_id ' . $item->item_id . ': ' . ($mac !== null ? $mac : 'NOT FOUND'));
                // Konversi MAC ke unit yang dipakai user
                $mac_converted = null;
                if ($mac !== null) {
                    // Default: MAC sudah dalam unit kecil
                    $mac_converted = $mac;
                    // Cek unit yang dipakai user
                    if ($item->unit_id == $item->medium_unit_id && $item->small_conversion_qty > 0) {
                        $mac_converted = $mac * $item->small_conversion_qty;
                    } elseif ($item->unit_id == $item->large_unit_id && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                        $mac_converted = $mac * $item->small_conversion_qty * $item->medium_conversion_qty;
                    }
                }
                $subtotal_mac = ($mac_converted !== null) ? ($mac_converted * $item->qty) : null;
                \Log::debug('DETAILS: mac_converted=' . $mac_converted . ', subtotal_mac=' . $subtotal_mac);
                $result[] = [
                    ...collect($item)->toArray(),
                    'mac_converted' => $mac_converted,
                    'subtotal_mac' => $subtotal_mac
                ];
            } catch (\Throwable $e) {
                \Log::error('DETAILS: Error processing item_id ' . $item->item_id . ': ' . $e->getMessage());
            }
        }
        \Log::debug('DETAILS: Final result', $result);
        return response()->json(['details' => $result]);
    }

    /**
     * Get approvers for approval flow
     */
    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');
        
        $users = User::where('users.status', 'A')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where(function($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            })
            ->select('users.id', 'users.nama_lengkap as name', 'users.email', 'tbl_data_jabatan.nama_jabatan as jabatan')
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();
        
        return response()->json(['success' => true, 'users' => $users]);
    }

    /**
     * Approve outlet internal use waste header
     */
    public function approve(Request $request, $id)
    {
        $header = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
        
        if (!$header) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        if ($header->status !== 'SUBMITTED') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya data dengan status SUBMITTED yang dapat di-approve'
            ], 400);
        }

        try {
            DB::beginTransaction();
            $currentApprover = auth()->user();
            
            // Update the approval flow for current approver
            $currentApprovalFlow = DB::table('outlet_internal_use_waste_approval_flows')
                ->where('header_id', $id)
                ->where('approver_id', $currentApprover->id)
                ->where('status', 'PENDING')
                ->first();
            
            if (!$currentApprovalFlow) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak untuk approve data ini'
                ], 403);
            }
            
            DB::table('outlet_internal_use_waste_approval_flows')
                ->where('id', $currentApprovalFlow->id)
                ->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);
            
            // Check if there are more approvers pending (need to check if all lower levels are approved)
            $currentLevel = $currentApprovalFlow->approval_level;
            $lowerLevelsPending = DB::table('outlet_internal_use_waste_approval_flows')
                ->where('header_id', $id)
                ->where('approval_level', '<', $currentLevel)
                ->where('status', 'PENDING')
                ->count();
            
            if ($lowerLevelsPending > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tunggu approval dari level yang lebih rendah terlebih dahulu'
                ], 400);
            }
            
            // Check if there are more approvers pending at same or higher level
            $pendingApprovers = DB::table('outlet_internal_use_waste_approval_flows')
                ->where('header_id', $id)
                ->where('status', 'PENDING')
                ->count();
            
            if ($pendingApprovers > 0) {
                // Still have pending approvers, keep status as SUBMITTED
                // Send notification to next approver
                DB::commit();
                $this->sendNotificationToNextApprover($id);
                
                $message = 'Approval berhasil! Notifikasi dikirim ke approver berikutnya.';
            } else {
                // All approvers have approved, process stock and update status
                $this->processStockAfterApproval($id);
                
                DB::table('outlet_internal_use_waste_headers')
                    ->where('id', $id)
                    ->update([
                        'status' => 'APPROVED',
                        'updated_at' => now(),
                    ]);
                
                DB::commit();
                $message = 'Semua approval telah selesai! Stock telah diproses.';
            }
            
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error approving outlet internal use waste: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject outlet internal use waste header
     */
    public function reject(Request $request, $id)
    {
        $header = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
        
        if (!$header) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        if ($header->status !== 'SUBMITTED') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya data dengan status SUBMITTED yang dapat ditolak'
            ], 400);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            DB::beginTransaction();
            $currentApprover = auth()->user();
            
            // Update the approval flow for current approver
            $currentApprovalFlow = DB::table('outlet_internal_use_waste_approval_flows')
                ->where('header_id', $id)
                ->where('approver_id', $currentApprover->id)
                ->where('status', 'PENDING')
                ->first();
            
            if (!$currentApprovalFlow) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak untuk menolak data ini'
                ], 403);
            }
            
            DB::table('outlet_internal_use_waste_approval_flows')
                ->where('id', $currentApprovalFlow->id)
                ->update([
                    'status' => 'REJECTED',
                    'rejected_at' => now(),
                    'comments' => $validated['rejection_reason'],
                    'updated_at' => now(),
                ]);
            
            // Update header status to REJECTED
            DB::table('outlet_internal_use_waste_headers')
                ->where('id', $id)
                ->update([
                    'status' => 'REJECTED',
                    'updated_at' => now(),
                ]);
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil ditolak'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error rejecting outlet internal use waste: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses penolakan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process stock after all approvals are complete
     */
    private function processStockAfterApproval($headerId)
    {
        $header = DB::table('outlet_internal_use_waste_headers')->where('id', $headerId)->first();
        if (!$header) {
            throw new \Exception('Header not found');
        }
        
        $details = DB::table('outlet_internal_use_waste_details')->where('header_id', $headerId)->get();
        
        foreach ($details as $item) {
            $inventoryItem = DB::table('outlet_food_inventory_items')
                ->where('item_id', $item->item_id)
                ->first();
            if (!$inventoryItem) {
                continue;
            }
            
            $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
            $unit = DB::table('units')->where('id', $item->unit_id)->value('name');
            $qty_input = $item->qty;
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
            
            $stock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('id_outlet', $header->outlet_id)
                ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                ->first();
            
            if (!$stock) {
                continue;
            }
            
            // Check stock availability
            if ($qty_small > $stock->qty_small) {
                throw new \Exception("Qty melebihi stok yang tersedia. Stok tersedia: {$stock->qty_small} {$unitSmall}");
            }
            
            // Update stok di outlet (kurangi)
            DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('id_outlet', $header->outlet_id)
                ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                ->update([
                    'qty_small' => $stock->qty_small - $qty_small,
                    'qty_medium' => $stock->qty_medium - $qty_medium,
                    'qty_large' => $stock->qty_large - $qty_large,
                    'updated_at' => now(),
                ]);
            
            // Insert kartu stok OUT
            DB::table('outlet_food_inventory_cards')->insert([
                'inventory_item_id' => $inventoryItem->id,
                'id_outlet' => $header->outlet_id,
                'warehouse_outlet_id' => $header->warehouse_outlet_id,
                'date' => $header->date,
                'reference_type' => 'outlet_internal_use_waste',
                'reference_id' => $headerId,
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
                'description' => 'Stock Out - ' . $header->type . ' (After Approval)',
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Send notification to the next approver in line
     */
    private function sendNotificationToNextApprover($headerId)
    {
        try {
            // Get the lowest level approver that is still pending
            $nextApprover = DB::table('outlet_internal_use_waste_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->where('af.header_id', $headerId)
                ->where('af.status', 'PENDING')
                ->orderBy('af.approval_level')
                ->select('u.id', 'u.nama_lengkap', 'u.email')
                ->first();

            if (!$nextApprover) {
                return; // No pending approvers
            }

            // Get header details
            $header = DB::table('outlet_internal_use_waste_headers as h')
                ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
                ->join('users as creator', 'h.created_by', '=', 'creator.id')
                ->where('h.id', $headerId)
                ->select('h.*', 'o.nama_outlet', 'creator.nama_lengkap as creator_name')
                ->first();

            if (!$header) {
                return;
            }

            // Create notification (assuming you have a notifications table or system)
            // This is a placeholder - adjust based on your notification system
            if (DB::getSchemaBuilder()->hasTable('notifications')) {
                DB::table('notifications')->insert([
                    'user_id' => $nextApprover->id,
                    'type' => 'outlet_internal_use_waste_approval',
                    'title' => 'Approval Category Cost Outlet',
                    'message' => "Category Cost Outlet dengan tipe {$header->type} dari outlet {$header->nama_outlet} oleh {$header->creator_name} menunggu approval Anda.",
                    'data' => json_encode(['header_id' => $headerId]),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error sending notification to next approver: ' . $e->getMessage());
        }
    }

    /**
     * Get pending approvals for current user
     */
    public function getPendingApprovals(Request $request)
    {
        $currentUser = auth()->user();
        
        // Get all headers that have pending approval for current user
        // Only show if all lower level approvals are done
        $pendingHeaders = DB::table('outlet_internal_use_waste_headers as h')
            ->join('outlet_internal_use_waste_approval_flows as af', 'h.id', '=', 'af.header_id')
            ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->join('users as creator', 'h.created_by', '=', 'creator.id')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->where('af.approver_id', $currentUser->id)
            ->where('af.status', 'PENDING')
            ->where('h.status', 'SUBMITTED')
            ->select(
                'h.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'creator.nama_lengkap as creator_name',
                'af.approval_level'
            )
            ->get()
            ->filter(function($header) use ($currentUser) {
                // Check if all lower level approvals are done
                $lowerLevelsPending = DB::table('outlet_internal_use_waste_approval_flows')
                    ->where('header_id', $header->id)
                    ->where('approval_level', '<', $header->approval_level)
                    ->where('status', 'PENDING')
                    ->count();
                
                return $lowerLevelsPending === 0;
            })
            ->values();
        
        return response()->json([
            'success' => true,
            'headers' => $pendingHeaders
        ]);
    }

    /**
     * Get approval details for a header (for modal display)
     */
    public function getApprovalDetails($id)
    {
        $header = DB::table('outlet_internal_use_waste_headers as h')
            ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->join('users as creator', 'h.created_by', '=', 'creator.id')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->where('h.id', $id)
            ->select(
                'h.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'creator.nama_lengkap as creator_name'
            )
            ->first();
        
        if (!$header) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }
        
        $details = DB::table('outlet_internal_use_waste_details as d')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->join('units as u', 'd.unit_id', '=', 'u.id')
            ->where('d.header_id', $id)
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name')
            ->get();
        
        $approvalFlows = DB::table('outlet_internal_use_waste_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.header_id', $id)
            ->select(
                'af.*',
                'u.nama_lengkap as approver_name',
                'u.email as approver_email',
                'j.nama_jabatan as approver_jabatan'
            )
            ->orderBy('af.approval_level')
            ->get();
        
        return response()->json([
            'success' => true,
            'header' => $header,
            'details' => $details,
            'approval_flows' => $approvalFlows
        ]);
    }
} 