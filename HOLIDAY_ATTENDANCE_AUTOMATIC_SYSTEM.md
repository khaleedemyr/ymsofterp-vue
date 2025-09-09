# 🎯 Holiday Attendance Automatic System

## 📋 **SISTEM SAAT INI vs SISTEM OTOMATIS**

### **❌ Sistem Manual (Sebelumnya):**
- Admin harus buka halaman `/holiday-attendance`
- Pilih tanggal libur secara manual
- Klik "Process Holiday" untuk setiap tanggal
- Risiko lupa memproses kompensasi

### **✅ Sistem Otomatis (Sekarang):**
- **Otomatis berjalan setiap hari** pada jam 6:00 AM dan 11:00 PM
- **Otomatis deteksi** karyawan yang masuk di hari libur
- **Otomatis beri kompensasi** sesuai level jabatan
- **Log otomatis** untuk tracking dan audit

---

## 🚀 **CARA KERJA SISTEM OTOMATIS**

### **1. Scheduled Jobs (Cron Jobs)**
```bash
# Berjalan otomatis setiap hari:
# - 06:00 AM: Proses holiday attendance untuk hari sebelumnya
# - 11:00 PM: Proses ulang untuk memastikan tidak ada yang terlewat
```

### **2. Alur Kerja Otomatis:**
```
1. ⏰ Cron job trigger (6:00 AM / 11:00 PM)
   ↓
2. 🔍 Cek tanggal kemarin apakah hari libur
   ↓
3. 👥 Cari karyawan yang scan masuk di hari libur
   ↓
4. 🏢 Cek level jabatan karyawan (users → tbl_data_jabatan → tbl_data_level)
   ↓
5. 💰 Beri kompensasi:
   - Jika nilai_public_holiday = 0 → Extra Off Day
   - Jika nilai_public_holiday > 0 → Bonus uang
   ↓
6. 📝 Simpan ke tabel holiday_attendance_compensations
   ↓
7. 📊 Log hasil ke storage/logs/holiday-attendance.log
```

---

## ⚙️ **SETUP SISTEM OTOMATIS**

### **1. Jalankan Setup Script:**
```bash
# Berikan permission execute
chmod +x setup_holiday_attendance_cron.sh

# Jalankan setup
./setup_holiday_attendance_cron.sh
```

### **2. Manual Setup Cron Job:**
```bash
# Edit crontab
crontab -e

# Tambahkan baris ini:
0 6,23 * * * cd /path/to/your/project && php artisan attendance:process-holiday >> /dev/null 2>&1
```

### **3. Verifikasi Setup:**
```bash
# Cek crontab
crontab -l

# Test manual
php artisan attendance:process-holiday 2024-01-15
```

---

## 📊 **MONITORING & LOGS**

### **1. Log File:**
- **Location:** `storage/logs/holiday-attendance.log`
- **Content:** Hasil proses, error, dan statistik
- **Rotation:** Otomatis cleanup setelah 30 hari

### **2. Database Tracking:**
- **Table:** `holiday_attendance_compensations`
- **Fields:** user_id, holiday_date, compensation_type, amount, status
- **Status:** pending, approved, used, cancelled

### **3. Dashboard Monitoring:**
- **URL:** `/holiday-attendance`
- **Features:** Statistik, filter, export, manual process

---

## 🎯 **CONTOH SKENARIO OTOMATIS**

### **Skenario 1: Hari Libur Nasional**
```
Tanggal: 2024-01-01 (Tahun Baru)
Jam 06:00: Cron job berjalan
↓
Sistem cek: Apakah 2024-01-01 hari libur? ✅ Ya
↓
Cari karyawan yang scan masuk:
- Ahmad (Level 1, nilai_public_holiday = 0) → Extra Off Day
- Siti (Level 3, nilai_public_holiday = 50000) → Bonus Rp 50,000
- Budi (Level 2, nilai_public_holiday = 0) → Extra Off Day
↓
Simpan ke database dan log hasil
```

### **Skenario 2: Hari Kerja Normal**
```
Tanggal: 2024-01-02 (Hari Kerja)
Jam 06:00: Cron job berjalan
↓
Sistem cek: Apakah 2024-01-02 hari libur? ❌ Tidak
↓
Skip processing, tidak ada kompensasi
```

---

## 🔧 **COMMANDS YANG TERSEDIA**

### **1. Manual Processing:**
```bash
# Proses tanggal tertentu
php artisan attendance:process-holiday 2024-01-15

# Proses kemarin (default)
php artisan attendance:process-holiday

# Force process (skip confirmation)
php artisan attendance:process-holiday 2024-01-15 --force
```

### **2. Log Management:**
```bash
# Cleanup logs older than 30 days
php artisan attendance:cleanup-logs

# Cleanup logs older than 7 days
php artisan attendance:cleanup-logs --days=7
```

### **3. Monitoring:**
```bash
# Cek log file
tail -f storage/logs/holiday-attendance.log

# Cek crontab
crontab -l
```

---

## 📈 **KEUNTUNGAN SISTEM OTOMATIS**

### **✅ Untuk HR/Admin:**
- **Tidak perlu manual** proses setiap hari libur
- **Konsisten** dalam pemberian kompensasi
- **Audit trail** lengkap dengan logs
- **Dashboard** untuk monitoring

### **✅ Untuk Karyawan:**
- **Otomatis dapat** kompensasi tanpa perlu claim
- **Transparan** - bisa lihat history di dashboard
- **Fair** - semua level dapat sesuai aturan

### **✅ Untuk Perusahaan:**
- **Compliance** dengan kebijakan HR
- **Cost control** - tracking semua kompensasi
- **Reporting** - data lengkap untuk analisis

---

## 🚨 **TROUBLESHOOTING**

### **1. Cron Job Tidak Berjalan:**
```bash
# Cek apakah cron service aktif
sudo systemctl status cron

# Restart cron service
sudo systemctl restart cron

# Cek log cron
sudo tail -f /var/log/cron
```

### **2. Permission Error:**
```bash
# Berikan permission ke storage
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

### **3. Database Error:**
```bash
# Cek koneksi database
php artisan tinker
>>> DB::connection()->getPdo();

# Cek tabel exists
php artisan migrate:status
```

---

## 📋 **CHECKLIST IMPLEMENTASI**

- [ ] ✅ Buat tabel `holiday_attendance_compensations`
- [ ] ✅ Setup `HolidayAttendanceService`
- [ ] ✅ Buat command `ProcessHolidayAttendance`
- [ ] ✅ Setup `Kernel.php` untuk scheduled jobs
- [ ] ✅ Jalankan `setup_holiday_attendance_cron.sh`
- [ ] ✅ Test manual dengan command
- [ ] ✅ Verifikasi cron job berjalan
- [ ] ✅ Cek log file
- [ ] ✅ Test dengan data real

---

## 🎉 **HASIL AKHIR**

Setelah setup selesai, sistem akan **otomatis**:

1. **Setiap hari jam 6:00 AM dan 11:00 PM**
2. **Cek apakah kemarin hari libur**
3. **Cari karyawan yang masuk kerja**
4. **Beri kompensasi sesuai level:**
   - `nilai_public_holiday = 0` → **Extra Off Day**
   - `nilai_public_holiday > 0` → **Bonus Uang**
5. **Simpan ke database dan log**

**Tidak perlu manual lagi!** 🚀
