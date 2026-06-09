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
        $mapping = $parameter->erpMapping;
        if (!$mapping || $mapping->status !== 'A') {
            return null;
        }

        $outletId = (int) ($context['outlet_id'] ?? 0);
        $periodMonth = (string) ($context['period_month'] ?? '');
        if ($outletId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return null;
        }

        $analyzer = $this->getAnalyzerData($outletId, $periodMonth);
        if ($analyzer === null) {
            return null;
        }

        $period = $this->outletAnalyzer->calendarPeriod($periodMonth);
        $year = (int) substr($periodMonth, 0, 4);
        $month = (int) substr($periodMonth, 5, 2);

        return match ($mapping->resolver_key) {
            'daily_revenue_forecast' => (float) ($analyzer['revenue']['total'] ?? 0),
            'daily_revenue_forecast_budget' => $this->resolveMonthlyBudget($outletId, $month, $year),
            'cost_report_cogs' => (float) ($analyzer['fj_inventory']['line_total'] ?? 0),
            'outlet_analyzer_payroll' => (float) ($analyzer['payroll']['total_gaji'] ?? 0),
            'outlet_analyzer_petty_cash' => (float) ($analyzer['petty_cash']['total'] ?? 0),
            'outlet_internal_use_waste' => $this->resolveCategoryCost($analyzer, $parameter->code),
            'lost_breakage' => $this->resolveCategoryCostByType($analyzer, 'stock_cut'),
            'guest_comment_gsi' => (float) ($analyzer['guest_comment_gsi']['total_forms'] ?? 0),
            'regional_visit_report' => (float) ($analyzer['regional_visits']['visit_days'] ?? 0),
            'training_compliance' => $this->resolveTrainingCompliance((int) ($context['user_id'] ?? 0), $period['start_date'], $period['end_date']),
            'ticket_complaint_count' => $this->resolveTicketCount($outletId, $period['start_date'], $period['end_date'], 'complaint'),
            'ticket_improvement_closed' => $this->resolveTicketCount($outletId, $period['start_date'], $period['end_date'], 'improvement', true),
            'ticket_improvement_total' => $this->resolveTicketCount($outletId, $period['start_date'], $period['end_date'], 'improvement', false),
            default => null,
        };
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

    private function resolveMonthlyBudget(int $outletId, int $month, int $year): float
    {
        $qrCode = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->value('qr_code');

        if (!$qrCode) {
            return 0.0;
        }

        return (float) (DB::table('outlet_monthly_budgets')
            ->where('outlet_qr_code', $qrCode)
            ->where('month', $month)
            ->where('year', $year)
            ->value('budget_amount') ?? 0);
    }

    /**
     * @param  array<string, mixed>  $analyzer
     */
    private function resolveCategoryCost(array $analyzer, string $parameterCode): float
    {
        return match ($parameterCode) {
            'D005' => $this->resolveCategoryCostByType($analyzer, 'waste'),
            'D006' => $this->resolveCategoryCostByType($analyzer, 'spoil'),
            default => (float) ($analyzer['category_cost_outlet']['total'] ?? 0),
        };
    }

    /**
     * @param  array<string, mixed>  $analyzer
     */
    private function resolveCategoryCostByType(array $analyzer, string $type): float
    {
        $modes = $analyzer['category_cost_outlet']['modes'] ?? [];
        foreach ($modes as $mode) {
            if (($mode['key'] ?? '') === $type) {
                return (float) ($mode['amount'] ?? 0);
            }
        }

        return 0.0;
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

    private function resolveTicketCount(int $outletId, string $start, string $end, string $kind, ?bool $closedOnly = null): ?float
    {
        if (!DB::getSchemaBuilder()->hasTable('tickets')) {
            return null;
        }

        if (!DB::getSchemaBuilder()->hasTable('ticket_categories')) {
            return null;
        }

        $query = DB::table('tickets as t')
            ->join('ticket_categories as tc', 't.category_id', '=', 'tc.id')
            ->where('t.outlet_id', $outletId)
            ->whereDate('t.created_at', '>=', $start)
            ->whereDate('t.created_at', '<=', $end)
            ->where('tc.status', 'A');

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
