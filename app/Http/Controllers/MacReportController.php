<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MacReportController extends Controller
{
    public function index(Request $request)
    {
        // Get latest MAC for each inventory item with unit information and conversions
        $query = DB::table('food_inventory_cost_histories as fh')
            ->leftJoin('food_inventory_items as fi', 'fh.inventory_item_id', '=', 'fi.id')
            ->leftJoin('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('units as small_u', 'i.small_unit_id', '=', 'small_u.id')
            ->leftJoin('units as medium_u', 'i.medium_unit_id', '=', 'medium_u.id')
            ->leftJoin('units as large_u', 'i.large_unit_id', '=', 'large_u.id')
            ->leftJoin('warehouses as w', 'fh.warehouse_id', '=', 'w.id')
            ->leftJoin('item_prices as ip', 'i.id', '=', 'ip.item_id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'i.sku as item_code',
                'small_u.name as small_unit_name',
                'medium_u.name as medium_unit_name',
                'large_u.name as large_unit_name',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'w.name as warehouse_name',
                'fh.mac as current_cost',
                'fh.date as last_updated',
                'ip.price as item_price',
                DB::raw('((fh.mac * i.small_conversion_qty / i.medium_conversion_qty) * 1.12) as mac_plus_12')
            )
            ->whereIn('fh.id', function($query) {
                $query->select(DB::raw('MAX(id)'))
                      ->from('food_inventory_cost_histories')
                      ->groupBy('inventory_item_id');
            })
            ->groupBy('i.id', 'i.name', 'i.sku', 'small_u.name', 'medium_u.name', 'large_u.name', 'i.small_conversion_qty', 'i.medium_conversion_qty', 'w.name', 'fh.mac', 'fh.date', 'ip.price')
            ->orderBy('i.name')
            ->orderBy('w.name');

        // Filter by search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('i.name', 'like', "%{$search}%")
                  ->orWhere('i.sku', 'like', "%{$search}%")
                  ->orWhere('w.name', 'like', "%{$search}%");
            });
        }

        // Filter by warehouse
        if ($request->filled('warehouse_id')) {
            $query->where('w.id', $request->warehouse_id);
        }

        $perPage = $request->get('per_page', 50);
        $items = $query->paginate($perPage);

        // Get warehouses for filter
        $warehouses = DB::table('warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();



        // Summary statistics
        $summary = DB::table('food_inventory_cost_histories as fh')
            ->leftJoin('food_inventory_items as fi', 'fh.inventory_item_id', '=', 'fi.id')
            ->leftJoin('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('item_prices as ip', 'i.id', '=', 'ip.item_id')
                         ->select(
                 DB::raw('COUNT(DISTINCT i.id) as total_items'),
                 DB::raw('COUNT(CASE WHEN ip.price > ((fh.mac * i.small_conversion_qty / i.medium_conversion_qty) * 1.12) + 1 THEN 1 END) as profit_items'),
                 DB::raw('COUNT(CASE WHEN ip.price < ((fh.mac * i.small_conversion_qty / i.medium_conversion_qty) * 1.12) - 1 THEN 1 END) as loss_items'),
                 DB::raw('COUNT(CASE WHEN ABS(ip.price - ((fh.mac * i.small_conversion_qty / i.medium_conversion_qty) * 1.12)) <= 1 THEN 1 END) as break_even_items')
             )
            ->whereIn('fh.id', function($query) {
                $query->select(DB::raw('MAX(id)'))
                      ->from('food_inventory_cost_histories')
                      ->groupBy('inventory_item_id');
            })
            ->first();

        return Inertia::render('MacReport/Index', [
            'items' => $items,
            'warehouses' => $warehouses,
            'summary' => $summary,
            'filters' => $request->only(['search', 'warehouse_id', 'per_page'])
        ]);
    }

    public function export(Request $request)
    {
        $query = DB::table('food_inventory_cost_histories as fh')
            ->leftJoin('food_inventory_items as fi', 'fh.inventory_item_id', '=', 'fi.id')
            ->leftJoin('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('units as small_u', 'i.small_unit_id', '=', 'small_u.id')
            ->leftJoin('units as medium_u', 'i.medium_unit_id', '=', 'medium_u.id')
            ->leftJoin('units as large_u', 'i.large_unit_id', '=', 'large_u.id')
            ->leftJoin('warehouses as w', 'fh.warehouse_id', '=', 'w.id')
            ->leftJoin('item_prices as ip', 'i.id', '=', 'ip.item_id')
            ->select(
                'i.sku as item_code',
                'i.name as item_name',
                'small_u.name as small_unit_name',
                'medium_u.name as medium_unit_name',
                'large_u.name as large_unit_name',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'w.name as warehouse_name',
                'fh.mac as current_cost',
                'fh.date as last_updated',
                'ip.price as item_price',
                DB::raw('((fh.mac * i.small_conversion_qty / i.medium_conversion_qty) * 1.12) as mac_plus_12')
            )
            ->whereIn('fh.id', function($query) {
                $query->select(DB::raw('MAX(id)'))
                      ->from('food_inventory_cost_histories')
                      ->groupBy('inventory_item_id');
            })
            ->groupBy('i.id', 'i.name', 'i.sku', 'small_u.name', 'medium_u.name', 'large_u.name', 'i.small_conversion_qty', 'i.medium_conversion_qty', 'w.name', 'fh.mac', 'fh.date', 'ip.price')
            ->orderBy('i.name')
            ->orderBy('w.name');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('i.name', 'like', "%{$search}%")
                  ->orWhere('i.sku', 'like', "%{$search}%")
                  ->orWhere('w.name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('warehouse_id')) {
            $query->where('w.id', $request->warehouse_id);
        }

        $data = $query->get();

        $filename = 'mac_report_' . date('Y-m-d_H-i-s') . '.xlsx';

        return response()->json([
            'success' => true,
            'data' => $data,
            'filename' => $filename
        ]);
    }
}
