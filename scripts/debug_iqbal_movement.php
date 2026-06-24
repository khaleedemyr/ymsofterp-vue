<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$nik = '251411';
$user = DB::table('users')->where('nik', $nik)->first();
if (! $user) {
    echo "User not found\n";
    exit(1);
}

echo "=== User ===\n";
echo "id={$user->id} nama={$user->nama_lengkap} id_outlet={$user->id_outlet}\n\n";

echo "=== employee_movements (all) ===\n";
$movements = DB::table('employee_movements')
    ->where('employee_id', $user->id)
    ->get();

if ($movements->isEmpty()) {
    echo "(tidak ada record)\n";
} else {
    foreach ($movements as $m) {
        echo json_encode($m, JSON_UNESCAPED_UNICODE)."\n";
    }
}

echo "\n=== employee_movements by name like Iqbal ===\n";
$byName = DB::table('employee_movements as em')
    ->join('users as u', 'em.employee_id', '=', 'u.id')
    ->where('u.nama_lengkap', 'like', '%Iqbal Hamdani%')
    ->select('em.*', 'u.nik', 'u.nama_lengkap', 'u.id_outlet')
    ->get();
foreach ($byName as $m) {
    echo json_encode($m, JSON_UNESCAPED_UNICODE)."\n";
}

// Table structure hint
$cols = DB::select('SHOW COLUMNS FROM employee_movements');
echo "\n=== columns ===\n";
foreach ($cols as $c) {
    echo "{$c->Field} ({$c->Type})\n";
}
