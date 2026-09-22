<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolling projected revenue forecast (layer terpisah dari outlet_revenue_target_*).
 * Tidak menulis ulang target resmi — compute on-request untuk Outlet Dashboard.
 */
class OutletRollingForecastService
{
    private const PACE_MIN = 0.45;
    private const PACE_MAX = 1.55;
    private const CAP_HIGH = 1.25;
    /** Hari awal bulan: jika MTD masih sangat kecil, pakai baseline penuh. */
    private const MIN_DAYS_FOR_PACE = 2;

    /**
     * @return array<string, mixed>
     */
    public function build(int $outletId, string $month): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $today = Carbon::today();
        $isCurrentMonth = $today->format('Y-m') === $month;
        $isFutureMonth = $monthStart->gt($today->copy()->startOfMonth());
        $isPastMonth = $monthEnd->lt($today);

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->select('id_outlet', 'qr_code', 'nama_outlet')
            ->first();

        if (!$outlet) {
            return $this->errorPayload('Outlet tidak ditemukan.', 404);
        }

        if (empty($outlet->qr_code)) {
            return $this->errorPayload('QR Code outlet belum tersedia.', 422);
        }

        $header = DB::table('outlet_revenue_target_headers')
            ->where('outlet_id', $outletId)
            ->where('target_month', $monthStart->toDateString())
            ->first();

        if (!$header || (float) $header->monthly_target <= 0) {
            return [
                'success' => true,
                'has_target' => false,
                'message' => 'Belum ada monthly target di menu Revenue Target untuk bulan ini.',
                'outlet' => [
                    'id' => (int) $outlet->id_outlet,
                    'name' => $outlet->nama_outlet,
                ],
                'month' => $month,
                'monthly_target' => 0,
                'actual_mtd' => 0,
                'projected_eom' => 0,
                'gap_vs_target' => 0,
                'pace_factor' => 1.0,
                'mode' => 'no_target',
                'remaining_weekdays' => 0,
                'remaining_weekends' => 0,
                'remaining_holidays' => 0,
                'days' => [],
                'history_compare' => [],
            ];
        }

        $monthlyTarget = (float) $header->monthly_target;
        $qrCode = $outlet->qr_code;

        $threeMonthsAgoStart = $monthStart->copy()->subMonths(3)->startOfMonth();
        $previousMonthEnd = $monthStart->copy()->subMonth()->endOfMonth();

        $holidaysMeta = $this->loadHolidays(
            $threeMonthsAgoStart->toDateString(),
            $monthEnd->toDateString()
        );
        $holidaySet = $holidaysMeta['set'];
        $holidayNames = $holidaysMeta['names'];

        $histRevenueByDate = $this->dailyRevenueMap(
            $qrCode,
            $threeMonthsAgoStart->toDateString() . ' 00:00:00',
            $previousMonthEnd->toDateString() . ' 23:59:59'
        );

        $stats = $this->buildHistoricalStats($threeMonthsAgoStart, $previousMonthEnd, $histRevenueByDate, $holidaySet);
        $avgByDow = $stats['avg_by_dow'];
        $avgByType = $stats['avg_by_type'];
        $holidayBoost = $stats['holiday_boost'];
        $ramadanBoost = $stats['ramadan_boost'];

        $baselineRows = $this->buildBaselineDays(
            $monthStart,
            $monthEnd,
            $monthlyTarget,
            $avgByDow,
            $avgByType,
            $holidaySet,
            $holidayNames,
            $holidayBoost,
            $ramadanBoost
        );

        $actualByDate = $this->dailyRevenueMap(
            $qrCode,
            $monthStart->toDateString() . ' 00:00:00',
            $monthEnd->toDateString() . ' 23:59:59'
        );

        // As-of: lewat kemarin agar pace tidak terpengaruh revenue partial hari ini.
        // Hari 1 bulan berjalan → belum ada hari penuh → mode baseline.
        if ($isCurrentMonth) {
            $asOf = $today->copy()->subDay();
            if ($asOf->lt($monthStart)) {
                $asOf = $monthStart->copy()->subDay();
            }
        } elseif ($isPastMonth) {
            $asOf = $monthEnd->copy();
        } else {
            // Future month: belum ada actual — pakai baseline penuh
            $asOf = $monthStart->copy()->subDay();
        }

        $actualMtd = 0.0;
        $expectedToDate = 0.0;
        $daysPassedWithBaseline = 0;

        foreach ($baselineRows as $row) {
            $dateKey = $row['forecast_date'];
            if ($dateKey > $asOf->toDateString()) {
                continue;
            }
            $actual = (float) ($actualByDate[$dateKey] ?? 0);
            $actualMtd += $actual;
            $expectedToDate += (float) $row['baseline'];
            $daysPassedWithBaseline++;
        }

        $usePace = !$isFutureMonth
            && $daysPassedWithBaseline >= self::MIN_DAYS_FOR_PACE
            && $expectedToDate > 0
            && $actualMtd > 0;

        $paceFactor = 1.0;
        if ($usePace) {
            $paceFactor = max(self::PACE_MIN, min(self::PACE_MAX, $actualMtd / $expectedToDate));
        }

        $remainingWeekdays = 0;
        $remainingWeekends = 0;
        $remainingHolidays = 0;
        $days = [];
        $projectedRemaining = 0.0;

        foreach ($baselineRows as $row) {
            $dateKey = $row['forecast_date'];
            $dayType = $row['day_type'];
            $baseline = (float) $row['baseline'];
            $histAvg = (float) $row['hist_avg'];
            $actual = array_key_exists($dateKey, $actualByDate)
                ? (float) $actualByDate[$dateKey]
                : null;

            $isPastOrToday = $dateKey <= $asOf->toDateString();
            $isFutureDay = $dateKey > $asOf->toDateString();

            if ($isFutureDay) {
                if ($dayType === 'holiday' || $dayType === 'ramadan') {
                    $remainingHolidays++;
                } elseif ($dayType === 'weekend') {
                    $remainingWeekends++;
                } else {
                    $remainingWeekdays++;
                }

                $rawProjected = $baseline * $paceFactor;
                $projected = $this->capAchievable($rawProjected, $histAvg, $baseline);
                $projectedRemaining += $projected;
                $status = 'forecast';
            } elseif ($isPastOrToday) {
                $projected = $actual !== null ? (float) $actual : 0.0;
                $status = 'actual';
            } else {
                $projected = $baseline;
                $status = 'baseline';
            }

            $days[] = [
                'forecast_date' => $dateKey,
                'day_name' => $row['day_name'],
                'day_type' => $dayType,
                'holiday_name' => $row['holiday_name'],
                'actual' => $isPastOrToday ? round((float) ($actual ?? 0), 2) : null,
                'baseline' => round($baseline, 2),
                'projected' => round($projected, 2),
                'hist_avg' => round($histAvg, 2),
                'status' => $status,
            ];
        }

        if ($isFutureMonth) {
            foreach ($days as $idx => $d) {
                $days[$idx]['projected'] = $d['baseline'];
                $days[$idx]['status'] = 'baseline';
            }
            $projectedEom = round(array_sum(array_column($days, 'baseline')), 2);
            $mode = 'baseline';
            $actualMtd = 0.0;
            $remainingWeekdays = 0;
            $remainingWeekends = 0;
            $remainingHolidays = 0;
            foreach ($days as $d) {
                if ($d['day_type'] === 'holiday' || $d['day_type'] === 'ramadan') {
                    $remainingHolidays++;
                } elseif ($d['day_type'] === 'weekend') {
                    $remainingWeekends++;
                } else {
                    $remainingWeekdays++;
                }
            }
        } elseif (!$usePace) {
            // Awal bulan / MTD kosong → projected ≈ target (baseline sisa + actual)
            foreach ($days as $idx => $d) {
                if ($d['status'] === 'forecast') {
                    $days[$idx]['projected'] = $d['baseline'];
                }
            }
            $projectedRemaining = array_sum(array_map(
                fn ($d) => $d['status'] === 'forecast' ? (float) $d['projected'] : 0.0,
                $days
            ));
            $projectedEom = round($actualMtd + $projectedRemaining, 2);
            $mode = $actualMtd <= 0 ? 'baseline' : 'early_month';
            $paceFactor = 1.0;
        } else {
            $projectedEom = round($actualMtd + $projectedRemaining, 2);
            $mode = 'rolling';
        }

        $gapVsTarget = round($projectedEom - $monthlyTarget, 2);
        $historyCompare = $this->buildHistoryCompare(
            $monthStart,
            $qrCode,
            $holidaySet
        );

        return [
            'success' => true,
            'has_target' => true,
            'message' => null,
            'outlet' => [
                'id' => (int) $outlet->id_outlet,
                'name' => $outlet->nama_outlet,
            ],
            'month' => $month,
            'monthly_target' => round($monthlyTarget, 2),
            'actual_mtd' => round($actualMtd, 2),
            'projected_eom' => $projectedEom,
            'gap_vs_target' => $gapVsTarget,
            'pct_of_target' => $monthlyTarget > 0
                ? round(($projectedEom / $monthlyTarget) * 100, 1)
                : 0.0,
            'pace_factor' => round($paceFactor, 4),
            'expected_to_date' => round($expectedToDate, 2),
            'mode' => $mode,
            'as_of' => $asOf->toDateString(),
            'remaining_weekdays' => $remainingWeekdays,
            'remaining_weekends' => $remainingWeekends,
            'remaining_holidays' => $remainingHolidays,
            'days' => $days,
            'history_compare' => $historyCompare,
        ];
    }

    /**
     * @return array{success:bool,has_target:bool,message:string,error_code:int}
     */
    private function errorPayload(string $message, int $code): array
    {
        return [
            'success' => false,
            'has_target' => false,
            'message' => $message,
            'error_code' => $code,
        ];
    }

    /**
     * @return array{set: array<string, true>, names: array<string, string>}
     */
    private function loadHolidays(string $from, string $to): array
    {
        $rows = DB::table('tbl_kalender_perusahaan')
            ->whereBetween('tgl_libur', [$from, $to])
            ->get(['tgl_libur', 'keterangan']);

        $set = [];
        $names = [];
        foreach ($rows as $row) {
            $key = Carbon::parse($row->tgl_libur)->toDateString();
            $set[$key] = true;
            $names[$key] = (string) ($row->keterangan ?? '');
        }

        return ['set' => $set, 'names' => $names];
    }

    /**
     * @return array<string, float>
     */
    private function dailyRevenueMap(string $qrCode, string $fromDt, string $toDt): array
    {
        return DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereBetween('created_at', [$fromDt, $toDt])
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DATE(created_at) as dt, SUM(grand_total) as revenue')
            ->groupBy('dt')
            ->pluck('revenue', 'dt')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @param  array<string, float>  $histRevenueByDate
     * @param  array<string, true>  $holidaySet
     * @return array{avg_by_dow: array<int, float>, avg_by_type: array<string, float>, holiday_boost: float, ramadan_boost: float}
     */
    private function buildHistoricalStats(
        Carbon $from,
        Carbon $to,
        array $histRevenueByDate,
        array $holidaySet
    ): array {
        $statsByDow = [];
        $statsByType = [
            'weekday' => [],
            'weekend' => [],
            'holiday' => [],
            'ramadan' => [],
        ];

        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $dateKey = $cursor->toDateString();
            $dow = (int) $cursor->dayOfWeek;
            $isHoliday = isset($holidaySet[$dateKey]);
            $isWeekend = in_array($dow, [0, 6], true);
            $dayType = $isHoliday ? 'holiday' : ($isWeekend ? 'weekend' : 'weekday');
            $revenue = (float) ($histRevenueByDate[$dateKey] ?? 0);

            $statsByDow[$dow][] = $revenue;
            $statsByType[$dayType][] = $revenue;
            if ($isHoliday) {
                $statsByType['ramadan'][] = $revenue;
            }

            $cursor->addDay();
        }

        $avgByDow = [];
        foreach ($statsByDow as $dow => $values) {
            $avgByDow[(int) $dow] = $this->average($values);
        }

        $avgWeekday = $this->average($statsByType['weekday']);
        $avgWeekend = $this->average($statsByType['weekend']);
        $avgHoliday = $this->average($statsByType['holiday']);

        $holidayRatio = $avgWeekday > 0 ? ($avgHoliday / $avgWeekday) : 1.15;
        $holidayBoost = max(1.03, min(1.18, $holidayRatio > 0 ? $holidayRatio : 1.10));
        $ramadanBoost = max($holidayBoost, min(1.25, $holidayRatio > 0 ? $holidayRatio * 1.05 : 1.15));

        return [
            'avg_by_dow' => $avgByDow,
            'avg_by_type' => [
                'weekday' => $avgWeekday,
                'weekend' => $avgWeekend,
                'holiday' => $avgHoliday,
                'ramadan' => $avgHoliday > 0 ? $avgHoliday : $avgWeekend,
            ],
            'holiday_boost' => $holidayBoost,
            'ramadan_boost' => $ramadanBoost,
        ];
    }

    /**
     * @param  array<int, float>  $avgByDow
     * @param  array<string, float>  $avgByType
     * @param  array<string, true>  $holidaySet
     * @param  array<string, string>  $holidayNames
     * @return list<array{forecast_date: string, day_name: string, day_type: string, holiday_name: string|null, baseline: float, hist_avg: float, weight: float}>
     */
    private function buildBaselineDays(
        Carbon $monthStart,
        Carbon $monthEnd,
        float $monthlyTarget,
        array $avgByDow,
        array $avgByType,
        array $holidaySet,
        array $holidayNames,
        float $holidayBoost,
        float $ramadanBoost
    ): array {
        $weightRows = [];
        $totalWeight = 0.0;
        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            $dateKey = $cursor->toDateString();
            $dow = (int) $cursor->dayOfWeek;
            $isHoliday = isset($holidaySet[$dateKey]);
            $isWeekend = in_array($dow, [0, 6], true);
            $holidayName = $isHoliday ? ($holidayNames[$dateKey] ?? '') : null;
            $isRamadan = $isHoliday && $this->isRamadanName((string) $holidayName);

            $dayType = $isRamadan
                ? 'ramadan'
                : ($isHoliday ? 'holiday' : ($isWeekend ? 'weekend' : 'weekday'));

            $histAvg = (float) ($avgByDow[$dow] ?? 0);
            if ($histAvg <= 0) {
                $histAvg = match ($dayType) {
                    'ramadan' => $avgByType['ramadan'] > 0 ? $avgByType['ramadan'] : ($avgByType['holiday'] ?: 1.35),
                    'holiday' => $avgByType['holiday'] > 0 ? $avgByType['holiday'] : 1.35,
                    'weekend' => $avgByType['weekend'] > 0 ? $avgByType['weekend'] : 1.20,
                    default => $avgByType['weekday'] > 0 ? $avgByType['weekday'] : 1.00,
                };
            }

            $weight = $histAvg > 0 ? $histAvg : 1.0;
            if ($dayType === 'ramadan') {
                $weight *= $ramadanBoost;
            } elseif ($dayType === 'holiday') {
                $weight *= $holidayBoost;
            }
            $weight = max(0.01, $weight);

            $weightRows[] = [
                'forecast_date' => $dateKey,
                'day_name' => $cursor->locale('id')->isoFormat('dddd'),
                'day_type' => $dayType,
                'holiday_name' => $holidayName ?: null,
                'hist_avg' => $histAvg,
                'weight' => $weight,
            ];
            $totalWeight += $weight;
            $cursor->addDay();
        }

        $normalization = $totalWeight > 0 ? ($monthlyTarget / $totalWeight) : 0.0;
        $result = [];
        $sumBaseline = 0.0;

        foreach ($weightRows as $row) {
            $baseline = round($row['weight'] * $normalization, 2);
            $sumBaseline += $baseline;
            $result[] = [
                'forecast_date' => $row['forecast_date'],
                'day_name' => $row['day_name'],
                'day_type' => $row['day_type'],
                'holiday_name' => $row['holiday_name'],
                'baseline' => $baseline,
                'hist_avg' => round((float) $row['hist_avg'], 2),
                'weight' => $row['weight'],
            ];
        }

        // Koreksi rounding ke hari terakhir
        $diff = round($monthlyTarget - $sumBaseline, 2);
        if (!empty($result) && abs($diff) >= 0.01) {
            $last = count($result) - 1;
            $result[$last]['baseline'] = round(max(0, $result[$last]['baseline'] + $diff), 2);
        }

        return $result;
    }

    private function capAchievable(float $value, float $histAvg, float $baseline = 0.0): float
    {
        // Batasi upside heroik saja — jangan naikkan hari yang memang lemah (MTD jelek).
        // Ceiling = max(1.25× hist DoW, baseline) agar baseline target tidak dihancurkan.
        if ($histAvg <= 0) {
            return max(0, round($value, 2));
        }

        $high = max($histAvg * self::CAP_HIGH, $baseline);

        return round(max(0, min($high, $value)), 2);
    }

    private function isRamadanName(string $name): bool
    {
        $n = mb_strtolower($name);
        return str_contains($n, 'ramadhan')
            || str_contains($n, 'ramadan')
            || str_contains($n, 'puasa');
    }

    /**
     * Ringkasan 3 bulan sebelumnya untuk compare di UI.
     *
     * @param  array<string, true>  $holidaySet
     * @return list<array<string, mixed>>
     */
    private function buildHistoryCompare(Carbon $monthStart, string $qrCode, array $holidaySet): array
    {
        $cards = [];

        for ($k = 1; $k <= 3; $k++) {
            $start = $monthStart->copy()->subMonths($k)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $monthKey = $start->format('Y-m');

            $daily = $this->dailyRevenueMap(
                $qrCode,
                $start->toDateString() . ' 00:00:00',
                $end->toDateString() . ' 23:59:59'
            );

            $weekdayTotal = 0.0;
            $weekendTotal = 0.0;
            $holidayTotal = 0.0;
            $weekdayDays = 0;
            $weekendDays = 0;
            $holidayDays = 0;

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $dateKey = $cursor->toDateString();
                $rev = (float) ($daily[$dateKey] ?? 0);
                $dow = (int) $cursor->dayOfWeek;
                $isHoliday = isset($holidaySet[$dateKey]);
                $isWeekend = in_array($dow, [0, 6], true);

                if ($isHoliday) {
                    $holidayTotal += $rev;
                    $holidayDays++;
                } elseif ($isWeekend) {
                    $weekendTotal += $rev;
                    $weekendDays++;
                } else {
                    $weekdayTotal += $rev;
                    $weekdayDays++;
                }
                $cursor->addDay();
            }

            $total = array_sum($daily);
            $cards[] = [
                'month' => $monthKey,
                'label' => $start->locale('id')->translatedFormat('F Y'),
                'total' => round($total, 2),
                'avg_weekday' => $weekdayDays > 0 ? round($weekdayTotal / $weekdayDays, 2) : 0,
                'avg_weekend' => $weekendDays > 0 ? round($weekendTotal / $weekendDays, 2) : 0,
                'avg_holiday' => $holidayDays > 0 ? round($holidayTotal / $holidayDays, 2) : 0,
                'days_with_sales' => count(array_filter($daily, fn ($v) => $v > 0)),
            ];
        }

        return $cards;
    }

    /**
     * @param  list<float|int>  $values
     */
    private function average(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }
}
