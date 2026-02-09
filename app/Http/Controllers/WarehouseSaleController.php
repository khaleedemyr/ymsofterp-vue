<?php

namespace App\Http\Controllers;

use App\Models\WarehouseSale;
use App\Models\WarehouseSaleItem;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\StockCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class WarehouseSaleController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $sales = \App\Models\WarehouseSale::with(['sourceWarehouse', 'targetWarehouse', 'creator'])
            ->orderByDesc('date')
            ->paginate(10);

        // Hitung total_items untuk setiap sale
        $sales->getCollection()->transform(function($sale) {
            $sale->total_items = $sale->items()->count();
            return $sale;
        });

        // Check if user can delete
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        return Inertia::render('Inventory/WarehouseSales/Index', [
            'sales' => $sales,
            'canDelete' => $canDelete
        ]);
    }

    public function store(Request $request)
    {
        Log::info('WarehouseSaleController@store START', [
            'payload' => $request->all()
        ]);
        $validated = $request->validate([
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'target_warehouse_id' => 'required|exists:warehouses,id|different:source_warehouse_id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.selected_unit' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'note' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Generate unique number (consider soft-deleted too)
            $prefix = 'WHS';
            $date = date('ym', strtotime($validated['date']));
            $lastNumber = WarehouseSale::withTrashed()
                ->where('number', 'like', $prefix . $date . '%')
                ->orderBy('number', 'desc')
                ->value('number');
            $sequence = $lastNumber ? (int)substr($lastNumber, -4) + 1 : 1;
            $number = $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            // Ensure uniqueness in case of race conditions
            while (WarehouseSale::withTrashed()->where('number', $number)->exists()) {
                $sequence++;
                $number = $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            }

            // Simpan header
            $sale = WarehouseSale::create([
                'number' => $number,
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'target_warehouse_id' => $validated['target_warehouse_id'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
                'status' => 'confirmed',
                'created_by' => auth()->id()
            ]);

            $warehouseFromName = Warehouse::find($validated['source_warehouse_id'])->name;
            $warehouseToName = Warehouse::find($validated['target_warehouse_id'])->name;

            foreach ($validated['items'] as $item) {
                $itemMaster = Item::find($item['item_id']);
                $unit = $item['selected_unit'];
                $qty_input = $item['qty'];
                $qty_small = 0;
                $qty_medium = 0;
                $qty_large = 0;
                $cost_small = 0;
                $cost_medium = 0;
                $cost_large = 0;
                $unitSmall = optional($itemMaster->smallUnit)->name;
                $unitMedium = optional($itemMaster->mediumUnit)->name;
                $unitLarge = optional($itemMaster->largeUnit)->name;
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                $price = $item['price'];

                // Konversi qty dan cost berdasarkan unit yang dipilih
                if ($unit === $unitSmall) {
                    $qty_small = $qty_input;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    
                    $cost_small = $price;
                    $cost_medium = $price * $smallConv;
                    $cost_large = $price * $smallConv * $mediumConv;
                } elseif ($unit === $unitMedium) {
                    $qty_medium = $qty_input;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    
                    $cost_medium = $price;
                    $cost_small = $price / $smallConv;
                    $cost_large = $price * $mediumConv;
                } elseif ($unit === $unitLarge) {
                    $qty_large = $qty_input;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                    
                    $cost_large = $price;
                    $cost_medium = $price / $mediumConv;
                    $cost_small = $price / ($mediumConv * $smallConv);
                } else {
                    $qty_small = $qty_input;
                    $cost_small = $price;
                }

                // Simpan detail
                WarehouseSaleItem::create([
                    'warehouse_sale_id' => $sale->id,
                    'item_id' => $item['item_id'],
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'price' => $price,
                    'total' => $qty_input * $price,
                    'note' => $item['note'] ?? null,
                ]);

                // Update stok di warehouse asal (kurangi)
                $inventoryItem = \App\Models\FoodInventoryItem::where('item_id', $item['item_id'])->first();
                if (!$inventoryItem) throw new \Exception('Inventory item not found for item_id: ' . $item['item_id']);
                $inventory_item_id = $inventoryItem->id;
                $stockFrom = \App\Models\FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $validated['source_warehouse_id'])->first();
                if (!$stockFrom) throw new \Exception('Stok tidak ditemukan di gudang asal');

                // Hitung value out menggunakan MAC dari gudang asal
                $value_out = 0;
                if ($unit === $unitSmall) {
                    $value_out = $qty_small * $stockFrom->last_cost_small;
                } elseif ($unit === $unitMedium) {
                    $value_out = $qty_medium * $stockFrom->last_cost_medium;
                } elseif ($unit === $unitLarge) {
                    $value_out = $qty_large * $stockFrom->last_cost_large;
                }

                $stockFrom->qty_small -= $qty_small;
                $stockFrom->qty_medium -= $qty_medium;
                $stockFrom->qty_large -= $qty_large;
                $stockFrom->value -= $value_out;
                $stockFrom->save();

                // Update stok di warehouse tujuan (tambah)
                $stockTo = \App\Models\FoodInventoryStock::firstOrCreate(
                    [
                        'inventory_item_id' => $inventory_item_id,
                        'warehouse_id' => $validated['target_warehouse_id']
                    ],
                    [
                        'qty_small' => 0,
                        'qty_medium' => 0,
                        'qty_large' => 0,
                        'value' => 0,
                        'last_cost_small' => 0,
                        'last_cost_medium' => 0,
                        'last_cost_large' => 0,
                    ]
                );

                // Hitung value in menggunakan price transaksi
                $value_in = $qty_input * $price;

                $stockTo->qty_small += $qty_small;
                $stockTo->qty_medium += $qty_medium;
                $stockTo->qty_large += $qty_large;
                $stockTo->value += $value_in;
                $stockTo->last_cost_small = $cost_small;
                $stockTo->last_cost_medium = $cost_medium;
                $stockTo->last_cost_large = $cost_large;
                $stockTo->save();

                // Insert kartu stok OUT (gudang asal)
                \App\Models\FoodInventoryCard::create([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['source_warehouse_id'],
                    'date' => $validated['date'],
                    'reference_type' => 'warehouse_sale',
                    'reference_id' => $sale->id,
                    'out_qty_small' => $qty_small,
                    'out_qty_medium' => $qty_medium,
                    'out_qty_large' => $qty_large,
                    'cost_per_small' => $stockFrom->last_cost_small,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_out' => $value_out,
                    'saldo_qty_small' => $stockFrom->qty_small,
                    'saldo_qty_medium' => $stockFrom->qty_medium,
                    'saldo_qty_large' => $stockFrom->qty_large,
                    'saldo_value' => $stockFrom->value,
                    'description' => 'Penjualan ke Gudang ' . $warehouseToName,
                ]);

                // Insert kartu stok IN (gudang tujuan)
                \App\Models\FoodInventoryCard::create([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['target_warehouse_id'],
                    'date' => $validated['date'],
                    'reference_type' => 'warehouse_sale',
                    'reference_id' => $sale->id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'cost_per_small' => $cost_small,
                    'cost_per_medium' => $cost_medium,
                    'cost_per_large' => $cost_large,
                    'value_in' => $value_in,
                    'saldo_qty_small' => $stockTo->qty_small,
                    'saldo_qty_medium' => $stockTo->qty_medium,
                    'saldo_qty_large' => $stockTo->qty_large,
                    'saldo_value' => $stockTo->value,
                    'description' => 'Penerimaan dari Gudang ' . $warehouseFromName,
                ]);
            }
            DB::commit();
            Log::info('WarehouseSaleController@store SUCCESS', [
                'sale_id' => $sale->id,
                'number' => $sale->number,
            ]);
            return redirect()->route('warehouse-sales.index')->with('success', 'Penjualan antar gudang berhasil disimpan');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WarehouseSaleController@store ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function getItemPrice(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            // 'unit_type' => 'required' // not used for price lookup here
        ]);

        // Ambil harga dari item_prices dengan prioritas region_id=1, lalu availability_price_type='all'
        $price = DB::table('item_prices')
            ->where('item_id', $request->item_id)
            ->where(function($q) {
                $q->where('region_id', 1)
                  ->orWhere('availability_price_type', 'all');
            })
            ->orderByRaw("CASE WHEN region_id = 1 THEN 0 WHEN availability_price_type = 'all' THEN 1 ELSE 2 END")
            ->value('price');

        return response()->json([
            'price' => $price ? (float)$price : 0
        ]);
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::all();
        return Inertia::render('Inventory/WarehouseSales/Create', [
            'warehouses' => $warehouses,
            'items' => $items
        ]);
    }

    public function show(WarehouseSale $warehouseSale)
    {
        $warehouseSale->load(['sourceWarehouse', 'targetWarehouse', 'creator', 'items.item']);
        
        return Inertia::render('Inventory/WarehouseSales/Show', [
            'sale' => $warehouseSale
        ]);
    }

    public function destroy(WarehouseSale $warehouseSale)
    {
        // Check authorization
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        
        if (!$canDelete) {
            return redirect()->route('warehouse-sales.index')->with('error', 'Anda tidak memiliki akses untuk menghapus data ini');
        }

        DB::beginTransaction();
        try {
            $this->rollbackWarehouseSale($warehouseSale);
            DB::commit();
            return redirect()->route('warehouse-sales.index')->with('success', 'Penjualan antar gudang berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    // ---------- API for mobile app ----------

    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        $query = WarehouseSale::with(['sourceWarehouse', 'targetWarehouse', 'creator'])
            ->orderByDesc('date');

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }
        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where(function ($qry) use ($q) {
                $qry->where('number', 'like', $q)
                    ->orWhereHas('sourceWarehouse', fn($w) => $w->where('name', 'like', $q))
                    ->orWhereHas('targetWarehouse', fn($w) => $w->where('name', 'like', $q));
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $sales = $query->paginate($perPage);

        $sales->getCollection()->transform(function ($sale) {
            $sale->total_items = $sale->items()->count();
            return $sale;
        });

        return response()->json([
            'data' => $sales->items(),
            'current_page' => $sales->currentPage(),
            'last_page' => $sales->lastPage(),
            'per_page' => $sales->perPage(),
            'total' => $sales->total(),
            'can_delete' => $canDelete,
        ]);
    }

    public function apiCreate()
    {
        $warehouses = Warehouse::orderBy('name')->get(['id', 'code', 'name', 'location', 'status']);
        return response()->json([
            'warehouses' => $warehouses,
        ]);
    }

    public function apiSearchItems(Request $request)
    {
        $request->validate(['q' => 'required|string|min:1']);
        $q = '%' . $request->q . '%';
        $items = Item::with(['smallUnit', 'mediumUnit', 'largeUnit'])
            ->where('status', 'active')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', $q)
                    ->orWhere('sku', 'like', $q);
            })
            ->limit(50)
            ->get();

        $list = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'unit_small' => optional($item->smallUnit)->name,
                'unit_medium' => optional($item->mediumUnit)->name,
                'unit_large' => optional($item->largeUnit)->name,
                'small_conversion_qty' => $item->small_conversion_qty,
                'medium_conversion_qty' => $item->medium_conversion_qty,
            ];
        });

        return response()->json(['items' => $list]);
    }

    public function apiShow($id)
    {
        $warehouseSale = WarehouseSale::with(['sourceWarehouse', 'targetWarehouse', 'creator', 'items.item.smallUnit', 'items.item.mediumUnit', 'items.item.largeUnit'])->findOrFail($id);
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        return response()->json([
            'sale' => $warehouseSale,
            'can_delete' => $canDelete,
        ]);
    }

    public function apiStore(Request $request)
    {
        Log::info('WarehouseSaleController@apiStore START', ['payload' => $request->all()]);
        $validated = $request->validate([
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'target_warehouse_id' => 'required|exists:warehouses,id|different:source_warehouse_id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.selected_unit' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'note' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $prefix = 'WHS';
            $date = date('ym', strtotime($validated['date']));
            $lastNumber = WarehouseSale::withTrashed()
                ->where('number', 'like', $prefix . $date . '%')
                ->orderBy('number', 'desc')
                ->value('number');
            $sequence = $lastNumber ? (int) substr($lastNumber, -4) + 1 : 1;
            $number = $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            while (WarehouseSale::withTrashed()->where('number', $number)->exists()) {
                $sequence++;
                $number = $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            }

            $sale = WarehouseSale::create([
                'number' => $number,
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'target_warehouse_id' => $validated['target_warehouse_id'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
                'status' => 'confirmed',
                'created_by' => auth()->id()
            ]);

            $warehouseFromName = Warehouse::find($validated['source_warehouse_id'])->name;
            $warehouseToName = Warehouse::find($validated['target_warehouse_id'])->name;

            foreach ($validated['items'] as $item) {
                $itemMaster = Item::find($item['item_id']);
                $unit = $item['selected_unit'];
                $qty_input = $item['qty'];
                $qty_small = 0;
                $qty_medium = 0;
                $qty_large = 0;
                $cost_small = 0;
                $cost_medium = 0;
                $cost_large = 0;
                $unitSmall = optional($itemMaster->smallUnit)->name;
                $unitMedium = optional($itemMaster->mediumUnit)->name;
                $unitLarge = optional($itemMaster->largeUnit)->name;
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                $price = $item['price'];

                if ($unit === $unitSmall) {
                    $qty_small = $qty_input;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    $cost_small = $price;
                    $cost_medium = $price * $smallConv;
                    $cost_large = $price * $smallConv * $mediumConv;
                } elseif ($unit === $unitMedium) {
                    $qty_medium = $qty_input;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    $cost_medium = $price;
                    $cost_small = $price / $smallConv;
                    $cost_large = $price * $mediumConv;
                } elseif ($unit === $unitLarge) {
                    $qty_large = $qty_input;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                    $cost_large = $price;
                    $cost_medium = $price / $mediumConv;
                    $cost_small = $price / ($mediumConv * $smallConv);
                } else {
                    $qty_small = $qty_input;
                    $cost_small = $price;
                }

                WarehouseSaleItem::create([
                    'warehouse_sale_id' => $sale->id,
                    'item_id' => $item['item_id'],
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'price' => $price,
                    'total' => $qty_input * $price,
                    'note' => $item['note'] ?? null,
                ]);

                $inventoryItem = \App\Models\FoodInventoryItem::where('item_id', $item['item_id'])->first();
                if (!$inventoryItem) throw new \Exception('Inventory item not found for item_id: ' . $item['item_id']);
                $inventory_item_id = $inventoryItem->id;
                $stockFrom = \App\Models\FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $validated['source_warehouse_id'])->first();
                if (!$stockFrom) throw new \Exception('Stok tidak ditemukan di gudang asal');

                $value_out = 0;
                if ($unit === $unitSmall) {
                    $value_out = $qty_small * $stockFrom->last_cost_small;
                } elseif ($unit === $unitMedium) {
                    $value_out = $qty_medium * $stockFrom->last_cost_medium;
                } elseif ($unit === $unitLarge) {
                    $value_out = $qty_large * $stockFrom->last_cost_large;
                }

                $stockFrom->qty_small -= $qty_small;
                $stockFrom->qty_medium -= $qty_medium;
                $stockFrom->qty_large -= $qty_large;
                $stockFrom->value -= $value_out;
                $stockFrom->save();

                $stockTo = \App\Models\FoodInventoryStock::firstOrCreate(
                    ['inventory_item_id' => $inventory_item_id, 'warehouse_id' => $validated['target_warehouse_id']],
                    ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0, 'value' => 0, 'last_cost_small' => 0, 'last_cost_medium' => 0, 'last_cost_large' => 0]
                );
                $value_in = $qty_input * $price;
                $stockTo->qty_small += $qty_small;
                $stockTo->qty_medium += $qty_medium;
                $stockTo->qty_large += $qty_large;
                $stockTo->value += $value_in;
                $stockTo->last_cost_small = $cost_small;
                $stockTo->last_cost_medium = $cost_medium;
                $stockTo->last_cost_large = $cost_large;
                $stockTo->save();

                \App\Models\FoodInventoryCard::create([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['source_warehouse_id'],
                    'date' => $validated['date'],
                    'reference_type' => 'warehouse_sale',
                    'reference_id' => $sale->id,
                    'out_qty_small' => $qty_small, 'out_qty_medium' => $qty_medium, 'out_qty_large' => $qty_large,
                    'cost_per_small' => $stockFrom->last_cost_small, 'cost_per_medium' => $stockFrom->last_cost_medium, 'cost_per_large' => $stockFrom->last_cost_large,
                    'value_out' => $value_out,
                    'saldo_qty_small' => $stockFrom->qty_small, 'saldo_qty_medium' => $stockFrom->qty_medium, 'saldo_qty_large' => $stockFrom->qty_large,
                    'saldo_value' => $stockFrom->value,
                    'description' => 'Penjualan ke Gudang ' . $warehouseToName,
                ]);
                \App\Models\FoodInventoryCard::create([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['target_warehouse_id'],
                    'date' => $validated['date'],
                    'reference_type' => 'warehouse_sale',
                    'reference_id' => $sale->id,
                    'in_qty_small' => $qty_small, 'in_qty_medium' => $qty_medium, 'in_qty_large' => $qty_large,
                    'cost_per_small' => $cost_small, 'cost_per_medium' => $cost_medium, 'cost_per_large' => $cost_large,
                    'value_in' => $value_in,
                    'saldo_qty_small' => $stockTo->qty_small, 'saldo_qty_medium' => $stockTo->qty_medium, 'saldo_qty_large' => $stockTo->qty_large,
                    'saldo_value' => $stockTo->value,
                    'description' => 'Penerimaan dari Gudang ' . $warehouseFromName,
                ]);
            }

            DB::commit();
            Log::info('WarehouseSaleController@apiStore SUCCESS', ['sale_id' => $sale->id, 'number' => $sale->number]);
            return response()->json([
                'success' => true,
                'message' => 'Penjualan antar gudang berhasil disimpan',
                'sale' => $sale->load(['sourceWarehouse', 'targetWarehouse', 'items.item']),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WarehouseSaleController@apiStore ERROR', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function apiDestroy(WarehouseSale $warehouseSale)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        if (!$canDelete) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menghapus data ini'], 403);
        }
        DB::beginTransaction();
        try {
            $this->rollbackWarehouseSale($warehouseSale);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Penjualan antar gudang berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    private function rollbackWarehouseSale(WarehouseSale $warehouseSale): void
    {
        $warehouseSale->load(['items.item']);
        foreach ($warehouseSale->items as $item) {
            $inventoryItem = \App\Models\FoodInventoryItem::where('item_id', $item->item_id)->first();
            if (!$inventoryItem) throw new \Exception('Inventory item not found for item_id: ' . $item->item_id);
            $stockFrom = \App\Models\FoodInventoryStock::where('inventory_item_id', $inventoryItem->id)
                ->where('warehouse_id', $warehouseSale->source_warehouse_id)->first();
            if ($stockFrom) {
                $stockFrom->qty_small += $item->qty_small;
                $stockFrom->qty_medium += $item->qty_medium;
                $stockFrom->qty_large += $item->qty_large;
                $stockFrom->value += $item->total;
                $stockFrom->save();
            }
            $stockTo = \App\Models\FoodInventoryStock::where('inventory_item_id', $inventoryItem->id)
                ->where('warehouse_id', $warehouseSale->target_warehouse_id)->first();
            if ($stockTo) {
                $stockTo->qty_small -= $item->qty_small;
                $stockTo->qty_medium -= $item->qty_medium;
                $stockTo->qty_large -= $item->qty_large;
                $stockTo->value -= $item->total;
                $stockTo->save();
            }
            \App\Models\FoodInventoryCard::where('reference_type', 'warehouse_sale')->where('reference_id', $warehouseSale->id)->delete();
        }
        $warehouseSale->items()->delete();
        $warehouseSale->delete();
    }
} 