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
                $hariKerja = (float) (($data['hariKerjaOutletLama'] ?? 0) + ($data['hariKerjaOutletBaru'] ?? 0));
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
        bool $isNewEmployee = false
    ): array {
        if (! $enabled || $poolAmount <= 0) {
            return ['by_point' => 0.0, 'pro_rate' => 0.0, 'total' => 0.0];
        }

        if ($isMutatedEmployee && $mutationEffectiveDate) {
            $gajian2Start = Carbon::create($year, $month, 1)->startOfDay();
            $gajian2End = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

            if ($mutationEffectiveDate->lt($gajian2Start)) {
                return ['by_point' => 0.0, 'pro_rate' => 0.0, 'total' => 0.0];
            }

            $hariKalenderOutletLama = $gajian2Start->diffInDays($mutationEffectiveDate->copy()->subDay()) + 1;
            if ($hariKalenderOutletLama < 0) {
                $hariKalenderOutletLama = 0;
            }

            $hariKalenderOutletBaru = $mutationEffectiveDate->diffInDays($gajian2End) + 1;
            if ($hariKalenderOutletBaru < 0) {
                $hariKalenderOutletBaru = 0;
            }

            $byPoint = ($rateByPoint * ($userPoint * $hariKalenderOutletLama))
                + ($rateByPoint * ($userPoint * $hariKalenderOutletBaru));
            $proRate = ($rateProRate * $hariKalenderOutletLama)
                + ($rateProRate * $hariKalenderOutletBaru);
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
