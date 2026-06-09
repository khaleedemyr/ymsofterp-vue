<?php

namespace App\Services;

use App\Models\KpiParameter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiParameterResolverService
{
    /** @var array<string, mixed>|null */
    private ?array $analyzerCache = null;

    public function __construct(
        private OutletAnalyzerService $outletAnalyzer,
    ) {}

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
                $year = (int) substr($periodMonth, 0, 4);
                $month = (int) substr($periodMonth, 5, 2);
                $sampleCodes = DB::table('orders')
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->where('status', '!=', 'cancelled')
                    ->where('grand_total', '>', 0)
                    ->distinct()
                    ->limit(5)
                    ->pluck('kode_outlet')
                    ->filter()
                    ->values()
                    ->all();

                $hints[] = 'Tidak ada order POS untuk scope outlet di periode ' . $periodMonth
                    . (empty($sampleCodes) ? '' : '. Sample kode_outlet di orders: ' . implode(', ', $sampleCodes));
            }

            $year = (int) substr($periodMonth, 0, 4);
            $month = (int) substr($periodMonth, 5, 2);
            $budgetAmount = 0.0;
            $budgetFound = false;
            foreach ($scopeOutlets as $o) {
                $qr = trim((string) ($o->qr_code ?? ''));
                if ($qr === '') {
                    continue;
                }
                $b = DB::table('outlet_monthly_budgets')
                    ->where('outlet_qr_code', $qr)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->value('budget_amount');
                if ($b !== null) {
                    $budgetAmount += (float) $b;
                    $budgetFound = true;
                }
            }
            if (!$budgetFound) {
                $hints[] = "Budget belum di-set di outlet_monthly_budgets untuk {$periodMonth} — D002 akan 0.";
                $budgetAmount = null;
            }
        }

        if (DB::getSchemaBuilder()->hasTable('ticket_categories')) {
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
            'scope_outlet_ids' => $outletIds,
            'scope_outlet_names' => $scopeOutlets->pluck('nama_outlet')->all(),
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

        $standalone = match ($mapping->resolver_key) {
            'daily_revenue_forecast' => $this->resolveDailyRevenueMtd($outletIds, $periodMonth),
            'daily_revenue_forecast_budget' => $this->resolveMonthlyBudget($outletIds, $month, $year),
            'training_compliance' => $this->resolveTrainingCompliance((int) ($context['user_id'] ?? 0), $period['start_date'], $period['end_date']),
            'ticket_complaint_count' => $this->resolveTicketCount($outletIds, $period['start_date'], $period['end_date'], 'complaint'),
            'ticket_improvement_closed' => $this->resolveTicketCount($outletIds, $period['start_date'], $period['end_date'], 'improvement', true),
            'ticket_improvement_total' => $this->resolveTicketCount($outletIds, $period['start_date'], $period['end_date'], 'improvement', false),
            default => null,
        };

        if ($standalone !== null || in_array($mapping->resolver_key, [
            'daily_revenue_forecast',
            'daily_revenue_forecast_budget',
            'training_compliance',
            'ticket_complaint_count',
            'ticket_improvement_closed',
            'ticket_improvement_total',
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
            'regional_visit_report' => $this->sumAnalyzerPath($outletIds, $periodMonth, ['regional_visits', 'visit_days']),
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
     * @param  list<int>  $outletIds
     */
    private function resolveDailyRevenueMtd(array $outletIds, string $periodMonth): ?float
    {
        if (empty($outletIds)) {
            return null;
        }

        $result = $this->queryOrderRevenue($outletIds, $periodMonth, true);

        return $result['match'] === null ? null : $result['total'];
    }
    /**
     * @param  list<int>  $outletIds
     * @return array{total: float, count: int, match: string|null}
     */
    private function queryOrderRevenue(array $outletIds, string $periodMonth, bool $mtdOnly = true): array
    {
        $total = 0.0;
        $count = 0;
        $matches = [];

        foreach (array_unique(array_filter($outletIds)) as $outletId) {
            $result = $this->queryOrderRevenueSingle((int) $outletId, $periodMonth, $mtdOnly);
            $total += $result['total'];
            $count += $result['count'];
            if ($result['match']) {
                $matches[] = $result['match'];
            }
        }

        return [
            'total' => round($total, 2),
            'count' => $count,
            'match' => $matches !== [] ? implode(', ', array_unique($matches)) : null,
        ];
    }

    /**
     * @return array{total: float, count: int, match: string|null}
     */
    private function queryOrderRevenueSingle(int $outletId, string $periodMonth, bool $mtdOnly = true): array
    {
        $empty = ['total' => 0.0, 'count' => 0, 'match' => null];

        if (!preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return $empty;
        }

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->select('id_outlet', 'nama_outlet', 'qr_code')
            ->first();

        if (!$outlet) {
            return $empty;
        }

        $year = (int) substr($periodMonth, 0, 4);
        $month = (int) substr($periodMonth, 5, 2);

        $baseQuery = function () use ($year, $month, $mtdOnly, $periodMonth) {
            $query = DB::table('orders')
                ->where('status', '!=', 'cancelled')
                ->where('grand_total', '>', 0);

            if ($mtdOnly) {
                $currentDate = Carbon::now();
                $mtdEndDay = ($currentDate->year === $year && $currentDate->month === $month)
                    ? $currentDate->day
                    : Carbon::create($year, $month, 1)->daysInMonth;

                $query->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->whereDay('created_at', '<=', $mtdEndDay);
            } else {
                $period = $this->outletAnalyzer->calendarPeriod($periodMonth);
                $query->whereDate('created_at', '>=', $period['start_date'])
                    ->whereDate('created_at', '<=', $period['end_date']);
            }

            return $query;
        };

        $candidates = array_values(array_unique(array_filter([
            trim((string) ($outlet->qr_code ?? '')),
            trim((string) ($outlet->nama_outlet ?? '')),
            (string) $outletId,
        ])));

        foreach ($candidates as $code) {
            $count = (int) $baseQuery()->where('kode_outlet', $code)->count();
            if ($count > 0) {
                return [
                    'total' => round((float) $baseQuery()->where('kode_outlet', $code)->sum('grand_total'), 2),
                    'count' => $count,
                    'match' => $code,
                ];
            }
        }

        $qrCode = trim((string) ($outlet->qr_code ?? ''));
        if ($qrCode !== '') {
            $count = (int) $baseQuery()->whereRaw('LOWER(TRIM(kode_outlet)) = ?', [strtolower($qrCode)])->count();
            if ($count > 0) {
                return [
                    'total' => round((float) $baseQuery()->whereRaw('LOWER(TRIM(kode_outlet)) = ?', [strtolower($qrCode)])->sum('grand_total'), 2),
                    'count' => $count,
                    'match' => $qrCode,
                ];
            }
        }

        $joinedQuery = DB::table('orders')
            ->join('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
            ->where('o.id_outlet', $outletId)
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.grand_total', '>', 0);

        if ($mtdOnly) {
            $currentDate = Carbon::now();
            $mtdEndDay = ($currentDate->year === $year && $currentDate->month === $month)
                ? $currentDate->day
                : Carbon::create($year, $month, 1)->daysInMonth;

            $joinedQuery->whereYear('orders.created_at', $year)
                ->whereMonth('orders.created_at', $month)
                ->whereDay('orders.created_at', '<=', $mtdEndDay);
        } else {
            $period = $this->outletAnalyzer->calendarPeriod($periodMonth);
            $joinedQuery->whereDate('orders.created_at', '>=', $period['start_date'])
                ->whereDate('orders.created_at', '<=', $period['end_date']);
        }

        $joinedCount = (int) (clone $joinedQuery)->count();
        if ($joinedCount > 0) {
            return [
                'total' => round((float) (clone $joinedQuery)->sum('orders.grand_total'), 2),
                'count' => $joinedCount,
                'match' => $qrCode !== '' ? $qrCode : 'join:id_outlet',
            ];
        }

        return $empty;
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
        $this->analyzerCache = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getAnalyzerData(int $outletId, string $periodMonth): ?array
    {
        $cacheKey = "{$outletId}:{$periodMonth}";
        if ($this->analyzerCache !== null && ($this->analyzerCache['_key'] ?? '') === $cacheKey) {
            return $this->analyzerCache;
        }

        $data = $this->outletAnalyzer->analyze($outletId, $periodMonth);
        if ($data === null) {
            return null;
        }

        $this->analyzerCache = array_merge($data, ['_key' => $cacheKey]);

        return $this->analyzerCache;
    }

    /**
     * @param  list<int>  $outletIds
     */
    private function resolveMonthlyBudget(array $outletIds, int $month, int $year): ?float
    {
        if (empty($outletIds)) {
            return null;
        }

        $total = 0.0;
        $found = false;

        foreach ($outletIds as $outletId) {
            $qrCode = trim((string) DB::table('tbl_data_outlet')
                ->where('id_outlet', $outletId)
                ->value('qr_code'));

            if ($qrCode === '') {
                continue;
            }

            $budget = DB::table('outlet_monthly_budgets')
                ->where('outlet_qr_code', $qrCode)
                ->where('month', $month)
                ->where('year', $year)
                ->value('budget_amount');

            if ($budget !== null) {
                $total += (float) $budget;
                $found = true;
            }
        }

        return $found ? round($total, 2) : 0.0;
    }

    private function resolveTrainingCompliance(int $userId, string $start, string $end): ?float
    {
        if ($userId <= 0) {
            return null;
        }

        if (!DB::getSchemaBuilder()->hasTable('training_assignments')) {
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

        $total = 0.0;
        foreach ($outletIds as $outletId) {
            $total += $this->resolveTicketCountSingle((int) $outletId, $start, $end, $kind, $closedOnly);
        }

        return $total;
    }

    private function resolveTicketCountSingle(int $outletId, string $start, string $end, string $kind, ?bool $closedOnly = null): float
    {
        if (!DB::getSchemaBuilder()->hasTable('tickets')) {
            return 0.0;
        }

        if (!DB::getSchemaBuilder()->hasTable('ticket_categories')) {
            return 0.0;
        }

        $query = DB::table('tickets as t')
            ->join('ticket_categories as tc', 't.category_id', '=', 'tc.id')
            ->where('t.outlet_id', $outletId)
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

            if ($closedOnly === true && DB::getSchemaBuilder()->hasTable('ticket_statuses')) {
                $query->join('ticket_statuses as ts', 't.status_id', '=', 'ts.id')
                    ->where(function ($q) {
                        $q->where('ts.is_final', 1)
                            ->orWhereIn('ts.slug', ['closed', 'resolved', 'done']);
                    });
            }
        }

        return (float) $query->count('t.id');
    }
}
