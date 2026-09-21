<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\WarehouseMacAnomalyDetectionService;
use Illuminate\Support\Facades\DB;

$menu = DB::table('erp_menu')->where('code', 'warehouse_cost_health_dashboard')->first();
echo "MENU: " . json_encode($menu) . PHP_EOL;

$perm = DB::table('erp_permission')->where('code', 'warehouse_cost_health_dashboard_view')->first();
echo "PERM: " . json_encode($perm) . PHP_EOL;

if ($perm) {
    echo "ROLES: " . json_encode(DB::table('erp_role_permission')->where('permission_id', $perm->id)->pluck('role_id')) . PHP_EOL;
}

$svc = app(WarehouseMacAnomalyDetectionService::class);
$snap = $svc->dashboardSnapshot([
    'date_from' => now()->subDays(7)->format('Y-m-d'),
    'date_to' => now()->format('Y-m-d'),
    'max_mac' => 10_000_000,
]);

echo "KPIS: " . json_encode($snap['kpis']) . PHP_EOL;
echo "TOP_WH: " . count($snap['top_warehouses']) . " MODULES: " . count($snap['module_breakdown']) . " ITEMS: " . count($snap['top_items']) . PHP_EOL;
