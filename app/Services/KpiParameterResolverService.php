<?php

namespace App\Services;

use App\Models\KpiParameter;
use App\Models\UserRegional;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

    /** @var list<string> */
    private const MANUAL_COGS_RESOLVER_KEYS = [
        'manual_cogs_percent',
        'manual_deviation_percent',
        'manual_catcost_percent',
    ];

    /**
     * Bulan data KPI untuk resolver — prioritas data_period_month, fallback evaluasi -1 bulan.
     */
    private function dataPeriodMonthFromContext(array $context): string
    {
        $dataMonth = (string) ($context['data_period_month'] ?? '');
        if (preg_match('/^\d{4}-\d{2}$/', $dataMonth)) {
            return $dataMonth;
        }

        $evaluationMonth = (string) ($context['evaluation_period_month'] ?? '');
        if (preg_match('/^\d{4}-\d{2}$/', $evaluationMonth)) {
            return Carbon::createFromFormat('Y-m', $evaluationMonth)
                ->subMonth()
                ->format('Y-m');
        }

        return (string) ($context['period_month'] ?? '');
    }

    /** @var array<string, array<string, mixed>> */
    private array $analyzerCache = [];

    /** @var array<string, array{total: float, count: int, match: string|null}> */
    private array $revenueCache = [];

    /** @var array<string, float|null> */
    private array $budgetCache = [];

    /** @var array<string, array<int, float>> */
    private array $ticketCountCache = [];

    /** @var array<string, array<int, float>> */
    private array $pettyCashBudgetByOutletCache = [];

    private const PERSISTENT_ANALYZER_CACHE_TTL_MINUTES = 120;

    private ?bool $hasTicketsTable = null;

    private ?bool $hasTicketCategoriesTable = null;

    private ?bool $hasTicketStatusesTable = null;

    private ?bool $hasTrainingTable = null;

    /** @var list<string> */
    private const CVCC_NEGATIVE_SEVERITIES = [
        'minor', 'major', 'critical', 'mild_negative', 'negative', 'severe',
    ];

    public function __construct(
        private OutletAnalyzerService $outletAnalyzer,
        private RegionalVisitAnalyticsService $regionalVisits,
        private PettyCashLockBudgetService $pettyCashLockBudget,
        private FeedbackCapaService $feedbackCapaService,
    ) {}

    /**
     * Pre-load data berat sekali sebelum resolve banyak parameter.
     *
     * @param  array{outlet_ids?: list<int>, outlet_id?: int|null, user_id?: int|null, period_month: string}  $context
     * @param  iterable<KpiParameter>  $parameters
     */
    public function prefetch(array $context, iterable $parameters): void
    {
        $this->prefetchForParameters($context, $parameters);
    }

    /**
     * Pre-load hanya data yang dibutuhkan parameter terkait (lebih ringan dari prefetch penuh).
     *
     * @param  array{outlet_ids?: list<int>, outlet_id?: int|null, user_id?: int|null, period_month: string}  $context
     * @param  iterable<KpiParameter>  $parameters
     */
    public function prefetchForParameters(array $context, iterable $parameters): void
    {
        $outletIds = $this->outletIdsFromContext($context);
        $periodMonth = $this->dataPeriodMonthFromContext($context);

        if (empty($outletIds) || ! preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return;
        }

        $resolverKeys = [];
        foreach ($parameters as $param) {
            $key = $param->erpMapping?->resolver_key ?? '';
            if ($key !== '') {
                $resolverKeys[$key] = true;
            }
        }

        if ($resolverKeys === []) {
            return;
        }

        if (! empty(array_intersect(array_keys($resolverKeys), self::ANALYZER_RESOLVER_KEYS))) {
            $this->prefetchAnalyzers($outletIds, $periodMonth);
        }

        if (isset($resolverKeys['daily_revenue_forecast']) || isset($resolverKeys['pos_order_count'])) {
            $useFullCalendarMonth = (bool) ($context['use_full_calendar_month'] ?? true);
            $this->queryOrderRevenue($outletIds, $periodMonth, ! $useFullCalendarMonth);
        }

        $year = (int) substr($periodMonth, 0, 4);
        $month = (int) substr($periodMonth, 5, 2);

        if (isset($resolverKeys['daily_revenue_forecast_budget'])) {
            $this->resolveMonthlyBudget($outletIds, $month, $year);
        }

        if (isset($resolverKeys['petty_cash_lock_budget'])) {
            $this->prefetchPettyCashLockBudgets($outletIds, $year, $month);
        }

        $period = $this->outletAnalyzer->calendarPeriod($periodMonth);
        if (isset($resolverKeys['ticket_complaint_count'])) {
            $this->getTicketCountsByOutlet($outletIds, $period['start_date'], $period['end_date'], 'complaint', null);
        }
        if (isset($resolverKeys['ticket_improvement_closed'])) {
            $this->getTicketCountsByOutlet($outletIds, $period['start_date'], $period['end_date'], 'improvement', true);
        }
        if (isset($resolverKeys['ticket_improvement_total'])) {
            $this->getTicketCountsByOutlet($outletIds, $period['start_date'], $period['end_date'], 'improvement', false);
        }

        if (isset($resolverKeys['retail_petty_cash_usage'])) {
            $this->resolveRetailPettyCashUsage($outletIds, $periodMonth);
        }

        if (! empty(array_intersect(array_keys($resolverKeys), self::MANUAL_COGS_RESOLVER_KEYS))) {
            $year = (int) substr($periodMonth, 0, 4);
            $month = (int) substr($periodMonth, 5, 2);
            $this->resolveManualOutletPercent(
                'manual_cogs_deviation_catcost',
                'manual_cogs_deviation_catcost_items',
                'cogs_percent',
                $outletIds,
                $month,
                $year,
            );
        }
    }

    /**
     * Resolve semua parameter untuk tiap outlet — satu prefetch, lalu baca cache.
     *
     * @param  list<int>  $outletIds
     * @param  iterable<KpiParameter>  $parameters
     * @param  array<string, mixed>  $baseContext
     * @return array<int, array<string, ?float>>
     */
    public function resolveParameterGridForOutlets(array $outletIds, iterable $parameters, array $baseContext): array
    {
        $params = $parameters instanceof Collection ? $parameters : collect($parameters);
        $grid = [];

        foreach ($outletIds as $outletId) {
            $grid[(int) $outletId] = [];
        }

        if ($params->isEmpty() || $outletIds === []) {
            return $grid;
        }

        $this->prefetchForParameters($baseContext, $params);

        foreach ($outletIds as $outletId) {
            $outletId = (int) $outletId;
            $context = array_merge($baseContext, [
                'outlet_ids' => [$outletId],
                'outlet_id' => $outletId,
            ]);

            foreach ($params as $param) {
                // Parameter employee-scope (mis. target kunjungan total) bukan nilai per outlet.
                if ($param->scope_type === 'employee') {
                    $grid[$outletId][$param->code] = null;
                    continue;
                }

                $grid[$outletId][$param->code] = $this->resolve($param, $context);
            }
        }

        return $grid;
    }

    /**
     * @param  list<int>  $outletIds
     */
    public function clearPersistentCaches(array $outletIds, string $periodMonth): void
    {
        foreach (array_unique(array_filter(array_map('intval', $outletIds))) as $outletId) {
            if ($outletId > 0) {
                Cache::forget($this->persistentAnalyzerCacheKey($outletId, $periodMonth));
            }
        }
    }

    private function persistentAnalyzerCacheKey(int $outletId, string $periodMonth): string
    {
        return 'kpi_outlet_analyzer:' . $outletId . ':' . $periodMonth;
    }

    /**
     * @param  list<int>  $outletIds
     */
    private function prefetchPettyCashLockBudgets(array $outletIds, int $year, int $month): void
    {
        $cacheKey = $this->scopeCacheKey($outletIds, sprintf('%04d-%02d', $year, $month)) . ':petty_lock';
        if (isset($this->pettyCashBudgetByOutletCache[$cacheKey])) {
            return;
        }

        $this->pettyCashBudgetByOutletCache[$cacheKey] = $this->pettyCashLockBudget
            ->lockBudgetsByOutlet($outletIds, $year, $month);
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
        $periodMonth = $this->dataPeriodMonthFromContext($context);
        $erpDataScope = (string) ($context['erp_data_scope'] ?? 'employee_outlet');
        /** @var list<string> $parameterCodes */
        $parameterCodes = array_values(array_unique(array_filter(
            array_map('strval', $context['parameter_codes'] ?? []),
        )));
        $usesParameter = static fn (string $code): bool => $parameterCodes === [] || in_array($code, $parameterCodes, true);

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
            $useFullCalendarMonth = (bool) ($context['use_full_calendar_month'] ?? true);
            $revenueProbe = $this->queryOrderRevenue($outletIds, $periodMonth, ! $useFullCalendarMonth);
            $revenueMtd = $revenueProbe['total'];
            $revenueMatch = $revenueProbe['match'];
            $orderCount = $revenueProbe['count'];

            if ($orderCount === 0 && $usesParameter('D001')) {
                $hints[] = 'Tidak ada order POS untuk scope outlet di periode ' . $periodMonth
                    . ' — pastikan outlet yang dipilih benar dan punya transaksi.';
            }

            $year = (int) substr($periodMonth, 0, 4);
            $month = (int) substr($periodMonth, 5, 2);
            $budgetAmount = $this->resolveMonthlyBudget($outletIds, $month, $year);
            if ($budgetAmount === 0.0 && $usesParameter('D002')) {
                $hints[] = "Budget belum di-set di Revenue Targets (Target Pendapatan) atau outlet_monthly_budgets untuk {$periodMonth} — D002 akan 0.";
                $budgetAmount = null;
            }

            $usesManualCogs = $usesParameter('D048') || $usesParameter('D049') || $usesParameter('D050');
            if ($usesManualCogs && DB::getSchemaBuilder()->hasTable('manual_cogs_deviation_catcost')) {
                $manualHeaderExists = DB::table('manual_cogs_deviation_catcost')
                    ->where('month', $month)
                    ->where('year', $year)
                    ->exists();

                if (! $manualHeaderExists) {
                    $hints[] = "Manual COGS, Deviation & Catcost belum diinput untuk {$periodMonth} (bulan data KPI) — D048/D049/D050 akan kosong.";
                }
            }

            $usesCvcc = $usesParameter('D053') || $usesParameter('D054') || $usesParameter('D055');
            if ($usesCvcc) {
                $userId = (int) ($context['user_id'] ?? 0);
                $cvccScope = $userId > 0 ? $this->resolveCvccRegionalScope($userId) : null;
                if ($cvccScope === null) {
                    $hints[] = 'Karyawan belum terdaftar di Regional Management — parameter CVCC (D053/D054/D055) akan kosong.';
                }
            }
        }

        if ($usesParameter('D014') && $this->hasTicketCategoriesTable()) {
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
            'evaluation_period_month' => (string) ($context['evaluation_period_month'] ?? ''),
            'data_period_month' => $periodMonth,
            'data_period_start' => (string) ($context['period_start'] ?? ''),
            'data_period_end' => (string) ($context['period_end'] ?? ''),
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
        $periodMonth = $this->dataPeriodMonthFromContext($context);

        if (!preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return null;
        }

        if ($parameter->scope_type !== 'employee' && empty($outletIds)) {
            return null;
        }

        $period = $this->outletAnalyzer->calendarPeriod($periodMonth);
        $year = (int) substr($periodMonth, 0, 4);
        $month = (int) substr($periodMonth, 5, 2);
        $useFullCalendarMonth = (bool) ($context['use_full_calendar_month'] ?? true);
        $attendanceStart = (string) ($context['attendance_start'] ?? $this->outletAnalyzer->payrollPeriod($periodMonth)['start_date']);
        $attendanceEnd = (string) ($context['attendance_end'] ?? $this->outletAnalyzer->payrollPeriod($periodMonth)['end_date']);

        $aggregation = strtolower(trim((string) ($mapping->aggregation ?? 'sum')));

        $standalone = match ($mapping->resolver_key) {
            'daily_revenue_forecast' => $this->resolveOrderPosMetric($outletIds, $periodMonth, $aggregation, $useFullCalendarMonth),
            'pos_order_count' => $this->resolveOrderPosMetric($outletIds, $periodMonth, 'count', $useFullCalendarMonth),
            'daily_revenue_forecast_budget' => $this->resolveMonthlyBudget($outletIds, $month, $year),
            'petty_cash_lock_budget' => $this->pettyCashLockBudget->sumLockBudgetForOutlets($outletIds, $year, $month),
            'training_compliance' => $this->resolveTrainingCompliance((int) ($context['user_id'] ?? 0), $attendanceStart, $attendanceEnd),
            'just_academy_training_completion' => $this->resolveJustAcademyTrainingCompletion(
                (int) ($context['user_id'] ?? 0),
                $periodMonth,
            ),
            'qa2_audit1_score' => $this->resolveQa2Audit1Score(
                $outletIds,
                $period['start_date'],
                $period['end_date'],
            ),
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
            'retail_petty_cash_usage' => $this->resolveRetailPettyCashUsage($outletIds, $periodMonth),
            'manual_cogs_percent' => $this->resolveManualOutletPercent(
                'manual_cogs_deviation_catcost',
                'manual_cogs_deviation_catcost_items',
                'cogs_percent',
                $outletIds,
                $month,
                $year,
            ),
            'manual_deviation_percent' => $this->resolveManualOutletPercent(
                'manual_cogs_deviation_catcost',
                'manual_cogs_deviation_catcost_items',
                'deviation_percent',
                $outletIds,
                $month,
                $year,
            ),
            'manual_catcost_percent' => $this->resolveManualOutletPercent(
                'manual_cogs_deviation_catcost',
                'manual_cogs_deviation_catcost_items',
                'catcost_percent',
                $outletIds,
                $month,
                $year,
            ),
            'manual_lost_breakage_percent' => $this->resolveManualOutletPercent(
                'asset_manual_monthly_lost_breakage',
                'asset_manual_monthly_lost_breakage_items',
                'lost_breakage_percent',
                $outletIds,
                $month,
                $year,
            ),
            'manual_labor_cost_percent' => $this->resolveManualOutletPercent(
                'manual_monthly_labor_cost',
                'manual_monthly_labor_cost_items',
                'labor_cost_percent',
                $outletIds,
                $month,
                $year,
            ),
            'cvcc_avg_resolution_hours' => $this->resolveCvccAvgResolutionHours(
                (int) ($context['user_id'] ?? 0),
                $period['start_date'],
                $period['end_date'],
            ),
            'cvcc_service_negative_complaint_count' => $this->resolveCvccServiceNegativeComplaintCount(
                (int) ($context['user_id'] ?? 0),
                $period['start_date'],
                $period['end_date'],
            ),
            'cvcc_total_review_count' => $this->resolveCvccTotalReviewCount(
                (int) ($context['user_id'] ?? 0),
                $period['start_date'],
                $period['end_date'],
            ),
            default => null,
        };

        if ($standalone !== null || in_array($mapping->resolver_key, [
            'daily_revenue_forecast',
            'pos_order_count',
            'daily_revenue_forecast_budget',
            'petty_cash_lock_budget',
            'training_compliance',
            'just_academy_training_completion',
            'qa2_audit1_score',
            'ticket_complaint_count',
            'ticket_improvement_closed',
            'ticket_improvement_total',
            'regional_target_outlet_visits',
            'regional_visit_report',
            'retail_petty_cash_usage',
            'manual_cogs_percent',
            'manual_deviation_percent',
            'manual_catcost_percent',
            'manual_lost_breakage_percent',
            'manual_labor_cost_percent',
            'cvcc_avg_resolution_hours',
            'cvcc_service_negative_complaint_count',
            'cvcc_total_review_count',
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
     * Revenue bulan kalender penuh (sum) atau jumlah order POS sesuai aggregation.
     *
     * @param  list<int>  $outletIds
     */
    private function resolveOrderPosMetric(
        array $outletIds,
        string $periodMonth,
        string $aggregation = 'sum',
        bool $useFullCalendarMonth = false,
    ): ?float {
        if (empty($outletIds)) {
            return null;
        }

        $result = $this->queryOrderRevenue($outletIds, $periodMonth, ! $useFullCalendarMonth);
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
        $this->pettyCashBudgetByOutletCache = [];
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

        $persistentKey = $this->persistentAnalyzerCacheKey($outletId, $periodMonth);
        $data = Cache::remember(
            $persistentKey,
            now()->addMinutes(self::PERSISTENT_ANALYZER_CACHE_TTL_MINUTES),
            fn () => $this->outletAnalyzer->analyzeForKpi($outletId, $periodMonth),
        );

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
     * Just Academy — % modul wajib (materi + quiz lulus) selesai pada jadwal training bulan evaluasi.
     */
    private function resolveJustAcademyTrainingCompletion(int $userId, string $periodMonth): ?float
    {
        if ($userId <= 0 || ! preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return null;
        }

        if (! DB::getSchemaBuilder()->hasTable('ja_schedules')) {
            return null;
        }

        $rangeStart = sprintf('%s-01 00:00:00', $periodMonth);
        $rangeEnd = date('Y-m-t 23:59:59', strtotime($rangeStart));

        $scheduleIds = DB::table('ja_schedule_participants as sp')
            ->join('ja_schedules as s', 's.id', '=', 'sp.schedule_id')
            ->where('sp.user_id', $userId)
            ->whereIn('s.status', ['published', 'ongoing', 'completed'])
            ->where('s.start_at', '>=', $rangeStart)
            ->where('s.start_at', '<=', $rangeEnd)
            ->pluck('s.id');

        if ($scheduleIds->isEmpty()) {
            return null;
        }

        $requiredItems = DB::table('ja_program_items as pi')
            ->join('ja_schedules as s', 's.program_id', '=', 'pi.program_id')
            ->whereIn('s.id', $scheduleIds)
            ->where('pi.is_required', 1)
            ->select('s.id as schedule_id', 'pi.item_type', 'pi.material_id', 'pi.quiz_id')
            ->get();

        if ($requiredItems->isEmpty()) {
            return null;
        }

        $totalRequired = 0;
        $totalCompleted = 0;

        foreach ($requiredItems as $item) {
            $totalRequired++;

            if ($item->item_type === 'material' && $item->material_id) {
                $done = DB::table('ja_material_progress')
                    ->where('schedule_id', $item->schedule_id)
                    ->where('user_id', $userId)
                    ->where('material_id', $item->material_id)
                    ->exists();
                if ($done) {
                    $totalCompleted++;
                }

                continue;
            }

            if ($item->item_type === 'quiz' && $item->quiz_id) {
                $done = DB::table('ja_quiz_attempts')
                    ->where('schedule_id', $item->schedule_id)
                    ->where('user_id', $userId)
                    ->where('quiz_id', $item->quiz_id)
                    ->whereNotNull('submitted_at')
                    ->where('passed', 1)
                    ->exists();
                if ($done) {
                    $totalCompleted++;
                }
            }
        }

        if ($totalRequired === 0) {
            return null;
        }

        return round(($totalCompleted / $totalRequired) * 100, 2);
    }

    /**
     * QA Audit 1 (QA2 Audits) — rata-rata skor C / (C + NC) audit submitted per outlet scope.
     *
     * @param  list<int>  $outletIds
     */
    private function resolveQa2Audit1Score(array $outletIds, string $startDate, string $endDate): ?float
    {
        if (empty($outletIds) || ! DB::getSchemaBuilder()->hasTable('qa2_audits')) {
            return null;
        }

        $auditIds = DB::table('qa2_audits as a')
            ->join('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->whereIn('a.outlet_id', array_map('intval', $outletIds))
            ->where('a.status', 'submitted')
            ->whereBetween('a.audit_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where(function ($q) {
                $q->where('t.name', 'like', '%Audit 1%')
                    ->orWhere('t.name', 'like', '%Audit1%')
                    ->orWhere('t.code', 'like', '%AUDIT1%')
                    ->orWhere('t.code', 'like', '%AUDIT_1%');
            })
            ->pluck('a.id');

        if ($auditIds->isEmpty()) {
            return null;
        }

        $stats = DB::table('qa2_audit_items')
            ->whereIn('audit_id', $auditIds)
            ->whereIn('result', ['C', 'NC'])
            ->selectRaw("SUM(CASE WHEN result = 'C' THEN 1 ELSE 0 END) as compliant")
            ->selectRaw('COUNT(*) as total')
            ->first();

        $total = (int) ($stats->total ?? 0);
        if ($total === 0) {
            return null;
        }

        return round(((int) ($stats->compliant ?? 0) / $total) * 100, 2);
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
        if (! $this->hasTicketsTable()) {
            return $this->ticketCountCache[$cacheKey] = $empty;
        }

        if ($kind === 'improvement') {
            return $this->ticketCountCache[$cacheKey] = $this->countTicketingTicketsByOutlet(
                $ids,
                $start,
                $end,
                $closedOnly === true,
            );
        }

        if (! $this->hasTicketCategoriesTable()) {
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

    /**
     * Semua ticket Ticketing System (exclude cancelled) — dipakai KPI D023/D024.
     *
     * @param  list<int>  $outletIds
     * @return array<int, float>
     */
    private function countTicketingTicketsByOutlet(
        array $outletIds,
        string $start,
        string $end,
        bool $closedOnly,
    ): array {
        $counts = array_fill_keys($outletIds, 0.0);

        $query = DB::table('tickets as t')
            ->whereIn('t.outlet_id', $outletIds)
            ->whereDate('t.created_at', '>=', $start)
            ->whereDate('t.created_at', '<=', $end);

        if ($this->hasTicketStatusesTable()) {
            $query->join('ticket_statuses as ts', 't.status_id', '=', 'ts.id')
                ->where('ts.slug', '!=', 'cancelled');

            if ($closedOnly) {
                $query->where('ts.slug', 'closed');
            }
        }

        $rows = $query
            ->select('t.outlet_id', DB::raw('COUNT(t.id) as cnt'))
            ->groupBy('t.outlet_id')
            ->pluck('cnt', 'outlet_id');

        foreach ($outletIds as $id) {
            $counts[$id] = (float) ($rows[$id] ?? 0);
        }

        return $counts;
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

    /**
     * Retail Food + Retail Non Food (approved, non contra_bon) — sama seperti Retail Food lock budget.
     *
     * @param  list<int>  $outletIds
     */
    private function resolveRetailPettyCashUsage(array $outletIds, string $periodMonth): ?float
    {
        if (empty($outletIds) || ! preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return null;
        }

        $total = 0.0;
        $found = false;

        foreach ($outletIds as $outletId) {
            $outletId = (int) $outletId;
            if ($outletId <= 0) {
                continue;
            }

            $retailFood = (float) (DB::table('retail_food')
                ->where('outlet_id', $outletId)
                ->where('status', 'approved')
                ->where('payment_method', '!=', 'contra_bon')
                ->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$periodMonth])
                ->sum('total_amount') ?? 0);

            $retailNonFood = (float) (DB::table('retail_non_food')
                ->where('outlet_id', $outletId)
                ->where('status', 'approved')
                ->where('payment_method', '!=', 'contra_bon')
                ->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$periodMonth])
                ->sum('total_amount') ?? 0);

            if ($retailFood > 0 || $retailNonFood > 0) {
                $found = true;
            }

            $total += $retailFood + $retailNonFood;
        }

        return $found ? round($total, 2) : null;
    }

    /**
     * Rata-rata persen per outlet dari menu manual bulanan (COGS / Deviation / Catcost / L&B / Labor).
     *
     * @param  list<int>  $outletIds
     */
    private function resolveManualOutletPercent(
        string $headerTable,
        string $itemsTable,
        string $percentColumn,
        array $outletIds,
        int $month,
        int $year,
    ): ?float {
        if (empty($outletIds) || ! DB::getSchemaBuilder()->hasTable($headerTable)) {
            return null;
        }

        $headerId = DB::table($headerTable)
            ->where('month', $month)
            ->where('year', $year)
            ->value('id');

        if (! $headerId) {
            return null;
        }

        $fk = match ($headerTable) {
            'manual_cogs_deviation_catcost' => 'manual_cogs_deviation_catcost_id',
            'asset_manual_monthly_lost_breakage' => 'asset_manual_monthly_lost_breakage_id',
            'manual_monthly_labor_cost' => 'manual_monthly_labor_cost_id',
            default => null,
        };

        if ($fk === null) {
            return null;
        }

        if ($headerTable === 'manual_cogs_deviation_catcost') {
            return $this->averageManualCogsDeviationCatcostPercent($itemsTable, $fk, (int) $headerId, $percentColumn, $outletIds);
        }

        $values = DB::table($itemsTable)
            ->where($fk, $headerId)
            ->whereIn('outlet_id', array_map('intval', $outletIds))
            ->whereNotNull($percentColumn)
            ->pluck($percentColumn)
            ->map(fn ($v) => (float) $v);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->avg(), 4);
    }

    /**
     * Ambil % dari kolom persen; jika kosong/0 tapi nilai rupiah ada, hitung dari nilai / cogs_value.
     *
     * @param  list<int>  $outletIds
     */
    private function averageManualCogsDeviationCatcostPercent(
        string $itemsTable,
        string $fk,
        int $headerId,
        string $percentColumn,
        array $outletIds,
    ): ?float {
        $rows = DB::table($itemsTable)
            ->where($fk, $headerId)
            ->whereIn('outlet_id', array_map('intval', $outletIds))
            ->get([
                'cogs_value',
                'cogs_percent',
                'deviation_value',
                'deviation_percent',
                'catcost_value',
                'catcost_percent',
            ]);

        if ($rows->isEmpty()) {
            return null;
        }

        $values = $rows
            ->map(function ($row) use ($percentColumn) {
                return match ($percentColumn) {
                    'cogs_percent' => $this->resolveManualCogsMetricPercent(
                        (float) ($row->cogs_percent ?? 0),
                        (float) ($row->cogs_value ?? 0),
                        (float) ($row->cogs_value ?? 0),
                    ),
                    'deviation_percent' => $this->resolveManualCogsMetricPercent(
                        (float) ($row->deviation_percent ?? 0),
                        (float) ($row->deviation_value ?? 0),
                        (float) ($row->cogs_value ?? 0),
                    ),
                    'catcost_percent' => $this->resolveManualCogsMetricPercent(
                        (float) ($row->catcost_percent ?? 0),
                        (float) ($row->catcost_value ?? 0),
                        (float) ($row->cogs_value ?? 0),
                    ),
                    default => null,
                };
            })
            ->filter(fn ($value) => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->avg(), 4);
    }

    /**
     * Prioritas kolom % manual; fallback hitung (nilai / cogs_value) × 100.
     */
    private function resolveManualCogsMetricPercent(float $storedPercent, float $metricValue, float $cogsValue): ?float
    {
        if (abs($storedPercent) > 0.0000001) {
            return $storedPercent;
        }

        if (abs($metricValue) < 0.0000001) {
            return 0.0;
        }

        if (abs($cogsValue) < 0.0000001) {
            return null;
        }

        return ($metricValue / $cogsValue) * 100;
    }

    /**
     * Scope CVCC dari Regional Management: area user + bawahan (jika ada).
     *
     * @return array{regional_user_ids: list<int>, area: string, capa_division: string}|null
     */
    private function resolveCvccRegionalScope(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $assignment = UserRegional::query()->where('user_id', $userId)->first();
        if ($assignment === null || ! in_array($assignment->area, UserRegional::AREAS, true)) {
            return null;
        }

        $jabatanId = (int) (DB::table('users')->where('id', $userId)->value('id_jabatan') ?? 0);

        $subordinateIds = [];
        if ($jabatanId > 0 && DB::getSchemaBuilder()->hasColumn('user_regional', 'supervisor_position_id')) {
            $subordinateIds = DB::table('user_regional')
                ->where('supervisor_position_id', $jabatanId)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();
        }

        $regionalUserIds = $subordinateIds !== []
            ? array_values(array_unique(array_merge([$userId], $subordinateIds)))
            : [$userId];

        return [
            'regional_user_ids' => $regionalUserIds,
            'area' => (string) $assignment->area,
            'capa_division' => $this->regionalAreaToCapaDivision((string) $assignment->area),
        ];
    }

    private function regionalAreaToCapaDivision(string $area): string
    {
        return match ($area) {
            'Bar' => 'bar',
            'Kitchen' => 'kitchen',
            'Service' => 'service',
            default => strtolower($area),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeFeedbackCaseMeta(mixed $meta): ?array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if ($meta === null || $meta === '') {
            return null;
        }

        $decoded = json_decode((string) $meta, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<int>
     */
    private function extractRegionalUserIdsFromMetaArray(array $meta): array
    {
        if (! isset($meta['regional_user_ids']) || ! is_array($meta['regional_user_ids'])) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $meta['regional_user_ids']),
            fn ($id) => $id > 0,
        )));
    }

    /**
     * @param  array{regional_user_ids: list<int>, area: string, capa_division: string}  $scope
     */
    private function caseMatchesCvccRegionalScope(mixed $meta, array $scope, bool $requireCapaInput = false): bool
    {
        $decoded = $this->decodeFeedbackCaseMeta($meta);
        if ($decoded === null) {
            return false;
        }

        $caseRegionalIds = $this->extractRegionalUserIdsFromMetaArray($decoded);
        if ($caseRegionalIds === []) {
            return false;
        }

        $allowedIds = array_flip($scope['regional_user_ids']);
        $matchedRegionalIds = array_values(array_filter(
            $caseRegionalIds,
            fn (int $id) => isset($allowedIds[$id]),
        ));

        if ($matchedRegionalIds === []) {
            return false;
        }

        $areaMatched = DB::table('user_regional')
            ->whereIn('user_id', $matchedRegionalIds)
            ->where('area', $scope['area'])
            ->exists();

        if (! $areaMatched) {
            return false;
        }

        if (! $requireCapaInput) {
            return true;
        }

        $division = (string) ($scope['capa_division'] ?? '');
        $capaDivision = $decoded['capa_divisions'][$division] ?? null;
        if (! is_array($capaDivision) && $division === 'service' && isset($decoded['capa']) && is_array($decoded['capa'])) {
            $capaDivision = $decoded['capa'];
        }

        return is_array($capaDivision)
            && $this->feedbackCapaService->storedCapaHasUserInput($capaDivision);
    }

    /**
     * @return list<object{meta: mixed, event_at: mixed, resolved_at: mixed}>
     */
    private function fetchCvccCasesForPeriod(string $startDate, string $endDate, bool $resolvedOnly = false): array
    {
        if (! DB::getSchemaBuilder()->hasTable('feedback_cases')) {
            return [];
        }

        $query = DB::table('feedback_cases')
            ->whereBetween('event_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($resolvedOnly) {
            $query->whereNotNull('resolved_at');
        }

        return $query->get(['meta', 'event_at', 'resolved_at', 'severity'])->all();
    }

    private function resolveCvccAvgResolutionHours(int $userId, string $startDate, string $endDate): ?float
    {
        $scope = $this->resolveCvccRegionalScope($userId);
        if ($scope === null) {
            return null;
        }

        $hours = [];
        foreach ($this->fetchCvccCasesForPeriod($startDate, $endDate, resolvedOnly: true) as $row) {
            if (! $this->caseMatchesCvccRegionalScope($row->meta, $scope)) {
                continue;
            }

            $eventAt = strtotime((string) $row->event_at);
            $resolvedAt = strtotime((string) $row->resolved_at);
            if ($eventAt === false || $resolvedAt === false || $resolvedAt < $eventAt) {
                continue;
            }

            $hours[] = ($resolvedAt - $eventAt) / 3600;
        }

        if ($hours === []) {
            return null;
        }

        return round(array_sum($hours) / count($hours), 2);
    }

    /**
     * Negative comment CVCC dengan CAPA division area user yang sudah diisi.
     */
    private function resolveCvccServiceNegativeComplaintCount(int $userId, string $startDate, string $endDate): ?float
    {
        $scope = $this->resolveCvccRegionalScope($userId);
        if ($scope === null) {
            return null;
        }

        $count = 0;
        $matchedCases = 0;
        foreach ($this->fetchCvccCasesForPeriod($startDate, $endDate) as $row) {
            if (! in_array(strtolower(trim((string) ($row->severity ?? ''))), self::CVCC_NEGATIVE_SEVERITIES, true)) {
                continue;
            }

            if (! $this->caseMatchesCvccRegionalScope($row->meta, $scope, requireCapaInput: true)) {
                continue;
            }

            $matchedCases++;
            $count++;
        }

        if ($matchedCases === 0) {
            return null;
        }

        return (float) $count;
    }

    private function resolveCvccTotalReviewCount(int $userId, string $startDate, string $endDate): ?float
    {
        $scope = $this->resolveCvccRegionalScope($userId);
        if ($scope === null) {
            return null;
        }

        $count = 0;
        foreach ($this->fetchCvccCasesForPeriod($startDate, $endDate) as $row) {
            if ($this->caseMatchesCvccRegionalScope($row->meta, $scope)) {
                $count++;
            }
        }

        return $count > 0 ? (float) $count : null;
    }
}
