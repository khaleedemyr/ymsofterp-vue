<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ButcherAnalysisReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $warehouse_id = $request->input('warehouse_id');

        // Query data pemotongan
        $query = DB::table('butcher_process_items as bpi')
            ->join('butcher_processes as bp', 'bpi.butcher_process_id', '=', 'bp.id')
            ->join('items as whole_item', 'bpi.whole_item_id', '=', 'whole_item.id')
            ->join('items as pcs_item', 'bpi.pcs_item_id', '=', 'pcs_item.id')
            ->leftJoin('butcher_process_item_details as bpid', 'bpi.id', '=', 'bpid.butcher_process_item_id')
            ->leftJoin('warehouses as w', 'bp.warehouse_id', '=', 'w.id')
            ->select(
                'bp.process_date',
                'w.name as warehouse_name',
                'whole_item.name as whole_item_name',
                'pcs_item.name as pcs_item_name',
                'bpi.whole_qty',
                'bpi.pcs_qty',
                'bpid.qty_kg',
                'bpid.susut_air_qty',
                'bpid.susut_air_unit',
                'bpid.mac_pcs',
                'bpid.costs_0',
                'bpi.id as bpi_id',
                'bp.id as process_id'
            )
            ->when($from, function ($q) use ($from) {
                $q->where('bp.process_date', '>=', $from);
            })
            ->when($to, function ($q) use ($to) {
                $q->where('bp.process_date', '<=', $to);
            })
            ->when($warehouse_id, function ($q) use ($warehouse_id) {
                $q->where('bp.warehouse_id', $warehouse_id);
            })
            ->orderBy('bp.process_date', 'desc');

        $data = $query->get();

        // Group by proses (process_id)
        $grouped = $data->groupBy('process_id');
        $analisis = collect();
        foreach ($grouped as $process_id => $rows) {
            $first = $rows->first();
            $input = $first->whole_qty;
            $susut_air = $first->susut_air_qty ?? 0;
            $susut_air_unit = $first->susut_air_unit;
            $output_kg = $rows->where('costs_0', 0)->sum('qty_kg');
            $output_pcs = $rows->where('costs_0', 0)->sum('pcs_qty');
            $waste_kg = $rows->where('costs_0', 1)->sum('qty_kg');
            $waste = $susut_air + $waste_kg;
            $efisiensi = $input > 0 ? ($output_kg / $input) * 100 : 0;
            foreach ($rows as $row) {
                $analisis->push([
                    'tanggal' => $row->process_date,
                    'warehouse' => $row->warehouse_name,
                    'item_whole' => $row->whole_item_name,
                    'item_pcs' => $row->pcs_item_name,
                    'input_whole' => $input,
                    'output_pcs' => $row->pcs_qty,
                    'output_kg' => $row->qty_kg,
                    'costs_0' => $row->costs_0,
                    'susut_air' => $susut_air,
                    'susut_air_unit' => $susut_air_unit,
                    'waste' => $waste,
                    'efisiensi' => round($efisiensi, 2),
                    'cost_per_unit' => $row->mac_pcs,
                ]);
            }
        }

        // Group analisis per proses butcher (bukan per item hasil)
        $prosesGroups = $analisis->groupBy(function ($row) {
            return implode('|', [
                $row['tanggal'],
                $row['warehouse'],
                $row['item_whole'],
                $row['input_whole'],
                $row['susut_air'],
                $row['susut_air_unit'],
            ]);
        });

        // Rekap tren per hari, input dan susut hanya dihitung sekali per proses
        $tren = $prosesGroups->flatMap(function ($group) {
            // Ambil baris pertama dari group (per proses)
            return [$group->first()];
        })->groupBy('tanggal')->map(function ($rows, $tanggal) {
            $total_input = $rows->sum('input_whole');
            $total_output_kg = $rows->sum('output_kg');
            $total_output_pcs = $rows->sum('output_pcs');
            $total_susut = $rows->sum('susut_air');
            $total_waste = $rows->sum('waste');
            $avg_efisiensi = $total_input > 0 ? ($total_output_kg / $total_input) * 100 : 0;
            return [
                'tanggal' => $tanggal,
                'total_input' => $total_input,
                'total_output_kg' => $total_output_kg,
                'total_output_pcs' => $total_output_pcs,
                'total_susut' => $total_susut,
                'total_waste' => $total_waste,
                'avg_efisiensi' => round($avg_efisiensi, 2),
            ];
        })->values();

        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('ButcherAnalysisReport/Index', [
            'analisis' => $analisis,
            'tren' => $tren,
            'warehouses' => $warehouses,
            'filters' => $request->only(['from', 'to', 'warehouse_id'])
        ]);
    }
} 