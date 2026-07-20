<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Asset inventory ownership resolver.
 *
 * owner_outlet_id biasanya = id_outlet di tbl_data_outlet.
 * Dua pemilik khusus (bukan outlet operasional) memakai ID reserved
 * tanpa baris di tbl_data_outlet:
 *   90001 = Pak Yudi
 *   90002 = PT BAA
 *
 * Virtual ID hanya untuk ownership, bukan lokasi fisik / warehouse parent.
 */
class AssetOwnership
{
    public const PAK_YUDI_ID = 90001;

    public const PT_BAA_ID = 90002;

    /**
     * @return array<int, string>
     */
    public static function virtualOwners(): array
    {
        return [
            self::PAK_YUDI_ID => 'Pak Yudi',
            self::PT_BAA_ID => 'PT BAA',
        ];
    }

    /**
     * @return list<int>
     */
    public static function virtualIds(): array
    {
        return array_keys(self::virtualOwners());
    }

    public static function isVirtual(?int $id): bool
    {
        if ($id === null) {
            return false;
        }

        return array_key_exists($id, self::virtualOwners());
    }

    public static function name(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        if (self::isVirtual($id)) {
            return self::virtualOwners()[$id];
        }

        $name = DB::table('tbl_data_outlet')->where('id_outlet', $id)->value('nama_outlet');

        return $name !== null ? (string) $name : null;
    }

    /**
     * Dropdown pemilik: outlet aktif + virtual owners.
     * Shape: { id_outlet, nama_outlet } — sama seperti query outlet biasa.
     */
    public static function options(): Collection
    {
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $virtual = collect(self::virtualOwners())
            ->map(fn (string $name, int $id) => (object) [
                'id_outlet' => $id,
                'nama_outlet' => $name,
            ])
            ->values();

        return $outlets->concat($virtual)->values();
    }

    /**
     * Outlet lokasi fisik saja (tanpa virtual ownership).
     */
    public static function locationOptions(): Collection
    {
        return DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();
    }

    /**
     * SQL expression untuk nama pemilik di SELECT.
     * Contoh: AssetOwnership::ownerNameSql('gr.owner_outlet_id', 'oo.nama_outlet')
     */
    public static function ownerNameSql(string $idExpr, string $outletNameExpr): string
    {
        $cases = [];
        foreach (self::virtualOwners() as $id => $name) {
            $escaped = str_replace("'", "''", $name);
            $cases[] = "WHEN {$idExpr} = {$id} THEN '{$escaped}'";
        }

        return '(CASE ' . implode(' ', $cases) . " ELSE {$outletNameExpr} END)";
    }

    /**
     * Cek ID pemilik valid (virtual atau ada di tbl_data_outlet).
     */
    public static function isValidOwnerId(?int $id): bool
    {
        if ($id === null || $id <= 0) {
            return false;
        }

        if (self::isVirtual($id)) {
            return true;
        }

        return DB::table('tbl_data_outlet')->where('id_outlet', $id)->exists();
    }

    /**
     * Closure rule untuk Laravel validate (virtual ATAU exists outlet).
     * Ownership virtual hanya boleh dipilih HQ (id_outlet = 1).
     *
     * @return \Closure(string, mixed, \Closure): void
     */
    public static function ownerIdRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $id = $value !== null && $value !== '' ? (int) $value : null;
            if (! self::isValidOwnerId($id)) {
                $fail('Pemilik aset tidak valid.');

                return;
            }

            if (self::isVirtual($id)) {
                $user = auth()->user();
                if (! $user || (int) $user->id_outlet !== 1) {
                    $fail('Ownership Pak Yudi / PT BAA hanya dapat dipilih oleh HQ.');
                }
            }
        };
    }
}
