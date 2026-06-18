<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sql = file_get_contents(__DIR__ . '/../database/sql/alter_just_academy_schedule_trainers_external.sql');
$statements = array_filter(array_map('trim', preg_split('/;\s*\R/', $sql)));

foreach ($statements as $statement) {
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    try {
        Illuminate\Support\Facades\DB::statement($statement);
        echo "OK\n";
    } catch (Throwable $e) {
        echo 'SKIP: ' . $e->getMessage() . "\n";
    }
}
