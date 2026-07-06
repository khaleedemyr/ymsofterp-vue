<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PettyCashLockBudgetService
{
    public const FORECAST_AFTER_RESERVE_RATIO = 0.80;

    public const FORECAST_PETTY_CASH_RATIO_OF_REST = 0.008;

    /**
     * Sumber: monthly_target pada outlet_revenue_target_headers per outlet/bulan.
     * Lock budget = monthly_target × 80% × 0.8%
     *
     * @return array{monthly_target: float, usable_after_reserve: float, lock_budget: float}|null
     */
    public function resolveForOutlet(int $outletId, string $monthStart): ?array
    {
        $header = DB::table('outlet_revenue_target_headers')
            ->where('outlet_id', $outletId)
            ->where('target_month', $monthStart)
            ->first(['monthly_target']);
        if (! $header) {
            return null;
        }

        $monthlyTarget = (float) ($header->monthly_target ?? 0);
        if ($monthlyTarget <= 0) {
            return null;
        }

        $usableAfterReserve = round($monthlyTarget * self::FORECAST_AFTER_RESERVE_RATIO, 2);
        $lockBudget = round($usableAfterReserve * self::FORECAST_PETTY_CASH_RATIO_OF_REST, 2);

        return [
            'monthly_target' => round($monthlyTarget, 2),
            'usable_after_reserve' => $usableAfterReserve,
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
            ->get(['outlet_id', 'monthly_target']);

        $result = [];
        foreach ($headers as $header) {
            $monthlyTarget = (float) ($header->monthly_target ?? 0);
            if ($monthlyTarget <= 0) {
                continue;
            }

            $result[(int) $header->outlet_id] = round(
                $monthlyTarget * self::FORECAST_AFTER_RESERVE_RATIO * self::FORECAST_PETTY_CASH_RATIO_OF_REST,
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

    /**
     * @param  array{monthly_target: float, lock_budget: float}  $monthlyBudget
     * @param  array{retail_food_non_contra_bon_total: float, retail_non_food_non_contra_bon_total: float, monthly_total: float}  $usage
     */
    public function buildExceededMessage(array $monthlyBudget, array $usage, float $newAmount): string
    {
        $totalAfterNew = $usage['monthly_total'] + $newAmount;

        return "Transaksi ditolak! Budget petty cash outlet bulan ini terlampaui.\n\n" .
            "📊 Detail Budget:\n" .
            '• Target Pendapatan Bulanan (header): Rp ' . number_format($monthlyBudget['monthly_target'], 0, ',', '.') . "\n" .
            '• Budget Petty Cash (0.8% × 80% target): Rp ' . number_format($monthlyBudget['lock_budget'], 0, ',', '.') . "\n" .
            '• Retail Food non-contra bon (bulan ini): Rp ' . number_format($usage['retail_food_non_contra_bon_total'], 0, ',', '.') . "\n" .
            '• Retail Non Food non-contra bon (bulan ini): Rp ' . number_format($usage['retail_non_food_non_contra_bon_total'], 0, ',', '.') . "\n" .
            '• Total terpakai sebelum transaksi: Rp ' . number_format($usage['monthly_total'], 0, ',', '.') . "\n" .
            '• Transaksi baru: Rp ' . number_format($newAmount, 0, ',', '.') . "\n" .
            '• Total setelah transaksi: Rp ' . number_format($totalAfterNew, 0, ',', '.') . "\n" .
            '• Kelebihan: Rp ' . number_format($totalAfterNew - $monthlyBudget['lock_budget'], 0, ',', '.');
    }
}
