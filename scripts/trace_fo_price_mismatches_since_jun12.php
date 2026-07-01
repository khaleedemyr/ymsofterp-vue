<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FoodGrLastPurchaseForItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$since = '2026-06-12';
$toDate = null;
$allStatuses = false;
$csvPath = null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--since=') || str_starts_with($arg, '--from=')) {
        $since = substr($arg, strpos($arg, '=') + 1);
    }
    if (str_starts_with($arg, '--to=')) {
        $toDate = substr($arg, strlen('--to='));
    }
    if ($arg === '--all-statuses') {
        $allStatuses = true;
    }
    if (str_starts_with($arg, '--csv=')) {
        $csvPath = substr($arg, strlen('--csv='));
    }
}

echo "=== Trace FO price vs item_prices (UoM-aware) ===\n";
echo "Tanggal FO: {$since}" . ($toDate ? " s/d {$toDate}" : ' s/d hari ini') . "\n";
echo 'Status FO: ' . ($allStatuses ? 'semua' : 'draft, submitted') . "\n\n";

if (! Schema::hasTable('food_floor_order_items') || ! Schema::hasTable('food_floor_orders')) {
    echo "Tabel FO tidak ditemukan.\n";
    exit(1);
}

$query = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->join('items as i', 'i.id', '=', 'ffoi.item_id')
    ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
    ->whereNotIn('ffo.fo_mode', ['RO Khusus', 'RO Supplier'])
    ->whereDate('ffo.tanggal', '>=', $since)
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

if ($toDate) {
    $query->whereDate('ffo.tanggal', '<=', $toDate);
}

if (! $allStatuses) {
    $query->whereIn('ffo.status', ['draft', 'submitted']);
}

$rows = $query->get();
echo 'Baris FO di-scan: ' . $rows->count() . "\n";

$itemIds = $rows->pluck('item_id')->unique()->values()->all();
echo 'Item unik: ' . count($itemIds) . "\n";

$items = collect();
foreach (array_chunk($itemIds, 500) as $chunk) {
    $items = $items->merge(DB::table('items')->whereIn('id', $chunk)->get());
}
$items = $items->keyBy('id');

$unitIds = $items->flatMap(fn ($i) => [$i->small_unit_id, $i->medium_unit_id, $i->large_unit_id])
    ->filter()->unique()->values()->all();
$unitNameById = $unitIds !== []
    ? DB::table('units')->whereIn('id', $unitIds)->pluck('name', 'id')->all()
    : [];

$priceRowsByItem = collect();
foreach (array_chunk($itemIds, 500) as $chunk) {
    $batch = DB::table('item_prices')
        ->whereIn('item_id', $chunk)
        ->orderByDesc('id')
        ->get();
    foreach ($batch->groupBy('item_id') as $itemId => $group) {
        $priceRowsByItem[$itemId] = ($priceRowsByItem[$itemId] ?? collect())->merge($group)->sortByDesc('id')->values();
    }
}
echo "Master item & item_prices loaded.\n";

$pickPriceRow = static function ($rows, ?int $regionId, ?string $outletId): ?object {
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
};

$autoPriceCache = [];
$largePriceCache = [];

$resolveLarge = static function (int $itemId, ?object $priceRow, object $item) use ($autoPriceCache): float {
    $mode = ($priceRow && ($priceRow->pricing_mode ?? '') === 'auto') ? 'auto' : 'manual';
    if ($mode === 'auto') {
        $auto = $autoPriceCache[$itemId] ?? null;
        if ($auto !== null && $auto > 0) {
            return (float) $auto;
        }
    }
    if ($priceRow && (float) $priceRow->price > 0) {
        return (float) $priceRow->price;
    }
    $auto = $autoPriceCache[$itemId] ?? null;

    return ($auto !== null && $auto > 0) ? (float) $auto : 0.0;
};

$resolveExpected = static function (
    int $itemId,
    ?string $unitName,
    ?int $regionId,
    ?string $outletId,
) use ($items, $priceRowsByItem, $pickPriceRow, $resolveLarge, &$largePriceCache, $unitNameById): array {
    $cacheKey = $itemId . '|' . ($regionId ?? 0) . '|' . ($outletId ?? '0');
    $item = $items[$itemId] ?? null;
    if (! $item) {
        return ['expected' => 0.0, 'large' => 0.0, 'mode' => 'n/a', 'tier' => 'medium'];
    }

    if (! isset($largePriceCache[$cacheKey])) {
        $rows = $priceRowsByItem->get($itemId, collect());
        $priceRow = $pickPriceRow($rows, $regionId, $outletId);
        $large = $resolveLarge($itemId, $priceRow, $item);
        $mode = ($priceRow && ($priceRow->pricing_mode ?? '') === 'auto') ? 'auto' : 'manual';
        $largePriceCache[$cacheKey] = ['large' => $large, 'mode' => $mode];
    }

    $large = $largePriceCache[$cacheKey]['large'];
    $mode = $largePriceCache[$cacheKey]['mode'];
    if ($large <= 0) {
        return ['expected' => 0.0, 'large' => 0.0, 'mode' => $mode, 'tier' => 'medium'];
    }

    $tier = FloorOrderItemPriceResolver::detectUnitTier($item, $unitName, $unitNameById);
    $expected = match ($tier) {
        'large' => FloorOrderItemPriceResolver::roundUpToHundred($large),
        'small' => FloorOrderItemPriceResolver::roundUpToHundred(
            FloorOrderItemPriceResolver::largeToSmallPrice($large, $item)
        ),
        default => FloorOrderItemPriceResolver::roundUpToHundred(
            FloorOrderItemPriceResolver::largeToMediumPrice($large, $item)
        ),
    };

    return ['expected' => $expected, 'large' => $large, 'mode' => $mode, 'tier' => $tier];
};

$needsGrLookup = [];
foreach ($itemIds as $itemId) {
    $priceRows = $priceRowsByItem->get($itemId, collect());
    $hasPositiveManual = $priceRows->contains(
        fn ($r) => ($r->pricing_mode ?? 'manual') !== 'auto' && (float) ($r->price ?? 0) > 0
    );
    $hasAuto = $priceRows->contains(fn ($r) => ($r->pricing_mode ?? '') === 'auto');
    if ($hasAuto || ! $hasPositiveManual) {
        $needsGrLookup[] = (int) $itemId;
    }
}
echo 'Preload harga GR/auto: ' . count($needsGrLookup) . " item\n";
foreach ($needsGrLookup as $i => $itemId) {
    $autoPriceCache[$itemId] = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
    if (($i + 1) % 100 === 0) {
        echo '  ' . ($i + 1) . '/' . count($needsGrLookup) . "\n";
    }
}
echo "Memproses baris FO...\n";

$mismatches = [];
$matched = 0;
$skippedNoPrice = 0;
$processed = 0;

foreach ($rows as $row) {
    $processed++;
    if ($processed % 2000 === 0) {
        echo "  ...{$processed}/{$rows->count()}\n";
    }
    $itemId = (int) $row->item_id;
    $item = $items[$itemId] ?? null;
    if (! $item) {
        continue;
    }

    $regionId = $row->region_id ? (int) $row->region_id : null;
    $outletId = $row->id_outlet ? (string) $row->id_outlet : null;
    $unitName = (string) ($row->unit ?? '');

    $resolved = $resolveExpected($itemId, $unitName, $regionId, $outletId);
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
        'item_id' => $itemId,
        'item_name' => (string) $row->item_name,
        'tanggal' => (string) $row->tanggal,
        'order_number' => (string) ($row->order_number ?? ''),
        'status' => (string) ($row->status ?? ''),
        'outlet' => (string) ($row->nama_outlet ?? ''),
        'unit' => $unitName,
        'unit_tier' => $resolved['tier'],
        'current_price' => $current,
        'expected_price' => $expected,
        'diff' => round($current - $expected, 2),
        'price_large' => $resolved['large'],
        'pricing_mode' => $resolved['mode'],
        'medium_conv' => (float) ($item->medium_conversion_qty ?? 1),
        'small_conv' => (float) ($item->small_conversion_qty ?? 1),
        'qty' => (float) $row->qty,
        'line_id' => (int) $row->id,
    ];
}

echo 'Cocok: ' . $matched . "\n";
echo 'Tanpa harga master: ' . $skippedNoPrice . "\n";
echo 'Selisih: ' . count($mismatches) . " baris\n\n";

$byItem = [];
foreach ($mismatches as $m) {
    $key = $m['item_id'];
    if (! isset($byItem[$key])) {
        $byItem[$key] = [
            'item_id' => $m['item_id'],
            'item_name' => $m['item_name'],
            'price_large' => $m['price_large'],
            'pricing_mode' => $m['pricing_mode'],
            'medium_conv' => $m['medium_conv'],
            'small_conv' => $m['small_conv'],
            'mismatch_rows' => 0,
            'current_prices' => [],
            'expected_prices' => [],
            'units' => [],
        ];
    }
    $byItem[$key]['mismatch_rows']++;
    $byItem[$key]['current_prices'][(string) $m['current_price']] = ($byItem[$key]['current_prices'][(string) $m['current_price']] ?? 0) + 1;
    $byItem[$key]['expected_prices'][(string) $m['expected_price']] = ($byItem[$key]['expected_prices'][(string) $m['expected_price']] ?? 0) + 1;
    $unitKey = $m['unit'] . ' (' . $m['unit_tier'] . ')';
    $byItem[$key]['units'][$unitKey] = ($byItem[$key]['units'][$unitKey] ?? 0) + 1;
}

uasort($byItem, fn ($a, $b) => $b['mismatch_rows'] <=> $a['mismatch_rows']);

echo "=== Ringkasan per item (urut jumlah baris selisih) ===\n";
printf("%-6s %-32s %6s %12s %6s %5s %10s %10s %s\n",
    'ID', 'Item', 'Baris', 'Harga Large', 'MedC', 'Mode', 'FO harga', 'Seharusnya', 'Unit FO');
echo str_repeat('-', 130) . "\n";

foreach ($byItem as $summary) {
    $currentParts = [];
    foreach ($summary['current_prices'] as $price => $cnt) {
        $currentParts[] = number_format((float) $price, 0, ',', '.') . " (x{$cnt})";
    }
    $expectedParts = [];
    foreach ($summary['expected_prices'] as $price => $cnt) {
        $expectedParts[] = number_format((float) $price, 0, ',', '.') . " (x{$cnt})";
    }
    $unitParts = [];
    foreach ($summary['units'] as $unit => $cnt) {
        $unitParts[] = "{$unit} x{$cnt}";
    }

    printf(
        "%-6d %-32s %6d %12s %6s %5s %s | %s | %s\n",
        $summary['item_id'],
        mb_substr($summary['item_name'], 0, 32),
        $summary['mismatch_rows'],
        number_format($summary['price_large'], 0, ',', '.'),
        $summary['medium_conv'],
        $summary['pricing_mode'],
        implode(', ', $currentParts),
        implode(', ', $expectedParts),
        implode('; ', $unitParts),
    );
}

echo "\n=== Sample baris selisih (max 30) ===\n";
$sample = array_slice($mismatches, 0, 30);
foreach ($sample as $m) {
    echo "[{$m['status']}] {$m['tanggal']} {$m['order_number']} | {$m['outlet']}\n";
    echo "  {$m['item_name']} (#{$m['item_id']}) | unit={$m['unit']} ({$m['unit_tier']})\n";
    echo '  FO: ' . number_format($m['current_price'], 0, ',', '.')
        . ' → seharusnya: ' . number_format($m['expected_price'], 0, ',', '.')
        . ' (large=' . number_format($m['price_large'], 0, ',', '.')
        . ", conv med={$m['medium_conv']}, mode={$m['pricing_mode']})\n";
}
if (count($mismatches) > count($sample)) {
    echo '... dan ' . (count($mismatches) - count($sample)) . " baris lagi\n";
}

if ($csvPath) {
    $fp = fopen($csvPath, 'w');
    if ($fp) {
        fputcsv($fp, [
            'line_id', 'item_id', 'item_name', 'tanggal', 'order_number', 'status', 'outlet',
            'unit', 'unit_tier', 'qty', 'current_price', 'expected_price', 'diff',
            'price_large', 'medium_conv', 'small_conv', 'pricing_mode',
        ]);
        foreach ($mismatches as $m) {
            fputcsv($fp, [
                $m['line_id'], $m['item_id'], $m['item_name'], $m['tanggal'], $m['order_number'],
                $m['status'], $m['outlet'], $m['unit'], $m['unit_tier'], $m['qty'],
                $m['current_price'], $m['expected_price'], $m['diff'],
                $m['price_large'], $m['medium_conv'], $m['small_conv'], $m['pricing_mode'],
            ]);
        }
        fclose($fp);
        echo "\nCSV: {$csvPath}\n";
    }
}

echo "\nTotal item unik selisih: " . count($byItem) . "\n";
