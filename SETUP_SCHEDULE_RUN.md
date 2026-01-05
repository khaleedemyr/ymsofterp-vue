# 📅 Setup Schedule:Run untuk Semua Scheduled Tasks

## ✅ Konfigurasi Cron Job Schedule:Run

### **Cron Job yang Harus Ada (HANYA INI)**

Di cPanel → Cron Jobs, **HAPUS semua cron jobs individual** dan **TAMBAHKAN HANYA INI**:

**Minute:** `*`  
**Hour:** `*`  
**Day:** `*`  
**Month:** `*`  
**Weekday:** `*`  
**Command:**
```bash
cd /home/ymsuperadmin/public_html && /usr/bin/php artisan schedule:run >> /home/ymsuperadmin/public_html/storage/logs/schedule.log 2>&1
```

**PENTING:**
- Ganti `/usr/bin/php` dengan path PHP yang benar (cek dengan `which php`)
- Pastikan path aplikasi benar: `/home/ymsuperadmin/public_html`
- Log akan tersimpan di `storage/logs/schedule.log`

---

## 📋 Daftar Semua Scheduled Tasks

Berikut adalah semua scheduled tasks yang sudah dikonfigurasi di `app/Console/Kernel.php`:

### **1. Attendance Tasks**

| Task | Schedule | Log File |
|------|----------|----------|
| `attendance:process-holiday` | Daily 06:00 | `storage/logs/holiday-attendance.log` |
| `attendance:process-holiday` | Daily 23:00 | `storage/logs/holiday-attendance.log` |
| `attendance:cleanup-logs` | Weekly (Sunday 02:00) | - |

### **2. Extra Off Detection**

| Task | Schedule | Log File |
|------|----------|----------|
| `extra-off:detect` | Daily 07:00 | `storage/logs/extra-off-detection.log` |
| `extra-off:detect` | Daily 23:30 | `storage/logs/extra-off-detection.log` |

### **3. Employee Movements**

| Task | Schedule | Log File |
|------|----------|----------|
| `employee-movements:execute` | Daily 08:00 | `storage/logs/employee-movements-execution.log` |

### **4. Leave Management**

| Task | Schedule | Log File |
|------|----------|----------|
| `leave:monthly-credit` | Monthly (1st, 00:00) | - |
| `leave:burn-previous-year` | Yearly (March 1st, 00:00) | - |

### **5. Member Management**

| Task | Schedule | Log File |
|------|----------|----------|
| `members:update-tiers` | Monthly (1st, 00:00) | `storage/logs/member-tiers-update.log` |
| `points:expire` | Daily 00:00 | `storage/logs/points-expiry.log` |
| `vouchers:distribute-birthday` | Daily 01:00 | `storage/logs/birthday-vouchers-distribution.log` |

### **6. Member Notifications**

| Task | Schedule | Log File |
|------|----------|----------|
| `member:notify-incomplete-profile` | Hourly | `storage/logs/incomplete-profile-notifications.log` |
| `member:notify-incomplete-challenge` | Hourly | `storage/logs/incomplete-challenge-notifications.log` |
| `member:notify-inactive` | Daily 10:00 | `storage/logs/inactive-member-notifications.log` |
| `member:notify-long-inactive` | Daily 11:00 | `storage/logs/long-inactive-member-notifications.log` |
| `member:notify-expiring-points` | Daily 09:00 | `storage/logs/expiring-points-notifications.log` |
| `member:notify-monthly-inactive` | Daily 10:30 | `storage/logs/monthly-inactive-member-notifications.log` |
| `member:notify-expiring-vouchers` | Daily 09:30 | `storage/logs/expiring-vouchers-notifications.log` |

### **7. Device Tokens Cleanup**

| Task | Schedule | Log File |
|------|----------|----------|
| `device-tokens:cleanup` | Daily 02:00 | `storage/logs/device-tokens-cleanup.log` |

---

## 🗑️ Cron Jobs yang Harus Dihapus

**HAPUS semua cron jobs berikut** karena sudah di-handle oleh `schedule:run`:

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
11. ❌ `points:expire` (00:00 atau 01:00)
12. ❌ `member:notify-incomplete-profile` (07:00 atau hourly)
13. ❌ `member:notify-incomplete-challenge` (08:00 atau hourly)
14. ❌ `member:notify-inactive` (10:00)
15. ❌ `member:notify-long-inactive` (tanggal 11, 00:00)
16. ❌ `member:notify-expiring-points` (tanggal 9, 00:00)
17. ❌ `member:notify-monthly-inactive` (10:30)
18. ❌ `member:notify-expiring-vouchers` (09:30)
19. ❌ `device-tokens:cleanup` (02:00)

**PERTAHANKAN HANYA:**
- ✅ `schedule:run` (setiap menit)
- ✅ `queue:work` (setelah diperbaiki sesuai solusi sebelumnya)

---

## 🔧 Langkah Setup

### **Langkah 1: Backup Cron Jobs Saat Ini**

Sebelum menghapus, **backup dulu**:
1. Screenshot semua cron jobs
2. Atau copy-paste ke file text

### **Langkah 2: Hapus Semua Cron Jobs Individual**

Di cPanel → Cron Jobs:
1. Hapus satu per satu semua cron jobs individual (19 jobs di atas)
2. **JANGAN hapus** `schedule:run` dan `queue:work` dulu

### **Langkah 3: Pastikan Schedule:Run Ada dan Benar**

**Check apakah sudah ada:**
- Minute: `*`
- Hour: `*`
- Day: `*`
- Month: `*`
- Weekday: `*`
- Command: `cd /home/ymsuperadmin/public_html && /usr/bin/php artisan schedule:run >> /home/ymsuperadmin/public_html/storage/logs/schedule.log 2>&1`

**Jika belum ada atau salah, edit/tambah:**
1. Klik "Add New Cron Job" atau "Edit"
2. Isi sesuai di atas
3. Pastikan menggunakan **full path** untuk PHP dan aplikasi

### **Langkah 4: Test Schedule:Run**

Jalankan di server (via SSH atau Terminal):
```bash
cd /home/ymsuperadmin/public_html
php artisan schedule:run
```

**Expected output:**
- Tidak ada error
- Tasks yang waktunya sudah tiba akan dijalankan
- Tasks yang belum waktunya akan di-skip

### **Langkah 5: Test Schedule:List**

Jalankan:
```bash
php artisan schedule:list
```

**Expected output:**
- Menampilkan semua scheduled tasks dengan waktu next run
- Pastikan semua task yang diharapkan ada di list

### **Langkah 6: Monitor Log**

Monitor log file:
```bash
tail -f /home/ymsuperadmin/public_html/storage/logs/schedule.log
```

Tunggu 1-2 menit, seharusnya ada entry baru setiap menit.

### **Langkah 7: Verifikasi Task Berjalan**

Tunggu sampai waktu scheduled task (misal jam 06:00 untuk `attendance:process-holiday`), lalu check log:
```bash
tail -20 /home/ymsuperadmin/public_html/storage/logs/holiday-attendance.log
```

Seharusnya ada entry baru sesuai jadwal.

---

## ✅ Checklist Setup

- [ ] Backup cron jobs saat ini (screenshot/copy)
- [ ] Hapus semua cron jobs individual (19 jobs)
- [ ] Pastikan cron job `schedule:run` ada dan benar
- [ ] Test `php artisan schedule:run` manual berhasil
- [ ] Test `php artisan schedule:list` menampilkan semua tasks
- [ ] Monitor `storage/logs/schedule.log` update setiap menit
- [ ] Verifikasi 1-2 scheduled tasks berjalan sesuai jadwal
- [ ] Check log file masing-masing task untuk memastikan berjalan

---

## 🔍 Troubleshooting

### Problem: Schedule:Run tidak jalan

**Solusi:**
1. Check cron job ada: `crontab -l | grep schedule:run`
2. Check PHP path benar: `which php`
3. Check permission: `chmod +x artisan`
4. Check log: `tail -20 storage/logs/schedule.log`

Lihat file `SOLUSI_SCHEDULE_RUN_TIDAK_JALAN.md` untuk solusi lengkap.

### Problem: Task tidak dieksekusi meski schedule:run jalan

**Solusi:**
1. Check waktu server: `date`
2. Check timezone di `.env`: `APP_TIMEZONE`
3. Check `schedule:list` untuk melihat next run time
4. Test manual: `php artisan [task-name]`

### Problem: Duplicate execution

**Solusi:**
- Pastikan semua cron jobs individual sudah dihapus
- Hanya `schedule:run` yang tersisa (selain `queue:work`)

---

## 📊 Monitoring

### Check Schedule Status
```bash
# List semua scheduled tasks
php artisan schedule:list

# Test run dengan verbose
php artisan schedule:run -v

# Monitor log real-time
tail -f storage/logs/schedule.log
```

### Check Task Execution
```bash
# Check log file masing-masing task
tail -20 storage/logs/holiday-attendance.log
tail -20 storage/logs/extra-off-detection.log
tail -20 storage/logs/employee-movements-execution.log
# ... dst
```

---

## ⚠️ CATATAN PENTING

1. **Jangan hapus semua cron jobs sekaligus** - hapus satu per satu dan test
2. **Backup dulu** sebelum menghapus
3. **Test manual** sebelum mengandalkan cron
4. **Monitor selama 24 jam** setelah setup
5. **Pastikan timezone server benar** - ini penting untuk scheduled tasks

---

## 🎯 Expected Results

Setelah setup:
- ✅ Hanya 2 cron jobs: `schedule:run` dan `queue:work`
- ✅ Semua scheduled tasks berjalan via `schedule:run`
- ✅ Tidak ada duplicate execution
- ✅ Log file update sesuai jadwal
- ✅ Server lebih efisien (tidak ada 20+ cron jobs)

