<?php

namespace App\Console\Commands;

use App\Services\LeaveManagementService;
use Illuminate\Console\Command;

class BurnPreviousYearLeave extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:burn-previous-year 
                            {--year= : Tahun saat ini (default: tahun saat ini)}
                            {--force : Paksa proses meskipun sudah pernah dijalankan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Burning sisa cuti tahun sebelumnya di bulan Maret';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentYear = $this->option('year') ?: date('Y');
        $force = $this->option('force');

        $this->info("Memproses burning cuti tahun " . ($currentYear - 1) . " untuk tahun {$currentYear}");

        $leaveService = new LeaveManagementService();
        $result = $leaveService->burnPreviousYearLeave($currentYear);

        if ($result['success']) {
            $this->info("✅ Berhasil memproses {$result['processed_count']} karyawan");
            $this->info("🔥 Total cuti yang diburning: {$result['total_burned']} hari");
            
            if (!empty($result['errors'])) {
                $this->warn("⚠️  Terjadi {$result['error_count']} error:");
                foreach ($result['errors'] as $error) {
                    $this->error("   - {$error}");
                }
            }
        } else {
            $this->error("❌ Gagal memproses: {$result['error']}");
            return 1;
        }

        return 0;
    }
}
