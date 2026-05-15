<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deteksi nomor seri yang sudah "keluar" / dipakai di sistem (bukan hanya DO).
 */
class InventorySerialInUse
{
    public static function failureMessage(): string
    {
        return 'Tidak dapat mengubah serial: ada nomor seri yang sudah digunakan.';
    }

    /**
     * Tambahkan kondisi: serial sudah pernah dipakai (keluar DO, transfer antar gudang/outlet, atau diterima di outlet).
     */
    public static function whereMarkedInUse(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_out', 1)
                ->orWhereNotNull('out_delivery_order_id')
                ->orWhere('is_transferred', 1)
                ->orWhereNotNull('transfer_id')
                ->orWhere('is_received', 1)
                ->orWhereNotNull('received_outlet_gr_id');
        });
    }

    public static function existsInUseFor(callable $scope): bool
    {
        $q = DB::table('inventory_item_serials');
        $scope($q);

        return static::whereMarkedInUse($q)->exists();
    }

    /**
     * Untuk SELECT … SUM(…) di serial summary (MySQL).
     *
     * @param  string  $alias  alias tabel, mis. 's' → s.is_out
     */
    public static function mysqlSumInUseCase(string $alias = 's'): string
    {
        $p = $alias !== '' ? $alias . '.' : '';

        return "SUM(CASE WHEN ({$p}is_out = 1 OR {$p}out_delivery_order_id IS NOT NULL OR {$p}is_transferred = 1 OR {$p}transfer_id IS NOT NULL OR {$p}is_received = 1 OR {$p}received_outlet_gr_id IS NOT NULL) THEN 1 ELSE 0 END)";
    }
}
