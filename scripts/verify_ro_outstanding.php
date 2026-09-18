<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\OpexOutletDashboardService;
use Illuminate\Support\Facades\DB;

$outlet = DB::table('tbl_data_outlet')->where('nama_outlet', 'Justus Steak House Cipete')->first();
$svc = app(OpexOutletDashboardService::class);
$fmt = fn ($n) => number_format((float) $n, 0, ',', '.');

$f = $svc->buildRoForecastSummary((int) $outlet->id_outlet, '2026-09-01', '2026-09-30');

echo "FB purchased={$fmt($f['fb']['purchased'])}\n";
echo "FB outstanding={$fmt($f['fb']['ro_outstanding'])}\n";
echo "FB remaining={$fmt($f['fb']['remaining'])}\n";
echo "FB remaining after commit={$fmt($f['fb']['remaining_after_commit'])}\n";
echo "SVC purchased={$fmt($f['service']['purchased'])}\n";
echo "SVC outstanding={$fmt($f['service']['ro_outstanding'])}\n";
echo "Outstanding total={$fmt($f['ro_outstanding_total'])}\n";
