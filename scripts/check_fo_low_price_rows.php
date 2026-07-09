<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$targets = [
    52334 => 17500,   // Beras Lokal
    52337 => 257700,  // Black Pepper
    52339 => 61700,   // Peach
    52351 => 146100,  // Coca Cola
    52360 => 453900,  // Fryall
    52365 => 34900,   // Gerkin
    52388 => 121000,  // Madu
    52537 => 17400,   // Sauce Chilli
    52717 => 30300,   // Dishwash
    52819 => 6500,    // Tissue Towel
    54789 => 44900,   // HVS
];

echo "FO lines where price ≈ master/1.12 (ratio 0.88–0.91) — last 90 days\n\n";

foreach ($targets as $itemId => $master) {
    $lowMin = $master / 1.12 * 0.98;
    $lowMax = $master / 1.12 * 1.02;

    $rows = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->join('items as i', 'i.id', '=', 'ffoi.item_id')
        ->where('ffoi.item_id', $itemId)
        ->whereBetween('ffoi.price', [$lowMin, $lowMax])
        ->whereDate('ffo.tanggal', '>=', '2026-04-01')
        ->orderByDesc('ffo.tanggal')
        ->limit(5)
        ->get([
            'i.name',
            'ffo.order_number',
            'ffo.tanggal',
            'ffo.status',
            'ffo.fo_mode',
            'ffo.id_outlet',
            'ffoi.unit',
            'ffoi.price',
            'ffoi.created_at',
            'ffoi.updated_at',
        ]);

    $countAll = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->whereBetween('ffoi.price', [$lowMin, $lowMax])
        ->whereDate('ffo.tanggal', '>=', '2026-04-01')
        ->count();

    $countOk = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->whereBetween('ffoi.price', [$master * 0.995, $master * 1.005])
        ->whereDate('ffo.tanggal', '>=', '2026-04-01')
        ->count();

    $name = DB::table('items')->where('id', $itemId)->value('name');
    echo "=== {$name} (id={$itemId}) master={$master} | bad≈/1.12 count={$countAll} | ok=master count={$countOk} ===\n";
    if ($rows->isEmpty()) {
        echo "  (no FO rows with cost-like price since Apr 2026)\n\n";
        continue;
    }
    foreach ($rows as $r) {
        $ratio = round(((float) $r->price) / $master, 4);
        echo sprintf(
            "  %s | %s | status=%s | mode=%s | outlet=%s | unit=%s | fo_price=%s | ratio=%s | created=%s\n",
            $r->tanggal,
            $r->order_number,
            $r->status,
            $r->fo_mode,
            $r->id_outlet,
            $r->unit,
            number_format((float) $r->price, 2, '.', ','),
            $ratio,
            $r->created_at
        );
    }
    echo "\n";
}
