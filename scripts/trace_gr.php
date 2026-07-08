<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$gr = DB::table('asset_good_receives')->where('id',2)->first();
echo json_encode($gr)."\n";
$lb = DB::table('lost_breakage_replacements')->where('asset_good_receive_id',2)->get();
echo "LB replacements: ".$lb->count()."\n";
