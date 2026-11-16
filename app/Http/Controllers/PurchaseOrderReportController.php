<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PoGrReportExport;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $results = $this->buildCombinedQuery($request, $from, $to)
            ->paginate($request->perPage ?? 15)
            ->withQueryString();

        $results->setCollection($this->enrichRows($results->getCollection()));
        $results->setCollection($this->applyPriceChangeFilters($results->getCollection(), $request));

        $suppliers = DB::table('suppliers')->select('id', 'name')->orderBy('name')->get();
        $items = DB::table('items')->select('id', 'name')->orderBy('name')->get();

        $filters = $request->only([
            'search', 'from', 'to', 'supplier_id', 'item_id', 'perPage',
            'hasPriceChange', 'priceIncrease', 'priceDecrease',
            'include_ungr', 'payment_status',
        ]) ?: [];
        if (! isset($filters['from']) && ! isset($filters['to'])) {
            $filters['from'] = $from;
            $filters['to'] = $to;
        }
        if (! array_key_exists('include_ungr', $filters) || $filters['include_ungr'] === null || $filters['include_ungr'] === '') {
            $filters['include_ungr'] = 'true';
        }

        return Inertia::render('PurchaseOrder/Report', [
            'reports' => $results,
            'suppliers' => $suppliers,
            'items' => $items,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request)
    {
        [$from, $to, $results] = $this->fetchExportRows($request);

        $exportData = [];
        foreach ($results as $row) {
            $exportData[] = [
                $row->row_type === 'ungr' ? 'Belum GR' : ($row->gr_number ?? '-'),
                $row->receive_date ?? '-',
                $row->po_number ?? '-',
                $row->po_date ?? '-',
                $row->supplier_name ?? '-',
                $row->item_name ?? '-',
                $row->category_name ?? '-',
                $row->po_qty ?? 0,
                $row->qty_received ?? 0,
                $row->unit_name ?? '-',
                $row->po_price ?? 0,
                $row->previous_price ?? 0,
                $row->price_change ?? 0,
                $row->price_change_percentage ? $row->price_change_percentage.'%' : '-',
                $row->payment_status_label ?? '-',
                $row->payment_number ?? '-',
                $row->contra_bon_number ?? '-',
                $row->po_creator_name ?? '-',
                $row->received_by_name ?? '-',
            ];
        }

        $filename = 'po_gr_report_'.date('Y-m-d_H-i-s').'.xlsx';

        return Excel::download(
            new PoGrReportExport($exportData, $from, $to),
            $filename
        );
    }

    public function exportPdf(Request $request)
    {
        [$from, $to, $results] = $this->fetchExportRows($request);

        if ($results->isEmpty()) {
            return back()->with('error', 'Tidak ada data untuk diekspor. Silakan Load Data terlebih dahulu.');
        }

        $supplierName = null;
        if ($request->supplier_id && $request->supplier_id !== '') {
            $supplierName = DB::table('suppliers')->where('id', $request->supplier_id)->value('name');
        }

        $itemName = null;
        if ($request->item_id && $request->item_id !== '') {
            $itemName = DB::table('items')->where('id', $request->item_id)->value('name');
        }

        $summary = [
            'total_items' => $results->count(),
            'naik_harga' => $results->where('price_change', '>', 0)->count(),
            'turun_harga' => $results->where('price_change', '<', 0)->count(),
            'harga_stabil' => $results->filter(fn ($row) => (float) ($row->price_change ?? 0) == 0.0)->count(),
            'belum_gr' => $results->where('row_type', 'ungr')->count(),
            'sudah_bayar' => $results->where('payment_status', 'paid')->count(),
        ];

        $pdf = Pdf::loadView('exports.po_gr_report_pdf', [
            'rows' => $results,
            'summary' => $summary,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'search' => $request->search ?: null,
                'supplier_name' => $supplierName,
                'item_name' => $itemName,
                'has_price_change' => $request->hasPriceChange == 'true',
                'price_increase' => $request->priceIncrease == 'true',
                'price_decrease' => $request->priceDecrease == 'true',
                'include_ungr' => $this->shouldIncludeUngr($request),
                'payment_status' => $request->payment_status ?: null,
            ],
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'po_gr_report_'.date('Y-m-d_H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: \Illuminate\Support\Collection}
     */
    private function fetchExportRows(Request $request): array
    {
        [$from, $to] = $this->resolveDateRange($request);

        $results = $this->buildCombinedQuery($request, $from, $to)
            ->get();

        $results = $this->enrichRows($results);
        $results = $this->applyPriceChangeFilters($results, $request);

        return [$from, $to, $results];
    }

    private function resolveDateRange(Request $request): array
    {
        $from = $request->from;
        $to = $request->to;

        if (! $from && ! $to) {
            $from = now()->startOfMonth()->format('Y-m-d');
            $to = now()->format('Y-m-d');
        }

        return [$from, $to];
    }

    private function shouldIncludeUngr(Request $request): bool
    {
        // Default ON supaya PO belum GR ikut muncul.
        if (! $request->has('include_ungr') || $request->include_ungr === null || $request->include_ungr === '') {
            return true;
        }

        return in_array((string) $request->include_ungr, ['1', 'true', 'yes', 'on'], true);
    }

    private function buildCombinedQuery(Request $request, ?string $from, ?string $to)
    {
        $grQuery = $this->baseGrQuery($request, $from, $to);

        if (! $this->shouldIncludeUngr($request)) {
            return DB::query()
                ->fromSub($grQuery, 'report')
                ->orderByRaw('COALESCE(sort_date, po_date) DESC')
                ->orderByDesc('po_number')
                ->orderBy('item_name');
        }

        $ungrQuery = $this->baseUngrQuery($request, $from, $to);
        $union = $grQuery->unionAll($ungrQuery);

        return DB::query()
            ->fromSub($union, 'report')
            ->orderByRaw('COALESCE(sort_date, po_date) DESC')
            ->orderByDesc('po_number')
            ->orderBy('item_name');
    }

    private function baseGrQuery(Request $request, ?string $from, ?string $to)
    {
        $query = DB::table('food_good_receives as gr')
            ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->join('food_good_receive_items as gri', 'gr.id', '=', 'gri.good_receive_id')
            ->join('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
            ->join('items as i', 'gri.item_id', '=', 'i.id')
            ->join('units as u', 'gri.unit_id', '=', 'u.id')
            ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('users as received_by', 'gr.received_by', '=', 'received_by.id')
            ->leftJoin('users as po_creator', 'po.created_by', '=', 'po_creator.id')
            ->select([
                DB::raw("'received' as row_type"),
                'gr.id as gr_id',
                'gr.gr_number',
                'gr.receive_date',
                'gr.receive_date as sort_date',
                'po.id as po_id',
                'po.number as po_number',
                'po.date as po_date',
                'po.supplier_id',
                's.name as supplier_name',
                'i.id as item_id',
                'i.name as item_name',
                'c.name as category_name',
                'gri.qty_received',
                'poi.quantity as po_qty',
                'u.name as unit_name',
                'poi.price as po_price',
                'received_by.nama_lengkap as received_by_name',
                'po_creator.nama_lengkap as po_creator_name',
            ]);

        if ($from) {
            $query->whereDate('gr.receive_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('gr.receive_date', '<=', $to);
        }
        $this->applyCommonFilters($query, $request, 'gri.item_id', true);

        return $query;
    }

    private function baseUngrQuery(Request $request, ?string $from, ?string $to)
    {
        $query = DB::table('purchase_order_food_items as poi')
            ->join('purchase_order_foods as po', 'poi.purchase_order_food_id', '=', 'po.id')
            ->join('items as i', 'poi.item_id', '=', 'i.id')
            ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('units as u', 'poi.unit_id', '=', 'u.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('users as po_creator', 'po.created_by', '=', 'po_creator.id')
            ->leftJoin('food_good_receive_items as gri', 'gri.po_item_id', '=', 'poi.id')
            ->whereNull('gri.id')
            ->whereNotIn('po.status', ['rejected', 'cancelled', 'draft'])
            ->select([
                DB::raw("'ungr' as row_type"),
                DB::raw('NULL as gr_id'),
                DB::raw('NULL as gr_number'),
                DB::raw('NULL as receive_date'),
                'po.date as sort_date',
                'po.id as po_id',
                'po.number as po_number',
                'po.date as po_date',
                'po.supplier_id',
                's.name as supplier_name',
                'i.id as item_id',
                'i.name as item_name',
                'c.name as category_name',
                DB::raw('0 as qty_received'),
                'poi.quantity as po_qty',
                'u.name as unit_name',
                'poi.price as po_price',
                DB::raw('NULL as received_by_name'),
                'po_creator.nama_lengkap as po_creator_name',
            ]);

        // PO belum GR difilter by tanggal PO
        if ($from) {
            $query->whereDate('po.date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('po.date', '<=', $to);
        }
        $this->applyCommonFilters($query, $request, 'poi.item_id', false);

        // Jika filter status bayar spesifik (bukan belum_gr), jangan tampilkan baris unGR
        $paymentStatus = trim((string) $request->get('payment_status', ''));
        if ($paymentStatus !== '' && $paymentStatus !== 'belum_gr') {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function applyCommonFilters($query, Request $request, string $itemColumn, bool $isGrQuery): void
    {
        if ($request->supplier_id && $request->supplier_id !== '') {
            $query->where('po.supplier_id', $request->supplier_id);
        }
        if ($request->item_id && $request->item_id !== '') {
            $query->where($itemColumn, $request->item_id);
        }
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search, $isGrQuery) {
                $q->where('po.number', 'like', "%{$search}%")
                    ->orWhere('s.name', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%");
                if ($isGrQuery) {
                    $q->orWhere('gr.gr_number', 'like', "%{$search}%");
                }
            });
        }
    }

    private function enrichRows(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $paymentByGr = $this->loadPaymentStatusByGrIds(
            $rows->where('row_type', 'received')->pluck('gr_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        return $rows->map(function ($item) use ($paymentByGr) {
            $previousPrice = DB::table('purchase_order_food_items as poi2')
                ->join('purchase_order_foods as po2', 'poi2.purchase_order_food_id', '=', 'po2.id')
                ->where('poi2.item_id', $item->item_id)
                ->where('po2.supplier_id', $item->supplier_id)
                ->where('po2.date', '<', $item->po_date)
                ->orderBy('po2.date', 'desc')
                ->value('poi2.price');

            $item->previous_price = $previousPrice;
            $item->price_change = $previousPrice ? $item->po_price - $previousPrice : 0;
            $item->price_change_percentage = ($previousPrice !== null && $previousPrice != 0)
                ? round((($item->po_price - $previousPrice) / $previousPrice) * 100, 2)
                : 0;

            if (($item->row_type ?? '') === 'ungr') {
                $item->payment_status = 'belum_gr';
                $item->payment_status_label = 'Belum GR';
                $item->payment_number = null;
                $item->contra_bon_number = null;
                $item->gr_status_label = 'Belum GR';
                $item->is_paid = false;
            } else {
                $pay = $paymentByGr[(int) $item->gr_id] ?? null;
                $item->payment_status = $pay['status'] ?? 'unpaid';
                $item->payment_status_label = $pay['label'] ?? 'Belum Dibayar';
                $item->payment_number = $pay['payment_number'] ?? null;
                $item->contra_bon_number = $pay['contra_bon_number'] ?? null;
                $item->gr_status_label = 'Sudah GR';
                $item->is_paid = ($item->payment_status === 'paid');
            }

            return $item;
        })->values();
    }

    /**
     * @param  array<int>  $grIds
     * @return array<int, array{status: string, label: string, payment_number: ?string, contra_bon_number: ?string}>
     */
    private function loadPaymentStatusByGrIds(array $grIds): array
    {
        if ($grIds === []) {
            return [];
        }

        $map = [];

        $fromSources = DB::table('food_contra_bon_sources as src')
            ->leftJoin('food_contra_bons as cb', 'cb.id', '=', 'src.contra_bon_id')
            ->leftJoin('food_payment_contra_bons as fpcb', 'fpcb.contra_bon_id', '=', 'cb.id')
            ->leftJoin('food_payments as fp', 'fp.id', '=', 'fpcb.food_payment_id')
            ->whereIn('src.gr_id', $grIds)
            ->get([
                'src.gr_id',
                'cb.number as contra_bon_number',
                'cb.status as contra_bon_status',
                'fp.number as payment_number',
                'fp.status as payment_status',
            ]);

        foreach ($fromSources as $row) {
            $this->mergePaymentRow($map, (int) $row->gr_id, $row);
        }

        // Fallback via GR item → contra bon items (kalau belum ada di sources)
        $missing = array_values(array_diff($grIds, array_keys($map)));
        if ($missing !== []) {
            $fromItems = DB::table('food_good_receive_items as gri')
                ->join('food_contra_bon_items as cbi', 'cbi.gr_item_id', '=', 'gri.id')
                ->join('food_contra_bons as cb', 'cb.id', '=', 'cbi.contra_bon_id')
                ->leftJoin('food_payment_contra_bons as fpcb', 'fpcb.contra_bon_id', '=', 'cb.id')
                ->leftJoin('food_payments as fp', 'fp.id', '=', 'fpcb.food_payment_id')
                ->whereIn('gri.good_receive_id', $missing)
                ->get([
                    'gri.good_receive_id as gr_id',
                    'cb.number as contra_bon_number',
                    'cb.status as contra_bon_status',
                    'fp.number as payment_number',
                    'fp.status as payment_status',
                ]);

            foreach ($fromItems as $row) {
                $this->mergePaymentRow($map, (int) $row->gr_id, $row);
            }
        }

        foreach ($grIds as $grId) {
            if (! isset($map[$grId])) {
                $map[$grId] = [
                    'status' => 'unpaid',
                    'label' => 'Belum Dibayar',
                    'payment_number' => null,
                    'contra_bon_number' => null,
                ];
            }
        }

        return $map;
    }

    private function mergePaymentRow(array &$map, int $grId, object $row): void
    {
        $current = $map[$grId] ?? [
            'status' => 'unpaid',
            'label' => 'Belum Dibayar',
            'payment_number' => null,
            'contra_bon_number' => null,
            'rank' => 0,
        ];

        $status = 'unpaid';
        $label = 'Belum Dibayar';
        $rank = 1;

        if (($row->payment_status ?? null) === 'paid' || ($row->contra_bon_status ?? null) === 'paid') {
            $status = 'paid';
            $label = 'Sudah Dibayar';
            $rank = 3;
        } elseif (! empty($row->contra_bon_number) || ! empty($row->payment_number)) {
            $status = 'in_process';
            $label = 'Sudah Contra Bon';
            $rank = 2;
        }

        if ($rank >= ($current['rank'] ?? 0)) {
            $map[$grId] = [
                'status' => $status,
                'label' => $label,
                'payment_number' => $row->payment_number ?: ($current['payment_number'] ?? null),
                'contra_bon_number' => $row->contra_bon_number ?: ($current['contra_bon_number'] ?? null),
                'rank' => $rank,
            ];
        }
    }

    private function applyPriceChangeFilters(Collection $rows, Request $request): Collection
    {
        $paymentStatus = trim((string) $request->get('payment_status', ''));
        if ($paymentStatus === 'paid') {
            $rows = $rows->filter(fn ($item) => ($item->payment_status ?? '') === 'paid')->values();
        } elseif ($paymentStatus === 'unpaid') {
            // Belum lunas finance: belum dibayar ATAU sudah contra bon tapi belum paid
            $rows = $rows->filter(fn ($item) => in_array(($item->payment_status ?? ''), ['unpaid', 'in_process'], true))->values();
        }

        if ($request->hasPriceChange == 'true' || $request->priceIncrease == 'true' || $request->priceDecrease == 'true') {
            $rows = $rows->filter(function ($item) use ($request) {
                if ($request->hasPriceChange == 'true' && $item->price_change == 0) {
                    return false;
                }
                if ($request->priceIncrease == 'true' && $item->price_change <= 0) {
                    return false;
                }
                if ($request->priceDecrease == 'true' && $item->price_change >= 0) {
                    return false;
                }

                return true;
            })->values();
        }

        return $rows;
    }
}
