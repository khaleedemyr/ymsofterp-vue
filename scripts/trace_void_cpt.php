<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$orderId = 'CPT-msykoet29lg3';

$rows = DB::table('pos_void_item_requests')
    ->where('order_id', 'like', '%' . $orderId . '%')
    ->orWhere('order_nomor', 'like', '%' . $orderId . '%')
    ->orWhere('order_id', 'like', '%CPT26080751%')
    ->orWhere('order_nomor', 'like', '%CPT26080751%')
    ->get();

echo "pos_void_item_requests count=" . $rows->count() . PHP_EOL;
foreach ($rows as $r) {
    echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    if (!empty($r->item_snapshot)) {
        $snap = json_decode($r->item_snapshot, true);
        echo "snapshot parsed:\n";
        echo json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}

$cols = Schema::getColumnListing('void_bill_detail_logs');
echo "\nvoid_bill_detail_logs cols=" . implode(',', $cols) . PHP_EOL;
$q = DB::table('void_bill_detail_logs');
$q->where(function ($w) use ($cols, $orderId) {
    foreach ($cols as $c) {
        if (stripos($c, 'order') !== false || stripos($c, 'bill') !== false || stripos($c, 'nomor') !== false) {
            $w->orWhere($c, 'like', '%' . $orderId . '%')
              ->orWhere($c, 'like', '%CPT26080751%');
        }
    }
});
$v = $q->limit(50)->get();
echo "void_bill_detail_logs count=" . $v->count() . PHP_EOL;
foreach ($v as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

// What item prices around 175500?
echo "\nPossible missing amount components for 175500:\n";
$candidates = [175500, 135000, 99000, 78000, 58500, 49000, 40500, 39000, 35000, 29000];
foreach ($candidates as $c) {
    if ($c > 0 && 175500 % $c === 0) {
        echo "  {$c} x " . (175500 / $c) . "\n";
    }
}
// try combinations of menu prices from remaining steaks/drinks
$prices = [135000, 99000, 49000, 39000, 35000, 29000];
echo "2-item combos =\n";
foreach ($prices as $a) {
    foreach ($prices as $b) {
        if ($a + $b === 175500) echo "  {$a}+{$b}\n";
    }
}
echo "3-item combos =\n";
foreach ($prices as $a) {
    foreach ($prices as $b) {
        foreach ($prices as $c) {
            if ($a + $b + $c === 175500) echo "  {$a}+{$b}+{$c}\n";
        }
    }
}
