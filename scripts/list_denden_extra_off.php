<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$userId = 1436;
$name = DB::table('users')->where('id', $userId)->value('nama_lengkap');

$earned = DB::table('extra_off_transactions')
    ->where('user_id', $userId)
    ->where('transaction_type', 'earned')
    ->orderBy('source_date')
    ->orderBy('id')
    ->get();

$used = DB::table('extra_off_transactions')
    ->where('user_id', $userId)
    ->where('transaction_type', 'used')
    ->orderBy('source_date')
    ->get();

$balance = DB::table('extra_off_balance')->where('user_id', $userId)->value('balance');

echo "=== Extra Off earned: {$name} (id={$userId}) ===\n";
echo str_repeat('-', 90) . "\n";
printf("%-12s | %-6s | %-18s | %s\n", 'Tanggal', 'Hari', 'Sumber', 'Keterangan');
echo str_repeat('-', 90) . "\n";

foreach ($earned as $t) {
    $date = $t->source_date ? substr((string) $t->source_date, 0, 10) : '-';
    $amt = (float) $t->amount;
    $src = $t->source_type ?? '-';
    $desc = preg_replace('/\s+/', ' ', (string) ($t->description ?? ''));
    if (preg_match('/jam ([0-9:]+) - ([0-9:]+), ([0-9.]+) jam/', $desc, $m)) {
        $desc = "Masuk {$m[1]} – pulang {$m[2]} ({$m[3]} jam)";
    }
    printf("%-12s | %+4.0f   | %-18s | %s\n", $date, $amt, $src, $desc);
}

echo str_repeat('-', 90) . "\n";
echo 'Total earned: ' . (float) $earned->sum('amount') . " hari\n";
echo 'Total used:   ' . (float) $used->sum(fn ($t) => abs((float) $t->amount)) . " hari\n";
echo "Saldo sekarang: {$balance} hari\n";

if ($used->isNotEmpty()) {
    echo "\n=== Pemakaian extra off ===\n";
    foreach ($used as $t) {
        echo substr((string) ($t->source_date ?? $t->used_date ?? '-'), 0, 10)
            . ' | ' . abs((float) $t->amount) . " hari | {$t->description}\n";
    }
} else {
    echo "\nBelum ada pemakaian extra off (used).\n";
}
