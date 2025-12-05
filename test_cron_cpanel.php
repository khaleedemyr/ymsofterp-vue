<?php
/**
 * Test Cron Job untuk cPanel
 * 
 * File ini untuk test apakah cron job berjalan dengan benar
 * Hapus file ini setelah setup selesai untuk keamanan
 */

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Log file
$logFile = 'cron_test.log';
$message = date('Y-m-d H:i:s') . " - Cron job test executed successfully!\n";

// Write to log
file_put_contents($logFile, $message, FILE_APPEND);

// Also write to Laravel log if possible
if (file_exists('storage/logs/laravel.log')) {
    $laravelMessage = date('Y-m-d H:i:s') . " - Cron test from cPanel\n";
    file_put_contents('storage/logs/laravel.log', $laravelMessage, FILE_APPEND);
}

// Output for web browser
if (isset($_GET['web'])) {
    echo "<h2>✅ Cron Job Test</h2>";
    echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";
    echo "<p>Log written to: " . $logFile . "</p>";
    echo "<p><a href='" . $logFile . "'>View Log File</a></p>";
    echo "<p><strong>Hapus file ini setelah setup selesai!</strong></p>";
} else {
    // Output for cron job
    echo "Cron test completed at " . date('Y-m-d H:i:s');
}
?>
