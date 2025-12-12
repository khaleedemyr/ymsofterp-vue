<?php

namespace App\Http\Controllers;

use App\Models\WarehouseStockOpname;
use App\Models\WarehouseStockOpnameItem;
use App\Models\WarehouseStockOpnameApprovalFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use App\Services\NotificationService;

class WarehouseStockOpnameController extends Controller
{
    /**
     * Display a listing of warehouse stock opnames
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $search = $request->get('search', '');
        $status = $request->get('status', 'all');
        $warehouseId = $request->get('warehouse_id', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $perPage = $request->get('per_page', 15);

        $query = WarehouseStockOpname::with([
            'warehouse',
            'warehouseDivision',
            'creator',
            'approvalFlows.approver'
        ]);

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

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($dateFrom) {
            $query->whereDate('opname_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('opname_date', '<=', $dateTo);
        }

        $stockOpnames = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Get warehouses for filter
        $warehouses = DB::table('warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('WarehouseStockOpname/Index', [
            'stockOpnames' => $stockOpnames,
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'warehouse_id' => $warehouseId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Show the form for creating a new warehouse stock opname
     */
    public function create(Request $request)
    {
        $user = auth()->user();
        $warehouseId = $request->get('warehouse_id');
        $warehouseDivisionId = $request->get('warehouse_division_id');

        // Get warehouses
        $warehouses = DB::table('warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Get warehouse divisions
        $warehouseDivisions = DB::table('warehouse_division')
            ->select('id', 'name', 'warehouse_id')
            ->orderBy('name')
            ->get();

        // Get inventory items if warehouse and warehouse division selected
        $items = [];
        if ($warehouseId && $warehouseDivisionId) {
            $items = $this->getInventoryItems($warehouseId, $warehouseDivisionId);
        }

        return Inertia::render('WarehouseStockOpname/Create', [
            'warehouses' => $warehouses,
            'warehouseDivisions' => $warehouseDivisions,
            'items' => $items,
            'selectedWarehouseId' => $warehouseId,
            'selectedWarehouseDivisionId' => $warehouseDivisionId,
        ]);
    }

    /**
     * Get inventory items for selected warehouse and warehouse division
     */
    public function getInventoryItems($warehouseId, $warehouseDivisionId)
    {
        $query = DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->where('s.warehouse_id', $warehouseId)
            ->where(function($q) {
                $q->where('s.qty_small', '>', 0)
                  ->orWhere('s.qty_medium', '>', 0)
                  ->orWhere('s.qty_large', '>', 0);
            });

        // Filter by warehouse division if provided
        if ($warehouseDivisionId) {
            $query->where('i.warehouse_division_id', $warehouseDivisionId);
        }

        $result = $query->select(
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

        return $result;
    }

    /**
     * Store a newly created warehouse stock opname in storage
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer',
            'warehouse_division_id' => 'nullable|integer',
            'opname_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|integer',
            'items.*.qty_physical_small' => 'nullable|numeric|min:0',
            'items.*.qty_physical_medium' => 'nullable|numeric|min:0',
            'items.*.qty_physical_large' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string',
        ]);

        $user = auth()->user();

        try {
            DB::beginTransaction();

            // Generate opname number
            $opnameNumber = $this->generateOpnameNumber();

            // Create stock opname
            $stockOpname = WarehouseStockOpname::create([
                'opname_number' => $opnameNumber,
                'warehouse_id' => $validated['warehouse_id'],
                'warehouse_division_id' => $validated['warehouse_division_id'] ?? null,
                'opname_date' => $validated['opname_date'],
                'status' => 'DRAFT',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            // Create items
            foreach ($validated['items'] as $itemData) {
                // Get system qty from inventory stocks
                $stock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $itemData['inventory_item_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();

                if (!$stock) {
                    continue; // Skip if stock not found
                }

                $qtySystemSmall = $stock->qty_small ?? 0;
                $qtySystemMedium = $stock->qty_medium ?? 0;
                $qtySystemLarge = $stock->qty_large ?? 0;
                $mac = $stock->last_cost_small ?? 0;

                // If physical qty not provided, use system qty (tombol "=")
                $qtyPhysicalSmall = $itemData['qty_physical_small'] ?? $qtySystemSmall;
                $qtyPhysicalMedium = $itemData['qty_physical_medium'] ?? $qtySystemMedium;
                $qtyPhysicalLarge = $itemData['qty_physical_large'] ?? $qtySystemLarge;

                // Calculate difference
                $qtyDiffSmall = $qtyPhysicalSmall - $qtySystemSmall;
                $qtyDiffMedium = $qtyPhysicalMedium - $qtySystemMedium;
                $qtyDiffLarge = $qtyPhysicalLarge - $qtySystemLarge;

                // Calculate value adjustment (using MAC for small unit)
                $valueAdjustment = $qtyDiffSmall * $mac;

                WarehouseStockOpnameItem::create([
                    'stock_opname_id' => $stockOpname->id,
                    'inventory_item_id' => $itemData['inventory_item_id'],
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
                ]);
            }

            DB::commit();

            return redirect()->route('warehouse-stock-opnames.show', $stockOpname->id)
                           ->with('success', 'Warehouse Stock Opname berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal membuat stock opname: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified warehouse stock opname
     */
    public function show($id)
    {
        $stockOpname = WarehouseStockOpname::with([
            'warehouse',
            'warehouseDivision',
            'creator',
            'items.inventoryItem.item',
            'approvalFlows.approver'
        ])->findOrFail($id);

        $user = auth()->user();

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
            ->leftJoin('jabatan', 'users.id_jabatan', '=', 'jabatan.id')
            ->where('users.status', 'active')
            ->select('users.id', 'users.nama_lengkap', 'jabatan.nama_jabatan')
            ->orderBy('users.nama_lengkap');

        $users = $usersQuery->get()->map(function($u) {
            return [
                'id' => $u->id,
                'nama_lengkap' => $u->nama_lengkap,
                'jabatan' => $u->nama_jabatan ? ['nama_jabatan' => $u->nama_jabatan] : null,
            ];
        });

        // Get approvers for display
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

        // Add approvers to stockOpname object for frontend
        $stockOpname->approvers = $approvers;

        return Inertia::render('WarehouseStockOpname/Show', [
            'stockOpname' => $stockOpname,
            'canApprove' => $canApprove,
            'pendingFlow' => $pendingFlow,
            'users' => $users,
        ]);
    }

    /**
     * Show the form for editing the specified warehouse stock opname
     */
    public function edit($id)
    {
        $stockOpname = WarehouseStockOpname::with([
            'warehouse',
            'warehouseDivision',
            'items.inventoryItem.item'
        ])->findOrFail($id);

        $user = auth()->user();

        // Only allow editing if status is DRAFT
        if ($stockOpname->status !== 'DRAFT') {
            return redirect()->route('warehouse-stock-opnames.show', $stockOpname->id)
                           ->with('error', 'Stock opname hanya dapat diedit jika status adalah DRAFT.');
        }

        // Get warehouses
        $warehouses = DB::table('warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Get warehouse divisions
        $warehouseDivisions = DB::table('warehouse_division')
            ->select('id', 'name', 'warehouse_id')
            ->orderBy('name')
            ->get();

        // Get current items with their data
        $items = [];
        if ($stockOpname->warehouse_id) {
            $items = $this->getInventoryItems($stockOpname->warehouse_id, $stockOpname->warehouse_division_id);
            
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

        return Inertia::render('WarehouseStockOpname/Edit', [
            'stockOpname' => $stockOpname,
            'warehouses' => $warehouses,
            'warehouseDivisions' => $warehouseDivisions,
            'items' => $items,
            'approvers' => $approvers,
        ]);
    }

    /**
     * Update the specified warehouse stock opname in storage
     */
    public function update(Request $request, $id)
    {
        $stockOpname = WarehouseStockOpname::findOrFail($id);
        $user = auth()->user();

        // Only allow editing if status is DRAFT
        if ($stockOpname->status !== 'DRAFT') {
            return back()->withErrors(['error' => 'Stock opname hanya dapat diedit jika status adalah DRAFT.']);
        }

        $validated = $request->validate([
            'warehouse_id' => 'required|integer',
            'warehouse_division_id' => 'nullable|integer',
            'opname_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|integer',
            'items.*.qty_physical_small' => 'nullable|numeric|min:0',
            'items.*.qty_physical_medium' => 'nullable|numeric|min:0',
            'items.*.qty_physical_large' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Update stock opname header
            $stockOpname->update([
                'warehouse_id' => $validated['warehouse_id'],
                'warehouse_division_id' => $validated['warehouse_division_id'] ?? null,
                'opname_date' => $validated['opname_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Delete existing items
            $stockOpname->items()->delete();

            // Create new items
            foreach ($validated['items'] as $itemData) {
                // Get system qty from inventory stocks
                $stock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $itemData['inventory_item_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();

                if (!$stock) {
                    continue; // Skip if stock not found
                }

                $qtySystemSmall = $stock->qty_small ?? 0;
                $qtySystemMedium = $stock->qty_medium ?? 0;
                $qtySystemLarge = $stock->qty_large ?? 0;
                $mac = $stock->last_cost_small ?? 0;

                // If physical qty not provided, use system qty (tombol "=")
                $qtyPhysicalSmall = $itemData['qty_physical_small'] ?? $qtySystemSmall;
                $qtyPhysicalMedium = $itemData['qty_physical_medium'] ?? $qtySystemMedium;
                $qtyPhysicalLarge = $itemData['qty_physical_large'] ?? $qtySystemLarge;

                // Calculate difference
                $qtyDiffSmall = $qtyPhysicalSmall - $qtySystemSmall;
                $qtyDiffMedium = $qtyPhysicalMedium - $qtySystemMedium;
                $qtyDiffLarge = $qtyPhysicalLarge - $qtySystemLarge;

                // Calculate value adjustment (using MAC for small unit)
                $valueAdjustment = $qtyDiffSmall * $mac;

                WarehouseStockOpnameItem::create([
                    'stock_opname_id' => $stockOpname->id,
                    'inventory_item_id' => $itemData['inventory_item_id'],
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
                ]);
            }

            DB::commit();

            return redirect()->route('warehouse-stock-opnames.show', $stockOpname->id)
                           ->with('success', 'Warehouse Stock Opname berhasil di-update!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal update stock opname: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified warehouse stock opname from storage
     */
    public function destroy($id)
    {
        $stockOpname = WarehouseStockOpname::findOrFail($id);
        $user = auth()->user();

        // Only allow deletion if status is DRAFT
        if ($stockOpname->status !== 'DRAFT') {
            return back()->withErrors(['error' => 'Stock opname hanya dapat dihapus jika status adalah DRAFT.']);
        }

        try {
            DB::beginTransaction();

            // Delete items (cascade will handle this, but we do it explicitly)
            $stockOpname->items()->delete();
            
            // Delete approval flows if any
            $stockOpname->approvalFlows()->delete();

            // Delete stock opname
            $stockOpname->delete();

            DB::commit();

            return redirect()->route('warehouse-stock-opnames.index')
                           ->with('success', 'Warehouse Stock Opname berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal menghapus stock opname: ' . $e->getMessage()]);
        }
    }

    /**
     * Submit warehouse stock opname for approval
     */
    public function submitForApproval(Request $request, $id)
    {
        $stockOpname = WarehouseStockOpname::findOrFail($id);
        $user = auth()->user();

        if (!$stockOpname->canBeSubmitted()) {
            return back()->withErrors(['error' => 'Stock opname tidak dapat di-submit. Pastikan status adalah DRAFT dan ada items.']);
        }

        $validated = $request->validate([
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            // Create approval flows
            foreach ($validated['approvers'] as $index => $approverId) {
                WarehouseStockOpnameApprovalFlow::create([
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

            return redirect()->route('warehouse-stock-opnames.show', $stockOpname->id)
                           ->with('success', 'Stock opname berhasil di-submit untuk approval!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal submit approval: ' . $e->getMessage()]);
        }
    }

    /**
     * Approve or reject warehouse stock opname
     */
    public function approve(Request $request, $id)
    {
        $stockOpname = WarehouseStockOpname::with('approvalFlows')->findOrFail($id);
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

            return redirect()->route('warehouse-stock-opnames.show', $stockOpname->id)
                           ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal proses approval: ' . $e->getMessage()]);
        }
    }

    /**
     * Process approved warehouse stock opname (update inventory)
     */
    public function process($id)
    {
        $stockOpname = WarehouseStockOpname::with('items.inventoryItem')->findOrFail($id);
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
                $warehouseId = $stockOpname->warehouse_id;

                // Get current stock
                $stock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $warehouseId)
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
                $valueAdjustment = $item->value_adjustment;
                $newValue = $stock->value + $valueAdjustment;

                // Update stock
                DB::table('food_inventory_stocks')
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
                $lastCard = DB::table('food_inventory_cards')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $warehouseId)
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
                DB::table('food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'warehouse_id' => $warehouseId,
                    'date' => $stockOpname->opname_date,
                    'reference_type' => 'warehouse_stock_opname',
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
                    'description' => 'Warehouse Stock Opname: ' . ($item->reason ?? 'Koreksi fisik'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update cost history
                DB::table('food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'warehouse_id' => $warehouseId,
                    'date' => $stockOpname->opname_date,
                    'old_cost' => $mac,
                    'new_cost' => $mac, // MAC tidak berubah
                    'mac' => $mac,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update status to completed
            $stockOpname->update(['status' => 'COMPLETED']);

            DB::commit();

            return redirect()->route('warehouse-stock-opnames.show', $stockOpname->id)
                           ->with('success', 'Stock opname berhasil di-process! Inventory telah di-update.');
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
        $lastOpname = WarehouseStockOpname::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOpname) {
            $lastNumber = (int) substr($lastOpname->opname_number, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return 'WSO-' . $date . '-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
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
            DB::table('notifications')->insert([
                'user_id' => $nextFlow->approver_id,
                'type' => 'warehouse_stock_opname_approval_request',
                'message' => 'Warehouse Stock Opname ' . $stockOpname->opname_number . ' membutuhkan approval Anda.',
                'url' => route('warehouse-stock-opnames.show', $stockOpname->id),
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Get inventory items via API (for AJAX)
     */
    public function getItems(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer',
            'warehouse_division_id' => 'nullable|integer',
        ]);

        $items = $this->getInventoryItems($validated['warehouse_id'], $validated['warehouse_division_id'] ?? null);

        return response()->json($items);
    }

    /**
     * Get approvers for search (for AJAX)
     */
    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');
        
        $usersQuery = DB::table('users')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where('users.status', 'A');
        
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

            $query = WarehouseStockOpname::with([
                'warehouse',
                'warehouseDivision',
                'creator',
                'approvalFlows.approver'
            ])
            ->where('status', 'SUBMITTED');

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
            $filteredApprovals = $pendingApprovals->filter(function($opname) use ($user) {
                $pendingFlows = $opname->approvalFlows->where('status', 'PENDING')->sortBy('approval_level');
                $nextApprover = $pendingFlows->first();
                
                if (!$nextApprover) {
                    return false;
                }

                // Check if user is the next approver
                if ($user->id_role === '5af56935b011a') {
                    return true; // Superadmin can approve any
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
                    'warehouse' => $opname->warehouse ? ['name' => $opname->warehouse->name] : null,
                    'warehouse_division' => $opname->warehouseDivision ? ['name' => $opname->warehouseDivision->name] : null,
                    'opname_date' => $opname->opname_date,
                    'creator' => $opname->creator ? ['nama_lengkap' => $opname->creator->nama_lengkap] : null,
                    'approver_name' => $nextApprover && $nextApprover->approver ? $nextApprover->approver->nama_lengkap : null,
                    'approval_level' => $nextApprover ? $nextApprover->approval_level : null,
                ];
            });

            return response()->json([
                'success' => true,
                'warehouse_stock_opnames' => $mappedApprovals->values()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting pending Warehouse Stock Opname approvals', [
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
