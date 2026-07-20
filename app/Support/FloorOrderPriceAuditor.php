<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scan & perbaiki harga baris food_floor_order_items vs item_prices (+ konversi UoM).
 */
final class FloorOrderPriceAuditor
{
    /** @var array<int, object> */
    private array $items = [];

    /** @var array<int, string> */
    private array $unitNameById = [];

    /** @var Collection<int, Collection<int, object>> */
    private Collection $priceRowsByItem;

    /** @var array<int, float|null> */
    private array $autoPriceCache = [];

    /** @var array<string, array{large: float, mode: string}> */
    private array $largePriceCache = [];

    public function scan(
        string $dateFrom,
        ?string $dateTo,
        bool $allStatuses = false,
        ?array $itemIds = null,
    ): array {
        $query = DB::table('food_floor_order_items as ffoi')
            ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
            ->join('items as i', 'i.id', '=', 'ffoi.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->whereNotIn('ffo.fo_mode', ['RO Khusus', 'RO Supplier'])
            ->whereDate('ffo.tanggal', '>=', $dateFrom)
            ->where(function ($q) {
                $q->whereNull('c.is_asset')->orWhere('c.is_asset', '!=', '1');
            })
            ->select(
                'ffoi.id',
                'ffoi.floor_order_id',
                'ffoi.item_id',
                'ffoi.item_name',
                'ffoi.qty',
                'ffoi.price',
                'ffoi.unit',
                'ffo.tanggal',
                'ffo.order_number',
                'ffo.status',
                'ffo.id_outlet',
                'o.region_id',
                'o.nama_outlet',
            )
            ->orderBy('ffoi.item_name')
            ->orderBy('ffo.tanggal');

        if ($dateTo) {
            $query->whereDate('ffo.tanggal', '<=', $dateTo);
        }

        if (! $allStatuses) {
            $query->whereIn('ffo.status', ['draft', 'submitted']);
        }

        if ($itemIds !== null && $itemIds !== []) {
            $query->whereIn('ffoi.item_id', $itemIds);
        }

        $rows = $query->get();
        $this->preloadMasters($rows->pluck('item_id')->unique()->values()->all());

        $matched = 0;
        $skippedNoPrice = 0;
        $mismatches = [];

        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $item = $this->items[$itemId] ?? null;
            if (! $item) {
                continue;
            }

            $regionId = $row->region_id ? (int) $row->region_id : null;
            $outletId = $row->id_outlet ? (string) $row->id_outlet : null;
            $unitName = (string) ($row->unit ?? '');

            $resolved = $this->resolveExpected($itemId, $unitName, $regionId, $outletId);
            $expected = $resolved['expected'];

            if ($expected <= 0) {
                $skippedNoPrice++;
                continue;
            }

            $current = (float) $row->price;
            if (abs($expected - $current) < 0.01) {
                $matched++;
                continue;
            }

            $mismatches[] = [
                'line_id' => (int) $row->id,
                'floor_order_id' => (int) $row->floor_order_id,
                'item_id' => $itemId,
                'item_name' => (string) $row->item_name,
                'tanggal' => (string) $row->tanggal,
                'order_number' => (string) ($row->order_number ?? ''),
                'status' => (string) ($row->status ?? ''),
                'outlet' => (string) ($row->nama_outlet ?? ''),
                'unit' => $unitName,
                'unit_tier' => $resolved['tier'],
                'qty' => (float) $row->qty,
                'current_price' => $current,
                'expected_price' => $expected,
                'expected_subtotal' => round($expected * (float) $row->qty, 2),
                'diff' => round($current - $expected, 2),
                'price_large' => $resolved['large'],
                'pricing_mode' => $resolved['mode'],
                'medium_conv' => (float) ($item->medium_conversion_qty ?? 1),
                'small_conv' => (float) ($item->small_conversion_qty ?? 1),
            ];
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'rows_scanned' => $rows->count(),
            'matched' => $matched,
            'skipped_no_price' => $skippedNoPrice,
            'mismatches' => $mismatches,
            'summary_by_item' => $this->summarizeByItem($mismatches),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fixes
     * @return array{updated: int, orders_recalculated: int}
     */
    public function applyFixes(array $fixes): array
    {
        $updated = 0;
        $affectedOrderIds = [];

        foreach ($fixes as $f) {
            DB::table('food_floor_order_items')
                ->where('id', $f['line_id'])
                ->update([
                    'price' => $f['expected_price'],
                    'subtotal' => $f['expected_subtotal'],
                    'updated_at' => now(),
                ]);
            $affectedOrderIds[$f['floor_order_id']] = true;
            $updated++;
        }

        $ordersRecalculated = 0;
        if (\Illuminate\Support\Facades\Schema::hasColumn('food_floor_orders', 'total_amount')) {
            foreach (array_keys($affectedOrderIds) as $orderId) {
                $total = (float) DB::table('food_floor_order_items')
                    ->where('floor_order_id', $orderId)
                    ->sum('subtotal');
                DB::table('food_floor_orders')
                    ->where('id', $orderId)
                    ->update([
                        'total_amount' => $total,
                        'updated_at' => now(),
                    ]);
                $ordersRecalculated++;
            }
        }

        return ['updated' => $updated, 'orders_recalculated' => $ordersRecalculated];
    }

    /**
     * Sinkronkan harga semua baris satu FO dengan item_prices / FGR +12% terkini.
     *
     * @return array{updated: int, lines_checked: int}
     */
    public function refreshOrder(int $floorOrderId): array
    {
        $header = DB::table('food_floor_orders as ffo')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
            ->where('ffo.id', $floorOrderId)
            ->select('ffo.id', 'ffo.id_outlet', 'o.region_id')
            ->first();

        if (! $header) {
            return ['updated' => 0, 'lines_checked' => 0];
        }

        $rows = DB::table('food_floor_order_items as ffoi')
            ->join('items as i', 'i.id', '=', 'ffoi.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->where('ffoi.floor_order_id', $floorOrderId)
            ->where(function ($q) {
                $q->whereNull('c.is_asset')->orWhere('c.is_asset', '!=', '1');
            })
            ->select(
                'ffoi.id',
                'ffoi.floor_order_id',
                'ffoi.item_id',
                'ffoi.qty',
                'ffoi.price',
                'ffoi.unit',
            )
            ->get();

        if ($rows->isEmpty()) {
            return ['updated' => 0, 'lines_checked' => 0];
        }

        $this->preloadMasters($rows->pluck('item_id')->unique()->values()->all());

        $regionId = $header->region_id ? (int) $header->region_id : null;
        $outletId = $header->id_outlet ? (string) $header->id_outlet : null;
        $fixes = [];

        foreach ($rows as $row) {
            $resolved = $this->resolveExpected(
                (int) $row->item_id,
                (string) ($row->unit ?? ''),
                $regionId,
                $outletId,
            );
            $expected = $resolved['expected'];
            if ($expected <= 0) {
                continue;
            }
            $current = (float) $row->price;
            if (abs($expected - $current) < 0.01) {
                continue;
            }

            $fixes[] = [
                'line_id' => (int) $row->id,
                'floor_order_id' => (int) $row->floor_order_id,
                'expected_price' => $expected,
                'expected_subtotal' => round($expected * (float) $row->qty, 2),
            ];
        }

        if ($fixes === []) {
            return ['updated' => 0, 'lines_checked' => $rows->count()];
        }

        $stats = $this->applyFixes($fixes);

        return ['updated' => $stats['updated'], 'lines_checked' => $rows->count()];
    }

    /** @param  list<int>  $itemIds */
    private function preloadMasters(array $itemIds): void
    {
        $this->items = [];
        $this->priceRowsByItem = collect();
        $this->autoPriceCache = [];
        $this->largePriceCache = [];

        if ($itemIds === []) {
            return;
        }

        foreach (array_chunk($itemIds, 500) as $chunk) {
            foreach (DB::table('items')->whereIn('id', $chunk)->get() as $item) {
                $this->items[(int) $item->id] = $item;
            }
        }

        $unitIds = collect($this->items)->flatMap(fn ($i) => [
            $i->small_unit_id, $i->medium_unit_id, $i->large_unit_id,
        ])->filter()->unique()->values()->all();

        $this->unitNameById = $unitIds !== []
            ? DB::table('units')->whereIn('id', $unitIds)->pluck('name', 'id')->all()
            : [];

        foreach (array_chunk($itemIds, 500) as $chunk) {
            $batch = DB::table('item_prices')
                ->whereIn('item_id', $chunk)
                ->orderByDesc('id')
                ->get();
            foreach ($batch->groupBy('item_id') as $itemId => $group) {
                $existing = $this->priceRowsByItem->get($itemId, collect());
                $this->priceRowsByItem[$itemId] = $existing->merge($group)->sortByDesc('id')->values();
            }
        }

        $needsGr = [];
        foreach ($itemIds as $itemId) {
            $priceRows = $this->priceRowsByItem->get($itemId, collect());
            $hasManual = $priceRows->contains(
                fn ($r) => ($r->pricing_mode ?? 'manual') !== 'auto' && (float) ($r->price ?? 0) > 0
            );
            $hasAuto = $priceRows->contains(fn ($r) => ($r->pricing_mode ?? '') === 'auto');
            if ($hasAuto || ! $hasManual) {
                $needsGr[] = (int) $itemId;
            }
        }

        foreach ($needsGr as $itemId) {
            $this->autoPriceCache[$itemId] = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
        }
    }

  /** @return array{expected: float, large: float, mode: string, tier: string} */
    private function resolveExpected(int $itemId, string $unitName, ?int $regionId, ?string $outletId): array
    {
        $cacheKey = $itemId . '|' . ($regionId ?? 0) . '|' . ($outletId ?? '0');
        $item = $this->items[$itemId] ?? null;
        if (! $item) {
            return ['expected' => 0.0, 'large' => 0.0, 'mode' => 'n/a', 'tier' => 'medium'];
        }

        if (! isset($this->largePriceCache[$cacheKey])) {
            $rows = $this->priceRowsByItem->get($itemId, collect());
            $priceRow = $this->pickPriceRow($rows, $regionId, $outletId);
            $large = $this->resolveLarge($itemId, $priceRow);
            $mode = ($priceRow && ($priceRow->pricing_mode ?? '') === 'auto') ? 'auto' : 'manual';
            $this->largePriceCache[$cacheKey] = ['large' => $large, 'mode' => $mode];
        }

        $large = $this->largePriceCache[$cacheKey]['large'];
        $mode = $this->largePriceCache[$cacheKey]['mode'];
        if ($large <= 0) {
            return ['expected' => 0.0, 'large' => 0.0, 'mode' => $mode, 'tier' => 'medium'];
        }

        $tier = FloorOrderItemPriceResolver::detectUnitTier($item, $unitName, $this->unitNameById);
        $expected = match ($tier) {
            'large' => FloorOrderItemPriceResolver::roundUpToHundred($large),
            'small' => FloorOrderItemPriceResolver::roundUpToHundred(
                FloorOrderItemPriceResolver::largeToSmallPrice($large, $item)
            ),
            default => FloorOrderItemPriceResolver::roundUpToHundred(
                FloorOrderItemPriceResolver::largeToMediumPrice($large, $item)
            ),
        };

        $expected = FloorOrderItemPriceResolver::guardAgainstLargePriceOnMediumUnit(
            $expected,
            $itemId,
            $unitName,
            $regionId,
            $outletId,
            $item,
            $this->unitNameById,
        );

        return ['expected' => $expected, 'large' => $large, 'mode' => $mode, 'tier' => $tier];
    }

    private function pickPriceRow(Collection $rows, ?int $regionId, ?string $outletId): ?object
    {
        $pick = static function (string $type, ?int $region = null, ?string $outlet = null, bool $pricedOnly = true) use ($rows): ?object {
            return $rows->first(function ($row) use ($type, $region, $outlet, $pricedOnly) {
                if (($row->availability_price_type ?? '') !== $type) {
                    return false;
                }
                if ($type === 'region' && (int) ($row->region_id ?? 0) !== (int) $region) {
                    return false;
                }
                if ($type === 'outlet' && (string) ($row->outlet_id ?? '') !== (string) $outlet) {
                    return false;
                }
                if ($pricedOnly && (float) ($row->price ?? 0) <= 0) {
                    return false;
                }

                return true;
            });
        };

        return $pick('outlet', null, $outletId)
            ?? $pick('region', $regionId, null)
            ?? $pick('all')
            ?? $pick('outlet', null, $outletId, false)
            ?? $pick('region', $regionId, null, false)
            ?? $pick('all', null, null, false);
    }

    private function resolveLarge(int $itemId, ?object $priceRow): float
    {
        $mode = ($priceRow && ($priceRow->pricing_mode ?? '') === 'auto') ? 'auto' : 'manual';
        if ($mode === 'auto') {
            $auto = $this->autoPriceCache[$itemId] ?? null;
            if ($auto !== null && $auto > 0) {
                return (float) $auto;
            }
        }
        if ($priceRow && (float) $priceRow->price > 0) {
            return (float) $priceRow->price;
        }
        $auto = $this->autoPriceCache[$itemId] ?? null;

        return ($auto !== null && $auto > 0) ? (float) $auto : 0.0;
    }

    /** @param  list<array<string, mixed>>  $mismatches */
    private function summarizeByItem(array $mismatches): array
    {
        $byItem = [];
        foreach ($mismatches as $m) {
            $key = $m['item_id'];
            if (! isset($byItem[$key])) {
                $byItem[$key] = [
                    'item_id' => $m['item_id'],
                    'item_name' => $m['item_name'],
                    'mismatch_rows' => 0,
                    'current_prices' => [],
                    'expected_prices' => [],
                ];
            }
            $byItem[$key]['mismatch_rows']++;
            $byItem[$key]['current_prices'][(string) $m['current_price']] =
                ($byItem[$key]['current_prices'][(string) $m['current_price']] ?? 0) + 1;
            $byItem[$key]['expected_prices'][(string) $m['expected_price']] =
                ($byItem[$key]['expected_prices'][(string) $m['expected_price']] ?? 0) + 1;
        }

        uasort($byItem, fn ($a, $b) => $b['mismatch_rows'] <=> $a['mismatch_rows']);

        return array_values($byItem);
    }
}
