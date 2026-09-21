<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = app(App\Http\Controllers\WarehouseCostHealthDashboardController::class);
$list = $c->transactions(Illuminate\Http\Request::create('/x','GET',[
    'type'=>'delivery_order','period'=>'2026-09','date_from'=>'2026-09-21','date_to'=>'2026-09-21','per_page'=>20
]))->getData(true);
echo "LIST sample:\n".json_encode(array_slice($list['transactions']??[],0,3), JSON_PRETTY_PRINT).PHP_EOL;
$d = $c->transactionDetail(Illuminate\Http\Request::create('/x','GET',['type'=>'delivery_order','id'=>66455]))->getData(true);
echo "DETAIL DO2609210132:\n".json_encode($d, JSON_PRETTY_PRINT).PHP_EOL;
$d2 = $c->transactionDetail(Illuminate\Http\Request::create('/x','GET',['type'=>'delivery_order','id'=>58565]))->getData(true);
echo "DETAIL with OGR:\n".json_encode([
    'header'=>$d2['header']??null,
    'items'=>array_slice($d2['items']??[],0,3),
    'grand_total'=>$d2['grand_total']??null,
], JSON_PRETTY_PRINT).PHP_EOL;
