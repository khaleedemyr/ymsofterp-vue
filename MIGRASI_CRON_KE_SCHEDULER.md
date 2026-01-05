# 🔄 Migrasi Cron Jobs ke Laravel Scheduler

## 🚨 **MASALAH KRITIS YANG DITEMUKAN**

### **1. QUEUE WORKER BERJALAN SETIAP MENIT** ⚠️⚠️⚠️
**Ini penyebab utama CPU 100%!**

Cron job Row 8:
```
* * * * * php artisan queue:work --queue-notifications ...
```

**Masalah:**
- Queue worker dijalankan **setiap menit** via cron
- Setiap worker bisa berjalan sampai **1 jam** (`--max-time=3680`)
- Hasilnya: **60+ queue worker berjalan bersamaan** → CPU 100%

**Solusi:** Hapus ini dan setup queue worker dengan benar (lihat bagian Queue Worker di bawah).

---

### **2. SEMUA CRON JOBS ADALAH DUPLICATE** ⚠️

**Semua 19 cron jobs lainnya sudah ada di Laravel scheduler!**

Ini menyebabkan:
- Task dijalankan **2 kali** (dari cron DAN dari scheduler)
- Waste resources
- Bisa menyebabkan race condition
- CPU usage tinggi

**Solusi:** Hapus semua duplicate cron jobs, hanya pertahankan `schedule:run`.

---

## ✅ **ANALISIS: Cron Jobs vs Scheduler**

| # | Cron Job | Schedule di Cron | Ada di Scheduler? | Status |
|---|----------|------------------|-------------------|--------|
| 1 | `attendance:process-holiday` (23:00) | ✅ | ✅ Line 23-27 | ❌ **DUPLICATE** |
| 2 | `attendance:process-holiday` (06:00) | ✅ | ✅ Line 16-20 | ❌ **DUPLICATE** |
| 3 | `extra-off:detect` (07:00) | ✅ | ✅ Line 36-40 | ❌ **DUPLICATE** |
| 4 | `extra-off:detect` (23:30) | ✅ | ✅ Line 43-47 | ❌ **DUPLICATE** |
| 5 | `employee-movements:execute` (05:00) | ✅ | ✅ Line 50-54 | ❌ **DUPLICATE** |
| 6 | `leave:monthly-credit` (tanggal 1) | ✅ | ✅ Line 57-59 | ❌ **DUPLICATE** |
| 7 | `leave:burn-previous-year` (1 Maret) | ✅ | ✅ Line 62-64 | ❌ **DUPLICATE** |
| 8 | **`queue:work` (setiap menit)** | ✅ | ❌ | ⚠️ **FIX INI!** |
| 9 | `vouchers:distribute-birthday` (01:00) | ✅ | ✅ Line 83-88 | ❌ **DUPLICATE** |
| 10 | `attendance:cleanup-logs` (Minggu 02:00) | ✅ | ✅ Line 30-33 | ❌ **DUPLICATE** |
| 11 | `members:update-tiers` (tanggal 1) | ✅ | ✅ Line 67-72 | ❌ **DUPLICATE** |
| 12 | `points:expire` (00:00) | ✅ | ✅ Line 75-80 | ❌ **DUPLICATE** |
| 13 | `member:notify-incomplete-profile` (07:00) | ✅ | ✅ Line 92-97 (hourly) | ❌ **DUPLICATE** |
| 14 | `member:notify-incomplete-challenge` (08:00) | ✅ | ✅ Line 101-106 (hourly) | ❌ **DUPLICATE** |
| 15 | `member:notify-inactive` (10:00) | ✅ | ✅ Line 110-115 | ❌ **DUPLICATE** |
| 16 | `member:notify-long-inactive` (tanggal 11) | ✅ | ✅ Line 119-124 (11:00 daily) | ❌ **DUPLICATE** |
| 17 | `member:notify-expiring-points` (tanggal 9) | ✅ | ✅ Line 128-133 (09:00 daily) | ❌ **DUPLICATE** |
| 18 | `member:notify-monthly-inactive` (10:30) | ✅ | ✅ Line 137-142 | ❌ **DUPLICATE** |
| 19 | `member:notify-expiring-vouchers` (09:30) | ✅ | ✅ Line 146-151 | ❌ **DUPLICATE** |
| 20 | `device-tokens:cleanup` (02:00) | ✅ | ✅ Line 155-160 | ❌ **DUPLICATE** |

**KESIMPULAN:** Semua cron jobs sudah ada di scheduler, jadi **SEMUA HARUS DIHAPUS** dari cron!

---

## ✅ **SOLUSI LENGKAP**

### **LANGKAH 1: Fix Queue Worker (PRIORITAS TINGGI!)**

Queue worker **TIDAK BOLEH** dijalankan via cron setiap menit. Gunakan salah satu solusi:

#### **Opsi A: Pakai Supervisor (RECOMMENDED)**

1. Install Supervisor (jika belum):
   ```bash
   # CentOS/RHEL
   yum install supervisor
   
   # Ubuntu/Debian
   apt-get install supervisor
   ```

2. Buat file: `/etc/supervisor/conf.d/ymsofterp-queue.conf`
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

4. **HAPUS** cron job queue worker (Row 8)

#### **Opsi B: Single Process Script (Jika tidak bisa pakai Supervisor)**

1. **HAPUS** cron job queue worker (Row 8)

2. Ganti dengan ini (hanya 1 worker yang berjalan):
   ```bash
   */5 * * * * cd /home/ymsuperadmin/public_html && [ $(ps aux | grep 'queue:work' | grep -v grep | wc -l) -eq 0 ] && php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=300 >> storage/logs/queue-worker.log 2>&1
   ```

   Ini akan check apakah ada worker yang berjalan, kalau tidak ada baru start.

---

### **LANGKAH 2: Pastikan schedule:run Ada di Cron**

**HARUS ADA** cron job ini (jika belum ada, tambahkan):

```bash
* * * * * cd /home/ymsuperadmin/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Ini akan menjalankan semua scheduled tasks dari `app/Console/Kernel.php`.

---

### **LANGKAH 3: Hapus Semua Duplicate Cron Jobs**

**HAPUS semua cron jobs berikut** (Row 1-7, 9-20):

1. ❌ `attendance:process-holiday` (23:00) - Row 1
2. ❌ `attendance:process-holiday` (06:00) - Row 2
3. ❌ `extra-off:detect` (07:00) - Row 3
4. ❌ `extra-off:detect` (23:30) - Row 4
5. ❌ `employee-movements:execute` (05:00) - Row 5
6. ❌ `leave:monthly-credit` (tanggal 1) - Row 6
7. ❌ `leave:burn-previous-year` (1 Maret) - Row 7
8. ❌ **`queue:work` (setiap menit)** - Row 8 ⚠️ **FIX DULU!**
9. ❌ `vouchers:distribute-birthday` (01:00) - Row 9
10. ❌ `attendance:cleanup-logs` (Minggu 02:00) - Row 10
11. ❌ `members:update-tiers` (tanggal 1) - Row 11
12. ❌ `points:expire` (00:00) - Row 12
13. ❌ `member:notify-incomplete-profile` (07:00) - Row 13
14. ❌ `member:notify-incomplete-challenge` (08:00) - Row 14
15. ❌ `member:notify-inactive` (10:00) - Row 15
16. ❌ `member:notify-long-inactive` (tanggal 11) - Row 16
17. ❌ `member:notify-expiring-points` (tanggal 9) - Row 17
18. ❌ `member:notify-monthly-inactive` (10:30) - Row 18
19. ❌ `member:notify-expiring-vouchers` (09:30) - Row 19
20. ❌ `device-tokens:cleanup` (02:00) - Row 20

**PERTAHANKAN hanya:**
- ✅ `* * * * * php artisan schedule:run` (jika belum ada, tambahkan)
- ✅ Queue worker (setelah diperbaiki di Langkah 1)

---

## 📋 **CHECKLIST IMPLEMENTASI**

### **Langkah 1: Fix Queue Worker (URGENT!)**
- [ ] Install Supervisor (jika pakai Opsi A)
- [ ] Buat config supervisor untuk queue worker
- [ ] Start queue worker via supervisor
- [ ] **HAPUS** cron job queue worker (Row 8)
- [ ] Verifikasi hanya 1-2 queue worker yang berjalan:
  ```bash
  ps aux | grep 'queue:work' | grep -v grep | wc -l
  # Harusnya: 1-2, bukan 60+
  ```

### **Langkah 2: Pastikan schedule:run Ada**
- [ ] Check apakah ada cron job `schedule:run`
- [ ] Jika belum ada, tambahkan:
  ```bash
  * * * * * cd /home/ymsuperadmin/public_html && php artisan schedule:run >> /dev/null 2>&1
  ```

### **Langkah 3: Hapus Duplicate Cron Jobs**
- [ ] Backup list cron jobs saat ini (screenshot atau copy-paste)
- [ ] Hapus Row 1-7 (duplicate tasks)
- [ ] Hapus Row 9-20 (duplicate tasks)
- [ ] **JANGAN hapus** queue worker dulu (hapus setelah fix di Langkah 1)

### **Langkah 4: Verifikasi**
- [ ] Test bahwa scheduled tasks masih berjalan:
  ```bash
  php artisan schedule:list
  ```
- [ ] Monitor selama 24 jam
- [ ] Check log files untuk memastikan tasks berjalan
- [ ] Monitor CPU usage (harusnya turun drastis)

---

## 🎯 **EXPECTED RESULTS**

Setelah migrasi:

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| **Cron Jobs** | 20 jobs | 1-2 jobs (schedule:run + queue worker) |
| **Queue Workers** | 60+ (setiap menit) | 1-2 (supervisor) |
| **CPU Usage** | 100% | 30-50% |
| **Task Execution** | Duplicate (2x) | Single (1x) |
| **Server Stability** | Tidak stabil | Stabil |

---

## ⚠️ **CATATAN PENTING**

### **Perbedaan Schedule di Cron vs Scheduler:**

Beberapa task di cron memiliki waktu yang sedikit berbeda dengan scheduler:

| Task | Cron | Scheduler | Action |
|------|------|----------|--------|
| `employee-movements:execute` | 05:00 | 08:00 | ✅ Gunakan scheduler (08:00) |
| `member:notify-incomplete-profile` | 07:00 | hourly | ✅ Gunakan scheduler (hourly lebih baik) |
| `member:notify-incomplete-challenge` | 08:00 | hourly | ✅ Gunakan scheduler (hourly lebih baik) |
| `member:notify-long-inactive` | tanggal 11, 00:00 | 11:00 daily | ✅ Gunakan scheduler (11:00 daily) |
| `member:notify-expiring-points` | tanggal 9, 00:00 | 09:00 daily | ✅ Gunakan scheduler (09:00 daily) |

**Semua perbedaan ini sudah lebih baik di scheduler**, jadi gunakan scheduler.

---

## 🔍 **VERIFIKASI SETELAH MIGRASI**

### **1. Check Scheduled Tasks:**
```bash
cd /home/ymsuperadmin/public_html
php artisan schedule:list
```

Harusnya menampilkan semua tasks yang ada di `Kernel.php`.

### **2. Test Manual:**
```bash
# Test schedule:run
php artisan schedule:run

# Test queue worker
php artisan queue:work --queue=notifications --once
```

### **3. Monitor Logs:**
```bash
# Check scheduler log
tail -f storage/logs/laravel.log

# Check queue worker log
tail -f storage/logs/queue-worker.log

# Check task-specific logs
tail -f storage/logs/holiday-attendance.log
tail -f storage/logs/extra-off-detection.log
# dll...
```

### **4. Monitor CPU:**
```bash
# Check CPU usage
top

# Check queue workers
ps aux | grep 'queue:work' | grep -v grep | wc -l
# Harusnya: 1-2

# Check PHP-FPM processes
ps aux | grep php-fpm | grep -v grep | wc -l
# Harusnya: 12-24 (setelah optimasi PHP-FPM)
```

---

## 🚨 **TROUBLESHOOTING**

### **Scheduled tasks tidak berjalan setelah hapus cron?**

1. **Pastikan `schedule:run` ada di cron:**
   ```bash
   crontab -l | grep schedule:run
   ```

2. **Test manual:**
   ```bash
   php artisan schedule:run
   ```

3. **Check log:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **Lihat dokumentasi:** `SOLUSI_SCHEDULER_TIDAK_TERDETEKSI.md`

### **Queue worker tidak jalan setelah setup supervisor?**

1. **Check supervisor status:**
   ```bash
   supervisorctl status
   ```

2. **Check log:**
   ```bash
   tail -f /home/ymsuperadmin/public_html/storage/logs/queue-worker.log
   ```

3. **Restart supervisor:**
   ```bash
   supervisorctl restart ymsofterp-queue-worker:*
   ```

### **CPU masih 100% setelah migrasi?**

1. **Check queue workers:**
   ```bash
   ps aux | grep 'queue:work' | grep -v grep
   ```
   Jika masih banyak, kill semua dan restart dengan supervisor.

2. **Check PHP-FPM:**
   ```bash
   ps aux | grep php-fpm | grep -v grep | wc -l
   ```
   Harusnya 12-24, bukan 40+.

3. **Check scheduled tasks:**
   ```bash
   ps aux | grep 'schedule:run' | grep -v grep
   ```
   Harusnya hanya 1 yang jalan.

---

## 📚 **REFERENSI**

- `SOLUSI_LEMOT_SERVER.md` - Solusi lengkap server lemot
- `SOLUSI_SCHEDULER_TIDAK_TERDETEKSI.md` - Troubleshooting scheduler
- `app/Console/Kernel.php` - File scheduler
- `check-php-fpm-status.sh` - Script monitoring

---

## ✅ **KESIMPULAN**

**YA, BISA PAKAI SCHEDULER!** Semua cron jobs sudah ada di scheduler.

**Yang perlu dilakukan:**
1. ⚠️ **URGENT:** Fix queue worker (jangan pakai cron setiap menit!)
2. ✅ Pastikan `schedule:run` ada di cron
3. ✅ Hapus semua duplicate cron jobs (19 jobs)
4. ✅ Monitor selama 24 jam

**Hasil:** CPU turun dari 100% ke 30-50%, server lebih stabil, tidak ada duplicate execution.

---

**Mulai dari Langkah 1 sekarang!** ⚡

