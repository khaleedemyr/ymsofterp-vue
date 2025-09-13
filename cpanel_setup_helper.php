<?php
/**
 * cPanel Setup Helper - Employee Movement Execution
 * 
 * File ini membantu generate command cron job yang tepat untuk cPanel
 * Buka di browser untuk mendapatkan command yang siap copy-paste
 */

// Cek apakah ini Laravel project
if (!file_exists('artisan')) {
    die('❌ Error: File artisan tidak ditemukan. Pastikan ini adalah Laravel project.');
}

// Get current directory
$currentPath = getcwd();
$phpPath = PHP_BINARY;

// Generate commands
$command1 = "cd {$currentPath} && php artisan schedule:run >> storage/logs/cron.log 2>&1";
$command2 = "cd {$currentPath} && php artisan employee-movements:execute >> storage/logs/employee-movements-execution.log 2>&1";

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cPanel Setup Helper - Employee Movement Execution</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .command-box { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; word-break: break-all; }
        .copy-btn { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 3px; cursor: pointer; margin-left: 10px; font-size: 14px; }
        .copy-btn:hover { background: #2980b9; }
        .step { background: #ecf0f1; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #3498db; }
        .warning { background: #f39c12; color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #27ae60; color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #3498db; color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .timing-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .timing-table th, .timing-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .timing-table th { background-color: #f2f2f2; }
        .highlight { background: #fff3cd; padding: 10px; border-radius: 5px; border-left: 4px solid #ffc107; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 cPanel Setup Helper</h1>
            <h2>Employee Movement Execution - Automatic Setup</h2>
        </div>

        <div class="success">
            ✅ <strong>Laravel Project Detected!</strong><br>
            <strong>Path:</strong> <?php echo $currentPath; ?><br>
            <strong>PHP Path:</strong> <?php echo $phpPath; ?>
        </div>

        <div class="info">
            <h3>📋 Langkah-langkah Setup di cPanel:</h3>
            <ol>
                <li>Login ke cPanel hosting Anda</li>
                <li>Buka menu <strong>"Cron Jobs"</strong> di bagian <strong>"Advanced"</strong></li>
                <li>Klik <strong>"Add New Cron Job"</strong></li>
                <li>Pilih <strong>"Standard"</strong></li>
                <li>Copy salah satu command di bawah ini</li>
                <li>Paste ke field <strong>"Command"</strong></li>
                <li>Set timing sesuai tabel di bawah</li>
                <li>Klik <strong>"Add Cron Job"</strong></li>
            </ol>
        </div>

        <div class="step">
            <h3>🎯 Option 1: Laravel Scheduler (Recommended)</h3>
            <p>Menggunakan Laravel scheduler yang sudah dikonfigurasi di <code>app/Console/Kernel.php</code></p>
            <div class="command-box" id="command1">
                <?php echo $command1; ?>
            </div>
            <button class="copy-btn" onclick="copyToClipboard('command1')">📋 Copy Command</button>
            
            <h4>Timing di cPanel:</h4>
            <table class="timing-table">
                <tr><th>Field</th><th>Value</th><th>Deskripsi</th></tr>
                <tr><td>Minute</td><td><code>*</code></td><td>Setiap menit</td></tr>
                <tr><td>Hour</td><td><code>*</code></td><td>Setiap jam</td></tr>
                <tr><td>Day</td><td><code>*</code></td><td>Setiap hari</td></tr>
                <tr><td>Month</td><td><code>*</code></td><td>Setiap bulan</td></tr>
                <tr><td>Weekday</td><td><code>*</code></td><td>Setiap hari dalam seminggu</td></tr>
            </table>
            
            <div class="highlight">
                <strong>Keuntungan:</strong> Laravel scheduler akan handle timing dan menjalankan employee-movements:execute setiap hari jam 08:00
            </div>
        </div>

        <div class="step">
            <h3>🎯 Option 2: Direct Command</h3>
            <p>Langsung menjalankan command employee-movements:execute setiap hari jam 08:00</p>
            <div class="command-box" id="command2">
                <?php echo $command2; ?>
            </div>
            <button class="copy-btn" onclick="copyToClipboard('command2')">📋 Copy Command</button>
            
            <h4>Timing di cPanel:</h4>
            <table class="timing-table">
                <tr><th>Field</th><th>Value</th><th>Deskripsi</th></tr>
                <tr><td>Minute</td><td><code>0</code></td><td>Menit ke-0</td></tr>
                <tr><td>Hour</td><td><code>8</code></td><td>Jam 08:00</td></tr>
                <tr><td>Day</td><td><code>*</code></td><td>Setiap hari</td></tr>
                <tr><td>Month</td><td><code>*</code></td><td>Setiap bulan</td></tr>
                <tr><td>Weekday</td><td><code>*</code></td><td>Setiap hari dalam seminggu</td></tr>
            </table>
            
            <div class="highlight">
                <strong>Keuntungan:</strong> Lebih direct dan mudah dipahami
            </div>
        </div>

        <div class="warning">
            <h3>⚠️ Penting!</h3>
            <ul>
                <li><strong>Ganti path:</strong> Pastikan path project Laravel sudah benar</li>
                <li><strong>Permission:</strong> Folder <code>storage/logs/</code> harus writable</li>
                <li><strong>Database:</strong> Database connection harus berfungsi</li>
                <li><strong>Security:</strong> Hapus file ini setelah setup selesai</li>
            </ul>
        </div>

        <div class="step">
            <h3>🔍 Test Cron Job</h3>
            <p>Setelah setup, test apakah cron job berjalan:</p>
            <ol>
                <li>Tunggu 1-2 menit (untuk Option 1) atau sampai jam 08:00 (untuk Option 2)</li>
                <li>Cek file log: <code>storage/logs/employee-movements-execution.log</code></li>
                <li>Atau cek: <code>storage/logs/cron.log</code></li>
                <li>Jika ada log baru, berarti cron job berjalan</li>
            </ol>
        </div>

        <div class="step">
            <h3>📊 Monitoring</h3>
            <p>File log yang akan ter-generate:</p>
            <ul>
                <li><code>storage/logs/employee-movements-execution.log</code> - Log khusus employee movement</li>
                <li><code>storage/logs/cron.log</code> - Log umum cron job (Option 1)</li>
                <li><code>storage/logs/laravel.log</code> - Log Laravel umum</li>
            </ul>
        </div>

        <div class="step">
            <h3>🧪 Test Manual</h3>
            <p>Test command manual di terminal cPanel:</p>
            <div class="command-box">
                cd <?php echo $currentPath; ?> && php artisan employee-movements:execute
            </div>
            <button class="copy-btn" onclick="copyToClipboard('test-command')">📋 Copy Test Command</button>
        </div>

        <div class="info">
            <h3>🆘 Troubleshooting</h3>
            <p>Jika ada masalah:</p>
            <ul>
                <li>Cek log files di <code>storage/logs/</code></li>
                <li>Test manual command di atas</li>
                <li>Cek database connection di <code>.env</code></li>
                <li>Pastikan file permissions benar</li>
                <li>Hubungi support hosting jika perlu</li>
            </ul>
        </div>

        <div class="warning">
            <h3>🔒 Security Notice</h3>
            <p><strong>Hapus file ini setelah setup selesai!</strong></p>
            <p>File ini mengandung informasi path project yang bisa diakses publik.</p>
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
