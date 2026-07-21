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
        $dateFrom = $request->get('date_from', Carbon::now()->subMonths(11)->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $groupBy = $request->get('group_by', 'monthly');

        if (!in_array($groupBy, ['daily', 'monthly', 'yearly'], true)) {
            $groupBy = 'monthly';
        }

        $trendData = $this->getTrendData($dateFrom, $dateTo, $groupBy);

        return Inertia::render('SalesTrendDashboard/Index', [
            'trendData' => $trendData,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'group_by' => $groupBy,
            ],
        ]);
    }

    private function getTrendData(string $dateFrom, string $dateTo, string $groupBy): array
    {
        $periodSelect = match ($groupBy) {
            'daily' => "DATE(created_at)",
            'yearly' => "YEAR(created_at)",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $results = DB::table('orders')
            ->selectRaw("
                {$periodSelect} as period,
                COUNT(*) as orders,
                COALESCE(SUM(grand_total), 0) as revenue,
                COALESCE(AVG(grand_total), 0) as avg_order_value,
                COALESCE(SUM(pax), 0) as customers
            ")
            ->whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->groupByRaw($periodSelect)
            ->orderByRaw($periodSelect . ' ASC')
            ->get();

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
