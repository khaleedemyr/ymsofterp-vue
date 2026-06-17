<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Traits\ReportHelperTrait;

class TraceFjDetail extends \App\Http\Controllers\Controller
{
    use ReportHelperTrait;

    public function merged(string $customer, string $from, string $to): ?object
    {
        $food = $this->rekapFjFetchFoodGrDetailRows($customer, $from, $to, 'MAIN STORE', null, ['Chemical', 'Stationary', 'Marketing']);
        $serial = $this->rekapFjFetchSerialGrDetailRows($customer, $from, $to, 'MAIN STORE', null, ['Chemical', 'Stationary', 'Marketing']);
        $merged = $this->rekapFjMergeFjDetailRows($food, $serial);

        return $merged->first(fn ($r) => $r->item_name === 'Beef Tenderloin Aussie 250gr');
    }
}

$trace = new TraceFjDetail();
$customer = 'Justus Steak House Cipete';

// Scan weekly windows in 2026
$start = strtotime('2026-06-01');
$end = strtotime('2026-06-30');
for ($d = $start; $d <= $end; $d += 86400 * 7) {
    $from = date('Y-m-d', $d);
    $to = date('Y-m-d', min($d + 86400 * 6, $end));
    $row = $trace->merged($customer, $from, $to);
    if ($row) {
        echo "{$from}..{$to} qty={$row->received_qty} price=" . number_format($row->price, 2) . "\n";
    }
}

echo "\nTarget ~51849 qty=30:\n";
$row = $trace->merged($customer, '2026-06-01', '2026-06-30');
if ($row) {
    echo "Full June: qty={$row->received_qty} price=" . number_format($row->price, 2) . "\n";
}

// Try single day Jun 15 when serial GSR happened
foreach (['2026-06-15', '2026-06-14', '2026-06-01', '2026-06-04', '2026-06-06'] as $day) {
    $row = $trace->merged($customer, $day, $day);
    if ($row && (float) $row->received_qty > 0) {
        echo "{$day}: qty={$row->received_qty} price=" . number_format($row->price, 2) . "\n";
    }
}
