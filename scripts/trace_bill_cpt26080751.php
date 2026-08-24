<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$needle = 'CPT26080751';
$cols = Schema::getColumnListing('orders');
echo "orders columns: " . implode(', ', $cols) . PHP_EOL . PHP_EOL;

$candidates = array_values(array_filter($cols, function ($c) {
    return stripos($c, 'bill') !== false
        || stripos($c, 'order') !== false
        || stripos($c, 'nomor') !== false
        || stripos($c, 'no_') !== false
        || $c === 'id';
}));

echo "candidate search cols: " . implode(', ', $candidates) . PHP_EOL;

$query = DB::table('orders');
$query->where(function ($q) use ($needle, $candidates) {
    foreach ($candidates as $col) {
        $q->orWhere($col, 'like', '%' . $needle . '%');
    }
});

$rows = $query->limit(20)->get();
echo "matched orders: " . $rows->count() . PHP_EOL;
foreach ($rows as $row) {
    echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

// Also search by receipt-like ids
foreach (['msykoet29lg3', 'msykmb49v80', 'CPT-msykoet29lg3'] as $alt) {
    $altRows = DB::table('orders')->where(function ($q) use ($alt, $candidates) {
        foreach ($candidates as $col) {
            $q->orWhere($col, 'like', '%' . $alt . '%');
        }
    })->limit(5)->get();
    echo PHP_EOL . "search {$alt}: " . $altRows->count() . PHP_EOL;
    foreach ($altRows as $row) {
        echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
