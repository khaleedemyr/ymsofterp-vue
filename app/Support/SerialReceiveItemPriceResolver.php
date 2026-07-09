<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Harga cost_small untuk GR Nomor Seri outlet.
 * Mode manual: selalu dari item_prices (bukan cost_small batch serial / GR+12%).
 */
final class SerialReceiveItemPriceResolver
{
    /**
     * @return array{0: float, 1: string, 2: string} [cost_small, cost_source_key, cost_source_label]
     */
    public static function resolveCostSmall(
        int $itemId,
        ?object $itemMaster,
        ?object $serial,
        ?string $receiveOutletId = null,
    ): array {
        $item = $itemMaster ?? DB::table('items')->where('id', $itemId)->first();
        if (! $item) {
            return [0.0, 'item_prices', 'Item tidak ditemukan'];
        }

        $outletId = $receiveOutletId ?? $serial->out_outlet_id ?? null;
        $regionId = null;
        if ($outletId) {
            $regionId = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('region_id');
            $regionId = $regionId ? (int) $regionId : null;
        }

        $priceRow = FloorOrderItemPriceResolver::resolvePriceRow($itemId, $regionId, $outletId);
        $isManual = self::isManualMode($itemId, $priceRow, $regionId, $outletId);

        if ($isManual) {
            $priceLarge = self::resolveManualPriceLarge($itemId, $priceRow, $regionId, $outletId);
            if ($priceLarge > 0) {
                return [
                    self::itemPriceLargeToCostSmall($priceLarge, $item),
                    'item_prices',
                    'Item Price (manual)',
                ];
            }

            Log::warning('serial_receive_manual_price_missing', [
                'item_id' => $itemId,
                'outlet_id' => $outletId,
                'serial_number' => $serial->serial_number ?? null,
            ]);

            return [0.0, 'item_prices', 'Item Price (manual, kosong)'];
        }

        if ($serial && self::isWarehouseSaleSerial($serial) && (float) ($serial->cost_small ?? 0) > 0) {
            return self::finalizeAutoResult(
                (float) $serial->cost_small,
                $item,
                $serial,
                'serial_warehouse_sale',
                'Harga jual gudang (serial)',
            );
        }

        if ($serial && self::isGoodReceiveSerial($serial) && (float) ($serial->cost_small ?? 0) > 0) {
            return self::finalizeAutoResult(
                self::autoSellCostSmallFromGrHpp((float) $serial->cost_small, $item, $serial),
                $item,
                $serial,
                'auto_fgr_12pct',
                'FGR Pusat +12% (dari serial GR)',
            );
        }

        $costSmall = self::costSmallFromCentralFoodGrMarkup($itemId, $item);
        if ($costSmall > 0) {
            return self::finalizeAutoResult($costSmall, $item, $serial, 'auto_fgr_12pct', 'FGR Pusat +12%');
        }

        return [0.0, 'auto_fgr_12pct', 'FGR Pusat +12%'];
    }

    /**
     * Mode auto: bulatkan harga per satuan tampilan ke atas kelipatan Rp 100.
     *
     * @return array{0: float, 1: string, 2: string}
     */
    private static function finalizeAutoResult(
        float $costSmall,
        object $item,
        ?object $serial,
        string $sourceKey,
        string $sourceLabel,
    ): array {
        return [
            self::applyAutoUnitPriceRoundUp($costSmall, $item, $serial),
            $sourceKey,
            $sourceLabel,
        ];
    }

    private static function applyAutoUnitPriceRoundUp(float $costSmall, object $item, ?object $serial): float
    {
        if ($costSmall <= 0) {
            return 0.0;
        }

        $unitId = (int) ($serial->unit_id ?? $item->small_unit_id ?? 0);
        if ($unitId <= 0) {
            return $costSmall;
        }

        $unitPrice = ItemUnitCost::priceForUnit($costSmall, $item, $unitId);
        $roundedUnitPrice = FloorOrderItemPriceResolver::roundUpToHundred($unitPrice);
        if ($roundedUnitPrice <= 0) {
            return $costSmall;
        }

        return ItemUnitCost::costSmallFromUnitPrice($roundedUnitPrice, $item, $unitId);
    }

    /**
     * Manual jika baris harga prioritas (dengan price > 0) bukan auto,
     * atau masih ada harga manual > 0 di scope outlet/region/all.
     */
    public static function isManualMode(
        int $itemId,
        ?object $priceRow,
        ?int $regionId,
        ?string $outletId,
    ): bool {
        if ($priceRow && (float) ($priceRow->price ?? 0) > 0) {
            return self::rowIsManual($priceRow);
        }

        $scopedRow = self::firstPricedRowInScope($itemId, $regionId, $outletId);
        if ($scopedRow) {
            return self::rowIsManual($scopedRow);
        }

        if ($priceRow) {
            return self::rowIsManual($priceRow);
        }

        return true;
    }

    public static function resolveManualPriceLarge(
        int $itemId,
        ?object $priceRow,
        ?int $regionId,
        ?string $outletId,
    ): float {
        if ($priceRow && self::rowIsManual($priceRow) && (float) $priceRow->price > 0) {
            return (float) $priceRow->price;
        }

        $scopedRow = self::firstManualPricedRowInScope($itemId, $regionId, $outletId);
        if ($scopedRow) {
            return (float) $scopedRow->price;
        }

        return 0.0;
    }

    public static function itemPriceLargeToCostSmall(float $priceLarge, ?object $itemMaster): float
    {
        if ($priceLarge <= 0 || ! $itemMaster) {
            return 0.0;
        }

        $smallConv = (float) ($itemMaster->small_conversion_qty ?? 1) ?: 1;
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?? 1) ?: 1;
        $divisor = ($smallConv > 0 && $mediumConv > 0) ? ($smallConv * $mediumConv) : 1;

        return round($priceLarge / $divisor, 4);
    }

    /**
     * HPP cost_small dari serial FGR Pusat → harga jual (+12%, bulat ke atas Rp 100).
     */
    public static function autoSellCostSmallFromGrHpp(float $hppCostSmall, object $item, ?object $serial): float
    {
        if ($hppCostSmall <= 0) {
            return 0.0;
        }

        $smallConv = (float) ($item->small_conversion_qty ?? 1) ?: 1;
        $mediumConv = (float) ($item->medium_conversion_qty ?? 1) ?: 1;
        $hppLarge = $hppCostSmall * $smallConv * $mediumConv;
        $sellLarge = FloorOrderItemPriceResolver::roundUpToHundred($hppLarge * 1.12);
        $sellCostSmall = self::itemPriceLargeToCostSmall($sellLarge, $item);

        return self::applyAutoUnitPriceRoundUp($sellCostSmall, $item, $serial);
    }

    public static function isWarehouseSaleSerial(?object $serial): bool
    {
        if (! $serial) {
            return false;
        }

        return in_array((string) ($serial->source_type ?? ''), ['warehouse_sale', 'retail_warehouse'], true);
    }

    public static function isGoodReceiveSerial(?object $serial): bool
    {
        return $serial && ($serial->source_type ?? '') === 'good_receive';
    }

    private static function rowIsManual(?object $row): bool
    {
        if (! $row) {
            return true;
        }
        if (! Schema::hasColumn('item_prices', 'pricing_mode')) {
            return true;
        }

        return ($row->pricing_mode ?? 'manual') !== 'auto';
    }

    private static function firstPricedRowInScope(int $itemId, ?int $regionId, ?string $outletId): ?object
    {
        return self::scopedRows($itemId, $regionId, $outletId)
            ->first(fn ($row) => (float) ($row->price ?? 0) > 0);
    }

    private static function firstManualPricedRowInScope(int $itemId, ?int $regionId, ?string $outletId): ?object
    {
        return self::scopedRows($itemId, $regionId, $outletId)
            ->first(fn ($row) => self::rowIsManual($row) && (float) ($row->price ?? 0) > 0);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function scopedRows(int $itemId, ?int $regionId, ?string $outletId)
    {
        $rows = DB::table('item_prices')
            ->where('item_id', $itemId)
            ->where(function ($q) use ($regionId, $outletId) {
                $q->where('availability_price_type', 'all');
                if ($regionId) {
                    $q->orWhere(function ($q2) use ($regionId) {
                        $q2->where('availability_price_type', 'region')->where('region_id', $regionId);
                    });
                }
                if ($outletId) {
                    $q->orWhere(function ($q2) use ($outletId) {
                        $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outletId);
                    });
                }
            })
            ->orderByDesc('id')
            ->get();

        $pick = static function (string $type, ?int $region = null, ?string $outlet = null) use ($rows): ?object {
            return $rows->first(function ($row) use ($type, $region, $outlet) {
                if (($row->availability_price_type ?? '') !== $type) {
                    return false;
                }
                if ($type === 'region' && (int) ($row->region_id ?? 0) !== (int) $region) {
                    return false;
                }
                if ($type === 'outlet' && (string) ($row->outlet_id ?? '') !== (string) $outlet) {
                    return false;
                }

                return true;
            });
        };

        return collect([
            $pick('outlet', null, $outletId),
            $pick('region', $regionId, null),
            $pick('all'),
        ])->filter();
    }

    private static function costSmallFromCentralFoodGrMarkup(int $itemId, ?object $itemMaster): float
    {
        $priceLarge = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
        if ($priceLarge === null || $priceLarge <= 0) {
            return 0.0;
        }

        return self::itemPriceLargeToCostSmall($priceLarge, $itemMaster);
    }
}
