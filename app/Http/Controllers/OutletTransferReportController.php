<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\OutletTransfer;

class OutletTransferReportController extends Controller
{
    /**
     * Report Outlet Transfer: list transfer dengan detail item termasuk Qty dan Nilai (MAC).
     * Hanya user dengan id_outlet=1 yang boleh akses.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (($user->id_outlet ?? null) != 1) {
            abort(403, 'Anda tidak memiliki akses ke laporan ini. Hanya user outlet pusat (id_outlet=1) yang dapat mengakses.');
        }

        $query = OutletTransfer::with([
            'warehouseOutletFrom.outlet',
            'warehouseOutletTo.outlet',
            'items.item',
            'items.unit',
        ]);

        // Report hanya untuk id_outlet=1, jadi tidak perlu filter by outlet (lihat semua)

        if ($request->from) {
            $query->whereDate('transfer_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('transfer_date', '<=', $request->to);
        }
        if ($request->outlet_from_id) {
            $query->whereHas('warehouseOutletFrom', fn($q) => $q->where('outlet_id', $request->outlet_from_id));
        }
        if ($request->outlet_to_id) {
            $query->whereHas('warehouseOutletTo', fn($q) => $q->where('outlet_id', $request->outlet_to_id));
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $transfers = $query->orderByDesc('transfer_date')->orderByDesc('id')->get();

        // Ambil kartu stok OUT untuk transfer approved (untuk cost & nilai per item)
        $cardMap = [];
        if ($transfers->isNotEmpty()) {
            $transferIds = $transfers->pluck('id')->toArray();
            $cards = DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'outlet_transfer')
                ->whereIn('reference_id', $transferIds)
                ->where('out_qty_small', '>', 0)
                ->select('reference_id', 'inventory_item_id', 'cost_per_small', 'value_out', 'out_qty_small')
                ->get();

            foreach ($cards as $c) {
                $cardMap[$c->reference_id][$c->inventory_item_id] = [
                    'cost_per_small' => (float) $c->cost_per_small,
                    'value_out' => (float) $c->value_out,
                    'out_qty_small' => (float) $c->out_qty_small,
                ];
            }
        }

        // Mapping item_id -> inventory_item_id
        $itemToInv = DB::table('outlet_food_inventory_items')
            ->select('item_id', 'id as inventory_item_id')
            ->get()
            ->keyBy('item_id');

        $rows = [];
        $grandTotalQty = 0;
        $grandTotalNilai = 0;

        foreach ($transfers as $tr) {
            $outletFromName = $tr->warehouseOutletFrom && $tr->warehouseOutletFrom->outlet
                ? $tr->warehouseOutletFrom->outlet->nama_outlet
                : '-';
            $outletToName = $tr->warehouseOutletTo && $tr->warehouseOutletTo->outlet
                ? $tr->warehouseOutletTo->outlet->nama_outlet
                : '-';
            $whFromName = $tr->warehouseOutletFrom->name ?? '-';
            $whToName = $tr->warehouseOutletTo->name ?? '-';

            $transferTotalNilai = 0;

            foreach ($tr->items as $item) {
                $inv = $itemToInv->get($item->item_id);
                $inventoryItemId = $inv ? $inv->inventory_item_id : null;
                $costPerSmall = null;
                $nilai = null;
                if ($inventoryItemId && isset($cardMap[$tr->id][$inventoryItemId])) {
                    $costPerSmall = $cardMap[$tr->id][$inventoryItemId]['cost_per_small'];
                    $nilai = $cardMap[$tr->id][$inventoryItemId]['value_out'];
                    $transferTotalNilai += $nilai;
                    $grandTotalNilai += $nilai;
                }
                $qty = (float) ($item->quantity ?? 0);

                $rows[] = [
                    'transfer_id' => $tr->id,
                    'transfer_number' => $tr->transfer_number,
                    'transfer_date' => $tr->transfer_date,
                    'status' => $tr->status,
                    'outlet_from_name' => $outletFromName,
                    'warehouse_from_name' => $whFromName,
                    'outlet_to_name' => $outletToName,
                    'warehouse_to_name' => $whToName,
                    'item_name' => $item->item->name ?? '-',
                    'item_sku' => $item->item->sku ?? null,
                    'quantity' => $qty,
                    'unit_name' => $item->unit->name ?? '-',
                    'cost_per_small' => $costPerSmall,
                    'nilai' => $nilai,
                ];
            }

            // Jika transfer punya items tapi tidak ada satu pun yang punya nilai (misal belum approved), tetap tampilkan baris
            if ($tr->items->isEmpty()) {
                $rows[] = [
                    'transfer_id' => $tr->id,
                    'transfer_number' => $tr->transfer_number,
                    'transfer_date' => $tr->transfer_date,
                    'status' => $tr->status,
                    'outlet_from_name' => $outletFromName,
                    'warehouse_from_name' => $whFromName,
                    'outlet_to_name' => $outletToName,
                    'warehouse_to_name' => $whToName,
                    'item_name' => '-',
                    'item_sku' => null,
                    'quantity' => 0,
                    'unit_name' => '-',
                    'cost_per_small' => null,
                    'nilai' => null,
                ];
            }
        }

        $outlets = \App\Models\Outlet::where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('OutletTransfer/Report', [
            'rows' => $rows,
            'filters' => $request->only(['from', 'to', 'outlet_from_id', 'outlet_to_id', 'status']),
            'outlets' => $outlets,
            'grandTotalNilai' => round($grandTotalNilai, 2),
        ]);
    }
}
