<?php

namespace App\Console\Commands;

use App\Services\LostBreakageStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyLostBreakageStockCommand extends Command
{
    protected $signature = 'lost-breakage:apply-stock
                            {--number= : Nomor LB tertentu (contoh LB-20260711-537D)}
                            {--dry-run : Tampilkan rencana tanpa menulis ke database}';

    protected $description = 'Terapkan pengurangan stok asset untuk Lost & Breakage yang sudah APPROVED tapi belum memotong stok';

    public function handle(LostBreakageStockService $stockService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $number = trim((string) $this->option('number'));

        $query = DB::table('lost_breakage_headers as h')
            ->where('h.status', 'APPROVED')
            ->orderBy('h.id');

        if ($number !== '') {
            $query->where('h.number', $number);
        }

        $headers = $query->get();
        if ($headers->isEmpty()) {
            $this->warn('Tidak ada header APPROVED yang cocok.');

            return self::SUCCESS;
        }

        $applied = 0;
        $skipped = 0;

        foreach ($headers as $header) {
            if ($stockService->hasStockBeenApplied((int) $header->id)) {
                $this->line("Skip {$header->number} (stok sudah dipotong).");
                $skipped++;
                continue;
            }

            $details = DB::table('lost_breakage_details')
                ->where('header_id', $header->id)
                ->get()
                ->all();

            if ($dryRun) {
                $this->info("[DRY RUN] Akan potong stok: {$header->number} ({$header->id}), " . count($details) . ' detail.');
                $applied++;
                continue;
            }

            DB::beginTransaction();
            try {
                $stockService->applyStockOut($header, $details);
                DB::commit();
                $this->info("Stok dipotong: {$header->number}");
                $applied++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Gagal {$header->number}: {$e->getMessage()}");
            }
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Selesai. Diproses: {$applied}, dilewati: {$skipped}.");

        return self::SUCCESS;
    }
}
