<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ButcherReportController extends Controller
{
    public function index(Request $request)
    {
        // Log::info('ButcherReportController@index called', $request->all());
        $query = DB::table('butcher_processes as bp')
            ->join('warehouses as w', 'bp.warehouse_id', '=', 'w.id')
            ->leftJoin('food_good_receives as gr', 'bp.good_receive_id', '=', 'gr.id')
            ->leftJoin('users as u', 'bp.created_by', '=', 'u.id')
            ->leftJoin('butcher_process_items as bpi', 'bp.id', '=', 'bpi.butcher_process_id')
            ->leftJoin('items as whole_item', 'bpi.whole_item_id', '=', 'whole_item.id')
            ->leftJoin('items as pcs_item', 'bpi.pcs_item_id', '=', 'pcs_item.id')
            ->leftJoin('units as unit', 'bpi.unit_id', '=', 'unit.id')
            ->leftJoin('butcher_process_item_details as bpid', 'bpi.id', '=', 'bpid.butcher_process_item_id')
            ->select(
                'bp.id',
                'bp.number',
                'bp.process_date',
                'w.name as warehouse_name',
                'gr.gr_number',
                'u.nama_lengkap as created_by',
                'whole_item.name as whole_item_name',
                'pcs_item.name as pcs_item_name',
                'bpi.whole_qty',
                'bpi.pcs_qty',
                'unit.name as unit_name',
                'bpid.slaughter_date',
                'bpid.packing_date',
                'bpid.batch_est',
                'bpid.qty_purchase',
                'bpid.susut_air_qty',
                'bpid.susut_air_unit',
                'bpid.mac_pcs',
                'pcs_item.exp as pcs_item_exp'
            )
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('bp.number', 'like', "%{$search}%")
                        ->orWhere('gr.gr_number', 'like', "%{$search}%")
                        ->orWhere('w.name', 'like', "%{$search}%")
                        ->orWhere('whole_item.name', 'like', "%{$search}%")
                        ->orWhere('pcs_item.name', 'like', "%{$search}%");
                });
            })
            ->when($request->from, function ($query, $from) {
                $query->where('bp.process_date', '>=', $from);
            })
            ->when($request->to, function ($query, $to) {
                $query->where('bp.process_date', '<=', $to);
            })
            ->when($request->warehouse_id, function ($query, $warehouse_id) {
                $query->where('bp.warehouse_id', $warehouse_id);
            })
            ->orderBy('bp.process_date', 'desc')
            ->orderBy('bp.number', 'desc');

        $data = $query->get();

        // Transform data untuk grouping
        $transformedData = $data->groupBy('id')->map(function ($group) {
            $first = $group->first();
            return [
                'id' => $first->id,
                'number' => $first->number,
                'process_date' => $first->process_date,
                'warehouse_name' => $first->warehouse_name,
                'gr_number' => $first->gr_number,
                'created_by' => $first->created_by,
                'items' => $group->map(function ($item) {
                    $slaughter = $item->slaughter_date;
                    $exp_days = $item->pcs_item_exp ?? 0;
                    $exp_date = $slaughter ? Carbon::parse($slaughter)->addDays($exp_days)->toDateString() : null;
                    return [
                        'whole_item_name' => $item->whole_item_name,
                        'pcs_item_name' => $item->pcs_item_name,
                        'whole_qty' => $item->whole_qty,
                        'pcs_qty' => $item->pcs_qty,
                        'unit_name' => $item->unit_name,
                        'slaughter_date' => $item->slaughter_date,
                        'packing_date' => $item->packing_date,
                        'batch_est' => $item->batch_est,
                        'qty_purchase' => $item->qty_purchase,
                        'susut_air' => [
                            'qty' => $item->susut_air_qty,
                            'unit' => $item->susut_air_unit
                        ],
                        'mac_pcs' => $item->mac_pcs,
                        'exp_date' => $exp_date
                    ];
                })->values()
            ];
        })->values();

        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('ButcherProcess/Report', [
            'processes' => $transformedData,
            'warehouses' => $warehouses,
            'filters' => $request->only(['search', 'from', 'to', 'warehouse_id'])
        ]);
    }

    public function summaryReport(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $query = \DB::table('butcher_processes as bp')
            ->join('butcher_process_items as bpi', 'bpi.butcher_process_id', '=', 'bp.id')
            ->join('items as i', 'bpi.pcs_item_id', '=', 'i.id')
            ->join('units as u', 'bpi.unit_id', '=', 'u.id')
            ->leftJoin('butcher_process_item_details as bpid', 'bpid.butcher_process_item_id', '=', 'bpi.id')
            ->select(
                'bp.process_date',
                'i.name as item_name',
                \DB::raw('SUM(bpi.pcs_qty) as total_pcs_qty'),
                \DB::raw('SUM(bpid.qty_kg) as total_qty_kg'),
                'u.name as unit_name'
            );
        if ($from) $query->whereDate('bp.process_date', '>=', $from);
        if ($to) $query->whereDate('bp.process_date', '<=', $to);
        $data = $query->groupBy('bp.process_date', 'i.name', 'u.name')
            ->orderBy('bp.process_date', 'desc')
            ->get();
        return inertia('ButcherReport/Summary', [
            'data' => $data,
            'filters' => [
                'from' => $from,
                'to' => $to
            ]
        ]);
    }
} 