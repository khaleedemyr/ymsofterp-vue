<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Traits\ReportHelperTrait;
use Illuminate\Support\Facades\DB;

class TraceFjDetail extends \App\Http\Controllers\Controller
{
    use ReportHelperTrait;

    public function run(string $customer, string $from, string $to): void
    {
        $food = $this->rekapFjFetchFoodGrDetailRows($customer, $from, $to, 'MAIN STORE', null, ['Chemical', 'Stationary', 'Marketing']);
        $serial = $this->rekapFjFetchSerialGrDetailRows($customer, $from, $to, 'MAIN STORE', null, ['Chemical', 'Stationary', 'Marketing']);
        $merged = $this->rekapFjMergeFjDetailRows($food, $serial);

        foreach ($merged as $row) {
            if (stripos($row->item_name, 'Tenderloin Aussie') === false) {
                continue;
            }
            echo "{$row->item_name} | {$row->category} | {$row->unit}\n";
            echo "  qty={$row->received_qty} price=" . number_format($row->price, 2) . ' subtotal=' . number_format($row->subtotal, 2) . "\n";
        }

        echo "\n--- Food only ---\n";
        foreach ($food as $row) {
            if (stripos($row->item_name, 'Tenderloin Aussie') === false) {
                continue;
            }
            echo "{$row->item_name}: qty={$row->received_qty} price=" . number_format($row->price, 2) . "\n";
        }

        echo "\n--- Serial only ---\n";
        foreach ($serial as $row) {
            if (stripos($row->item_name, 'Tenderloin Aussie') === false) {
                continue;
            }
            echo "{$row->item_name}: qty={$row->received_qty} price=" . number_format($row->price, 2) . " subtotal=" . number_format($row->subtotal, 2) . "\n";
        }
    }
}

$trace = new TraceFjDetail();
$trace->run('Justus Steak House Cipete', '2026-06-01', '2026-06-30');
