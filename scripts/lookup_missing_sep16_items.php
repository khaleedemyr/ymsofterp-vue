<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$patterns = [
    '%Rib%Eye%',
    '%rib%eye%',
    '%Blue Label%250%',
    '%Fresh%Milk%',
    '%Freshmilk%',
    '%GF%Milk%',
    '%Milk%GF%',
    '%UHT%GF%',
];

foreach ($patterns as $p) {
    echo "--- {$p} ---\n";
    $rows = DB::table('items')->where('name', 'like', $p)->orderBy('name')->limit(40)->get(['id', 'name']);
    foreach ($rows as $r) {
        $inv = DB::table('food_inventory_items')->where('item_id', $r->id)->value('id');
        echo "  {$r->id} inv=".($inv ?: '-')." {$r->name}\n";
    }
}
