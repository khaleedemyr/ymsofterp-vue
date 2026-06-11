<?php

namespace App\Support;

/**
 * Pemetaan reference_type pada outlet_food_inventory_cost_histories → modul ERP & tautan perbaikan.
 */
final class MacAnomalyReferenceRegistry
{
    /** @return array<string, array{label: string, module: string, fix_hint: string, url_pattern: string|null}> */
    public static function definitions(): array
    {
        return [
            'good_receive_outlet' => [
                'label' => 'Good Receive Outlet (FJ)',
                'module' => 'Outlet Food Good Receive',
                'fix_hint' => 'Cek harga GR, qty received, dan unit konversi. Batalkan/revisi GR jika harga salah.',
                'url_pattern' => '/outlet-food-good-receives/{id}',
            ],
            'good_receive_supplier' => [
                'label' => 'GR Supplier (HO)',
                'module' => 'Good Receive Outlet Supplier',
                'fix_hint' => 'Verifikasi harga supplier dan qty pada GR supplier outlet.',
                'url_pattern' => '/good-receive-outlet-supplier/{id}',
            ],
            'good_receive_outlet_supplier' => [
                'label' => 'GR Supplier Outlet',
                'module' => 'Good Receive Outlet Supplier',
                'fix_hint' => 'Verifikasi harga supplier dan qty pada GR supplier outlet.',
                'url_pattern' => '/good-receive-outlet-supplier/{id}',
            ],
            'outlet_transfer' => [
                'label' => 'Transfer Antar Outlet',
                'module' => 'Outlet Transfer',
                'fix_hint' => 'Cek MAC gudang pengirim & qty transfer. MAC penerima mengikuti MAC asal.',
                'url_pattern' => '/outlet-transfer/{id}',
            ],
            'internal_warehouse_transfer' => [
                'label' => 'Transfer Internal Warehouse',
                'module' => 'Internal Warehouse Transfer',
                'fix_hint' => 'Pastikan MAC gudang asal benar sebelum transfer internal.',
                'url_pattern' => '/internal-warehouse-transfer/{id}',
            ],
            'stock_opname' => [
                'label' => 'Stock Opname Outlet',
                'module' => 'Stock Opname',
                'fix_hint' => 'Review MAC before/after opname. Koreksi opname atau adjustment jika MAC fisik salah.',
                'url_pattern' => '/stock-opnames/{id}',
            ],
            'outlet_stock_adjustment' => [
                'label' => 'Penyesuaian Stok Outlet',
                'module' => 'Outlet Food Inventory Adjustment',
                'fix_hint' => 'Cek qty adjustment & MAC manual. Batalkan adjustment jika input salah.',
                'url_pattern' => '/outlet-food-inventory-adjustment/{id}',
            ],
            'retail_food' => [
                'label' => 'Retail Food Outlet',
                'module' => 'Retail Food',
                'fix_hint' => 'Verifikasi harga beli retail & qty pada transaksi retail food.',
                'url_pattern' => '/retail-food/{id}',
            ],
            'serial_receive' => [
                'label' => 'Penerimaan Serial',
                'module' => 'Outlet Serial Receive',
                'fix_hint' => 'Cek harga pada penerimaan serial & mapping item.',
                'url_pattern' => '/outlet-serial-receive/{id}',
            ],
            'serial_receive_rollback' => [
                'label' => 'Rollback Serial Receive',
                'module' => 'Outlet Serial Receive',
                'fix_hint' => 'Rollback serial bisa mengubah MAC — pastikan transaksi asal sudah benar.',
                'url_pattern' => '/outlet-serial-receive/{id}',
            ],
            'outlet_internal_use_waste' => [
                'label' => 'Category Cost (Internal Use / Waste)',
                'module' => 'Outlet Internal Use & Waste',
                'fix_hint' => 'Biasanya tidak mengubah MAC saldo; cek jika ada koreksi stok terkait.',
                'url_pattern' => '/outlet-internal-use-waste/{id}',
            ],
            'outlet_food_return' => [
                'label' => 'Retur Outlet Food',
                'module' => 'Outlet Food Return',
                'fix_hint' => 'Cek qty retur & MAC saat proses retur.',
                'url_pattern' => '/outlet-food-returns/{id}',
            ],
            'outlet_rejection' => [
                'label' => 'Rejection Outlet',
                'module' => 'Outlet Rejection',
                'fix_hint' => 'Verifikasi qty rejection & dampaknya ke MAC.',
                'url_pattern' => '/outlet-rejections/{id}',
            ],
            'outlet_wip_production' => [
                'label' => 'Produksi WIP Outlet',
                'module' => 'Outlet WIP Production',
                'fix_hint' => 'Cek BOM, yield, dan biaya bahan pada produksi WIP.',
                'url_pattern' => '/outlet-wip/{id}',
            ],
            'initial_balance' => [
                'label' => 'Saldo Awal / Import Stok',
                'module' => 'Import Saldo Stok Outlet',
                'fix_hint' => 'Perbaiki file import saldo awal atau buat adjustment koreksi MAC.',
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
