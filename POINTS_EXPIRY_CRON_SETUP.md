# Point Expiry - Cron Job Setup

## Overview
Point yang sudah melewati tanggal expire akan otomatis dikurangi dari saldo member setiap hari menggunakan cron job.

## Current Cron Job Configuration

### 1. Laravel Scheduler (app/Console/Kernel.php)
```php
// Expire points that have passed their expiration date - run daily at midnight
$schedule->command('points:expire')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/points-expiry.log'))
    ->description('Expire points that have passed their expiration date and reduce member point balance');
```

**Penjelasan:**
- **Waktu**: Setiap hari jam 00:00 (midnight)
- **Command**: `php artisan points:expire`
- **withoutOverlapping()**: Mencegah command berjalan bersamaan jika masih ada yang sedang berjalan
- **runInBackground()**: Menjalankan di background agar tidak blocking
- **Log**: Output disimpan di `storage/logs/points-expiry.log`

### 2. Server Cron Job Setup

**PENTING**: Laravel menggunakan scheduler yang berjalan setiap menit. Hanya perlu **SATU** cron job untuk menjalankan `schedule:run`, dan Laravel akan otomatis menjalankan semua scheduled commands termasuk `points:expire`.

#### Untuk Linux/Ubuntu Server:
```bash
# Edit crontab
crontab -e

# Tambahkan baris berikut (jika belum ada):
* * * * * cd /path/to/ymsofterp && php artisan schedule:run >> /dev/null 2>&1
```

**Catatan**: 
- Ganti `/path/to/ymsofterp` dengan path sebenarnya ke project Laravel
- Cron job ini akan berjalan **setiap menit** dan Laravel akan otomatis menjalankan command yang sudah dijadwalkan
- Jika sudah ada entry `schedule:run` untuk command lain, tidak perlu menambahkan lagi

#### Untuk Windows Server (Task Scheduler):
1. Buka **Task Scheduler**
2. Klik **Create Basic Task**
3. **Name**: Laravel Scheduler
4. **Trigger**: 
   - **Daily** atau **At startup** (untuk berjalan setiap menit, gunakan trigger "On a schedule" dengan interval 1 minute)
5. **Action**: Start a program
6. **Program/script**: `C:\path\to\php.exe` (atau `php` jika sudah di PATH)
7. **Add arguments**: `artisan schedule:run`
8. **Start in**: `D:\Gawean\YM\web\ymsofterp`

**Alternatif untuk Windows (PowerShell Script)**:
Buat file `run-scheduler.ps1`:
```powershell
cd D:\Gawean\YM\web\ymsofterp
php artisan schedule:run
```

Lalu buat scheduled task yang menjalankan PowerShell script ini setiap menit.

#### Untuk Shared Hosting (cPanel):
1. Login ke cPanel
2. Buka **Cron Jobs**
3. Tambahkan cron job baru:

   **Option 1: Menggunakan Laravel Scheduler (Recommended)**
   - **Minute**: `*`
   - **Hour**: `*`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: `cd /home/ymsuperadmin/public_html && php artisan schedule:run >> /dev/null 2>&1`
   
   **Option 2: Command Langsung untuk Point Expiry**
   - **Minute**: `0`
   - **Hour**: `0`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: `cd /home/ymsuperadmin/public_html && php artisan points:expire >> storage/logs/points-expiry.log 2>&1`
   
   (Ganti path sesuai dengan server Anda)

## Command Details

### Command: `php artisan points:expire`

**Fungsi:**
- Mencari point transactions dengan type `earn` yang sudah expire
- Memfilter berdasarkan `expires_at <= tanggal yang dicek` dan `is_expired = false`
- Mengurangi saldo point member sesuai dengan point yang expire
- Mark transaction sebagai expired (`is_expired = true`, `expired_at = now()`)
- Membuat transaction tracking dengan type `expired` untuk audit trail

**Options:**
- `--date=Y-m-d`: Expire points untuk tanggal tertentu (default: hari ini)
- `--dry-run`: Lihat saja tanpa mengubah data (untuk testing)

**Output Log:**
```
Checking for expired points as of 2025-12-01...
Found 5 expired point transaction(s).
Successfully expired 5 point transaction(s).
Total points deducted: 1500
```

## Monitoring & Troubleshooting

### 1. Check Log Files
```bash
# Lihat log point expiry
tail -f storage/logs/points-expiry.log

# Lihat log Laravel umum
tail -f storage/logs/laravel.log
```

### 2. Manual Execution (Testing)
```bash
# Jalankan manual untuk testing
php artisan points:expire

# Dry run (lihat saja tanpa mengubah data)
php artisan points:expire --dry-run

# Expire untuk tanggal tertentu
php artisan points:expire --date=2025-12-01

# Check scheduled commands
php artisan schedule:list
```

### 3. Verify Cron Job Running
```bash
# Check if Laravel scheduler is running
php artisan schedule:list

# Test scheduler (akan menjalankan semua scheduled commands yang waktunya sudah tiba)
php artisan schedule:run

# Check crontab entries
crontab -l

# Check cron service status (Linux)
sudo systemctl status cron
```

### 4. Check Point Transactions
```bash
# Via Laravel Tinker
php artisan tinker

# Check expired points
>>> App\Models\MemberAppsPointTransaction::where('is_expired', true)->count();

# Check points that will expire soon
>>> App\Models\MemberAppsPointTransaction::where('transaction_type', 'earn')
    ->where('is_expired', false)
    ->whereNotNull('expires_at')
    ->where('expires_at', '<=', now()->addDays(7))
    ->get();
```

## Best Practices

### 1. Time Zone
Pastikan server timezone sudah benar:
```php
// config/app.php
'timezone' => 'Asia/Jakarta',
```

### 2. Log Rotation
Setup log rotation untuk mencegah log file terlalu besar:
```bash
# /etc/logrotate.d/laravel
/path/to/project/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    notifempty
    create 644 www-data www-data
}
```

### 3. Error Handling
Command sudah memiliki error handling:
- Menggunakan database transaction untuk memastikan data konsisten
- Jika ada error, transaction akan di-rollback
- Error details akan dicatat di log file
- Command tidak akan crash jika ada masalah dengan satu transaction

### 4. Performance
- Command menggunakan `withoutOverlapping()` untuk mencegah multiple execution
- Menggunakan `runInBackground()` untuk tidak blocking
- Hanya memproses transactions yang sudah expire dan belum di-mark sebagai expired

## Troubleshooting Common Issues

### 1. Cron Job Tidak Berjalan
```bash
# Check cron service
sudo systemctl status cron

# Check crontab
crontab -l

# Check Laravel scheduler
php artisan schedule:list

# Test scheduler manually
php artisan schedule:run
```

### 2. Permission Issues
```bash
# Set proper permissions
chmod -R 755 storage/
chown -R www-data:www-data storage/
```

### 3. Database Connection Issues
- Check `.env` database configuration
- Ensure database server is running
- Check network connectivity

### 4. Point Tidak Ter-expire
```bash
# Check apakah ada point yang seharusnya sudah expire
php artisan tinker
>>> $expired = App\Models\MemberAppsPointTransaction::where('transaction_type', 'earn')
    ->whereNotNull('expires_at')
    ->where('expires_at', '<=', now())
    ->where('is_expired', false)
    ->get();
>>> $expired->count();

# Jika ada, jalankan manual
php artisan points:expire
```

### 5. Point Ter-expire Tapi Saldo Tidak Berkurang
- Check log untuk melihat apakah ada error
- Pastikan member masih memiliki point yang cukup (mungkin sudah di-redeem)
- Check transaction dengan type 'expired' untuk tracking

## Testing

### Test Command Secara Manual
```bash
# 1. Dry run untuk melihat apa yang akan di-expire
php artisan points:expire --dry-run

# 2. Expire untuk tanggal tertentu (testing)
php artisan points:expire --date=2025-12-01

# 3. Check hasil
php artisan tinker
>>> App\Models\MemberAppsPointTransaction::where('transaction_type', 'expired')->latest()->first();
```

### Test Scheduler
```bash
# Lihat semua scheduled commands
php artisan schedule:list

# Test run scheduler (akan menjalankan command yang waktunya sudah tiba)
php artisan schedule:run
```

## Contact & Support
Jika ada masalah dengan cron job execution, check:
1. Log files di `storage/logs/points-expiry.log`
2. Laravel log di `storage/logs/laravel.log`
3. Server cron log di `/var/log/cron.log` (Linux)
4. Task Scheduler history (Windows)

