<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\AssetGoodReceive;
use App\Models\AssetGoodReceiveItem;
use App\Models\ActivityLog;
use App\Services\AssetInventoryStockService;
use App\Services\LostBreakageReplacementService;

class AssetGoodReceiveController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('asset_good_receives as gr')
            ->leftJoin('purchase_order_ops as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_outlet as oo', 'gr.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.po_id',
                'gr.owner_outlet_id',
                'gr.outlet_id',
                'gr.warehouse_outlet_id',
                'gr.receive_date',
                'gr.received_by',
                'gr.status',
                'gr.notes',
                'gr.created_at',
                'gr.updated_at',
                'po.number as po_number',
                'oo.nama_outlet as owner_outlet_name',
                'o.nama_outlet as location_outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as received_by_name'
            )
            ->addSelect(DB::raw('(SELECT COALESCE(SUM(gri.total), 0) FROM asset_good_receive_items gri WHERE gri.asset_good_receive_id = gr.id) as total'));

        if ($user->id_outlet != 1) {
            $query->where('gr.owner_outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('gr.gr_number', 'like', "%{$search}%")
                  ->orWhere('po.number', 'like', "%{$search}%");
            });
        }

        if ($request->from) {
            $query->whereDate('gr.receive_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('gr.receive_date', '<=', $request->to);
        }
        if ($request->status) {
            $query->where('gr.status', $request->status);
        }
        if ($request->owner_outlet_id) {
            $query->where('gr.owner_outlet_id', $request->owner_outlet_id);
        }
        if ($request->outlet_id) {
            $query->where('gr.outlet_id', $request->outlet_id);
        }

        $goodReceives = $query->orderByDesc('gr.created_at')->paginate(15)->withQueryString();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return inertia('AssetGoodReceive/Index', [
            'goodReceives' => $goodReceives,
            'filters' => $request->only(['search', 'from', 'to', 'status', 'outlet_id']),
            'user' => $user,
            'outlets' => $outlets,
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

        return inertia('AssetGoodReceive/Create', [
            'user' => $user,
            'outlets' => $outlets,
        ]);
    }

    public function fetchPO(Request $request)
    {
        $number = $request->query('number');
        if (!$number) {
            return response()->json(['message' => 'Nomor PO harus diisi'], 422);
        }

        $po = DB::table('purchase_order_ops')->where('number', $number)->first();
        if (!$po) {
            return response()->json(['message' => 'PO tidak ditemukan'], 404);
        }

        if ($po->source_type !== 'purchase_requisition_ops') {
            return response()->json(['message' => 'PO ini bukan dari Purchase Requisition Ops'], 422);
        }

        $pr = DB::table('purchase_requisitions')->where('id', $po->source_id)->first();
        if (!$pr || $pr->mode !== 'pr_assets') {
            return response()->json(['message' => 'PO ini bukan untuk asset (PR mode bukan pr_assets)'], 422);
        }

        $poItems = DB::table('purchase_order_ops_items as poi')
            ->leftJoin('purchase_requisition_items as pri', 'poi.pr_ops_item_id', '=', 'pri.id')
            ->where('poi.purchase_order_ops_id', $po->id)
            ->select(
                'poi.id',
                'poi.item_name',
                'poi.quantity',
                'poi.unit',
                'poi.price',
                'poi.discount_percent',
                'poi.discount_amount',
                'poi.total',
                'poi.pr_ops_item_id',
                'poi.outlet_id',
                'pri.id as pr_item_id'
            )
            ->get();

        $poItems = $poItems->map(fn ($poItem) => $this->enrichPoItemForAssetReceive($poItem));

        $supplier = DB::table('suppliers')->where('id', $po->supplier_id)->first();

        $suggestedOwnerOutletId = AssetInventoryStockService::suggestedOwnerOutletIdFromPoItems($poItems);

        return response()->json([
            'po' => $po,
            'supplier' => $supplier,
            'pr' => $pr,
            'items' => $poItems,
            'suggested_owner_outlet_id' => $suggestedOwnerOutletId,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'po_id' => 'required|integer',
            'owner_outlet_id' => 'required|integer',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'nullable|integer',
            'receive_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.po_item_id' => 'required|integer',
            'items.*.item_id' => 'required|integer',
            'items.*.unit_id' => 'required|integer',
            'items.*.qty_ordered' => 'required|numeric|min:0',
            'items.*.qty_received' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($request->warehouse_outlet_id) {
            AssetInventoryStockService::assertWarehouseBelongsToOutlet(
                (int) $request->warehouse_outlet_id,
                (int) $request->outlet_id
            );
        }

        $locationOutletId = AssetInventoryStockService::resolveLocationOutletId(
            (int) $request->outlet_id,
            $request->warehouse_outlet_id ? (int) $request->warehouse_outlet_id : null
        );

        DB::beginTransaction();
        try {
            $grNumber = AssetGoodReceive::generateNumber();

            $gr = AssetGoodReceive::create([
                'gr_number' => $grNumber,
                'po_id' => $request->po_id,
                'owner_outlet_id' => $request->owner_outlet_id,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'receive_date' => $request->receive_date,
                'received_by' => Auth::id(),
                'status' => 'draft',
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $itemData) {
                $qtyReceived = (float) $itemData['qty_received'];
                $price = (float) $itemData['price'];
                $total = $qtyReceived * $price;

                $grItem = AssetGoodReceiveItem::create([
                    'asset_good_receive_id' => $gr->id,
                    'po_item_id' => $itemData['po_item_id'],
                    'item_id' => $itemData['item_id'],
                    'unit_id' => $itemData['unit_id'],
                    'qty_ordered' => $itemData['qty_ordered'],
                    'qty_received' => $qtyReceived,
                    'price' => $price,
                    'total' => $total,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $this->processInventoryIn(
                    $itemData['item_id'],
                    $itemData['unit_id'],
                    $qtyReceived,
                    $price,
                    (int) $request->owner_outlet_id,
                    $locationOutletId,
                    $request->warehouse_outlet_id,
                    $request->receive_date,
                    $gr->id,
                    $itemData['po_item_id']
                );

                app(LostBreakageReplacementService::class)->recordReplacementFromGrItem(
                    (int) $gr->id,
                    (int) $grItem->id,
                    (int) $itemData['po_item_id'],
                    $qtyReceived,
                    (int) $itemData['unit_id'],
                    (int) $itemData['item_id']
                );
            }

            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'asset_good_receive',
                'description' => 'Create Asset Good Receive: ' . $grNumber,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode([
                    'gr_id' => $gr->id,
                    'gr_number' => $grNumber,
                    'po_id' => $request->po_id,
                    'items_count' => count($request->items),
                ]),
                'created_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('asset-good-receives.show', $gr->id)
                ->with('success', 'Asset Good Receive berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating Asset Good Receive: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'Gagal menyimpan: ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $gr = DB::table('asset_good_receives as gr')
            ->leftJoin('purchase_order_ops as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_outlet as oo', 'gr.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.*',
                'po.number as po_number',
                'po.date as po_date',
                's.name as supplier_name',
                'oo.nama_outlet as owner_outlet_name',
                'o.nama_outlet as location_outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as received_by_name'
            )
            ->where('gr.id', $id)
            ->first();

        if (!$gr) {
            abort(404, 'Asset Good Receive tidak ditemukan');
        }

        $items = DB::table('asset_good_receive_items as gri')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
            ->leftJoin('purchase_order_ops_items as poi', 'gri.po_item_id', '=', 'poi.id')
            ->select(
                'gri.*',
                'i.name as item_name',
                'u.name as unit_name',
                'poi.item_name as po_item_name',
                'poi.quantity as po_quantity',
                'poi.unit as po_unit'
            )
            ->where('gri.asset_good_receive_id', $id)
            ->get();

        $gr->items = $items;

        return inertia('AssetGoodReceive/Show', [
            'goodReceive' => $gr,
        ]);
    }

    public function edit($id)
    {
        $gr = DB::table('asset_good_receives as gr')
            ->leftJoin('purchase_order_ops as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_outlet as oo', 'gr.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.*',
                'po.number as po_number',
                'po.date as po_date',
                's.name as supplier_name',
                'oo.nama_outlet as owner_outlet_name',
                'o.nama_outlet as location_outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as received_by_name'
            )
            ->where('gr.id', $id)
            ->first();

        if (!$gr) {
            abort(404, 'Asset Good Receive tidak ditemukan');
        }

        if ($gr->status !== 'draft') {
            return redirect()->route('asset-good-receives.show', $id)
                ->withErrors(['error' => 'Hanya GR dengan status draft yang dapat diedit.']);
        }

        $items = DB::table('asset_good_receive_items as gri')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
            ->leftJoin('purchase_order_ops_items as poi', 'gri.po_item_id', '=', 'poi.id')
            ->select(
                'gri.*',
                'i.name as item_name',
                'u.name as unit_name',
                'poi.item_name as po_item_name',
                'poi.quantity as po_quantity',
                'poi.unit as po_unit'
            )
            ->where('gri.asset_good_receive_id', $id)
            ->get();

        $gr->items = $items;

        $user = auth()->user();
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return inertia('AssetGoodReceive/Edit', [
            'goodReceive' => $gr,
            'user' => $user,
            'outlets' => $outlets,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'owner_outlet_id' => 'required|integer',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'nullable|integer',
            'receive_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.po_item_id' => 'required|integer',
            'items.*.item_id' => 'required|integer',
            'items.*.unit_id' => 'required|integer',
            'items.*.qty_ordered' => 'required|numeric|min:0',
            'items.*.qty_received' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($request->warehouse_outlet_id) {
            AssetInventoryStockService::assertWarehouseBelongsToOutlet(
                (int) $request->warehouse_outlet_id,
                (int) $request->outlet_id
            );
        }

        $locationOutletId = AssetInventoryStockService::resolveLocationOutletId(
            (int) $request->outlet_id,
            $request->warehouse_outlet_id ? (int) $request->warehouse_outlet_id : null
        );

        DB::beginTransaction();
        try {
            $gr = AssetGoodReceive::findOrFail($id);

            if ($gr->status !== 'draft') {
                return back()->withErrors(['error' => 'Hanya GR dengan status draft yang dapat diedit.']);
            }

            $oldItems = AssetGoodReceiveItem::where('asset_good_receive_id', $id)->get();

            foreach ($oldItems as $oldItem) {
                $this->rollbackInventory(
                    $oldItem->item_id,
                    $oldItem->unit_id,
                    (float) $oldItem->qty_received,
                    (int) $gr->owner_outlet_id,
                    (int) $gr->outlet_id,
                    $gr->warehouse_outlet_id,
                    $gr->id
                );
            }

            AssetGoodReceiveItem::where('asset_good_receive_id', $id)->delete();

            $gr->update([
                'owner_outlet_id' => $request->owner_outlet_id,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'receive_date' => $request->receive_date,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $itemData) {
                $qtyReceived = (float) $itemData['qty_received'];
                $price = (float) $itemData['price'];
                $total = $qtyReceived * $price;

                $grItem = AssetGoodReceiveItem::create([
                    'asset_good_receive_id' => $gr->id,
                    'po_item_id' => $itemData['po_item_id'],
                    'item_id' => $itemData['item_id'],
                    'unit_id' => $itemData['unit_id'],
                    'qty_ordered' => $itemData['qty_ordered'],
                    'qty_received' => $qtyReceived,
                    'price' => $price,
                    'total' => $total,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $this->processInventoryIn(
                    $itemData['item_id'],
                    $itemData['unit_id'],
                    $qtyReceived,
                    $price,
                    (int) $request->owner_outlet_id,
                    $locationOutletId,
                    $request->warehouse_outlet_id,
                    $request->receive_date,
                    $gr->id,
                    $itemData['po_item_id']
                );

                app(LostBreakageReplacementService::class)->recordReplacementFromGrItem(
                    (int) $gr->id,
                    (int) $grItem->id,
                    (int) $itemData['po_item_id'],
                    $qtyReceived,
                    (int) $itemData['unit_id'],
                    (int) $itemData['item_id']
                );
            }

            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'asset_good_receive',
                'description' => 'Update Asset Good Receive: ' . $gr->gr_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => json_encode($oldItems),
                'new_data' => json_encode($request->items),
                'created_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('asset-good-receives.show', $id)
                ->with('success', 'Asset Good Receive berhasil diupdate.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating Asset Good Receive: ' . $e->getMessage(), [
                'gr_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'Gagal update: ' . $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $gr = AssetGoodReceive::findOrFail($id);

            if ($gr->status !== 'draft') {
                return back()->withErrors(['error' => 'Hanya GR dengan status draft yang dapat dihapus.']);
            }

            $items = AssetGoodReceiveItem::where('asset_good_receive_id', $id)->get();

            foreach ($items as $item) {
                $this->rollbackInventory(
                    $item->item_id,
                    $item->unit_id,
                    (float) $item->qty_received,
                    (int) $gr->owner_outlet_id,
                    (int) $gr->outlet_id,
                    $gr->warehouse_outlet_id,
                    $gr->id
                );
            }

            AssetGoodReceiveItem::where('asset_good_receive_id', $id)->delete();
            $gr->delete();

            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'asset_good_receive',
                'description' => 'Delete Asset Good Receive: ' . $gr->gr_number,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($gr),
                'new_data' => null,
                'created_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('asset-good-receives.index')
                ->with('success', 'Asset Good Receive berhasil dihapus.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting Asset Good Receive: ' . $e->getMessage(), [
                'gr_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'Gagal menghapus: ' . $e->getMessage()]);
        }
    }

    /**
     * Process inventory stock-in for a single item during Good Receive.
     * Handles: inventory item creation, unit conversion, MAC calculation,
     * stock upsert, stock card, and cost history.
     */
    private function processInventoryIn(
        int $itemId,
        int $unitId,
        float $qtyReceived,
        float $price,
        int $ownerOutletId,
        int $locationOutletId,
        ?int $warehouseOutletId,
        string $receiveDate,
        int $grId,
        int $poItemId
    ): void {
        $itemMaster = DB::table('items')
            ->leftJoin('units as su', 'items.small_unit_id', '=', 'su.id')
            ->leftJoin('units as mu', 'items.medium_unit_id', '=', 'mu.id')
            ->leftJoin('units as lu', 'items.large_unit_id', '=', 'lu.id')
            ->where('items.id', $itemId)
            ->select('items.*', 'su.name as small_unit_name', 'mu.name as medium_unit_name', 'lu.name as large_unit_name')
            ->first();

        if (!$itemMaster) {
            throw new \Exception("Item master tidak ditemukan untuk item_id: {$itemId}");
        }

        // 1. Get or create asset_inventory_items
        $inventoryItem = DB::table('asset_inventory_items')->where('item_id', $itemId)->first();
        if (!$inventoryItem) {
            $inventoryItemId = DB::table('asset_inventory_items')->insertGetId([
                'item_id' => $itemId,
                'small_unit_id' => $itemMaster->small_unit_id,
                'medium_unit_id' => $itemMaster->medium_unit_id,
                'large_unit_id' => $itemMaster->large_unit_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $inventoryItemId = $inventoryItem->id;
        }

        // 2. Unit conversion
        $smallConv = $itemMaster->medium_conversion_qty ?: 1; // small per medium
        $largeConv = $itemMaster->small_conversion_qty ?: 1;  // small per large

        $poUnitName = '';
        $poUnitRecord = DB::table('units')->where('id', $unitId)->first();
        if ($poUnitRecord) {
            $poUnitName = strtolower(trim($poUnitRecord->name));
        }

        $unitSmall = strtolower(trim($itemMaster->small_unit_name ?? ''));
        $unitMedium = strtolower(trim($itemMaster->medium_unit_name ?? ''));
        $unitLarge = strtolower(trim($itemMaster->large_unit_name ?? ''));

        if ($poUnitName === $unitSmall) {
            $qty_small = $qtyReceived;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = $largeConv > 0 ? $qty_small / $largeConv : 0;
        } elseif ($poUnitName === $unitMedium) {
            $qty_medium = $qtyReceived;
            $qty_small = $qty_medium * $smallConv;
            $qty_large = $largeConv > 0 ? $qty_small / $largeConv : 0;
        } elseif ($poUnitName === $unitLarge) {
            $qty_large = $qtyReceived;
            $qty_small = $qty_large * $largeConv;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
        } else {
            $qty_small = $qtyReceived;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = $largeConv > 0 ? $qty_small / $largeConv : 0;
        }

        // 3. Cost calculation per unit tier
        $cost_small = $price;
        if ($poUnitName === $unitMedium) {
            $cost_small = $smallConv > 0 ? $price / $smallConv : $price;
        } elseif ($poUnitName === $unitLarge) {
            $cost_small = $largeConv > 0 ? $price / $largeConv : $price;
        }
        $cost_medium = $cost_small * $smallConv;
        $cost_large = $cost_small * $largeConv;

        $newValue = $qty_small * $cost_small;

        // 4. Moving Average Cost & upsert stock
        $existingStock = AssetInventoryStockService::findStock(
            $inventoryItemId,
            $ownerOutletId,
            $warehouseOutletId
        );

        $oldQty = $existingStock ? (float) $existingStock->qty_small : 0;
        $oldValue = $existingStock ? (float) $existingStock->value : 0;
        $totalQty = $oldQty + $qty_small;
        $totalValue = $oldValue + $newValue;
        $mac = $totalQty > 0 ? $totalValue / $totalQty : $cost_small;

        if ($existingStock) {
            DB::table('asset_inventory_stocks')
                ->where('id', $existingStock->id)
                ->update([
                    'qty_small' => $totalQty,
                    'qty_medium' => (float) $existingStock->qty_medium + $qty_medium,
                    'qty_large' => (float) $existingStock->qty_large + $qty_large,
                    'value' => $totalValue,
                    'last_cost_small' => $mac,
                    'last_cost_medium' => $cost_medium,
                    'last_cost_large' => $cost_large,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('asset_inventory_stocks')->insert([
                'inventory_item_id' => $inventoryItemId,
                'owner_outlet_id' => $ownerOutletId,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $warehouseOutletId,
                'qty_small' => $qty_small,
                'qty_medium' => $qty_medium,
                'qty_large' => $qty_large,
                'value' => $newValue,
                'last_cost_small' => $cost_small,
                'last_cost_medium' => $cost_medium,
                'last_cost_large' => $cost_large,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Stock card (asset_inventory_cards)
        $lastCardQuery = DB::table('asset_inventory_cards')
            ->where('inventory_item_id', $inventoryItemId);
        AssetInventoryStockService::applyOwnerWarehouseScope($lastCardQuery, $ownerOutletId, $warehouseOutletId);
        $lastCard = $lastCardQuery
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        $saldo_qty_small = ($lastCard ? (float) $lastCard->saldo_qty_small : 0) + $qty_small;
        $saldo_qty_medium = ($lastCard ? (float) $lastCard->saldo_qty_medium : 0) + $qty_medium;
        $saldo_qty_large = ($lastCard ? (float) $lastCard->saldo_qty_large : 0) + $qty_large;
        $saldo_value = $totalQty * $mac;

        DB::table('asset_inventory_cards')->insert([
            'inventory_item_id' => $inventoryItemId,
            'owner_outlet_id' => $ownerOutletId,
            'outlet_id' => $locationOutletId,
            'warehouse_outlet_id' => $warehouseOutletId,
            'date' => $receiveDate,
            'reference_type' => 'asset_good_receive',
            'reference_id' => $grId,
            'in_qty_small' => $qty_small,
            'in_qty_medium' => $qty_medium,
            'in_qty_large' => $qty_large,
            'out_qty_small' => 0,
            'out_qty_medium' => 0,
            'out_qty_large' => 0,
            'cost_per_small' => $cost_small,
            'cost_per_medium' => $cost_medium,
            'cost_per_large' => $cost_large,
            'value_in' => $newValue,
            'value_out' => 0,
            'saldo_qty_small' => $saldo_qty_small,
            'saldo_qty_medium' => $saldo_qty_medium,
            'saldo_qty_large' => $saldo_qty_large,
            'saldo_value' => $saldo_value,
            'description' => 'Asset Good Receive',
            'created_at' => now(),
        ]);

        // 6. Cost history (asset_inventory_cost_histories)
        $lastCostQuery = DB::table('asset_inventory_cost_histories')
            ->where('inventory_item_id', $inventoryItemId);
        AssetInventoryStockService::applyOwnerWarehouseScope($lastCostQuery, $ownerOutletId, $warehouseOutletId);
        $lastCostHistory = $lastCostQuery
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->first();

        DB::table('asset_inventory_cost_histories')->insert([
            'inventory_item_id' => $inventoryItemId,
            'owner_outlet_id' => $ownerOutletId,
            'outlet_id' => $locationOutletId,
            'warehouse_outlet_id' => $warehouseOutletId,
            'date' => $receiveDate,
            'reference_type' => 'asset_good_receive',
            'reference_id' => $grId,
            'old_cost_small' => $lastCostHistory ? $lastCostHistory->new_cost_small : 0,
            'old_cost_medium' => $lastCostHistory ? $lastCostHistory->new_cost_medium : 0,
            'old_cost_large' => $lastCostHistory ? $lastCostHistory->new_cost_large : 0,
            'new_cost_small' => $mac,
            'new_cost_medium' => $cost_medium,
            'new_cost_large' => $cost_large,
            'qty' => $qty_small,
            'value' => $newValue,
            'created_at' => now(),
        ]);
    }

    /**
     * Rollback inventory for a single item (used during update/delete).
     * Reverses stock, removes stock card and cost history entries.
     */
    private function rollbackInventory(
        int $itemId,
        int $unitId,
        float $qtyReceived,
        int $ownerOutletId,
        int $locationOutletId,
        ?int $warehouseOutletId,
        int $grId
    ): void {
        $inventoryItem = DB::table('asset_inventory_items')->where('item_id', $itemId)->first();
        if (!$inventoryItem) {
            return;
        }

        $itemMaster = DB::table('items')
            ->leftJoin('units as su', 'items.small_unit_id', '=', 'su.id')
            ->leftJoin('units as mu', 'items.medium_unit_id', '=', 'mu.id')
            ->leftJoin('units as lu', 'items.large_unit_id', '=', 'lu.id')
            ->where('items.id', $itemId)
            ->select('items.*', 'su.name as small_unit_name', 'mu.name as medium_unit_name', 'lu.name as large_unit_name')
            ->first();

        if (!$itemMaster) {
            return;
        }

        // Re-calculate qty conversions to rollback
        $smallConv = $itemMaster->medium_conversion_qty ?: 1;
        $largeConv = $itemMaster->small_conversion_qty ?: 1;

        $poUnitName = '';
        $poUnitRecord = DB::table('units')->where('id', $unitId)->first();
        if ($poUnitRecord) {
            $poUnitName = strtolower(trim($poUnitRecord->name));
        }

        $unitSmall = strtolower(trim($itemMaster->small_unit_name ?? ''));
        $unitMedium = strtolower(trim($itemMaster->medium_unit_name ?? ''));
        $unitLarge = strtolower(trim($itemMaster->large_unit_name ?? ''));

        if ($poUnitName === $unitSmall) {
            $qty_small = $qtyReceived;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = $largeConv > 0 ? $qty_small / $largeConv : 0;
        } elseif ($poUnitName === $unitMedium) {
            $qty_medium = $qtyReceived;
            $qty_small = $qty_medium * $smallConv;
            $qty_large = $largeConv > 0 ? $qty_small / $largeConv : 0;
        } elseif ($poUnitName === $unitLarge) {
            $qty_large = $qtyReceived;
            $qty_small = $qty_large * $largeConv;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
        } else {
            $qty_small = $qtyReceived;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = $largeConv > 0 ? $qty_small / $largeConv : 0;
        }

        // Rollback stock
        $currentStock = AssetInventoryStockService::findStock(
            $inventoryItem->id,
            $ownerOutletId,
            $warehouseOutletId
        );

        if ($currentStock) {
            $newQtySmall = max(0, (float) $currentStock->qty_small - $qty_small);
            $newQtyMedium = max(0, (float) $currentStock->qty_medium - $qty_medium);
            $newQtyLarge = max(0, (float) $currentStock->qty_large - $qty_large);

            if ($newQtySmall <= 0 && $newQtyMedium <= 0 && $newQtyLarge <= 0) {
                DB::table('asset_inventory_stocks')->where('id', $currentStock->id)->delete();
            } else {
                $newValue = $newQtySmall * ((float) $currentStock->last_cost_small ?: 0);
                DB::table('asset_inventory_stocks')
                    ->where('id', $currentStock->id)
                    ->update([
                        'qty_small' => $newQtySmall,
                        'qty_medium' => $newQtyMedium,
                        'qty_large' => $newQtyLarge,
                        'value' => $newValue,
                        'updated_at' => now(),
                    ]);
            }
        }

        // Remove stock card entries
        DB::table('asset_inventory_cards')
            ->where('reference_type', 'asset_good_receive')
            ->where('reference_id', $grId)
            ->where('inventory_item_id', $inventoryItem->id)
            ->delete();

        // Remove cost history entries
        DB::table('asset_inventory_cost_histories')
            ->where('reference_type', 'asset_good_receive')
            ->where('reference_id', $grId)
            ->where('inventory_item_id', $inventoryItem->id)
            ->delete();
    }

    // ============================
    // API methods for mobile app
    // ============================

    public function apiIndex(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('asset_good_receives as gr')
            ->leftJoin('purchase_order_ops as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.po_id',
                'gr.outlet_id',
                'gr.warehouse_outlet_id',
                'gr.receive_date',
                'gr.received_by',
                'gr.status',
                'gr.notes',
                'gr.created_at',
                'gr.updated_at',
                'po.number as po_number',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as received_by_name',
                's.name as supplier_name'
            )
            ->addSelect(DB::raw('(SELECT COALESCE(SUM(gri.total), 0) FROM asset_good_receive_items gri WHERE gri.asset_good_receive_id = gr.id) as total'));

        if ($user->id_outlet != 1) {
            $query->where('gr.outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('gr.gr_number', 'like', "%{$search}%")
                  ->orWhere('po.number', 'like', "%{$search}%");
            });
        }

        if ($request->from) {
            $query->whereDate('gr.receive_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('gr.receive_date', '<=', $request->to);
        }

        $perPage = $request->per_page ?? 20;
        $goodReceives = $query->orderByDesc('gr.created_at')->paginate($perPage);

        return response()->json($goodReceives);
    }

    public function apiShow($id)
    {
        $gr = DB::table('asset_good_receives as gr')
            ->leftJoin('purchase_order_ops as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.*',
                'po.number as po_number',
                'po.date as po_date',
                's.name as supplier_name',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as received_by_name'
            )
            ->where('gr.id', $id)
            ->first();

        if (!$gr) {
            return response()->json(['message' => 'Asset Good Receive tidak ditemukan'], 404);
        }

        $items = DB::table('asset_good_receive_items as gri')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
            ->leftJoin('purchase_order_ops_items as poi', 'gri.po_item_id', '=', 'poi.id')
            ->select(
                'gri.*',
                'i.name as item_name',
                'u.name as unit_name',
                'poi.item_name as po_item_name',
                'poi.quantity as po_quantity',
                'poi.unit as po_unit'
            )
            ->where('gri.asset_good_receive_id', $id)
            ->get();

        $gr->items = $items;

        return response()->json(['success' => true, 'good_receive' => $gr]);
    }

    public function apiFetchPO(Request $request)
    {
        $number = $request->input('po_number') ?? $request->query('number');
        if (!$number) {
            return response()->json(['message' => 'Nomor PO harus diisi'], 422);
        }

        $po = DB::table('purchase_order_ops')->where('number', $number)->first();
        if (!$po) {
            return response()->json(['message' => 'PO tidak ditemukan'], 404);
        }

        if ($po->source_type !== 'purchase_requisition_ops') {
            return response()->json(['message' => 'PO ini bukan dari Purchase Requisition Ops'], 422);
        }

        $pr = DB::table('purchase_requisitions')->where('id', $po->source_id)->first();
        if (!$pr || $pr->mode !== 'pr_assets') {
            return response()->json(['message' => 'PO ini bukan untuk asset (PR mode bukan pr_assets)'], 422);
        }

        $poItems = DB::table('purchase_order_ops_items as poi')
            ->leftJoin('purchase_requisition_items as pri', 'poi.pr_ops_item_id', '=', 'pri.id')
            ->where('poi.purchase_order_ops_id', $po->id)
            ->select(
                'poi.id',
                'poi.item_name',
                'poi.quantity',
                'poi.unit',
                'poi.price',
                'poi.discount_percent',
                'poi.discount_amount',
                'poi.total',
                'poi.pr_ops_item_id',
                'poi.outlet_id',
                'pri.id as pr_item_id'
            )
            ->get();

        $poItems = $poItems->map(fn ($poItem) => $this->enrichPoItemForAssetReceive($poItem));

        $supplier = DB::table('suppliers')->where('id', $po->supplier_id)->first();

        $user = auth()->user();
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        $suggestedOwnerOutletId = AssetInventoryStockService::suggestedOwnerOutletIdFromPoItems($poItems);

        return response()->json([
            'po' => $po,
            'supplier' => $supplier,
            'pr' => $pr,
            'items' => $poItems,
            'suggested_owner_outlet_id' => $suggestedOwnerOutletId,
            'outlets' => $outlets,
            'warehouse_outlets' => $warehouseOutlets,
            'user' => ['id_outlet' => $user->id_outlet],
        ]);
    }

    public function apiStore(Request $request)
    {
        $request->validate([
            'po_id' => 'required|integer',
            'owner_outlet_id' => 'required|integer',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'nullable|integer',
            'receive_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.po_item_id' => 'required|integer',
            'items.*.item_id' => 'required|integer',
            'items.*.unit_id' => 'required|integer',
            'items.*.qty_ordered' => 'required|numeric|min:0',
            'items.*.qty_received' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($request->warehouse_outlet_id) {
            AssetInventoryStockService::assertWarehouseBelongsToOutlet(
                (int) $request->warehouse_outlet_id,
                (int) $request->outlet_id
            );
        }

        $locationOutletId = AssetInventoryStockService::resolveLocationOutletId(
            (int) $request->outlet_id,
            $request->warehouse_outlet_id ? (int) $request->warehouse_outlet_id : null
        );

        DB::beginTransaction();
        try {
            $grNumber = AssetGoodReceive::generateNumber();

            $gr = AssetGoodReceive::create([
                'gr_number' => $grNumber,
                'po_id' => $request->po_id,
                'owner_outlet_id' => $request->owner_outlet_id,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'receive_date' => $request->receive_date,
                'received_by' => Auth::id(),
                'status' => 'draft',
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $itemData) {
                $qtyReceived = (float) $itemData['qty_received'];
                $price = (float) $itemData['price'];
                $total = $qtyReceived * $price;

                $grItem = AssetGoodReceiveItem::create([
                    'asset_good_receive_id' => $gr->id,
                    'po_item_id' => $itemData['po_item_id'],
                    'item_id' => $itemData['item_id'],
                    'unit_id' => $itemData['unit_id'],
                    'qty_ordered' => $itemData['qty_ordered'],
                    'qty_received' => $qtyReceived,
                    'price' => $price,
                    'total' => $total,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $this->processInventoryIn(
                    $itemData['item_id'],
                    $itemData['unit_id'],
                    $qtyReceived,
                    $price,
                    (int) $request->owner_outlet_id,
                    $locationOutletId,
                    $request->warehouse_outlet_id,
                    $request->receive_date,
                    $gr->id,
                    $itemData['po_item_id']
                );

                app(LostBreakageReplacementService::class)->recordReplacementFromGrItem(
                    (int) $gr->id,
                    (int) $grItem->id,
                    (int) $itemData['po_item_id'],
                    $qtyReceived,
                    (int) $itemData['unit_id'],
                    (int) $itemData['item_id']
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset Good Receive berhasil disimpan.',
                'gr_id' => $gr->id,
                'gr_number' => $grNumber,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API Error creating Asset Good Receive: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function apiDestroy($id)
    {
        DB::beginTransaction();
        try {
            $gr = AssetGoodReceive::findOrFail($id);

            if ($gr->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya GR dengan status draft yang dapat dihapus.',
                ], 422);
            }

            $items = AssetGoodReceiveItem::where('asset_good_receive_id', $id)->get();

            foreach ($items as $item) {
                $this->rollbackInventory(
                    $item->item_id,
                    $item->unit_id,
                    (float) $item->qty_received,
                    (int) $gr->owner_outlet_id,
                    (int) $gr->outlet_id,
                    $gr->warehouse_outlet_id,
                    $gr->id
                );
            }

            AssetGoodReceiveItem::where('asset_good_receive_id', $id)->delete();
            $gr->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset Good Receive berhasil dihapus.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API Error deleting Asset Good Receive: ' . $e->getMessage(), [
                'gr_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function enrichPoItemForAssetReceive(object $poItem): object
    {
        $qtyAlreadyReceived = DB::table('asset_good_receive_items')
            ->where('po_item_id', $poItem->id)
            ->sum('qty_received');

        $poItem->qty_already_received = (float) $qtyAlreadyReceived;
        $poItem->qty_remaining = (float) $poItem->quantity - $qtyAlreadyReceived;

        $itemName = trim((string) ($poItem->item_name ?? ''));
        $itemRecord = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('categories.is_asset', 1)
            ->where(function ($q) use ($itemName) {
                $q->where('items.name', $itemName)
                    ->orWhereRaw('TRIM(items.name) = ?', [$itemName])
                    ->orWhereRaw('LOWER(TRIM(items.name)) = ?', [strtolower($itemName)]);
            })
            ->select('items.id', 'items.name', 'items.small_unit_id', 'items.medium_unit_id', 'items.large_unit_id')
            ->first();

        $poItem->item_id = $itemRecord ? $itemRecord->id : null;
        $poItem->resolved_item_name = $itemRecord ? $itemRecord->name : $poItem->item_name;
        $poItem->small_unit_id = $itemRecord ? $itemRecord->small_unit_id : null;
        $poItem->medium_unit_id = $itemRecord ? $itemRecord->medium_unit_id : null;
        $poItem->large_unit_id = $itemRecord ? $itemRecord->large_unit_id : null;

        $poItem->unit_id = $this->resolveUnitIdForAssetPoItem($poItem->unit ?? '', $itemRecord);
        $poItem->resolve_ok = (bool) ($poItem->item_id && $poItem->unit_id);

        return $poItem;
    }

    private function resolveUnitIdForAssetPoItem(string $poUnit, ?object $itemRecord): ?int
    {
        $unitName = strtolower(trim($poUnit));
        if ($unitName === '') {
            return null;
        }

        $unitRecord = DB::table('units')
            ->whereRaw('LOWER(TRIM(name)) = ?', [$unitName])
            ->first();

        if ($unitRecord) {
            return (int) $unitRecord->id;
        }

        if (! $itemRecord) {
            return null;
        }

        $candidateUnitIds = array_values(array_filter([
            $itemRecord->small_unit_id ?? null,
            $itemRecord->medium_unit_id ?? null,
            $itemRecord->large_unit_id ?? null,
        ]));

        if (empty($candidateUnitIds)) {
            return null;
        }

        $itemUnits = DB::table('units')->whereIn('id', $candidateUnitIds)->get();
        foreach ($itemUnits as $itemUnit) {
            if (strtolower(trim((string) $itemUnit->name)) === $unitName) {
                return (int) $itemUnit->id;
            }
        }

        return $itemRecord->small_unit_id ? (int) $itemRecord->small_unit_id : null;
    }
}
