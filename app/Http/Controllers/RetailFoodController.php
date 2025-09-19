<?php

namespace App\Http\Controllers;

use App\Models\RetailFood;
use App\Models\RetailFoodItem;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RetailFoodController extends Controller
{
    private function generateRetailNumber()
    {
        $prefix = 'RF';
        $date = date('Ymd');
        
        // Cari nomor terakhir hari ini (termasuk data yang soft deleted)
        $lastNumber = RetailFood::withTrashed()
            ->where('retail_number', 'like', $prefix . $date . '%')
            ->orderBy('retail_number', 'desc')
            ->first();
            
        if ($lastNumber) {
            $sequence = (int) substr($lastNumber->retail_number, -4) + 1;
        } else {
            $sequence = 1;
        }
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function index(Request $request)
    {
        $user = auth()->user()->load('outlet');
        $userOutletId = $user->id_outlet;
        $outletExists = \DB::table('tbl_data_outlet')->where('id_outlet', $userOutletId)->exists();
        if (!$outletExists && $userOutletId != 1) {
            abort(403, 'Outlet tidak terdaftar');
        }

        // Get filter parameters
        $search = $request->get('search', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $paymentMethod = $request->get('payment_method', '');

        // Query dengan join warehouse outlet dan supplier
        $query = RetailFood::query()
            ->with(['outlet', 'creator', 'items', 'supplier'])
            ->leftJoin('warehouse_outlets as wo', 'retail_food.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('suppliers as s', 'retail_food.supplier_id', '=', 's.id')
            ->addSelect('retail_food.*', 'wo.name as warehouse_outlet_name', 's.name as supplier_name')
            ->orderByDesc('retail_food.created_at');

        // Apply outlet filter
        if ($userOutletId != 1) {
            $query->where('retail_food.outlet_id', $userOutletId);
        }

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('retail_food.retail_number', 'like', "%{$search}%")
                  ->orWhere('s.name', 'like', "%{$search}%")
                  ->orWhere('wo.name', 'like', "%{$search}%")
                  ->orWhereHas('outlet', function($outletQuery) use ($search) {
                      $outletQuery->where('nama_outlet', 'like', "%{$search}%");
                  });
            });
        }

        // Apply date filter
        if ($dateFrom) {
            $query->whereDate('retail_food.transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('retail_food.transaction_date', '<=', $dateTo);
        }

        // Apply payment method filter
        if ($paymentMethod) {
            $query->where('retail_food.payment_method', $paymentMethod);
        }

        $retailFoods = $query->paginate(10)->withQueryString();

        return Inertia::render('RetailFood/Index', [
            'user' => $user,
            'retailFoods' => $retailFoods,
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'payment_method' => $paymentMethod,
            ]
        ]);
    }

    public function create()
    {
        $user = auth()->user()->load('outlet');
        $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);
        // Ambil warehouse outlet hanya untuk outlet user dan status aktif
        if ($user->id_outlet == 1) {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        } else {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('outlet_id', $user->id_outlet)
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        }
        
        // Ambil data supplier
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
            
        return Inertia::render('RetailFood/Form', [
            'user' => $user,
            'outlets' => $outlets,
            'warehouse_outlets' => $warehouse_outlets,
            'suppliers' => $suppliers,
        ]);
    }

    private function getBudgetInfoForResponse($items, $outletId)
    {
        $budgetInfo = [];
        foreach ($items as $item) {
            $itemMaster = DB::table('items')->where('name', $item['item_name'])->first();
            if ($itemMaster && $itemMaster->sub_category_id) {
                $lockedBudget = DB::table('locked_budget_food_categories')
                    ->where('sub_category_id', $itemMaster->sub_category_id)
                    ->where('outlet_id', $outletId)
                    ->first();
                
                if ($lockedBudget) {
                    $subCategoryInfo = DB::table('sub_categories as sc')
                        ->join('categories as c', 'sc.category_id', '=', 'c.id')
                        ->where('sc.id', $itemMaster->sub_category_id)
                        ->select('sc.name as sub_category_name', 'c.name as category_name')
                        ->first();
                    
                    $currentMonth = date('Y-m');
                    $retailFoodTotal = DB::table('retail_food_items as rfi')
                        ->join('retail_food as rf', 'rfi.retail_food_id', '=', 'rf.id')
                        ->join('items as i', DB::raw('TRIM(i.name)'), '=', DB::raw('TRIM(rfi.item_name)'))
                        ->where('i.sub_category_id', $itemMaster->sub_category_id)
                        ->where('rf.outlet_id', $outletId)
                        ->where('rf.status', 'approved')
                        ->whereRaw("DATE_FORMAT(rf.transaction_date, '%Y-%m') = ?", [$currentMonth])
                        ->sum('rfi.subtotal');
                    
                    $foodFloorOrderTotal = DB::table('food_floor_order_items as ffoi')
                        ->join('food_floor_orders as ffo', 'ffoi.floor_order_id', '=', 'ffo.id')
                        ->join('items as i', 'ffoi.item_id', '=', 'i.id')
                        ->where('i.sub_category_id', $itemMaster->sub_category_id)
                        ->where('ffo.id_outlet', $outletId)
                        ->whereIn('ffo.status', ['approved', 'received'])
                        ->whereRaw("DATE_FORMAT(ffo.tanggal, '%Y-%m') = ?", [$currentMonth])
                        ->sum('ffoi.subtotal');
                    
                    $monthlyTotal = $retailFoodTotal + $foodFloorOrderTotal;
                    $remainingBudget = $lockedBudget->budget - $monthlyTotal;
                    
                    $budgetInfo[] = [
                        'item_name' => $item['item_name'],
                        'category_name' => $subCategoryInfo->category_name ?? 'N/A',
                        'sub_category_name' => $subCategoryInfo->sub_category_name ?? 'N/A',
                        'budget_amount' => $lockedBudget->budget,
                        'retail_food_total' => $retailFoodTotal,
                        'food_floor_order_total' => $foodFloorOrderTotal,
                        'monthly_total' => $monthlyTotal,
                        'remaining_budget' => $remainingBudget,
                        'budget_percentage' => $monthlyTotal > 0 ? round(($monthlyTotal / $lockedBudget->budget) * 100, 2) : 0
                    ];
                }
            }
        }
        return $budgetInfo;
    }

    public function store(Request $request)
    {
        \Log::info('RETAIL_FOOD_STORE: Mulai proses simpan retail food', [
            'user_id' => auth()->id(),
            'request_data' => $request->all(),
            'has_files' => $request->hasFile('invoices'),
            'file_count' => $request->hasFile('invoices') ? count($request->file('invoices')) : 0
        ]);

        try {
            \Log::info('RETAIL_FOOD_STORE: Mulai validasi request');
            $request->validate([
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'transaction_date' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.item_name' => 'required|string',
                'items.*.qty' => 'required|numeric|min:0',
                'items.*.unit' => 'required|string',
                'items.*.price' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
                'payment_method' => 'required|in:cash,contra_bon',
                'supplier_id' => 'nullable|exists:suppliers,id',
            ]);
            \Log::info('RETAIL_FOOD_STORE: Validasi berhasil');

            // Validasi tambahan untuk warehouse_outlet_id dan supplier_id
            if ($request->warehouse_outlet_id) {
                $warehouseExists = DB::table('warehouse_outlets')
                    ->where('id', $request->warehouse_outlet_id)
                    ->where('status', 'active')
                    ->exists();
                if (!$warehouseExists) {
                    \Log::error('RETAIL_FOOD_STORE: Warehouse outlet tidak ditemukan atau tidak aktif', [
                        'warehouse_outlet_id' => $request->warehouse_outlet_id
                    ]);
                    return response()->json([
                        'message' => 'Warehouse outlet tidak ditemukan atau tidak aktif'
                    ], 422);
                }
            }

            if ($request->supplier_id) {
                $supplierExists = DB::table('suppliers')
                    ->where('id', $request->supplier_id)
                    ->where('status', 'active')
                    ->exists();
                if (!$supplierExists) {
                    \Log::error('RETAIL_FOOD_STORE: Supplier tidak ditemukan atau tidak aktif', [
                        'supplier_id' => $request->supplier_id
                    ]);
                    return response()->json([
                        'message' => 'Supplier tidak ditemukan atau tidak aktif'
                    ], 422);
                }
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('RETAIL_FOOD_STORE: Validasi gagal', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            \Log::info('RETAIL_FOOD_STORE: Mulai transaction database');
            DB::beginTransaction();

            // Generate nomor retail food
            \Log::info('RETAIL_FOOD_STORE: Generate retail number');
            $retailNumber = $this->generateRetailNumber();
            \Log::info('RETAIL_FOOD_STORE: Retail number generated', ['retail_number' => $retailNumber]);

            // Hitung total amount
            \Log::info('RETAIL_FOOD_STORE: Hitung total amount');
            $totalAmount = collect($request->items)->sum(function ($item) {
                return $item['qty'] * $item['price'];
            });
            \Log::info('RETAIL_FOOD_STORE: Total amount calculated', ['total_amount' => $totalAmount]);

            // Cek total transaksi hari ini
            \Log::info('RETAIL_FOOD_STORE: Cek total transaksi hari ini');
            $dailyTotal = RetailFood::whereDate('transaction_date', $request->transaction_date)
                ->where('status', 'approved')
                ->sum('total_amount');
            \Log::info('RETAIL_FOOD_STORE: Daily total checked', ['daily_total' => $dailyTotal]);

            // Buat retail food
            \Log::info('RETAIL_FOOD_STORE: Buat retail food record', [
                'outlet_id' => $request->outlet_id,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'supplier_id' => $request->supplier_id,
                'payment_method' => $request->payment_method
            ]);
            $retailFood = RetailFood::create([
                'retail_number' => $retailNumber,
                'outlet_id' => $request->outlet_id,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'created_by' => auth()->id(),
                'transaction_date' => $request->transaction_date,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'payment_method' => $request->payment_method,
                'supplier_id' => $request->supplier_id,
                'status' => 'approved'
            ]);
            \Log::info('RETAIL_FOOD_STORE: Retail food created', ['retail_food_id' => $retailFood->id]);

            // Simpan items dan proses inventory outlet
            \Log::info('RETAIL_FOOD_STORE: Mulai proses items', ['item_count' => count($request->items)]);
            foreach ($request->items as $index => $item) {
                \Log::info('RETAIL_FOOD_STORE: Proses item ke-' . ($index + 1), [
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'price' => $item['price']
                ]);
                
                // 1. Cari item master
                \Log::info('RETAIL_FOOD_STORE: Cari item master', ['item_name' => $item['item_name']]);
                $itemMaster = DB::table('items')->where('name', $item['item_name'])->first();
                if (!$itemMaster) {
                    \Log::error('RETAIL_FOOD_STORE: Item tidak ditemukan', ['item_name' => $item['item_name']]);
                    throw new \Exception('Item tidak ditemukan: ' . $item['item_name']);
                }
                \Log::info('RETAIL_FOOD_STORE: Item master ditemukan', [
                    'item_id' => $itemMaster->id,
                    'item_name' => $itemMaster->name,
                    'sub_category_id' => $itemMaster->sub_category_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'requested_unit_id' => $item['unit_id']
                ]);
                
                // Budget checking untuk sub_category_id yang ada di locked_budget_food_categories
                if ($itemMaster->sub_category_id) {
                    \Log::info('RETAIL_FOOD_STORE: Cek budget untuk sub_category_id', [
                        'sub_category_id' => $itemMaster->sub_category_id,
                        'outlet_id' => $request->outlet_id
                    ]);
                    
                    // Cek apakah sub_category_id ada di locked_budget_food_categories
                    $lockedBudget = DB::table('locked_budget_food_categories')
                        ->where('sub_category_id', $itemMaster->sub_category_id)
                        ->where('outlet_id', $request->outlet_id)
                        ->first();
                    
                    if ($lockedBudget) {
                        // Ambil informasi sub category dan category
                        $subCategoryInfo = DB::table('sub_categories as sc')
                            ->join('categories as c', 'sc.category_id', '=', 'c.id')
                            ->where('sc.id', $itemMaster->sub_category_id)
                            ->select('sc.name as sub_category_name', 'c.name as category_name')
                            ->first();
                        
                        \Log::info('RETAIL_FOOD_STORE: Budget ditemukan', [
                            'budget_id' => $lockedBudget->id,
                            'budget_amount' => $lockedBudget->budget,
                            'category_name' => $subCategoryInfo->category_name ?? 'N/A',
                            'sub_category_name' => $subCategoryInfo->sub_category_name ?? 'N/A'
                        ]);
                        
                        // Hitung total transaksi bulan berjalan untuk sub_category_id ini
                        $currentMonth = date('Y-m');
                        
                        // 1. Total dari retail_food_items
                        $retailFoodQuery = DB::table('retail_food_items as rfi')
                            ->join('retail_food as rf', 'rfi.retail_food_id', '=', 'rf.id')
                            ->join('items as i', DB::raw('TRIM(i.name)'), '=', DB::raw('TRIM(rfi.item_name)'))
                            ->where('i.sub_category_id', $itemMaster->sub_category_id)
                            ->where('rf.outlet_id', $request->outlet_id)
                            ->where('rf.status', 'approved')
                            ->whereRaw("DATE_FORMAT(rf.transaction_date, '%Y-%m') = ?", [$currentMonth]);
                        
                        \Log::info('RETAIL_FOOD_STORE: Retail Food Query Debug', [
                            'sql' => $retailFoodQuery->toSql(),
                            'bindings' => $retailFoodQuery->getBindings(),
                            'sub_category_id' => $itemMaster->sub_category_id,
                            'outlet_id' => $request->outlet_id,
                            'current_month' => $currentMonth
                        ]);
                        
                        $retailFoodTotal = $retailFoodQuery->sum('rfi.subtotal');
                        
                        // 2. Total dari food_floor_order_items
                        $foodFloorOrderQuery = DB::table('food_floor_order_items as ffoi')
                            ->join('food_floor_orders as ffo', 'ffoi.floor_order_id', '=', 'ffo.id')
                            ->join('items as i', 'ffoi.item_id', '=', 'i.id')
                            ->where('i.sub_category_id', $itemMaster->sub_category_id)
                            ->where('ffo.id_outlet', $request->outlet_id)
                            ->whereIn('ffo.status', ['approved', 'received'])
                            ->whereRaw("DATE_FORMAT(ffo.tanggal, '%Y-%m') = ?", [$currentMonth]);
                        
                        \Log::info('RETAIL_FOOD_STORE: Food Floor Order Query Debug', [
                            'sql' => $foodFloorOrderQuery->toSql(),
                            'bindings' => $foodFloorOrderQuery->getBindings(),
                            'sub_category_id' => $itemMaster->sub_category_id,
                            'outlet_id' => $request->outlet_id,
                            'current_month' => $currentMonth
                        ]);
                        
                        $foodFloorOrderTotal = $foodFloorOrderQuery->sum('ffoi.subtotal');
                        
                        // 3. Total gabungan
                        $monthlyTotal = $retailFoodTotal + $foodFloorOrderTotal;
                        
                        \Log::info('RETAIL_FOOD_STORE: Total bulanan saat ini', [
                            'retail_food_total' => $retailFoodTotal,
                            'food_floor_order_total' => $foodFloorOrderTotal,
                            'monthly_total' => $monthlyTotal,
                            'current_month' => $currentMonth,
                            'sub_category_id' => $itemMaster->sub_category_id,
                            'outlet_id' => $request->outlet_id
                        ]);
                        
                        // Hitung subtotal item baru
                        $newItemSubtotal = $item['qty'] * $item['price'];
                        
                        // Total setelah ditambah item baru
                        $totalAfterNewItem = $monthlyTotal + $newItemSubtotal;
                        
                        \Log::info('RETAIL_FOOD_STORE: Perhitungan budget', [
                            'retail_food_total' => $retailFoodTotal,
                            'food_floor_order_total' => $foodFloorOrderTotal,
                            'monthly_total' => $monthlyTotal,
                            'new_item_subtotal' => $newItemSubtotal,
                            'total_after_new_item' => $totalAfterNewItem,
                            'budget_limit' => $lockedBudget->budget
                        ]);
                        
                        // Cek apakah melebihi budget
                        if ($totalAfterNewItem > $lockedBudget->budget) {
                            \Log::error('RETAIL_FOOD_STORE: Budget terlampaui', [
                                'total_after_new_item' => $totalAfterNewItem,
                                'budget_limit' => $lockedBudget->budget,
                                'excess_amount' => $totalAfterNewItem - $lockedBudget->budget
                            ]);
                            
                            DB::rollBack();
                            return response()->json([
                                'message' => "Transaksi ditolak! Budget untuk sub kategori '{$subCategoryInfo->sub_category_name}' (Kategori: {$subCategoryInfo->category_name}) telah terlampaui.\n\n" .
                                           "📊 Detail Budget:\n" .
                                           "• Budget yang ditetapkan: Rp " . number_format($lockedBudget->budget, 0, ',', '.') . "\n" .
                                           "• Total Retail Food (bulan ini): Rp " . number_format($retailFoodTotal, 0, ',', '.') . "\n" .
                                           "• Total Food Floor Order (bulan ini): Rp " . number_format($foodFloorOrderTotal, 0, ',', '.') . "\n" .
                                           "• Total Gabungan: Rp " . number_format($totalAfterNewItem, 0, ',', '.') . "\n" .
                                           "• Kelebihan: Rp " . number_format($totalAfterNewItem - $lockedBudget->budget, 0, ',', '.')
                            ], 422);
                        }
                        
                        \Log::info('RETAIL_FOOD_STORE: Budget check passed', [
                            'category_name' => $subCategoryInfo->category_name ?? 'N/A',
                            'sub_category_name' => $subCategoryInfo->sub_category_name ?? 'N/A',
                            'retail_food_total' => $retailFoodTotal,
                            'food_floor_order_total' => $foodFloorOrderTotal,
                            'total_after_new_item' => $totalAfterNewItem,
                            'budget_limit' => $lockedBudget->budget,
                            'remaining_budget' => $lockedBudget->budget - $totalAfterNewItem
                        ]);
                    } else {
                        \Log::info('RETAIL_FOOD_STORE: Tidak ada budget lock untuk sub_category_id', [
                            'sub_category_id' => $itemMaster->sub_category_id
                        ]);
                    }
                } else {
                    \Log::info('RETAIL_FOOD_STORE: Item tidak memiliki sub_category_id');
                }
                
                // Validasi unit_id
                $validUnits = [$itemMaster->small_unit_id, $itemMaster->medium_unit_id, $itemMaster->large_unit_id];
                if (!in_array($item['unit_id'], $validUnits)) {
                    \Log::error('RETAIL_FOOD_STORE: Unit tidak valid', [
                        'item_name' => $item['item_name'],
                        'requested_unit_id' => $item['unit_id'],
                        'valid_units' => $validUnits
                    ]);
                    throw new \Exception('Unit tidak valid untuk item: ' . $item['item_name']);
                }
                \Log::info('RETAIL_FOOD_STORE: Unit valid', ['unit_id' => $item['unit_id']]);
                
                // 2. Cek/insert outlet_food_inventory_items
                \Log::info('RETAIL_FOOD_STORE: Cek inventory item');
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $itemMaster->id)
                    ->first();
                if (!$inventoryItem) {
                    \Log::info('RETAIL_FOOD_STORE: Buat inventory item baru');
                    $inventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                        'item_id' => $itemMaster->id,
                        'small_unit_id' => $itemMaster->small_unit_id,
                        'medium_unit_id' => $itemMaster->medium_unit_id,
                        'large_unit_id' => $itemMaster->large_unit_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    \Log::info('RETAIL_FOOD_STORE: Inventory item dibuat', ['inventory_item_id' => $inventoryItemId]);
                } else {
                    $inventoryItemId = $inventoryItem->id;
                    \Log::info('RETAIL_FOOD_STORE: Inventory item sudah ada', ['inventory_item_id' => $inventoryItemId]);
                }
                // 3. Konversi qty ke small, medium, large
                \Log::info('RETAIL_FOOD_STORE: Konversi quantity', [
                    'unit_id' => $item['unit_id'],
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'small_conv' => $itemMaster->small_conversion_qty,
                    'medium_conv' => $itemMaster->medium_conversion_qty
                ]);
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                $qty_small = 0; $qty_medium = 0; $qty_large = 0; $qty_small_for_value = 0;
                if ($item['unit_id'] == $itemMaster->large_unit_id) {
                    $qty_large = (float) $item['qty'];
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_small_for_value = $qty_small;
                } elseif ($item['unit_id'] == $itemMaster->medium_unit_id) {
                    $qty_medium = (float) $item['qty'];
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_small_for_value = $qty_small;
                } elseif ($item['unit_id'] == $itemMaster->small_unit_id) {
                    $qty_small = (float) $item['qty'];
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    $qty_small_for_value = $qty_small;
                }
                \Log::info('RETAIL_FOOD_STORE: Quantity converted', [
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'qty_small_for_value' => $qty_small_for_value
                ]);
                
                // 4. Hitung cost
                \Log::info('RETAIL_FOOD_STORE: Hitung cost', ['price' => $item['price']]);
                $cost = $item['price'];
                $cost_small = $cost;
                if ($item['unit_id'] == $itemMaster->large_unit_id) {
                    $cost_small = $cost / (($itemMaster->small_conversion_qty ?: 1) * ($itemMaster->medium_conversion_qty ?: 1));
                } elseif ($item['unit_id'] == $itemMaster->medium_unit_id) {
                    $cost_small = $cost / ($itemMaster->small_conversion_qty ?: 1);
                }
                $cost_medium = $cost_small * ($itemMaster->small_conversion_qty ?: 1);
                $cost_large = $cost_medium * ($itemMaster->medium_conversion_qty ?: 1);
                \Log::info('RETAIL_FOOD_STORE: Cost calculated', [
                    'cost_small' => $cost_small,
                    'cost_medium' => $cost_medium,
                    'cost_large' => $cost_large
                ]);
                
                // 5. Insert/update outlet_food_inventory_stocks (MAC) - Mengikuti pola Good Receive Outlet Food
                \Log::info('RETAIL_FOOD_STORE: Cek existing stock');
                $existingStock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $request->outlet_id)
                    ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
                    ->first();
                $qty_lama = $existingStock ? $existingStock->qty_small : 0;
                $nilai_lama = $existingStock ? $existingStock->value : 0;
                $qty_baru = $qty_small;
                $nilai_baru = $qty_small_for_value * $cost_small;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $cost_small;
                \Log::info('RETAIL_FOOD_STORE: Stock calculation', [
                    'qty_lama' => $qty_lama,
                    'nilai_lama' => $nilai_lama,
                    'qty_baru' => $qty_baru,
                    'nilai_baru' => $nilai_baru,
                    'total_qty' => $total_qty,
                    'total_nilai' => $total_nilai,
                    'mac' => $mac
                ]);
                if ($existingStock) {
                    \Log::info('RETAIL_FOOD_STORE: Update existing stock', ['stock_id' => $existingStock->id]);
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $existingStock->id)
                        ->update([
                            'qty_small' => $total_qty,
                            'qty_medium' => $existingStock->qty_medium + $qty_medium,
                            'qty_large' => $existingStock->qty_large + $qty_large,
                            'value' => $total_nilai,
                            'last_cost_small' => $mac,
                            'last_cost_medium' => $cost_medium,
                            'last_cost_large' => $cost_large,
                            'updated_at' => now(),
                        ]);
                    \Log::info('RETAIL_FOOD_STORE: Stock berhasil diupdate');
                } else {
                    \Log::info('RETAIL_FOOD_STORE: Insert new stock');
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'id_outlet' => $request->outlet_id,
                        'warehouse_outlet_id' => $request->warehouse_outlet_id,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $nilai_baru,
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $cost_medium,
                        'last_cost_large' => $cost_large,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    \Log::info('RETAIL_FOOD_STORE: Stock baru berhasil diinsert');
                }
                
                // 6. Hitung saldo kartu stok (stock card) - Mengikuti pola Good Receive Outlet Food
                \Log::info('RETAIL_FOOD_STORE: Hitung saldo kartu stok');
                $lastCard = DB::table('outlet_food_inventory_cards')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $request->outlet_id)
                    ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->first();
                if ($lastCard) {
                    $saldo_qty_small = $lastCard->saldo_qty_small + $qty_small;
                    $saldo_qty_medium = $lastCard->saldo_qty_medium + $qty_medium;
                    $saldo_qty_large = $lastCard->saldo_qty_large + $qty_large;
                    \Log::info('RETAIL_FOOD_STORE: Saldo dari kartu terakhir', [
                        'last_saldo_small' => $lastCard->saldo_qty_small,
                        'last_saldo_medium' => $lastCard->saldo_qty_medium,
                        'last_saldo_large' => $lastCard->saldo_qty_large
                    ]);
                } else {
                    $saldo_qty_small = $qty_small;
                    $saldo_qty_medium = $qty_medium;
                    $saldo_qty_large = $qty_large;
                    \Log::info('RETAIL_FOOD_STORE: Kartu stok pertama');
                }
                
                // 7. Insert ke outlet_food_inventory_cards
                \Log::info('RETAIL_FOOD_STORE: Insert kartu stok');
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $request->outlet_id,
                    'warehouse_outlet_id' => $request->warehouse_outlet_id,
                    'date' => $request->transaction_date,
                    'reference_type' => 'retail_food',
                    'reference_id' => $retailFood->id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'out_qty_small' => 0,
                    'out_qty_medium' => 0,
                    'out_qty_large' => 0,
                    'cost_per_small' => $mac,
                    'cost_per_medium' => $cost_medium,
                    'cost_per_large' => $cost_large,
                    'value_in' => $qty_small_for_value * $cost_small,
                    'value_out' => 0,
                    'saldo_qty_small' => $saldo_qty_small,
                    'saldo_qty_medium' => $saldo_qty_medium,
                    'saldo_qty_large' => $saldo_qty_large,
                    'saldo_value' => $saldo_qty_small * $mac,
                    'description' => 'Retail Food: ' . $retailNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                \Log::info('RETAIL_FOOD_STORE: Kartu stok berhasil diinsert');
                
                // 8. Insert ke outlet_food_inventory_cost_histories - Mengikuti pola Good Receive Outlet Food
                \Log::info('RETAIL_FOOD_STORE: Insert cost history');
                $lastCostHistory = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $request->outlet_id)
                    ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $request->outlet_id,
                    'warehouse_outlet_id' => $request->warehouse_outlet_id,
                    'date' => $request->transaction_date,
                    'old_cost' => $old_cost,
                    'new_cost' => $cost_small,
                    'mac' => $mac,
                    'type' => 'retail_food',
                    'reference_type' => 'retail_food',
                    'reference_id' => $retailFood->id,
                    'created_at' => now(),
                ]);
                \Log::info('RETAIL_FOOD_STORE: Cost history berhasil diinsert');
                
                // 9. Simpan item retail food
                \Log::info('RETAIL_FOOD_STORE: Simpan retail food item');
                RetailFoodItem::create([
                    'retail_food_id' => $retailFood->id,
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price']
                ]);
                \Log::info('RETAIL_FOOD_STORE: Retail food item berhasil disimpan');
            }

            \Log::info('RETAIL_FOOD_STORE: Semua items berhasil diproses');
            
            // Setelah RetailFood berhasil dibuat
            \Log::info('RETAIL_FOOD_STORE: Proses file upload');
            if ($request->hasFile('invoices')) {
                \Log::info('RETAIL_FOOD_STORE: Ada file invoices', ['file_count' => count($request->file('invoices'))]);
                foreach ($request->file('invoices') as $index => $file) {
                    \Log::info('RETAIL_FOOD_STORE: Proses file ke-' . ($index + 1), [
                        'filename' => $file->getClientOriginalName(),
                        'extension' => $file->extension(),
                        'size' => $file->getSize()
                    ]);
                    if (in_array($file->extension(), ['jpg', 'jpeg', 'png'])) {
                        $path = $file->store('retail_food_invoices', 'public');
                        \Log::info('RETAIL_FOOD_STORE: File disimpan', ['path' => $path]);
                        $retailFood->invoices()->create([
                            'file_path' => $path
                        ]);
                    } else {
                        \Log::warning('RETAIL_FOOD_STORE: File extension tidak didukung', ['extension' => $file->extension()]);
                    }
                }
            } else {
                \Log::info('RETAIL_FOOD_STORE: Tidak ada file invoices');
            }

            \Log::info('RETAIL_FOOD_STORE: Commit transaction');
            DB::commit();
            \Log::info('RETAIL_FOOD_STORE: Transaction berhasil di-commit');

            // Activity log
            \Log::info('RETAIL_FOOD_STORE: Insert activity log');
            try {
                DB::table('activity_logs')->insert([
                    'user_id' => auth()->id(),
                    'activity_type' => 'create',
                    'module' => 'retail_food',
                    'description' => 'Membuat retail food: ' . $retailNumber,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => null,
                    'new_data' => json_encode([
                        'retail_number' => $retailNumber,
                        'outlet_id' => $request->outlet_id,
                        'total_amount' => $totalAmount,
                        'payment_method' => $request->payment_method,
                        'item_count' => count($request->items)
                    ]),
                    'created_at' => now()
                ]);
                \Log::info('RETAIL_FOOD_STORE: Activity log berhasil diinsert');
            } catch (\Exception $e) {
                \Log::warning('RETAIL_FOOD_STORE: Gagal insert activity log', [
                    'error' => $e->getMessage()
                ]);
                // Tidak throw error karena activity log bukan critical
            }

            // Cek apakah total hari ini sudah melebihi 500rb
            \Log::info('RETAIL_FOOD_STORE: Cek limit harian', [
                'daily_total' => $dailyTotal,
                'total_amount' => $totalAmount,
                'total_combined' => $dailyTotal + $totalAmount,
                'limit' => 500000
            ]);
            if ($dailyTotal + $totalAmount >= 500000) {
                \Log::info('RETAIL_FOOD_STORE: Total hari ini melebihi 500rb', ['daily_total' => $dailyTotal, 'total_amount' => $totalAmount]);
                
                // Kumpulkan informasi budget yang di-lock untuk response
                $budgetInfo = $this->getBudgetInfoForResponse($request->items, $request->outlet_id);
                
                $response = [
                    'message' => 'Transaksi berhasil disimpan, namun total pembelian hari ini sudah melebihi Rp 500.000',
                    'data' => $retailFood->load('items')
                ];
                
                if (!empty($budgetInfo)) {
                    $response['budget_info'] = $budgetInfo;
                }
                
                return response()->json($response, 201);
            }

            // Kumpulkan informasi budget yang di-lock untuk response
            $budgetInfo = $this->getBudgetInfoForResponse($request->items, $request->outlet_id);

            \Log::info('RETAIL_FOOD_STORE: Transaksi berhasil disimpan');
            \Log::info('RETAIL_FOOD_STORE: Response success', [
                'retail_food_id' => $retailFood->id,
                'retail_number' => $retailNumber,
                'total_amount' => $totalAmount,
                'budget_info_count' => count($budgetInfo)
            ]);
            
            $response = [
                'message' => 'Transaksi berhasil disimpan',
                'data' => $retailFood->load('items')
            ];
            
            // Tambahkan informasi budget jika ada
            if (!empty($budgetInfo)) {
                $response['budget_info'] = $budgetInfo;
            }
            
            return response()->json($response, 201);

        } catch (\Exception $e) {
            \Log::error('RETAIL_FOOD_STORE: Error terjadi', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'user_id' => auth()->id()
            ]);
            DB::rollBack();
            \Log::info('RETAIL_FOOD_STORE: Transaction di-rollback');
            return response()->json([
                'message' => 'Gagal menyimpan transaksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $retailFood = RetailFood::with(['outlet', 'creator', 'items', 'invoices', 'supplier'])
            ->findOrFail($id);

        return Inertia::render('RetailFood/Detail', [
            'retailFood' => $retailFood
        ]);
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            
            // Check if user has permission to delete (only admin with id_outlet = 1)
            if ($user->id_outlet !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk menghapus transaksi retail food'
                ], 403);
            }

            $retailFood = RetailFood::findOrFail($id);

            \Log::info('RETAIL_FOOD_DELETE: Starting deletion process', [
                'user_id' => $user->id,
                'retail_food_id' => $id,
                'retail_number' => $retailFood->retail_number
            ]);

            DB::beginTransaction();

            // Get items to rollback inventory
            $items = $retailFood->items;
            
            // Rollback inventory for each item
            foreach ($items as $item) {
                $this->rollbackInventory($item, $retailFood->outlet_id);
            }

            // Delete retail food items
            $retailFood->items()->delete();
            
            // Delete retail food
            $retailFood->delete();

            DB::commit();

            \Log::info('RETAIL_FOOD_DELETE: Deletion completed successfully', [
                'retail_food_id' => $id,
                'retail_number' => $retailFood->retail_number
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi retail food berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('RETAIL_FOOD_DELETE: Deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'retail_food_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    private function rollbackInventory($item, $outletId)
    {
        try {
            // Find item master
            $itemMaster = DB::table('items')->where('name', $item->item_name)->first();
            if (!$itemMaster) {
                \Log::warning('RETAIL_FOOD_DELETE: Item master not found', ['item_name' => $item->item_name]);
                return;
            }

            // Find inventory item
            $inventoryItem = DB::table('food_inventory_items')->where('item_id', $itemMaster->id)->first();
            if (!$inventoryItem) {
                \Log::warning('RETAIL_FOOD_DELETE: Inventory item not found', ['item_id' => $itemMaster->id]);
                return;
            }

            $inventory_item_id = $inventoryItem->id;
            $unit = $item->unit;
            $qty_input = $item->qty;
            
            // Get unit names
            $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
            $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
            $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

            // Convert quantity to small unit
            $qty_small = 0;
            if ($unit === $unitSmall) {
                $qty_small = $qty_input;
            } elseif ($unit === $unitMedium) {
                $qty_small = $qty_input * $smallConv;
            } elseif ($unit === $unitLarge) {
                $qty_small = $qty_input * $smallConv * $mediumConv;
            } else {
                $qty_small = $qty_input;
            }

            // Find outlet inventory stock
            $stock = DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('outlet_id', $outletId)
                ->first();

            if ($stock) {
                // Rollback stock (subtract the quantity)
                DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('outlet_id', $outletId)
                    ->update([
                        'qty_small' => $stock->qty_small - $qty_small,
                        'updated_at' => now()
                    ]);

                \Log::info('RETAIL_FOOD_DELETE: Inventory rolled back', [
                    'item_name' => $item->item_name,
                    'qty_removed' => $qty_small,
                    'new_stock' => $stock->qty_small - $qty_small
                ]);
            } else {
                \Log::warning('RETAIL_FOOD_DELETE: Stock not found for rollback', [
                    'inventory_item_id' => $inventory_item_id,
                    'outlet_id' => $outletId
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('RETAIL_FOOD_DELETE: Rollback inventory failed', [
                'error' => $e->getMessage(),
                'item_name' => $item->item_name
            ]);
        }
    }

    public function getBudgetInfo(Request $request)
    {
        try {
            $request->validate([
                'items' => 'required|array',
                'items.*.item_name' => 'required|string',
                'items.*.qty' => 'required|numeric|min:0',
                'items.*.price' => 'required|numeric|min:0',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            ]);

            // Group items by sub_category_id
            $subCategoryGroups = [];
            foreach ($request->items as $item) {
                $itemMaster = DB::table('items')->where('name', $item['item_name'])->first();
                if ($itemMaster && $itemMaster->sub_category_id) {
                    $subCategoryId = $itemMaster->sub_category_id;
                    if (!isset($subCategoryGroups[$subCategoryId])) {
                        $subCategoryGroups[$subCategoryId] = [
                            'sub_category_id' => $subCategoryId,
                            'items' => []
                        ];
                    }
                    $subCategoryGroups[$subCategoryId]['items'][] = $item;
                }
            }

            $budgetInfo = [];
            foreach ($subCategoryGroups as $subCategoryId => $group) {
                $lockedBudget = DB::table('locked_budget_food_categories')
                    ->where('sub_category_id', $subCategoryId)
                    ->where('outlet_id', $request->outlet_id)
                    ->first();
                
                if ($lockedBudget) {
                    $subCategoryInfo = DB::table('sub_categories as sc')
                        ->join('categories as c', 'sc.category_id', '=', 'c.id')
                        ->where('sc.id', $subCategoryId)
                        ->select('sc.name as sub_category_name', 'c.name as category_name')
                        ->first();
                    
                    $currentMonth = date('Y-m');
                    $retailFoodTotal = DB::table('retail_food_items as rfi')
                        ->join('retail_food as rf', 'rfi.retail_food_id', '=', 'rf.id')
                        ->join('items as i', 'rfi.item_name', '=', 'i.name')
                        ->where('i.sub_category_id', $subCategoryId)
                        ->where('rf.outlet_id', $request->outlet_id)
                        ->where('rf.status', 'approved')
                        ->whereRaw("DATE_FORMAT(rf.transaction_date, '%Y-%m') = ?", [$currentMonth])
                        ->sum('rfi.subtotal');
                    
                    $foodFloorOrderTotal = DB::table('food_floor_order_items as ffoi')
                        ->join('food_floor_orders as ffo', 'ffoi.floor_order_id', '=', 'ffo.id')
                        ->join('items as i', 'ffoi.item_id', '=', 'i.id')
                        ->where('i.sub_category_id', $subCategoryId)
                        ->where('ffo.id_outlet', $request->outlet_id)
                        ->where('ffo.status', 'approved')
                        ->whereRaw("DATE_FORMAT(ffo.tanggal, '%Y-%m') = ?", [$currentMonth])
                        ->sum('ffoi.subtotal');
                    
                    $monthlyTotal = $retailFoodTotal + $foodFloorOrderTotal;
                    
                    // Sum all new items for this sub category
                    $newItemsTotal = 0;
                    $itemNames = [];
                    foreach ($group['items'] as $item) {
                        $newItemsTotal += $item['qty'] * $item['price'];
                        $itemNames[] = $item['item_name'];
                    }
                    
                    $totalAfterNewItems = $monthlyTotal + $newItemsTotal;
                    $remainingBudget = $lockedBudget->budget - $totalAfterNewItems;
                    
                    $budgetInfo[] = [
                        'sub_category_id' => $subCategoryId,
                        'category_name' => $subCategoryInfo->category_name ?? 'N/A',
                        'sub_category_name' => $subCategoryInfo->sub_category_name ?? 'N/A',
                        'item_names' => $itemNames,
                        'budget_amount' => $lockedBudget->budget,
                        'retail_food_total' => $retailFoodTotal,
                        'food_floor_order_total' => $foodFloorOrderTotal,
                        'monthly_total' => $monthlyTotal,
                        'new_items_total' => $newItemsTotal,
                        'total_after_new_items' => $totalAfterNewItems,
                        'remaining_budget' => $remainingBudget,
                        'budget_percentage' => $totalAfterNewItems > 0 ? round(($totalAfterNewItems / $lockedBudget->budget) * 100, 2) : 0
                    ];
                }
            }

            return response()->json([
                'budget_info' => $budgetInfo
            ]);

        } catch (\Exception $e) {
            \Log::error('GET_BUDGET_INFO: Error occurred', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'budget_info' => []
            ], 500);
        }
    }

    public function getItemUnits(Request $request, $itemId)
    {
        \Log::info('getItemUnits called', [
            'itemId' => $itemId,
            'payment_method' => $request->get('payment_method'),
            'outlet_id' => $request->get('outlet_id'),
            'region_id' => $request->get('region_id')
        ]);

        $item = \DB::table('items')->where('id', $itemId)->first();
        if (!$item) {
            \Log::warning('Item not found', ['itemId' => $itemId]);
            return response()->json(['units' => []]);
        }

        $paymentMethod = $request->get('payment_method', 'cash');
        $outletId = $request->get('outlet_id');
        $regionId = $request->get('region_id');

        // Get unit names
        $unitSmall = \DB::table('units')->where('id', $item->small_unit_id)->value('name');
        $unitMedium = \DB::table('units')->where('id', $item->medium_unit_id)->value('name');
        $unitLarge = \DB::table('units')->where('id', $item->large_unit_id)->value('name');

        $units = [];
        $defaultUnit = null;
        $defaultPrice = 0;

        // Build units array
        if ($item->small_unit_id) {
            $units[] = [
                'id' => $item->small_unit_id,
                'name' => $unitSmall,
                'is_medium' => false
            ];
        }
        if ($item->medium_unit_id) {
            $units[] = [
                'id' => $item->medium_unit_id,
                'name' => $unitMedium,
                'is_medium' => true
            ];
        }
        if ($item->large_unit_id) {
            $units[] = [
                'id' => $item->large_unit_id,
                'name' => $unitLarge,
                'is_medium' => false
            ];
        }

        // For contra bon payment method, prioritize medium unit and get price
        if ($paymentMethod === 'contra_bon') {
            \Log::info('Processing contra bon payment method', [
                'item_id' => $itemId,
                'medium_unit_id' => $item->medium_unit_id,
                'small_unit_id' => $item->small_unit_id
            ]);

            // Set default unit to medium if available
            if ($item->medium_unit_id) {
                $defaultUnit = [
                    'id' => $item->medium_unit_id,
                    'name' => $unitMedium
                ];
                \Log::info('Using medium unit as default', ['unit' => $defaultUnit]);
            } else {
                // Fallback to small unit if medium not available
                $defaultUnit = [
                    'id' => $item->small_unit_id,
                    'name' => $unitSmall
                ];
                \Log::info('Using small unit as default (medium not available)', ['unit' => $defaultUnit]);
            }

            // Get price from item_prices with same priority as floor order
            $price = \DB::table('item_prices')
                ->where('item_id', $itemId)
                ->where(function($q) use ($outletId, $regionId) {
                    $q->where('availability_price_type', 'all');
                    if ($outletId) {
                        $q->orWhere(function($q2) use ($outletId) {
                            $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outletId);
                        });
                    }
                    if ($regionId) {
                        $q->orWhere(function($q2) use ($regionId) {
                            $q2->where('availability_price_type', 'region')->where('region_id', $regionId);
                        });
                    }
                })
                ->orderByRaw("CASE 
                    WHEN availability_price_type = 'outlet' THEN 1
                    WHEN availability_price_type = 'region' THEN 2
                    ELSE 3 END")
                ->orderByDesc('id')
                ->first();

            \Log::info('Price query result', [
                'price_found' => $price ? true : false,
                'price_data' => $price
            ]);

            if ($price) {
                $finalPrice = $price->price;
                // Round up to nearest 100 (same as floor order)
                $defaultPrice = ceil($finalPrice / 100) * 100;
                \Log::info('Price calculated', [
                    'original_price' => $finalPrice,
                    'rounded_price' => $defaultPrice
                ]);
            } else {
                \Log::warning('No price found for item', ['item_id' => $itemId]);
            }
        }

        $response = [
            'units' => $units,
            'default_unit' => $defaultUnit,
            'default_price' => $defaultPrice,
            'payment_method' => $paymentMethod
        ];

        \Log::info('getItemUnits response', $response);

        return response()->json($response);
    }

    public function dailyTotal(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'transaction_date' => 'required|date',
            'payment_method' => 'nullable|in:cash,contra_bon',
        ]);
        
        $query = RetailFood::where('outlet_id', $request->outlet_id)
            ->whereDate('transaction_date', $request->transaction_date);
            
        // Jika payment_method diisi, filter berdasarkan metode pembayaran
        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }
        
        $total = $query->sum('total_amount');
        return response()->json(['total' => $total]);
    }

    /**
     * Debug method untuk testing query budget
     */
    public function debugBudgetQuery(Request $request)
    {
        $request->validate([
            'sub_category_id' => 'required|integer',
            'outlet_id' => 'required|integer'
        ]);

        $subCategoryId = $request->sub_category_id;
        $outletId = $request->outlet_id;
        $currentMonth = date('Y-m');

        // Test Retail Food Query
        $retailFoodQuery = DB::table('retail_food_items as rfi')
            ->join('retail_food as rf', 'rfi.retail_food_id', '=', 'rf.id')
            ->join('items as i', DB::raw('TRIM(i.name)'), '=', DB::raw('TRIM(rfi.item_name)'))
            ->where('i.sub_category_id', $subCategoryId)
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereRaw("DATE_FORMAT(rf.transaction_date, '%Y-%m') = ?", [$currentMonth]);

        $retailFoodTotal = $retailFoodQuery->sum('rfi.subtotal');
        $retailFoodRecords = $retailFoodQuery->select('rfi.*', 'rf.transaction_date', 'i.name as item_name_master')->get();

        // Test Food Floor Order Query
        $foodFloorOrderQuery = DB::table('food_floor_order_items as ffoi')
            ->join('food_floor_orders as ffo', 'ffoi.floor_order_id', '=', 'ffo.id')
            ->join('items as i', 'ffoi.item_id', '=', 'i.id')
            ->where('i.sub_category_id', $subCategoryId)
            ->where('ffo.id_outlet', $outletId)
            ->whereIn('ffo.status', ['approved', 'received'])
            ->whereRaw("DATE_FORMAT(ffo.tanggal, '%Y-%m') = ?", [$currentMonth]);

        $foodFloorOrderTotal = $foodFloorOrderQuery->sum('ffoi.subtotal');
        $foodFloorOrderRecords = $foodFloorOrderQuery->select('ffoi.*', 'ffo.tanggal', 'i.name as item_name_master')->get();

        return response()->json([
            'debug_info' => [
                'sub_category_id' => $subCategoryId,
                'outlet_id' => $outletId,
                'current_month' => $currentMonth,
                'retail_food_query_sql' => $retailFoodQuery->toSql(),
                'retail_food_query_bindings' => $retailFoodQuery->getBindings(),
                'retail_food_total' => $retailFoodTotal,
                'retail_food_records_count' => $retailFoodRecords->count(),
                'retail_food_records' => $retailFoodRecords,
                'food_floor_order_query_sql' => $foodFloorOrderQuery->toSql(),
                'food_floor_order_query_bindings' => $foodFloorOrderQuery->getBindings(),
                'food_floor_order_total' => $foodFloorOrderTotal,
                'food_floor_order_records_count' => $foodFloorOrderRecords->count(),
                'food_floor_order_records' => $foodFloorOrderRecords,
                'total_combined' => $retailFoodTotal + $foodFloorOrderTotal
            ]
        ]);
    }
} 