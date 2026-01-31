<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Item;
use App\Models\Customer;
use App\Models\RetailWarehouseSale;
use Illuminate\Support\Facades\Log;

class RetailWarehouseSaleController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('retail_warehouse_sales as rws')
            ->leftJoin('customers as c', 'rws.customer_id', '=', 'c.id')
            ->leftJoin('warehouses as w', 'rws.warehouse_id', '=', 'w.id')
            ->leftJoin('warehouse_division as wd', 'rws.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('users as u', 'rws.created_by', '=', 'u.id')
            ->select(
                'rws.*',
                'c.name as customer_name',
                'c.code as customer_code',
                'w.name as warehouse_name',
                'wd.name as division_name',
                'u.nama_lengkap as created_by_name'
            );

        // Filter search
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('rws.number', 'like', $search)
                  ->orWhere('c.name', 'like', $search)
                  ->orWhere('c.code', 'like', $search)
                  ->orWhere('w.name', 'like', $search)
                  ->orWhere('wd.name', 'like', $search);
            });
        }

        // Filter tanggal dari
        if ($request->filled('from')) {
            $query->whereDate('rws.created_at', '>=', $request->from);
        }

        // Filter tanggal sampai
        if ($request->filled('to')) {
            $query->whereDate('rws.created_at', '<=', $request->to);
        }

        $sales = $query->orderByDesc('rws.created_at')->paginate($request->get('per_page', 15));

        // Check if user can delete
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        return Inertia::render('RetailWarehouseSale/Index', [
            'sales' => $sales,
            'filters' => $request->only(['search', 'from', 'to', 'per_page']),
            'canDelete' => $canDelete
        ]);
    }

    public function create(Request $request)
    {
        // Ambil daftar warehouse dan division
        $warehouses = DB::table('warehouses')->where('status', 'active')->get();
        $warehouseDivisions = DB::table('warehouse_division')->get();
        $customers = DB::table('customers')->where('status', 'active')->get();

        return Inertia::render('RetailWarehouseSale/Form', [
            'warehouses' => $warehouses,
            'warehouseDivisions' => $warehouseDivisions,
            'customers' => $customers
        ]);
    }

    public function store(Request $request)
    {
        
        // Validate request
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'warehouse_division_id' => 'nullable|exists:warehouse_division,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);
        
        DB::beginTransaction();
        try {
            // Generate number
            $number = $this->generateSaleNumber();
            
            // Insert retail warehouse sale
            $saleId = DB::table('retail_warehouse_sales')->insertGetId([
                'number' => $number,
                'customer_id' => $request->customer_id,
                'sale_date' => $request->sale_date ?? now()->toDateString(),
                'warehouse_id' => $request->warehouse_id,
                'warehouse_division_id' => $request->warehouse_division_id,
                'total_amount' => $request->total_amount,
                'notes' => $request->notes,
                'status' => 'completed',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert items
            foreach ($request->items as $item) {
                DB::table('retail_warehouse_sale_items')->insert([
                    'retail_warehouse_sale_id' => $saleId,
                    'item_id' => $item['item_id'],
                    'barcode' => $item['barcode'] ?? null,
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update inventory stock
                $this->updateInventoryStock($item, $request->warehouse_id, $saleId);
            }

            // Log activity
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'module' => 'retail_warehouse_sale',
                'description' => 'Membuat retail warehouse sale #' . $number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Retail Warehouse Sale berhasil disimpan!',
                'sale_id' => $saleId,
                'number' => $number
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal simpan Retail Warehouse Sale: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan Retail Warehouse Sale: ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $sale = DB::table('retail_warehouse_sales as rws')
            ->leftJoin('customers as c', 'rws.customer_id', '=', 'c.id')
            ->leftJoin('warehouses as w', 'rws.warehouse_id', '=', 'w.id')
            ->leftJoin('warehouse_division as wd', 'rws.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('users as u', 'rws.created_by', '=', 'u.id')
            ->select(
                'rws.*',
                'c.name as customer_name',
                'c.code as customer_code',
                'c.phone as customer_phone',
                'c.address as customer_address',
                'w.name as warehouse_name',
                'wd.name as division_name',
                'u.nama_lengkap as created_by_name'
            )
            ->where('rws.id', $id)
            ->first();

        $items = DB::table('retail_warehouse_sale_items as rwsi')
            ->leftJoin('items as i', 'rwsi.item_id', '=', 'i.id')
            ->select(
                'rwsi.id',
                'i.name as item_name',
                'rwsi.barcode',
                'rwsi.qty',
                'rwsi.unit',
                'rwsi.price',
                'rwsi.subtotal'
            )
            ->where('rwsi.retail_warehouse_sale_id', $id)
            ->get();

        return Inertia::render('RetailWarehouseSale/Show', [
            'sale' => $sale,
            'items' => $items
        ]);
    }

    public function destroy($id)
    {
        // Check authorization
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        
        if (!$canDelete) {
            return redirect()->route('retail-warehouse-sale.index')->with('error', 'Anda tidak memiliki akses untuk menghapus data ini');
        }

        DB::beginTransaction();
        try {
            $sale = DB::table('retail_warehouse_sales')->where('id', $id)->first();
            if (!$sale) {
                return redirect()->route('retail-warehouse-sale.index')->with('error', 'Retail Warehouse Sale tidak ditemukan');
            }

            $items = DB::table('retail_warehouse_sale_items')->where('retail_warehouse_sale_id', $id)->get();

            // Rollback inventory
            foreach ($items as $item) {
                $this->rollbackInventoryStock($item, $sale->warehouse_id);
            }

            // Delete items
            DB::table('retail_warehouse_sale_items')->where('retail_warehouse_sale_id', $id)->delete();
            
            // Delete sale
            DB::table('retail_warehouse_sales')->where('id', $id)->delete();

            // Log activity
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'retail_warehouse_sale',
                'description' => 'Menghapus retail warehouse sale #' . $sale->number,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($sale),
                'new_data' => null,
                'created_at' => now(),
            ]);

            DB::commit();
            return redirect()->route('retail-warehouse-sale.index')->with('success', 'Retail Warehouse Sale berhasil dihapus');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function generateSaleNumber()
    {
        $prefix = 'RWS';
        $date = now()->format('ymd');
        
        $lastSale = DB::table('retail_warehouse_sales')
            ->where('number', 'like', $prefix . $date . '%')
            ->orderBy('number', 'desc')
            ->first();
        
        if ($lastSale) {
            $lastNumber = (int) substr($lastSale->number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    private function updateInventoryStock($item, $warehouseId, $saleId)
    {
        // Pastikan item_id valid
        $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
        if (!$itemMaster) throw new \Exception('Item master tidak ditemukan di tabel items untuk item_id: ' . $item['item_id']);
        
        // Cari inventory_item_id, insert jika belum ada
        $inventoryItem = DB::table('food_inventory_items')->where('item_id', $item['item_id'])->first();
        if (!$inventoryItem) {
            $inventoryItemId = DB::table('food_inventory_items')->insertGetId([
                'item_id' => $item['item_id'],
                'small_unit_id' => $itemMaster->small_unit_id,
                'medium_unit_id' => $itemMaster->medium_unit_id,
                'large_unit_id' => $itemMaster->large_unit_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inventoryItem = DB::table('food_inventory_items')->where('id', $inventoryItemId)->first();
            if (!$inventoryItem) throw new \Exception('Gagal insert food_inventory_items untuk item_id: ' . $item['item_id']);
        }
        $inventory_item_id = $inventoryItem->id;
        
        // Ambil data konversi dari tabel items
        $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
        if (!$itemMaster) throw new \Exception('Item master not found for item_id: ' . $item['item_id']);
        $unit = $item['unit'];
        $qty_input = $item['qty'];
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
        
        // Tambahkan log sebelum cek stok tersedia
        
        $stock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventory_item_id)
            ->where('warehouse_id', $warehouseId)
            ->first();
        if (!$stock) {
            Log::error('Stok tidak ditemukan di gudang', [
                'inventory_item_id' => $inventory_item_id,
                'warehouse_id' => $warehouseId,
                'item_id' => $item['item_id']
            ]);
            throw new \Exception('Stok tidak ditemukan di gudang');
        }
        
        // Tambahkan log sebelum validasi qty
        
        if ($qty_small > $stock->qty_small) {
            Log::error('Qty melebihi stok yang tersedia', [
                'qty_small' => $qty_small,
                'stok_tersedia' => $stock->qty_small,
                'unit' => $unitSmall
            ]);
            throw new \Exception("Qty melebihi stok yang tersedia. Stok tersedia: {$stock->qty_small} {$unitSmall}");
        }
        
        // Update stok di warehouse (kurangi)
        DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventory_item_id)
            ->where('warehouse_id', $warehouseId)
            ->update([
                'qty_small' => $stock->qty_small - $qty_small,
                'qty_medium' => $stock->qty_medium - $qty_medium,
                'qty_large' => $stock->qty_large - $qty_large,
                'updated_at' => now(),
            ]);
        
        // Insert kartu stok OUT
        DB::table('food_inventory_cards')->insert([
            'inventory_item_id' => $inventory_item_id,
            'warehouse_id' => $warehouseId,
            'date' => now()->toDateString(),
            'reference_type' => 'retail_warehouse_sale',
            'reference_id' => $saleId,
            'out_qty_small' => $qty_small,
            'out_qty_medium' => $qty_medium,
            'out_qty_large' => $qty_large,
            'cost_per_small' => $stock->last_cost_small,
            'cost_per_medium' => $stock->last_cost_medium,
            'cost_per_large' => $stock->last_cost_large,
            'value_out' => $qty_small * $stock->last_cost_small,
            'saldo_qty_small' => $stock->qty_small - $qty_small,
            'saldo_qty_medium' => $stock->qty_medium - $qty_medium,
            'saldo_qty_large' => $stock->qty_large - $qty_large,
            'saldo_value' => ($stock->qty_small - $qty_small) * $stock->last_cost_small,
            'description' => 'Stock Out - Retail Warehouse Sale',
            'created_at' => now(),
        ]);
    }

    private function rollbackInventoryStock($item, $warehouseId)
    {
        $realItemId = $item->item_id;
        $itemMaster = DB::table('items')->where('id', $realItemId)->first();
        if (!$itemMaster) return;
        
        $inventoryItem = DB::table('food_inventory_items')->where('item_id', $realItemId)->first();
        if (!$inventoryItem) return;
        
        $inventory_item_id = $inventoryItem->id;
        $unit = $item->unit ?? null;
        $qty_input = $item->qty;
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
        
        $stock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventory_item_id)
            ->where('warehouse_id', $warehouseId)
            ->first();
            
        if ($stock) {
            DB::table('food_inventory_stocks')
                ->where('id', $stock->id)
                ->update([
                    'qty_small' => $stock->qty_small + $qty_small,
                    'qty_medium' => $stock->qty_medium + $qty_medium,
                    'qty_large' => $stock->qty_large + $qty_large,
                    'updated_at' => now(),
                ]);
        } else {
        }
        
        // Hapus kartu stok OUT
        DB::table('food_inventory_cards')
            ->where('inventory_item_id', $inventory_item_id)
            ->where('warehouse_id', $warehouseId)
            ->where('reference_type', 'retail_warehouse_sale')
            ->where('reference_id', $item->retail_warehouse_sale_id)
            ->delete();
    }

    public function searchItems(Request $request)
    {
        $barcode = $request->barcode;
        $warehouseId = $request->warehouse_id;

        $item = DB::table('items as i')
            ->leftJoin('item_barcodes as ib', 'i.id', '=', 'ib.item_id')
            ->leftJoin('food_inventory_items as fii', 'i.id', '=', 'fii.item_id')
            ->leftJoin('food_inventory_stocks as fis', function($join) use ($warehouseId) {
                $join->on('fii.id', '=', 'fis.inventory_item_id')
                     ->where('fis.warehouse_id', $warehouseId);
            })
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'fis.qty_small',
                'fis.qty_medium',
                'fis.qty_large'
            )
            ->where('ib.barcode', $barcode)
            ->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item tidak ditemukan']);
        }

        // Get unit names
        $unitSmall = DB::table('units')->where('id', $item->small_unit_id)->value('name');
        $unitMedium = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
        $unitLarge = DB::table('units')->where('id', $item->large_unit_id)->value('name');

        $item->unit_small = $unitSmall;
        $item->unit_medium = $unitMedium;
        $item->unit_large = $unitLarge;

        // Get price from item_prices with priority for region_id=1 or availability_price_type='all'
        $price = DB::table('item_prices')
            ->where('item_id', $item->item_id)
            ->where(function($q) {
                $q->where('region_id', 1)
                  ->orWhere('availability_price_type', 'all');
            })
            ->orderByRaw("CASE WHEN region_id = 1 THEN 0 WHEN availability_price_type = 'all' THEN 1 ELSE 2 END")
            ->value('price');

        $item->price = $price ? (float)$price : 0;

        return response()->json(['success' => true, 'item' => $item]);
    }

    public function searchItemsByName(Request $request)
    {
        $search = $request->search;
        $warehouseId = $request->warehouse_id;

        if (empty($search) || strlen($search) < 2) {
            return response()->json(['success' => false, 'message' => 'Minimal 2 karakter untuk pencarian']);
        }

        $items = DB::table('items as i')
            ->leftJoin('food_inventory_items as fii', 'i.id', '=', 'fii.item_id')
            ->leftJoin('food_inventory_stocks as fis', function($join) use ($warehouseId) {
                $join->on('fii.id', '=', 'fis.inventory_item_id')
                     ->where('fis.warehouse_id', $warehouseId);
            })
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'fis.qty_small',
                'fis.qty_medium',
                'fis.qty_large'
            )
            ->where('i.name', 'like', '%' . $search . '%')
            ->whereNotNull('fis.id') // Hanya item yang ada stoknya di warehouse
            ->limit(10)
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Item tidak ditemukan']);
        }

        // Get unit names for each item
        $items->transform(function($item) {
            $unitSmall = DB::table('units')->where('id', $item->small_unit_id)->value('name');
            $unitMedium = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
            $unitLarge = DB::table('units')->where('id', $item->large_unit_id)->value('name');

            $item->unit_small = $unitSmall;
            $item->unit_medium = $unitMedium;
            $item->unit_large = $unitLarge;

            // Get price from item_prices with priority for region_id=1 or availability_price_type='all'
            $price = DB::table('item_prices')
                ->where('item_id', $item->item_id)
                ->where(function($q) {
                    $q->where('region_id', 1)
                      ->orWhere('availability_price_type', 'all');
                })
                ->orderByRaw("CASE WHEN region_id = 1 THEN 0 WHEN availability_price_type = 'all' THEN 1 ELSE 2 END")
                ->value('price');

            $item->price = $price ? (float)$price : 0;

            return $item;
        });

        return response()->json(['success' => true, 'items' => $items]);
    }

    public function searchCustomers(Request $request)
    {
        $search = $request->search;
        
        $customers = DB::table('customers')
            ->where('status', 'active')
            ->where(function($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                      ->orWhere('code', 'like', "%$search%")
                      ->orWhere('phone', 'like', "%$search%");
            })
            ->limit(10)
            ->get();

        return response()->json(['customers' => $customers]);
    }

    public function storeCustomer(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:customers,code',
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'type' => 'required|in:branch,customer',
            'region' => 'nullable|string|max:20',
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'address' => $validated['address'],
            'type' => $validated['type'],
            'region' => $validated['region'],
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer = DB::table('customers')->where('id', $customerId)->first();

        return response()->json(['success' => true, 'customer' => $customer]);
    }

    public function getItemPrice(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
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

    public function print($id)
    {
        $sale = DB::table('retail_warehouse_sales as rws')
            ->leftJoin('customers as c', 'rws.customer_id', '=', 'c.id')
            ->leftJoin('warehouses as w', 'rws.warehouse_id', '=', 'w.id')
            ->leftJoin('warehouse_division as wd', 'rws.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('users as u', 'rws.created_by', '=', 'u.id')
            ->select(
                'rws.*',
                'c.name as customer_name',
                'c.code as customer_code',
                'c.phone as customer_phone',
                'c.address as customer_address',
                'w.name as warehouse_name',
                'wd.name as division_name',
                'u.nama_lengkap as created_by_name'
            )
            ->where('rws.id', $id)
            ->first();

        if (!$sale) {
            return redirect()->route('retail-warehouse-sale.index')->with('error', 'Retail Warehouse Sale tidak ditemukan');
        }

        $items = DB::table('retail_warehouse_sale_items as rwsi')
            ->leftJoin('items as i', 'rwsi.item_id', '=', 'i.id')
            ->select(
                'rwsi.id',
                'i.name as item_name',
                'rwsi.barcode',
                'rwsi.qty',
                'rwsi.unit',
                'rwsi.price',
                'rwsi.subtotal'
            )
            ->where('rwsi.retail_warehouse_sale_id', $id)
            ->get();

        return Inertia::render('RetailWarehouseSale/PrintStruk', [
            'sale' => $sale,
            'items' => $items,
            'customer' => [
                'name' => $sale->customer_name,
                'code' => $sale->customer_code,
                'phone' => $sale->customer_phone,
                'address' => $sale->customer_address
            ],
            'warehouse' => [
                'name' => $sale->warehouse_name
            ],
            'division' => [
                'name' => $sale->division_name
            ]
        ]);
    }
} 