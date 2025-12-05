<?php

namespace App\Http\Controllers;

use App\Models\RetailWarehouseFood;
use App\Models\RetailWarehouseFoodItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RetailWarehouseFoodController extends Controller
{
    private function generateRetailNumber()
    {
        $prefix = 'RWF';
        $date = date('Ymd');
        
        // Cari nomor terakhir hari ini (termasuk data yang soft deleted)
        $lastNumber = RetailWarehouseFood::withTrashed()
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
        $user = auth()->user();

        // Get filter parameters
        $search = $request->get('search', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $paymentMethod = $request->get('payment_method', '');

        // Query dengan join warehouse, warehouse division, dan supplier
        $query = RetailWarehouseFood::query()
            ->with(['warehouse', 'warehouseDivision', 'creator', 'items', 'supplier'])
            ->leftJoin('warehouses as w', 'retail_warehouse_food.warehouse_id', '=', 'w.id')
            ->leftJoin('warehouse_division as wd', 'retail_warehouse_food.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('suppliers as s', 'retail_warehouse_food.supplier_id', '=', 's.id')
            ->addSelect('retail_warehouse_food.*', 'w.name as warehouse_name', 'wd.name as warehouse_division_name', 's.name as supplier_name')
            ->orderByDesc('retail_warehouse_food.created_at');

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('retail_warehouse_food.retail_number', 'like', "%{$search}%")
                  ->orWhere('s.name', 'like', "%{$search}%")
                  ->orWhere('w.name', 'like', "%{$search}%")
                  ->orWhere('wd.name', 'like', "%{$search}%");
            });
        }

        // Apply date filter
        if ($dateFrom) {
            $query->whereDate('retail_warehouse_food.transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('retail_warehouse_food.transaction_date', '<=', $dateTo);
        }

        // Apply payment method filter
        if ($paymentMethod) {
            $query->where('retail_warehouse_food.payment_method', $paymentMethod);
        }

        $retailWarehouseFoods = $query->paginate(10)->withQueryString();

        return Inertia::render('RetailWarehouseFood/Index', [
            'user' => $user,
            'retailWarehouseFoods' => $retailWarehouseFoods,
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
        $user = auth()->user();
        
        // Ambil semua warehouse aktif
        $warehouses = DB::table('warehouses')
            ->where('status', 'active')
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
        
        // Ambil warehouse divisions
        $warehouseDivisions = DB::table('warehouse_division')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        // Ambil data supplier
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
            
        return Inertia::render('RetailWarehouseFood/Form', [
            'user' => $user,
            'warehouses' => $warehouses,
            'warehouseDivisions' => $warehouseDivisions,
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'warehouse_division_id' => 'nullable|exists:warehouse_division,id',
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

            // Validasi supplier_id
            if ($request->supplier_id) {
                $supplierExists = DB::table('suppliers')
                    ->where('id', $request->supplier_id)
                    ->where('status', 'active')
                    ->exists();
                if (!$supplierExists) {
                    return response()->json([
                        'message' => 'Supplier tidak ditemukan atau tidak aktif'
                    ], 422);
                }
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generate nomor retail warehouse food
            $retailNumber = $this->generateRetailNumber();

            // Hitung total amount
            $totalAmount = collect($request->items)->sum(function ($item) {
                return $item['qty'] * $item['price'];
            });

            // Buat retail warehouse food
            $retailWarehouseFood = RetailWarehouseFood::create([
                'retail_number' => $retailNumber,
                'warehouse_id' => $request->warehouse_id,
                'warehouse_division_id' => $request->warehouse_division_id,
                'created_by' => auth()->id(),
                'transaction_date' => $request->transaction_date,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'payment_method' => $request->payment_method,
                'supplier_id' => $request->supplier_id,
                'status' => 'approved'
            ]);

            // Simpan items dan proses inventory warehouse (mengikuti Food Good Receive)
            foreach ($request->items as $index => $item) {
                
                // 1. Cari item master
                $itemMaster = DB::table('items')->where('name', $item['item_name'])->first();
                if (!$itemMaster) {
                    throw new \Exception('Item tidak ditemukan: ' . $item['item_name']);
                }
                
                // Validasi unit_id
                $validUnits = [$itemMaster->small_unit_id, $itemMaster->medium_unit_id, $itemMaster->large_unit_id];
                if (!in_array($item['unit_id'], $validUnits)) {
                    throw new \Exception('Unit tidak valid untuk item: ' . $item['item_name']);
                }
                
                // 2. Cek/insert food_inventory_items
                $inventoryItem = DB::table('food_inventory_items')->where('item_id', $itemMaster->id)->first();
                if (!$inventoryItem) {
                    $inventoryItemId = DB::table('food_inventory_items')->insertGetId([
                        'item_id' => $itemMaster->id,
                        'small_unit_id' => $itemMaster->small_unit_id,
                        'medium_unit_id' => $itemMaster->medium_unit_id,
                        'large_unit_id' => $itemMaster->large_unit_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $inventoryItemId = $inventoryItem->id;
                }
                
                // 3. Konversi qty ke small, medium, large
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
                
                // 4. Hitung cost
                $cost = $item['price'];
                $cost_small = $cost;
                if ($item['unit_id'] == $itemMaster->large_unit_id) {
                    $cost_small = $cost / (($itemMaster->small_conversion_qty ?: 1) * ($itemMaster->medium_conversion_qty ?: 1));
                } elseif ($item['unit_id'] == $itemMaster->medium_unit_id) {
                    $cost_small = $cost / ($itemMaster->small_conversion_qty ?: 1);
                }
                $cost_medium = $cost_small * ($itemMaster->small_conversion_qty ?: 1);
                $cost_large = $cost_medium * ($itemMaster->medium_conversion_qty ?: 1);
                
                // 5. Insert/update food_inventory_stocks (MAC) - Mengikuti pola Food Good Receive
                $existingStock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->first();
                $qty_lama = $existingStock ? $existingStock->qty_small : 0;
                $nilai_lama = $existingStock ? $existingStock->value : 0;
                $qty_baru = $qty_small;
                $nilai_baru = $qty_small_for_value * $cost_small;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $cost_small;
                if ($existingStock) {
                    DB::table('food_inventory_stocks')
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
                } else {
                    DB::table('food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'warehouse_id' => $request->warehouse_id,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $nilai_baru,
                        'last_cost_small' => $cost_small,
                        'last_cost_medium' => $cost_medium,
                        'last_cost_large' => $cost_large,
                        'updated_at' => now(),
                    ]);
                }
                
                // 6. Hitung saldo kartu stok (stock card)
                $lastCard = DB::table('food_inventory_cards')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->first();
                if ($lastCard) {
                    $saldo_qty_small = $lastCard->saldo_qty_small + $qty_small;
                    $saldo_qty_medium = $lastCard->saldo_qty_medium + $qty_medium;
                    $saldo_qty_large = $lastCard->saldo_qty_large + $qty_large;
                } else {
                    $saldo_qty_small = $qty_small;
                    $saldo_qty_medium = $qty_medium;
                    $saldo_qty_large = $qty_large;
                }
                
                // 7. Insert ke food_inventory_cards
                DB::table('food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'warehouse_id' => $request->warehouse_id,
                    'date' => $request->transaction_date,
                    'reference_type' => 'retail_warehouse_food',
                    'reference_id' => $retailWarehouseFood->id,
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
                    'description' => 'Retail Warehouse Food: ' . $retailNumber,
                    'created_at' => now(),
                ]);
                
                // 8. Insert ke food_inventory_cost_histories
                $lastCostHistory = DB::table('food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                DB::table('food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'warehouse_id' => $request->warehouse_id,
                    'warehouse_division_id' => $request->warehouse_division_id,
                    'date' => $request->transaction_date,
                    'old_cost' => $old_cost,
                    'new_cost' => $cost_small,
                    'mac' => $mac,
                    'type' => 'retail_warehouse_food',
                    'reference_type' => 'retail_warehouse_food',
                    'reference_id' => $retailWarehouseFood->id,
                    'created_at' => now(),
                ]);
                
                // 9. Simpan item retail warehouse food
                RetailWarehouseFoodItem::create([
                    'retail_warehouse_food_id' => $retailWarehouseFood->id,
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price']
                ]);
            }

            // Upload invoices jika ada
            if ($request->hasFile('invoices')) {
                foreach ($request->file('invoices') as $index => $file) {
                    if (in_array($file->extension(), ['jpg', 'jpeg', 'png'])) {
                        $path = $file->store('retail_warehouse_food_invoices', 'public');
                        $retailWarehouseFood->invoices()->create([
                            'file_path' => $path
                        ]);
                    }
                }
            }

            DB::commit();

            // Activity log
            try {
                DB::table('activity_logs')->insert([
                    'user_id' => auth()->id(),
                    'activity_type' => 'create',
                    'module' => 'retail_warehouse_food',
                    'description' => 'Membuat retail warehouse food: ' . $retailNumber,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => null,
                    'new_data' => json_encode([
                        'retail_number' => $retailNumber,
                        'warehouse_id' => $request->warehouse_id,
                        'total_amount' => $totalAmount,
                        'payment_method' => $request->payment_method,
                        'item_count' => count($request->items)
                    ]),
                    'created_at' => now()
                ]);
            } catch (\Exception $e) {
                // Tidak throw error karena activity log bukan critical
            }

            return response()->json([
                'message' => 'Transaksi berhasil disimpan',
                'data' => $retailWarehouseFood->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('RetailWarehouseFood Store Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Gagal menyimpan transaksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $retailWarehouseFood = RetailWarehouseFood::with(['warehouse', 'warehouseDivision', 'creator', 'items', 'invoices', 'supplier'])
            ->findOrFail($id);

        return Inertia::render('RetailWarehouseFood/Detail', [
            'retailWarehouseFood' => $retailWarehouseFood
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
                    'message' => 'Anda tidak memiliki izin untuk menghapus transaksi retail warehouse food'
                ], 403);
            }

            $retailWarehouseFood = RetailWarehouseFood::findOrFail($id);

            DB::beginTransaction();

            // Get items to rollback inventory
            $items = $retailWarehouseFood->items;
            
            // Rollback inventory for each item
            foreach ($items as $item) {
                $this->rollbackInventory($item, $retailWarehouseFood->warehouse_id);
            }

            // Delete retail warehouse food items
            $retailWarehouseFood->items()->delete();
            
            // Delete retail warehouse food
            $retailWarehouseFood->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi retail warehouse food berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    private function rollbackInventory($item, $warehouseId)
    {
        try {
            // Find item master
            $itemMaster = DB::table('items')->where('name', $item->item_name)->first();
            if (!$itemMaster) {
                return;
            }

            // Find inventory item
            $inventoryItem = DB::table('food_inventory_items')->where('item_id', $itemMaster->id)->first();
            if (!$inventoryItem) {
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

            // Find warehouse inventory stock
            $stock = DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if ($stock) {
                // Rollback stock (subtract the quantity)
                DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $warehouseId)
                    ->update([
                        'qty_small' => max(0, $stock->qty_small - $qty_small),
                        'updated_at' => now()
                    ]);
            }

        } catch (\Exception $e) {
            // Rollback failed, but don't stop the process
            \Log::error('Rollback inventory error', ['error' => $e->getMessage()]);
        }
    }

    public function getItemUnits(Request $request, $itemId)
    {
        $item = DB::table('items')->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['units' => []]);
        }

        $paymentMethod = $request->get('payment_method', 'cash');

        // Get unit names
        $unitSmall = DB::table('units')->where('id', $item->small_unit_id)->value('name');
        $unitMedium = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
        $unitLarge = DB::table('units')->where('id', $item->large_unit_id)->value('name');

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
            // Set default unit to medium if available
            if ($item->medium_unit_id) {
                $defaultUnit = [
                    'id' => $item->medium_unit_id,
                    'name' => $unitMedium
                ];
            } else {
                // Fallback to small unit if medium not available
                $defaultUnit = [
                    'id' => $item->small_unit_id,
                    'name' => $unitSmall
                ];
            }

            // Get price from item_prices (priority: all)
            $price = DB::table('item_prices')
                ->where('item_id', $itemId)
                ->where('availability_price_type', 'all')
                ->orderByDesc('id')
                ->first();

            if ($price) {
                $finalPrice = $price->price;
                // Round up to nearest 100
                $defaultPrice = ceil($finalPrice / 100) * 100;
            }
        }

        return response()->json([
            'units' => $units,
            'default_unit' => $defaultUnit,
            'default_price' => $defaultPrice,
            'payment_method' => $paymentMethod
        ]);
    }

    public function dailyTotal(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'transaction_date' => 'required|date',
            'payment_method' => 'nullable|in:cash,contra_bon',
        ]);
        
        $query = RetailWarehouseFood::where('warehouse_id', $request->warehouse_id)
            ->whereDate('transaction_date', $request->transaction_date);
            
        // Jika payment_method diisi, filter berdasarkan metode pembayaran
        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }
        
        $total = $query->sum('total_amount');
        return response()->json(['total' => $total]);
    }
}

