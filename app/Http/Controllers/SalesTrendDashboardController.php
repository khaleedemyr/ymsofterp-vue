<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesTrendDashboardController extends Controller
{
    public function index(Request $request)
    {
        [$dateFrom, $dateTo, $groupBy, $monthFrom, $monthTo] = $this->resolveFilters($request);

        return Inertia::render('SalesTrendDashboard/Index', [
            'trendData' => $this->getOverallTrend($dateFrom, $dateTo, $groupBy),
            'regionTrend' => $this->getRegionTrend($dateFrom, $dateTo, $groupBy),
            'regions' => $this->getRegions(),
            'filters' => [
                'month_from' => $monthFrom,
                'month_to' => $monthTo,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'group_by' => $groupBy,
            ],
        ]);
    }

    /**
     * Lazy-load outlet trend for a selected region.
     */
    public function outletTrend(Request $request)
    {
        [$dateFrom, $dateTo, $groupBy, $monthFrom, $monthTo] = $this->resolveFilters($request);
        $regionId = $request->get('region_id');

        if (!$regionId && $regionId !== '0' && $regionId !== 0) {
            return response()->json(['error' => 'region_id is required'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $this->getOutletTrend($dateFrom, $dateTo, $groupBy, (int) $regionId),
            'filters' => [
                'month_from' => $monthFrom,
                'month_to' => $monthTo,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'group_by' => $groupBy,
                'region_id' => (int) $regionId,
            ],
        ]);
    }

    private function resolveFilters(Request $request): array
    {
        $defaultMonthFrom = Carbon::now()->subMonths(11)->format('Y-m');
        $defaultMonthTo = Carbon::now()->format('Y-m');

        $monthFrom = $request->get('month_from');
        $monthTo = $request->get('month_to');

        // Backward compatible: allow date_from/date_to if month_* not sent
        if (!$monthFrom && $request->filled('date_from')) {
            $monthFrom = Carbon::parse($request->get('date_from'))->format('Y-m');
        }
        if (!$monthTo && $request->filled('date_to')) {
            $monthTo = Carbon::parse($request->get('date_to'))->format('Y-m');
        }

        $monthFrom = $monthFrom ?: $defaultMonthFrom;
        $monthTo = $monthTo ?: $defaultMonthTo;

        if ($monthFrom > $monthTo) {
            [$monthFrom, $monthTo] = [$monthTo, $monthFrom];
        }

        $dateFrom = Carbon::createFromFormat('Y-m', $monthFrom)->startOfMonth()->format('Y-m-d');
        $dateTo = Carbon::createFromFormat('Y-m', $monthTo)->endOfMonth()->format('Y-m-d');
        $groupBy = $request->get('group_by', 'monthly');

        if (!in_array($groupBy, ['daily', 'monthly', 'yearly'], true)) {
            $groupBy = 'monthly';
        }

        return [$dateFrom, $dateTo, $groupBy, $monthFrom, $monthTo];
    }

    private function periodSelect(string $groupBy): string
    {
        return match ($groupBy) {
            'daily' => 'DATE(o.created_at)',
            'yearly' => 'YEAR(o.created_at)',
            default => "DATE_FORMAT(o.created_at, '%Y-%m')",
        };
    }

    private function getOverallTrend(string $dateFrom, string $dateTo, string $groupBy): array
    {
        $periodSelect = $this->periodSelect($groupBy);

        $results = DB::table('orders as o')
            ->selectRaw("
                {$periodSelect} as period,
                COUNT(*) as orders,
                COALESCE(SUM(o.grand_total), 0) as revenue,
                COALESCE(AVG(o.grand_total), 0) as avg_order_value,
                COALESCE(SUM(o.pax), 0) as customers
            ")
            ->whereBetween(DB::raw('DATE(o.created_at)'), [$dateFrom, $dateTo])
            ->groupByRaw($periodSelect)
            ->orderByRaw($periodSelect . ' ASC')
            ->get();

        return $this->buildSummaryPayload($results);
    }

    private function getRegionTrend(string $dateFrom, string $dateTo, string $groupBy): array
    {
        $periodSelect = $this->periodSelect($groupBy);

        $rows = DB::table('orders as o')
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->leftJoin('regions as region', 'outlet.region_id', '=', 'region.id')
            ->selectRaw("
                {$periodSelect} as period,
                COALESCE(region.id, 0) as region_id,
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                COUNT(*) as orders,
                COALESCE(SUM(o.grand_total), 0) as revenue,
                COALESCE(SUM(o.pax), 0) as customers
            ")
            ->whereBetween(DB::raw('DATE(o.created_at)'), [$dateFrom, $dateTo])
            ->groupByRaw("{$periodSelect}, region.id, region.name, region.code")
            ->orderByRaw("{$periodSelect} ASC, revenue DESC")
            ->get();

        $periods = $rows->pluck('period')->unique()->values()->all();
        $regionMap = [];

        foreach ($rows as $row) {
            $key = (string) $row->region_id;
            if (!isset($regionMap[$key])) {
                $regionMap[$key] = [
                    'region_id' => (int) $row->region_id,
                    'region_name' => $row->region_name,
                    'region_code' => $row->region_code,
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'total_customers' => 0,
                    'by_period' => [],
                ];
            }

            $regionMap[$key]['by_period'][$row->period] = [
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
                'customers' => (int) $row->customers,
            ];
            $regionMap[$key]['total_revenue'] += (float) $row->revenue;
            $regionMap[$key]['total_orders'] += (int) $row->orders;
            $regionMap[$key]['total_customers'] += (int) $row->customers;
        }

        $regions = collect($regionMap)
            ->sortByDesc('total_revenue')
            ->values()
            ->map(function ($region) use ($periods) {
                $series = [];
                foreach ($periods as $period) {
                    $point = $region['by_period'][$period] ?? null;
                    $series[] = [
                        'period' => $period,
                        'orders' => $point['orders'] ?? 0,
                        'revenue' => $point['revenue'] ?? 0,
                        'customers' => $point['customers'] ?? 0,
                    ];
                }

                return [
                    'region_id' => $region['region_id'],
                    'region_name' => $region['region_name'],
                    'region_code' => $region['region_code'],
                    'total_revenue' => $region['total_revenue'],
                    'total_orders' => $region['total_orders'],
                    'total_customers' => $region['total_customers'],
                    'series' => $series,
                ];
            })
            ->all();

        return [
            'periods' => $periods,
            'regions' => $regions,
        ];
    }

    private function getOutletTrend(string $dateFrom, string $dateTo, string $groupBy, int $regionId): array
    {
        $periodSelect = $this->periodSelect($groupBy);

        $query = DB::table('orders as o')
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->leftJoin('regions as region', 'outlet.region_id', '=', 'region.id')
            ->selectRaw("
                {$periodSelect} as period,
                o.kode_outlet as outlet_code,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COUNT(*) as orders,
                COALESCE(SUM(o.grand_total), 0) as revenue,
                COALESCE(SUM(o.pax), 0) as customers
            ")
            ->whereBetween(DB::raw('DATE(o.created_at)'), [$dateFrom, $dateTo]);

        if ($regionId === 0) {
            $query->where(function ($q) {
                $q->whereNull('outlet.region_id')->orWhereNull('region.id');
            });
        } else {
            $query->where('outlet.region_id', $regionId);
        }

        $rows = $query
            ->groupByRaw("{$periodSelect}, o.kode_outlet, outlet.nama_outlet")
            ->orderByRaw("{$periodSelect} ASC, revenue DESC")
            ->get();

        $periods = $rows->pluck('period')->unique()->values()->all();
        $outletMap = [];

        foreach ($rows as $row) {
            $key = $row->outlet_code;
            if (!isset($outletMap[$key])) {
                $outletMap[$key] = [
                    'outlet_code' => $row->outlet_code,
                    'outlet_name' => $row->outlet_name,
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'total_customers' => 0,
                    'by_period' => [],
                ];
            }

            $outletMap[$key]['by_period'][$row->period] = [
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
                'customers' => (int) $row->customers,
            ];
            $outletMap[$key]['total_revenue'] += (float) $row->revenue;
            $outletMap[$key]['total_orders'] += (int) $row->orders;
            $outletMap[$key]['total_customers'] += (int) $row->customers;
        }

        // Keep chart readable: top 10 outlets by revenue in selected region
        $outlets = collect($outletMap)
            ->sortByDesc('total_revenue')
            ->take(10)
            ->values()
            ->map(function ($outlet) use ($periods) {
                $series = [];
                foreach ($periods as $period) {
                    $point = $outlet['by_period'][$period] ?? null;
                    $series[] = [
                        'period' => $period,
                        'orders' => $point['orders'] ?? 0,
                        'revenue' => $point['revenue'] ?? 0,
                        'customers' => $point['customers'] ?? 0,
                    ];
                }

                return [
                    'outlet_code' => $outlet['outlet_code'],
                    'outlet_name' => $outlet['outlet_name'],
                    'total_revenue' => $outlet['total_revenue'],
                    'total_orders' => $outlet['total_orders'],
                    'total_customers' => $outlet['total_customers'],
                    'series' => $series,
                ];
            })
            ->all();

        return [
            'periods' => $periods,
            'outlets' => $outlets,
            'outlet_count_total' => count($outletMap),
            'outlet_count_shown' => count($outlets),
        ];
    }

    private function getRegions()
    {
        return DB::table('regions')
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
    }

    private function buildSummaryPayload($results): array
    {
        $totalRevenue = (float) $results->sum('revenue');
        $totalOrders = (int) $results->sum('orders');
        $totalCustomers = (int) $results->sum('customers');

        return [
            'series' => $results,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'total_customers' => $totalCustomers,
                'avg_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
                'avg_check' => $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0,
            ],
        ];
    }
}
