<?php

namespace App\Http\Controllers;

use App\Exports\ModalEngineeringExport;
use App\Services\ModalEngineeringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ModalEngineeringReportController extends Controller
{
    public function __construct(
        private ModalEngineeringService $modalEngineering,
    ) {}
    public function index(Request $request)
    {
        return Inertia::render('Reports/ModalEngineering', $this->buildReportPayload($request));
    }

    public function export(Request $request)
    {
        $payload = $this->buildReportPayload($request);
        $month = preg_replace('/[^0-9\-]/', '', (string) ($payload['selectedMonth'] ?? now()->format('Y-m')));
        $outletId = (int) ($payload['selectedOutletId'] ?? 0);

        return Excel::download(
            new ModalEngineeringExport($payload),
            'modal_x_engineering_'.$month.'_outlet_'.$outletId.'_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportPayload(Request $request): array
    {
        $user = auth()->user();
        $isAdminOutlet = (int) ($user->id_outlet ?? 0) === 1;

        $month = $request->input('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $rangeStart = $monthStart->toDateString();
        $rangeEnd = $monthEnd->toDateString();

        $selectedOutletId = (int) ($request->input('outlet_id') ?: 0);
        if (! $isAdminOutlet) {
            $selectedOutletId = (int) ($user->id_outlet ?? 0);
        } elseif ($selectedOutletId <= 0) {
            $selectedOutletId = 1;
        }

        $outletsQuery = DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        if (! $isAdminOutlet) {
            $outletsQuery->where('id_outlet', $selectedOutletId);
        }

        $outlets = $outletsQuery->get();

        $outletName = $outlets->firstWhere('id', $selectedOutletId)?->name
            ?? DB::table('tbl_data_outlet')->where('id_outlet', $selectedOutletId)->value('nama_outlet')
            ?? '—';

        $stockCutByDate = [];
        $categoryCostUsageByDate = [];
        $engineeringByDate = [];
        $outletQr = null;

        if ($selectedOutletId > 0) {
            $stockCutRows = DB::table('stock_cut_details as scd')
                ->join('stock_cut_logs as scl', 'scd.stock_cut_log_id', '=', 'scl.id')
                ->where('scl.outlet_id', $selectedOutletId)
                ->where('scl.status', 'success')
                ->whereBetween('scl.tanggal', [$rangeStart, $rangeEnd])
                ->selectRaw('DATE(scl.tanggal) as d, SUM(COALESCE(scd.value_out, 0)) as total_stock_cut')
                ->groupBy(DB::raw('DATE(scl.tanggal)'))
                ->get();

            foreach ($stockCutRows as $stockCutRow) {
                $stockCutByDate[Carbon::parse($stockCutRow->d)->toDateString()] = round((float) ($stockCutRow->total_stock_cut ?? 0), 2);
            }

            $categoryCostUsageRows = DB::table('outlet_internal_use_waste_headers as h')
                ->where('h.outlet_id', $selectedOutletId)
                ->where('h.type', 'usage')
                ->whereIn('h.status', ['APPROVED', 'PROCESSED'])
                ->whereBetween('h.date', [$rangeStart, $rangeEnd])
                ->selectRaw('DATE(h.date) as d, SUM(COALESCE(h.subtotal_mac, 0)) as total_category_cost_usage')
                ->groupBy(DB::raw('DATE(h.date)'))
                ->get();

            foreach ($categoryCostUsageRows as $categoryCostUsageRow) {
                $categoryCostUsageByDate[Carbon::parse($categoryCostUsageRow->d)->toDateString()] = round((float) ($categoryCostUsageRow->total_category_cost_usage ?? 0), 2);
            }

            $outletQr = DB::table('tbl_data_outlet')
                ->where('id_outlet', $selectedOutletId)
                ->value('qr_code');

            if ($outletQr) {
                $salesRows = DB::table('orders')
                    ->where('kode_outlet', $outletQr)
                    ->whereBetween(DB::raw('DATE(created_at)'), [$rangeStart, $rangeEnd])
                    ->selectRaw('DATE(created_at) as d, COALESCE(SUM(total), 0) as total_before_discount_tax_service')
                    ->groupBy(DB::raw('DATE(created_at)'))
                    ->get();

                foreach ($salesRows as $salesRow) {
                    $engineeringByDate[Carbon::parse($salesRow->d)->toDateString()] = round((float) ($salesRow->total_before_discount_tax_service ?? 0), 2);
                }
            }
        }

        $rows = [];
        $totals = [
            'stock_cut' => 0.0,
            'category_cost_usage' => 0.0,
            'total_modal' => 0.0,
            'engineering' => 0.0,
        ];

        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $ds = $cursor->toDateString();
            $stockCut = round((float) ($stockCutByDate[$ds] ?? 0), 2);
            $categoryCostUsage = round((float) ($categoryCostUsageByDate[$ds] ?? 0), 2);
            $totalModal = round($stockCut + $categoryCostUsage, 2);
            $engineering = round((float) ($engineeringByDate[$ds] ?? 0), 2);
            $modalXEngineering = $engineering > 0
                ? round(($totalModal / $engineering) * 100, 2)
                : null;

            $rows[] = [
                'date' => $ds,
                'day_name' => $cursor->locale('id')->isoFormat('dddd'),
                'stock_cut' => $stockCut,
                'category_cost_usage' => $categoryCostUsage,
                'total_modal' => $totalModal,
                'engineering' => $engineering,
                'modal_x_engineering_pct' => $modalXEngineering,
            ];

            $totals['stock_cut'] += $stockCut;
            $totals['category_cost_usage'] += $categoryCostUsage;
            $totals['total_modal'] += $totalModal;
            $totals['engineering'] += $engineering;

            $cursor->addDay();
        }

        $periodTotals = $this->modalEngineering->totalsForPeriod(
            $selectedOutletId,
            $rangeStart,
            $rangeEnd,
            $outletQr ? (string) $outletQr : null,
        );
        $totals = array_merge($totals, $periodTotals);

        return [
            'outlets' => $outlets,
            'selectedOutletId' => $selectedOutletId,
            'selectedOutletName' => $outletName,
            'selectedMonth' => $month,
            'month_label' => $monthStart->locale('id')->isoFormat('MMMM YYYY'),
            'rows' => $rows,
            'totals' => $totals,
            'canSelectOutlet' => $isAdminOutlet,
        ];
    }
}
