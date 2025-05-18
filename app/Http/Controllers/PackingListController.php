<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FoodPackingList;
use App\Models\FoodFloorOrder;
use App\Models\WarehouseDivision;

class PackingListController extends Controller
{
    public function index()
    {
        $user = auth()->user()->load('outlet');
        $packingLists = FoodPackingList::with([
            'warehouseDivision',
            'floorOrder.outlet',
            'floorOrder.requester',
            'items',
            'creator',
        ])->orderByDesc('created_at')->paginate(10);

        return inertia('PackingList/Index', [
            'user' => $user,
            'packingLists' => $packingLists,
        ]);
    }

    public function create()
    {
        $floorOrders = FoodFloorOrder::where('status', 'approved')
            ->with(['outlet', 'user', 'items.item.smallUnit', 'items.item.mediumUnit', 'items.item.largeUnit'])
            ->orderByDesc('created_at')->get();
        $warehouseDivisions = \App\Models\WarehouseDivision::all();
        return inertia('PackingList/Form', [
            'floorOrders' => $floorOrders,
            'warehouseDivisions' => $warehouseDivisions,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'food_floor_order_id' => 'required|exists:food_floor_orders,id',
            'warehouse_division_id' => 'required|exists:warehouse_division,id',
            'reason' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.food_floor_order_item_id' => 'required|exists:food_floor_order_items,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.source' => 'required|in:warehouse,supplier',
        ]);

        \DB::beginTransaction();
        try {
            // Generate packing_number
            $date = now()->format('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $packingNumber = 'PL-' . $date . '-' . $random;
            $packingList = FoodPackingList::create([
                'food_floor_order_id' => $data['food_floor_order_id'],
                'warehouse_division_id' => $data['warehouse_division_id'],
                'reason' => $data['reason'] ?? null,
                'created_by' => auth()->id(),
                'packing_number' => $packingNumber,
                'status' => 'packing',
            ]);
            foreach ($data['items'] as $item) {
                $packingList->items()->create([
                    'food_floor_order_item_id' => $item['food_floor_order_item_id'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'source' => $item['source'],
                ]);
            }
            // Update status FO
            $fo = FoodFloorOrder::find($data['food_floor_order_id']);
            $oldStatus = $fo->status;
            $fo->update(['status' => 'packing']);
            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'module' => 'packing_list',
                'description' => 'Membuat Packing List untuk FO #' . $fo->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => json_encode(['status' => $oldStatus]),
                'new_data' => json_encode(['status' => 'packing']),
                'created_at' => now(),
            ]);
            \DB::commit();
            return response()->json(['success' => true, 'message' => 'Packing List berhasil dibuat.']);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan Packing List: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $packingList = \App\Models\FoodPackingList::with([
            'warehouseDivision',
            'floorOrder.outlet',
            'floorOrder.requester',
            'items.floorOrderItem.item'
        ])->findOrFail($id);
        return inertia('PackingList/Detail', [
            'packingList' => $packingList
        ]);
    }

    public function edit($id)
    {
        return "Packing List Edit: $id";
    }

    public function update(Request $request, $id)
    {
        return "Packing List Update: $id";
    }

    public function destroy($id)
    {
        return "Packing List Destroy: $id";
    }

    public function availableItems(Request $request)
    {
        $foId = $request->input('fo_id');
        $divisionId = $request->input('division_id');

        // Ambil semua item FO yang sesuai division
        $foItems = \App\Models\FoodFloorOrderItem::where('floor_order_id', $foId)
            ->whereHas('item', function($q) use ($divisionId) {
                $q->where('warehouse_division_id', $divisionId);
            })
            ->with(['item.smallUnit', 'item.mediumUnit', 'item.largeUnit', 'item.category'])
            ->get();

        // Item yang sudah pernah di-packing
        $packedItemIds = \App\Models\FoodPackingListItem::whereHas('packingList', function($q) use ($foId, $divisionId) {
            $q->where('food_floor_order_id', $foId)
              ->where('warehouse_division_id', $divisionId);
        })->pluck('food_floor_order_item_id');

        // Filter hanya item yang belum pernah di-packing
        $itemsToPack = $foItems->whereNotIn('id', $packedItemIds)->values();

        return response()->json(['items' => $itemsToPack]);
    }

    public function itemStocks(Request $request)
    {
        $request->validate([
            'warehouse_division_id' => 'required|exists:warehouse_division,id',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'required|integer',
        ]);
        $division = \App\Models\WarehouseDivision::find($request->warehouse_division_id);
        $warehouse_id = $division->warehouse_id;
        $stocks = \DB::table('food_inventory_stocks')
            ->where('warehouse_id', $warehouse_id)
            ->whereIn('inventory_item_id', $request->item_ids)
            ->get();
        $result = [];
        foreach ($request->item_ids as $itemId) {
            $stock = $stocks->firstWhere('inventory_item_id', $itemId);
            $result[$itemId] = [
                'qty_small' => $stock ? $stock->qty_small : 0,
                'qty_medium' => $stock ? $stock->qty_medium : 0,
                'qty_large' => $stock ? $stock->qty_large : 0,
            ];
        }
        return response()->json(['stocks' => $result]);
    }
} 