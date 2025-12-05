# Panduan Setup Cron Job di cPanel
## Sistem Public Holiday & Extra Off Detection

---

## 📋 **Daftar Isi**
1. [Persiapan](#persiapan)
2. [Setup Cron Job Public Holiday](#setup-cron-job-public-holiday)
3. [Setup Cron Job Extra Off](#setup-cron-job-extra-off)
4. [Setup Cron Job Cleanup](#setup-cron-job-cleanup)
5. [Testing & Monitoring](#testing--monitoring)
6. [Troubleshooting](#troubleshooting)
7. [Checklist](#checklist)

---

## 🔧 **Persiapan**

### **1. Informasi yang Diperlukan:**
- Username cPanel hosting
- Password cPanel hosting
- Path project Laravel (biasanya `/home/username/public_html`)
- PHP version yang digunakan

### **2. Akses cPanel:**
- Buka browser dan login ke cPanel
- URL biasanya: `https://yourdomain.com/cpanel` atau `https://cpanel.yourdomain.com`

---

## 🎯 **Setup Cron Job Public Holiday**

### **Langkah 1: Akses Cron Jobs**
1. Login ke cPanel
2. Cari menu **"Cron Jobs"** di bagian **"Advanced"**
3. Klik **"Cron Jobs"**

### **Langkah 2: Tambah Cron Job Holiday Attendance (Pagi)**
1. Klik **"Add New Cron Job"**
2. Isi form dengan data berikut:

```
Minute: 0
Hour: 6
Day: *
Month: *
Weekday: *
Command: cd /home/username/public_html && php artisan attendance:process-holiday >> storage/logs/holiday-attendance.log 2>&1
```

3. Klik **"Add Cron Job"**

### **Langkah 3: Tambah Cron Job Holiday Attendance (Malam)**
1. Klik **"Add New Cron Job"** lagi
2. Isi form dengan data berikut:

```
Minute: 0
Hour: 23
Day: *
Month: *
Weekday: *
Command: cd /home/username/public_html && php artisan attendance:process-holiday >> storage/logs/holiday-attendance.log 2>&1
```

3. Klik **"Add Cron Job"**

---

## 🎯 **Setup Cron Job Extra Off**

### **Langkah 1: Tambah Cron Job Extra Off (Pagi)**
1. Klik **"Add New Cron Job"**
2. Isi form dengan data berikut:

```
Minute: 0
Hour: 7
Day: *
Month: *
Weekday: *
Command: cd /home/username/public_html && php artisan extra-off:detect >> storage/logs/extra-off-detection.log 2>&1
```

3. Klik **"Add Cron Job"**

### **Langkah 2: Tambah Cron Job Extra Off (Malam)**
1. Klik **"Add New Cron Job"** lagi
2. Isi form dengan data berikut:

```
Minute: 30
Hour: 23
Day: *
Month: *
Weekday: *
Command: cd /home/username/public_html && php artisan extra-off:detect >> storage/logs/extra-off-detection.log 2>&1
```

3. Klik **"Add Cron Job"**

---

## 🎯 **Setup Cron Job Cleanup (Opsional)**

### **Langkah 1: Tambah Cron Job Cleanup Logs**
1. Klik **"Add New Cron Job"**
2. Isi form dengan data berikut:

```
Minute: 0
Hour: 2
Day: *
Month: *
Weekday: 0
Command: cd /home/username/public_html && php artisan attendance:cleanup-logs >> storage/logs/cleanup.log 2>&1
```

3. Klik **"Add Cron Job"**

---

## 📝 **Template Command yang Siap Copy-Paste**

### **Public Holiday Commands:**
```bash
# Holiday Attendance - Pagi (6:00 AM)
cd /home/username/public_html && php artisan attendance:process-holiday >> storage/logs/holiday-attendance.log 2>&1

# Holiday Attendance - Malam (11:00 PM)
cd /home/username/public_html && php artisan attendance:process-holiday >> storage/logs/holiday-attendance.log 2>&1
```

### **Extra Off Commands:**
```bash
# Extra Off Detection - Pagi (7:00 AM)
cd /home/username/public_html && php artisan extra-off:detect >> storage/logs/extra-off-detection.log 2>&1

# Extra Off Detection - Malam (11:30 PM)
cd /home/username/public_html && php artisan extra-off:detect >> storage/logs/extra-off-detection.log 2>&1
```

### **Cleanup Command:**
```bash
# Cleanup Logs - Minggu (2:00 AM)
cd /home/username/public_html && php artisan attendance:cleanup-logs >> storage/logs/cleanup.log 2>&1
```

---

## ⚠️ **Catatan Penting**

### **1. Ganti Path Project:**
- Ganti `username` dengan username hosting Anda
- Ganti `public_html` jika project di folder lain
- Contoh: `/home/myusername/domain.com`

### **2. PHP Path:**
- Jika error "php: command not found", ganti dengan path lengkap
- Contoh: `/usr/bin/php` atau `/usr/local/bin/php`
- Command menjadi: `cd /home/username/public_html && /usr/bin/php artisan ...`

### **3. Permission:**
- Pastikan folder `storage/logs` bisa ditulis
- Set permission: `chmod 755 storage/logs`

---

## 🧪 **Testing & Monitoring**

### **1. Test Manual di Terminal cPanel:**
1. Buka **"Terminal"** di cPanel
2. Jalankan command berikut:

```bash
cd /home/username/public_html
php artisan attendance:process-holiday
php artisan extra-off:detect
```

### **2. Cek Log Files:**
```bash
# Cek log holiday attendance
tail -f storage/logs/holiday-attendance.log

# Cek log extra off detection
tail -f storage/logs/extra-off-detection.log

# Cek log cleanup
tail -f storage/logs/cleanup.log
```

### **3. Cek Database:**
- Buka phpMyAdmin atau database manager
- Cek table `holiday_attendance_compensations`
- Cek table `extra_off_transactions`
- Pastikan ada data baru setelah cron job jalan

### **4. Cek Status Cron Job:**
- Kembali ke halaman **"Cron Jobs"**
- Pastikan semua cron job status **"Active"**
- Klik **"View Logs"** untuk melihat log cron job

---

## 🚨 **Troubleshooting**

### **Error: "Command not found"**
**Solusi:**
- Ganti `php` dengan path lengkap PHP
- Cek path PHP dengan: `which php`
- Contoh: `/usr/bin/php` atau `/usr/local/bin/php`

### **Error: "Permission denied"**
**Solusi:**
- Set permission folder storage: `chmod -R 755 storage`
- Set permission file artisan: `chmod 755 artisan`
- Pastikan user web server bisa akses folder

### **Error: "No such file or directory"**
**Solusi:**
- Pastikan path project benar
- Cek apakah file `artisan` ada di folder project
- Gunakan path absolut yang benar

### **Cron Job tidak jalan:**
**Solusi:**
- Cek timezone cPanel
- Pastikan format waktu benar (Minute Hour Day Month Weekday)
- Test command manual dulu
- Cek log cron job di cPanel

### **Log file tidak terbuat:**
**Solusi:**
- Pastikan folder `storage/logs` ada
- Set permission: `chmod 755 storage/logs`
- Cek apakah ada error di command

---

## 📊 **Monitoring Harian**

### **1. Cek Log Files:**
- `storage/logs/holiday-attendance.log`
- `storage/logs/extra-off-detection.log`
- `storage/logs/cleanup.log`

### **2. Cek Database:**
- Monitor table `holiday_attendance_compensations`
- Monitor table `extra_off_transactions`
- Pastikan tidak ada error

### **3. Cek Status Cron Job:**
- Buka cPanel → Cron Jobs
- Pastikan semua status "Active"
- Cek log cron job jika ada error

---

## ✅ **Checklist Setup**

### **Persiapan:**
- [ ] Login ke cPanel
- [ ] Catat username hosting
- [ ] Catat path project Laravel
- [ ] Cek PHP version

### **Setup Cron Job:**
- [ ] Buka menu Cron Jobs
- [ ] Tambah cron job holiday attendance pagi (6:00 AM)
- [ ] Tambah cron job holiday attendance malam (11:00 PM)
- [ ] Tambah cron job extra off pagi (7:00 AM)
- [ ] Tambah cron job extra off malam (11:30 PM)
- [ ] Tambah cron job cleanup (opsional)

### **Testing:**
- [ ] Test command manual di terminal
- [ ] Cek log files
- [ ] Cek database
- [ ] Verifikasi cron job aktif

### **Monitoring:**
- [ ] Cek log files harian
- [ ] Monitor database
- [ ] Cek status cron job
- [ ] Troubleshoot jika ada error

---

## 📞 **Support**

Jika ada masalah dengan setup cron job:

1. **Cek Log Files** terlebih dahulu
2. **Test Command Manual** di terminal
3. **Cek Permission** folder dan file
4. **Hubungi Support Hosting** jika masalah server

---

## 🎯 **Jadwal Cron Job**

| Waktu | Command | Deskripsi |
|-------|---------|-----------|
| 06:00 | `attendance:process-holiday` | Deteksi holiday attendance pagi |
| 07:00 | `extra-off:detect` | Deteksi extra off pagi |
| 23:00 | `attendance:process-holiday` | Deteksi holiday attendance malam |
| 23:30 | `extra-off:detect` | Deteksi extra off malam |
| 02:00 (Minggu) | `attendance:cleanup-logs` | Cleanup logs mingguan |

---

**Setup selesai!** Sistem akan otomatis mendeteksi holiday attendance dan extra off setiap hari. 🎉
