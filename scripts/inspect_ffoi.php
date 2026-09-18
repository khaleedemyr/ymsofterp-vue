<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo implode(', ', Schema::getColumnListing('food_floor_order_items')).PHP_EOL;

$row = DB::table('food_floor_order_items')->orderByDesc('id')->first();
print_r($row);
