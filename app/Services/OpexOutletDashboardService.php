<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Outlet spend & revenue summary for Opex Outlet Dashboard.
 * Sources: Revenue (orders), GSR/RO (food GR + serial GSR), RWS, Retail Food, Retail Non Food.
 * No PR / non-food payment.
 */
class OpexOutletDashboardService
{
    private const FB_BUDGET_RATIO = 0.40;

    private const SERVICE_BUDGET_RATIO = 0.05;

    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(?int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! $outletId) {
            return $this->emptyDashboard();
        }

        $meta = $this->buildSectionMeta($outletId);
        $overview = $this->buildSectionOverview($outletId, $dateFrom, $dateTo);
        $member = $this->buildSectionMember($outletId, $dateFrom, $dateTo);
        $charts = $this->buildSectionCharts($outletId, $dateFrom, $dateTo);
        $payments = $this->buildSectionPayments($outletId, $dateFrom, $dateTo);
        $ro = $this->buildSectionRoForecast($outletId, $dateFrom, $dateTo);

        return array_merge($meta, [
            'overview' => array_merge($overview['overview'] ?? [], $member['overview'] ?? []),
            'trend' => $charts['trend'] ?? [],
            'spend_mix' => $charts['spend_mix'] ?? [],
            'payment_methods' => $payments['payment_methods'] ?? [],
            'ro_forecast' => $ro['ro_forecast'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSection(string $section, int $outletId, string $dateFrom, string $dateTo): array
    {
        return match ($section) {
            'meta' => $this->buildSectionMeta($outletId),
            'overview' => $this->buildSectionOverview($outletId, $dateFrom, $dateTo),
            'member' => $this->buildSectionMember($outletId, $dateFrom, $dateTo),
            'ro_forecast' => $this->buildSectionRoForecast($outletId, $dateFrom, $dateTo),
            'payments' => $this->buildSectionPayments($outletId, $dateFrom, $dateTo),
            'charts' => $this->buildSectionCharts($outletId, $dateFrom, $dateTo),
            default => ['error' => 'Unknown section'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyDashboard(): array
    {
        return [
            'overview' => null,
            'trend' => [],
            'spend_mix' => [],
            'payment_methods' => [],
            'ro_forecast' => null,
            'outlet_name' => null,
        ];
    }

    /**
     * @return array{outlet_name: string|null}
     */
    public function buildSectionMeta(int $outletId): array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['nama_outlet']);

        return ['outlet_name' => $outlet?->nama_outlet];
    }

    /**
     * KPI utama tanpa CRM member (lebih cepat).
     *
     * @return array{overview: array<string, mixed>}
     */
    public function buildSectionOverview(int $outletId, string $dateFrom, string $dateTo): array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['qr_code', 'nama_outlet']);

        $current = $this->buildOverviewMetrics($outletId, $outlet?->qr_code, $dateFrom, $dateTo);
        [$prevFrom, $prevTo] = $this->previousMonthRange($dateFrom, $dateTo);
        $previous = $this->buildOverviewMetrics($outletId, $outlet?->qr_code, $prevFrom, $prevTo);

        $current['vs_last_month'] = [
            'period_from' => $prevFrom,
            'period_to' => $prevTo,
            'label' => Carbon::parse($prevFrom)->locale('id')->translatedFormat('M Y'),
            'revenue' => $this->vsMetric($current['revenue'], $previous['revenue']),
            'total_spend' => $this->vsMetric($current['total_spend'], $previous['total_spend']),
            'net' => $this->vsMetric($current['net'], $previous['net']),
            'cover' => $this->vsMetric($current['cover'], $previous['cover']),
            'avg_pax' => $this->vsMetric($current['avg_pax'], $previous['avg_pax']),
            'avg_check' => $this->vsMetric($current['avg_check'], $previous['avg_check']),
            'discount' => $this->vsMetric($current['discount'], $previous['discount']),
            'gsr_ro' => $this->vsMetric($current['gsr_ro'], $previous['gsr_ro']),
            'rws' => $this->vsMetric($current['rws'], $previous['rws']),
            'retail_food' => $this->vsMetric($current['retail_food'], $previous['retail_food']),
            'retail_non_food' => $this->vsMetric($current['retail_non_food'], $previous['retail_non_food']),
            'petty_cash' => $this->vsMetric($current['petty_cash'], $previous['petty_cash']),
        ];

        return ['overview' => $current];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOverviewMetrics(int $outletId, ?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $revenue = $this->sumRevenue($qrCode, $dateFrom, $dateTo);
        $gsrRo = $this->sumGsrRo($outletId, $dateFrom, $dateTo);
        $rws = $this->sumRws($outletId, $dateFrom, $dateTo);
        $rf = $this->sumRetailFood($outletId, $dateFrom, $dateTo);
        $rnf = $this->sumRetailNonFood($outletId, $dateFrom, $dateTo);

        $totalSpend = round($gsrRo['total'] + $rws['total'] + $rf['total'] + $rnf['total'], 2);
        $spendRatio = $revenue['total'] > 0 ? round(($totalSpend / $revenue['total']) * 100, 2) : null;
        $discountRatio = $revenue['gross_before_discount'] > 0
            ? round(($revenue['discount'] / $revenue['gross_before_discount']) * 100, 2)
            : null;

        $pettyCash = round($rf['cash_total'] + $rnf['cash_total'], 2);
        $pettyCashCount = $rf['cash_count'] + $rnf['cash_count'];
        $pctOfRevenue = static function (float $amount) use ($revenue): ?float {
            return $revenue['total'] > 0 ? round(($amount / $revenue['total']) * 100, 2) : null;
        };

        return [
            'revenue' => $revenue['total'],
            'revenue_count' => $revenue['count'],
            'cover' => $revenue['cover'],
            'avg_pax' => $revenue['avg_pax'],
            'avg_check' => $revenue['avg_check'],
            'discount' => $revenue['discount'],
            'discount_count' => $revenue['discount_count'],
            'discount_ratio_percent' => $discountRatio,
            'gsr_ro' => $gsrRo['total'],
            'gsr_ro_count' => $gsrRo['count'],
            'gsr_ro_gr' => $gsrRo['gr_total'],
            'gsr_ro_gsr' => $gsrRo['gsr_total'],
            'rws' => $rws['total'],
            'rws_count' => $rws['count'],
            'retail_food' => $rf['total'],
            'retail_food_count' => $rf['count'],
            'retail_food_cash_total' => $rf['cash_total'],
            'retail_food_cash_count' => $rf['cash_count'],
            'retail_food_contra_bon_total' => $rf['contra_bon_total'],
            'retail_food_contra_bon_count' => $rf['contra_bon_count'],
            'retail_food_revenue_pct' => $pctOfRevenue($rf['total']),
            'retail_non_food' => $rnf['total'],
            'retail_non_food_count' => $rnf['count'],
            'retail_non_food_cash_total' => $rnf['cash_total'],
            'retail_non_food_cash_count' => $rnf['cash_count'],
            'retail_non_food_contra_bon_total' => $rnf['contra_bon_total'],
            'retail_non_food_contra_bon_count' => $rnf['contra_bon_count'],
            'retail_non_food_revenue_pct' => $pctOfRevenue($rnf['total']),
            'petty_cash' => $pettyCash,
            'petty_cash_count' => $pettyCashCount,
            'petty_cash_rf' => $rf['cash_total'],
            'petty_cash_rnf' => $rnf['cash_total'],
            'petty_cash_revenue_pct' => $pctOfRevenue($pettyCash),
            'total_spend' => $totalSpend,
            'spend_ratio_percent' => $spendRatio,
            'net' => round($revenue['total'] - $totalSpend, 2),
        ];
    }

    /**
     * @return array{overview: array<string, mixed>}
     */
    public function buildSectionMember(int $outletId, string $dateFrom, string $dateTo): array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['qr_code', 'nama_outlet']);

        $member = $this->sumMemberActivity($outlet?->qr_code, $outlet?->nama_outlet, $dateFrom, $dateTo);
        [$prevFrom, $prevTo] = $this->previousMonthRange($dateFrom, $dateTo);
        $prevMember = $this->sumMemberActivity($outlet?->qr_code, $outlet?->nama_outlet, $prevFrom, $prevTo);

        return [
            'overview' => [
                'member_bills' => $member['member_bills'],
                'member_revenue' => $member['member_revenue'],
                'member_top_up' => $member['top_up_value'],
                'member_top_up_count' => $member['top_up_count'],
                'member_top_up_points' => $member['top_up_points'],
                'member_redeem' => $member['redeem_value'],
                'member_redeem_count' => $member['redeem_count'],
                'member_redeem_points' => $member['redeem_points'],
                'member_source' => $member['source'],
                'vs_last_month_member' => [
                    'period_from' => $prevFrom,
                    'period_to' => $prevTo,
                    'label' => Carbon::parse($prevFrom)->locale('id')->translatedFormat('M Y'),
                    'member_bills' => $this->vsMetric($member['member_bills'], $prevMember['member_bills']),
                    'member_revenue' => $this->vsMetric($member['member_revenue'], $prevMember['member_revenue']),
                    'member_top_up_points' => $this->vsMetric($member['top_up_points'], $prevMember['top_up_points']),
                    'member_redeem' => $this->vsMetric($member['redeem_value'], $prevMember['redeem_value']),
                ],
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function previousMonthRange(string $dateFrom, string $dateTo): array
    {
        return [
            Carbon::parse($dateFrom)->subMonthNoOverflow()->format('Y-m-d'),
            Carbon::parse($dateTo)->subMonthNoOverflow()->format('Y-m-d'),
        ];
    }

    /**
     * @return array{previous: float|null, diff: float|null, pct: float|null}
     */
    private function vsMetric(float|int|null $current, float|int|null $previous): array
    {
        $curr = $current === null ? null : (float) $current;
        $prev = $previous === null ? null : (float) $previous;
        if ($curr === null || $prev === null) {
            return ['previous' => $prev, 'diff' => null, 'pct' => null];
        }

        $diff = round($curr - $prev, 2);
        $pct = null;
        if ($prev != 0.0) {
            $pct = round(($diff / abs($prev)) * 100, 1);
        } elseif ($curr == 0.0) {
            $pct = 0.0;
        } else {
            $pct = 100.0;
        }

        return [
            'previous' => round($prev, 2),
            'diff' => $diff,
            'pct' => $pct,
        ];
    }

    /**
     * @return array{ro_forecast: array<string, mixed>}
     */
    public function buildSectionRoForecast(int $outletId, string $dateFrom, string $dateTo): array
    {
        return [
            'ro_forecast' => $this->buildRoForecastSummary($outletId, $dateFrom, $dateTo),
        ];
    }

    /**
     * @return array{payment_methods: list<array<string, mixed>>}
     */
    public function buildSectionPayments(int $outletId, string $dateFrom, string $dateTo): array
    {
        $qrCode = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('qr_code');

        return [
            'payment_methods' => $this->sumPaymentMethods($qrCode, $dateFrom, $dateTo),
        ];
    }

    /**
     * @return array{trend: list<array<string, mixed>>, spend_mix: list<array<string, mixed>>}
     */
    public function buildSectionCharts(int $outletId, string $dateFrom, string $dateTo): array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['qr_code']);

        $trend = $this->buildTrend($outletId, $outlet?->qr_code, $dateFrom, $dateTo);

        // Spend mix dari agregat trend (hindari query GSR/RWS/RF/RNF ulang).
        $mix = [
            'gsr_ro' => 0.0,
            'rws' => 0.0,
            'retail_food' => 0.0,
            'retail_non_food' => 0.0,
        ];
        foreach ($trend as $row) {
            $mix['gsr_ro'] += (float) ($row['gsr_ro'] ?? 0);
            $mix['rws'] += (float) ($row['rws'] ?? 0);
            $mix['retail_food'] += (float) ($row['retail_food'] ?? 0);
            $mix['retail_non_food'] += (float) ($row['retail_non_food'] ?? 0);
        }

        return [
            'trend' => $trend,
            'spend_mix' => [
                ['key' => 'gsr_ro', 'label' => 'GSR / RO', 'amount' => round($mix['gsr_ro'], 2)],
                ['key' => 'rws', 'label' => 'RWS', 'amount' => round($mix['rws'], 2)],
                ['key' => 'retail_food', 'label' => 'Retail Food', 'amount' => round($mix['retail_food'], 2)],
                ['key' => 'retail_non_food', 'label' => 'Retail Non Food', 'amount' => round($mix['retail_non_food'], 2)],
            ],
        ];
    }

    /**
     * Ringkas kolom RO Forecast (Floor Order vs Forecast):
     * Forecast, F&B Purchase (budget 40%), Service Purchase (budget 5%), sisa budget.
     * Selalu dihitung full calendar month (bukan MTD dari filter tanggal).
     *
     * @return array<string, mixed>
     */
    public function buildRoForecastSummary(int $outletId, string $dateFrom, string $dateTo): array
    {
        [$monthFrom, $monthTo] = $this->fullMonthBounds($dateFrom, $dateTo);

        $forecastTotal = $this->sumForecastRevenue($outletId, $monthFrom, $monthTo);
        $purchased = $this->sumRoPurchasedByBucket($outletId, $monthFrom, $monthTo);

        $fbBudget = round($forecastTotal * self::FB_BUDGET_RATIO, 2);
        $svcBudget = round($forecastTotal * self::SERVICE_BUDGET_RATIO, 2);
        $fbPurchased = $purchased['kitchen_bar'];
        $svcPurchased = $purchased['service'];

        $fbRemaining = round($fbBudget - $fbPurchased, 2);
        $svcRemaining = round($svcBudget - $svcPurchased, 2);
        $fbPct = $fbBudget > 0 ? round(($fbPurchased / $fbBudget) * 100, 1) : null;
        $svcPct = $svcBudget > 0 ? round(($svcPurchased / $svcBudget) * 100, 1) : null;

        return [
            'has_forecast' => $forecastTotal > 0 || $this->hasForecastHeaderForRange($outletId, $monthFrom, $monthTo),
            'period_from' => $monthFrom,
            'period_to' => $monthTo,
            'is_full_month' => true,
            'forecast' => $forecastTotal,
            'fb' => [
                'budget_ratio_pct' => (int) round(self::FB_BUDGET_RATIO * 100),
                'budget' => $fbBudget,
                'purchased' => $fbPurchased,
                'remaining' => $fbRemaining,
                'variance' => round($fbPurchased - $fbBudget, 2),
                'pct' => $fbPct,
            ],
            'service' => [
                'budget_ratio_pct' => (int) round(self::SERVICE_BUDGET_RATIO * 100),
                'budget' => $svcBudget,
                'purchased' => $svcPurchased,
                'remaining' => $svcRemaining,
                'variance' => round($svcPurchased - $svcBudget, 2),
                'pct' => $svcPct,
            ],
        ];
    }

    /**
     * Expand filter dates to full calendar month(s): startOfMonth(from) .. endOfMonth(to).
     *
     * @return array{0: string, 1: string}
     */
    private function fullMonthBounds(string $dateFrom, string $dateTo): array
    {
        return [
            Carbon::parse($dateFrom)->startOfMonth()->format('Y-m-d'),
            Carbon::parse($dateTo)->endOfMonth()->format('Y-m-d'),
        ];
    }

    private function hasForecastHeaderForRange(int $outletId, string $dateFrom, string $dateTo): bool
    {
        $months = $this->monthsCovered($dateFrom, $dateTo);
        foreach ($months as $monthStart) {
            $exists = DB::table('outlet_revenue_target_headers')
                ->where('outlet_id', $outletId)
                ->where('target_month', $monthStart)
                ->exists();
            if ($exists) {
                return true;
            }
        }

        return false;
    }

    private function sumForecastRevenue(int $outletId, string $dateFrom, string $dateTo): float
    {
        $months = $this->monthsCovered($dateFrom, $dateTo);
        if ($months === []) {
            return 0.0;
        }

        $headerIds = DB::table('outlet_revenue_target_headers')
            ->where('outlet_id', $outletId)
            ->whereIn('target_month', $months)
            ->pluck('id');

        if ($headerIds->isEmpty()) {
            return 0.0;
        }

        return round((float) DB::table('outlet_revenue_target_details')
            ->whereIn('header_id', $headerIds)
            ->whereDate('forecast_date', '>=', $dateFrom)
            ->whereDate('forecast_date', '<=', $dateTo)
            ->sum('forecast_revenue'), 2);
    }

    /**
     * Same buckets as Floor Order vs Forecast: kitchen/bar vs service
     * from food_floor_orders (+ retail_food by warehouse_outlet).
     *
     * @return array{kitchen_bar: float, service: float}
     */
    private function sumRoPurchasedByBucket(int $outletId, string $dateFrom, string $dateTo): array
    {
        $warehouseBucketById = DB::table('warehouse_outlets')
            ->select('id', 'name')
            ->get()
            ->mapWithKeys(function ($w) {
                $name = strtolower(trim((string) ($w->name ?? '')));
                $bucket = 'other';
                if (in_array($name, ['kitchen', 'bar'], true)) {
                    $bucket = 'kitchen_bar';
                } elseif ($name === 'service') {
                    $bucket = 'service';
                }

                return [(int) $w->id => $bucket];
            })
            ->all();

        $bucketExpr = "CASE
            WHEN LOWER(TRIM(wo.name)) IN ('kitchen', 'bar') THEN 'kitchen_bar'
            WHEN LOWER(TRIM(wo.name)) = 'service' THEN 'service'
            ELSE 'other'
        END";

        $receivedQtyByRoItem = DB::table('outlet_food_good_receive_items as gri')
            ->join('outlet_food_good_receives as gr', function ($join) {
                $join->on('gri.outlet_food_good_receive_id', '=', 'gr.id')
                    ->whereNull('gr.deleted_at')
                    ->where('gr.status', 'completed');
            })
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_floor_orders as ffo_r', 'do.floor_order_id', '=', 'ffo_r.id')
            ->where('ffo_r.id_outlet', $outletId)
            ->whereNotNull('ffo_r.arrival_date')
            ->whereBetween(DB::raw('DATE(ffo_r.arrival_date)'), [$dateFrom, $dateTo])
            ->whereNotIn('ffo_r.status', ['draft', 'rejected'])
            ->groupBy('do.floor_order_id', 'gri.item_id')
            ->select(
                'do.floor_order_id as floor_order_id',
                'gri.item_id as item_id',
                DB::raw('SUM(gri.received_qty) as qty_received')
            );

        $lineValueSql = '(CASE
            WHEN recv.qty_received IS NOT NULL AND recv.qty_received > 0
            THEN recv.qty_received * COALESCE(ffoi.price, 0)
            ELSE COALESCE(ffoi.subtotal, 0)
        END)';

        $aggregates = DB::table('food_floor_orders as ffo')
            ->join('warehouse_outlets as wo', 'wo.id', '=', 'ffo.warehouse_outlet_id')
            ->join('food_floor_order_items as ffoi', 'ffoi.floor_order_id', '=', 'ffo.id')
            ->leftJoinSub($receivedQtyByRoItem, 'recv', function ($join) {
                $join->on('recv.floor_order_id', '=', 'ffo.id')
                    ->on('recv.item_id', '=', 'ffoi.item_id');
            })
            ->where('ffo.id_outlet', $outletId)
            ->whereNotNull('ffo.arrival_date')
            ->whereBetween(DB::raw('DATE(ffo.arrival_date)'), [$dateFrom, $dateTo])
            ->whereNotIn('ffo.status', ['draft', 'rejected'])
            ->selectRaw($bucketExpr.' as bucket, SUM('.$lineValueSql.') as total')
            ->groupBy(DB::raw($bucketExpr))
            ->get();

        $kitchenBar = 0.0;
        $service = 0.0;
        foreach ($aggregates as $row) {
            $total = (float) $row->total;
            if ($row->bucket === 'kitchen_bar') {
                $kitchenBar += $total;
            } elseif ($row->bucket === 'service') {
                $service += $total;
            }
        }

        $retailFoodRows = DB::table('retail_food as rf')
            ->join('warehouse_outlets as wo', 'wo.id', '=', 'rf.warehouse_outlet_id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereBetween(DB::raw('DATE(rf.transaction_date)'), [$dateFrom, $dateTo])
            ->selectRaw('wo.id as warehouse_outlet_id, SUM(rf.total_amount) as total')
            ->groupBy('wo.id')
            ->get();

        foreach ($retailFoodRows as $rfRow) {
            $bucket = $warehouseBucketById[(int) $rfRow->warehouse_outlet_id] ?? 'other';
            $total = (float) $rfRow->total;
            if ($bucket === 'kitchen_bar') {
                $kitchenBar += $total;
            } elseif ($bucket === 'service') {
                $service += $total;
            }
        }

        return [
            'kitchen_bar' => round($kitchenBar, 2),
            'service' => round($service, 2),
        ];
    }

    /**
     * @return list<string> Y-m-01
     */
    private function monthsCovered(string $dateFrom, string $dateTo): array
    {
        $months = [];
        $cursor = Carbon::parse($dateFrom)->startOfMonth();
        $end = Carbon::parse($dateTo)->startOfMonth();
        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m-01');
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * @return array{
     *   total: float,
     *   count: int,
     *   cover: float,
     *   avg_pax: float|null,
     *   avg_check: float|null,
     *   discount: float,
     *   discount_count: int,
     *   gross_before_discount: float
     * }
     */
    public function sumRevenue(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $empty = [
            'total' => 0.0,
            'count' => 0,
            'cover' => 0.0,
            'avg_pax' => null,
            'avg_check' => null,
            'discount' => 0.0,
            'discount_count' => 0,
            'gross_before_discount' => 0.0,
        ];

        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return $empty;
        }

        $row = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('
                COALESCE(SUM(grand_total), 0) as total,
                COUNT(*) as cnt,
                COALESCE(SUM(pax), 0) as cover,
                COALESCE(SUM(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)), 0) as discount,
                SUM(CASE WHEN (COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) > 0 THEN 1 ELSE 0 END) as discount_count,
                COALESCE(SUM(COALESCE(total, 0)), 0) as gross_before_discount
            ')
            ->first();

        $total = round((float) ($row->total ?? 0), 2);
        $count = (int) ($row->cnt ?? 0);
        $cover = round((float) ($row->cover ?? 0), 2);
        $discount = round((float) ($row->discount ?? 0), 2);
        $gross = round((float) ($row->gross_before_discount ?? 0), 2);

        return [
            'total' => $total,
            'count' => $count,
            'cover' => $cover,
            'avg_pax' => $count > 0 ? round($cover / $count, 2) : null,
            'avg_check' => $cover > 0 ? round($total / $cover) : null,
            'discount' => $discount,
            'discount_count' => (int) ($row->discount_count ?? 0),
            'gross_before_discount' => $gross,
        ];
    }

    /**
     * Member activity outlet-scoped:
     * - bills/revenue dari orders.member_id
     * - Point Earn / Redeem dari member_apps_* (link via orders.id = reference_id)
     *
     * @return array{
     *   member_bills: int,
     *   member_revenue: float,
     *   top_up_value: float,
     *   top_up_count: int,
     *   top_up_points: float,
     *   redeem_value: float,
     *   redeem_count: int,
     *   redeem_points: float,
     *   source: string
     * }
     */
    public function sumMemberActivity(?string $qrCode, ?string $outletName, string $dateFrom, string $dateTo): array
    {
        $empty = [
            'member_bills' => 0,
            'member_revenue' => 0.0,
            'top_up_value' => 0.0,
            'top_up_count' => 0,
            'top_up_points' => 0.0,
            'redeem_value' => 0.0,
            'redeem_count' => 0,
            'redeem_points' => 0.0,
            'source' => 'member_apps',
        ];

        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return $empty;
        }

        $memberRow = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->selectRaw('COUNT(*) as bills, COALESCE(SUM(grand_total), 0) as revenue')
            ->first();

        $empty['member_bills'] = (int) ($memberRow->bills ?? 0);
        $empty['member_revenue'] = round((float) ($memberRow->revenue ?? 0), 2);

        $points = $this->sumMemberAppsPointsByOutlet($qrCode, $dateFrom, $dateTo);
        $empty['top_up_value'] = $points['earn_bill_amount'];
        $empty['top_up_count'] = $points['earn_count'];
        $empty['top_up_points'] = $points['earn_points'];
        $empty['redeem_value'] = $points['redeem_value'];
        $empty['redeem_count'] = $points['redeem_count'];
        $empty['redeem_points'] = $points['redeem_points'];

        return $empty;
    }

    /**
     * Point earn/redeem member apps, di-scope ke outlet via orders.id.
     *
     * @return array{
     *   earn_count: int,
     *   earn_points: float,
     *   earn_bill_amount: float,
     *   redeem_count: int,
     *   redeem_points: float,
     *   redeem_value: float
     * }
     */
    public function sumMemberAppsPointsByOutlet(string $qrCode, string $dateFrom, string $dateTo): array
    {
        $result = [
            'earn_count' => 0,
            'earn_points' => 0.0,
            'earn_bill_amount' => 0.0,
            'redeem_count' => 0,
            'redeem_points' => 0.0,
            'redeem_value' => 0.0,
        ];

        if (! Schema::hasTable('member_apps_point_transactions')) {
            return $result;
        }

        $orderIds = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($orderIds === []) {
            return $result;
        }

        foreach (array_chunk($orderIds, 500) as $chunk) {
            $earn = DB::table('member_apps_point_transactions')
                ->whereIn('reference_id', $chunk)
                ->where('transaction_type', 'earn')
                ->whereDate('transaction_date', '>=', $dateFrom)
                ->whereDate('transaction_date', '<=', $dateTo)
                ->selectRaw('
                    COUNT(*) as cnt,
                    COALESCE(SUM(point_amount), 0) as points,
                    COALESCE(SUM(transaction_amount), 0) as bill_amount
                ')
                ->first();

            $result['earn_count'] += (int) ($earn->cnt ?? 0);
            $result['earn_points'] += (float) ($earn->points ?? 0);
            $result['earn_bill_amount'] += (float) ($earn->bill_amount ?? 0);

            if (Schema::hasTable('member_apps_point_redemptions')) {
                $redeem = DB::table('member_apps_point_redemptions')
                    ->where('status', 'completed')
                    ->whereDate('redemption_date', '>=', $dateFrom)
                    ->whereDate('redemption_date', '<=', $dateTo)
                    ->whereIn(DB::raw("SUBSTRING_INDEX(reference_id, '|', -1)"), $chunk)
                    ->selectRaw('
                        COUNT(*) as cnt,
                        COALESCE(SUM(point_amount), 0) as points,
                        COALESCE(SUM(COALESCE(product_price, cash_value, 0)), 0) as value
                    ')
                    ->first();

                $result['redeem_count'] += (int) ($redeem->cnt ?? 0);
                $result['redeem_points'] += (float) ($redeem->points ?? 0);
                $result['redeem_value'] += (float) ($redeem->value ?? 0);
            }
        }

        $result['earn_points'] = round($result['earn_points'], 2);
        $result['earn_bill_amount'] = round($result['earn_bill_amount'], 2);
        $result['redeem_points'] = round($result['redeem_points'], 2);
        $result['redeem_value'] = round($result['redeem_value'], 2);

        return $result;
    }

    /**
     * @return array<string, float>
     */
    public function memberAppsEarnByDate(string $qrCode, string $dateFrom, string $dateTo): array
    {
        return $this->memberAppsMetricByDate($qrCode, $dateFrom, $dateTo, 'earn');
    }

    /**
     * @return array<string, float>
     */
    public function memberAppsRedeemByDate(string $qrCode, string $dateFrom, string $dateTo): array
    {
        return $this->memberAppsMetricByDate($qrCode, $dateFrom, $dateTo, 'redeem');
    }

    /**
     * @return array<string, float>
     */
    private function memberAppsMetricByDate(string $qrCode, string $dateFrom, string $dateTo, string $kind): array
    {
        $orderIds = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($orderIds === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($orderIds, 500) as $chunk) {
            if ($kind === 'earn') {
                $rows = DB::table('member_apps_point_transactions')
                    ->whereIn('reference_id', $chunk)
                    ->where('transaction_type', 'earn')
                    ->whereDate('transaction_date', '>=', $dateFrom)
                    ->whereDate('transaction_date', '<=', $dateTo)
                    ->selectRaw('DATE(transaction_date) as d, SUM(COALESCE(transaction_amount, 0)) as total')
                    ->groupBy(DB::raw('DATE(transaction_date)'))
                    ->get();
            } else {
                $rows = DB::table('member_apps_point_redemptions')
                    ->where('status', 'completed')
                    ->whereDate('redemption_date', '>=', $dateFrom)
                    ->whereDate('redemption_date', '<=', $dateTo)
                    ->whereIn(DB::raw("SUBSTRING_INDEX(reference_id, '|', -1)"), $chunk)
                    ->selectRaw('DATE(redemption_date) as d, SUM(COALESCE(product_price, cash_value, 0)) as total')
                    ->groupBy(DB::raw('DATE(redemption_date)'))
                    ->get();
            }

            foreach ($rows as $row) {
                $d = (string) $row->d;
                $map[$d] = ($map[$d] ?? 0) + (float) $row->total;
            }
        }

        return $map;
    }

    /**
     * Kept for optional CRM fallback (may be unreachable).
     *
     * @return array{
     *   available: bool,
     *   top_up_value: float,
     *   top_up_count: int,
     *   top_up_points: float,
     *   redeem_value: float,
     *   redeem_count: int,
     *   redeem_points: float
     * }
     */
    private function sumCrmPointActivity(?string $outletName, string $dateFrom, string $dateTo): array
    {
        $empty = [
            'available' => false,
            'top_up_value' => 0.0,
            'top_up_count' => 0,
            'top_up_points' => 0.0,
            'redeem_value' => 0.0,
            'redeem_count' => 0,
            'redeem_points' => 0.0,
        ];

        $cabangId = $this->resolveCabangId($outletName);
        if (! $cabangId) {
            return $empty;
        }

        try {
            $row = DB::connection('mysql_second')
                ->table('point')
                ->where('cabang_id', $cabangId)
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->selectRaw('
                    SUM(CASE WHEN type = "1" THEN 1 ELSE 0 END) as top_up_count,
                    SUM(CASE WHEN type = "2" THEN 1 ELSE 0 END) as redeem_count,
                    SUM(CASE WHEN type = "1" THEN point ELSE 0 END) as top_up_points,
                    SUM(CASE WHEN type = "2" THEN point ELSE 0 END) as redeem_points,
                    SUM(CASE WHEN type = "1" THEN jml_trans ELSE 0 END) as top_up_value,
                    SUM(CASE WHEN type = "2" THEN jml_trans ELSE 0 END) as redeem_value
                ')
                ->first();

            return [
                'available' => true,
                'top_up_value' => round((float) ($row->top_up_value ?? 0), 2),
                'top_up_count' => (int) ($row->top_up_count ?? 0),
                'top_up_points' => round((float) ($row->top_up_points ?? 0), 2),
                'redeem_value' => round((float) ($row->redeem_value ?? 0), 2),
                'redeem_count' => (int) ($row->redeem_count ?? 0),
                'redeem_points' => round((float) ($row->redeem_points ?? 0), 2),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    private function resolveCabangId(?string $outletName): ?int
    {
        $name = trim((string) $outletName);
        if ($name === '') {
            return null;
        }

        try {
            $exact = DB::connection('mysql_second')
                ->table('cabangs')
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                ->value('id');
            if ($exact) {
                return (int) $exact;
            }

            $fuzzy = DB::connection('mysql_second')
                ->table('cabangs')
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%'])
                ->orderBy('id')
                ->value('id');

            return $fuzzy ? (int) $fuzzy : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Metode pembayaran dari order_payment (periode filter).
     *
     * @return list<array{payment_code: string, payment_type: string|null, amount: float, count: int, pct: float|null}>
     */
    public function sumPaymentMethods(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        $rows = DB::table('order_payment as op')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $dateFrom)
            ->whereDate('o.created_at', '<=', $dateTo)
            ->where('o.status', '!=', 'cancelled')
            ->where('o.grand_total', '>', 0)
            ->groupBy('op.payment_code', 'op.payment_type')
            ->orderByDesc(DB::raw('SUM(op.amount)'))
            ->selectRaw("
                COALESCE(NULLIF(TRIM(op.payment_code), ''), 'Other') as payment_code,
                NULLIF(TRIM(op.payment_type), '') as payment_type,
                COALESCE(SUM(op.amount), 0) as amount,
                COUNT(*) as cnt
            ")
            ->get();

        $grand = 0.0;
        foreach ($rows as $row) {
            $grand += (float) $row->amount;
        }

        $result = [];
        foreach ($rows as $row) {
            $amount = round((float) $row->amount, 2);
            $result[] = [
                'payment_code' => (string) $row->payment_code,
                'payment_type' => $row->payment_type !== null ? (string) $row->payment_type : null,
                'amount' => $amount,
                'count' => (int) $row->cnt,
                'pct' => $grand > 0 ? round(($amount / $grand) * 100, 1) : null,
            ];
        }

        return $result;
    }

    /**
     * Food GR (RO/FO) + Serial GSR.
     *
     * @return array{total: float, count: int, gr_total: float, gsr_total: float}
     */
    public function sumGsrRo(int $outletId, string $dateFrom, string $dateTo): array
    {
        $grTotal = (float) DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                $join->on('ofgri.item_id', '=', 'ffoi.item_id')
                    ->where(function ($q) {
                        $q->whereColumn('ffoi.floor_order_id', 'do.floor_order_id')
                            ->orWhereColumn('ffoi.floor_order_id', 'ffo_ro.id');
                    });
            })
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereDate('ofgr.receive_date', '>=', $dateFrom)
            ->whereDate('ofgr.receive_date', '<=', $dateTo)
            ->sum(DB::raw('ofgri.received_qty * COALESCE(ffoi.price, 0)'));

        $grCount = (int) DB::table('outlet_food_good_receives as ofgr')
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereDate('ofgr.receive_date', '>=', $dateFrom)
            ->whereDate('ofgr.receive_date', '<=', $dateTo)
            ->count('ofgr.id');

        $gsrTotal = 0.0;
        $gsrCount = 0;
        if ($this->hasSerialGrTables()) {
            $priceSql = $this->serialGrPriceSql('it');
            $gsrTotal = (float) DB::table('outlet_serial_receive_items as si')
                ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->whereDate('h.receive_date', '>=', $dateFrom)
                ->whereDate('h.receive_date', '<=', $dateTo)
                ->sum(DB::raw("si.qty * ({$priceSql})"));

            $gsrCount = (int) DB::table('outlet_serial_receive_headers as h')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->whereDate('h.receive_date', '>=', $dateFrom)
                ->whereDate('h.receive_date', '<=', $dateTo)
                ->count('h.id');
        }

        return [
            'total' => round($grTotal + $gsrTotal, 2),
            'count' => $grCount + $gsrCount,
            'gr_total' => round($grTotal, 2),
            'gsr_total' => round($gsrTotal, 2),
        ];
    }

    /**
     * @return array{total: float, count: int}
     */
    public function sumRws(int $outletId, string $dateFrom, string $dateTo): array
    {
        $q = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->where('c.id_outlet', $outletId)
            ->whereDate('rws.sale_date', '>=', $dateFrom)
            ->whereDate('rws.sale_date', '<=', $dateTo);

        return [
            'total' => round((float) (clone $q)->sum(DB::raw('COALESCE(rws.total_amount, 0)')), 2),
            'count' => (int) (clone $q)->count('rws.id'),
        ];
    }

    /**
     * @return array{
     *   total: float,
     *   count: int,
     *   cash_total: float,
     *   cash_count: int,
     *   contra_bon_total: float,
     *   contra_bon_count: int
     * }
     */
    public function sumRetailFood(int $outletId, string $dateFrom, string $dateTo): array
    {
        return $this->sumRetailByPaymentMethod('retail_food', $outletId, $dateFrom, $dateTo);
    }

    /**
     * @return array{
     *   total: float,
     *   count: int,
     *   cash_total: float,
     *   cash_count: int,
     *   contra_bon_total: float,
     *   contra_bon_count: int
     * }
     */
    public function sumRetailNonFood(int $outletId, string $dateFrom, string $dateTo): array
    {
        return $this->sumRetailByPaymentMethod('retail_non_food', $outletId, $dateFrom, $dateTo);
    }

    /**
     * @return array{
     *   total: float,
     *   count: int,
     *   cash_total: float,
     *   cash_count: int,
     *   contra_bon_total: float,
     *   contra_bon_count: int
     * }
     */
    private function sumRetailByPaymentMethod(string $table, int $outletId, string $dateFrom, string $dateTo): array
    {
        $row = DB::table($table)
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->selectRaw("
                COALESCE(SUM(total_amount), 0) as total,
                COUNT(*) as cnt,
                COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_total,
                SUM(CASE WHEN payment_method = 'cash' THEN 1 ELSE 0 END) as cash_count,
                COALESCE(SUM(CASE WHEN payment_method = 'contra_bon' THEN total_amount ELSE 0 END), 0) as contra_bon_total,
                SUM(CASE WHEN payment_method = 'contra_bon' THEN 1 ELSE 0 END) as contra_bon_count
            ")
            ->first();

        return [
            'total' => round((float) ($row->total ?? 0), 2),
            'count' => (int) ($row->cnt ?? 0),
            'cash_total' => round((float) ($row->cash_total ?? 0), 2),
            'cash_count' => (int) ($row->cash_count ?? 0),
            'contra_bon_total' => round((float) ($row->contra_bon_total ?? 0), 2),
            'contra_bon_count' => (int) ($row->contra_bon_count ?? 0),
        ];
    }

    /**
     * Petty cash = RF cash + RNF cash.
     *
     * @return array<string, float>
     */
    public function pettyCashByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        return $this->mergeDateMaps(
            $this->retailCashByDate('retail_food', $outletId, $dateFrom, $dateTo),
            $this->retailCashByDate('retail_non_food', $outletId, $dateFrom, $dateTo)
        );
    }

    /**
     * @return array<string, float>
     */
    private function retailCashByDate(string $table, int $outletId, string $dateFrom, string $dateTo): array
    {
        return DB::table($table)
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->where('payment_method', 'cash')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->selectRaw('DATE(transaction_date) as d, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildTrend(int $outletId, ?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $dates = $this->dateRange($dateFrom, $dateTo);
        $revenueByDate = $this->revenueByDate($qrCode, $dateFrom, $dateTo);
        $grByDate = $this->foodGrByDate($outletId, $dateFrom, $dateTo);
        $gsrByDate = $this->gsrByDate($outletId, $dateFrom, $dateTo);
        $rwsByDate = $this->rwsByDate($outletId, $dateFrom, $dateTo);
        $rfByDate = $this->retailFoodByDate($outletId, $dateFrom, $dateTo);
        $rnfByDate = $this->retailNonFoodByDate($outletId, $dateFrom, $dateTo);

        $rows = [];
        foreach ($dates as $date) {
            $gsrRo = (float) ($grByDate[$date] ?? 0) + (float) ($gsrByDate[$date] ?? 0);
            $rws = (float) ($rwsByDate[$date] ?? 0);
            $rf = (float) ($rfByDate[$date] ?? 0);
            $rnf = (float) ($rnfByDate[$date] ?? 0);
            $spend = $gsrRo + $rws + $rf + $rnf;
            $revenue = (float) ($revenueByDate[$date] ?? 0);
            $rows[] = [
                'date' => $date,
                'revenue' => $revenue,
                'gsr_ro' => round($gsrRo, 2),
                'rws' => round($rws, 2),
                'retail_food' => round($rf, 2),
                'retail_non_food' => round($rnf, 2),
                'total_spend' => round($spend, 2),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, float>
     */
    public function revenueByDate(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        return DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DATE(created_at) as d, SUM(grand_total) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function foodGrByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        return DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                $join->on('ofgri.item_id', '=', 'ffoi.item_id')
                    ->where(function ($q) {
                        $q->whereColumn('ffoi.floor_order_id', 'do.floor_order_id')
                            ->orWhereColumn('ffoi.floor_order_id', 'ffo_ro.id');
                    });
            })
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereDate('ofgr.receive_date', '>=', $dateFrom)
            ->whereDate('ofgr.receive_date', '<=', $dateTo)
            ->selectRaw('DATE(ofgr.receive_date) as d, SUM(ofgri.received_qty * COALESCE(ffoi.price, 0)) as total')
            ->groupBy(DB::raw('DATE(ofgr.receive_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function gsrByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! $this->hasSerialGrTables()) {
            return [];
        }

        $priceSql = $this->serialGrPriceSql('it');

        return DB::table('outlet_serial_receive_items as si')
            ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
            ->join('items as it', 'si.item_id', '=', 'it.id')
            ->whereNull('h.deleted_at')
            ->where('h.status', 'completed')
            ->where('h.outlet_id', $outletId)
            ->whereDate('h.receive_date', '>=', $dateFrom)
            ->whereDate('h.receive_date', '<=', $dateTo)
            ->selectRaw("DATE(h.receive_date) as d, SUM(si.qty * ({$priceSql})) as total")
            ->groupBy(DB::raw('DATE(h.receive_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function rwsByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        return DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->where('c.id_outlet', $outletId)
            ->whereDate('rws.sale_date', '>=', $dateFrom)
            ->whereDate('rws.sale_date', '<=', $dateTo)
            ->selectRaw('DATE(rws.sale_date) as d, SUM(COALESCE(rws.total_amount, 0)) as total')
            ->groupBy(DB::raw('DATE(rws.sale_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function retailFoodByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        return DB::table('retail_food')
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->selectRaw('DATE(transaction_date) as d, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function retailNonFoodByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        return DB::table('retail_non_food')
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->selectRaw('DATE(transaction_date) as d, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return list<array{date: string, amount: float}>
     */
    public function cardTrend(int $outletId, ?string $qrCode, string $dateFrom, string $dateTo, string $type): array
    {
        $dates = $this->dateRange($dateFrom, $dateTo);
        $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');

        $map = match ($type) {
            'revenue' => $this->revenueByDate($qrCode, $dateFrom, $dateTo),
            'discount' => $this->discountByDate($qrCode, $dateFrom, $dateTo),
            'member_top_up' => $qrCode
                ? $this->memberAppsEarnByDate((string) $qrCode, $dateFrom, $dateTo)
                : [],
            'member_redeem' => $qrCode
                ? $this->memberAppsRedeemByDate((string) $qrCode, $dateFrom, $dateTo)
                : [],
            'gsr_ro' => $this->mergeDateMaps(
                $this->foodGrByDate($outletId, $dateFrom, $dateTo),
                $this->gsrByDate($outletId, $dateFrom, $dateTo)
            ),
            'rws' => $this->rwsByDate($outletId, $dateFrom, $dateTo),
            'retail_food' => $this->retailFoodByDate($outletId, $dateFrom, $dateTo),
            'retail_non_food' => $this->retailNonFoodByDate($outletId, $dateFrom, $dateTo),
            'petty_cash' => $this->pettyCashByDate($outletId, $dateFrom, $dateTo),
            'total_spend' => $this->mergeDateMaps(
                $this->foodGrByDate($outletId, $dateFrom, $dateTo),
                $this->gsrByDate($outletId, $dateFrom, $dateTo),
                $this->rwsByDate($outletId, $dateFrom, $dateTo),
                $this->retailFoodByDate($outletId, $dateFrom, $dateTo),
                $this->retailNonFoodByDate($outletId, $dateFrom, $dateTo)
            ),
            default => [],
        };

        return array_map(fn ($d) => [
            'date' => $d,
            'amount' => round((float) ($map[$d] ?? 0), 2),
        ], $dates);
    }

    /**
     * @return array<string, float>
     */
    public function discountByDate(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        return DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DATE(created_at) as d, SUM(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function posRedeemByDate(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        return DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DATE(created_at) as d, SUM(COALESCE(redeem_amount, 0)) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function crmPointByDate(?string $outletName, string $dateFrom, string $dateTo, string $type): array
    {
        $cabangId = $this->resolveCabangId($outletName);
        if (! $cabangId) {
            return [];
        }

        try {
            return DB::connection('mysql_second')
                ->table('point')
                ->where('cabang_id', $cabangId)
                ->where('type', $type)
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->selectRaw('DATE(created_at) as d, SUM(COALESCE(jml_trans, 0)) as total')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->pluck('total', 'd')
                ->map(fn ($v) => (float) $v)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Prefer CRM redeem trend; fallback POS redeem_amount.
     *
     * @return array<string, float>
     */
    public function memberRedeemByDate(?string $qrCode, ?string $outletName, string $dateFrom, string $dateTo): array
    {
        $crm = $this->crmPointByDate($outletName, $dateFrom, $dateTo, '2');
        if ($crm !== []) {
            return $crm;
        }

        return $this->posRedeemByDate($qrCode, $dateFrom, $dateTo);
    }

    private function hasSerialGrTables(): bool
    {
        return Schema::hasTable('outlet_serial_receive_headers')
            && Schema::hasTable('outlet_serial_receive_items');
    }

    private function serialGrPriceSql(string $itemAlias = 'it'): string
    {
        $costSmall = 'COALESCE(si.cost_small, 0)';
        $smallConv = "COALESCE({$itemAlias}.small_conversion_qty, 1)";
        $mediumConv = "COALESCE({$itemAlias}.medium_conversion_qty, 1)";

        return "(CASE
            WHEN si.unit_id = {$itemAlias}.large_unit_id THEN {$costSmall} * {$smallConv} * {$mediumConv}
            WHEN si.unit_id = {$itemAlias}.medium_unit_id THEN {$costSmall} * {$smallConv}
            ELSE {$costSmall}
        END)";
    }

    /**
     * @param  array<string, float>  ...$maps
     * @return array<string, float>
     */
    private function mergeDateMaps(array ...$maps): array
    {
        $out = [];
        foreach ($maps as $map) {
            foreach ($map as $d => $v) {
                $out[$d] = ($out[$d] ?? 0) + (float) $v;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function dateRange(string $dateFrom, string $dateTo): array
    {
        $dates = [];
        $current = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->startOfDay();
        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        return $dates;
    }
}
