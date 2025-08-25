<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequisitionFood;
use App\Models\PurchaseOrderFood;
use App\Models\PurchaseOrderFoodItem;
use App\Models\PurchaseRequisitionFoodItem;
use App\Models\Supplier;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderFoodsController extends Controller
{
    public function index()
    {
        $query = PurchaseOrderFood::with(['supplier', 'creator', 'items', 'purchasing_manager', 'gm_finance'])
            ->orderBy('created_at', 'desc');

        if (request('search')) {
            $search = request('search');
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', '%' . $search . '%')
                  ->orWhereHas('supplier', function($supplierQuery) use ($search) {
                      $supplierQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }
        if (request('status')) {
            $query->where('status', request('status'));
        }
        if (request('from')) {
            $query->whereDate('date', '>=', request('from'));
        }
        if (request('to')) {
            $query->whereDate('date', '<=', request('to'));
        }

        $perPage = request('perPage', 10);
        $purchaseOrders = $query->paginate($perPage)->withQueryString();
        $purchaseOrders->getCollection()->transform(function ($po) {
            // Get source information
            if ($po->source_type === 'pr_foods' || !$po->source_type) {
                // For PR Foods or legacy PO without source_type, get PR numbers
                $prItemIds = $po->items->pluck('pr_food_item_id')->toArray();
                $prIds = \App\Models\PurchaseRequisitionFoodItem::whereIn('id', $prItemIds)->pluck('pr_food_id')->unique()->toArray();
                $prNumbers = \App\Models\PurchaseRequisitionFood::whereIn('id', $prIds)->pluck('pr_number')->unique()->toArray();
                $po->source_numbers = $prNumbers;
            } elseif ($po->source_type === 'ro_supplier') {
                $roNumbers = $po->items->pluck('ro_number')->unique()->filter()->toArray();
                $po->source_numbers = $roNumbers;
            } else {
                $po->source_numbers = [];
            }
            
            return $po;
        });

        return inertia('PurchaseOrder/PurchaseOrderFoods', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => request()->only(['search', 'status', 'from', 'to', 'perPage']),
            'user' => [
                'id' => auth()->user()->id,
                'id_jabatan' => auth()->user()->id_jabatan,
                'id_role' => auth()->user()->id_role,
                'status' => auth()->user()->status,
                'nama_lengkap' => auth()->user()->nama_lengkap,
            ],
        ]);
    }

    public function create()
    {
        return inertia('PurchaseOrder/CreatePurchaseOrderFoods');
    }

    public function getPOList()
    {
        $pos = PurchaseOrderFood::with(['supplier', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($po) {
                return [
                    'id' => $po->id,
                    'number' => $po->number,
                    'date' => Carbon::parse($po->date)->format('d/m/Y'),
                    'status' => $po->status,
                    'supplier' => $po->supplier,
                    'creator' => $po->creator,
                ];
            });

        return response()->json($pos);
    }

    public function getAvailablePR()
    {
        // Ambil semua pr_food_item_id yang sudah ada di PO
        $poPrItemIds = \App\Models\PurchaseOrderFoodItem::pluck('pr_food_item_id')->toArray();

        $prs = \App\Models\PurchaseRequisitionFood::where('status', 'approved')
            ->whereHas('items', function($q) use ($poPrItemIds) {
                $q->whereNotIn('id', $poPrItemIds);
            })
            ->with(['items' => function($q) use ($poPrItemIds) {
                $q->whereNotIn('id', $poPrItemIds);
            }, 'items.item', 'warehouse'])
            ->get()
            ->map(function ($pr) {
                return [
                    'id' => $pr->id,
                    'number' => $pr->pr_number,
                    'date' => \Carbon\Carbon::parse($pr->tanggal)->format('d/m/Y'),
                    'warehouse_id' => $pr->warehouse_id,
                    'warehouse_name' => $pr->warehouse ? $pr->warehouse->name : 'Unknown Warehouse',
                    'items' => $pr->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'item_id' => $item->item_id,
                            'name' => $item->item->name ?? '-',
                            'quantity' => $item->qty,
                            'unit' => $item->unit,
                            'arrival_date' => $item->arrival_date,
                            'supplier_id' => null,
                            'price' => null,
                        ];
                    }),
                ];
            });

        return response()->json($prs);
    }

    public function getPRItems(Request $request)
    {
        $request->validate([
            'pr_ids' => 'required|array',
            'pr_ids.*' => 'exists:pr_foods,id'
        ]);

        $items = PurchaseRequisitionFood::whereIn('id', $request->pr_ids)
            ->with(['items.item'])
            ->get()
            ->flatMap(function ($pr) {
                return $pr->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'pr_id' => $pr->id,
                        'pr_number' => $pr->pr_number,
                        'name' => $item->item->name,
                        'quantity' => $item->qty,
                        'unit' => $item->unit,
                        'item_id' => $item->item_id,
                    ];
                });
            });

        return response()->json($items);
    }

    public function generatePO(Request $request)
    {
        // Debug logging
        \Log::info('Generate PO Request Data:', [
            'items_by_supplier' => $request->items_by_supplier
        ]);
        
        $request->validate([
            'items_by_supplier' => 'required|array',
            'items_by_supplier.*' => 'required|array',
            'items_by_supplier.*.*.supplier_id' => 'required|exists:suppliers,id',
            'items_by_supplier.*.*.qty' => 'required|numeric|min:0',
            'items_by_supplier.*.*.price' => 'required|numeric|min:0',
            'ppn_enabled' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Collect all PR IDs that will be processed (only for PR Foods)
            $prIds = collect($request->items_by_supplier)
                ->flatMap(function ($items) {
                    return collect($items)
                        ->filter(function ($item) {
                            return !isset($item['source']) || $item['source'] !== 'ro_supplier';
                        })
                        ->pluck('pr_id');
                })
                ->unique()
                ->values();

            // Group items by supplier
            $itemsBySupplier = collect($request->items_by_supplier)
                ->map(function ($items, $supplierId) {
                    return collect($items)->map(function ($item) use ($supplierId) {
                        return [
                            'pr_item_id' => $item['id'] ?? null,
                            'supplier_id' => $supplierId,
                            'price' => $item['price'],
                            'qty' => $item['qty'],
                            'pr_id' => $item['pr_id'] ?? null,
                            'source' => $item['source'] ?? 'pr_foods',
                            'item_id' => $item['item_id'] ?? null,
                            'item_name' => $item['item_name'] ?? null,
                            'unit' => $item['unit'] ?? null,
                            'arrival_date' => $item['arrival_date'] ?? null,
                            'ro_id' => $item['ro_id'] ?? null,
                            'ro_number' => $item['ro_number'] ?? null,
                        ];
                    });
                });

            $createdPOs = [];

            foreach ($itemsBySupplier as $supplierId => $items) {
                if (empty($items)) continue;

                // Generate PO number
                $prefix = 'POF';
                $date = date('ym', strtotime(now()));
                $lastNumber = PurchaseOrderFood::where('number', 'like', $prefix . $date . '%')
                    ->orderBy('number', 'desc')
                    ->value('number');

                if ($lastNumber) {
                    $lastSequence = intval(substr($lastNumber, -4));
                    $sequence = $lastSequence + 1;
                } else {
                    $sequence = 1;
                }

                $poNumber = $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);

                // Calculate subtotal for this PO
                $subtotal = collect($items)->sum(function ($item) {
                    return $item['price'] * $item['qty'];
                });

                // Calculate PPN if enabled
                $ppnAmount = 0;
                $grandTotal = $subtotal;
                if ($request->ppn_enabled) {
                    $ppnAmount = $subtotal * 0.11; // 11% PPN
                    $grandTotal = $subtotal + $ppnAmount;
                }

                // Determine source type and ID for this PO
                $sourceType = null;
                $sourceId = null;
                
                // Check if all items are from the same source
                $firstItem = $items[0];
                if (isset($firstItem['source'])) {
                    $sourceType = $firstItem['source'];
                    if ($sourceType === 'pr_foods') {
                        // For PR Foods, use the first PR ID
                        $sourceId = $firstItem['pr_id'] ?? null;
                    } elseif ($sourceType === 'ro_supplier') {
                        // For RO Supplier, use the first RO ID
                        $sourceId = $firstItem['ro_id'] ?? null;
                    }
                }
                
                // Create PO
                $po = PurchaseOrderFood::create([
                    'number' => $poNumber,
                    'date' => now(),
                    'supplier_id' => $supplierId,
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                    'notes' => $request->notes,
                    'ppn_enabled' => $request->ppn_enabled ?? false,
                    'ppn_amount' => $ppnAmount,
                    'subtotal' => $subtotal,
                    'grand_total' => $grandTotal,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ]);

                $createdPOs[] = $po;

                // Get items for this supplier (PR or RO)
                $priceChanges = [];

                // Get PR items for this supplier (only for PR Foods)
                $prItemIds = collect($items)
                    ->filter(function ($item) {
                        return !isset($item['source']) || $item['source'] !== 'ro_supplier';
                    })
                    ->pluck('pr_item_id')
                    ->filter();
                
                $prItems = collect();
                if ($prItemIds->isNotEmpty()) {
                    $prItems = PurchaseRequisitionFoodItem::with('item')
                        ->whereIn('id', $prItemIds)
                        ->get();
                }

                foreach ($items as $itemData) {
                    // Debug logging for each item
                    \Log::info('Processing item:', $itemData);
                    
                    // Handle both PR Foods and RO Supplier items
                    if (isset($itemData['source']) && $itemData['source'] === 'ro_supplier') {
                        // RO Supplier item - FIXED LOGIC
                        $itemId = $itemData['item_id'] ?? null; // This should be the actual item_id (e.g., 53063)
                        $itemName = $itemData['item_name'] ?? 'Unknown Item';
                        $unit = $itemData['unit'] ?? null;
                        $arrivalDate = $itemData['arrival_date'] ?? null;
                        
                        // Get food_floor_order_item_id for RO Supplier items
                        $prItemId = null;
                        if ($itemId && isset($itemData['ro_id'])) {
                            $floorOrderItem = \DB::table('food_floor_order_items')
                                ->where('floor_order_id', $itemData['ro_id'])
                                ->where('item_id', $itemId)
                                ->first();
                            $prItemId = $floorOrderItem ? $floorOrderItem->id : null;
                            
                            // Debug logging for floor order item search
                            \Log::info('Searching for floor order item:', [
                                'floor_order_id' => $itemData['ro_id'],
                                'item_id' => $itemId,
                                'found_floor_order_item' => $floorOrderItem ? (array) $floorOrderItem : null,
                                'pr_item_id_result' => $prItemId
                            ]);
                        }
                        
                        // Debug logging for RO Supplier item
                        \Log::info('RO Supplier item data:', [
                            'item_id' => $itemId, // This should be the actual item_id (e.g., 53063)
                            'item_name' => $itemName,
                            'unit' => $unit,
                            'arrival_date' => $arrivalDate,
                            'ro_id' => $itemData['ro_id'] ?? null,
                            'floor_order_item_id' => $prItemId // This should be the food_floor_order_items.id (e.g., 227881)
                        ]);
                        
                        // Verify that item_id is correct
                        if ($itemId) {
                            $actualItem = \App\Models\Item::find($itemId);
                            if (!$actualItem) {
                                \Log::error('Item not found in items table:', [
                                    'item_id' => $itemId,
                                    'ro_id' => $itemData['ro_id'] ?? null
                                ]);
                            } else {
                                \Log::info('Item found in items table:', [
                                    'item_id' => $itemId,
                                    'item_name' => $actualItem->name
                                ]);
                            }
                        }
                    } else {
                        // PR Foods item
                        $prItem = $prItems->firstWhere('id', $itemData['pr_item_id']);
                        if (!$prItem) continue;
                        
                        $itemId = $prItem->item_id;
                        $itemName = $prItem->item->name ?? 'Unknown Item';
                        $unit = $prItem->unit;
                        $arrivalDate = $prItem->arrival_date;
                        $prItemId = $prItem->id;
                    }

                    $quantity = floatval($itemData['qty']);
                    $price = floatval($itemData['price']);
                    $total = round($quantity * $price, 2); // Ensure proper decimal precision

                    // Get unit ID
                    $unitId = null;
                    if ($unit) {
                        // Try to find unit by name first
                        $unitModel = Unit::where('name', $unit)->first();
                        if ($unitModel) {
                            $unitId = $unitModel->id;
                        } else {
                            // If unit name not found, try to get default unit from item
                            if ($itemId) {
                                $itemModel = \App\Models\Item::find($itemId);
                                if ($itemModel && $itemModel->small_unit_id) {
                                    $unitId = $itemModel->small_unit_id;
                                    \Log::info('Using default unit from item:', [
                                        'item_id' => $itemId,
                                        'default_unit_id' => $unitId,
                                        'requested_unit' => $unit
                                    ]);
                                }
                            }
                        }
                    } else {
                        // If no unit provided, try to get default unit from item
                        if ($itemId) {
                            $itemModel = \App\Models\Item::find($itemId);
                            if ($itemModel && $itemModel->small_unit_id) {
                                $unitId = $itemModel->small_unit_id;
                                \Log::info('Using default unit from item (no unit provided):', [
                                    'item_id' => $itemId,
                                    'default_unit_id' => $unitId
                                ]);
                            }
                        }
                    }
                    
                    // Debug logging for unit
                    \Log::info('Unit processing:', [
                        'unit_name' => $unit,
                        'unit_id' => $unitId,
                        'item_name' => $itemName,
                        'item_id' => $itemId
                    ]);

                    // Check for price changes using the same logic as getLastPrice
                    $lastPrice = null;
                    
                    // Cari inventory_item_id dari item_id
                    $inventoryItem = \DB::table('food_inventory_items')->where('item_id', $itemId)->first();
                    if ($inventoryItem) {
                        $inventoryItemId = $inventoryItem->id;
                        
                        // Ambil item dan konversi unit
                        $item = \App\Models\Item::with(['smallUnit', 'mediumUnit', 'largeUnit'])->find($itemId);
                        
                        if ($item) {
                            // Ambil cost histories (cost per small unit)
                            $lastCost = \DB::table('food_inventory_cost_histories')
                                ->where('inventory_item_id', $inventoryItemId)
                                ->orderBy('date', 'desc')
                                ->value('new_cost');
                            
                            if ($lastCost) {
                                // Ambil nama unit dari relasi
                                $unitSmall = $item->smallUnit ? $item->smallUnit->name : null;
                                $unitMedium = $item->mediumUnit ? $item->mediumUnit->name : null;
                                $unitLarge = $item->largeUnit ? $item->largeUnit->name : null;
                                $smallConv = $item->small_conversion_qty ?: 1;
                                $mediumConv = $item->medium_conversion_qty ?: 1;
                                
                                // Konversi cost ke unit yang diminta
                                if ($unit == $unitSmall) {
                                    $lastPrice = $lastCost;
                                } elseif ($unit == $unitMedium) {
                                    $lastPrice = $lastCost * $smallConv;
                                } elseif ($unit == $unitLarge) {
                                    $lastPrice = $lastCost * $smallConv * $mediumConv;
                                } else {
                                    $lastPrice = $lastCost; // Default ke small unit
                                }
                            }
                        }
                    }

                    if ($lastPrice && $lastPrice > 0) {
                        $priceDiff = $price - $lastPrice;
                        $priceDiffPercent = ($priceDiff / $lastPrice) * 100;

                        if (abs($priceDiffPercent) > 0) { // Jika ada perubahan harga
                            $priceChanges[] = [
                                'item_name' => $itemName,
                                'last_price' => $lastPrice,
                                'new_price' => $price,
                                'price_diff' => $priceDiff,
                                'price_diff_percent' => $priceDiffPercent,
                                'supplier_name' => $po->supplier->name
                            ];
                        }
                    }

                    // Skip if unit_id is still null
                    if (!$unitId) {
                        \Log::error('Cannot create PO item - unit_id is null:', [
                            'item_name' => $itemName,
                            'item_id' => $itemId,
                            'unit' => $unit
                        ]);
                        continue;
                    }
                    
                    // Debug logging before creating PO item
                    \Log::info('Creating PO item:', [
                        'purchase_order_food_id' => $po->id,
                        'pr_food_item_id' => $prItemId,
                        'item_id' => $itemId,
                        'quantity' => $quantity,
                        'unit_id' => $unitId,
                        'price' => $price,
                        'total' => $total,
                        'arrival_date' => $arrivalDate,
                        'source_type' => $itemData['source'] ?? 'pr_foods',
                        'source_id' => $sourceId,
                        'ro_id' => $itemData['ro_id'] ?? null,
                        'ro_number' => $itemData['ro_number'] ?? null,
                        'is_ro_supplier' => isset($itemData['source']) && $itemData['source'] === 'ro_supplier',
                        'floor_order_item_id' => $prItemId // This should be the ID from food_floor_order_items for RO Supplier
                    ]);

                    // Determine source_id based on source type
                    $sourceId = null;
                    if (isset($itemData['source']) && $itemData['source'] === 'ro_supplier') {
                        // For RO Supplier, source_id should be the RO ID
                        $sourceId = $itemData['ro_id'] ?? null;
                    } else {
                        // For PR Foods, source_id should be the PR ID
                        $sourceId = $itemData['pr_id'] ?? ($prItemId ? \App\Models\PurchaseRequisitionFoodItem::find($prItemId)->pr_food_id : null);
                    }

                    $poItem = PurchaseOrderFoodItem::create([
                        'purchase_order_food_id' => $po->id,
                        'pr_food_item_id' => $prItemId, // This will be food_floor_order_item_id for RO Supplier
                        'item_id' => $itemId,
                        'quantity' => $quantity,
                        'unit_id' => $unitId,
                        'price' => $price,
                        'total' => $total,
                        'created_by' => auth()->id(),
                        'arrival_date' => $arrivalDate,
                        'source_type' => $itemData['source'] ?? 'pr_foods',
                        'source_id' => $sourceId,
                        'ro_id' => $itemData['ro_id'] ?? null,
                        'ro_number' => $itemData['ro_number'] ?? null,
                    ]);

                    // Debug logging after PO item created successfully
                    \Log::info('PO item created successfully:', [
                        'po_item_id' => $poItem->id,
                        'pr_food_item_id' => $poItem->pr_food_item_id,
                        'item_id' => $poItem->item_id,
                        'source_type' => $poItem->source_type,
                        'source_id' => $poItem->source_id,
                        'ro_id' => $poItem->ro_id,
                        'ro_number' => $poItem->ro_number,
                        'is_ro_supplier' => isset($itemData['source']) && $itemData['source'] === 'ro_supplier'
                    ]);

                    // Debug logging after creating PO item
                    \Log::info('PO item created successfully:', [
                        'po_item_id' => $poItem->id,
                        'item_id' => $poItem->item_id,
                        'source_type' => $poItem->source_type
                    ]);
                }

                // Kirim notifikasi jika ada perubahan harga
                if (!empty($priceChanges)) {
                    $notifyUsers = DB::table('users')
                        ->where('id_jabatan', 167)
                        ->where('status', 'A')
                        ->pluck('id');

                    if ($notifyUsers->isNotEmpty()) {
                        $this->sendNotification(
                            $notifyUsers,
                            'price_change',
                            'Perubahan Harga Item',
                            'Ada perubahan harga pada item di PO ' . $po->number,
                            route('po-foods.show', $po->id)
                        );
                    }
                }

                // Update PR status to 'in_po' (only for PR Foods)
                if ($sourceType === 'pr_foods') {
                    foreach ($prIds as $prId) {
                        PurchaseRequisitionFood::where('id', $prId)->update(['status' => 'in_po']);
                    }
                }

                // Update RO Supplier status to 'packing' if all items are in PO
                if ($sourceType === 'ro_supplier') {
                    // Get all RO IDs from this PO
                    $roIds = collect($items)->pluck('ro_id')->unique()->filter();
                    
                    foreach ($roIds as $roId) {
                        // Check if all items from this RO are now in PO
                        $roItems = \DB::table('food_floor_order_items')->where('floor_order_id', $roId)->get();
                        $roItemsInPO = \App\Models\PurchaseOrderFoodItem::where('ro_id', $roId)->get();
                        
                        // If all RO items are in PO, update RO status to 'packing'
                        if ($roItems->count() > 0 && $roItems->count() === $roItemsInPO->count()) {
                            \App\Models\FoodFloorOrder::where('id', $roId)->update(['status' => 'packing']);
                            
                            \Log::info('RO Supplier status updated to packing:', [
                                'ro_id' => $roId,
                                'ro_items_count' => $roItems->count(),
                                'ro_items_in_po_count' => $roItemsInPO->count()
                            ]);
                        }
                    }
                }

                // Log activity
                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'activity_type' => 'create',
                    'module' => 'purchase_order_foods',
                    'description' => 'Create PO Foods: ' . $po->number,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => null,
                    'new_data' => $po->toArray(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PO berhasil dibuat',
                'data' => $createdPOs
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getLastPrice(Request $request)
    {
        try {
            $request->validate([
                'item_id' => 'required|integer',
                'unit' => 'required|string',
            ]);

            // Cari inventory_item_id dari item_id
            $inventoryItem = \DB::table('food_inventory_items')->where('item_id', $request->item_id)->first();
            if (!$inventoryItem) {
                return response()->json([
                    'error' => true,
                    'message' => 'Inventory item tidak ditemukan untuk item_id: ' . $request->item_id
                ], 404);
            }
            $inventoryItemId = $inventoryItem->id;

            // Ambil item dan konversi unit
            $item = \App\Models\Item::with(['smallUnit', 'mediumUnit', 'largeUnit'])->findOrFail($request->item_id);

            // Log item data untuk debugging
            \Log::info('Item data:', ['item' => $item->toArray()]);

            // Cari harga dari PO sebelumnya berdasarkan item_id dan unit
            // Pertama, cari unit_id dari nama unit
            $unit = \App\Models\Unit::where('name', $request->unit)->first();
            
            if ($unit) {
                $poQuery = \DB::table('purchase_order_food_items as pofi')
                    ->join('purchase_order_foods as pof', 'pofi.purchase_order_food_id', '=', 'pof.id')
                    ->where('pofi.item_id', $request->item_id)
                    ->where('pofi.unit_id', $unit->id)
                    ->orderBy('pof.created_at', 'desc');

                $last = (clone $poQuery)->first()?->price;
                $min = (clone $poQuery)->min('price');
                $max = (clone $poQuery)->max('price');

                \Log::info('PO data:', [
                    'item_id' => $request->item_id,
                    'unit' => $request->unit,
                    'unit_id' => $unit->id,
                    'last' => $last,
                    'min' => $min,
                    'max' => $max
                ]);
            } else {
                $last = $min = $max = null;
                \Log::info('Unit not found:', ['unit' => $request->unit]);
            }

            // Jika tidak ada data PO, fallback ke cost histories
            $useCostHistories = false;
            if (!$last) {
                $useCostHistories = true;
                $query = \DB::table('food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->orderBy('date', 'desc');

                $last = (clone $query)->first()?->new_cost;
                $min = (clone $query)->min('new_cost');
                $max = (clone $query)->max('new_cost');

                \Log::info('Fallback to cost histories:', [
                    'last' => $last,
                    'min' => $min,
                    'max' => $max
                ]);
            }

            // Log cost data untuk debugging
            \Log::info('Cost data:', [
                'last' => $last,
                'min' => $min,
                'max' => $max
            ]);

            // Ambil nama unit dari relasi
            $unitSmall = $item->smallUnit ? $item->smallUnit->name : null;
            $unitMedium = $item->mediumUnit ? $item->mediumUnit->name : null;
            $unitLarge = $item->largeUnit ? $item->largeUnit->name : null;
            $smallConv = $item->small_conversion_qty ?: 1;
            $mediumConv = $item->medium_conversion_qty ?: 1;

            // Log unit data untuk debugging
            \Log::info('Unit data:', [
                'small' => $unitSmall,
                'medium' => $unitMedium,
                'large' => $unitLarge,
                'small_conv' => $smallConv,
                'medium_conv' => $mediumConv,
                'requested_unit' => $request->unit
            ]);

            // Konversi harga jika menggunakan cost histories
            $convertCost = function($cost) use ($request, $unitSmall, $unitMedium, $unitLarge, $smallConv, $mediumConv) {
                if ($request->unit == $unitSmall) {
                    return $cost;
                } elseif ($request->unit == $unitMedium) {
                    return $cost * $smallConv;
                } elseif ($request->unit == $unitLarge) {
                    return $cost * $smallConv * $mediumConv;
                }
                return $cost;
            };

            // Jika data dari PO, langsung gunakan tanpa konversi karena sudah sesuai unit
            // Jika data dari cost histories, perlu konversi
            $response = [
                'last_price' => $useCostHistories ? $convertCost($last ?? 0) : ($last ?? 0),
                'min_price' => $useCostHistories ? $convertCost($min ?? 0) : ($min ?? 0),
                'max_price' => $useCostHistories ? $convertCost($max ?? 0) : ($max ?? 0),
                'unit_info' => [
                    'requested_unit' => $request->unit,
                    'available_units' => [
                        'small' => $unitSmall,
                        'medium' => $unitMedium,
                        'large' => $unitLarge
                    ]
                ]
            ];

            \Log::info('Response data:', $response);

            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('Error in getLastPrice:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function approvePurchasingManager(Request $request, $id)
    {
        $po = PurchaseOrderFood::findOrFail($id);
        $updateData = [
            'purchasing_manager_approved_at' => now(),
            'purchasing_manager_approved_by' => Auth::id(),
            'purchasing_manager_note' => $request->note,
        ];
        if (!$request->approved) {
            $updateData['status'] = 'rejected';
        }
        $po->update($updateData);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => $request->approved ? 'approve' : 'reject',
            'module' => 'purchase_order_foods',
            'description' => ($request->approved ? 'Approve' : 'Reject') . ' PO Foods (Purchasing Manager): ' . $po->number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $po->fresh()->toArray(),
        ]);

        if ($request->approved) {
            // Notifikasi ke GM Finance
            $gmFinances = \DB::table('users')->where('id_jabatan', 152)->where('status', 'A')->pluck('id');
            $this->sendNotification(
                $gmFinances,
                'po_approval',
                'Approval PO Foods',
                "PO {$po->number} menunggu approval Anda.",
                route('po-foods.show', $po->id)
            );
        }
        // ... handle notifikasi reject jika perlu
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'PO berhasil diapprove']);
        }
        return redirect()->route('po-foods.show', $po->id);
    }

    public function approveGMFinance(Request $request, $id)
    {
        $po = PurchaseOrderFood::findOrFail($id);
        $updateData = [
            'gm_finance_approved_at' => now(),
            'gm_finance_approved_by' => Auth::id(),
            'gm_finance_note' => $request->note,
        ];
        if ($request->approved) {
            $updateData['status'] = 'approved';
        } else {
            $updateData['status'] = 'rejected';
        }
        $po->update($updateData);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => $request->approved ? 'approve' : 'reject',
            'module' => 'purchase_order_foods',
            'description' => ($request->approved ? 'Approve' : 'Reject') . ' PO Foods (GM Finance): ' . $po->number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $po->fresh()->toArray(),
        ]);
        // ... handle notifikasi jika perlu
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'PO berhasil diapprove']);
        }
        return redirect()->route('po-foods.show', $po->id);
    }

    // Helper untuk insert notifikasi
    private function sendNotification($userIds, $type, $title, $message, $url) {
        $now = now();
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        \DB::table('notifications')->insert($data);
    }

    public function show($id)
    {
        $po = PurchaseOrderFood::with([
            'supplier',
            'creator',
            'items.item',
            'items.unit',
            'purchasing_manager',
            'gm_finance'
        ])->findOrFail($id);

        // Transform items to ensure proper display for both PR Foods and RO Supplier
        $po->items->transform(function ($item) {
            // For RO Supplier items, ensure item_name is available
            if ($item->source_type === 'ro_supplier' && !$item->item) {
                // If item relation is null but we have item_id, try to get item data
                if ($item->item_id) {
                    $itemData = \App\Models\Item::find($item->item_id);
                    if ($itemData) {
                        $item->item = $itemData;
                    }
                }
            }
            
            // Ensure unit information is available
            if (!$item->unit && $item->unit_id) {
                $unitData = \App\Models\Unit::find($item->unit_id);
                if ($unitData) {
                    $item->unit = $unitData;
                }
            }
            
            return $item;
        });

        // Add RO Supplier information if PO is from RO Supplier
        $roSupplierInfo = null;
        if ($po->source_type === 'ro_supplier' && $po->source_id) {
            // Get RO information
            $ro = \App\Models\FoodFloorOrder::with(['outlet', 'warehouseOutlet', 'creator'])
                ->find($po->source_id);
            
            if ($ro) {
                $roSupplierInfo = [
                    'ro_number' => $ro->order_number,
                    'ro_date' => $ro->tanggal,
                    'outlet_name' => $ro->outlet ? $ro->outlet->nama_outlet : 'Unknown Outlet',
                    'warehouse_outlet_name' => $ro->warehouseOutlet ? $ro->warehouseOutlet->name : 'Unknown Warehouse',
                    'ro_creator' => $ro->creator ? $ro->creator->nama_lengkap : 'Unknown User',
                    'ro_description' => $ro->description
                ];
            }
        }

        $user = auth()->user();
        $userData = [
            'id' => $user->id,
            'id_jabatan' => $user->id_jabatan,
            'id_role' => $user->id_role,
            'status' => $user->status,
            'nama_lengkap' => $user->nama_lengkap,
        ];

        return inertia('PurchaseOrder/DetailPurchaseOrderFoods', [
            'po' => $po,
            'roSupplierInfo' => $roSupplierInfo,
            'user' => $userData
        ]);
    }

    public function edit($id)
    {
        $po = PurchaseOrderFood::with([
            'supplier',
            'items.item',
            'items.unit'
        ])->findOrFail($id);

        // Only allow editing if status is draft or approved
        if (!in_array($po->status, ['draft', 'approved'])) {
            return redirect()->route('po-foods.show', $po->id)
                ->with('error', 'PO tidak dapat diedit karena status sudah received');
        }

        // Ambil data supplier dari tabel suppliers
        $suppliers = \App\Models\Supplier::whereIn('status', ['A', 'active'])->get(['id', 'name']);

        return inertia('PurchaseOrder/EditPurchaseOrderFoods', [
            'po' => $po,
            'suppliers' => $suppliers,
        ]);
    }

    public function update(Request $request, $id)
    {
        $po = PurchaseOrderFood::findOrFail($id);

        // Only allow updating if status is draft or approved
        if (!in_array($po->status, ['draft', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'PO tidak dapat diupdate karena status sudah received'
            ], 422);
        }

        $request->validate([
            'notes' => 'nullable|string',
            'ppn_enabled' => 'boolean',
            'items' => 'required|array',
            'items.*.id' => 'nullable|exists:purchase_order_food_items,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'new_items' => 'nullable|array',
            'new_items.*.item.id' => 'required|exists:items,id',
            'new_items.*.quantity' => 'required|numeric|min:0',
            'new_items.*.price' => 'required|numeric|min:0',
            'deleted_items' => 'nullable|array',
            'deleted_items.*' => 'exists:purchase_order_food_items,id',
        ]);

        try {
            DB::beginTransaction();

            // Handle deleted items
            if ($request->deleted_items) {
                PurchaseOrderFoodItem::whereIn('id', $request->deleted_items)->delete();
            }

            // Update existing items
            foreach ($request->items as $item) {
                if ($item['id']) {
                    PurchaseOrderFoodItem::where('id', $item['id'])
                        ->update([
                            'price' => $item['price'],
                            'total' => $item['total'],
                        ]);
                }
            }

            // Add new items
            if ($request->new_items) {
                foreach ($request->new_items as $newItem) {
                    // Get item details
                    $item = Item::find($newItem['item']['id']);
                    
                    PurchaseOrderFoodItem::create([
                        'purchase_order_food_id' => $po->id,
                        'item_id' => $item->id,
                        'quantity' => $newItem['quantity'],
                        'price' => $newItem['price'],
                        'total' => round(floatval($newItem['quantity']) * floatval($newItem['price']), 2),
                        'unit_id' => $item->small_unit_id,
                        'created_by' => auth()->id(),
                        'pr_food_item_id' => $newItem['pr_food_item_id'] ?? null, // Reference to PR item if available
                    ]);
                }
            }

            // Recalculate totals
            $allItems = PurchaseOrderFoodItem::where('purchase_order_food_id', $po->id)->get();
            $subtotal = $allItems->sum('total');
            
            // Calculate PPN if enabled
            $ppnAmount = 0;
            $grandTotal = $subtotal;
            if ($request->ppn_enabled) {
                $ppnAmount = $subtotal * 0.11; // 11% PPN
                $grandTotal = $subtotal + $ppnAmount;
            }

            // Update PO notes and PPN
            $po->update([
                'notes' => $request->notes,
                'ppn_enabled' => $request->ppn_enabled ?? false,
                'ppn_amount' => $ppnAmount,
                'subtotal' => $subtotal,
                'grand_total' => $grandTotal,
            ]);

            // Log activity
            ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'update',
                'module' => 'purchase_order_foods',
                'description' => 'Update PO Foods: ' . $po->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $po->fresh()->toArray(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PO berhasil diupdate'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate PO: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $po = PurchaseOrderFood::with('items')->findOrFail($id);
        if (!in_array($po->status, ['draft', 'approved'])) {
            return back()->with('error', 'PO hanya bisa dihapus jika status draft atau approved');
        }
        try {
            \DB::beginTransaction();
            // Hapus item
            foreach ($po->items as $item) {
                $item->delete();
            }
            $po->delete();
            // Log activity
            ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'purchase_order_foods',
                'description' => 'Hapus PO Foods: ' . $po->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => $po->toArray(),
                'new_data' => null,
            ]);
            \DB::commit();
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'PO berhasil dihapus']);
            }
            return redirect()->route('po-foods.index')->with('success', 'PO berhasil dihapus');
        } catch (\Exception $e) {
            \DB::rollBack();
            return back()->with('error', 'Gagal menghapus PO: ' . $e->getMessage());
        }
    }

    public function markPrinted($id)
    {
        $po = PurchaseOrderFood::findOrFail($id);
        $po->printed_at = now();
        $po->save();
        return response()->json(['success' => true, 'printed_at' => $po->printed_at]);
    }

    // Get PO yang pending GM Finance approval
    public function getPendingGMFINANCEPOs(Request $request)
    {
        $query = PurchaseOrderFood::with(['supplier', 'creator', 'items.item', 'items.unit', 'purchasing_manager'])
            ->where('status', 'draft')
            ->whereNotNull('purchasing_manager_approved_at')
            ->whereNull('gm_finance_approved_at')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', '%' . $search . '%')
                  ->orWhereHas('supplier', function($supplierQuery) use ($search) {
                      $supplierQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('date', '<=', $request->to);
        }

        $pos = $query->get()
            ->map(function ($po) {
                // Transform items to ensure proper display for both PR Foods and RO Supplier
                $po->items->transform(function ($item) {
                    // For RO Supplier items, ensure item_name is available
                    if ($item->source_type === 'ro_supplier' && !$item->item) {
                        // If item relation is null but we have item_id, try to get item data
                        if ($item->item_id) {
                            $itemData = \App\Models\Item::find($item->item_id);
                            if ($itemData) {
                                $item->item = $itemData;
                            }
                        }
                    }
                    
                    // Ensure unit information is available
                    if (!$item->unit && $item->unit_id) {
                        $unitData = \App\Models\Unit::find($item->unit_id);
                        if ($unitData) {
                            $item->unit = $unitData;
                        }
                    }
                    
                    return $item;
                });

                // Get source numbers based on source type
                if ($po->source_type === 'pr_foods' || !$po->source_type) {
                    $prItemIds = $po->items->pluck('pr_food_item_id')->toArray();
                    $prIds = \App\Models\PurchaseRequisitionFoodItem::whereIn('id', $prItemIds)->pluck('pr_food_id')->unique()->toArray();
                    $prNumbers = \App\Models\PurchaseRequisitionFood::whereIn('id', $prIds)->pluck('pr_number')->unique()->toArray();
                    $po->pr_numbers = $prNumbers;
                    
                    // Ambil warehouse dari PR pertama untuk stock fetching
                    if (!empty($prIds)) {
                        $firstPR = \App\Models\PurchaseRequisitionFood::find($prIds[0]);
                        $po->warehouse_outlet_id = $firstPR ? $firstPR->warehouse_id : null;
                    }
                } elseif ($po->source_type === 'ro_supplier') {
                    $roNumbers = $po->items->pluck('ro_number')->unique()->filter()->toArray();
                    $po->pr_numbers = $roNumbers; // Reuse pr_numbers field for RO numbers
                    
                    // For RO Supplier, we need to determine warehouse differently
                    // You might need to adjust this based on your business logic
                    $po->warehouse_outlet_id = null; // Set appropriate warehouse for RO Supplier
                }
                
                return $po;
            });

        return response()->json($pos);
    }
} 