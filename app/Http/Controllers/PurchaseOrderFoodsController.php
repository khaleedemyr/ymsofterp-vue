<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequisitionFood;
use App\Models\PurchaseOrderFood;
use App\Models\PurchaseOrderFoodItem;
use App\Models\PurchaseRequisitionFoodItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderFoodsController extends Controller
{
    public function index()
    {
        $query = PurchaseOrderFood::with(['supplier', 'creator', 'items'])
            ->orderBy('created_at', 'desc');

        if (request('search')) {
            $query->where('number', 'like', '%' . request('search') . '%');
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

        $purchaseOrders = $query->paginate(10)->withQueryString();
        $purchaseOrders->getCollection()->transform(function ($po) {
            $prItemIds = $po->items->pluck('pr_food_item_id')->toArray();
            $prIds = \App\Models\PurchaseRequisitionFoodItem::whereIn('id', $prItemIds)->pluck('pr_food_id')->unique()->toArray();
            $prNumbers = \App\Models\PurchaseRequisitionFood::whereIn('id', $prIds)->pluck('pr_number')->unique()->toArray();
            $po->pr_numbers = $prNumbers;
            return $po;
        });

        return inertia('PurchaseOrder/PurchaseOrderFoods', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => request()->only(['search', 'status', 'from', 'to']),
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
            }, 'items.item'])
            ->get()
            ->map(function ($pr) {
                return [
                    'id' => $pr->id,
                    'number' => $pr->pr_number,
                    'date' => \Carbon\Carbon::parse($pr->tanggal)->format('d/m/Y'),
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

            // Group items by supplier_id and arrival_date
            $groupedItems = [];
            foreach ($request->items_by_supplier as $supplierId => $items) {
                foreach ($items as $item) {
                    $prItem = PurchaseRequisitionFoodItem::findOrFail($item['id']);
                    $arrivalDate = $prItem->arrival_date;
                    
                    // Create unique key combining supplier_id and arrival_date
                    $key = $supplierId . '_' . $arrivalDate;
                    
                    if (!isset($groupedItems[$key])) {
                        $groupedItems[$key] = [
                            'supplier_id' => $supplierId,
                            'arrival_date' => $arrivalDate,
                            'items' => []
                        ];
                    }
                    
                    $groupedItems[$key]['items'][] = $item;
                }
            }

            // Create PO for each group
            foreach ($groupedItems as $group) {
                // Generate PO number
                $poNumber = 'PO-F/' . date('Ymd') . '/' . str_pad(PurchaseOrderFood::whereDate('created_at', Carbon::today())->count() + 1, 4, '0', STR_PAD_LEFT);

                // Create PO
                $po = PurchaseOrderFood::create([
                    'number' => $poNumber,
                    'date' => Carbon::now(),
                    'supplier_id' => $group['supplier_id'],
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                    'notes' => $request->notes ?? null,
                    'arrival_date' => $group['arrival_date'],
                ]);

                // Create PO items and check price changes
                $priceChanges = [];
                foreach ($group['items'] as $item) {
                    $prItem = PurchaseRequisitionFoodItem::findOrFail($item['id']);
                    $quantity = $item['qty'];
                    $price = $item['price'];
                    $total = $quantity * $price;

                    // Cari unit_id berdasarkan nama unit
                    $unitId = null;
                    if ($prItem->unit) {
                        $unitId = \App\Models\Unit::where('name', $prItem->unit)->value('id');
                    }
                    if (!$unitId) {
                        throw new \Exception('Unit ID tidak ditemukan untuk unit: ' . $prItem->unit);
                    }

                    // Cek harga terakhir dari PO sebelumnya
                    $lastPOItem = PurchaseOrderFoodItem::where('item_id', $prItem->item_id)
                        ->whereHas('purchaseOrder', function($q) use ($group) {
                            $q->where('supplier_id', $group['supplier_id'])
                              ->where('status', '!=', 'draft')
                              ->orderBy('created_at', 'desc');
                        })
                        ->latest()
                        ->first();

                    if ($lastPOItem) {
                        $lastPrice = $lastPOItem->price;
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

                    foreach ($notifyUsers as $userId) {
                        $message = "Perubahan harga pada PO {$po->number}:\n\n";
                        foreach ($priceChanges as $change) {
                            $direction = $change['price_diff'] > 0 ? 'naik' : 'turun';
                            $message .= "Item: {$change['item_name']}\n";
                            $message .= "Supplier: {$change['supplier_name']}\n";
                            $message .= "Harga lama: " . number_format($change['last_price'], 2) . "\n";
                            $message .= "Harga baru: " . number_format($change['new_price'], 2) . "\n";
                            $message .= "Perubahan: {$direction} " . number_format(abs($change['price_diff_percent']), 2) . "%\n\n";
                        }

                        DB::table('notifications')->insert([
                            'user_id' => $userId,
                            'type' => 'price_change',
                            'title' => 'Perubahan Harga PO',
                            'message' => $message,
                            'url' => '/po-foods/' . $po->id,
                            'is_read' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Update PR status to 'po' for all processed PRs
            PurchaseRequisitionFood::whereIn('id', $prIds)
                ->update(['status' => 'po']);

            // Log activity
            ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'module' => 'purchase_order_foods',
                'description' => 'Create PO Foods from PR: ' . implode(', ', $prIds->toArray()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => [
                    'pr_ids' => $prIds,
                    'status' => 'po'
                ],
            ]);

            DB::commit();
            return response()->json(['message' => 'PO generated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
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
            'items' => 'required|array',
            'items.*.id' => 'required|exists:purchase_order_food_items,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Update PO notes
            $po->update([
                'notes' => $request->notes
            ]);

            // Update items
            foreach ($request->items as $item) {
                PurchaseOrderFoodItem::where('id', $item['id'])
                    ->update([
                        'price' => $item['price'],
                        'total' => $item['total'],
                        'subtotal' => $item['total']
                    ]);
            }

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
} 