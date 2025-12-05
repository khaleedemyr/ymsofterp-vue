<?php

namespace App\Console\Commands;

use App\Services\LeaveManagementService;
use Illuminate\Console\Command;

class ProcessMonthlyLeave extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:monthly-credit 
                            {--year= : Tahun untuk kredit cuti (default: tahun saat ini)}
                            {--month= : Bulan untuk kredit cuti (default: bulan saat ini)}
                            {--force : Paksa proses meskipun sudah pernah dijalankan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memberikan cuti bulanan ke semua karyawan aktif';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->option('year') ?: date('Y');
        $month = $this->option('month') ?: date('n');
        $force = $this->option('force');

        $this->info("Memproses kredit cuti bulanan untuk {$year}-{$month}");

        $leaveService = new LeaveManagementService();
        $result = $leaveService->giveMonthlyLeave($year, $month);

        if ($result['success']) {
            $this->info("✅ Berhasil memproses {$result['processed_count']} karyawan");
            
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
