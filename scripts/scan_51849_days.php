<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Report\WarehouseReportController;
use Illuminate\Http\Request;

$controller = app(WarehouseReportController::class);
$customer = 'Justus Steak House Cipete';

for ($d = strtotime('2026-05-01'); $d <= strtotime('2026-06-30'); $d += 86400) {
    $day = date('Y-m-d', $d);
    $req = Request::create('/api/fj-detail', 'GET', [
        'customer' => $customer,
        'from' => $day,
        'to' => $day,
    ]);
    $res = $controller->fjDetail($req);
    $data = json_decode($res->getContent(), true);
    $items = $data['main_store']['all'] ?? [];
    foreach ($items as $item) {
        if (($item['item_name'] ?? '') !== 'Beef Tenderloin Aussie 250gr') {
            continue;
        }
        $qty = (float) ($item['received_qty'] ?? 0);
        $price = (float) ($item['price'] ?? 0);
        if ($qty > 0 && abs($price - 51849) < 200) {
            echo "{$day}: qty={$qty} price=" . number_format($price, 2) . ' subtotal=' . number_format((float) $item['subtotal'], 2) . "\n";
        }
    }
}

echo "Done scan.\n";
