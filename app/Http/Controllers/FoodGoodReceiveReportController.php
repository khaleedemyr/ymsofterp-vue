<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class FoodGoodReceiveReportController extends Controller
{
    public function index(Request $request)
    {
        // Get main GR data
        $grQuery = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'gr.notes',
                'po.number as po_number',
                'po.date as po_date',
                's.name as supplier_name',
                's.code as supplier_code',
                'u.nama_lengkap as received_by_name',
                'gr.created_at',
                DB::raw('(SELECT COUNT(*) FROM food_good_receive_items WHERE good_receive_id = gr.id) as total_items')
            );

        // Filter berdasarkan tanggal
        if ($request->filled('from_date')) {
            $grQuery->whereDate('gr.receive_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $grQuery->whereDate('gr.receive_date', '<=', $request->to_date);
        }

        // Filter berdasarkan supplier
        if ($request->filled('supplier_id')) {
            $grQuery->where('gr.supplier_id', $request->supplier_id);
        }

        // Filter berdasarkan status
        if ($request->filled('status')) {
            $grQuery->where('gr.status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $grQuery->where(function($q) use ($search) {
                $q->where('gr.gr_number', 'like', "%$search%")
                  ->orWhere('po.number', 'like', "%$search%")
                  ->orWhere('s.name', 'like', "%$search%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $grResults = $grQuery->orderBy('gr.receive_date', 'desc')
                            ->orderBy('gr.gr_number', 'desc')
                            ->paginate($perPage)
                            ->withQueryString();

        // Get items for each GR (will be loaded on demand)
        $grIds = $grResults->pluck('id')->toArray();
        
        $itemsData = [];
        if (!empty($grIds)) {
            $itemsQuery = DB::table('food_good_receive_items as gri')
                ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
                ->leftJoin('units as u_item', 'gri.unit_id', '=', 'u_item.id')
                ->select(
                    'gri.good_receive_id',
                    'gri.id as item_id',
                    'i.name as item_name',
                    'i.sku as item_sku',
                    'gri.qty_ordered',
                    'gri.qty_received',
                    DB::raw('(gri.qty_received - gri.used_qty) as remaining_qty'),
                    'u_item.name as unit_name'
                )
                ->whereIn('gri.good_receive_id', $grIds);

            // Filter berdasarkan item jika ada
            if ($request->filled('item_id')) {
                $itemsQuery->where('gri.item_id', $request->item_id);
            }

            $items = $itemsQuery->get();
            
            // Group items by good_receive_id
            foreach ($items as $item) {
                $grId = $item->good_receive_id;
                if (!isset($itemsData[$grId])) {
                    $itemsData[$grId] = [];
                }
                $itemsData[$grId][] = $item;
            }
        }

        // Get summary data
        $summary = DB::table('food_good_receives as gr')
            ->leftJoin('food_good_receive_items as gri', 'gr.id', '=', 'gri.good_receive_id')
            ->when($request->filled('from_date'), function($q) use ($request) {
                $q->whereDate('gr.receive_date', '>=', $request->from_date);
            })
            ->when($request->filled('to_date'), function($q) use ($request) {
                $q->whereDate('gr.receive_date', '<=', $request->to_date);
            })
            ->when($request->filled('supplier_id'), function($q) use ($request) {
                $q->where('gr.supplier_id', $request->supplier_id);
            })
            ->when($request->filled('item_id'), function($q) use ($request) {
                $q->where('gri.item_id', $request->item_id);
            })
            ->when($request->filled('status'), function($q) use ($request) {
                $q->where('gr.status', $request->status);
            })
            ->when($request->filled('search'), function($q) use ($request) {
                $search = $request->search;
                $q->where(function($subQ) use ($search) {
                    $subQ->where('gr.gr_number', 'like', "%$search%")
                         ->orWhere('po.number', 'like', "%$search%")
                         ->orWhere('s.name', 'like', "%$search%");
                });
            })
            ->select(
                DB::raw('COUNT(DISTINCT gr.id) as total_gr'),
                DB::raw('SUM(gri.qty_received) as total_qty_received')
            )
            ->first();

        // Get filter options
        $suppliers = DB::table('suppliers')
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        $items = DB::table('items')
            ->select('id', 'name', 'sku')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return Inertia::render('FoodGoodReceive/Report', [
            'results' => $grResults,
            'itemsData' => $itemsData,
            'summary' => $summary,
            'suppliers' => $suppliers,
            'items' => $items,
            'filters' => $request->only(['from_date', 'to_date', 'supplier_id', 'item_id', 'status', 'search', 'per_page']),
        ]);
    }

    public function export(Request $request)
    {
        // Get all GR data for export
        $grQuery = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'gr.notes',
                'po.number as po_number',
                'po.date as po_date',
                's.name as supplier_name',
                's.code as supplier_code',
                'u.nama_lengkap as received_by_name'
            );

        // Apply filters
        if ($request->filled('from_date')) {
            $grQuery->whereDate('gr.receive_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $grQuery->whereDate('gr.receive_date', '<=', $request->to_date);
        }
        if ($request->filled('supplier_id')) {
            $grQuery->where('gr.supplier_id', $request->supplier_id);
        }
        if ($request->filled('status')) {
            $grQuery->where('gr.status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $grQuery->where(function($q) use ($search) {
                $q->where('gr.gr_number', 'like', "%$search%")
                  ->orWhere('po.number', 'like', "%$search%")
                  ->orWhere('s.name', 'like', "%$search%");
            });
        }

        $grResults = $grQuery->orderBy('gr.receive_date', 'desc')
                            ->orderBy('gr.gr_number', 'desc')
                            ->get();

        // Get items for export
        $grIds = $grResults->pluck('id')->toArray();
        $itemsQuery = DB::table('food_good_receive_items as gri')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u_item', 'gri.unit_id', '=', 'u_item.id')
            ->select(
                'gri.good_receive_id',
                'i.name as item_name',
                'i.sku as item_sku',
                'gri.qty_ordered',
                'gri.qty_received',
                DB::raw('(gri.qty_received - gri.used_qty) as remaining_qty'),
                'u_item.name as unit_name'
            )
            ->whereIn('gri.good_receive_id', $grIds);

        if ($request->filled('item_id')) {
            $itemsQuery->where('gri.item_id', $request->item_id);
        }

        $items = $itemsQuery->get();

        // Combine GR and items data for export
        $exportData = [];
        foreach ($grResults as $gr) {
            $grItems = $items->where('good_receive_id', $gr->id);
            
            if ($grItems->count() > 0) {
                foreach ($grItems as $item) {
                    $exportData[] = [
                        'gr_number' => $gr->gr_number,
                        'receive_date' => $gr->receive_date,
                        'po_number' => $gr->po_number,
                        'po_date' => $gr->po_date,
                        'supplier_name' => $gr->supplier_name,
                        'supplier_code' => $gr->supplier_code,
                        'received_by_name' => $gr->received_by_name,
                        'item_name' => $item->item_name,
                        'item_sku' => $item->item_sku,
                        'qty_ordered' => $item->qty_ordered,
                        'qty_received' => $item->qty_received,
                        'remaining_qty' => $item->remaining_qty,
                        'unit_name' => $item->unit_name,
                        'notes' => $gr->notes
                    ];
                }
            } else {
                // Add GR without items
                $exportData[] = [
                    'gr_number' => $gr->gr_number,
                    'receive_date' => $gr->receive_date,
                    'po_number' => $gr->po_number,
                    'po_date' => $gr->po_date,
                    'supplier_name' => $gr->supplier_name,
                    'supplier_code' => $gr->supplier_code,
                    'received_by_name' => $gr->received_by_name,
                    'item_name' => '',
                    'item_sku' => '',
                    'qty_ordered' => 0,
                    'qty_received' => 0,
                    'remaining_qty' => 0,
                    'unit_name' => '',
                    'notes' => $gr->notes
                ];
            }
        }

        // Return Excel export using the Responsable interface
        return (new \App\Exports\FoodGoodReceiveReportExport(collect($exportData)))->toResponse($request);
    }
}
