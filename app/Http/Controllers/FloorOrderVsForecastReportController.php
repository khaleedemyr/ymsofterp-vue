<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FloorOrderVsForecastReportController extends Controller
{
    private const KITCHEN_BAR_RATIO = 0.35;

    private const SERVICE_RATIO = 0.05;

    public function index(Request $request)
    {
        return Inertia::render('Reports/FloorOrderVsForecast', $this->buildReportPayload($request));
    }

    /**
     * GET /api/approval-app/floor-order-vs-forecast — payload sama seperti halaman web (mobile app).
     */
    public function apiIndex(Request $request)
    {
        return response()->json(array_merge([
            'success' => true,
        ], $this->buildReportPayload($request)));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportPayload(Request $request): array
    {
        $user = auth()->user();
        $isAdminOutlet = (int) ($user->id_outlet ?? 0) === 1;

        $month = $request->input('month', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthDate = $monthStart->toDateString();

        $selectedOutletId = (int) ($request->input('outlet_id') ?: 0);
        if (!$isAdminOutlet) {
            $selectedOutletId = (int) ($user->id_outlet ?? 0);
        } elseif ($selectedOutletId <= 0) {
            $selectedOutletId = 1;
        }

        $outletsQuery = DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        if (!$isAdminOutlet) {
            $outletsQuery->where('id_outlet', $selectedOutletId);
        }

        $outlets = $outletsQuery->get();

        $forecastByDate = [];
        $monthlyTarget = null;
        $revenueTargetHeaderExists = false;

        if ($selectedOutletId > 0) {
            $header = DB::table('outlet_revenue_target_headers')
                ->where('outlet_id', $selectedOutletId)
                ->where('target_month', $monthDate)
                ->first();

            if ($header) {
                $revenueTargetHeaderExists = true;
                $monthlyTarget = (float) $header->monthly_target;
                $details = DB::table('outlet_revenue_target_details')
                    ->where('header_id', $header->id)
                    ->orderBy('forecast_date')
                    ->get(['forecast_date', 'forecast_revenue']);

                foreach ($details as $d) {
                    $key = Carbon::parse($d->forecast_date)->toDateString();
                    $forecastByDate[$key] = (float) $d->forecast_revenue;
                }
            }
        }

        $roKitchenBar = [];
        $roService = [];
        $roOther = [];

        if ($selectedOutletId > 0) {
            $rangeStart = $monthStart->toDateString();
            $rangeEnd = $monthEnd->toDateString();

            $bucketExpr = 'CASE
                WHEN LOWER(TRIM(wo.name)) IN (\'kitchen\', \'bar\') THEN \'kitchen_bar\'
                WHEN LOWER(TRIM(wo.name)) = \'service\' THEN \'service\'
                ELSE \'other\'
            END';

            $aggregates = DB::table('food_floor_orders as ffo')
                ->join('warehouse_outlets as wo', 'wo.id', '=', 'ffo.warehouse_outlet_id')
                ->join('food_floor_order_items as ffoi', 'ffoi.floor_order_id', '=', 'ffo.id')
                ->where('ffo.id_outlet', $selectedOutletId)
                ->whereNotNull('ffo.arrival_date')
                ->whereBetween(DB::raw('DATE(ffo.arrival_date)'), [$rangeStart, $rangeEnd])
                ->whereNotIn('ffo.status', ['draft', 'rejected'])
                ->selectRaw('DATE(ffo.arrival_date) as d, '.$bucketExpr.' as bucket, SUM(ffoi.subtotal) as total')
                ->groupBy(DB::raw('DATE(ffo.arrival_date)'), DB::raw($bucketExpr))
                ->get();

            foreach ($aggregates as $row) {
                $dateKey = Carbon::parse($row->d)->toDateString();
                $total = round((float) $row->total, 2);
                if ($row->bucket === 'kitchen_bar') {
                    $roKitchenBar[$dateKey] = ($roKitchenBar[$dateKey] ?? 0) + $total;
                } elseif ($row->bucket === 'service') {
                    $roService[$dateKey] = ($roService[$dateKey] ?? 0) + $total;
                } else {
                    $roOther[$dateKey] = ($roOther[$dateKey] ?? 0) + $total;
                }
            }
        }

        $rows = [];
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $ds = $cursor->toDateString();
            $forecast = (float) ($forecastByDate[$ds] ?? 0);
            $capKb = round($forecast * self::KITCHEN_BAR_RATIO, 2);
            $capSvc = round($forecast * self::SERVICE_RATIO, 2);
            $kb = round((float) ($roKitchenBar[$ds] ?? 0), 2);
            $svc = round((float) ($roService[$ds] ?? 0), 2);
            $other = round((float) ($roOther[$ds] ?? 0), 2);

            $diffKb = round($kb - $capKb, 2);
            $diffSvc = round($svc - $capSvc, 2);

            $pctKb = $capKb > 0 ? round(($kb / $capKb) * 100, 1) : null;
            $pctSvc = $capSvc > 0 ? round(($svc / $capSvc) * 100, 1) : null;

            $rows[] = [
                'date' => $ds,
                'day_name' => $cursor->locale('id')->isoFormat('dddd'),
                'forecast_revenue' => $forecast,
                'cap_kitchen_bar' => $capKb,
                'cap_service' => $capSvc,
                'ro_kitchen_bar' => $kb,
                'ro_service' => $svc,
                'ro_other' => $other,
                'diff_kitchen_bar' => $diffKb,
                'diff_service' => $diffSvc,
                'pct_kitchen_bar_vs_cap' => $pctKb,
                'pct_service_vs_cap' => $pctSvc,
            ];
            $cursor->addDay();
        }

        $totals = [
            'forecast_revenue' => 0,
            'cap_kitchen_bar' => 0,
            'cap_service' => 0,
            'ro_kitchen_bar' => 0,
            'ro_service' => 0,
            'ro_other' => 0,
            'diff_kitchen_bar' => 0,
            'diff_service' => 0,
        ];
        foreach ($rows as $r) {
            $totals['forecast_revenue'] += $r['forecast_revenue'];
            $totals['cap_kitchen_bar'] += $r['cap_kitchen_bar'];
            $totals['cap_service'] += $r['cap_service'];
            $totals['ro_kitchen_bar'] += $r['ro_kitchen_bar'];
            $totals['ro_service'] += $r['ro_service'];
            $totals['ro_other'] += $r['ro_other'];
            $totals['diff_kitchen_bar'] += $r['diff_kitchen_bar'];
            $totals['diff_service'] += $r['diff_service'];
        }
        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }

        return [
            'outlets' => $outlets,
            'selectedOutletId' => $selectedOutletId,
            'selectedMonth' => $month,
            'month_label' => $monthStart->locale('id')->translatedFormat('F Y'),
            'monthlyTarget' => $monthlyTarget,
            'kitchen_bar_ratio_pct' => (int) round(self::KITCHEN_BAR_RATIO * 100),
            'service_ratio_pct' => (int) round(self::SERVICE_RATIO * 100),
            'rows' => $rows,
            'totals' => $totals,
            'has_forecast_header' => $revenueTargetHeaderExists || count($forecastByDate) > 0,
            'canSelectOutlet' => $isAdminOutlet,
        ];
    }
}
