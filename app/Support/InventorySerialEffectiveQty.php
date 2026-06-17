<?php

namespace App\Support;

/**
 * Qty efektif per nomor seri (scan DO, GR outlet, dll).
 */
class InventorySerialEffectiveQty
{
    /**
     * Qty fisik per serial dalam unit serial (mis. 500 Pcs per SN chunk, 10 Roll per SN).
     * Dipakai untuk movement stok / kartu inventory.
     */
    public static function resolve(object $serial): float
    {
        if (! empty($serial->repack_unit_id) && (float) ($serial->repack_qty ?? 0) > 0) {
            return (float) $serial->repack_qty;
        }

        // Serial repack lama: qty disimpan di source_qty / generated_qty_unit
        if (($serial->source_type ?? '') === 'repack') {
            foreach (['source_qty', 'generated_qty_unit'] as $field) {
                $qty = (float) ($serial->{$field} ?? 0);
                if ($qty > 0) {
                    return $qty;
                }
            }
        }

        return 1.0;
    }

    /**
     * Qty + unit untuk response scan (WT, ORJ, IWT, penjualan, dll).
     * Qty fisik dalam unit serial (unit_id), bukan repack_unit — selaras DO / Outlet GR.
     *
     * @return array{qty: float, unit_id: int, unit_name: string, qty_small: float, repack_unit_id: ?int, repack_qty: ?float, repack_unit_name: ?string, physical_qty: float}
     */
    public static function resolveForScan(object $serial): array
    {
        $qty = self::resolve($serial);
        $unitId = (int) ($serial->unit_id ?? 0);
        $itemUom = self::itemUomFromRow($serial);
        $qtySmall = $unitId > 0 ? self::toSmallQty($qty, $unitId, $itemUom) : $qty;

        $repackQty = (float) ($serial->repack_qty ?? 0);
        $hasRepack = ! empty($serial->repack_unit_id) && $repackQty > 0;

        return [
            'qty' => $qty,
            'unit_id' => $unitId,
            'unit_name' => (string) ($serial->unit_name ?? ''),
            'qty_small' => $qtySmall,
            'repack_unit_id' => $hasRepack ? (int) $serial->repack_unit_id : null,
            'repack_qty' => $hasRepack ? $repackQty : null,
            'repack_unit_name' => $hasRepack ? trim((string) ($serial->repack_unit_name ?? '')) : null,
            'physical_qty' => $qty,
        ];
    }

    public static function qtyToSmall(float $qty, int $unitId, object $itemUom): float
    {
        return self::toSmallQty($qty, $unitId, $itemUom);
    }

    /**
     * Qty untuk dokumen (packing list DO, dll) dalam unit baris dokumen.
     *
     * - "1 Pack = 10 Roll" (unit serial Roll, label Pack): 1 SN chunk = repack_qty Roll → dikonversi ke Pack via master item.
     * - "1 Pack = 500 Pcs" (unit serial Pcs, label Pack): 1 SN chunk = 500 Pcs → 0,5 Pack jika master 1000 Pcs/Pack.
     * - "1 SN = 5 Kilogram" (unit serial = Kilogram, repack_qty 5): 1 SN = +5 Kilogram.
     */
    public static function resolveForDocumentUnit(object $serial, ?string $documentUnitName, ?object $itemUom = null): float
    {
        $physical = self::resolve($serial);
        $documentUnitName = trim((string) $documentUnitName);
        if ($documentUnitName === '') {
            return $physical;
        }

        $repackUnitName = trim((string) ($serial->repack_unit_name ?? ''));
        $serialUnitName = trim((string) ($serial->unit_name ?? ''));

        // Satu SN = satu chunk repack_qty dalam unit serial; konversi ke unit dokumen via master item.
        if ($repackUnitName !== ''
            && strcasecmp($repackUnitName, $documentUnitName) === 0
            && $serialUnitName !== ''
            && strcasecmp($serialUnitName, $repackUnitName) !== 0) {
            if ($itemUom !== null) {
                return self::convertBetweenItemUnits(
                    $physical,
                    (int) ($serial->unit_id ?? 0),
                    $documentUnitName,
                    $itemUom
                );
            }

            return 1.0;
        }

        if ($serialUnitName !== '' && strcasecmp($serialUnitName, $documentUnitName) === 0) {
            return $physical;
        }

        if ($repackUnitName !== '' && strcasecmp($repackUnitName, $documentUnitName) === 0) {
            if ($itemUom !== null && $serialUnitName !== '') {
                return self::convertBetweenItemUnits(
                    $physical,
                    (int) ($serial->unit_id ?? 0),
                    $documentUnitName,
                    $itemUom
                );
            }

            return $physical;
        }

        if ($itemUom !== null && $serialUnitName !== '') {
            return self::convertBetweenItemUnits(
                $physical,
                (int) ($serial->unit_id ?? 0),
                $documentUnitName,
                $itemUom
            );
        }

        return $physical;
    }

    public static function convertBetweenItemUnits(float $qty, int $fromUnitId, string $toUnitName, object $itemUom): float
    {
        if ($fromUnitId <= 0 || $qty <= 0) {
            return $qty;
        }

        $toUnitId = self::resolveItemUnitIdByName($toUnitName, $itemUom);
        if (! $toUnitId || $fromUnitId === $toUnitId) {
            return $qty;
        }

        $qtySmall = self::toSmallQty($qty, $fromUnitId, $itemUom);

        return self::fromSmallQty($qtySmall, $toUnitId, $itemUom);
    }

    public static function itemUomFromRow(object $row): object
    {
        return (object) [
            'small_unit_id' => (int) ($row->small_unit_id ?? 0),
            'medium_unit_id' => (int) ($row->medium_unit_id ?? 0),
            'large_unit_id' => (int) ($row->large_unit_id ?? 0),
            'small_conversion_qty' => (float) ($row->small_conversion_qty ?? 1),
            'medium_conversion_qty' => (float) ($row->medium_conversion_qty ?? 1),
            'small_unit_name' => (string) ($row->small_unit_name ?? ''),
            'medium_unit_name' => (string) ($row->medium_unit_name ?? ''),
            'large_unit_name' => (string) ($row->large_unit_name ?? ''),
        ];
    }

    private static function resolveItemUnitIdByName(string $unitName, object $itemUom): ?int
    {
        $unitName = trim($unitName);
        if ($unitName === '') {
            return null;
        }

        foreach ([
            'small_unit_name' => 'small_unit_id',
            'medium_unit_name' => 'medium_unit_id',
            'large_unit_name' => 'large_unit_id',
        ] as $nameField => $idField) {
            $name = trim((string) ($itemUom->{$nameField} ?? ''));
            if ($name !== '' && strcasecmp($name, $unitName) === 0) {
                return (int) $itemUom->{$idField};
            }
        }

        return null;
    }

    private static function toSmallQty(float $qty, int $unitId, object $itemUom): float
    {
        $smallConv = (float) ($itemUom->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemUom->medium_conversion_qty ?: 1);

        if ($unitId === (int) $itemUom->small_unit_id) {
            return $qty;
        }
        if ($unitId === (int) $itemUom->medium_unit_id) {
            return $qty * $smallConv;
        }
        if ($unitId === (int) $itemUom->large_unit_id) {
            return $qty * $smallConv * $mediumConv;
        }

        return $qty;
    }

    private static function fromSmallQty(float $qtySmall, int $unitId, object $itemUom): float
    {
        $smallConv = (float) ($itemUom->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemUom->medium_conversion_qty ?: 1);

        if ($unitId === (int) $itemUom->small_unit_id) {
            return $qtySmall;
        }
        if ($unitId === (int) $itemUom->medium_unit_id) {
            return $smallConv > 0 ? ($qtySmall / $smallConv) : 0;
        }
        if ($unitId === (int) $itemUom->large_unit_id) {
            $divider = $smallConv * $mediumConv;

            return $divider > 0 ? ($qtySmall / $divider) : 0;
        }

        return $qtySmall;
    }
}
