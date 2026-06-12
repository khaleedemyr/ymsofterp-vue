<?php

namespace App\Services;

use App\Http\Controllers\AttendanceReportController;
use App\Http\Traits\ReportHelperTrait;
use App\Models\UserRegional;
use App\Services\PayrollGajiSplitCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OutletAnalyzerService
{
    use ReportHelperTrait;

    private const GSI_SUBJECT_COLUMNS = [
        'rating_service' => 'Quality of Service',
        'rating_food' => 'Quality of Food',
        'rating_beverage' => 'Quality of Beverage',
        'rating_cleanliness' => 'Quality of Cleanliness',
        'rating_staff' => 'Attentiveness of Staff',
        'rating_value' => 'Value for Money',
    ];

    /** @var list<string> */
    private const FOOD_ITEM_TYPES = ['Food Asian', 'Food Western', 'Food'];

    public function __construct(
        private RegionalVisitAnalyticsService $regionalVisits,
        private AttendanceReportController $attendanceReport,
        private ModalEngineeringService $modalEngineering,
    ) {}

    /**
     * @return array{month: string, start_date: string, end_date: string, label: string}
     */
    public function calendarPeriod(string $month): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [
            'month' => $month,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'label' => $start->locale('id')->translatedFormat('F Y'),
        ];
    }

    /**
     * Periode payroll/absensi: tanggal 26 bulan sebelumnya s/d 25 bulan terpilih.
     *
     * @return array{
     *     month: int,
     *     year: int,
     *     start_date: string,
     *     end_date: string,
     *     label: string
     * }
     */
    public function payrollPeriod(string $month): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $year = (int) substr($month, 0, 4);
        $payrollMonth = (int) substr($month, 5, 2);
        $startDate = date('Y-m-d', strtotime("$year-$payrollMonth-26 -1 month"));
        $endDate = date('Y-m-d', strtotime("$year-$payrollMonth-25"));

        return [
            'month' => $payrollMonth,
            'year' => $year,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'label' => Carbon::parse($startDate)->format('d/m/Y').' - '.Carbon::parse($endDate)->format('d/m/Y'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function analyze(int $outletId, string $month): ?array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet', 'qr_code', 'is_outlet')
            ->first();

        if (! $outlet) {
            return null;
        }

        $period = $this->calendarPeriod($month);
        $start = $period['start_date'];
        $end = $period['end_date'];
        $payrollPeriod = $this->payrollPeriod($month);

        $revenue = $this->getRevenue($outlet, $start, $end);
        $fj = $this->getFjInventory((int) $outlet->id_outlet, (string) $outlet->nama_outlet, $start, $end);
        $pettyCash = $this->getPettyCash((int) $outlet->id_outlet, $start, $end);
        $prOps = $this->getPrOpsExpenditure((int) $outlet->id_outlet, $start, $end);
        $payroll = $this->getPayroll((int) $outlet->id_outlet, $month);
        $categoryCost = $this->getCategoryCostOutlet((int) $outlet->id_outlet, $start, $end);

        return [
            'outlet' => [
                'id_outlet' => (int) $outlet->id_outlet,
                'nama_outlet' => (string) $outlet->nama_outlet,
            ],
            'period' => $period,
            'revenue' => $revenue,
            'cashflow_summary' => $this->buildCashflowSummary($revenue, $fj, $pettyCash, $prOps, $payroll),
            'top_menu_items' => $this->getTopMenuItems($outlet, $start, $end),
            'waiter_upsell_ranking' => $this->getWaiterUpsellRanking($outlet, $start, $end),
            'guest_comment_gsi' => $this->getGuestCommentGsi((int) $outlet->id_outlet, $start, $end),
            'google_review_gsi' => $this->getGoogleReviewGsi((int) $outlet->id_outlet, (string) $outlet->nama_outlet, $start, $end),
            'regional_visits' => $this->getRegionalVisits((int) $outlet->id_outlet, $start, $end),
            'fj_inventory' => $fj,
            'petty_cash' => $pettyCash,
            'pr_ops_expenditure' => $prOps,
            'payroll' => $payroll,
            'category_cost_outlet' => $categoryCost,
            'modal_x_engineering' => $this->modalEngineering->totalsForPeriod(
                (int) $outlet->id_outlet,
                $start,
                $end,
                (string) ($outlet->qr_code ?? ''),
            ),
            'employee_attendance' => $this->getEmployeeAttendance(
                (int) $outlet->id_outlet,
                $payrollPeriod['start_date'],
                $payrollPeriod['end_date'],
                $payrollPeriod,
            ),
        ];
    }

    /**
     * Ringkasan arus kas outlet — revenue vs total pengeluaran operasional periode.
     *
     * @return array{
     *     cash_in: float,
     *     cash_out: float,
     *     net_cashflow: float,
     *     cash_out_percentage: float,
     *     outflows: list<array{key: string, label: string, amount: float}>
     * }
     */
    private function buildCashflowSummary(
        array $revenue,
        array $fj,
        array $pettyCash,
        array $prOps,
        array $payroll,
    ): array {
        $cashIn = round((float) ($revenue['total'] ?? 0), 2);

        $outflows = [
            [
                'key' => 'fj_inventory',
                'label' => 'Food Purchased',
                'amount' => round((float) ($fj['line_total'] ?? 0), 2),
            ],
            [
                'key' => 'petty_cash',
                'label' => 'Petty Cash',
                'amount' => round((float) ($pettyCash['total'] ?? 0), 2),
            ],
            [
                'key' => 'pr_ops',
                'label' => 'PR Ops',
                'amount' => round((float) ($prOps['total'] ?? 0), 2),
            ],
            [
                'key' => 'payroll',
                'label' => 'Payroll',
                'amount' => round((float) ($payroll['total_gaji'] ?? 0), 2),
            ],
        ];

        $cashOut = round(array_sum(array_column($outflows, 'amount')), 2);
        $netCashflow = round($cashIn - $cashOut, 2);

        return [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cashflow' => $netCashflow,
            'cash_out_percentage' => $cashIn > 0 ? round(($cashOut / $cashIn) * 100, 2) : 0.0,
            'outflows' => $outflows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getRevenue(object $outlet, string $start, string $end): array
    {
        $empty = [
            'total' => 0.0,
            'cover' => 0,
            'lunch' => 0.0,
            'dinner' => 0.0,
            'avg_check' => 0.0,
            'order_count' => 0,
            'discount' => 0.0,
            'service_charge' => 0.0,
            'commission_fee' => 0.0,
            'manual_discount' => 0.0,
            'pb1' => 0.0,
            'net_sales' => 0.0,
            'daily' => [],
        ];

        $qrCode = trim((string) ($outlet->qr_code ?? ''));
        if ($qrCode === '') {
            return $empty;
        }

        $orders = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->select(
                'grand_total',
                'pax',
                'created_at',
                'discount',
                'service',
                'commfee',
                'manual_discount_amount',
                'pb1',
            )
            ->get();

        $total = 0.0;
        $cover = 0;
        $lunch = 0.0;
        $dinner = 0.0;
        $discount = 0.0;
        $serviceCharge = 0.0;
        $commissionFee = 0.0;
        $manualDiscount = 0.0;
        $pb1Total = 0.0;
        $netSales = 0.0;
        $daily = [];

        foreach ($orders as $order) {
            $amount = (float) $order->grand_total;
            $orderDiscount = (float) ($order->discount ?? 0);
            $orderService = (float) ($order->service ?? 0);
            $orderCommfee = (float) ($order->commfee ?? 0);
            $orderManualDiscount = (float) ($order->manual_discount_amount ?? 0);
            $orderPb1 = (float) ($order->pb1 ?? 0);
            $orderNetSales = $amount - $orderDiscount - $orderService - $orderCommfee - $orderManualDiscount;
            $pax = (int) ($order->pax ?? 0);
            $date = date('Y-m-d', strtotime((string) $order->created_at));
            $hour = (int) date('G', strtotime((string) $order->created_at));
            $period = $hour <= 17 ? 'lunch' : 'dinner';

            if (! isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'label' => date('d/m', strtotime($date)),
                    'revenue' => 0.0,
                    'cover' => 0,
                    'lunch' => 0.0,
                    'dinner' => 0.0,
                    'orders' => 0,
                    'discount' => 0.0,
                    'service_charge' => 0.0,
                    'commission_fee' => 0.0,
                    'manual_discount' => 0.0,
                    'pb1' => 0.0,
                    'net_sales' => 0.0,
                ];
            }

            $total += $amount;
            $discount += $orderDiscount;
            $serviceCharge += $orderService;
            $commissionFee += $orderCommfee;
            $manualDiscount += $orderManualDiscount;
            $pb1Total += $orderPb1;
            $netSales += $orderNetSales;
            $cover += $pax;
            $daily[$date]['revenue'] += $amount;
            $daily[$date]['discount'] += $orderDiscount;
            $daily[$date]['service_charge'] += $orderService;
            $daily[$date]['commission_fee'] += $orderCommfee;
            $daily[$date]['manual_discount'] += $orderManualDiscount;
            $daily[$date]['pb1'] += $orderPb1;
            $daily[$date]['net_sales'] += $orderNetSales;
            $daily[$date]['cover'] += $pax;
            $daily[$date]['orders']++;

            if ($period === 'lunch') {
                $lunch += $amount;
                $daily[$date]['lunch'] += $amount;
            } else {
                $dinner += $amount;
                $daily[$date]['dinner'] += $amount;
            }
        }

        ksort($daily);

        return [
            'total' => round($total, 2),
            'cover' => $cover,
            'lunch' => round($lunch, 2),
            'dinner' => round($dinner, 2),
            'avg_check' => $cover > 0 ? round($total / $cover, 0) : 0.0,
            'order_count' => $orders->count(),
            'discount' => round($discount, 2),
            'service_charge' => round($serviceCharge, 2),
            'commission_fee' => round($commissionFee, 2),
            'manual_discount' => round($manualDiscount, 2),
            'pb1' => round($pb1Total, 2),
            'net_sales' => round($netSales, 2),
            'daily' => array_values(array_map(function ($row) {
                $row['revenue'] = round($row['revenue'], 2);
                $row['lunch'] = round($row['lunch'], 2);
                $row['dinner'] = round($row['dinner'], 2);
                $row['discount'] = round($row['discount'], 2);
                $row['service_charge'] = round($row['service_charge'], 2);
                $row['commission_fee'] = round($row['commission_fee'], 2);
                $row['manual_discount'] = round($row['manual_discount'], 2);
                $row['pb1'] = round($row['pb1'], 2);
                $row['net_sales'] = round($row['net_sales'], 2);

                return $row;
            }, $daily)),
        ];
    }

    /**
     * @return array{top_food: list<array<string, mixed>>, top_beverages: list<array<string, mixed>>}
     */
    private function getTopMenuItems(object $outlet, string $start, string $end): array
    {
        $empty = [
            'top_food' => [],
            'top_beverages' => [],
        ];

        $qrCode = trim((string) ($outlet->qr_code ?? ''));
        if ($qrCode === '') {
            return $empty;
        }

        return [
            'top_food' => $this->fetchTopOrderItems($qrCode, $start, $end, self::FOOD_ITEM_TYPES),
            'top_beverages' => $this->fetchTopOrderItems($qrCode, $start, $end, ['Beverages']),
        ];
    }

    /**
     * @param  list<string>  $itemTypes
     * @return list<array<string, mixed>>
     */
    private function fetchTopOrderItems(string $qrCode, string $start, string $end, array $itemTypes, int $limit = 10): array
    {
        if ($itemTypes === []) {
            return [];
        }

        return DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('items as i', 'oi.item_id', '=', 'i.id')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $start)
            ->whereDate('o.created_at', '<=', $end)
            ->where('o.status', '!=', 'cancelled')
            ->where('o.grand_total', '>', 0)
            ->whereIn('i.type', $itemTypes)
            ->selectRaw('
                oi.item_id,
                MAX(oi.item_name) as item_name,
                SUM(oi.qty) as total_qty,
                SUM(oi.subtotal) as total_revenue,
                COUNT(DISTINCT oi.order_id) as order_count
            ')
            ->groupBy('oi.item_id')
            ->orderByDesc('total_qty')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'item_id' => (int) $row->item_id,
                'item_name' => (string) ($row->item_name ?: '-'),
                'total_qty' => round((float) ($row->total_qty ?? 0), 2),
                'total_revenue' => round((float) ($row->total_revenue ?? 0), 2),
                'order_count' => (int) ($row->order_count ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Ranking waiter — total revenue (grand_total) per waiter dari orders.
     *
     * @return array{top: list<array<string, mixed>>, rest: list<array<string, mixed>>}
     */
    private function getWaiterUpsellRanking(object $outlet, string $start, string $end): array
    {
        $empty = ['top' => [], 'rest' => []];

        $qrCode = trim((string) ($outlet->qr_code ?? ''));
        if ($qrCode === '') {
            return $empty;
        }

        $rows = DB::table('orders as o')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $start)
            ->whereDate('o.created_at', '<=', $end)
            ->where('o.status', '!=', 'cancelled')
            ->where('o.grand_total', '>', 0)
            ->whereNotNull('o.waiters')
            ->where('o.waiters', '!=', '')
            ->where('o.waiters', '!=', '-')
            ->selectRaw('
                o.waiters AS waiter_name,
                SUM(o.grand_total) AS total_revenue,
                COUNT(o.id) AS order_count,
                SUM(COALESCE(o.pax, 0)) AS cover
            ')
            ->groupBy('o.waiters')
            ->orderByDesc('total_revenue')
            ->orderByDesc('order_count')
            ->get();

        if ($rows->isEmpty()) {
            return $empty;
        }

        $waiterNames = $rows->pluck('waiter_name')->filter()->values()->all();
        $usersByWaiterName = $this->resolveUsersForWaiterNames(
            $waiterNames,
            (int) $outlet->id_outlet,
        );

        $ranked = $this->mapWaiterRankingRows($rows, $usersByWaiterName);

        return [
            'top' => array_slice($ranked, 0, 3),
            'rest' => array_slice($ranked, 3),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @param  array<string, object>  $usersByWaiterName
     * @return list<array<string, mixed>>
     */
    private function mapWaiterRankingRows($rows, array $usersByWaiterName): array
    {
        return $rows->values()->map(function ($row, $index) use ($usersByWaiterName) {
            $waiterName = (string) ($row->waiter_name ?? '-');
            $user = $usersByWaiterName[$waiterName] ?? null;

            return [
                'rank' => $index + 1,
                'waiter_name' => $waiterName,
                'user_id' => $user ? (int) $user->id : null,
                'avatar' => $this->resolveUserAvatarPath($user),
                'total_revenue' => round((float) ($row->total_revenue ?? 0), 2),
                'order_count' => (int) ($row->order_count ?? 0),
                'cover' => (int) ($row->cover ?? 0),
            ];
        })->all();
    }

    /**
     * @param  list<string>  $waiterNames
     * @return array<string, object>
     */
    private function resolveUsersForWaiterNames(array $waiterNames, int $outletId): array
    {
        if ($waiterNames === []) {
            return [];
        }

        $targetKeys = [];
        foreach ($waiterNames as $name) {
            $targetKeys[$this->normalizeWaiterName($name)] = $name;
        }

        $users = DB::table('users')
            ->where('status', 'A')
            ->where(function ($q) use ($outletId) {
                $q->where('id_outlet', $outletId)
                    ->orWhere('id_outlet', 1);
            })
            ->whereNotNull('nama_lengkap')
            ->where('nama_lengkap', '!=', '')
            ->select('id', 'nama_lengkap', 'nama_panggilan', 'avatar', 'upload_latest_color_photo')
            ->get();

        $usersByNormalized = [];
        foreach ($users as $user) {
            foreach ([(string) $user->nama_lengkap, (string) ($user->nama_panggilan ?? '')] as $label) {
                $key = $this->normalizeWaiterName($label);
                if ($key === '' || isset($usersByNormalized[$key])) {
                    continue;
                }
                $usersByNormalized[$key] = $user;
            }
        }

        $resolved = [];
        foreach ($waiterNames as $waiterName) {
            $user = $usersByNormalized[$this->normalizeWaiterName($waiterName)] ?? null;
            if ($user) {
                $resolved[$waiterName] = $user;
            }
        }

        return $resolved;
    }

    private function resolveUserAvatarPath(?object $user): ?string
    {
        if (! $user) {
            return null;
        }

        foreach (['avatar', 'upload_latest_color_photo'] as $field) {
            $path = trim((string) ($user->{$field} ?? ''));
            if ($path !== '') {
                return $path;
            }
        }

        return null;
    }

    private function normalizeWaiterName(string $name): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));

        return $normalized;
    }

    /**
     * @return array{overall_pct: ?float, total_forms: int, total_responses: int}
     */
    private function getGuestCommentGsi(int $outletId, string $start, string $end): array
    {
        $base = DB::table('guest_comment_forms')
            ->where('status', 'verified')
            ->where('id_outlet', $outletId)
            ->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59']);

        $totalForms = (int) (clone $base)->count();
        $overallPositive = 0;
        $overallResponses = 0;

        $subjects = [];
        foreach (self::GSI_SUBJECT_COLUMNS as $column => $label) {
            $r = (clone $base)
                ->selectRaw("
                    SUM(CASE WHEN {$column} = 'excellent' THEN 1 ELSE 0 END) AS excellent,
                    SUM(CASE WHEN {$column} = 'good' THEN 1 ELSE 0 END) AS good,
                    SUM(CASE WHEN {$column} = 'average' THEN 1 ELSE 0 END) AS average,
                    SUM(CASE WHEN {$column} = 'poor' THEN 1 ELSE 0 END) AS poor
                ")
                ->first();

            $excellent = (int) ($r->excellent ?? 0);
            $good = (int) ($r->good ?? 0);
            $average = (int) ($r->average ?? 0);
            $poor = (int) ($r->poor ?? 0);
            $responses = $excellent + $good + $average + $poor;
            $positive = $excellent + $good;
            $overallPositive += $positive;
            $overallResponses += $responses;

            $subjects[] = [
                'subject' => $label,
                'excellent' => $excellent,
                'good' => $good,
                'average' => $average,
                'poor' => $poor,
                'responses' => $responses,
                'gsi_pct' => $responses > 0 ? round(($positive / $responses) * 100, 2) : null,
            ];
        }

        return [
            'overall_pct' => $overallResponses > 0
                ? round(($overallPositive / $overallResponses) * 100, 2)
                : null,
            'total_forms' => $totalForms,
            'total_responses' => $overallResponses,
            'subjects' => $subjects,
        ];
    }

    /**
     * @return array{overall_pct: ?float, total_reviews: int, positive_reviews: int, sources: array<string, int>}
     */
    private function getGoogleReviewGsi(int $outletId, string $outletName, string $start, string $end): array
    {
        $positive = 0;
        $total = 0;
        $sources = ['manual' => 0, 'ai_classified' => 0];
        $items = [];

        $manualRows = DB::table('google_review_manual_reviews')
            ->where('id_outlet', $outletId)
            ->where(function ($q) {
                $q->where('is_active', 1)->orWhereNull('is_active');
            })
            ->whereDate('review_date', '>=', $start)
            ->whereDate('review_date', '<=', $end)
            ->select('author', 'rating', 'review_date', 'text')
            ->orderByDesc('review_date')
            ->get();

        foreach ($manualRows as $row) {
            $score = (float) $row->rating;
            if ($score <= 0) {
                continue;
            }
            $total++;
            $sources['manual']++;
            $isPositive = $score >= 4;
            if ($isPositive) {
                $positive++;
            }
            $items[] = [
                'source' => 'Manual',
                'author' => (string) ($row->author ?? '-'),
                'rating' => $score,
                'review_date' => $row->review_date,
                'text' => mb_substr((string) ($row->text ?? ''), 0, 200),
                'is_positive' => $isPositive,
            ];
        }

        $aiRows = DB::table('google_review_ai_items as i')
            ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
            ->where('r.status', 'completed')
            ->whereIn('r.source', ['apify_dataset', 'places_api', 'scraper_inline', 'manual_db'])
            ->where(function ($q) use ($outletId, $outletName) {
                $q->where('r.id_outlet', $outletId)
                    ->orWhere('r.nama_outlet', $outletName);
            })
            ->whereDate('i.created_at', '>=', $start)
            ->whereDate('i.created_at', '<=', $end)
            ->select('i.author', 'i.rating', 'i.review_date', 'i.text', 'i.severity', 'r.source as report_source')
            ->orderByDesc('i.created_at')
            ->get();

        foreach ($aiRows as $row) {
            $total++;
            $sources['ai_classified']++;
            $severity = strtolower(trim((string) ($row->severity ?? '')));
            $score = (float) $row->rating;
            $isPositive = $severity === 'positive' || $score >= 4;
            if ($isPositive) {
                $positive++;
            }
            $items[] = [
                'source' => match ((string) ($row->report_source ?? '')) {
                    'apify_dataset' => 'Apify',
                    'places_api' => 'Places API',
                    'scraper_inline' => 'Scraper',
                    default => 'AI Report',
                },
                'author' => (string) ($row->author ?? '-'),
                'rating' => $score > 0 ? $score : null,
                'review_date' => $row->review_date,
                'text' => mb_substr((string) ($row->text ?? ''), 0, 200),
                'severity' => $severity ?: null,
                'is_positive' => $isPositive,
            ];
        }

        usort($items, fn ($a, $b) => strcmp((string) ($b['review_date'] ?? ''), (string) ($a['review_date'] ?? '')));

        return [
            'overall_pct' => $total > 0 ? round(($positive / $total) * 100, 2) : null,
            'total_reviews' => $total,
            'positive_reviews' => $positive,
            'sources' => $sources,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getRegionalVisits(int $outletId, string $start, string $end): array
    {
        $regionalUsers = DB::table('user_regional as ur')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->where('u.status', 'A')
            ->select('ur.user_id', 'ur.area', 'u.nama_lengkap')
            ->get();

        $regionalUserIds = $regionalUsers->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all();
        $userAreaMap = $regionalUsers->keyBy('user_id');

        $empty = [
            'visit_days' => 0,
            'scan_in_count' => 0,
            'total_hours' => 0.0,
            'unique_visitors' => 0,
            'visitors' => [],
            'areas' => [],
            'daily_visits' => [],
            'hourly_frequency' => null,
        ];

        if (empty($regionalUserIds)) {
            return $empty;
        }

        $detail = $this->regionalVisits->getOutletVisitDetail($regionalUserIds, $outletId, $start, $end);
        $visitorMap = [];
        $areaMap = [];
        $visitorVisitDates = [];
        $areaVisitDates = [];
        $memberVisitDates = [];

        foreach (UserRegional::AREAS as $areaName) {
            $areaMap[$areaName] = [
                'area' => $areaName,
                'visit_days' => 0,
                'total_hours' => 0.0,
                'scan_in_count' => 0,
                'members' => [],
            ];
        }

        foreach ($detail['daily_visits'] ?? [] as $day) {
            $tanggal = (string) ($day['tanggal'] ?? '');
            if ($tanggal === '') {
                continue;
            }

            foreach ($day['sessions'] ?? [] as $session) {
                $uid = (int) ($session['user_id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }

                $regionalUser = $userAreaMap->get($uid);
                $area = (string) ($regionalUser->area ?? 'Lainnya');
                if (! isset($areaMap[$area])) {
                    $areaMap[$area] = [
                        'area' => $area,
                        'visit_days' => 0,
                        'total_hours' => 0.0,
                        'scan_in_count' => 0,
                        'members' => [],
                    ];
                }

                $hours = round(((int) ($session['durasi_menit'] ?? 0)) / 60, 2);
                $scanIn = (int) ($session['scan_in_count'] ?? 0);

                if (! isset($visitorMap[$uid])) {
                    $visitorMap[$uid] = [
                        'id' => $uid,
                        'name' => (string) ($session['user_name'] ?? ($regionalUser->nama_lengkap ?? '-')),
                        'area' => $area,
                        'nama_jabatan' => (string) ($session['nama_jabatan'] ?? '-'),
                        'visit_days' => 0,
                        'total_hours' => 0.0,
                        'scan_in_count' => 0,
                    ];
                }

                $visitorMap[$uid]['total_hours'] = round($visitorMap[$uid]['total_hours'] + $hours, 2);
                $visitorMap[$uid]['scan_in_count'] += $scanIn;

                if (! isset($visitorVisitDates[$uid][$tanggal])) {
                    $visitorVisitDates[$uid][$tanggal] = true;
                    $visitorMap[$uid]['visit_days']++;
                }

                $areaMap[$area]['total_hours'] = round($areaMap[$area]['total_hours'] + $hours, 2);
                $areaMap[$area]['scan_in_count'] += $scanIn;

                if (! isset($areaVisitDates[$area][$tanggal])) {
                    $areaVisitDates[$area][$tanggal] = true;
                    $areaMap[$area]['visit_days']++;
                }

                if (! isset($areaMap[$area]['members'][$uid])) {
                    $areaMap[$area]['members'][$uid] = [
                        'id' => $uid,
                        'name' => $visitorMap[$uid]['name'],
                        'visit_days' => 0,
                        'total_hours' => 0.0,
                    ];
                }

                $areaMap[$area]['members'][$uid]['total_hours'] = round(
                    $areaMap[$area]['members'][$uid]['total_hours'] + $hours,
                    2,
                );

                $memberKey = $area.'_'.$uid;
                if (! isset($memberVisitDates[$memberKey][$tanggal])) {
                    $memberVisitDates[$memberKey][$tanggal] = true;
                    $areaMap[$area]['members'][$uid]['visit_days']++;
                }
            }
        }

        $visitors = collect($visitorMap)->sortByDesc('visit_days')->values()->all();

        $areas = collect($areaMap)
            ->map(function ($row) {
                $row['members'] = collect($row['members'] ?? [])
                    ->sortByDesc('visit_days')
                    ->values()
                    ->all();
                $row['total_hours'] = round((float) $row['total_hours'], 2);

                return $row;
            })
            ->filter(fn ($row) => $row['visit_days'] > 0)
            ->sortByDesc('visit_days')
            ->values()
            ->all();

        return [
            'visit_days' => (int) ($detail['summary']['visit_days'] ?? 0),
            'scan_in_count' => (int) ($detail['summary']['scan_in_count'] ?? 0),
            'total_hours' => (float) ($detail['summary']['total_hours'] ?? 0),
            'unique_visitors' => (int) ($detail['summary']['unique_visitors'] ?? 0),
            'visitors' => $visitors,
            'areas' => $areas,
            'daily_visits' => $detail['daily_visits'] ?? [],
            'hourly_frequency' => $detail['hourly_frequency'] ?? null,
        ];
    }

    /**
     * @return array<string, float|array<string, mixed>>
     */
    private function getFjInventory(int $outletId, string $outletName, string $start, string $end): array
    {
        $totals = [
            'main_kitchen' => 0.0,
            'main_store' => 0.0,
            'chemical' => 0.0,
            'stationary' => 0.0,
            'marketing' => 0.0,
            'line_total' => 0.0,
        ];

        $foodRows = $this->rekapFjFetchFoodGrPivotItemRows($start, $end)
            ->filter(fn ($row) => (string) $row->customer === $outletName);
        $serialRows = $this->rekapFjFetchSerialGrPivotItemRows($start, $end)
            ->filter(fn ($row) => (string) $row->customer === $outletName);

        $aggregated = $this->rekapFjAggregatePivotItemRowsByOutlet($foodRows->concat($serialRows));
        $row = $aggregated[$outletName] ?? null;

        if ($row) {
            $totals['main_kitchen'] = (float) $row->main_kitchen;
            $totals['main_store'] = (float) $row->main_store;
            $totals['chemical'] = (float) $row->chemical;
            $totals['stationary'] = (float) $row->stationary;
            $totals['marketing'] = (float) $row->marketing;
            $totals['line_total'] = (float) $row->line_total;
        }

        $retailContraBon = $this->fetchRetailFoodContraBonFjBuckets($outletId, $start, $end);
        foreach (array_keys($totals) as $key) {
            $totals[$key] += (float) ($retailContraBon[$key] ?? 0);
        }

        $categories = [
            ['key' => 'main_kitchen', 'label' => 'Main Kitchen', 'amount' => round($totals['main_kitchen'], 2)],
            ['key' => 'main_store', 'label' => 'Main Store', 'amount' => round($totals['main_store'], 2)],
            ['key' => 'chemical', 'label' => 'Chemical', 'amount' => round($totals['chemical'], 2)],
            ['key' => 'stationary', 'label' => 'Stationary', 'amount' => round($totals['stationary'], 2)],
            ['key' => 'marketing', 'label' => 'Marketing', 'amount' => round($totals['marketing'], 2)],
        ];

        $foodGrTransactions = $this->fetchFjFoodGrTransactions($outletId, $start, $end);
        $serialGrTransactions = $this->fetchFjSerialGrTransactions($outletId, $start, $end);
        $retailContraBonTransactions = $this->fetchFjRetailFoodContraBonTransactions($outletId, $start, $end);

        $sources = [
            [
                'key' => 'food_gr',
                'label' => 'GR Food',
                'amount' => round(array_sum(array_column($foodGrTransactions, 'total_amount')), 2),
                'count' => count($foodGrTransactions),
            ],
            [
                'key' => 'serial_gr',
                'label' => 'GR Serial',
                'amount' => round(array_sum(array_column($serialGrTransactions, 'total_amount')), 2),
                'count' => count($serialGrTransactions),
            ],
            [
                'key' => 'retail_food_contra_bon',
                'label' => 'Retail Food Contra Bon',
                'amount' => round(array_sum(array_column($retailContraBonTransactions, 'total_amount')), 2),
                'count' => count($retailContraBonTransactions),
            ],
        ];

        return [
            'main_kitchen' => round($totals['main_kitchen'], 2),
            'main_store' => round($totals['main_store'], 2),
            'chemical' => round($totals['chemical'], 2),
            'stationary' => round($totals['stationary'], 2),
            'marketing' => round($totals['marketing'], 2),
            'line_total' => round($totals['line_total'], 2),
            'categories' => $categories,
            'retail_food_contra_bon_total' => round((float) ($retailContraBon['line_total'] ?? 0), 2),
            'transaction_count' => count($foodGrTransactions) + count($serialGrTransactions) + count($retailContraBonTransactions),
            'sources' => array_values(array_filter($sources, fn ($row) => $row['count'] > 0 || $row['amount'] > 0)),
            'transactions' => [
                'food_gr' => $foodGrTransactions,
                'serial_gr' => $serialGrTransactions,
                'retail_food_contra_bon' => $retailContraBonTransactions,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFjFoodGrTransactions(int $outletId, string $start, string $end): array
    {
        $headers = DB::table('outlet_food_good_receives as gr')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('users as u', 'gr.created_by', '=', 'u.id')
            ->where('gr.outlet_id', $outletId)
            ->whereNull('gr.deleted_at')
            ->whereDate('gr.receive_date', '>=', $start)
            ->whereDate('gr.receive_date', '<=', $end)
            ->select(
                'gr.id',
                'gr.number',
                'gr.receive_date',
                'gr.notes',
                'do.number as delivery_order_number',
                'u.nama_lengkap as creator_name',
            )
            ->orderByDesc('gr.receive_date')
            ->orderByDesc('gr.id')
            ->get();

        if ($headers->isEmpty()) {
            return [];
        }

        $itemsByGr = $this->fetchFjFoodGrItemsByHeaderIds(
            $headers->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        return $headers
            ->map(function ($row) use ($itemsByGr) {
                $items = $itemsByGr[(int) $row->id] ?? [];
                $total = round(array_sum(array_column($items, 'subtotal')), 2);
                if ($total <= 0) {
                    return null;
                }

                return $this->mapFjTransaction($row, 'food_gr', 'GR Food', $items, $total, [
                    'reference' => (string) ($row->delivery_order_number ?? '-'),
                ]);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $headerIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchFjFoodGrItemsByHeaderIds(array $headerIds): array
    {
        if ($headerIds === []) {
            return [];
        }

        $effectivePriceExpr = $this->rekapFjFoodGrEffectivePriceSql();
        $grouped = [];

        DB::table('outlet_food_good_receive_items as i')
            ->join('outlet_food_good_receives as gr', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereIn('gr.id', $headerIds)
            ->whereNull('gr.deleted_at')
            ->whereNotNull('w.name')
            ->select(
                'gr.id as header_id',
                'it.name as item_name',
                'u.name as unit',
                DB::raw('i.received_qty as qty'),
                DB::raw("{$effectivePriceExpr} as price"),
                DB::raw("i.received_qty * {$effectivePriceExpr} as subtotal"),
                'w.name as warehouse',
            )
            ->orderBy('it.name')
            ->get()
            ->each(function ($row) use (&$grouped) {
                $subtotal = round((float) ($row->subtotal ?? 0), 2);
                if ($subtotal <= 0) {
                    return;
                }

                $headerId = (int) $row->header_id;
                $grouped[$headerId][] = [
                    'item_name' => (string) ($row->item_name ?? '-'),
                    'qty' => round((float) ($row->qty ?? 0), 3),
                    'unit' => (string) ($row->unit ?? ''),
                    'price' => round((float) ($row->price ?? 0), 2),
                    'subtotal' => $subtotal,
                    'warehouse' => (string) ($row->warehouse ?? '-'),
                ];
            });

        return $grouped;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFjSerialGrTransactions(int $outletId, string $start, string $end): array
    {
        if (! $this->rekapFjHasSerialGrTables()) {
            return [];
        }

        $headers = DB::table('outlet_serial_receive_headers as h')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->where('h.outlet_id', $outletId)
            ->whereNull('h.deleted_at')
            ->whereDate('h.receive_date', '>=', $start)
            ->whereDate('h.receive_date', '<=', $end)
            ->select(
                'h.id',
                'h.number',
                'h.receive_date',
                'h.notes',
                'u.nama_lengkap as creator_name',
            )
            ->orderByDesc('h.receive_date')
            ->orderByDesc('h.id')
            ->get();

        if ($headers->isEmpty()) {
            return [];
        }

        $itemsByHeader = $this->fetchFjSerialGrItemsByHeaderIds(
            $headers->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        return $headers
            ->map(function ($row) use ($itemsByHeader) {
                $items = $itemsByHeader[(int) $row->id] ?? [];
                $total = round(array_sum(array_column($items, 'subtotal')), 2);
                if ($total <= 0) {
                    return null;
                }

                return $this->mapFjTransaction($row, 'serial_gr', 'GR Serial', $items, $total);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $headerIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchFjSerialGrItemsByHeaderIds(array $headerIds): array
    {
        if ($headerIds === []) {
            return [];
        }

        $effectivePriceExpr = $this->rekapFjSerialGrEffectivePriceSql();
        $grouped = [];

        DB::table('outlet_serial_receive_items as si')
            ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
            ->join('items as it', 'si.item_id', '=', 'it.id')
            ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereIn('h.id', $headerIds)
            ->whereNull('h.deleted_at')
            ->whereNotNull('w.name')
            ->select(
                'h.id as header_id',
                'it.name as item_name',
                'u.name as unit',
                DB::raw('si.qty as qty'),
                DB::raw("{$effectivePriceExpr} as price"),
                DB::raw("si.qty * {$effectivePriceExpr} as subtotal"),
                'w.name as warehouse',
            )
            ->orderBy('it.name')
            ->get()
            ->each(function ($row) use (&$grouped) {
                $subtotal = round((float) ($row->subtotal ?? 0), 2);
                if ($subtotal <= 0) {
                    return;
                }

                $headerId = (int) $row->header_id;
                $grouped[$headerId][] = [
                    'item_name' => (string) ($row->item_name ?? '-'),
                    'qty' => round((float) ($row->qty ?? 0), 3),
                    'unit' => (string) ($row->unit ?? ''),
                    'price' => round((float) ($row->price ?? 0), 2),
                    'subtotal' => $subtotal,
                    'warehouse' => (string) ($row->warehouse ?? '-'),
                ];
            });

        return $grouped;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFjRetailFoodContraBonTransactions(int $outletId, string $start, string $end): array
    {
        $rows = DB::table('retail_food as rf')
            ->leftJoin('users as u', 'rf.created_by', '=', 'u.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.payment_method', 'contra_bon')
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereDate('rf.transaction_date', '>=', $start)
            ->whereDate('rf.transaction_date', '<=', $end)
            ->select(
                'rf.id',
                'rf.retail_number',
                'rf.transaction_date',
                'rf.total_amount',
                'rf.notes',
                'u.nama_lengkap as creator_name',
            )
            ->orderByDesc('rf.transaction_date')
            ->orderByDesc('rf.id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $itemsByRetail = $this->fetchFjRetailFoodContraBonItems(
            $rows->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        return $rows
            ->map(function ($row) use ($itemsByRetail) {
                $items = $itemsByRetail[(int) $row->id] ?? [];
                $total = round(array_sum(array_column($items, 'subtotal')), 2);
                if ($total <= 0) {
                    $total = round((float) ($row->total_amount ?? 0), 2);
                }
                if ($total <= 0) {
                    return null;
                }

                return $this->mapFjTransaction(
                    $row,
                    'retail_food_contra_bon',
                    'Retail Food Contra Bon',
                    $items,
                    $total,
                    ['document_number' => (string) ($row->retail_number ?? '-')],
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $retailFoodIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchFjRetailFoodContraBonItems(array $retailFoodIds): array
    {
        if ($retailFoodIds === []) {
            return [];
        }

        $grouped = [];
        DB::table('retail_food_items as rfi')
            ->join('retail_food as rf', 'rf.id', '=', 'rfi.retail_food_id')
            ->leftJoin('items as it', 'rfi.item_name', '=', 'it.name')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereIn('rf.id', $retailFoodIds)
            ->select(
                'rf.id as retail_food_id',
                'rfi.item_name',
                'rfi.qty',
                'rfi.unit',
                'rfi.price',
                'rfi.subtotal',
                'w.name as warehouse',
            )
            ->orderBy('rfi.id')
            ->get()
            ->each(function ($row) use (&$grouped) {
                $subtotal = round((float) ($row->subtotal ?? 0), 2);
                if ($subtotal <= 0) {
                    return;
                }

                $parentId = (int) $row->retail_food_id;
                $grouped[$parentId][] = [
                    'item_name' => (string) ($row->item_name ?? '-'),
                    'qty' => round((float) ($row->qty ?? 0), 3),
                    'unit' => (string) ($row->unit ?? ''),
                    'price' => round((float) ($row->price ?? 0), 2),
                    'subtotal' => $subtotal,
                    'warehouse' => (string) ($row->warehouse ?? '-'),
                ];
            });

        return $grouped;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mapFjTransaction(
        object $row,
        string $source,
        string $sourceLabel,
        array $items,
        float $total,
        array $overrides = [],
    ): array {
        $documentNumber = (string) ($overrides['document_number'] ?? $row->number ?? $row->retail_number ?? '-');

        return [
            'id' => (int) $row->id,
            'source' => $source,
            'source_label' => $sourceLabel,
            'document_number' => $documentNumber,
            'transaction_date' => $row->receive_date ?? $row->transaction_date ?? null,
            'reference' => (string) ($overrides['reference'] ?? '-'),
            'total_amount' => round($total, 2),
            'creator_name' => (string) ($row->creator_name ?? '-'),
            'notes' => mb_substr((string) ($row->notes ?? ''), 0, 120),
            'items' => $items,
        ];
    }

    /**
     * Retail Food payment_method=contra_bon — hanya untuk Outlet Analyzer (bukan Report Rekap FJ).
     *
     * @return array<string, float>
     */
    private function fetchRetailFoodContraBonFjBuckets(int $outletId, string $start, string $end): array
    {
        $totals = [
            'main_kitchen' => 0.0,
            'main_store' => 0.0,
            'chemical' => 0.0,
            'stationary' => 0.0,
            'marketing' => 0.0,
            'line_total' => 0.0,
        ];

        $rows = DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->leftJoin('items as it', 'rfi.item_name', '=', 'it.name')
            ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.payment_method', 'contra_bon')
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereDate('rf.transaction_date', '>=', $start)
            ->whereDate('rf.transaction_date', '<=', $end)
            ->select('rfi.subtotal', 'w.name as warehouse', 'sc.name as sub_category')
            ->get();

        foreach ($rows as $row) {
            $amount = (float) ($row->subtotal ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $totals['line_total'] += $amount;
            $bucket = $this->classifyFjWarehouseBucket(
                $row->warehouse !== null ? (string) $row->warehouse : null,
                $row->sub_category !== null ? (string) $row->sub_category : null,
            );
            if ($bucket !== null) {
                $totals[$bucket] += $amount;
            }
        }

        return array_map(fn ($v) => round((float) $v, 2), $totals);
    }

    /**
     * Pengeluaran petty cash: retail food + retail non food, payment_method selain contra_bon.
     *
     * @return array<string, mixed>
     */
    private function getPettyCash(int $outletId, string $start, string $end): array
    {
        $retailFoodBuckets = $this->fetchRetailFoodPettyCashFjBuckets($outletId, $start, $end);
        $retailFoodTotal = (float) ($retailFoodBuckets['line_total'] ?? 0);

        $retailNonFoodTotal = (float) DB::table('retail_non_food as rnf')
            ->where('rnf.outlet_id', $outletId)
            ->where('rnf.status', 'approved')
            ->whereNull('rnf.deleted_at')
            ->whereDate('rnf.transaction_date', '>=', $start)
            ->whereDate('rnf.transaction_date', '<=', $end)
            ->where(function ($q) {
                $this->applyNonContraBonPaymentFilter($q, 'rnf.payment_method');
            })
            ->sum('rnf.total_amount');

        $retailNonFoodCategories = DB::table('retail_non_food as rnf')
            ->leftJoin('purchase_requisition_categories as prc', 'rnf.category_budget_id', '=', 'prc.id')
            ->where('rnf.outlet_id', $outletId)
            ->where('rnf.status', 'approved')
            ->whereNull('rnf.deleted_at')
            ->whereDate('rnf.transaction_date', '>=', $start)
            ->whereDate('rnf.transaction_date', '<=', $end)
            ->where(function ($q) {
                $this->applyNonContraBonPaymentFilter($q, 'rnf.payment_method');
            })
            ->selectRaw("COALESCE(prc.name, 'Tanpa Kategori') as label, COALESCE(SUM(rnf.total_amount), 0) as amount")
            ->groupBy('prc.id', 'prc.name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'amount' => round((float) $row->amount, 2),
            ])
            ->filter(fn ($row) => $row['amount'] > 0)
            ->values()
            ->all();

        $retailFoodRows = DB::table('retail_food as rf')
            ->leftJoin('users as u', 'rf.created_by', '=', 'u.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereDate('rf.transaction_date', '>=', $start)
            ->whereDate('rf.transaction_date', '<=', $end)
            ->where(function ($q) {
                $this->applyNonContraBonPaymentFilter($q, 'rf.payment_method');
            })
            ->select(
                'rf.id',
                'rf.retail_number',
                'rf.transaction_date',
                'rf.total_amount',
                'rf.payment_method',
                'rf.notes',
                'u.nama_lengkap as creator_name',
            )
            ->orderByDesc('rf.transaction_date')
            ->orderByDesc('rf.id')
            ->get();

        $retailNonFoodRows = DB::table('retail_non_food as rnf')
            ->leftJoin('purchase_requisition_categories as prc', 'rnf.category_budget_id', '=', 'prc.id')
            ->leftJoin('users as u', 'rnf.created_by', '=', 'u.id')
            ->where('rnf.outlet_id', $outletId)
            ->where('rnf.status', 'approved')
            ->whereNull('rnf.deleted_at')
            ->whereDate('rnf.transaction_date', '>=', $start)
            ->whereDate('rnf.transaction_date', '<=', $end)
            ->where(function ($q) {
                $this->applyNonContraBonPaymentFilter($q, 'rnf.payment_method');
            })
            ->select(
                'rnf.id',
                'rnf.retail_number',
                'rnf.transaction_date',
                'rnf.total_amount',
                'rnf.payment_method',
                'rnf.notes',
                'prc.name as category_name',
                'u.nama_lengkap as creator_name',
            )
            ->orderByDesc('rnf.transaction_date')
            ->orderByDesc('rnf.id')
            ->get();

        $retailFoodItems = $this->fetchPettyCashRetailFoodItems(
            $retailFoodRows->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
        $retailNonFoodItems = $this->fetchPettyCashRetailNonFoodItems(
            $retailNonFoodRows->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        $retailFoodTransactions = $retailFoodRows
            ->map(fn ($row) => $this->mapPettyCashTransaction(
                $row,
                'retail_food',
                $retailFoodItems[(int) $row->id] ?? [],
            ))
            ->all();

        $retailNonFoodTransactions = $retailNonFoodRows
            ->map(fn ($row) => $this->mapPettyCashTransaction(
                $row,
                'retail_non_food',
                $retailNonFoodItems[(int) $row->id] ?? [],
            ))
            ->all();

        $retailFoodCategories = [
            ['key' => 'main_kitchen', 'label' => 'Main Kitchen', 'amount' => round((float) ($retailFoodBuckets['main_kitchen'] ?? 0), 2)],
            ['key' => 'main_store', 'label' => 'Main Store', 'amount' => round((float) ($retailFoodBuckets['main_store'] ?? 0), 2)],
            ['key' => 'chemical', 'label' => 'Chemical', 'amount' => round((float) ($retailFoodBuckets['chemical'] ?? 0), 2)],
            ['key' => 'stationary', 'label' => 'Stationary', 'amount' => round((float) ($retailFoodBuckets['stationary'] ?? 0), 2)],
            ['key' => 'marketing', 'label' => 'Marketing', 'amount' => round((float) ($retailFoodBuckets['marketing'] ?? 0), 2)],
        ];

        $sources = [
            ['key' => 'retail_food', 'label' => 'Retail Food', 'amount' => round($retailFoodTotal, 2)],
            ['key' => 'retail_non_food', 'label' => 'Retail Non Food', 'amount' => round($retailNonFoodTotal, 2)],
        ];

        return [
            'total' => round($retailFoodTotal + $retailNonFoodTotal, 2),
            'retail_food_total' => round($retailFoodTotal, 2),
            'retail_non_food_total' => round($retailNonFoodTotal, 2),
            'retail_food_count' => count($retailFoodTransactions),
            'retail_non_food_count' => count($retailNonFoodTransactions),
            'transaction_count' => count($retailFoodTransactions) + count($retailNonFoodTransactions),
            'sources' => $sources,
            'retail_food_categories' => array_values(array_filter($retailFoodCategories, fn ($row) => $row['amount'] > 0)),
            'retail_non_food_categories' => $retailNonFoodCategories,
            'transactions' => [
                'retail_food' => $retailFoodTransactions,
                'retail_non_food' => $retailNonFoodTransactions,
            ],
        ];
    }

    /**
     * Retail Food petty cash — payment_method selain contra_bon, per kategori gudang.
     *
     * @return array<string, float>
     */
    private function fetchRetailFoodPettyCashFjBuckets(int $outletId, string $start, string $end): array
    {
        $totals = [
            'main_kitchen' => 0.0,
            'main_store' => 0.0,
            'chemical' => 0.0,
            'stationary' => 0.0,
            'marketing' => 0.0,
            'line_total' => 0.0,
        ];

        $rows = DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->leftJoin('items as it', 'rfi.item_name', '=', 'it.name')
            ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereDate('rf.transaction_date', '>=', $start)
            ->whereDate('rf.transaction_date', '<=', $end)
            ->where(function ($q) {
                $this->applyNonContraBonPaymentFilter($q, 'rf.payment_method');
            })
            ->select('rfi.subtotal', 'w.name as warehouse', 'sc.name as sub_category')
            ->get();

        foreach ($rows as $row) {
            $amount = (float) ($row->subtotal ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $totals['line_total'] += $amount;
            $bucket = $this->classifyFjWarehouseBucket(
                $row->warehouse !== null ? (string) $row->warehouse : null,
                $row->sub_category !== null ? (string) $row->sub_category : null,
            );
            if ($bucket !== null) {
                $totals[$bucket] += $amount;
            }
        }

        return array_map(fn ($v) => round((float) $v, 2), $totals);
    }

    private function applyNonContraBonPaymentFilter($query, string $column): void
    {
        $query->where(function ($q) use ($column) {
            $q->where($column, '!=', 'contra_bon')
                ->orWhereNull($column);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function mapPettyCashTransaction(object $row, string $source, array $items = []): array
    {
        $paymentMethod = (string) ($row->payment_method ?? 'cash');

        return [
            'id' => (int) $row->id,
            'source' => $source,
            'source_label' => $source === 'retail_food' ? 'Retail Food' : 'Retail Non Food',
            'retail_number' => (string) ($row->retail_number ?? '-'),
            'transaction_date' => $row->transaction_date,
            'total_amount' => round((float) ($row->total_amount ?? 0), 2),
            'payment_method' => $paymentMethod,
            'payment_method_label' => $paymentMethod === 'cash' ? 'Cash' : ucfirst(str_replace('_', ' ', $paymentMethod)),
            'category_name' => isset($row->category_name) ? (string) ($row->category_name ?: 'Tanpa Kategori') : null,
            'creator_name' => (string) ($row->creator_name ?? '-'),
            'notes' => mb_substr((string) ($row->notes ?? ''), 0, 120),
            'items' => $items,
        ];
    }

    /**
     * @param  list<int>  $retailFoodIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchPettyCashRetailFoodItems(array $retailFoodIds): array
    {
        if ($retailFoodIds === []) {
            return [];
        }

        $grouped = [];
        DB::table('retail_food_items')
            ->whereIn('retail_food_id', $retailFoodIds)
            ->select('retail_food_id', 'item_name', 'qty', 'unit', 'price', 'subtotal')
            ->orderBy('id')
            ->get()
            ->each(function ($row) use (&$grouped) {
                $parentId = (int) $row->retail_food_id;
                $grouped[$parentId][] = [
                    'item_name' => (string) ($row->item_name ?? '-'),
                    'qty' => round((float) ($row->qty ?? 0), 3),
                    'unit' => (string) ($row->unit ?? ''),
                    'price' => round((float) ($row->price ?? 0), 2),
                    'subtotal' => round((float) ($row->subtotal ?? 0), 2),
                ];
            });

        return $grouped;
    }

    /**
     * @param  list<int>  $retailNonFoodIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchPettyCashRetailNonFoodItems(array $retailNonFoodIds): array
    {
        if ($retailNonFoodIds === []) {
            return [];
        }

        $grouped = [];
        DB::table('retail_non_food_items')
            ->whereIn('retail_non_food_id', $retailNonFoodIds)
            ->select('retail_non_food_id', 'item_name', 'qty', 'unit', 'price', 'subtotal')
            ->orderBy('id')
            ->get()
            ->each(function ($row) use (&$grouped) {
                $parentId = (int) $row->retail_non_food_id;
                $grouped[$parentId][] = [
                    'item_name' => (string) ($row->item_name ?? '-'),
                    'qty' => round((float) ($row->qty ?? 0), 2),
                    'unit' => (string) ($row->unit ?? ''),
                    'price' => round((float) ($row->price ?? 0), 2),
                    'subtotal' => round((float) ($row->subtotal ?? 0), 2),
                ];
            });

        return $grouped;
    }

    /**
     * Pengeluaran Purchase Requisition Ops — pembayaran non food per kategori PR.
     *
     * @return array<string, mixed>
     */
    private function getPrOpsExpenditure(int $outletId, string $start, string $end): array
    {
        $combined = [];

        $appendCategoryRows = function ($rows) use (&$combined): void {
            foreach ($rows as $item) {
                $categoryId = $item->category_id ?? null;
                $key = $categoryId ? (string) $categoryId : 'uncategorized';

                if (! isset($combined[$key])) {
                    $combined[$key] = [
                        'category_id' => $categoryId ?: 'uncategorized',
                        'label' => (string) ($item->category_name ?: 'Tanpa Kategori'),
                        'division' => (string) ($item->division ?? ''),
                        'amount' => 0.0,
                        'payment_count' => 0,
                    ];
                }

                $combined[$key]['amount'] += (float) ($item->amount ?? 0);
                $combined[$key]['payment_count'] += (int) ($item->payment_count ?? 0);
            }
        };

        $nfpoRows = DB::table('non_food_payment_outlets as nfpo')
            ->join('non_food_payments as nfp', 'nfp.id', '=', 'nfpo.non_food_payment_id')
            ->leftJoin('purchase_requisition_categories as prc', 'nfpo.category_id', '=', 'prc.id')
            ->where('nfpo.outlet_id', $outletId)
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNull('nfp.retail_non_food_id')
            ->where(function ($q) {
                $this->applyPrOpsPaymentScope($q, 'nfp');
            })
            ->select(
                'prc.id as category_id',
                'prc.name as category_name',
                'prc.division',
                DB::raw('COALESCE(SUM(nfpo.amount), 0) as amount'),
                DB::raw('COUNT(DISTINCT nfp.id) as payment_count'),
            )
            ->groupBy('prc.id', 'prc.name', 'prc.division')
            ->get();

        $appendCategoryRows($nfpoRows);

        $legacyViaPo = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->whereNull('nfp.retail_non_food_id')
            ->whereNotExists(function ($sub) {
                $sub->from('non_food_payment_outlets as nfpo')
                    ->whereColumn('nfpo.non_food_payment_id', 'nfp.id');
            })
            ->where(function ($q) use ($outletId) {
                $this->applyOutletPrOpsScope($q, $outletId, 'pr');
            })
            ->select(
                'prc.id as category_id',
                'prc.name as category_name',
                'prc.division',
                DB::raw('COALESCE(SUM(nfp.amount), 0) as amount'),
                DB::raw('COUNT(DISTINCT nfp.id) as payment_count'),
            )
            ->groupBy('prc.id', 'prc.name', 'prc.division')
            ->get();

        $legacyDirect = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id')
            ->whereNull('nfp.retail_non_food_id')
            ->whereNotExists(function ($sub) {
                $sub->from('non_food_payment_outlets as nfpo')
                    ->whereColumn('nfpo.non_food_payment_id', 'nfp.id');
            })
            ->where(function ($q) use ($outletId) {
                $this->applyOutletPrOpsScope($q, $outletId, 'pr');
            })
            ->select(
                'prc.id as category_id',
                'prc.name as category_name',
                'prc.division',
                DB::raw('COALESCE(SUM(nfp.amount), 0) as amount'),
                DB::raw('COUNT(DISTINCT nfp.id) as payment_count'),
            )
            ->groupBy('prc.id', 'prc.name', 'prc.division')
            ->get();

        $appendCategoryRows($legacyViaPo);
        $appendCategoryRows($legacyDirect);

        $categories = collect($combined)
            ->filter(fn ($row) => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->values()
            ->map(function ($row) {
                $row['amount'] = round((float) $row['amount'], 2);

                return $row;
            })
            ->all();

        $transactions = $this->fetchPrOpsPaymentTransactions($outletId, $start, $end);
        $paymentCount = count(array_unique(array_column($transactions, 'id')));

        return [
            'total' => round(array_sum(array_column($categories, 'amount')), 2),
            'payment_count' => $paymentCount,
            'categories' => $categories,
            'transactions' => $transactions,
        ];
    }

    private function applyPrOpsPaymentScope($query, string $alias = 'nfp'): void
    {
        $query->where(function ($q) use ($alias) {
            $q->whereNotNull("{$alias}.purchase_requisition_id")
                ->orWhere(function ($poQ) use ($alias) {
                    $poQ->whereNotNull("{$alias}.purchase_order_ops_id")
                        ->whereExists(function ($sub) use ($alias) {
                            $sub->from('purchase_order_ops as poo')
                                ->whereColumn('poo.id', "{$alias}.purchase_order_ops_id")
                                ->where(function ($sourceQ) {
                                    $sourceQ->where('poo.source_type', 'purchase_requisition_ops')
                                        ->orWhereExists(function ($poiSub) {
                                            $poiSub->from('purchase_order_ops_items as poi')
                                                ->whereColumn('poi.purchase_order_ops_id', 'poo.id')
                                                ->where('poi.source_type', 'purchase_requisition_ops');
                                        });
                                });
                        });
                });
        });
    }

    private function applyOutletPrOpsScope($query, int $outletId, string $prAlias = 'pr'): void
    {
        $query->where(function ($q) use ($outletId, $prAlias) {
            $q->where("{$prAlias}.outlet_id", $outletId)
                ->orWhereExists(function ($sub) use ($outletId, $prAlias) {
                    $sub->from('purchase_requisition_items as pri')
                        ->whereColumn('pri.purchase_requisition_id', "{$prAlias}.id")
                        ->where('pri.outlet_id', $outletId);
                });
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPrOpsPaymentTransactions(int $outletId, string $start, string $end): array
    {
        $viaOutletSplit = DB::table('non_food_payment_outlets as nfpo')
            ->join('non_food_payments as nfp', 'nfp.id', '=', 'nfpo.non_food_payment_id')
            ->leftJoin('purchase_requisition_categories as prc', 'nfpo.category_id', '=', 'prc.id')
            ->leftJoin('purchase_order_ops as poo', 'poo.id', '=', 'nfp.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr_direct', 'pr_direct.id', '=', 'nfp.purchase_requisition_id')
            ->leftJoin('purchase_requisitions as pr_po', function ($join) {
                $join->on('pr_po.id', '=', 'poo.source_id')
                    ->where('poo.source_type', '=', 'purchase_requisition_ops');
            })
            ->where('nfpo.outlet_id', $outletId)
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNull('nfp.retail_non_food_id')
            ->where(function ($q) {
                $this->applyPrOpsPaymentScope($q, 'nfp');
            })
            ->select(
                'nfpo.id as payment_outlet_id',
                'nfp.id',
                'nfp.payment_number',
                'nfp.payment_date',
                'nfpo.amount',
                'nfp.payment_method',
                'nfp.purchase_order_ops_id',
                'nfp.purchase_requisition_id',
                'nfpo.category_id',
                DB::raw('COALESCE(pr_direct.id, pr_po.id, nfp.purchase_requisition_id) as pr_id'),
                'nfp.created_by as payment_created_by',
                'nfp.approved_by',
                'nfp.approved_finance_manager_by',
                'nfp.approved_gm_finance_by',
                DB::raw('COALESCE(pr_direct.created_by, pr_po.created_by) as pr_created_by'),
                DB::raw('COALESCE(pr_direct.approved_ssd_by, pr_po.approved_ssd_by) as pr_approved_ssd_by'),
                DB::raw('COALESCE(pr_direct.approved_cc_by, pr_po.approved_cc_by) as pr_approved_cc_by'),
                'poo.created_by as po_created_by',
                DB::raw('COALESCE(pr_direct.pr_number, pr_po.pr_number) as pr_number'),
                DB::raw('COALESCE(pr_direct.title, pr_po.title) as title'),
                DB::raw("COALESCE(prc.name, 'Tanpa Kategori') as category_name"),
                'poo.number as po_number',
            )
            ->get();

        $legacyViaPo = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', function ($join) {
                $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                    ->where('poi.source_type', '=', 'purchase_requisition_ops');
            })
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->whereNull('nfp.retail_non_food_id')
            ->whereNotExists(function ($sub) {
                $sub->from('non_food_payment_outlets as nfpo')
                    ->whereColumn('nfpo.non_food_payment_id', 'nfp.id');
            })
            ->where(function ($q) use ($outletId) {
                $this->applyOutletPrOpsScope($q, $outletId, 'pr');
            })
            ->select(
                DB::raw('NULL as payment_outlet_id'),
                'nfp.id',
                'nfp.payment_number',
                'nfp.payment_date',
                'nfp.amount',
                'nfp.payment_method',
                'nfp.purchase_order_ops_id',
                'nfp.purchase_requisition_id',
                'prc.id as category_id',
                'pr.id as pr_id',
                'nfp.created_by as payment_created_by',
                'nfp.approved_by',
                'nfp.approved_finance_manager_by',
                'nfp.approved_gm_finance_by',
                'pr.created_by as pr_created_by',
                'pr.approved_ssd_by as pr_approved_ssd_by',
                'pr.approved_cc_by as pr_approved_cc_by',
                'poo.created_by as po_created_by',
                'pr.pr_number',
                'pr.title',
                DB::raw("COALESCE(prc.name, 'Tanpa Kategori') as category_name"),
                'poo.number as po_number',
            )
            ->distinct()
            ->get();

        $legacyDirect = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id')
            ->whereNull('nfp.retail_non_food_id')
            ->whereNotExists(function ($sub) {
                $sub->from('non_food_payment_outlets as nfpo')
                    ->whereColumn('nfpo.non_food_payment_id', 'nfp.id');
            })
            ->where(function ($q) use ($outletId) {
                $this->applyOutletPrOpsScope($q, $outletId, 'pr');
            })
            ->select(
                DB::raw('NULL as payment_outlet_id'),
                'nfp.id',
                'nfp.payment_number',
                'nfp.payment_date',
                'nfp.amount',
                'nfp.payment_method',
                DB::raw('NULL as purchase_order_ops_id'),
                'nfp.purchase_requisition_id',
                'prc.id as category_id',
                'pr.id as pr_id',
                'nfp.created_by as payment_created_by',
                'nfp.approved_by',
                'nfp.approved_finance_manager_by',
                'nfp.approved_gm_finance_by',
                'pr.created_by as pr_created_by',
                'pr.approved_ssd_by as pr_approved_ssd_by',
                'pr.approved_cc_by as pr_approved_cc_by',
                DB::raw('NULL as po_created_by'),
                'pr.pr_number',
                'pr.title',
                DB::raw("COALESCE(prc.name, 'Tanpa Kategori') as category_name"),
                DB::raw('NULL as po_number'),
            )
            ->get();

        $rows = $viaOutletSplit->concat($legacyViaPo)->concat($legacyDirect)
            ->sortByDesc('payment_date')
            ->values()
            ->all();

        return $this->enrichPrOpsTransactions($rows, $outletId);
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function enrichPrOpsTransactions(array $rows, int $outletId): array
    {
        if ($rows === []) {
            return [];
        }

        $userIds = [];
        foreach ($rows as $row) {
            foreach ([
                'payment_created_by',
                'pr_created_by',
                'po_created_by',
                'approved_by',
                'approved_finance_manager_by',
                'approved_gm_finance_by',
                'pr_approved_ssd_by',
                'pr_approved_cc_by',
            ] as $field) {
                $uid = (int) ($row->{$field} ?? 0);
                if ($uid > 0) {
                    $userIds[$uid] = true;
                }
            }
        }

        $users = $userIds === []
            ? collect()
            : DB::table('users')->whereIn('id', array_keys($userIds))->pluck('nama_lengkap', 'id');

        $itemsMap = $this->buildPrOpsPaymentItemsMap($rows, $outletId);

        return array_map(function ($row) use ($users, $itemsMap, $outletId) {
            $paymentMethod = (string) ($row->payment_method ?? 'transfer');
            $rowKey = $row->payment_outlet_id
                ? 'nfpo_' . (int) $row->payment_outlet_id
                : 'nfp_' . (int) $row->id;

            $paymentCreator = $users[(int) ($row->payment_created_by ?? 0)] ?? null;
            $prCreator = $users[(int) ($row->pr_created_by ?? 0)] ?? null;
            $poCreator = $users[(int) ($row->po_created_by ?? 0)] ?? null;

            return [
                'row_key' => $rowKey,
                'id' => (int) $row->id,
                'payment_number' => (string) ($row->payment_number ?? '-'),
                'payment_date' => $row->payment_date,
                'amount' => round((float) ($row->amount ?? 0), 2),
                'payment_method' => $paymentMethod,
                'payment_method_label' => match ($paymentMethod) {
                    'cash' => 'Cash',
                    'transfer' => 'Transfer',
                    'check' => 'Check',
                    default => ucfirst(str_replace('_', ' ', $paymentMethod)),
                },
                'pr_number' => (string) ($row->pr_number ?? '-'),
                'title' => mb_substr((string) ($row->title ?? ''), 0, 120),
                'category_name' => (string) ($row->category_name ?: 'Tanpa Kategori'),
                'po_number' => $row->po_number ? (string) $row->po_number : null,
                'payment_creator_name' => $paymentCreator ? (string) $paymentCreator : null,
                'pr_creator_name' => $prCreator ? (string) $prCreator : null,
                'po_creator_name' => $poCreator ? (string) $poCreator : null,
                'approvers' => $this->buildPrOpsApproversList($row, $users),
                'items' => $this->resolvePrOpsItemsForRow($row, $itemsMap, $outletId),
            ];
        }, $rows);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $users
     * @return list<array{role: string, name: string}>
     */
    private function buildPrOpsApproversList(object $row, $users): array
    {
        $approvers = [];
        $append = function (string $role, ?int $userId) use (&$approvers, $users): void {
            if ($userId === null || $userId <= 0) {
                return;
            }
            $name = $users[$userId] ?? null;
            if ($name) {
                $approvers[] = ['role' => $role, 'name' => (string) $name];
            }
        };

        $append('Finance Manager', isset($row->approved_finance_manager_by) ? (int) $row->approved_finance_manager_by : null);
        $append('GM Finance', isset($row->approved_gm_finance_by) ? (int) $row->approved_gm_finance_by : null);
        $append('Payment Approver', isset($row->approved_by) ? (int) $row->approved_by : null);
        $append('PR SSD', isset($row->pr_approved_ssd_by) ? (int) $row->pr_approved_ssd_by : null);
        $append('PR CC', isset($row->pr_approved_cc_by) ? (int) $row->pr_approved_cc_by : null);

        return $approvers;
    }

    /**
     * @param  list<object>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildPrOpsPaymentItemsMap(array $rows, int $outletId): array
    {
        $map = [];

        $poIds = collect($rows)
            ->pluck('purchase_order_ops_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($poIds !== []) {
            $poItems = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_requisition_items as pri', 'poi.pr_ops_item_id', '=', 'pri.id')
                ->whereIn('poi.purchase_order_ops_id', $poIds)
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->select(
                    'poi.purchase_order_ops_id',
                    DB::raw('COALESCE(poi.outlet_id, pri.outlet_id) as outlet_id'),
                    'pri.category_id',
                    'poi.item_name',
                    'poi.quantity',
                    'poi.unit',
                    'poi.price',
                    'poi.total',
                )
                ->orderBy('poi.id')
                ->get();

            foreach ($poItems as $item) {
                $itemOutlet = $this->normalizePrOpsOutletId($item->outlet_id);
                if ($itemOutlet !== null && $itemOutlet !== $outletId) {
                    continue;
                }

                $categoryId = $item->category_id !== null ? (int) $item->category_id : null;
                $key = $this->prOpsPaymentItemKey((int) $item->purchase_order_ops_id, null, $outletId, $categoryId);
                $map[$key][] = $this->mapPrOpsPoItemRow($item);
            }
        }

        $prIds = collect($rows)
            ->filter(fn ($row) => empty($row->purchase_order_ops_id))
            ->map(fn ($row) => (int) ($row->pr_id ?? $row->purchase_requisition_id ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($prIds !== []) {
            DB::table('purchase_requisition_items')
                ->whereIn('purchase_requisition_id', $prIds)
                ->orderBy('id')
                ->get()
                ->each(function ($item) use (&$map, $outletId) {
                    $itemOutlet = $this->normalizePrOpsOutletId($item->outlet_id);
                    if ($itemOutlet !== null && $itemOutlet !== $outletId) {
                        return;
                    }

                    $categoryId = $item->category_id !== null ? (int) $item->category_id : null;
                    $key = $this->prOpsPaymentItemKey(
                        null,
                        (int) $item->purchase_requisition_id,
                        $outletId,
                        $categoryId,
                    );
                    $map[$key][] = $this->mapPrOpsPrItemRow($item);
                });
        }

        return $map;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $itemsMap
     * @return list<array<string, mixed>>
     */
    private function resolvePrOpsItemsForRow(object $row, array $itemsMap, int $outletId): array
    {
        $categoryId = isset($row->category_id) && $row->category_id ? (int) $row->category_id : null;
        $poId = ! empty($row->purchase_order_ops_id) ? (int) $row->purchase_order_ops_id : null;
        $prId = (int) ($row->pr_id ?? $row->purchase_requisition_id ?? 0);

        $candidateKeys = [];
        if ($poId) {
            $candidateKeys[] = $this->prOpsPaymentItemKey($poId, null, $outletId, $categoryId);
            $candidateKeys[] = $this->prOpsPaymentItemKey($poId, null, $outletId, null);
        } elseif ($prId > 0) {
            $candidateKeys[] = $this->prOpsPaymentItemKey(null, $prId, $outletId, $categoryId);
            $candidateKeys[] = $this->prOpsPaymentItemKey(null, $prId, $outletId, null);
        }

        foreach ($candidateKeys as $key) {
            if (! empty($itemsMap[$key])) {
                return $itemsMap[$key];
            }
        }

        return [];
    }

    private function prOpsPaymentItemKey(?int $poId, ?int $prId, int $outletId, ?int $categoryId): string
    {
        if ($poId) {
            return 'po:' . $poId . ':' . $outletId . ':' . ($categoryId ?? 'all');
        }

        return 'pr:' . (int) $prId . ':' . $outletId . ':' . ($categoryId ?? 'all');
    }

    private function normalizePrOpsOutletId($outletId): ?int
    {
        if ($outletId === null || $outletId === '' || $outletId === 0 || $outletId === '0') {
            return null;
        }

        return (int) $outletId;
    }

    /**
     * @return array{item_name: string, qty: float, unit: string, price: float, subtotal: float}
     */
    private function mapPrOpsPoItemRow(object $row): array
    {
        return [
            'item_name' => (string) ($row->item_name ?? '-'),
            'qty' => round((float) ($row->quantity ?? 0), 3),
            'unit' => (string) ($row->unit ?? ''),
            'price' => round((float) ($row->price ?? 0), 2),
            'subtotal' => round((float) ($row->total ?? 0), 2),
        ];
    }

    /**
     * @return array{item_name: string, qty: float, unit: string, price: float, subtotal: float}
     */
    private function mapPrOpsPrItemRow(object $row): array
    {
        $qty = (float) ($row->qty ?? 0);
        if ($qty <= 0) {
            $qty = 1;
        }

        $subtotal = (float) ($row->subtotal ?? 0);
        $price = (float) ($row->unit_price ?? 0);
        if ($price <= 0 && $subtotal > 0) {
            $price = $subtotal / $qty;
        }

        return [
            'item_name' => (string) ($row->item_name ?? '-'),
            'qty' => round($qty, 3),
            'unit' => (string) ($row->unit ?? ''),
            'price' => round($price, 2),
            'subtotal' => round($subtotal, 2),
        ];
    }

    /**
     * Kategorisasi gudang — selaras rekapFjAggregatePivotItemRowsByOutlet().
     */
    private function classifyFjWarehouseBucket(?string $warehouse, ?string $subCategory): ?string
    {
        $warehouse = $warehouse !== null ? trim($warehouse) : null;
        $subCategory = $subCategory !== null ? trim($subCategory) : null;

        if ($warehouse && in_array($warehouse, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen'], true)) {
            return 'main_kitchen';
        }

        if ($warehouse && strtoupper($warehouse) === 'MAIN STORE') {
            if ($subCategory && strtoupper($subCategory) === 'CHEMICAL') {
                return 'chemical';
            }
            if ($subCategory && strtoupper($subCategory) === 'STATIONARY') {
                return 'stationary';
            }
            if ($subCategory && strtoupper($subCategory) === 'MARKETING') {
                return 'marketing';
            }

            return 'main_store';
        }

        return null;
    }

    /**
     * Category Cost Outlet — internal use / spoil / waste / usage / marketing, dll.
     *
     * @return array<string, mixed>
     */
    private function getCategoryCostOutlet(int $outletId, string $start, string $end): array
    {
        $typesRequiringApproval = ['r_and_d', 'marketing', 'wrong_maker', 'training'];

        $headers = DB::table('outlet_internal_use_waste_headers as h')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->where('h.outlet_id', $outletId)
            ->whereDate('h.date', '>=', $start)
            ->whereDate('h.date', '<=', $end)
            ->where('h.status', '!=', 'DRAFT')
            ->where(function ($q) use ($typesRequiringApproval) {
                $q->whereNotIn('h.type', $typesRequiringApproval)
                    ->orWhere(function ($subQ) use ($typesRequiringApproval) {
                        $subQ->whereIn('h.type', $typesRequiringApproval)
                            ->where('h.status', 'APPROVED');
                    });
            })
            ->select(
                'h.id',
                'h.number',
                'h.date',
                'h.type',
                'h.status',
                'h.notes',
                'h.outlet_id',
                'h.warehouse_outlet_id',
                DB::raw('COALESCE(h.subtotal_mac, 0) as subtotal_mac'),
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name',
            )
            ->orderByDesc('h.date')
            ->orderByDesc('h.id')
            ->get();

        if ($headers->isEmpty()) {
            return [
                'total' => 0.0,
                'document_count' => 0,
                'modes' => [],
                'transactions' => [],
            ];
        }

        $headersById = $headers->keyBy('id');
        $headerIds = $headers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $itemsByHeader = $this->fetchCategoryCostOutletItems($headerIds, $headersById);

        $modeBuckets = [];
        $total = 0.0;
        $transactions = [];

        foreach ($headers as $header) {
            $type = (string) ($header->type ?? '');
            $amount = round((float) ($header->subtotal_mac ?? 0), 2);
            $headerItems = $itemsByHeader[(int) $header->id] ?? [];

            if ($amount <= 0 && $headerItems !== []) {
                $amount = round(array_sum(array_column($headerItems, 'subtotal_mac')), 2);
            }

            $total += $amount;

            if (! isset($modeBuckets[$type])) {
                $modeBuckets[$type] = [
                    'key' => $type,
                    'label' => $this->categoryCostOutletTypeLabel($type),
                    'amount' => 0.0,
                    'document_count' => 0,
                ];
            }

            $modeBuckets[$type]['amount'] = round($modeBuckets[$type]['amount'] + $amount, 2);
            $modeBuckets[$type]['document_count']++;

            $transactions[] = [
                'id' => (int) $header->id,
                'row_key' => 'cc_'.(int) $header->id,
                'document_number' => (string) ($header->number ?? '-'),
                'date' => $header->date,
                'type' => $type,
                'type_label' => $this->categoryCostOutletTypeLabel($type),
                'status' => (string) ($header->status ?? '-'),
                'subtotal_mac' => $amount,
                'warehouse_outlet_name' => (string) ($header->warehouse_outlet_name ?? '-'),
                'creator_name' => (string) ($header->creator_name ?? '-'),
                'notes' => mb_substr((string) ($header->notes ?? ''), 0, 120),
                'items' => $headerItems,
            ];
        }

        $modes = collect($modeBuckets)
            ->values()
            ->map(fn ($row) => [
                'key' => $row['key'],
                'label' => $row['label'],
                'amount' => round((float) $row['amount'], 2),
                'document_count' => (int) $row['document_count'],
            ])
            ->filter(fn ($row) => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->values()
            ->all();

        return [
            'total' => round($total, 2),
            'document_count' => count($transactions),
            'modes' => $modes,
            'transactions' => $transactions,
        ];
    }

    /**
     * @param  list<int>  $headerIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchCategoryCostOutletItems(array $headerIds, $headersById): array
    {
        if ($headerIds === []) {
            return [];
        }

        $details = DB::table('outlet_internal_use_waste_details as d')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
            ->select(
                'd.header_id',
                'd.item_id',
                'd.qty',
                'd.unit_id',
                'd.note',
                'i.name as item_name',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'u.name as unit_name',
            )
            ->whereIn('d.header_id', $headerIds)
            ->orderBy('d.id')
            ->get();

        $itemIds = $details->pluck('item_id')->unique()->filter()->all();
        $inventoryItems = [];
        if ($itemIds !== []) {
            $inventoryItems = DB::table('outlet_food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->get()
                ->keyBy('item_id')
                ->all();
        }

        $macHistories = [];
        foreach ($details as $detail) {
            $header = $headersById->get($detail->header_id);
            if (! $header || ! isset($inventoryItems[$detail->item_id])) {
                continue;
            }

            $inventoryItem = $inventoryItems[$detail->item_id];
            $macKey = "{$inventoryItem->id}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";

            if (! array_key_exists($macKey, $macHistories)) {
                $macRow = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('id_outlet', $header->outlet_id)
                    ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                    ->where('date', '<=', $header->date)
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->first();
                $macHistories[$macKey] = $macRow ? (float) $macRow->new_cost : null;
            }
        }

        $grouped = [];
        foreach ($details as $detail) {
            $header = $headersById->get($detail->header_id);
            $mac = null;

            if ($header && isset($inventoryItems[$detail->item_id])) {
                $inventoryItem = $inventoryItems[$detail->item_id];
                $macKey = "{$inventoryItem->id}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
                $mac = $macHistories[$macKey] ?? null;
            }

            $macConverted = $this->convertCategoryCostMac($mac, $detail);
            $subtotalMac = $macConverted !== null ? round($macConverted * (float) ($detail->qty ?? 0), 2) : 0.0;

            $grouped[(int) $detail->header_id][] = [
                'item_name' => (string) ($detail->item_name ?? '-'),
                'qty' => round((float) ($detail->qty ?? 0), 3),
                'unit' => (string) ($detail->unit_name ?? ''),
                'mac' => $macConverted !== null ? round($macConverted, 2) : null,
                'subtotal_mac' => $subtotalMac,
                'note' => mb_substr((string) ($detail->note ?? ''), 0, 80),
            ];
        }

        return $grouped;
    }

    private function convertCategoryCostMac(?float $mac, object $detail): ?float
    {
        if ($mac === null) {
            return null;
        }

        $converted = $mac;

        if ($detail->unit_id == $detail->medium_unit_id && (float) ($detail->small_conversion_qty ?? 0) > 0) {
            $converted = $mac * (float) $detail->small_conversion_qty;
        } elseif ($detail->unit_id == $detail->large_unit_id
            && (float) ($detail->small_conversion_qty ?? 0) > 0
            && (float) ($detail->medium_conversion_qty ?? 0) > 0) {
            $converted = $mac * (float) $detail->small_conversion_qty * (float) $detail->medium_conversion_qty;
        }

        return $converted;
    }

    private function categoryCostOutletTypeLabel(string $type): string
    {
        $labels = [
            'internal_use' => 'Internal Use',
            'spoil' => 'Spoil',
            'waste' => 'Waste',
            'stock_cut' => 'Usage',
            'usage' => 'Usage',
            'r_and_d' => 'R & D',
            'marketing' => 'Marketing',
            'non_commodity' => 'Non Commodity',
            'guest_supplies' => 'Guest Supplies',
            'wrong_maker' => 'Wrong Maker',
            'training' => 'Training',
        ];

        return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    /**
     * @return array<string, mixed>
     */
    private function getEmployeeAttendance(int $outletId, string $start, string $end, array $period = []): array
    {
        $employees = DB::table('users as u')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->where('u.id_outlet', $outletId)
            ->where('u.status', 'A')
            ->select(
                'u.id',
                'u.nama_lengkap',
                'u.avatar',
                'u.upload_latest_color_photo',
                'u.division_id',
                'j.nama_jabatan',
                'd.nama_divisi',
            )
            ->orderBy('d.nama_divisi')
            ->orderBy('u.nama_lengkap')
            ->get();

        $summary = [
            'employee_count' => $employees->count(),
            'present_days' => 0,
            'total_telat' => 0,
            'alpa_days' => 0,
            'off_days' => 0,
            'total_lembur' => 0,
            'ph_days' => 0,
            'leave_days' => 0,
            'percentage' => 0.0,
            'total_shift_days' => 0,
        ];

        $leaveMap = [];
        $employeeRows = [];

        foreach ($employees as $employee) {
            $empSummary = $this->attendanceReport->buildEmployeePeriodSummary((int) $employee->id, $start, $end);

            $summary['present_days'] += (int) ($empSummary['present_days'] ?? 0);
            $summary['total_telat'] += (int) ($empSummary['total_telat'] ?? 0);
            $summary['alpa_days'] += (int) ($empSummary['alpa_days'] ?? 0);
            $summary['off_days'] += (int) ($empSummary['off_days'] ?? 0);
            $summary['total_lembur'] += (int) ($empSummary['total_lembur'] ?? 0);
            $summary['ph_days'] += (int) ($empSummary['ph_days'] ?? 0);
            $summary['total_shift_days'] += (int) ($empSummary['total_shift_days'] ?? 0);

            $empLeaveDays = 0;
            foreach ($empSummary['leave_breakdown'] ?? [] as $leave) {
                $days = (int) ($leave['days'] ?? 0);
                $empLeaveDays += $days;
                $typeId = (int) ($leave['leave_type_id'] ?? 0);
                if (! isset($leaveMap[$typeId])) {
                    $leaveMap[$typeId] = [
                        'leave_type_id' => $typeId,
                        'name' => (string) ($leave['name'] ?? 'Izin'),
                        'days' => 0,
                    ];
                }
                $leaveMap[$typeId]['days'] += $days;
            }
            $summary['leave_days'] += $empLeaveDays;

            $shiftCount = (int) ($empSummary['total_shift_days'] ?? 0);
            $present = (int) ($empSummary['present_days'] ?? 0);
            $employeeRows[] = [
                'id' => (int) $employee->id,
                'name' => (string) $employee->nama_lengkap,
                'jabatan' => (string) ($employee->nama_jabatan ?: '-'),
                'divisi' => (string) ($employee->nama_divisi ?: 'Tanpa Divisi'),
                'division_id' => (int) ($employee->division_id ?? 0),
                'avatar' => $this->resolveUserAvatarPath($employee),
                'present_days' => $present,
                'total_telat' => (int) ($empSummary['total_telat'] ?? 0),
                'total_lembur' => (int) ($empSummary['total_lembur'] ?? 0),
                'alpa_days' => (int) ($empSummary['alpa_days'] ?? 0),
                'off_days' => (int) ($empSummary['off_days'] ?? 0),
                'leave_days' => $empLeaveDays,
                'percentage' => $shiftCount > 0 ? round(($present / $shiftCount) * 100, 1) : 0.0,
            ];
        }

        if ($summary['total_shift_days'] > 0) {
            $summary['percentage'] = round(($summary['present_days'] / $summary['total_shift_days']) * 100, 1);
        }

        $leaveBreakdown = collect($leaveMap)
            ->filter(fn ($item) => $item['days'] > 0)
            ->sortByDesc('days')
            ->values()
            ->all();

        usort($employeeRows, fn ($a, $b) => $b['total_telat'] <=> $a['total_telat'] ?: strcmp($a['name'], $b['name']));

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
                'label' => (string) ($period['label'] ?? ''),
                'month' => $period['month'] ?? null,
                'year' => $period['year'] ?? null,
            ],
            'summary' => $summary,
            'leave_breakdown' => $leaveBreakdown,
            'composition' => [
                ['key' => 'hadir', 'label' => 'Hadir', 'days' => $summary['present_days']],
                ['key' => 'alpha', 'label' => 'Alpha', 'days' => $summary['alpa_days']],
                ['key' => 'off', 'label' => 'OFF', 'days' => $summary['off_days']],
                ['key' => 'izin', 'label' => 'Izin & Cuti', 'days' => $summary['leave_days']],
            ],
            'employees' => collect($employeeRows)
                ->sortBy([
                    ['divisi', 'asc'],
                    ['name', 'asc'],
                ])
                ->values()
                ->all(),
            'top_late' => array_slice($employeeRows, 0, 10),
            'top_overtime' => collect($employeeRows)->sortByDesc('total_lembur')->take(10)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayroll(int $outletId, string $month): array
    {
        $empty = [
            'has_generated' => false,
            'payroll_month' => null,
            'payroll_year' => null,
            'period_label' => null,
            'period_start' => null,
            'period_end' => null,
            'total_gaji' => 0.0,
            'total_gaji_akhir_bulan' => 0.0,
            'total_gaji_tanggal_8' => 0.0,
            'total_bpjs_perusahaan' => 0.0,
            'employee_count' => 0,
            'gajian1_paid_at' => null,
            'gajian2_paid_at' => null,
            'splits' => [],
            'employees' => [],
        ];

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $empty;
        }

        $payrollPeriod = $this->payrollPeriod($month);
        $year = $payrollPeriod['year'];
        $payrollMonth = $payrollPeriod['month'];
        $periodStart = $payrollPeriod['start_date'];
        $periodEnd = $payrollPeriod['end_date'];
        $periodLabel = $payrollPeriod['label'];

        $base = array_merge($empty, [
            'payroll_month' => $payrollMonth,
            'payroll_year' => $year,
            'period_label' => $periodLabel,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $payrollGenerated = DB::table('payroll_generated')
            ->where('outlet_id', $outletId)
            ->where('month', $payrollMonth)
            ->where('year', $year)
            ->first();

        if (! $payrollGenerated) {
            return $base;
        }

        $details = DB::table('payroll_generated_details as pgd')
            ->join('users as u', 'u.id', '=', 'pgd.user_id')
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->where('pgd.payroll_generated_id', $payrollGenerated->id)
            ->orderBy('d.nama_divisi')
            ->orderBy('u.nama_lengkap')
            ->select(
                'pgd.user_id',
                'pgd.gaji_pokok',
                'pgd.tunjangan',
                'pgd.bpjs_jkn',
                'pgd.bpjs_tk',
                'pgd.potongan_telat',
                'pgd.potongan_alpha',
                'pgd.potongan_unpaid_leave',
                'pgd.potongan_kasbon',
                'pgd.service_charge',
                'pgd.uang_makan',
                'pgd.gaji_lembur',
                'pgd.ph_bonus',
                'pgd.lb_total',
                'pgd.deviasi_total',
                'pgd.city_ledger_total',
                'pgd.custom_items',
                'pgd.bpjs_perusahaan_detail',
                'pgd.payment_method',
                'u.nama_lengkap as user_name',
                'j.nama_jabatan',
                'd.nama_divisi',
            )
            ->get();

        $sumAkhirBulan = 0.0;
        $sumTanggal8 = 0.0;
        $sumTotalGaji = 0.0;
        $sumBpjsPerusahaan = 0.0;
        $employees = [];

        foreach ($details as $detail) {
            $customSums = $this->resolvePayrollCustomGajianSums($detail->custom_items);
            $split = PayrollGajiSplitCalculator::calculate([
                'gaji_pokok' => $detail->gaji_pokok ?? 0,
                'tunjangan' => $detail->tunjangan ?? 0,
                'custom_earnings_gajian1' => $customSums['custom_earnings_gajian1'],
                'custom_deductions_gajian1' => $customSums['custom_deductions_gajian1'],
                'bpjs_jkn' => $detail->bpjs_jkn ?? 0,
                'bpjs_tk' => $detail->bpjs_tk ?? 0,
                'potongan_telat' => $detail->potongan_telat ?? 0,
                'potongan_alpha' => $detail->potongan_alpha ?? 0,
                'potongan_unpaid_leave' => $detail->potongan_unpaid_leave ?? 0,
                'potongan_kasbon' => $detail->potongan_kasbon ?? 0,
                'service_charge' => $detail->service_charge ?? 0,
                'uang_makan' => $detail->uang_makan ?? 0,
                'gaji_lembur' => $detail->gaji_lembur ?? 0,
                'ph_bonus' => $detail->ph_bonus ?? 0,
                'custom_earnings_gajian2' => $customSums['custom_earnings_gajian2'],
                'custom_deductions_gajian2' => $customSums['custom_deductions_gajian2'],
                'lb_total' => $detail->lb_total ?? 0,
                'deviasi_total' => $detail->deviasi_total ?? 0,
                'city_ledger_total' => $detail->city_ledger_total ?? 0,
            ]);

            $bpjsDetail = is_string($detail->bpjs_perusahaan_detail)
                ? json_decode($detail->bpjs_perusahaan_detail, true)
                : null;
            $bpjsPerusahaan = is_array($bpjsDetail) ? (float) ($bpjsDetail['total_perusahaan'] ?? 0) : 0.0;

            $sumAkhirBulan += (float) $split['total_gaji_akhir_bulan'];
            $sumTanggal8 += (float) $split['total_gaji_tanggal_8'];
            $sumTotalGaji += (float) $split['total_gaji'];
            $sumBpjsPerusahaan += $bpjsPerusahaan;

            $employees[] = [
                'user_id' => (int) $detail->user_id,
                'name' => (string) ($detail->user_name ?: '-'),
                'jabatan' => (string) ($detail->nama_jabatan ?: '-'),
                'divisi' => (string) ($detail->nama_divisi ?: 'Tanpa Divisi'),
                'payment_method' => (string) ($detail->payment_method ?: 'transfer'),
                'total_gaji_akhir_bulan' => (float) $split['total_gaji_akhir_bulan'],
                'total_gaji_tanggal_8' => (float) $split['total_gaji_tanggal_8'],
                'total_gaji' => (float) $split['total_gaji'],
                'bpjs_perusahaan' => round($bpjsPerusahaan, 2),
            ];
        }

        $totalGaji1 = round($sumAkhirBulan, 2);
        $totalGaji2 = round($sumTanggal8, 2);
        $totalGaji = round($sumTotalGaji, 2);

        return [
            'has_generated' => true,
            'payroll_month' => $payrollMonth,
            'payroll_year' => $year,
            'period_label' => $periodLabel,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_gaji' => $totalGaji,
            'total_gaji_akhir_bulan' => $totalGaji1,
            'total_gaji_tanggal_8' => $totalGaji2,
            'total_bpjs_perusahaan' => round($sumBpjsPerusahaan, 2),
            'employee_count' => count($employees),
            'gajian1_paid_at' => $payrollGenerated->gajian1_paid_at ?? null,
            'gajian2_paid_at' => $payrollGenerated->gajian2_paid_at ?? null,
            'splits' => collect([
                ['key' => 'gajian1', 'label' => 'Gajian 1 (Akhir Bulan)', 'amount' => $totalGaji1],
                ['key' => 'gajian2', 'label' => 'Gajian 2 (Tanggal 8)', 'amount' => $totalGaji2],
            ])->filter(fn ($item) => $item['amount'] > 0)->values()->all(),
            'employees' => $employees,
        ];
    }

    /**
     * @return array{
     *     custom_earnings_gajian1: float,
     *     custom_deductions_gajian1: float,
     *     custom_earnings_gajian2: float,
     *     custom_deductions_gajian2: float
     * }
     */
    private function resolvePayrollCustomGajianSums(mixed $customItemsJson): array
    {
        $items = is_string($customItemsJson)
            ? json_decode($customItemsJson, true)
            : (is_array($customItemsJson) ? $customItemsJson : []);

        if (! is_array($items)) {
            $items = [];
        }

        $sum = [
            'custom_earnings_gajian1' => 0.0,
            'custom_deductions_gajian1' => 0.0,
            'custom_earnings_gajian2' => 0.0,
            'custom_deductions_gajian2' => 0.0,
        ];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemType = (string) ($item['item_type'] ?? '');
            $gajianType = (string) ($item['gajian_type'] ?? 'gajian1');
            $amount = (float) ($item['item_amount'] ?? 0);

            if ($itemType === 'earn') {
                if ($gajianType === 'gajian2') {
                    $sum['custom_earnings_gajian2'] += $amount;
                } else {
                    $sum['custom_earnings_gajian1'] += $amount;
                }
            }

            if ($itemType === 'deduction') {
                if ($gajianType === 'gajian2') {
                    $sum['custom_deductions_gajian2'] += $amount;
                } else {
                    $sum['custom_deductions_gajian1'] += $amount;
                }
            }
        }

        return $sum;
    }
}
