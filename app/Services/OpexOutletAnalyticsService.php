<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fast auto diagnostics for Outlet Opex Dashboard.
 * One batched pass per metric across current + 3 compare windows (datetime ranges, no whereDate).
 */
class OpexOutletAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function build(int $outletId, string $dateFrom, string $dateTo): array
    {
        $periodStart = Carbon::parse($dateFrom)->startOfDay();
        $periodEnd = Carbon::parse($dateTo)->startOfDay();
        $monthKey = $periodStart->format('Y-m');
        $endDay = $periodEnd->day;

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['qr_code', 'nama_outlet']);
        $qrCode = trim((string) ($outlet?->qr_code ?? ''));

        $windows = [
            [
                'key' => 'current',
                'label' => $periodStart->locale('id')->translatedFormat('F Y'),
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ];
        for ($i = 1; $i <= 3; $i++) {
            $m = $periodStart->copy()->subMonthsNoOverflow($i);
            $toDay = min($endDay, $m->daysInMonth);
            $windows[] = [
                'key' => $m->format('Y-m'),
                'label' => $m->locale('id')->translatedFormat('F Y'),
                'from' => $m->copy()->startOfMonth()->toDateString(),
                'to' => $m->copy()->startOfMonth()->day($toDay)->toDateString(),
            ];
        }

        $byKey = $this->batchSnapshots($outletId, $qrCode, $windows);
        $current = $byKey['current'] ?? $this->emptySnapshot($outlet?->nama_outlet);

        $priorMonths = [];
        foreach ($windows as $w) {
            if ($w['key'] === 'current') {
                continue;
            }
            $row = $byKey[$w['key']] ?? $this->emptySnapshot($outlet?->nama_outlet);
            $priorMonths[] = array_merge($w, $row, [
                'vs_current' => $this->deltaBlock($current, $row),
            ]);
        }

        $keys = [
            'revenue', 'cover', 'avg_check', 'discount', 'discount_ratio_percent',
            'gsr_ro', 'retail_food', 'retail_non_food', 'petty_cash',
            'total_spend', 'spend_ratio_percent', 'net',
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
                'outlet_name' => $outlet?->nama_outlet,
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
     * @param  list<array{key: string, label: string, from: string, to: string}>  $windows
     * @return array<string, array<string, mixed>>
     */
    private function batchSnapshots(int $outletId, string $qrCode, array $windows): array
    {
        $revenue = $this->batchRevenue($qrCode, $windows);
        $gsr = $this->batchGsrRo($outletId, $windows);
        $rf = $this->batchRetail('retail_food', $outletId, $windows);
        $rnf = $this->batchRetail('retail_non_food', $outletId, $windows);

        $out = [];
        foreach ($windows as $w) {
            $key = $w['key'];
            $rev = $revenue[$key] ?? ['total' => 0.0, 'cover' => 0.0, 'discount' => 0.0, 'gross' => 0.0, 'count' => 0];
            $gsrTotal = (float) ($gsr[$key] ?? 0);
            $rfRow = $rf[$key] ?? ['total' => 0.0, 'cash' => 0.0];
            $rnfRow = $rnf[$key] ?? ['total' => 0.0, 'cash' => 0.0];
            $totalSpend = round($gsrTotal + (float) $rfRow['total'] + (float) $rnfRow['total'], 2);
            $revenueTotal = (float) $rev['total'];
            $cover = (float) $rev['cover'];
            $discount = (float) $rev['discount'];
            $gross = (float) $rev['gross'];
            $count = (int) $rev['count'];
            $petty = round((float) $rfRow['cash'] + (float) $rnfRow['cash'], 2);

            $out[$key] = [
                'outlet_name' => null,
                'revenue' => round($revenueTotal, 2),
                'cover' => (int) $cover,
                'avg_pax' => $count > 0 ? round($cover / $count, 2) : null,
                'avg_check' => $cover > 0 ? round($revenueTotal / $cover) : null,
                'discount' => round($discount, 2),
                'discount_ratio_percent' => $gross > 0 ? round(($discount / $gross) * 100, 2) : null,
                'gsr_ro' => round($gsrTotal, 2),
                'retail_food' => round((float) $rfRow['total'], 2),
                'retail_non_food' => round((float) $rnfRow['total'], 2),
                'petty_cash' => $petty,
                'total_spend' => $totalSpend,
                'spend_ratio_percent' => $revenueTotal > 0 ? round(($totalSpend / $revenueTotal) * 100, 2) : null,
                'net' => round($revenueTotal - $totalSpend, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{key: string, from: string, to: string}>  $windows
     * @return array<string, array{total: float, cover: float, discount: float, gross: float, count: int}>
     */
    private function batchRevenue(string $qrCode, array $windows): array
    {
        $empty = [];
        foreach ($windows as $w) {
            $empty[$w['key']] = ['total' => 0.0, 'cover' => 0.0, 'discount' => 0.0, 'gross' => 0.0, 'count' => 0];
        }
        if ($qrCode === '') {
            return $empty;
        }

        [$selects, $bindings, $histFrom, $histToEx] = $this->windowCaseSelects(
            $windows,
            'created_at',
            true,
            [
                'total' => 'grand_total',
                'cover' => 'pax',
                'discount' => '(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0))',
                'gross' => 'COALESCE(total, 0)',
                'count' => '1',
            ]
        );

        $row = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->where('created_at', '>=', $histFrom)
            ->where('created_at', '<', $histToEx)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw(implode(",\n", $selects), $bindings)
            ->first();

        return $this->mapWindowAgg($windows, $row, ['total', 'cover', 'discount', 'gross', 'count']);
    }

    /**
     * @param  list<array{key: string, from: string, to: string}>  $windows
     * @return array<string, float>
     */
    private function batchGsrRo(int $outletId, array $windows): array
    {
        $out = [];
        foreach ($windows as $w) {
            $out[$w['key']] = 0.0;
        }

        // GR: join berat tapi 1x untuk semua window (range datetime/date tanpa whereDate).
        [$grSelects, $grBindings, $grFrom, $grTo] = $this->windowCaseSelects(
            $windows,
            'ofgr.receive_date',
            false,
            ['total' => 'ofgri.received_qty * COALESCE(ffoi_do.price, ffoi_ro.price, 0)']
        );

        $grRow = DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_order_items as ffoi_do', function ($join) {
                $join->on('ofgri.item_id', '=', 'ffoi_do.item_id')
                    ->whereColumn('ffoi_do.floor_order_id', 'do.floor_order_id');
            })
            ->leftJoin('food_floor_order_items as ffoi_ro', function ($join) {
                $join->on('ofgri.item_id', '=', 'ffoi_ro.item_id')
                    ->whereColumn('ffoi_ro.floor_order_id', 'po.source_id');
            })
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->where('ofgr.receive_date', '>=', $grFrom)
            ->where('ofgr.receive_date', '<=', $grTo)
            ->selectRaw(implode(",\n", $grSelects), $grBindings)
            ->first();

        foreach ($windows as $i => $w) {
            $out[$w['key']] += (float) ($grRow->{'total_'.$i} ?? 0);
        }

        if (Schema::hasTable('outlet_serial_receive_headers') && Schema::hasTable('outlet_serial_receive_items')) {
            // Same unit conversion as OpexOutletDashboardService::serialGrPriceSql
            $priceSql = '(CASE
                WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1) * COALESCE(it.medium_conversion_qty, 1)
                WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
                ELSE COALESCE(si.cost_small, 0)
            END)';

            [$gsrSelects, $gsrBindings, $gsrFrom, $gsrTo] = $this->windowCaseSelects(
                $windows,
                'h.receive_date',
                false,
                ['total' => "si.qty * ({$priceSql})"]
            );

            $gsrRow = DB::table('outlet_serial_receive_items as si')
                ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->where('h.receive_date', '>=', $gsrFrom)
                ->where('h.receive_date', '<=', $gsrTo)
                ->selectRaw(implode(",\n", $gsrSelects), $gsrBindings)
                ->first();

            foreach ($windows as $i => $w) {
                $out[$w['key']] += (float) ($gsrRow->{'total_'.$i} ?? 0);
            }
        }

        foreach ($out as $k => $v) {
            $out[$k] = round($v, 2);
        }

        return $out;
    }

    /**
     * @param  list<array{key: string, from: string, to: string}>  $windows
     * @return array<string, array{total: float, cash: float}>
     */
    private function batchRetail(string $table, int $outletId, array $windows): array
    {
        $empty = [];
        foreach ($windows as $w) {
            $empty[$w['key']] = ['total' => 0.0, 'cash' => 0.0];
        }
        if (! Schema::hasTable($table)) {
            return $empty;
        }

        [$selects, $bindings, $from, $to] = $this->windowCaseSelects(
            $windows,
            'transaction_date',
            false,
            [
                'total' => 'total_amount',
                'cash' => "CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END",
            ]
        );

        $row = DB::table($table)
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->where('transaction_date', '>=', $from)
            ->where('transaction_date', '<=', $to)
            ->selectRaw(implode(",\n", $selects), $bindings)
            ->first();

        $mapped = $this->mapWindowAgg($windows, $row, ['total', 'cash']);
        $out = [];
        foreach ($windows as $w) {
            $out[$w['key']] = [
                'total' => (float) ($mapped[$w['key']]['total'] ?? 0),
                'cash' => (float) ($mapped[$w['key']]['cash'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Build CASE WHEN aggregates for each window.
     *
     * @param  list<array{key: string, from: string, to: string}>  $windows
     * @param  array<string, string>  $metrics  alias => SQL expression
     * @return array{0: list<string>, 1: list<mixed>, 2: string, 3: string}
     */
    private function windowCaseSelects(array $windows, string $dateColumn, bool $isDateTime, array $metrics): array
    {
        $selects = [];
        $bindings = [];
        $histFrom = null;
        $histTo = null;
        $histToEx = null;

        foreach ($windows as $i => $w) {
            if ($isDateTime) {
                $from = $w['from'].' 00:00:00';
                $toEx = Carbon::parse($w['to'])->addDay()->format('Y-m-d').' 00:00:00';
                $cond = "{$dateColumn} >= ? AND {$dateColumn} < ?";
                foreach ($metrics as $alias => $expr) {
                    $selects[] = "COALESCE(SUM(CASE WHEN {$cond} THEN {$expr} ELSE 0 END), 0) as {$alias}_{$i}";
                    $bindings[] = $from;
                    $bindings[] = $toEx;
                }
                $histFrom = $histFrom === null || $from < $histFrom ? $from : $histFrom;
                $histToEx = $histToEx === null || $toEx > $histToEx ? $toEx : $histToEx;
            } else {
                $from = $w['from'];
                $to = $w['to'];
                $cond = "{$dateColumn} >= ? AND {$dateColumn} <= ?";
                foreach ($metrics as $alias => $expr) {
                    $selects[] = "COALESCE(SUM(CASE WHEN {$cond} THEN {$expr} ELSE 0 END), 0) as {$alias}_{$i}";
                    $bindings[] = $from;
                    $bindings[] = $to;
                }
                $histFrom = $histFrom === null || $from < $histFrom ? $from : $histFrom;
                $histTo = $histTo === null || $to > $histTo ? $to : $histTo;
            }
        }

        return [$selects, $bindings, $histFrom, $isDateTime ? $histToEx : $histTo];
    }

    /**
     * @param  list<array{key: string}>  $windows
     * @param  list<string>  $aliases
     * @return array<string, array<string, float>>
     */
    private function mapWindowAgg(array $windows, ?object $row, array $aliases): array
    {
        $out = [];
        foreach ($windows as $i => $w) {
            $item = [];
            foreach ($aliases as $alias) {
                $item[$alias] = round((float) ($row?->{"{$alias}_{$i}"} ?? 0), 2);
            }
            $out[$w['key']] = $item;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySnapshot(?string $outletName = null): array
    {
        return [
            'outlet_name' => $outletName,
            'revenue' => 0.0,
            'cover' => 0,
            'avg_pax' => null,
            'avg_check' => null,
            'discount' => 0.0,
            'discount_ratio_percent' => null,
            'gsr_ro' => 0.0,
            'retail_food' => 0.0,
            'retail_non_food' => 0.0,
            'petty_cash' => 0.0,
            'total_spend' => 0.0,
            'spend_ratio_percent' => null,
            'net' => 0.0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return array<string, float|null>
     */
    private function averageTotals(array $rows, array $keys): array
    {
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
            'gsr_ro', 'retail_food', 'retail_non_food', 'petty_cash',
            'total_spend', 'spend_ratio_percent', 'net',
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
            'headline' => $this->headlineText($primary, $delta),
        ];
    }

    /**
     * @param  array<string, mixed>  $delta
     */
    private function headlineText(string $primary, array $delta): string
    {
        $fmt = static function (?float $v): string {
            if ($v === null) {
                return 'n/a';
            }

            return ($v >= 0 ? '+' : '').$v.'%';
        };

        $ratio = $delta['spend_ratio_percent'] ?? null;

        return 'Vs rata-rata 3 bulan (span hari sama): Net '.$fmt($delta['net_pct'] ?? null)
            .', Revenue '.$fmt($delta['revenue_pct'] ?? null)
            .', Spend '.$fmt($delta['total_spend_pct'] ?? null)
            .', Spend ratio '.($ratio === null ? 'n/a' : (($ratio >= 0 ? '+' : '').$ratio.' pp'))
            .'. Driver utama: '
            .($primary === 'revenue' ? 'Revenue' : ($primary === 'spend' ? 'Spend' : 'campuran keduanya'))
            .'.';
    }

    /**
     * @param  array<string, mixed>  $delta
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $base
     * @return array{items: list<array<string, mixed>>, top_driver: string|null, top_label: string|null}
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

        foreach (array_slice($findings, 1, 3) as $f) {
            $lines[] = '• '.$f['headline'].($f['detail'] ? ' — '.$f['detail'] : '');
        }

        return implode("\n", $lines);
    }
}
