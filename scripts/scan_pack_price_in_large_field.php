<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$sql = <<<'SQL'
SELECT
    i.id AS item_id,
    i.name,
    w.name AS warehouse,
    um.name AS medium_unit,
    COALESCE(i.medium_conversion_qty, 1) AS medium_conv,
    ip.price AS price_large,
    CEIL(ip.price / 100) * 100 AS master_ui_pack,
    CEIL((ip.price / COALESCE(NULLIF(i.medium_conversion_qty, 0), 1)) / 100) * 100 AS sys_pack_price
FROM items i
JOIN warehouse_division wd ON wd.id = i.warehouse_division_id
JOIN warehouses w ON w.id = wd.warehouse_id
LEFT JOIN units um ON um.id = i.medium_unit_id
JOIN item_prices ip ON ip.item_id = i.id AND ip.availability_price_type = 'all'
WHERE ip.price > 0
  AND COALESCE(i.medium_conversion_qty, 1) > 1
  AND w.name IN ('Main Store', 'MK1 Hot Kitchen', 'MK2 Cold Kitchen', 'Main Kitchen')
  AND ABS(
      CEIL((ip.price / COALESCE(NULLIF(i.medium_conversion_qty, 0), 1)) / 100) * 100
      - CEIL(ip.price / 100) * 100
  ) > 500
ORDER BY w.name, i.name
SQL;

$rows = DB::select($sql);

echo 'Candidates (master UI pack != sys divided pack): ' . count($rows) . PHP_EOL . PHP_EOL;
printf("%-8s %-32s %-14s %6s %12s %12s %12s\n", 'id', 'Item', 'Warehouse', 'conv', 'sys Pack', 'master UI', 'new large');
echo str_repeat('-', 110) . PHP_EOL;

$ids = [];
foreach ($rows as $r) {
    $newLarge = round((float) $r->price_large * (float) $r->medium_conv, 2);
    printf(
        "%-8d %-32s %-14s %6.0f %12s %12s %12s\n",
        $r->item_id,
        mb_substr($r->name, 0, 32),
        mb_substr($r->warehouse, 0, 14),
        $r->medium_conv,
        number_format($r->sys_pack_price, 0, ',', '.'),
        number_format($r->master_ui_pack, 0, ',', '.'),
        number_format($newLarge, 0, ',', '.'),
    );
    $ids[] = (int) $r->item_id;
}

$csv = __DIR__ . '/pack_price_fix_candidates.csv';
$fp = fopen($csv, 'w');
fputcsv($fp, ['item_id']);
foreach ($ids as $id) {
    fputcsv($fp, [$id]);
}
fclose($fp);
echo PHP_EOL . "CSV: {$csv} (" . count($ids) . " items)\n";
