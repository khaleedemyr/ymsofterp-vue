<?php

namespace App\Http\Controllers;

use App\Services\WarehouseMacAnomalyDetectionService;
use App\Support\MacAnomalyHistoryCutoff;
use App\Support\WarehouseMacAnomalyReferenceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WarehouseMacAnomalyTrackingController extends Controller
{
    public function __construct(
        private WarehouseMacAnomalyDetectionService $anomalyDetection,
    ) {}

    public function index()
    {
        return Inertia::render('WarehouseMacAnomalyTracking/Index', [
            'referenceModules' => WarehouseMacAnomalyReferenceRegistry::moduleCatalog(),
            'historyCutoffDate' => MacAnomalyHistoryCutoff::DATE,
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'min_spike_percent' => ['nullable', 'numeric', 'min:0'],
            'spike_multiplier' => ['nullable', 'numeric', 'min:1.1'],
            'max_mac' => ['nullable', 'numeric', 'min:0'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:negative_mac,negative_new_cost,spike_percent,spike_multiplier,absolute_high,current_stock'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        try {
            $result = $this->anomalyDetection->scan($validated);

            return response()->json([
                'status' => 'success',
                ...$result,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memindai anomali MAC warehouse: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function referenceModules()
    {
        return response()->json([
            'status' => 'success',
            'modules' => WarehouseMacAnomalyReferenceRegistry::moduleCatalog(),
        ]);
    }

    public function options(Request $request)
    {
        $warehouseId = (int) $request->input('warehouse_id');

        $warehouses = DB::table('warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $itemsQuery = DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fii', 's.inventory_item_id', '=', 'fii.id')
            ->join('items as i', 'fii.item_id', '=', 'i.id')
            ->select('i.id as item_id', 'i.name as item_name', 'i.sku as item_code')
            ->distinct();

        if ($warehouseId) {
            $itemsQuery->where('s.warehouse_id', $warehouseId);
        }

        $items = $itemsQuery
            ->orderBy('i.name')
            ->get();

        return response()->json([
            'status' => 'success',
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    public function data(Request $request)
    {
        $warehouseId = (int) $request->input('warehouse_id');
        $itemId = (int) $request->input('item_id');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 20);

        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        if (!$warehouseId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Warehouse wajib dipilih',
            ], 422);
        }

        if (!$itemId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Barang wajib dipilih',
            ], 422);
        }

        $inventoryItemId = DB::table('food_inventory_items')
            ->where('item_id', $itemId)
            ->value('id');

        if (!$inventoryItemId) {
            return response()->json([
                'status' => 'success',
                'mac_changes' => [],
                'summary' => [
                    'total_updates' => 0,
                    'current_mac' => null,
                    'previous_mac' => null,
                    'last_update_date' => null,
                    'current_qty_small' => '0.00',
                    'current_qty_small_unit' => null,
                ],
                'message' => 'Inventory item tidak ditemukan untuk barang ini',
            ]);
        }

        $item = DB::table('items')
            ->leftJoin('units as u', 'items.small_unit_id', '=', 'u.id')
            ->where('items.id', $itemId)
            ->select('items.id', 'items.name', 'items.sku', 'u.name as small_unit_name')
            ->first();

        $warehouse = DB::table('warehouses')
            ->where('id', $warehouseId)
            ->select('id', 'name')
            ->first();

        $historyBaseQuery = DB::table('food_inventory_cost_histories')
            ->where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $inventoryItemId)
            ->where('date', '>=', MacAnomalyHistoryCutoff::DATE)
            ->orderByDesc('date')
            ->orderByDesc('id');

        $totalUpdates = (clone $historyBaseQuery)->count();
        $lastPage = max(1, (int) ceil($totalUpdates / $perPage));
        $page = min($page, $lastPage);

        $historyRows = (clone $historyBaseQuery)
            ->forPage($page, $perPage)
            ->get();

        $latestTwo = (clone $historyBaseQuery)
            ->limit(2)
            ->get();

        $transactionNumberMap = $this->buildTransactionNumberMap($historyRows);

        $stock = DB::table('food_inventory_stocks')
            ->where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $inventoryItemId)
            ->first();
        $qtySmall = (float) ($stock->qty_small ?? 0);
        $currentMac = $stock ? (float) ($stock->last_cost_small ?? 0) : null;

        if ($historyRows->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'item' => [
                    'item_id' => $itemId,
                    'item_name' => $item->name ?? '-',
                    'item_code' => $item->sku ?? null,
                    'small_unit_name' => $item->small_unit_name ?? null,
                ],
                'warehouse' => [
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $warehouse->name ?? '-',
                ],
                'mac_changes' => [],
                'summary' => [
                    'total_updates' => 0,
                    'current_mac' => $currentMac !== null ? number_format($currentMac, 4, '.', '') : null,
                    'previous_mac' => null,
                    'last_update_date' => null,
                    'current_qty_small' => number_format($qtySmall, 2, '.', ''),
                    'current_qty_small_unit' => $item->small_unit_name ?? null,
                ],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
                'message' => 'Belum ada histori MAC untuk kombinasi warehouse dan barang ini',
            ]);
        }

        $macChanges = $historyRows->map(function ($row) use ($transactionNumberMap) {
            $oldCost = (float) ($row->old_cost ?? 0);
            $newCost = (float) ($row->new_cost ?? 0);
            $changePercent = null;
            $referenceKey = ($row->reference_type ?? '') . ':' . ($row->reference_id ?? '');
            $transactionNumber = $transactionNumberMap[$referenceKey] ?? null;

            if ($oldCost > 0) {
                $changePercent = (($newCost - $oldCost) / $oldCost) * 100;
            }

            return [
                'history_id' => (int) $row->id,
                'date' => $row->date,
                'created_at' => $row->created_at,
                'old_cost' => number_format($oldCost, 4, '.', ''),
                'new_cost' => number_format($newCost, 4, '.', ''),
                'mac' => number_format((float) ($row->mac ?? 0), 4, '.', ''),
                'change_percent' => $changePercent !== null ? number_format($changePercent, 2, '.', '') : null,
                'type' => $row->type,
                'reference_type' => $row->reference_type,
                'reference_id' => $row->reference_id,
                'reference_label' => WarehouseMacAnomalyReferenceRegistry::labelFor($row->reference_type),
                'source_url' => WarehouseMacAnomalyReferenceRegistry::sourceUrl(
                    $row->reference_type,
                    $row->reference_id ? (int) $row->reference_id : null
                ),
                'transaction_number' => $transactionNumber,
            ];
        })->values();

        $latest = $latestTwo->first();
        $previous = $latestTwo->count() > 1 ? $latestTwo->get(1) : null;

        return response()->json([
            'status' => 'success',
            'item' => [
                'item_id' => $itemId,
                'item_name' => $item->name ?? '-',
                'item_code' => $item->sku ?? null,
                'small_unit_name' => $item->small_unit_name ?? null,
            ],
            'warehouse' => [
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouse->name ?? '-',
            ],
            'mac_changes' => $macChanges,
            'summary' => [
                'total_updates' => $totalUpdates,
                'current_mac' => number_format($this->historyWeightedMac($latest), 4, '.', ''),
                'previous_mac' => $previous ? number_format($this->historyWeightedMac($previous), 4, '.', '') : null,
                'last_update_date' => $latest->date,
                'current_qty_small' => number_format($qtySmall, 2, '.', ''),
                'current_qty_small_unit' => $item->small_unit_name ?? null,
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalUpdates,
                'last_page' => $lastPage,
            ],
        ]);
    }

    private function buildTransactionNumberMap($historyRows): array
    {
        $map = [];
        $idsByType = [];
        foreach ($historyRows as $row) {
            if (empty($row->reference_type) || empty($row->reference_id)) {
                continue;
            }
            $idsByType[$row->reference_type][] = (int) $row->reference_id;
        }

        foreach ($idsByType as $type => $ids) {
            $ids = array_values(array_unique(array_filter($ids)));
            if (empty($ids)) {
                continue;
            }

            $pairs = match ($type) {
                'good_receive' => DB::table('food_good_receives')->whereIn('id', $ids)->select('id', 'gr_number as transaction_number')->get(),
                'warehouse_transfer' => DB::table('warehouse_transfers')->whereIn('id', $ids)->select('id', 'transfer_number as transaction_number')->get(),
                'butcher_process' => DB::table('butcher_processes')->whereIn('id', $ids)->select('id', 'number as transaction_number')->get(),
                'retail_warehouse_food' => DB::table('retail_warehouse_food')->whereIn('id', $ids)->select('id', 'retail_number as transaction_number')->get(),
                'mk_production' => DB::table('mk_productions')->whereIn('id', $ids)->select('id', DB::raw("CONCAT('MK-', id) as transaction_number"))->get(),
                'outlet_rejection' => DB::table('outlet_rejections')->whereIn('id', $ids)->select('id', 'rejection_number as transaction_number')->get(),
                'stock_adjustment' => DB::table('food_inventory_adjustments')->whereIn('id', $ids)->select('id', 'number as transaction_number')->get(),
                default => collect(),
            };

            foreach ($pairs as $pair) {
                $map[$type . ':' . $pair->id] = $pair->transaction_number;
            }
        }

        return $map;
    }

    private function historyWeightedMac(object $row): float
    {
        $mac = (float) ($row->mac ?? 0);

        return $mac > 0 ? $mac : (float) ($row->new_cost ?? 0);
    }
}
