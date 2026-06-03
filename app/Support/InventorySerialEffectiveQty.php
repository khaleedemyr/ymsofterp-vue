<?php

namespace App\Support;

/**
 * Qty efektif per nomor seri (scan DO, GR outlet, dll).
 */
class InventorySerialEffectiveQty
{
    /**
     * Qty fisik per serial dalam unit dasar serial (mis. Roll).
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
     * Qty untuk dokumen (packing list DO, dll) dalam unit baris dokumen.
     * Contoh: PL 2 Pack, serial "1 Pack = 10 Roll" → 1 per scan (bukan 10).
     */
    public static function resolveForDocumentUnit(object $serial, ?string $documentUnitName): float
    {
        $physical = self::resolve($serial);
        $documentUnitName = trim((string) $documentUnitName);
        if ($documentUnitName === '') {
            return $physical;
        }

        $repackUnitName = trim((string) ($serial->repack_unit_name ?? ''));
        $serialUnitName = trim((string) ($serial->unit_name ?? ''));

        if ($repackUnitName !== '' && strcasecmp($repackUnitName, $documentUnitName) === 0) {
            return 1.0;
        }

        if ($serialUnitName !== '' && strcasecmp($serialUnitName, $documentUnitName) === 0) {
            return $physical;
        }

        return $physical;
    }
}
