<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PettyCashLockBudgetService
{
    public const FORECAST_AFTER_RESERVE_RATIO = 0.80;

    public const FORECAST_PETTY_CASH_RATIO_OF_REST = 0.008;

    /**
     * Sumber: Target Pendapatan (sum forecast_revenue harian bulan berjalan).
     *
     * @return array{forecast_monthly_total: float, lock_budget: float}|null
     */
    public function resolveForOutlet(int $outletId, string $monthStart, float $lockRatioOfRest = self::FORECAST_PETTY_CASH_RATIO_OF_REST): ?array
    {
        $header = DB::table('outlet_revenue_target_headers')
            ->where('outlet_id', $outletId)
            ->where('target_month', $monthStart)
            ->first(['id']);
        if (! $header) {
            return null;
        }

        $forecastMonthlyTotal = (float) (DB::table('outlet_revenue_target_details')
            ->where('header_id', $header->id)
            ->sum('forecast_revenue') ?? 0);
        if ($forecastMonthlyTotal <= 0) {
            return null;
        }

        $lockBudget = round(
            $forecastMonthlyTotal * self::FORECAST_AFTER_RESERVE_RATIO * $lockRatioOfRest,
            2
        );

        return [
            'forecast_monthly_total' => round($forecastMonthlyTotal, 2),
            'lock_budget' => $lockBudget,
        ];
    }

    /**
     * @param  list<int>  $outletIds
     */
    public function sumLockBudgetForOutlets(array $outletIds, int $year, int $month): ?float
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $total = 0.0;
        $found = false;

        foreach (array_values(array_unique(array_filter(array_map('intval', $outletIds)))) as $outletId) {
            if ($outletId <= 0) {
                continue;
            }

            $resolved = $this->resolveForOutlet($outletId, $monthStart);
            if ($resolved === null) {
                continue;
            }

            $total += (float) $resolved['lock_budget'];
            $found = true;
        }

        return $found ? round($total, 2) : null;
    }
}
