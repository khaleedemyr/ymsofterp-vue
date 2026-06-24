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

    /**
     * @return array{start: Carbon, end: Carbon}|null
     */
    public static function resolveMutationDateSegment(
        Carbon $effectiveDate,
        string $role,
        Carbon $periodStart,
        Carbon $periodEnd
    ): ?array {
        if (self::calculateMutationDaysInPeriod($effectiveDate, $periodStart, $periodEnd, $role) <= 0) {
            return null;
        }

        $effective = $effectiveDate->copy()->startOfDay();
        $start = $periodStart->copy()->startOfDay();
        $end = $periodEnd->copy()->startOfDay();

        if ($role === 'from') {
            $segmentEnd = $effective->copy()->subDay();
            if ($segmentEnd->gt($end)) {
                $segmentEnd = $end->copy();
            }

            return ['start' => $start, 'end' => $segmentEnd];
        }

        $segmentStart = $effective->gt($start) ? $effective->copy() : $start->copy();

        return ['start' => $segmentStart, 'end' => $end];
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
     * Hari kerja pool gajian 2 (kolom D Excel): absensi 1–akhir bulan, mutasi/resign pakai aturan khusus.
     *
     * @param  array<string, mixed>  $data
     */
    public static function resolveGajian2PoolDays(array $data, int $year, int $month): int
    {
        if ($data['isMutatedEmployee'] ?? false) {
            $attendanceDays = max(0, (int) ($data['hariKerjaGajian2Attendance'] ?? 0));
            if ($attendanceDays > 0) {
                return $attendanceDays;
            }

            return max(0, (int) ($data['hariKerjaGajian2'] ?? 0));
        }

        $gajian2Start = Carbon::create($year, $month, 1)->startOfDay();

        $resignationDate = null;
        if (! empty($data['resignationDate'])) {
            $resignationDate = $data['resignationDate'] instanceof Carbon
                ? $data['resignationDate']->copy()->startOfDay()
                : Carbon::parse($data['resignationDate'])->startOfDay();
        }

        if ($resignationDate && $resignationDate->lt($gajian2Start)) {
            return 0;
        }

        $attendanceDays = max(0, (int) ($data['hariKerjaGajian2Attendance'] ?? 0));

        if ($resignationDate && ($data['affectsGajian2'] ?? false)) {
            if ($attendanceDays > 0) {
                return $attendanceDays;
            }

            $tanggalMasuk = ! empty($data['tanggalMasuk'])
                ? ($data['tanggalMasuk'] instanceof Carbon ? $data['tanggalMasuk'] : Carbon::parse($data['tanggalMasuk']))
                : null;

            return self::calculateGajian2DaysForResigned(
                $resignationDate,
                $year,
                $month,
                $tanggalMasuk,
                (bool) ($data['isNewEmployee'] ?? false)
            );
        }

        if ($resignationDate && ! ($data['affectsGajian2'] ?? false)) {
            return 0;
        }

        return $attendanceDays;
    }

    /**
     * Σ hari & Σ(poin×hari) untuk rate pool — SEMUA karyawan di daftar payroll (mirror Excel D47/E47).
     * Flag sc/lb/deviasi/city_ledger hanya mengontrol penerima, bukan pembagi.
     *
     * @param  array<int, array<string, mixed>>  $userData
     * @return array{totalPointHariKerja: float, totalHariKerja: float}
     */
    public static function calculatePoolTotals(array $userData): array
    {
        $totalPointHariKerja = 0.0;
        $totalHariKerja = 0.0;

        foreach ($userData as $data) {
            $hariKerja = (float) ($data['hariKerjaUntukServiceCharge'] ?? 0);
            $totalPointHariKerja += (float) ($data['userPoint'] ?? 0) * $hariKerja;
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
        float $hariKerjaPool,
        bool $isMutatedEmployee = false,
        ?Carbon $mutationEffectiveDate = null,
        int $year = 0,
        int $month = 0,
        bool $affectsGajian2 = false,
        ?Carbon $resignationDate = null,
        ?Carbon $tanggalMasuk = null,
        bool $isNewEmployee = false,
        ?string $mutationRole = null
    ): array {
        if (! $enabled || $poolAmount <= 0 || $hariKerjaPool <= 0) {
            return ['by_point' => 0.0, 'pro_rate' => 0.0, 'total' => 0.0];
        }

        $byPoint = $rateByPoint * ($userPoint * $hariKerjaPool);
        $proRate = $rateProRate * $hariKerjaPool;

        return [
            'by_point' => $byPoint,
            'pro_rate' => $proRate,
            'total' => $byPoint + $proRate,
        ];
    }
}
