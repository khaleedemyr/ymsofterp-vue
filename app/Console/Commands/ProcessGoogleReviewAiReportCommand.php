<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGoogleReviewAiReportJob;
use App\Services\AIAnalyticsService;
use App\Services\ApifyGoogleReviewsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessGoogleReviewAiReportCommand extends Command
{
    protected $signature = 'google-review:process-ai-report
                            {id : ID laporan di tabel google_review_ai_reports}
                            {--force : Proses ulang meski status sudah completed}';

    protected $description = 'Jalankan klasifikasi AI untuk satu laporan Google Review (tanpa queue worker — cocok jika Redis/worker tidak jalan)';

    public function handle(AIAnalyticsService $ai, ApifyGoogleReviewsService $apify): int
    {
        $id = (int) $this->argument('id');
        if ($id < 1) {
            $this->error('ID tidak valid.');

            return self::FAILURE;
        }

        $row = DB::table('google_review_ai_reports')->where('id', $id)->first();
        if (! $row) {
            $this->error("Laporan #{$id} tidak ditemukan.");

            return self::FAILURE;
        }

        if ($row->status === 'completed' && ! $this->option('force')) {
            $this->warn("Laporan #{$id} sudah selesai. Gunakan --force untuk memproses ulang.");

            return self::SUCCESS;
        }

        if ($row->status === 'processing' && ! $this->option('force')) {
            $this->error('Status masih "processing" (worker mungkin macet). Jalankan lagi dengan --force untuk memaksa dari CLI.');

            return self::FAILURE;
        }

        $this->info("Memproses laporan #{$id} ({$row->source})…");
        $this->line('(Ini sama dengan job antrian; bisa beberapa menit untuk ratusan review.)');

        try {
            $job = new ProcessGoogleReviewAiReportJob($id);
            $job->handle($ai, $apify);
        } catch (\Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $fresh = DB::table('google_review_ai_reports')->where('id', $id)->first();
        if ($fresh && $fresh->status === 'completed') {
            $this->info("Selesai. Review terklasifikasi: {$fresh->review_count}");

            return self::SUCCESS;
        }
        if ($fresh && $fresh->status === 'failed') {
            $this->error('Status failed: '.($fresh->error_message ?? 'tanpa pesan'));

            return self::FAILURE;
        }

        $this->warn('Selesai dengan status: '.($fresh->status ?? '?'));

        return self::SUCCESS;
    }
}
