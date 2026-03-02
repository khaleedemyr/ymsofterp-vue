<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MacAnomalyTrackingController extends Controller
{
    public function index()
    {
        return Inertia::render('MacAnomalyTracking/Index');
    }

    public function options(Request $request)
    {
        $idOutlet = (int) $request->input('id_outlet');
        $warehouseOutletId = (int) $request->input('warehouse_outlet_id');

        if (!$idOutlet) {
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
        ]);
    }

    public function data(Request $request)
    {
        $idOutlet = (int) $request->input('id_outlet');
        $warehouseOutletId = (int) $request->input('warehouse_outlet_id');
        $itemId = (int) $request->input('item_id');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 20);

        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        if (!$idOutlet) {
            return response()->json([
                'status' => 'error',
                'message' => 'Outlet wajib dipilih',
            ], 422);
        }

        if (!$warehouseOutletId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Warehouse outlet wajib dipilih',
            ], 422);
        }

        if (!$itemId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Barang wajib dipilih',
            ], 422);
        }

        $inventoryItemId = DB::table('outlet_food_inventory_items')
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

        $warehouse = DB::table('warehouse_outlets')
            ->where('id', $warehouseOutletId)
            ->select('id', 'name')
            ->first();

        $historyBaseQuery = DB::table('outlet_food_inventory_cost_histories')
            ->where('id_outlet', $idOutlet)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('inventory_item_id', $inventoryItemId)
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
                'mac_changes' => [],
                'summary' => [
                    'total_updates' => 0,
                    'current_mac' => null,
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
                'message' => 'Belum ada histori MAC untuk kombinasi outlet, warehouse, dan barang ini',
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
                'warehouse_outlet_id' => $warehouseOutletId,
                'warehouse_name' => $warehouse->name ?? '-',
            ],
            'mac_changes' => $macChanges,
            'summary' => [
                'total_updates' => $totalUpdates,
                'current_mac' => number_format((float) ($latest->new_cost ?? 0), 4, '.', ''),
                'previous_mac' => $previous ? number_format((float) ($previous->new_cost ?? 0), 4, '.', '') : null,
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

            $pairs = [];

            switch ($type) {
                case 'good_receive_outlet':
                    $pairs = DB::table('outlet_food_good_receives')
                        ->whereIn('id', $ids)
                        ->select('id', 'number as transaction_number')
                        ->get();
                    break;

                case 'good_receive_supplier':
                case 'good_receive_outlet_supplier':
                    $pairs = DB::table('good_receive_outlet_suppliers')
                        ->whereIn('id', $ids)
                        ->select('id', 'gr_number as transaction_number')
                        ->get();
                    break;

                case 'outlet_transfer':
                    $pairs = DB::table('outlet_transfers')
                        ->whereIn('id', $ids)
                        ->select('id', 'transfer_number as transaction_number')
                        ->get();
                    break;

                case 'internal_warehouse_transfer':
                    $pairs = DB::table('internal_warehouse_transfers')
                        ->whereIn('id', $ids)
                        ->select('id', 'transfer_number as transaction_number')
                        ->get();
                    break;

                case 'warehouse_transfer':
                    $pairs = DB::table('warehouse_transfers')
                        ->whereIn('id', $ids)
                        ->select('id', 'transfer_number as transaction_number')
                        ->get();
                    break;

                case 'stock_opname':
                    $pairs = DB::table('outlet_stock_opnames')
                        ->whereIn('id', $ids)
                        ->select('id', 'opname_number as transaction_number')
                        ->get();
                    break;

                case 'warehouse_stock_opname':
                    $pairs = DB::table('warehouse_stock_opnames')
                        ->whereIn('id', $ids)
                        ->select('id', 'opname_number as transaction_number')
                        ->get();
                    break;

                default:
                    $pairs = [];
                    break;
            }

            foreach ($pairs as $pair) {
                $map[$type . ':' . $pair->id] = $pair->transaction_number;
            }
        }

        return $map;
    }
}
