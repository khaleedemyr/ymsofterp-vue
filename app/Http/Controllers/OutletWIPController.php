<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class OutletWIPController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Check delete permission: only superadmin or warehouse division can delete
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        
        // OPTIMASI: Cache items (data jarang berubah, cache 1 jam)
        $items = Cache::remember('outlet_wip_items', 3600, function () {
            return DB::table('items')
                ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
                ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
                ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->where('items.composition_type', 'composed')
                ->where('items.status', 'active')
                ->where('items.type', 'WIP')
                ->where('categories.show_pos', '0')
                ->select(
                    'items.*',
                    'small_unit.name as small_unit_name',
                    'medium_unit.name as medium_unit_name',
                    'large_unit.name as large_unit_name'
                )
                ->get();
        });

        // OPTIMASI: Cache warehouse outlets (data jarang berubah, cache 1 jam)
        $cacheKey = $user->id_outlet == 1 
            ? 'outlet_wip_warehouse_outlets_all' 
            : 'outlet_wip_warehouse_outlets_' . $user->id_outlet;
            
        $warehouse_outlets = Cache::remember($cacheKey, 3600, function () use ($user) {
            if ($user->id_outlet == 1) {
                return DB::table('warehouse_outlets')
                    ->where('status', 'active')
                    ->select('id', 'name', 'outlet_id')
                    ->orderBy('name')
                    ->get();
            } else {
                return DB::table('warehouse_outlets')
                    ->where('outlet_id', $user->id_outlet)
                    ->where('status', 'active')
                    ->select('id', 'name', 'outlet_id')
                    ->orderBy('name')
                    ->get();
            }
        });

        // Per page
        $perPage = $request->input('per_page', 10);
        $currentPage = $request->input('page', 1);

        // Build base query untuk headers
        $queryHeaders = DB::table('outlet_wip_production_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select(
                'h.id',
                'h.number',
                'h.production_date',
                'h.batch_number',
                'h.outlet_id',
                'h.warehouse_outlet_id',
                'h.notes',
                'h.status',
                'h.created_by',
                'h.created_at',
                'h.updated_at',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as created_by_name',
                DB::raw("'header' as source_type")
            );

        // Filter berdasarkan outlet user untuk header
        if ($user->id_outlet != 1) {
            $queryHeaders->where('h.outlet_id', $user->id_outlet);
        }

        // Build base query untuk old data
        $queryOld = DB::table('outlet_wip_productions as p')
            ->leftJoin('tbl_data_outlet as o', 'p.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'p.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'p.created_by', '=', 'u.id')
            ->whereNull('p.header_id')
            ->select(
                'p.id',
                DB::raw("NULL as number"),
                'p.production_date',
                'p.batch_number',
                'p.outlet_id',
                'p.warehouse_outlet_id',
                'p.notes',
                DB::raw("'PROCESSED' as status"),
                'p.created_by',
                'p.created_at',
                'p.updated_at',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as created_by_name',
                DB::raw("'old' as source_type")
            );

        // Filter berdasarkan outlet user untuk data lama
        if ($user->id_outlet != 1) {
            $queryOld->where('p.outlet_id', $user->id_outlet);
        }

        // Apply filters untuk header
        if ($request->filled('date_from')) {
            $queryHeaders->whereDate('h.production_date', '>=', $request->date_from);
            $queryOld->whereDate('p.production_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $queryHeaders->whereDate('h.production_date', '<=', $request->date_to);
            $queryOld->whereDate('p.production_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $queryHeaders->where(function($q) use ($search) {
                $q->where('h.number', 'like', "%{$search}%")
                  ->orWhere('h.batch_number', 'like', "%{$search}%")
                  ->orWhere('h.notes', 'like', "%{$search}%")
                  ->orWhere('h.status', 'like', "%{$search}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                  ->orWhere('wo.name', 'like', "%{$search}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$search}%");
            });
            $queryOld->where(function($q) use ($search) {
                $q->where('p.batch_number', 'like', "%{$search}%")
                  ->orWhere('p.notes', 'like', "%{$search}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                  ->orWhere('wo.name', 'like', "%{$search}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$search}%");
            });
        }

        // OPTIMASI: Gunakan UNION untuk menggabungkan query dan pagination di database level
        // Build SQL untuk UNION query
        $headersSql = $queryHeaders->toSql();
        $headersBindings = $queryHeaders->getBindings();
        
        $oldSql = $queryOld->toSql();
        $oldBindings = $queryOld->getBindings();
        
        // Combine dengan UNION ALL dan sorting di database
        $unionSql = "SELECT * FROM (
            ({$headersSql}) 
            UNION ALL 
            ({$oldSql})
        ) as combined_results 
        ORDER BY production_date DESC, id DESC";
        
        $allBindings = array_merge($headersBindings, $oldBindings);
        
        // Count total (lebih efisien)
        $countSql = "SELECT COUNT(*) as total FROM (
            ({$headersSql}) 
            UNION ALL 
            ({$oldSql})
        ) as combined_results";
        
        $total = DB::selectOne($countSql, $allBindings)->total ?? 0;
        
        // Get paginated results
        $offset = ($currentPage - 1) * $perPage;
        $paginatedSql = $unionSql . " LIMIT {$perPage} OFFSET {$offset}";
        
        $combined = collect(DB::select($paginatedSql, $allBindings));

        // Create paginator
        $headers = new \Illuminate\Pagination\LengthAwarePaginator(
            $combined,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        // OPTIMASI: Get production details HANYA untuk headers yang ditampilkan di halaman ini
        $headerIds = $combined->where('source_type', 'header')->pluck('id')->toArray();
        $oldIds = $combined->where('source_type', 'old')->pluck('id')->toArray();
        $productionsByHeader = [];

        // Get productions for new headers (hanya yang ditampilkan) - gunakan GROUP BY di database
        if (!empty($headerIds)) {
            $productions = DB::table('outlet_wip_productions as p')
                ->leftJoin('items', 'p.item_id', '=', 'items.id')
                ->leftJoin('units', 'p.unit_id', '=', 'units.id')
                ->whereIn('p.header_id', $headerIds)
                ->select(
                    'p.header_id',
                    'p.item_id',
                    'p.qty',
                    'p.qty_jadi',
                    'p.unit_id',
                    'items.name as item_name',
                    'units.name as unit_name'
                )
                ->groupBy('p.header_id', 'p.item_id', 'p.qty', 'p.qty_jadi', 'p.unit_id', 'items.name', 'units.name')
                ->get();

            foreach ($productions as $prod) {
                $headerId = $prod->header_id;
                if (!isset($productionsByHeader[$headerId])) {
                    $productionsByHeader[$headerId] = [];
                }
                $productionsByHeader[$headerId][] = $prod;
            }
        }

        // Get productions for old data (hanya yang ditampilkan)
        if (!empty($oldIds)) {
            $oldProductions = DB::table('outlet_wip_productions as p')
                ->leftJoin('items', 'p.item_id', '=', 'items.id')
                ->leftJoin('units', 'p.unit_id', '=', 'units.id')
                ->whereIn('p.id', $oldIds)
                ->select(
                    'p.id as header_id',
                    'p.item_id',
                    'p.qty',
                    'p.qty_jadi',
                    'p.unit_id',
                    'items.name as item_name',
                    'units.name as unit_name'
                )
                ->get();

            foreach ($oldProductions as $prod) {
                $headerId = $prod->header_id;
                if (!isset($productionsByHeader[$headerId])) {
                    $productionsByHeader[$headerId] = [];
                }
                $productionsByHeader[$headerId][] = $prod;
            }
        }

        return Inertia::render('OutletWIP/Index', [
            'items' => $items,
            'warehouse_outlets' => $warehouse_outlets,
            'headers' => $headers,
            'productionsByHeader' => $productionsByHeader,
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'search' => $request->search,
                'per_page' => $perPage,
            ],
            'canDelete' => $canDelete,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        
        // OPTIMASI: Cache items (data jarang berubah, cache 1 jam)
        $items = Cache::remember('outlet_wip_items', 3600, function () {
            return DB::table('items')
                ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
                ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
                ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->where('items.composition_type', 'composed')
                ->where('items.status', 'active')
                ->where('items.type', 'WIP')
                ->where('categories.show_pos', '0')
                ->select(
                    'items.*',
                    'small_unit.name as small_unit_name',
                    'medium_unit.name as medium_unit_name',
                    'large_unit.name as large_unit_name'
                )
                ->get();
        });

        // OPTIMASI: Cache warehouse outlets (data jarang berubah, cache 1 jam)
        $cacheKey = $user->id_outlet == 1 
            ? 'outlet_wip_warehouse_outlets_all' 
            : 'outlet_wip_warehouse_outlets_' . $user->id_outlet;
            
        $warehouse_outlets = Cache::remember($cacheKey, 3600, function () use ($user) {
            if ($user->id_outlet == 1) {
                return DB::table('warehouse_outlets')
                    ->where('status', 'active')
                    ->select('id', 'name', 'outlet_id')
                    ->orderBy('name')
                    ->get();
            } else {
                return DB::table('warehouse_outlets')
                    ->where('outlet_id', $user->id_outlet)
                    ->where('status', 'active')
                    ->select('id', 'name', 'outlet_id')
                    ->orderBy('name')
                    ->get();
            }
        });

        // OPTIMASI: Cache outlets untuk superuser (data jarang berubah, cache 1 jam)
        $outlets = [];
        if ($user->id_outlet == 1) {
            $outlets = Cache::remember('outlet_wip_outlets_all', 3600, function () {
                return DB::table('tbl_data_outlet')
                    ->where('status', 'A')
                    ->select('id_outlet as id', 'nama_outlet as name')
                    ->orderBy('nama_outlet')
                    ->get();
            });
        }

        return Inertia::render('OutletWIP/Create', [
            'items' => $items,
            'warehouse_outlets' => $warehouse_outlets,
            'outlets' => $outlets,
            'user_outlet_id' => $user->id_outlet,
        ]);
    }

    public function edit($id)
    {
        $user = auth()->user();
        
        // Get header
        $header = DB::table('outlet_wip_production_headers')->where('id', $id)->first();
        if (!$header) {
            return redirect()->route('outlet-wip.index')->with('error', 'Data produksi tidak ditemukan');
        }

        // Check access
        if ($user->id_outlet != 1 && $header->outlet_id != $user->id_outlet) {
            return redirect()->route('outlet-wip.index')->with('error', 'Tidak memiliki akses ke data ini');
        }

        // Only allow editing if status is DRAFT
        if ($header->status !== 'DRAFT') {
            return redirect()->route('outlet-wip.index')->with('error', 'Hanya draft yang dapat diedit');
        }

        // Get production details
        $details = DB::table('outlet_wip_productions')
            ->where('header_id', $id)
            ->get();

        // OPTIMASI: Cache items (data jarang berubah, cache 1 jam)
        $items = Cache::remember('outlet_wip_items', 3600, function () {
            return DB::table('items')
                ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
                ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
                ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->where('items.composition_type', 'composed')
                ->where('items.status', 'active')
                ->where('items.type', 'WIP')
                ->where('categories.show_pos', '0')
                ->select(
                    'items.*',
                    'small_unit.name as small_unit_name',
                    'medium_unit.name as medium_unit_name',
                    'large_unit.name as large_unit_name'
                )
                ->get();
        });

        // OPTIMASI: Cache warehouse outlets (data jarang berubah, cache 1 jam)
        $cacheKey = $user->id_outlet == 1 
            ? 'outlet_wip_warehouse_outlets_all' 
            : 'outlet_wip_warehouse_outlets_' . $user->id_outlet;
            
        $warehouse_outlets = Cache::remember($cacheKey, 3600, function () use ($user) {
            if ($user->id_outlet == 1) {
                return DB::table('warehouse_outlets')
                    ->where('status', 'active')
                    ->select('id', 'name', 'outlet_id')
                    ->orderBy('name')
                    ->get();
            } else {
                return DB::table('warehouse_outlets')
                    ->where('outlet_id', $user->id_outlet)
                    ->where('status', 'active')
                    ->select('id', 'name', 'outlet_id')
                    ->orderBy('name')
                    ->get();
            }
        });

        // OPTIMASI: Cache outlets untuk superuser (data jarang berubah, cache 1 jam)
        $outlets = [];
        if ($user->id_outlet == 1) {
            $outlets = Cache::remember('outlet_wip_outlets_all', 3600, function () {
                return DB::table('tbl_data_outlet')
                    ->where('status', 'A')
                    ->select('id_outlet as id', 'nama_outlet as name')
                    ->orderBy('nama_outlet')
                    ->get();
            });
        }

        return Inertia::render('OutletWIP/Create', [
            'items' => $items,
            'warehouse_outlets' => $warehouse_outlets,
            'outlets' => $outlets,
            'user_outlet_id' => $user->id_outlet,
            'headerData' => $header,
            'detailData' => $details,
        ]);
    }

    public function getBomAndStock(Request $request)
    {
        $item_id = $request->input('item_id');
        $qty = $request->input('qty');
        $outlet_id = $request->input('outlet_id');
        $warehouse_outlet_id = $request->input('warehouse_outlet_id');

        if (!$item_id || !$qty || !$outlet_id || !$warehouse_outlet_id) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        // Ambil BOM untuk item tersebut
        $bom = DB::table('item_bom')
            ->leftJoin('items as material', 'item_bom.material_item_id', '=', 'material.id')
            ->leftJoin('units as material_unit', 'item_bom.unit_id', '=', 'material_unit.id')
            ->where('item_bom.item_id', $item_id)
            ->select(
                'item_bom.*',
                'material.name as material_name',
                'material_unit.name as material_unit_name'
            )
            ->get();

        // OPTIMASI: Ambil semua inventory items sekaligus untuk menghindari N+1 query
        $materialItemIds = $bom->pluck('material_item_id')->unique()->toArray();
        $inventoryItems = DB::table('outlet_food_inventory_items')
            ->whereIn('item_id', $materialItemIds)
            ->get()
            ->keyBy('item_id');

        // OPTIMASI: Ambil semua stock data sekaligus
        $inventoryItemIds = $inventoryItems->pluck('id')->toArray();
        $stockDataMap = [];
        if (!empty($inventoryItemIds)) {
            $stockDataList = DB::table('outlet_food_inventory_stocks')
                ->whereIn('inventory_item_id', $inventoryItemIds)
                ->where('id_outlet', $outlet_id)
                ->where('warehouse_outlet_id', $warehouse_outlet_id)
                ->get();
            
            // Map by inventory_item_id untuk akses cepat
            foreach ($stockDataList as $stockData) {
                $stockDataMap[$stockData->inventory_item_id] = $stockData;
            }
        }

        $result = [];
        foreach ($bom as $b) {
            // Cari inventory item untuk material dari map
            $inventoryItem = $inventoryItems->get($b->material_item_id);

            $stock = 0;
            $stock_medium = 0;
            $stock_large = 0;
            $last_cost_small = 0;
            $last_cost_medium = 0;
            $last_cost_large = 0;

            if ($inventoryItem) {
                $stockData = $stockDataMap[$inventoryItem->id] ?? null;

                if ($stockData) {
                    $stock = $stockData->qty_small;
                    $stock_medium = $stockData->qty_medium;
                    $stock_large = $stockData->qty_large;
                    $last_cost_small = $stockData->last_cost_small;
                    $last_cost_medium = $stockData->last_cost_medium;
                    $last_cost_large = $stockData->last_cost_large;
                }
            }

            // Hitung qty yang dibutuhkan
            $qty_needed = $b->qty * $qty;

            $result[] = [
                'material_item_id' => $b->material_item_id,
                'material_name' => $b->material_name,
                'qty' => $b->qty,
                'qty_needed' => $qty_needed,
                'unit_id' => $b->unit_id,
                'material_unit_name' => $b->material_unit_name,
                'stock' => $stock,
                'stock_medium' => $stock_medium,
                'stock_large' => $stock_large,
                'last_cost_small' => $last_cost_small,
                'last_cost_medium' => $last_cost_medium,
                'last_cost_large' => $last_cost_large,
                'sufficient' => $stock >= $qty_needed
            ];
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        Log::info('[OutletWIP] Payload request', $request->all());
        
        $isAutosave = $request->input('autosave', false);
        
        // Validation for multiple productions
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'warehouse_outlet_id' => 'required|exists:warehouse_outlets,id',
            'production_date' => 'required|date',
            'batch_number' => 'nullable|string',
            'notes' => 'nullable|string',
            'productions' => 'required|array|min:1',
            'productions.*.item_id' => 'required|exists:items,id',
            'productions.*.qty' => 'required|numeric|min:0',
            'productions.*.qty_jadi' => 'required|numeric|min:0',
            'productions.*.unit_id' => 'required|exists:units,id',
        ]);

        $userId = Auth::id();
        $production_date = $request->input('production_date');
        $batch_number = $request->input('batch_number');
        $notes = $request->input('notes');
        $outlet_id = $request->input('outlet_id');
        $warehouse_outlet_id = $request->input('warehouse_outlet_id');
        $productions = $request->input('productions', []);
        
        $status = 'DRAFT'; // Always save as DRAFT first
        
        // For autosave, skip stock validation
        if (!$isAutosave) {
            // Validate stock for each production (only for manual save)
            foreach ($productions as $prod) {
                $item_id = $prod['item_id'];
                $qty_produksi = $prod['qty'];
                
                $bom = DB::table('item_bom')->where('item_id', $item_id)->get();
                
                foreach ($bom as $b) {
                    $bomInventory = DB::table('outlet_food_inventory_items')->where('item_id', $b->material_item_id)->first();
                    $bomInventoryId = $bomInventory ? $bomInventory->id : null;
                    $stok = 0;
                    if ($bomInventoryId) {
                        $stok = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $bomInventoryId)
                            ->where('id_outlet', $outlet_id)
                            ->where('warehouse_outlet_id', $warehouse_outlet_id)
                            ->value('qty_small');
                    }
                    $qty_total = $b->qty * $qty_produksi;
                    if ($stok < $qty_total) {
                        return response()->json([
                            'success' => false,
                            'message' => "Stok bahan tidak cukup untuk item produksi"
                        ], 400);
                    }
                }
            }
        }

        DB::beginTransaction();
        try {
            // Cek apakah sudah ada draft untuk outlet, warehouse, dan user yang sama
            $existingHeader = DB::table('outlet_wip_production_headers')
                ->where('outlet_id', $outlet_id)
                ->where('warehouse_outlet_id', $warehouse_outlet_id)
                ->where('created_by', $userId)
                ->where('status', 'DRAFT')
                ->lockForUpdate()
                ->first();
            
            if ($existingHeader) {
                // Update existing draft
                $headerId = $existingHeader->id;
                
                DB::table('outlet_wip_production_headers')
                    ->where('id', $headerId)
                    ->update([
                        'production_date' => $production_date,
                        'batch_number' => $batch_number,
                        'notes' => $notes,
                        'updated_at' => now()
                    ]);
                
                // Delete existing details
                DB::table('outlet_wip_productions')->where('header_id', $headerId)->delete();
            } else {
                // Double check again after lock
                $doubleCheck = DB::table('outlet_wip_production_headers')
                    ->where('outlet_id', $outlet_id)
                    ->where('warehouse_outlet_id', $warehouse_outlet_id)
                    ->where('created_by', $userId)
                    ->where('status', 'DRAFT')
                    ->first();
                
                if ($doubleCheck) {
                    $headerId = $doubleCheck->id;
                    DB::table('outlet_wip_production_headers')
                        ->where('id', $headerId)
                        ->update([
                            'production_date' => $production_date,
                            'batch_number' => $batch_number,
                            'notes' => $notes,
                            'updated_at' => now()
                        ]);
                    DB::table('outlet_wip_productions')->where('header_id', $headerId)->delete();
                } else {
                    // Create new draft
                    $draftNumber = 'DRAFT-' . $userId . '-' . time();
                    
                    $headerId = DB::table('outlet_wip_production_headers')->insertGetId([
                        'number' => $draftNumber,
                        'production_date' => $production_date,
                        'batch_number' => $batch_number,
                        'outlet_id' => $outlet_id,
                        'warehouse_outlet_id' => $warehouse_outlet_id,
                        'notes' => $notes,
                        'status' => $status,
                        'created_by' => $userId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            
            // Insert production details (no stock processing for DRAFT)
            // Remove duplicates based on item_id to prevent duplicate entries
            $uniqueProductions = [];
            $seenItemIds = [];
            foreach ($productions as $prod) {
                $itemId = $prod['item_id'];
                // Only insert if we haven't seen this item_id yet for this header
                if (!in_array($itemId, $seenItemIds)) {
                    $uniqueProductions[] = $prod;
                    $seenItemIds[] = $itemId;
                } else {
                    Log::warning('[OutletWIP] Store - Duplicate item_id detected, skipping', [
                        'header_id' => $headerId,
                        'item_id' => $itemId,
                        'productions_count' => count($productions)
                    ]);
                }
            }
            
            Log::info('[OutletWIP] Store - Inserting productions', [
                'header_id' => $headerId,
                'original_count' => count($productions),
                'unique_count' => count($uniqueProductions),
                'item_ids' => $seenItemIds
            ]);
            
            foreach ($uniqueProductions as $prod) {
                DB::table('outlet_wip_productions')->insert([
                    'header_id' => $headerId,
                    'production_date' => $production_date,
                    'batch_number' => $batch_number,
                    'item_id' => $prod['item_id'],
                    'qty' => $prod['qty'],
                    'qty_jadi' => $prod['qty_jadi'],
                    'unit_id' => $prod['unit_id'],
                    'outlet_id' => $outlet_id,
                    'warehouse_outlet_id' => $warehouse_outlet_id,
                    'notes' => $notes,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            DB::commit();
            
            // OPTIMASI: Clear cache untuk index (jika ada cache untuk list headers)
            // Cache akan auto-refresh saat diakses lagi
            
            return response()->json([
                'success' => true,
                'header_id' => $headerId,
                'message' => 'Draft berhasil disimpan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[OutletWIP] ERROR', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function submit(Request $request, $id)
    {
        Log::info('[OutletWIP] Submit request', ['header_id' => $id, 'payload' => $request->all()]);
        
        $header = DB::table('outlet_wip_production_headers')
            ->where('id', $id)
            ->lockForUpdate()
            ->first();
        
        if (!$header) {
            return response()->json([
                'success' => false,
                'message' => 'Header produksi tidak ditemukan'
            ], 404);
        }
        
        if ($header->status !== 'DRAFT') {
            return response()->json([
                'success' => false,
                'message' => 'Produksi ini sudah disubmit sebelumnya',
                'current_status' => $header->status
            ], 400);
        }
        
        // Get production details
        $productions = DB::table('outlet_wip_productions')
            ->where('header_id', $id)
            ->get();
        
        if ($productions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada item produksi'
            ], 400);
        }
        
        $outlet_id = $header->outlet_id;
        $warehouse_outlet_id = $header->warehouse_outlet_id;
        $production_date = $header->production_date;
        
        DB::beginTransaction();
        try {
            // Process each production
            foreach ($productions as $prod) {
                $item_id = $prod->item_id;
                $qty_produksi = $prod->qty;
                $qty_jadi = $prod->qty_jadi;
                $unit_jadi = $prod->unit_id;
                
                $bom = DB::table('item_bom')->where('item_id', $item_id)->get();
                
                // Validate stock
                foreach ($bom as $b) {
                    $bomInventory = DB::table('outlet_food_inventory_items')->where('item_id', $b->material_item_id)->first();
                    if (!$bomInventory) {
                        throw new \Exception("Item bahan baku tidak ditemukan di inventory outlet");
                    }
                    
                    $stock = DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $bomInventory->id)
                        ->where('id_outlet', $outlet_id)
                        ->where('warehouse_outlet_id', $warehouse_outlet_id)
                        ->first();
                    
                    if (!$stock) {
                        throw new \Exception("Stok bahan baku tidak ditemukan");
                    }
                    
                    $itemMaster = DB::table('items')->where('id', $b->material_item_id)->first();
                    $unit = DB::table('units')->where('id', $b->unit_id)->value('name');
                    $qty_input = $b->qty * $qty_produksi;
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
                    
                    // Validate stock sufficient
                    if ($stock->qty_small < $qty_small) {
                        $itemName = DB::table('items')->where('id', $b->material_item_id)->value('name');
                        throw new \Exception("Stok bahan baku '{$itemName}' tidak cukup");
                    }
                    
                    // Calculate saldo before update
                    $saldo_qty_small = $stock->qty_small - $qty_small;
                    $saldo_qty_medium = $stock->qty_medium - $qty_medium;
                    $saldo_qty_large = $stock->qty_large - $qty_large;
                    $saldo_value = $saldo_qty_small * $stock->last_cost_small;
                    
                    // Update stock (reduce)
                    DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $bomInventory->id)
                        ->where('id_outlet', $outlet_id)
                        ->where('warehouse_outlet_id', $warehouse_outlet_id)
                        ->update([
                            'qty_small' => $saldo_qty_small,
                            'qty_medium' => $saldo_qty_medium,
                            'qty_large' => $saldo_qty_large,
                            'updated_at' => now(),
                        ]);
                    
                    // Insert stock card OUT
                    DB::table('outlet_food_inventory_cards')->insert([
                        'inventory_item_id' => $bomInventory->id,
                        'id_outlet' => $outlet_id,
                        'warehouse_outlet_id' => $warehouse_outlet_id,
                        'date' => $production_date,
                        'reference_type' => 'outlet_wip_production',
                        'reference_id' => $id,
                        'out_qty_small' => $qty_small,
                        'out_qty_medium' => $qty_medium,
                        'out_qty_large' => $qty_large,
                        'cost_per_small' => $stock->last_cost_small,
                        'cost_per_medium' => $stock->last_cost_medium,
                        'cost_per_large' => $stock->last_cost_large,
                        'value_out' => $qty_small * $stock->last_cost_small,
                        'saldo_qty_small' => $saldo_qty_small,
                        'saldo_qty_medium' => $saldo_qty_medium,
                        'saldo_qty_large' => $saldo_qty_large,
                        'saldo_value' => $saldo_value,
                        'description' => 'Stock Out - WIP Production',
                        'created_at' => now(),
                    ]);
                }
                
                // Process production result (add stock)
                $prodInventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item_id)->first();
                if (!$prodInventoryItem) {
                    $itemMaster = DB::table('items')->where('id', $item_id)->first();
                    $prodInventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                        'item_id' => $item_id,
                        'small_unit_id' => $itemMaster->small_unit_id,
                        'medium_unit_id' => $itemMaster->medium_unit_id,
                        'large_unit_id' => $itemMaster->large_unit_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $prodInventoryItemId = $prodInventoryItem->id;
                }
                
                // Calculate production result qty
                $itemMaster = DB::table('items')->where('id', $item_id)->first();
                $unit = DB::table('units')->where('id', $unit_jadi)->value('name');
                $qty_small = 0;
                $qty_medium = 0;
                $qty_large = 0;
                
                $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                
                if ($unit === $unitSmall) {
                    $qty_small = $qty_jadi;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                } elseif ($unit === $unitMedium) {
                    $qty_medium = $qty_jadi;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                } elseif ($unit === $unitLarge) {
                    $qty_large = $qty_jadi;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } else {
                    $qty_small = $qty_jadi;
                }
                
                // Calculate cost
                $total_cost = 0;
                $total_qty_small = 0;
                foreach ($bom as $b) {
                    $bomInventory = DB::table('outlet_food_inventory_items')->where('item_id', $b->material_item_id)->first();
                    if ($bomInventory) {
                        $stock = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $bomInventory->id)
                            ->where('id_outlet', $outlet_id)
                            ->where('warehouse_outlet_id', $warehouse_outlet_id)
                            ->first();
                        if ($stock) {
                            $itemMaster = DB::table('items')->where('id', $b->material_item_id)->first();
                            $unit = DB::table('units')->where('id', $b->unit_id)->value('name');
                            $qty_input = $b->qty * $qty_produksi;
                            $qty_small_bahan = 0;
                            
                            $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                            $smallConv = $itemMaster->small_conversion_qty ?: 1;
                            
                            if ($unit === $unitSmall) {
                                $qty_small_bahan = $qty_input;
                            } elseif ($unit === $unitMedium) {
                                $qty_small_bahan = $qty_input * $smallConv;
                            } elseif ($unit === $unitLarge) {
                                $qty_small_bahan = $qty_input * $smallConv * $mediumConv;
                            } else {
                                $qty_small_bahan = $qty_input;
                            }
                            
                            $total_cost += $qty_small_bahan * $stock->last_cost_small;
                            $total_qty_small += $qty_small_bahan;
                        }
                    }
                }
                
                $last_cost_small = $total_qty_small > 0 ? $total_cost / $total_qty_small : 0;
                $last_cost_medium = $smallConv > 0 ? $last_cost_small / $smallConv : 0;
                $last_cost_large = ($smallConv > 0 && $mediumConv > 0) ? $last_cost_small / ($smallConv * $mediumConv) : 0;
                
                // Update or insert production result stock
                $existingStock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $prodInventoryItemId)
                    ->where('id_outlet', $outlet_id)
                    ->where('warehouse_outlet_id', $warehouse_outlet_id)
                    ->first();
                
                if ($existingStock) {
                    $qty_baru_small = $existingStock->qty_small + $qty_small;
                    $qty_baru_medium = $existingStock->qty_medium + $qty_medium;
                    $qty_baru_large = $existingStock->qty_large + $qty_large;
                    $nilai_baru = $qty_baru_small * $last_cost_small;
                    
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $existingStock->id)
                        ->update([
                            'qty_small' => $qty_baru_small,
                            'qty_medium' => $qty_baru_medium,
                            'qty_large' => $qty_baru_large,
                            'value' => $nilai_baru,
                            'last_cost_small' => $last_cost_small,
                            'last_cost_medium' => $last_cost_medium,
                            'last_cost_large' => $last_cost_large,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $prodInventoryItemId,
                        'id_outlet' => $outlet_id,
                        'warehouse_outlet_id' => $warehouse_outlet_id,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $qty_small * $last_cost_small,
                        'last_cost_small' => $last_cost_small,
                        'last_cost_medium' => $last_cost_medium,
                        'last_cost_large' => $last_cost_large,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $qty_baru_small = $qty_small;
                    $qty_baru_medium = $qty_medium;
                    $qty_baru_large = $qty_large;
                    $nilai_baru = $qty_small * $last_cost_small;
                }
                
                // Insert stock card IN
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $prodInventoryItemId,
                    'id_outlet' => $outlet_id,
                    'warehouse_outlet_id' => $warehouse_outlet_id,
                    'date' => $production_date,
                    'reference_type' => 'outlet_wip_production',
                    'reference_id' => $id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'cost_per_small' => $last_cost_small,
                    'cost_per_medium' => $last_cost_medium,
                    'cost_per_large' => $last_cost_large,
                    'value_in' => $qty_small * $last_cost_small,
                    'saldo_qty_small' => $qty_baru_small,
                    'saldo_qty_medium' => $qty_baru_medium,
                    'saldo_qty_large' => $qty_baru_large,
                    'saldo_value' => $nilai_baru,
                    'description' => "Hasil produksi WIP",
                    'created_at' => now(),
                ]);
            }
            
            // Generate final number
            $date = now()->format('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $finalNumber = 'WIP-' . $date . '-' . $random;
            
            // Update header status and number LAST, after all operations succeed
            Log::info('[OutletWIP] Submit - Updating status (final step)', [
                'header_id' => $id,
                'old_status' => $header->status,
                'new_status' => 'PROCESSED',
                'old_number' => $header->number,
                'new_number' => $finalNumber
            ]);
            
            // Use lockForUpdate to prevent race conditions
            $headerToUpdate = DB::table('outlet_wip_production_headers')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();
            
            if (!$headerToUpdate) {
                Log::error('[OutletWIP] Submit - Header not found for update', ['header_id' => $id]);
                throw new \Exception('Header tidak ditemukan saat akan update status');
            }
            
            if ($headerToUpdate->status !== 'DRAFT') {
                Log::warning('[OutletWIP] Submit - Header status changed before update', [
                    'header_id' => $id,
                    'expected_status' => 'DRAFT',
                    'actual_status' => $headerToUpdate->status
                ]);
                throw new \Exception('Status header sudah berubah. Kemungkinan sudah disubmit sebelumnya.');
            }
            
            // Update status and number
            Log::info('[OutletWIP] Submit - About to update status', [
                'header_id' => $id,
                'header_to_update_status' => $headerToUpdate->status,
                'final_number' => $finalNumber
            ]);
            
            $statusUpdated = DB::table('outlet_wip_production_headers')
                ->where('id', $id)
                ->where('status', 'DRAFT') // Ensure we only update if still DRAFT
                ->update([
                    'status' => 'PROCESSED',
                    'number' => $finalNumber,
                    'updated_at' => now()
                ]);
            
            Log::info('[OutletWIP] Submit - Update query executed', [
                'header_id' => $id,
                'rows_affected' => $statusUpdated,
                'query_conditions' => [
                    'id' => $id,
                    'status' => 'DRAFT'
                ]
            ]);
            
            if ($statusUpdated === false || $statusUpdated === 0) {
                // Check current status
                $currentHeader = DB::table('outlet_wip_production_headers')->where('id', $id)->first();
                Log::error('[OutletWIP] Submit - Failed to update status', [
                    'header_id' => $id,
                    'rows_affected' => $statusUpdated,
                    'current_status' => $currentHeader->status ?? 'NOT_FOUND',
                    'expected_status' => 'DRAFT'
                ]);
                throw new \Exception('Gagal mengupdate status header. Status saat ini: ' . ($currentHeader->status ?? 'NOT_FOUND'));
            }
            
            Log::info('[OutletWIP] Submit - Status update query executed successfully', [
                'header_id' => $id,
                'rows_affected' => $statusUpdated,
                'new_status' => 'PROCESSED'
            ]);
            
            DB::commit();
            
            // Verify status was updated
            $finalHeader = DB::table('outlet_wip_production_headers')->where('id', $id)->first();
            Log::info('[OutletWIP] Submit - Final status check after commit', [
                'header_id' => $id,
                'final_status' => $finalHeader->status ?? 'NOT_FOUND',
                'final_number' => $finalHeader->number ?? 'NOT_FOUND'
            ]);
            
            Log::info('[OutletWIP] Submit success', ['header_id' => $id]);
            
            // OPTIMASI: Clear cache untuk index (jika ada cache untuk list headers)
            // Cache akan auto-refresh saat diakses lagi
            
            return response()->json([
                'success' => true,
                'message' => 'Produksi WIP berhasil disubmit',
                'current_status' => $finalHeader->status ?? 'PROCESSED'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[OutletWIP] Submit ERROR', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function storeAndSubmit(Request $request)
    {
        Log::info('[OutletWIP] StoreAndSubmit request', ['payload' => $request->all()]);
        
        $validated = $request->validate([
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'production_date' => 'required|date',
            'batch_number' => 'nullable|string',
            'notes' => 'nullable|string',
            'productions' => 'required|array|min:1',
            'productions.*.item_id' => 'required|integer',
            'productions.*.qty' => 'required|numeric|min:0.01',
            'productions.*.qty_jadi' => 'required|numeric|min:0',
            'productions.*.unit_id' => 'required|integer',
        ]);
        
        $outlet_id = $validated['outlet_id'];
        $warehouse_outlet_id = $validated['warehouse_outlet_id'];
        $production_date = $validated['production_date'];
        $batch_number = $validated['batch_number'] ?? '';
        $notes = $validated['notes'] ?? '';
        $productions = $validated['productions'];
        $userId = auth()->id();
        
        // Remove duplicates based on item_id
        $uniqueProductions = [];
        $seenItemIds = [];
        foreach ($productions as $prod) {
            $itemId = $prod['item_id'];
            if (!in_array($itemId, $seenItemIds)) {
                $uniqueProductions[] = $prod;
                $seenItemIds[] = $itemId;
            }
        }
        
        DB::beginTransaction();
        try {
            // Generate final number directly (not DRAFT)
            $date = date('Ymd', strtotime($production_date));
            $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
            $finalNumber = 'WIP-' . $date . '-' . $random;
            
            // Create header with PROCESSED status directly
            $headerId = DB::table('outlet_wip_production_headers')->insertGetId([
                'number' => $finalNumber,
                'production_date' => $production_date,
                'batch_number' => $batch_number,
                'outlet_id' => $outlet_id,
                'warehouse_outlet_id' => $warehouse_outlet_id,
                'notes' => $notes,
                'status' => 'PROCESSED', // Directly set to PROCESSED
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Insert production details
            foreach ($uniqueProductions as $prod) {
                DB::table('outlet_wip_productions')->insert([
                    'header_id' => $headerId,
                    'production_date' => $production_date,
                    'batch_number' => $batch_number,
                    'item_id' => $prod['item_id'],
                    'qty' => $prod['qty'],
                    'qty_jadi' => $prod['qty_jadi'],
                    'unit_id' => $prod['unit_id'],
                    'outlet_id' => $outlet_id,
                    'warehouse_outlet_id' => $warehouse_outlet_id,
                    'notes' => $notes,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Process stock (same logic as submit method)
            $productionsData = DB::table('outlet_wip_productions')
                ->where('header_id', $headerId)
                ->get();
            
            foreach ($productionsData as $prod) {
                $item_id = $prod->item_id;
                $qty_produksi = $prod->qty;
                $qty_jadi = $prod->qty_jadi;
                $unit_jadi = $prod->unit_id;
                
                $bom = DB::table('item_bom')->where('item_id', $item_id)->get();
                
                // Process BOM materials (reduce stock)
                foreach ($bom as $b) {
                    $bomInventory = DB::table('outlet_food_inventory_items')->where('item_id', $b->material_item_id)->first();
                    if (!$bomInventory) {
                        throw new \Exception("Item bahan baku tidak ditemukan di inventory outlet");
                    }
                    
                    $stock = DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $bomInventory->id)
                        ->where('id_outlet', $outlet_id)
                        ->where('warehouse_outlet_id', $warehouse_outlet_id)
                        ->first();
                    
                    if (!$stock) {
                        throw new \Exception("Stok bahan baku tidak ditemukan");
                    }
                    
                    $itemMaster = DB::table('items')->where('id', $b->material_item_id)->first();
                    $unit = DB::table('units')->where('id', $b->unit_id)->value('name');
                    $qty_input = $b->qty * $qty_produksi;
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
                    
                    // Validate stock sufficient
                    if ($stock->qty_small < $qty_small) {
                        $itemName = DB::table('items')->where('id', $b->material_item_id)->value('name');
                        throw new \Exception("Stok bahan baku '{$itemName}' tidak cukup");
                    }
                    
                    // Calculate saldo before update
                    $saldo_qty_small = $stock->qty_small - $qty_small;
                    $saldo_qty_medium = $stock->qty_medium - $qty_medium;
                    $saldo_qty_large = $stock->qty_large - $qty_large;
                    $saldo_value = $saldo_qty_small * $stock->last_cost_small;
                    
                    // Update stock (reduce)
                    DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $bomInventory->id)
                        ->where('id_outlet', $outlet_id)
                        ->where('warehouse_outlet_id', $warehouse_outlet_id)
                        ->update([
                            'qty_small' => $saldo_qty_small,
                            'qty_medium' => $saldo_qty_medium,
                            'qty_large' => $saldo_qty_large,
                            'updated_at' => now(),
                        ]);
                    
                    // Insert stock card OUT
                    DB::table('outlet_food_inventory_cards')->insert([
                        'inventory_item_id' => $bomInventory->id,
                        'id_outlet' => $outlet_id,
                        'warehouse_outlet_id' => $warehouse_outlet_id,
                        'date' => $production_date,
                        'reference_type' => 'outlet_wip_production',
                        'reference_id' => $headerId,
                        'out_qty_small' => $qty_small,
                        'out_qty_medium' => $qty_medium,
                        'out_qty_large' => $qty_large,
                        'cost_per_small' => $stock->last_cost_small,
                        'cost_per_medium' => $stock->last_cost_medium,
                        'cost_per_large' => $stock->last_cost_large,
                        'value_out' => $qty_small * $stock->last_cost_small,
                        'saldo_qty_small' => $saldo_qty_small,
                        'saldo_qty_medium' => $saldo_qty_medium,
                        'saldo_qty_large' => $saldo_qty_large,
                        'saldo_value' => $saldo_value,
                        'description' => 'Stock Out - WIP Production',
                        'created_at' => now(),
                    ]);
                }
                
                // Process production result (add stock) - same logic as submit method
                $prodInventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item_id)->first();
                if (!$prodInventoryItem) {
                    $itemMaster = DB::table('items')->where('id', $item_id)->first();
                    $prodInventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                        'item_id' => $item_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $prodInventoryItemId = $prodInventoryItem->id;
                }
                
                $itemMaster = DB::table('items')->where('id', $item_id)->first();
                $unitJadi = DB::table('units')->where('id', $unit_jadi)->value('name');
                $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                
                $qty_small = 0;
                $qty_medium = 0;
                $qty_large = 0;
                
                if ($unitJadi === $unitSmall) {
                    $qty_small = $qty_jadi;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                } elseif ($unitJadi === $unitMedium) {
                    $qty_medium = $qty_jadi;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                } elseif ($unitJadi === $unitLarge) {
                    $qty_large = $qty_jadi;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } else {
                    $qty_small = $qty_jadi;
                }
                
                // Calculate cost from BOM
                $total_cost = 0;
                $total_qty_small = 0;
                foreach ($bom as $b) {
                    $bomInventory = DB::table('outlet_food_inventory_items')->where('item_id', $b->material_item_id)->first();
                    if ($bomInventory) {
                        $stock = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $bomInventory->id)
                            ->where('id_outlet', $outlet_id)
                            ->where('warehouse_outlet_id', $warehouse_outlet_id)
                            ->first();
                        
                        if ($stock) {
                            $itemMasterBom = DB::table('items')->where('id', $b->material_item_id)->first();
                            $unit = DB::table('units')->where('id', $b->unit_id)->value('name');
                            $qty_input = $b->qty * $qty_produksi;
                            $qty_small_bahan = 0;
                            
                            $unitSmallBom = DB::table('units')->where('id', $itemMasterBom->small_unit_id)->value('name');
                            $unitMediumBom = DB::table('units')->where('id', $itemMasterBom->medium_unit_id)->value('name');
                            $unitLargeBom = DB::table('units')->where('id', $itemMasterBom->large_unit_id)->value('name');
                            $smallConvBom = $itemMasterBom->small_conversion_qty ?: 1;
                            $mediumConvBom = $itemMasterBom->medium_conversion_qty ?: 1;
                            
                            if ($unit === $unitSmallBom) {
                                $qty_small_bahan = $qty_input;
                            } elseif ($unit === $unitMediumBom) {
                                $qty_small_bahan = $qty_input * $smallConvBom;
                            } elseif ($unit === $unitLargeBom) {
                                $qty_small_bahan = $qty_input * $smallConvBom * $mediumConvBom;
                            } else {
                                $qty_small_bahan = $qty_input;
                            }
                            
                            $total_cost += $qty_small_bahan * $stock->last_cost_small;
                            $total_qty_small += $qty_small_bahan;
                        }
                    }
                }
                
                $last_cost_small = $total_qty_small > 0 ? $total_cost / $total_qty_small : 0;
                $last_cost_medium = $smallConv > 0 ? $last_cost_small / $smallConv : 0;
                $last_cost_large = ($smallConv > 0 && $mediumConv > 0) ? $last_cost_small / ($smallConv * $mediumConv) : 0;
                
                // Update or insert production result stock
                $existingStock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $prodInventoryItemId)
                    ->where('id_outlet', $outlet_id)
                    ->where('warehouse_outlet_id', $warehouse_outlet_id)
                    ->first();
                
                if ($existingStock) {
                    $qty_baru_small = $existingStock->qty_small + $qty_small;
                    $qty_baru_medium = $existingStock->qty_medium + $qty_medium;
                    $qty_baru_large = $existingStock->qty_large + $qty_large;
                    $nilai_baru = $qty_baru_small * $last_cost_small;
                    
                    $saldo_qty_small = $qty_baru_small;
                    $saldo_qty_medium = $qty_baru_medium;
                    $saldo_qty_large = $qty_baru_large;
                    $saldo_value = $nilai_baru;
                    
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $existingStock->id)
                        ->update([
                            'qty_small' => $qty_baru_small,
                            'qty_medium' => $qty_baru_medium,
                            'qty_large' => $qty_baru_large,
                            'value' => $nilai_baru,
                            'last_cost_small' => $last_cost_small,
                            'last_cost_medium' => $last_cost_medium,
                            'last_cost_large' => $last_cost_large,
                            'updated_at' => now(),
                        ]);
                } else {
                    $saldo_qty_small = $qty_small;
                    $saldo_qty_medium = $qty_medium;
                    $saldo_qty_large = $qty_large;
                    $saldo_value = $qty_small * $last_cost_small;
                    
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $prodInventoryItemId,
                        'id_outlet' => $outlet_id,
                        'warehouse_outlet_id' => $warehouse_outlet_id,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $saldo_value,
                        'last_cost_small' => $last_cost_small,
                        'last_cost_medium' => $last_cost_medium,
                        'last_cost_large' => $last_cost_large,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                
                // Insert stock card IN
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $prodInventoryItemId,
                    'id_outlet' => $outlet_id,
                    'warehouse_outlet_id' => $warehouse_outlet_id,
                    'date' => $production_date,
                    'reference_type' => 'outlet_wip_production',
                    'reference_id' => $headerId,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'cost_per_small' => $last_cost_small,
                    'cost_per_medium' => $last_cost_medium,
                    'cost_per_large' => $last_cost_large,
                    'value_in' => $qty_small * $last_cost_small,
                    'saldo_qty_small' => $saldo_qty_small,
                    'saldo_qty_medium' => $saldo_qty_medium,
                    'saldo_qty_large' => $saldo_qty_large,
                    'saldo_value' => $saldo_value,
                    'description' => 'Hasil produksi WIP ' . $qty_produksi . ' x ' . $item_id,
                    'created_at' => now(),
                ]);
            }
            
            DB::commit();
            
            Log::info('[OutletWIP] StoreAndSubmit success', ['header_id' => $headerId]);
            
            // OPTIMASI: Clear cache untuk index (jika ada cache untuk list headers)
            // Cache akan auto-refresh saat diakses lagi
            
            return response()->json([
                'success' => true,
                'header_id' => $headerId,
                'message' => 'Produksi WIP berhasil disubmit'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[OutletWIP] StoreAndSubmit ERROR', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function show($id)
    {
        $user = auth()->user();
        
        // Cek apakah ini header atau production lama
        $header = DB::table('outlet_wip_production_headers')->where('id', $id)->first();
        $isOldData = false;
        
        if (!$header) {
            // Cek apakah ini data lama (production tanpa header_id)
            $oldProduction = DB::table('outlet_wip_productions')->where('id', $id)->whereNull('header_id')->first();
            if ($oldProduction) {
                $isOldData = true;
                // Create virtual header from old production
                $header = (object)[
                    'id' => $oldProduction->id,
                    'number' => null,
                    'production_date' => $oldProduction->production_date,
                    'batch_number' => $oldProduction->batch_number,
                    'outlet_id' => $oldProduction->outlet_id,
                    'warehouse_outlet_id' => $oldProduction->warehouse_outlet_id,
                    'notes' => $oldProduction->notes,
                    'status' => 'PROCESSED',
                    'created_by' => $oldProduction->created_by,
                    'created_at' => $oldProduction->created_at,
                    'updated_at' => $oldProduction->updated_at,
                ];
            } else {
                return redirect()->route('outlet-wip.index')->with('error', 'Data produksi tidak ditemukan');
            }
        }

        // Cek akses berdasarkan outlet
        if ($user->id_outlet != 1 && $header->outlet_id != $user->id_outlet) {
            return redirect()->route('outlet-wip.index')->with('error', 'Tidak memiliki akses ke data ini');
        }

        // Get productions
        if ($isOldData) {
            // Data lama: ambil production dengan id tersebut
            $productions = DB::table('outlet_wip_productions')
                ->leftJoin('items', 'outlet_wip_productions.item_id', '=', 'items.id')
                ->leftJoin('units', 'outlet_wip_productions.unit_id', '=', 'units.id')
                ->where('outlet_wip_productions.id', $id)
                ->select(
                    'outlet_wip_productions.*',
                    'items.name as item_name',
                    'units.name as unit_name'
                )
                ->get();
        } else {
            // Data baru: ambil dari header_id
            $productions = DB::table('outlet_wip_productions')
                ->leftJoin('items', 'outlet_wip_productions.item_id', '=', 'items.id')
                ->leftJoin('units', 'outlet_wip_productions.unit_id', '=', 'units.id')
                ->where('outlet_wip_productions.header_id', $id)
                ->select(
                    'outlet_wip_productions.id',
                    'outlet_wip_productions.header_id',
                    'outlet_wip_productions.item_id',
                    'outlet_wip_productions.qty',
                    'outlet_wip_productions.qty_jadi',
                    'outlet_wip_productions.unit_id',
                    'outlet_wip_productions.production_date',
                    'outlet_wip_productions.batch_number',
                    'outlet_wip_productions.outlet_id',
                    'outlet_wip_productions.warehouse_outlet_id',
                    'outlet_wip_productions.notes',
                    'outlet_wip_productions.created_by',
                    'outlet_wip_productions.created_at',
                    'outlet_wip_productions.updated_at',
                    'items.name as item_name',
                    'units.name as unit_name'
                )
                ->distinct()
                ->get();
        }

        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $header->outlet_id)->first();
        $warehouse_outlet = DB::table('warehouse_outlets')->where('id', $header->warehouse_outlet_id)->first();

        // Ambil kartu stok hasil produksi dengan nama item dan unit
        $stockCards = DB::table('outlet_food_inventory_cards as sc')
            ->leftJoin('outlet_food_inventory_items as inv_item', 'sc.inventory_item_id', '=', 'inv_item.id')
            ->leftJoin('items', 'inv_item.item_id', '=', 'items.id')
            ->leftJoin('units as item_unit', 'items.small_unit_id', '=', 'item_unit.id')
            ->where('sc.reference_type', 'outlet_wip_production')
            ->where('sc.reference_id', $id)
            ->select(
                'sc.id',
                'sc.inventory_item_id',
                'sc.id_outlet',
                'sc.warehouse_outlet_id',
                'sc.date',
                'sc.reference_type',
                'sc.reference_id',
                'sc.in_qty_small',
                'sc.in_qty_medium',
                'sc.in_qty_large',
                'sc.out_qty_small',
                'sc.out_qty_medium',
                'sc.out_qty_large',
                'sc.cost_per_small',
                'sc.cost_per_medium',
                'sc.cost_per_large',
                'sc.value_in',
                'sc.value_out',
                'sc.saldo_qty_small',
                'sc.saldo_qty_medium',
                'sc.saldo_qty_large',
                'sc.saldo_value',
                'sc.description',
                'sc.created_at',
                'sc.updated_at',
                'items.name as item_name',
                'item_unit.name as unit_name'
            )
            ->distinct()
            ->orderBy('sc.date')
            ->orderBy('sc.id')
            ->get();

        // Get BOM for each production item
        $bomData = [];
        foreach ($productions as $prod) {
            $bom = DB::table('item_bom')
                ->leftJoin('items as material', 'item_bom.material_item_id', '=', 'material.id')
                ->leftJoin('units', 'item_bom.unit_id', '=', 'units.id')
                ->where('item_bom.item_id', $prod->item_id)
                ->select(
                    'item_bom.*',
                    'material.name as material_name',
                    'units.name as unit_name'
                )
                ->get();
            
            $bomData[$prod->item_id] = $bom;
        }

        return Inertia::render('OutletWIP/Show', [
            'header' => $header,
            'productions' => $productions,
            'outlet' => $outlet,
            'warehouse_outlet' => $warehouse_outlet,
            'stockCards' => $stockCards,
            'bomData' => $bomData,
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        
        // Check permission: only superadmin or warehouse division can delete
        if ($user->id_role !== '5af56935b011a' && $user->division_id != 11) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menghapus data ini'], 403);
        }
        
        DB::beginTransaction();
        try {
            // Cek apakah ini header atau production lama
            $header = DB::table('outlet_wip_production_headers')->where('id', $id)->first();
            $isOldData = false;
            
            if (!$header) {
                // Cek apakah ini data lama (production tanpa header_id)
                $oldProduction = DB::table('outlet_wip_productions')->where('id', $id)->whereNull('header_id')->first();
                if ($oldProduction) {
                    $isOldData = true;
                    // Create virtual header from old production for access check
                    $header = (object)[
                        'id' => $oldProduction->id,
                        'outlet_id' => $oldProduction->outlet_id,
                        'status' => 'PROCESSED',
                    ];
                } else {
                    return response()->json(['success' => false, 'message' => 'Data produksi tidak ditemukan'], 404);
                }
            }

            // Cek akses berdasarkan outlet
            if ($user->id_outlet != 1 && $header->outlet_id != $user->id_outlet) {
                return response()->json(['success' => false, 'message' => 'Tidak memiliki akses ke data ini'], 403);
            }

            if ($isOldData) {
                // Handle data lama: hapus production langsung
                $production = DB::table('outlet_wip_productions')->where('id', $id)->first();
                
                if ($production) {
                    // Rollback stock for old data (always processed)
                    // rollbackStockForProduction already handles stock cards deletion
                    $this->rollbackStockForProduction($production, $production->outlet_id, $production->warehouse_outlet_id, $id);
                }
                
                // Delete production
                DB::table('outlet_wip_productions')->where('id', $id)->delete();
                
                $oldData = json_encode($production);
            } else {
                // Handle data baru: hapus header dan details
                // Only rollback stock if status is not DRAFT
                if ($header->status !== 'DRAFT') {
                    // Rollback stock for each production
                    $productions = DB::table('outlet_wip_productions')
                        ->where('header_id', $id)
                        ->get();
                    
                    foreach ($productions as $prod) {
                        $this->rollbackStockForProduction($prod, $header->outlet_id, $header->warehouse_outlet_id, $id);
                    }
                }

                // Delete production details
                DB::table('outlet_wip_productions')->where('header_id', $id)->delete();

                // Delete header
                DB::table('outlet_wip_production_headers')->where('id', $id)->delete();
                
                $oldData = json_encode($header);
            }

            // Activity log DELETE
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'outlet_wip_production',
                'description' => 'Menghapus produksi WIP outlet: ' . $id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => $oldData,
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

    private function rollbackStockForProduction($production, $outlet_id, $warehouse_outlet_id, $reference_id)
    {
        $item_id = $production->item_id;
        $qty_produksi = $production->qty;
        $qty_jadi = $production->qty_jadi;
        $unit_jadi = $production->unit_id;
        
        Log::info('[OutletWIP] Destroy - Rolling back stock for production', [
            'production_id' => $production->id ?? 'N/A',
            'item_id' => $item_id,
            'qty_produksi' => $qty_produksi,
            'qty_jadi' => $qty_jadi,
            'reference_id' => $reference_id
        ]);
        
        // Get BOM for this production
        $bom = DB::table('item_bom')->where('item_id', $item_id)->get();
        
        // Rollback stock for materials (add back stock that was deducted)
        foreach ($bom as $b) {
            $bomInventory = DB::table('outlet_food_inventory_items')->where('item_id', $b->material_item_id)->first();
            if (!$bomInventory) {
                Log::warning('[OutletWIP] Destroy - Material inventory item not found', [
                    'material_item_id' => $b->material_item_id
                ]);
                continue;
            }
            
            $stock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $bomInventory->id)
                ->where('id_outlet', $outlet_id)
                ->where('warehouse_outlet_id', $warehouse_outlet_id)
                ->first();
            
            if (!$stock) {
                Log::warning('[OutletWIP] Destroy - Stock not found for material', [
                    'material_item_id' => $b->material_item_id,
                    'inventory_item_id' => $bomInventory->id
                ]);
                continue;
            }
            
            // Calculate qty to rollback
            $itemMaster = DB::table('items')->where('id', $b->material_item_id)->first();
            $unit = DB::table('units')->where('id', $b->unit_id)->value('name');
            $qty_input = $b->qty * $qty_produksi;
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
            
            // Rollback stock (add back)
            $oldQtySmall = $stock->qty_small;
            DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $bomInventory->id)
                ->where('id_outlet', $outlet_id)
                ->where('warehouse_outlet_id', $warehouse_outlet_id)
                ->update([
                    'qty_small' => DB::raw('qty_small + ' . $qty_small),
                    'qty_medium' => DB::raw('qty_medium + ' . $qty_medium),
                    'qty_large' => DB::raw('qty_large + ' . $qty_large),
                    'updated_at' => now(),
                ]);
            
            Log::info('[OutletWIP] Destroy - Material stock rolled back', [
                'material_item_id' => $b->material_item_id,
                'qty_small_added' => $qty_small,
                'old_qty_small' => $oldQtySmall
            ]);
        }
        
        // Rollback stock for production result (reduce stock that was added)
        $prodInventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item_id)->first();
        if ($prodInventoryItem) {
            $itemMaster = DB::table('items')->where('id', $item_id)->first();
            $unit = DB::table('units')->where('id', $unit_jadi)->value('name');
            $qty_small = 0;
            $qty_medium = 0;
            $qty_large = 0;
            
            $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
            $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
            $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
            
            if ($unit === $unitSmall) {
                $qty_small = $qty_jadi;
                $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
            } elseif ($unit === $unitMedium) {
                $qty_medium = $qty_jadi;
                $qty_small = $qty_medium * $smallConv;
                $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
            } elseif ($unit === $unitLarge) {
                $qty_large = $qty_jadi;
                $qty_medium = $qty_large * $mediumConv;
                $qty_small = $qty_medium * $smallConv;
            } else {
                $qty_small = $qty_jadi;
            }
            
            $prodStock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $prodInventoryItem->id)
                ->where('id_outlet', $outlet_id)
                ->where('warehouse_outlet_id', $warehouse_outlet_id)
                ->first();
            
            if ($prodStock) {
                $oldQtySmall = $prodStock->qty_small;
                // Rollback stock (reduce)
                DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $prodInventoryItem->id)
                    ->where('id_outlet', $outlet_id)
                    ->where('warehouse_outlet_id', $warehouse_outlet_id)
                    ->update([
                        'qty_small' => DB::raw('qty_small - ' . $qty_small),
                        'qty_medium' => DB::raw('qty_medium - ' . $qty_medium),
                        'qty_large' => DB::raw('qty_large - ' . $qty_large),
                        'updated_at' => now(),
                    ]);
                
                Log::info('[OutletWIP] Destroy - Production result stock rolled back', [
                    'item_id' => $item_id,
                    'qty_small_reduced' => $qty_small,
                    'old_qty_small' => $oldQtySmall
                ]);
            } else {
                Log::warning('[OutletWIP] Destroy - Production result stock not found', [
                    'item_id' => $item_id,
                    'inventory_item_id' => $prodInventoryItem->id
                ]);
            }
        }
        
        // Delete stock cards (both OUT for materials and IN for production result)
        $cardsDeleted = DB::table('outlet_food_inventory_cards')
            ->where('reference_type', 'outlet_wip_production')
            ->where('reference_id', $reference_id)
            ->delete();
        
        Log::info('[OutletWIP] Destroy - Stock cards deleted', [
            'reference_id' => $reference_id,
            'cards_deleted' => $cardsDeleted
        ]);
    }

    /**
     * API: Index data untuk mobile app (JSON).
     */
    public function apiIndex(Request $request)
    {
        $request->merge([
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
            'per_page' => $request->input('per_page', 10),
            'page' => $request->input('page', 1),
        ]);
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        $perPage = (int) $request->input('per_page', 10);
        $currentPage = (int) $request->input('page', 1);
        $id_outlet = $user->id_outlet ?? null;

        $queryHeaders = DB::table('outlet_wip_production_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select(
                'h.id',
                'h.number',
                'h.production_date',
                'h.batch_number',
                'h.outlet_id',
                'h.warehouse_outlet_id',
                'h.notes',
                'h.status',
                'h.created_by',
                'h.created_at',
                'h.updated_at',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as created_by_name',
                DB::raw("'header' as source_type")
            );
        if ($id_outlet && $id_outlet != 1) {
            $queryHeaders->where('h.outlet_id', $id_outlet);
        }
        if ($request->filled('date_from')) {
            $queryHeaders->whereDate('h.production_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $queryHeaders->whereDate('h.production_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $queryHeaders->where(function ($q) use ($search) {
                $q->where('h.number', 'like', "%{$search}%")
                    ->orWhere('h.batch_number', 'like', "%{$search}%")
                    ->orWhere('h.notes', 'like', "%{$search}%")
                    ->orWhere('h.status', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                    ->orWhere('wo.name', 'like', "%{$search}%")
                    ->orWhere('u.nama_lengkap', 'like', "%{$search}%");
            });
        }

        $queryOld = DB::table('outlet_wip_productions as p')
            ->leftJoin('tbl_data_outlet as o', 'p.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'p.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'p.created_by', '=', 'u.id')
            ->whereNull('p.header_id')
            ->select(
                'p.id',
                DB::raw("NULL as number"),
                'p.production_date',
                'p.batch_number',
                'p.outlet_id',
                'p.warehouse_outlet_id',
                'p.notes',
                DB::raw("'PROCESSED' as status"),
                'p.created_by',
                'p.created_at',
                'p.updated_at',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as created_by_name',
                DB::raw("'old' as source_type")
            );
        if ($id_outlet && $id_outlet != 1) {
            $queryOld->where('p.outlet_id', $id_outlet);
        }
        if ($request->filled('date_from')) {
            $queryOld->whereDate('p.production_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $queryOld->whereDate('p.production_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $queryOld->where(function ($q) use ($search) {
                $q->where('p.batch_number', 'like', "%{$search}%")
                    ->orWhere('p.notes', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                    ->orWhere('wo.name', 'like', "%{$search}%")
                    ->orWhere('u.nama_lengkap', 'like', "%{$search}%");
            });
        }

        $headersSql = $queryHeaders->toSql();
        $headersBindings = $queryHeaders->getBindings();
        $oldSql = $queryOld->toSql();
        $oldBindings = $queryOld->getBindings();
        $unionSql = "SELECT * FROM (({$headersSql}) UNION ALL ({$oldSql})) as combined_results ORDER BY production_date DESC, id DESC";
        $allBindings = array_merge($headersBindings, $oldBindings);
        $countSql = "SELECT COUNT(*) as total FROM (({$headersSql}) UNION ALL ({$oldSql})) as combined_results";
        $total = DB::selectOne($countSql, $allBindings)->total ?? 0;
        $offset = ($currentPage - 1) * $perPage;
        $paginatedSql = $unionSql . " LIMIT {$perPage} OFFSET {$offset}";
        $combined = collect(DB::select($paginatedSql, $allBindings));

        $headerIds = $combined->where('source_type', 'header')->pluck('id')->toArray();
        $oldIds = $combined->where('source_type', 'old')->pluck('id')->toArray();
        $productionsByHeader = [];

        if (!empty($headerIds)) {
            $productions = DB::table('outlet_wip_productions as p')
                ->leftJoin('items', 'p.item_id', '=', 'items.id')
                ->leftJoin('units', 'p.unit_id', '=', 'units.id')
                ->whereIn('p.header_id', $headerIds)
                ->select('p.header_id', 'p.item_id', 'p.qty', 'p.qty_jadi', 'p.unit_id', 'items.name as item_name', 'units.name as unit_name')
                ->get();
            foreach ($productions as $prod) {
                $productionsByHeader[$prod->header_id][] = (array) $prod;
            }
        }
        if (!empty($oldIds)) {
            $oldProductions = DB::table('outlet_wip_productions as p')
                ->leftJoin('items', 'p.item_id', '=', 'items.id')
                ->leftJoin('units', 'p.unit_id', '=', 'units.id')
                ->whereIn('p.id', $oldIds)
                ->select(DB::raw('p.id as header_id'), 'p.item_id', 'p.qty', 'p.qty_jadi', 'p.unit_id', 'items.name as item_name', 'units.name as unit_name')
                ->get();
            foreach ($oldProductions as $prod) {
                $productionsByHeader[$prod->header_id][] = (array) $prod;
            }
        }

        $data = $combined->map(function ($row) {
            return (array) $row;
        })->values()->all();

        return response()->json([
            'data' => $data,
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => (int) $total,
            'last_page' => (int) ceil($total / $perPage),
            'productions_by_header' => $productionsByHeader,
            'can_delete' => $canDelete,
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'search' => $request->search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * API: Create form data untuk mobile app (JSON).
     */
    public function apiCreateData()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $items = Cache::remember('outlet_wip_items', 3600, function () {
            return DB::table('items')
                ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
                ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
                ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->where('items.composition_type', 'composed')
                ->where('items.status', 'active')
                ->where('items.type', 'WIP')
                ->where('categories.show_pos', '0')
                ->select(
                    'items.id',
                    'items.name',
                    'items.small_unit_id',
                    'items.medium_unit_id',
                    'items.large_unit_id',
                    'small_unit.name as small_unit_name',
                    'medium_unit.name as medium_unit_name',
                    'large_unit.name as large_unit_name'
                )
                ->get();
        });
        $cacheKey = $user->id_outlet == 1 ? 'outlet_wip_warehouse_outlets_all' : 'outlet_wip_warehouse_outlets_' . $user->id_outlet;
        $warehouse_outlets = Cache::remember($cacheKey, 3600, function () use ($user) {
            if ($user->id_outlet == 1) {
                return DB::table('warehouse_outlets')->where('status', 'active')->select('id', 'name', 'outlet_id')->orderBy('name')->get();
            }
            return DB::table('warehouse_outlets')->where('outlet_id', $user->id_outlet)->where('status', 'active')->select('id', 'name', 'outlet_id')->orderBy('name')->get();
        });
        $outlets = [];
        if ($user->id_outlet == 1) {
            $outlets = Cache::remember('outlet_wip_outlets_all', 3600, function () {
                return DB::table('tbl_data_outlet')->where('status', 'A')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
            });
        }
        return response()->json([
            'items' => $items,
            'warehouse_outlets' => $warehouse_outlets,
            'outlets' => $outlets,
            'user_outlet_id' => $user->id_outlet,
        ]);
    }

    /**
     * API: Show detail untuk mobile app (JSON).
     */
    public function apiShow($id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $header = DB::table('outlet_wip_production_headers')->where('id', $id)->first();
        $isOldData = false;
        if (!$header) {
            $oldProduction = DB::table('outlet_wip_productions')->where('id', $id)->whereNull('header_id')->first();
            if ($oldProduction) {
                $isOldData = true;
                $header = (object) [
                    'id' => $oldProduction->id,
                    'number' => null,
                    'production_date' => $oldProduction->production_date,
                    'batch_number' => $oldProduction->batch_number,
                    'outlet_id' => $oldProduction->outlet_id,
                    'warehouse_outlet_id' => $oldProduction->warehouse_outlet_id,
                    'notes' => $oldProduction->notes,
                    'status' => 'PROCESSED',
                    'created_by' => $oldProduction->created_by,
                    'created_at' => $oldProduction->created_at,
                    'updated_at' => $oldProduction->updated_at,
                ];
            } else {
                return response()->json(['error' => 'Data produksi tidak ditemukan'], 404);
            }
        }
        if ($user->id_outlet != 1 && $header->outlet_id != $user->id_outlet) {
            return response()->json(['error' => 'Tidak memiliki akses ke data ini'], 403);
        }
        if ($isOldData) {
            $productions = DB::table('outlet_wip_productions as p')
                ->leftJoin('items', 'p.item_id', '=', 'items.id')
                ->leftJoin('units', 'p.unit_id', '=', 'units.id')
                ->where('p.id', $id)
                ->select('p.*', 'items.name as item_name', 'units.name as unit_name')
                ->get();
        } else {
            $productions = DB::table('outlet_wip_productions as p')
                ->leftJoin('items', 'p.item_id', '=', 'items.id')
                ->leftJoin('units', 'p.unit_id', '=', 'units.id')
                ->where('p.header_id', $id)
                ->select('p.id', 'p.header_id', 'p.item_id', 'p.qty', 'p.qty_jadi', 'p.unit_id', 'p.production_date', 'p.batch_number', 'p.outlet_id', 'p.warehouse_outlet_id', 'p.notes', 'p.created_by', 'p.created_at', 'p.updated_at', 'items.name as item_name', 'units.name as unit_name')
                ->get();
        }
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $header->outlet_id)->first();
        $warehouse_outlet = DB::table('warehouse_outlets')->where('id', $header->warehouse_outlet_id)->first();
        $stockCards = DB::table('outlet_food_inventory_cards as sc')
            ->leftJoin('outlet_food_inventory_items as inv_item', 'sc.inventory_item_id', '=', 'inv_item.id')
            ->leftJoin('items', 'inv_item.item_id', '=', 'items.id')
            ->leftJoin('units as item_unit', 'items.small_unit_id', '=', 'item_unit.id')
            ->where('sc.reference_type', 'outlet_wip_production')
            ->where('sc.reference_id', $id)
            ->select('sc.*', 'items.name as item_name', 'item_unit.name as unit_name')
            ->orderBy('sc.date')->orderBy('sc.id')
            ->get();
        $bomData = [];
        foreach ($productions as $prod) {
            $bom = DB::table('item_bom')
                ->leftJoin('items as material', 'item_bom.material_item_id', '=', 'material.id')
                ->leftJoin('units', 'item_bom.unit_id', '=', 'units.id')
                ->where('item_bom.item_id', $prod->item_id)
                ->select('item_bom.*', 'material.name as material_name', 'units.name as unit_name')
                ->get();
            $bomData[$prod->item_id] = $bom;
        }
        return response()->json([
            'header' => $header,
            'productions' => $productions,
            'outlet' => $outlet,
            'warehouse_outlet' => $warehouse_outlet,
            'stock_cards' => $stockCards,
            'bom_data' => $bomData,
        ]);
    }

    /**
     * API: Report data untuk mobile app (JSON).
     */
    public function apiReport(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $query = DB::table('outlet_wip_productions')
            ->leftJoin('items', 'outlet_wip_productions.item_id', '=', 'items.id')
            ->leftJoin('tbl_data_outlet as o', 'outlet_wip_productions.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'outlet_wip_productions.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'outlet_wip_productions.*',
                'items.name as item_name',
                'items.exp as item_exp',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name'
            );
        if ($user->id_outlet != 1) {
            $query->where('outlet_wip_productions.outlet_id', $user->id_outlet);
        }
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('outlet_wip_productions.production_date', [$request->start_date, $request->end_date]);
        }
        $productions = $query->orderByDesc('outlet_wip_productions.production_date')->get();
        $productions = $productions->map(function ($row) {
            $exp_days = $row->item_exp ?? 0;
            $prod_date = $row->production_date;
            $exp_date = $prod_date ? (\Carbon\Carbon::parse($prod_date)->addDays($exp_days)->toDateString()) : null;
            $row->exp_date = $exp_date;
            return $row;
        });
        return response()->json([
            'productions' => $productions,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);
    }

    public function report(Request $request)
    {
        $user = auth()->user();
        
        $query = DB::table('outlet_wip_productions')
            ->leftJoin('items', 'outlet_wip_productions.item_id', '=', 'items.id')
            ->leftJoin('tbl_data_outlet as o', 'outlet_wip_productions.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'outlet_wip_productions.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'outlet_wip_productions.*',
                'items.name as item_name',
                'items.exp as item_exp',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name'
            );

        // Filter berdasarkan outlet user
        if ($user->id_outlet != 1) {
            $query->where('outlet_wip_productions.outlet_id', $user->id_outlet);
        }

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('outlet_wip_productions.production_date', [$request->start_date, $request->end_date]);
        }

        $productions = $query->orderByDesc('outlet_wip_productions.production_date')->get();

        // Hitung exp_date di backend
        $productions = $productions->map(function ($row) {
            $exp_days = $row->item_exp ?? 0;
            $prod_date = $row->production_date;
            $exp_date = $prod_date ? (\Carbon\Carbon::parse($prod_date)->addDays($exp_days)->toDateString()) : null;
            $row->exp_date = $exp_date;
            return $row;
        });

        return Inertia::render('OutletWIP/Report', [
            'productions' => $productions,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);
    }
}
