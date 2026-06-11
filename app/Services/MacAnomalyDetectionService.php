<?php

namespace App\Services;

use App\Support\MacAnomalyReferenceRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MacAnomalyDetectionService
{
    /**
     * @param  array{
     *   id_outlet?: int,
     *   warehouse_outlet_id?: int,
     *   date_from?: string,
     *   date_to?: string,
     *   min_spike_percent?: float,
     *   spike_multiplier?: float,
     *   max_mac?: float,
     *   types?: list<string>,
     *   page?: int,
     *   per_page?: int,
     * }  $filters
     * @return array{
     *   anomalies: list<array<string, mixed>>,
     *   summary: array<string, mixed>,
     *   module_breakdown: list<array<string, mixed>>,
     *   pagination: array<string, int>,
     * }
     */
    public function scan(array $filters): array
    {
        $idOutlet = (int) ($filters['id_outlet'] ?? 0);
        $warehouseOutletId = (int) ($filters['warehouse_outlet_id'] ?? 0);
        $dateTo = (string) ($filters['date_to'] ?? Carbon::now()->format('Y-m-d'));
        $dateFrom = (string) ($filters['date_from'] ?? Carbon::parse($dateTo)->subDays(30)->format('Y-m-d'));
        $minSpikePercent = max(0, (float) ($filters['min_spike_percent'] ?? 100));
        $spikeMultiplier = max(1.1, (float) ($filters['spike_multiplier'] ?? 5));
        $maxMac = max(0, (float) ($filters['max_mac'] ?? 10_000_000));
        $types = $filters['types'] ?? ['negative_mac', 'negative_new_cost', 'spike_percent', 'spike_multiplier', 'absolute_high'];
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));

        $historyAnomalies = $this->scanHistoryAnomalies(
            $idOutlet,
            $warehouseOutletId,
            $dateFrom,
            $dateTo,
            $minSpikePercent,
            $spikeMultiplier,
            $maxMac,
            $types,
        );

        $stockAnomalies = in_array('current_stock', $types, true)
            ? $this->scanCurrentStockAnomalies($idOutlet, $warehouseOutletId, $maxMac)
            : [];

        $all = array_merge($historyAnomalies, $stockAnomalies);
        usort($all, fn ($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        $moduleBreakdown = $this->buildModuleBreakdown($all);
        $typeBreakdown = $this->buildTypeBreakdown($all);

        $total = count($all);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($all, $offset, $perPage);

        $this->attachTransactionNumbers($paged);

        return [
            'anomalies' => $paged,
            'summary' => [
                'total' => $total,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'type_breakdown' => $typeBreakdown,
                'filters' => [
                    'min_spike_percent' => $minSpikePercent,
                    'spike_multiplier' => $spikeMultiplier,
                    'max_mac' => $maxMac,
                    'types' => $types,
                ],
            ],
            'module_breakdown' => $moduleBreakdown,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * @param  list<string>  $types
     * @return list<array<string, mixed>>
     */
    private function scanHistoryAnomalies(
        int $idOutlet,
        int $warehouseOutletId,
        string $dateFrom,
        string $dateTo,
        float $minSpikePercent,
        float $spikeMultiplier,
        float $maxMac,
        array $types,
    ): array {
        $bindings = [$dateFrom, $dateTo];
        $where = 'h.date BETWEEN ? AND ?';

        if ($idOutlet > 0) {
            $where .= ' AND h.id_outlet = ?';
            $bindings[] = $idOutlet;
        }
        if ($warehouseOutletId > 0) {
            $where .= ' AND h.warehouse_outlet_id = ?';
            $bindings[] = $warehouseOutletId;
        }

        $sql = "
            SELECT
                h.id AS history_id,
                h.id_outlet,
                h.warehouse_outlet_id,
                h.inventory_item_id,
                h.date,
                h.created_at,
                h.old_cost,
                h.new_cost,
                h.mac,
                h.type,
                h.reference_type,
                h.reference_id,
                o.nama_outlet AS outlet_name,
                wo.name AS warehouse_name,
                i.id AS item_id,
                i.name AS item_name,
                i.sku AS item_code,
                LAG(h.mac) OVER (
                    PARTITION BY h.id_outlet, h.warehouse_outlet_id, h.inventory_item_id
                    ORDER BY h.date, h.id
                ) AS prev_mac
            FROM outlet_food_inventory_cost_histories h
            LEFT JOIN tbl_data_outlet o ON o.id_outlet = h.id_outlet
            LEFT JOIN warehouse_outlets wo ON wo.id = h.warehouse_outlet_id
            LEFT JOIN outlet_food_inventory_items ofii ON ofii.id = h.inventory_item_id
            LEFT JOIN items i ON i.id = ofii.item_id
            WHERE {$where}
        ";

        $rows = DB::select($sql, $bindings);
        $anomalies = [];

        foreach ($rows as $row) {
            $mac = (float) ($row->mac ?? 0);
            $newCost = (float) ($row->new_cost ?? 0);
            $prevMac = $row->prev_mac !== null ? (float) $row->prev_mac : null;
            $flags = [];

            if (in_array('negative_mac', $types, true) && $mac < 0) {
                $flags[] = 'negative_mac';
            }
            if (in_array('negative_new_cost', $types, true) && $newCost < 0) {
                $flags[] = 'negative_new_cost';
            }
            if (in_array('absolute_high', $types, true) && $maxMac > 0 && $mac > $maxMac) {
                $flags[] = 'absolute_high';
            }
            if ($prevMac !== null && $prevMac > 0) {
                $changePct = (($mac - $prevMac) / $prevMac) * 100;
                if (in_array('spike_percent', $types, true) && abs($changePct) >= $minSpikePercent) {
                    $flags[] = 'spike_percent';
                }
                if (in_array('spike_multiplier', $types, true) && $mac >= $prevMac * $spikeMultiplier) {
                    $flags[] = 'spike_multiplier';
                }
            }

            if (empty($flags)) {
                continue;
            }

            $changePct = ($prevMac !== null && $prevMac > 0)
                ? round((($mac - $prevMac) / $prevMac) * 100, 2)
                : null;

            $refType = $row->reference_type ?? null;
            $refId = $row->reference_id ? (int) $row->reference_id : null;

            $anomalies[] = [
                'history_id' => (int) $row->history_id,
                'anomaly_types' => $flags,
                'anomaly_labels' => array_map(fn ($f) => $this->typeLabel($f), $flags),
                'date' => $row->date,
                'created_at' => $row->created_at,
                'id_outlet' => (int) $row->id_outlet,
                'outlet_name' => $row->outlet_name ?? '-',
                'warehouse_outlet_id' => (int) $row->warehouse_outlet_id,
                'warehouse_name' => $row->warehouse_name ?? '-',
                'item_id' => $row->item_id ? (int) $row->item_id : null,
                'inventory_item_id' => (int) $row->inventory_item_id,
                'item_name' => $row->item_name ?? '-',
                'item_code' => $row->item_code ?? null,
                'old_cost' => number_format((float) ($row->old_cost ?? 0), 4, '.', ''),
                'new_cost' => number_format($newCost, 4, '.', ''),
                'prev_mac' => $prevMac !== null ? number_format($prevMac, 4, '.', '') : null,
                'mac' => number_format($mac, 4, '.', ''),
                'change_percent' => $changePct !== null ? number_format($changePct, 2, '.', '') : null,
                'type' => $row->type,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'reference_label' => MacAnomalyReferenceRegistry::labelFor($refType),
                'module_name' => MacAnomalyReferenceRegistry::moduleFor($refType),
                'fix_hint' => MacAnomalyReferenceRegistry::fixHintFor($refType),
                'source_url' => MacAnomalyReferenceRegistry::sourceUrl($refType, $refId),
                'transaction_number' => null,
                'source' => 'history',
            ];
        }

        return $anomalies;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scanCurrentStockAnomalies(int $idOutlet, int $warehouseOutletId, float $maxMac): array
    {
        $query = DB::table('outlet_food_inventory_stocks as s')
            ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 's.id_outlet')
            ->join('warehouse_outlets as wo', 'wo.id', '=', 's.warehouse_outlet_id')
            ->join('outlet_food_inventory_items as ofii', 'ofii.id', '=', 's.inventory_item_id')
            ->join('items as i', 'i.id', '=', 'ofii.item_id')
            ->where(function ($q) use ($maxMac) {
                $q->where('s.last_cost_small', '<', 0);
                if ($maxMac > 0) {
                    $q->orWhere('s.last_cost_small', '>', $maxMac);
                }
            });

        if ($idOutlet > 0) {
            $query->where('s.id_outlet', $idOutlet);
        }
        if ($warehouseOutletId > 0) {
            $query->where('s.warehouse_outlet_id', $warehouseOutletId);
        }

        $rows = $query->select(
            's.id_outlet',
            'o.nama_outlet as outlet_name',
            's.warehouse_outlet_id',
            'wo.name as warehouse_name',
            'ofii.id as inventory_item_id',
            'i.id as item_id',
            'i.name as item_name',
            'i.sku as item_code',
            's.qty_small',
            's.last_cost_small',
            's.updated_at',
        )->get();

        $anomalies = [];
        foreach ($rows as $row) {
            $mac = (float) ($row->last_cost_small ?? 0);
            $flags = [];
            if ($mac < 0) {
                $flags[] = 'negative_mac';
            }
            if ($maxMac > 0 && $mac > $maxMac) {
                $flags[] = 'absolute_high';
            }

            $anomalies[] = [
                'history_id' => null,
                'anomaly_types' => array_merge($flags, ['current_stock']),
                'anomaly_labels' => array_map(fn ($f) => $this->typeLabel($f), array_merge($flags, ['current_stock'])),
                'date' => $row->updated_at ? Carbon::parse($row->updated_at)->format('Y-m-d') : null,
                'created_at' => $row->updated_at,
                'id_outlet' => (int) $row->id_outlet,
                'outlet_name' => $row->outlet_name ?? '-',
                'warehouse_outlet_id' => (int) $row->warehouse_outlet_id,
                'warehouse_name' => $row->warehouse_name ?? '-',
                'item_id' => (int) $row->item_id,
                'inventory_item_id' => (int) $row->inventory_item_id,
                'item_name' => $row->item_name ?? '-',
                'item_code' => $row->item_code ?? null,
                'old_cost' => null,
                'new_cost' => null,
                'prev_mac' => null,
                'mac' => number_format($mac, 4, '.', ''),
                'change_percent' => null,
                'type' => 'current_stock',
                'reference_type' => null,
                'reference_id' => null,
                'reference_label' => 'Stok saat ini',
                'module_name' => 'Saldo stok outlet',
                'fix_hint' => 'Buka riwayat MAC barang ini untuk cari transaksi penyebab, lalu koreksi via adjustment atau opname.',
                'source_url' => null,
                'transaction_number' => null,
                'qty_small' => number_format((float) ($row->qty_small ?? 0), 2, '.', ''),
                'source' => 'current_stock',
            ];
        }

        return $anomalies;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'negative_mac' => 'MAC minus',
            'negative_new_cost' => 'Biaya masuk minus',
            'spike_percent' => 'Lonjakan % MAC',
            'spike_multiplier' => 'MAC melonjak kelipatan',
            'absolute_high' => 'MAC terlalu besar',
            'current_stock' => 'Anomali stok saat ini',
            default => $type,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $anomalies
     * @return list<array<string, mixed>>
     */
    private function buildModuleBreakdown(array $anomalies): array
    {
        $counts = [];
        foreach ($anomalies as $row) {
            if (($row['source'] ?? '') === 'current_stock') {
                $key = '__current_stock__';
            } else {
                $key = (string) ($row['reference_type'] ?? '__unknown__');
            }
            if (!isset($counts[$key])) {
                $counts[$key] = [
                    'reference_type' => $key === '__current_stock__' ? null : ($key === '__unknown__' ? null : $key),
                    'module_name' => $key === '__current_stock__'
                        ? 'Saldo stok saat ini'
                        : MacAnomalyReferenceRegistry::moduleFor($key === '__unknown__' ? null : $key),
                    'reference_label' => $key === '__current_stock__'
                        ? 'Stok saat ini'
                        : MacAnomalyReferenceRegistry::labelFor($key === '__unknown__' ? null : $key),
                    'fix_hint' => $key === '__current_stock__'
                        ? 'Telusuri riwayat MAC per barang.'
                        : MacAnomalyReferenceRegistry::fixHintFor($key === '__unknown__' ? null : $key),
                    'count' => 0,
                ];
            }
            $counts[$key]['count']++;
        }

        $list = array_values($counts);
        usort($list, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $list;
    }

    /**
     * @param  list<array<string, mixed>>  $anomalies
     * @return array<string, int>
     */
    private function buildTypeBreakdown(array $anomalies): array
    {
        $counts = [];
        foreach ($anomalies as $row) {
            foreach ($row['anomaly_types'] as $type) {
                $counts[$type] = ($counts[$type] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function attachTransactionNumbers(array &$rows): void
    {
        $idsByType = [];
        foreach ($rows as $row) {
            if (empty($row['reference_type']) || empty($row['reference_id'])) {
                continue;
            }
            $idsByType[$row['reference_type']][] = (int) $row['reference_id'];
        }

        $map = [];
        foreach ($idsByType as $type => $ids) {
            $ids = array_values(array_unique(array_filter($ids)));
            if (empty($ids)) {
                continue;
            }

            $pairs = match ($type) {
                'good_receive_outlet' => DB::table('outlet_food_good_receives')->whereIn('id', $ids)->select('id', 'number as transaction_number')->get(),
                'good_receive_supplier', 'good_receive_outlet_supplier' => DB::table('good_receive_outlet_suppliers')->whereIn('id', $ids)->select('id', 'gr_number as transaction_number')->get(),
                'outlet_transfer' => DB::table('outlet_transfers')->whereIn('id', $ids)->select('id', 'transfer_number as transaction_number')->get(),
                'internal_warehouse_transfer' => DB::table('internal_warehouse_transfers')->whereIn('id', $ids)->select('id', 'transfer_number as transaction_number')->get(),
                'stock_opname' => DB::table('outlet_stock_opnames')->whereIn('id', $ids)->select('id', 'opname_number as transaction_number')->get(),
                'outlet_stock_adjustment' => DB::table('outlet_food_inventory_adjustments')->whereIn('id', $ids)->select('id', 'number as transaction_number')->get(),
                'retail_food' => DB::table('retail_food')->whereIn('id', $ids)->select('id', 'retail_number as transaction_number')->get(),
                'serial_receive', 'serial_receive_rollback' => DB::table('outlet_serial_receive_headers')->whereIn('id', $ids)->select('id', 'number as transaction_number')->get(),
                'outlet_internal_use_waste' => DB::table('outlet_internal_use_waste_headers')->whereIn('id', $ids)->select('id', 'number as transaction_number')->get(),
                'outlet_food_return' => DB::table('outlet_food_returns')->whereIn('id', $ids)->select('id', 'return_number as transaction_number')->get(),
                'outlet_rejection' => DB::table('outlet_rejections')->whereIn('id', $ids)->select('id', 'rejection_number as transaction_number')->get(),
                default => collect(),
            };

            foreach ($pairs as $pair) {
                $map[$type . ':' . $pair->id] = $pair->transaction_number;
            }
        }

        foreach ($rows as &$row) {
            if (empty($row['reference_type']) || empty($row['reference_id'])) {
                continue;
            }
            $key = $row['reference_type'] . ':' . $row['reference_id'];
            $row['transaction_number'] = $map[$key] ?? null;
        }
        unset($row);
    }
}
