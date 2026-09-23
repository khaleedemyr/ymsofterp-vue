<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$ids = ['PVJ26090737', 'PVJ26090749'];

foreach ($ids as $pn) {
    $o = DB::table('orders')->where('paid_number', $pn)->first();
    if (!$o) {
        echo "NOT FOUND $pn\n";
        continue;
    }
    echo "=== $pn / {$o->id} mode={$o->mode} ===\n";
    echo sprintf(
        "total=%s dpp=%s pb1=%s svc=%s comm=%s rnd=%s GT=%s\n",
        $o->total, $o->dpp, $o->pb1, $o->service, $o->commfee, $o->rounding, $o->grand_total
    );
    $items = DB::table('order_items')->where('order_id', $o->id)->get();
    $sum = 0;
    foreach ($items as $i) {
        $sum += (float) $i->subtotal;
        echo "  item {$i->item_name} qty={$i->qty} price={$i->price} sub={$i->subtotal}\n";
    }
    echo "  SUM items=$sum\n";

    $payTable = Schema::hasTable('payments') ? 'payments' : null;
    foreach (['order_payment', 'payments', 'payment_orders', 'pos_payments'] as $t) {
        if (Schema::hasTable($t)) {
            $cols = Schema::getColumnListing($t);
            $fk = null;
            foreach (['order_id', 'id_order', 'orderId'] as $c) {
                if (in_array($c, $cols, true)) {
                    $fk = $c;
                    break;
                }
            }
            if (!$fk) {
                continue;
            }
            $rows = DB::table($t)->where($fk, $o->id)->get();
            if ($rows->count() === 0) {
                continue;
            }
            echo "  PAY($t):\n";
            foreach ($rows as $p) {
                echo '    ' . json_encode($p, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }
    echo "\n";
}
