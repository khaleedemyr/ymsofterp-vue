<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$missing = [
    'Choux Powder',
    'Coking Glove',
    'Creamer Bubuk',
    'Garam Sachet',
    'Genoise Chocolate',
    'Gula Palem',
    'Kawista Syrup',
    'Kecap Manis Botol',
    'Mint Syrup',
    'Nestea Lemon',
    'Olive Oil Extra',
    'Pandao',
    'Pasta Macaroni Spiral',
    'Pastaro',
    'Plastik Roll 25Cm',
    'Plastik Wrap 45Cm',
    'Salad Bowl Round 1000',
    'Sauce BBQ',
    'Soy Sauce Botol',
    'Steak Stick',
    'Strawberry Pure',
    'Tepung Bumbu',
    'Tepung Protein Sedang',
    'Tissue Toilet',
];

foreach ($missing as $name) {
    echo "\n=== {$name} ===\n";
    $tokens = preg_split('/\s+/', $name);
    $q = DB::table('items')->where('status', 'active');
    foreach ($tokens as $t) {
        if (strlen($t) < 3) continue;
        $q->where('name', 'like', '%' . $t . '%');
    }
    $rows = $q->orderBy('name')->limit(10)->get(['id', 'name']);
    if ($rows->isEmpty()) {
        // try first 2 tokens only
        $q2 = DB::table('items')->where('status', 'active');
        foreach (array_slice($tokens, 0, 2) as $t) {
            if (strlen($t) < 3) continue;
            $q2->where('name', 'like', '%' . $t . '%');
        }
        $rows = $q2->orderBy('name')->limit(10)->get(['id', 'name']);
    }
    if ($rows->isEmpty()) {
        echo "  (none)\n";
        continue;
    }
    foreach ($rows as $r) {
        echo "  {$r->id} | {$r->name}\n";
    }
}
