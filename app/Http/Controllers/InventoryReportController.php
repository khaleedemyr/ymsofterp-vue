<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class InventoryReportController extends Controller
{
    // Laporan Stok Akhir
    public function stockPosition(Request $request)
    {
        $data = DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('warehouses as w', 's.warehouse_id', '=', 'w.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'w.id as warehouse_id',
                'w.name as warehouse_name',
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
                'ul.name as large_unit_name'
            )
            ->orderBy('w.name')
            ->orderBy('i.name')
            ->get();
        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();

        // Tampilkan qty langsung dari database
        $data = $data->map(function ($row) {
            $row->display_small = $row->qty_small;
            $row->display_medium = $row->qty_medium;
            $row->display_large = $row->qty_large;
            return $row;
        });

        return inertia('Inventory/StockPosition', [
            'stocks' => $data,
            'warehouses' => $warehouses,
        ]);
    }

    // Laporan Kartu Stok
    public function stockCard(Request $request)
    {
        $data = DB::table('food_inventory_cards as c')
            ->join('food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('warehouses as w', 'c.warehouse_id', '=', 'w.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->leftJoin('food_good_receives as gr', function($join) {
                $join->on('c.reference_id', '=', 'gr.id')
                     ->where('c.reference_type', '=', 'good_receive');
            })
            ->leftJoin('warehouse_transfers as wt', function($join) {
                $join->on('c.reference_id', '=', 'wt.id')
                     ->where('c.reference_type', '=', 'warehouse_transfer');
            })
            ->select(
                'c.id',
                'c.date',
                'i.name as item_name',
                'w.name as warehouse_name',
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
                'gr.gr_number as reference_number',
                'wt.transfer_number as transfer_number',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->orderBy('i.name')
            ->orderBy('w.name')
            ->orderBy('c.date')
            ->orderBy('c.id')
            ->get();

        // Setelah get(), map reference_number agar jika reference_type warehouse_transfer, pakai transfer_number
        $data = $data->map(function ($row) {
            if ($row->reference_type === 'warehouse_transfer' && $row->transfer_number) {
                $row->reference_number = $row->transfer_number;
            }
            return $row;
        });

        // Hitung saldo dinamis per item+warehouse dan konversi pecahan ke semua satuan
        $saldoMap = [];
        foreach ($data as $row) {
            $key = $row->item_name . '-' . $row->warehouse_name;
            $smallPerMedium = (int)($row->small_conversion_qty ?? 1);
            $mediumPerLarge = (int)($row->medium_conversion_qty ?? 1);

            // Konversi semua qty masuk dan keluar ke small unit
            $in_total_small = ($row->in_qty_small ?? 0)
                + (($row->in_qty_medium ?? 0) * $smallPerMedium)
                + (($row->in_qty_large ?? 0) * $smallPerMedium * $mediumPerLarge);
            $out_total_small = ($row->out_qty_small ?? 0)
                + (($row->out_qty_medium ?? 0) * $smallPerMedium)
                + (($row->out_qty_large ?? 0) * $smallPerMedium * $mediumPerLarge);

            // Hitung saldo small dinamis
            if (!isset($saldoMap[$key])) {
                $saldoMap[$key] = 0;
            }
            $saldoMap[$key] += $in_total_small - $out_total_small;
            $saldo_total_small = $saldoMap[$key];

            // Pecah saldo ke large, medium, small
            $display_large = ($smallPerMedium > 0 && $mediumPerLarge > 0) ? intdiv($saldo_total_small, $smallPerMedium * $mediumPerLarge) : 0;
            $sisaSetelahLarge = ($smallPerMedium > 0 && $mediumPerLarge > 0) ? $saldo_total_small % ($smallPerMedium * $mediumPerLarge) : $saldo_total_small;
            $display_medium = $smallPerMedium > 0 ? intdiv($sisaSetelahLarge, $smallPerMedium) : 0;
            $display_small = $smallPerMedium > 0 ? $sisaSetelahLarge % $smallPerMedium : $sisaSetelahLarge;

            $row->display_large = (int)($display_large ?? 0);
            $row->display_medium = (int)($display_medium ?? 0);
            $row->display_small = (int)($display_small ?? 0);

            // Tambahkan juga total in/out dalam satuan medium/large untuk frontend jika perlu
            $row->in_total_small = $in_total_small;
            $row->out_total_small = $out_total_small;

            \Log::info('StockCard row', [
                'item' => $row->item_name,
                'small_conversion_qty' => $smallPerMedium,
                'medium_conversion_qty' => $mediumPerLarge,
                'display_large' => $row->display_large,
                'display_medium' => $row->display_medium,
                'display_small' => $row->display_small,
            ]);
        }

        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();
        $items = DB::table('items')->select('id', 'name')->orderBy('name')->get();
        return inertia('Inventory/StockCard', [
            'cards' => $data,
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    // Laporan Penerimaan Barang (Goods Received Report)
    public function goodsReceivedReport(Request $request)
    {
        $data = DB::table('food_inventory_cards as c')
            ->join('food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('warehouses as w', 'c.warehouse_id', '=', 'w.id')
            ->leftJoin('food_good_receives as gr', function($join) {
                $join->on('c.reference_id', '=', 'gr.id')
                     ->where('c.reference_type', '=', 'good_receive');
            })
            ->leftJoin('food_good_receive_items as gri', function($join) {
                $join->on('gri.good_receive_id', '=', 'gr.id')
                     ->on('gri.item_id', '=', 'fi.item_id');
            })
            ->leftJoin('purchase_order_food_items as poi', function($join) {
                $join->on('poi.id', '=', 'gri.po_item_id');
            })
            ->leftJoin('pr_food_items as pri', 'poi.pr_food_item_id', '=', 'pri.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->select(
                'c.date',
                'i.name as item_name',
                'w.name as warehouse_name',
                DB::raw('c.in_qty_small + c.in_qty_medium + c.in_qty_large as qty_received'),
                'pri.unit as unit_pr',
                's.name as supplier_name',
                'po.number as po_number',
                'gr.gr_number',
                'c.value_in',
                'c.description'
            )
            ->where('c.reference_type', 'good_receive')
            ->orderByDesc('c.date')
            ->orderByDesc('c.id')
            ->get();
        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();
        $items = DB::table('items')->select('id', 'name')->orderBy('name')->get();
        return inertia('Inventory/GoodsReceivedReport', [
            'receives' => $data,
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    // Laporan Nilai Persediaan (Inventory Value Report)
    public function inventoryValueReport(Request $request)
    {
        $data = DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->join('warehouses as w', 's.warehouse_id', '=', 'w.id')
            ->select(
                'i.name as item_name',
                'w.name as warehouse_name',
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
                's.last_cost_small',
                's.last_cost_medium',
                's.last_cost_large',
                's.value as total_value'
            )
            ->orderBy('w.name')
            ->orderBy('i.name')
            ->get();
        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();
        $categories = DB::table('categories')->select('id', 'name')->orderBy('name')->get();
        $items = DB::table('items')->select('id', 'name', 'small_unit_id', 'medium_unit_id', 'large_unit_id')->orderBy('name')->get();
        return inertia('Inventory/InventoryValueReport', [
            'stocks' => $data,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'items' => $items,
        ]);
    }

    // Laporan Riwayat Perubahan Harga Pokok (Cost History Report)
    public function costHistoryReport(Request $request)
    {
        $data = DB::table('food_inventory_cost_histories as ch')
            ->join('food_inventory_items as fi', 'ch.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('warehouses as w', 'ch.warehouse_id', '=', 'w.id')
            ->leftJoin('food_good_receives as gr', function($join) {
                $join->on('ch.reference_id', '=', 'gr.id')
                     ->where('ch.reference_type', '=', 'good_receive');
            })
            ->leftJoin('purchase_order_foods as po', function($join) {
                $join->on('ch.reference_id', '=', 'po.id')
                     ->where('ch.reference_type', '=', 'purchase_order');
            })
            ->select(
                'ch.created_at as date',
                'i.name as item_name',
                'w.name as warehouse_name',
                'ch.old_cost',
                'ch.new_cost',
                'ch.type',
                'ch.reference_type',
                'ch.reference_id',
                'ch.date',
                DB::raw('COALESCE(gr.gr_number, po.number) as reference_number')
            )
            ->orderBy('ch.created_at', 'desc')
            ->get();

        $warehouses = DB::table('warehouses')->select('id', 'name')->get();
        $items = DB::table('items')->select('id', 'name')->get();

        return Inertia::render('Inventory/CostHistoryReport', [
            'histories' => $data,
            'warehouses' => $warehouses,
            'items' => $items
        ]);
    }

    // Laporan Stok Minimum / Safety Stock
    public function minimumStockReport(Request $request)
    {
        $data = DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('warehouses as w', 's.warehouse_id', '=', 'w.id')
            ->select(
                'i.name as item_name',
                'w.name as warehouse_name',
                DB::raw('(s.qty_small + s.qty_medium + s.qty_large) as qty'),
                'i.min_stock'
            )
            ->whereRaw('(s.qty_small + s.qty_medium + s.qty_large) < i.min_stock')
            ->orderBy('w.name')
            ->orderBy('i.name')
            ->get();
        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();
        $items = DB::table('items')->select('id', 'name')->orderBy('name')->get();
        return inertia('Inventory/MinimumStockReport', [
            'stocks' => $data,
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    // Laporan Rekap Persediaan per Kategori
    public function categoryRecapReport(Request $request)
    {
        $data = DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->select(
                'c.name as category_name',
                DB::raw('SUM(s.qty_small + s.qty_medium + s.qty_large) as total_qty'),
                DB::raw('SUM(s.qty_small * s.last_cost_small + s.qty_medium * s.last_cost_medium + s.qty_large * s.last_cost_large) as total_value')
            )
            ->groupBy('c.name')
            ->orderBy('c.name')
            ->get();
        $categories = DB::table('categories')->select('id', 'name')->orderBy('name')->get();
        return inertia('Inventory/CategoryRecapReport', [
            'recaps' => $data,
            'categories' => $categories,
        ]);
    }

    // Laporan Umur Persediaan (Aging Report)
    public function agingReport(Request $request)
    {
        $today = now();
        $stocks = DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->join('warehouses as w', 's.warehouse_id', '=', 'w.id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'w.id as warehouse_id',
                'w.name as warehouse_name',
                'c.name as category_name',
                's.qty_small',
                's.qty_medium',
                's.qty_large',
                's.updated_at'
            )
            ->orderBy('w.name')
            ->orderBy('i.name')
            ->get();

        $agings = $stocks->map(function ($row) use ($today) {
            // Ambil semua kartu stok untuk item+warehouse ini
            $cards = DB::table('food_inventory_cards as c')
                ->join('food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
                ->where('fi.item_id', $row->item_id)
                ->where('c.warehouse_id', $row->warehouse_id)
                ->orderBy('c.date', 'asc')
                ->get();
            $firstIn = $cards->filter(function($c) {
                return ($c->in_qty_small ?? 0) > 0 || ($c->in_qty_medium ?? 0) > 0 || ($c->in_qty_large ?? 0) > 0;
            })->sortBy('date')->first();
            $lastOut = $cards->filter(function($c) {
                return ($c->out_qty_small ?? 0) > 0 || ($c->out_qty_medium ?? 0) > 0 || ($c->out_qty_large ?? 0) > 0;
            })->sortByDesc('date')->first();
            $firstInDate = $firstIn ? $firstIn->date : null;
            $lastOutDate = $lastOut ? $lastOut->date : null;
            if ($firstInDate) {
                try {
                    $dateObj = \Carbon\Carbon::createFromFormat('m/d/Y', $firstInDate);
                } catch (\Exception $e) {
                    try {
                        $dateObj = \Carbon\Carbon::parse($firstInDate);
                    } catch (\Exception $e2) {
                        $dateObj = null;
                    }
                }
                $daysInStock = $dateObj ? $today->diffInDays($dateObj) : null;
            } else {
                $daysInStock = null;
            }
            $hasOut30 = $cards->filter(function($c) use ($today) {
                return (
                    ($c->out_qty_small ?? 0) > 0 ||
                    ($c->out_qty_medium ?? 0) > 0 ||
                    ($c->out_qty_large ?? 0) > 0
                ) && $c->date >= $today->copy()->subDays(30)->toDateString();
            })->count() > 0;
            $hasOut90 = $cards->filter(function($c) use ($today) {
                return (
                    ($c->out_qty_small ?? 0) > 0 ||
                    ($c->out_qty_medium ?? 0) > 0 ||
                    ($c->out_qty_large ?? 0) > 0
                ) && $c->date >= $today->copy()->subDays(90)->toDateString();
            })->count() > 0;

            if ($hasOut30) {
                $moving = 'Fast Moving';
            } elseif (!$hasOut90 || ($daysInStock && $daysInStock > 90)) {
                $moving = 'Slow Moving';
            } else {
                $moving = 'Normal';
            }

            $stock = ($row->qty_small ?? 0) + ($row->qty_medium ?? 0) + ($row->qty_large ?? 0);

            return [
                'item_name' => $row->item_name,
                'warehouse_name' => $row->warehouse_name,
                'category_name' => $row->category_name,
                'stock' => $stock,
                'days_in_stock' => is_null($daysInStock) ? null : max(0, (int) $daysInStock),
                'moving_category' => $moving,
                'first_in_date' => $firstInDate,
                'last_out_date' => $lastOutDate,
            ];
        });

        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();
        $categories = DB::table('categories')->select('id', 'name')->orderBy('name')->get();
        $items = DB::table('items')->select('id', 'name')->orderBy('name')->get();
        return inertia('Inventory/AgingReport', [
            'agings' => $agings,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'items' => $items,
        ]);
    }
} 