<?php

namespace App\Http\Controllers;

use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockOpnameApprovalFlow;
use App\Models\StockOpnameAdjustment;
use App\Services\NotificationService;
use App\Exports\StockOpnameImportTemplateExport;
use App\Imports\StockOpnameImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class StockOpnameController extends Controller
{
    /**
     * Display a listing of stock opnames
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $search = $request->get('search', '');
        $status = $request->get('status', 'all');
        $outletId = $request->get('outlet_id', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $perPage = $request->get('per_page', 15);

        $query = StockOpname::with([
            'outlet',
            'warehouseOutlet',
            'creator',
            'approvalFlows.approver'
        ]);

        // Filter by outlet if user is not superadmin
        if ($user->id_outlet != 1) {
            $query->where('outlet_id', $user->id_outlet);
        }

        // Apply filters
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('opname_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($outletId) {
            // Validate: if user is not superadmin, can only filter their own outlet
            if ($user->id_outlet != 1 && $outletId != $user->id_outlet) {
                $outletId = $user->id_outlet; // Force to user's outlet
            }
            $query->where('outlet_id', $outletId);
        }

        if ($dateFrom) {
            $query->whereDate('opname_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('opname_date', '<=', $dateTo);
        }

        $stockOpnames = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Get outlets for filter
        $outletsQuery = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        if ($user->id_outlet != 1) {
            $outletsQuery->where('id_outlet', $user->id_outlet);
        }

        $outlets = $outletsQuery->get();

        return Inertia::render('StockOpname/Index', [
            'stockOpnames' => $stockOpnames,
            'outlets' => $outlets,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'outlet_id' => $outletId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'user_outlet_id' => $user->id_outlet ?? null,
        ]);
    }

    /**
     * Show the form for creating a new stock opname
     */
    public function create(Request $request)
    {
        $user = auth()->user();
        $outletId = $request->get('outlet_id');
        $warehouseOutletId = $request->get('warehouse_outlet_id');

        // Get outlets
        $outletsQuery = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        if ($user->id_outlet != 1) {
            $outletsQuery->where('id_outlet', $user->id_outlet);
            $outletId = $user->id_outlet;
        }

        $outlets = $outletsQuery->get();

        // Get warehouse outlets
        $warehouseOutletsQuery = DB::table('warehouse_outlets')
            ->where('status', 'active')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name');

        if ($user->id_outlet != 1) {
            $warehouseOutletsQuery->where('outlet_id', $user->id_outlet);
        }

        if ($outletId) {
            $warehouseOutletsQuery->where('outlet_id', $outletId);
        }

        $warehouseOutlets = $warehouseOutletsQuery->get();

        // Get inventory items if outlet and warehouse outlet selected
        $items = [];
        if ($outletId && $warehouseOutletId) {
            $items = $this->getInventoryItems($outletId, $warehouseOutletId);
        }

        return Inertia::render('StockOpname/Create', [
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'items' => $items,
            'selectedOutletId' => $outletId,
            'selectedWarehouseOutletId' => $warehouseOutletId,
            'user_outlet_id' => $user->id_outlet ?? null,
        ]);
    }

    /**
     * Get inventory items for selected outlet and warehouse outlet
     */
    public function getInventoryItems($outletId, $warehouseOutletId)
    {
        $query = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->where('s.id_outlet', $outletId)
            ->where('s.warehouse_outlet_id', $warehouseOutletId)
            ->where(function($q) {
                $q->where('s.qty_small', '>', 0)
                  ->orWhere('s.qty_medium', '>', 0)
                  ->orWhere('s.qty_large', '>', 0);
            })
            ->select(
                'fi.id as inventory_item_id',
                'i.id as item_id',
                'i.name as item_name',
                'c.name as category_name',
                's.qty_small as qty_system_small',
                's.qty_medium as qty_system_medium',
                's.qty_large as qty_system_large',
                's.last_cost_small as mac',
                's.value',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->orderBy('c.name')
            ->orderBy('i.name')
            ->get();

        return $query;
    }

    /**
     * Store a newly created stock opname in storage
     */
    public function store(Request $request)
    {
        // For autosave, allow empty items array
        $itemsRule = $request->has('autosave') && $request->autosave 
            ? 'nullable|array' 
            : 'required|array|min:1';
            
        $validated = $request->validate([
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'nullable|integer',
            'opname_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => $itemsRule,
            'items.*.inventory_item_id' => 'required|integer',
            'items.*.qty_physical_small' => 'nullable|numeric|min:0',
            'items.*.qty_physical_medium' => 'nullable|numeric|min:0',
            'items.*.qty_physical_large' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'approvers' => 'nullable|array',
            'approvers.*' => 'integer|exists:users,id',
        ]);

        $user = auth()->user();

        // Validate outlet access - if user is not superadmin, force to their outlet
        if ($user->id_outlet != 1) {
            if ($user->id_outlet != $validated['outlet_id']) {
                return back()->withErrors(['error' => 'Anda tidak memiliki akses untuk outlet ini.']);
            }
            // Force outlet_id to user's outlet
            $validated['outlet_id'] = $user->id_outlet;
        }

        try {
            DB::beginTransaction();

            // Generate opname number
            $opnameNumber = $this->generateOpnameNumber();

            // Determine status: if autosave, keep as DRAFT; if manual save, set to SAVED
            $status = ($request->has('autosave') && $request->autosave) ? 'DRAFT' : 'SAVED';

            // Create stock opname
            $stockOpname = StockOpname::create([
                'opname_number' => $opnameNumber,
                'outlet_id' => $validated['outlet_id'],
                'warehouse_outlet_id' => $validated['warehouse_outlet_id'],
                'opname_date' => $validated['opname_date'],
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            // Create items (skip if items array is empty for autosave)
            $items = $validated['items'] ?? [];
            
            // For autosave, only save items that have been filled in
            // Item is considered "filled in" if:
            // 1. Has at least one physical qty field with a value (not null, not empty string, or 0 is valid)
            // 2. Has reason filled in
            // 3. Or has been explicitly set (field exists in request, even if value is null/empty - means user clicked "=" button)
            if ($request->has('autosave') && $request->autosave) {
                $items = array_filter($items, function($itemData) {
                    // Check if any physical qty field has been explicitly set (including 0)
                    $hasQtySmall = array_key_exists('qty_physical_small', $itemData) && $itemData['qty_physical_small'] !== null && $itemData['qty_physical_small'] !== '';
                    $hasQtyMedium = array_key_exists('qty_physical_medium', $itemData) && $itemData['qty_physical_medium'] !== null && $itemData['qty_physical_medium'] !== '';
                    $hasQtyLarge = array_key_exists('qty_physical_large', $itemData) && $itemData['qty_physical_large'] !== null && $itemData['qty_physical_large'] !== '';
                    $hasReason = isset($itemData['reason']) && $itemData['reason'] !== null && trim($itemData['reason']) !== '';
                    
                    // Item is filled if any qty field is set OR reason is filled
                    return $hasQtySmall || $hasQtyMedium || $hasQtyLarge || $hasReason;
                });
            }
            
            // PERFORMANCE OPTIMIZATION: Batch query untuk stocks (fix N+1 query problem)
            // Get all inventory item IDs
            $inventoryItemIds = array_column($items, 'inventory_item_id');
            
            // Batch fetch all stocks in one query instead of querying per item
            $stocks = [];
            if (!empty($inventoryItemIds)) {
                $stocksQuery = DB::table('outlet_food_inventory_stocks')
                    ->whereIn('inventory_item_id', $inventoryItemIds)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_id'])
                    ->get();
                
                // Index by inventory_item_id for fast lookup
                foreach ($stocksQuery as $stock) {
                    $stocks[$stock->inventory_item_id] = $stock;
                }
            }
            
            // Prepare items for batch insert
            $itemsToInsert = [];
            
            foreach ($items as $itemData) {
                $inventoryItemId = $itemData['inventory_item_id'];
                
                // Get stock from batch result
                $stock = $stocks[$inventoryItemId] ?? null;

                if (!$stock) {
                    continue; // Skip if stock not found
                }

                $qtySystemSmall = $stock->qty_small ?? 0;
                $qtySystemMedium = $stock->qty_medium ?? 0;
                $qtySystemLarge = $stock->qty_large ?? 0;
                $mac = $stock->last_cost_small ?? 0;

                // Get physical qty from request
                // Check if value is explicitly provided (not null and not empty string)
                // If null or empty string, use system qty (tombol "=")
                // If 0 or any numeric value is provided, use that value
                $qtyPhysicalSmall = (array_key_exists('qty_physical_small', $itemData) && $itemData['qty_physical_small'] !== null && $itemData['qty_physical_small'] !== '') 
                    ? (float)$itemData['qty_physical_small'] 
                    : $qtySystemSmall;
                $qtyPhysicalMedium = (array_key_exists('qty_physical_medium', $itemData) && $itemData['qty_physical_medium'] !== null && $itemData['qty_physical_medium'] !== '') 
                    ? (float)$itemData['qty_physical_medium'] 
                    : $qtySystemMedium;
                $qtyPhysicalLarge = (array_key_exists('qty_physical_large', $itemData) && $itemData['qty_physical_large'] !== null && $itemData['qty_physical_large'] !== '') 
                    ? (float)$itemData['qty_physical_large'] 
                    : $qtySystemLarge;

                // Calculate difference
                $qtyDiffSmall = $qtyPhysicalSmall - $qtySystemSmall;
                $qtyDiffMedium = $qtyPhysicalMedium - $qtySystemMedium;
                $qtyDiffLarge = $qtyPhysicalLarge - $qtySystemLarge;

                // Validate: if there's a difference, reason is required
                $hasDifference = abs($qtyDiffSmall) > 0.01 || abs($qtyDiffMedium) > 0.01 || abs($qtyDiffLarge) > 0.01;
                $hasReason = isset($itemData['reason']) && $itemData['reason'] !== null && trim($itemData['reason']) !== '';
                
                if ($hasDifference && !$hasReason) {
                    // Skip autosave validation for reason
                    if (!$request->has('autosave') || !$request->autosave) {
                        DB::rollBack();
                        return back()->withErrors([
                            'error' => 'Alasan wajib diisi jika ada selisih antara qty system dan qty physical.'
                        ]);
                    }
                }

                // Calculate value adjustment (using MAC for small unit)
                $valueAdjustment = $qtyDiffSmall * $mac;

                // Prepare for batch insert
                $itemsToInsert[] = [
                    'stock_opname_id' => $stockOpname->id,
                    'inventory_item_id' => $inventoryItemId,
                    'qty_system_small' => $qtySystemSmall,
                    'qty_system_medium' => $qtySystemMedium,
                    'qty_system_large' => $qtySystemLarge,
                    'qty_physical_small' => $qtyPhysicalSmall,
                    'qty_physical_medium' => $qtyPhysicalMedium,
                    'qty_physical_large' => $qtyPhysicalLarge,
                    'qty_diff_small' => $qtyDiffSmall,
                    'qty_diff_medium' => $qtyDiffMedium,
                    'qty_diff_large' => $qtyDiffLarge,
                    'reason' => $itemData['reason'] ?? null,
                    'mac_before' => $mac,
                    'mac_after' => $mac, // MAC tidak berubah (sesuai rekomendasi)
                    'value_adjustment' => $valueAdjustment,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            // PERFORMANCE OPTIMIZATION: Batch insert all items at once
            if (!empty($itemsToInsert)) {
                StockOpnameItem::insert($itemsToInsert);
            }

            // Save approvers if provided (only if status is still DRAFT)
            if ($stockOpname->status === 'DRAFT' && isset($validated['approvers']) && is_array($validated['approvers']) && count($validated['approvers']) > 0) {
                // Delete existing approval flows if any
                $stockOpname->approvalFlows()->delete();

                // Create approval flows
                foreach ($validated['approvers'] as $index => $approverId) {
                    StockOpnameApprovalFlow::create([
                        'stock_opname_id' => $stockOpname->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1, // Level 1 = terendah, level terakhir = tertinggi
                        'status' => 'PENDING',
                    ]);
                }
            }

            DB::commit();

            // If autosave request, return JSON
            if ($request->has('autosave') && $request->autosave) {
                return response()->json([
                    'success' => true,
                    'id' => $stockOpname->id,
                    'message' => 'Draft tersimpan'
                ]);
            }

            return redirect()->route('stock-opnames.show', $stockOpname->id)
                           ->with('success', 'Stock Opname berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();
            
            // If autosave request, return JSON error
            if ($request->has('autosave') && $request->autosave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menyimpan draft: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withErrors(['error' => 'Gagal membuat stock opname: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified stock opname
     */
    public function show($id)
    {
        $stockOpname = StockOpname::with([
            'outlet',
            'warehouseOutlet',
            'creator',
            'items.inventoryItem.item',
            'items.inventoryItem.item.category',
            'items.inventoryItem.item.smallUnit',
            'items.inventoryItem.item.mediumUnit',
            'items.inventoryItem.item.largeUnit',
            'approvalFlows.approver',
            'approvalFlows.approver.jabatan'
        ])->findOrFail($id);

        $user = auth()->user();

        // Validate access
        if ($user->id_outlet != 1 && $user->id_outlet != $stockOpname->outlet_id) {
            abort(403, 'Anda tidak memiliki akses untuk stock opname ini.');
        }

        // Check if user can approve
        $canApprove = false;
        $pendingFlow = $stockOpname->approvalFlows()
            ->where('status', 'PENDING')
            ->orderBy('approval_level')
            ->first();

        if ($pendingFlow && $pendingFlow->approver_id == $user->id) {
            $canApprove = true;
        }

        // Check if user is superadmin
        if ($user->id_role === '5af56935b011a') {
            $canApprove = true;
        }

        // Get users for approver selection
        $usersQuery = DB::table('users')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where('users.status', 'active')
            ->select('users.id', 'users.nama_lengkap', 'tbl_data_jabatan.nama_jabatan')
            ->orderBy('users.nama_lengkap');

        // Filter by outlet if user is not superadmin
        if ($user->id_outlet != 1) {
            $usersQuery->where('users.id_outlet', $user->id_outlet);
        }

        $users = $usersQuery->get()->map(function($u) {
            return [
                'id' => $u->id,
                'nama_lengkap' => $u->nama_lengkap,
                'jabatan' => $u->nama_jabatan ? ['nama_jabatan' => $u->nama_jabatan] : null,
            ];
        });

        // Get approvers for display
        $approvers = $stockOpname->approvalFlows()
            ->with(['approver:id,nama_lengkap,email,id_jabatan', 'approver.jabatan:id_jabatan,nama_jabatan'])
            ->orderBy('approval_level')
            ->get()
            ->map(function($flow) {
                return [
                    'id' => $flow->approver_id,
                    'name' => $flow->approver->nama_lengkap ?? '',
                    'email' => $flow->approver->email ?? '',
                    'jabatan' => ($flow->approver && $flow->approver->jabatan) ? $flow->approver->jabatan->nama_jabatan : '',
                ];
            });

        // Add approvers to stockOpname object for frontend
        $stockOpname->approvers = $approvers;

        return Inertia::render('StockOpname/Show', [
            'stockOpname' => $stockOpname,
            'canApprove' => $canApprove,
            'pendingFlow' => $pendingFlow,
            'users' => $users,
            'user_outlet_id' => $user->id_outlet ?? null,
        ]);
    }

    /**
     * Show the form for editing the specified stock opname
     */
    public function edit($id)
    {
        $stockOpname = StockOpname::with([
            'outlet',
            'warehouseOutlet',
            'items.inventoryItem.item'
        ])->findOrFail($id);

        $user = auth()->user();

        // Validate access
        if ($user->id_outlet != 1 && $user->id_outlet != $stockOpname->outlet_id) {
            abort(403, 'Anda tidak memiliki akses untuk stock opname ini.');
        }

        // Only allow editing if status is DRAFT (not SAVED or other statuses)
        if ($stockOpname->status !== 'DRAFT') {
            return redirect()->route('stock-opnames.show', $stockOpname->id)
                           ->with('error', 'Stock opname hanya dapat diedit jika belum disimpan. Setelah klik tombol Simpan Draft, data tidak dapat diedit lagi.');
        }

        // Get outlets
        $outletsQuery = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        if ($user->id_outlet != 1) {
            $outletsQuery->where('id_outlet', $user->id_outlet);
        }

        $outlets = $outletsQuery->get();

        // Get warehouse outlets
        $warehouseOutletsQuery = DB::table('warehouse_outlets')
            ->where('status', 'active')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name');

        if ($user->id_outlet != 1) {
            $warehouseOutletsQuery->where('outlet_id', $user->id_outlet);
        }

        if ($stockOpname->outlet_id) {
            $warehouseOutletsQuery->where('outlet_id', $stockOpname->outlet_id);
        }

        $warehouseOutlets = $warehouseOutletsQuery->get();

        // Get current items with their data
        $items = [];
        if ($stockOpname->outlet_id && $stockOpname->warehouse_outlet_id) {
            $items = $this->getInventoryItems($stockOpname->outlet_id, $stockOpname->warehouse_outlet_id);
            
            // Merge with existing opname items data
            foreach ($items as $item) {
                $existingItem = $stockOpname->items->firstWhere('inventory_item_id', $item->inventory_item_id);
                if ($existingItem) {
                    $item->qty_physical_small = $existingItem->qty_physical_small;
                    $item->qty_physical_medium = $existingItem->qty_physical_medium;
                    $item->qty_physical_large = $existingItem->qty_physical_large;
                    $item->reason = $existingItem->reason;
                }
            }
        }

        // Get existing approvers
        $approvers = $stockOpname->approvalFlows()
            ->with('approver:id,nama_lengkap,email,id_jabatan')
            ->orderBy('approval_level')
            ->get()
            ->map(function($flow) {
                return [
                    'id' => $flow->approver_id,
                    'name' => $flow->approver->nama_lengkap ?? '',
                    'email' => $flow->approver->email ?? '',
                    'jabatan' => $flow->approver->jabatan->nama_jabatan ?? '',
                ];
            });

        return Inertia::render('StockOpname/Edit', [
            'stockOpname' => $stockOpname,
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'items' => $items,
            'approvers' => $approvers,
            'user_outlet_id' => $user->id_outlet ?? null,
        ]);
    }

    /**
     * Update the specified stock opname in storage
     */
    public function update(Request $request, $id)
    {
        $stockOpname = StockOpname::findOrFail($id);
        $user = auth()->user();

        // Validate access
        if ($user->id_outlet != 1 && $user->id_outlet != $stockOpname->outlet_id) {
            return back()->withErrors(['error' => 'Anda tidak memiliki akses untuk stock opname ini.']);
        }

        // Only allow editing if status is DRAFT (not SAVED or other statuses)
        if ($stockOpname->status !== 'DRAFT') {
            return back()->withErrors(['error' => 'Stock opname hanya dapat diedit jika belum disimpan. Setelah klik tombol Simpan Draft, data tidak dapat diedit lagi.']);
        }

        // For autosave, allow empty items array
        $itemsRule = $request->has('autosave') && $request->autosave 
            ? 'nullable|array' 
            : 'required|array|min:1';
            
        $validated = $request->validate([
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'nullable|integer',
            'opname_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => $itemsRule,
            'items.*.inventory_item_id' => 'required|integer',
            'items.*.qty_physical_small' => 'nullable|numeric|min:0',
            'items.*.qty_physical_medium' => 'nullable|numeric|min:0',
            'items.*.qty_physical_large' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'approvers' => 'nullable|array',
            'approvers.*' => 'integer|exists:users,id',
        ]);

        // Validate outlet access - if user is not superadmin, force to their outlet
        if ($user->id_outlet != 1) {
            if ($user->id_outlet != $validated['outlet_id']) {
                return back()->withErrors(['error' => 'Anda tidak memiliki akses untuk outlet ini.']);
            }
            // Force outlet_id to user's outlet
            $validated['outlet_id'] = $user->id_outlet;
        }

        try {
            DB::beginTransaction();

            // Determine status: if autosave, keep as DRAFT; if manual save, set to SAVED
            $status = ($request->has('autosave') && $request->autosave) ? 'DRAFT' : 'SAVED';

            // Update stock opname header
            $stockOpname->update([
                'outlet_id' => $validated['outlet_id'],
                'warehouse_outlet_id' => $validated['warehouse_outlet_id'],
                'opname_date' => $validated['opname_date'],
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => $user->id,
            ]);

            // PERFORMANCE OPTIMIZATION: Use upsert pattern instead of delete & recreate
            // Get existing items indexed by inventory_item_id
            $existingItems = $stockOpname->items()->get()->keyBy('inventory_item_id');

            // Process new items (skip if items array is empty for autosave)
            $items = $validated['items'] ?? [];
            
            // For autosave, only save items that have been filled in
            if ($request->has('autosave') && $request->autosave) {
                $items = array_filter($items, function($itemData) {
                    $hasQtySmall = array_key_exists('qty_physical_small', $itemData) && $itemData['qty_physical_small'] !== null && $itemData['qty_physical_small'] !== '';
                    $hasQtyMedium = array_key_exists('qty_physical_medium', $itemData) && $itemData['qty_physical_medium'] !== null && $itemData['qty_physical_medium'] !== '';
                    $hasQtyLarge = array_key_exists('qty_physical_large', $itemData) && $itemData['qty_physical_large'] !== null && $itemData['qty_physical_large'] !== '';
                    $hasReason = isset($itemData['reason']) && $itemData['reason'] !== null && trim($itemData['reason']) !== '';
                    return $hasQtySmall || $hasQtyMedium || $hasQtyLarge || $hasReason;
                });
            }
            
            // PERFORMANCE OPTIMIZATION: Batch query untuk stocks (fix N+1 query problem)
            $inventoryItemIds = array_column($items, 'inventory_item_id');
            $stocks = [];
            if (!empty($inventoryItemIds)) {
                $stocksQuery = DB::table('outlet_food_inventory_stocks')
                    ->whereIn('inventory_item_id', $inventoryItemIds)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_id'])
                    ->get();
                
                foreach ($stocksQuery as $stock) {
                    $stocks[$stock->inventory_item_id] = $stock;
                }
            }
            
            // Prepare items for batch operations
            $itemsToInsert = [];
            $itemsToUpdate = [];
            $inventoryItemIdsToKeep = [];
            
            foreach ($items as $itemData) {
                $inventoryItemId = $itemData['inventory_item_id'];
                $inventoryItemIdsToKeep[] = $inventoryItemId;
                
                $stock = $stocks[$inventoryItemId] ?? null;
                if (!$stock) {
                    continue;
                }

                $qtySystemSmall = $stock->qty_small ?? 0;
                $qtySystemMedium = $stock->qty_medium ?? 0;
                $qtySystemLarge = $stock->qty_large ?? 0;
                $mac = $stock->last_cost_small ?? 0;

                $qtyPhysicalSmall = (array_key_exists('qty_physical_small', $itemData) && $itemData['qty_physical_small'] !== null && $itemData['qty_physical_small'] !== '') 
                    ? (float)$itemData['qty_physical_small'] 
                    : $qtySystemSmall;
                $qtyPhysicalMedium = (array_key_exists('qty_physical_medium', $itemData) && $itemData['qty_physical_medium'] !== null && $itemData['qty_physical_medium'] !== '') 
                    ? (float)$itemData['qty_physical_medium'] 
                    : $qtySystemMedium;
                $qtyPhysicalLarge = (array_key_exists('qty_physical_large', $itemData) && $itemData['qty_physical_large'] !== null && $itemData['qty_physical_large'] !== '') 
                    ? (float)$itemData['qty_physical_large'] 
                    : $qtySystemLarge;

                $qtyDiffSmall = $qtyPhysicalSmall - $qtySystemSmall;
                $qtyDiffMedium = $qtyPhysicalMedium - $qtySystemMedium;
                $qtyDiffLarge = $qtyPhysicalLarge - $qtySystemLarge;

                $hasDifference = abs($qtyDiffSmall) > 0.01 || abs($qtyDiffMedium) > 0.01 || abs($qtyDiffLarge) > 0.01;
                $hasReason = isset($itemData['reason']) && $itemData['reason'] !== null && trim($itemData['reason']) !== '';
                
                if ($hasDifference && !$hasReason) {
                    if (!$request->has('autosave') || !$request->autosave) {
                        DB::rollBack();
                        return back()->withErrors([
                            'error' => 'Alasan wajib diisi jika ada selisih antara qty system dan qty physical.'
                        ]);
                    }
                }

                $valueAdjustment = $qtyDiffSmall * $mac;
                
                $itemDataToSave = [
                    'qty_system_small' => $qtySystemSmall,
                    'qty_system_medium' => $qtySystemMedium,
                    'qty_system_large' => $qtySystemLarge,
                    'qty_physical_small' => $qtyPhysicalSmall,
                    'qty_physical_medium' => $qtyPhysicalMedium,
                    'qty_physical_large' => $qtyPhysicalLarge,
                    'qty_diff_small' => $qtyDiffSmall,
                    'qty_diff_medium' => $qtyDiffMedium,
                    'qty_diff_large' => $qtyDiffLarge,
                    'reason' => $itemData['reason'] ?? null,
                    'mac_before' => $mac,
                    'mac_after' => $mac,
                    'value_adjustment' => $valueAdjustment,
                ];

                // Check if item already exists
                if ($existingItems->has($inventoryItemId)) {
                    // Update existing item
                    $existingItem = $existingItems[$inventoryItemId];
                    $itemsToUpdate[] = [
                        'id' => $existingItem->id,
                        ...$itemDataToSave,
                        'updated_at' => now(),
                    ];
                } else {
                    // Insert new item
                    $itemsToInsert[] = [
                        'stock_opname_id' => $stockOpname->id,
                        'inventory_item_id' => $inventoryItemId,
                        ...$itemDataToSave,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            
            // PERFORMANCE OPTIMIZATION: Batch operations
            // Delete items that are no longer in the list (only if not autosave)
            if (!$request->has('autosave') || !$request->autosave) {
                $stockOpname->items()
                    ->whereNotIn('inventory_item_id', $inventoryItemIdsToKeep)
                    ->delete();
            }
            
            // Batch update existing items
            if (!empty($itemsToUpdate)) {
                foreach ($itemsToUpdate as $itemUpdate) {
                    $id = $itemUpdate['id'];
                    unset($itemUpdate['id']);
                    StockOpnameItem::where('id', $id)->update($itemUpdate);
                }
            }
            
            // Batch insert new items
            if (!empty($itemsToInsert)) {
                StockOpnameItem::insert($itemsToInsert);
            }

            // Save approvers if provided (only if status is still DRAFT)
            if ($stockOpname->status === 'DRAFT' && isset($validated['approvers']) && is_array($validated['approvers']) && count($validated['approvers']) > 0) {
                // Delete existing approval flows if any
                $stockOpname->approvalFlows()->delete();

                // Create approval flows
                foreach ($validated['approvers'] as $index => $approverId) {
                    StockOpnameApprovalFlow::create([
                        'stock_opname_id' => $stockOpname->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1, // Level 1 = terendah, level terakhir = tertinggi
                        'status' => 'PENDING',
                    ]);
                }
            }

            DB::commit();

            // If autosave request, return JSON
            if ($request->has('autosave') && $request->autosave) {
                return response()->json([
                    'success' => true,
                    'id' => $stockOpname->id,
                    'message' => 'Draft tersimpan'
                ]);
            }

            return redirect()->route('stock-opnames.show', $stockOpname->id)
                           ->with('success', 'Stock Opname berhasil di-update!');
        } catch (\Exception $e) {
            DB::rollBack();
            
            // If autosave request, return JSON error
            if ($request->has('autosave') && $request->autosave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menyimpan draft: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withErrors(['error' => 'Gagal update stock opname: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified stock opname from storage
     */
    public function destroy(Request $request, $id)
    {
        try {
            $stockOpname = StockOpname::find($id);
            
            if (!$stockOpname) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Stock opname tidak ditemukan.'
                    ], 404);
                }
                return back()->withErrors(['error' => 'Stock opname tidak ditemukan.']);
            }
            
            $user = auth()->user();

            // Validate access
            if ($user->id_outlet != 1 && $user->id_outlet != $stockOpname->outlet_id) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses untuk stock opname ini.'
                    ], 403);
                }
                return back()->withErrors(['error' => 'Anda tidak memiliki akses untuk stock opname ini.']);
            }

            // Only allow deletion if status is DRAFT
            if ($stockOpname->status !== 'DRAFT') {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Stock opname hanya dapat dihapus jika status adalah DRAFT.'
                    ], 422);
                }
                return back()->withErrors(['error' => 'Stock opname hanya dapat dihapus jika status adalah DRAFT.']);
            }

            DB::beginTransaction();

            // Delete items (cascade will handle this, but we do it explicitly)
            $stockOpname->items()->delete();
            
            // Delete approval flows if any
            $stockOpname->approvalFlows()->delete();

            // Delete stock opname
            $stockOpname->delete();

            DB::commit();

            // Return JSON if AJAX request, otherwise redirect
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Stock Opname berhasil dihapus!'
                ]);
            }

            return redirect()->route('stock-opnames.index')
                           ->with('success', 'Stock Opname berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Return JSON if AJAX request, otherwise redirect with error
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghapus stock opname: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withErrors(['error' => 'Gagal menghapus stock opname: ' . $e->getMessage()]);
        }
    }

    /**
     * Submit stock opname for approval
     */
    public function submitForApproval(Request $request, $id)
    {
        $stockOpname = StockOpname::findOrFail($id);
        $user = auth()->user();

        // Validate access
        if ($user->id_outlet != 1 && $user->id_outlet != $stockOpname->outlet_id) {
            return back()->withErrors(['error' => 'Anda tidak memiliki akses untuk stock opname ini.']);
        }

        if (!$stockOpname->canBeSubmitted()) {
            return back()->withErrors(['error' => 'Stock opname tidak dapat di-submit. Pastikan status adalah DRAFT atau SAVED dan ada items.']);
        }

        $validated = $request->validate([
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            // Delete existing approval flows if any (in case of resubmit)
            $stockOpname->approvalFlows()->delete();

            // Create approval flows
            foreach ($validated['approvers'] as $index => $approverId) {
                StockOpnameApprovalFlow::create([
                    'stock_opname_id' => $stockOpname->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1, // Level 1 = terendah, level terakhir = tertinggi
                    'status' => 'PENDING',
                ]);
            }

            // Update status
            $stockOpname->update(['status' => 'SUBMITTED']);

            // Send notification to first approver
            $this->sendNotificationToNextApprover($stockOpname);

            DB::commit();

            return redirect()->route('stock-opnames.show', $stockOpname->id)
                           ->with('success', 'Stock opname berhasil di-submit untuk approval!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal submit approval: ' . $e->getMessage()]);
        }
    }

    /**
     * Approve or reject stock opname
     */
    public function approve(Request $request, $id)
    {
        $stockOpname = StockOpname::with('approvalFlows')->findOrFail($id);
        $user = auth()->user();

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'comments' => 'nullable|string',
        ]);

        // Find pending flow for current user
        $pendingFlow = $stockOpname->approvalFlows()
            ->where('status', 'PENDING')
            ->where('approver_id', $user->id)
            ->orderBy('approval_level')
            ->first();

        // Superadmin can approve any
        if (!$pendingFlow && $user->id_role === '5af56935b011a') {
            $pendingFlow = $stockOpname->approvalFlows()
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
        }

        if (!$pendingFlow) {
            return back()->withErrors(['error' => 'Anda tidak memiliki approval yang pending.']);
        }

        try {
            DB::beginTransaction();

            if ($validated['action'] === 'approve') {
                $pendingFlow->approve($validated['comments'] ?? null);

                // Check if all approvals are done
                $allApproved = $stockOpname->approvalFlows()
                    ->where('status', 'PENDING')
                    ->count() === 0;

                if ($allApproved) {
                    $stockOpname->update(['status' => 'APPROVED']);
                } else {
                    // Send notification to next approver
                    $this->sendNotificationToNextApprover($stockOpname);
                }
            } else {
                $pendingFlow->reject($validated['comments'] ?? null);
                $stockOpname->update(['status' => 'REJECTED']);
            }

            DB::commit();

            $message = $validated['action'] === 'approve' 
                ? 'Stock opname berhasil di-approve!' 
                : 'Stock opname telah di-reject.';

            return redirect()->route('stock-opnames.show', $stockOpname->id)
                           ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal proses approval: ' . $e->getMessage()]);
        }
    }

    /**
     * Process approved stock opname (update inventory)
     */
    public function process($id)
    {
        $stockOpname = StockOpname::with('items.inventoryItem')->findOrFail($id);
        $user = auth()->user();

        if (!$stockOpname->canBeProcessed()) {
            return back()->withErrors(['error' => 'Stock opname belum di-approve.']);
        }

        try {
            DB::beginTransaction();

            foreach ($stockOpname->items as $item) {
                if (!$item->hasDifference()) {
                    continue; // Skip items without difference
                }

                $inventoryItemId = $item->inventory_item_id;
                $outletId = $stockOpname->outlet_id;
                $warehouseOutletId = $stockOpname->warehouse_outlet_id;

                // Get current stock
                $stock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId)
                    ->first();

                if (!$stock) {
                    continue;
                }

                $mac = $item->mac_before;
                $qtyDiffSmall = $item->qty_diff_small;
                $qtyDiffMedium = $item->qty_diff_medium;
                $qtyDiffLarge = $item->qty_diff_large;

                // Calculate new quantities
                $newQtySmall = $stock->qty_small + $qtyDiffSmall;
                $newQtyMedium = $stock->qty_medium + $qtyDiffMedium;
                $newQtyLarge = $stock->qty_large + $qtyDiffLarge;

                // Calculate new value
                // For positive diff: add value using MAC
                // For negative diff: subtract value using MAC
                $valueAdjustment = $item->value_adjustment;
                $newValue = $stock->value + $valueAdjustment;

                // Update stock
                DB::table('outlet_food_inventory_stocks')
                    ->where('id', $stock->id)
                    ->update([
                        'qty_small' => $newQtySmall,
                        'qty_medium' => $newQtyMedium,
                        'qty_large' => $newQtyLarge,
                        'value' => $newValue,
                        // MAC tidak berubah (sesuai rekomendasi)
                        'updated_at' => now(),
                    ]);

                // Get last card for saldo calculation
                $lastCard = DB::table('outlet_food_inventory_cards')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId)
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->first();

                // Calculate new saldo
                if ($lastCard) {
                    $saldoQtySmall = $lastCard->saldo_qty_small + $qtyDiffSmall;
                    $saldoQtyMedium = $lastCard->saldo_qty_medium + $qtyDiffMedium;
                    $saldoQtyLarge = $lastCard->saldo_qty_large + $qtyDiffLarge;
                    $saldoValue = $lastCard->saldo_value + $valueAdjustment;
                } else {
                    $saldoQtySmall = $newQtySmall;
                    $saldoQtyMedium = $newQtyMedium;
                    $saldoQtyLarge = $newQtyLarge;
                    $saldoValue = $newValue;
                }

                // Insert stock card
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $outletId,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'date' => $stockOpname->opname_date,
                    'reference_type' => 'stock_opname',
                    'reference_id' => $stockOpname->id,
                    'in_qty_small' => $qtyDiffSmall > 0 ? $qtyDiffSmall : 0,
                    'in_qty_medium' => $qtyDiffMedium > 0 ? $qtyDiffMedium : 0,
                    'in_qty_large' => $qtyDiffLarge > 0 ? $qtyDiffLarge : 0,
                    'out_qty_small' => $qtyDiffSmall < 0 ? abs($qtyDiffSmall) : 0,
                    'out_qty_medium' => $qtyDiffMedium < 0 ? abs($qtyDiffMedium) : 0,
                    'out_qty_large' => $qtyDiffLarge < 0 ? abs($qtyDiffLarge) : 0,
                    'cost_per_small' => $mac,
                    'cost_per_medium' => $stock->last_cost_medium ?? 0,
                    'cost_per_large' => $stock->last_cost_large ?? 0,
                    'value_in' => $valueAdjustment > 0 ? $valueAdjustment : 0,
                    'value_out' => $valueAdjustment < 0 ? abs($valueAdjustment) : 0,
                    'saldo_qty_small' => $saldoQtySmall,
                    'saldo_qty_medium' => $saldoQtyMedium,
                    'saldo_qty_large' => $saldoQtyLarge,
                    'saldo_value' => $saldoValue,
                    'description' => 'Stock Opname: ' . ($item->reason ?? 'Koreksi fisik'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update cost history if MAC changed (though in our case MAC doesn't change)
                // But we still record it for audit trail
                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $outletId,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'date' => $stockOpname->opname_date,
                    'old_cost' => $mac,
                    'new_cost' => $mac, // MAC tidak berubah
                    'mac' => $mac,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Insert adjustment record to new table
                StockOpnameAdjustment::create([
                    'stock_opname_id' => $stockOpname->id,
                    'stock_opname_item_id' => $item->id,
                    'inventory_item_id' => $inventoryItemId,
                    'outlet_id' => $outletId,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'qty_diff_small' => $qtyDiffSmall,
                    'qty_diff_medium' => $qtyDiffMedium,
                    'qty_diff_large' => $qtyDiffLarge,
                    'reason' => $item->reason,
                    'mac_before' => $item->mac_before,
                    'mac_after' => $item->mac_after,
                    'value_adjustment' => $valueAdjustment,
                    'processed_at' => now(),
                    'processed_by' => $user->id,
                ]);
            }

            // Update status to completed
            $stockOpname->update(['status' => 'COMPLETED']);

            DB::commit();

            return redirect()->route('stock-opnames.show', $stockOpname->id)
                           ->with('success', 'Stock opname berhasil di-process!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal process stock opname: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate opname number
     */
    private function generateOpnameNumber()
    {
        $date = now()->format('Ymd');
        $lastOpname = StockOpname::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOpname) {
            $lastNumber = (int) substr($lastOpname->opname_number, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return 'SO-' . $date . '-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Send notification to next approver
     */
    private function sendNotificationToNextApprover($stockOpname)
    {
        $nextFlow = $stockOpname->approvalFlows()
            ->where('status', 'PENDING')
            ->orderBy('approval_level')
            ->first();

        if ($nextFlow) {
            // Create notification
            NotificationService::insert([
                'user_id' => $nextFlow->approver_id,
                'type' => 'stock_opname_approval_request',
                'message' => 'Stock Opname ' . $stockOpname->opname_number . ' membutuhkan approval Anda.',
                'url' => route('stock-opnames.show', $stockOpname->id),
                'is_read' => 0,
            ]);
        }
    }

    /**
     * Get inventory items via API (for AJAX)
     */
    public function getItems(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
        ]);

        $items = $this->getInventoryItems($validated['outlet_id'], $validated['warehouse_outlet_id']);

        return response()->json($items);
    }

    /**
     * Download template import Stock Opname (Info + Items, tanpa MAC)
     */
    public function downloadTemplate()
    {
        return Excel::download(
            new StockOpnameImportTemplateExport,
            'template_stock_opname.xlsx'
        );
    }

    /**
     * Preview import: parse file, resolve item & MAC dari sistem, return tabel preview (tanpa insert)
     */
    public function previewImport(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);

        try {
            $parsed = $this->parseStockOpnameFile($request->file('file'));
            if (!empty($parsed['info_errors'])) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $parsed['info_errors']),
                    'info_errors' => $parsed['info_errors'],
                ], 422);
            }

            $rows = [];
            foreach ($parsed['item_rows'] as $r) {
                $qtySmall = (float) ($r['qty_physical_small'] ?? 0);
                $qtyMedium = (float) ($r['qty_physical_medium'] ?? 0);
                $qtyLarge = (float) ($r['qty_physical_large'] ?? 0);
                $sysSmall = (float) ($r['qty_system_small'] ?? 0);
                $sysMedium = (float) ($r['qty_system_medium'] ?? 0);
                $sysLarge = (float) ($r['qty_system_large'] ?? 0);
                $diffSmall = $qtySmall - $sysSmall;
                $mac = (float) ($r['mac'] ?? 0);

                $rows[] = [
                    'no' => $r['no'] ?? '',
                    'kategori' => $r['kategori'] ?? '',
                    'nama_item' => $r['nama_item'] ?? '',
                    'qty_terkecil' => $r['qty_terkecil'] ?? '',
                    'unit_terkecil' => $r['unit_terkecil'] ?? '',
                    'alasan' => $r['alasan'] ?? '',
                    'qty_system_small' => $sysSmall,
                    'mac' => $mac,
                    'qty_physical_small' => $qtySmall,
                    'qty_physical_medium' => $qtyMedium,
                    'qty_physical_large' => $qtyLarge,
                    'selisih_small' => $diffSmall,
                    'status' => $r['error'] ? 'error' : 'ok',
                    'error' => $r['error'] ?? null,
                ];
            }

            return response()->json([
                'success' => true,
                'info' => [
                    'outlet' => $parsed['outlet_name'] ?? '',
                    'warehouse_outlet' => $parsed['warehouse_outlet_name'] ?? '',
                    'tanggal_opname' => $parsed['opname_date'] ?? '',
                    'catatan' => $parsed['notes'] ?? '',
                ],
                'rows' => $rows,
                'total' => count($rows),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Import Stock Opname dari Excel. MAC diisi otomatis dari sistem (last_cost_small).
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);

        $user = auth()->user();

        try {
            $parsed = $this->parseStockOpnameFile($request->file('file'));
            if (!empty($parsed['info_errors'])) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $parsed['info_errors']),
                ], 422);
            }

            $outletId = (int) $parsed['outlet_id'];
            $warehouseOutletId = (int) $parsed['warehouse_outlet_id'];
            if ($user->id_outlet != 1 && $user->id_outlet != $outletId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk outlet ini.',
                ], 403);
            }

            $validItems = [];
            $errors = [];
            foreach ($parsed['item_rows'] as $r) {
                if (!empty($r['error'])) {
                    $errors[] = ['row' => $r['excel_row'] ?? 0, 'error' => $r['error']];
                    continue;
                }
                $validItems[] = $r;
            }

            if (empty($validItems)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada baris item yang valid. ' . (count($errors) ? 'Error: ' . ($errors[0]['error'] ?? '') : ''),
                    'errors' => $errors,
                ], 422);
            }

            DB::beginTransaction();

            $opnameNumber = $this->generateOpnameNumber();
            $stockOpname = StockOpname::create([
                'opname_number' => $opnameNumber,
                'outlet_id' => $outletId,
                'warehouse_outlet_id' => $warehouseOutletId,
                'opname_date' => $parsed['opname_date'],
                'status' => 'SAVED',
                'notes' => $parsed['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $itemsToInsert = [];
            foreach ($validItems as $r) {
                $qtyPhysicalSmall = (float) ($r['qty_physical_small'] ?? 0);
                $qtyPhysicalMedium = (float) ($r['qty_physical_medium'] ?? 0);
                $qtyPhysicalLarge = (float) ($r['qty_physical_large'] ?? 0);
                $qtySystemSmall = (float) ($r['qty_system_small'] ?? 0);
                $qtySystemMedium = (float) ($r['qty_system_medium'] ?? 0);
                $qtySystemLarge = (float) ($r['qty_system_large'] ?? 0);
                $mac = (float) ($r['mac'] ?? 0);
                $qtyDiffSmall = $qtyPhysicalSmall - $qtySystemSmall;
                $qtyDiffMedium = $qtyPhysicalMedium - $qtySystemMedium;
                $qtyDiffLarge = $qtyPhysicalLarge - $qtySystemLarge;
                $valueAdjustment = $qtyDiffSmall * $mac;

                $itemsToInsert[] = [
                    'stock_opname_id' => $stockOpname->id,
                    'inventory_item_id' => $r['inventory_item_id'],
                    'qty_system_small' => $qtySystemSmall,
                    'qty_system_medium' => $qtySystemMedium,
                    'qty_system_large' => $qtySystemLarge,
                    'qty_physical_small' => $qtyPhysicalSmall,
                    'qty_physical_medium' => $qtyPhysicalMedium,
                    'qty_physical_large' => $qtyPhysicalLarge,
                    'qty_diff_small' => $qtyDiffSmall,
                    'qty_diff_medium' => $qtyDiffMedium,
                    'qty_diff_large' => $qtyDiffLarge,
                    'reason' => $r['alasan'] ?? null,
                    'mac_before' => $mac,
                    'mac_after' => $mac,
                    'value_adjustment' => $valueAdjustment,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            StockOpnameItem::insert($itemsToInsert);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock Opname berhasil di-import. ' . count($validItems) . ' item tersimpan.',
                'id' => $stockOpname->id,
                'success_count' => count($validItems),
                'errors' => $errors,
                'error_count' => count($errors),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Parse Excel Stock Opname (Info + Items). MAC diambil dari outlet_food_inventory_stocks.last_cost_small.
     * Returns: info_errors, outlet_id, warehouse_outlet_id, outlet_name, warehouse_outlet_name, opname_date, notes, item_rows[]
     */
    private function parseStockOpnameFile($file): array
    {
        $out = [
            'info_errors' => [],
            'outlet_id' => null,
            'warehouse_outlet_id' => null,
            'outlet_name' => null,
            'warehouse_outlet_name' => null,
            'opname_date' => null,
            'notes' => null,
            'item_rows' => [],
        ];

        $sheets = Excel::toArray(new StockOpnameImport, $file);

        $infoRows = $sheets['Info'] ?? [];
        $info = [];
        foreach ($infoRows as $row) {
            $key = $row['key'] ?? $row['Key'] ?? $row[0] ?? null;
            $val = $row['value'] ?? $row['Value'] ?? $row[1] ?? null;
            if ($key !== null && $key !== '') {
                $info[trim((string) $key)] = $val !== null ? trim((string) $val) : '';
            }
        }

        $outletName = $info['Outlet'] ?? '';
        $whName = $info['Warehouse Outlet'] ?? '';
        $opnameDate = $info['Tanggal Opname'] ?? '';
        $notes = $info['Catatan'] ?? '';

        if ($outletName === '') {
            $out['info_errors'][] = 'Outlet wajib diisi di sheet Info.';
        }
        if ($whName === '') {
            $out['info_errors'][] = 'Warehouse Outlet wajib diisi di sheet Info.';
        }
        if ($opnameDate === '') {
            $out['info_errors'][] = 'Tanggal Opname wajib diisi di sheet Info.';
        } else {
            try {
                $d = \Carbon\Carbon::parse($opnameDate);
                $opnameDate = $d->format('Y-m-d');
            } catch (\Throwable $e) {
                $out['info_errors'][] = 'Tanggal Opname tidak valid.';
            }
        }

        if (!empty($out['info_errors'])) {
            $out['opname_date'] = $opnameDate ?: null;
            $out['notes'] = $notes;
            return $out;
        }

        $outlet = DB::table('tbl_data_outlet')->where('nama_outlet', $outletName)->where('status', 'A')->first();
        if (!$outlet) {
            $out['info_errors'][] = "Outlet '{$outletName}' tidak ditemukan.";
            return $out;
        }
        $outletId = (int) $outlet->id_outlet;

        $wh = DB::table('warehouse_outlets')->where('name', $whName)->where('outlet_id', $outletId)->where('status', 'active')->first();
        if (!$wh) {
            $out['info_errors'][] = "Warehouse Outlet '{$whName}' tidak ditemukan untuk outlet tersebut.";
            return $out;
        }
        $warehouseOutletId = (int) $wh->id;

        $out['outlet_id'] = $outletId;
        $out['warehouse_outlet_id'] = $warehouseOutletId;
        $out['outlet_name'] = $outletName;
        $out['warehouse_outlet_name'] = $whName;
        $out['opname_date'] = $opnameDate;
        $out['notes'] = $notes;

        $itemRows = $sheets['Items'] ?? [];
        $smallConv = 1;
        $mediumConv = 1;

        foreach ($itemRows as $idx => $row) {
            $no = $row['no'] ?? $row['No'] ?? ($idx + 1);
            $kategori = trim((string) ($row['kategori'] ?? $row['Kategori'] ?? ''));
            $namaItem = trim((string) ($row['nama_item'] ?? $row['Nama Item'] ?? ''));
            $qtyTerkecil = $row['qty_terkecil'] ?? $row['Qty Terkecil'] ?? '';
            $unitTerkecil = trim((string) ($row['unit_terkecil'] ?? $row['Unit Terkecil'] ?? ''));
            $alasan = trim((string) ($row['alasan'] ?? $row['Alasan'] ?? ''));
            if ($namaItem === '' && $qtyTerkecil === '' && $kategori === '') {
                continue;
            }

            $excelRow = $idx + 2;
            if ($namaItem === '') {
                $out['item_rows'][] = [
                    'excel_row' => $excelRow,
                    'no' => $no, 'kategori' => $kategori, 'nama_item' => $namaItem,
                    'qty_terkecil' => $qtyTerkecil, 'unit_terkecil' => $unitTerkecil, 'alasan' => $alasan,
                    'error' => 'Nama item wajib diisi.',
                ] + array_fill_keys(['inventory_item_id','qty_system_small','qty_system_medium','qty_system_large','mac','qty_physical_small','qty_physical_medium','qty_physical_large','small_conversion_qty','medium_conversion_qty'], null);
                continue;
            }

            $qtyVal = is_numeric($qtyTerkecil) ? (float) $qtyTerkecil : null;
            if ($qtyVal === null || $qtyVal < 0) {
                $out['item_rows'][] = [
                    'excel_row' => $excelRow,
                    'no' => $no, 'kategori' => $kategori, 'nama_item' => $namaItem,
                    'qty_terkecil' => $qtyTerkecil, 'unit_terkecil' => $unitTerkecil, 'alasan' => $alasan,
                    'error' => 'Qty Terkecil harus angka >= 0.',
                ] + array_fill_keys(['inventory_item_id','qty_system_small','qty_system_medium','qty_system_large','mac','qty_physical_small','qty_physical_medium','qty_physical_large','small_conversion_qty','medium_conversion_qty'], null);
                continue;
            }

            $rec = DB::table('outlet_food_inventory_stocks as s')
                ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
                ->join('items as i', 'fi.item_id', '=', 'i.id')
                ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
                ->where('s.id_outlet', $outletId)
                ->where('s.warehouse_outlet_id', $warehouseOutletId)
                ->where('i.name', $namaItem)
                ->when($kategori !== '', function ($q) use ($kategori) {
                    $q->where('c.name', $kategori);
                })
                ->select(
                    'fi.id as inventory_item_id',
                    's.qty_small as qty_system_small', 's.qty_medium as qty_system_medium', 's.qty_large as qty_system_large',
                    's.last_cost_small as mac',
                    'i.small_conversion_qty', 'i.medium_conversion_qty'
                )
                ->first();

            if (!$rec) {
                $out['item_rows'][] = [
                    'excel_row' => $excelRow,
                    'no' => $no, 'kategori' => $kategori, 'nama_item' => $namaItem,
                    'qty_terkecil' => $qtyTerkecil, 'unit_terkecil' => $unitTerkecil, 'alasan' => $alasan,
                    'error' => "Item '{$namaItem}'" . ($kategori ? " (kategori: {$kategori})" : '') . ' tidak ditemukan di stok outlet/warehouse ini.',
                ] + array_fill_keys(['inventory_item_id','qty_system_small','qty_system_medium','qty_system_large','mac','qty_physical_small','qty_physical_medium','qty_physical_large','small_conversion_qty','medium_conversion_qty'], null);
                continue;
            }

            $smallConv = (float) ($rec->small_conversion_qty ?? 1) ?: 1;
            $mediumConv = (float) ($rec->medium_conversion_qty ?? 1) ?: 1;
            $qtyPhysicalSmall = $qtyVal;
            $qtyPhysicalMedium = $smallConv > 0 ? $qtyPhysicalSmall / $smallConv : 0;
            $qtyPhysicalLarge = ($smallConv > 0 && $mediumConv > 0) ? $qtyPhysicalSmall / ($smallConv * $mediumConv) : 0;

            $qtySystemSmall = (float) ($rec->qty_system_small ?? 0);
            $qtySystemMedium = (float) ($rec->qty_system_medium ?? 0);
            $qtySystemLarge = (float) ($rec->qty_system_large ?? 0);
            $diffSmall = $qtyPhysicalSmall - $qtySystemSmall;
            $diffMedium = $qtyPhysicalMedium - $qtySystemMedium;
            $diffLarge = $qtyPhysicalLarge - $qtySystemLarge;
            $hasDiff = abs($diffSmall) > 0.01 || abs($diffMedium) > 0.01 || abs($diffLarge) > 0.01;

            if ($hasDiff && $alasan === '') {
                $out['item_rows'][] = [
                    'excel_row' => $excelRow,
                    'no' => $no, 'kategori' => $kategori, 'nama_item' => $namaItem,
                    'qty_terkecil' => $qtyTerkecil, 'unit_terkecil' => $unitTerkecil, 'alasan' => $alasan,
                    'inventory_item_id' => $rec->inventory_item_id,
                    'qty_system_small' => $qtySystemSmall, 'qty_system_medium' => $qtySystemMedium, 'qty_system_large' => $qtySystemLarge,
                    'mac' => (float) ($rec->mac ?? 0),
                    'qty_physical_small' => $qtyPhysicalSmall, 'qty_physical_medium' => $qtyPhysicalMedium, 'qty_physical_large' => $qtyPhysicalLarge,
                    'error' => 'Ada selisih qty. Kolom Alasan wajib diisi.',
                ];
                continue;
            }

            $out['item_rows'][] = [
                'excel_row' => $excelRow,
                'no' => $no, 'kategori' => $kategori, 'nama_item' => $namaItem,
                'qty_terkecil' => $qtyTerkecil, 'unit_terkecil' => $unitTerkecil, 'alasan' => $alasan,
                'inventory_item_id' => $rec->inventory_item_id,
                'qty_system_small' => $qtySystemSmall, 'qty_system_medium' => $qtySystemMedium, 'qty_system_large' => $qtySystemLarge,
                'mac' => (float) ($rec->mac ?? 0),
                'qty_physical_small' => $qtyPhysicalSmall, 'qty_physical_medium' => $qtyPhysicalMedium, 'qty_physical_large' => $qtyPhysicalLarge,
                'error' => null,
            ];
        }

        return $out;
    }

    /**
     * Get approvers for search (for AJAX)
     * Menampilkan semua user aktif tanpa filter outlet
     */
    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');
        
        $usersQuery = DB::table('users')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where('users.status', 'A');
        
        // Tidak ada filter outlet - tampilkan semua user aktif
        if ($search) {
            $usersQuery->where(function($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            });
        }
        
        $users = $usersQuery->select(
                'users.id', 
                'users.nama_lengkap as name', 
                'users.email',
                'tbl_data_jabatan.nama_jabatan as jabatan'
            )
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();
        
        return response()->json(['success' => true, 'users' => $users]);
    }

    /**
     * Get pending approvals for current user
     */
    public function getPendingApprovals()
    {
        try {
            $user = auth()->user();
            $userId = $user->id;

            $query = StockOpname::with([
                'outlet',
                'warehouseOutlet',
                'creator',
                'approvalFlows.approver'
            ])
            ->where('status', 'SUBMITTED');

            // Filter by outlet if user is not superadmin
            if ($user->id_outlet != 1) {
                $query->where('outlet_id', $user->id_outlet);
            }

            // Get stock opnames with pending approval flows
            if ($user->id_role === '5af56935b011a') {
                // Superadmin can see all pending approvals
                $pendingApprovals = $query->whereHas('approvalFlows', function($q) {
                    $q->where('status', 'PENDING');
                })->get();
            } else {
                // Regular users only see approvals assigned to them
                $pendingApprovals = $query->whereHas('approvalFlows', function($q) use ($userId) {
                    $q->where('approver_id', $userId)
                      ->where('status', 'PENDING');
                })->get();
            }

            // Filter to only show approvals where user is the next approver
            // and all previous approval levels have been approved
            $filteredApprovals = $pendingApprovals->filter(function($opname) use ($user) {
                // Get all approval flows sorted by level
                $allFlows = $opname->approvalFlows->sortBy('approval_level');
                $pendingFlows = $allFlows->where('status', 'PENDING');
                $nextApprover = $pendingFlows->first();
                
                if (!$nextApprover) {
                    return false;
                }

                // Check if all previous approval levels have been approved
                $nextApprovalLevel = $nextApprover->approval_level;
                $previousFlows = $allFlows->where('approval_level', '<', $nextApprovalLevel);
                $allPreviousApproved = $previousFlows->every(function($flow) {
                    return $flow->status === 'APPROVED';
                });

                // If previous levels are not all approved, don't show this approval
                if (!$allPreviousApproved) {
                    return false;
                }

                // Check if user is the next approver
                if ($user->id_role === '5af56935b011a') {
                    return true; // Superadmin can approve any (but still need previous levels approved)
                }

                return $nextApprover->approver_id == $user->id;
            });

            // Map to include approver name
            $mappedApprovals = $filteredApprovals->map(function($opname) use ($user) {
                $pendingFlows = $opname->approvalFlows->where('status', 'PENDING')->sortBy('approval_level');
                $nextApprover = $pendingFlows->first();
                
                return [
                    'id' => $opname->id,
                    'opname_number' => $opname->opname_number,
                    'outlet' => $opname->outlet ? ['nama_outlet' => $opname->outlet->nama_outlet] : null,
                    'warehouse_outlet' => $opname->warehouseOutlet ? ['name' => $opname->warehouseOutlet->name] : null,
                    'opname_date' => $opname->opname_date,
                    'creator' => $opname->creator ? ['nama_lengkap' => $opname->creator->nama_lengkap] : null,
                    'approver_name' => $nextApprover && $nextApprover->approver ? $nextApprover->approver->nama_lengkap : null,
                    'approval_level' => $nextApprover ? $nextApprover->approval_level : null,
                ];
            });

            return response()->json([
                'success' => true,
                'stock_opnames' => $mappedApprovals->values()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting pending Stock Opname approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get pending approvals'
            ], 500);
        }
    }
}

