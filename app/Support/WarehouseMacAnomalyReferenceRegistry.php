<?php

namespace App\Support;

/**
 * Pemetaan reference_type pada food_inventory_cost_histories (warehouse HO/MK) → modul ERP & tautan perbaikan.
 */
final class WarehouseMacAnomalyReferenceRegistry
{
    /** @return array<string, array{label: string, module: string, fix_hint: string, url_pattern: string|null}> */
    public static function definitions(): array
    {
        return [
            'good_receive' => [
                'label' => 'Good Receive Warehouse',
                'module' => 'Food Good Receive',
                'fix_hint' => 'Cek harga GR, qty received, dan unit konversi. Value yatim (qty=0) bisa membuat MAC meledak saat GR berikutnya.',
                'url_pattern' => '/food-good-receive/{id}',
            ],
            'retail_warehouse_food' => [
                'label' => 'Retail Warehouse Food',
                'module' => 'Retail Warehouse Food',
                'fix_hint' => 'Verifikasi harga beli retail & qty. MAC memakai value lama — pastikan tidak ada orphan value.',
                'url_pattern' => '/retail-warehouse-food/{id}',
            ],
            'warehouse_transfer' => [
                'label' => 'Transfer Antar Warehouse',
                'module' => 'Warehouse Transfer',
                'fix_hint' => 'Cek MAC gudang pengirim. Transfer membawa cost asal ke gudang tujuan.',
                'url_pattern' => '/warehouse-transfer/{id}',
            ],
            'mk_production' => [
                'label' => 'MK Production',
                'module' => 'MK Production',
                'fix_hint' => 'Cek BOM, yield (qty_jadi vs qty small), dan cost bahan. Bug lama: cost ÷ qty_jadi (Pack) bukan qty_small.',
                'url_pattern' => '/mk-production/{id}',
            ],
            'butcher_process' => [
                'label' => 'Butcher Process',
                'module' => 'Butcher Process',
                'fix_hint' => 'Cek alokasi cost induk ke hasil butcher & konversi satuan.',
                'url_pattern' => '/butcher-processes/{id}',
            ],
            'outlet_rejection' => [
                'label' => 'Rejection Outlet → Warehouse',
                'module' => 'Outlet Rejection',
                'fix_hint' => 'Verifikasi qty rejection & MAC yang dibawa kembali ke warehouse.',
                'url_pattern' => '/outlet-rejections/{id}',
            ],
            'stock_adjustment' => [
                'label' => 'Penyesuaian Stok Warehouse',
                'module' => 'Food Inventory Adjustment',
                'fix_hint' => 'Cek qty adjustment & MAC manual pada penyesuaian stok warehouse.',
                'url_pattern' => '/food-inventory-adjustment/{id}',
            ],
            'initial_balance' => [
                'label' => 'Saldo Awal / Import Stok',
                'module' => 'Import Saldo Stok Warehouse',
                'fix_hint' => 'Perbaiki file import saldo awal atau buat adjustment koreksi MAC.',
                'url_pattern' => null,
            ],
            'mass_repair_mk_cost_bug' => [
                'label' => 'Mass Repair Cost (MK bug)',
                'module' => 'Cost Repair Script',
                'fix_hint' => 'Baris koreksi massal setelah bug MK Production — bukan transaksi operasional.',
                'url_pattern' => null,
            ],
            'mass_repair_pass2' => [
                'label' => 'Mass Repair Cost (pass 2)',
                'module' => 'Cost Repair Script',
                'fix_hint' => 'Baris koreksi lanjutan orphan/sauce — bukan transaksi operasional.',
                'url_pattern' => null,
            ],
            'manual_repair_mk_cost_bug' => [
                'label' => 'Manual Repair Cost',
                'module' => 'Cost Repair Script',
                'fix_hint' => 'Koreksi manual cost (mis. Kimchi/Simple Syrup).',
                'url_pattern' => null,
            ],
            'cost_repair' => [
                'label' => 'Cost Repair',
                'module' => 'Cost Repair',
                'fix_hint' => 'Entri koreksi cost historis.',
                'url_pattern' => null,
            ],
        ];
    }

    public static function labelFor(?string $referenceType): string
    {
        if ($referenceType === null || $referenceType === '') {
            return 'Tidak diketahui';
        }

        return self::definitions()[$referenceType]['label'] ?? $referenceType;
    }

    public static function moduleFor(?string $referenceType): string
    {
        if ($referenceType === null || $referenceType === '') {
            return 'Tidak diketahui';
        }

        return self::definitions()[$referenceType]['module'] ?? $referenceType;
    }

    public static function fixHintFor(?string $referenceType): string
    {
        if ($referenceType === null || $referenceType === '') {
            return 'Telusuri riwayat MAC per barang untuk menemukan transaksi penyebab.';
        }

        return self::definitions()[$referenceType]['fix_hint'] ?? 'Review transaksi sumber dan koreksi jika input salah.';
    }

    public static function sourceUrl(?string $referenceType, ?int $referenceId): ?string
    {
        if ($referenceType === null || $referenceId === null || $referenceId <= 0) {
            return null;
        }

        $pattern = self::definitions()[$referenceType]['url_pattern'] ?? null;
        if ($pattern === null) {
            return null;
        }

        return str_replace('{id}', (string) $referenceId, $pattern);
    }

    /** @return list<array{reference_type: string, label: string, module: string, fix_hint: string}> */
    public static function moduleCatalog(): array
    {
        $list = [];
        foreach (self::definitions() as $type => $def) {
            $list[] = [
                'reference_type' => $type,
                'label' => $def['label'],
                'module' => $def['module'],
                'fix_hint' => $def['fix_hint'],
            ];
        }

        return $list;
    }
}
