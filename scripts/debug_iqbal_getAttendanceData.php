<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ctrl = app(\App\Http\Controllers\PayrollReportController::class);
$ref = new ReflectionMethod($ctrl, 'getAttendanceData');
$ref->setAccessible(true);

$userId = 1850;
$outletId = 24;
$start = \Carbon\Carbon::parse('2026-05-04');
$end = \Carbon\Carbon::parse('2026-05-25');

$rows = $ref->invoke($ctrl, $userId, $outletId, $start, $end);

$c1 = 0; // has_check_in && !is_off (current HK)
$c2 = 0; // is_off || has_check_in
$c3 = 0; // is_off || (has_check_in && !is_off)
$c4 = 0; // !is_alpha && (is_off || has_check_in)

foreach ($rows as $r) {
    if (!empty($r['has_check_in']) && empty($r['is_off'])) {
        $c1++;
    }
    if (!empty($r['is_off']) || !empty($r['has_check_in'])) {
        $c2++;
    }
    if (!empty($r['is_off']) || (!empty($r['has_check_in']) && empty($r['is_off']))) {
        $c3++;
    }
    if (empty($r['is_alpha']) && (!empty($r['is_off']) || !empty($r['has_check_in']))) {
        $c4++;
    }
    echo sprintf("%s off=%s in=%s alpha=%s\n", $r['tanggal'], $r['is_off']?'Y':'N', $r['has_check_in']?'Y':'N', $r['is_alpha']?'Y':'N');
}

echo "\nHK only (in && !off): $c1\n";
echo "off || in: $c2\n";
echo "off || (in && !off): $c3\n";
echo "!alpha && (off || in): $c4\n";

$ref2 = new ReflectionMethod($ctrl, 'countMutationSegmentScDays');
$ref2->setAccessible(true);
$cMut = $ref2->invoke($ctrl, $userId, $outletId, $start, $end, 'to');
echo "\ncountMutationSegmentScDays (role=to): $cMut\n";

$mikoStart = \Carbon\Carbon::parse('2026-04-26');
$mikoEnd = \Carbon\Carbon::parse('2026-05-03');
$cFrom = $ref2->invoke($ctrl, $userId, 2, $mikoStart, $mikoEnd, 'from');
echo "countMutationSegmentScDays Miko (role=from, Apr26-May3): $cFrom\n";
