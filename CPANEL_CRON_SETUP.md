# Employee Movement Execution - cPanel Cron Job Setup

## Overview
Panduan lengkap untuk setup automatic execution employee movements di cPanel hosting.

## Step-by-Step Setup di cPanel

### 1. Login ke cPanel
1. Buka cPanel hosting Anda
2. Login dengan username dan password
3. Cari menu **"Cron Jobs"** atau **"Advanced" → "Cron Jobs"**

### 2. Setup Cron Job

#### Option A: Simple Cron Job (Recommended)
```
* * * * * /usr/local/bin/php /home/username/public_html/artisan schedule:run
```

**Penjelasan:**
- `* * * * *` = Setiap menit (Laravel scheduler akan handle timing)
- `/usr/local/bin/php` = Path PHP di server
- `/home/username/public_html/` = Path ke project Laravel
- `artisan schedule:run` = Command Laravel scheduler

#### Option B: Direct Command (Alternative)
```
0 8 * * * /usr/local/bin/php /home/username/public_html/artisan employee-movements:execute
```

**Penjelasan:**
- `0 8 * * *` = Setiap hari jam 08:00
- Langsung menjalankan command employee-movements:execute

### 3. Cara Setup di cPanel Interface

#### Langkah-langkah:
1. **Buka Cron Jobs** di cPanel
2. **Pilih "Standard"** (bukan Advanced)
3. **Set timing:**
   - Minute: `*` (atau `0` untuk Option B)
   - Hour: `*` (atau `8` untuk Option B)
   - Day: `*`
   - Month: `*`
   - Weekday: `*`
4. **Command:** Masukkan salah satu command di atas
5. **Klik "Add New Cron Job"**

### 4. Cek Path PHP yang Benar

#### Cara cek path PHP di cPanel:
1. Buat file `phpinfo.php` di public_html:
```php
<?php phpinfo(); ?>
```
2. Buka `http://yourdomain.com/phpinfo.php`
3. Cari **"System"** → **"PHP Executable"**
4. Copy path yang muncul (biasanya `/usr/local/bin/php`)

#### Alternative - Cek via SSH:
```bash
which php
# atau
whereis php
```

### 5. Cek Path Project Laravel

#### Biasanya path-nya:
```
/home/username/public_html/
# atau
/home/username/domains/yourdomain.com/public_html/
# atau
/home/username/public_html/yourproject/
```

#### Cara cek:
1. Lihat di **File Manager** cPanel
2. Cari folder yang berisi file `artisan`
3. Copy full path-nya

### 6. Test Cron Job

#### Buat file test: `test_cron.php`
```php
<?php
// Simpan di public_html/test_cron.php
$log_file = 'cron_test.log';
$message = date('Y-m-d H:i:s') . " - Cron job is working!\n";
file_put_contents($log_file, $message, FILE_APPEND);
echo "Cron test completed. Check cron_test.log";
?>
```

#### Setup cron job test:
```
* * * * * /usr/local/bin/php /home/username/public_html/test_cron.php
```

#### Cek hasil:
- Tunggu 1-2 menit
- Buka `http://yourdomain.com/cron_test.log`
- Harus ada timestamp baru setiap menit

### 7. Setup Employee Movement Cron Job

#### Setelah test berhasil, ganti dengan command asli:

**Command untuk cPanel:**
```bash
* * * * * /usr/local/bin/php /home/username/public_html/artisan schedule:run >> /home/username/public_html/storage/logs/cron.log 2>&1
```

**Penjelasan:**
- `* * * * *` = Setiap menit
- `>> /home/username/public_html/storage/logs/cron.log` = Log output
- `2>&1` = Capture error juga

### 8. Monitoring & Troubleshooting

#### Cek Log Files:
1. **Laravel Log:** `storage/logs/laravel.log`
2. **Employee Movement Log:** `storage/logs/employee-movements-execution.log`
3. **Cron Log:** `storage/logs/cron.log`

#### Cek via File Manager cPanel:
1. Buka **File Manager**
2. Navigate ke `storage/logs/`
3. Download dan buka file log

#### Cek via SSH (jika ada akses):
```bash
# Cek log terbaru
tail -f storage/logs/employee-movements-execution.log

# Cek semua log
ls -la storage/logs/
```

### 9. Common Issues & Solutions

#### Issue 1: "Command not found"
**Solution:**
- Cek path PHP yang benar
- Gunakan full path: `/usr/local/bin/php`

#### Issue 2: "Permission denied"
**Solution:**
- Set permission folder storage:
```bash
chmod -R 755 storage/
chmod -R 777 storage/logs/
```

#### Issue 3: "Database connection failed"
**Solution:**
- Cek file `.env` di cPanel
- Pastikan database credentials benar
- Cek apakah database server accessible

#### Issue 4: "Artisan command not found"
**Solution:**
- Pastikan path ke project Laravel benar
- Cek apakah file `artisan` ada di folder tersebut

### 10. Alternative: Manual Execution

#### Jika cron job tidak bisa, bisa setup manual execution:

**Buat file: `execute_movements.php`**
```php
<?php
// Simpan di public_html/execute_movements.php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$exitCode = $kernel->handle(
    new Symfony\Component\Console\Input\ArrayInput([
        'command' => 'employee-movements:execute'
    ]),
    new Symfony\Component\Console\Output\BufferedOutput()
);

echo "Execution completed with exit code: " . $exitCode;
?>
```

**Setup cron job:**
```
0 8 * * * /usr/local/bin/php /home/username/public_html/execute_movements.php
```

### 11. Security Considerations

#### Jangan expose file PHP:
1. **Hapus file test** setelah setup selesai:
   - `test_cron.php`
   - `cron_test.log`
   - `execute_movements.php` (jika tidak digunakan)

2. **Protect log files:**
```apache
# Tambahkan di .htaccess
<Files "*.log">
    Order allow,deny
    Deny from all
</Files>
```

### 12. Final Checklist

- [ ] Cron job sudah di-setup di cPanel
- [ ] Path PHP sudah benar
- [ ] Path project Laravel sudah benar
- [ ] Test cron job berhasil
- [ ] Employee movement command berjalan
- [ ] Log files ter-generate
- [ ] File test sudah dihapus
- [ ] Log files sudah di-protect

### 13. Support

Jika ada masalah:
1. Cek log files di `storage/logs/`
2. Test manual: `php artisan employee-movements:execute`
3. Cek database connection
4. Cek file permissions
5. Contact hosting support jika perlu

---

**Note:** Ganti `username` dan `yourdomain.com` dengan informasi hosting Anda yang sebenarnya.
