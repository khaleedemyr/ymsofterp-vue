# ✅ Langkah Selanjutnya Setelah Supervisor Running

## 🎉 **Status Saat Ini**

✅ Queue worker sudah running via supervisor:
- `ymsofterp-queue-worker_00` - RUNNING
- `ymsofterp-queue-worker_01` - RUNNING

---

## 📋 **LANGKAH SELANJUTNYA**

### **LANGKAH 1: Hapus Queue Worker dari Cron (PENTING!)**

Queue worker sekarang sudah jalan via supervisor, jadi **HAPUS dari cron** untuk mencegah duplicate.

1. **Edit cron:**
   ```bash
   crontab -e
   ```

2. **Cari dan hapus baris ini:**
   ```
   * * * * * cd /home/ymsuperadmin/public_html && php artisan queue:work --queue-notifications --tries=3 --timeout=380 --sleep=3 --max-jobs=1000 --max-time=3680 --stop-when-empty >> storage/logs/queue-worker.log 2>&1
   ```

3. **Save dan exit** (di nano: `Ctrl + O`, `Enter`, `Ctrl + X`)

4. **Verifikasi sudah dihapus:**
   ```bash
   crontab -l | grep queue:work
   ```
   
   **Harusnya tidak ada output** (sudah dihapus)

---

### **LANGKAH 2: Pastikan schedule:run Ada di Cron**

Laravel scheduler harus ada di cron untuk menjalankan semua scheduled tasks.

1. **Check apakah sudah ada:**
   ```bash
   crontab -l | grep schedule:run
   ```

2. **Jika TIDAK ADA, tambahkan:**
   ```bash
   crontab -e
   ```
   
   Tambahkan baris ini:
   ```
   * * * * * cd /home/ymsuperadmin/public_html && php artisan schedule:run >> /dev/null 2>&1
   ```

3. **Verifikasi:**
   ```bash
   crontab -l | grep schedule:run
   ```
   
   **Harusnya muncul** baris schedule:run

---

### **LANGKAH 3: Hapus Semua Duplicate Cron Jobs**

Semua cron jobs berikut **sudah ada di Laravel scheduler**, jadi harus dihapus:

**Hapus cron jobs berikut:**

1. ❌ `attendance:process-holiday` (23:00)
2. ❌ `attendance:process-holiday` (06:00)
3. ❌ `extra-off:detect` (07:00)
4. ❌ `extra-off:detect` (23:30)
5. ❌ `employee-movements:execute` (05:00)
6. ❌ `leave:monthly-credit` (tanggal 1)
7. ❌ `leave:burn-previous-year` (1 Maret)
8. ❌ `vouchers:distribute-birthday` (01:00)
9. ❌ `attendance:cleanup-logs` (Minggu 02:00)
10. ❌ `members:update-tiers` (tanggal 1)
11. ❌ `points:expire` (00:00)
12. ❌ `member:notify-incomplete-profile` (07:00)
13. ❌ `member:notify-incomplete-challenge` (08:00)
14. ❌ `member:notify-inactive` (10:00)
15. ❌ `member:notify-long-inactive` (tanggal 11)
16. ❌ `member:notify-expiring-points` (tanggal 9)
17. ❌ `member:notify-monthly-inactive` (10:30)
18. ❌ `member:notify-expiring-vouchers` (09:30)
19. ❌ `device-tokens:cleanup` (02:00)

**Cara hapus:**
1. Edit cron: `crontab -e`
2. Hapus satu per satu baris cron jobs di atas
3. Save dan exit

**Atau hapus semua sekaligus:**
```bash
# Backup dulu
crontab -l > /root/crontab_backup_$(date +%Y%m%d_%H%M%S).txt

# Edit dan hapus manual
crontab -e
```

---

### **LANGKAH 4: Verifikasi Cron Jobs yang Tersisa**

Setelah hapus semua duplicate, **hanya harus ada 1 cron job:**

```bash
crontab -l
```

**Expected output:**
```
* * * * * cd /home/ymsuperadmin/public_html && php artisan schedule:run >> /dev/null 2>&1
```

**Total: 1 cron job** (bukan 20!)

---

### **LANGKAH 5: Verifikasi Queue Workers**

1. **Check queue workers via supervisor:**
   ```bash
   supervisorctl status
   ```
   
   **Harusnya:**
   ```
   ymsofterp-queue-worker:ymsofterp-queue-worker_00   RUNNING   pid 15942, uptime 0:05:00
   ymsofterp-queue-worker:ymsofterp-queue-worker_01   RUNNING   pid 15943, uptime 0:05:00
   ```

2. **Check total queue workers (harusnya hanya 2):**
   ```bash
   ps aux | grep 'queue:work' | grep -v grep | wc -l
   ```
   
   **Harusnya: 2** (bukan 60+!)

3. **Check apakah masih ada queue worker dari cron:**
   ```bash
   ps aux | grep 'queue:work' | grep -v grep
   ```
   
   **Harusnya hanya 2 proses** dari supervisor, tidak ada yang dari cron.

---

### **LANGKAH 6: Monitor CPU Usage**

1. **Check CPU usage:**
   ```bash
   top
   ```
   
   **Expected:** CPU usage turun dari 100% ke 30-50%

2. **Check load average:**
   ```bash
   uptime
   ```
   
   **Expected:** Load average < 8.0 (untuk 8 vCPU)

3. **Monitor selama 1-2 jam** untuk memastikan stabil.

---

### **LANGKAH 7: Test Scheduled Tasks**

1. **List scheduled tasks:**
   ```bash
   cd /home/ymsuperadmin/public_html
   php artisan schedule:list
   ```
   
   **Harusnya muncul semua tasks** dari `app/Console/Kernel.php`

2. **Test manual (optional):**
   ```bash
   php artisan schedule:run
   ```
   
   **Harusnya tidak ada error**

3. **Check log:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

## 📊 **CHECKLIST FINAL**

- [ ] Queue worker dihapus dari cron
- [ ] `schedule:run` ada di cron
- [ ] Semua duplicate cron jobs dihapus (19 jobs)
- [ ] Hanya 1 cron job tersisa (schedule:run)
- [ ] Queue workers hanya 2 (via supervisor)
- [ ] CPU usage turun ke 30-50%
- [ ] Scheduled tasks masih berjalan
- [ ] Monitor selama 24 jam

---

## 🎯 **EXPECTED RESULTS**

Setelah semua langkah:

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| **Cron Jobs** | 20 jobs | 1 job (schedule:run) |
| **Queue Workers** | 60+ (setiap menit) | 2 (supervisor) |
| **CPU Usage** | 100% | 30-50% |
| **Load Average** | > 8.0 | < 8.0 |
| **Task Execution** | Duplicate (2x) | Single (1x) |

---

## ⚠️ **CATATAN PENTING**

1. **Jangan lupa hapus queue worker dari cron** - ini penting!
2. **Monitor selama 24 jam** setelah perubahan
3. **Jika ada masalah, check log:**
   - Queue worker: `/home/ymsuperadmin/public_html/storage/logs/queue-worker.log`
   - Laravel: `/home/ymsuperadmin/public_html/storage/logs/laravel.log`
   - Supervisor: `journalctl -u supervisord -n 50`

---

## 🔍 **VERIFIKASI CEPAT**

Jalankan command ini untuk check semua sekaligus:

```bash
echo "=== 1. Cron Jobs ==="
crontab -l
echo ""
echo "=== 2. Queue Workers (harusnya 2) ==="
ps aux | grep 'queue:work' | grep -v grep | wc -l
echo ""
echo "=== 3. Supervisor Status ==="
supervisorctl status
echo ""
echo "=== 4. CPU Usage ==="
top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print "CPU Idle: " $1 "%"}'
echo ""
echo "=== 5. Load Average ==="
uptime
```

---

## 📚 **DOKUMENTASI TERKAIT**

- `MIGRASI_CRON_KE_SCHEDULER.md` - Panduan lengkap migrasi
- `CRON_JOBS_TO_DELETE.md` - Daftar cron jobs yang harus dihapus
- `SETUP_SUPERVISOR_MANUAL.md` - Setup supervisor (sudah selesai ✅)

---

**Lakukan Langkah 1-7 secara berurutan!** ✅

Yang paling penting: **Langkah 1** - Hapus queue worker dari cron sekarang!

