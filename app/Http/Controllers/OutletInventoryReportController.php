<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OutletInventoryReportController extends Controller
{
    // Laporan Stok Akhir Outlet
    public function stockPosition(Request $request)
    {
        $data = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('tbl_data_outlet as o', 's.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'o.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                's.qty_small',
                's.qty_medium',
                's.qty_large',
                's.value',
                's.last_cost_small',
                's.last_cost_medium',
                's.last_cost_large',
                's.updated_at',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name',
                'wo.name as warehouse_outlet_name',
                's.warehouse_outlet_id'
            )
            ->orderBy('o.nama_outlet')
            ->orderBy('i.name')
            ->get();
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        $user = auth()->user();
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

        return inertia('OutletInventory/StockPosition', [
            'stocks' => $data,
            'outlets' => $outlets,
            'warehouse_outlets' => $warehouse_outlets,
            'user_outlet_id' => $user->id_outlet ?? null,
        ]);
    }

    public function stockCard(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $itemId = $request->input('item_id');
        $outletId = $request->input('outlet_id');
        $query = DB::table('outlet_food_inventory_cards as c')
            ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->leftJoin('warehouse_outlets as wo', 'c.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'c.id',
                'c.date',
                'i.id as item_id',
                'i.name as item_name',
                'o.id_outlet',
                'o.nama_outlet as outlet_name',
                'c.in_qty_small',
                'c.in_qty_medium',
                'c.in_qty_large',
                'c.out_qty_small',
                'c.out_qty_medium',
                'c.out_qty_large',
                'c.value_in',
                'c.value_out',
                'c.saldo_value',
                'c.saldo_qty_small',
                'c.saldo_qty_medium',
                'c.saldo_qty_large',
                'c.reference_type',
                'c.reference_id',
                'c.description',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'wo.name as warehouse_outlet_name',
                'c.warehouse_outlet_id'
            );
        if ($itemId) $query->where('i.id', $itemId);
        if ($outletId) $query->where('o.id_outlet', $outletId);
        if ($from) $query->whereDate('c.date', '>=', $from);
        if ($to) $query->whereDate('c.date', '<=', $to);
        $query->orderBy('c.date')->orderBy('c.id');
        $data = $query->get();
        // Saldo awal: ambil saldo akhir transaksi terakhir sebelum tanggal from
        $saldoAwal = null;
        if ($from && $itemId) {
            $saldoQuery = DB::table('outlet_food_inventory_cards as c')
                ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
                ->join('items as i', 'fi.item_id', '=', 'i.id')
                ->where('i.id', $itemId)
                ->whereDate('c.date', '<', $from);
            if ($outletId) $saldoQuery->where('c.id_outlet', $outletId);
            $saldoQuery->orderByDesc('c.date')->orderByDesc('c.id');
            $last = $saldoQuery->first();
            if ($last) {
                $saldoAwal = [
                    'small' => $last->saldo_qty_small,
                    'medium' => $last->saldo_qty_medium,
                    'large' => $last->saldo_qty_large,
                    'small_unit_name' => $last->small_unit_name ?? '',
                    'medium_unit_name' => $last->medium_unit_name ?? '',
                    'large_unit_name' => $last->large_unit_name ?? '',
                ];
            }
        }
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        
        // Selalu kirim semua warehouse outlet untuk filtering di frontend
        $warehouse_outlets = DB::table('warehouse_outlets')
            ->where('status', 'active')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();
        $items = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('categories.show_pos', '0')
            ->select('items.id', 'items.name')
            ->orderBy('items.name')
            ->get();
        return inertia('OutletInventory/StockCard', [
            'cards' => $data,
            'outlets' => $outlets,
            'warehouse_outlets' => $warehouse_outlets,
            'items' => $items,
            'saldo_awal' => $saldoAwal,
        ]);
    }

    public function inventoryValueReport(Request $request)
    {
        $data = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->join('tbl_data_outlet as o', 's.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'i.name as item_name',
                'o.nama_outlet as outlet_name',
                'c.name as category_name',
                's.qty_small',
                's.qty_medium',
                's.qty_large',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                's.value',
                DB::raw('(
                    SELECT new_cost
                    FROM outlet_food_inventory_cost_histories
                    WHERE inventory_item_id = s.inventory_item_id AND id_outlet = s.id_outlet
                    ORDER BY date DESC, created_at DESC
                    LIMIT 1
                ) as last_cost_small'),
                DB::raw('(
                    SELECT mac
                    FROM outlet_food_inventory_cost_histories
                    WHERE inventory_item_id = s.inventory_item_id AND id_outlet = s.id_outlet
                    ORDER BY date DESC, created_at DESC
                    LIMIT 1
                ) as mac'),
                's.last_cost_medium',
                's.last_cost_large',
                DB::raw('(
                    s.qty_small * (
                        SELECT mac
                        FROM outlet_food_inventory_cost_histories
                        WHERE inventory_item_id = s.inventory_item_id AND id_outlet = s.id_outlet
                        ORDER BY date DESC, created_at DESC
                        LIMIT 1
                    )
                ) as total_value'),
                'wo.name as warehouse_outlet_name',
                's.warehouse_outlet_id'
            )
            ->orderBy('o.nama_outlet')
            ->orderBy('i.name')
            ->get();
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $categories = DB::table('categories')->select('id', 'name')->orderBy('name')->get();
        $user = auth()->user();
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
        $items = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('categories.show_pos', '0')
            ->select('items.id', 'items.name', 'items.small_unit_id', 'items.medium_unit_id', 'items.large_unit_id')
            ->orderBy('items.name')
            ->get();
        return inertia('OutletInventory/InventoryValueReport', [
            'stocks' => $data,
            'outlets' => $outlets,
            'categories' => $categories,
            'warehouse_outlets' => $warehouse_outlets,
            'items' => $items,
        ]);
    }

    public function categoryRecapReport(Request $request)
    {
        $data = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->join('tbl_data_outlet as o', 's.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'c.name as category_name',
                'o.nama_outlet as outlet_name',
                DB::raw('SUM(s.qty_small) as total_qty'),
                DB::raw('SUM(s.qty_small * (
                    SELECT mac
                    FROM outlet_food_inventory_cost_histories
                    WHERE inventory_item_id = s.inventory_item_id AND id_outlet = s.id_outlet
                    ORDER BY date DESC, created_at DESC
                    LIMIT 1
                )) as total_value'),
                'wo.name as warehouse_outlet_name',
                's.warehouse_outlet_id'
            )
            ->groupBy('c.name', 'o.nama_outlet', 'wo.name', 's.warehouse_outlet_id')
            ->orderBy('c.name')
            ->orderBy('o.nama_outlet')
            ->get();
        $categories = DB::table('categories')->select('id', 'name')->orderBy('name')->get();
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $user = auth()->user();
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
        return inertia('OutletInventory/CategoryRecapReport', [
            'recaps' => $data,
            'categories' => $categories,
            'outlets' => $outlets,
            'warehouse_outlets' => $warehouse_outlets,
        ]);
    }
} 