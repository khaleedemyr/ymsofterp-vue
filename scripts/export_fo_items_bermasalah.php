<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderPriceAuditor;

$from = '2026-06-12';
$to = null;
$allStatuses = true;
$output = __DIR__ . '/fo_items_harga_bermasalah.csv';

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, strpos($arg, '=') + 1);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, strpos($arg, '=') + 1);
    }
    if (str_starts_with($arg, '--output=')) {
        $output = substr($arg, strlen('--output='));
    }
    if ($arg === '--draft-submitted-only') {
        $allStatuses = false;
    }
}

echo "Scan FO {$from}" . ($to ? " s/d {$to}" : ' s/d hari ini') . "\n";
echo 'Status: ' . ($allStatuses ? 'semua' : 'draft, submitted') . "\n\n";

$auditor = new FloorOrderPriceAuditor();
$result = $auditor->scan($from, $to, $allStatuses);
$mismatches = $result['mismatches'];

$byItem = [];
foreach ($mismatches as $m) {
    $id = $m['item_id'];
    if (! isset($byItem[$id])) {
        $byItem[$id] = [
            'item_id' => $id,
            'item_name' => $m['item_name'],
            'mismatch_rows' => 0,
            'price_large' => $m['price_large'],
            'medium_conv' => $m['medium_conv'],
            'small_conv' => $m['small_conv'],
            'pricing_mode' => $m['pricing_mode'],
            'units' => [],
            'fo_prices' => [],
            'expected_prices' => [],
        ];
    }
    $byItem[$id]['mismatch_rows']++;
    $uk = $m['unit'] . ' (' . $m['unit_tier'] . ')';
    $byItem[$id]['units'][$uk] = ($byItem[$id]['units'][$uk] ?? 0) + 1;
    $pk = (string) $m['current_price'];
    $byItem[$id]['fo_prices'][$pk] = ($byItem[$id]['fo_prices'][$pk] ?? 0) + 1;
    $ek = (string) $m['expected_price'];
    $byItem[$id]['expected_prices'][$ek] = ($byItem[$id]['expected_prices'][$ek] ?? 0) + 1;
}

uasort($byItem, fn ($a, $b) => $b['mismatch_rows'] <=> $a['mismatch_rows']);

$fp = fopen($output, 'w');
fputcsv($fp, [
    'item_id',
    'item_name',
    'baris_selisih',
    'harga_large_item_prices',
    'medium_conversion_qty',
    'small_conversion_qty',
    'pricing_mode',
    'unit_fo',
    'harga_fo_saat_ini',
    'harga_seharusnya',
]);

foreach ($byItem as $row) {
    $formatPrices = static function (array $map): string {
        $parts = [];
        foreach ($map as $price => $cnt) {
            $parts[] = number_format((float) $price, 0, ',', '.') . " (x{$cnt})";
        }

        return implode('; ', $parts);
    };

    fputcsv($fp, [
        $row['item_id'],
        $row['item_name'],
        $row['mismatch_rows'],
        $row['price_large'],
        $row['medium_conv'],
        $row['small_conv'],
        $row['pricing_mode'],
        implode('; ', array_map(fn ($u, $c) => "{$u} x{$c}", array_keys($row['units']), $row['units'])),
        $formatPrices($row['fo_prices']),
        $formatPrices($row['expected_prices']),
    ]);
}
fclose($fp);

echo "Baris FO di-scan: {$result['rows_scanned']}\n";
echo 'Item bermasalah: ' . count($byItem) . "\n";
echo "File: {$output}\n";
