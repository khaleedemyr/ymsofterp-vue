# 🔍 Cara Cek Scheduler di Laravel

Scheduler di Laravel dikonfigurasi di file `app/Console/Kernel.php`. Berikut cara mengeceknya:

---

## ✅ Cara 1: Via Artisan Command (RECOMMENDED)

### **List Semua Scheduled Tasks**

Jalankan di server:
```bash
cd /home/ymsuperadmin/public_html
php artisan schedule:list
```

**Output akan menampilkan:**
- Command name
- Description
- Next Due Time
- Timezone

**Contoh output:**
```
0 6 * * *  php artisan attendance:process-holiday ................ Next Due: 2024-01-15 06:00:00
0 23 * * *  php artisan attendance:process-holiday ................ Next Due: 2024-01-14 23:00:00
0 7 * * *  php artisan extra-off:detect ......................... Next Due: 2024-01-15 07:00:00
...
```

### **Test Run Scheduler (Dry Run)**

Jalankan:
```bash
php artisan schedule:run -v
```

**Output akan menampilkan:**
- Tasks yang akan dijalankan (jika waktunya sudah tiba)
- Tasks yang di-skip (jika waktunya belum tiba)
- Hasil execution

---

## ✅ Cara 2: Check File Kernel.php Langsung

### **Lihat Scheduled Tasks di Code**

```bash
cd /home/ymsuperadmin/public_html
grep "\$schedule->command" app/Console/Kernel.php
```

**Atau dengan lebih detail:**
```bash
# Tampilkan dengan context (3 baris sebelum dan sesudah)
grep -A 3 "\$schedule->command" app/Console/Kernel.php
```

**Atau buka file langsung:**
```bash
cat app/Console/Kernel.php
# atau
nano app/Console/Kernel.php
# atau
vi app/Console/Kernel.php
```

---

## ✅ Cara 3: Via Script Helper

Gunakan script yang sudah dibuat:
```bash
bash check-scheduler.sh
```

Script ini akan:
- ✅ Check Kernel.php ada atau tidak
- ✅ Count berapa banyak scheduled tasks
- ✅ List semua scheduled tasks via `schedule:list`
- ✅ Test run scheduler
- ✅ Check cron job untuk schedule:run
- ✅ Check schedule log

---

## ✅ Cara 4: Check via Web (Jika Ada Route)

Jika ada route untuk check scheduler (biasanya untuk admin panel), bisa akses via browser.

---

## 📋 Scheduled Tasks yang Ada di Kernel.php

Berdasarkan `app/Console/Kernel.php`, berikut adalah semua scheduled tasks:

### **Attendance (3 tasks)**
1. `attendance:process-holiday` - Daily 06:00
2. `attendance:process-holiday` - Daily 23:00
3. `attendance:cleanup-logs` - Weekly (Sunday 02:00)

### **Extra Off (2 tasks)**
4. `extra-off:detect` - Daily 07:00
5. `extra-off:detect` - Daily 23:30

### **Employee (1 task)**
6. `employee-movements:execute` - Daily 08:00

### **Leave (2 tasks)**
7. `leave:monthly-credit` - Monthly (1st, 00:00)
8. `leave:burn-previous-year` - Yearly (March 1st, 00:00)

### **Member Management (3 tasks)**
9. `members:update-tiers` - Monthly (1st, 00:00)
10. `points:expire` - Daily 00:00
11. `vouchers:distribute-birthday` - Daily 01:00

### **Notifications (7 tasks)**
12. `member:notify-incomplete-profile` - Hourly
13. `member:notify-incomplete-challenge` - Hourly
14. `member:notify-inactive` - Daily 10:00
15. `member:notify-long-inactive` - Daily 11:00
16. `member:notify-expiring-points` - Daily 09:00
17. `member:notify-monthly-inactive` - Daily 10:30
18. `member:notify-expiring-vouchers` - Daily 09:30

### **Cleanup (1 task)**
19. `device-tokens:cleanup` - Daily 02:00

**Total: 19 scheduled tasks**

---

## 🔍 Verifikasi Scheduler Berjalan

### **1. Check Cron Job**

```bash
# Check apakah cron job schedule:run ada
crontab -l | grep schedule:run
```

**Harusnya ada:**
```
* * * * * cd /home/ymsuperadmin/public_html && /usr/bin/php artisan schedule:run >> /home/ymsuperadmin/public_html/storage/logs/schedule.log 2>&1
```

### **2. Check Schedule Log**

```bash
# Monitor log real-time
tail -f /home/ymsuperadmin/public_html/storage/logs/schedule.log
```

**Harusnya ada entry baru setiap menit.**

### **3. Check Process**

```bash
# Check apakah schedule:run berjalan
ps aux | grep schedule:run | grep -v grep
```

**Harusnya muncul process saat scheduler berjalan (hanya beberapa detik).**

### **4. Test Manual**

```bash
cd /home/ymsuperadmin/public_html
php artisan schedule:run -v
```

**Harusnya menampilkan tasks yang akan dijalankan atau di-skip.**

---

## 🐛 Troubleshooting

### Problem: `schedule:list` tidak menampilkan tasks

**Solusi:**
1. Check Kernel.php ada: `ls -la app/Console/Kernel.php`
2. Check syntax error: `php artisan` (harusnya tidak ada error)
3. Check cache: `php artisan config:clear && php artisan cache:clear`

### Problem: Scheduler tidak jalan

**Solusi:**
1. Check cron job ada: `crontab -l | grep schedule:run`
2. Check cron service: `systemctl status crond` atau `systemctl status cron`
3. Check log: `tail -20 storage/logs/schedule.log`
4. Test manual: `php artisan schedule:run`

### Problem: Tasks tidak dieksekusi

**Solusi:**
1. Check waktu server: `date`
2. Check timezone di `.env`: `APP_TIMEZONE`
3. Check `schedule:list` untuk melihat next due time
4. Test manual task: `php artisan [task-name]`

---

## 📊 Monitoring Commands

```bash
# List semua scheduled tasks
php artisan schedule:list

# Test run dengan verbose
php artisan schedule:run -v

# Monitor log real-time
tail -f storage/logs/schedule.log

# Check cron job
crontab -l | grep schedule:run

# Check process
watch -n 1 'ps aux | grep schedule:run | grep -v grep'

# Count scheduled tasks di Kernel.php
grep -c "\$schedule->command" app/Console/Kernel.php
```

---

## ✅ Checklist Verifikasi

- [ ] `php artisan schedule:list` menampilkan semua tasks
- [ ] `php artisan schedule:run -v` berjalan tanpa error
- [ ] Cron job `schedule:run` ada di crontab
- [ ] Log file `storage/logs/schedule.log` update setiap menit
- [ ] Scheduled tasks berjalan sesuai jadwal (check log masing-masing task)
- [ ] Tidak ada duplicate execution (check log untuk memastikan)

---

## 📝 Catatan

1. **Scheduler di Laravel** dikonfigurasi di `app/Console/Kernel.php` method `schedule()`
2. **Scheduler tidak jalan otomatis** - perlu cron job yang menjalankan `schedule:run` setiap menit
3. **Scheduler check waktu** - hanya menjalankan tasks yang waktunya sudah tiba
4. **Log file** - semua output scheduler tersimpan di `storage/logs/schedule.log`
5. **Timezone penting** - pastikan timezone server dan `.env` sama

---

## 🔗 File Terkait

- `app/Console/Kernel.php` - Konfigurasi scheduler
- `storage/logs/schedule.log` - Log scheduler
- `storage/logs/[task-name].log` - Log masing-masing task
- `check-scheduler.sh` - Script helper untuk check scheduler

