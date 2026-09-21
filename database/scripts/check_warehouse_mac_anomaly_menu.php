<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$menu = DB::table('erp_menu')->where('code', 'warehouse_mac_anomaly_tracking')->first();
echo "MENU: " . json_encode($menu) . PHP_EOL;

$perm = DB::table('erp_permission')->where('code', 'warehouse_mac_anomaly_tracking_view')->first();
echo "PERM: " . json_encode($perm) . PHP_EOL;

if ($perm) {
    $roles = DB::table('erp_role_permission')->where('permission_id', $perm->id)->pluck('role_id');
    echo "ROLES with warehouse anomaly: " . json_encode($roles) . PHP_EOL;
}

$outletPerm = DB::table('erp_permission')->where('code', 'mac_anomaly_tracking_view')->first();
if ($outletPerm) {
    $outletRoles = DB::table('erp_role_permission')->where('permission_id', $outletPerm->id)->pluck('role_id');
    echo "ROLES with outlet anomaly: " . json_encode($outletRoles) . PHP_EOL;
}

$whTrack = DB::table('erp_permission')->where('code', 'warehouse_mac_tracking_view')->first();
if ($whTrack) {
    $whRoles = DB::table('erp_role_permission')->where('permission_id', $whTrack->id)->pluck('role_id');
    echo "ROLES with warehouse tracking: " . json_encode($whRoles) . PHP_EOL;
}
