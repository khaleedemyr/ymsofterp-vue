<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Auto diagnostics for Outlet Opex Dashboard.
 * Explains net / spend movement vs same day-span average of last 3 months.
 */
class OpexOutletAnalyticsService
{
    public function __construct(
        private OpexOutletDashboardService $opex
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $outletId, string $dateFrom, string $dateTo): array
    {
        $periodStart = Carbon::parse($dateFrom)->startOfDay();
        $periodEnd = Carbon::parse($dateTo)->startOfDay();
        $monthKey = $periodStart->format('Y-m');
        $endDay = $periodEnd->day;

        $compareMonths = [];
        for ($i = 1; $i <= 3; $i++) {
            $m = $periodStart->copy()->subMonthsNoOverflow($i);
            $from = $m->copy()->startOfMonth();
            $toDay = min($endDay, $m->daysInMonth);
            $to = $m->copy()->startOfMonth()->day($toDay);
            $compareMonths[] = [
                'key' => $m->format('Y-m'),
                'label' => $m->locale('id')->translatedFormat('F Y'),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ];
        }

        $current = $this->opex->buildAnalyticsSnapshot($outletId, $dateFrom, $dateTo);

        $priorMonths = [];
        foreach ($compareMonths as $cm) {
            $row = $this->opex->buildAnalyticsSnapshot($outletId, $cm['from'], $cm['to']);
            $priorMonths[] = array_merge($cm, $row, [
                'vs_current' => $this->deltaBlock($current, $row),
            ]);
        }

        $keys = [
            'revenue', 'cover', 'avg_check', 'discount', 'discount_ratio_percent',
            'gsr_ro', 'retail_food', 'retail_non_food', 'petty_cash', 'mcs_purchase',
            'stock_cut', 'category_cost', 'total_spend', 'spend_ratio_percent', 'net',
        ];
        $avg3 = $this->averageTotals(array_map(
            fn ($p) => array_intersect_key($p, array_flip($keys)),
            $priorMonths
        ), $keys);

        $vsAvg3 = $this->deltaBlock($current, $avg3);
        $driver = $this->diagnoseDriver($vsAvg3, $current, $avg3);
        $spendMix = $this->spendMixDrivers($vsAvg3, $current, $avg3);
        $findings = $this->composeFindings($driver, $vsAvg3, $spendMix, $current, $avg3);

        return [
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'month' => $monthKey,
                'label' => $periodStart->locale('id')->translatedFormat('F Y'),
                'outlet_name' => $current['outlet_name'] ?? null,
                'compare_day_span' => $periodStart->day.'–'.$endDay,
            ],
            'current' => $current,
            'compare_months' => $priorMonths,
            'avg_last_3_months' => $avg3,
            'vs_avg_last_3' => $vsAvg3,
            'driver' => $driver,
            'spend_mix' => $spendMix,
            'findings' => $findings,
            'narrative' => $this->buildNarrative($driver, $vsAvg3, $spendMix, $findings, $current, $avg3),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return array<string, float|null>
     */
    private function averageTotals(array $rows, array $keys): array
    {
        $n = max(1, count($rows));
        $out = [];
        foreach ($keys as $key) {
            $sum = 0.0;
            $count = 0;
            foreach ($rows as $row) {
                if (! array_key_exists($key, $row) || $row[$key] === null) {
                    continue;
                }
                $sum += (float) $row[$key];
                $count++;
            }
            $out[$key] = $count > 0 ? round($sum / $count, 2) : null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function deltaBlock(array $current, array $base): array
    {
        $keys = [
            'revenue', 'cover', 'avg_check', 'discount', 'discount_ratio_percent',
            'gsr_ro', 'retail_food', 'retail_non_food', 'petty_cash', 'mcs_purchase',
            'stock_cut', 'category_cost', 'total_spend', 'spend_ratio_percent', 'net',
        ];
        $out = [];
        foreach ($keys as $key) {
            $c = isset($current[$key]) && $current[$key] !== null ? (float) $current[$key] : null;
            $b = isset($base[$key]) && $base[$key] !== null ? (float) $base[$key] : null;
            $diff = ($c !== null && $b !== null) ? round($c - $b, 2) : null;
            $out[$key] = $diff;
            $out[$key.'_pct'] = $this->pctChange($c, $b);
        }

        return $out;
    }

    private function pctChange(?float $current, ?float $base): ?float
    {
        if ($current === null || $base === null) {
            return null;
        }
        if ($base == 0.0) {
            return $current == 0.0 ? 0.0 : null;
        }

        return round((($current - $base) / abs($base)) * 100, 1);
    }

    /**
     * Net ≈ Revenue − Spend → attribute net movement to revenue vs spend.
     *
     * @param  array<string, mixed>  $delta
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function diagnoseDriver(array $delta, array $current, array $base): array
    {
        $netPct = $delta['net_pct'] ?? null;
        $direction = 'flat';
        if ($netPct !== null) {
            if ($netPct < -1) {
                $direction = 'down';
            } elseif ($netPct > 1) {
                $direction = 'up';
            }
        }

        $revDelta = (float) ($delta['revenue'] ?? 0);
        $spendDelta = (float) ($delta['total_spend'] ?? 0);
        // Higher spend hurts net → spend effect on net is -spendDelta
        $revEffect = $revDelta;
        $spendEffect = -$spendDelta;
        $absTotal = abs($revEffect) + abs($spendEffect);
        $revShare = $absTotal > 0 ? round((abs($revEffect) / $absTotal) * 100, 1) : 50.0;
        $spendShare = round(100 - $revShare, 1);

        $primary = 'mixed';
        if ($revShare >= 60) {
            $primary = 'revenue';
        } elseif ($spendShare >= 60) {
            $primary = 'spend';
        }

        $revDown = ($delta['revenue_pct'] ?? 0) < -1;
        $spendUp = ($delta['total_spend_pct'] ?? 0) > 1;
        $revUp = ($delta['revenue_pct'] ?? 0) > 1;
        $spendDown = ($delta['total_spend_pct'] ?? 0) < -1;

        $label = match (true) {
            $direction === 'down' && $revDown && $spendUp => 'Net turun: Revenue melemah sekaligus Spend naik',
            $direction === 'down' && $primary === 'revenue' => 'Net turun terutama karena Revenue turun',
            $direction === 'down' && $primary === 'spend' => 'Net turun terutama karena Spend naik',
            $direction === 'up' && $revUp && $spendDown => 'Net naik didukung Revenue naik dan Spend turun',
            $direction === 'up' && $primary === 'revenue' => 'Net naik terutama karena Revenue naik',
            $direction === 'up' && $primary === 'spend' => 'Net naik terutama karena Spend lebih terkendali',
            $direction === 'down' && $revDown && $spendDown => 'Net turun: Revenue turun meski Spend juga turun (tidak cukup kompensasi)',
            $direction === 'down' && $revUp && $spendUp => 'Net turun: Spend naik lebih cepat dari Revenue',
            default => 'Pergerakan net relatif stabil / campuran',
        };

        return [
            'direction' => $direction,
            'primary' => $primary,
            'label' => $label,
            'revenue_effect' => round($revEffect, 2),
            'spend_effect' => round($spendEffect, 2),
            'revenue_share_pct' => $revShare,
            'spend_share_pct' => $spendShare,
            'headline' => $this->headlineText($direction, $primary, $delta),
        ];
    }

    /**
     * @param  array<string, mixed>  $delta
     */
    private function headlineText(string $direction, string $primary, array $delta): string
    {
        $net = $delta['net_pct'];
        $rev = $delta['revenue_pct'];
        $spd = $delta['total_spend_pct'];
        $ratio = $delta['spend_ratio_percent'];

        $fmt = static function (?float $v): string {
            if ($v === null) {
                return 'n/a';
            }

            return ($v >= 0 ? '+' : '').$v.'%';
        };

        return 'Vs rata-rata 3 bulan (span hari sama): Net '.$fmt($net)
            .', Revenue '.$fmt($rev)
            .', Spend '.$fmt($spd)
            .', Spend ratio '.($ratio === null ? 'n/a' : (($ratio >= 0 ? '+' : '').$ratio.' pp'))
            .'. Driver utama: '
            .($primary === 'revenue' ? 'Revenue' : ($primary === 'spend' ? 'Spend' : 'campuran keduanya'))
            .'.';
    }

    /**
     * @param  array<string, mixed>  $delta
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $base
     * @return array{items: list<array<string, mixed>>, top_driver: string|null}
     */
    private function spendMixDrivers(array $delta, array $current, array $base): array
    {
        $lines = [
            'gsr_ro' => 'GSR/RO',
            'retail_food' => 'Retail Food',
            'retail_non_food' => 'Retail Non Food',
        ];
        $items = [];
        $absSum = 0.0;
        foreach ($lines as $key => $label) {
            $d = (float) ($delta[$key] ?? 0);
            $absSum += abs($d);
            $items[] = [
                'key' => $key,
                'label' => $label,
                'current' => (float) ($current[$key] ?? 0),
                'avg' => (float) ($base[$key] ?? 0),
                'delta' => round($d, 2),
                'pct' => $delta[$key.'_pct'] ?? null,
            ];
        }

        foreach ($items as &$item) {
            $item['share_of_spend_change_pct'] = $absSum > 0
                ? round((abs($item['delta']) / $absSum) * 100, 1)
                : 0.0;
        }
        unset($item);

        usort($items, fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));

        return [
            'items' => $items,
            'top_driver' => $items[0]['key'] ?? null,
            'top_label' => $items[0]['label'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $driver
     * @param  array<string, mixed>  $vs
     * @param  array<string, mixed>  $spendMix
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $avg
     * @return list<array{headline: string, severity: string, category: string, detail: string}>
     */
    private function composeFindings(array $driver, array $vs, array $spendMix, array $current, array $avg): array
    {
        $findings = [];
        $severity = $driver['direction'] === 'down' ? 'critical' : ($driver['direction'] === 'up' ? 'positive' : 'info');
        $findings[] = [
            'category' => 'driver',
            'severity' => $severity,
            'headline' => $driver['label'],
            'detail' => $driver['headline']
                .' Kontribusi Revenue ~'.$driver['revenue_share_pct']
                .'%, Spend ~'.$driver['spend_share_pct'].'%.',
        ];

        $top = $spendMix['items'][0] ?? null;
        if ($top && abs((float) $top['delta']) > 0 && ($top['pct'] ?? 0) != 0) {
            $findings[] = [
                'category' => 'spend_mix',
                'severity' => ($top['delta'] > 0 ? 'warning' : 'positive'),
                'headline' => 'Komponen spend terkuat: '.$top['label'],
                'detail' => sprintf(
                    '%s %s%% vs rata-rata 3 bulan (Δ %s). Share perubahan spend ~%s%%.',
                    $top['label'],
                    $this->fmtPct($top['pct']),
                    number_format((float) $top['delta'], 0, ',', '.'),
                    $top['share_of_spend_change_pct']
                ),
            ];
        }

        $ratioPct = $vs['spend_ratio_percent'] ?? null;
        if ($ratioPct !== null && abs((float) $ratioPct) >= 1) {
            $findings[] = [
                'category' => 'spend_ratio',
                'severity' => $ratioPct > 0 ? 'warning' : 'positive',
                'headline' => $ratioPct > 0
                    ? 'Spend ratio naik '.$this->fmtPct($ratioPct).' pp'
                    : 'Spend ratio membaik '.$this->fmtPct($ratioPct).' pp',
                'detail' => sprintf(
                    'Spend ratio sekarang %s%% vs rata-rata %s%%.',
                    $current['spend_ratio_percent'] ?? 'n/a',
                    $avg['spend_ratio_percent'] ?? 'n/a'
                ),
            ];
        }

        $discPct = $vs['discount_pct'] ?? null;
        if ($discPct !== null && (float) $discPct > 5) {
            $findings[] = [
                'category' => 'discount',
                'severity' => 'warning',
                'headline' => 'Diskon naik '.$this->fmtPct($discPct).'%',
                'detail' => sprintf(
                    'Diskon periode %s vs rata-rata %s (ratio %s%%).',
                    number_format((float) ($current['discount'] ?? 0), 0, ',', '.'),
                    number_format((float) ($avg['discount'] ?? 0), 0, ',', '.'),
                    $current['discount_ratio_percent'] ?? 'n/a'
                ),
            ];
        }

        $checkPct = $vs['avg_check_pct'] ?? null;
        $coverPct = $vs['cover_pct'] ?? null;
        if ($checkPct !== null || $coverPct !== null) {
            $findings[] = [
                'category' => 'traffic',
                'severity' => 'info',
                'headline' => 'Traffic & check: Cover '.$this->fmtPct($coverPct).' · Avg Check '.$this->fmtPct($checkPct),
                'detail' => 'Perubahan revenue bisa diurai ke Cover (pax) dan Average Check.',
            ];
        }

        $catPct = $vs['category_cost_pct'] ?? null;
        if ($catPct !== null && (float) $catPct > 10) {
            $findings[] = [
                'category' => 'category_cost',
                'severity' => 'warning',
                'headline' => 'Category Cost naik '.$this->fmtPct($catPct).'%',
                'detail' => 'Periksa spoil / waste / internal use vs rata-rata 3 bulan.',
            ];
        }

        return $findings;
    }

    private function fmtPct(?float $v): string
    {
        if ($v === null) {
            return 'n/a';
        }

        return ($v >= 0 ? '+' : '').$v;
    }

    /**
     * @param  array<string, mixed>  $driver
     * @param  array<string, mixed>  $vs
     * @param  array<string, mixed>  $spendMix
     * @param  list<array<string, mixed>>  $findings
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $avg
     */
    private function buildNarrative(
        array $driver,
        array $vs,
        array $spendMix,
        array $findings,
        array $current,
        array $avg
    ): string {
        $lines = [];
        $lines[] = $driver['label'].'.';
        $lines[] = sprintf(
            'Net periode %s vs rata-rata 3 bln %s (%s%%). Revenue %s%%, Spend %s%%, Spend ratio %s%% → %s%%.',
            number_format((float) ($current['net'] ?? 0), 0, ',', '.'),
            number_format((float) ($avg['net'] ?? 0), 0, ',', '.'),
            $this->fmtPct($vs['net_pct'] ?? null),
            $this->fmtPct($vs['revenue_pct'] ?? null),
            $this->fmtPct($vs['total_spend_pct'] ?? null),
            $avg['spend_ratio_percent'] ?? 'n/a',
            $current['spend_ratio_percent'] ?? 'n/a'
        );

        if (! empty($spendMix['top_label'])) {
            $top = $spendMix['items'][0];
            $lines[] = 'Perubahan spend paling kuat di '.$top['label']
                .' ('.$this->fmtPct($top['pct']).'%, share ~'.$top['share_of_spend_change_pct'].'%).';
        }

        $extra = array_slice($findings, 1, 3);
        foreach ($extra as $f) {
            $lines[] = '• '.$f['headline'].($f['detail'] ? ' — '.$f['detail'] : '');
        }

        return implode("\n", $lines);
    }
}
