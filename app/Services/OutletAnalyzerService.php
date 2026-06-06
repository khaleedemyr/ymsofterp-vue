<?php

namespace App\Services;

use App\Http\Controllers\AttendanceReportController;
use App\Http\Traits\ReportHelperTrait;
use App\Models\UserRegional;
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

    public function __construct(
        private RegionalVisitAnalyticsService $regionalVisits,
        private AttendanceReportController $attendanceReport,
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

        $revenue = $this->getRevenue($outlet, $start, $end);
        $fj = $this->getFjInventory((int) $outlet->id_outlet, (string) $outlet->nama_outlet, $start, $end);

        return [
            'outlet' => [
                'id_outlet' => (int) $outlet->id_outlet,
                'nama_outlet' => (string) $outlet->nama_outlet,
            ],
            'period' => $period,
            'revenue' => $revenue,
            'guest_comment_gsi' => $this->getGuestCommentGsi((int) $outlet->id_outlet, $start, $end),
            'google_review_gsi' => $this->getGoogleReviewGsi((int) $outlet->id_outlet, (string) $outlet->nama_outlet, $start, $end),
            'regional_visits' => $this->getRegionalVisits((int) $outlet->id_outlet, $start, $end),
            'fj_inventory' => $fj,
            'petty_cash' => $this->getPettyCash((int) $outlet->id_outlet, $start, $end),
            'pr_ops_expenditure' => $this->getPrOpsExpenditure((int) $outlet->id_outlet, $start, $end),
            'employee_attendance' => $this->getEmployeeAttendance((int) $outlet->id_outlet, $start, $end),
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
        $netSales = 0.0;
        $daily = [];

        foreach ($orders as $order) {
            $amount = (float) $order->grand_total;
            $orderDiscount = (float) ($order->discount ?? 0);
            $orderService = (float) ($order->service ?? 0);
            $orderCommfee = (float) ($order->commfee ?? 0);
            $orderManualDiscount = (float) ($order->manual_discount_amount ?? 0);
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
                    'net_sales' => 0.0,
                ];
            }

            $total += $amount;
            $discount += $orderDiscount;
            $serviceCharge += $orderService;
            $commissionFee += $orderCommfee;
            $manualDiscount += $orderManualDiscount;
            $netSales += $orderNetSales;
            $cover += $pax;
            $daily[$date]['revenue'] += $amount;
            $daily[$date]['discount'] += $orderDiscount;
            $daily[$date]['service_charge'] += $orderService;
            $daily[$date]['commission_fee'] += $orderCommfee;
            $daily[$date]['manual_discount'] += $orderManualDiscount;
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
            'net_sales' => round($netSales, 2),
            'daily' => array_values(array_map(function ($row) {
                $row['revenue'] = round($row['revenue'], 2);
                $row['lunch'] = round($row['lunch'], 2);
                $row['dinner'] = round($row['dinner'], 2);
                $row['discount'] = round($row['discount'], 2);
                $row['service_charge'] = round($row['service_charge'], 2);
                $row['commission_fee'] = round($row['commission_fee'], 2);
                $row['manual_discount'] = round($row['manual_discount'], 2);
                $row['net_sales'] = round($row['net_sales'], 2);

                return $row;
            }, $daily)),
        ];
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

                $visitorMap[$uid]['visit_days']++;
                $visitorMap[$uid]['total_hours'] = round($visitorMap[$uid]['total_hours'] + $hours, 2);
                $visitorMap[$uid]['scan_in_count'] += $scanIn;

                $areaMap[$area]['visit_days']++;
                $areaMap[$area]['total_hours'] = round($areaMap[$area]['total_hours'] + $hours, 2);
                $areaMap[$area]['scan_in_count'] += $scanIn;

                if (! isset($areaMap[$area]['members'][$uid])) {
                    $areaMap[$area]['members'][$uid] = [
                        'id' => $uid,
                        'name' => $visitorMap[$uid]['name'],
                        'visit_days' => 0,
                        'total_hours' => 0.0,
                    ];
                }
                $areaMap[$area]['members'][$uid]['visit_days']++;
                $areaMap[$area]['members'][$uid]['total_hours'] = round(
                    $areaMap[$area]['members'][$uid]['total_hours'] + $hours,
                    2,
                );
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

        return [
            'main_kitchen' => round($totals['main_kitchen'], 2),
            'main_store' => round($totals['main_store'], 2),
            'chemical' => round($totals['chemical'], 2),
            'stationary' => round($totals['stationary'], 2),
            'marketing' => round($totals['marketing'], 2),
            'line_total' => round($totals['line_total'], 2),
            'categories' => $categories,
            'retail_food_contra_bon_total' => round((float) ($retailContraBon['line_total'] ?? 0), 2),
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

        $retailFoodTransactions = DB::table('retail_food as rf')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereDate('rf.transaction_date', '>=', $start)
            ->whereDate('rf.transaction_date', '<=', $end)
            ->where(function ($q) {
                $this->applyNonContraBonPaymentFilter($q, 'rf.payment_method');
            })
            ->select('rf.id', 'rf.retail_number', 'rf.transaction_date', 'rf.total_amount', 'rf.payment_method', 'rf.notes')
            ->orderByDesc('rf.transaction_date')
            ->orderByDesc('rf.id')
            ->get()
            ->map(fn ($row) => $this->mapPettyCashTransaction($row, 'retail_food'))
            ->all();

        $retailNonFoodTransactions = DB::table('retail_non_food as rnf')
            ->leftJoin('purchase_requisition_categories as prc', 'rnf.category_budget_id', '=', 'prc.id')
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
            )
            ->orderByDesc('rnf.transaction_date')
            ->orderByDesc('rnf.id')
            ->get()
            ->map(fn ($row) => $this->mapPettyCashTransaction($row, 'retail_non_food'))
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
     * @return array<string, mixed>
     */
    private function mapPettyCashTransaction(object $row, string $source): array
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
            'notes' => mb_substr((string) ($row->notes ?? ''), 0, 120),
        ];
    }

    /**
     * Pengeluaran Purchase Requisition Ops — pembayaran non food per kategori PR.
     *
     * @return array<string, mixed>
     */
    private function getPrOpsExpenditure(int $outletId, string $start, string $end): array
    {
        $paidByCategory = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->where('pr.outlet_id', $outletId)
            ->select(
                'prc.id as category_id',
                'prc.name as category_name',
                'prc.division',
                DB::raw('COALESCE(SUM(nfp.amount), 0) as amount'),
                DB::raw('COUNT(DISTINCT nfp.id) as payment_count'),
            )
            ->groupBy('prc.id', 'prc.name', 'prc.division')
            ->get();

        $directPaidByCategory = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id')
            ->where('pr.outlet_id', $outletId)
            ->select(
                'prc.id as category_id',
                'prc.name as category_name',
                'prc.division',
                DB::raw('COALESCE(SUM(nfp.amount), 0) as amount'),
                DB::raw('COUNT(DISTINCT nfp.id) as payment_count'),
            )
            ->groupBy('prc.id', 'prc.name', 'prc.division')
            ->get();

        $uncategorizedPaid = (float) DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->whereNull('pr.category_id')
            ->where('pr.outlet_id', $outletId)
            ->sum('nfp.amount');

        $uncategorizedDirectPaid = (float) DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id')
            ->whereNull('pr.category_id')
            ->where('pr.outlet_id', $outletId)
            ->sum('nfp.amount');

        $combined = [];
        $uncategorizedKey = 'uncategorized';

        foreach ([$paidByCategory, $directPaidByCategory] as $rows) {
            foreach ($rows as $item) {
                $key = $item->category_id ? (string) $item->category_id : $uncategorizedKey;
                if (! isset($combined[$key])) {
                    $combined[$key] = [
                        'category_id' => $item->category_id ?: $uncategorizedKey,
                        'label' => (string) ($item->category_name ?: 'Tanpa Kategori'),
                        'division' => (string) ($item->division ?? ''),
                        'amount' => 0.0,
                        'payment_count' => 0,
                    ];
                }
                $combined[$key]['amount'] += (float) $item->amount;
                $combined[$key]['payment_count'] += (int) $item->payment_count;
            }
        }

        $uncategorizedTotal = $uncategorizedPaid + $uncategorizedDirectPaid;
        if ($uncategorizedTotal > 0) {
            if (! isset($combined[$uncategorizedKey])) {
                $combined[$uncategorizedKey] = [
                    'category_id' => $uncategorizedKey,
                    'label' => 'Tanpa Kategori',
                    'division' => '',
                    'amount' => 0.0,
                    'payment_count' => 0,
                ];
            }
            $combined[$uncategorizedKey]['amount'] += $uncategorizedTotal;
        }

        $categories = collect($combined)
            ->filter(fn ($row) => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->values()
            ->map(function ($row) {
                $row['amount'] = round((float) $row['amount'], 2);

                return $row;
            })
            ->all();

        $total = round(array_sum(array_column($categories, 'amount')), 2);
        $paymentCount = (int) DB::table('non_food_payments as nfp')
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->where(function ($q) use ($outletId) {
                $q->whereExists(function ($subQ) use ($outletId) {
                    $subQ->select(DB::raw(1))
                        ->from('purchase_order_ops as poo')
                        ->join('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                        ->join('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->whereColumn('poo.id', 'nfp.purchase_order_ops_id')
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->where('pr.outlet_id', $outletId);
                })
                    ->orWhereExists(function ($subQ) use ($outletId) {
                        $subQ->select(DB::raw(1))
                            ->from('purchase_requisitions as pr')
                            ->whereColumn('pr.id', 'nfp.purchase_requisition_id')
                            ->where('pr.outlet_id', $outletId);
                    });
            })
            ->count('nfp.id');

        $transactions = $this->fetchPrOpsPaymentTransactions($outletId, $start, $end);

        return [
            'total' => $total,
            'payment_count' => $paymentCount,
            'categories' => $categories,
            'transactions' => $transactions,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPrOpsPaymentTransactions(int $outletId, string $start, string $end): array
    {
        $viaPo = DB::table('non_food_payments as nfp')
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
            ->where('pr.outlet_id', $outletId)
            ->select(
                'nfp.id',
                'nfp.payment_number',
                'nfp.payment_date',
                'nfp.amount',
                'nfp.payment_method',
                'pr.pr_number',
                'pr.title',
                'prc.name as category_name',
                'poo.number as po_number',
            )
            ->distinct()
            ->get();

        $direct = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$start, $end])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id')
            ->where('pr.outlet_id', $outletId)
            ->select(
                'nfp.id',
                'nfp.payment_number',
                'nfp.payment_date',
                'nfp.amount',
                'nfp.payment_method',
                'pr.pr_number',
                'pr.title',
                'prc.name as category_name',
                DB::raw('NULL as po_number'),
            )
            ->get();

        $rows = $viaPo->concat($direct)
            ->unique('id')
            ->sortByDesc('payment_date')
            ->values();

        return $rows->map(function ($row) {
            $paymentMethod = (string) ($row->payment_method ?? 'transfer');

            return [
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
            ];
        })->all();
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
     * @return array<string, mixed>
     */
    private function getEmployeeAttendance(int $outletId, string $start, string $end): array
    {
        $employees = DB::table('users')
            ->where('id_outlet', $outletId)
            ->where('status', 'A')
            ->select('id', 'nama_lengkap')
            ->orderBy('nama_lengkap')
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
                'present_days' => $present,
                'total_telat' => (int) ($empSummary['total_telat'] ?? 0),
                'total_lembur' => (int) ($empSummary['total_lembur'] ?? 0),
                'alpa_days' => (int) ($empSummary['alpa_days'] ?? 0),
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
            'summary' => $summary,
            'leave_breakdown' => $leaveBreakdown,
            'composition' => [
                ['key' => 'hadir', 'label' => 'Hadir', 'days' => $summary['present_days']],
                ['key' => 'alpha', 'label' => 'Alpha', 'days' => $summary['alpa_days']],
                ['key' => 'off', 'label' => 'OFF', 'days' => $summary['off_days']],
                ['key' => 'izin', 'label' => 'Izin & Cuti', 'days' => $summary['leave_days']],
            ],
            'top_late' => array_slice($employeeRows, 0, 10),
            'top_overtime' => collect($employeeRows)->sortByDesc('total_lembur')->take(10)->values()->all(),
        ];
    }
}
