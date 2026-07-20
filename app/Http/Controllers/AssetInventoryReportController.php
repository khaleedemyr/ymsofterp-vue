<?php

namespace App\Http\Controllers;

use App\Support\AssetOwnership;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\AssetStockPositionExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\AssetInventoryStockService;

class AssetInventoryReportController extends Controller
{
    // ─── WEB (Inertia) ───────────────────────────────────────────────

    public function stockPosition(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('asset_inventory_stocks as s')
            ->join('asset_inventory_items as ai', 's.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->join('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('tbl_data_outlet as oo', 's.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'ai.id as inventory_item_id',
                's.owner_outlet_id',
                DB::raw(AssetOwnership::ownerNameSql('s.owner_outlet_id', 'oo.nama_outlet') . ' as owner_outlet_name'),
                'o.id_outlet as outlet_id',
                'o.nama_outlet as location_outlet_name',
                'wo.id as warehouse_outlet_id',
                'wo.name as warehouse_name',
                's.qty_small',
                's.qty_medium',
                's.qty_large',
                's.value',
                's.last_cost_small',
                's.last_cost_medium',
                's.last_cost_large',
                's.updated_at',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name'
            )
            ->orderByRaw(AssetOwnership::ownerNameSql('s.owner_outlet_id', 'oo.nama_outlet'))
            ->orderBy('o.nama_outlet')
            ->orderBy('wo.name')
            ->orderBy('i.name');

        AssetInventoryStockService::applyOwnerVisibilityForUser($query, $user, 's.owner_outlet_id');

        if ($request->filled('owner_outlet_id')) {
            $query->where('s.owner_outlet_id', $request->owner_outlet_id);
        }
        if ($request->filled('outlet_id')) {
            $query->where('s.outlet_id', $request->outlet_id);
        }
        if ($request->filled('warehouse_outlet_id')) {
            $query->where('s.warehouse_outlet_id', $request->warehouse_outlet_id);
        }

        $data = $query->get();

        $outlets = AssetOwnership::options();
        $locationOutlets = AssetOwnership::locationOptions();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return inertia('AssetInventoryReport/StockPosition', [
            'stocks' => $data,
            'outlets' => $outlets,
            'locationOutlets' => $locationOutlets ?? AssetOwnership::locationOptions(),
            'warehouseOutlets' => $warehouseOutlets,
            'user' => $user,
        ]);
    }

    public function exportStockPosition(Request $request)
    {
        return Excel::download(
            new AssetStockPositionExport(
                $request->owner_outlet_id,
                $request->outlet_id,
                $request->warehouse_outlet_id
            ),
            'asset_stock_position_' . date('Ymd_His') . '.xlsx'
        );
    }

    public function stockCardDetail(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $inventoryItemId = $request->input('inventory_item_id');
        $warehouseOutletId = $request->input('warehouse_outlet_id');
        $ownerOutletId = $request->input('owner_outlet_id');

        if (!$inventoryItemId || !$warehouseOutletId || !$ownerOutletId) {
            return response()->json(['error' => 'inventory_item_id, owner_outlet_id, dan warehouse_outlet_id harus diisi'], 400);
        }

        $now = now();
        $from = $request->input('from', $now->copy()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', $now->copy()->endOfMonth()->format('Y-m-d'));

        $saldoAwal = $this->getSaldoAwal($inventoryItemId, (int) $ownerOutletId, $warehouseOutletId, $from);

        $cards = $this->getCardEntries($inventoryItemId, (int) $ownerOutletId, $warehouseOutletId, $from, $to);

        return response()->json([
            'cards' => $cards->toArray(),
            'saldo_awal' => $saldoAwal,
            'count' => $cards->count(),
        ]);
    }

    // ─── API (JSON for mobile) ───────────────────────────────────────

    public function apiStockPosition(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('asset_inventory_stocks as s')
            ->join('asset_inventory_items as ai', 's.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->join('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('tbl_data_outlet as oo', 's.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'ai.id as inventory_item_id',
                's.owner_outlet_id',
                DB::raw(AssetOwnership::ownerNameSql('s.owner_outlet_id', 'oo.nama_outlet') . ' as owner_outlet_name'),
                'o.id_outlet as outlet_id',
                'o.nama_outlet as location_outlet_name',
                'wo.id as warehouse_outlet_id',
                'wo.name as warehouse_name',
                's.qty_small',
                's.qty_medium',
                's.qty_large',
                's.value',
                's.last_cost_small',
                's.last_cost_medium',
                's.last_cost_large',
                's.updated_at',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name'
            )
            ->orderByRaw(AssetOwnership::ownerNameSql('s.owner_outlet_id', 'oo.nama_outlet'))
            ->orderBy('o.nama_outlet')
            ->orderBy('wo.name')
            ->orderBy('i.name');

        AssetInventoryStockService::applyOwnerVisibilityForUser($query, $user, 's.owner_outlet_id');

        if ($request->filled('owner_outlet_id')) {
            $query->where('s.owner_outlet_id', $request->owner_outlet_id);
        }
        if ($request->filled('outlet_id')) {
            $query->where('s.outlet_id', $request->outlet_id);
        }
        if ($request->filled('warehouse_outlet_id')) {
            $query->where('s.warehouse_outlet_id', $request->warehouse_outlet_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('i.name', 'like', "%{$search}%")
                  ->orWhere('wo.name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $data = $query->paginate($perPage);

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        $outlets = AssetOwnership::options();
        $locationOutlets = AssetOwnership::locationOptions();

        return response()->json([
            'success' => true,
            'stocks' => $data,
            'warehouseOutlets' => $warehouseOutlets,
            'outlets' => $outlets,
            'locationOutlets' => $locationOutlets ?? AssetOwnership::locationOptions(),
        ]);
    }

    public function apiStockCardDetail(Request $request)
    {
        $inventoryItemId = $request->input('inventory_item_id');
        $warehouseOutletId = $request->input('warehouse_outlet_id');
        $ownerOutletId = $request->input('owner_outlet_id');

        if (!$inventoryItemId || !$warehouseOutletId || !$ownerOutletId) {
            return response()->json(['error' => 'inventory_item_id, owner_outlet_id, dan warehouse_outlet_id harus diisi'], 400);
        }

        $now = now();
        $from = $request->input('from', $now->copy()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', $now->copy()->endOfMonth()->format('Y-m-d'));

        $saldoAwal = $this->getSaldoAwal($inventoryItemId, (int) $ownerOutletId, $warehouseOutletId, $from);
        $cards = $this->getCardEntries($inventoryItemId, (int) $ownerOutletId, $warehouseOutletId, $from, $to);

        return response()->json([
            'success' => true,
            'cards' => $cards->toArray(),
            'saldo_awal' => $saldoAwal,
            'count' => $cards->count(),
        ]);
    }

    // ─── Private helpers ─────────────────────────────────────────────

    private function getSaldoAwal(int $inventoryItemId, int $ownerOutletId, int $warehouseOutletId, string $fromDate): array
    {
        $dayBefore = \Carbon\Carbon::parse($fromDate)->subDay()->format('Y-m-d');

        $last = DB::table('asset_inventory_cards as c')
            ->join('asset_inventory_items as ai', 'c.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->where('c.inventory_item_id', $inventoryItemId)
            ->where('c.owner_outlet_id', $ownerOutletId)
            ->where('c.warehouse_outlet_id', $warehouseOutletId)
            ->whereDate('c.date', '<=', $dayBefore)
            ->orderByDesc('c.date')
            ->orderByDesc('c.id')
            ->first();

        if ($last) {
            return [
                'small' => $last->saldo_qty_small ?? 0,
                'medium' => $last->saldo_qty_medium ?? 0,
                'large' => $last->saldo_qty_large ?? 0,
                'small_unit_name' => $last->small_unit_name ?? '',
                'medium_unit_name' => $last->medium_unit_name ?? '',
                'large_unit_name' => $last->large_unit_name ?? '',
            ];
        }

        $unitQuery = DB::table('asset_inventory_items as ai')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->where('ai.id', $inventoryItemId)
            ->select('us.name as small_unit_name', 'um.name as medium_unit_name', 'ul.name as large_unit_name')
            ->first();

        return [
            'small' => 0,
            'medium' => 0,
            'large' => 0,
            'small_unit_name' => $unitQuery->small_unit_name ?? '',
            'medium_unit_name' => $unitQuery->medium_unit_name ?? '',
            'large_unit_name' => $unitQuery->large_unit_name ?? '',
        ];
    }

    private function getCardEntries(int $inventoryItemId, int $ownerOutletId, int $warehouseOutletId, string $from, string $to)
    {
        $query = DB::table('asset_inventory_cards as c')
            ->join('asset_inventory_items as ai', 'c.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->join('warehouse_outlets as wo', 'c.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->where('c.inventory_item_id', $inventoryItemId)
            ->where('c.owner_outlet_id', $ownerOutletId)
            ->where('c.warehouse_outlet_id', $warehouseOutletId)
            ->whereDate('c.date', '>=', $from)
            ->whereDate('c.date', '<=', $to)
            ->select(
                'c.id',
                'c.date',
                'i.id as item_id',
                'i.name as item_name',
                'wo.id as warehouse_outlet_id',
                'wo.name as warehouse_name',
                'c.in_qty_small', 'c.in_qty_medium', 'c.in_qty_large',
                'c.out_qty_small', 'c.out_qty_medium', 'c.out_qty_large',
                'c.cost_per_small', 'c.cost_per_medium', 'c.cost_per_large',
                'c.value_in', 'c.value_out',
                'c.saldo_qty_small', 'c.saldo_qty_medium', 'c.saldo_qty_large',
                'c.saldo_value',
                'c.reference_type',
                'c.reference_id',
                'c.description',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name'
            )
            ->orderBy('c.date')
            ->orderBy('c.id');

        $data = $query->get();

        $data = $data->map(function ($row) {
            $row->reference_label = $this->formatReferenceLabel($row->reference_type, $row->reference_id);
            return $row;
        });

        return $data;
    }

    private function formatReferenceLabel(?string $referenceType, $referenceId): string
    {
        if (!$referenceType) return '-';

        $labels = [
            'asset_good_receive' => 'Good Receive',
            'asset_inventory_transfer' => 'Transfer',
            'asset_owner_transfer' => 'Transfer Kepemilikan',
            'asset_stock_adjustment' => 'Stock Adjustment',
            'asset_service_order' => 'Service Order',
            'asset_disposal' => 'Disposal',
            'initial_balance' => 'Saldo Awal',
        ];

        $label = $labels[$referenceType] ?? $referenceType;

        $number = null;
        switch ($referenceType) {
            case 'asset_good_receive':
                $rec = DB::table('asset_good_receives')->where('id', $referenceId)->value('gr_number');
                $number = $rec;
                break;
            case 'asset_inventory_transfer':
                $rec = DB::table('asset_inventory_transfers')->where('id', $referenceId)->value('transfer_number');
                $number = $rec;
                break;
            case 'asset_owner_transfer':
                $rec = DB::table('asset_owner_transfers')->where('id', $referenceId)->value('transfer_number');
                $number = $rec;
                break;
            case 'asset_stock_adjustment':
                $rec = DB::table('asset_inventory_adjustments')->where('id', $referenceId)->value('number');
                $number = $rec;
                break;
            case 'asset_service_order':
                $rec = DB::table('asset_service_orders')->where('id', $referenceId)->value('number');
                $number = $rec;
                break;
            case 'asset_disposal':
                $rec = DB::table('asset_disposals')->where('id', $referenceId)->value('number');
                $number = $rec;
                break;
        }

        return $number ? "{$label} #{$number}" : $label;
    }
}
