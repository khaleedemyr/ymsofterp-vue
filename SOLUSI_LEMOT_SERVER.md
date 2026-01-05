# 🚨 Solusi Server Lemot - CPU 100%

## Masalah Utama yang Ditemukan

Berdasarkan analisis setting PHP-FPM, CPU usage, dan cron jobs, ada **3 masalah utama**:

### 1. ⚠️ **QUEUE WORKER BERJALAN TERLALU BANYAK** (PENYEBAB UTAMA)
- Queue worker dijalankan **setiap menit** via cron
- Setiap worker bisa berjalan sampai **1 jam** (`--max-time=3600`)
- Hasilnya: **60+ queue worker berjalan bersamaan** → CPU 100%

### 2. ⚠️ **DUPLICATE CRON JOBS**
- Banyak task yang dijalankan **2 kali**: dari Laravel scheduler DAN dari cron manual
- Contoh: `attendance:process-holiday` ada di cron jam 06:00 dan 23:00, padahal sudah ada di `schedule:run`

### 3. ⚠️ **PHP-FPM SETTINGS TIDAK OPTIMAL**
- Max Children: **40** (terlalu tinggi untuk 8 vCPU)
- Max Requests: **500** (terlalu tinggi, bisa memory leak)

---

## ✅ LANGKAH PERBAIKAN (URUTAN PRIORITAS)

### **LANGKAH 1: Fix Queue Worker** (Lakukan SEKARANG - URGENT!)

**Masalah:** Queue worker berjalan setiap menit, menyebabkan puluhan worker bersamaan.

**Solusi Pilihan A: Pakai Supervisor** (RECOMMENDED)

1. Install Supervisor (jika belum):
   ```bash
   # CentOS/RHEL
   yum install supervisor
   
   # Ubuntu/Debian  
   apt-get install supervisor
   ```

2. Buat file config: `/etc/supervisor/conf.d/ymsofterp-queue.conf`
   ```ini
   [program:ymsofterp-queue-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /home/ymsuperadmin/public_html/artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=ymsuperadmin
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/home/ymsuperadmin/public_html/storage/logs/queue-worker.log
   stopwaitsecs=3600
   ```

3. Aktifkan:
   ```bash
   supervisorctl reread
   supervisorctl update
   supervisorctl start ymsofterp-queue-worker:*
   ```

4. **HAPUS** cron job queue worker yang berjalan setiap menit di cPanel

**Solusi Pilihan B: Single Process** (Jika tidak bisa pakai Supervisor)

1. Hapus cron job queue worker yang berjalan setiap menit
2. Ganti dengan ini (hanya 1 worker yang berjalan):
   ```bash
   */5 * * * * cd /home/ymsuperadmin/public_html && [ $(ps aux | grep 'queue:work' | grep -v grep | wc -l) -eq 0 ] && php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=300 >> storage/logs/queue-worker.log 2>&1
   ```
   Ini akan check apakah ada worker yang berjalan, kalau tidak ada baru start.

---

### **LANGKAH 2: Hapus Duplicate Cron Jobs**

**Hapus cron jobs berikut** karena sudah di-handle oleh `schedule:run`:

1. ❌ `attendance:process-holiday` (06:00)
2. ❌ `attendance:process-holiday` (23:00)
3. ❌ `extra-off:detect` (07:00)
4. ❌ `extra-off:detect` (23:30)
5. ❌ `employee-movements:execute` (05:00)
6. ❌ `leave:monthly-credit` (tanggal 1, 00:00)
7. ❌ `leave:burn-previous-year` (1 Maret, 00:00)
8. ❌ `vouchers:distribute-birthday` (01:00)
9. ❌ `attendance:cleanup-logs` (Minggu, 02:00)
10. ❌ `members:update-tiers` (tanggal 1, 00:00)
11. ❌ `points:expire` (00:00)
12. ❌ `member:notify-incomplete-profile` (hourly)
13. ❌ `member:notify-incomplete-challenge` (hourly)
14. ❌ `member:notify-inactive` (10:00)
15. ❌ `member:notify-long-inactive` (tanggal 11, 00:00)
16. ❌ `member:notify-expiring-points` (tanggal 9, 00:00)
17. ❌ `member:notify-monthly-inactive` (10:30)
18. ❌ `member:notify-expiring-vouchers` (09:30)
19. ❌ `device-tokens:cleanup` (02:00)

**PERTAHANKAN hanya:**
- ✅ `* * * * * php artisan schedule:run` (ini yang menjalankan semua)
- ✅ Queue worker (setelah diperbaiki di Langkah 1)

**Cara:** Masuk ke cPanel → Cron Jobs → Hapus satu per satu cron jobs di atas.

---

### **LANGKAH 3: Optimasi PHP-FPM Settings**

Masuk ke cPanel → PHP-FPM Settings, ubah:

| Setting | Dari | Ke | Alasan |
|---------|------|-----|--------|
| **Max Children** | 40 | **20-24** | Untuk 8 vCPU, 20-24 sudah cukup |
| **Max Requests** | 500 | **50-100** | Mencegah memory leak |
| **Process Idle Timeout** | 10 | **10** | Sudah baik, tetap |

**Rumus Max Children:** (vCPU × 2) + 4 = (8 × 2) + 4 = **20**

Setelah ubah, klik **Update** dan restart PHP-FPM.

---

### **LANGKAH 4: Optimasi Scheduled Tasks** (Optional)

Edit file: `app/Console/Kernel.php`

Ubah hourly jobs menjadi setiap 2 jam (baris 92-106):

```php
// Ganti dari hourly() menjadi everyTwoHours()
$schedule->command('member:notify-incomplete-profile')
    ->everyTwoHours()  // ← GANTI INI
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/incomplete-profile-notifications.log'));

$schedule->command('member:notify-incomplete-challenge')
    ->everyTwoHours()  // ← GANTI INI
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/incomplete-challenge-notifications.log'));
```

---

## 📊 Monitoring Setelah Perbaikan

### Check Status Server
Jalankan script (atau manual):
```bash
# Check berapa banyak queue worker
ps aux | grep 'queue:work' | grep -v grep | wc -l
# Harusnya hanya 1-2, bukan 60+

# Check CPU usage
top
# Harusnya turun ke 20-40%, bukan 100%

# Check PHP-FPM processes
ps aux | grep php-fpm | wc -l
# Harusnya sekitar 20-24, bukan 40+
```

### Expected Results
- ✅ CPU usage: **20-40%** (normal, bukan 100%)
- ✅ Queue workers: **1-2** (bukan 60+)
- ✅ PHP-FPM processes: **20-24** (bukan 40+)
- ✅ Response time aplikasi lebih cepat
- ✅ Server lebih stabil

---

## ⚠️ PENTING!

1. **Lakukan Langkah 1 DULU** (fix queue worker) - ini yang paling urgent
2. **Monitor selama 1-2 jam** setelah Langkah 1 sebelum lanjut ke Langkah 2
3. **Backup cron jobs** sebelum menghapus (screenshot atau copy-paste)
4. **Test di waktu low traffic** jika memungkinkan
5. Jika masih ada masalah setelah semua langkah, kurangi Max Children ke 16

---

## 🔧 Troubleshooting

### CPU masih 100% setelah fix queue worker?
```bash
# Kill semua queue worker
pkill -f 'queue:work'

# Check apakah masih ada
ps aux | grep queue:work

# Restart dengan config baru
```

### Scheduled tasks tidak berjalan setelah hapus cron?
- Pastikan cron job `schedule:run` masih ada
- Test manual: `php artisan schedule:run`
- Check log: `storage/logs/laravel.log`
- **Jika `schedule:run` tidak jalan terus:** Lihat file `SOLUSI_SCHEDULE_RUN_TIDAK_JALAN.md` untuk solusi lengkap

### Aplikasi jadi lambat setelah kurangi Max Children?
- Naikkan sedikit ke 24 atau 28
- Monitor memory usage
- Pastikan tidak ada memory leak di aplikasi

---

## 📞 Bantuan

Jika masih ada masalah setelah implementasi:
1. Check log: `storage/logs/queue-worker.log`
2. Check log: `storage/logs/laravel.log`
3. Monitor dengan: `top`, `htop`, atau `glances`
4. Check apakah ada error di aplikasi

---

**File Helper:**
- `fix-queue-worker.sh` - Script untuk fix queue worker
- `check-server-status.sh` - Script untuk check status server
- `PERFORMANCE_OPTIMIZATION.md` - Dokumentasi lengkap (English)

