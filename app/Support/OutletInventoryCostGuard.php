<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Validasi harga/MAC masuk outlet terhadap anchor pembelian agar tidak menulis anomali MAC.
 */
final class OutletInventoryCostGuard
{
    /** Sama dengan OutletInventoryCostResolver::MAC_SPIKE_MULTIPLIER */
    public const SPIKE_MULTIPLIER = 5.0;

    /**
     * Jenis transaksi masuk yang dianggap punya harga referensi untuk adjustment IN.
     * (bukan adjustment/opname/keluar yang tidak membawa harga beli baru)
     */
    public const ADJUSTMENT_IN_COST_REFERENCE_TYPES = [
        'serial_receive',
        'good_receive_outlet',
        'outlet_food_good_receive',
        'retail_food',
        'internal_warehouse_transfer',
        'initial_balance',
        'mac_correction',
    ];

    /**
     * Anchor MAC per unit kecil: histori terpercaya → histori terbaru → MAC stok tersanitasi.
     */
    public static function resolveAnchorMacPerSmall(
        int $outletId,
        int $warehouseOutletId,
        int $inventoryItemId,
        ?object $stockRow = null
    ): ?float {
        $trusted = OutletInventoryCostResolver::latestTrustedNewCostPerSmallUnit(
            $outletId,
            $warehouseOutletId,
            $inventoryItemId
        );
        if ($trusted !== null && $trusted > 0) {
            return $trusted;
        }

        $latest = OutletInventoryCostResolver::latestNewCostPerSmallUnit(
            $outletId,
            $warehouseOutletId,
            $inventoryItemId
        );
        if ($latest !== null && $latest > 0) {
            return $latest;
        }

        if ($stockRow !== null) {
            $mac = OutletInventoryCostResolver::resolveMacFromStockRow($stockRow);

            return $mac > 0 ? $mac : null;
        }

        return null;
    }

    /**
     * Adjustment IN wajib punya harga referensi di outlet + warehouse outlet yang sama.
     *
     * @return array{ok: bool, message: ?string, anchor_mac: ?float, item_name: string}
     */
    public static function checkAdjustmentInCostReference(
        int $outletId,
        int $warehouseOutletId,
        int $itemId,
        string $itemName
    ): array {
        $inventoryItem = DB::table('outlet_food_inventory_items')
            ->where('item_id', $itemId)
            ->first(['id']);

        if (! $inventoryItem) {
            return [
                'ok' => false,
                'message' => self::formatAdjustmentInNoReferenceMessage(
                    $itemName,
                    'Item belum pernah masuk ke inventory outlet ini (belum ada Good Receive / Retail Food / transfer masuk).'
                ),
                'anchor_mac' => null,
                'item_name' => $itemName,
            ];
        }

        $inventoryItemId = (int) $inventoryItem->id;
        $stockRow = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->first();

        $hasInboundCostHistory = DB::table('outlet_food_inventory_cost_histories')
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->where('inventory_item_id', $inventoryItemId)
            ->whereIn('reference_type', self::ADJUSTMENT_IN_COST_REFERENCE_TYPES)
            ->whereNotNull('new_cost')
            ->where('new_cost', '>', 0)
            ->exists();

        $anchorMac = self::resolveAnchorMacPerSmall($outletId, $warehouseOutletId, $inventoryItemId, $stockRow);
        $stockQty = $stockRow ? (float) ($stockRow->qty_small ?? 0) : 0.0;
        $hasStockWithMac = $stockQty > 0 && $anchorMac !== null && $anchorMac > 0;

        if ($hasInboundCostHistory || $hasStockWithMac) {
            return [
                'ok' => true,
                'message' => null,
                'anchor_mac' => $anchorMac,
                'item_name' => $itemName,
            ];
        }

        return [
            'ok' => false,
            'message' => self::formatAdjustmentInNoReferenceMessage(
                $itemName,
                'Belum ada histori pembelian / penerimaan stok (GR, Retail Food, serial receive, atau transfer masuk) '
                . 'untuk outlet dan warehouse outlet yang dipilih.'
            ),
            'anchor_mac' => null,
            'item_name' => $itemName,
        ];
    }

    public static function formatAdjustmentInNoReferenceMessage(string $itemName, string $reason): string
    {
        return "Adjustment IN ditolak untuk item \"{$itemName}\".\n\n"
            . "• Alasan: {$reason}\n"
            . "• Tanpa harga referensi, stok masuk tidak bisa diberi nilai MAC yang benar.\n\n"
            . 'Lakukan Good Receive Outlet, Retail Food, atau transfer masuk terlebih dahulu '
            . 'untuk item ini di outlet + warehouse outlet yang sama.';
    }

    /**
     * @return array{ok: bool, message: ?string, entered_cost_small: float, anchor_mac: ?float, ratio: ?float, min_allowed: ?float, max_allowed: ?float}
     */
    public static function checkCostSmall(
        float $enteredCostSmall,
        ?float $anchorMac,
        string $itemName,
        string $contextLabel = 'harga beli'
    ): array {
        $entered = round($enteredCostSmall, 4);

        if ($entered <= 0 || $anchorMac === null || $anchorMac <= 0) {
            return [
                'ok' => true,
                'message' => null,
                'entered_cost_small' => $entered,
                'anchor_mac' => $anchorMac,
                'ratio' => null,
                'min_allowed' => null,
                'max_allowed' => null,
            ];
        }

        $anchor = round($anchorMac, 4);
        $ratio = $entered / $anchor;
        $minAllowed = round($anchor / self::SPIKE_MULTIPLIER, 4);
        $maxAllowed = round($anchor * self::SPIKE_MULTIPLIER, 4);

        if ($ratio > self::SPIKE_MULTIPLIER || $ratio < (1.0 / self::SPIKE_MULTIPLIER)) {
            $direction = $ratio > 1 ? 'terlalu tinggi' : 'terlalu rendah';

            return [
                'ok' => false,
                'message' => self::formatBlockMessage($itemName, $contextLabel, $entered, $anchor, $minAllowed, $maxAllowed, $direction, $ratio),
                'entered_cost_small' => $entered,
                'anchor_mac' => $anchor,
                'ratio' => round($ratio, 2),
                'min_allowed' => $minAllowed,
                'max_allowed' => $maxAllowed,
            ];
        }

        return [
            'ok' => true,
            'message' => null,
            'entered_cost_small' => $entered,
            'anchor_mac' => $anchor,
            'ratio' => round($ratio, 2),
            'min_allowed' => $minAllowed,
            'max_allowed' => $maxAllowed,
        ];
    }

    public static function formatBlockMessage(
        string $itemName,
        string $contextLabel,
        float $entered,
        float $anchor,
        float $minAllowed,
        float $maxAllowed,
        string $direction,
        float $ratio
    ): string {
        return "Harga ditolak untuk item \"{$itemName}\".\n\n"
            . "• {$contextLabel} yang diinput (per unit kecil): Rp " . self::formatRupiah($entered) . "\n"
            . '• MAC / harga referensi outlet: Rp ' . self::formatRupiah($anchor) . "\n"
            . '• Rasio: ' . number_format($ratio, 2, ',', '.') . "x (batas aman maks. " . self::SPIKE_MULTIPLIER . "x)\n"
            . '• Rentang yang diizinkan per unit kecil: Rp ' . self::formatRupiah($minAllowed)
            . ' s/d Rp ' . self::formatRupiah($maxAllowed) . "\n\n"
            . "Nilai {$direction} — kemungkinan salah input harga atau salah pilih satuan. "
            . 'Periksa kembali harga per satuan yang dipilih (small/medium/large), bukan harga total bon.';
    }

    public static function formatRupiah(float $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
