<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseOrderReportController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('food_good_receives as gr')
            ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->join('food_good_receive_items as gri', 'gr.id', '=', 'gri.good_receive_id')
            ->join('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
            ->join('items as i', 'gri.item_id', '=', 'i.id')
            ->join('units as u', 'gri.unit_id', '=', 'u.id')
            ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('users as received_by', 'gr.received_by', '=', 'received_by.id')
            ->leftJoin('users as po_creator', 'po.created_by', '=', 'po_creator.id')
            ->select(
                'gr.id as gr_id',
                'gr.gr_number',
                'gr.receive_date',
                'po.id as po_id',
                'po.number as po_number',
                'po.date as po_date',
                'po.supplier_id',
                's.name as supplier_name',
                'i.id as item_id',
                'i.name as item_name',
                'gri.qty_received',
                'poi.quantity as po_qty',
                'u.name as unit_name',
                'poi.price as po_price',
                'received_by.nama_lengkap as received_by_name',
                'po_creator.nama_lengkap as po_creator_name'
            );

        // Filter by date range
        if ($request->from) {
            $query->whereDate('gr.receive_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('gr.receive_date', '<=', $request->to);
        }

        // Filter by supplier
        if ($request->supplier_id && $request->supplier_id !== '') {
            $query->where('po.supplier_id', $request->supplier_id);
        }

        // Filter by item
        if ($request->item_id && $request->item_id !== '') {
            $query->where('gri.item_id', $request->item_id);
        }

        // Search
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('po.number', 'like', "%$search%")
                  ->orWhere('gr.gr_number', 'like', "%$search%")
                  ->orWhere('s.name', 'like', "%$search%")
                  ->orWhere('i.name', 'like', "%$search%");
            });
        }

        $results = $query->orderBy('gr.receive_date', 'desc')
                        ->paginate($request->perPage ?? 15)
                        ->withQueryString();

        // Get previous PO prices for comparison
        $results->getCollection()->transform(function ($item) {
            // Get previous PO price for the same item and supplier
            $previousPrice = DB::table('purchase_order_food_items as poi2')
                ->join('purchase_order_foods as po2', 'poi2.purchase_order_food_id', '=', 'po2.id')
                ->where('poi2.item_id', $item->item_id)
                ->where('po2.supplier_id', $item->supplier_id)
                ->where('po2.date', '<', $item->po_date)
                ->orderBy('po2.date', 'desc')
                ->value('poi2.price');

            $item->previous_price = $previousPrice;
            $item->price_change = $previousPrice ? $item->po_price - $previousPrice : 0;
            $item->price_change_percentage = $previousPrice ? round((($item->po_price - $previousPrice) / $previousPrice) * 100, 2) : 0;

            return $item;
        });

        // Get suppliers for filter
        $suppliers = DB::table('suppliers')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Get items for filter
        $items = DB::table('items')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('PurchaseOrder/Report', [
            'reports' => $results,
            'suppliers' => $suppliers,
            'items' => $items,
            'filters' => $request->only(['search', 'from', 'to', 'supplier_id', 'item_id', 'perPage']) ?: [],
        ]);
    }

    public function export(Request $request)
    {
        $query = DB::table('food_good_receives as gr')
            ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->join('food_good_receive_items as gri', 'gr.id', '=', 'gri.good_receive_id')
            ->join('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
            ->join('items as i', 'gri.item_id', '=', 'i.id')
            ->join('units as u', 'gri.unit_id', '=', 'u.id')
            ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('users as received_by', 'gr.received_by', '=', 'received_by.id')
            ->leftJoin('users as po_creator', 'po.created_by', '=', 'po_creator.id')
            ->select(
                'gr.gr_number',
                'gr.receive_date',
                'po.number as po_number',
                'po.date as po_date',
                'po.supplier_id',
                's.name as supplier_name',
                'i.name as item_name',
                'i.id as item_id',
                'gri.qty_received',
                'poi.quantity as po_qty',
                'u.name as unit_name',
                'poi.price as po_price',
                'received_by.nama_lengkap as received_by_name',
                'po_creator.nama_lengkap as po_creator_name'
            );

        // Apply same filters as index
        if ($request->from) {
            $query->whereDate('gr.receive_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('gr.receive_date', '<=', $request->to);
        }
        if ($request->supplier_id && $request->supplier_id !== '') {
            $query->where('po.supplier_id', $request->supplier_id);
        }
        if ($request->item_id && $request->item_id !== '') {
            $query->where('gri.item_id', $request->item_id);
        }
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('po.number', 'like', "%$search%")
                  ->orWhere('gr.gr_number', 'like', "%$search%")
                  ->orWhere('s.name', 'like', "%$search%")
                  ->orWhere('i.name', 'like', "%$search%");
            });
        }

        $results = $query->orderBy('gr.receive_date', 'desc')->get();

        // Add previous price information
        $results->transform(function ($item) {
            $previousPrice = DB::table('purchase_order_food_items as poi2')
                ->join('purchase_order_foods as po2', 'poi2.purchase_order_food_id', '=', 'po2.id')
                ->where('poi2.item_id', $item->item_id)
                ->where('po2.supplier_id', $item->supplier_id)
                ->where('po2.date', '<', $item->po_date)
                ->orderBy('po2.date', 'desc')
                ->value('poi2.price');

            $item->previous_price = $previousPrice;
            $item->price_change = $previousPrice ? $item->po_price - $previousPrice : 0;
            $item->price_change_percentage = $previousPrice ? round((($item->po_price - $previousPrice) / $previousPrice) * 100, 2) : 0;

            return $item;
        });

        // Generate CSV
        $filename = 'po_gr_report_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($results) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($file, [
                'GR Number',
                'Receive Date',
                'PO Number',
                'PO Date',
                'Supplier',
                'Item',
                'Qty PO',
                'Qty Received',
                'Unit',
                'PO Price',
                'Previous Price',
                'Price Change',
                'Price Change %',
                'PO Creator',
                'Received By'
            ]);

            // Data
            foreach ($results as $row) {
                fputcsv($file, [
                    $row->gr_number,
                    $row->receive_date,
                    $row->po_number,
                    $row->po_date,
                    $row->supplier_name,
                    $row->item_name,
                    $row->po_qty,
                    $row->qty_received,
                    $row->unit_name,
                    number_format($row->po_price, 2),
                    $row->previous_price ? number_format($row->previous_price, 2) : '-',
                    $row->price_change ? number_format($row->price_change, 2) : '-',
                    $row->price_change_percentage ? $row->price_change_percentage . '%' : '-',
                    $row->po_creator_name,
                    $row->received_by_name
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
