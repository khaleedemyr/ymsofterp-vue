# Solusi Optimasi Performa Server

## 🔴 Masalah yang Ditemukan

### 1. **Queue Worker Berjalan Terlalu Banyak (MASALAH UTAMA)**
   - Queue worker dijalankan **setiap menit** dengan `--max-time=3600` (1 jam)
   - Setiap menit, worker baru dimulai sementara worker lama masih berjalan
   - Ini bisa menyebabkan **60+ queue worker berjalan bersamaan**, mengkonsumsi CPU 100%

### 2. **Duplicate Cron Jobs**
   - Banyak scheduled task yang dijalankan **2 kali**: sekali dari Laravel scheduler dan sekali dari cron job manual
   - Contoh: `attendance:process-holiday`, `extra-off:detect`, dll sudah ada di `schedule:run` tapi juga ada di cron terpisah

### 3. **PHP-FPM Settings Tidak Optimal**
   - **Max Children: 40** terlalu tinggi untuk 8 vCPU (seharusnya 16-24)
   - **Max Requests: 500** terlalu tinggi (seharusnya 50-100)
   - Ini menyebabkan terlalu banyak proses PHP berjalan bersamaan

### 4. **Hourly Jobs Overlapping**
   - `member:notify-incomplete-profile` dan `member:notify-incomplete-challenge` berjalan setiap jam
   - Bisa overlap dengan job lain yang sedang berjalan

---

## ✅ SOLUSI

### **SOLUSI 1: Perbaiki Queue Worker (PRIORITAS TINGGI)**

**Masalah:** Queue worker berjalan setiap menit dengan max-time 1 jam, menyebabkan banyak worker bersamaan.

**Solusi:** Gunakan **Supervisor** atau ubah cron job menjadi **single long-running process**.

#### Opsi A: Gunakan Supervisor (RECOMMENDED)
Buat file supervisor config: `/etc/supervisor/conf.d/ymsofterp-queue.conf`

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

**Langkah:**
1. Install supervisor (jika belum): `yum install supervisor` atau `apt-get install supervisor`
2. Buat file config di atas
3. Jalankan: `supervisorctl reread && supervisorctl update && supervisorctl start ymsofterp-queue-worker:*`
4. **HAPUS** cron job queue worker yang berjalan setiap menit

#### Opsi B: Ubah Cron Job (Jika tidak bisa pakai Supervisor)
Ganti cron job queue worker dari:
```bash
* * * * * cd /home/ymsuperadmin/public_html && php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600 --stop-when-empty >> storage/logs/queue-worker.log 2>&1
```

Menjadi (hanya 1 worker yang berjalan):
```bash
@reboot cd /home/ymsuperadmin/public_html && nohup php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 > storage/logs/queue-worker.log 2>&1 &
```

Atau gunakan script untuk memastikan hanya 1 worker:
```bash
*/5 * * * * cd /home/ymsuperadmin/public_html && [ $(ps aux | grep 'queue:work' | grep -v grep | wc -l) -eq 0 ] && php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=300 >> storage/logs/queue-worker.log 2>&1
```

---

### **SOLUSI 2: Hapus Duplicate Cron Jobs**

**Hapus cron jobs berikut** karena sudah di-handle oleh `schedule:run`:

1. ❌ `attendance:process-holiday` (06:00 dan 23:00)
2. ❌ `extra-off:detect` (07:00 dan 23:30)
3. ❌ `employee-movements:execute` (05:00)
4. ❌ `leave:monthly-credit` (tanggal 1, 00:00)
5. ❌ `leave:burn-previous-year` (1 Maret, 00:00)
6. ❌ `vouchers:distribute-birthday` (01:00)
7. ❌ `attendance:cleanup-logs` (Minggu, 02:00)
8. ❌ `members:update-tiers` (tanggal 1, 00:00)
9. ❌ `points:expire` (00:00)
10. ❌ `member:notify-incomplete-profile` (hourly)
11. ❌ `member:notify-incomplete-challenge` (hourly)
12. ❌ `member:notify-inactive` (10:00)
13. ❌ `member:notify-long-inactive` (11:00) - **NOTE:** Di Kernel.php seharusnya 11:00, tapi di cron ada 00:00 tanggal 11
14. ❌ `member:notify-expiring-points` (09:00)
15. ❌ `member:notify-monthly-inactive` (10:30)
16. ❌ `member:notify-expiring-vouchers` (09:30)
17. ❌ `device-tokens:cleanup` (02:00)

**PERTAHANKAN hanya:**
- ✅ `* * * * * php artisan schedule:run` (ini yang menjalankan semua scheduled tasks)
- ✅ Queue worker (setelah diperbaiki seperti Solusi 1)

---

### **SOLUSI 3: Optimasi PHP-FPM Settings**

**Ubah setting PHP-FPM menjadi:**

| Setting | Current | Recommended | Alasan |
|---------|---------|-------------|--------|
| **Max Children** | 40 | **20-24** | Untuk 8 vCPU, 20-24 proses sudah cukup. Formula: (vCPU × 2) + 4 = (8 × 2) + 4 = 20 |
| **Max Requests** | 500 | **50-100** | Mencegah memory leak, restart proses lebih sering |
| **Process Idle Timeout** | 10 | **10** | Sudah baik |

**Perhitungan Memory:**
- Setiap PHP-FPM process biasanya menggunakan 50-100MB RAM
- 20 processes × 75MB = ~1.5GB untuk PHP-FPM
- Masih ada sisa RAM untuk aplikasi lain

---

### **SOLUSI 4: Optimasi Scheduled Tasks**

#### A. Kurangi Frekuensi Hourly Jobs
Ubah di `app/Console/Kernel.php`:

```php
// Dari hourly menjadi setiap 2 jam
$schedule->command('member:notify-incomplete-profile')
    ->everyTwoHours()  // Ganti dari hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/incomplete-profile-notifications.log'));

$schedule->command('member:notify-incomplete-challenge')
    ->everyTwoHours()  // Ganti dari hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/incomplete-challenge-notifications.log'));
```

#### B. Tambahkan Timeout untuk Heavy Jobs
Tambahkan timeout untuk job yang berat:

```php
$schedule->command('members:update-tiers')
    ->monthlyOn(1, '00:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->timeout(3600)  // Timeout 1 jam
    ->appendOutputTo(storage_path('logs/member-tiers-update.log'));
```

---

### **SOLUSI 5: Monitoring & Debugging**

#### Check Process yang Berjalan
```bash
# Cek berapa banyak queue worker yang berjalan
ps aux | grep 'queue:work' | grep -v grep | wc -l

# Cek berapa banyak PHP-FPM process
ps aux | grep 'php-fpm' | grep -v grep | wc -l

# Cek CPU usage per process
top -p $(pgrep -d',' -f 'queue:work')

# Cek memory usage
free -h
```

#### Check Queue Status
```bash
cd /home/ymsuperadmin/public_html
php artisan queue:work --help
php artisan queue:monitor notifications
```

---

## 📋 CHECKLIST IMPLEMENTASI

### Langkah 1: Fix Queue Worker (URGENT)
- [ ] Install Supervisor atau ubah cron job queue worker
- [ ] Hapus cron job queue worker yang berjalan setiap menit
- [ ] Verifikasi hanya 1-2 queue worker yang berjalan
- [ ] Monitor CPU usage setelah perubahan

### Langkah 2: Hapus Duplicate Cron Jobs
- [ ] Backup list cron jobs saat ini
- [ ] Hapus semua duplicate cron jobs (17 jobs)
- [ ] Pastikan hanya `schedule:run` yang tersisa (selain queue worker)
- [ ] Test bahwa scheduled tasks masih berjalan via `schedule:run`

### Langkah 3: Optimasi PHP-FPM
- [ ] Ubah Max Children dari 40 ke 20-24
- [ ] Ubah Max Requests dari 500 ke 50-100
- [ ] Restart PHP-FPM: `systemctl restart php-fpm` atau via cPanel
- [ ] Monitor memory usage

### Langkah 4: Optimasi Scheduled Tasks
- [ ] Ubah hourly jobs menjadi everyTwoHours
- [ ] Tambahkan timeout untuk heavy jobs
- [ ] Test scheduled tasks masih berjalan dengan baik

### Langkah 5: Monitoring
- [ ] Setup monitoring CPU dan memory
- [ ] Check log files untuk error
- [ ] Monitor selama 24-48 jam setelah perubahan

---

## 🎯 EXPECTED RESULTS

Setelah implementasi:
- ✅ CPU usage turun dari 100% ke 20-40% (normal)
- ✅ Response time aplikasi lebih cepat
- ✅ Tidak ada duplicate execution
- ✅ Memory usage lebih efisien
- ✅ Server lebih stabil

---

## ⚠️ CATATAN PENTING

1. **Lakukan perubahan secara bertahap**, jangan sekaligus
2. **Backup cron jobs** sebelum menghapus
3. **Monitor selama 24 jam** setelah setiap perubahan
4. **Test di waktu low traffic** dulu jika memungkinkan
5. Jika masih ada masalah, kurangi Max Children ke 16 atau kurangi lagi

---

## 🔧 TROUBLESHOOTING

### Jika CPU masih tinggi setelah fix queue worker:
```bash
# Cek apakah masih ada banyak queue worker
ps aux | grep queue:work

# Kill semua queue worker yang berjalan
pkill -f 'queue:work'

# Restart dengan config baru
```

### Jika aplikasi menjadi lambat setelah kurangi Max Children:
- Naikkan sedikit ke 24 atau 28
- Monitor memory usage
- Pastikan tidak ada memory leak

### Jika scheduled tasks tidak berjalan:
- Pastikan cron job `schedule:run` masih ada
- Check log: `storage/logs/laravel.log`
- Test manual: `php artisan schedule:run`

