<?php
/**
 * Queue Worker Script untuk Member Notification (cPanel)
 * 
 * Script ini bisa dijalankan via cron job atau browser
 * Untuk cron job: php queue-worker-php.php
 * Untuk browser: akses via URL (tidak recommended untuk production)
 */

// Set execution time limit (5 menit)
set_time_limit(300);

// Set memory limit
ini_set('memory_limit', '256M');

// Path ke folder aplikasi (auto-detect)
$appPath = __DIR__;

// Change directory ke folder aplikasi
chdir($appPath);

// Load Laravel bootstrap
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Jalankan queue worker
// --stop-when-empty: Stop jika tidak ada job (untuk cron job)
// --max-time=300: Auto-restart setelah 5 menit
// --max-jobs=100: Auto-restart setelah 100 jobs
$exitCode = $kernel->call('queue:work', [
    '--queue' => 'notifications',
    '--tries' => 3,
    '--timeout' => 300,
    '--sleep' => 3,
    '--max-jobs' => 100,
    '--max-time' => 300,
    '--stop-when-empty' => true,
]);

// Return exit code
exit($exitCode);

