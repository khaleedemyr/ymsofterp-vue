<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Optimized sales outlet dashboard data builder.
 * - Avoids DATE(created_at) in WHERE (index-friendly range)
 * - Consolidates overlapping scans into fewer queries
 * - Supports progressive section loading
 */
class SalesOutletDashboardService
{
    public function emptyDashboard(): array
    {
        return [
            'overview' => [
                'total_orders' => 0,
                'total_revenue' => 0.0,
                'avg_order_value' => 0.0,
                'total_customers' => 0,
                'avg_pax_per_order' => 0.0,
                'avg_check' => 0.0,
                'total_discount' => 0.0,
                'total_service_charge' => 0.0,
                'total_commission_fee' => 0.0,
                'total_manual_discount' => 0.0,
                'revenue_growth' => 0.0,
                'order_growth' => 0.0,
                'previous_period' => [
                    'date_from' => null,
                    'date_to' => null,
                    'total_orders' => 0,
                    'total_revenue' => 0.0,
                ],
            ],
            'salesTrend' => [],
            'topItems' => [],
            'paymentMethods' => [],
            'hourlySales' => [],
            'promoUsage' => [
                'orders_with_promo' => 0,
                'total_promo_usage' => 0,
                'promo_usage_percentage' => 0,
            ],
            'bankPromoDiscount' => [
                'orders_with_bank_promo' => 0,
                'total_bank_discount_amount' => 0.0,
                'avg_bank_discount_amount' => 0.0,
                'bank_promo_percentage' => 0,
            ],
            'avgOrderValue' => (object) [
                'avg_order_value' => 0,
                'min_order_value' => 0,
                'max_order_value' => 0,
                'median_order_value' => 0,
            ],
            'peakHours' => [],
            'lunchDinnerOrders' => [
                'lunch' => ['order_count' => 0, 'total_revenue' => 0, 'total_pax' => 0, 'avg_order_value' => 0],
                'dinner' => ['order_count' => 0, 'total_revenue' => 0, 'total_pax' => 0, 'avg_order_value' => 0],
            ],
            'weekdayWeekendRevenue' => [
                'weekday' => ['order_count' => 0, 'total_revenue' => 0, 'total_pax' => 0, 'avg_order_value' => 0],
                'weekend' => ['order_count' => 0, 'total_revenue' => 0, 'total_pax' => 0, 'avg_order_value' => 0],
            ],
            'revenuePerOutlet' => [],
            'revenuePerOutletLunchDinner' => [],
            'revenuePerOutletWeekendWeekday' => [],
            'revenuePerRegion' => [
                'total_revenue' => [],
                'lunch_dinner' => [],
                'weekday_weekend' => [],
            ],
            'forecast' => null,
        ];
    }

    public function getFullDashboard(string $dateFrom, string $dateTo): array
    {
        $data = $this->emptyDashboard();
        foreach (['overview', 'trend', 'charts', 'catalog', 'promo', 'revenue', 'forecast'] as $section) {
            $data = array_replace($data, $this->buildSection($section, $dateFrom, $dateTo));
        }

        return $data;
    }

    public function buildSection(string $section, string $dateFrom, string $dateTo): array
    {
        return match ($section) {
            'overview' => $this->sectionOverview($dateFrom, $dateTo),
            'trend' => ['salesTrend' => $this->getSalesTrend($dateFrom, $dateTo)],
            'charts' => $this->sectionCharts($dateFrom, $dateTo),
            'catalog' => $this->sectionCatalog($dateFrom, $dateTo),
            'promo' => $this->sectionPromo($dateFrom, $dateTo),
            'revenue' => $this->sectionRevenue($dateFrom, $dateTo),
            'forecast' => $this->sectionForecast($dateFrom, $dateTo),
            default => [],
        };
    }

    /**
     * @return array{0:string,1:string} [startInclusive, endExclusive]
     */
    public function bounds(string $dateFrom, string $dateTo): array
    {
        $start = Carbon::parse($dateFrom)->startOfDay()->format('Y-m-d H:i:s');
        $endExclusive = Carbon::parse($dateTo)->addDay()->startOfDay()->format('Y-m-d H:i:s');

        return [$start, $endExclusive];
    }

    /**
     * Rolling Auto Forecast + nilai Forecast RO (pesimis) untuk semua outlet.
     * Sumber logika: OutletRollingForecastService (sama dengan Opex Outlet Dashboard).
     */
    private function sectionForecast(string $dateFrom, string $dateTo): array
    {
        $month = Carbon::parse($dateFrom)->format('Y-m');

        return [
            'forecast' => app(OutletRollingForecastService::class)->buildAllOutletsSummary($month),
        ];
    }

    private function sectionOverview(string $dateFrom, string $dateTo): array
    {
        [$start, $end] = $this->bounds($dateFrom, $dateTo);

        $result = DB::selectOne("
            SELECT
                COUNT(*) as total_orders,
                COALESCE(SUM(grand_total), 0) as total_revenue,
                COALESCE(AVG(grand_total), 0) as avg_order_value,
                COALESCE(SUM(pax), 0) as total_customers,
                COALESCE(AVG(pax), 0) as avg_pax_per_order,
                COALESCE(SUM(discount), 0) as total_discount,
                COALESCE(SUM(service), 0) as total_service_charge,
                COALESCE(SUM(commfee), 0) as total_commission_fee,
                COALESCE(SUM(manual_discount_amount), 0) as total_manual_discount,
                COALESCE(MIN(grand_total), 0) as min_order_value,
                COALESCE(MAX(grand_total), 0) as max_order_value
            FROM orders
            WHERE created_at >= ? AND created_at < ?
        ", [$start, $end]);

        $days = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo));
        $prevDateFrom = Carbon::parse($dateFrom)->subDays($days)->format('Y-m-d');
        $prevDateTo = Carbon::parse($dateFrom)->subDay()->format('Y-m-d');
        [$prevStart, $prevEnd] = $this->bounds($prevDateFrom, $prevDateTo);

        $prev = DB::selectOne("
            SELECT
                COUNT(*) as total_orders,
                COALESCE(SUM(grand_total), 0) as total_revenue
            FROM orders
            WHERE created_at >= ? AND created_at < ?
        ", [$prevStart, $prevEnd]);

        $totalRevenue = (float) $result->total_revenue;
        $totalOrders = (int) $result->total_orders;
        $totalCustomers = (int) $result->total_customers;
        $prevRevenue = (float) $prev->total_revenue;
        $prevOrders = (int) $prev->total_orders;
        $avgOrderValue = (float) $result->avg_order_value;

        return [
            'overview' => [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'avg_order_value' => $avgOrderValue,
                'total_customers' => $totalCustomers,
                'avg_pax_per_order' => (float) $result->avg_pax_per_order,
                'avg_check' => $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0.0,
                'total_discount' => (float) $result->total_discount,
                'total_service_charge' => (float) $result->total_service_charge,
                'total_commission_fee' => (float) $result->total_commission_fee,
                'total_manual_discount' => (float) $result->total_manual_discount,
                'revenue_growth' => $prevRevenue > 0
                    ? (($totalRevenue - $prevRevenue) / $prevRevenue) * 100
                    : 0.0,
                'order_growth' => $prevOrders > 0
                    ? (($totalOrders - $prevOrders) / $prevOrders) * 100
                    : 0.0,
                'previous_period' => [
                    'date_from' => $prevDateFrom,
                    'date_to' => $prevDateTo,
                    'total_orders' => $prevOrders,
                    'total_revenue' => $prevRevenue,
                ],
            ],
            // Derived from overview — avoids a second full scan
            'avgOrderValue' => (object) [
                'avg_order_value' => $avgOrderValue,
                'min_order_value' => (float) $result->min_order_value,
                'max_order_value' => (float) $result->max_order_value,
                'median_order_value' => $avgOrderValue,
            ],
        ];
    }

    private function getSalesTrend(string $dateFrom, string $dateTo): array
    {
        [$start, $end] = $this->bounds($dateFrom, $dateTo);

        return DB::select("
            SELECT
                DATE(created_at) as period,
                COUNT(*) as orders,
                COALESCE(SUM(grand_total), 0) as revenue,
                COALESCE(AVG(grand_total), 0) as avg_order_value,
                COALESCE(SUM(pax), 0) as customers
            FROM orders
            WHERE created_at >= ? AND created_at < ?
            GROUP BY DATE(created_at)
            ORDER BY period ASC
        ", [$start, $end]);
    }

    /**
     * One bucket scan → hourly, peak, lunch/dinner, weekday/weekend.
     */
    private function sectionCharts(string $dateFrom, string $dateTo): array
    {
        [$start, $end] = $this->bounds($dateFrom, $dateTo);

        $rows = DB::select("
            SELECT
                HOUR(created_at) as hour,
                CASE WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend' ELSE 'Weekday' END as day_type,
                COUNT(*) as orders,
                COALESCE(SUM(grand_total), 0) as revenue,
                COALESCE(SUM(pax), 0) as customers,
                COALESCE(AVG(grand_total), 0) as avg_order_value
            FROM orders
            WHERE created_at >= ? AND created_at < ?
            GROUP BY HOUR(created_at), CASE WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend' ELSE 'Weekday' END
        ", [$start, $end]);

        $hourlyMap = [];
        $lunchDinner = [
            'lunch' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'revenue_sum_for_avg' => 0.0],
            'dinner' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'revenue_sum_for_avg' => 0.0],
        ];
        $weekdayWeekend = [
            'weekday' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'revenue_sum_for_avg' => 0.0],
            'weekend' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'revenue_sum_for_avg' => 0.0],
        ];

        foreach ($rows as $row) {
            $hour = (int) $row->hour;
            $orders = (int) $row->orders;
            $revenue = (float) $row->revenue;
            $customers = (int) $row->customers;

            if (! isset($hourlyMap[$hour])) {
                $hourlyMap[$hour] = [
                    'hour' => $hour,
                    'orders' => 0,
                    'revenue' => 0.0,
                    'customers' => 0,
                    'weighted_avg_sum' => 0.0,
                ];
            }
            $hourlyMap[$hour]['orders'] += $orders;
            $hourlyMap[$hour]['revenue'] += $revenue;
            $hourlyMap[$hour]['customers'] += $customers;
            $hourlyMap[$hour]['weighted_avg_sum'] += ((float) $row->avg_order_value) * $orders;

            $meal = $hour <= 17 ? 'lunch' : 'dinner';
            $lunchDinner[$meal]['order_count'] += $orders;
            $lunchDinner[$meal]['total_revenue'] += $revenue;
            $lunchDinner[$meal]['total_pax'] += $customers;

            $dayKey = strtolower($row->day_type);
            $weekdayWeekend[$dayKey]['order_count'] += $orders;
            $weekdayWeekend[$dayKey]['total_revenue'] += $revenue;
            $weekdayWeekend[$dayKey]['total_pax'] += $customers;
        }

        $hourlySales = [];
        foreach ($hourlyMap as $hour => $data) {
            $hourlySales[] = (object) [
                'hour' => $hour,
                'orders' => $data['orders'],
                'revenue' => $data['revenue'],
                'avg_order_value' => $data['orders'] > 0
                    ? $data['weighted_avg_sum'] / $data['orders']
                    : 0,
            ];
        }
        usort($hourlySales, fn ($a, $b) => $a->hour <=> $b->hour);

        $peakHours = array_map(function ($row) {
            return (object) [
                'hour' => $row->hour,
                'order_count' => $row->orders,
                'revenue' => $row->revenue,
                'avg_order_value' => $row->avg_order_value,
                'total_customers' => $hourlyMap[$row->hour]['customers'] ?? 0,
            ];
        }, $hourlySales);
        usort($peakHours, fn ($a, $b) => $b->order_count <=> $a->order_count);
        $peakHours = array_slice($peakHours, 0, 5);

        foreach (['lunch', 'dinner'] as $key) {
            $c = $lunchDinner[$key]['order_count'];
            $lunchDinner[$key] = [
                'order_count' => $lunchDinner[$key]['order_count'],
                'total_revenue' => $lunchDinner[$key]['total_revenue'],
                'total_pax' => $lunchDinner[$key]['total_pax'],
                'avg_order_value' => $c > 0
                    ? $lunchDinner[$key]['total_revenue'] / $c
                    : 0.0,
            ];
        }

        foreach (['weekday', 'weekend'] as $key) {
            $c = $weekdayWeekend[$key]['order_count'];
            $weekdayWeekend[$key] = [
                'order_count' => $weekdayWeekend[$key]['order_count'],
                'total_revenue' => $weekdayWeekend[$key]['total_revenue'],
                'total_pax' => $weekdayWeekend[$key]['total_pax'],
                'avg_order_value' => $c > 0
                    ? $weekdayWeekend[$key]['total_revenue'] / $c
                    : 0.0,
            ];
        }

        return [
            'hourlySales' => $hourlySales,
            'peakHours' => $peakHours,
            'lunchDinnerOrders' => $lunchDinner,
            'weekdayWeekendRevenue' => $weekdayWeekend,
        ];
    }

    private function sectionCatalog(string $dateFrom, string $dateTo): array
    {
        [$start, $end] = $this->bounds($dateFrom, $dateTo);

        $topItems = DB::select("
            SELECT
                oi.item_name,
                SUM(oi.qty) as total_qty,
                SUM(oi.subtotal) as total_revenue,
                COUNT(DISTINCT oi.order_id) as order_count,
                AVG(oi.price) as avg_price
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE o.created_at >= ? AND o.created_at < ?
            GROUP BY oi.item_name
            ORDER BY total_revenue DESC
            LIMIT 10
        ", [$start, $end]);

        $chartData = DB::select("
            SELECT
                op.payment_code,
                COUNT(*) as transaction_count,
                SUM(op.amount) as total_amount,
                AVG(op.amount) as avg_amount
            FROM order_payment op
            INNER JOIN orders o ON op.order_id = o.id
            WHERE o.created_at >= ? AND o.created_at < ?
            GROUP BY op.payment_code
            ORDER BY total_amount DESC
        ", [$start, $end]);

        $detailData = DB::select("
            SELECT
                op.payment_code,
                op.payment_type,
                COUNT(*) as transaction_count,
                SUM(op.amount) as total_amount,
                AVG(op.amount) as avg_amount
            FROM order_payment op
            INNER JOIN orders o ON op.order_id = o.id
            WHERE o.created_at >= ? AND o.created_at < ?
            GROUP BY op.payment_code, op.payment_type
            ORDER BY op.payment_code, total_amount DESC
        ", [$start, $end]);

        $groupedDetails = [];
        foreach ($detailData as $detail) {
            $groupedDetails[$detail->payment_code][] = $detail;
        }

        $paymentMethods = [];
        foreach ($chartData as $chart) {
            $paymentMethods[] = [
                'payment_code' => $chart->payment_code,
                'transaction_count' => $chart->transaction_count,
                'total_amount' => $chart->total_amount,
                'avg_amount' => $chart->avg_amount,
                'details' => $groupedDetails[$chart->payment_code] ?? [],
            ];
        }

        return [
            'topItems' => $topItems,
            'paymentMethods' => $paymentMethods,
        ];
    }

    private function sectionPromo(string $dateFrom, string $dateTo): array
    {
        [$start, $end] = $this->bounds($dateFrom, $dateTo);

        // Single scan for total orders + bank promo aggregates
        $totals = DB::selectOne("
            SELECT
                COUNT(*) as total_orders,
                SUM(CASE WHEN manual_discount_reason LIKE '%BANK%' THEN 1 ELSE 0 END) as orders_with_bank_promo,
                COALESCE(SUM(CASE WHEN manual_discount_reason LIKE '%BANK%' THEN manual_discount_amount ELSE 0 END), 0) as total_bank_discount_amount,
                COALESCE(AVG(CASE WHEN manual_discount_reason LIKE '%BANK%' THEN manual_discount_amount ELSE NULL END), 0) as avg_bank_discount_amount
            FROM orders
            WHERE created_at >= ? AND created_at < ?
        ", [$start, $end]);

        $promo = DB::selectOne("
            SELECT
                COUNT(DISTINCT op.order_id) as orders_with_promo,
                COUNT(op.id) as total_promo_usage
            FROM order_promos op
            INNER JOIN orders o ON op.order_id = o.id
            INNER JOIN promos p ON op.promo_id = p.id
            WHERE o.created_at >= ? AND o.created_at < ?
              AND p.status = 'active'
              AND o.discount > 0
        ", [$start, $end]);

        $totalOrders = (int) $totals->total_orders;
        $ordersWithPromo = (int) $promo->orders_with_promo;
        $ordersWithBank = (int) $totals->orders_with_bank_promo;

        return [
            'promoUsage' => [
                'orders_with_promo' => $ordersWithPromo,
                'total_promo_usage' => (int) $promo->total_promo_usage,
                'promo_usage_percentage' => $totalOrders > 0
                    ? ($ordersWithPromo / $totalOrders) * 100
                    : 0,
            ],
            'bankPromoDiscount' => [
                'orders_with_bank_promo' => $ordersWithBank,
                'total_bank_discount_amount' => (float) $totals->total_bank_discount_amount,
                'avg_bank_discount_amount' => (float) $totals->avg_bank_discount_amount,
                'bank_promo_percentage' => $totalOrders > 0
                    ? ($ordersWithBank / $totalOrders) * 100
                    : 0,
            ],
        ];
    }

    /**
     * One outlet×hour×day_type scan → all outlet/region revenue views.
     */
    private function sectionRevenue(string $dateFrom, string $dateTo): array
    {
        [$start, $end] = $this->bounds($dateFrom, $dateTo);

        $rows = DB::select("
            SELECT
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                HOUR(o.created_at) as hour,
                CASE WHEN DAYOFWEEK(o.created_at) IN (1, 7) THEN 'Weekend' ELSE 'Weekday' END as day_type,
                COUNT(*) as order_count,
                COALESCE(SUM(o.grand_total), 0) as total_revenue,
                COALESCE(SUM(o.pax), 0) as total_pax
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= ? AND o.created_at < ?
            GROUP BY
                o.kode_outlet,
                outlet.nama_outlet,
                region.name,
                region.code,
                HOUR(o.created_at),
                CASE WHEN DAYOFWEEK(o.created_at) IN (1, 7) THEN 'Weekend' ELSE 'Weekday' END
        ", [$start, $end]);

        $revenuePerOutlet = [];
        $revenuePerOutletLunchDinner = [];
        $revenuePerOutletWeekendWeekday = [];
        $regionTotals = [];
        $regionLunchDinner = [];
        $regionWeekdayWeekend = [];

        foreach ($rows as $row) {
            $regionName = $row->region_name;
            $regionCode = $row->region_code;
            $outletKey = $row->outlet_name;
            $hour = (int) $row->hour;
            $dayType = $row->day_type;
            $orders = (int) $row->order_count;
            $revenue = (float) $row->total_revenue;
            $pax = (int) $row->total_pax;
            $avg = $orders > 0 ? $revenue / $orders : 0.0;

            // --- revenuePerOutlet (all hours) ---
            if (! isset($revenuePerOutlet[$regionName])) {
                $revenuePerOutlet[$regionName] = [
                    'region_code' => $regionCode,
                    'outlets' => [],
                    'total_revenue' => 0.0,
                    'total_orders' => 0,
                    'total_pax' => 0,
                ];
            }
            if (! isset($revenuePerOutlet[$regionName]['outlets'][$outletKey])) {
                $revenuePerOutlet[$regionName]['outlets'][$outletKey] = [
                    'outlet_code' => $row->kode_outlet,
                    'outlet_name' => $row->outlet_name,
                    'order_count' => 0,
                    'total_revenue' => 0.0,
                    'total_pax' => 0,
                ];
            }
            $revenuePerOutlet[$regionName]['outlets'][$outletKey]['order_count'] += $orders;
            $revenuePerOutlet[$regionName]['outlets'][$outletKey]['total_revenue'] += $revenue;
            $revenuePerOutlet[$regionName]['outlets'][$outletKey]['total_pax'] += $pax;
            $revenuePerOutlet[$regionName]['total_revenue'] += $revenue;
            $revenuePerOutlet[$regionName]['total_orders'] += $orders;
            $revenuePerOutlet[$regionName]['total_pax'] += $pax;

            // --- region total ---
            if (! isset($regionTotals[$regionName])) {
                $regionTotals[$regionName] = [
                    'region_name' => $regionName,
                    'region_code' => $regionCode,
                    'total_orders' => 0,
                    'total_revenue' => 0.0,
                    'total_pax' => 0,
                ];
            }
            $regionTotals[$regionName]['total_orders'] += $orders;
            $regionTotals[$regionName]['total_revenue'] += $revenue;
            $regionTotals[$regionName]['total_pax'] += $pax;

            // --- lunch/dinner per outlet (11-15 / 17-22) ---
            $mealPeriod = null;
            if ($hour >= 11 && $hour <= 15) {
                $mealPeriod = 'Lunch';
            } elseif ($hour >= 17 && $hour <= 22) {
                $mealPeriod = 'Dinner';
            }

            if ($mealPeriod !== null) {
                if (! isset($revenuePerOutletLunchDinner[$regionName])) {
                    $revenuePerOutletLunchDinner[$regionName] = [
                        'region_code' => $regionCode,
                        'outlets' => [],
                        'lunch' => ['total_revenue' => 0.0, 'total_orders' => 0, 'total_pax' => 0],
                        'dinner' => ['total_revenue' => 0.0, 'total_orders' => 0, 'total_pax' => 0],
                        'total_revenue' => 0.0,
                        'total_orders' => 0,
                        'total_pax' => 0,
                    ];
                }
                if (! isset($revenuePerOutletLunchDinner[$regionName]['outlets'][$outletKey])) {
                    $revenuePerOutletLunchDinner[$regionName]['outlets'][$outletKey] = [
                        'outlet_code' => $row->kode_outlet,
                        'outlet_name' => $row->outlet_name,
                        'lunch' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'avg_order_value' => 0.0],
                        'dinner' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'avg_order_value' => 0.0],
                        'total_revenue' => 0.0,
                        'total_orders' => 0,
                        'total_pax' => 0,
                    ];
                }

                $mealKey = strtolower($mealPeriod);
                $slot = &$revenuePerOutletLunchDinner[$regionName]['outlets'][$outletKey][$mealKey];
                $slot['order_count'] += $orders;
                $slot['total_revenue'] += $revenue;
                $slot['total_pax'] += $pax;
                $slot['avg_order_value'] = $slot['order_count'] > 0
                    ? $slot['total_revenue'] / $slot['order_count']
                    : 0.0;
                unset($slot);

                $revenuePerOutletLunchDinner[$regionName][$mealKey]['total_revenue'] += $revenue;
                $revenuePerOutletLunchDinner[$regionName][$mealKey]['total_orders'] += $orders;
                $revenuePerOutletLunchDinner[$regionName][$mealKey]['total_pax'] += $pax;
                $revenuePerOutletLunchDinner[$regionName]['outlets'][$outletKey]['total_revenue'] += $revenue;
                $revenuePerOutletLunchDinner[$regionName]['outlets'][$outletKey]['total_orders'] += $orders;
                $revenuePerOutletLunchDinner[$regionName]['outlets'][$outletKey]['total_pax'] += $pax;
                $revenuePerOutletLunchDinner[$regionName]['total_revenue'] += $revenue;
                $revenuePerOutletLunchDinner[$regionName]['total_orders'] += $orders;
                $revenuePerOutletLunchDinner[$regionName]['total_pax'] += $pax;
            }

            // --- region lunch/dinner uses <=17 definition (legacy) ---
            $regionMeal = $hour <= 17 ? 'lunch' : 'dinner';
            if (! isset($regionLunchDinner[$regionName])) {
                $regionLunchDinner[$regionName] = [
                    'region_code' => $regionCode,
                    'lunch' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'avg_order_value' => 0.0],
                    'dinner' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'avg_order_value' => 0.0],
                ];
            }
            $regionLunchDinner[$regionName][$regionMeal]['order_count'] += $orders;
            $regionLunchDinner[$regionName][$regionMeal]['total_revenue'] += $revenue;
            $regionLunchDinner[$regionName][$regionMeal]['total_pax'] += $pax;

            // --- weekend/weekday per outlet ---
            if (! isset($revenuePerOutletWeekendWeekday[$regionName])) {
                $revenuePerOutletWeekendWeekday[$regionName] = [
                    'region_code' => $regionCode,
                    'outlets' => [],
                    'weekend' => ['total_revenue' => 0.0, 'total_orders' => 0, 'total_pax' => 0],
                    'weekday' => ['total_revenue' => 0.0, 'total_orders' => 0, 'total_pax' => 0],
                    'total_revenue' => 0.0,
                    'total_orders' => 0,
                    'total_pax' => 0,
                ];
            }
            if (! isset($revenuePerOutletWeekendWeekday[$regionName]['outlets'][$outletKey])) {
                $revenuePerOutletWeekendWeekday[$regionName]['outlets'][$outletKey] = [
                    'outlet_code' => $row->kode_outlet,
                    'outlet_name' => $row->outlet_name,
                    'weekend' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'avg_order_value' => 0.0],
                    'weekday' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'avg_order_value' => 0.0],
                    'total_revenue' => 0.0,
                    'total_orders' => 0,
                    'total_pax' => 0,
                ];
            }

            $dayKey = strtolower($dayType);
            $daySlot = &$revenuePerOutletWeekendWeekday[$regionName]['outlets'][$outletKey][$dayKey];
            $daySlot['order_count'] += $orders;
            $daySlot['total_revenue'] += $revenue;
            $daySlot['total_pax'] += $pax;
            $daySlot['avg_order_value'] = $daySlot['order_count'] > 0
                ? $daySlot['total_revenue'] / $daySlot['order_count']
                : 0.0;
            unset($daySlot);

            $revenuePerOutletWeekendWeekday[$regionName][$dayKey]['total_revenue'] += $revenue;
            $revenuePerOutletWeekendWeekday[$regionName][$dayKey]['total_orders'] += $orders;
            $revenuePerOutletWeekendWeekday[$regionName][$dayKey]['total_pax'] += $pax;
            $revenuePerOutletWeekendWeekday[$regionName]['outlets'][$outletKey]['total_revenue'] += $revenue;
            $revenuePerOutletWeekendWeekday[$regionName]['outlets'][$outletKey]['total_orders'] += $orders;
            $revenuePerOutletWeekendWeekday[$regionName]['outlets'][$outletKey]['total_pax'] += $pax;
            $revenuePerOutletWeekendWeekday[$regionName]['total_revenue'] += $revenue;
            $revenuePerOutletWeekendWeekday[$regionName]['total_orders'] += $orders;
            $revenuePerOutletWeekendWeekday[$regionName]['total_pax'] += $pax;

            if (! isset($regionWeekdayWeekend[$regionName])) {
                $regionWeekdayWeekend[$regionName] = [
                    'region_code' => $regionCode,
                    'weekday' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'avg_order_value' => 0.0],
                    'weekend' => ['order_count' => 0, 'total_revenue' => 0.0, 'total_pax' => 0, 'avg_order_value' => 0.0],
                ];
            }
            $regionWeekdayWeekend[$regionName][$dayKey]['order_count'] += $orders;
            $regionWeekdayWeekend[$regionName][$dayKey]['total_revenue'] += $revenue;
            $regionWeekdayWeekend[$regionName][$dayKey]['total_pax'] += $pax;
        }

        // Finalize outlet lists + avg_order_value
        foreach ($revenuePerOutlet as $regionName => &$region) {
            $outlets = [];
            foreach ($region['outlets'] as $outlet) {
                $outlet['avg_order_value'] = $outlet['order_count'] > 0
                    ? $outlet['total_revenue'] / $outlet['order_count']
                    : 0.0;
                $outlets[] = $outlet;
            }
            usort($outlets, fn ($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);
            $region['outlets'] = $outlets;
        }
        unset($region);

        foreach ($revenuePerOutletLunchDinner as $regionName => &$region) {
            $region['outlets'] = array_values($region['outlets']);
        }
        unset($region);

        foreach ($revenuePerOutletWeekendWeekday as $regionName => &$region) {
            $region['outlets'] = array_values($region['outlets']);
        }
        unset($region);

        foreach ($regionLunchDinner as $regionName => &$data) {
            foreach (['lunch', 'dinner'] as $meal) {
                $c = $data[$meal]['order_count'];
                $data[$meal]['avg_order_value'] = $c > 0 ? $data[$meal]['total_revenue'] / $c : 0.0;
            }
        }
        unset($data);

        foreach ($regionWeekdayWeekend as $regionName => &$data) {
            foreach (['weekday', 'weekend'] as $day) {
                $c = $data[$day]['order_count'];
                $data[$day]['avg_order_value'] = $c > 0 ? $data[$day]['total_revenue'] / $c : 0.0;
            }
        }
        unset($data);

        $totalRevenueList = array_values(array_map(function ($row) {
            return [
                'region_name' => $row['region_name'],
                'region_code' => $row['region_code'],
                'total_orders' => $row['total_orders'],
                'total_revenue' => $row['total_revenue'],
                'total_pax' => $row['total_pax'],
                'avg_order_value' => $row['total_orders'] > 0
                    ? $row['total_revenue'] / $row['total_orders']
                    : 0.0,
            ];
        }, $regionTotals));
        usort($totalRevenueList, fn ($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        return [
            'revenuePerOutlet' => $revenuePerOutlet,
            'revenuePerOutletLunchDinner' => $revenuePerOutletLunchDinner,
            'revenuePerOutletWeekendWeekday' => $revenuePerOutletWeekendWeekday,
            'revenuePerRegion' => [
                'total_revenue' => $totalRevenueList,
                'lunch_dinner' => $regionLunchDinner,
                'weekday_weekend' => $regionWeekdayWeekend,
            ],
        ];
    }
}
