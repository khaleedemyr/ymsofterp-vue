# Panduan Setup Cron Job di cPanel
## Sistem Employee Movement Execution

---

## 📋 **Daftar Isi**
1. [Persiapan](#persiapan)
2. [Setup Cron Job Employee Movement](#setup-cron-job-employee-movement)
3. [Testing & Monitoring](#testing--monitoring)
4. [Troubleshooting](#troubleshooting)
5. [Checklist](#checklist)

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

### **3. Cek Project Laravel:**
- Pastikan file `artisan` ada di folder project
- Pastikan command `employee-movements:execute` sudah terdaftar
- Pastikan folder `storage/logs` bisa ditulis

---

## 🎯 **Setup Cron Job Employee Movement**

### **Langkah 1: Akses Cron Jobs**
1. Login ke cPanel
2. Cari menu **"Cron Jobs"** di bagian **"Advanced"**
3. Klik **"Cron Jobs"**

### **Langkah 2: Tambah Cron Job Employee Movement (Option 1 - Recommended)**
1. Klik **"Add New Cron Job"**
2. Isi form dengan data berikut:

```
Minute: *
Hour: *
Day: *
Month: *
Weekday: *
Command: cd /home/username/public_html && php artisan schedule:run >> storage/logs/cron.log 2>&1
```

3. Klik **"Add Cron Job"**

**Penjelasan:**
- Menggunakan Laravel Scheduler yang sudah dikonfigurasi
- Akan menjalankan `employee-movements:execute` setiap hari jam 08:00
- Log disimpan di `storage/logs/employee-movements-execution.log`

### **Langkah 3: Tambah Cron Job Employee Movement (Option 2 - Direct)**
1. Klik **"Add New Cron Job"**
2. Isi form dengan data berikut:

```
Minute: 0
Hour: 8
Day: *
Month: *
Weekday: *
Command: cd /home/ymsuperadmin/public_html && php artisan employee-movements:execute >> storage/logs/employee-movements-execution.log 2>&1
```

3. Klik **"Add Cron Job"**

**Penjelasan:**
- Langsung menjalankan command employee movement
- Setiap hari jam 08:00 WIB
- Log disimpan di `storage/logs/employee-movements-execution.log`

---

## 📝 **Template Command yang Siap Copy-Paste**

### **Option 1 - Laravel Scheduler (Recommended):**
```bash
# Employee Movement Execution - Laravel Scheduler
cd /home/username/public_html && php artisan schedule:run >> storage/logs/cron.log 2>&1
```

### **Option 2 - Direct Command:**
```bash
# Employee Movement Execution - Direct
cd /home/username/public_html && php artisan employee-movements:execute >> storage/logs/employee-movements-execution.log 2>&1
```

### **Test Command:**
```bash
# Test Cron Job
cd /home/username/public_html && php artisan employee-movements:execute
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

### **4. Database Connection:**
- Pastikan database connection berfungsi
- Cek file `.env` untuk konfigurasi database
- Pastikan tabel `employee_movements` dan `users` ada

---

## 🧪 **Testing & Monitoring**

### **1. Test Manual di Terminal cPanel:**
1. Buka **"Terminal"** di cPanel
2. Jalankan command berikut:

```bash
cd /home/username/public_html
php artisan employee-movements:execute
```

**Expected Output:**
```
Found 1 employee movements to execute today.
✓ Executed movement for [Employee Name]
Execution completed. Success: 1, Errors: 0
```

### **2. Cek Log Files:**
```bash
# Cek log employee movement execution
tail -f storage/logs/employee-movements-execution.log

# Cek log cron job umum
tail -f storage/logs/cron.log

# Cek log Laravel umum
tail -f storage/logs/laravel.log
```

### **3. Cek Database:**
- Buka phpMyAdmin atau database manager
- Cek table `employee_movements` - status berubah dari `approved` ke `executed`
- Cek table `users` - field `id_outlet` berubah sesuai movement

### **4. Cek Status Cron Job:**
- Kembali ke halaman **"Cron Jobs"**
- Pastikan cron job status **"Active"**
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

### **Error: "Database connection failed"**
**Solusi:**
- Cek file `.env` untuk konfigurasi database
- Pastikan database server accessible
- Cek username, password, dan host database

### **Error: "Column not found"**
**Solusi:**
- Pastikan tabel `employee_movements` sudah ada
- Cek apakah kolom `status` enum sudah lengkap
- Jalankan migration jika perlu

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

### **Employee movement tidak dieksekusi:**
**Solusi:**
- Cek apakah ada movement dengan status `approved`
- Cek apakah `employment_effective_date` sudah tiba
- Cek log file untuk error details
- Test manual command

---

## 📊 **Monitoring Harian**

### **1. Cek Log Files:**
- `storage/logs/employee-movements-execution.log`
- `storage/logs/cron.log`
- `storage/logs/laravel.log`

### **2. Cek Database:**
- Monitor table `employee_movements`
- Monitor table `users`
- Pastikan tidak ada error

### **3. Cek Status Cron Job:**
- Buka cPanel → Cron Jobs
- Pastikan status "Active"
- Cek log cron job jika ada error

### **4. Cek Employee Movements:**
- Buka aplikasi web
- Cek halaman Employee Movement
- Pastikan status berubah dari `approved` ke `executed`

---

## ✅ **Checklist Setup**

### **Persiapan:**
- [ ] Login ke cPanel
- [ ] Catat username hosting
- [ ] Catat path project Laravel
- [ ] Cek PHP version
- [ ] Cek file `artisan` ada
- [ ] Cek command `employee-movements:execute` terdaftar

### **Setup Cron Job:**
- [ ] Buka menu Cron Jobs
- [ ] Pilih Option 1 (Laravel Scheduler) atau Option 2 (Direct)
- [ ] Tambah cron job dengan command yang sesuai
- [ ] Pastikan status "Active"

### **Testing:**
- [ ] Test command manual di terminal
- [ ] Cek log files
- [ ] Cek database
- [ ] Verifikasi cron job aktif
- [ ] Test dengan employee movement yang sudah approved

### **Monitoring:**
- [ ] Cek log files harian
- [ ] Monitor database
- [ ] Cek status cron job
- [ ] Cek employee movements di aplikasi
- [ ] Troubleshoot jika ada error

---

## 🎯 **Jadwal Cron Job**

| Waktu | Command | Deskripsi |
|-------|---------|-----------|
| 08:00 | `employee-movements:execute` | Eksekusi employee movements yang sudah approved dan effective date-nya hari ini |

---

## 📞 **Support**

Jika ada masalah dengan setup cron job:

1. **Cek Log Files** terlebih dahulu
2. **Test Command Manual** di terminal
3. **Cek Permission** folder dan file
4. **Cek Database Connection**
5. **Hubungi Support Hosting** jika masalah server

---

## 🔍 **Verifikasi Setup**

### **Test 1: Manual Command**
```bash
cd /home/username/public_html
php artisan employee-movements:execute
```

### **Test 2: Cek Log**
```bash
tail -f storage/logs/employee-movements-execution.log
```

### **Test 3: Cek Database**
- Buka phpMyAdmin
- Cek table `employee_movements`
- Cek table `users`

### **Test 4: Cek Aplikasi**
- Buka aplikasi web
- Cek halaman Employee Movement
- Pastikan status berubah

---

**Setup selesai!** Sistem akan otomatis mengeksekusi employee movements yang sudah approved setiap hari jam 08:00. 🎉

---

## 📋 **Quick Reference**

### **Command untuk Copy-Paste:**
```bash
# Option 1 (Recommended)
cd /home/username/public_html && php artisan schedule:run >> storage/logs/cron.log 2>&1

# Option 2 (Direct)
cd /home/username/public_html && php artisan employee-movements:execute >> storage/logs/employee-movements-execution.log 2>&1
```

### **Timing di cPanel:**
- **Option 1:** `* * * * *` (setiap menit)
- **Option 2:** `0 8 * * *` (jam 08:00)

### **Log Files:**
- `storage/logs/employee-movements-execution.log`
- `storage/logs/cron.log`
- `storage/logs/laravel.log`
