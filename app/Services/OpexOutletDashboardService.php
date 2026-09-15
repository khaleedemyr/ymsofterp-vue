<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared aggregations for Opex Outlet Dashboard.
 * Definitions:
 * - Paid PR: non_food_payments (PO-linked + direct), no PO-item fan-out
 * - Petty Cash: retail_food + retail_non_food (approved, non contra_bon)
 * - Food Receive: outlet_food_good_receives @ FO price only
 * - Total Opex: Paid PR + Petty Cash + Food Receive (unpaid PR excluded)
 */
class OpexOutletDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getOverview(?int $outletId, string $dateFrom, string $dateTo): array
    {
        $paid = $this->sumPaidPr($outletId, $dateFrom, $dateTo);
        $petty = $this->sumPettyCash($outletId, $dateFrom, $dateTo);
        $food = $this->sumFoodReceive($outletId, $dateFrom, $dateTo);
        $unpaid = $this->sumUnpaidPr($outletId, $dateFrom, $dateTo);

        $totalOpex = round($paid['total'] + $petty['total'] + $food['total'], 2);

        return [
            'total_paid' => $paid['total'],
            'payment_count' => $paid['count'],
            'total_petty_cash' => $petty['total'],
            'petty_cash_count' => $petty['count'],
            'total_retail_non_food' => $petty['rnf_total'],
            'retail_non_food_count' => $petty['rnf_count'],
            'total_retail_food' => $petty['rf_total'],
            'retail_food_count' => $petty['rf_count'],
            'total_food' => $food['total'],
            'food_count' => $food['count'],
            'total_unpaid' => $unpaid['total'],
            'unpaid_pr_count' => $unpaid['count'],
            'total_opex' => $totalOpex,
        ];
    }

    /**
     * @return array{total: float, count: int}
     */
    public function sumPaidPr(?int $outletId, string $dateFrom, string $dateTo): array
    {
        $poLinked = $this->paidPrPoLinkedBase($outletId, $dateFrom, $dateTo)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(nfp.amount), 0) as total')
            ->first();

        $direct = $this->paidPrDirectBase($outletId, $dateFrom, $dateTo)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(nfp.amount), 0) as total')
            ->first();

        return [
            'total' => round((float) ($poLinked->total ?? 0) + (float) ($direct->total ?? 0), 2),
            'count' => (int) ($poLinked->cnt ?? 0) + (int) ($direct->cnt ?? 0),
        ];
    }

    /**
     * @return array{total: float, count: int, rnf_total: float, rnf_count: int, rf_total: float, rf_count: int}
     */
    public function sumPettyCash(?int $outletId, string $dateFrom, string $dateTo): array
    {
        $rnfQ = DB::table('retail_non_food')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('status', 'approved')
            ->where('payment_method', '!=', 'contra_bon')
            ->whereNull('deleted_at')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));

        $rfQ = DB::table('retail_food')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('status', 'approved')
            ->where('payment_method', '!=', 'contra_bon')
            ->whereNull('deleted_at')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));

        $rnfTotal = (float) (clone $rnfQ)->sum('total_amount');
        $rnfCount = (int) (clone $rnfQ)->count('id');
        $rfTotal = (float) (clone $rfQ)->sum('total_amount');
        $rfCount = (int) (clone $rfQ)->count('id');

        return [
            'total' => round($rnfTotal + $rfTotal, 2),
            'count' => $rnfCount + $rfCount,
            'rnf_total' => round($rnfTotal, 2),
            'rnf_count' => $rnfCount,
            'rf_total' => round($rfTotal, 2),
            'rf_count' => $rfCount,
        ];
    }

    /**
     * @return array{total: float, count: int}
     */
    public function sumFoodReceive(?int $outletId, string $dateFrom, string $dateTo): array
    {
        $total = (float) $this->foodReceiveLinesBase($outletId, $dateFrom, $dateTo)
            ->sum(DB::raw('i.received_qty * COALESCE(fo.price, 0)'));

        $count = (int) DB::table('outlet_food_good_receives as gr')
            ->whereBetween('gr.receive_date', [$dateFrom, $dateTo])
            ->whereNull('gr.deleted_at')
            ->when($outletId, fn ($q) => $q->where('gr.outlet_id', $outletId))
            ->count('gr.id');

        return [
            'total' => round($total, 2),
            'count' => $count,
        ];
    }

    /**
     * @return array{total: float, count: int}
     */
    public function sumUnpaidPr(?int $outletId, string $dateFrom, string $dateTo): array
    {
        $balances = $this->unpaidPrBalances($outletId, $dateFrom, $dateTo);
        $positive = $balances->filter(fn ($row) => $row['unpaid_amount'] > 0);

        return [
            'total' => round((float) $positive->sum('unpaid_amount'), 2),
            'count' => $positive->count(),
        ];
    }

    /**
     * Batch unpaid calculation (no N+1).
     *
     * @return Collection<int, array{id: int, unpaid_amount: float, pr_amount: float, paid_amount: float}>
     */
    public function unpaidPrBalances(?int $outletId, string $dateFrom, string $dateTo): Collection
    {
        $prs = DB::table('purchase_requisitions as pr')
            ->whereBetween('pr.created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
            ->where('pr.is_held', false)
            ->when($outletId, fn ($q) => $q->where('pr.outlet_id', $outletId))
            ->get(['pr.id', 'pr.amount']);

        if ($prs->isEmpty()) {
            return collect();
        }

        $prIds = $prs->pluck('id')->all();

        $poTotals = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->whereIn('poi.source_id', $prIds)
            ->where('poo.status', 'approved')
            ->groupBy('poi.source_id')
            ->selectRaw('poi.source_id as pr_id, SUM(poi.total) as po_total')
            ->pluck('po_total', 'pr_id');

        $poIdsByPr = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->whereIn('poi.source_id', $prIds)
            ->where('poo.status', 'approved')
            ->select('poi.source_id as pr_id', 'poi.purchase_order_ops_id')
            ->get()
            ->groupBy('pr_id')
            ->map(fn ($rows) => $rows->pluck('purchase_order_ops_id')->unique()->values()->all());

        $allPoIds = $poIdsByPr->flatten()->unique()->values()->all();

        $paidByPo = collect();
        if ($allPoIds !== []) {
            $paidByPo = DB::table('non_food_payments')
                ->whereIn('purchase_order_ops_id', $allPoIds)
                ->whereIn('status', ['paid', 'approved'])
                ->groupBy('purchase_order_ops_id')
                ->selectRaw('purchase_order_ops_id, SUM(amount) as paid')
                ->pluck('paid', 'purchase_order_ops_id');
        }

        $paidDirect = DB::table('non_food_payments')
            ->whereIn('purchase_requisition_id', $prIds)
            ->whereIn('status', ['paid', 'approved'])
            ->groupBy('purchase_requisition_id')
            ->selectRaw('purchase_requisition_id, SUM(amount) as paid')
            ->pluck('paid', 'purchase_requisition_id');

        return $prs->map(function ($pr) use ($poTotals, $poIdsByPr, $paidByPo, $paidDirect) {
            $poTotal = (float) ($poTotals[$pr->id] ?? 0);
            $prAmount = $poTotal > 0 ? $poTotal : (float) $pr->amount;

            if ($poTotal > 0) {
                $paid = 0.0;
                foreach ($poIdsByPr[$pr->id] ?? [] as $poId) {
                    $paid += (float) ($paidByPo[$poId] ?? 0);
                }
            } else {
                $paid = (float) ($paidDirect[$pr->id] ?? 0);
            }

            return [
                'id' => (int) $pr->id,
                'pr_amount' => round($prAmount, 2),
                'paid_amount' => round($paid, 2),
                'unpaid_amount' => round(max(0, $prAmount - $paid), 2),
            ];
        });
    }

    /**
     * Daily trend: paid_amount, petty_cash_amount, food_amount (GR only).
     *
     * @return list<array{date: string, paid_amount: float, petty_cash_amount: float, retail_non_food_amount: float, food_amount: float}>
     */
    public function getOpexTrend(?int $outletId, string $dateFrom, string $dateTo): array
    {
        $dates = $this->dateRange($dateFrom, $dateTo);

        $paidByDate = $this->paidPrByDate($outletId, $dateFrom, $dateTo);
        $pettyByDate = $this->pettyCashByDate($outletId, $dateFrom, $dateTo);
        $foodByDate = $this->foodReceiveByDate($outletId, $dateFrom, $dateTo);

        $result = [];
        foreach ($dates as $date) {
            $paid = (float) ($paidByDate[$date] ?? 0);
            $petty = (float) ($pettyByDate[$date] ?? 0);
            $food = (float) ($foodByDate[$date] ?? 0);
            $result[] = [
                'date' => $date,
                'paid_amount' => $paid,
                'petty_cash_amount' => $petty,
                // backward-compatible key used by old chart series
                'retail_non_food_amount' => $petty,
                'food_amount' => $food,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{date: string, amount: float}>
     */
    public function getCardTrend(?int $outletId, string $dateFrom, string $dateTo, string $type): array
    {
        $dates = $this->dateRange($dateFrom, $dateTo);
        $paidByDate = in_array($type, ['total_paid', 'total_opex'], true)
            ? $this->paidPrByDate($outletId, $dateFrom, $dateTo) : [];
        $pettyByDate = in_array($type, ['petty_cash', 'retail_non_food', 'total_opex'], true)
            ? $this->pettyCashByDate($outletId, $dateFrom, $dateTo) : [];
        $foodByDate = in_array($type, ['food', 'food_receive', 'total_opex'], true)
            ? $this->foodReceiveByDate($outletId, $dateFrom, $dateTo) : [];

        $unpaidByDate = [];
        if ($type === 'unpaid_pr') {
            // Snapshot unpaid for PRs created on that day (same window semantics as overview)
            $balances = $this->unpaidPrBalances($outletId, $dateFrom, $dateTo);
            $prDates = DB::table('purchase_requisitions')
                ->whereIn('id', $balances->pluck('id'))
                ->get(['id', 'created_at'])
                ->keyBy('id');
            foreach ($balances as $row) {
                if ($row['unpaid_amount'] <= 0) {
                    continue;
                }
                $pr = $prDates[$row['id']] ?? null;
                if (! $pr) {
                    continue;
                }
                $d = Carbon::parse($pr->created_at)->toDateString();
                $unpaidByDate[$d] = ($unpaidByDate[$d] ?? 0) + $row['unpaid_amount'];
            }
        }

        $result = [];
        foreach ($dates as $date) {
            $amount = 0.0;
            if ($type === 'total_paid') {
                $amount = (float) ($paidByDate[$date] ?? 0);
            } elseif ($type === 'petty_cash' || $type === 'retail_non_food') {
                $amount = (float) ($pettyByDate[$date] ?? 0);
            } elseif ($type === 'food' || $type === 'food_receive') {
                $amount = (float) ($foodByDate[$date] ?? 0);
            } elseif ($type === 'unpaid_pr') {
                $amount = (float) ($unpaidByDate[$date] ?? 0);
            } elseif ($type === 'total_opex') {
                $amount = (float) ($paidByDate[$date] ?? 0)
                    + (float) ($pettyByDate[$date] ?? 0)
                    + (float) ($foodByDate[$date] ?? 0);
            }
            $result[] = ['date' => $date, 'amount' => round($amount, 2)];
        }

        return $result;
    }

    /**
     * Revenue from orders (same base as Daily Outlet Revenue).
     */
    public function sumOutletRevenue(?string $qrCode, string $dateFrom, string $dateTo): float
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return 0.0;
        }

        return round((float) DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->sum('grand_total'), 2);
    }

    /**
     * @return array{mtd_revenue: float, opex_ratio_percent: float|null, period_label: string}|null
     */
    public function buildRevenueKpi(?int $outletId, string $dateFrom, string $dateTo, float $totalOpex): ?array
    {
        if (! $outletId) {
            return null;
        }

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['qr_code', 'nama_outlet']);

        $mtdFrom = Carbon::parse($dateFrom)->startOfMonth()->toDateString();
        $revenue = $this->sumOutletRevenue($outlet?->qr_code, $mtdFrom, $dateTo);
        $ratio = $revenue > 0 ? round(($totalOpex / $revenue) * 100, 2) : null;

        return [
            'mtd_revenue' => $revenue,
            'opex_ratio_percent' => $ratio,
            'period_label' => Carbon::parse($dateFrom)->locale('id')->translatedFormat('F Y'),
            'outlet_name' => $outlet?->nama_outlet,
        ];
    }

    /**
     * @return array<string, float> date => amount
     */
    public function paidPrByDate(?int $outletId, string $dateFrom, string $dateTo): array
    {
        $po = $this->paidPrPoLinkedBase($outletId, $dateFrom, $dateTo)
            ->selectRaw('DATE(nfp.payment_date) as d, SUM(nfp.amount) as total')
            ->groupBy(DB::raw('DATE(nfp.payment_date)'))
            ->pluck('total', 'd');

        $direct = $this->paidPrDirectBase($outletId, $dateFrom, $dateTo)
            ->selectRaw('DATE(nfp.payment_date) as d, SUM(nfp.amount) as total')
            ->groupBy(DB::raw('DATE(nfp.payment_date)'))
            ->pluck('total', 'd');

        $map = [];
        foreach ($po as $d => $total) {
            $map[$d] = ($map[$d] ?? 0) + (float) $total;
        }
        foreach ($direct as $d => $total) {
            $map[$d] = ($map[$d] ?? 0) + (float) $total;
        }

        return $map;
    }

    /**
     * @return array<string, float>
     */
    public function pettyCashByDate(?int $outletId, string $dateFrom, string $dateTo): array
    {
        $rnf = DB::table('retail_non_food')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('status', 'approved')
            ->where('payment_method', '!=', 'contra_bon')
            ->whereNull('deleted_at')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->selectRaw('DATE(transaction_date) as d, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->pluck('total', 'd');

        $rf = DB::table('retail_food')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('status', 'approved')
            ->where('payment_method', '!=', 'contra_bon')
            ->whereNull('deleted_at')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->selectRaw('DATE(transaction_date) as d, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->pluck('total', 'd');

        $map = [];
        foreach ($rnf as $d => $total) {
            $map[$d] = ($map[$d] ?? 0) + (float) $total;
        }
        foreach ($rf as $d => $total) {
            $map[$d] = ($map[$d] ?? 0) + (float) $total;
        }

        return $map;
    }

    /**
     * @return array<string, float>
     */
    public function foodReceiveByDate(?int $outletId, string $dateFrom, string $dateTo): array
    {
        return $this->foodReceiveLinesBase($outletId, $dateFrom, $dateTo)
            ->selectRaw('DATE(gr.receive_date) as d, SUM(i.received_qty * COALESCE(fo.price, 0)) as total')
            ->groupBy(DB::raw('DATE(gr.receive_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Payment rows without PO-item fan-out (exists subquery for outlet).
     */
    public function paidPrPoLinkedBase(?int $outletId, string $dateFrom, string $dateTo)
    {
        $q = DB::table('non_food_payments as nfp')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->whereExists(function ($sub) use ($outletId) {
                $sub->select(DB::raw(1))
                    ->from('purchase_order_ops_items as poi')
                    ->join('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->whereColumn('poi.purchase_order_ops_id', 'nfp.purchase_order_ops_id')
                    ->where('poi.source_type', 'purchase_requisition_ops');
                if ($outletId) {
                    $sub->where('pr.outlet_id', $outletId);
                }
            });

        return $q;
    }

    public function paidPrDirectBase(?int $outletId, string $dateFrom, string $dateTo)
    {
        return DB::table('non_food_payments as nfp')
            ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id')
            ->when($outletId, fn ($q) => $q->where('pr.outlet_id', $outletId));
    }

    public function foodReceiveLinesBase(?int $outletId, string $dateFrom, string $dateTo)
    {
        $q = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->where(function ($q) {
                        $q->whereColumn('fo.floor_order_id', 'do.floor_order_id')
                            ->orWhereColumn('fo.floor_order_id', 'ffo_ro.id');
                    });
            })
            ->whereBetween('gr.receive_date', [$dateFrom, $dateTo])
            ->whereNull('gr.deleted_at');

        if ($outletId) {
            $q->where('gr.outlet_id', $outletId);
        }

        return $q;
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
