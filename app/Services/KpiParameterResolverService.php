<?php

namespace App\Services;

use App\Models\KpiParameter;
use App\Models\UserRegional;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiParameterResolverService
{
    /** @var list<string> */
    private const ANALYZER_RESOLVER_KEYS = [
        'cost_report_cogs',
        'outlet_analyzer_payroll',
        'outlet_analyzer_petty_cash',
        'outlet_internal_use_waste',
        'lost_breakage',
        'guest_comment_gsi',
    ];

    /** @var array<string, array<string, mixed>> */
    private array $analyzerCache = [];

    /** @var array<string, array{total: float, count: int, match: string|null}> */
    private array $revenueCache = [];

    /** @var array<string, float|null> */
    private array $budgetCache = [];

    /** @var array<string, array<int, float>> */
    private array $ticketCountCache = [];

    private ?bool $hasTicketsTable = null;

    private ?bool $hasTicketCategoriesTable = null;

    private ?bool $hasTicketStatusesTable = null;

    private ?bool $hasTrainingTable = null;

    public function __construct(
        private OutletAnalyzerService $outletAnalyzer,
        private RegionalVisitAnalyticsService $regionalVisits,
        private PettyCashLockBudgetService $pettyCashLockBudget,
    ) {}

    /**
     * Pre-load data berat sekali sebelum resolve banyak parameter.
     *
     * @param  array{outlet_ids?: list<int>, outlet_id?: int|null, user_id?: int|null, period_month: string}  $context
     * @param  iterable<KpiParameter>  $parameters
     */
    public function prefetch(array $context, iterable $parameters): void
    {
        $outletIds = $this->outletIdsFromContext($context);
        $periodMonth = (string) ($context['period_month'] ?? '');

        if (empty($outletIds) || !preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return;
        }

        $needsAnalyzer = false;
        foreach ($parameters as $param) {
            $key = $param->erpMapping?->resolver_key ?? '';
            if (in_array($key, self::ANALYZER_RESOLVER_KEYS, true)) {
                $needsAnalyzer = true;
                break;
            }
        }

        if ($needsAnalyzer) {
            $this->prefetchAnalyzers($outletIds, $periodMonth);
        }

        $this->queryOrderRevenue($outletIds, $periodMonth, true);

        $year = (int) substr($periodMonth, 0, 4);
        $month = (int) substr($periodMonth, 5, 2);
        $this->resolveMonthlyBudget($outletIds, $month, $year);

        $period = $this->outletAnalyzer->calendarPeriod($periodMonth);
        $this->getTicketCountsByOutlet($outletIds, $period['start_date'], $period['end_date'], 'complaint', null);
        $this->getTicketCountsByOutlet($outletIds, $period['start_date'], $period['end_date'], 'improvement', null);
        $this->getTicketCountsByOutlet($outletIds, $period['start_date'], $period['end_date'], 'improvement', true);
    }

    /**
     * @param  list<int>  $outletIds
     */
    public function prefetchAnalyzers(array $outletIds, string $periodMonth): void
    {
        foreach (array_unique(array_filter(array_map('intval', $outletIds))) as $outletId) {
            if ($outletId > 0) {
                $this->getAnalyzerData($outletId, $periodMonth);
            }
        }
    }

    /**
     * @param  array{outlet_id?: int|null, user_id?: int|null, period_month: string}  $context
     */
    public function resolve(KpiParameter $parameter, array $context): ?float
    {
        try {
            return $this->resolveValue($parameter, $context);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Diagnosa kenapa nilai ERP bisa kosong / nol.
     *
     * @param  array{outlet_id?: int|null, user_id?: int|null, period_month: string}  $context
     * @return array<string, mixed>
     */
    public function diagnose(array $context): array
    {
        $outletIds = $this->outletIdsFromContext($context);
        $periodMonth = (string) ($context['period_month'] ?? '');
        $erpDataScope = (string) ($context['erp_data_scope'] ?? 'employee_outlet');

        $issues = [];
        $hints = [];

        if (!preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            $issues[] = 'Format periode tidak valid.';
        }

        if ($erpDataScope !== 'all_outlets' && empty($outletIds)) {
            $issues[] = 'Belum ada outlet dalam scope data ERP — pilih outlet di setting evaluasi.';
        }

        $scopeOutlets = DB::table('tbl_data_outlet')
            ->whereIn('id_outlet', $outletIds)
            ->select('id_outlet', 'nama_outlet', 'qr_code')
            ->orderBy('nama_outlet')
            ->get();

        $revenueMtd = null;
        $revenueMatch = null;
        $orderCount = null;
        $budgetAmount = null;

        if (!empty($outletIds) && preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            $revenueProbe = $this->queryOrderRevenue($outletIds, $periodMonth, true);
            $revenueMtd = $revenueProbe['total'];
            $revenueMatch = $revenueProbe['match'];
            $orderCount = $revenueProbe['count'];

            if ($orderCount === 0) {
                $hints[] = 'Tidak ada order POS untuk scope outlet di periode ' . $periodMonth
                    . ' — pastikan outlet yang dipilih benar dan punya transaksi.';
            }

            $year = (int) substr($periodMonth, 0, 4);
            $month = (int) substr($periodMonth, 5, 2);
            $budgetAmount = $this->resolveMonthlyBudget($outletIds, $month, $year);
            if ($budgetAmount === 0.0) {
                $hints[] = "Budget belum di-set di Revenue Targets (Target Pendapatan) atau outlet_monthly_budgets untuk {$periodMonth} — D002 akan 0.";
                $budgetAmount = null;
            }
        }

        if ($this->hasTicketCategoriesTable()) {
            $complaintCats = (int) DB::table('ticket_categories')
                ->where(function ($q) {
                    $q->where('name', 'like', '%complaint%')
                        ->orWhere('name', 'like', '%komplain%')
                        ->orWhere('name', 'like', '%keluhan%');
                })
                ->count();

            if ($complaintCats === 0) {
                $hints[] = 'Tidak ada kategori ticket "complaint/komplain" — D014 akan 0.';
            }
        }

        return [
            'erp_data_scope' => $erpDataScope,
            'scope_label' => match ($erpDataScope) {
                'all_outlets' => 'Semua outlet operasional',
                'employee_outlet' => 'Outlet karyawan',
                'single_outlet' => '1 outlet',
                'multiple_outlets' => 'Beberapa outlet',
                default => $erpDataScope,
            },
            'scope_outlet_ids' => $outletIds,
            'scope_outlet_count' => count($outletIds),
            'scope_outlet_names' => $scopeOutlets->pluck('nama_outlet')->all(),
            'scope_outlet_names_preview' => $erpDataScope === 'all_outlets' && $scopeOutlets->count() > 8
                ? $scopeOutlets->take(8)->pluck('nama_outlet')->push('… +' . ($scopeOutlets->count() - 8) . ' outlet lainnya')->all()
                : $scopeOutlets->pluck('nama_outlet')->all(),
            'period_month' => $periodMonth,
            'order_count' => $orderCount,
            'revenue_mtd' => $revenueMtd,
            'revenue_match_kode' => $revenueMatch,
            'budget_amount' => $budgetAmount,
            'issues' => $issues,
            'hints' => $hints,
        ];
    }

    /**
     * @param  array{outlet_ids?: list<int>, outlet_id?: int|null, user_id?: int|null, period_month: string, erp_data_scope?: string}  $context
     */
    private function resolveValue(KpiParameter $parameter, array $context): ?float
    {
        $mapping = $parameter->erpMapping;
        if (!$mapping || $mapping->status !== 'A') {
            return null;
        }

        $outletIds = $this->outletIdsFromContext($context);
        $periodMonth = (string) ($context['period_month'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return null;
        }

        if ($parameter->scope_type !== 'employee' && empty($outletIds)) {
            return null;
        }

        $period = $this->outletAnalyzer->calendarPeriod($periodMonth);
        $year = (int) substr($periodMonth, 0, 4);
        $month = (int) substr($periodMonth, 5, 2);

        $aggregation = strtolower(trim((string) ($mapping->aggregation ?? 'sum')));

        $standalone = match ($mapping->resolver_key) {
            'daily_revenue_forecast' => $this->resolveOrderPosMetric($outletIds, $periodMonth, $aggregation),
            'pos_order_count' => $this->resolveOrderPosMetric($outletIds, $periodMonth, 'count'),
            'daily_revenue_forecast_budget' => $this->resolveMonthlyBudget($outletIds, $month, $year),
            'petty_cash_lock_budget' => $this->pettyCashLockBudget->sumLockBudgetForOutlets($outletIds, $year, $month),
            'training_compliance' => $this->resolveTrainingCompliance((int) ($context['user_id'] ?? 0), $period['start_date'], $period['end_date']),
            'ticket_complaint_count' => $this->resolveTicketCount($outletIds, $period['start_date'], $period['end_date'], 'complaint'),
            'ticket_improvement_closed' => $this->resolveTicketCount($outletIds, $period['start_date'], $period['end_date'], 'improvement', true),
            'ticket_improvement_total' => $this->resolveTicketCount($outletIds, $period['start_date'], $period['end_date'], 'improvement', false),
            'regional_target_outlet_visits' => $this->resolveRegionalTargetOutletVisits((int) ($context['user_id'] ?? 0)),
            'regional_visit_report' => $this->resolveRegionalUserVisitCount(
                $outletIds,
                $period['start_date'],
                $period['end_date'],
                (int) ($context['user_id'] ?? 0),
            ),
            default => null,
        };

        if ($standalone !== null || in_array($mapping->resolver_key, [
            'daily_revenue_forecast',
            'pos_order_count',
            'daily_revenue_forecast_budget',
            'petty_cash_lock_budget',
            'training_compliance',
            'ticket_complaint_count',
            'ticket_improvement_closed',
            'ticket_improvement_total',
            'regional_target_outlet_visits',
            'regional_visit_report',
        ], true)) {
            return $standalone;
        }

        return match ($mapping->resolver_key) {
            'cost_report_cogs' => $this->sumAnalyzerPath($outletIds, $periodMonth, ['fj_inventory', 'line_total']),
            'outlet_analyzer_payroll' => $this->sumAnalyzerPath($outletIds, $periodMonth, ['payroll', 'total_gaji']),
            'outlet_analyzer_petty_cash' => $this->sumAnalyzerPath($outletIds, $periodMonth, ['petty_cash', 'total']),
            'outlet_internal_use_waste' => $this->sumCategoryCost($outletIds, $periodMonth, $parameter->code),
            'lost_breakage' => $this->sumCategoryCostByType($outletIds, $periodMonth, 'stock_cut'),
            'guest_comment_gsi' => $this->sumAnalyzerPath($outletIds, $periodMonth, ['guest_comment_gsi', 'total_forms']),
            default => null,
        };
    }

    /**
     * @param  array{outlet_ids?: list<int>, outlet_id?: int|null}  $context
     * @return list<int>
     */
    private function outletIdsFromContext(array $context): array
    {
        if (!empty($context['outlet_ids']) && is_array($context['outlet_ids'])) {
            return array_values(array_unique(array_filter(array_map('intval', $context['outlet_ids']))));
        }

        $id = (int) ($context['outlet_id'] ?? 0);

        return $id > 0 ? [$id] : [];
    }

    /**
     * @param  list<int>  $outletIds
     * @param  list<string>  $path
     */
    private function sumAnalyzerPath(array $outletIds, string $periodMonth, array $path): ?float
    {
        $total = 0.0;
        $found = false;

        foreach ($outletIds as $outletId) {
            $analyzer = $this->getAnalyzerData((int) $outletId, $periodMonth);
            if ($analyzer === null) {
                continue;
            }

            $value = $analyzer;
            foreach ($path as $key) {
                $value = is_array($value) ? ($value[$key] ?? null) : null;
                if ($value === null) {
                    break;
                }
            }

            if (is_numeric($value)) {
                $total += (float) $value;
                $found = true;
            }
        }

        return $found ? round($total, 2) : null;
    }

    /**
     * @param  list<int>  $outletIds
     */
    private function sumCategoryCost(array $outletIds, string $periodMonth, string $parameterCode): ?float
    {
        return match ($parameterCode) {
            'D005' => $this->sumCategoryCostByType($outletIds, $periodMonth, 'waste'),
            'D006' => $this->sumCategoryCostByType($outletIds, $periodMonth, 'spoil'),
            default => $this->sumAnalyzerPath($outletIds, $periodMonth, ['category_cost_outlet', 'total']),
        };
    }

    /**
     * @param  list<int>  $outletIds
     */
    private function sumCategoryCostByType(array $outletIds, string $periodMonth, string $type): ?float
    {
        $total = 0.0;
        $found = false;

        foreach ($outletIds as $outletId) {
            $analyzer = $this->getAnalyzerData((int) $outletId, $periodMonth);
            if ($analyzer === null) {
                continue;
            }

            foreach ($analyzer['category_cost_outlet']['modes'] ?? [] as $mode) {
                if (($mode['key'] ?? '') === $type) {
                    $total += (float) ($mode['amount'] ?? 0);
                    $found = true;
                    break;
                }
            }
        }

        return $found ? round($total, 2) : null;
    }

    /**
     * Revenue MTD (sum) atau jumlah order POS sesuai aggregation.
     *
     * @param  list<int>  $outletIds
     */
    private function resolveOrderPosMetric(array $outletIds, string $periodMonth, string $aggregation = 'sum'): ?float
    {
        if (empty($outletIds)) {
            return null;
        }

        $result = $this->queryOrderRevenue($outletIds, $periodMonth, true);
        if ($result['match'] === null) {
            return null;
        }

        return match (strtolower(trim($aggregation))) {
            'count' => (float) $result['count'],
            'avg' => $result['count'] > 0 ? round($result['total'] / $result['count'], 2) : null,
            default => $result['total'],
        };
    }
    /**
     * @param  list<int>  $outletIds
     * @return array{total: float, count: int, match: string|null}
     */
    private function queryOrderRevenue(array $outletIds, string $periodMonth, bool $mtdOnly = true): array
    {
        $cacheKey = $this->scopeCacheKey($outletIds, $periodMonth) . ($mtdOnly ? ':mtd' : ':full');
        if (isset($this->revenueCache[$cacheKey])) {
            return $this->revenueCache[$cacheKey];
        }

        $empty = ['total' => 0.0, 'count' => 0, 'match' => null];
        if (empty($outletIds) || !preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return $this->revenueCache[$cacheKey] = $empty;
        }

        $outlets = DB::table('tbl_data_outlet')
            ->whereIn('id_outlet', array_unique(array_filter(array_map('intval', $outletIds))))
            ->select('id_outlet', 'nama_outlet', 'qr_code')
            ->get();

        if ($outlets->isEmpty()) {
            return $this->revenueCache[$cacheKey] = $empty;
        }

        $year = (int) substr($periodMonth, 0, 4);
        $month = (int) substr($periodMonth, 5, 2);

        $orderQuery = DB::table('orders')
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0);

        if ($mtdOnly) {
            $currentDate = Carbon::now();
            $mtdEndDay = ($currentDate->year === $year && $currentDate->month === $month)
                ? $currentDate->day
                : Carbon::create($year, $month, 1)->daysInMonth;

            $orderQuery->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereDay('created_at', '<=', $mtdEndDay);
        } else {
            $period = $this->outletAnalyzer->calendarPeriod($periodMonth);
            $orderQuery->whereDate('created_at', '>=', $period['start_date'])
                ->whereDate('created_at', '<=', $period['end_date']);
        }

        /** @var \Illuminate\Support\Collection<string, object{total: float, cnt: int}> $aggregates */
        $aggregates = $orderQuery
            ->select('kode_outlet', DB::raw('SUM(grand_total) as total'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('kode_outlet')
            ->get()
            ->keyBy(fn ($row) => strtolower(trim((string) $row->kode_outlet)));

        $total = 0.0;
        $count = 0;
        $matches = [];

        foreach ($outlets as $outlet) {
            $outletId = (int) $outlet->id_outlet;
            $candidates = array_values(array_unique(array_filter([
                trim((string) ($outlet->qr_code ?? '')),
                trim((string) ($outlet->nama_outlet ?? '')),
                (string) $outletId,
            ])));

            $matched = false;
            foreach ($candidates as $code) {
                $row = $aggregates->get(strtolower($code));
                if ($row) {
                    $total += (float) $row->total;
                    $count += (int) $row->cnt;
                    $matches[] = $code;
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                continue;
            }

            $qrCode = trim((string) ($outlet->qr_code ?? ''));
            if ($qrCode === '') {
                continue;
            }

            $joined = DB::table('orders')
                ->join('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
                ->where('o.id_outlet', $outletId)
                ->where('orders.status', '!=', 'cancelled')
                ->where('orders.grand_total', '>', 0);

            if ($mtdOnly) {
                $currentDate = Carbon::now();
                $mtdEndDay = ($currentDate->year === $year && $currentDate->month === $month)
                    ? $currentDate->day
                    : Carbon::create($year, $month, 1)->daysInMonth;

                $joined->whereYear('orders.created_at', $year)
                    ->whereMonth('orders.created_at', $month)
                    ->whereDay('orders.created_at', '<=', $mtdEndDay);
            } else {
                $period = $this->outletAnalyzer->calendarPeriod($periodMonth);
                $joined->whereDate('orders.created_at', '>=', $period['start_date'])
                    ->whereDate('orders.created_at', '<=', $period['end_date']);
            }

            $joinedCount = (int) (clone $joined)->count();
            if ($joinedCount > 0) {
                $total += (float) (clone $joined)->sum('orders.grand_total');
                $count += $joinedCount;
                $matches[] = $qrCode !== '' ? $qrCode : "join:{$outletId}";
            }
        }

        return $this->revenueCache[$cacheKey] = [
            'total' => round($total, 2),
            'count' => $count,
            'match' => $matches !== [] ? implode(', ', array_unique($matches)) : null,
        ];
    }

    private function nullableAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    public function clearCache(): void
    {
        $this->analyzerCache = [];
        $this->revenueCache = [];
        $this->budgetCache = [];
        $this->ticketCountCache = [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getAnalyzerData(int $outletId, string $periodMonth): ?array
    {
        $cacheKey = "{$outletId}:{$periodMonth}";
        if (isset($this->analyzerCache[$cacheKey])) {
            return $this->analyzerCache[$cacheKey];
        }

        $data = $this->outletAnalyzer->analyzeForKpi($outletId, $periodMonth);
        if ($data === null) {
            return null;
        }

        $this->analyzerCache[$cacheKey] = $data;

        return $data;
    }

    /**
     * @param  list<int>  $outletIds
     */
    private function scopeCacheKey(array $outletIds, string $periodMonth): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $outletIds))));
        sort($ids);

        return implode(',', $ids) . ':' . $periodMonth;
    }

    /**
     * @param  list<int>  $outletIds
     */
    private function resolveMonthlyBudget(array $outletIds, int $month, int $year): ?float
    {
        if (empty($outletIds)) {
            return null;
        }

        $cacheKey = $this->scopeCacheKey($outletIds, "{$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT)) . ':budget';
        if (array_key_exists($cacheKey, $this->budgetCache)) {
            return $this->budgetCache[$cacheKey];
        }

        $outletIdList = array_values(array_unique(array_filter(array_map('intval', $outletIds))));
        $monthStart = sprintf('%04d-%02d-01', $year, $month);

        $revenueTargetTotal = 0.0;
        $hasRevenueTarget = false;
        foreach ($outletIdList as $outletId) {
            if ($outletId <= 0) {
                continue;
            }

            $header = DB::table('outlet_revenue_target_headers')
                ->where('outlet_id', $outletId)
                ->where('target_month', $monthStart)
                ->first(['id', 'monthly_target']);

            if ($header === null) {
                continue;
            }

            $amount = (float) ($header->monthly_target ?? 0);
            if ($amount <= 0) {
                $amount = (float) (DB::table('outlet_revenue_target_details')
                    ->where('header_id', $header->id)
                    ->sum('forecast_revenue') ?? 0);
            }

            if ($amount > 0) {
                $hasRevenueTarget = true;
                $revenueTargetTotal += $amount;
            }
        }

        if ($hasRevenueTarget) {
            return $this->budgetCache[$cacheKey] = round($revenueTargetTotal, 2);
        }

        $qrCodes = DB::table('tbl_data_outlet')
            ->whereIn('id_outlet', $outletIdList)
            ->pluck('qr_code')
            ->map(fn ($qr) => trim((string) $qr))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($qrCodes === []) {
            return $this->budgetCache[$cacheKey] = 0.0;
        }

        $budgets = DB::table('outlet_monthly_budgets')
            ->whereIn('outlet_qr_code', $qrCodes)
            ->where('month', $month)
            ->where('year', $year)
            ->pluck('budget_amount');

        if ($budgets->isEmpty()) {
            return $this->budgetCache[$cacheKey] = 0.0;
        }

        return $this->budgetCache[$cacheKey] = round((float) $budgets->sum(), 2);
    }

    private function resolveRegionalTargetOutletVisits(int $userId): ?float
    {
        if ($userId <= 0) {
            return null;
        }

        $target = UserRegional::where('user_id', $userId)->value('target_outlet_visits');
        if ($target === null || $target === '') {
            return null;
        }

        return (float) $target;
    }

    /**
     * Jumlah hari kunjungan outlet oleh karyawan KPI (bukan semua regional user).
     *
     * @param  list<int>  $outletIds
     */
    private function resolveRegionalUserVisitCount(array $outletIds, string $startDate, string $endDate, int $userId): ?float
    {
        if ($userId <= 0 || empty($outletIds)) {
            return null;
        }

        if (! UserRegional::where('user_id', $userId)->exists()) {
            return null;
        }

        $totalVisitDays = 0;

        foreach (array_unique(array_filter(array_map('intval', $outletIds))) as $outletId) {
            if ($outletId <= 0) {
                continue;
            }

            $detail = $this->regionalVisits->getOutletVisitDetail([$userId], $outletId, $startDate, $endDate);
            $totalVisitDays += (int) ($detail['summary']['visit_days'] ?? 0);
        }

        return (float) $totalVisitDays;
    }

    private function resolveTrainingCompliance(int $userId, string $start, string $end): ?float
    {
        if ($userId <= 0) {
            return null;
        }

        if (!$this->hasTrainingTable()) {
            return null;
        }

        $stats = DB::table('training_assignments')
            ->where('user_id', $userId)
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->first();

        $total = (int) ($stats->total ?? 0);
        if ($total === 0) {
            return null;
        }

        return round(((int) ($stats->completed ?? 0) / $total) * 100, 2);
    }

    /**
     * @param  list<int>  $outletIds
     */
    private function resolveTicketCount(array $outletIds, string $start, string $end, string $kind, ?bool $closedOnly = null): ?float
    {
        if (empty($outletIds)) {
            return null;
        }

        $counts = $this->getTicketCountsByOutlet($outletIds, $start, $end, $kind, $closedOnly);

        return array_sum($counts);
    }

    /**
     * @param  list<int>  $outletIds
     * @return array<int, float>
     */
    private function getTicketCountsByOutlet(array $outletIds, string $start, string $end, string $kind, ?bool $closedOnly): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $outletIds))));
        sort($ids);

        $cacheKey = implode(',', $ids) . ":{$start}:{$end}:{$kind}:" . ($closedOnly === true ? 'closed' : ($closedOnly === false ? 'all' : 'any'));
        if (isset($this->ticketCountCache[$cacheKey])) {
            return $this->ticketCountCache[$cacheKey];
        }

        $empty = array_fill_keys($ids, 0.0);
        if (!$this->hasTicketsTable() || !$this->hasTicketCategoriesTable()) {
            return $this->ticketCountCache[$cacheKey] = $empty;
        }

        $query = DB::table('tickets as t')
            ->join('ticket_categories as tc', 't.category_id', '=', 'tc.id')
            ->whereIn('t.outlet_id', $ids)
            ->whereDate('t.created_at', '>=', $start)
            ->whereDate('t.created_at', '<=', $end);

        if ($kind === 'complaint') {
            $query->where(function ($q) {
                $q->where('tc.name', 'like', '%complaint%')
                    ->orWhere('tc.name', 'like', '%komplain%')
                    ->orWhere('tc.name', 'like', '%keluhan%')
                    ->orWhere('tc.description', 'like', '%complaint%')
                    ->orWhere('tc.description', 'like', '%komplain%');
            });
        } else {
            $query->where(function ($q) {
                $q->where('tc.name', 'like', '%improvement%')
                    ->orWhere('tc.name', 'like', '%perbaikan%')
                    ->orWhere('tc.name', 'like', '%action%')
                    ->orWhere('tc.description', 'like', '%improvement%')
                    ->orWhere('tc.description', 'like', '%perbaikan%');
            });

            if ($closedOnly === true && $this->hasTicketStatusesTable()) {
                $query->join('ticket_statuses as ts', 't.status_id', '=', 'ts.id')
                    ->where(function ($q) {
                        $q->where('ts.is_final', 1)
                            ->orWhereIn('ts.slug', ['closed', 'resolved', 'done']);
                    });
            }
        }

        $rows = $query
            ->select('t.outlet_id', DB::raw('COUNT(t.id) as cnt'))
            ->groupBy('t.outlet_id')
            ->pluck('cnt', 'outlet_id');

        foreach ($ids as $id) {
            $empty[$id] = (float) ($rows[$id] ?? 0);
        }

        return $this->ticketCountCache[$cacheKey] = $empty;
    }

    private function hasTicketsTable(): bool
    {
        return $this->hasTicketsTable ??= DB::getSchemaBuilder()->hasTable('tickets');
    }

    private function hasTicketCategoriesTable(): bool
    {
        return $this->hasTicketCategoriesTable ??= DB::getSchemaBuilder()->hasTable('ticket_categories');
    }

    private function hasTicketStatusesTable(): bool
    {
        return $this->hasTicketStatusesTable ??= DB::getSchemaBuilder()->hasTable('ticket_statuses');
    }

    private function hasTrainingTable(): bool
    {
        return $this->hasTrainingTable ??= DB::getSchemaBuilder()->hasTable('training_assignments');
    }
}
