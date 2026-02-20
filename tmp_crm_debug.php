<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

$membersWithBirthdate = DB::table('member_apps_members')
    ->whereNotNull('tanggal_lahir')
    ->where('is_active', 1)
    ->count();

$ordersMemberYtd = DB::connection('db_justus')->table('orders')
    ->where('status', 'paid')
    ->whereNotNull('member_id')
    ->where('member_id', '!=', '')
    ->whereBetween('created_at', [now()->startOfYear(), now()])
    ->count();

$joinedCount = DB::select("SELECT COUNT(*) AS c
FROM member_apps_members m
INNER JOIN " . DB::connection('db_justus')->getDatabaseName() . ".orders o
  ON o.member_id COLLATE utf8mb4_unicode_ci = m.member_id COLLATE utf8mb4_unicode_ci
WHERE o.status = ?
  AND o.member_id <> ''
  AND o.member_id IS NOT NULL
  AND m.tanggal_lahir IS NOT NULL
  AND m.is_active = 1", ['paid']);

$ctrl = app(App\Http\Controllers\CrmDashboardController::class);
$req = Request::create('/api/crm/chart-data', 'GET', ['type' => 'purchasingPower']);
$resp = $ctrl->getChartData($req);
$payload = json_decode($resp->getContent(), true);

$result = [
    'members_with_birthdate' => $membersWithBirthdate,
    'orders_member_ytd' => $ordersMemberYtd,
    'joined_count' => $joinedCount[0]->c ?? null,
    'purchasing_power_count' => is_array($payload) ? count($payload) : null,
    'purchasing_power_sample' => is_array($payload) ? array_slice($payload, 0, 2) : $payload,
];

echo json_encode($result, JSON_PRETTY_PRINT);
