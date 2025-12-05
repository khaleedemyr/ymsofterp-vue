<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockCostReportController extends Controller
{
    public function index(Request $request)
    {
        // Filter
        $from = $request->input('from');
        $to = $request->input('to');
        $warehouse_id = $request->input('warehouse_id');

        // Query perubahan stok whole (pengurangan) dan PCS (penambahan)
        $query = DB::table('butcher_process_items as bpi')
            ->join('butcher_processes as bp', 'bpi.butcher_process_id', '=', 'bp.id')
            ->join('items as whole_item', 'bpi.whole_item_id', '=', 'whole_item.id')
            ->join('items as pcs_item', 'bpi.pcs_item_id', '=', 'pcs_item.id')
            ->join('units as unit', 'bpi.unit_id', '=', 'unit.id')
            ->leftJoin('units as u_small', 'whole_item.small_unit_id', '=', 'u_small.id')
            ->leftJoin('units as u_medium', 'whole_item.medium_unit_id', '=', 'u_medium.id')
            ->leftJoin('units as u_large', 'whole_item.large_unit_id', '=', 'u_large.id')
            ->leftJoin('warehouses as w', 'bp.warehouse_id', '=', 'w.id')
            ->leftJoin('butcher_process_item_details as bpid', 'bpi.id', '=', 'bpid.butcher_process_item_id')
            ->select(
                'bp.process_date',
                'w.id as warehouse_id',
                'w.name as warehouse_name',
                'whole_item.id as whole_item_id',
                'whole_item.name as whole_item_name',
                'pcs_item.id as pcs_item_id',
                'pcs_item.name as pcs_item_name',
                'unit.name as unit_name',
                'u_small.name as small_unit_name',
                'u_medium.name as medium_unit_name',
                'u_large.name as large_unit_name',
                'bpi.whole_qty', // pengurangan
                'bpi.pcs_qty',    // penambahan
                'bpid.mac_pcs'
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

        // Dummy: Cost per unit, MAC, saldo, nilai stok (implementasi detail bisa disesuaikan dengan struktur cost/history di sistem Anda)
        $report = $data->map(function ($row) {
            // Ambil saldo dari food_inventory_stocks untuk item PCS dan warehouse
            $saldoRow = DB::table('food_inventory_stocks')
                ->join('food_inventory_items', 'food_inventory_stocks.inventory_item_id', '=', 'food_inventory_items.id')
                ->where('food_inventory_items.item_id', $row->pcs_item_id)
                ->where('food_inventory_stocks.warehouse_id', $row->warehouse_id)
                ->select('qty_small')
                ->first();
            $saldo = $saldoRow->qty_small ?? 0;
            $mac = $row->mac_pcs ?? 0;
            $nilai_stok = $mac * $saldo;
            $unit_whole = $row->large_unit_name ?: ($row->medium_unit_name ?: $row->small_unit_name);
            return [
                'tanggal' => $row->process_date,
                'warehouse' => $row->warehouse_name,
                'item_whole' => $row->whole_item_name,
                'item_pcs' => $row->pcs_item_name,
                'unit' => $row->unit_name,
                'unit_whole' => $unit_whole,
                'pengurangan_whole' => $row->whole_qty,
                'penambahan_pcs' => $row->pcs_qty,
                'mac' => $mac,
                'saldo' => $saldo,
                'nilai_stok' => $nilai_stok,
            ];
        });

        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('StockCostReport/Index', [
            'report' => $report,
            'warehouses' => $warehouses,
            'filters' => $request->only(['from', 'to', 'warehouse_id'])
        ]);
    }
} 