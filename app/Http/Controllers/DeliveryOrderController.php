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
                'packing_list_id' => $request->packing_list_id,
                'floor_order_id' => $floorOrderId, // simpan id FO
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Log::info('DO ID: ' . $doId);
            foreach ($request->items as $item) {
                Log::info('Insert delivery_order_items', $item);
                DB::table('delivery_order_items')->insert([
                    'delivery_order_id' => $doId,
                    'item_id' => $item['id'],
                    'barcode' => $item['barcode'],
                    'qty_packing_list' => $item['qty'],
                    'qty_scan' => $item['qty_scan'],
                    'unit' => $item['unit'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($warehouseId) {
                    $inventoryItem = DB::table('food_inventory_items')->where('item_id', $item['id'])->first();
                    $itemMaster = \App\Models\Item::find($item['id']);
                    if ($inventoryItem && $itemMaster) {
                        $stock = DB::table('food_inventory_stocks')
                            ->where('inventory_item_id', $inventoryItem->id)
                            ->where('warehouse_id', $warehouseId)
                            ->first();
                        if ($stock) {
                            $unitInput = $item['unit'] ?? null;
                            $qtyInput = $item['qty_scan'];
                            $unitSmall = optional($itemMaster->smallUnit)->name;
                            $unitMedium = optional($itemMaster->mediumUnit)->name;
                            $unitLarge = optional($itemMaster->largeUnit)->name;
                            $smallConv = $itemMaster->small_conversion_qty ?: 1;
                            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                            $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                            if ($unitInput === $unitSmall) {
                                $qty_small = $qtyInput;
                                $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                                $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                            } elseif ($unitInput === $unitMedium) {
                                $qty_medium = $qtyInput;
                                $qty_small = $qty_medium * $smallConv;
                                $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                            } elseif ($unitInput === $unitLarge) {
                                $qty_large = $qtyInput;
                                $qty_medium = $qty_large * $mediumConv;
                                $qty_small = $qty_medium * $smallConv;
                            } else {
                                $qty_small = $qtyInput;
                            }
                            Log::info('Konversi qty', compact('unitInput','qtyInput','qty_small','qty_medium','qty_large','unitSmall','unitMedium','unitLarge','smallConv','mediumConv'));
                            $lastCostSmall = $stock->last_cost_small ?? 0;
                            $lastCostMedium = $stock->last_cost_medium ?? 0;
                            $lastCostLarge = $stock->last_cost_large ?? 0;
                            $saldo_qty_small = $stock->qty_small - $qty_small;
                            $saldo_qty_medium = $stock->qty_medium - $qty_medium;
                            $saldo_qty_large = $stock->qty_large - $qty_large;
                            $saldo_value = ($saldo_qty_small * $lastCostSmall)
                                + ($saldo_qty_medium * $lastCostMedium)
                                + ($saldo_qty_large * $lastCostLarge);
                            DB::table('food_inventory_stocks')
                                ->where('id', $stock->id)
                                ->update([
                                    'qty_small' => $saldo_qty_small,
                                    'qty_medium' => $saldo_qty_medium,
                                    'qty_large' => $saldo_qty_large,
                                ]);
                            Log::info('Update stok', ['stock_id' => $stock->id, 'qty_small' => $saldo_qty_small, 'qty_medium' => $saldo_qty_medium, 'qty_large' => $saldo_qty_large]);
                            DB::table('food_inventory_cards')->insert([
                                'inventory_item_id' => $inventoryItem->id,
                                'warehouse_id' => $warehouseId,
                                'date' => now()->toDateString(),
                                'reference_type' => 'delivery_order',
                                'reference_id' => $doId,
                                'out_qty_small' => $qty_small,
                                'out_qty_medium' => $qty_medium,
                                'out_qty_large' => $qty_large,
                                'cost_per_small' => $lastCostSmall,
                                'cost_per_medium' => $lastCostMedium,
                                'cost_per_large' => $lastCostLarge,
                                'value_out' => $qty_small * $lastCostSmall,
                                'saldo_qty_small' => $saldo_qty_small,
                                'saldo_qty_medium' => $saldo_qty_medium,
                                'saldo_qty_large' => $saldo_qty_large,
                                'saldo_value' => $saldo_value,
                                'description' => 'Delivery Order',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            Log::info('Insert kartu stok', ['inventory_item_id' => $inventoryItem->id, 'warehouse_id' => $warehouseId]);
                        }
                    }
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
            return redirect()->route('delivery-order.index')->with('success', 'Delivery Order berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal simpan Delivery Order: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Gagal menyimpan Delivery Order: ' . $e->getMessage());
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
                $inventoryItem = DB::table('food_inventory_items')->where('item_id', $item->item_id)->first();
                $itemMaster = Item::find($item->item_id);
                if ($inventoryItem && $itemMaster && $warehouseId) {
                    $stock = DB::table('food_inventory_stocks')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('warehouse_id', $warehouseId)
                        ->first();
                    if ($stock) {
                        // Konversi qty_scan ke small, medium, large
                        $unitInput = $item->unit ?? null;
                        $qtyInput = $item->qty_scan;
                        $unitSmall = optional($itemMaster->smallUnit)->name;
                        $unitMedium = optional($itemMaster->mediumUnit)->name;
                        $unitLarge = optional($itemMaster->largeUnit)->name;
                        $smallConv = $itemMaster->small_conversion_qty ?: 1;
                        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                        $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                        if ($unitInput === $unitSmall) {
                            $qty_small = $qtyInput;
                            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                            $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                        } elseif ($unitInput === $unitMedium) {
                            $qty_medium = $qtyInput;
                            $qty_small = $qty_medium * $smallConv;
                            $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                        } elseif ($unitInput === $unitLarge) {
                            $qty_large = $qtyInput;
                            $qty_medium = $qty_large * $mediumConv;
                            $qty_small = $qty_medium * $smallConv;
                        } else {
                            $qty_small = $qtyInput;
                        }
                        // Rollback semua level
                        DB::table('food_inventory_stocks')
                            ->where('id', $stock->id)
                            ->update([
                                'qty_small' => $stock->qty_small + $qty_small,
                                'qty_medium' => $stock->qty_medium + $qty_medium,
                                'qty_large' => $stock->qty_large + $qty_large,
                            ]);
                    }
                    // Hapus kartu stok OUT
                    DB::table('food_inventory_cards')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('warehouse_id', $warehouseId)
                        ->where('reference_type', 'delivery_order')
                        ->where('reference_id', $id)
                        ->delete();
                }
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