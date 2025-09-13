# Employee Movement Execution - Cron Job Setup

## Overview
Employee movements yang sudah di-approve dan effective date-nya sudah tiba akan dieksekusi secara otomatis setiap hari menggunakan cron job.

## Current Cron Job Configuration

### 1. Laravel Scheduler (app/Console/Kernel.php)
```php
// Execute approved employee movements on their effective date at 8:00 AM
$schedule->command('employee-movements:execute')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/employee-movements-execution.log'));
```

**Penjelasan:**
- **Waktu**: Setiap hari jam 08:00 WIB
- **Command**: `php artisan employee-movements:execute`
- **withoutOverlapping()**: Mencegah command berjalan bersamaan jika masih ada yang sedang berjalan
- **runInBackground()**: Menjalankan di background agar tidak blocking
- **Log**: Output disimpan di `storage/logs/employee-movements-execution.log`

### 2. Server Cron Job Setup

#### Untuk Linux/Ubuntu Server:
```bash
# Edit crontab
crontab -e

# Tambahkan baris berikut:
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

#### Untuk Windows Server (Task Scheduler):
1. Buka **Task Scheduler**
2. Klik **Create Basic Task**
3. **Name**: Laravel Scheduler
4. **Trigger**: Daily
5. **Start**: 00:00:00
6. **Action**: Start a program
7. **Program/script**: `php`
8. **Add arguments**: `artisan schedule:run`
9. **Start in**: `D:\Gawean\YM\web\ymsofterp`

#### Untuk Shared Hosting (cPanel):
```bash
# Tambahkan di cPanel Cron Jobs:
* * * * * /usr/local/bin/php /home/username/public_html/artisan schedule:run
```

## Command Details

### Command: `php artisan employee-movements:execute`

**Fungsi:**
- Mencari employee movements dengan status `approved`
- Memfilter berdasarkan `employment_effective_date` = hari ini
- Mengeksekusi perubahan employee data:
  - Position change
  - Level change  
  - Salary change
  - Division change
  - **Outlet change (id_outlet)**
- Mengupdate status movement menjadi `executed`

**Output Log:**
```
Found 1 employee movements to execute today.
✓ Executed movement for [Employee Name]
Execution completed. Success: 1, Errors: 0
```

## Monitoring & Troubleshooting

### 1. Check Log Files
```bash
# Lihat log execution
tail -f storage/logs/employee-movements-execution.log

# Lihat log Laravel umum
tail -f storage/logs/laravel.log
```

### 2. Manual Execution (Testing)
```bash
# Jalankan manual untuk testing
php artisan employee-movements:execute

# Check status movements
php artisan tinker
>>> App\Models\EmployeeMovement::where('status', 'approved')->get();
```

### 3. Verify Cron Job Running
```bash
# Check if Laravel scheduler is running
php artisan schedule:list

# Test scheduler
php artisan schedule:run
```

## Best Practices

### 1. Time Zone
Pastikan server timezone sudah benar:
```php
// config/app.php
'timezone' => 'Asia/Jakarta',
```

### 2. Log Rotation
Setup log rotation untuk mencegah log file terlalu besar:
```bash
# /etc/logrotate.d/laravel
/path/to/project/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    notifempty
    create 644 www-data www-data
}
```

### 3. Error Handling
Command sudah memiliki error handling:
- Jika ada error, status movement akan diupdate ke `rejected`
- Error details akan dicatat di log file
- Command tidak akan crash jika ada masalah dengan satu movement

### 4. Performance
- Command menggunakan `withoutOverlapping()` untuk mencegah multiple execution
- Menggunakan `runInBackground()` untuk tidak blocking
- Hanya memproses movements yang effective date-nya hari ini

## Status Flow

```
draft → pending → approved → executed
  ↓        ↓         ↓         ↓
rejected  rejected  rejected  (final)
```

## Troubleshooting Common Issues

### 1. Cron Job Tidak Berjalan
```bash
# Check cron service
sudo systemctl status cron

# Check crontab
crontab -l

# Check Laravel scheduler
php artisan schedule:list
```

### 2. Permission Issues
```bash
# Set proper permissions
chmod -R 755 storage/
chown -R www-data:www-data storage/
```

### 3. Database Connection Issues
- Check `.env` database configuration
- Ensure database server is running
- Check network connectivity

### 4. Memory Issues
```bash
# Increase PHP memory limit
php -d memory_limit=512M artisan employee-movements:execute
```

## Contact & Support
Jika ada masalah dengan cron job execution, check:
1. Log files di `storage/logs/`
2. Laravel log di `storage/logs/laravel.log`
3. Server cron log di `/var/log/cron.log`
