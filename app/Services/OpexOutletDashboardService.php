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
    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(?int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! $outletId) {
            return [
                'overview' => null,
                'trend' => [],
                'spend_mix' => [],
                'outlet_name' => null,
            ];
        }

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['id_outlet', 'nama_outlet', 'qr_code']);

        $revenue = $this->sumRevenue($outlet?->qr_code, $dateFrom, $dateTo);
        $gsrRo = $this->sumGsrRo($outletId, $dateFrom, $dateTo);
        $rws = $this->sumRws($outletId, $dateFrom, $dateTo);
        $rf = $this->sumRetailFood($outletId, $dateFrom, $dateTo);
        $rnf = $this->sumRetailNonFood($outletId, $dateFrom, $dateTo);

        $totalSpend = round($gsrRo['total'] + $rws['total'] + $rf['total'] + $rnf['total'], 2);
        $spendRatio = $revenue['total'] > 0 ? round(($totalSpend / $revenue['total']) * 100, 2) : null;

        $overview = [
            'revenue' => $revenue['total'],
            'revenue_count' => $revenue['count'],
            'gsr_ro' => $gsrRo['total'],
            'gsr_ro_count' => $gsrRo['count'],
            'gsr_ro_gr' => $gsrRo['gr_total'],
            'gsr_ro_gsr' => $gsrRo['gsr_total'],
            'rws' => $rws['total'],
            'rws_count' => $rws['count'],
            'retail_food' => $rf['total'],
            'retail_food_count' => $rf['count'],
            'retail_non_food' => $rnf['total'],
            'retail_non_food_count' => $rnf['count'],
            'total_spend' => $totalSpend,
            'spend_ratio_percent' => $spendRatio,
            'net' => round($revenue['total'] - $totalSpend, 2),
        ];

        return [
            'overview' => $overview,
            'trend' => $this->buildTrend($outletId, $outlet?->qr_code, $dateFrom, $dateTo),
            'spend_mix' => [
                ['key' => 'gsr_ro', 'label' => 'GSR / RO', 'amount' => $gsrRo['total']],
                ['key' => 'rws', 'label' => 'RWS', 'amount' => $rws['total']],
                ['key' => 'retail_food', 'label' => 'Retail Food', 'amount' => $rf['total']],
                ['key' => 'retail_non_food', 'label' => 'Retail Non Food', 'amount' => $rnf['total']],
            ],
            'outlet_name' => $outlet?->nama_outlet,
        ];
    }

    /**
     * @return array{total: float, count: int}
     */
    public function sumRevenue(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return ['total' => 0.0, 'count' => 0];
        }

        $row = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total, COUNT(*) as cnt')
            ->first();

        return [
            'total' => round((float) ($row->total ?? 0), 2),
            'count' => (int) ($row->cnt ?? 0),
        ];
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
     * @return array{total: float, count: int}
     */
    public function sumRetailFood(int $outletId, string $dateFrom, string $dateTo): array
    {
        $q = DB::table('retail_food')
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo);

        return [
            'total' => round((float) (clone $q)->sum('total_amount'), 2),
            'count' => (int) (clone $q)->count('id'),
        ];
    }

    /**
     * @return array{total: float, count: int}
     */
    public function sumRetailNonFood(int $outletId, string $dateFrom, string $dateTo): array
    {
        $q = DB::table('retail_non_food')
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo);

        return [
            'total' => round((float) (clone $q)->sum('total_amount'), 2),
            'count' => (int) (clone $q)->count('id'),
        ];
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
        $map = match ($type) {
            'revenue' => $this->revenueByDate($qrCode, $dateFrom, $dateTo),
            'gsr_ro' => $this->mergeDateMaps(
                $this->foodGrByDate($outletId, $dateFrom, $dateTo),
                $this->gsrByDate($outletId, $dateFrom, $dateTo)
            ),
            'rws' => $this->rwsByDate($outletId, $dateFrom, $dateTo),
            'retail_food' => $this->retailFoodByDate($outletId, $dateFrom, $dateTo),
            'retail_non_food' => $this->retailNonFoodByDate($outletId, $dateFrom, $dateTo),
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
