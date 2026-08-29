<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$keys = [
    'JTFCL-mtce39sxiluf',
    'JTFCL26081162',
    'JTFCL28081162',
    'JTFCL-mtczr32o2oi9',
    'JTFCL26081209',
    'JTFCL-mtcggtwn8azb',
    'JTFCL26081183',
];

function fmt($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

function dumpOrder($order): void
{
    echo str_repeat('=', 80) . "\n";
    echo "ORDER {$order->id} | paid {$order->paid_number} | {$order->status} | {$order->created_at}\n";
    echo sprintf(
        "  table=%s pax=%s mode=%s waiter=%s\n",
        $order->table, $order->pax, $order->mode, $order->waiters
    );
    echo sprintf(
        "  total=%s disc=%s manual=%s cb=%s dpp=%s pb1=%s svc=%s comm=%s rnd=%s GT=%s\n",
        fmt($order->total), fmt($order->discount), fmt($order->manual_discount_amount),
        fmt($order->cashback), fmt($order->dpp), fmt($order->pb1), fmt($order->service),
        fmt($order->commfee), fmt($order->rounding), fmt($order->grand_total)
    );

    $items = DB::table('order_items')->where('order_id', $order->id)->get();
    echo "  ITEMS ({$items->count()}):\n";
    $itemSum = 0;
    foreach ($items as $it) {
        $itemSum += (float) $it->subtotal;
        echo sprintf("    %s x%s @%s = %s\n", $it->item_name, fmt($it->qty), fmt($it->price), fmt($it->subtotal));
    }
    echo '    SUM items=' . fmt($itemSum) . ' vs total=' . fmt($order->total) . "\n";

    $pays = DB::table('order_payment')->where('order_id', $order->id)->orderBy('created_at')->orderBy('id')->get();
    echo "  PAYMENTS ({$pays->count()}):\n";
    $sumAmt = 0;
    $sumNet = 0;
    foreach ($pays as $p) {
        $sumAmt += (float) $p->amount;
        $sumNet += (float) $p->amount - (float) ($p->change ?? 0);
        echo sprintf(
            "    [%s] %s/%s amt=%s chg=%s net=%s kasir=%s note=%s @%s\n",
            $p->id,
            $p->payment_code ?? '-',
            $p->payment_type ?? '-',
            fmt($p->amount),
            fmt($p->change ?? 0),
            fmt((float) $p->amount - (float) ($p->change ?? 0)),
            $p->kasir ?? '-',
            $p->note ?? '-',
            $p->created_at
        );
    }
    $gt = (float) $order->grand_total;
    echo sprintf(
        "    SUM amount=%s SUM net(amt-chg)=%s GT=%s  GT-amt=%s  GT-net=%s\n",
        fmt($sumAmt), fmt($sumNet), fmt($gt), fmt($gt - $sumAmt), fmt($gt - $sumNet)
    );

    if (Schema::hasTable('jurnal')) {
        $jurnal = DB::table('jurnal')->where('reference_type', 'pos_order')->where('reference_id', $order->id)->get();
        echo "  JURNAL ({$jurnal->count()}):\n";
        foreach ($jurnal as $j) {
            echo sprintf(
                "    %s debit=%s kredit=%s status=%s\n",
                $j->no_jurnal, fmt($j->jumlah_debit), fmt($j->jumlah_kredit), $j->status
            );
        }
    }
    echo "\n";
}

$found = [];
foreach ($keys as $key) {
    $orders = DB::table('orders')
        ->where('id', $key)
        ->orWhere('nomor', $key)
        ->orWhere('paid_number', $key)
        ->get();
    foreach ($orders as $o) {
        $found[$o->id] = $o;
    }
}

echo 'Found unique orders: ' . count($found) . "\n\n";
foreach ($found as $o) {
    dumpOrder($o);
}

if (count($found) === 0) {
    echo "Tidak ketemu exact key, coba LIKE...\n";
    foreach (['mtce39', 'mtczr32', 'mtcggtw', '81162', '81209', '81183'] as $frag) {
        $rows = DB::table('orders')
            ->where('kode_outlet', 'FC005')
            ->whereDate('created_at', '2026-08-28')
            ->where(function ($q) use ($frag) {
                $q->where('id', 'like', "%{$frag}%")
                    ->orWhere('paid_number', 'like', "%{$frag}%");
            })
            ->get();
        echo "frag {$frag}: {$rows->count()}\n";
        foreach ($rows as $o) {
            dumpOrder($o);
        }
    }
}
