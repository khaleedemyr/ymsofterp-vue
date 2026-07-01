<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$columns = DB::select("SHOW COLUMNS FROM orders");

foreach ($columns as $column) {
    echo $column->Field . " | " . $column->Type . "\n";
}
