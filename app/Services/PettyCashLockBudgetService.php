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
     * @return array<int, float>
     */
    public function lockBudgetsByOutlet(array $outletIds, int $year, int $month): array
    {
        $outletIdList = array_values(array_unique(array_filter(array_map('intval', $outletIds))));
        if ($outletIdList === []) {
            return [];
        }

        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $headers = DB::table('outlet_revenue_target_headers')
            ->whereIn('outlet_id', $outletIdList)
            ->where('target_month', $monthStart)
            ->get(['id', 'outlet_id']);

        if ($headers->isEmpty()) {
            return [];
        }

        $forecastByHeader = DB::table('outlet_revenue_target_details')
            ->whereIn('header_id', $headers->pluck('id'))
            ->select('header_id', DB::raw('SUM(forecast_revenue) as forecast_total'))
            ->groupBy('header_id')
            ->pluck('forecast_total', 'header_id');

        $result = [];
        foreach ($headers as $header) {
            $forecast = (float) ($forecastByHeader[$header->id] ?? 0);
            if ($forecast <= 0) {
                continue;
            }

            $result[(int) $header->outlet_id] = round(
                $forecast * self::FORECAST_AFTER_RESERVE_RATIO * self::FORECAST_PETTY_CASH_RATIO_OF_REST,
                2
            );
        }

        return $result;
    }

    /**
     * @param  list<int>  $outletIds
     */
    public function sumLockBudgetForOutlets(array $outletIds, int $year, int $month): ?float
    {
        $byOutlet = $this->lockBudgetsByOutlet($outletIds, $year, $month);
        if ($byOutlet === []) {
            return null;
        }

        return round(array_sum($byOutlet), 2);
    }
}
