<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Process holiday attendance automatically every day at 6:00 AM
        $schedule->command('attendance:process-holiday')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/holiday-attendance.log'));

        // Process holiday attendance for yesterday at 11:00 PM (in case of late scans)
        $schedule->command('attendance:process-holiday')
            ->dailyAt('23:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/holiday-attendance.log'));

        // Weekly cleanup of old logs (optional)
        $schedule->command('attendance:cleanup-logs')
            ->weekly()
            ->sundays()
            ->at('02:00');

        // Detect extra off for unscheduled work every day at 7:00 AM
        $schedule->command('extra-off:detect')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/extra-off-detection.log'));

        // Detect extra off for unscheduled work at 11:30 PM (in case of late scans)
        $schedule->command('extra-off:detect')
            ->dailyAt('23:30')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/extra-off-detection.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
