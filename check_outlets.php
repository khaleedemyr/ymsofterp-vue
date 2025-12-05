<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Available outlets:\n";
$outlets = DB::table('tbl_data_outlet')->select('qr_code', 'nama_outlet')->get();

foreach ($outlets as $outlet) {
    echo $outlet->qr_code . " - " . $outlet->nama_outlet . "\n";
}

echo "\nTotal outlets: " . $outlets->count() . "\n"; 