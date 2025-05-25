<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

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
        // Ambil floor_order_id dari packing_lists
        $packingList = DB::table('packing_lists')->where('id', $request->packing_list_id)->first();
        $floorOrderId = $packingList->floor_order_id ?? null;
        // Simpan hasil scan
        $doId = DB::table('delivery_orders')->insertGetId([
            'packing_list_id' => $request->packing_list_id,
            'floor_order_id' => $floorOrderId, // simpan id FO
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ($request->items as $item) {
            DB::table('delivery_order_items')->insert([
                'delivery_order_id' => $doId,
                'item_id' => $item['id'],
                'barcode' => $item['barcode'],
                'qty_packing_list' => $item['qty'],
                'qty_scan' => $item['qty_scan'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return redirect()->route('delivery-order.index')->with('success', 'Delivery Order berhasil disimpan!');
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
} 