<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupHolidayAttendanceLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:cleanup-logs {--days=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old holiday attendance logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $logPath = storage_path('logs/holiday-attendance.log');
        
        if (!File::exists($logPath)) {
            $this->info('No holiday attendance log file found.');
            return 0;
        }

        $fileTime = File::lastModified($logPath);
        $cutoffTime = time() - ($days * 24 * 60 * 60);

        if ($fileTime < $cutoffTime) {
            File::delete($logPath);
            $this->info("Deleted holiday attendance log file older than {$days} days.");
        } else {
            $this->info('Holiday attendance log file is still recent, keeping it.');
        }

        return 0;
    }
}
