# ❌ Daftar Cron Jobs yang Harus Dihapus

## 🚨 **URGENT: Queue Worker (Row 8)**

**HAPUS INI DULU** setelah setup supervisor/alternatif:

```
* * * * * cd /home/ymsuperadmin/public_html && php artisan queue:work --queue-notifications --tries=3 --timeout=380 --sleep=3 --max-jobs=1000 --max-time=3680 --stop-when-empty >> storage/logs/queue-worker.log 2>&1
```

**Alasan:** Ini berjalan setiap menit dan menyebabkan 60+ queue worker bersamaan → CPU 100%

**Action:** Setup queue worker dengan supervisor atau single process script (lihat `MIGRASI_CRON_KE_SCHEDULER.md`)

---

## ❌ **Semua Cron Jobs Berikut Harus Dihapus (Duplicate)**

Semua cron jobs ini **sudah ada di Laravel scheduler** (`app/Console/Kernel.php`), jadi harus dihapus:

### **Row 1:**
```
0 23 * * * cd /home/ymsuperadmin/public_html && php artisan attendance:process-holiday >> storage/logs/holiday-attendance.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 23-27 (dailyAt 23:00)

### **Row 2:**
```
0 6 * * * cd /home/ymsuperadmin/public_html && php artisan attendance:process-holiday >> storage/logs/holiday-attendance.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 16-20 (dailyAt 06:00)

### **Row 3:**
```
0 7 * * * cd /home/ymsuperadmin/public_html && php artisan extra-off:detect >> storage/logs/extra-off-detection.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 36-40 (dailyAt 07:00)

### **Row 4:**
```
30 23 * * * cd /home/ymsuperadmin/public_html && php artisan extra-off:detect >> storage/logs/extra-off-detection.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 43-47 (dailyAt 23:30)

### **Row 5:**
```
0 5 * * * cd /home/ymsuperadmin/public_html && php artisan employee-movements:execute >> storage/logs/employee-movements-execution.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 50-54 (dailyAt 08:00) - **Note:** Scheduler pakai 08:00, lebih baik

### **Row 6:**
```
0 0 1 * * cd /home/ymsuperadmin/public_html && php artisan leave:monthly-credit >> storage/logs/leave-monthly-credit.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 57-59 (monthlyOn 1, 00:00)

### **Row 7:**
```
0 0 1 3 * cd /home/ymsuperadmin/public_html && php artisan leave:burn-previous-year >> storage/logs/leave-burning.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 62-64 (yearlyOn 3, 1, 00:00)

### **Row 9:**
```
0 1 * * * cd /home/ymsuperadmin/public_html && php artisan vouchers:distribute-birthday >> storage/logs/birthday-vouchers-distribution.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 83-88 (dailyAt 01:00)

### **Row 10:**
```
0 2 * * 0 cd /home/ymsuperadmin/public_html && php artisan attendance:cleanup-logs >> storage/logs/attendance-cleanup.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 30-33 (weekly, sundays, 02:00)

### **Row 11:**
```
0 0 1 * * cd /home/ymsuperadmin/public_html && php artisan members:update-tiers >> storage/logs/member-tiers-update.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 67-72 (monthlyOn 1, 00:00)

### **Row 12:**
```
0 1 * * * cd /home/ymsuperadmin/public_html && php artisan points:expire >> storage/logs/points-expiry.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 75-80 (dailyAt 00:00) - **Note:** Scheduler pakai 00:00, cron pakai 01:00

### **Row 13:**
```
0 7 * * * cd /home/ymsuperadmin/public_html && php artisan member:notify-incomplete-profile >> storage/logs/incomplete-profile-notifications.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 92-97 (hourly) - **Note:** Scheduler lebih baik (hourly vs daily)

### **Row 14:**
```
0 8 * * * cd /home/ymsuperadmin/public_html && php artisan member:notify-incomplete-challenge >> storage/logs/incomplete-challenge-notifications.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 101-106 (hourly) - **Note:** Scheduler lebih baik (hourly vs daily)

### **Row 15:**
```
0 10 * * * cd /home/ymsuperadmin/public_html && php artisan member:notify-inactive >> storage/logs/inactive-member-notifications.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 110-115 (dailyAt 10:00)

### **Row 16:**
```
0 0 11 * * cd /home/ymsuperadmin/public_html && php artisan member:notify-long-inactive >> storage/logs/long-inactive-member-notifications.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 119-124 (dailyAt 11:00) - **Note:** Scheduler lebih baik (daily vs monthly)

### **Row 17:**
```
0 0 9 * * cd /home/ymsuperadmin/public_html && php artisan member:notify-expiring-points >> storage/logs/expiring-points-notifications.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 128-133 (dailyAt 09:00) - **Note:** Scheduler lebih baik (daily vs monthly)

### **Row 18:**
```
30 10 * * * cd /home/ymsuperadmin/public_html && php artisan member:notify-monthly-inactive >> storage/logs/monthly-inactive-member-notifications.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 137-142 (dailyAt 10:30)

### **Row 19:**
```
30 9 * * * cd /home/ymsuperadmin/public_html && php artisan member:notify-expiring-vouchers >> storage/logs/expiring-vouchers-notifications.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 146-151 (dailyAt 09:30)

### **Row 20:**
```
0 2 * * * cd /home/ymsuperadmin/public_html && php artisan device-tokens:cleanup --days=30 --limit=5 >> storage/logs/device-tokens-cleanup.log 2>&1
```
✅ **Sudah ada di scheduler:** Line 155-160 (dailyAt 02:00)

---

## ✅ **Yang Harus Dipertahankan**

### **1. schedule:run (HARUS ADA!)**

Jika belum ada, tambahkan:
```
* * * * * cd /home/ymsuperadmin/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Ini yang akan menjalankan semua scheduled tasks dari `app/Console/Kernel.php`.

### **2. Queue Worker (Setelah Diperbaiki)**

**JANGAN pakai cron setiap menit!** Setup dengan supervisor atau single process script.

Lihat `MIGRASI_CRON_KE_SCHEDULER.md` untuk detail.

---

## 📋 **CHECKLIST**

- [ ] **URGENT:** Fix queue worker (setup supervisor/alternatif)
- [ ] Hapus queue worker dari cron (Row 8)
- [ ] Pastikan `schedule:run` ada di cron
- [ ] Hapus Row 1-7 (duplicate tasks)
- [ ] Hapus Row 9-20 (duplicate tasks)
- [ ] Verifikasi hanya 1-2 cron jobs tersisa
- [ ] Test bahwa scheduled tasks masih berjalan
- [ ] Monitor CPU usage (harusnya turun drastis)

---

## 🎯 **HASIL AKHIR**

Setelah hapus semua duplicate:

**Cron Jobs yang Tersisa:**
1. ✅ `* * * * * php artisan schedule:run` (wajib)
2. ✅ Queue worker (via supervisor, bukan cron)

**Total:** 1-2 cron jobs (bukan 20!)

**CPU Usage:** Turun dari 100% ke 30-50%

---

**Lihat `MIGRASI_CRON_KE_SCHEDULER.md` untuk panduan lengkap!**

