<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Exports\StockPositionExport;
use Maatwebsite\Excel\Facades\Excel;

class InventoryReportController extends Controller
{
    /**
     * API: Laporan Stok Akhir Warehouse untuk mobile app (JSON).
     */
    public function apiStockPosition(Request $request)
    {
        $warehouses = DB::table('warehouses')->where('status', 'active')->select('id', 'name')->orderBy('name')->get();

        $query = DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('warehouses as w', 's.warehouse_id', '=', 'w.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'i.category_id as category_id',
                'c.name as category_name',
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
            ->orderBy('i.name');

        if ($request->filled('warehouse_id')) {
            $query->where('s.warehouse_id', $request->warehouse_id);
        }

        $data = $query->get()->map(function ($row) {
            $row->display_small = $row->qty_small;
            $row->display_medium = $row->qty_medium;
            $row->display_large = $row->qty_large;
            return $row;
        });

        return response()->json([
            'success' => true,
            'warehouses' => $warehouses,
            'stocks' => $data,
        ]);
    }

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

    // Export Stock Position to Excel
    public function exportStockPosition(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        return Excel::download(
            new StockPositionExport($warehouseId), 
            "laporan_stok_akhir_{$timestamp}.xlsx"
        );
    }

    // Laporan Kartu Stok
    public function stockCard(Request $request)
    {
        try {
            // Set memory limit dan execution time untuk query yang berat
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300);
            
            $from = $request->input('from');
            $to = $request->input('to');
            $itemId = $request->input('item_id');
            $warehouseId = $request->input('warehouse_id');
            
            // Log request untuk debugging
            \Log::info('Stock Card Request', [
                'from' => $from,
                'to' => $to,
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'all_params' => $request->all()
            ]);
            
            // Validasi input yang diperlukan - harus ada item_id untuk load data
            if (!$itemId) {
                return inertia('Inventory/StockCard', [
                    'cards' => collect([]),
                    'warehouses' => DB::table('warehouses')->select('id', 'name')->orderBy('name')->get(),
                    'items' => DB::table('items')
                        ->join('categories', 'items.category_id', '=', 'categories.id')
                        ->where('categories.show_pos', '0')
                        ->select('items.id', 'items.name')
                        ->orderBy('items.name')
                        ->get(),
                    'saldo_awal' => null,
                    'error' => null
                ]);
            }
            
            // Batasi range tanggal untuk mencegah query terlalu berat
            if ($from && $to) {
                $fromDate = \Carbon\Carbon::parse($from);
                $toDate = \Carbon\Carbon::parse($to);
                $diffInDays = $fromDate->diffInDays($toDate);
                
                if ($diffInDays > 365) {
                    return inertia('Inventory/StockCard', [
                        'cards' => collect([]),
                        'warehouses' => DB::table('warehouses')->select('id', 'name')->orderBy('name')->get(),
                        'items' => DB::table('items')
                            ->join('categories', 'items.category_id', '=', 'categories.id')
                            ->where('categories.show_pos', '0')
                            ->select('items.id', 'items.name')
                            ->orderBy('items.name')
                            ->get(),
                        'saldo_awal' => null,
                        'error' => 'Range tanggal maksimal 1 tahun untuk performa yang optimal'
                    ]);
                }
            }
            
            $query = DB::table('food_inventory_cards as c')
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
                ->leftJoin('delivery_orders as do', function($join) {
                    $join->on('c.reference_id', '=', 'do.id')
                         ->where('c.reference_type', '=', 'delivery_order');
                })
                ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
                ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
                ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
                ->select(
                    'c.id',
                    'c.date',
                    'i.id as item_id',
                    'i.name as item_name',
                    'w.id as warehouse_id',
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
                    'do.number as do_number',
                    'o.nama_outlet as outlet_name',
                    'wo.name as warehouse_outlet_name',
                    'us.name as small_unit_name',
                    'um.name as medium_unit_name',
                    'ul.name as large_unit_name',
                    'i.small_conversion_qty',
                    'i.medium_conversion_qty'
                );
            
            // Apply filters
            if ($itemId) $query->where('i.id', $itemId);
            if ($warehouseId) $query->where('w.id', $warehouseId);
            if ($from) $query->whereDate('c.date', '>=', $from);
            if ($to) $query->whereDate('c.date', '<=', $to);
            
            // Add pagination untuk mencegah memory overflow
            $query->orderBy('c.date')->orderBy('c.id');
            
            // Limit hasil untuk performa yang lebih baik
            $query->limit(10000);
            
            $data = $query->get();
            
            // Log hasil query untuk debugging
            \Log::info('Stock Card Query Result', [
                'total_records' => $data->count(),
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'from' => $from,
                'to' => $to
            ]);
            
            // Modifikasi description untuk delivery order
            $data = $data->map(function($row) {
                if ($row->reference_type === 'delivery_order' && $row->do_number) {
                    $description = 'Stock Out - Delivery Order';
                    if ($row->outlet_name || $row->warehouse_outlet_name) {
                        $description .= ' - DO: ' . $row->do_number;
                        if ($row->outlet_name) {
                            $description .= ', Outlet: ' . $row->outlet_name;
                        }
                        if ($row->warehouse_outlet_name) {
                            $description .= ', Warehouse Outlet: ' . $row->warehouse_outlet_name;
                        }
                    }
                    $row->description = $description;
                }
                return $row;
            });
            
            // Saldo awal: ambil saldo akhir transaksi terakhir sebelum tanggal from
            $saldoAwal = null;
            if ($from && $itemId) {
                $saldoQuery = DB::table('food_inventory_cards as c')
                    ->join('food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
                    ->join('items as i', 'fi.item_id', '=', 'i.id')
                    ->where('i.id', $itemId)
                    ->whereDate('c.date', '<', $from);
                if ($warehouseId) $saldoQuery->where('c.warehouse_id', $warehouseId);
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
            
            $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();
            $items = DB::table('items')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->where('categories.show_pos', '0')
                ->select('items.id', 'items.name')
                ->orderBy('items.name')
                ->get();
                
            return inertia('Inventory/StockCard', [
                'cards' => $data,
                'warehouses' => $warehouses,
                'items' => $items,
                'saldo_awal' => $saldoAwal,
            ]);
            
        } catch (\Exception $e) {
            // Log error untuk debugging
            \Log::error('Stock Card Report Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            // Return error response
            return inertia('Inventory/StockCard', [
                'cards' => collect([]),
                'warehouses' => DB::table('warehouses')->select('id', 'name')->orderBy('name')->get(),
                'items' => DB::table('items')
                    ->join('categories', 'items.category_id', '=', 'categories.id')
                    ->where('categories.show_pos', '0')
                    ->select('items.id', 'items.name')
                    ->orderBy('items.name')
                    ->get(),
                'saldo_awal' => null,
                'error' => 'Terjadi kesalahan saat memuat data. Silakan coba lagi atau hubungi administrator.'
            ]);
        }
    }

    /**
     * Get stock card detail for a specific item (for expandable rows in stock position)
     */
    public function getStockCardDetail(Request $request)
    {
        $user = auth()->user();
        
        // Validasi user sudah login
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized. Silakan login terlebih dahulu.'
            ], 401);
        }
        
        $itemId = $request->input('item_id');
        $warehouseId = $request->input('warehouse_id');
        
        // Default to current month (from tanggal 1 bulan berjalan)
        $now = now();
        $from = $request->input('from', $now->copy()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', $now->copy()->endOfMonth()->format('Y-m-d'));
        
        // Validasi input
        if (!$itemId || !$warehouseId) {
            return response()->json([
                'error' => 'Item ID dan Warehouse ID harus diisi'
            ], 400);
        }
        
        // Get saldo awal (saldo akhir bulan sebelumnya)
        $saldoAwal = null;
        $lastDayOfPreviousMonth = now()->copy()->subMonth()->endOfMonth();
        
        $saldoQuery = DB::table('food_inventory_cards as c')
            ->join('food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->where('i.id', $itemId)
            ->where('c.warehouse_id', $warehouseId)
            ->whereDate('c.date', '<=', $lastDayOfPreviousMonth->format('Y-m-d'));
        
        $saldoQuery->orderByDesc('c.date')->orderByDesc('c.id');
        $last = $saldoQuery->first();
        
        if ($last) {
            $saldoAwal = [
                'small' => $last->saldo_qty_small ?? 0,
                'medium' => $last->saldo_qty_medium ?? 0,
                'large' => $last->saldo_qty_large ?? 0,
                'small_unit_name' => $last->small_unit_name ?? '',
                'medium_unit_name' => $last->medium_unit_name ?? '',
                'large_unit_name' => $last->large_unit_name ?? '',
            ];
        } else {
            // Jika tidak ada data sebelumnya, saldo awal = 0
            $unitQuery = DB::table('items as i')
                ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
                ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
                ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
                ->where('i.id', $itemId)
                ->select('us.name as small_unit_name', 'um.name as medium_unit_name', 'ul.name as large_unit_name')
                ->first();
            
            $saldoAwal = [
                'small' => 0,
                'medium' => 0,
                'large' => 0,
                'small_unit_name' => $unitQuery->small_unit_name ?? '',
                'medium_unit_name' => $unitQuery->medium_unit_name ?? '',
                'large_unit_name' => $unitQuery->large_unit_name ?? '',
            ];
        }
        
        // Get transactions from current month
        $query = DB::table('food_inventory_cards as c')
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
            ->leftJoin('delivery_orders as do', function($join) {
                $join->on('c.reference_id', '=', 'do.id')
                     ->where('c.reference_type', '=', 'delivery_order');
            })
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->where('i.id', $itemId)
            ->where('c.warehouse_id', $warehouseId)
            ->whereDate('c.date', '>=', $from)
            ->whereDate('c.date', '<=', $to)
            ->select(
                'c.id',
                'c.date',
                'i.id as item_id',
                'i.name as item_name',
                'w.id as warehouse_id',
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
                'do.number as do_number',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            );
        
        $query->orderBy('c.date')->orderBy('c.id');
        $data = $query->get();
        
        // Modifikasi description untuk delivery order
        $data = $data->map(function($row) {
            if ($row->reference_type === 'delivery_order' && $row->do_number) {
                $description = 'Stock Out - Delivery Order';
                if ($row->outlet_name || $row->warehouse_outlet_name) {
                    $description .= ' - DO: ' . $row->do_number;
                    if ($row->outlet_name) {
                        $description .= ', Outlet: ' . $row->outlet_name;
                    }
                    if ($row->warehouse_outlet_name) {
                        $description .= ', Warehouse Outlet: ' . $row->warehouse_outlet_name;
                    }
                }
                $row->description = $description;
            }
            return $row;
        });
        
        // Convert collection to array for JSON response
        return response()->json([
            'cards' => $data->toArray(),
            'saldo_awal' => $saldoAwal,
            'count' => $data->count()
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
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->select(
                'c.date',
                'i.name as item_name',
                'w.name as warehouse_name',
                'c.in_qty_large',
                'c.in_qty_medium',
                'c.in_qty_small',
                'ul.name as large_unit_name',
                'um.name as medium_unit_name',
                'us.name as small_unit_name',
                DB::raw('ROUND((c.in_qty_small / NULLIF(i.small_conversion_qty,0)) + c.in_qty_medium + (c.in_qty_large * IFNULL(i.medium_conversion_qty,1)), 3) as qty_received_kg'),
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
        $items = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('categories.show_pos', '0')
            ->select('items.id', 'items.name')
            ->orderBy('items.name')
            ->get();
        $data = $data->map(function ($row) {
            $parts = [];
            if ($row->in_qty_large > 0) $parts[] = $row->in_qty_large . ' ' . $row->large_unit_name;
            if ($row->in_qty_medium > 0) $parts[] = $row->in_qty_medium . ' ' . $row->medium_unit_name;
            if ($row->in_qty_small > 0) $parts[] = $row->in_qty_small . ' ' . $row->small_unit_name;
            $row->qty_received_display = implode(', ', $parts);
            return $row;
        });
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
                DB::raw('(
                    SELECT new_cost
                    FROM food_inventory_cost_histories
                    WHERE inventory_item_id = s.inventory_item_id AND warehouse_id = s.warehouse_id
                    ORDER BY date DESC, created_at DESC
                    LIMIT 1
                ) as last_cost_small'),
                DB::raw('(
                    SELECT mac
                    FROM food_inventory_cost_histories
                    WHERE inventory_item_id = s.inventory_item_id AND warehouse_id = s.warehouse_id
                    ORDER BY date DESC, created_at DESC
                    LIMIT 1
                ) as mac'),
                's.last_cost_medium',
                's.last_cost_large',
                DB::raw('(
                    s.qty_small * (
                        SELECT mac
                        FROM food_inventory_cost_histories
                        WHERE inventory_item_id = s.inventory_item_id AND warehouse_id = s.warehouse_id
                        ORDER BY date DESC, created_at DESC
                        LIMIT 1
                    )
                ) as total_value')
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
        $items = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('categories.show_pos', '0')
            ->select('items.id', 'items.name')
            ->orderBy('items.name')
            ->get();

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
        $items = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('categories.show_pos', '0')
            ->select('items.id', 'items.name')
            ->orderBy('items.name')
            ->get();
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
            ->join('warehouses as w', 's.warehouse_id', '=', 'w.id')
            ->select(
                'c.name as category_name',
                'w.name as warehouse_name',
                DB::raw('SUM(s.qty_small) as total_qty'),
                DB::raw('SUM(s.qty_small * (
                    SELECT mac
                    FROM food_inventory_cost_histories
                    WHERE inventory_item_id = s.inventory_item_id AND warehouse_id = s.warehouse_id
                    ORDER BY date DESC, created_at DESC
                    LIMIT 1
                )) as total_value')
            )
            ->groupBy('c.name', 'w.name')
            ->orderBy('c.name')
            ->orderBy('w.name')
            ->get();
        $categories = DB::table('categories')->select('id', 'name')->orderBy('name')->get();
        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();
        return inertia('Inventory/CategoryRecapReport', [
            'recaps' => $data,
            'categories' => $categories,
            'warehouses' => $warehouses,
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
        $items = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('categories.show_pos', '0')
            ->select('items.id', 'items.name')
            ->orderBy('items.name')
            ->get();
        return inertia('Inventory/AgingReport', [
            'agings' => $agings,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'items' => $items,
        ]);
    }

    // Laporan Perubahan Harga PO per Item
    public function purchaseOrderPriceChangeReport(Request $request)
    {
        $from = $request->input('from_date');
        $to = $request->input('to_date');
        $query = DB::table('purchase_order_food_items as poi')
            ->join('purchase_order_foods as po', 'poi.purchase_order_food_id', '=', 'po.id')
            ->join('items as i', 'poi.item_id', '=', 'i.id')
            ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->select(
                'poi.item_id',
                'i.name as item_name',
                'poi.price',
                'po.date as po_date',
                's.name as supplier_name',
                'po.number as po_number'
            );
        if ($from) $query->whereDate('po.date', '>=', $from);
        if ($to) $query->whereDate('po.date', '<=', $to);
        $query->orderBy('poi.item_id')->orderByDesc('po.date');
        $rows = $query->get();

        // Group by item, ambil 2 PO terakhir per item
        $grouped = $rows->groupBy('item_id');
        $result = [];
        foreach ($grouped as $item_id => $list) {
            $latest = $list->first();
            $previous = $list->skip(1)->first();
            if ($latest && $previous) {
                $percent = $previous->price != 0 ? round((($latest->price - $previous->price) / $previous->price) * 100, 2) : null;
                $result[] = [
                    'item_name' => $latest->item_name,
                    'prev_price' => $previous->price,
                    'prev_supplier' => $previous->supplier_name,
                    'prev_po_number' => $previous->po_number,
                    'prev_po_date' => $previous->po_date,
                    'latest_price' => $latest->price,
                    'latest_supplier' => $latest->supplier_name,
                    'latest_po_number' => $latest->po_number,
                    'latest_po_date' => $latest->po_date,
                    'percent_change' => $percent,
                ];
            }
        }
        return inertia('Inventory/PurchaseOrderPriceChangeReport', [
            'reports' => $result,
            'from_date' => $from,
            'to_date' => $to,
        ]);
    }
} 