<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\PayrollReportController;
use App\Services\PayrollScTraceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$userId = 1850;
$outletId = 24;

foreach ([4, 5] as $month) {
    $year = 2026;
    $start = date('Y-m-d', strtotime("$year-$month-26 -1 month"));
    $end = date('Y-m-d', strtotime("$year-$month-25"));
    $g2s = Carbon::create($year, $month, 1)->startOfDay();
    $g2e = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

    echo "\n========== Payroll bulan {$month}/{$year} ==========\n";
    echo "Gajian1: {$start} .. {$end}\n";
    echo "Gajian2: {$g2s->toDateString()} .. {$g2e->toDateString()}\n";

    $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
    $ctrl = app(PayrollReportController::class);
    $ref = new ReflectionMethod($ctrl, 'collectMutationsForPayrollOutlet');
    $ref->setAccessible(true);
    $mutations = $ref->invoke($ctrl, $outletId, $outletName, $start, $end, $g2s, $g2e);

    echo "Mutations found: ".$mutations->count()."\n";
    foreach ($mutations as $m) {
        if ((int) $m->employee_id === $userId) {
            echo "  Iqbal movement id={$m->id} effective={$m->employment_effective_date} from={$m->unit_property_from} to={$m->unit_property_to} unit_property_change={$m->unit_property_change}\n";
        }
    }

    $ref2 = new ReflectionMethod($ctrl, 'buildPayrollMutationMap');
    $ref2->setAccessible(true);
    $map = $ref2->invoke($ctrl, $mutations, $outletId, $outletName);
    if (isset($map[$userId])) {
        echo 'Mutation map: '.json_encode($map[$userId])."\n";
        $ref3 = new ReflectionMethod($ctrl, 'resolveMutationPayrollContext');
        $ref3->setAccessible(true);
        $ctx = $ref3->invoke($ctrl, $map[$userId], Carbon::parse($start), Carbon::parse($end), $g2s, $g2e);
        echo 'Context: gajian1_days='.$ctx['hariKerjaGajian1'].' gajian2_calendar='.$ctx['hariKerjaGajian2'].' role='.$ctx['mutationRole']."\n";
    } else {
        echo "Iqbal NOT in mutation map (treated as normal employee)\n";
    }

    $tracer = app(PayrollScTraceService::class);
    $result = $tracer->run($outletId, $year, $month, 22750551);
    foreach ($result['rows'] as $row) {
        if (stripos($row['nama'], 'Iqbal Hamdani') !== false) {
            echo "Payroll API: hari_kerja={$row['hari_kerja']} hari_g2={$row['hari_kerja_gajian2']} mutasi=".($row['is_mutasi'] ? 'yes' : 'no')." sc={$row['erp_sc']}\n";
        }
    }
}
