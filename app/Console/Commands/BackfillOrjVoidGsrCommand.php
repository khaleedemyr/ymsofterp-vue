<?php

namespace App\Console\Commands;

use App\Http\Controllers\OutletRejectionController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillOrjVoidGsrCommand extends Command
{
    protected $signature = 'orj:void-gsr
                            {--rejection= : Batasi ke nomor/id outlet rejection tertentu}
                            {--serial= : Batasi ke serial_number tertentu}
                            {--dry-run : Tampilkan rencana tanpa menulis ke database}';

    protected $description = 'Void sisa baris GSR untuk serial yang sudah ORJ completed (agar hilang dari Rekap FJ)';

    public function handle(): int
    {
        if (! Schema::hasTable('outlet_rejection_serial_items')
            || ! Schema::hasTable('outlet_serial_receive_items')) {
            $this->error('Tabel ORJ serial / GSR tidak ditemukan.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rejectionFilter = trim((string) $this->option('rejection'));
        $serialFilter = trim((string) $this->option('serial'));

        $query = DB::table('outlet_rejection_serial_items as osi')
            ->join('outlet_rejections as r', 'r.id', '=', 'osi.outlet_rejection_id')
            ->where('r.status', 'completed')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('outlet_serial_receive_items as si')
                    ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
                    ->whereNull('h.deleted_at')
                    ->where(function ($inner) {
                        $inner->whereColumn('si.serial_id', 'osi.serial_id')
                            ->orWhereColumn('si.serial_number', 'osi.serial_number');
                    });
            })
            ->select(
                'osi.*',
                'r.id as rejection_id',
                'r.number as rejection_number',
                'r.warehouse_id'
            )
            ->orderBy('r.id')
            ->orderBy('osi.id');

        if ($rejectionFilter !== '') {
            if (ctype_digit($rejectionFilter)) {
                $query->where('r.id', (int) $rejectionFilter);
            } else {
                $query->where('r.number', $rejectionFilter);
            }
        }
        if ($serialFilter !== '') {
            $query->where('osi.serial_number', $serialFilter);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->info('Tidak ada serial ORJ completed yang masih punya baris GSR aktif.');

            return self::SUCCESS;
        }

        $this->info('Ditemukan '.$rows->count().' baris untuk di-void GSR.');
        if ($dryRun) {
            foreach ($rows as $row) {
                $this->line("- {$row->rejection_number} | {$row->serial_number} (serial_id={$row->serial_id})");
            }
            $this->warn('Dry-run: tidak ada perubahan.');

            return self::SUCCESS;
        }

        /** @var OutletRejectionController $controller */
        $controller = app(OutletRejectionController::class);
        $ok = 0;
        $fail = 0;

        foreach ($rows as $row) {
            try {
                DB::transaction(function () use ($controller, $row) {
                    $rejection = (object) [
                        'id' => $row->rejection_id,
                        'warehouse_id' => $row->warehouse_id,
                    ];
                    $controller->voidOutletSerialReceiveForRejectedSerial($rejection, $row);

                    if (! empty($row->serial_id)) {
                        DB::table('inventory_item_serials')
                            ->where('id', $row->serial_id)
                            ->update([
                                'is_received' => 0,
                                'received_at' => null,
                                'received_by' => null,
                                'received_outlet_gr_id' => null,
                                'updated_at' => now(),
                            ]);
                    }
                });
                $ok++;
                $this->info("OK {$row->rejection_number} / {$row->serial_number}");
            } catch (\Throwable $e) {
                $fail++;
                $this->error("FAIL {$row->rejection_number} / {$row->serial_number}: ".$e->getMessage());
            }
        }

        $this->info("Selesai. sukses={$ok}, gagal={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
