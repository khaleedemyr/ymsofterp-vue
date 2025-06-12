<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Item;
use Illuminate\Support\Facades\Log;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        // List Delivery Order dengan join ke food_packing_lists, food_floor_orders, users
        $orders = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->select(
                'do.*',
                'pl.packing_number',
                'fo.order_number as floor_order_number',
                'u.nama_lengkap as created_by_name'
            )
            ->orderByDesc('do.created_at')
            ->get();
        return Inertia::render('DeliveryOrder/Index', [
            'orders' => $orders
        ]);
    }

    public function create(Request $request)
    {
        // Ambil daftar packing list yang belum/do belum dibuat
        $usedPackingListIds = DB::table('delivery_orders')->pluck('packing_list_id');
        $packingLists = DB::table('food_packing_lists as pl')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'pl.created_by', '=', 'u.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereNotIn('pl.id', $usedPackingListIds)
            ->select(
                'pl.id',
                'pl.packing_number',
                'pl.created_at',
                'fo.order_number as floor_order_number',
                'fo.tanggal as floor_order_date',
                'o.nama_outlet',
                'u.nama_lengkap as creator_name',
                'wd.name as division_name',
                'w.name as warehouse_name'
            )
            ->orderByDesc('pl.created_at')
            ->get();
        return Inertia::render('DeliveryOrder/Form', [
            'packingLists' => $packingLists
        ]);
    }

    public function show($id)
    {
        $order = DB::table('delivery_orders')->where('id', $id)->first();
        $items = DB::table('delivery_order_items')->where('delivery_order_id', $id)->get();
        return Inertia::render('DeliveryOrder/Show', [
            'order' => $order,
            'items' => $items
        ]);
    }

    private function generateDONumber()
    {
        $prefix = 'DO';
        $date = now()->format('ymd');
        
        // Get the last DO number for today
        $lastDO = DB::table('delivery_orders')
            ->where('number', 'like', $prefix . $date . '%')
            ->orderBy('number', 'desc')
            ->first();
        
        if ($lastDO) {
            $lastNumber = (int) substr($lastDO->number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {
        Log::info('Mulai proses store Delivery Order', $request->all());
        $packingList = DB::table('food_packing_lists')->where('id', $request->packing_list_id)->first();
        $floorOrderId = $packingList->food_floor_order_id ?? null;
        $warehouseDivisionId = $packingList->warehouse_division_id ?? null;
        $warehouseId = null;
        if ($warehouseDivisionId) {
            $warehouseId = DB::table('warehouse_division')->where('id', $warehouseDivisionId)->value('warehouse_id');
        }
        DB::beginTransaction();
        try {
            Log::info('Insert delivery_orders', ['packing_list_id' => $request->packing_list_id]);
            $doId = DB::table('delivery_orders')->insertGetId([
                'number' => $this->generateDONumber(),
                'packing_list_id' => $request->packing_list_id,
                'floor_order_id' => $floorOrderId,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Log::info('DO ID: ' . $doId);
            foreach ($request->items as $item) {
                // Ambil item_id dari food_floor_order_items via packing list item
                $packingListItem = DB::table('food_packing_list_items')->where('id', $item['id'])->first();
                if (!$packingListItem) throw new \Exception('Packing list item tidak ditemukan untuk id: ' . $item['id']);
                $floorOrderItem = DB::table('food_floor_order_items')->where('id', $packingListItem->food_floor_order_item_id)->first();
                if (!$floorOrderItem) throw new \Exception('Floor order item tidak ditemukan untuk id: ' . $packingListItem->food_floor_order_item_id);
                $realItemId = $floorOrderItem->item_id;
                // Ambil barcode hasil scan dari frontend (ambil barcode pertama jika array, string jika satu, null jika tidak ada)
                $barcode = null;
                if (isset($item['barcode'])) {
                    if (is_array($item['barcode']) && count($item['barcode']) > 0) {
                        $barcode = $item['barcode'][0];
                    } elseif (is_string($item['barcode'])) {
                        $barcode = $item['barcode'];
                    }
                }
                DB::table('delivery_order_items')->insert([
                    'delivery_order_id' => $doId,
                    'item_id' => $realItemId,
                    'barcode' => $barcode,
                    'qty_packing_list' => $item['qty'],
                    'qty_scan' => $item['qty_scan'],
                    'unit' => $item['unit'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($warehouseId) {
                    // Ambil item_id dari food_floor_order_items via food_packing_list_items
                    $packingListItem = DB::table('food_packing_list_items')->where('id', $item['id'])->first();
                    if (!$packingListItem) throw new \Exception('Packing list item tidak ditemukan untuk id: ' . $item['id']);
                    $floorOrderItem = DB::table('food_floor_order_items')->where('id', $packingListItem->food_floor_order_item_id)->first();
                    if (!$floorOrderItem) throw new \Exception('Floor order item tidak ditemukan untuk id: ' . $packingListItem->food_floor_order_item_id);
                    $realItemId = $floorOrderItem->item_id;
                    // Pastikan item_id valid
                    $itemMaster = DB::table('items')->where('id', $realItemId)->first();
                    if (!$itemMaster) throw new \Exception('Item master tidak ditemukan di tabel items untuk item_id: ' . $realItemId);
                    // Cari inventory_item_id, insert jika belum ada
                    $inventoryItem = DB::table('food_inventory_items')->where('item_id', $realItemId)->first();
                    if (!$inventoryItem) {
                        $inventoryItemId = DB::table('food_inventory_items')->insertGetId([
                            'item_id' => $realItemId,
                            'small_unit_id' => $itemMaster->small_unit_id,
                            'medium_unit_id' => $itemMaster->medium_unit_id,
                            'large_unit_id' => $itemMaster->large_unit_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $inventoryItem = DB::table('food_inventory_items')->where('id', $inventoryItemId)->first();
                        if (!$inventoryItem) throw new \Exception('Gagal insert food_inventory_items untuk item_id: ' . $realItemId);
                    }
                    $inventory_item_id = $inventoryItem->id;
                    // Ambil data konversi dari tabel items
                    $itemMaster = DB::table('items')->where('id', $realItemId)->first();
                    if (!$itemMaster) throw new \Exception('Item master not found for item_id: ' . $realItemId);
                    $unit = $item['unit'] ?? null;
                    $qty_input = $item['qty_scan'];
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
                    Log::info('Cek stok inventory', [
                        'inventory_item_id' => $inventory_item_id,
                        'warehouse_id' => $warehouseId,
                        'item_id' => $realItemId,
                        'qty_small' => $qty_small,
                    ]);
                    $stock = DB::table('food_inventory_stocks')
                        ->where('inventory_item_id', $inventory_item_id)
                        ->where('warehouse_id', $warehouseId)
                        ->first();
                    if (!$stock) {
                        Log::error('Stok tidak ditemukan di gudang', [
                            'inventory_item_id' => $inventory_item_id,
                            'warehouse_id' => $warehouseId,
                            'item_id' => $realItemId
                        ]);
                        throw new \Exception('Stok tidak ditemukan di gudang');
                    }
                    // Tambahkan log sebelum validasi qty
                    Log::info('Validasi qty vs stok', [
                        'qty_small' => $qty_small,
                        'stok_tersedia' => $stock->qty_small,
                        'unit' => $unitSmall
                    ]);
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
                        'reference_type' => 'delivery_order',
                        'reference_id' => $doId,
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
                        'description' => 'Stock Out - Delivery Order',
                        'created_at' => now(),
                    ]);
                }
            }
            Log::info('Insert activity_logs');
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'module' => 'delivery_order',
                'description' => 'Membuat delivery order untuk packing list #' . $request->packing_list_id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);
            DB::commit();
            Log::info('Sukses simpan Delivery Order');
            return response()->json(['success' => true, 'message' => 'Delivery Order berhasil disimpan!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal simpan Delivery Order: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan Delivery Order: ' . $e->getMessage()]);
        }
    }

    public function getPackingListItems($id)
    {
        // Ambil packing list untuk dapat warehouse_division_id
        $packingList = DB::table('food_packing_lists')->where('id', $id)->first();
        $warehouse_division_id = $packingList->warehouse_division_id ?? null;
        $warehouse_id = null;
        if ($warehouse_division_id) {
            $warehouse_id = DB::table('warehouse_division')->where('id', $warehouse_division_id)->value('warehouse_id');
        }
        $items = DB::table('food_packing_list_items as fpli')
            ->join('food_floor_order_items as ffoi', 'fpli.food_floor_order_item_id', '=', 'ffoi.id')
            ->join('items', 'ffoi.item_id', '=', 'items.id')
            ->select('fpli.id', 'fpli.qty', 'fpli.unit', 'items.name', 'items.id as item_id')
            ->where('fpli.packing_list_id', $id)
            ->where('fpli.qty', '>', 0)
            ->get();
        // Ambil semua barcode untuk setiap item
        $itemIds = $items->pluck('item_id')->unique()->values();
        $barcodeMap = DB::table('item_barcodes')
            ->whereIn('item_id', $itemIds)
            ->select('item_id', 'barcode')
            ->get()
            ->groupBy('item_id')
            ->map(function($rows) {
                return $rows->pluck('barcode')->values();
            });
        // Ambil stock untuk setiap item sesuai unit
        $itemStocks = [];
        if ($warehouse_id) {
            $inventoryItems = DB::table('food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->get()->keyBy('item_id');
            $inventoryItemIds = $inventoryItems->pluck('id')->unique()->values();
            $stocks = DB::table('food_inventory_stocks')
                ->whereIn('inventory_item_id', $inventoryItemIds)
                ->where('warehouse_id', $warehouse_id)
                ->get()->keyBy('inventory_item_id');
            foreach ($items as $item) {
                $inv = $inventoryItems[$item->item_id] ?? null;
                $stock = $inv ? $stocks[$inv->id] ?? null : null;
                $unit = $item->unit;
                $stockQty = null;
                if ($stock) {
                    // Ambil nama unit dari tabel units
                    $unitNameSmall = DB::table('units')->where('id', $inv->small_unit_id)->value('name');
                    $unitNameMedium = DB::table('units')->where('id', $inv->medium_unit_id)->value('name');
                    $unitNameLarge = DB::table('units')->where('id', $inv->large_unit_id)->value('name');
                    if ($unit == $unitNameSmall) {
                        $stockQty = $stock->qty_small;
                    } elseif ($unit == $unitNameMedium) {
                        $stockQty = $stock->qty_medium;
                    } elseif ($unit == $unitNameLarge) {
                        $stockQty = $stock->qty_large;
                    }
                }
                $itemStocks[$item->id] = $stockQty !== null ? (float)$stockQty : 0;
            }
        }
        $items = $items->map(function($item) use ($barcodeMap, $itemStocks) {
            $item->barcodes = $barcodeMap[$item->item_id] ?? collect();
            $item->stock = $itemStocks[$item->id] ?? 0;
            return $item;
        });
        return response()->json(['items' => $items]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $order = DB::table('delivery_orders')->where('id', $id)->first();
            if (!$order) {
                return redirect()->route('delivery-order.index')->with('error', 'Delivery Order tidak ditemukan');
            }
            $items = DB::table('delivery_order_items')->where('delivery_order_id', $id)->get();
            $packingList = DB::table('food_packing_lists')->where('id', $order->packing_list_id)->first();
            $warehouseDivisionId = $packingList->warehouse_division_id ?? null;
            $warehouseId = null;
            if ($warehouseDivisionId) {
                $warehouseId = DB::table('warehouse_division')->where('id', $warehouseDivisionId)->value('warehouse_id');
            }
            // Rollback inventory
            foreach ($items as $item) {
                $realItemId = $item->item_id;
                $itemMaster = DB::table('items')->where('id', $realItemId)->first();
                if (!$itemMaster) continue;
                $inventoryItem = DB::table('food_inventory_items')->where('item_id', $realItemId)->first();
                if (!$inventoryItem) continue;
                $inventory_item_id = $inventoryItem->id;
                $unit = $item->unit ?? null;
                $qty_input = $item->qty_scan;
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
                    \Log::warning('Rollback stok gagal: stok tidak ditemukan', [
                        'inventory_item_id' => $inventory_item_id,
                        'warehouse_id' => $warehouseId,
                        'item_id' => $realItemId
                    ]);
                }
                // Hapus kartu stok OUT
                DB::table('food_inventory_cards')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $warehouseId)
                    ->where('reference_type', 'delivery_order')
                    ->where('reference_id', $id)
                    ->delete();
            }
            // Hapus delivery_order_items
            DB::table('delivery_order_items')->where('delivery_order_id', $id)->delete();
            // Hapus delivery_order
            DB::table('delivery_orders')->where('id', $id)->delete();
            // Log activity
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'delivery_order',
                'description' => 'Menghapus delivery order #' . $id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($order),
                'new_data' => null,
                'created_at' => now(),
            ]);
            DB::commit();
            return redirect()->route('delivery-order.index')->with('success', 'Delivery Order berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
} 