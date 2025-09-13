<?php
/**
 * cPanel Cron Job Setup Helper
 * 
 * File ini membantu setup cron job untuk employee movement execution
 * Jalankan file ini di browser untuk mendapatkan command yang tepat
 */

// Cek apakah ini Laravel project
if (!file_exists('artisan')) {
    die('❌ Error: File artisan tidak ditemukan. Pastikan ini adalah Laravel project.');
}

// Cek apakah command employee-movements:execute ada
$output = shell_exec('php artisan list 2>&1');
if (strpos($output, 'employee-movements:execute') === false) {
    die('❌ Error: Command employee-movements:execute tidak ditemukan.');
}

// Get current directory
$currentPath = getcwd();
$phpPath = PHP_BINARY;

// Generate commands
$command1 = "* * * * * {$phpPath} {$currentPath}/artisan schedule:run >> {$currentPath}/storage/logs/cron.log 2>&1";
$command2 = "0 8 * * * {$phpPath} {$currentPath}/artisan employee-movements:execute >> {$currentPath}/storage/logs/employee-movements-execution.log 2>&1";

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cPanel Cron Job Setup - Employee Movement Execution</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #2c3e50; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .command-box { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; word-break: break-all; }
        .copy-btn { background: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 3px; cursor: pointer; margin-left: 10px; }
        .copy-btn:hover { background: #2980b9; }
        .step { background: #ecf0f1; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #3498db; }
        .warning { background: #f39c12; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .success { background: #27ae60; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #3498db; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 cPanel Cron Job Setup</h1>
            <p>Employee Movement Execution - Automatic Setup</p>
        </div>

        <div class="success">
            ✅ <strong>Laravel Project Detected!</strong><br>
            Path: <?php echo $currentPath; ?><br>
            PHP Path: <?php echo $phpPath; ?>
        </div>

        <div class="info">
            <h3>📋 Langkah-langkah Setup di cPanel:</h3>
            <ol>
                <li>Login ke cPanel hosting Anda</li>
                <li>Buka menu <strong>"Cron Jobs"</strong></li>
                <li>Pilih <strong>"Standard"</strong></li>
                <li>Copy salah satu command di bawah ini</li>
                <li>Paste ke field "Command"</li>
                <li>Klik <strong>"Add New Cron Job"</strong></li>
            </ol>
        </div>

        <div class="step">
            <h3>🎯 Option 1: Laravel Scheduler (Recommended)</h3>
            <p>Menggunakan Laravel scheduler yang sudah dikonfigurasi di <code>app/Console/Kernel.php</code></p>
            <div class="command-box" id="command1">
                <?php echo $command1; ?>
            </div>
            <button class="copy-btn" onclick="copyToClipboard('command1')">📋 Copy</button>
            
            <h4>Timing di cPanel:</h4>
            <ul>
                <li>Minute: <code>*</code></li>
                <li>Hour: <code>*</code></li>
                <li>Day: <code>*</code></li>
                <li>Month: <code>*</code></li>
                <li>Weekday: <code>*</code></li>
            </ul>
        </div>

        <div class="step">
            <h3>🎯 Option 2: Direct Command</h3>
            <p>Langsung menjalankan command employee-movements:execute setiap hari jam 08:00</p>
            <div class="command-box" id="command2">
                <?php echo $command2; ?>
            </div>
            <button class="copy-btn" onclick="copyToClipboard('command2')">📋 Copy</button>
            
            <h4>Timing di cPanel:</h4>
            <ul>
                <li>Minute: <code>0</code></li>
                <li>Hour: <code>8</code></li>
                <li>Day: <code>*</code></li>
                <li>Month: <code>*</code></li>
                <li>Weekday: <code>*</code></li>
            </ul>
        </div>

        <div class="warning">
            <h3>⚠️ Penting!</h3>
            <ul>
                <li>Pastikan path PHP dan project Laravel sudah benar</li>
                <li>Folder <code>storage/logs/</code> harus writable</li>
                <li>Database connection harus berfungsi</li>
                <li>Hapus file ini setelah setup selesai untuk keamanan</li>
            </ul>
        </div>

        <div class="step">
            <h3>🔍 Test Cron Job</h3>
            <p>Setelah setup, test apakah cron job berjalan:</p>
            <ol>
                <li>Tunggu 1-2 menit</li>
                <li>Cek file log: <code>storage/logs/cron.log</code></li>
                <li>Atau cek: <code>storage/logs/employee-movements-execution.log</code></li>
                <li>Jika ada log baru, berarti cron job berjalan</li>
            </ol>
        </div>

        <div class="step">
            <h3>📊 Monitoring</h3>
            <p>File log yang akan ter-generate:</p>
            <ul>
                <li><code>storage/logs/cron.log</code> - Log umum cron job</li>
                <li><code>storage/logs/employee-movements-execution.log</code> - Log khusus employee movement</li>
                <li><code>storage/logs/laravel.log</code> - Log Laravel umum</li>
            </ul>
        </div>

        <div class="info">
            <h3>🆘 Troubleshooting</h3>
            <p>Jika ada masalah:</p>
            <ul>
                <li>Cek log files di <code>storage/logs/</code></li>
                <li>Test manual: <code>php artisan employee-movements:execute</code></li>
                <li>Cek database connection di <code>.env</code></li>
                <li>Pastikan file permissions benar</li>
            </ul>
        </div>
    </div>

    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            navigator.clipboard.writeText(text).then(function() {
                alert('✅ Command copied to clipboard!');
            }, function(err) {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('✅ Command copied to clipboard!');
            });
        }
    </script>
</body>
</html>
