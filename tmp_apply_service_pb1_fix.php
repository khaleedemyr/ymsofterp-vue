<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$csvPath = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'orders_service_pb1_rekomendasi_fix_from_2026-06-26.csv';

if (!is_file($csvPath)) {
    fwrite(STDERR, "File CSV rekomendasi tidak ditemukan: {$csvPath}\n");
    exit(1);
}

$fp = fopen($csvPath, 'r');
if ($fp === false) {
    fwrite(STDERR, "Gagal membuka CSV: {$csvPath}\n");
    exit(1);
}

$header = fgetcsv($fp);
if ($header === false) {
    fwrite(STDERR, "CSV kosong: {$csvPath}\n");
    fclose($fp);
    exit(1);
}

$idx = array_flip($header);
$required = ['nomor_order', 'service_rekomendasi', 'pb1_rekomendasi', 'grand_total_rekomendasi'];
foreach ($required as $col) {
    if (!array_key_exists($col, $idx)) {
        fwrite(STDERR, "Kolom wajib {$col} tidak ada di CSV\n");
        fclose($fp);
        exit(1);
    }
}

$rows = [];
while (($data = fgetcsv($fp)) !== false) {
    $nomor = trim((string) ($data[$idx['nomor_order']] ?? ''));
    if ($nomor === '') {
        continue;
    }

    $rows[] = [
        'nomor' => $nomor,
        'service' => (int) round((float) ($data[$idx['service_rekomendasi']] ?? 0)),
        'pb1' => (int) round((float) ($data[$idx['pb1_rekomendasi']] ?? 0)),
        'grand_total' => (int) round((float) ($data[$idx['grand_total_rekomendasi']] ?? 0)),
    ];
}
fclose($fp);

if (empty($rows)) {
    echo "Tidak ada baris untuk di-update.\n";
    exit(0);
}

$updated = 0;
$missing = [];

DB::transaction(function () use ($rows, &$updated, &$missing): void {
    foreach ($rows as $row) {
        $exists = DB::table('orders')->where('nomor', $row['nomor'])->exists();
        if (!$exists) {
            $missing[] = $row['nomor'];
            continue;
        }

        DB::table('orders')
            ->where('nomor', $row['nomor'])
            ->update([
                'service' => $row['service'],
                'pb1' => $row['pb1'],
                'grand_total' => $row['grand_total'],
                'updated_at' => now(),
            ]);

        $updated++;
    }
});

echo "Total row di CSV: " . count($rows) . PHP_EOL;
echo "Berhasil di-update: {$updated}" . PHP_EOL;

if (!empty($missing)) {
    echo "Nomor order tidak ditemukan: " . implode(', ', $missing) . PHP_EOL;
}
