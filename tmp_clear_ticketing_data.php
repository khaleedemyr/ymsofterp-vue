<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tablesToDelete = [
    'ticket_attachments',
    'ticket_comments',
    'ticket_assignments',
    'ticket_history',
    'tickets',
];

$existingTables = array_values(array_filter($tablesToDelete, static fn (string $table): bool => Schema::hasTable($table)));

if ($existingTables === []) {
    echo "Tidak ada tabel ticketing yang ditemukan.\n";
    exit(0);
}

echo "Mulai hapus data ticketing...\n";

try {
    DB::transaction(function () use ($existingTables): void {
        if (Schema::hasTable('purchase_requisitions') && Schema::hasColumn('purchase_requisitions', 'ticket_id')) {
            $updated = DB::table('purchase_requisitions')
                ->whereNotNull('ticket_id')
                ->update(['ticket_id' => null]);

            echo "purchase_requisitions ticket_id di-null-kan: {$updated}\n";
        }

        foreach ($existingTables as $table) {
            $count = DB::table($table)->count();
            DB::table($table)->delete();
            echo "{$table}: {$count} baris dihapus\n";
        }
    });

    echo "Selesai. Semua data transaksi ticketing sudah dihapus.\n";
} catch (Throwable $e) {
    echo "Gagal menghapus data ticketing: " . $e->getMessage() . "\n";
    exit(1);
}
