# Quick Fix: Server CPU 100% - Langkah Cepat

## ⚠️ LANGKAH DARURAT (Jalankan Sekarang!)

### 1. Cek dan Kill MySQL Processes yang Stuck
```sql
-- Login ke MySQL
mysql -u root -p

-- Lihat semua process
SHOW PROCESSLIST;

-- Lihat process yang stuck (> 60 detik)
SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, LEFT(INFO, 200) as QUERY
FROM information_schema.PROCESSLIST
WHERE TIME > 60
ORDER BY TIME DESC;

-- Kill process yang stuck (GANTI [ID] dengan ID yang sebenarnya)
KILL [ID];
```

### 2. Restart Queue Workers
```bash
cd /path/to/ymsofterp
php artisan queue:restart
```

### 3. Cek Scheduled Tasks yang Stuck
```bash
# Cek apakah ada schedule:run yang stuck
ps aux | grep "schedule:run" | grep -v grep

# Jika ada yang stuck, kill process tersebut
pkill -f "schedule:run"

# Restart schedule:run (jika menggunakan supervisor atau cron)
```

### 4. Cek PHP-FPM Processes
```bash
# Cek jumlah PHP-FPM processes
ps aux | grep php-fpm | wc -l

# Jika terlalu banyak (> 50), restart PHP-FPM
sudo service php-fpm restart
# atau
sudo systemctl restart php-fpm
```

### 5. Clear Laravel Cache
```bash
cd /path/to/ymsofterp
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## 🔍 DIAGNOSTIK LANJUTAN

### Step 1: Identifikasi Process yang Menggunakan CPU
```bash
# Lihat top 10 process yang menggunakan CPU
top -bn1 | head -20

# Atau gunakan htop (jika tersedia)
htop
```

### Step 2: Cek MySQL Status
```sql
-- Cek connection count
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Threads_running';
SHOW VARIABLES LIKE 'max_connections';

-- Cek process yang membuat index atau alter table
SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO
FROM information_schema.PROCESSLIST
WHERE INFO LIKE '%CREATE INDEX%' 
   OR INFO LIKE '%ALTER TABLE%'
   OR INFO LIKE '%DROP INDEX%'
ORDER BY TIME DESC;
```

### Step 3: Cek Laravel Logs
```bash
# Cek error terbaru
tail -100 storage/logs/laravel.log | grep -i "error\|exception\|timeout"

# Cek scheduled tasks logs
tail -50 storage/logs/schedule.log
tail -50 storage/logs/holiday-attendance.log
tail -50 storage/logs/extra-off-detection.log
tail -50 storage/logs/member-tiers-update.log
```

### Step 4: Cek Queue Status
```bash
# Cek failed jobs
php artisan queue:failed

# Cek queue table (jika menggunakan database queue)
mysql -u root -p -e "SELECT * FROM jobs ORDER BY id DESC LIMIT 10;"
```

## 🛠️ PERBAIKAN YANG PERLU DILAKUKAN

### 1. Fix Scheduled Tasks yang Tidak Ada Protection
Edit `app/Console/Kernel.php` dan tambahkan `withoutOverlapping()` dan `runInBackground()` pada task yang belum ada:

```php
// Line 57-59: leave:monthly-credit
$schedule->command('leave:monthly-credit')
    ->monthlyOn(1, '00:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/monthly-leave-credit.log'))
    ->description('Memberikan cuti bulanan ke semua karyawan aktif');

// Line 62-64: leave:burn-previous-year
$schedule->command('leave:burn-previous-year')
    ->yearlyOn(3, 1, '00:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/burn-previous-year-leave.log'))
    ->description('Burning sisa cuti tahun sebelumnya');

// Line 30-33: attendance:cleanup-logs
$schedule->command('attendance:cleanup-logs')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/attendance-cleanup.log'));
```

### 2. Optimasi Query di Marketing Dashboard
Query di `app/Services/AIDatabaseHelper.php` perlu dioptimasi:
- Tambahkan index pada kolom yang sering di-query
- Tambahkan limit pada query yang tidak perlu semua data
- Cache hasil query yang tidak sering berubah

### 3. Monitor Queue Workers
Pastikan queue workers tidak stuck:
```bash
# Setup supervisor untuk queue workers (recommended)
# Atau gunakan cron untuk restart queue workers setiap jam
0 * * * * cd /path/to/ymsofterp && php artisan queue:restart
```

### 4. Optimasi Database Connection
Pastikan database connection pool tidak terlalu besar:
- Monitor `Threads_connected` di MySQL
- Pastikan PHP-FPM cleanup connection dengan benar
- Pertimbangkan mengurangi `max_connections` jika terlalu besar

## 📊 MONITORING SETELAH PERBAIKAN

### 1. Monitor CPU Usage
```bash
# Monitor CPU setiap 5 detik
watch -n 5 'top -bn1 | head -20'
```

### 2. Monitor MySQL Processes
```sql
-- Monitor process yang lama
SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, LEFT(INFO, 200) as QUERY
FROM information_schema.PROCESSLIST
WHERE TIME > 10
ORDER BY TIME DESC;
```

### 3. Monitor Laravel Logs
```bash
# Monitor logs real-time
tail -f storage/logs/laravel.log
```

## ⚡ PREVENTIVE MEASURES

1. **Setup Monitoring**: Install monitoring tools (New Relic, DataDog, atau custom monitoring)
2. **Regular Maintenance**: 
   - Optimize tables secara berkala
   - Cleanup old logs
   - Monitor slow queries
3. **Code Review**: 
   - Review semua query yang berat
   - Tambahkan index yang diperlukan
   - Optimize N+1 queries
4. **Resource Limits**: 
   - Set max execution time untuk scheduled tasks
   - Set memory limit yang sesuai
   - Monitor disk space

## 📞 JIKA MASALAH TERUS BERLANJUT

1. Cek apakah ada migration atau index creation yang sedang berjalan
2. Cek apakah ada backup atau maintenance task yang sedang berjalan
3. Cek disk I/O (mungkin disk penuh atau I/O bottleneck)
4. Cek network latency ke database server
5. Pertimbangkan untuk scale up server resources
