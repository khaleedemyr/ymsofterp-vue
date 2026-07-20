<?php

namespace App\Http\Controllers;

use App\Support\MacAnomalyReferenceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OutletMacTrackingController extends Controller
{
    public function index()
    {
        return Inertia::render('OutletMacTracking/Index', [
            'referenceTypes' => MacAnomalyReferenceRegistry::moduleCatalog(),
        ]);
    }

    public function options(Request $request)
    {
        $idOutlet = (int) $request->input('id_outlet');
        $warehouseOutletId = (int) $request->input('warehouse_outlet_id');

        if (! $idOutlet) {
            return response()->json([
                'status' => 'error',
                'message' => 'Outlet wajib dipilih',
            ], 422);
        }

        $warehouses = DB::table('warehouse_outlets')
            ->where('outlet_id', $idOutlet)
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $itemsQuery = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as ofii', 's.inventory_item_id', '=', 'ofii.id')
            ->join('items as i', 'ofii.item_id', '=', 'i.id')
            ->where('s.id_outlet', $idOutlet)
            ->select('i.id as item_id', 'i.name as item_name', 'i.sku as item_code')
            ->distinct();

        if ($warehouseOutletId) {
            $itemsQuery->where('s.warehouse_outlet_id', $warehouseOutletId);
        }

        $items = $itemsQuery
            ->orderBy('i.name')
            ->get();

        return response()->json([
            'status' => 'success',
            'warehouses' => $warehouses,
            'items' => $items,
            'reference_types' => MacAnomalyReferenceRegistry::moduleCatalog(),
        ]);
    }

    public function data(Request $request)
    {
        $idOutlet = (int) $request->input('id_outlet');
        $warehouseOutletId = (int) $request->input('warehouse_outlet_id');
        $itemId = (int) $request->input('item_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $referenceType = trim((string) $request->input('reference_type', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 20);

        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        if (! $idOutlet) {
            return response()->json([
                'status' => 'error',
                'message' => 'Outlet wajib dipilih',
            ], 422);
        }

        if (! $warehouseOutletId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Warehouse outlet wajib dipilih',
            ], 422);
        }

        if (! $itemId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Barang wajib dipilih',
            ], 422);
        }

        $inventoryItemId = DB::table('outlet_food_inventory_items')
            ->where('item_id', $itemId)
            ->value('id');

        $item = DB::table('items')
            ->leftJoin('units as u', 'items.small_unit_id', '=', 'u.id')
            ->where('items.id', $itemId)
            ->select('items.id', 'items.name', 'items.sku', 'u.name as small_unit_name')
            ->first();

        $warehouse = DB::table('warehouse_outlets')
            ->where('id', $warehouseOutletId)
            ->select('id', 'name')
            ->first();

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $idOutlet)
            ->select('id_outlet', 'nama_outlet')
            ->first();

        $emptySummary = [
            'total_updates' => 0,
            'current_mac' => null,
            'previous_mac' => null,
            'last_update_date' => null,
            'current_qty_small' => '0.00',
            'current_qty_small_unit' => $item->small_unit_name ?? null,
        ];

        if (! $inventoryItemId) {
            return response()->json([
                'status' => 'success',
                'item' => [
                    'item_id' => $itemId,
                    'item_name' => $item->name ?? '-',
                    'item_code' => $item->sku ?? null,
                    'small_unit_name' => $item->small_unit_name ?? null,
                ],
                'warehouse' => [
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'warehouse_name' => $warehouse->name ?? '-',
                ],
                'outlet' => [
                    'id_outlet' => $idOutlet,
                    'outlet_name' => $outlet->nama_outlet ?? '-',
                ],
                'mac_changes' => [],
                'summary' => $emptySummary,
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
                'message' => 'Inventory item tidak ditemukan untuk barang ini',
            ]);
        }

        $historyBaseQuery = DB::table('outlet_food_inventory_cost_histories')
            ->where('id_outlet', $idOutlet)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('inventory_item_id', $inventoryItemId)
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->when($referenceType !== '', fn ($q) => $q->where('reference_type', $referenceType))
            ->orderByDesc('date')
            ->orderByDesc('id');

        $totalUpdates = (clone $historyBaseQuery)->count();
        $lastPage = max(1, (int) ceil($totalUpdates / $perPage));
        $page = min($page, $lastPage);

        $historyRows = (clone $historyBaseQuery)
            ->forPage($page, $perPage)
            ->get();

        $latestTwo = DB::table('outlet_food_inventory_cost_histories')
            ->where('id_outlet', $idOutlet)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('inventory_item_id', $inventoryItemId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        $transactionNumberMap = $this->buildTransactionNumberMap($historyRows);

        $qtySmall = (float) (DB::table('outlet_food_inventory_stocks')
            ->where('id_outlet', $idOutlet)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('inventory_item_id', $inventoryItemId)
            ->value('qty_small') ?? 0);

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
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'warehouse_name' => $warehouse->name ?? '-',
                ],
                'outlet' => [
                    'id_outlet' => $idOutlet,
                    'outlet_name' => $outlet->nama_outlet ?? '-',
                ],
                'mac_changes' => [],
                'summary' => [
                    'total_updates' => 0,
                    'current_mac' => $latestTwo->isNotEmpty()
                        ? number_format($this->historyWeightedMac($latestTwo->first()), 4, '.', '')
                        : null,
                    'previous_mac' => $latestTwo->count() > 1
                        ? number_format($this->historyWeightedMac($latestTwo->get(1)), 4, '.', '')
                        : null,
                    'last_update_date' => $latestTwo->first()->date ?? null,
                    'current_qty_small' => number_format($qtySmall, 2, '.', ''),
                    'current_qty_small_unit' => $item->small_unit_name ?? null,
                ],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
                'message' => 'Belum ada histori MAC untuk filter yang dipilih',
            ]);
        }

        $macChanges = $historyRows->map(function ($row) use ($transactionNumberMap) {
            $oldCost = (float) ($row->old_cost ?? 0);
            $newCost = (float) ($row->new_cost ?? 0);
            $mac = $this->historyWeightedMac($row);
            $changePercent = null;
            $refType = (string) ($row->reference_type ?? '');
            $refId = $row->reference_id ? (int) $row->reference_id : null;
            $referenceKey = $refType.':'.($refId ?? '');
            $transactionNumber = $transactionNumberMap[$referenceKey] ?? null;

            if ($oldCost > 0) {
                $changePercent = (($mac - $oldCost) / $oldCost) * 100;
            }

            return [
                'history_id' => (int) $row->id,
                'date' => $row->date,
                'created_at' => $row->created_at,
                'old_cost' => number_format($oldCost, 4, '.', ''),
                'new_cost' => number_format($newCost, 4, '.', ''),
                'mac' => number_format($mac, 4, '.', ''),
                'change_percent' => $changePercent !== null ? number_format($changePercent, 2, '.', '') : null,
                'type' => $row->type,
                'reference_type' => $refType !== '' ? $refType : null,
                'reference_id' => $refId,
                'transaction_number' => $transactionNumber,
                'module_label' => MacAnomalyReferenceRegistry::labelFor($refType !== '' ? $refType : null),
                'source_url' => MacAnomalyReferenceRegistry::sourceUrl(
                    $refType !== '' ? $refType : null,
                    $refId
                ),
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
                'warehouse_outlet_id' => $warehouseOutletId,
                'warehouse_name' => $warehouse->name ?? '-',
            ],
            'outlet' => [
                'id_outlet' => $idOutlet,
                'outlet_name' => $outlet->nama_outlet ?? '-',
            ],
            'mac_changes' => $macChanges,
            'summary' => [
                'total_updates' => $totalUpdates,
                'current_mac' => $latest ? number_format($this->historyWeightedMac($latest), 4, '.', '') : null,
                'previous_mac' => $previous ? number_format($this->historyWeightedMac($previous), 4, '.', '') : null,
                'last_update_date' => $latest->date ?? null,
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

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $historyRows
     * @return array<string, string|null>
     */
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
            if ($ids === []) {
                continue;
            }

            $pairs = match ($type) {
                'good_receive_outlet' => DB::table('outlet_food_good_receives')
                    ->whereIn('id', $ids)
                    ->select('id', 'number as transaction_number')
                    ->get(),
                'good_receive_supplier', 'good_receive_outlet_supplier' => DB::table('good_receive_outlet_suppliers')
                    ->whereIn('id', $ids)
                    ->select('id', 'gr_number as transaction_number')
                    ->get(),
                'outlet_transfer' => DB::table('outlet_transfers')
                    ->whereIn('id', $ids)
                    ->select('id', 'transfer_number as transaction_number')
                    ->get(),
                'internal_warehouse_transfer' => DB::table('internal_warehouse_transfers')
                    ->whereIn('id', $ids)
                    ->select('id', 'transfer_number as transaction_number')
                    ->get(),
                'warehouse_transfer' => DB::table('warehouse_transfers')
                    ->whereIn('id', $ids)
                    ->select('id', 'transfer_number as transaction_number')
                    ->get(),
                'stock_opname' => DB::table('outlet_stock_opnames')
                    ->whereIn('id', $ids)
                    ->select('id', 'opname_number as transaction_number')
                    ->get(),
                'warehouse_stock_opname' => DB::table('warehouse_stock_opnames')
                    ->whereIn('id', $ids)
                    ->select('id', 'opname_number as transaction_number')
                    ->get(),
                'outlet_stock_adjustment' => DB::table('outlet_food_inventory_adjustments')
                    ->whereIn('id', $ids)
                    ->select('id', 'number as transaction_number')
                    ->get(),
                'retail_food' => DB::table('retail_food')
                    ->whereIn('id', $ids)
                    ->select('id', 'retail_number as transaction_number')
                    ->get(),
                'serial_receive', 'serial_receive_rollback' => DB::table('outlet_serial_receive_headers')
                    ->whereIn('id', $ids)
                    ->select('id', 'number as transaction_number')
                    ->get(),
                'outlet_internal_use_waste' => DB::table('outlet_internal_use_waste_headers')
                    ->whereIn('id', $ids)
                    ->select('id', 'number as transaction_number')
                    ->get(),
                'outlet_food_return' => DB::table('outlet_food_returns')
                    ->whereIn('id', $ids)
                    ->select('id', 'return_number as transaction_number')
                    ->get(),
                'outlet_rejection' => DB::table('outlet_rejections')
                    ->whereIn('id', $ids)
                    ->select('id', 'rejection_number as transaction_number')
                    ->get(),
                'outlet_wip_production' => DB::table('outlet_wip_production_headers')
                    ->whereIn('id', $ids)
                    ->select('id', 'number as transaction_number')
                    ->get(),
                default => collect(),
            };

            foreach ($pairs as $pair) {
                $map[$type.':'.$pair->id] = $pair->transaction_number;
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
