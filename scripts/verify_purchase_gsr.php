<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\OpexOutletDashboardService;
use Illuminate\Support\Facades\DB;

$outlet = DB::table('tbl_data_outlet')->where('nama_outlet', 'Justus Steak House Cipete')->first();
$oid = (int) $outlet->id_outlet;
$svc = app(OpexOutletDashboardService::class);
$fmt = fn ($n) => number_format((float) $n, 0, ',', '.');

$from = '2026-09-01';
$to = '2026-09-30';

$gsr = $svc->sumGsrRo($oid, $from, $to);
$rf = $svc->sumRetailFood($oid, $from, $to);
$forecast = $svc->buildRoForecastSummary($oid, $from, $to);

echo "GSR card={$fmt($gsr['gsr_total'])} GR card={$fmt($gsr['gr_total'])}\n";
echo "RF={$fmt($rf['total'])}\n";
echo "Kitchen purchased={$fmt($forecast['kitchen']['purchased'])}\n";
echo "Bar purchased={$fmt($forecast['bar']['purchased'])}\n";
echo "Service purchased={$fmt($forecast['service']['purchased'])}\n";
echo "Purchased sum={$fmt($forecast['kitchen']['purchased'] + $forecast['bar']['purchased'] + $forecast['service']['purchased'])}\n";
echo "Expected GSR+RF (if all GSR in kitchen/bar/service)={$fmt($gsr['total'] + $rf['total'])}\n";
