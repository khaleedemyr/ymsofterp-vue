<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OutletRevenueRecapService
{
    public function buildRecap(string $dateFrom, string $dateTo): array
    {
        $salesByOutlet = $this->aggregateSalesByOutlet($dateFrom, $dateTo);

        $outlets = DB::table('tbl_data_outlet as o')
            ->leftJoin('regions as r', 'r.id', '=', 'o.region_id')
            ->where('o.status', 'A')
            ->where('o.is_outlet', 1)
            ->orderBy('r.name')
            ->orderBy('o.nama_outlet')
            ->select(
                'o.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                'o.qr_code',
                'o.region_id',
                DB::raw('COALESCE(r.name, \'Tanpa Region\') as region_name')
            )
            ->get();

        $grouped = [];
        foreach ($outlets as $outlet) {
            $regionKey = (string) ($outlet->region_id ?? '0');
            if (! isset($grouped[$regionKey])) {
                $grouped[$regionKey] = [
                    'region_id' => $outlet->region_id ? (int) $outlet->region_id : null,
                    'region_name' => (string) $outlet->region_name,
                    'rows' => [],
                    'subtotal' => $this->emptyMetrics(),
                ];
            }

            $metrics = $salesByOutlet[$outlet->qr_code] ?? $this->emptyMetrics();
            $row = [
                'outlet_id' => (int) $outlet->outlet_id,
                'outlet_name' => (string) $outlet->outlet_name,
                ...$metrics,
            ];

            $grouped[$regionKey]['rows'][] = $row;
            $grouped[$regionKey]['subtotal'] = $this->sumMetrics(
                $grouped[$regionKey]['subtotal'],
                $metrics
            );
        }

        $groups = array_values($grouped);
        $grandTotals = $this->emptyMetrics();
        foreach ($groups as $group) {
            $grandTotals = $this->sumMetrics($grandTotals, $group['subtotal']);
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'groups' => $groups,
            'totals' => $grandTotals,
        ];
    }

    private function aggregateSalesByOutlet(string $dateFrom, string $dateTo): array
    {
        $rows = DB::table('orders')
            ->join('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
            ->where('o.status', 'A')
            ->where('o.is_outlet', 1)
            ->whereDate('orders.created_at', '>=', $dateFrom)
            ->whereDate('orders.created_at', '<=', $dateTo)
            ->groupBy('orders.kode_outlet')
            ->select(
                'orders.kode_outlet',
                DB::raw('SUM(COALESCE(orders.total, 0)) as total_sales'),
                DB::raw('SUM(COALESCE(orders.discount, 0) + COALESCE(orders.manual_discount_amount, 0)) as discount'),
                DB::raw('SUM(COALESCE(orders.service, 0)) as service_charge'),
                DB::raw('SUM(COALESCE(orders.pb1, 0)) as pb1'),
                DB::raw('SUM(COALESCE(orders.commfee, 0)) as commfee'),
                DB::raw('SUM(COALESCE(orders.pax, 0)) as total_pax'),
                DB::raw('SUM(COALESCE(orders.grand_total, 0)) as grand_total')
            )
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->kode_outlet] = $this->formatMetricsRow($row);
        }

        return $map;
    }

    private function formatMetricsRow(object $row): array
    {
        $totalPax = (float) ($row->total_pax ?? 0);
        $grandTotal = (float) ($row->grand_total ?? 0);

        return [
            'total_sales' => (float) ($row->total_sales ?? 0),
            'discount' => (float) ($row->discount ?? 0),
            'service_charge' => (float) ($row->service_charge ?? 0),
            'pb1' => (float) ($row->pb1 ?? 0),
            'commfee' => (float) ($row->commfee ?? 0),
            'total_pax' => $totalPax,
            'grand_total' => $grandTotal,
            'avg_check' => $totalPax > 0 ? (float) round($grandTotal / $totalPax) : 0.0,
        ];
    }

    private function emptyMetrics(): array
    {
        return [
            'total_sales' => 0.0,
            'discount' => 0.0,
            'service_charge' => 0.0,
            'pb1' => 0.0,
            'commfee' => 0.0,
            'total_pax' => 0.0,
            'grand_total' => 0.0,
            'avg_check' => 0.0,
        ];
    }

    private function sumMetrics(array $a, array $b): array
    {
        $totalPax = (float) $a['total_pax'] + (float) $b['total_pax'];
        $grandTotal = (float) ($a['grand_total'] ?? 0) + (float) ($b['grand_total'] ?? 0);

        return [
            'total_sales' => (float) $a['total_sales'] + (float) $b['total_sales'],
            'discount' => (float) $a['discount'] + (float) $b['discount'],
            'service_charge' => (float) $a['service_charge'] + (float) $b['service_charge'],
            'pb1' => (float) $a['pb1'] + (float) $b['pb1'],
            'commfee' => (float) $a['commfee'] + (float) $b['commfee'],
            'total_pax' => $totalPax,
            'grand_total' => $grandTotal,
            'avg_check' => $totalPax > 0 ? (float) round($grandTotal / $totalPax) : 0.0,
        ];
    }
}
