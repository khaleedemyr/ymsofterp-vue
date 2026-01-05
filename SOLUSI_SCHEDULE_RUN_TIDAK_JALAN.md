# 🔧 Solusi: Schedule:Run Tidak Jalan Terus

## Masalah
`schedule:run` tidak berjalan terus, menyebabkan scheduled tasks tidak dieksekusi.

---

## ✅ SOLUSI LENGKAP

### **LANGKAH 1: Test Schedule:Run Manual**

Jalankan di server:
```bash
cd /home/ymsuperadmin/public_html
php artisan schedule:run
```

**Jika error:**
- Check error message
- Pastikan path PHP benar
- Pastikan Laravel .env file ada

**Jika berhasil:**
- Lanjut ke Langkah 2

---

### **LANGKAH 2: Pastikan Cron Job Ada**

#### A. Via cPanel (RECOMMENDED untuk shared hosting)

1. Login ke cPanel
2. Buka **Cron Jobs**
3. Pastikan ada entry berikut:

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
   - Gunakan **full path** untuk PHP (`/usr/bin/php` atau cek dengan `which php`)
   - Gunakan **full path** untuk aplikasi
   - Redirect output ke log file agar bisa di-monitor

4. Klik **Add New Cron Job**

#### B. Via SSH (VPS/Dedicated Server)

1. Edit crontab:
   ```bash
   crontab -e
   ```

2. Tambahkan baris berikut:
   ```bash
   * * * * * cd /home/ymsuperadmin/public_html && /usr/bin/php artisan schedule:run >> /home/ymsuperadmin/public_html/storage/logs/schedule.log 2>&1
   ```

3. Simpan dan keluar (di nano: Ctrl+X, lalu Y, lalu Enter)

4. Verifikasi:
   ```bash
   crontab -l | grep schedule:run
   ```

---

### **LANGKAH 3: Pastikan Cron Service Berjalan**

Jalankan di server:
```bash
# CentOS/RHEL
systemctl status crond

# Ubuntu/Debian
systemctl status cron
```

**Jika tidak berjalan:**
```bash
# CentOS/RHEL
sudo systemctl start crond
sudo systemctl enable crond

# Ubuntu/Debian
sudo systemctl start cron
sudo systemctl enable cron
```

---

### **LANGKAH 4: Test Cron Job**

#### Test 1: Check apakah cron job ada
```bash
crontab -l | grep schedule:run
```

#### Test 2: Simulasi cron execution
```bash
cd /home/ymsuperadmin/public_html
/usr/bin/php artisan schedule:run
```

#### Test 3: Monitor log file
```bash
tail -f /home/ymsuperadmin/public_html/storage/logs/schedule.log
```

Tunggu 1-2 menit, seharusnya ada entry baru setiap menit.

#### Test 4: Check process
```bash
# Setiap 10 detik, check apakah schedule:run berjalan
watch -n 10 'ps aux | grep schedule:run | grep -v grep'
```

---

### **LANGKAH 5: Troubleshooting**

#### Problem 1: Cron job ada tapi tidak jalan

**Check:**
```bash
# Check cron log (biasanya di /var/log/cron atau /var/log/syslog)
tail -50 /var/log/cron
# atau
grep CRON /var/log/syslog | tail -20
```

**Solusi:**
- Pastikan path PHP benar (gunakan `which php` atau `/usr/bin/php`)
- Pastikan path aplikasi benar
- Check permission file `artisan` (harus executable)
- Check permission folder `storage/logs` (harus writable)

#### Problem 2: Schedule:run jalan tapi tasks tidak dieksekusi

**Check:**
```bash
# List scheduled tasks
php artisan schedule:list

# Test run scheduled task manual
php artisan schedule:run -v
```

**Solusi:**
- Pastikan waktu server benar (check dengan `date`)
- Pastikan timezone di `.env` benar
- Check apakah task sudah waktunya (lihat `schedule:list`)

#### Problem 3: Permission denied

**Solusi:**
```bash
# Set permission untuk storage
chmod -R 775 /home/ymsuperadmin/public_html/storage
chown -R ymsuperadmin:ymsuperadmin /home/ymsuperadmin/public_html/storage

# Set permission untuk artisan
chmod +x /home/ymsuperadmin/public_html/artisan
```

#### Problem 4: PHP path salah

**Cari PHP path:**
```bash
which php
# atau
whereis php
# atau
find /usr -name php 2>/dev/null | grep bin
```

**Update cron job dengan path yang benar.**

---

## 🔍 MONITORING

### Script Helper yang Tersedia:

1. **test-schedule-run.sh** - Test dan debug schedule:run
   ```bash
   bash test-schedule-run.sh
   ```

2. **fix-schedule-run.sh** - Auto-fix cron job
   ```bash
   bash fix-schedule-run.sh
   ```

3. **monitor-schedule.sh** - Monitor schedule:run
   ```bash
   bash monitor-schedule.sh 5  # Monitor 5 menit
   ```

### Manual Monitoring:

```bash
# Monitor log real-time
tail -f /home/ymsuperadmin/public_html/storage/logs/schedule.log

# Check apakah schedule:run berjalan
watch -n 1 'ps aux | grep schedule:run | grep -v grep'

# Check cron execution
tail -f /var/log/cron | grep schedule
```

---

## ✅ VERIFIKASI

Setelah setup, verifikasi dengan:

1. **Check cron job ada:**
   ```bash
   crontab -l | grep schedule:run
   ```
   Harusnya muncul entry cron job.

2. **Check log file update:**
   ```bash
   tail -20 /home/ymsuperadmin/public_html/storage/logs/schedule.log
   ```
   Harusnya ada entry baru setiap menit.

3. **Check scheduled tasks berjalan:**
   - Tunggu sampai waktu scheduled task (misal jam 06:00 untuk `attendance:process-holiday`)
   - Check log file task tersebut:
     ```bash
     tail -20 /home/ymsuperadmin/public_html/storage/logs/holiday-attendance.log
     ```

4. **Test manual:**
   ```bash
   cd /home/ymsuperadmin/public_html
   php artisan schedule:run -v
   ```
   Harusnya menampilkan tasks yang akan dijalankan.

---

## 📋 CHECKLIST

- [ ] Test `php artisan schedule:run` manual berhasil
- [ ] Cron job `schedule:run` sudah ditambahkan di cPanel/crontab
- [ ] Cron service berjalan (`systemctl status crond/cron`)
- [ ] Log file `storage/logs/schedule.log` dibuat dan update setiap menit
- [ ] Permission folder `storage/logs` benar (writable)
- [ ] PHP path di cron job benar (full path)
- [ ] Aplikasi path di cron job benar (full path)
- [ ] Test scheduled task berjalan sesuai jadwal

---

## ⚠️ CATATAN PENTING

1. **Gunakan full path** di cron job (PHP dan aplikasi)
2. **Redirect output ke log file** agar bisa di-monitor
3. **Test manual dulu** sebelum mengandalkan cron
4. **Monitor log file** selama 24 jam pertama
5. **Jangan hapus cron job** yang sudah jalan dengan baik

---

## 🔄 ALTERNATIF: Jika Cron Tidak Bisa Dipakai

Jika cron tidak bisa dipakai (misal shared hosting yang tidak support), gunakan **external cron service**:

1. **EasyCron** (https://www.easycron.com/)
   - Setup HTTP request ke endpoint khusus
   - Buat route di Laravel yang menjalankan `schedule:run`

2. **Cron-job.org** (https://cron-job.org/)
   - Setup HTTP request setiap menit

3. **Laravel Scheduler via Web** (tidak recommended untuk production)
   - Setup route yang menjalankan `schedule:run`
   - Panggil via external cron service

---

## 📞 Bantuan Lebih Lanjut

Jika masih ada masalah:
1. Check log: `storage/logs/schedule.log`
2. Check log: `storage/logs/laravel.log`
3. Check cron log: `/var/log/cron` atau `/var/log/syslog`
4. Test manual: `php artisan schedule:run -v`
5. Check permission: `ls -la storage/logs`

