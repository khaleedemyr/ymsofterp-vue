<?php
/**
 * Script untuk test email di server
 * 
 * Cara pakai:
 * 1. Upload ke server
 * 2. Akses via browser atau command line
 * 3. Cek hasil di log
 */

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

echo "<h2>Email Test Script</h2>";

// Test 1: Cek konfigurasi email
echo "<h3>1. Konfigurasi Email</h3>";
echo "MAIL_MAILER: " . config('mail.default') . "<br>";
echo "MAIL_HOST: " . config('mail.mailers.smtp.host') . "<br>";
echo "MAIL_PORT: " . config('mail.mailers.smtp.port') . "<br>";
echo "MAIL_USERNAME: " . config('mail.mailers.smtp.username') . "<br>";
echo "MAIL_ENCRYPTION: " . config('mail.mailers.smtp.encryption') . "<br>";
echo "APP_ENV: " . config('app.env') . "<br>";

// Test 2: Cek koneksi SMTP
echo "<h3>2. Test Koneksi SMTP</h3>";
$host = config('mail.mailers.smtp.host');
$port = config('mail.mailers.smtp.port');

$connection = @fsockopen($host, $port, $errno, $errstr, 10);
if ($connection) {
    echo "✅ Koneksi ke {$host}:{$port} berhasil<br>";
    fclose($connection);
} else {
    echo "❌ Koneksi ke {$host}:{$port} gagal: {$errstr} ({$errno})<br>";
}

// Test 3: Test kirim email sederhana
echo "<h3>3. Test Kirim Email</h3>";
try {
    $testEmail = 'test@example.com'; // Ganti dengan email test Anda
    
    Mail::raw('Test email dari server - ' . date('Y-m-d H:i:s'), function($message) use ($testEmail) {
        $message->to($testEmail)
               ->subject('Test Email Server - ' . date('Y-m-d H:i:s'));
    });
    
    echo "✅ Email berhasil dikirim ke {$testEmail}<br>";
    Log::info('Test email berhasil dikirim', ['to' => $testEmail]);
    
} catch (Exception $e) {
    echo "❌ Gagal kirim email: " . $e->getMessage() . "<br>";
    Log::error('Test email gagal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

// Test 4: Cek log email
echo "<h3>4. Cek Log Email</h3>";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $emailLogs = preg_grep('/email|mail/i', explode("\n", $logContent));
    $recentLogs = array_slice($emailLogs, -10); // Ambil 10 log terakhir
    
    echo "Log email terakhir:<br>";
    echo "<pre>";
    foreach ($recentLogs as $log) {
        echo htmlspecialchars($log) . "\n";
    }
    echo "</pre>";
} else {
    echo "❌ File log tidak ditemukan<br>";
}

// Test 5: Cek PHP extensions
echo "<h3>5. Cek PHP Extensions</h3>";
$requiredExtensions = ['openssl', 'mbstring', 'pdo', 'pdo_mysql'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ {$ext} extension aktif<br>";
    } else {
        echo "❌ {$ext} extension tidak aktif<br>";
    }
}

// Test 6: Cek environment
echo "<h3>6. Environment Info</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";

echo "<hr>";
echo "<p><strong>Jika semua test berhasil, email seharusnya bisa berfungsi di server.</strong></p>";
echo "<p><strong>Jika ada yang gagal, cek troubleshooting guide di EMAIL_TROUBLESHOOTING.md</strong></p>";
?> 