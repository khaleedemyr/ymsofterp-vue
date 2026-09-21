<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarehouseDashboardOpsService
{
    /**
     * @return array{date_from: string, date_to: string, label: string, month: string}
     */
    public static function resolvePeriod(?string $period): array
    {
        $month = $period && preg_match('/^\d{4}-\d{2}$/', $period)
            ? $period
            : Carbon::now()->format('Y-m');

        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $label = $start->locale('id')->translatedFormat('F Y');
        $label = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');

        return [
            'month' => $month,
            'date_from' => $start->format('Y-m-d'),
            'date_to' => $end->format('Y-m-d'),
            'label' => $label,
        ];
    }

    /**
     * @return array{
     *   summary: list<array<string, mixed>>,
     *   recent: list<array<string, mixed>>,
     *   daily: list<array<string, mixed>>,
     * }
     */
    public function build(string $dateFrom, string $dateTo, int $warehouseId = 0): array
    {
        $summary = [
            $this->countPrFoods($dateFrom, $dateTo, $warehouseId),
            $this->countGoodReceives($dateFrom, $dateTo, $warehouseId),
            $this->countTransfers($dateFrom, $dateTo, $warehouseId),
            $this->countPackingLists($dateFrom, $dateTo, $warehouseId),
            $this->countDeliveryOrders($dateFrom, $dateTo, $warehouseId),
            $this->countRetailFood($dateFrom, $dateTo, $warehouseId),
            $this->countRetailSales($dateFrom, $dateTo, $warehouseId),
            $this->countAdjustments($dateFrom, $dateTo, $warehouseId),
            $this->countStockOpnames($dateFrom, $dateTo, $warehouseId),
            $this->countInternalUseWaste($dateFrom, $dateTo, $warehouseId),
            $this->countWarehouseSales($dateFrom, $dateTo, $warehouseId),
            $this->countOutletRejections($dateFrom, $dateTo, $warehouseId),
        ];

        $recent = $this->recentTransactions($dateFrom, $dateTo, $warehouseId, 20);
        $daily = $this->dailyCounts($dateFrom, $dateTo, $warehouseId);

        return [
            'summary' => $summary,
            'recent' => $recent,
            'daily' => $daily,
            'total_transactions' => array_sum(array_column($summary, 'count')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function countPrFoods(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('pr_foods')->whereBetween('tanggal', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byStatus = DB::table('pr_foods')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('tanggal', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('pr_foods', 'Purchase Requisition', $count, '/pr-foods', 'fa-solid fa-file-invoice', $byStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function countGoodReceives(string $from, string $to, int $warehouseId): array
    {
        // food_good_receives tidak punya warehouse_id — hitung by receive_date (filter WH diabaikan)
        $count = (int) DB::table('food_good_receives')
            ->whereBetween('receive_date', [$from, $to])
            ->count();

        return $this->card('food_good_receive', 'Penerimaan Barang', $count, '/food-good-receive', 'fa-solid fa-truck', [], $warehouseId > 0 ? 'Semua WH (GR tanpa warehouse_id)' : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function countTransfers(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('warehouse_transfers')->whereBetween('transfer_date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where(function ($qq) use ($warehouseId) {
                $qq->where('warehouse_from_id', $warehouseId)
                    ->orWhere('warehouse_to_id', $warehouseId);
            });
        }
        $count = (int) $q->count();

        return $this->card('warehouse_transfer', 'Pindah Gudang', $count, '/warehouse-transfer', 'fa-solid fa-right-left');
    }

    /**
     * @return array<string, mixed>
     */
    private function countPackingLists(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('packing_lists')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byStatus = DB::table('packing_lists')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('packing_list', 'Packing List', $count, '/packing-list', 'fa-solid fa-box', $byStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function countDeliveryOrders(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('delivery_orders as do')
            ->leftJoin('packing_lists as pl', 'pl.id', '=', 'do.packing_list_id')
            ->whereDate('do.created_at', '>=', $from)
            ->whereDate('do.created_at', '<=', $to);
        if ($warehouseId > 0) {
            $q->where('pl.warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();

        return $this->card('delivery_order', 'Delivery Order', $count, '/delivery-order', 'fa-solid fa-truck-arrow-right');
    }

    /**
     * @return array<string, mixed>
     */
    private function countRetailFood(string $from, string $to, int $warehouseId): array
    {
        $base = DB::table('retail_warehouse_food')
            ->whereNull('deleted_at')
            ->whereBetween('transaction_date', [$from, $to]);
        if ($warehouseId > 0) {
            $base->where('warehouse_id', $warehouseId);
        }
        $count = (int) (clone $base)->count();
        $amount = (float) (clone $base)->sum('total_amount');
        $byStatus = (clone $base)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $card = $this->card('retail_warehouse_food', 'Warehouse Retail Food', $count, '/retail-warehouse-food', 'fa-solid fa-warehouse', $byStatus);
        $card['amount'] = round($amount, 2);

        return $card;
    }

    /**
     * @return array<string, mixed>
     */
    private function countRetailSales(string $from, string $to, int $warehouseId): array
    {
        if (!Schema::hasTable('retail_warehouse_sales')) {
            return $this->card('retail_warehouse_sale', 'Penjualan Warehouse Retail', 0, '/retail-warehouse-sale', 'fa-solid fa-store');
        }

        $dateCol = Schema::hasColumn('retail_warehouse_sales', 'transaction_date')
            ? 'transaction_date'
            : (Schema::hasColumn('retail_warehouse_sales', 'date') ? 'date' : 'created_at');

        $q = DB::table('retail_warehouse_sales');
        if ($dateCol === 'created_at') {
            $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
        } else {
            $q->whereBetween($dateCol, [$from, $to]);
        }
        if ($warehouseId > 0 && Schema::hasColumn('retail_warehouse_sales', 'warehouse_id')) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();

        return $this->card('retail_warehouse_sale', 'Penjualan Warehouse Retail', $count, '/retail-warehouse-sale', 'fa-solid fa-store');
    }

    /**
     * @return array<string, mixed>
     */
    private function countAdjustments(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('food_inventory_adjustments')->whereBetween('date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byStatus = DB::table('food_inventory_adjustments')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('stock_adjustment', 'Penyesuaian Stok', $count, '/food-inventory-adjustment', 'fa-solid fa-boxes-stacked', $byStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function countStockOpnames(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('warehouse_stock_opnames')->whereBetween('opname_date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byStatus = DB::table('warehouse_stock_opnames')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('opname_date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('warehouse_stock_opname', 'Stock Opname', $count, '/warehouse-stock-opnames', 'fa-solid fa-clipboard-check', $byStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function countInternalUseWaste(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('internal_use_wastes')->whereBetween('date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byType = DB::table('internal_use_wastes')
            ->select('type', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('type')
            ->pluck('cnt', 'type')
            ->all();

        return $this->card('internal_use_waste', 'Pemakaian Internal & Sampah', $count, '/internal-use-waste', 'fa-solid fa-recycle', $byType);
    }

    /**
     * @return array<string, mixed>
     */
    private function countWarehouseSales(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('warehouse_sales')
            ->whereNull('deleted_at')
            ->whereBetween('date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where(function ($qq) use ($warehouseId) {
                $qq->where('source_warehouse_id', $warehouseId)
                    ->orWhere('target_warehouse_id', $warehouseId);
            });
        }
        $count = (int) $q->count();
        $byStatus = DB::table('warehouse_sales')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereNull('deleted_at')
            ->whereBetween('date', [$from, $to])
            ->when($warehouseId > 0, function ($qq) use ($warehouseId) {
                $qq->where(function ($q2) use ($warehouseId) {
                    $q2->where('source_warehouse_id', $warehouseId)
                        ->orWhere('target_warehouse_id', $warehouseId);
                });
            })
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('warehouse_sales', 'Penjualan Antar Gudang', $count, '/warehouse-sales', 'fas fa-exchange-alt', $byStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function countOutletRejections(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('outlet_rejections')->whereBetween('rejection_date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byStatus = DB::table('outlet_rejections')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('rejection_date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('outlet_rejection', 'Penolakan Outlet', $count, '/outlet-rejections', 'fas fa-undo', $byStatus);
    }

    /**
     * @param  array<string, int|string>  $byStatus
     * @return array<string, mixed>
     */
    private function card(
        string $key,
        string $label,
        int $count,
        string $route,
        string $icon,
        array $byStatus = [],
        ?string $note = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'route' => $route,
            'icon' => $icon,
            'by_status' => $byStatus,
            'note' => $note,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentTransactions(string $from, string $to, int $warehouseId, int $limit = 20): array
    {
        $rows = [];

        $gr = DB::table('food_good_receives')
            ->whereBetween('receive_date', [$from, $to])
            ->orderByDesc('receive_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'gr_number as number', 'receive_date as txn_date']);
        foreach ($gr as $r) {
            $rows[] = [
                'type' => 'Penerimaan Barang',
                'type_key' => 'food_good_receive',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => '—',
                'status' => null,
                'url' => '/food-good-receive/' . $r->id,
            ];
        }

        $tfQ = DB::table('warehouse_transfers as t')
            ->leftJoin('warehouses as wf', 'wf.id', '=', 't.warehouse_from_id')
            ->leftJoin('warehouses as wt', 'wt.id', '=', 't.warehouse_to_id')
            ->whereBetween('t.transfer_date', [$from, $to])
            ->when($warehouseId > 0, function ($q) use ($warehouseId) {
                $q->where(function ($qq) use ($warehouseId) {
                    $qq->where('t.warehouse_from_id', $warehouseId)
                        ->orWhere('t.warehouse_to_id', $warehouseId);
                });
            })
            ->orderByDesc('t.transfer_date')
            ->orderByDesc('t.id')
            ->limit(8)
            ->get([
                't.id',
                't.transfer_number as number',
                't.transfer_date as txn_date',
                DB::raw("CONCAT(COALESCE(wf.name,'?'), ' → ', COALESCE(wt.name,'?')) as warehouse_name"),
            ]);
        foreach ($tfQ as $r) {
            $rows[] = [
                'type' => 'Pindah Gudang',
                'type_key' => 'warehouse_transfer',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name,
                'status' => null,
                'url' => '/warehouse-transfer/' . $r->id,
            ];
        }

        $retailQ = DB::table('retail_warehouse_food as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->whereNull('r.deleted_at')
            ->whereBetween('r.transaction_date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('r.warehouse_id', $warehouseId))
            ->orderByDesc('r.transaction_date')
            ->orderByDesc('r.id')
            ->limit(8)
            ->get([
                'r.id',
                'r.retail_number as number',
                'r.transaction_date as txn_date',
                'r.status',
                'w.name as warehouse_name',
                'r.total_amount',
            ]);
        foreach ($retailQ as $r) {
            $rows[] = [
                'type' => 'Retail Food',
                'type_key' => 'retail_warehouse_food',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name ?? '—',
                'status' => $r->status,
                'amount' => $r->total_amount,
                'url' => '/retail-warehouse-food/' . $r->id,
            ];
        }

        $adjQ = DB::table('food_inventory_adjustments as a')
            ->leftJoin('warehouses as w', 'w.id', '=', 'a.warehouse_id')
            ->whereBetween('a.date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('a.warehouse_id', $warehouseId))
            ->orderByDesc('a.date')
            ->orderByDesc('a.id')
            ->limit(6)
            ->get(['a.id', 'a.number', 'a.date as txn_date', 'a.status', 'w.name as warehouse_name']);
        foreach ($adjQ as $r) {
            $rows[] = [
                'type' => 'Penyesuaian Stok',
                'type_key' => 'stock_adjustment',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name ?? '—',
                'status' => $r->status,
                'url' => '/food-inventory-adjustment/' . $r->id,
            ];
        }

        $soQ = DB::table('warehouse_stock_opnames as s')
            ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->whereBetween('s.opname_date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('s.warehouse_id', $warehouseId))
            ->orderByDesc('s.opname_date')
            ->orderByDesc('s.id')
            ->limit(6)
            ->get(['s.id', 's.opname_number as number', 's.opname_date as txn_date', 's.status', 'w.name as warehouse_name']);
        foreach ($soQ as $r) {
            $rows[] = [
                'type' => 'Stock Opname',
                'type_key' => 'warehouse_stock_opname',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name ?? '—',
                'status' => $r->status,
                'url' => '/warehouse-stock-opnames/' . $r->id,
            ];
        }

        $doQ = DB::table('delivery_orders as do')
            ->leftJoin('packing_lists as pl', 'pl.id', '=', 'do.packing_list_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'pl.warehouse_id')
            ->whereDate('do.created_at', '>=', $from)
            ->whereDate('do.created_at', '<=', $to)
            ->when($warehouseId > 0, fn ($q) => $q->where('pl.warehouse_id', $warehouseId))
            ->orderByDesc('do.created_at')
            ->orderByDesc('do.id')
            ->limit(6)
            ->get([
                'do.id',
                'do.number',
                DB::raw('DATE(do.created_at) as txn_date'),
                'w.name as warehouse_name',
            ]);
        foreach ($doQ as $r) {
            $rows[] = [
                'type' => 'Delivery Order',
                'type_key' => 'delivery_order',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name ?? '—',
                'status' => null,
                'url' => '/delivery-order/' . $r->id,
            ];
        }

        $wsQ = DB::table('warehouse_sales as s')
            ->leftJoin('warehouses as wf', 'wf.id', '=', 's.source_warehouse_id')
            ->leftJoin('warehouses as wt', 'wt.id', '=', 's.target_warehouse_id')
            ->whereNull('s.deleted_at')
            ->whereBetween('s.date', [$from, $to])
            ->when($warehouseId > 0, function ($q) use ($warehouseId) {
                $q->where(function ($qq) use ($warehouseId) {
                    $qq->where('s.source_warehouse_id', $warehouseId)
                        ->orWhere('s.target_warehouse_id', $warehouseId);
                });
            })
            ->orderByDesc('s.date')
            ->orderByDesc('s.id')
            ->limit(6)
            ->get([
                's.id',
                's.number',
                's.date as txn_date',
                's.status',
                DB::raw("CONCAT(COALESCE(wf.name,'?'), ' → ', COALESCE(wt.name,'?')) as warehouse_name"),
            ]);
        foreach ($wsQ as $r) {
            $rows[] = [
                'type' => 'Penjualan Antar Gudang',
                'type_key' => 'warehouse_sales',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name,
                'status' => $r->status,
                'url' => '/warehouse-sales/' . $r->id,
            ];
        }

        $rejQ = DB::table('outlet_rejections as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->whereBetween('r.rejection_date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('r.warehouse_id', $warehouseId))
            ->orderByDesc('r.rejection_date')
            ->orderByDesc('r.id')
            ->limit(6)
            ->get(['r.id', 'r.number', 'r.rejection_date as txn_date', 'r.status', 'w.name as warehouse_name']);
        foreach ($rejQ as $r) {
            $rows[] = [
                'type' => 'Penolakan Outlet',
                'type_key' => 'outlet_rejection',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name ?? '—',
                'status' => $r->status,
                'url' => '/outlet-rejections/' . $r->id,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dailyCounts(string $from, string $to, int $warehouseId): array
    {
        $days = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $days[$key] = [
                'date' => $key,
                'gr' => 0,
                'transfer' => 0,
                'retail' => 0,
                'do' => 0,
                'adjustment' => 0,
            ];
            $cursor->addDay();
        }

        $grRows = DB::table('food_good_receives')
            ->select('receive_date as d', DB::raw('COUNT(*) as c'))
            ->whereBetween('receive_date', [$from, $to])
            ->groupBy('receive_date')
            ->get();
        foreach ($grRows as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['gr'] = (int) $r->c;
            }
        }

        $tfQ = DB::table('warehouse_transfers')
            ->select('transfer_date as d', DB::raw('COUNT(*) as c'))
            ->whereBetween('transfer_date', [$from, $to])
            ->when($warehouseId > 0, function ($q) use ($warehouseId) {
                $q->where(function ($qq) use ($warehouseId) {
                    $qq->where('warehouse_from_id', $warehouseId)
                        ->orWhere('warehouse_to_id', $warehouseId);
                });
            })
            ->groupBy('transfer_date')
            ->get();
        foreach ($tfQ as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['transfer'] = (int) $r->c;
            }
        }

        $retailRows = DB::table('retail_warehouse_food')
            ->select('transaction_date as d', DB::raw('COUNT(*) as c'))
            ->whereNull('deleted_at')
            ->whereBetween('transaction_date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->groupBy('transaction_date')
            ->get();
        foreach ($retailRows as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['retail'] = (int) $r->c;
            }
        }

        $doRows = DB::table('delivery_orders as do')
            ->leftJoin('packing_lists as pl', 'pl.id', '=', 'do.packing_list_id')
            ->select(DB::raw('DATE(do.created_at) as d'), DB::raw('COUNT(*) as c'))
            ->whereDate('do.created_at', '>=', $from)
            ->whereDate('do.created_at', '<=', $to)
            ->when($warehouseId > 0, fn ($q) => $q->where('pl.warehouse_id', $warehouseId))
            ->groupBy(DB::raw('DATE(do.created_at)'))
            ->get();
        foreach ($doRows as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['do'] = (int) $r->c;
            }
        }

        $adjRows = DB::table('food_inventory_adjustments')
            ->select('date as d', DB::raw('COUNT(*) as c'))
            ->whereBetween('date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->groupBy('date')
            ->get();
        foreach ($adjRows as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['adjustment'] = (int) $r->c;
            }
        }

        return array_values($days);
    }
}
