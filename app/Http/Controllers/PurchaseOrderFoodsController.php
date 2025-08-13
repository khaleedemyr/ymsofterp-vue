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
            $prItemIds = $po->items->pluck('pr_food_item_id')->toArray();
            $prIds = \App\Models\PurchaseRequisitionFoodItem::whereIn('id', $prItemIds)->pluck('pr_food_id')->unique()->toArray();
            $prNumbers = \App\Models\PurchaseRequisitionFood::whereIn('id', $prIds)->pluck('pr_number')->unique()->toArray();
            $po->pr_numbers = $prNumbers;
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
        $request->validate([
            'items_by_supplier' => 'required|array',
            'items_by_supplier.*' => 'required|array',
            'items_by_supplier.*.*.id' => 'required|exists:pr_food_items,id',
            'items_by_supplier.*.*.supplier_id' => 'required|exists:suppliers,id',
            'items_by_supplier.*.*.price' => 'required|numeric|min:0',
            'ppn_enabled' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Collect all PR IDs that will be processed
            $prIds = collect($request->items_by_supplier)
                ->flatMap(function ($items) {
                    return collect($items)->pluck('pr_id');
                })
                ->unique()
                ->values();

            // Group items by supplier
            $itemsBySupplier = collect($request->items_by_supplier)
                ->map(function ($items, $supplierId) {
                    return collect($items)->map(function ($item) use ($supplierId) {
                        return [
                            'pr_item_id' => $item['id'],
                            'supplier_id' => $supplierId,
                            'price' => $item['price'],
                            'qty' => $item['qty'],
                            'pr_id' => $item['pr_id'] ?? null,
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
                ]);

                $createdPOs[] = $po;

                // Get PR items for this supplier
                $prItemIds = collect($items)->pluck('pr_item_id');
                $prItems = PurchaseRequisitionFoodItem::with('item')
                    ->whereIn('id', $prItemIds)
                    ->get();

                $priceChanges = [];

                foreach ($items as $itemData) {
                    $prItem = $prItems->firstWhere('id', $itemData['pr_item_id']);
                    if (!$prItem) continue;

                    $quantity = $itemData['qty'];
                    $price = $itemData['price'];
                    $total = $quantity * $price;

                    // Get unit ID
                    $unitId = null;
                    if ($prItem->unit) {
                        $unit = Unit::where('name', $prItem->unit)->first();
                        $unitId = $unit ? $unit->id : null;
                    }

                    // Check for price changes using the same logic as getLastPrice
                    $lastPrice = null;
                    
                    // Cari inventory_item_id dari item_id
                    $inventoryItem = \DB::table('food_inventory_items')->where('item_id', $prItem->item_id)->first();
                    if ($inventoryItem) {
                        $inventoryItemId = $inventoryItem->id;
                        
                        // Ambil item dan konversi unit
                        $item = \App\Models\Item::with(['smallUnit', 'mediumUnit', 'largeUnit'])->find($prItem->item_id);
                        
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
                                if ($prItem->unit == $unitSmall) {
                                    $lastPrice = $lastCost;
                                } elseif ($prItem->unit == $unitMedium) {
                                    $lastPrice = $lastCost * $smallConv;
                                } elseif ($prItem->unit == $unitLarge) {
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
                                'item_name' => $prItem->item->name,
                                'last_price' => $lastPrice,
                                'new_price' => $price,
                                'price_diff' => $priceDiff,
                                'price_diff_percent' => $priceDiffPercent,
                                'supplier_name' => $po->supplier->name
                            ];
                        }
                    }

                    PurchaseOrderFoodItem::create([
                        'purchase_order_food_id' => $po->id,
                        'pr_food_item_id' => $prItem->id,
                        'item_id' => $prItem->item_id,
                        'quantity' => $quantity,
                        'unit_id' => $unitId,
                        'price' => $price,
                        'total' => $total,
                        'created_by' => auth()->id(),
                        'arrival_date' => $prItem->arrival_date,
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

                // Update PR status to 'in_po'
                foreach ($prIds as $prId) {
                    PurchaseRequisitionFood::where('id', $prId)->update(['status' => 'in_po']);
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

            // Ambil cost histories (cost per small unit)
            $query = \DB::table('food_inventory_cost_histories')
                ->where('inventory_item_id', $inventoryItemId)
                ->orderBy('date', 'desc');

            $last = (clone $query)->first()?->new_cost;
            $min = (clone $query)->min('new_cost');
            $max = (clone $query)->max('new_cost');

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

            $response = [
                'last_price' => $convertCost($last ?? 0),
                'min_price' => $convertCost($min ?? 0),
                'max_price' => $convertCost($max ?? 0),
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
            'new_items.*.quantity' => 'required|numeric|min:1',
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
                        'total' => $newItem['quantity'] * $newItem['price'],
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
                $prItemIds = $po->items->pluck('pr_food_item_id')->toArray();
                $prIds = \App\Models\PurchaseRequisitionFoodItem::whereIn('id', $prItemIds)->pluck('pr_food_id')->unique()->toArray();
                $prNumbers = \App\Models\PurchaseRequisitionFood::whereIn('id', $prIds)->pluck('pr_number')->unique()->toArray();
                $po->pr_numbers = $prNumbers;
                
                // Ambil warehouse dari PR pertama untuk stock fetching
                if (!empty($prIds)) {
                    $firstPR = \App\Models\PurchaseRequisitionFood::find($prIds[0]);
                    $po->warehouse_outlet_id = $firstPR ? $firstPR->warehouse_id : null;
                }
                
                return $po;
            });

        return response()->json($pos);
    }
} 