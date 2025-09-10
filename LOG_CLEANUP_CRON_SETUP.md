# Setup Cron Job untuk Membersihkan Debug Logs

Dokumentasi ini menjelaskan cara mengatur cron job untuk membersihkan debug logs secara otomatis.

## File yang Dibutuhkan

1. `clear_debug_logs_cron.php` - Script utama untuk membersihkan logs
2. `setup_log_cleanup_cron.sh` - Script setup untuk Linux/Mac
3. `setup_log_cleanup_cron.bat` - Script setup untuk Windows

## Setup untuk Linux/Mac (cPanel/Server)

### 1. Upload File ke Server
```bash
# Upload file ke direktori project
scp clear_debug_logs_cron.php user@server:/path/to/project/
scp setup_log_cleanup_cron.sh user@server:/path/to/project/
```

### 2. Setup Cron Job
```bash
# SSH ke server
ssh user@server

# Masuk ke direktori project
cd /path/to/project

# Buat script executable
chmod +x setup_log_cleanup_cron.sh

# Jalankan setup
./setup_log_cleanup_cron.sh
```

### 3. Manual Setup (Alternatif)
```bash
# Edit crontab
crontab -e

# Tambahkan baris berikut (ganti path sesuai lokasi project)
0 2 * * * cd /path/to/project && php clear_debug_logs_cron.php >> /path/to/project/storage/logs/cron_cleanup.log 2>&1
```

## Setup untuk Windows (Local Development)

### 1. Jalankan Setup Script
```cmd
# Buka Command Prompt as Administrator
# Masuk ke direktori project
cd D:\Gawean\YM\web\ymsofterp

# Jalankan setup script
setup_log_cleanup_cron.bat
```

### 2. Manual Setup (Alternatif)
1. Buka **Task Scheduler** (taskschd.msc)
2. Klik **Create Basic Task**
3. Nama: `LogCleanupTask`
4. Trigger: **Daily** at **2:00 AM**
5. Action: **Start a program**
   - Program: `php`
   - Arguments: `D:\Gawean\YM\web\ymsofterp\clear_debug_logs_cron.php`
   - Start in: `D:\Gawean\YM\web\ymsofterp`

## Setup untuk cPanel

### 1. Via cPanel Cron Jobs
1. Login ke cPanel
2. Buka **Cron Jobs**
3. Tambahkan cron job baru:
   - **Minute**: `0`
   - **Hour**: `2`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: 
     ```bash
     cd /home/username/public_html && php clear_debug_logs_cron.php >> storage/logs/cron_cleanup.log 2>&1
     ```

### 2. Via File Manager
1. Upload `clear_debug_logs_cron.php` ke root directory
2. Set permissions: `644`
3. Setup cron job seperti di atas

## Konfigurasi Cron Job

### Jadwal yang Direkomendasikan

| Jadwal | Deskripsi |
|--------|-----------|
| `0 2 * * *` | Setiap hari jam 2:00 AM (Recommended) |
| `0 */6 * * *` | Setiap 6 jam |
| `0 0 * * 0` | Setiap minggu (Minggu jam 00:00) |
| `0 0 1 * *` | Setiap bulan (tanggal 1 jam 00:00) |

### Contoh Konfigurasi

```bash
# Membersihkan logs setiap hari jam 2:00 AM
0 2 * * * cd /path/to/project && php clear_debug_logs_cron.php >> storage/logs/cron_cleanup.log 2>&1

# Membersihkan logs setiap 6 jam
0 */6 * * * cd /path/to/project && php clear_debug_logs_cron.php >> storage/logs/cron_cleanup.log 2>&1

# Membersihkan logs hanya jika file lebih dari 50MB
0 2 * * * cd /path/to/project && [ $(stat -f%z storage/logs/laravel.log) -gt 52428800 ] && php clear_debug_logs_cron.php >> storage/logs/cron_cleanup.log 2>&1
```

## Monitoring dan Troubleshooting

### 1. Cek Log Cron Job
```bash
# Lihat log cron job
tail -f storage/logs/cron_cleanup.log

# Lihat log sistem cron (Linux)
tail -f /var/log/cron
```

### 2. Test Manual
```bash
# Test script secara manual
php clear_debug_logs_cron.php

# Test dengan output verbose
php clear_debug_logs_cron.php | tee test_output.log
```

### 3. Cek Status Cron Job
```bash
# Linux/Mac
crontab -l

# Windows
schtasks /query /tn "LogCleanupTask"
```

### 4. Troubleshooting

#### Error: Permission Denied
```bash
# Set permission yang benar
chmod +x clear_debug_logs_cron.php
chmod 755 storage/logs/
```

#### Error: PHP Not Found
```bash
# Gunakan full path ke PHP
/usr/bin/php clear_debug_logs_cron.php

# Atau tambahkan ke PATH
export PATH=$PATH:/usr/bin
```

#### Error: File Not Found
```bash
# Pastikan path benar
pwd
ls -la clear_debug_logs_cron.php
```

## Konfigurasi Lanjutan

### 1. Conditional Cleanup (Hanya jika file > 10MB)
```bash
0 2 * * * cd /path/to/project && [ $(stat -f%z storage/logs/laravel.log) -gt 10485760 ] && php clear_debug_logs_cron.php >> storage/logs/cron_cleanup.log 2>&1
```

### 2. Backup Log Sebelum Cleanup
```bash
0 2 * * * cd /path/to/project && cp storage/logs/laravel.log storage/logs/laravel.log.backup.$(date +\%Y\%m\%d) && php clear_debug_logs_cron.php >> storage/logs/cron_cleanup.log 2>&1
```

### 3. Multiple Log Files
```bash
# Cleanup multiple log files
0 2 * * * cd /path/to/project && php clear_debug_logs_cron.php && php clear_other_logs.php >> storage/logs/cron_cleanup.log 2>&1
```

## Keamanan

### 1. File Permissions
```bash
# Set permission yang aman
chmod 644 clear_debug_logs_cron.php
chmod 755 storage/logs/
chmod 644 storage/logs/laravel.log
```

### 2. Path Security
- Jangan expose script ke web
- Gunakan absolute path
- Validasi input jika ada

## Monitoring Performance

### 1. Log Size Monitoring
```bash
# Cek ukuran log file
ls -lh storage/logs/laravel.log

# Monitor ukuran log
watch -n 60 'ls -lh storage/logs/laravel.log'
```

### 2. Cron Job Performance
```bash
# Cek waktu eksekusi
time php clear_debug_logs_cron.php
```

## Kesimpulan

Dengan setup cron job ini, debug logs akan dibersihkan secara otomatis setiap hari, mencegah file log membesar dan menghemat storage space.

**Jadwal yang direkomendasikan**: Setiap hari jam 2:00 AM
**Monitoring**: Cek log di `storage/logs/cron_cleanup.log`
**Testing**: Jalankan `php clear_debug_logs_cron.php` secara manual
