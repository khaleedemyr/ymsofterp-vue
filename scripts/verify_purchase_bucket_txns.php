<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\OpexOutletDashboardService;
use Illuminate\Support\Facades\DB;

$outlet = DB::table('tbl_data_outlet')->where('nama_outlet', 'like', '%Cipete%')->first();
$oid = (int) $outlet->id_outlet;
$svc = app(OpexOutletDashboardService::class);
$fmt = fn ($n) => number_format((float) $n, 0, ',', '.');

foreach (['kitchen', 'bar', 'service'] as $bucket) {
    $txns = $svc->listPurchasedBucketTransactions($oid, '2026-09-01', '2026-09-19', $bucket);
    $sum = array_sum(array_map(fn ($t) => (float) $t->amount, $txns));
    $items = array_sum(array_map(fn ($t) => count($t->items ?? []), $txns));
    echo strtoupper($bucket)." txns=".count($txns)." amount={$fmt($sum)} item_rows={$items}\n";
    if ($txns !== []) {
        $first = $txns[0];
        echo "  sample: {$first->source} {$first->number} {$fmt($first->amount)} items=".count($first->items)."\n";
    }
}

$forecast = $svc->buildRoForecastSummary($oid, '2026-09-01', '2026-09-19');
echo "card kitchen purchased={$fmt($forecast['kitchen']['purchased'])}\n";
echo "card bar purchased={$fmt($forecast['bar']['purchased'])}\n";
echo "card service purchased={$fmt($forecast['service']['purchased'])}\n";
