<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$itemId = 54706;
$item = DB::table('items')->where('id', $itemId)->first();
$unitNameById = DB::table('units')->pluck('name', 'id')->all();

foreach (['Pack', 'Recipe', 'Mili liter', 'pack', ''] as $u) {
    $tier = FloorOrderItemPriceResolver::detectUnitTier($item, $u, $unitNameById);
    $price = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $u, 1, '18', $item);
    echo "unit=" . json_encode($u) . " tier={$tier} price={$price}\n";
}

echo "\nUnit map:\n";
foreach (['small', 'medium', 'large'] as $t) {
    $id = $item->{$t . '_unit_id'};
    echo "  {$t}: id={$id} name=" . ($unitNameById[$id] ?? '?') . "\n";
}

// Screenshot math: 38 qty @ 609463
echo "\nMixed price check for 609463 avg:\n";
foreach ([[18, 58200, 20, 1105600], [15, 58200, 23, 1105600], [3, 58200, 35, 1105600]] as $mix) {
    [$q1, $p1, $q2, $p2] = $mix;
    $avg = ($q1 * $p1 + $q2 * $p2) / ($q1 + $q2);
    echo "  {$q1}@{$p1} + {$q2}@{$p2} => avg={$avg}\n";
}
