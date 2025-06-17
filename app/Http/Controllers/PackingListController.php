<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FoodPackingList;
use App\Models\FoodFloorOrder;
use App\Models\WarehouseDivision;
use App\Models\FoodPackingListItem;

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
        // Ambil semua FO yang approved
        $floorOrders = FoodFloorOrder::where('status', 'approved')
            ->with(['outlet', 'user', 'items.item.smallUnit', 'items.item.mediumUnit', 'items.item.largeUnit', 'warehouseDivisions', 'warehouseOutlet'])
            ->orderByDesc('created_at')
            ->get();

        // Filter FO yang masih memiliki item yang belum di-packing untuk setiap warehouse division
        $floorOrders = $floorOrders->filter(function($fo) {
            // Ambil semua warehouse division yang terkait dengan FO
            $foDivisions = $fo->warehouseDivisions->pluck('id')->toArray();
            
            // Untuk setiap warehouse division, cek apakah masih ada item yang belum di-packing
            foreach ($foDivisions as $divisionId) {
                // Ambil item yang sesuai dengan warehouse division ini
                $itemsInDivision = $fo->items->filter(function($item) use ($divisionId) {
                    return $item->item && $item->item->warehouse_division_id == $divisionId;
                });
                
                if ($itemsInDivision->count() > 0) {
                    // Cek apakah semua item di division ini sudah di-packing
                    $packedItems = FoodPackingListItem::whereHas('packingList', function($q) use ($fo, $divisionId) {
                        $q->where('food_floor_order_id', $fo->id)
                          ->where('warehouse_division_id', $divisionId);
                    })->pluck('food_floor_order_item_id')->toArray();
                    
                    // Jika masih ada item yang belum di-packing, FO ini masih valid
                    if ($itemsInDivision->whereNotIn('id', $packedItems)->count() > 0) {
                        return true;
                    }
                }
            }
            
            return false;
        })->values();

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
            'items.*.qty' => 'required|numeric|min:0',
            'items.*.unit' => 'required|string',
            'items.*.source' => 'required|in:warehouse,supplier',
            'items.*.reason' => 'nullable|string',
        ]);

        \DB::beginTransaction();
        try {
            // Validasi stock untuk setiap item
            $division = WarehouseDivision::find($data['warehouse_division_id']);
            $warehouse_id = $division ? $division->warehouse_id : null;
            foreach ($data['items'] as $item) {
                $foItem = \App\Models\FoodFloorOrderItem::with('item.smallUnit', 'item.mediumUnit', 'item.largeUnit')->find($item['food_floor_order_item_id']);
                $inventoryItem = \DB::table('food_inventory_items')->where('item_id', $foItem->item_id)->first();
                if ($inventoryItem && $warehouse_id) {
                    $stock = \DB::table('food_inventory_stocks')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('warehouse_id', $warehouse_id)
                        ->first();
                    $unit = $item['unit'];
                    $stockQty = null;
                    if ($stock) {
                        if ($unit == $foItem->item->smallUnit?->name) {
                            $stockQty = $stock->qty_small;
                        } elseif ($unit == $foItem->item->mediumUnit?->name) {
                            $stockQty = $stock->qty_medium;
                        } elseif ($unit == $foItem->item->largeUnit?->name) {
                            $stockQty = $stock->qty_large;
                        }
                    }
                    if ($stockQty !== null && $item['qty'] > $stockQty) {
                        throw new \Exception("Qty untuk item {$foItem->item->name} melebihi stock di warehouse!");
                    }
                }
            }
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
                \Log::info('PackingListItem reason', ['item' => $item]);
                $packingList->items()->create([
                    'food_floor_order_item_id' => $item['food_floor_order_item_id'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'source' => $item['source'],
                    'reason' => $item['reason'] ?? null,
                ]);
            }
            // Update status FO hanya jika semua warehouse division pada FO sudah selesai packing
            $fo = FoodFloorOrder::with('warehouseDivisions')->find($data['food_floor_order_id']);
            $oldStatus = $fo->status;
            $allDivisions = $fo->warehouseDivisions->pluck('id')->toArray();
            $packedDivisions = FoodPackingList::where('food_floor_order_id', $fo->id)
                ->whereIn('warehouse_division_id', $allDivisions)
                ->where('status', 'packing')
                ->pluck('warehouse_division_id')
                ->unique()
                ->toArray();
            if (count($packedDivisions) === count($allDivisions)) {
                $fo->update(['status' => 'packing']);
            }
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
        $division = \App\Models\WarehouseDivision::find($divisionId);
        $warehouse_id = $division ? $division->warehouse_id : null;
        // Ambil semua item FO yang sesuai division
        $foItems = \App\Models\FoodFloorOrderItem::where('floor_order_id', $foId)
            ->whereHas('item', function($q) use ($divisionId) {
                $q->where('warehouse_division_id', $divisionId);
            })
            ->with(['item.smallUnit', 'item.mediumUnit', 'item.largeUnit', 'item.category'])
            ->get();
        // Item yang sudah pernah di-packing (qty berapapun)
        $packedItemIds = \App\Models\FoodPackingListItem::whereHas('packingList', function($q) use ($foId, $divisionId) {
            $q->where('food_floor_order_id', $foId)
              ->where('warehouse_division_id', $divisionId);
        })->pluck('food_floor_order_item_id');
        // Filter hanya item yang belum pernah di-packing
        $itemsToPack = $foItems->whereNotIn('id', $packedItemIds)->values();
        // Ambil stock untuk setiap item sesuai unit yang dipakai
        $itemStocks = [];
        if ($warehouse_id) {
            $itemIds = $itemsToPack->pluck('item_id')->unique()->values();
            $inventoryItems = \DB::table('food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->get()->keyBy('item_id');
            $inventoryItemIds = $inventoryItems->pluck('id')->unique()->values();
            $stocks = \DB::table('food_inventory_stocks')
                ->whereIn('inventory_item_id', $inventoryItemIds)
                ->where('warehouse_id', $warehouse_id)
                ->get()->keyBy('inventory_item_id');
            foreach ($itemsToPack as $foItem) {
                $item = $foItem->item;
                $inv = $inventoryItems[$item->id] ?? null;
                $stock = $inv ? $stocks[$inv->id] ?? null : null;
                $unit = $foItem->unit;
                $stockQty = null;
                if ($stock) {
                    if ($unit == $item->smallUnit?->name) {
                        $stockQty = $stock->qty_small;
                    } elseif ($unit == $item->mediumUnit?->name) {
                        $stockQty = $stock->qty_medium;
                    } elseif ($unit == $item->largeUnit?->name) {
                        $stockQty = $stock->qty_large;
                    }
                }
                $itemStocks[$foItem->id] = $stockQty !== null ? (float)$stockQty : 0;
            }
        }
        $result = $itemsToPack->map(function($foItem) use ($itemStocks) {
            $arr = $foItem->toArray();
            $arr['stock'] = $itemStocks[$foItem->id] ?? 0;
            return $arr;
        });
        return response()->json(['items' => $result]);
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

    public function summary(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        // Get all floor orders that are approved but not yet fully packed
        $floorOrders = FoodFloorOrder::where('status', 'approved')
            ->whereDate('tanggal', $request->tanggal)
            ->with(['items.item', 'items.item.smallUnit', 'items.item.mediumUnit', 'items.item.largeUnit'])
            ->get();

        $summaryItems = [];
        foreach ($floorOrders as $fo) {
            foreach ($fo->items as $foItem) {
                $item = $foItem->item;
                if (!$item) continue;

                // Check if this item has been packed
                $isPacked = FoodPackingListItem::whereHas('packingList', function($q) use ($fo) {
                    $q->where('food_floor_order_id', $fo->id);
                })->where('food_floor_order_item_id', $foItem->id)->exists();

                if (!$isPacked) {
                    $key = $item->id;
                    if (!isset($summaryItems[$key])) {
                        $summaryItems[$key] = [
                            'item_id' => $item->id,
                            'item_name' => $item->name,
                            'total_qty' => 0,
                            'unit' => $item->smallUnit->name ?? '-',
                        ];
                    }
                    $summaryItems[$key]['total_qty'] += $foItem->qty;
                }
            }
        }

        return response()->json([
            'items' => array_values($summaryItems)
        ]);
    }
} 