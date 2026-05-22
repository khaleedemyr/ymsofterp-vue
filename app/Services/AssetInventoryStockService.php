<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AssetInventoryStockService
{
    /**
     * Outlet fisik dari gudang (warehouse selalu terikat ke satu outlet).
     */
    public static function locationOutletIdForWarehouse(?int $warehouseOutletId): ?int
    {
        if (!$warehouseOutletId) {
            return null;
        }

        $outletId = DB::table('warehouse_outlets')->where('id', $warehouseOutletId)->value('outlet_id');

        return $outletId !== null ? (int) $outletId : null;
    }

    public static function assertWarehouseBelongsToOutlet(int $warehouseOutletId, int $locationOutletId): void
    {
        $whOutletId = self::locationOutletIdForWarehouse($warehouseOutletId);

        if ($whOutletId === null || $whOutletId !== $locationOutletId) {
            throw new \InvalidArgumentException('Gudang tidak sesuai dengan outlet lokasi yang dipilih.');
        }
    }

    public static function resolveLocationOutletId(int $locationOutletId, ?int $warehouseOutletId): int
    {
        if ($warehouseOutletId) {
            self::assertWarehouseBelongsToOutlet($warehouseOutletId, $locationOutletId);

            return self::locationOutletIdForWarehouse($warehouseOutletId);
        }

        return $locationOutletId;
    }

    /**
     * Scope query stok/kartu per pemilik + gudang.
     */
    public static function applyOwnerWarehouseScope($query, int $ownerOutletId, ?int $warehouseOutletId, string $tableAlias = ''): void
    {
        $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';

        $query->where($prefix . 'owner_outlet_id', $ownerOutletId);

        if ($warehouseOutletId) {
            $query->where($prefix . 'warehouse_outlet_id', $warehouseOutletId);
        } else {
            $query->whereNull($prefix . 'warehouse_outlet_id');
        }
    }

    public static function findStock(int $inventoryItemId, int $ownerOutletId, ?int $warehouseOutletId): ?object
    {
        $query = DB::table('asset_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId);

        self::applyOwnerWarehouseScope($query, $ownerOutletId, $warehouseOutletId);

        return $query->first() ?: null;
    }

    /**
     * Filter daftar stok untuk user non-HQ: lihat milik outlet user (bisa di gudang outlet lain).
     */
    public static function applyOwnerVisibilityForUser($query, $user, string $ownerColumn = 's.owner_outlet_id'): void
    {
        if ($user && (int) $user->id_outlet !== 1) {
            $query->where($ownerColumn, (int) $user->id_outlet);
        }
    }

    /**
     * Outlet pemilik dari baris PO (satu nilai jika semua item sama outlet).
     */
    public static function suggestedOwnerOutletIdFromPoItems(iterable $poItems): ?int
    {
        $ids = [];
        foreach ($poItems as $item) {
            if (!empty($item->outlet_id)) {
                $ids[] = (int) $item->outlet_id;
            }
        }

        $unique = array_values(array_unique($ids));

        return $unique[0] ?? null;
    }
}
