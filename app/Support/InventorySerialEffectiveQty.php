<?php

namespace App\Support;

/**
 * Qty efektif per nomor seri (scan DO, GR outlet, dll).
 */
class InventorySerialEffectiveQty
{
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
}
