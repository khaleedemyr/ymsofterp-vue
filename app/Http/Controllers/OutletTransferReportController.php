<?php

namespace App\Http\Controllers;

use App\Exports\OutletTransferReportExport;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class OutletTransferReportController extends Controller
{
    /**
     * Report Outlet Transfer: list transfer dengan detail item termasuk Qty dan Nilai (MAC).
     * Hanya user dengan id_outlet=1 yang boleh akses.
     */
    public function index(Request $request)
    {
        $this->authorizeReportAccess();

        $data = $this->buildReportData($request);
        $outlets = Outlet::where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('OutletTransfer/Report', [
            'rows' => $data['rows'],
            'filters' => $request->only(['from', 'to', 'outlet_from_id', 'outlet_to_id', 'status']),
            'outlets' => $outlets,
            'grandTotalNilai' => $data['grand_total_nilai'],
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeReportAccess();

        $data = $this->buildReportData($request);
        if (empty($data['rows'])) {
            return back()->with('error', 'Tidak ada data untuk diekspor.');
        }

        $statusMap = [
            'draft' => 'Draft',
            'submitted' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];

        $exportRows = [];
        $no = 1;
        foreach ($data['rows'] as $row) {
            $exportRows[] = [
                $no++,
                $row['transfer_number'],
                $row['transfer_date'] ? date('d/m/Y', strtotime($row['transfer_date'])) : '-',
                $row['outlet_from_name'],
                $row['warehouse_from_name'],
                $row['outlet_to_name'],
                $row['warehouse_to_name'],
                $row['item_name'].($row['item_sku'] ? ' ('.$row['item_sku'].')' : ''),
                $row['quantity'],
                $row['unit_name'],
                $row['cost_per_small'],
                $row['nilai'],
                $statusMap[$row['status']] ?? $row['status'],
            ];
        }

        $filename = 'laporan-pindah-outlet-'.now()->format('YmdHis').'.xlsx';

        return Excel::download(
            new OutletTransferReportExport($exportRows, $data['grand_total_nilai']),
            $filename
        );
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeReportAccess();

        $data = $this->buildReportData($request);
        if (empty($data['rows'])) {
            return back()->with('error', 'Tidak ada data untuk diekspor.');
        }

        $outletFromName = null;
        $outletToName = null;
        if ($request->outlet_from_id) {
            $outletFromName = Outlet::where('id_outlet', $request->outlet_from_id)->value('nama_outlet');
        }
        if ($request->outlet_to_id) {
            $outletToName = Outlet::where('id_outlet', $request->outlet_to_id)->value('nama_outlet');
        }

        $statusMap = [
            'draft' => 'Draft',
            'submitted' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];

        $pdf = Pdf::loadView('exports.outlet_transfer_report_pdf', [
            'rows' => $data['rows'],
            'grandTotalNilai' => $data['grand_total_nilai'],
            'filters' => [
                'from' => $request->from,
                'to' => $request->to,
                'outlet_from_name' => $outletFromName,
                'outlet_to_name' => $outletToName,
                'status' => $request->status ? ($statusMap[$request->status] ?? $request->status) : null,
            ],
            'statusMap' => $statusMap,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-pindah-outlet-'.now()->format('YmdHis').'.pdf';

        return $pdf->download($filename);
    }

    private function authorizeReportAccess(): void
    {
        $user = auth()->user();
        if (($user->id_outlet ?? null) != 1) {
            abort(403, 'Anda tidak memiliki akses ke laporan ini. Hanya user outlet pusat (id_outlet=1) yang dapat mengakses.');
        }
    }

    /**
     * @return array{rows: array<int, array>, grand_total_nilai: float}
     */
    private function buildReportData(Request $request): array
    {
        $query = OutletTransfer::with([
            'warehouseOutletFrom.outlet',
            'warehouseOutletTo.outlet',
            'items.item',
            'items.unit',
        ]);

        if ($request->from) {
            $query->whereDate('transfer_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('transfer_date', '<=', $request->to);
        }
        if ($request->outlet_from_id) {
            $query->whereHas('warehouseOutletFrom', fn ($q) => $q->where('outlet_id', $request->outlet_from_id));
        }
        if ($request->outlet_to_id) {
            $query->whereHas('warehouseOutletTo', fn ($q) => $q->where('outlet_id', $request->outlet_to_id));
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $transfers = $query->orderByDesc('transfer_date')->orderByDesc('id')->get();

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

        $itemToInv = DB::table('outlet_food_inventory_items')
            ->select('item_id', 'id as inventory_item_id')
            ->get()
            ->keyBy('item_id');

        $rows = [];
        $grandTotalNilai = 0.0;

        foreach ($transfers as $tr) {
            $outletFromName = $tr->warehouseOutletFrom && $tr->warehouseOutletFrom->outlet
                ? $tr->warehouseOutletFrom->outlet->nama_outlet
                : '-';
            $outletToName = $tr->warehouseOutletTo && $tr->warehouseOutletTo->outlet
                ? $tr->warehouseOutletTo->outlet->nama_outlet
                : '-';
            $whFromName = $tr->warehouseOutletFrom->name ?? '-';
            $whToName = $tr->warehouseOutletTo->name ?? '-';

            foreach ($tr->items as $item) {
                $inv = $itemToInv->get($item->item_id);
                $inventoryItemId = $inv ? $inv->inventory_item_id : null;
                $costPerSmall = null;
                $nilai = null;
                if ($inventoryItemId && isset($cardMap[$tr->id][$inventoryItemId])) {
                    $costPerSmall = $cardMap[$tr->id][$inventoryItemId]['cost_per_small'];
                    $nilai = $cardMap[$tr->id][$inventoryItemId]['value_out'];
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

        return [
            'rows' => $rows,
            'grand_total_nilai' => round($grandTotalNilai, 2),
        ];
    }
}
