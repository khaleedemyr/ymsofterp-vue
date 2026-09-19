<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = app(App\Services\OpexOutletDashboardService::class);
$txns = $svc->listPurchasedBucketTransactions(20, '2026-09-01', '2026-09-30', 'bar');
$t = collect($txns)->firstWhere('number', 'GSR-20260916-0017');
echo 'amount='.$t->amount."\n";
echo 'items='.json_encode($t->items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
