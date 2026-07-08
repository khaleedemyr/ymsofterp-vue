<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AttendanceReportController;
use Illuminate\Http\Request;

$request = Request::create('/attendance-report/detail', 'GET', [
    'user_id' => 1944,
    'tanggal' => '2026-06-29',
]);

$response = app(AttendanceReportController::class)->detail($request);
echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT)."\n";
