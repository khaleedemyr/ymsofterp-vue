<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive sales diagnostics for Outlet Sales Dashboard.
 * Explains revenue movement vs last 3 months (pax vs average check, region, outlet, menu).
 */
class SalesOutletAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $dateFrom, string $dateTo): array
    {
        $currentStart = Carbon::parse($dateFrom)->startOfMonth();
        // Use filter bounds within the selected month window
        $periodStart = Carbon::parse($dateFrom)->startOfDay();
        $periodEnd = Carbon::parse($dateTo)->endOfDay();

        $monthKey = $currentStart->format('Y-m');
        $compareMonths = [];
        for ($i = 1; $i <= 3; $i++) {
            $m = $currentStart->copy()->subMonths($i);
            $compareMonths[] = [
                'key' => $m->format('Y-m'),
                'label' => $m->locale('id')->translatedFormat('F Y'),
                'from' => $m->copy()->startOfMonth()->toDateString(),
                'to' => $m->copy()->endOfMonth()->toDateString(),
            ];
        }

        $allMonthStarts = collect($compareMonths)
            ->pluck('from')
            ->push($periodStart->toDateString())
            ->sort()
            ->values();
        $histFrom = Carbon::parse($allMonthStarts->first())->startOfDay()->toDateTimeString();
        $histToExclusive = $periodEnd->copy()->addDay()->startOfDay()->toDateTimeString();

        $monthlyTotals = $this->monthlyTotals($histFrom, $histToExclusive);
        $current = $this->periodTotals(
            $periodStart->toDateTimeString(),
            $periodEnd->copy()->addDay()->startOfDay()->toDateTimeString()
        );

        $priorMonths = [];
        foreach ($compareMonths as $cm) {
            $key = $cm['key'];
            $row = $monthlyTotals[$key] ?? $this->emptyTotals();
            $priorMonths[] = array_merge($cm, $row, [
                'vs_current' => $this->deltaBlock($current, $row),
            ]);
        }

        $avg3 = $this->averageTotals(array_map(fn ($p) => [
            'revenue' => $p['revenue'],
            'orders' => $p['orders'],
            'pax' => $p['pax'],
            'avg_check' => $p['avg_check'],
            'aov' => $p['aov'],
        ], $priorMonths));

        $vsAvg3 = $this->deltaBlock($current, $avg3);
        $driver = $this->diagnoseDriver($vsAvg3, $current, $avg3);

        $regionAnalysis = $this->regionMonthAnalysis(
            $histFrom,
            $histToExclusive,
            $monthKey,
            array_column($compareMonths, 'key'),
            $periodStart->toDateTimeString(),
            $periodEnd->copy()->addDay()->startOfDay()->toDateTimeString()
        );

        $outletAnalysis = $this->outletMonthAnalysis(
            $histFrom,
            $histToExclusive,
            $monthKey,
            array_column($compareMonths, 'key'),
            $periodStart->toDateTimeString(),
            $periodEnd->copy()->addDay()->startOfDay()->toDateTimeString()
        );

        $daypartAnalysis = $this->daypartAnalysis(
            $periodStart->toDateTimeString(),
            $periodEnd->copy()->addDay()->startOfDay()->toDateTimeString(),
            $compareMonths
        );

        $weekdayAnalysis = $this->weekdayAnalysis(
            $periodStart->toDateTimeString(),
            $periodEnd->copy()->addDay()->startOfDay()->toDateTimeString(),
            $compareMonths
        );

        $menuAnalysis = $this->menuAnalysis(
            $periodStart->toDateTimeString(),
            $periodEnd->copy()->addDay()->startOfDay()->toDateTimeString(),
            $compareMonths
        );

        $findings = $this->buildFindings($driver, $vsAvg3, $regionAnalysis, $outletAnalysis, $daypartAnalysis, $menuAnalysis);

        return [
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'month' => $monthKey,
                'label' => $currentStart->locale('id')->translatedFormat('F Y'),
            ],
            'current' => $current,
            'compare_months' => $priorMonths,
            'avg_last_3_months' => $avg3,
            'vs_avg_last_3' => $vsAvg3,
            'driver' => $driver,
            'regions' => $regionAnalysis,
            'outlets' => $outletAnalysis,
            'daypart' => $daypartAnalysis,
            'weekday_weekend' => $weekdayAnalysis,
            'menu' => $menuAnalysis,
            'findings' => $findings,
            'narrative' => $this->buildNarrative($driver, $vsAvg3, $findings, $current, $avg3),
        ];
    }

    /**
     * @return array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}
     */
    private function emptyTotals(): array
    {
        return [
            'revenue' => 0.0,
            'orders' => 0,
            'pax' => 0,
            'avg_check' => 0.0,
            'aov' => 0.0,
        ];
    }

    /**
     * @return array<string, array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}>
     */
    private function monthlyTotals(string $fromDt, string $toExclusive): array
    {
        $rows = DB::select("
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month_key,
                COUNT(*) as orders,
                COALESCE(SUM(grand_total), 0) as revenue,
                COALESCE(SUM(pax), 0) as pax
            FROM orders
            WHERE created_at >= ? AND created_at < ?
              AND status != 'cancelled'
              AND grand_total > 0
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ", [$fromDt, $toExclusive]);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->month_key] = $this->normalizeTotals(
                (float) $row->revenue,
                (int) $row->orders,
                (int) $row->pax
            );
        }

        return $map;
    }

    /**
     * @return array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}
     */
    private function periodTotals(string $fromDt, string $toExclusive): array
    {
        $row = DB::selectOne("
            SELECT
                COUNT(*) as orders,
                COALESCE(SUM(grand_total), 0) as revenue,
                COALESCE(SUM(pax), 0) as pax
            FROM orders
            WHERE created_at >= ? AND created_at < ?
              AND status != 'cancelled'
              AND grand_total > 0
        ", [$fromDt, $toExclusive]);

        return $this->normalizeTotals(
            (float) ($row->revenue ?? 0),
            (int) ($row->orders ?? 0),
            (int) ($row->pax ?? 0)
        );
    }

    /**
     * @return array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}
     */
    private function normalizeTotals(float $revenue, int $orders, int $pax): array
    {
        return [
            'revenue' => round($revenue, 2),
            'orders' => $orders,
            'pax' => $pax,
            'avg_check' => $pax > 0 ? round($revenue / $pax, 2) : 0.0,
            'aov' => $orders > 0 ? round($revenue / $orders, 2) : 0.0,
        ];
    }

    /**
     * @param  list<array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}>  $rows
     * @return array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}
     */
    private function averageTotals(array $rows): array
    {
        $n = max(1, count($rows));
        $revenue = array_sum(array_column($rows, 'revenue')) / $n;
        $orders = (int) round(array_sum(array_column($rows, 'orders')) / $n);
        $pax = (int) round(array_sum(array_column($rows, 'pax')) / $n);

        return $this->normalizeTotals($revenue, $orders, $pax);
    }

    /**
     * @param  array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}  $current
     * @param  array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}  $base
     * @return array<string, mixed>
     */
    private function deltaBlock(array $current, array $base): array
    {
        return [
            'revenue_delta' => round($current['revenue'] - $base['revenue'], 2),
            'revenue_pct' => $this->pctChange($current['revenue'], $base['revenue']),
            'orders_delta' => $current['orders'] - $base['orders'],
            'orders_pct' => $this->pctChange($current['orders'], $base['orders']),
            'pax_delta' => $current['pax'] - $base['pax'],
            'pax_pct' => $this->pctChange($current['pax'], $base['pax']),
            'avg_check_delta' => round($current['avg_check'] - $base['avg_check'], 2),
            'avg_check_pct' => $this->pctChange($current['avg_check'], $base['avg_check']),
            'aov_delta' => round($current['aov'] - $base['aov'], 2),
            'aov_pct' => $this->pctChange($current['aov'], $base['aov']),
        ];
    }

    private function pctChange(float|int $current, float|int $base): ?float
    {
        if ((float) $base == 0.0) {
            return null;
        }

        return round((((float) $current - (float) $base) / (float) $base) * 100, 1);
    }

    /**
     * Decompose ΔRevenue ≈ Pax_base × ΔAvgCheck + AvgCheck_curr × ΔPax
     *
     * @param  array<string, mixed>  $delta
     * @param  array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}  $current
     * @param  array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}  $base
     * @return array<string, mixed>
     */
    private function diagnoseDriver(array $delta, array $current, array $base): array
    {
        $paxEffect = $base['avg_check'] * ($current['pax'] - $base['pax']);
        $checkEffect = $base['pax'] * ($current['avg_check'] - $base['avg_check']);
        // Cross term absorbed into check effect using current pax alternative:
        // more stable: use avg of both for attribution share
        $totalAbs = abs($paxEffect) + abs($checkEffect);
        $paxShare = $totalAbs > 0 ? round(abs($paxEffect) / $totalAbs * 100, 1) : 50.0;
        $checkShare = $totalAbs > 0 ? round(abs($checkEffect) / $totalAbs * 100, 1) : 50.0;

        $revenuePct = $delta['revenue_pct'];
        $direction = 'flat';
        if ($revenuePct !== null) {
            if ($revenuePct <= -3) {
                $direction = 'down';
            } elseif ($revenuePct >= 3) {
                $direction = 'up';
            }
        }

        $primary = 'mixed';
        if ($paxShare >= 60) {
            $primary = 'pax';
        } elseif ($checkShare >= 60) {
            $primary = 'avg_check';
        }

        $paxDown = ($delta['pax_pct'] ?? 0) < -1;
        $checkDown = ($delta['avg_check_pct'] ?? 0) < -1;
        $paxUp = ($delta['pax_pct'] ?? 0) > 1;
        $checkUp = ($delta['avg_check_pct'] ?? 0) > 1;

        $label = match (true) {
            $direction === 'down' && $paxDown && $checkDown => 'Omzet turun karena Pax dan Average Check sama-sama melemah',
            $direction === 'down' && $primary === 'pax' => 'Omzet turun terutama karena Pax (traffic) turun',
            $direction === 'down' && $primary === 'avg_check' => 'Omzet turun terutama karena Average Check turun',
            $direction === 'up' && $paxUp && $checkUp => 'Omzet naik didukung Pax dan Average Check',
            $direction === 'up' && $primary === 'pax' => 'Omzet naik terutama karena Pax naik',
            $direction === 'up' && $primary === 'avg_check' => 'Omzet naik terutama karena Average Check naik',
            $direction === 'down' && $paxDown && $checkUp => 'Omzet turun: Pax turun meski Average Check naik (tidak cukup kompensasi)',
            $direction === 'down' && $paxUp && $checkDown => 'Omzet turun: Average Check turun meski Pax naik',
            default => 'Pergerakan omzet relatif stabil / campuran',
        };

        return [
            'direction' => $direction,
            'primary' => $primary,
            'label' => $label,
            'pax_effect' => round($paxEffect, 2),
            'avg_check_effect' => round($checkEffect, 2),
            'pax_share_pct' => $paxShare,
            'avg_check_share_pct' => $checkShare,
            'severity' => $this->severityText($direction, $primary, $delta),
        ];
    }

    /**
     * @param  array<string, mixed>  $delta
     */
    private function headlineText(string $direction, string $primary, array $delta): string
    {
        $rev = $delta['revenue_pct'];
        $pax = $delta['pax_pct'];
        $chk = $delta['avg_check_pct'];
        $revTxt = $rev === null ? 'n/a' : (($rev >= 0 ? '+' : '') . $rev . '%');
        $paxTxt = $pax === null ? 'n/a' : (($pax >= 0 ? '+' : '') . $pax . '%');
        $chkTxt = $chk === null ? 'n/a' : (($chk >= 0 ? '+' : '') . $chk . '%');

        return "Vs rata-rata 3 bulan terakhir: Omzet {$revTxt}, Pax {$paxTxt}, Avg Check {$chkTxt}. Driver utama: "
            . ($primary === 'pax' ? 'Pax/traffic' : ($primary === 'avg_check' ? 'Average Check' : 'campuran keduanya'))
            . '.';
    }

    /**
     * @param  list<string>  $priorKeys
     * @return array<string, mixed>
     */
    private function regionMonthAnalysis(
        string $histFrom,
        string $histToExclusive,
        string $currentMonth,
        array $priorKeys,
        string $periodFrom,
        string $periodToExclusive
    ): array {
        $rows = DB::select("
            SELECT
                COALESCE(r.name, 'Unknown Region') as region_name,
                COALESCE(r.code, 'UNK') as region_code,
                DATE_FORMAT(o.created_at, '%Y-%m') as month_key,
                COUNT(*) as orders,
                COALESCE(SUM(o.grand_total), 0) as revenue,
                COALESCE(SUM(o.pax), 0) as pax
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions r ON outlet.region_id = r.id
            WHERE o.created_at >= ? AND o.created_at < ?
              AND o.status != 'cancelled'
              AND o.grand_total > 0
            GROUP BY COALESCE(r.name, 'Unknown Region'), COALESCE(r.code, 'UNK'), DATE_FORMAT(o.created_at, '%Y-%m')
        ", [$histFrom, $histToExclusive]);

        // Override current month with exact filter window
        $currentRows = DB::select("
            SELECT
                COALESCE(r.name, 'Unknown Region') as region_name,
                COALESCE(r.code, 'UNK') as region_code,
                COUNT(*) as orders,
                COALESCE(SUM(o.grand_total), 0) as revenue,
                COALESCE(SUM(o.pax), 0) as pax
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions r ON outlet.region_id = r.id
            WHERE o.created_at >= ? AND o.created_at < ?
              AND o.status != 'cancelled'
              AND o.grand_total > 0
            GROUP BY COALESCE(r.name, 'Unknown Region'), COALESCE(r.code, 'UNK')
        ", [$periodFrom, $periodToExclusive]);

        $byRegion = [];
        foreach ($rows as $row) {
            $name = $row->region_name;
            if (! isset($byRegion[$name])) {
                $byRegion[$name] = [
                    'region_name' => $name,
                    'region_code' => $row->region_code,
                    'months' => [],
                ];
            }
            $byRegion[$name]['months'][$row->month_key] = $this->normalizeTotals(
                (float) $row->revenue,
                (int) $row->orders,
                (int) $row->pax
            );
        }

        foreach ($currentRows as $row) {
            $name = $row->region_name;
            if (! isset($byRegion[$name])) {
                $byRegion[$name] = [
                    'region_name' => $name,
                    'region_code' => $row->region_code,
                    'months' => [],
                ];
            }
            $byRegion[$name]['months'][$currentMonth] = $this->normalizeTotals(
                (float) $row->revenue,
                (int) $row->orders,
                (int) $row->pax
            );
        }

        $result = [];
        foreach ($byRegion as $region) {
            $current = $region['months'][$currentMonth] ?? $this->emptyTotals();
            $priors = [];
            foreach ($priorKeys as $key) {
                $priors[] = $region['months'][$key] ?? $this->emptyTotals();
            }
            $avg = $this->averageTotals($priors);
            $delta = $this->deltaBlock($current, $avg);
            $driver = $this->diagnoseDriver($delta, $current, $avg);

            $result[] = [
                'region_name' => $region['region_name'],
                'region_code' => $region['region_code'],
                'current' => $current,
                'avg_last_3' => $avg,
                'vs_avg_last_3' => $delta,
                'driver' => [
                    'primary' => $driver['primary'],
                    'label' => $driver['label'],
                    'pax_share_pct' => $driver['pax_share_pct'],
                    'avg_check_share_pct' => $driver['avg_check_share_pct'],
                ],
            ];
        }

        usort($result, fn ($a, $b) => ($a['vs_avg_last_3']['revenue_pct'] ?? 0) <=> ($b['vs_avg_last_3']['revenue_pct'] ?? 0));

        return [
            'worst' => array_slice($result, 0, 5),
            'best' => array_slice(array_reverse($result), 0, 5),
            'all' => $result,
        ];
    }

    /**
     * @param  list<string>  $priorKeys
     * @return array<string, mixed>
     */
    private function outletMonthAnalysis(
        string $histFrom,
        string $histToExclusive,
        string $currentMonth,
        array $priorKeys,
        string $periodFrom,
        string $periodToExclusive
    ): array {
        $rows = DB::select("
            SELECT
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(r.name, 'Unknown Region') as region_name,
                DATE_FORMAT(o.created_at, '%Y-%m') as month_key,
                COUNT(*) as orders,
                COALESCE(SUM(o.grand_total), 0) as revenue,
                COALESCE(SUM(o.pax), 0) as pax
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions r ON outlet.region_id = r.id
            WHERE o.created_at >= ? AND o.created_at < ?
              AND o.status != 'cancelled'
              AND o.grand_total > 0
            GROUP BY o.kode_outlet, outlet.nama_outlet, r.name, month_key
        ", [$histFrom, $histToExclusive]);

        $currentRows = DB::select("
            SELECT
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(r.name, 'Unknown Region') as region_name,
                COUNT(*) as orders,
                COALESCE(SUM(o.grand_total), 0) as revenue,
                COALESCE(SUM(o.pax), 0) as pax
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions r ON outlet.region_id = r.id
            WHERE o.created_at >= ? AND o.created_at < ?
              AND o.status != 'cancelled'
              AND o.grand_total > 0
            GROUP BY o.kode_outlet, outlet.nama_outlet, r.name
        ", [$periodFrom, $periodToExclusive]);

        $byOutlet = [];
        foreach ($rows as $row) {
            $code = $row->kode_outlet;
            if (! isset($byOutlet[$code])) {
                $byOutlet[$code] = [
                    'outlet_code' => $code,
                    'outlet_name' => $row->outlet_name,
                    'region_name' => $row->region_name,
                    'months' => [],
                ];
            }
            $byOutlet[$code]['months'][$row->month_key] = $this->normalizeTotals(
                (float) $row->revenue,
                (int) $row->orders,
                (int) $row->pax
            );
        }
        foreach ($currentRows as $row) {
            $code = $row->kode_outlet;
            if (! isset($byOutlet[$code])) {
                $byOutlet[$code] = [
                    'outlet_code' => $code,
                    'outlet_name' => $row->outlet_name,
                    'region_name' => $row->region_name,
                    'months' => [],
                ];
            }
            $byOutlet[$code]['outlet_name'] = $row->outlet_name;
            $byOutlet[$code]['region_name'] = $row->region_name;
            $byOutlet[$code]['months'][$currentMonth] = $this->normalizeTotals(
                (float) $row->revenue,
                (int) $row->orders,
                (int) $row->pax
            );
        }

        $result = [];
        foreach ($byOutlet as $outlet) {
            $current = $outlet['months'][$currentMonth] ?? $this->emptyTotals();
            if ($current['revenue'] <= 0 && empty(array_filter($outlet['months']))) {
                continue;
            }
            $priors = [];
            foreach ($priorKeys as $key) {
                $priors[] = $outlet['months'][$key] ?? $this->emptyTotals();
            }
            $avg = $this->averageTotals($priors);
            // Skip outlets with no history and tiny current
            if ($avg['revenue'] <= 0 && $current['revenue'] <= 0) {
                continue;
            }
            $delta = $this->deltaBlock($current, $avg);
            $driver = $this->diagnoseDriver($delta, $current, $avg);

            $result[] = [
                'outlet_code' => $outlet['outlet_code'],
                'outlet_name' => $outlet['outlet_name'],
                'region_name' => $outlet['region_name'],
                'current' => $current,
                'avg_last_3' => $avg,
                'vs_avg_last_3' => $delta,
                'driver_primary' => $driver['primary'],
            ];
        }

        usort($result, fn ($a, $b) => ($a['vs_avg_last_3']['revenue_delta'] ?? 0) <=> ($b['vs_avg_last_3']['revenue_delta'] ?? 0));

        return [
            'worst' => array_slice($result, 0, 8),
            'best' => array_slice(array_reverse($result), 0, 8),
        ];
    }

    /**
     * @param  list<array{from: string, to: string}>  $compareMonths
     * @return array<string, mixed>
     */
    private function daypartAnalysis(string $periodFrom, string $periodToExclusive, array $compareMonths): array
    {
        $build = function (string $from, string $toExclusive) {
            $rows = DB::select("
                SELECT
                    CASE WHEN HOUR(created_at) <= 17 THEN 'lunch' ELSE 'dinner' END as meal,
                    COUNT(*) as orders,
                    COALESCE(SUM(grand_total), 0) as revenue,
                    COALESCE(SUM(pax), 0) as pax
                FROM orders
                WHERE created_at >= ? AND created_at < ?
                  AND status != 'cancelled'
                  AND grand_total > 0
                GROUP BY CASE WHEN HOUR(created_at) <= 17 THEN 'lunch' ELSE 'dinner' END
            ", [$from, $toExclusive]);

            $out = [
                'lunch' => $this->emptyTotals(),
                'dinner' => $this->emptyTotals(),
            ];
            foreach ($rows as $row) {
                $out[$row->meal] = $this->normalizeTotals((float) $row->revenue, (int) $row->orders, (int) $row->pax);
            }

            return $out;
        };

        $current = $build($periodFrom, $periodToExclusive);
        $priorsLunch = [];
        $priorsDinner = [];
        foreach ($compareMonths as $cm) {
            $from = Carbon::parse($cm['from'])->startOfDay()->toDateTimeString();
            $to = Carbon::parse($cm['to'])->addDay()->startOfDay()->toDateTimeString();
            $m = $build($from, $to);
            $priorsLunch[] = $m['lunch'];
            $priorsDinner[] = $m['dinner'];
        }
        $avgLunch = $this->averageTotals($priorsLunch);
        $avgDinner = $this->averageTotals($priorsDinner);

        return [
            'lunch' => [
                'current' => $current['lunch'],
                'avg_last_3' => $avgLunch,
                'vs_avg_last_3' => $this->deltaBlock($current['lunch'], $avgLunch),
                'driver' => $this->diagnoseDriver(
                    $this->deltaBlock($current['lunch'], $avgLunch),
                    $current['lunch'],
                    $avgLunch
                ),
            ],
            'dinner' => [
                'current' => $current['dinner'],
                'avg_last_3' => $avgDinner,
                'vs_avg_last_3' => $this->deltaBlock($current['dinner'], $avgDinner),
                'driver' => $this->diagnoseDriver(
                    $this->deltaBlock($current['dinner'], $avgDinner),
                    $current['dinner'],
                    $avgDinner
                ),
            ],
        ];
    }

    /**
     * @param  list<array{from: string, to: string}>  $compareMonths
     * @return array<string, mixed>
     */
    private function weekdayAnalysis(string $periodFrom, string $periodToExclusive, array $compareMonths): array
    {
        $build = function (string $from, string $toExclusive) {
            $rows = DB::select("
                SELECT
                    CASE WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'weekend' ELSE 'weekday' END as day_type,
                    COUNT(*) as orders,
                    COALESCE(SUM(grand_total), 0) as revenue,
                    COALESCE(SUM(pax), 0) as pax
                FROM orders
                WHERE created_at >= ? AND created_at < ?
                  AND status != 'cancelled'
                  AND grand_total > 0
                GROUP BY CASE WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'weekend' ELSE 'weekday' END
            ", [$from, $toExclusive]);

            $out = [
                'weekday' => $this->emptyTotals(),
                'weekend' => $this->emptyTotals(),
            ];
            foreach ($rows as $row) {
                $out[$row->day_type] = $this->normalizeTotals((float) $row->revenue, (int) $row->orders, (int) $row->pax);
            }

            return $out;
        };

        $current = $build($periodFrom, $periodToExclusive);
        $priorsWd = [];
        $priorsWe = [];
        foreach ($compareMonths as $cm) {
            $from = Carbon::parse($cm['from'])->startOfDay()->toDateTimeString();
            $to = Carbon::parse($cm['to'])->addDay()->startOfDay()->toDateTimeString();
            $m = $build($from, $to);
            $priorsWd[] = $m['weekday'];
            $priorsWe[] = $m['weekend'];
        }
        $avgWd = $this->averageTotals($priorsWd);
        $avgWe = $this->averageTotals($priorsWe);

        return [
            'weekday' => [
                'current' => $current['weekday'],
                'avg_last_3' => $avgWd,
                'vs_avg_last_3' => $this->deltaBlock($current['weekday'], $avgWd),
            ],
            'weekend' => [
                'current' => $current['weekend'],
                'avg_last_3' => $avgWe,
                'vs_avg_last_3' => $this->deltaBlock($current['weekend'], $avgWe),
            ],
        ];
    }

    /**
     * @param  list<array{from: string, to: string}>  $compareMonths
     * @return array<string, mixed>
     */
    private function menuAnalysis(string $periodFrom, string $periodToExclusive, array $compareMonths): array
    {
        $currentItems = DB::select("
            SELECT
                oi.item_name,
                SUM(oi.qty) as qty,
                SUM(oi.subtotal) as revenue
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE o.created_at >= ? AND o.created_at < ?
              AND o.status != 'cancelled'
              AND o.grand_total > 0
            GROUP BY oi.item_name
            ORDER BY revenue DESC
            LIMIT 40
        ", [$periodFrom, $periodToExclusive]);

        // Average of last 3 months for same item set
        $priorMaps = [];
        foreach ($compareMonths as $cm) {
            $from = Carbon::parse($cm['from'])->startOfDay()->toDateTimeString();
            $to = Carbon::parse($cm['to'])->addDay()->startOfDay()->toDateTimeString();
            $rows = DB::select("
                SELECT
                    oi.item_name,
                    SUM(oi.qty) as qty,
                    SUM(oi.subtotal) as revenue
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE o.created_at >= ? AND o.created_at < ?
                  AND o.status != 'cancelled'
                  AND o.grand_total > 0
                GROUP BY oi.item_name
            ", [$from, $to]);
            $map = [];
            foreach ($rows as $row) {
                $map[$row->item_name] = [
                    'qty' => (float) $row->qty,
                    'revenue' => (float) $row->revenue,
                ];
            }
            $priorMaps[] = $map;
        }

        $decliners = [];
        $gainers = [];
        foreach ($currentItems as $item) {
            $name = $item->item_name;
            $curRev = (float) $item->revenue;
            $curQty = (float) $item->qty;
            $priorRevs = [];
            $priorQtys = [];
            foreach ($priorMaps as $map) {
                $priorRevs[] = $map[$name]['revenue'] ?? 0;
                $priorQtys[] = $map[$name]['qty'] ?? 0;
            }
            $avgRev = array_sum($priorRevs) / max(1, count($priorRevs));
            $avgQty = array_sum($priorQtys) / max(1, count($priorQtys));
            $delta = $curRev - $avgRev;
            $pct = $avgRev > 0 ? round(($delta / $avgRev) * 100, 1) : null;

            $row = [
                'item_name' => $name,
                'current_revenue' => round($curRev, 2),
                'current_qty' => round($curQty, 1),
                'avg_last_3_revenue' => round($avgRev, 2),
                'avg_last_3_qty' => round($avgQty, 1),
                'revenue_delta' => round($delta, 2),
                'revenue_pct' => $pct,
                'qty_delta' => round($curQty - $avgQty, 1),
            ];

            if ($delta < 0) {
                $decliners[] = $row;
            } else {
                $gainers[] = $row;
            }
        }

        usort($decliners, fn ($a, $b) => $a['revenue_delta'] <=> $b['revenue_delta']);
        usort($gainers, fn ($a, $b) => $b['revenue_delta'] <=> $a['revenue_delta']);

        return [
            'top_decliners' => array_slice($decliners, 0, 10),
            'top_gainers' => array_slice($gainers, 0, 10),
        ];
    }

    /**
     * @param  array<string, mixed>  $driver
     * @param  array<string, mixed>  $vsAvg3
     * @param  array<string, mixed>  $regions
     * @param  array<string, mixed>  $outlets
     * @param  array<string, mixed>  $daypart
     * @param  array<string, mixed>  $menu
     * @return list<array{severity: string, severity: string, category: string}>
     */
    /**
     * @param  array<string, mixed>  $driver
     * @param  array<string, mixed>  $vsAvg3
     * @param  array<string, mixed>  $regions
     * @param  array<string, mixed>  $outlets
     * @param  array<string, mixed>  $daypart
     * @param  array<string, mixed>  $menu
     * @return list<array{headline: string, severity: string, category: string, detail: string}>
     */
    private function buildFindings(
        array $driver,
        array $vsAvg3,
        array $regions,
        array $outlets,
        array $daypart,
        array $menu
    ): array {
        return $this->composeFindings($driver, $vsAvg3, $regions, $outlets, $daypart, $menu);
    }

    private function composeFindings(
        array $driver,
        array $vsAvg3,
        array $regions,
        array $outlets,
        array $daypart,
        array $menu
    ): array {
        $findings = [];

        $severity = $driver['direction'] === 'down' ? 'critical' : ($driver['direction'] === 'up' ? 'positive' : 'info');
        $findings[] = [
            'category' => 'driver',
            'severity' => $severity,
            'headline' => $driver['label'],
            'detail' => $driver['headline']
                . ' Kontribusi Pax ~' . $driver['pax_share_pct'] . '%, Avg Check ~' . $driver['avg_check_share_pct'] . '%.',
        ];

        if (! empty($regions['worst'][0]) && ($regions['worst'][0]['vs_avg_last_3']['revenue_pct'] ?? 0) < -3) {
            $w = $regions['worst'][0];
            $findings[] = [
                'category' => 'region',
                'severity' => 'warning',
                'headline' => 'Region terlemah: ' . $w['region_name'],
                'detail' => sprintf(
                    'Omzet %s%% vs rata-rata 3 bulan (Pax %s%%, Avg Check %s%%). Driver: %s.',
                    $this->fmtPct($w['vs_avg_last_3']['revenue_pct']),
                    $this->fmtPct($w['vs_avg_last_3']['pax_pct']),
                    $this->fmtPct($w['vs_avg_last_3']['avg_check_pct']),
                    $w['driver']['primary'] === 'pax' ? 'Pax' : ($w['driver']['primary'] === 'avg_check' ? 'Avg Check' : 'campuran')
                ),
            ];
        }

        if (! empty($outlets['worst'][0]) && ($outlets['worst'][0]['vs_avg_last_3']['revenue_delta'] ?? 0) < 0) {
            $w = $outlets['worst'][0];
            $findings[] = [
                'category' => 'outlet',
                'severity' => 'warning',
                'headline' => 'Outlet paling drop: ' . $w['outlet_name'],
                'detail' => sprintf(
                    '%s · Δ omzet %s (Pax %s%%, Avg Check %s%%).',
                    $w['region_name'],
                    number_format($w['vs_avg_last_3']['revenue_delta'], 0, ',', '.'),
                    $this->fmtPct($w['vs_avg_last_3']['pax_pct']),
                    $this->fmtPct($w['vs_avg_last_3']['avg_check_pct'])
                ),
            ];
        }

        foreach (['lunch' => 'Lunch', 'dinner' => 'Dinner'] as $key => $label) {
            $pct = $daypart[$key]['vs_avg_last_3']['revenue_pct'] ?? null;
            if ($pct !== null && $pct <= -5) {
                $d = $daypart[$key]['driver'];
                $findings[] = [
                    'category' => 'daypart',
                    'severity' => 'warning',
                    'headline' => "{$label} melemah {$this->fmtPct($pct)}%",
                    'detail' => $d['label'] ?? '',
                ];
            }
        }

        if (! empty($menu['top_decliners'][0])) {
            $m = $menu['top_decliners'][0];
            $findings[] = [
                'category' => 'menu',
                'severity' => 'info',
                'headline' => 'Menu paling turun: ' . $m['item_name'],
                'detail' => sprintf(
                    'Revenue vs rata-rata 3 bulan: %s (%s). Qty Δ %s.',
                    number_format($m['revenue_delta'], 0, ',', '.'),
                    $m['revenue_pct'] === null ? 'n/a' : $this->fmtPct($m['revenue_pct']) . '%',
                    number_format($m['qty_delta'], 0, ',', '.')
                ),
            ];
        }

        return $findings;
    }

    private function fmtPct(?float $v): string
    {
        if ($v === null) {
            return 'n/a';
        }

        return ($v >= 0 ? '+' : '') . $v;
    }

    /**
     * @param  array<string, mixed>  $driver
     * @param  array<string, mixed>  $vsAvg3
     * @param  list<array<string, mixed>>  $findings
     * @param  array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}  $current
     * @param  array{revenue: float, orders: int, pax: int, avg_check: float, aov: float}  $avg3
     */
    private function buildNarrative(array $driver, array $vsAvg3, array $findings, array $current, array $avg3): string
    {
        $lines = [];
        $lines[] = $driver['label'] . '.';
        $lines[] = sprintf(
            'Periode ini omzet Rp %s (Pax %s, Avg Check Rp %s) vs rata-rata 3 bulan Rp %s (Pax %s, Avg Check Rp %s).',
            number_format($current['revenue'], 0, ',', '.'),
            number_format($current['pax'], 0, ',', '.'),
            number_format($current['avg_check'], 0, ',', '.'),
            number_format($avg3['revenue'], 0, ',', '.'),
            number_format($avg3['pax'], 0, ',', '.'),
            number_format($avg3['avg_check'], 0, ',', '.')
        );
        $lines[] = sprintf(
            'Atribusi perubahan omzet: Pax menyumbang ~%s%%, Average Check ~%s%%.',
            $driver['pax_share_pct'],
            $driver['avg_check_share_pct']
        );

        foreach (array_slice($findings, 1, 4) as $f) {
            $lines[] = '• ' . $f['headline'] . ($f['detail'] ? ' — ' . $f['detail'] : '');
        }

        if ($driver['direction'] === 'down' && $driver['primary'] === 'pax') {
            $lines[] = 'Rekomendasi fokus traffic: promo acquisition, coverage jam sibuk, dan program repeat guest.';
        } elseif ($driver['direction'] === 'down' && $driver['primary'] === 'avg_check') {
            $lines[] = 'Rekomendasi fokus average check: bundling, upselling signature, dan review discount depth.';
        } elseif ($driver['direction'] === 'down') {
            $lines[] = 'Rekomendasi: perbaiki traffic dan belanja per tamu secara paralel di region/outlet terlemah.';
        }

        return implode("\n", $lines);
    }
}
