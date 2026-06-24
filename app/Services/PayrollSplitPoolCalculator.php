<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Pembagian pool 50% by point + 50% pro rate (service charge, L&B, deviasi, city ledger).
 */
class PayrollSplitPoolCalculator
{
    /**
     * Hari kalender gajian 2 (1–akhir bulan) sampai tanggal resign — mirror mutasi.
     */
    public static function calculateGajian2DaysForResigned(
        Carbon $resignationDate,
        int $year,
        int $month,
        ?Carbon $tanggalMasuk = null,
        bool $isNewEmployee = false
    ): int {
        $gajian2Start = Carbon::create($year, $month, 1)->startOfDay();
        $gajian2End = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        $resignationDate = $resignationDate->copy()->startOfDay();

        if ($resignationDate->lt($gajian2Start)) {
            return 0;
        }

        $periodStart = $gajian2Start;
        if ($isNewEmployee && $tanggalMasuk) {
            $masuk = $tanggalMasuk->copy()->startOfDay();
            if ($masuk->gt($gajian2Start)) {
                $periodStart = $masuk;
            }
        }

        $periodEnd = $resignationDate->lte($gajian2End) ? $resignationDate : $gajian2End->copy()->startOfDay();

        if ($periodStart->gt($periodEnd)) {
            return 0;
        }

        return $periodStart->diffInDays($periodEnd) + 1;
    }

    /**
     * Hari kalender dalam segmen periode gajian untuk mutasi outlet (from = sebelum effective, to = sejak effective).
     */
    public static function calculateMutationDaysInPeriod(
        Carbon $effectiveDate,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $role
    ): int {
        $effective = $effectiveDate->copy()->startOfDay();
        $start = $periodStart->copy()->startOfDay();
        $end = $periodEnd->copy()->startOfDay();

        if ($role === 'from') {
            if ($effective->lte($start)) {
                return 0;
            }
            $segmentEnd = $effective->copy()->subDay();
            if ($segmentEnd->lt($start)) {
                return 0;
            }
            if ($segmentEnd->gt($end)) {
                $segmentEnd = $end->copy();
            }

            return $start->diffInDays($segmentEnd) + 1;
        }

        if ($effective->gt($end)) {
            return 0;
        }
        $segmentStart = $effective->gt($start) ? $effective->copy() : $start->copy();

        return $segmentStart->diffInDays($end) + 1;
    }

    public static function calculateMutationGajian2Days(
        Carbon $effectiveDate,
        int $year,
        int $month,
        string $role
    ): int {
        $gajian2Start = Carbon::create($year, $month, 1)->startOfDay();
        $gajian2End = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();

        return self::calculateMutationDaysInPeriod($effectiveDate, $gajian2Start, $gajian2End, $role);
    }

    /**
     * @param  array<int, array<string, mixed>>  $userData
     * @return array{totalPointHariKerja: float, totalHariKerja: float}
     */
    public static function calculatePoolTotals(array $userData, string $masterField, int $year = 0, int $month = 0): array
    {
        $totalPointHariKerja = 0.0;
        $totalHariKerja = 0.0;

        foreach ($userData as $data) {
            if ((int) ($data['masterData']->{$masterField} ?? 0) !== 1) {
                continue;
            }

            if ($data['isMutatedEmployee'] ?? false) {
                $hariKerja = (float) ($data['hariKerjaGajian2'] ?? $data['hariKerjaUntukServiceCharge'] ?? 0);
            } elseif (($data['affectsGajian2'] ?? false) && ! empty($data['resignationDate']) && $year > 0 && $month > 0) {
                $resignationDate = $data['resignationDate'] instanceof Carbon
                    ? $data['resignationDate']
                    : Carbon::parse($data['resignationDate'])->startOfDay();
                $tanggalMasuk = ! empty($data['tanggalMasuk'])
                    ? ($data['tanggalMasuk'] instanceof Carbon ? $data['tanggalMasuk'] : Carbon::parse($data['tanggalMasuk'])->startOfDay())
                    : null;
                $hariKerja = (float) self::calculateGajian2DaysForResigned(
                    $resignationDate,
                    $year,
                    $month,
                    $tanggalMasuk,
                    (bool) ($data['isNewEmployee'] ?? false)
                );
            } else {
                $hariKerja = (float) ($data['hariKerjaUntukServiceCharge'] ?? $data['hariKerja'] ?? 0);
            }

            $totalPointHariKerja += (float) $data['userPoint'] * $hariKerja;
            $totalHariKerja += $hariKerja;
        }

        return [
            'totalPointHariKerja' => $totalPointHariKerja,
            'totalHariKerja' => $totalHariKerja,
        ];
    }

    /**
     * @return array{rateByPoint: float, rateProRate: float}
     */
    public static function calculateRates(float $amount, float $totalPointHariKerja, float $totalHariKerja): array
    {
        $rateByPoint = 0.0;
        $rateProRate = 0.0;

        if ($amount > 0) {
            $half = $amount / 2;
            if ($totalPointHariKerja > 0) {
                $rateByPoint = $half / $totalPointHariKerja;
            }
            if ($totalHariKerja > 0) {
                $rateProRate = $half / $totalHariKerja;
            }
        }

        return [
            'rateByPoint' => $rateByPoint,
            'rateProRate' => $rateProRate,
        ];
    }

    /**
     * @return array{by_point: float, pro_rate: float, total: float}
     */
    public static function calculateUserAmount(
        bool $enabled,
        float $poolAmount,
        float $rateByPoint,
        float $rateProRate,
        float $userPoint,
        float $hariKerjaUntukServiceCharge,
        bool $isMutatedEmployee,
        ?Carbon $mutationEffectiveDate,
        int $year,
        int $month,
        bool $affectsGajian2 = false,
        ?Carbon $resignationDate = null,
        ?Carbon $tanggalMasuk = null,
        bool $isNewEmployee = false,
        ?string $mutationRole = null
    ): array {
        if (! $enabled || $poolAmount <= 0) {
            return ['by_point' => 0.0, 'pro_rate' => 0.0, 'total' => 0.0];
        }

        if ($isMutatedEmployee && $mutationEffectiveDate && $mutationRole) {
            $days = self::calculateMutationGajian2Days(
                $mutationEffectiveDate,
                $year,
                $month,
                $mutationRole
            );

            if ($days <= 0) {
                return ['by_point' => 0.0, 'pro_rate' => 0.0, 'total' => 0.0];
            }

            $byPoint = $rateByPoint * ($userPoint * $days);
            $proRate = $rateProRate * $days;
        } elseif ($affectsGajian2 && $resignationDate && $year > 0 && $month > 0) {
            $hariKalender = self::calculateGajian2DaysForResigned(
                $resignationDate,
                $year,
                $month,
                $tanggalMasuk,
                $isNewEmployee
            );

            if ($hariKalender <= 0) {
                return ['by_point' => 0.0, 'pro_rate' => 0.0, 'total' => 0.0];
            }

            $byPoint = $rateByPoint * ($userPoint * $hariKalender);
            $proRate = $rateProRate * $hariKalender;
        } else {
            $byPoint = $rateByPoint * ($userPoint * $hariKerjaUntukServiceCharge);
            $proRate = $rateProRate * $hariKerjaUntukServiceCharge;
        }

        return [
            'by_point' => $byPoint,
            'pro_rate' => $proRate,
            'total' => $byPoint + $proRate,
        ];
    }
}
