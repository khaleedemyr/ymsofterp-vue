# 🕐 Cron Jobs untuk Leave Management System

## 📋 **Cron Jobs yang Perlu Ditambahkan**

Berdasarkan pola cron jobs yang sudah ada di sistem, berikut adalah settingan cron untuk Leave Management:

### **1. 🗓️ Monthly Leave Credit (Kredit Cuti Bulanan)**
```bash
# Setiap tanggal 1, jam 00:00 (tengah malam)
0 0 1 * * cd /home/ymsuperadmin/public_html && php artisan leave:monthly-credit >> storage/logs/leave-monthly-credit.log 2>&1
```

### **2. 🔥 Previous Year Leave Burning (Burning Cuti Tahun Sebelumnya)**
```bash
# Setiap tanggal 1 Maret, jam 00:00
0 0 1 3 * cd /home/ymsuperadmin/public_html && php artisan leave:burn-previous-year >> storage/logs/leave-burning.log 2>&1
```

## 🎯 **Penjelasan Schedule**

### **Monthly Credit (Kredit Bulanan):**
- **Waktu**: `0 0 1 * *` = Setiap tanggal 1, jam 00:00
- **Fungsi**: Memberikan 1 hari cuti ke semua karyawan aktif
- **Log**: `storage/logs/leave-monthly-credit.log`

### **Leave Burning (Burning Cuti):**
- **Waktu**: `0 0 1 3 *` = Setiap tanggal 1 Maret, jam 00:00
- **Fungsi**: Menghapus sisa cuti tahun sebelumnya
- **Log**: `storage/logs/leave-burning.log`

## 🔧 **Cara Setup di cPanel/WHM**

### **Step 1: Akses Cron Jobs**
1. Login ke cPanel
2. Buka **"Cron Jobs"** di bagian **Advanced**
3. Klik **"Add New Cron Job"**

### **Step 2: Tambah Monthly Credit**
```
Minute: 0
Hour: 0
Day: 1
Month: *
Weekday: *
Command: cd /home/ymsuperadmin/public_html && php artisan leave:monthly-credit >> storage/logs/leave-monthly-credit.log 2>&1
```

### **Step 3: Tambah Leave Burning**
```
Minute: 0
Hour: 0
Day: 1
Month: 3
Weekday: *
Command: cd /home/ymsuperadmin/public_html && php artisan leave:burn-previous-year >> storage/logs/leave-burning.log 2>&1
```

## 📊 **Monitoring & Logging**

### **Log Files:**
- **Monthly Credit**: `storage/logs/leave-monthly-credit.log`
- **Leave Burning**: `storage/logs/leave-burning.log`

### **Contoh Log Output:**
```
[2025-01-01 00:00:01] Starting monthly leave credit for 2025-01
[2025-01-01 00:00:02] Processing 265 active users
[2025-01-01 00:00:05] User ID 16 (Agus Cahyadi): +1.00 hari
[2025-01-01 00:00:05] User ID 25 (Rafdi Efdiar): +1.00 hari
...
[2025-01-01 00:00:15] Monthly credit completed. 265 users processed.
```

## ⚠️ **Important Notes**

### **Testing Commands:**
```bash
# Test monthly credit (dry run)
php artisan leave:monthly-credit --year=2025 --month=1

# Test burning (dry run)  
php artisan leave:burn-previous-year --year=2025

# Force execution (skip duplicate check)
php artisan leave:monthly-credit --force
php artisan leave:burn-previous-year --force
```

### **Manual Execution:**
```bash
# Manual monthly credit
cd /home/ymsuperadmin/public_html && php artisan leave:monthly-credit

# Manual burning
cd /home/ymsuperadmin/public_html && php artisan leave:burn-previous-year
```

## 🚨 **Troubleshooting**

### **Common Issues:**
1. **Permission Error**: Pastikan file log bisa ditulis
2. **Path Error**: Pastikan path `/home/ymsuperadmin/public_html` benar
3. **Duplicate Execution**: Gunakan `--force` untuk skip duplicate check

### **Verification:**
```bash
# Cek log files
tail -f storage/logs/leave-monthly-credit.log
tail -f storage/logs/leave-burning.log

# Cek cron jobs
crontab -l
```

## 📅 **Schedule Summary**

| Job | Schedule | Description |
|-----|----------|-------------|
| Monthly Credit | `0 0 1 * *` | Setiap tanggal 1, jam 00:00 |
| Leave Burning | `0 0 1 3 *` | Setiap tanggal 1 Maret, jam 00:00 |

**Total Cron Jobs: 2 jobs untuk Leave Management System**
