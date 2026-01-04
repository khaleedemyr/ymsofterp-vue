<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FoodPackingList;
use App\Models\FoodFloorOrder;
use App\Models\WarehouseDivision;
use App\Models\FoodPackingListItem;
use Maatwebsite\Excel\Facades\Excel;

class PackingListController extends Controller
{
    /**
     * Helper method untuk membuat pagination default
     */
    private function getEmptyPagination()
    {
        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 15,
            'total' => 0,
            'from' => null,
            'to' => null,
            'links' => []
        ];
    }
    public function index(Request $request)
    {
        $user = auth()->user()->load('outlet');
        
        // Simpan filter di session untuk persist
        if ($request->hasAny(['search', 'date_from', 'date_to', 'status', 'load_data', 'per_page'])) {
            session([
                'packing_list_filters' => [
                    'search' => $request->search,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'status' => $request->status,
                    'load_data' => $request->load_data,
                    'per_page' => $request->per_page
                ]
            ]);
        }
        
        // Ambil filter dari session jika ada
        $filters = session('packing_list_filters', []);
        $search = $request->search ?? $filters['search'] ?? '';
        $dateFrom = $request->date_from ?? $filters['date_from'] ?? '';
        $dateTo = $request->date_to ?? $filters['date_to'] ?? '';
        $status = $request->status ?? $filters['status'] ?? '';
        $loadData = $request->load_data ?? $filters['load_data'] ?? '';
        $perPage = $request->per_page ?? $filters['per_page'] ?? 15;
        
        // OPTIMIZED: Tidak load data otomatis, hanya load jika ada filter
        $packingLists = null;
        
        if ($loadData === '1') {
            $query = FoodPackingList::with([
                'warehouseDivision',
                'floorOrder:id,order_number,id_outlet,user_id',
                'creator:id,nama_lengkap',
                'warehouseDivision:id,name',
                'items',
                'floorOrder.outlet:id_outlet,nama_outlet',
                'floorOrder.requester:id,nama_lengkap',
            ]);
            
            // Apply filters if provided
            if (!empty($search)) {
                $searchTerm = '%' . $search . '%';
                $query->where(function($q) use ($searchTerm) {
                    $q->where('packing_number', 'like', $searchTerm)
                      ->orWhereHas('floorOrder', function($subQ) use ($searchTerm) {
                          $subQ->where('order_number', 'like', $searchTerm);
                      })
                      ->orWhereHas('creator', function($subQ) use ($searchTerm) {
                          $subQ->where('nama_lengkap', 'like', $searchTerm);
                      })
                      ->orWhereHas('warehouseDivision', function($subQ) use ($searchTerm) {
                          $subQ->where('name', 'like', $searchTerm);
                      })
                      ->orWhereHas('floorOrder.outlet', function($subQ) use ($searchTerm) {
                          $subQ->where('nama_outlet', 'like', $searchTerm);
                      });
                });
            }
            
            if (!empty($dateFrom)) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            
            if (!empty($dateTo)) {
                $query->whereDate('created_at', '<=', $dateTo);
            }
            
            if (!empty($status)) {
                $query->where('status', $status);
            }
            
            $packingLists = $query->with([
                'warehouseDivision',
                'floorOrder:id,order_number,id_outlet,user_id',
                'creator:id,nama_lengkap',
                'warehouseDivision:id,name',
                'items',
                'floorOrder.outlet:id_outlet,nama_outlet',
                'floorOrder.requester:id,nama_lengkap',
            ])->orderByDesc('created_at')->paginate($perPage)->withQueryString();
        }

        return inertia('PackingList/Index', [
            'user' => $user,
            'packingLists' => $packingLists
                ? $packingLists->through(function($pl) {
                    return [
                        'id' => $pl->id,
                        'packing_number' => $pl->packing_number,
                        'created_at' => $pl->created_at,
                        'created_at_format' => $pl->created_at ? $pl->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') : null,
                        'fo_number' => $pl->floorOrder?->order_number,
                        'destination_outlet' => $pl->floorOrder?->outlet,
                        'requester' => $pl->floorOrder?->requester,
                        'warehouse_division' => $pl->warehouseDivision,
                        'floor_order' => $pl->floorOrder,
                        'creator' => $pl->creator,
                        'status' => $pl->status,
                        'items' => $pl->items,
                    ];
                })
                : [],
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $status,
                'load_data' => $loadData,
                'per_page' => $perPage
            ],
        ]);
    }

    public function clearFilters()
    {
        session()->forget('packing_list_filters');
        return redirect()->route('packing-list.index');
    }

    /**
     * API endpoint untuk mendapatkan list packing list (untuk mobile app)
     */
    public function apiIndex(Request $request)
    {
        try {
            $search = $request->search ?? '';
            $dateFrom = $request->date_from ?? '';
            $dateTo = $request->date_to ?? '';
            $status = $request->status ?? '';
            $perPage = $request->per_page ?? 15;
            $page = $request->page ?? 1;
            
            $query = FoodPackingList::with([
                'warehouseDivision',
                'floorOrder:id,order_number,id_outlet,user_id',
                'creator:id,nama_lengkap',
                'warehouseDivision:id,name',
                'items',
                'floorOrder.outlet:id_outlet,nama_outlet',
                'floorOrder.requester:id,nama_lengkap',
            ]);
            
            // Apply filters if provided
            if (!empty($search)) {
                $searchTerm = '%' . $search . '%';
                $query->where(function($q) use ($searchTerm) {
                    $q->where('packing_number', 'like', $searchTerm)
                      ->orWhereHas('floorOrder', function($subQ) use ($searchTerm) {
                          $subQ->where('order_number', 'like', $searchTerm);
                      })
                      ->orWhereHas('creator', function($subQ) use ($searchTerm) {
                          $subQ->where('nama_lengkap', 'like', $searchTerm);
                      })
                      ->orWhereHas('warehouseDivision', function($subQ) use ($searchTerm) {
                          $subQ->where('name', 'like', $searchTerm);
                      })
                      ->orWhereHas('floorOrder.outlet', function($subQ) use ($searchTerm) {
                          $subQ->where('nama_outlet', 'like', $searchTerm);
                      });
                });
            }
            
            if (!empty($dateFrom)) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            
            if (!empty($dateTo)) {
                $query->whereDate('created_at', '<=', $dateTo);
            }
            
            if (!empty($status)) {
                $query->where('status', $status);
            }
            
            $packingLists = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'props' => [
                    'packingLists' => [
                        'data' => $packingLists->map(function($pl) {
                            return [
                                'id' => $pl->id,
                                'packing_number' => $pl->packing_number,
                                'created_at' => $pl->created_at,
                                'created_at_format' => $pl->created_at ? $pl->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') : null,
                                'fo_number' => $pl->floorOrder?->order_number,
                                'destination_outlet' => $pl->floorOrder?->outlet,
                                'requester' => $pl->floorOrder?->requester,
                                'warehouse_division' => $pl->warehouseDivision,
                                'floor_order' => $pl->floorOrder,
                                'creator' => $pl->creator,
                                'status' => $pl->status,
                                'items' => $pl->items,
                            ];
                        }),
                        'current_page' => $packingLists->currentPage(),
                        'last_page' => $packingLists->lastPage(),
                        'per_page' => $packingLists->perPage(),
                        'total' => $packingLists->total(),
                        'from' => $packingLists->firstItem(),
                        'to' => $packingLists->lastItem(),
                        'links' => $packingLists->linkCollection(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function create(Request $request)
    {
        // OPTIMIZED: Gunakan raw query untuk performa maksimal
        // Query langsung di database untuk filter FO yang masih punya item belum di-packing
        $query = "
            SELECT DISTINCT fo.id
            FROM food_floor_orders fo
            JOIN food_floor_order_items foi ON fo.id = foi.floor_order_id
            JOIN items i ON foi.item_id = i.id
            LEFT JOIN food_packing_list_items pli ON foi.id = pli.food_floor_order_item_id
            LEFT JOIN food_packing_lists pl ON pli.packing_list_id = pl.id 
                AND pl.food_floor_order_id = fo.id 
                AND pl.status = 'packing'
            WHERE fo.status IN ('approved', 'packing')
            AND fo.fo_mode != 'RO Supplier'
        ";
        
        $params = [];
        
        // Filter berdasarkan tanggal kedatangan jika ada
        if ($request->filled('arrival_date')) {
            $query .= " AND DATE(food_floor_orders.arrival_date) = ?";
            $params[] = $request->arrival_date;
        }
        
        $query .= " AND pli.id IS NULL GROUP BY fo.id";

        // Query untuk mendapatkan FO yang masih punya item belum di-packing
        $floorOrderIds = \DB::select($query, $params);
        $foIds = array_column($floorOrderIds, 'id');
        
        // Jika tidak ada FO yang perlu di-packing, return empty
        if (empty($foIds)) {
            $warehouseDivisions = \App\Models\WarehouseDivision::all();
            return inertia('PackingList/Form', [
                'floorOrders' => [],
                'warehouseDivisions' => $warehouseDivisions,
            ]);
        }

        // OPTIMIZED: Ambil hanya FO yang relevan dengan eager loading minimal
        $query = FoodFloorOrder::whereIn('food_floor_orders.id', $foIds)
            ->with([
                'outlet:id_outlet,nama_outlet', 
                'user:id,nama_lengkap', 
                'items' => function($q) {
                    $q->select('id', 'floor_order_id', 'item_id', 'qty', 'unit')
                      ->with(['item:id,name,warehouse_division_id']);
                },
                'warehouseDivisions:id,name,warehouse_id', 
                'warehouseOutlet:id,name'
            ]);

        $floorOrders = $query->orderBy('food_floor_orders.tanggal', 'desc')
            ->get()
            ->sortBy(function($fo) {
                return $fo->outlet ? $fo->outlet->nama_outlet : '';
            })
            ->values();
        
        // OPTIMIZED: Batch query untuk cek packed items (lebih efisien)
        $packedItems = $this->getPackedItemsBatchOptimized($foIds);

        // Filter FO yang masih memiliki item yang belum di-packing untuk setiap warehouse division
        // OPTIMIZED: Gunakan collection methods yang lebih efisien
        $floorOrders = $floorOrders->filter(function($fo) use ($packedItems) {
            $foDivisions = $fo->warehouseDivisions->pluck('id')->toArray();
            
            foreach ($foDivisions as $divisionId) {
                $itemsInDivision = $fo->items->filter(function($item) use ($divisionId) {
                    return $item->item && $item->item->warehouse_division_id == $divisionId;
                });
                
                if ($itemsInDivision->count() > 0) {
                    // OPTIMIZED: Gunakan data yang sudah di-batch query
                    $packedItemIds = $packedItems
                        ->where('food_floor_order_id', $fo->id)
                        ->where('warehouse_division_id', $divisionId)
                        ->pluck('food_floor_order_item_id')
                        ->toArray();
                    
                    if ($itemsInDivision->whereNotIn('id', $packedItemIds)->count() > 0) {
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

    /**
     * OPTIMIZED: Batch query untuk mendapatkan packed items
     * Menggantikan N+1 queries dengan 1 query menggunakan join langsung
     */
    private function getPackedItemsBatch($floorOrderIds)
    {
        if (empty($floorOrderIds)) {
            return collect([]);
        }
        
        return FoodPackingListItem::whereHas('packingList', function($q) use ($floorOrderIds) {
            $q->whereIn('food_floor_order_id', $floorOrderIds)
              ->where('status', 'packing');
        })
        ->with('packingList:id,food_floor_order_id,warehouse_division_id')
        ->get()
        ->map(function($item) {
            return [
                'food_floor_order_id' => $item->packingList->food_floor_order_id,
                'warehouse_division_id' => $item->packingList->warehouse_division_id,
                'food_floor_order_item_id' => $item->food_floor_order_item_id
            ];
        });
    }

    /**
     * OPTIMIZED VERSION: Batch query menggunakan raw query untuk performa maksimal
     */
    private function getPackedItemsBatchOptimized($floorOrderIds)
    {
        if (empty($floorOrderIds)) {
            return collect([]);
        }
        
        $placeholders = implode(',', array_fill(0, count($floorOrderIds), '?'));
        
        $results = \DB::select("
            SELECT 
                pl.food_floor_order_id,
                pl.warehouse_division_id,
                pli.food_floor_order_item_id
            FROM food_packing_list_items pli
            JOIN food_packing_lists pl ON pli.packing_list_id = pl.id
            WHERE pl.food_floor_order_id IN ({$placeholders})
            AND pl.status = 'packing'
        ", $floorOrderIds);
        
        return collect($results)->map(function($row) {
            return [
                'food_floor_order_id' => $row->food_floor_order_id,
                'warehouse_division_id' => $row->warehouse_division_id,
                'food_floor_order_item_id' => $row->food_floor_order_item_id
            ];
        });
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
                // Log removed for performance
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
        \DB::beginTransaction();
        try {
            $packingList = FoodPackingList::with(['items', 'floorOrder'])->findOrFail($id);
            
            // Cek apakah status masih 'packing' (bisa dihapus)
            if ($packingList->status !== 'packing') {
                return response()->json([
                    'success' => false, 
                    'message' => 'Packing List tidak dapat dihapus karena status bukan "packing"',
                    'error' => 'Packing List tidak dapat dihapus karena status bukan "packing"'
                ], 400);
            }
            
            // Hapus semua item packing list
            $packingList->items()->delete();
            
            // Hapus packing list
            $packingList->delete();
            
            // Update status floor order jika perlu
            $floorOrder = $packingList->floorOrder;
            if ($floorOrder) {
                // Cek apakah masih ada packing list lain untuk floor order ini
                $remainingPackingLists = FoodPackingList::where('food_floor_order_id', $floorOrder->id)
                    ->where('status', 'packing')
                    ->count();
                
                // Jika tidak ada packing list lagi, kembalikan status ke 'approved'
                if ($remainingPackingLists === 0) {
                    $floorOrder->update(['status' => 'approved']);
                }
            }
            
            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'packing_list',
                'description' => 'Menghapus Packing List: ' . $packingList->packing_number,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($packingList->toArray()),
                'new_data' => null,
                'created_at' => now(),
            ]);
            
            \DB::commit();
            
            return response()->json([
                'success' => true, 
                'message' => 'Packing List berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error deleting packing list: ' . $e->getMessage(), [
                'packing_list_id' => $id,
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menghapus Packing List: ' . $e->getMessage(),
                'error' => 'Gagal menghapus Packing List: ' . $e->getMessage()
            ], 500);
        }
    }

    public function availableItems(Request $request)
    {
        $foId = $request->input('fo_id');
        $divisionId = $request->input('division_id');
        $division = \App\Models\WarehouseDivision::find($divisionId);
        $warehouse_id = $division ? $division->warehouse_id : null;
        
        // OPTIMIZED: Single query untuk semua data dengan eager loading
        $foItems = \App\Models\FoodFloorOrderItem::where('floor_order_id', $foId)
            ->whereHas('item', function($q) use ($divisionId) {
                $q->where('warehouse_division_id', $divisionId);
            })
            ->with(['item.smallUnit', 'item.mediumUnit', 'item.largeUnit', 'item.category'])
            ->get();
            
        // OPTIMIZED: Single query untuk packed items
        $packedItemIds = \App\Models\FoodPackingListItem::whereHas('packingList', function($q) use ($foId, $divisionId) {
            $q->where('food_floor_order_id', $foId)
              ->where('warehouse_division_id', $divisionId)
              ->where('status', 'packing');
        })->pluck('food_floor_order_item_id');
        
        // Filter hanya item yang belum pernah di-packing
        $itemsToPack = $foItems->whereNotIn('id', $packedItemIds)->values();
        
        // OPTIMIZED: Batch query untuk stock data
        $itemStocks = [];
        if ($warehouse_id && $itemsToPack->count() > 0) {
            $itemIds = $itemsToPack->pluck('item_id')->unique()->values();
            
            // Single query untuk inventory items
            $inventoryItems = \DB::table('food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->get()->keyBy('item_id');
                
            if ($inventoryItems->count() > 0) {
                $inventoryItemIds = $inventoryItems->pluck('id')->unique()->values();
                
                // Single query untuk stocks
                $stocks = \DB::table('food_inventory_stocks')
                    ->whereIn('inventory_item_id', $inventoryItemIds)
                    ->where('warehouse_id', $warehouse_id)
                    ->get()->keyBy('inventory_item_id');
                
                // Process stock data
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

        // OPTIMIZED: Gunakan raw query untuk performa maksimal
        $summaryData = \DB::select("
            SELECT 
                wd.name as warehouse_division_name,
                i.id as item_id,
                i.name as item_name,
                foi.unit,
                SUM(foi.qty) as total_qty
            FROM food_floor_orders fo
            JOIN food_floor_order_items foi ON fo.id = foi.floor_order_id
            JOIN items i ON foi.item_id = i.id
            JOIN warehouse_division wd ON i.warehouse_division_id = wd.id
            LEFT JOIN food_packing_list_items pli ON foi.id = pli.food_floor_order_item_id
            LEFT JOIN food_packing_lists pl ON pli.packing_list_id = pl.id AND pl.status = 'packing'
            WHERE fo.status IN ('approved', 'packing')
            AND DATE(fo.tanggal) = ?
            AND pli.id IS NULL
            GROUP BY wd.name, i.id, i.name, foi.unit
            ORDER BY wd.name, i.name
        ", [$request->tanggal]);

        // Group by division
        $summaryByDivision = [];
        foreach ($summaryData as $row) {
            if (!isset($summaryByDivision[$row->warehouse_division_name])) {
                $summaryByDivision[$row->warehouse_division_name] = [
                    'warehouse_division_name' => $row->warehouse_division_name,
                    'items' => []
                ];
            }
            
            $summaryByDivision[$row->warehouse_division_name]['items'][] = [
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'total_qty' => $row->total_qty,
                'unit' => $row->unit,
            ];
        }

        return response()->json([
            'divisions' => array_values($summaryByDivision)
        ]);
    }

    public function unpickedFloorOrders(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        // Get all floor orders that are approved or packing but not yet fully packed
        // Exclude RO Supplier from unpicked floor orders
        $floorOrders = FoodFloorOrder::whereIn('status', ['approved', 'packing'])
            ->where('fo_mode', '!=', 'RO Supplier') // Filter out RO Supplier
            ->whereDate('tanggal', $request->tanggal)
            ->with([
                'outlet',
                'requester',
                'warehouseDivisions',
                'warehouseOutlet',
                'items.item',
                'items.item.smallUnit',
                'items.item.mediumUnit',
                'items.item.largeUnit',
                'items.item.warehouseDivision'
            ])
            ->get();

        $result = [];
        foreach ($floorOrders as $fo) {
            $outletKey = $fo->outlet->nama_outlet;
            
            if (!isset($result[$outletKey])) {
                $result[$outletKey] = [
                    'outlet_name' => $outletKey,
                    'tanggal' => $fo->tanggal,
                    'warehouse_outlets' => []
                ];
            }

            // Group by warehouse outlet
            $warehouseOutletKey = $fo->warehouseOutlet ? $fo->warehouseOutlet->name : 'Default';
            
            if (!isset($result[$outletKey]['warehouse_outlets'][$warehouseOutletKey])) {
                $result[$outletKey]['warehouse_outlets'][$warehouseOutletKey] = [
                    'warehouse_outlet_name' => $warehouseOutletKey,
                    'warehouse_divisions' => []
                ];
            }

            // Group unpicked items by warehouse division
            $unpickedItemsByDivision = [];
            foreach ($fo->items as $foItem) {
                $item = $foItem->item;
                if (!$item) continue;

                // Check if this item has been packed
                $isPacked = FoodPackingListItem::whereHas('packingList', function($q) use ($fo) {
                    $q->where('food_floor_order_id', $fo->id)
                      ->where('status', 'packing'); // Hanya packing list yang masih aktif
                })->where('food_floor_order_item_id', $foItem->id)->exists();

                if (!$isPacked) {
                    $divisionName = $item->warehouseDivision ? $item->warehouseDivision->name : 'Default';
                    
                    if (!isset($unpickedItemsByDivision[$divisionName])) {
                        $unpickedItemsByDivision[$divisionName] = [
                            'warehouse_division_name' => $divisionName,
                            'items' => []
                        ];
                    }
                    
                    $unpickedItemsByDivision[$divisionName]['items'][] = [
                        'item_name' => $item->name,
                        'qty' => $foItem->qty,
                        'unit' => $foItem->unit,
                        'warehouse_division' => $divisionName
                    ];
                }
            }

            // Only add FO if it has unpicked items
            if (!empty($unpickedItemsByDivision)) {
                $result[$outletKey]['warehouse_outlets'][$warehouseOutletKey]['warehouse_divisions'][] = [
                    'fo_id' => $fo->id,
                    'fo_number' => $fo->order_number,
                    'requester' => $fo->requester ? $fo->requester->nama_lengkap : '-',
                    'unpicked_items_by_division' => array_values($unpickedItemsByDivision)
                ];
            }
        }

        // Convert to array and filter out empty warehouse outlets
        $finalResult = [];
        foreach ($result as $outletData) {
            $filteredWarehouseOutlets = [];
            foreach ($outletData['warehouse_outlets'] as $woData) {
                if (!empty($woData['warehouse_divisions'])) {
                    $filteredWarehouseOutlets[] = $woData;
                }
            }
            
            if (!empty($filteredWarehouseOutlets)) {
                $outletData['warehouse_outlets'] = $filteredWarehouseOutlets;
                $finalResult[] = $outletData;
            }
        }

        return response()->json([
            'outlets' => $finalResult
        ]);
    }

    public function exportUnpickedFloorOrders(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        try {
            // Set memory limit and timeout for this operation
            ini_set('memory_limit', '1G');
            set_time_limit(300); // 5 minutes timeout

            // Get all floor orders that are approved or packing but not yet fully packed
            // Exclude RO Supplier from export data
            $floorOrders = FoodFloorOrder::whereIn('status', ['approved', 'packing'])
                ->where('fo_mode', '!=', 'RO Supplier') // Filter out RO Supplier
                ->whereDate('tanggal', $request->tanggal)
                ->with([
                    'outlet',
                    'requester',
                    'warehouseDivisions',
                    'warehouseOutlet',
                    'items.item',
                    'items.item.smallUnit',
                    'items.item.mediumUnit',
                    'items.item.largeUnit',
                    'items.item.warehouseDivision'
                ])
                ->get();

            $result = [];
            foreach ($floorOrders as $fo) {
                $outletKey = $fo->outlet->nama_outlet;
                
                if (!isset($result[$outletKey])) {
                    $result[$outletKey] = [
                        'outlet_name' => $outletKey,
                        'tanggal' => $fo->tanggal,
                        'warehouse_outlets' => []
                    ];
                }

                // Group by warehouse outlet
                $warehouseOutletKey = $fo->warehouseOutlet ? $fo->warehouseOutlet->name : 'Default';
                
                if (!isset($result[$outletKey]['warehouse_outlets'][$warehouseOutletKey])) {
                    $result[$outletKey]['warehouse_outlets'][$warehouseOutletKey] = [
                        'warehouse_outlet_name' => $warehouseOutletKey,
                        'warehouse_divisions' => []
                    ];
                }

                // Group unpicked items by warehouse division
                $unpickedItemsByDivision = [];
                foreach ($fo->items as $foItem) {
                    $item = $foItem->item;
                    if (!$item) continue;

                    // Check if this item has been packed
                    $isPacked = FoodPackingListItem::whereHas('packingList', function($q) use ($fo) {
                        $q->where('food_floor_order_id', $fo->id)
                          ->where('status', 'packing'); // Hanya packing list yang masih aktif
                    })->where('food_floor_order_item_id', $foItem->id)->exists();

                    if (!$isPacked) {
                        $divisionName = $item->warehouseDivision ? $item->warehouseDivision->name : 'Default';
                        
                        if (!isset($unpickedItemsByDivision[$divisionName])) {
                            $unpickedItemsByDivision[$divisionName] = [
                                'warehouse_division_name' => $divisionName,
                                'items' => []
                            ];
                        }
                        
                        $unpickedItemsByDivision[$divisionName]['items'][] = [
                            'item_name' => $item->name,
                            'qty' => $foItem->qty,
                            'unit' => $foItem->unit,
                            'warehouse_division' => $divisionName
                        ];
                    }
                }

                // Only add FO if it has unpicked items
                if (!empty($unpickedItemsByDivision)) {
                    $result[$outletKey]['warehouse_outlets'][$warehouseOutletKey]['warehouse_divisions'][] = [
                        'fo_id' => $fo->id,
                        'fo_number' => $fo->order_number,
                        'requester' => $fo->requester ? $fo->requester->nama_lengkap : '-',
                        'unpicked_items_by_division' => array_values($unpickedItemsByDivision)
                    ];
                }
            }

            // Convert to array and filter out empty warehouse outlets
            $finalResult = [];
            foreach ($result as $outletData) {
                $filteredWarehouseOutlets = [];
                foreach ($outletData['warehouse_outlets'] as $woData) {
                    if (!empty($woData['warehouse_divisions'])) {
                        $filteredWarehouseOutlets[] = $woData;
                    }
                }
                
                if (!empty($filteredWarehouseOutlets)) {
                    $outletData['warehouse_outlets'] = $filteredWarehouseOutlets;
                    $finalResult[] = $outletData;
                }
            }

            // Prepare data for CSV export
            $exportData = [];
            $rowCount = 0;
            foreach ($finalResult as $outlet) {
                foreach ($outlet['warehouse_outlets'] as $warehouseOutlet) {
                    foreach ($warehouseOutlet['warehouse_divisions'] as $floorOrder) {
                        foreach ($floorOrder['unpicked_items_by_division'] as $division) {
                            foreach ($division['items'] as $item) {
                                $exportData[] = [
                                    'Tanggal' => date('d/m/Y', strtotime($outlet['tanggal'])),
                                    'Outlet' => $outlet['outlet_name'],
                                    'Warehouse Outlet' => $warehouseOutlet['warehouse_outlet_name'],
                                    'No. Floor Order' => $floorOrder['fo_number'],
                                    'Pemohon' => $floorOrder['requester'],
                                    'Warehouse Division' => $division['warehouse_division_name'],
                                    'Nama Item' => $item['item_name'],
                                    'Qty' => $item['qty'],
                                    'Unit' => $item['unit'],
                                ];
                                $rowCount++;
                                
                                // Free memory every 50 rows
                                if ($rowCount % 50 === 0) {
                                    gc_collect_cycles();
                                }
                            }
                        }
                    }
                }
            }

            // Debug log removed for performance
            if (empty($exportData)) {
                return response()->json(['error' => 'Tidak ada data untuk di-export'], 404);
            }

            // Generate filename
            $filename = 'RO_Belum_di_Packing_' . date('Y-m-d', strtotime($request->tanggal)) . '.xlsx';

            // Use Excel export
            return Excel::download(new \App\Exports\UnpickedFloorOrdersExport($exportData), $filename);

        } catch (\Exception $e) {
            \Log::error('CSV export error: ' . $e->getMessage());
            \Log::error('Memory usage: ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
            return response()->json(['error' => 'Gagal mengunduh file CSV: ' . $e->getMessage()], 500);
        }
    }

    public function exportSummary(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        try {
            // Set memory limit and timeout for this operation
            ini_set('memory_limit', '1G');
            set_time_limit(300); // 5 minutes timeout

            // Get all floor orders that are approved or packing but not yet fully packed
            // Exclude RO Supplier from CSV export
            $floorOrders = FoodFloorOrder::whereIn('status', ['approved', 'packing'])
                ->where('fo_mode', '!=', 'RO Supplier') // Filter out RO Supplier
                ->whereDate('tanggal', $request->tanggal)
                ->with([
                    'items.item',
                    'items.item.warehouseDivision'
                ])
                ->get();

            // Group items by warehouse division and aggregate quantities
            $summaryByDivision = [];
            
            foreach ($floorOrders as $fo) {
                foreach ($fo->items as $foItem) {
                    $item = $foItem->item;
                    if (!$item || !$item->warehouseDivision) continue;

                    // Check if this item has been packed
                    $isPacked = FoodPackingListItem::whereHas('packingList', function($q) use ($fo) {
                        $q->where('food_floor_order_id', $fo->id)
                          ->where('status', 'packing'); // Hanya packing list yang masih aktif
                    })->where('food_floor_order_item_id', $foItem->id)->exists();

                    if (!$isPacked) {
                        $divisionName = $item->warehouseDivision->name;
                        
                        if (!isset($summaryByDivision[$divisionName])) {
                            $summaryByDivision[$divisionName] = [
                                'warehouse_division_name' => $divisionName,
                                'items' => []
                            ];
                        }

                        // Check if item already exists in this division
                        $itemKey = $item->id;
                        $existingItemIndex = null;
                        
                        // Find existing item by index
                        if (isset($summaryByDivision[$divisionName]['items']) && is_array($summaryByDivision[$divisionName]['items'])) {
                            foreach ($summaryByDivision[$divisionName]['items'] as $index => $existingItemData) {
                                if (isset($existingItemData['item_id']) && $existingItemData['item_id'] == $itemKey && 
                                    isset($existingItemData['unit']) && $existingItemData['unit'] == $foItem->unit) {
                                    $existingItemIndex = $index;
                                    break;
                                }
                            }
                        }

                        if ($existingItemIndex !== null && isset($summaryByDivision[$divisionName]['items'][$existingItemIndex])) {
                            // Add to existing item
                            $summaryByDivision[$divisionName]['items'][$existingItemIndex]['total_qty'] += $foItem->qty;
                        } else {
                            // Create new item entry
                            if (!isset($summaryByDivision[$divisionName]['items'])) {
                                $summaryByDivision[$divisionName]['items'] = [];
                            }
                            $summaryByDivision[$divisionName]['items'][] = [
                                'item_id' => $item->id,
                                'item_name' => $item->name,
                                'total_qty' => $foItem->qty,
                                'unit' => $foItem->unit,
                            ];
                        }
                    }
                }
            }

            // Prepare data for CSV export
            $csvData = [];
            $csvData[] = ['Warehouse Division', 'Nama Item', 'Total Qty', 'Unit']; // Header
            
            foreach ($summaryByDivision as $division) {
                foreach ($division['items'] as $item) {
                    $csvData[] = [
                        $division['warehouse_division_name'],
                        $item['item_name'],
                        $item['total_qty'],
                        $item['unit'],
                    ];
                }
            }

            if (count($csvData) <= 1) { // Only header
                return response()->json(['error' => 'Tidak ada data untuk di-export'], 404);
            }

            // Generate CSV content
            $csvContent = '';
            foreach ($csvData as $row) {
                $csvContent .= '"' . implode('","', array_map(function($field) {
                    return str_replace('"', '""', $field);
                }, $row)) . '"' . "\n";
            }

            // Generate filename
            $filename = 'Rangkuman_Packing_List_' . date('Y-m-d', strtotime($request->tanggal)) . '.csv';

            // Return CSV response
            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($csvContent));

        } catch (\Exception $e) {
            \Log::error('CSV export error: ' . $e->getMessage());
            \Log::error('Memory usage: ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
            return response()->json(['error' => 'Gagal mengunduh file CSV: ' . $e->getMessage()], 500);
        }
    }

    public function matrix(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        try {
            // Get all floor orders that are approved or packing but not yet fully packed
            // Exclude RO Supplier from matrix
            $floorOrders = FoodFloorOrder::whereIn('status', ['approved', 'packing'])
                ->where('fo_mode', '!=', 'RO Supplier') // Filter out RO Supplier
                ->whereDate('tanggal', $request->tanggal)
                ->with([
                    'outlet',
                    'items.item',
                    'items.item.warehouseDivision'
                ])
                ->get();

            // Collect unique outlets and items
            $outlets = [];
            $items = [];
            $matrixData = [];

            foreach ($floorOrders as $fo) {
                $outletId = $fo->outlet->id;
                $outletName = $fo->outlet->nama_outlet;
                
                // Add outlet if not exists
                if (!isset($outlets[$outletId])) {
                    $outlets[$outletId] = [
                        'id' => $outletId,
                        'nama_outlet' => $outletName
                    ];
                }

                foreach ($fo->items as $foItem) {
                    $item = $foItem->item;
                    if (!$item) continue;

                    $itemId = $item->id;
                    $itemName = $item->name;
                    $itemUnit = $foItem->unit;
                    
                    // Add item if not exists
                    if (!isset($items[$itemId])) {
                        $items[$itemId] = [
                            'id' => $itemId,
                            'name' => $itemName,
                            'unit' => $itemUnit
                        ];
                    }

                    // Check if this item has been packed
                    $isPacked = FoodPackingListItem::whereHas('packingList', function($q) use ($fo) {
                        $q->where('food_floor_order_id', $fo->id)
                          ->where('status', 'packing'); // Hanya packing list yang masih aktif
                    })->where('food_floor_order_item_id', $foItem->id)->exists();

                    if (!$isPacked) {
                        // Add to matrix data
                        $matrixData[] = [
                            'outlet_id' => $fo->outlet->id,
                            'item_id' => $itemId,
                            'qty' => $foItem->qty
                        ];
                    }
                }
            }

            // Convert to indexed arrays
            $outletsArray = array_values($outlets);
            $itemsArray = array_values($items);

            return response()->json([
                'outlets' => $outletsArray,
                'items' => $itemsArray,
                'matrix' => $matrixData,
                'data' => $floorOrders
            ]);

        } catch (\Exception $e) {
            \Log::error('Matrix error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal mengambil data matrix: ' . $e->getMessage()], 500);
        }
    }

    public function exportMatrix(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'warehouse_division_id' => 'nullable|integer',
        ]);

        try {
            $tanggal = $request->tanggal;

            // Ambil semua outlet aktif (seperti report good receive outlet)
            $outlets = \DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->orderBy('nama_outlet')
                ->get();

            // Ambil data floor orders yang belum di-packing (seperti report good receive outlet)
            $query = FoodFloorOrder::whereIn('status', ['approved', 'packing'])
                ->where('fo_mode', '!=', 'RO Supplier')
                ->whereDate('arrival_date', $tanggal);

            if ($request->filled('warehouse_division_id')) {
                $query->whereHas('items.item.warehouseDivision', function($q) use ($request) {
                    $q->where('id', $request->warehouse_division_id);
                });
            }

            $floorOrders = $query->with([
                    'outlet',
                    'items.item',
                    'items.item.warehouseDivision'
                ])
                ->get();

            // Debug logging removed for performance

            // Bentuk data seperti report good receive outlet
            $data = [];
            foreach ($floorOrders as $fo) {
                foreach ($fo->items as $foItem) {
                    $item = $foItem->item;
                    if (!$item) continue;

                    // Check if this item has been packed
                    $isPacked = FoodPackingListItem::whereHas('packingList', function($q) use ($fo) {
                        $q->where('food_floor_order_id', $fo->id)
                          ->where('status', 'packing');
                    })->where('food_floor_order_item_id', $foItem->id)->exists();

                    if (!$isPacked) {
                        $data[] = (object)[
                            'item_id' => $item->id,
                            'item_name' => $item->name,
                            'unit_name' => $foItem->unit,
                            'id_outlet' => $fo->outlet->id,
                            'nama_outlet' => $fo->outlet->nama_outlet,
                            'qty' => $foItem->qty,
                            'warehouse_division' => $item->warehouseDivision ? $item->warehouseDivision->name : 'Unknown'
                        ];
                    }
                }
            }

            // Debug log removed for performance

            // Bentuk pivot seperti report good receive outlet
            $pivot = [];
            foreach ($data as $row) {
                $key = $row->item_id . '|' . $row->unit_name;
                if (!isset($pivot[$key])) {
                    $pivot[$key] = [
                        'item_name' => $row->item_name,
                        'unit_name' => $row->unit_name,
                        'warehouse_division' => $row->warehouse_division
                    ];
                }
                // Jika sudah ada data untuk outlet ini, tambahkan qty
                if (isset($pivot[$key][$row->nama_outlet])) {
                    $pivot[$key][$row->nama_outlet] += $row->qty;
                } else {
                    $pivot[$key][$row->nama_outlet] = $row->qty;
                }
            }

            $items = array_values($pivot);

            // Prepare data for export (seperti report good receive outlet)
            $exportData = [];
            foreach ($items as $item) {
                $row = [
                    'Warehouse Division' => $item['warehouse_division'],
                    'Nama Items' => $item['item_name'],
                    'Unit' => $item['unit_name'],
                ];
                
                foreach ($outlets as $outlet) {
                    $row[$outlet->nama_outlet] = isset($item[$outlet->nama_outlet]) ? number_format($item[$outlet->nama_outlet], 2) : '';
                }
                
                $exportData[] = $row;
            }

            // Debug logging removed for performance

            // Generate filename based on filters
            $filename = 'Matrix_Packing_List_Arrival_Date_' . date('Y-m-d', strtotime($tanggal));
            if ($request->filled('warehouse_division_id')) {
                $warehouseDivision = \App\Models\WarehouseDivision::find($request->warehouse_division_id);
                if ($warehouseDivision) {
                    $filename .= '_' . str_replace(' ', '_', $warehouseDivision->name);
                }
            }
            $filename .= '.xlsx';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\PackingListMatrixExport($exportData, $outlets),
                $filename
            );

        } catch (\Exception $e) {
            \Log::error('Matrix export error: ' . $e->getMessage());
            \Log::error('Matrix export error trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Gagal export matrix: ' . $e->getMessage()], 500);
        }
    }

    public function getWarehouseDivisions()
    {
        try {
            $warehouseDivisions = \App\Models\WarehouseDivision::orderBy('name')->get();
            return response()->json($warehouseDivisions);
        } catch (\Exception $e) {
            \Log::error('Get warehouse divisions error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal mengambil data warehouse division'], 500);
        }
    }

    public function testMatrixData(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'warehouse_division_id' => 'nullable|integer',
        ]);

        try {
            // Get all floor orders that are approved or packing but not yet fully packed
            $query = FoodFloorOrder::whereIn('status', ['approved', 'packing'])
                ->where('fo_mode', '!=', 'RO Supplier')
                ->whereDate('arrival_date', $request->tanggal);

            if ($request->filled('warehouse_division_id')) {
                $query->whereHas('items.item.warehouseDivision', function($q) use ($request) {
                    $q->where('id', $request->warehouse_division_id);
                });
            }

            $floorOrders = $query->with([
                    'outlet',
                    'items.item',
                    'items.item.warehouseDivision'
                ])
                ->get();

            // Get ALL outlets from database (seperti exportMatrix)
            $outlets = \DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->orderBy('nama_outlet')
                ->get();

            $debugData = [
                'tanggal' => $request->tanggal,
                'total_floor_orders' => $floorOrders->count(),
                'total_all_outlets' => $outlets->count(),
                'floor_orders_detail' => [],
                'outlets_found' => [],
                'items_found' => [],
                'matrix_data' => []
            ];

            foreach ($floorOrders as $fo) {
                $foData = [
                    'id' => $fo->id,
                    'fo_number' => $fo->fo_number,
                    'outlet' => [
                        'id' => $fo->outlet->id,
                        'nama_outlet' => $fo->outlet->nama_outlet
                    ],
                    'items' => []
                ];

                foreach ($fo->items as $foItem) {
                    $item = $foItem->item;
                    if (!$item) continue;

                    $isPacked = FoodPackingListItem::whereHas('packingList', function($q) use ($fo) {
                        $q->where('food_floor_order_id', $fo->id)
                          ->where('status', 'packing');
                    })->where('food_floor_order_item_id', $foItem->id)->exists();

                    $foData['items'][] = [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'unit' => $foItem->unit,
                        'qty' => $foItem->qty,
                        'warehouse_division' => $item->warehouseDivision ? $item->warehouseDivision->name : 'Unknown',
                        'is_packed' => $isPacked
                    ];

                    // Collect unique items
                    if (!isset($debugData['items_found'][$item->id])) {
                        $debugData['items_found'][$item->id] = $item->name;
                    }

                    if (!$isPacked) {
                        $debugData['matrix_data'][] = [
                            'outlet_id' => $fo->outlet->id,
                            'outlet_name' => $fo->outlet->nama_outlet,
                            'item_id' => $item->id,
                            'item_name' => $item->name,
                            'qty' => $foItem->qty
                        ];
                    }
                }

                $debugData['floor_orders_detail'][] = $foData;
            }

            // Add all outlets to debug data
            foreach ($outlets as $outlet) {
                $debugData['outlets_found'][$outlet->id_outlet] = $outlet->nama_outlet;
            }

            return response()->json($debugData);

        } catch (\Exception $e) {
            \Log::error('Test matrix data error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal test matrix data: ' . $e->getMessage()], 500);
        }
    }
} 