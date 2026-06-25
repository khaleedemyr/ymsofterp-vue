<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== Users Agus suparman ===\n";
$users = DB::table('users')->where('nama_lengkap', 'like', '%agus%suparman%')->orWhere('nik', '240212')->get(['id','nik','nama_lengkap','id_outlet']);
foreach ($users as $u) {
    echo json_encode($u)."\n";
    $mv = DB::table('employee_movements')->where('employee_id', $u->id)->get();
    echo "  movements: ".$mv->count()."\n";
    foreach ($mv as $m) {
        echo "  ".json_encode(['id'=>$m->id,'type'=>$m->employment_type,'eff'=>$m->employment_effective_date,'from'=>$m->unit_property_from,'to'=>$m->unit_property_to,'status'=>$m->status])."\n";
    }
}

echo "\n=== Movement 2026-05-08 SMB ===\n";
$mv2 = DB::table('employee_movements')
    ->where('employment_effective_date', '2026-05-08')
    ->orWhere('employee_name', 'like', '%Agus%')
    ->get();
foreach ($mv2 as $m) {
    if (stripos($m->employee_name ?? '', 'agus') !== false || $m->employment_effective_date === '2026-05-08') {
        echo json_encode(['id'=>$m->id,'emp'=>$m->employee_id,'name'=>$m->employee_name,'eff'=>$m->employment_effective_date,'from'=>$m->unit_property_from,'to'=>$m->unit_property_to,'status'=>$m->status])."\n";
    }
}
