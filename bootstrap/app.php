<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Exclude API routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Register Approval App middleware alias
        $middleware->alias([
            'approval.app.auth' => \App\Http\Middleware\ApprovalAppAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
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

        // Execute approved employee movements on their effective date at 8:00 AM
        $schedule->command('employee-movements:execute')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/employee-movements-execution.log'));

        // Cuti bulanan - jalankan setiap tanggal 1 setiap bulan
        $schedule->command('leave:monthly-credit')
            ->monthlyOn(1, '00:00')
            ->description('Memberikan cuti bulanan ke semua karyawan aktif');

        // Burning cuti tahun sebelumnya - jalankan setiap tanggal 1 Maret
        $schedule->command('leave:burn-previous-year')
            ->yearlyOn(3, 1, '00:00')
            ->description('Burning sisa cuti tahun sebelumnya');

        // Update member tiers based on rolling 12-month spending - run monthly on the 1st
        $schedule->command('members:update-tiers')
            ->monthlyOn(1, '00:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/member-tiers-update.log'))
            ->description('Update member tiers based on rolling 12-month spending');

        // Expire points that have passed their expiration date - run daily at midnight
        $schedule->command('points:expire')
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/points-expiry.log'))
            ->description('Expire points that have passed their expiration date and reduce member point balance');

        // Distribute birthday vouchers to members - run daily at 1:00 AM
        $schedule->command('vouchers:distribute-birthday')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/birthday-vouchers-distribution.log'))
            ->description('Distribute birthday vouchers to members who have their birthday today');

        // Send incomplete profile notification - run every hour
        $schedule->command('member:notify-incomplete-profile')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/incomplete-profile-notifications.log'))
            ->description('Send notification to members who registered 24 hours ago but haven\'t completed their profile');

        // Send incomplete challenge notification - run every hour
        $schedule->command('member:notify-incomplete-challenge')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/incomplete-challenge-notifications.log'))
            ->description('Send notification to members who started a challenge but haven\'t completed it within 24 hours');

        // Send inactive member notification - run daily at 10:00 AM
        $schedule->command('member:notify-inactive')
            ->dailyAt('10:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/inactive-member-notifications.log'))
            ->description('Send notification to members who haven\'t made a transaction in the last 7 days');

        // Send long inactive member notification - run daily at 11:00 AM
        $schedule->command('member:notify-long-inactive')
            ->dailyAt('11:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/long-inactive-member-notifications.log'))
            ->description('Send notification to members who haven\'t made a transaction in the last 3 months (90 days)');

        // Send expiring points notification - run daily at 9:00 AM
        $schedule->command('member:notify-expiring-points')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/expiring-points-notifications.log'))
            ->description('Send notification to members who have points or challenge rewards expiring in 14 days (2 weeks) or 7 days (1 week)');

        // Send monthly inactive member notification - run daily at 10:30 AM
        $schedule->command('member:notify-monthly-inactive')
            ->dailyAt('10:30')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/monthly-inactive-member-notifications.log'))
            ->description('Send notification to members who haven\'t made a transaction in the last 1 month (30 days)');

        // Send expiring vouchers notification - run daily at 9:30 AM
        $schedule->command('member:notify-expiring-vouchers')
            ->dailyAt('09:30')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/expiring-vouchers-notifications.log'))
            ->description('Send notification to members who have vouchers expiring in 7 days');

        // Cleanup old and excess device tokens - run daily at 2:00 AM
        $schedule->command('device-tokens:cleanup --days=30 --limit=5')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/device-tokens-cleanup.log'))
            ->description('Cleanup old and excess device tokens to prevent notification spam');
    })
    ->create();
